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

function packingPdfEscape($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function packingPdfNumber($value): string {
  return number_format((float)$value, 2, ',', '.');
}

$idRevision = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$incluirPeso = isset($_GET['peso']) && (int)$_GET['peso'] === 1;
if (!$idRevision) {
  http_response_code(400);
  exit('Packing List inválida.');
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT plr.id, plr.id_packing_list, plr.nombre, plr.id_proyecto, p.nombre AS nombre_proyecto,
               p.nro AS nro_proyecto, s.nro_sitio, s.nro_subsitio,
               DATE_FORMAT(plr.fecha, '%d/%m/%Y') AS fecha, plr.id_usuario,
               plr.id_estado_packing_list, plr.nro_revision, plr.anulado, plr.numero,
               cu1.nombre AS cuenta_elaboro, cu2.nombre AS cuenta_verifico, cu3.nombre AS cuenta_aprobo
        FROM packing_lists_revisiones plr
        INNER JOIN proyectos p ON p.id = plr.id_proyecto
        INNER JOIN sitios s ON s.id = p.id_sitio
        LEFT JOIN cuentas cu1 ON cu1.id = plr.id_cuenta_realizo
        LEFT JOIN cuentas cu2 ON cu2.id = plr.id_cuenta_reviso
        LEFT JOIN cuentas cu3 ON cu3.id = plr.id_cuenta_valido
        WHERE plr.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idRevision]);
$packingList = $q->fetch(PDO::FETCH_ASSOC);

if (empty($packingList)) {
  Database::disconnect();
  http_response_code(404);
  exit('No se encontró la Packing List solicitada.');
}

