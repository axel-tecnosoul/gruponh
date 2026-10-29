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
$q = $pdo->prepare(
    "SELECT * FROM comprobantes_arca
     WHERE id_factura_venta = ? AND estado = 'autorizado'
     ORDER BY id DESC LIMIT 1"
);
$q->execute([$idFactura]);
$comprobante = $q->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$comprobante) {
    Database::disconnect();
    http_response_code(403);
    exit('Solo se puede generar el PDF de un comprobante autorizado por ARCA.');
}
$homologacion = !empty($comprobante['homologacion']);

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
$footerText = $homologacion ? 'HOMOLOGACIÓN - SIN VALIDEZ FISCAL' : 'Comprobante autorizado por ARCA';

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
        'local_only' => false,
        'homologacion' => $homologacion,
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
