<?php
require("config.php");
require 'database.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$id_certificado_maestro = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_certificado_maestro) {
  http_response_code(400);
  die('Certificado Maestro inválido.');
}
if (function_exists('esOperacionesSinEconomico') && esOperacionesSinEconomico()) {
  http_response_code(403);
  die('No tiene permisos para exportar el Certificado Maestro.');
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqlCm = "SELECT cm.id,
                 DATE_FORMAT(cm.fecha_emision,'%d/%m/%Y') AS fecha_emision,
                 cm.monto_total,
                 cm.monto_acumulado_avances,
                 cm.monto_acumulado_anticipos,
                 cm.monto_acumulado_desacopios,
                 cm.monto_acumulado_descuentos,
                 cm.monto_acumulado_ajustes,
                  cm.aprobado_cliente,
                  occ.numero AS numero_occ,
                  cu.nombre AS cliente,
                  m.moneda
          FROM certificados_maestros cm
          INNER JOIN occ ON occ.id = cm.id_occ
          INNER JOIN cuentas cu ON cu.id = occ.id_cuenta_cliente
          INNER JOIN monedas m ON m.id = cm.id_moneda
          WHERE cm.id = ?";
$q = $pdo->prepare($sqlCm);
$q->execute([$id_certificado_maestro]);
$cm = $q->fetch(PDO::FETCH_ASSOC);

if (!$cm) {
  Database::disconnect();
  http_response_code(404);
  die('Certificado Maestro no encontrado.');
}

$sqlCas = "SELECT cac.id,
                  cac.nro_certificado,
                  cac.nro_revision,
                  cac.fecha_emision,
                  cac.fecha_inicio,
                  cac.fecha_fin,
                  cac.monto_total,
                  cac.monto_acumulado_desacopios,
                  cac.aprobado_cliente,
                  occ.numero AS numero_occ,
                  cu.nombre AS cliente,
                  m.moneda
           FROM certificados_avances_cabecera cac
           INNER JOIN certificados_maestros cm ON cm.id = cac.id_certificado_maestro
           INNER JOIN occ ON occ.id = cm.id_occ
           INNER JOIN cuentas cu ON cu.id = occ.id_cuenta_cliente
           INNER JOIN monedas m ON m.id = cm.id_moneda
           WHERE cac.id_certificado_maestro = ?
           ORDER BY cac.nro_certificado, cac.nro_revision";
$q = $pdo->prepare($sqlCas);
$q->execute([$id_certificado_maestro]);
$certificados = $q->fetchAll(PDO::FETCH_ASSOC);

$sqlProyectos = "SELECT GROUP_CONCAT(DISTINCT p.nombre SEPARATOR ', ') AS proyectos
                 FROM certificados_maestros_detalles cmd
                 LEFT JOIN proyectos p ON p.id = cmd.id_proyecto
                 WHERE cmd.id_certificado_maestro = ?";
$q = $pdo->prepare($sqlProyectos);
$q->execute([$id_certificado_maestro]);
$proyectosNombre = (string) ($q->fetchColumn() ?: '');

// Detalle por CA: filas del CM + avance propio de ese CA + acumulado previo (CA anteriores).
$sqlDetalle = "SELECT cmd.descripcion,
                      cmd.cantidad AS cantidad_cm,
                      um.unidad_medida,
                      cmd.precio_unitario AS precio_unitario_cm,
                      cmd.subtotal AS subtotal_cm,
                      cmd.incidencia_porcentaje,
                      cad.cantidad_actual,
                      COALESCE(prev.acumulado_previo, 0) AS acumulado_previo
               FROM certificados_maestros_detalles cmd
               LEFT JOIN certificados_avances_detalle cad
                      ON cad.id_certificado_maestro_detalle = cmd.id
                     AND cad.id_certificado_avance = ?
               LEFT JOIN unidades_medida um ON um.id = cmd.id_unidad_medida
               LEFT JOIN (
                   SELECT cad2.id_certificado_maestro_detalle,
                          SUM(cad2.cantidad_actual) AS acumulado_previo
                   FROM certificados_avances_detalle cad2
                   INNER JOIN certificados_avances_cabecera cac2 ON cac2.id = cad2.id_certificado_avance
                   WHERE cac2.id_certificado_maestro = ?
                     AND cac2.nro_certificado < ?
                     AND NOT EXISTS (
                       SELECT 1 FROM certificados_avances_cabecera y
                       WHERE y.id_certificado_maestro = cac2.id_certificado_maestro
                         AND y.nro_certificado = cac2.nro_certificado
                         AND y.nro_revision > cac2.nro_revision
                   )
                   GROUP BY cad2.id_certificado_maestro_detalle
               ) prev ON prev.id_certificado_maestro_detalle = cmd.id
               WHERE cmd.id_certificado_maestro = ?
               ORDER BY cmd.lote, cmd.aperturado, cmd.id";
$qDetalle = $pdo->prepare($sqlDetalle);

// Totalizado: acumulado por ítem (últimas revisiones de todos los CAs del CM).
$sqlTotAcum = "SELECT cad.id_certificado_maestro_detalle,
                      SUM(cad.cantidad_actual) AS acumulado
               FROM certificados_avances_detalle cad
               INNER JOIN certificados_avances_cabecera c ON c.id = cad.id_certificado_avance
               WHERE c.id_certificado_maestro = ?
                 AND NOT EXISTS (
                     SELECT 1 FROM certificados_avances_cabecera y
                     WHERE y.id_certificado_maestro = c.id_certificado_maestro
                       AND y.nro_certificado = c.nro_certificado
                       AND y.nro_revision > c.nro_revision
                 )
               GROUP BY cad.id_certificado_maestro_detalle";

// Base de ítems del CM para la hoja Totalizado.
$sqlBaseItems = "SELECT cmd.id,
                        cmd.descripcion,
                        cmd.cantidad AS cantidad_cm,
                        um.unidad_medida,
                        cmd.precio_unitario AS precio_unitario_cm,
                        cmd.subtotal AS subtotal_cm,
                        cmd.incidencia_porcentaje
                 FROM certificados_maestros_detalles cmd
                 LEFT JOIN unidades_medida um ON um.id = cmd.id_unidad_medida
                 WHERE cmd.id_certificado_maestro = ?
                 ORDER BY cmd.lote, cmd.aperturado, cmd.id";

Database::disconnect();

$objPHPExcel = new Spreadsheet();

$fmtNumero = '#,##0.00';
$fmtPorcentaje = '0.00"%"';

function xlsEstiloTitulo(): array {
  return [
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
  ];
}
function xlsEstiloBloque(): array {
  return [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE8F5']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
  ];
}
function xlsEstiloLabel(): array {
  return ['font' => ['bold' => true]];
}
function xlsEstiloTablaHeader(): array {
  return [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE8F5']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
  ];
}
function xlsBordes(): array {
  return ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
}
function xlsAnchos($sheet): void {
  $anchos = [
    'A' => 6, 'B' => 50, 'C' => 9, 'D' => 11, 'E' => 12, 'F' => 14, 'G' => 15,
    'H' => 10, 'I' => 8, 'J' => 14,
    'K' => 10, 'L' => 8, 'M' => 14,
    'N' => 10, 'O' => 8, 'P' => 14,
  ];
  foreach ($anchos as $col => $ancho) {
    $sheet->getColumnDimension($col)->setWidth($ancho);
  }
}
function xlsPageSetup($sheet, int $filaDesde, int $filaHasta): void {
  $pageSetup = $sheet->getPageSetup();
  $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
  $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
  $pageSetup->setFitToPage(true);
  $pageSetup->setFitToWidth(1);
  $pageSetup->setFitToHeight(0);
  $pageSetup->setRowsToRepeatAtTopByStartAndEnd($filaDesde, $filaHasta);
}

/**
 * Genera una hoja por Certificado de Avance con el formato del modelo.
 */
function xlsHojaCA(Spreadsheet $objPHPExcel, array $ca, array $filas, string $proyectosNombre, int $index): void
{
  $fmtNumero = '#,##0.00';
  $fmtPorcentaje = '0.00"%"';
  $tituloHoja = 'CA ' . $ca['nro_certificado'] . ' R' . $ca['nro_revision'];
  if ($index === 0) {
    $sheet = $objPHPExcel->getActiveSheet();
    $sheet->setTitle(substr($tituloHoja, 0, 31));
  } else {
    $sheet = $objPHPExcel->createSheet();
    $sheet->setTitle(substr($tituloHoja, 0, 31));
  }
  $sheet->setShowGridLines(false);

  $sheet->setCellValue('A1', 'CERTIFICADO DE AVANCE');
  $sheet->mergeCells('A1:P1');
  $sheet->getStyle('A1')->applyFromArray(xlsEstiloTitulo());

  $sheet->setCellValue('A3', 'Datos del Proyecto y Orden de Compra');
  $sheet->mergeCells('A3:D3');
  $sheet->getStyle('A3')->applyFromArray(xlsEstiloBloque());
  $pares = [
    ['Cliente', $ca['cliente']],
    ['Orden de Compra', $ca['numero_occ']],
    ['Moneda', $ca['moneda']],
  ];
  $f = 4;
  foreach ($pares as $par) {
    $sheet->getCell('A' . $f)->setValueExplicit($par[0]);
    $sheet->getStyle('A' . $f)->applyFromArray(xlsEstiloLabel());
    $sheet->getCell('B' . $f)->setValueExplicit($par[1]);
    $f++;
  }

  $sheet->setCellValue('F3', 'Datos del Certificado');
  $sheet->mergeCells('F3:H3');
  $sheet->getStyle('F3')->applyFromArray(xlsEstiloBloque());
  $sheet->getCell('F4')->setValueExplicit('Número');
  $sheet->getStyle('F4')->applyFromArray(xlsEstiloLabel());
  $sheet->getCell('G4')->setValueExplicit((int) $ca['nro_certificado']);
  $sheet->getCell('F5')->setValueExplicit('Revisión');
  $sheet->getStyle('F5')->applyFromArray(xlsEstiloLabel());
  $sheet->getCell('G5')->setValueExplicit((int) $ca['nro_revision']);
  $sheet->getCell('F6')->setValueExplicit('Fecha emisión');
  $sheet->getStyle('F6')->applyFromArray(xlsEstiloLabel());
  $sheet->getCell('G6')->setValueExplicit(date('d/m/Y', strtotime($ca['fecha_emision'])));

  $sheet->setCellValue('J3', 'Datos de la Medición');
  $sheet->mergeCells('J3:L3');
  $sheet->getStyle('J3')->applyFromArray(xlsEstiloBloque());
  $sheet->getCell('J4')->setValueExplicit('Periodo desde');
  $sheet->getStyle('J4')->applyFromArray(xlsEstiloLabel());
  $sheet->getCell('K4')->setValueExplicit(date('d/m/Y', strtotime($ca['fecha_inicio'])));
  $sheet->getCell('J5')->setValueExplicit('Periodo hasta');
  $sheet->getStyle('J5')->applyFromArray(xlsEstiloLabel());
  $sheet->getCell('K5')->setValueExplicit(date('d/m/Y', strtotime($ca['fecha_fin'])));

  $sheet->getCell('N3')->setValueExplicit('Proyecto');
  $sheet->getStyle('N3')->applyFromArray(xlsEstiloLabel());
  $sheet->getCell('N4')->setValueExplicit(htmlspecialchars_decode($proyectosNombre, ENT_QUOTES));
  $sheet->getStyle('N4')->getAlignment()->setWrapText(true);
  $sheet->mergeCells('N4:P6');

  $sheet->getCell('A8')->setValueExplicit('Pos');
  $sheet->getCell('B8')->setValueExplicit('Hito / Descripción');
  $sheet->getCell('C8')->setValueExplicit('Unidad');
  $sheet->getCell('D8')->setValueExplicit('Cantidad');
  $sheet->getCell('E8')->setValueExplicit('Incidencia (%)');
  $sheet->getCell('F8')->setValueExplicit('Precio Unitario');
  $sheet->getCell('G8')->setValueExplicit('Precio Total');
  $sheet->getCell('H8')->setValueExplicit('Anterior');
  $sheet->mergeCells('H8:J8');
  $sheet->getCell('K8')->setValueExplicit('Actual');
  $sheet->mergeCells('K8:M8');
  $sheet->getCell('N8')->setValueExplicit('Acumulado');
  $sheet->mergeCells('N8:P8');

  $subHeaders = ['Cant.', '%', 'Monto'];
  $colsGrupos = [['H', 'I', 'J'], ['K', 'L', 'M'], ['N', 'O', 'P']];
  foreach ($colsGrupos as $grupoCols) {
    foreach ($grupoCols as $i => $col) {
      $sheet->getCell($col . '9')->setValueExplicit($subHeaders[$i]);
    }
  }
  $sheet->getStyle('A8:P9')->applyFromArray(xlsEstiloTablaHeader());
  $sheet->getStyle('A8:P9')->applyFromArray(xlsBordes());

  $row = 10;
  $totales = [
    'cantidad_cm' => 0.0, 'total_cm' => 0.0,
    'cant_ant' => 0.0, 'monto_ant' => 0.0,
    'cant_act' => 0.0, 'monto_act' => 0.0,
    'cant_acu' => 0.0, 'monto_acu' => 0.0,
  ];

  foreach ($filas as $idx => $fila) {
    $cantidadCm = (float) ($fila['cantidad_cm'] ?? 0);
    $precioUnitario = (float) ($fila['precio_unitario_cm'] ?? 0);
    $subtotalCm = (float) ($fila['subtotal_cm'] ?? 0);
    $incidencia = (float) ($fila['incidencia_porcentaje'] ?? 0);
    $cantidadActual = (float) ($fila['cantidad_actual'] ?? 0);
    $cantidadAnterior = (float) ($fila['acumulado_previo'] ?? 0);
    $cantidadAcumulada = $cantidadAnterior + $cantidadActual;

    $pctAnterior = $cantidadCm > 0 ? ($cantidadAnterior / $cantidadCm) * 100 : 0;
    $pctActual = $cantidadCm > 0 ? ($cantidadActual / $cantidadCm) * 100 : 0;
    $pctAcumulado = $cantidadCm > 0 ? ($cantidadAcumulada / $cantidadCm) * 100 : 0;

    $montoAnterior = $cantidadAnterior * $precioUnitario;
    $montoActual = $cantidadActual * $precioUnitario;
    $montoAcumulado = $cantidadAcumulada * $precioUnitario;

    $totales['cantidad_cm'] += $cantidadCm;
    $totales['total_cm'] += $subtotalCm;
    $totales['cant_ant'] += $cantidadAnterior;
    $totales['monto_ant'] += $montoAnterior;
    $totales['cant_act'] += $cantidadActual;
    $totales['monto_act'] += $montoActual;
    $totales['cant_acu'] += $cantidadAcumulada;
    $totales['monto_acu'] += $montoAcumulado;

    $valores = [
      'A' => $idx + 1,
      'B' => $fila['descripcion'],
      'C' => $fila['unidad_medida'],
      'D' => $cantidadCm,
      'E' => round($incidencia, 2),
      'F' => $precioUnitario,
      'G' => $subtotalCm,
      'H' => $cantidadAnterior,
      'I' => round($pctAnterior, 2),
      'J' => $montoAnterior,
      'K' => $cantidadActual,
      'L' => round($pctActual, 2),
      'M' => $montoActual,
      'N' => $cantidadAcumulada,
      'O' => round($pctAcumulado, 2),
      'P' => $montoAcumulado,
    ];
    foreach ($valores as $col => $valor) {
      $sheet->getCell($col . $row)->setValueExplicit($valor);
    }
    foreach (['D', 'F', 'G', 'H', 'J', 'K', 'M', 'N', 'P'] as $colNum) {
      $sheet->getStyle($colNum . $row)->getNumberFormat()->setFormatCode($fmtNumero);
    }
    foreach (['E', 'I', 'L', 'O'] as $colPct) {
      $sheet->getStyle($colPct . $row)->getNumberFormat()->setFormatCode($fmtPorcentaje);
    }
    $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray(xlsBordes());
    $sheet->getStyle('B' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    $row++;
  }

  $sheet->getCell('A' . $row)->setValueExplicit('TOTALES');
  $sheet->mergeCells('A' . $row . ':C' . $row);
  $sheet->getCell('D' . $row)->setValueExplicit($totales['cantidad_cm']);
  $sheet->getCell('G' . $row)->setValueExplicit($totales['total_cm']);
  $sheet->getCell('H' . $row)->setValueExplicit($totales['cant_ant']);
  $sheet->getCell('J' . $row)->setValueExplicit($totales['monto_ant']);
  $sheet->getCell('K' . $row)->setValueExplicit($totales['cant_act']);
  $sheet->getCell('M' . $row)->setValueExplicit($totales['monto_act']);
  $sheet->getCell('N' . $row)->setValueExplicit($totales['cant_acu']);
  $sheet->getCell('P' . $row)->setValueExplicit($totales['monto_acu']);
  $sheet->getStyle('A' . $row . ':P' . $row)->getFont()->setBold(true);
  $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray(xlsBordes());
  foreach (['D', 'G', 'H', 'J', 'K', 'M', 'N', 'P'] as $colNum) {
    $sheet->getStyle($colNum . $row)->getNumberFormat()->setFormatCode($fmtNumero);
  }

  $rowDesacopio = $row + 2;
  $sheet->getCell('A' . $rowDesacopio)->setValueExplicit('Desacopio de anticipo:');
  $sheet->getStyle('A' . $rowDesacopio)->applyFromArray(xlsEstiloLabel());
  $sheet->mergeCells('A' . $rowDesacopio . ':B' . $rowDesacopio);
  $sheet->getCell('C' . $rowDesacopio)->setValueExplicit(-1 * (float) ($ca['monto_acumulado_desacopios'] ?? 0));
  $sheet->getStyle('C' . $rowDesacopio)->getNumberFormat()->setFormatCode($fmtNumero);

  $sheet->getCell('E' . $rowDesacopio)->setValueExplicit('Total Certificado:');
  $sheet->getStyle('E' . $rowDesacopio)->applyFromArray(xlsEstiloLabel());
  $sheet->mergeCells('E' . $rowDesacopio . ':F' . $rowDesacopio);
  $sheet->getCell('G' . $rowDesacopio)->setValueExplicit((float) ($ca['monto_total'] ?? 0));
  $sheet->getStyle('G' . $rowDesacopio)->getNumberFormat()->setFormatCode($fmtNumero);

  $rowFirmas = $rowDesacopio + 3;
  $sheet->getCell('A' . $rowFirmas)->setValueExplicit('FIRMA NH');
  $sheet->getStyle('A' . $rowFirmas)->getFont()->setBold(true);
  $sheet->getCell('F' . $rowFirmas)->setValueExplicit('FIRMA CLIENTE');
  $sheet->getStyle('F' . $rowFirmas)->getFont()->setBold(true);
  $sheet->getCell('K' . $rowFirmas)->setValueExplicit('FIRMA OTRO');
  $sheet->getStyle('K' . $rowFirmas)->getFont()->setBold(true);

  xlsAnchos($sheet);
  xlsPageSetup($sheet, 8, 9);
}

foreach ($certificados as $index => $ca) {
  $qDetalle->execute([(int) $ca['id'], $id_certificado_maestro, (int) $ca['nro_certificado'], $id_certificado_maestro]);
  $filas = $qDetalle->fetchAll(PDO::FETCH_ASSOC);
  $qDetalle->closeCursor();
  xlsHojaCA($objPHPExcel, $ca, $filas, $proyectosNombre, $index);
}

// ---------------------------------------------------------------------------
// Hoja "Totalizado": consolida por ítem las últimas revisiones de todos los CAs.
// ---------------------------------------------------------------------------
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$q = $pdo->prepare($sqlTotAcum);
$q->execute([$id_certificado_maestro]);
$mapAcum = [];
foreach ($q as $r) {
  $mapAcum[(int) $r['id_certificado_maestro_detalle']] = (float) $r['acumulado'];
}

$sqlUltimoCa = "SELECT id FROM certificados_avances_cabecera
                WHERE id_certificado_maestro = ?
                ORDER BY nro_certificado DESC, nro_revision DESC LIMIT 1";
$q = $pdo->prepare($sqlUltimoCa);
$q->execute([$id_certificado_maestro]);
$idUltimoCa = (int) ($q->fetchColumn() ?: 0);

$mapUlt = [];
if ($idUltimoCa > 0) {
  $sqlUltAct = "SELECT id_certificado_maestro_detalle, SUM(cantidad_actual) AS cant
                FROM certificados_avances_detalle
                WHERE id_certificado_avance = ?
                GROUP BY id_certificado_maestro_detalle";
  $q = $pdo->prepare($sqlUltAct);
  $q->execute([$idUltimoCa]);
  foreach ($q as $r) {
    $mapUlt[(int) $r['id_certificado_maestro_detalle']] = (float) $r['cant'];
  }
}

$q = $pdo->prepare($sqlBaseItems);
$q->execute([$id_certificado_maestro]);
$itemsBase = $q->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

$sheet = $objPHPExcel->createSheet();
$sheet->setTitle(substr('Totalizado', 0, 31));
$sheet->setShowGridLines(false);

$moneda = (string) ($cm['moneda'] ?? '');

$sheet->setCellValue('A1', 'CERTIFICADO MAESTRO - TOTALIZADO');
$sheet->mergeCells('A1:P1');
$sheet->getStyle('A1')->applyFromArray(xlsEstiloTitulo());

$sheet->setCellValue('A3', 'Datos del Proyecto y Orden de Compra');
$sheet->mergeCells('A3:D3');
$sheet->getStyle('A3')->applyFromArray(xlsEstiloBloque());
$paresCm = [
  ['Cliente', $cm['cliente']],
  ['Orden de Compra', $cm['numero_occ']],
  ['Moneda y Monto CM', $moneda . ' ' . number_format((float) ($cm['monto_total'] ?? 0), 2, ',', '.')],
];
$f = 4;
foreach ($paresCm as $par) {
  $sheet->getCell('A' . $f)->setValueExplicit($par[0]);
  $sheet->getStyle('A' . $f)->applyFromArray(xlsEstiloLabel());
  $sheet->getCell('B' . $f)->setValueExplicit($par[1]);
  $f++;
}

$sheet->setCellValue('F3', 'Datos del Certificado Maestro');
$sheet->mergeCells('F3:H3');
$sheet->getStyle('F3')->applyFromArray(xlsEstiloBloque());
$sheet->getCell('F4')->setValueExplicit('Número CM');
$sheet->getStyle('F4')->applyFromArray(xlsEstiloLabel());
$sheet->getCell('G4')->setValueExplicit('#' . $id_certificado_maestro);
$sheet->getCell('F5')->setValueExplicit('Fecha emisión');
$sheet->getStyle('F5')->applyFromArray(xlsEstiloLabel());
$sheet->getCell('G5')->setValueExplicit((string) $cm['fecha_emision']);
$sheet->getCell('F6')->setValueExplicit('Estado');
$sheet->getStyle('F6')->applyFromArray(xlsEstiloLabel());
$sheet->getCell('G6')->setValueExplicit(((int) ($cm['aprobado_cliente'] ?? 0) === 1) ? 'Aprobado' : 'Pendiente');

$sheet->setCellValue('J3', 'Proyecto');
$sheet->mergeCells('J3:L3');
$sheet->getStyle('J3')->applyFromArray(xlsEstiloBloque());
$sheet->getCell('J4')->setValueExplicit(htmlspecialchars_decode($proyectosNombre, ENT_QUOTES));
$sheet->getStyle('J4')->getAlignment()->setWrapText(true);
$sheet->mergeCells('J4:P6');

$sheet->getCell('A8')->setValueExplicit('Pos');
$sheet->getCell('B8')->setValueExplicit('Hito / Descripción');
$sheet->getCell('C8')->setValueExplicit('Unidad');
$sheet->getCell('D8')->setValueExplicit('Cantidad');
$sheet->getCell('E8')->setValueExplicit('Incidencia (%)');
$sheet->getCell('F8')->setValueExplicit('Precio Unitario');
$sheet->getCell('G8')->setValueExplicit('Precio Total');
$sheet->getCell('H8')->setValueExplicit('Anterior');
$sheet->mergeCells('H8:J8');
$sheet->getCell('K8')->setValueExplicit('Actual');
$sheet->mergeCells('K8:M8');
$sheet->getCell('N8')->setValueExplicit('Acumulado');
$sheet->mergeCells('N8:P8');
$subHeaders = ['Cant.', '%', 'Monto'];
$colsGrupos = [['H', 'I', 'J'], ['K', 'L', 'M'], ['N', 'O', 'P']];
foreach ($colsGrupos as $grupoCols) {
  foreach ($grupoCols as $i => $col) {
    $sheet->getCell($col . '9')->setValueExplicit($subHeaders[$i]);
  }
}
$sheet->getStyle('A8:P9')->applyFromArray(xlsEstiloTablaHeader());
$sheet->getStyle('A8:P9')->applyFromArray(xlsBordes());

$row = 10;
$tot = [
  'cantidad_cm' => 0.0, 'total_cm' => 0.0,
  'cant_ant' => 0.0, 'monto_ant' => 0.0,
  'cant_act' => 0.0, 'monto_act' => 0.0,
  'cant_acu' => 0.0, 'monto_acu' => 0.0,
];

foreach ($itemsBase as $idx => $item) {
  $cmdId = (int) $item['id'];
  $cantidadCm = (float) ($item['cantidad_cm'] ?? 0);
  $precioUnitario = (float) ($item['precio_unitario_cm'] ?? 0);
  $subtotalCm = (float) ($item['subtotal_cm'] ?? 0);
  $incidencia = (float) ($item['incidencia_porcentaje'] ?? 0);

  $acumulado = (float) ($mapAcum[$cmdId] ?? 0);
  $ultimo = (float) ($mapUlt[$cmdId] ?? 0);
  $anterior = max(0, $acumulado - $ultimo);

  $pctAnterior = $cantidadCm > 0 ? ($anterior / $cantidadCm) * 100 : 0;
  $pctUltimo = $cantidadCm > 0 ? ($ultimo / $cantidadCm) * 100 : 0;
  $pctAcumulado = $cantidadCm > 0 ? ($acumulado / $cantidadCm) * 100 : 0;

  $tot['cantidad_cm'] += $cantidadCm;
  $tot['total_cm'] += $subtotalCm;
  $tot['cant_ant'] += $anterior;
  $tot['monto_ant'] += $anterior * $precioUnitario;
  $tot['cant_act'] += $ultimo;
  $tot['monto_act'] += $ultimo * $precioUnitario;
  $tot['cant_acu'] += $acumulado;
  $tot['monto_acu'] += $acumulado * $precioUnitario;

  $valores = [
    'A' => $idx + 1,
    'B' => $item['descripcion'],
    'C' => $item['unidad_medida'],
    'D' => $cantidadCm,
    'E' => round($incidencia, 2),
    'F' => $precioUnitario,
    'G' => $subtotalCm,
    'H' => $anterior,
    'I' => round($pctAnterior, 2),
    'J' => $anterior * $precioUnitario,
    'K' => $ultimo,
    'L' => round($pctUltimo, 2),
    'M' => $ultimo * $precioUnitario,
    'N' => $acumulado,
    'O' => round($pctAcumulado, 2),
    'P' => $acumulado * $precioUnitario,
  ];
  foreach ($valores as $col => $valor) {
    $sheet->getCell($col . $row)->setValueExplicit($valor);
  }
  foreach (['D', 'F', 'G', 'H', 'J', 'K', 'M', 'N', 'P'] as $colNum) {
    $sheet->getStyle($colNum . $row)->getNumberFormat()->setFormatCode($fmtNumero);
  }
  foreach (['E', 'I', 'L', 'O'] as $colPct) {
    $sheet->getStyle($colPct . $row)->getNumberFormat()->setFormatCode($fmtPorcentaje);
  }
  $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray(xlsBordes());
  $sheet->getStyle('B' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
  $row++;
}

$sheet->getCell('A' . $row)->setValueExplicit('TOTALES');
$sheet->mergeCells('A' . $row . ':C' . $row);
$sheet->getCell('D' . $row)->setValueExplicit($tot['cantidad_cm']);
$sheet->getCell('G' . $row)->setValueExplicit($tot['total_cm']);
$sheet->getCell('H' . $row)->setValueExplicit($tot['cant_ant']);
$sheet->getCell('J' . $row)->setValueExplicit($tot['monto_ant']);
$sheet->getCell('K' . $row)->setValueExplicit($tot['cant_act']);
$sheet->getCell('M' . $row)->setValueExplicit($tot['monto_act']);
$sheet->getCell('N' . $row)->setValueExplicit($tot['cant_acu']);
$sheet->getCell('P' . $row)->setValueExplicit($tot['monto_acu']);
$sheet->getStyle('A' . $row . ':P' . $row)->getFont()->setBold(true);
$sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray(xlsBordes());
foreach (['D', 'G', 'H', 'J', 'K', 'M', 'N', 'P'] as $colNum) {
  $sheet->getStyle($colNum . $row)->getNumberFormat()->setFormatCode($fmtNumero);
}

$rowDesacopio = $row + 2;
$sheet->getCell('A' . $rowDesacopio)->setValueExplicit('Desacopio de anticipo:');
$sheet->getStyle('A' . $rowDesacopio)->applyFromArray(xlsEstiloLabel());
$sheet->mergeCells('A' . $rowDesacopio . ':B' . $rowDesacopio);
$sheet->getCell('C' . $rowDesacopio)->setValueExplicit(-1 * (float) ($cm['monto_acumulado_anticipos'] ?? 0));
$sheet->getStyle('C' . $rowDesacopio)->getNumberFormat()->setFormatCode($fmtNumero);

$sheet->getCell('E' . $rowDesacopio)->setValueExplicit('Total Certificado:');
$sheet->getStyle('E' . $rowDesacopio)->applyFromArray(xlsEstiloLabel());
$sheet->mergeCells('E' . $rowDesacopio . ':F' . $rowDesacopio);
$sheet->getCell('G' . $rowDesacopio)->setValueExplicit((float) ($cm['monto_total'] ?? 0));
$sheet->getStyle('G' . $rowDesacopio)->getNumberFormat()->setFormatCode($fmtNumero);

$rowRedet = $rowDesacopio + 1;
$sheet->getCell('A' . $rowRedet)->setValueExplicit('Redeterminaciones acumuladas:');
$sheet->getStyle('A' . $rowRedet)->applyFromArray(xlsEstiloLabel());
$sheet->mergeCells('A' . $rowRedet . ':B' . $rowRedet);
$sheet->getCell('C' . $rowRedet)->setValueExplicit((float) ($cm['monto_acumulado_ajustes'] ?? 0));
$sheet->getStyle('C' . $rowRedet)->getNumberFormat()->setFormatCode($fmtNumero);

$rowFirmas = $rowRedet + 3;
$sheet->getCell('A' . $rowFirmas)->setValueExplicit('FIRMA NH');
$sheet->getStyle('A' . $rowFirmas)->getFont()->setBold(true);
$sheet->getCell('F' . $rowFirmas)->setValueExplicit('FIRMA CLIENTE');
$sheet->getStyle('F' . $rowFirmas)->getFont()->setBold(true);
$sheet->getCell('K' . $rowFirmas)->setValueExplicit('FIRMA OTRO');
$sheet->getStyle('K' . $rowFirmas)->getFont()->setBold(true);

xlsAnchos($sheet);
xlsPageSetup($sheet, 8, 9);

$filename = 'certificados_maestros_CM' . $id_certificado_maestro . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($objPHPExcel);
$writer->save('php://output');
exit;
