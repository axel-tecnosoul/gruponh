<?php

if (!function_exists('get_magic_quotes_runtime')) {
    // FPDF 1.8 still checks this function, removed in PHP 8.
    function get_magic_quotes_runtime(): bool
    {
        return false;
    }
}

final class FacturaVentaARCAPDF extends FPDF
{
    private const RESUMEN_TOP = 158.0;
    private const RESUMEN_BOTTOM = 214.0;
    private const ITEMS_MIN_FONT_SIZE = 4.2;
    private const CONTENT_LEFT = 8.0;
    private const CONTENT_WIDTH = 194.0;

    private array $comprobante;
    private array $emisor;
    private array $receptor;
    private array $detalle;
    private array $alicuotas;
    private array $tributos;
    private string $letra;
    private string $titulo;
    private string $moneda;
    private bool $dolares;
    private bool $localOnly;
    private bool $homologacion;
    private bool $mostrarIVAItem;
    private bool $copiasMultiples;
    private string $pie;
    private ?string $qrFile;
    private string $copia = '';
    private int $copyPageStart = 0;

    public function __construct(array $data)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->comprobante = $data['comprobante'];
        $this->emisor = $data['emisor'];
        $this->receptor = $data['receptor'];
        $this->detalle = $data['detalle'];
        $this->alicuotas = $data['alicuotas'];
        $this->tributos = $data['tributos'];
        $this->letra = $data['letra'];
        $this->titulo = $data['titulo'];
        $this->moneda = $data['moneda'];
        $this->dolares = $data['dolares'];
        $this->localOnly = $data['local_only'];
        $this->homologacion = $data['homologacion'];
        $this->mostrarIVAItem = $data['mostrar_iva_item'];
        $this->copiasMultiples = $data['copias_multiples'];
        $this->pie = $data['pie'];
        $this->qrFile = $data['qr_file'];
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(false);
        $this->AliasNbPages();
    }

    public function generar(): string
    {
        $copies = $this->copiasMultiples
            ? ['ORIGINAL', 'DUPLICADO', 'TRIPLICADO']
            : [''];
        foreach ($copies as $copy) {
            $this->copia = $copy;
            $this->copyPageStart = $this->PageNo() + 1;
            $this->AddPage();
            $y = $this->dibujarEncabezado();
            if (!$this->localOnly && (int)($this->comprobante['concepto'] ?? 1) !== 1 && !empty($this->comprobante['fecha_servicio_desde'])) {
                $y = $this->dibujarPeriodo($y);
            }
            $y = $this->dibujarDatosCliente($y);
            $reference = trim((string)($this->detalle['proyecto'] ?? ''));
            if ($reference !== '') {
                $y = $this->dibujarReferencia($y, $reference);
            }
            $y = $this->dibujarTablaItems($y);
            $this->dibujarTotales();
            $this->dibujarPieFiscal();
        }

        return $this->Output('S');
    }

    public function texto(string $value): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted === false) {
            throw new RuntimeException('No se pudo convertir texto UTF-8 para generar el PDF con FPDF.');
        }
        return $converted;
    }

    public function Footer(): void
    {
    }

    public function Header(): void
    {
    }

    /**
     * Returns the page number relative to the current copy (ORIGINAL/DUPLICADO/TRIPLICADO).
     */
    private function copyPageNo(): int
    {
        return $this->PageNo() - $this->copyPageStart + 1;
    }

    private function normalizarCondicionIva(string $condicion): string
    {
        $trim = trim($condicion);
        if ($trim === '' || strcasecmp($trim, 'Inscripto') === 0 || strcasecmp($trim, 'Responsable Inscripto') === 0) {
            return 'IVA Responsable Inscripto';
        }
        if (strcasecmp($trim, 'Exento') === 0) {
            return 'IVA Exento';
        }
        if (strcasecmp($trim, 'Monotributo') === 0 || strcasecmp($trim, 'Responsable Monotributo') === 0) {
            return 'Responsable Monotributo';
        }
        if (strcasecmp($trim, 'Consumidor Final') === 0) {
            return 'Consumidor Final';
        }
        return $trim;
    }

    private function dibujarEncabezado(): float
    {
        $this->SetDrawColor(0, 0, 0);

        $top = 8.0;
        $copiaLineY = 16.0;
        $letterBoxBottom = 35.0;
        $letterBoxLeft = 96.5;
        $letterBoxRight = 113.5;
        $centerLineX = 105.0;
        $headerBottom = 56.0;

        // Caja exterior principal del encabezado
        $this->Rect(self::CONTENT_LEFT, $top, self::CONTENT_WIDTH, $headerBottom - $top);

        // Línea horizontal debajo de ORIGINAL
        $this->Line(self::CONTENT_LEFT, $copiaLineY, self::CONTENT_LEFT + self::CONTENT_WIDTH, $copiaLineY);

        // Caja de la letra del comprobante en el centro
        $this->Line($letterBoxLeft, $copiaLineY, $letterBoxLeft, $letterBoxBottom);
        $this->Line($letterBoxRight, $copiaLineY, $letterBoxRight, $letterBoxBottom);
        $this->Line($letterBoxLeft, $letterBoxBottom, $letterBoxRight, $letterBoxBottom);

        // Línea vertical que divide emisor y factura debajo de la letra
        $this->Line($centerLineX, $letterBoxBottom, $centerLineX, $headerBottom);

        // Texto de la copia (ORIGINAL / DUPLICADO / TRIPLICADO)
        if ($this->copia !== '') {
            $this->SetFont('Arial', 'B', 11);
            $this->SetXY(self::CONTENT_LEFT, $top + 1.2);
            $this->Cell(self::CONTENT_WIDTH, 5.5, $this->texto(strtoupper($this->copia)), 0, 0, 'C');
        }

        // Letra del comprobante y código
        $this->SetFont('Arial', 'B', 22);
        $this->SetXY($letterBoxLeft, $copiaLineY + 1.5);
        $this->Cell(17, 9, $this->texto($this->letra), 0, 0, 'C');
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetXY($letterBoxLeft, $copiaLineY + 11.5);
        $this->Cell(17, 5, $this->texto('COD. ' . str_pad((string)$this->comprobante['tipo_comprobante'], 2, '0', STR_PAD_LEFT)), 0, 0, 'C');

        // --- LADO IZQUIERDO: EMISOR ---
        $leftColWidth = $letterBoxLeft - self::CONTENT_LEFT; // 88.5 mm
        $this->SetFont('Arial', 'B', 11);
        $this->SetXY(self::CONTENT_LEFT, $copiaLineY + 6);
        $this->Cell($leftColWidth, 5, $this->texto((string)($this->emisor['razon_social'] ?? '')), 0, 0, 'C');

        // Fila 1: Razón Social
        $this->SetXY(self::CONTENT_LEFT + 2, $copiaLineY + 17);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Razón Social: '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto((string)($this->emisor['razon_social'] ?? '')));

        // Fila 2: Domicilio Comercial
        $this->SetXY(self::CONTENT_LEFT + 2, $copiaLineY + 23);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(31, 3.6, $this->texto('Domicilio Comercial: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $domicilioEmisor = (string)($this->emisor['domicilio'] ?? '');
        $this->MultiCell($leftColWidth - 33, 3.6, $this->texto($domicilioEmisor), 0, 'L');

        // Fila 3: Condición frente al IVA
        $this->SetXY(self::CONTENT_LEFT + 2, $headerBottom - 5.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Condición frente al IVA: '));
        $condicionEmisor = $this->normalizarCondicionIva((string)($this->emisor['condicion_iva'] ?? ''));
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto($condicionEmisor));

        // --- LADO DERECHO: FACTURA Y DATOS FISCALES ---
        $rightX = 118.0;
        $this->SetFont('Arial', 'B', 17);
        $this->SetXY($rightX, $copiaLineY + 2.5);
        $this->Cell(80, 7, $this->texto($this->titulo), 0, 0, 'L');

        // Punto de Venta y Comp. Nro (rótulo en negrita, valor regular)
        $pv = str_pad((string)$this->comprobante['punto_venta'], 5, '0', STR_PAD_LEFT);
        $number = str_pad((string)$this->comprobante['numero_comprobante'], 8, '0', STR_PAD_LEFT);
        $this->SetXY($rightX, $copiaLineY + 11.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Punto de Venta: '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto($pv . '   '));
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Comp. Nro: '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto($number));

        // Fecha de Emisión (rótulo en negrita, valor regular)
        $this->SetXY($rightX, $copiaLineY + 16.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Fecha de Emisión: '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto($this->fecha($this->comprobante['fecha_comprobante'])));

        // CUIT
        $this->SetXY($rightX, $copiaLineY + 23.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(3.6, $this->texto('CUIT:  '));
        $this->SetFont('Arial', '', 8);
        $this->Write(3.6, $this->texto((string)($this->emisor['cuit'] ?? '')));

        // Ingresos Brutos
        $this->SetXY($rightX, $copiaLineY + 27.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(3.6, $this->texto('Ingresos Brutos:  '));
        $this->SetFont('Arial', '', 8);
        $this->Write(3.6, $this->texto((string)($this->emisor['ingresos_brutos'] ?? '')));

        // Fecha de Inicio de Actividades
        $this->SetXY($rightX, $copiaLineY + 31.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(3.6, $this->texto('Fecha de Inicio de Actividades:  '));
        $this->SetFont('Arial', '', 8);
        $this->Write(3.6, $this->texto($this->fecha($this->emisor['fecha_inicio_actividades'] ?? '')));

        return $headerBottom + 2.0;
    }

    private function dibujarDatosCliente(float $y): float
    {
        $this->SetDrawColor(0, 0, 0);
        $boxHeight = 24.0;
        $this->Rect(self::CONTENT_LEFT, $y, self::CONTENT_WIDTH, $boxHeight);

        $document = ($this->receptor['cuit'] ?? '') ?: ($this->receptor['numero_documento'] ?? '');
        $condicionReceptor = $this->normalizarCondicionIva((string)($this->receptor['condicion_iva'] ?? ''));
        $razonSocialReceptor = (string)($this->receptor['razon_social'] ?? '');
        $domicilioReceptor = (string)($this->receptor['domicilio'] ?? '');
        $condicionVenta = (string)($this->detalle['condicion_venta'] ?? '');
        $remito = trim((string)($this->detalle['remito'] ?? ''));

        // Fila 1: CUIT | Apellido y Nombre / Razón Social
        $this->SetXY(self::CONTENT_LEFT + 2, $y + 2.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('CUIT:  '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto($document ?: '-'));

        $this->SetXY(70, $y + 2.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Apellido y Nombre / Razón Social: '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto($razonSocialReceptor));

        // Fila 2: Condición frente al IVA | Domicilio Comercial
        $this->SetXY(self::CONTENT_LEFT + 2, $y + 9.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Condición frente al IVA:  '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto($condicionReceptor ?: '-'));

        $this->SetXY(90, $y + 9.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(31, 3.6, $this->texto('Domicilio Comercial: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(self::CONTENT_LEFT + self::CONTENT_WIDTH - 121, 3.6, $this->texto($domicilioReceptor), 0, 'L');

        // Fila 3: Condición de venta | Remito
        $this->SetXY(self::CONTENT_LEFT + 2, $y + 17.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Condición de venta:  '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto($condicionVenta ?: '-'));

        $this->SetXY(98, $y + 17.5);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Remito: '));
        $this->SetFont('Arial', '', 8);
        $this->Write(4, $this->texto($remito ?: '-'));

        return $y + $boxHeight + 2.0;
    }

    private function dibujarPeriodo(float $y): float
    {
        $this->SetDrawColor(0, 0, 0);
        $this->Rect(self::CONTENT_LEFT, $y, self::CONTENT_WIDTH, 8);
        $this->SetXY(self::CONTENT_LEFT + 2, $y + 2);
        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Período facturado desde: '));
        $this->SetFont('Arial', '', 8);
        $fechaDesde = $this->fecha($this->comprobante['fecha_servicio_desde'] ?? $this->comprobante['fecha_comprobante']);
        $this->Write(4, $this->texto($fechaDesde . '   '));

        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Hasta: '));
        $this->SetFont('Arial', '', 8);
        $fechaHasta = $this->fecha($this->comprobante['fecha_servicio_hasta'] ?? $this->comprobante['fecha_comprobante']);
        $this->Write(4, $this->texto($fechaHasta . '   '));

        $this->SetFont('Arial', 'B', 8);
        $this->Write(4, $this->texto('Fecha de Vto. para el pago: '));
        $this->SetFont('Arial', '', 8);
        $fechaVto = $this->fecha($this->comprobante['fecha_vencimiento_pago'] ?? $this->comprobante['fecha_comprobante']);
        $this->Write(4, $this->texto($fechaVto));

        return $y + 10.0;
    }

    private function dibujarReferencia(float $y, string $reference): float
    {
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(self::CONTENT_LEFT, $y);
        $this->MultiCell(self::CONTENT_WIDTH, 6, $this->texto('Referencia Comercial: ' . $reference), 1, 'L');
        return $this->GetY();
    }

    private function columnas(): array
    {
        if ($this->mostrarIVAItem) {
            return [
                ['w' => 19, 'label' => 'N.C.M.'],
                ['w' => 57, 'label' => 'Producto'],
                ['w' => 19, 'label' => 'Cantidad'],
                ['w' => 14, 'label' => 'U. medida'],
                ['w' => 24, 'label' => 'Precio Unit. (' . $this->moneda . ')'],
                ['w' => 11, 'label' => '% Bonif'],
                ['w' => 22, 'label' => 'Subtotal (' . $this->moneda . ')'],
                ['w' => 12, 'label' => 'Alicuota IVA'],
                ['w' => 16, 'label' => 'Subtotal c/IVA (' . $this->moneda . ')'],
            ];
        }
        return [
            ['w' => 19, 'label' => 'N.C.M.'],
            ['w' => 57, 'label' => 'Producto'],
            ['w' => 19, 'label' => 'Cantidad'],
            ['w' => 14, 'label' => 'U. medida'],
            ['w' => 24, 'label' => 'Precio Unit. (' . $this->moneda . ')'],
            ['w' => 11, 'label' => '% Bonif'],
            ['w' => 28, 'label' => 'Imp. Bonif. (' . $this->moneda . ')'],
            ['w' => 22, 'label' => 'Subtotal (' . $this->moneda . ')'],
        ];
    }

    private function dibujarTablaItems(float $y): float
    {
        $columns = $this->columnas();
        $itemRows = [];
        foreach ($this->detalle['items'] as $item) {
            $quantity = (float)$item['cantidad'];
            $base = (float)$item['base_imponible'];
            $discount = (float)($item['descuento_porcentaje'] ?? 0);
            $unitPrice = (float)($item['precio_unitario'] ?? 0);
            $rate = (float)($item['alicuota_iva'] ?? 0);
            $discountAmount = max(0, $unitPrice * $quantity - $base);
            if ($unitPrice <= 0 && $quantity > 0) {
                $discountFactor = 1 - $discount / 100;
                $unitPrice = $discountFactor > 0 ? $base / ($quantity * $discountFactor) : 0;
            }
            $project = trim((string)($item['proyecto'] ?? $this->detalle['proyecto'] ?? ''));
            if ($project !== '') {
                $project = str_replace('_', '-', $project);
            }
            $description = trim((string)($item['descripcion'] ?? ''));
            $itemCode = trim((string)($item['codigo'] ?? ''));
            if ($itemCode !== '') {
                $description = $itemCode . ' - ' . $description;
            }
            if ($project !== '') {
                $description = $project . ' - ' . $description;
            }
            $ncm = (string)($item['ncm'] ?? $this->detalle['ncm'] ?? '73.08.20.00');
            if ($ncm === '73.08.20') {
                $ncm .= '.00';
            }
            $values = [
                $ncm,
                $description,
                number_format($quantity, 2, ',', ''),
                'unidades',
                $this->dineroPlano($unitPrice),
                $this->dineroPlano($discount),
            ];
            if ($this->mostrarIVAItem) {
                $values[] = $this->dineroPlano($base);
                $values[] = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
                $values[] = $this->dineroPlano($base + round($base * $rate / 100, 2));
            } else {
                $values[] = $this->dineroPlano($discountAmount);
                $values[] = $this->dineroPlano($base);
            }
            $itemRows[] = $values;
        }

        $index = 0;
        while ($index < count($itemRows)) {
            $y = $this->encabezadoItems($y, $columns);
            $finalSpace = self::RESUMEN_TOP - $y;
            $remainingMinHeight = $this->alturaFilas($itemRows, $index, count($itemRows), $columns, self::ITEMS_MIN_FONT_SIZE);
            if ($remainingMinHeight <= $finalSpace) {
                $fontSize = $this->buscarTamanoFilas($itemRows, $index, count($itemRows), $columns, $finalSpace);
                $this->dibujarFilas($itemRows, $index, count($itemRows), $columns, $y, $fontSize);
                return $y;
            }

            $continuationSpace = $finalSpace;
            $end = $this->filasQueEntran($itemRows, $index, $columns, $continuationSpace);
            if ($end === $index) {
                $end++;
            }
            $this->dibujarFilas($itemRows, $index, $end, $columns, $y, self::ITEMS_MIN_FONT_SIZE);
            $index = $end;
            if ($index < count($itemRows)) {
                $this->AddPage();
                $y = $this->dibujarEncabezado();
            }
        }
        return $y;
    }

    private function encabezadoItems(float $y, array $columns): float
    {
        $x = self::CONTENT_LEFT;
        $this->SetFillColor(218, 218, 218);
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 6.2);
        $lineHeight = 2.7;
        $labels = [];
        $maxLines = 1;
        foreach ($columns as $column) {
            $label = $this->texto($column['label']);
            $labels[] = $label;
            $maxLines = max($maxLines, $this->NbLines($column['w'], $label));
        }
        $rowHeight = max(6.5, $maxLines * $lineHeight + 1.0);
        foreach ($columns as $i => $column) {
            $this->Rect($x, $y, $column['w'], $rowHeight, 'DF');
            $lines = $this->NbLines($column['w'], $labels[$i]);
            $textY = $y + max(0.0, ($rowHeight - $lines * $lineHeight) / 2);
            $this->SetXY($x, $textY);
            $this->MultiCell($column['w'], $lineHeight, $labels[$i], 0, 'C');
            $x += $column['w'];
        }
        $this->SetFillColor(255, 255, 255);
        return $y + $rowHeight;
    }

    private function alturaFilas(array $rows, int $from, int $to, array $columns, float $fontSize): float
    {
        $lineHeight = 3.4 * $fontSize / 7.2;
        $this->SetFont('Arial', '', $fontSize);
        $height = 0.0;
        for ($index = $from; $index < $to; $index++) {
            $lines = $this->NbLines($columns[1]['w'] - 1.4, $this->texto($rows[$index][1]));
            $height += max(3.4, $lines * $lineHeight + 0.8);
        }
        return $height;
    }

    private function buscarTamanoFilas(array $rows, int $from, int $to, array $columns, float $available): float
    {
        $low = self::ITEMS_MIN_FONT_SIZE;
        $high = 7.2;
        for ($iteration = 0; $iteration < 12; $iteration++) {
            $middle = ($low + $high) / 2;
            if ($this->alturaFilas($rows, $from, $to, $columns, $middle) <= $available) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }
        return $low;
    }

    private function filasQueEntran(array $rows, int $from, array $columns, float $available): int
    {
        $end = $from;
        $height = 0.0;
        $this->SetFont('Arial', '', self::ITEMS_MIN_FONT_SIZE);
        while ($end < count($rows)) {
            $lines = $this->NbLines($columns[1]['w'] - 1.4, $this->texto($rows[$end][1]));
            $rowHeight = max(3.4, $lines * (3.4 * self::ITEMS_MIN_FONT_SIZE / 7.2) + 0.8);
            if ($height + $rowHeight > $available && $end > $from) {
                break;
            }
            $height += $rowHeight;
            $end++;
        }
        return $end;
    }

    private function dibujarFilas(
        array $rows,
        int $from,
        int $to,
        array $columns,
        float $y,
        float $fontSize
    ): void {
        $lineHeight = 3.4 * $fontSize / 7.2;
        $this->SetDrawColor(0, 0, 0);
        for ($index = $from; $index < $to; $index++) {
            $values = $rows[$index];
            $this->SetFont('Arial', '', $fontSize);
            $lines = $this->NbLines($columns[1]['w'] - 1.4, $this->texto($values[1]));
            $rowHeight = max(3.4, $lines * $lineHeight + 0.8);
            $x = 8;
            foreach ($columns as $columnIndex => $column) {
                $this->SetFont('Arial', '', $columnIndex === 1 ? $fontSize : min(6.5, $fontSize));
                $centered = in_array($columnIndex, [0, 2, 3], true)
                    || ($this->mostrarIVAItem && $columnIndex === 7);
                $align = $centered
                    ? 'C'
                    : ($columnIndex === 1 ? 'L' : 'R');
                $this->SetXY($x + 0.7, $y + 0.5);
                $this->MultiCell($column['w'] - 1.4, $lineHeight, $this->texto($values[$columnIndex]), 0, $align);
                $x += $column['w'];
            }
            $y += $rowHeight;
        }
    }

    private function dibujarTotales(): void
    {
        $ivaAmounts = [6 => 0, 5 => 0, 4 => 0, 8 => 0, 9 => 0, 3 => 0];
        foreach ($this->alicuotas as $alicuota) {
            $ivaAmounts[(int)$alicuota['codigo_arca']] = (float)$alicuota['importe'];
        }
        $ivaLabels = [6 => '27%', 5 => '21%', 4 => '10.5%', 8 => '5%', 9 => '2.5%', 3 => '0%'];
        $rows = [['Importe Neto Gravado', $this->comprobante['importe_neto']]];
        foreach ([
            'Importe No Gravado' => 'importe_no_gravado',
            'Importe Exento' => 'importe_exento',
        ] as $label => $key) {
            if ((float)$this->comprobante[$key] !== 0.0) {
                $rows[] = [$label, $this->comprobante[$key]];
            }
        }
        if ($this->mostrarIVAItem) {
            foreach ($ivaLabels as $code => $label) {
                $rows[] = ['IVA ' . $label, $ivaAmounts[$code]];
            }
        }
        foreach ($this->tributos as $tax) {
            $rows[] = [(string)$tax['descripcion'], $tax['importe']];
        }
        $rows[] = ['Importe Otros Tributos', $this->comprobante['importe_tributos']];
        $rows[] = ['Importe Total', $this->comprobante['importe_total'], true];
        $taxRows = count($rows);
        $panelTop = self::RESUMEN_TOP;
        $panelBottom = self::RESUMEN_BOTTOM + ($this->dolares ? 11.0 : 0.0);
        $this->SetDrawColor(0, 0, 0);
        $this->Rect(self::CONTENT_LEFT, $panelTop, self::CONTENT_WIDTH, $panelBottom - $panelTop);
        $currency = $this->dolares
            ? 'Moneda: USD - Dólar Estadounidense'
            : 'Moneda: ARS - Pesos Argentinos';
        $this->SetFont('Arial', 'B', 7);
        $this->SetXY(self::CONTENT_LEFT, $panelTop + 2);
        $this->Cell(self::CONTENT_WIDTH - 2, 4, $this->texto($currency), 0, 0, 'R');
        $this->Line(140, $panelTop + 6.5, self::CONTENT_LEFT + self::CONTENT_WIDTH - 2, $panelTop + 6.5);

        // Importe Otros Tributos a la izquierda
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(55, $panelTop + 8);
        $this->Cell(53, 5, $this->texto('Importe Otros Tributos: ' . $this->moneda), 0, 0, 'R');
        $this->SetX(109);
        $this->Cell(24, 5, $this->texto($this->dineroPlano((float)$this->comprobante['importe_tributos'])), 0, 0, 'R');

        $conversionHeight = $this->dolares ? 9.0 : 0.0;
        $conversionY = $panelBottom - $conversionHeight - 1.0;
        $rowsBottom = $this->dolares ? $conversionY - 1.0 : $panelBottom;
        $availableRowsHeight = $rowsBottom - ($panelTop + 15);

        // En Imagen 1 la lista de totales de la derecha incluye Importe Otros Tributos
        $rightRows = $rows;
        $rightRowCount = count($rightRows);

        $rowHeight = min(5.5, $availableRowsHeight / max(1, $rightRowCount));
        $fontSize = min(7.2, max(4.4, $rowHeight * 1.35));
        $y = $rowsBottom - ($rightRowCount * $rowHeight) - 1;
        $amountX = 178;
        foreach ($rightRows as $index => $row) {
            $isTotal = !empty($row[2]);
            $label = (string)$row[0];
            if ($this->dolares) {
                $label .= ': USD';
            }
            $totalFontSize = $isTotal ? min(9.0, $fontSize + 1.5) : $fontSize;
            // En Imagen 1 TODAS las etiquetas de la derecha están en BOLD
            $this->SetFont('Arial', 'B', $totalFontSize);
            $this->SetXY(129, $y);
            $this->Cell(48, $rowHeight, $this->texto($label), 0, 0, 'R');
            $this->SetX($amountX);
            // El importe total en BOLD, los demás en regular
            $this->SetFont('Arial', $isTotal ? 'B' : '', $totalFontSize);
            $this->Cell(24, $rowHeight, $this->texto($this->dineroPlano((float)$row[1])), 0, 1, 'R');
            $y += $rowHeight;
        }

        $rate = (float)$this->comprobante['cotizacion'];
        if ($this->dolares) {
            $totalPesos = round((float)$this->comprobante['importe_total'] * $rate, 2);
            $this->Rect(self::CONTENT_LEFT, $conversionY, self::CONTENT_WIDTH, $conversionHeight);
            $this->SetFont('Arial', '', 6.5);
            $this->SetXY(self::CONTENT_LEFT + 2, $conversionY + 1);
            $this->Cell(
                self::CONTENT_WIDTH - 4,
                3.5,
                $this->texto(
                    'El total de este comprobante expresado en moneda de curso legal - Pesos Argentinos - '
                    . 'considerándose un tipo de cambio'
                ),
                0,
                0,
                'C'
            );
            $this->SetXY(self::CONTENT_LEFT + 4, $conversionY + 4.5);
            $this->Cell(
                self::CONTENT_WIDTH - 48,
                3.5,
                $this->texto('consignado de ' . number_format($rate, 6, '.', '') . ' asciende a: $'),
                0,
                0,
                'R'
            );
            $this->SetFont('Arial', 'B', 10.5);
            $this->SetXY(self::CONTENT_LEFT + self::CONTENT_WIDTH - 44, $conversionY + 4.5);
            $this->Cell(
                40,
                3.5,
                $this->texto($this->dinero($totalPesos)),
                0,
                0,
                'R'
            );
        }
        if (!$this->localOnly || $this->dolares) {
            $this->SetFont('Arial', 'I', 7);
            $totalPesos = (float)$this->comprobante['importe_total'] * (float)$this->comprobante['cotizacion'];
            $taxText = '"A los fines impositivos Importe Neto $ '
                . $this->dinero((float)$this->comprobante['importe_neto'] * (float)$this->comprobante['cotizacion'])
                . ', IVA $ ' . $this->dinero((float)$this->comprobante['importe_iva'] * (float)$this->comprobante['cotizacion'])
                . ' y el Total $ ' . $this->dinero($totalPesos) . '."';
            $taxY = $this->dolares ? 226 : 216;
            $this->SetXY(self::CONTENT_LEFT, $taxY);
            $this->Cell(self::CONTENT_WIDTH, 9, $this->texto($taxText), 1, 0, 'C');
        }
        // Observaciones: solo se dibuja si existen notas comerciales
        $notes = trim((string)($this->detalle['observaciones'] ?? ''));
        if ($notes !== '') {
            $notesY = $this->dolares ? 236 : 226;
            $noteText = '"' . $notes . '"';
            $noteFontSize = $this->ajustarFuenteEnLineas($this->texto($noteText), self::CONTENT_WIDTH - 4, 7.5, 3);
            $this->SetFont('Arial', 'I', $noteFontSize);
            $this->SetXY(self::CONTENT_LEFT, $notesY);
            $this->MultiCell(self::CONTENT_WIDTH, 4.5, $this->texto($noteText), 1, 'C');
        }

        if ($this->localOnly) {
            $this->SetFont('Arial', 'B', 7);
            $this->SetXY(self::CONTENT_LEFT, 260);
            $this->Cell(self::CONTENT_WIDTH, 5, $this->texto('Documento interno. Sin autorización ARCA ni validez fiscal.'), 0, 0, 'C');
        } else {
            $this->dibujarPieAutorizacion();
        }
    }

    private function dibujarPieAutorizacion(): void
    {
        $this->SetDrawColor(0, 0, 0);

        if ($this->qrFile !== null && is_file($this->qrFile) && filesize($this->qrFile) > 0) {
            $this->Image($this->qrFile, 8, 248, 26, 26, 'PNG');
        }

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 16);
        $this->SetXY(38, 249);
        $this->Cell(48, 7, $this->texto('ARCA'), 0, 0, 'L');
        $this->SetFont('Arial', 'B', 5.5);
        $this->SetXY(38, 256);
        $this->MultiCell(48, 2.6, $this->texto('AGENCIA DE RECAUDACIÓN Y CONTROL ADUANERO'), 0, 'L');
        $this->SetFont('Arial', 'BI', 7.5);
        $this->SetXY(38, 264);
        $this->Cell(55, 4, $this->texto('Comprobante Autorizado'), 0, 0, 'L');
        $this->SetFont('Arial', 'I', 5.5);
        $this->SetXY(38, 269);
        $this->Cell(58, 2.8, $this->texto('Esta Agencia no se responsabiliza por los'), 0, 2, 'L');
        $this->Cell(58, 2.8, $this->texto('datos ingresados en el detalle de la operación'), 0, 0, 'L');

        // Paginación por copia (Pág 1/1 para cada ejemplar)
        $copyPage = $this->copyPageNo();
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY(85, 245);
        $this->Cell(40, 5, $this->texto('Pág. ' . $copyPage . '/' . $copyPage), 0, 0, 'C');

        $rightMargin = self::CONTENT_LEFT + self::CONTENT_WIDTH; // 202

        // CAE y Vencimiento a la derecha (espaciado exacto y alineado al margen derecho)
        $caeVal = (string)$this->comprobante['cae'];
        $this->SetFont('Arial', '', 9);
        $wValCae = $this->GetStringWidth($caeVal);
        $this->SetFont('Arial', 'B', 9);
        $wLblCae = $this->GetStringWidth('CAE Nº: ');
        $totalCaeW = $wLblCae + $wValCae;
        $this->SetXY($rightMargin - $totalCaeW, 250);
        $this->Cell($wLblCae, 4.5, $this->texto('CAE Nº: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell($wValCae, 4.5, $this->texto($caeVal), 0, 0, 'L');

        $vtoVal = $this->fecha($this->comprobante['fecha_vencimiento_cae']);
        $this->SetFont('Arial', '', 8.5);
        $wValVto = $this->GetStringWidth($vtoVal);
        $this->SetFont('Arial', 'B', 8.5);
        $wLblVto = $this->GetStringWidth('Fecha de Vto. de CAE: ');
        $totalVtoW = $wLblVto + $wValVto;
        $this->SetXY($rightMargin - $totalVtoW, 255.5);
        $this->Cell($wLblVto, 4.5, $this->texto('Fecha de Vto. de CAE: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 8.5);
        $this->Cell($wValVto, 4.5, $this->texto($vtoVal), 0, 0, 'L');

        // Declaración jurada
        $declaration = trim((string)($this->detalle['declaracion_jurada'] ?? ''));
        if ($declaration === '' && $this->dolares) {
            $declaration = 'EL ADQUIRENTE ASUME EL COMPROMISO, EN CARÁCTER DE DECLARACION JURADA, DE NO EXPORTAR EL BIEN DURANTE EL PLAZO DE TRES 3 AÑOS CONTADOS A PARTIR DE SU ADQUISICION';
        }
        if ($declaration !== '') {
            $this->SetFont('Arial', 'B', 5.8);
            $this->SetXY(97, 262);
            $this->MultiCell(105, 2.7, $this->texto($declaration), 0, 'R');
        }
    }

    private function dibujarPieFiscal(): void
    {
        // Limpio sin marcas de homologacion para coincidir con el comprobante de referencia
    }

    private function campo(string $label, $value, float $x, float $y, float $width): void
    {
        $this->SetFont('Arial', 'B', 7);
        $this->SetXY($x, $y);
        $this->Cell(30, 4, $this->texto($label . ':'));
        $this->SetFont('Arial', '', 7);
        $this->SetXY($x + 30, $y);
        $this->MultiCell($width - 30, 4, $this->texto((string)$value), 0, 'L');
    }

    private function ajustarFuenteEnLineas(string $text, float $width, float $fontSize, int $maxLines): float
    {
        $minimum = 4.5;
        $this->SetFont('Arial', '', $fontSize);
        if ($this->NbLines($width, $text) <= $maxLines) {
            return $fontSize;
        }
        $low = $minimum;
        $high = $fontSize;
        for ($iteration = 0; $iteration < 12; $iteration++) {
            $middle = ($low + $high) / 2;
            $this->SetFont('Arial', '', $middle);
            if ($this->NbLines($width, $text) <= $maxLines) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }
        return $low;
    }

    private function fecha($value): string
    {
        if (empty($value)) {
            return '';
        }
        $date = DateTime::createFromFormat('!Y-m-d', (string)$value);
        return $date ? $date->format('d/m/Y') : (string)$value;
    }

    private function dinero($value): string
    {
        return number_format((float)$value, 2, ',', '.');
    }

    private function dineroPlano($value): string
    {
        return number_format((float)$value, 2, ',', '');
    }

    private function NbLines(float $width, string $text): int
    {
        $cw = &$this->CurrentFont['cw'];
        if ($width == 0) {
            $width = $this->w - $this->rMargin - $this->x;
        }
        $maxWidth = ($width - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $textLength = strlen($text);
        if ($textLength > 0 && $text[$textLength - 1] === "\n") {
            $textLength--;
        }
        $separator = -1;
        $i = 0;
        $lineStart = 0;
        $lineWidth = 0;
        $lines = 1;
        while ($i < $textLength) {
            $character = $text[$i];
            if ($character === "\n") {
                $i++;
                $separator = -1;
                $lineStart = $i;
                $lineWidth = 0;
                $lines++;
                continue;
            }
            if ($character === ' ') {
                $separator = $i;
            }
            $lineWidth += $cw[$character] ?? 0;
            if ($lineWidth > $maxWidth) {
                if ($separator === -1) {
                    if ($i === $lineStart) {
                        $i++;
                    }
                } else {
                    $i = $separator + 1;
                }
                $separator = -1;
                $lineStart = $i;
                $lineWidth = 0;
                $lines++;
            } else {
                $i++;
            }
        }
        return $lines;
    }
}
