<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/FacturacionARCA.php';
require_once __DIR__ . '/vendor/autoload.php';

$idFactura = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($idFactura === null && isset($_GET['id'])) {
    $idFactura = filter_var($_GET['id'], FILTER_VALIDATE_INT);
}
if (!$idFactura || $idFactura < 1) {
    http_response_code(400);
    exit('Factura inválida.');
}

$pdo = Database::connect();
$comprobante = null;
$localOnly = true;
if (FacturacionARCA::habilitada()) {
    $q = $pdo->prepare(
        "SELECT * FROM comprobantes_arca
         WHERE id_factura_venta = ? AND estado = 'autorizado'
         ORDER BY id DESC LIMIT 1"
    );
    $q->execute([$idFactura]);
    $comprobante = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    $localOnly = !$comprobante;
}

if (!$localOnly) {
    $q = $pdo->prepare(
        'SELECT codigo_arca, base_imponible, importe
         FROM comprobantes_arca_iva WHERE id_comprobante_arca = ? ORDER BY codigo_arca'
    );
    $q->execute([$comprobante['id']]);
    $alicuotas = $q->fetchAll(PDO::FETCH_ASSOC);
    $q = $pdo->prepare(
        'SELECT descripcion, base_imponible, alicuota, importe
         FROM comprobantes_arca_tributos WHERE id_comprobante_arca = ? ORDER BY id'
    );
    $q->execute([$comprobante['id']]);
    $tributos = $q->fetchAll(PDO::FETCH_ASSOC);
    $emisor = json_decode((string)$comprobante['datos_emisor'], true);
    $receptor = json_decode((string)$comprobante['datos_receptor'], true);
    $detalle = json_decode((string)$comprobante['detalle_comprobante'], true);
    if (!is_array($emisor) || !is_array($receptor) || !is_array($detalle) || empty($detalle['items'])) {
        Database::disconnect();
        http_response_code(500);
        exit('El comprobante autorizado no contiene una copia fiscal histórica válida.');
    }
    $number = (int)$comprobante['numero_comprobante'];
    $footerText = !empty($comprobante['homologacion']) ? 'HOMOLOGACIÓN - SIN VALIDEZ FISCAL' : 'Comprobante autorizado por ARCA';
} else {
    $q = $pdo->prepare(
        "SELECT fv.*, tc.id AS id_tipo_comprobante, tc.tipo AS tipo_comprobante, lc.letra, m.moneda,
                CASE WHEN p.id IS NULL THEN '' ELSE CONCAT(
                    COALESCE(s.nro_sitio, ''), '_',
                    COALESCE(s.nro_subsitio, ''), '_',
                    COALESCE(p.nro, '')
                ) END AS proyecto_codigo,
                e.empresa AS emisor_razon_social, e.cuit AS emisor_cuit,
                e.direccion AS emisor_direccion, e.email AS emisor_email,
                e.iibb AS emisor_iibb, e.fecha_inicio_actividades AS emisor_inicio,
                ec.condicion_iva AS emisor_condicion_iva, fp.forma_pago AS condicion_venta,
                c.razon_social AS receptor_razon_social, c.cuit AS receptor_cuit,
                c.direccion AS receptor_direccion, c.email AS receptor_email,
                rc.condicion_iva AS receptor_condicion_iva
         FROM facturas_venta fv
         INNER JOIN tipos_comprobante tc ON tc.id = fv.id_tipo_comprobante
         INNER JOIN letras_comprobante lc ON lc.id = fv.id_letra_comprobante
         INNER JOIN monedas m ON m.id = fv.id_moneda
         INNER JOIN empresas e ON e.id = fv.id_empresa
         LEFT JOIN proyectos p ON p.id = fv.id_proyecto
         LEFT JOIN sitios s ON s.id = p.id_sitio
         LEFT JOIN condiciones_iva ec ON ec.id = e.id_condicion_iva
         LEFT JOIN formas_pago fp ON fp.id = fv.id_condicion_pago
         INNER JOIN cuentas c ON c.id = fv.id_cuenta_destino
         LEFT JOIN condiciones_iva rc ON rc.id = c.id_condicion_iva
         WHERE fv.id = ? AND fv.id_estado = 3"
    );
    $q->execute([$idFactura]);
    $invoice = $q->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) {
        Database::disconnect();
        http_response_code(404);
        exit('Solo se pueden imprimir facturas definitivas.');
    }
    $q = $pdo->prepare(
        'SELECT d.id, d.cantidad, d.precio, d.subtotal, d.descripcion,
                COALESCE(NULLIF(d.porcentaje_descuento, 0), d.porc_descuento, 0) AS descuento_porcentaje,
                cc.codigo AS concepto_codigo, cc.descripcion AS concepto_text,
                COALESCE(ti.tasa, 21) AS tasa_iva
         FROM facturas_venta_detalle d
         INNER JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
         LEFT JOIN tipos_iva ti ON ti.id = cc.id_alicuota_iva
         WHERE d.id_factura_venta = ? ORDER BY d.id'
    );
    $q->execute([$idFactura]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        Database::disconnect();
        http_response_code(404);
        exit('La factura definitiva no tiene renglones para imprimir.');
    }
    $items = [];
    $bases = [];
    foreach ($rows as $row) {
        $rate = (float)$row['tasa_iva'];
        $base = (float)$row['subtotal'];
        $bases[(string)$rate] = ($bases[(string)$rate] ?? 0) + $base;
        $items[] = [
            'codigo' => (string)$row['concepto_codigo'],
            'ncm' => '73.08.20',
            'proyecto' => (string)($invoice['proyecto_codigo'] ?? ''),
            'descripcion' => (string)($row['descripcion'] ?: $row['concepto_text']),
            'cantidad' => (float)$row['cantidad'],
            'precio_unitario' => (float)$row['precio'],
            'descuento_porcentaje' => (float)$row['descuento_porcentaje'],
            'base_imponible' => $base,
            'alicuota_iva' => in_array($invoice['letra'], ['C', 'E'], true) ? 0 : $rate,
        ];
    }
    $ivaCodeByRate = ['0' => 3, '2.5' => 9, '5' => 8, '10.5' => 4, '21' => 5, '27' => 6];
    $alicuotas = [];
    foreach ($bases as $rate => $base) {
        $key = rtrim(rtrim(number_format((float)$rate, 2, '.', ''), '0'), '.');
        if (isset($ivaCodeByRate[$key])) {
            $alicuotas[] = [
                'codigo_arca' => $ivaCodeByRate[$key],
                'importe' => round($base * (float)$rate / 100, 2),
            ];
        }
    }
    $q = $pdo->prepare(
        'SELECT COALESCE(regimen_text, codigo, articulo, "Retención") AS descripcion, monto AS importe
         FROM facturas_venta_retenciones WHERE id_factura_venta = ?'
    );
    $q->execute([$idFactura]);
    $tributos = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tributos as &$tax) {
        $tax['importe'] = (float)$tax['importe'];
    }
    unset($tax);

    $emisor = [
        'razon_social' => $invoice['emisor_razon_social'],
        'cuit' => $invoice['emisor_cuit'],
        'domicilio' => $invoice['emisor_direccion'],
        'email' => $invoice['emisor_email'],
        'ingresos_brutos' => $invoice['emisor_iibb'],
        'condicion_iva' => $invoice['emisor_condicion_iva'],
        'fecha_inicio_actividades' => $invoice['emisor_inicio'],
    ];
    $receptor = [
        'razon_social' => $invoice['receptor_razon_social'],
        'cuit' => $invoice['receptor_cuit'],
        'numero_documento' => $invoice['receptor_cuit'],
        'domicilio' => $invoice['receptor_direccion'],
        'condicion_iva' => $invoice['receptor_condicion_iva'],
    ];
    $detalle = [
        'letra' => $invoice['letra'],
        'tipo_comprobante_nombre' => $invoice['tipo_comprobante'],
        'condicion_venta' => (string)($invoice['condicion_venta'] ?? ''),
        'ncm' => '73.08.20',
        'proyecto' => (string)($invoice['proyecto_codigo'] ?? ''),
        'observaciones' => $invoice['observaciones'],
        'items' => $items,
    ];
    $numberParts = explode('-', (string)$invoice['numero'], 2);
    $number = (int)($numberParts[1] ?? 0);
    $currency = (string)$invoice['moneda'];
    $exchangeRate = (float)$invoice['cotizacion'];
    $invoiceIsDollar = function_exists('esMonedaDolar')
        ? esMonedaDolar($currency)
        : preg_match('/(?:usd|u\\$s|u\\$d|d[oó]lar)/iu', $currency) === 1;
    if (!$invoiceIsDollar) {
        $exchangeRate = 1;
    }
    $comprobante = [
        'id' => $idFactura,
        'punto_venta' => (int)($numberParts[0] ?? 0),
        'numero_comprobante' => $number,
        'tipo_comprobante' => [
            1 => ['A' => 1, 'B' => 6, 'C' => 11, 'E' => 19],
            2 => ['A' => 3, 'B' => 8, 'C' => 13, 'E' => 21],
            3 => ['A' => 2, 'B' => 7, 'C' => 12, 'E' => 20],
            4 => ['A' => 201, 'B' => 206, 'C' => 211],
            5 => ['A' => 202, 'B' => 207, 'C' => 212],
            6 => ['A' => 203, 'B' => 208, 'C' => 213],
        ][(int)$invoice['id_tipo_comprobante']][$invoice['letra']] ?? 0,
        'fecha_comprobante' => $invoice['fecha_emitida'],
        'fecha_servicio_desde' => null,
        'fecha_servicio_hasta' => null,
        'fecha_vencimiento_pago' => null,
        'moneda' => $currency,
        'cotizacion' => $exchangeRate,
        'importe_neto' => (float)$invoice['subtotal_no_gravado'],
        'importe_no_gravado' => 0,
        'importe_exento' => 0,
        'importe_iva' => (float)$invoice['iva'],
        'importe_tributos' => (float)$invoice['otros'],
        'importe_total' => (float)$invoice['total'],
        'cae' => null,
        'fecha_vencimiento_cae' => null,
        'homologacion' => 0,
    ];
    $footerText = 'COPIA LOCAL - SIN AUTORIZACIÓN ARCA - SIN VALIDEZ FISCAL';
}