$historialHtml = '';
$qHistorial = $pdo->prepare("SELECT plr.nro_revision, DATE_FORMAT(plr.fecha, '%d/%m/%Y') AS fecha,
                                    plr.descripcion, cu1.nombre AS elaboro,
                                    cu2.nombre AS reviso, cu3.nombre AS aprobo
                             FROM packing_lists_revisiones plr
                             LEFT JOIN cuentas cu1 ON cu1.id = plr.id_cuenta_realizo
                             LEFT JOIN cuentas cu2 ON cu2.id = plr.id_cuenta_reviso
                             LEFT JOIN cuentas cu3 ON cu3.id = plr.id_cuenta_valido
                             WHERE plr.id_packing_list = ? ORDER BY plr.nro_revision ASC");
$qHistorial->execute([$packingList['id_packing_list']]);
while ($revision = $qHistorial->fetch(PDO::FETCH_ASSOC)) {
  $historialHtml .= '<tr>'
    . '<td>' . packingPdfEscape($revision['nro_revision']) . '</td>'
    . '<td>' . packingPdfEscape($revision['fecha']) . '</td>'
    . '<td class="concept-cell">' . packingPdfEscape($revision['descripcion']) . '</td>'
    . '<td>' . packingPdfEscape($revision['elaboro']) . '</td>'
    . '<td>' . packingPdfEscape($revision['reviso']) . '</td>'
    . '<td>' . packingPdfEscape($revision['aprobo']) . '</td>'
    . '</tr>';
}

$seccionesHtml = '';
$pesoTotal = 0.0;
$qSecciones = $pdo->prepare("SELECT id, cantidad, observaciones FROM packing_lists_secciones WHERE id_packing_list_revision = ? ORDER BY id");
$qSecciones->execute([$idRevision]);
$qComponentes = $pdo->prepare("SELECT plc.id, plc.id_conjunto_lista_corte, plc.id_concepto, plc.cantidad,
                                      plc.observaciones, lcc.nombre AS nombre_conjunto, lcc.id AS id_conjunto,
                                      m.concepto, m.largo, m.peso_metro
                               FROM packing_lists_componentes plc
                               LEFT JOIN listas_corte_conjuntos lcc ON lcc.id = plc.id_conjunto_lista_corte
                               LEFT JOIN materiales m ON m.id = plc.id_concepto
                               WHERE plc.id_packing_list_seccion = ? ORDER BY plc.id");
$qPosiciones = $pdo->prepare("SELECT lcp.id, m.concepto, lcp.posicion, lcp.cantidad, lcp.largo, lcp.ancho,
                                     lcp.marca, lcp.peso, lcp.peso_calculado_posicion, c.nro_colada,
                                     lcp.diametro, lcp.calidad, lcp.observaciones
                              FROM lista_corte_posiciones lcp
                              INNER JOIN materiales m ON m.id = lcp.id_material
                              LEFT JOIN coladas c ON c.id = lcp.id_colada
                              WHERE lcp.id_lista_corte_conjunto = ?
                              ORDER BY lcp.id");
$qProcesos = $pdo->prepare("SELECT tp.tipo FROM lista_corte_procesos lcpr
                            INNER JOIN tipos_procesos tp ON tp.id = lcpr.id_tipo_proceso
                            WHERE lcpr.id_lista_corte_posicion = ? ORDER BY tp.tipo");

while ($seccion = $qSecciones->fetch(PDO::FETCH_ASSOC)) {
  $seccionesHtml .= '<div class="section-block">'
    . '<table class="section-heading"><tbody><tr>'
    . '<th>Sección</th><td class="concept-cell">' . packingPdfEscape($seccion['observaciones']) . '</td>'
    . '<th class="quantity-heading">Cantidad</th><td class="quantity-cell">' . packingPdfEscape($seccion['cantidad']) . '</td>'
    . '</tr></tbody></table>';

  $seccionesHtml .= '<table class="component-table"><thead><tr>'
    . '<th>Conjunto / Concepto</th><th>Cantidad</th><th>Largo</th>'
    . ($incluirPeso ? '<th>Peso kg</th>' : '')
    . '<th>Observaciones</th></tr></thead><tbody>';

  $qComponentes->execute([$seccion['id']]);
  while ($componente = $qComponentes->fetch(PDO::FETCH_ASSOC)) {
    $esConjunto = !empty($componente['id_conjunto_lista_corte']);
    $nombreComponente = $esConjunto ? $componente['nombre_conjunto'] : $componente['concepto'];
    $largo = $esConjunto ? '' : $componente['largo'];
    $pesoComponente = 0.0;

    if ($incluirPeso) {
      if ($esConjunto) {
        $pesoComponente = calcularPesoConjunto($pdo, (int)$componente['id_conjunto']);
      } elseif ($componente['largo'] !== null && $componente['peso_metro'] !== null) {
        $pesoComponente = (float)$componente['largo'] * (float)$componente['peso_metro'] / 1000;
      }
      $pesoTotal += $pesoComponente;
    }

    $seccionesHtml .= '<tr>'
      . '<td class="concept-cell">' . packingPdfEscape($nombreComponente) . '</td>'
      . '<td class="number-cell">' . packingPdfEscape($componente['cantidad']) . '</td>'
      . '<td class="number-cell">' . packingPdfEscape($largo) . '</td>'
      . ($incluirPeso ? '<td class="number-cell">' . packingPdfNumber($pesoComponente) . '</td>' : '')
      . '<td class="concept-cell">' . packingPdfEscape($componente['observaciones']) . '</td>'
      . '</tr>';

    if ($esConjunto) {
      $seccionesHtml .= '<tr class="positions-row"><td colspan="' . ($incluirPeso ? '5' : '4') . '" class="positions-cell">'
        . '<table class="positions-table"><colgroup>'
        . '<col class="flag-col"><col class="flag-col"><col class="flag-col"><col class="flag-col">'
        . '<col class="position-concept-col"><col class="position-col"><col class="small-col"><col class="small-col">'
        . '<col class="small-col"><col class="mark-col">'
        . ($incluirPeso ? '<col class="small-col">' : '')
        . '<col class="colada-col"><col class="small-col"><col class="quality-col"><col class="process-col"><col class="notes-col">'
        . '</colgroup><thead><tr><th>P</th><th>F</th><th>S</th><th>R</th><th>Concepto</th><th>Posición</th>'
        . '<th>Cant.</th><th>Largo</th><th>Ancho</th><th>Marca</th>'
        . ($incluirPeso ? '<th>Peso kg</th>' : '')
        . '<th>Colada</th><th>Diám.</th><th>Calidad</th><th>Procesos</th><th>Observaciones</th></tr></thead><tbody>';

      $qPosiciones->execute([$componente['id_conjunto']]);
      while ($posicion = $qPosiciones->fetch(PDO::FETCH_ASSOC)) {
        $pesoPosicion = $incluirPeso ? calcularPesoPosicion($posicion, (string)$posicion['concepto']) : 0;
        $qProcesos->execute([$posicion['id']]);
        $procesos = implode(', ', $qProcesos->fetchAll(PDO::FETCH_COLUMN));

        $seccionesHtml .= '<tr><td></td><td></td><td></td><td></td>'
          . '<td class="concept-cell">' . packingPdfEscape($posicion['concepto']) . '</td>'
          . '<td>' . packingPdfEscape($posicion['posicion']) . '</td>'
          . '<td class="number-cell">' . packingPdfEscape($posicion['cantidad']) . '</td>'
          . '<td class="number-cell">' . packingPdfEscape($posicion['largo']) . '</td>'
          . '<td class="number-cell">' . packingPdfEscape($posicion['ancho']) . '</td>'
          . '<td>' . packingPdfEscape($posicion['marca']) . '</td>'
          . ($incluirPeso ? '<td class="number-cell">' . packingPdfNumber($pesoPosicion) . '</td>' : '')
          . '<td>' . packingPdfEscape($posicion['nro_colada']) . '</td>'
          . '<td class="number-cell">' . packingPdfEscape($posicion['diametro']) . '</td>'
          . '<td>' . packingPdfEscape($posicion['calidad']) . '</td>'
          . '<td class="concept-cell">' . packingPdfEscape($procesos) . '</td>'
          . '<td class="concept-cell">' . packingPdfEscape($posicion['observaciones']) . '</td></tr>';
      }
      $seccionesHtml .= '</tbody></table></td></tr>';
    }
  }

  $seccionesHtml .= '</tbody></table></div>';
}

$logoPath = __DIR__ . '/assets/images/logo.jpg';
$logoHtml = is_file($logoPath)
  ? '<img class="logo" src="data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logoPath)) . '" alt="Logo">'
  : '';
$pesoTotalHtml = $incluirPeso
  ? '<div class="total-weight">Peso <strong>TOTAL</strong> de la lista: ' . packingPdfNumber($pesoTotal) . ' kg</div>'
  : '';

$html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
  @page { size: A4 landscape; margin: 10mm 9mm 13mm; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 7.5pt; color: #111; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border: 0.5pt solid #333; padding: 3pt; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
  th { background: #e9edf2; font-weight: bold; text-align: center; }
  .header-table { margin-bottom: 5pt; }
  .header-table td { height: 30pt; vertical-align: middle; }
  .logo { max-width: 115pt; max-height: 36pt; }
  .title { text-align: center; font-size: 15pt; font-weight: bold; }
  .identifier { text-align: right; font-size: 9pt; font-weight: bold; }
  .project-table { margin-bottom: 6pt; }
  .history-table { margin: 4pt 0 8pt; font-size: 6.7pt; }
  .history-table th:nth-child(1) { width: 6%; }
  .history-table th:nth-child(2) { width: 11%; }
  .history-table th:nth-child(3) { width: 39%; }
  .history-table th:nth-child(4), .history-table th:nth-child(5), .history-table th:nth-child(6) { width: 14.66%; }
  .section-block { margin: 7pt 0; page-break-inside: auto; }
  .section-heading { margin-bottom: 2pt; }
  .section-heading th { width: 10%; }
  .section-heading .quantity-heading { width: 12%; }
  .section-heading .quantity-cell { width: 10%; text-align: right; }
  .component-table { font-size: 7pt; }
  .component-table thead { display: table-header-group; }
  .component-table th:nth-child(1) { width: 27%; }
  .component-table th:nth-child(2) { width: 8%; }
  .component-table th:nth-child(3) { width: 8%; }
  .component-table th:nth-child(4) { width: ' . ($incluirPeso ? '10%' : '57%') . '; }
  ' . ($incluirPeso ? '.component-table th:nth-child(5) { width: 47%; }' : '') . '
  .positions-cell { padding: 2pt; }
  .positions-table { font-size: 5.7pt; }
  .positions-table thead { display: table-header-group; }
  .positions-table th, .positions-table td { padding: 2pt; }
  .flag-col { width: 2.6%; }
  .position-concept-col { width: 22%; }
  .position-col { width: 7%; }
  .small-col { width: 4.8%; }
  .mark-col { width: 5.5%; }
  .colada-col { width: 6%; }
  .quality-col { width: 6%; }
  .process-col { width: 8.5%; }
  .notes-col { width: ' . ($incluirPeso ? '7.9%' : '12.7%') . '; }
  .concept-cell { white-space: normal; overflow-wrap: break-word; word-wrap: break-word; }
  .number-cell { text-align: right; white-space: nowrap; }
  .component-table > tbody > tr:not(.positions-row), .positions-table tbody tr { page-break-inside: avoid; }
  .positions-row { page-break-inside: auto; }
  .total-weight { margin-top: 7pt; text-align: right; font-size: 9pt; }
</style></head><body>';

$html .= '<table class="header-table"><tbody><tr><td style="width:22%">' . $logoHtml . '</td>'
  . '<td class="title" style="width:48%">PACKING LIST</td>'
  . '<td class="identifier" style="width:30%">' . packingPdfEscape($packingList['nombre'])
  . '<br>Nº ' . packingPdfEscape($packingList['numero']) . ' - Rev. ' . packingPdfEscape($packingList['nro_revision']) . '</td></tr></tbody></table>';

$html .= '<table class="project-table"><tbody><tr><td>'
  . '<strong>Emisión:</strong> ' . packingPdfEscape($packingList['fecha']) . '<br>'
  . '<strong>Proyecto:</strong> ' . packingPdfEscape($packingList['nombre_proyecto']) . '<br>'
  . '<strong>Nro:</strong> ' . packingPdfEscape($packingList['nro_sitio'] . '_' . $packingList['nro_subsitio'] . '_' . $packingList['nro_proyecto'])
  . '</td></tr></tbody></table>';

$html .= '<table class="history-table"><thead><tr><th>Rev.</th><th>Fecha</th><th>Modificaciones</th><th>Elaboró</th><th>Revisó</th><th>Aprobó</th></tr></thead><tbody>'
  . ($historialHtml ?: '<tr><td colspan="6">Sin revisiones registradas.</td></tr>')
  . '</tbody></table>';
$html .= $seccionesHtml ?: '<p>No hay secciones cargadas.</p>';
$html .= $pesoTotalHtml . '</body></html>';

Database::disconnect();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('chroot', __DIR__);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
$canvas->page_text(760, 570, '{PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.35, 0.35, 0.35]);

$sufijoPeso = $incluirPeso ? '_con_peso' : '_sin_peso';
$dompdf->stream('packing_list_' . (int)$packingList['numero'] . '_rev_' . (int)$packingList['nro_revision'] . $sufijoPeso . '.pdf', ['Attachment' => false]);
