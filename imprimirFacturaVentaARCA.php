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
        "SELECT fv.*, tc.id AS id_tipo_comprobante, tc.codigo_afip, tc.tipo AS tipo_comprobante, lc.letra, m.moneda,
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
        $rate = in_array($invoice['letra'], ['C', 'E'], true) ? 0.0 : (float)$row['tasa_iva'];
        $grossSubtotal = (float)$row['subtotal'];
        $factor = ($rate > 0) ? (1 + $rate / 100) : 1;
        $netBase = round($grossSubtotal / $factor, 2);
        $netPrice = ((float)$row['precio'] > 0) ? round((float)$row['precio'] / $factor, 2) : 0;
        $bases[(string)$rate] = ($bases[(string)$rate] ?? 0) + $grossSubtotal;
        $items[] = [
            'codigo' => (string)$row['concepto_codigo'],
            'ncm' => '73.08.20.00',
            'proyecto' => (string)($invoice['proyecto_codigo'] ?? ''),
            'descripcion' => (string)($row['descripcion'] ?: $row['concepto_text']),
            'cantidad' => (float)$row['cantidad'],
            'precio_unitario' => $netPrice,
            'descuento_porcentaje' => (float)$row['descuento_porcentaje'],
            'base_imponible' => $netBase,
            'alicuota_iva' => $rate,
        ];
    }
    $ivaCodeByRate = ['0' => 3, '2.5' => 9, '5' => 8, '10.5' => 4, '21' => 5, '27' => 6];
    $alicuotas = [];
    foreach ($bases as $rate => $grossBase) {
        $key = rtrim(rtrim(number_format((float)$rate, 2, '.', ''), '0'), '.');
        if (isset($ivaCodeByRate[$key])) {
            $r = (float)$rate;
            $alicuotas[] = [
                'codigo_arca' => $ivaCodeByRate[$key],
                'importe' => ($r > 0) ? round($grossBase * $r / (100 + $r), 2) : 0,
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
        'ncm' => '73.08.20.00',
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
        'tipo_comprobante' => (int)($invoice['codigo_afip'] ?? 0) > 0
            ? (int)$invoice['codigo_afip']
            : ([
            1 => ['A' => 1, 'B' => 6, 'C' => 11, 'E' => 19],
            2 => ['A' => 3, 'B' => 8, 'C' => 13, 'E' => 21],
            3 => ['A' => 2, 'B' => 7, 'C' => 12, 'E' => 20],
            4 => ['A' => 201, 'B' => 206, 'C' => 211],
            5 => ['A' => 202, 'B' => 207, 'C' => 212],
            6 => ['A' => 203, 'B' => 208, 'C' => 213],
        ][(int)$invoice['id_tipo_comprobante']][$invoice['letra']] ?? 0),
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

require_once __DIR__ . '/FacturaVentaARCAPDF.php';

$number = (int)$comprobante['numero_comprobante'];
$currency = (string)$comprobante['moneda'];
// El snapshot autorizado guarda el código ARCA (DOL/PES), no el texto de monedas.
// esMonedaDolar('DOL') da false ('dol' no contiene ninguna marca), por eso el PDF
// de una factura USD autorizada salía como ARS. Mapeo explícito primero.
$currencyNorm = strtoupper(trim($currency));
if ($currencyNorm === 'DOL') {
    $isDollar = true;
} elseif ($currencyNorm === 'PES') {
    $isDollar = false;
} elseif (function_exists('esMonedaDolar')) {
    $isDollar = esMonedaDolar($currency);
} else {
    $isDollar = preg_match('/(?:usd|u\\$s|u\\$d|d[oó]lar)/iu', $currency) === 1;
}
$currencyLabel = $isDollar ? 'USD' : 'ARS';
$voucherType = (int)$comprobante['tipo_comprobante'];
$letra = (string)($detalle['letra'] ?? '');
if ($letra === '') {
    $letra = [1 => 'A', 6 => 'B', 11 => 'C'][$voucherType] ?? '';
}
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
$qrFile = null;

try {
    if (!$localOnly) {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('El módulo PHP GD es necesario para insertar el QR en el PDF FPDF.');
        }
        $issuerCuit = (int)$emisor['cuit'];
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
            'nroDocRec' => (int)$receptor['numero_documento'],
            'tipoCodAut' => 'E',
            'codAut' => (int)$comprobante['cae'],
        ];
        $qrUrl = 'https://www.afip.gob.ar/fe/qr/?p=' . base64_encode(json_encode(
            $qrData,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        $qrFile = tempnam(sys_get_temp_dir(), 'arca_qr_');
        if ($qrFile === false) {
            throw new RuntimeException('No se pudo crear un archivo temporal para el QR ARCA.');
        }
        $qrOptions = new chillerlan\QRCode\QROptions([
            'outputType' => chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => false,
            'scale' => 5,
            'quietzoneSize' => 2,
        ]);
        (new chillerlan\QRCode\QRCode($qrOptions))->render($qrUrl, $qrFile);
        if (!is_file($qrFile) || filesize($qrFile) === 0) {
            throw new RuntimeException('El generador QR no produjo un PNG utilizable para FPDF.');
        }
    }

    $footerText = $localOnly
        ? 'COPIA LOCAL - SIN AUTORIZACIÓN ARCA - SIN VALIDEZ FISCAL'
        : (!empty($comprobante['homologacion'])
            ? 'HOMOLOGACIÓN - SIN VALIDEZ FISCAL'
            : 'Comprobante autorizado por ARCA');
    $pdf = new FacturaVentaARCAPDF([
        'comprobante' => $comprobante,
        'emisor' => $emisor,
        'receptor' => $receptor,
        'detalle' => $detalle,
        'alicuotas' => $alicuotas,
        'tributos' => $tributos,
        'letra' => $letra,
        'titulo' => $title,
        'moneda' => $currencyLabel,
        'dolares' => $isDollar,
        'local_only' => $localOnly,
        'homologacion' => !$localOnly && !empty($comprobante['homologacion']),
        'mostrar_iva_item' => $letra === 'A',
        'copias_multiples' => in_array($voucherType, [1, 6, 11], true),
        'pie' => $footerText,
        'qr_file' => $qrFile,
    ]);
    $pdfContent = $pdf->generar();
} finally {
    if ($qrFile !== null && is_file($qrFile)) {
        unlink($qrFile);
    }
}

header('Content-Type: application/pdf');
$nombrePdf = 'Factura-' . str_pad((string)$comprobante['punto_venta'], 5, '0', STR_PAD_LEFT)
    . '-' . str_pad((string)$number, 8, '0', STR_PAD_LEFT) . '.pdf';
if (!empty($_GET['descargar'])) {
    header('Content-Disposition: attachment; filename="' . $nombrePdf . '"');
} else {
    header('Content-Disposition: inline; filename="' . $nombrePdf . '"');
}
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