Database::disconnect();

function arcaEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function arcaDate($value): string
{
    if (empty($value)) {
        return '';
    }
    $date = DateTime::createFromFormat('!Y-m-d', (string)$value);
    return $date ? $date->format('d/m/Y') : '';
}

function arcaMoney($value): string
{
    return number_format((float)$value, 2, ',', '.');
}

$number = (int)$comprobante['numero_comprobante'];
$qrImage = null;
if (!$localOnly) {
    $issuerCuit = (int)$emisor['cuit'];
    $receiverNumber = (int)$receptor['numero_documento'];
    $qrData = [
        'ver' => 1,
        'fecha' => (string)$comprobante['fecha_comprobante'],
        'cuit' => $issuerCuit,
        'ptoVta' => (int)$comprobante['punto_venta'],
        'tipoCmp' => (int)$comprobante['tipo_comprobante'],
        'nroCmp' => $number,
        'importe' => (float)$comprobante['importe_total'],
        'moneda' => (string)$comprobante['moneda'],
        'ctz' => (float)$comprobante['cotizacion'],
        'tipoDocRec' => (int)$comprobante['receptor_tipo_documento'],
        'nroDocRec' => $receiverNumber,
        'tipoCodAut' => 'E',
        'codAut' => (int)$comprobante['cae'],
    ];
    $qrContent = json_encode($qrData, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $qrUrl = 'https://www.afip.gob.ar/fe/qr/?p=' . base64_encode($qrContent);
    $qrOptions = new chillerlan\QRCode\QROptions([
        'outputType' => chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
        'scale' => 4,
        'quietzoneSize' => 2,
    ]);
    $qrOutput = (new chillerlan\QRCode\QRCode($qrOptions))->render($qrUrl);
    if (strpos($qrOutput, 'data:image/svg+xml;base64,') === 0) {
        $qrImage = $qrOutput;
    } elseif (strpos($qrOutput, '<svg') !== false) {
        $qrImage = 'data:image/svg+xml;base64,' . base64_encode($qrOutput);
    } else {
        throw new RuntimeException('El generador QR no produjo un SVG utilizable para el PDF.');
    }
}

$currency = (string)$comprobante['moneda'];
$isDollar = function_exists('esMonedaDolar')
    ? esMonedaDolar($currency)
    : preg_match('/(?:usd|u\\$s|u\\$d|d[oó]lar)/iu', $currency) === 1;
$currencyLabel = $isDollar ? 'USD' : 'ARS';
$totalPesos = (float)$comprobante['importe_total'] * (float)$comprobante['cotizacion'];
$letra = (string)($detalle['letra'] ?? '');
$voucherType = (int)$comprobante['tipo_comprobante'];
$voucherTitles = [
    1 => 'FACTURA', 6 => 'FACTURA', 11 => 'FACTURA',
    19 => 'FACTURA DE EXPORTACIÓN',
    2 => 'NOTA DE DÉBITO', 7 => 'NOTA DE DÉBITO', 12 => 'NOTA DE DÉBITO', 20 => 'NOTA DE DÉBITO',
    3 => 'NOTA DE CRÉDITO', 8 => 'NOTA DE CRÉDITO', 13 => 'NOTA DE CRÉDITO', 21 => 'NOTA DE CRÉDITO',
    201 => 'FACTURA DE CRÉDITO ELECTRÓNICA MiPyME',
    206 => 'FACTURA DE CRÉDITO ELECTRÓNICA MiPyME',
    211 => 'FACTURA DE CRÉDITO ELECTRÓNICA MiPyME',
    202 => 'NOTA DE DÉBITO ELECTRÓNICA MiPyME',
    207 => 'NOTA DE DÉBITO ELECTRÓNICA MiPyME',
    212 => 'NOTA DE DÉBITO ELECTRÓNICA MiPyME',
    203 => 'NOTA DE CRÉDITO ELECTRÓNICA MiPyME',
    208 => 'NOTA DE CRÉDITO ELECTRÓNICA MiPyME',
    213 => 'NOTA DE CRÉDITO ELECTRÓNICA MiPyME',
];
$title = $voucherTitles[$voucherType] ?? strtoupper((string)($detalle['tipo_comprobante_nombre'] ?? 'COMPROBANTE'));
$showVatByItem = $letra === 'A';
$hasMultipleCopies = in_array($voucherType, [1, 6, 11], true);
if (!$localOnly) {
    $footerText = !empty($comprobante['homologacion']) ? 'HOMOLOGACIÓN - SIN VALIDEZ FISCAL' : 'Comprobante autorizado por ARCA';
}

$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@page { size: A4; margin: 8mm 8mm 15mm; }
* { box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; color:#111; font-size:8pt; }
.watermark { position:fixed; top:39%; left:8%; width:84%; text-align:center; font-size:32pt; font-weight:bold; color:#dedede; transform:rotate(-32deg); z-index:-1; }
.topline { border-top:1px solid #111; }
.copy-label { width:100%; text-align:center; border:1px solid #222; border-bottom:0; padding:1.5mm; font-weight:bold; font-size:9pt; }
.header { width:100%; border-collapse:collapse; table-layout:fixed; margin-bottom:3mm; border:1px solid #222; }
.header td { vertical-align:top; padding:2mm; }
.issuer { width:42%; border-right:1px solid #222; }
.issuer .name { text-align:center; font-size:10pt; font-weight:bold; margin-bottom:2mm; }
.issuer .field { margin:.7mm 0; overflow-wrap:anywhere; }
.letter { width:11%; text-align:center; vertical-align:top !important; padding:2mm 1mm 1mm !important; }
.letterbox { border:1px solid #111; width:16mm; min-height:17mm; margin:0 auto; padding:1mm .5mm; text-align:center; overflow:hidden; }
.letterbox strong { display:block; font-size:20pt; line-height:10mm; }
.letterbox span { display:block; font-size:6.5pt; white-space:nowrap; }
.number { width:47%; border-left:1px solid #222; }
.number h1 { font-size:13pt; margin:0 0 1mm; text-align:center; }
.number table { width:100%; border-collapse:collapse; table-layout:fixed; }
.number td { padding:.5mm 0; vertical-align:top; }
.number td:first-child { width:52%; font-weight:bold; }
.number td:last-child { text-align:right; overflow-wrap:anywhere; }
.number .number-pair td { width:50%; white-space:nowrap; text-align:left; }
.number .number-pair td:last-child { text-align:right; }
.muted { color:#444; }
.period { border:1px solid #222; padding:2mm 3mm; margin:2mm 0; }
.customer { border:1px solid #222; padding:1mm 2mm; margin:2mm 0 3mm; }
.customer table { width:100%; border-collapse:collapse; }
.customer td { width:50%; padding:1.3mm 1mm; vertical-align:top; overflow-wrap:anywhere; }
.commercial-reference { border:1px solid #222; padding:1.5mm 2mm; margin:2mm 0; }
.items { width:100%; border-collapse:collapse; margin-top:3mm; }
.items thead { display:table-header-group; }
.items th { background:#b7b7b7; border:1px solid #333; padding:1.2mm .5mm; text-align:center; font-size:6.5pt; }
.items td { border:1px solid #555; padding:1.2mm .5mm; vertical-align:top; }
.items td.numeric { white-space:nowrap; }
.item-project { font-size:7pt; margin-bottom:.7mm; }
.right { text-align:right; } .center { text-align:center; }
tr { page-break-inside:avoid; }
.summary-box { width:88mm; max-width:100%; margin:3mm 0 0 auto; border:1px solid #222; padding:2mm; page-break-inside:avoid; }
.summary-caption { text-align:right; font-weight:bold; border-bottom:1px solid #777; padding-bottom:1mm; margin-bottom:1mm; }
.summary { width:100%; border-collapse:collapse; table-layout:fixed; margin:0; }
.summary td { padding:.6mm .5mm; vertical-align:top; overflow:hidden; }
.summary .label { width:59%; text-align:right; }
.summary .amount { width:41%; text-align:right; white-space:nowrap; }
.summary .total td { border-top:1px solid #111; font-weight:bold; font-size:8pt; padding-top:1mm; }
.legal { border:1px solid #111; padding:2mm; margin-top:3mm; text-align:right; }
.tax-transparency { border:1px solid #111; padding:2mm; margin-top:2mm; text-align:center; font-style:italic; }
.pesos-equivalents { border:1px solid #111; padding:2mm 3mm; margin-top:2mm; page-break-inside:avoid; }
.pesos-equivalents h3 { font-size:8pt; margin:0 0 1mm; text-align:right; }
.pesos-equivalents table { width:100%; border-collapse:collapse; }
.pesos-equivalents td { padding:.5mm; }
.pesos-equivalents td:last-child { text-align:right; white-space:nowrap; }
.footer { position:fixed; bottom:-10mm; left:0; right:0; border-top:1px solid #111; padding-top:2mm; font-size:7pt; }
.qr { width:31mm; height:31mm; }
.cae { font-size:8pt; }
.notes { margin-top:3mm; white-space:pre-wrap; }
.copy-break { page-break-before:always; }
</style></head><body>';
if (!empty($comprobante['homologacion'])) {
    $html .= '<div class="watermark">HOMOLOGACIÓN<br>SIN VALIDEZ FISCAL</div>';
} elseif ($localOnly) {
    $html .= '<div class="watermark">COPIA LOCAL<br>SIN VALIDEZ FISCAL</div>';
}
$html .= ($hasMultipleCopies ? '<div class="copy-label">__COMPROBANTE_COPIA__</div>' : '')
    . '<table class="header"><tr><td class="issuer">'
    . '<div class="name">' . arcaEscape($emisor['razon_social'] ?? '') . '</div>'
    . '<div class="field"><b>Razón Social:</b> ' . arcaEscape($emisor['razon_social'] ?? '') . '</div>'
    . '<div class="field"><b>Domicilio Comercial:</b> ' . arcaEscape($emisor['domicilio'] ?? '') . '</div>'
    . '<div class="field"><b>Condición frente al IVA:</b> ' . arcaEscape($emisor['condicion_iva'] ?? '') . '</div>'
    . '</td><td class="letter"><div class="letterbox"><strong>' . arcaEscape($letra) . '</strong><span>COD. '
    . ($voucherType > 0 ? str_pad((string)$voucherType, 2, '0', STR_PAD_LEFT) : '--') . '</span></div></td>'
    . '<td class="number"><h1>' . arcaEscape($title) . '</h1><table>'
    . '<tr class="number-pair"><td><b>Punto de Venta:</b> ' . str_pad((string)$comprobante['punto_venta'], 5, '0', STR_PAD_LEFT) . '</td>'
    . '<td><b>Comp. Nro.:</b> ' . str_pad((string)$number, 8, '0', STR_PAD_LEFT) . '</td></tr>'
    . '<tr><td colspan="2"><b>Fecha de Emisión:</b> ' . arcaEscape(arcaDate($comprobante['fecha_comprobante'])) . '</td></tr>'
    . '<tr><td colspan="2"><b>CUIT:</b> ' . arcaEscape($emisor['cuit'] ?? '') . '</td></tr>'
    . '<tr><td colspan="2"><b>Ingresos Brutos:</b> ' . arcaEscape($emisor['ingresos_brutos'] ?? '') . '</td></tr>'
    . '<tr><td colspan="2"><b>Fecha de Inicio de Actividades:</b> ' . arcaEscape(arcaDate($emisor['fecha_inicio_actividades'] ?? '')) . '</td></tr>'
    . '<tr><td colspan="2"><b>Moneda:</b> ' . arcaEscape($currencyLabel)
    . ($isDollar ? ' &nbsp; <b>Cotización:</b> ' . number_format((float)$comprobante['cotizacion'], 6, ',', '.') : '')
    . '</td></tr></table></td></tr></table>';

if (!$localOnly && (int)$comprobante['concepto'] !== 1) {
    $html .= '<div class="period"><b>Período facturado desde:</b> ' . arcaEscape(arcaDate($comprobante['fecha_servicio_desde']))
        . ' &nbsp; <b>Hasta:</b> ' . arcaEscape(arcaDate($comprobante['fecha_servicio_hasta']))
        . ' &nbsp; <b>Vencimiento para el pago:</b> ' . arcaEscape(arcaDate($comprobante['fecha_vencimiento_pago'])) . '</div>';
}
$html .= '<div class="customer"><table>'
    . '<tr><td><b>CUIT / Documento:</b> ' . arcaEscape(($receptor['cuit'] ?? '') ?: ($receptor['numero_documento'] ?? '')) . '</td>'
    . '<td><b>Apellido y Nombre / Razón Social:</b> ' . arcaEscape($receptor['razon_social'] ?? '') . '</td></tr>'
    . '<tr><td><b>Condición frente al IVA:</b> ' . arcaEscape($receptor['condicion_iva'] ?? '') . '</td>'
    . '<td><b>Domicilio:</b> ' . arcaEscape($receptor['domicilio'] ?? '') . '</td></tr>'
    . '<tr><td colspan="2"><b>Condición de venta:</b> ' . arcaEscape($detalle['condicion_venta'] ?? '') . '</td></tr>'
    . '</table></div>';
$commercialReference = trim((string)($detalle['proyecto'] ?? ''));
if ($commercialReference !== '') {
    $html .= '<div class="commercial-reference"><b>Referencia Comercial:</b> ' . arcaEscape($commercialReference) . '</div>';
}
$html .= '<table class="items"><thead><tr>'
    . '<th style="width:9%">N.C.M.</th><th style="width:' . ($showVatByItem ? '28' : '29') . '%">Producto / Servicio</th>'
    . '<th style="width:7%">Cantidad</th><th style="width:8%">U. Medida</th><th style="width:14%">Precio Unit. (' . arcaEscape($currencyLabel) . ')</th>'
    . '<th style="width:7%">% Bonif.</th>';
if ($showVatByItem) {
    $html .= '<th style="width:8%">IVA</th><th style="width:19%">Importe (' . arcaEscape($currencyLabel) . ')</th>';
} else {
    $html .= '<th style="width:10%">Imp. Bonif. (' . arcaEscape($currencyLabel) . ')</th>'
        . '<th style="width:16%">Subtotal (' . arcaEscape($currencyLabel) . ')</th>';
}
$html .= '</tr></thead><tbody>';

foreach ($detalle['items'] as $item) {
    $quantity = (float)$item['cantidad'];
    $base = (float)$item['base_imponible'];
    $discount = (float)($item['descuento_porcentaje'] ?? 0);
    $unitPrice = (float)($item['precio_unitario'] ?? 0);
    $rate = (float)($item['alicuota_iva'] ?? 0);
    $itemVat = $showVatByItem ? round($base * $rate / 100, 2) : 0;
    $discountAmount = max(0, ($unitPrice * $quantity) - $base);
    $projectCode = trim((string)($item['proyecto'] ?? $detalle['proyecto'] ?? ''));
    if ($projectCode === '') {
        $projectCode = 'Sin asignar';
    }
    if ($unitPrice <= 0 && $quantity > 0) {
        $discountFactor = 1 - $discount / 100;
        $unitPrice = $discountFactor > 0 ? $base / ($quantity * $discountFactor) : 0;
    }
    $html .= '<tr><td class="center">' . arcaEscape($item['ncm'] ?? $detalle['ncm'] ?? '73.08.20') . '</td>'
        . '<td><div class="item-project"><b>Proyecto:</b> ' . arcaEscape($projectCode) . '</div>'
        . arcaEscape($item['descripcion'] ?? '') . '</td>'
        . '<td class="center">' . arcaEscape(rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.')) . '</td>'
        . '<td class="center">unidades</td>'
        . '<td class="right numeric">' . arcaEscape($currencyLabel) . ' ' . arcaMoney($unitPrice) . '</td>'
        . '<td class="right numeric">' . arcaMoney($discount) . '</td>';
    if ($showVatByItem) {
        $html .= '<td class="center">' . arcaEscape(rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',')) . '%</td>'
            . '<td class="right numeric">' . arcaEscape($currencyLabel) . ' ' . arcaMoney($base + $itemVat) . '</td>';
    } else {
        $html .= '<td class="right numeric">' . arcaEscape($currencyLabel) . ' ' . arcaMoney($discountAmount) . '</td>'
            . '<td class="right numeric">' . arcaEscape($currencyLabel) . ' ' . arcaMoney($base) . '</td>';
    }
    $html .= '</tr>';
}
$html .= '</tbody></table><div class="summary-box"><div class="summary-caption">Moneda: '
    . arcaEscape($currencyLabel) . ($isDollar ? ' - Dólar Estadounidense' : ' - Pesos Argentinos') . '</div><table class="summary">'
    . '<tr><td class="label">Importe Neto Gravado:</td><td class="amount">' . arcaEscape($currencyLabel) . ' ' . arcaMoney($comprobante['importe_neto']) . '</td></tr>'
    . '<tr><td class="label">Importe No Gravado:</td><td class="amount">' . arcaEscape($currencyLabel) . ' ' . arcaMoney($comprobante['importe_no_gravado']) . '</td></tr>';
if ((float)$comprobante['importe_exento'] !== 0.0 || !$localOnly) {
    $html .= '<tr><td class="label">Importe Exento:</td><td class="amount">' . arcaEscape($currencyLabel) . ' ' . arcaMoney($comprobante['importe_exento']) . '</td></tr>';
}
$ivaAmounts = [6 => 0, 5 => 0, 4 => 0, 8 => 0, 9 => 0, 3 => 0];
foreach ($alicuotas as $alicuota) {
    $ivaAmounts[(int)$alicuota['codigo_arca']] = (float)$alicuota['importe'];
}
$ivaRateLabels = [6 => '27%', 5 => '21%', 4 => '10,5%', 8 => '5%', 9 => '2,5%', 3 => '0%'];
if ($showVatByItem) {
    foreach ($ivaRateLabels as $codigo => $rateLabel) {
        $html .= '<tr><td class="label">IVA ' . $rateLabel . ':</td><td class="amount">'
            . arcaEscape($currencyLabel) . ' ' . arcaMoney($ivaAmounts[$codigo]) . '</td></tr>';
    }
}
foreach ($tributos as $tributo) {
    $html .= '<tr><td class="label">' . arcaEscape($tributo['descripcion']) . ':</td><td class="amount">'
        . arcaEscape($currencyLabel) . ' ' . arcaMoney($tributo['importe']) . '</td></tr>';
}
$html .= '<tr><td class="label">Importe Otros Tributos:</td><td class="amount">' . arcaEscape($currencyLabel) . ' '
    . arcaMoney($comprobante['importe_tributos']) . '</td></tr>';
$html .= '<tr class="total"><td class="label">Importe Total:</td><td class="amount">' . arcaEscape($currencyLabel) . ' ' . arcaMoney($comprobante['importe_total']) . '</td></tr></table></div>';
if (!$localOnly) {
    $html .= '<div class="tax-transparency">&quot;A los fines impositivos Importe Neto $ '
        . arcaMoney((float)$comprobante['importe_neto'] * (float)$comprobante['cotizacion'])
        . ', IVA $ ' . arcaMoney((float)$comprobante['importe_iva'] * (float)$comprobante['cotizacion'])
        . ' y el Total $ ' . arcaMoney($totalPesos) . '.&quot;</div>';
}
if ($isDollar) {
    $rate = (float)$comprobante['cotizacion'];
    $html .= '<div class="pesos-equivalents"><h3>Importes en pesos argentinos (valores en USD multiplicados por la cotización '
        . number_format($rate, 6, ',', '.') . ')</h3><table>'
        . '<tr><td>Importe Neto Gravado</td><td>$ ' . arcaMoney((float)$comprobante['importe_neto'] * $rate) . '</td></tr>';
    if (!in_array($letra, ['C', 'E'], true)) {
        foreach ($ivaRateLabels as $codigo => $rateLabel) {
            $html .= '<tr><td>IVA ' . $rateLabel . '</td><td>$ ' . arcaMoney($ivaAmounts[$codigo] * $rate) . '</td></tr>';
        }
    }
    foreach ($tributos as $tributo) {
        $html .= '<tr><td>' . arcaEscape($tributo['descripcion']) . '</td><td>$ '
            . arcaMoney((float)$tributo['importe'] * $rate) . '</td></tr>';
    }
    $html .= '<tr><td>Importe Otros Tributos</td><td>$ '
        . arcaMoney((float)$comprobante['importe_tributos'] * $rate) . '</td></tr>'
        . '<tr><td><b>Importe Total en Pesos</b></td><td><b>$ ' . arcaMoney($totalPesos) . '</b></td></tr>'
        . '</table></div>';
}
if (!empty($detalle['observaciones'])) {
    $html .= '<div class="notes"><b>Observaciones:</b> ' . arcaEscape($detalle['observaciones']) . '</div>';
}
if ($localOnly) {
    $html .= '<div class="legal"><b>Documento interno.</b> No es un comprobante fiscal y no cuenta con autorización ARCA, CAE ni QR fiscal.</div>';
} else {
    $html .= '<table style="width:100%;margin-top:4mm"><tr><td class="cae"><b>CAE N°:</b> ' . arcaEscape($comprobante['cae'])
        . '<br><b>Fecha de vencimiento:</b> ' . arcaEscape(arcaDate($comprobante['fecha_vencimiento_cae'])) . '</td>'
        . '<td style="width:34mm;text-align:right"><img class="qr" src="' . $qrImage . '" alt="Código QR ARCA"></td></tr></table>';
}
$html .= '</body></html>';

$bodyStart = strpos($html, '<body>') + strlen('<body>');
$bodyEnd = strrpos($html, '</body>');
$bodyPrefix = substr($html, 0, $bodyStart);
$bodyContent = substr($html, $bodyStart, $bodyEnd - $bodyStart);
$bodySuffix = substr($html, $bodyEnd);
$copyLabels = $hasMultipleCopies ? ['ORIGINAL', 'DUPLICADO', 'TRIPLICADO'] : [''];
$copies = [];
foreach ($copyLabels as $index => $copyLabel) {
    $copyContent = str_replace('__COMPROBANTE_COPIA__', $copyLabel, $bodyContent);
    $copies[] = ($index > 0 ? '<div class="copy-break"></div>' : '') . $copyContent;
}
$html = $bodyPrefix . implode('', $copies)
    . '<div class="footer">' . arcaEscape($footerText) . '</div>'
    . $bodySuffix;

$options = new Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('isPhpEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('Factura-' . str_pad((string)$comprobante['punto_venta'], 5, '0', STR_PAD_LEFT) . '-'
    . str_pad((string)$number, 8, '0', STR_PAD_LEFT) . '.pdf', ['Attachment' => true]);
exit;
