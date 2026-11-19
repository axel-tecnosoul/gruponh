<?php
require 'config.php';
require 'database.php';
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

function presupuestoPdfEscape($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function presupuestoPdfNumero($value): string {
  return number_format((float)$value, 2, ',', '.');
}

function presupuestoPdfTexto($value): string {
  $texto = trim((string)$value);
  return $texto === '' ? '-' : presupuestoPdfEscape($texto);
}

function presupuestoPdfDias($value): string {
  $texto = trim((string)$value);
  return $texto === '' ? '-' : presupuestoPdfEscape($texto) . ' días';
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
  header('Location: listarPresupuestos.php');
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT p.id, p.nro, p.id_empresa, p.nro_revision, p.fecha, p.id_cuenta, p.solicitante,
               p.referencia, p.descripcion, p.id_moneda, p.monto, p.adjudicado, p.observaciones,
               p.es_marco, p.id_linea_negocio, p.certificacion, p.forma_pago,
               p.plazo_entrega_dias, p.validez_oferta_dias, p.anulado,
               DATE_FORMAT(p.fecha, '%d/%m/%Y') AS fecha_formateada,
               e.empresa, c.nombre AS cliente, c.razon_social, c.cuit, c.direccion,
               c.telefono, c.email, m.moneda, ln.linea_negocio, u.usuario
        FROM presupuestos p
        LEFT JOIN empresas e ON e.id = p.id_empresa
        LEFT JOIN cuentas c ON c.id = p.id_cuenta
        LEFT JOIN monedas m ON m.id = p.id_moneda
        LEFT JOIN lineas_negocio ln ON ln.id = p.id_linea_negocio
        LEFT JOIN usuarios u ON u.id = p.id_usuario
        WHERE p.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

if (empty($data)) {
  Database::disconnect();
  http_response_code(404);
  exit('No se encontró el presupuesto solicitado.');
}

$qItems = $pdo->prepare(
  "SELECT pd.detalle, pd.cantidad, um.unidad_medida, pd.costo, pd.precio
   FROM presupuestos_detalle pd
   LEFT JOIN unidades_medida um ON um.id = pd.id_unidad_medida
   WHERE pd.id_presupuesto = ?
   ORDER BY pd.id"
);
$qItems->execute([$id]);
$items = $qItems->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

$simbolo = ((int)$data['id_moneda'] === 1) ? 'U$S' : '$';
$totalCosto = 0.0;
$totalPrecio = 0.0;

$filasItems = '';
foreach ($items as $indice => $item) {
  $cantidad = (float)$item['cantidad'];
  $costo = (float)$item['costo'];
  $precio = (float)$item['precio'];
  $subtotalCosto = $cantidad * $costo;
  $subtotalPrecio = $cantidad * $precio;
  $totalCosto += $subtotalCosto;
  $totalPrecio += $subtotalPrecio;

  $filasItems .= '<tr>'
    . '<td class="centro">' . ($indice + 1) . '</td>'
    . '<td>' . presupuestoPdfEscape($item['detalle']) . '</td>'
    . '<td class="numero">' . presupuestoPdfNumero($cantidad) . '</td>'
    . '<td class="centro">' . presupuestoPdfEscape($item['unidad_medida'] ?? '') . '</td>'
    . '<td class="numero">' . $simbolo . ' ' . presupuestoPdfNumero($costo) . '</td>'
    . '<td class="numero">' . $simbolo . ' ' . presupuestoPdfNumero($precio) . '</td>'
    . '<td class="numero">' . $simbolo . ' ' . presupuestoPdfNumero($subtotalPrecio) . '</td>'
    . '</tr>';
}

if ($filasItems === '') {
  $filasItems = '<tr><td colspan="7" class="centro">El presupuesto no tiene ítems cargados.</td></tr>';
}

$logoArchivo = ((int)$data['id_empresa'] === 1) ? 'img/logo_nc.png' : 'img/logo_np.png';
$logoRuta = __DIR__ . '/' . $logoArchivo;
$logoHtml = is_file($logoRuta)
  ? '<img class="logo" src="data:image/png;base64,' . base64_encode((string)file_get_contents($logoRuta)) . '" alt="Logo">'
  : '';

$html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
  @page { size: A4 portrait; margin: 11mm 10mm 14mm; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 8pt; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 0.5pt solid #444; padding: 3pt; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
  th { background: #e9edf2; font-weight: bold; text-align: left; }
  .header-table td { border: none; padding: 0; vertical-align: middle; }
  .header-table .logo { max-width: 150pt; max-height: 52pt; }
  .title { text-align: center; font-size: 16pt; font-weight: bold; letter-spacing: 1pt; }
  .identifier { text-align: right; font-size: 9pt; font-weight: bold; }
  .info-table td { padding: 2.5pt 3pt; }
  .info-table td.label { width: 19%; background: #f3f5f8; font-weight: bold; }
  .info-table td.value { width: 31%; }
  .block-title { margin: 7pt 0 2pt; font-size: 9pt; font-weight: bold; }
  .text-block { border: 0.5pt solid #444; padding: 4pt; min-height: 16pt; }
  .items-table { margin-top: 7pt; }
  .items-table thead { display: table-header-group; }
  .items-table th:nth-child(1) { width: 5%; }
  .items-table th:nth-child(2) { width: 43%; }
  .items-table th:nth-child(3) { width: 10%; }
  .items-table th:nth-child(4) { width: 10%; }
  .items-table th:nth-child(5) { width: 10.66%; }
  .items-table th:nth-child(6) { width: 10.66%; }
  .items-table th:nth-child(7) { width: 10.68%; }
  .items-table tbody tr { page-break-inside: avoid; }
  .numero { text-align: right; white-space: nowrap; }
  .centro { text-align: center; }
  .totals-table { margin-top: 6pt; width: 45%; margin-left: 55%; }
  .totals-table td { border: none; padding: 2pt 3pt; }
  .totals-table td.label { font-weight: bold; }
  .totals-table td.numero { border: 0.5pt solid #444; }
  .footer-note { margin-top: 10pt; font-size: 7pt; color: #555; text-align: center; }
</style></head><body>';

$html .= '<table class="header-table"><tbody><tr>'
  . '<td style="width:30%">' . $logoHtml . '</td>'
  . '<td class="title" style="width:40%">PRESUPUESTO</td>'
  . '<td class="identifier" style="width:30%">Nº ' . presupuestoPdfEscape($data['nro'])
  . '<br>Revisión ' . presupuestoPdfEscape($data['nro_revision']) . '</td>'
  . '</tr></tbody></table>';

$html .= '<table class="info-table" style="margin-top:8pt"><tbody>'
  . '<tr><td class="label">Empresa</td><td class="value">' . presupuestoPdfTexto($data['empresa']) . '</td>'
  . '<td class="label">Fecha</td><td class="value">' . presupuestoPdfTexto($data['fecha_formateada']) . '</td></tr>'
  . '<tr><td class="label">Cliente</td><td class="value">' . presupuestoPdfTexto($data['cliente']) . '</td>'
  . '<td class="label">Razón social</td><td class="value">' . presupuestoPdfTexto($data['razon_social']) . '</td></tr>'
  . '<tr><td class="label">CUIT</td><td class="value">' . presupuestoPdfTexto($data['cuit']) . '</td>'
  . '<td class="label">Dirección</td><td class="value">' . presupuestoPdfTexto($data['direccion']) . '</td></tr>'
  . '<tr><td class="label">Teléfono</td><td class="value">' . presupuestoPdfTexto($data['telefono']) . '</td>'
  . '<td class="label">Email</td><td class="value">' . presupuestoPdfTexto($data['email']) . '</td></tr>'
  . '<tr><td class="label">Solicitante</td><td class="value">' . presupuestoPdfTexto($data['solicitante']) . '</td>'
  . '<td class="label">Referencia</td><td class="value">' . presupuestoPdfTexto($data['referencia']) . '</td></tr>'
  . '<tr><td class="label">Línea de negocio</td><td class="value">' . presupuestoPdfTexto($data['linea_negocio']) . '</td>'
  . '<td class="label">Moneda</td><td class="value">' . presupuestoPdfTexto($data['moneda']) . '</td></tr>'
  . '<tr><td class="label">Es marco</td><td class="value">' . ((int)$data['es_marco'] === 1 ? 'Sí' : 'No') . '</td>'
  . '<td class="label">Adjudicado</td><td class="value">' . ((int)$data['adjudicado'] === 1 ? 'Sí' : 'No') . '</td></tr>'
  . '<tr><td class="label">Certificación</td><td class="value">' . presupuestoPdfTexto($data['certificacion']) . '</td>'
  . '<td class="label">Forma de pago</td><td class="value">' . presupuestoPdfTexto($data['forma_pago']) . '</td></tr>'
  . '<tr><td class="label">Plazo de entrega</td><td class="value">' . presupuestoPdfDias($data['plazo_entrega_dias']) . '</td>'
  . '<td class="label">Validez de la oferta</td><td class="value">' . presupuestoPdfDias($data['validez_oferta_dias']) . '</td></tr>'
  . '</tbody></table>';

$html .= '<div class="block-title">Descripción</div>'
  . '<div class="text-block">' . nl2br(presupuestoPdfTexto($data['descripcion'])) . '</div>';

$html .= '<div class="block-title">Observaciones</div>'
  . '<div class="text-block">' . nl2br(presupuestoPdfTexto($data['observaciones'])) . '</div>';

$html .= '<table class="items-table"><thead><tr>'
  . '<th class="centro">#</th><th>Detalle</th><th class="numero">Cantidad</th>'
  . '<th class="centro">Unidad</th><th class="numero">Costo unit.</th>'
  . '<th class="numero">Precio unit.</th><th class="numero">Subtotal</th>'
  . '</tr></thead><tbody>' . $filasItems . '</tbody></table>';

$html .= '<table class="totals-table"><tbody>'
  . '<tr><td class="label">Total costo</td><td class="numero">' . $simbolo . ' ' . presupuestoPdfNumero($totalCosto) . '</td></tr>'
  . '<tr><td class="label">Total precio</td><td class="numero">' . $simbolo . ' ' . presupuestoPdfNumero($totalPrecio) . '</td></tr>'
  . '<tr><td class="label">Monto del presupuesto</td><td class="numero">' . $simbolo . ' ' . presupuestoPdfNumero($data['monto']) . '</td></tr>'
  . '</tbody></table>';

$html .= '<div class="footer-note">Emitido por ' . presupuestoPdfEscape($data['usuario']) . '</div>';
$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('chroot', __DIR__);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
$canvas->page_text(500, 800, '{PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.35, 0.35, 0.35]);

$dompdf->stream(
  'presupuesto_' . (int)$data['nro'] . '_rev_' . (int)$data['nro_revision'] . '.pdf',
  ['Attachment' => false]
);
