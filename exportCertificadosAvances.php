<?php
require 'config.php';
require 'database.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$idCA = filter_input(INPUT_GET, 'id_certificado_avance', FILTER_VALIDATE_INT);
$idCM = filter_input(INPUT_GET, 'id_certificado_maestro', FILTER_VALIDATE_INT);
if (!$idCA || !$idCM) { http_response_code(400); die('Certificado de Avance o Maestro inválido.'); }
if (function_exists('esOperacionesSinEconomico') && esOperacionesSinEconomico()) { http_response_code(403); die('No tiene permisos para exportar el Certificado de Avance.'); }

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Datos del Certificado de Avance (cabecera)
$sqlCA = "SELECT cac.id, cac.id_certificado_maestro, cac.nro_certificado, cac.nro_revision,
                 cac.fecha_emision, cac.fecha_inicio, cac.fecha_fin,
                 cac.cotizacion_dolar, cac.monto_total,
                 cac.monto_acumulado_avances, cac.monto_acumulado_anticipos,
                 cac.monto_acumulado_desacopios, cac.monto_acumulado_descuentos,
                 cac.monto_acumulado_ajustes, cac.aprobado_cliente,
                 cm.id_occ, cm.monto_total AS monto_total_cm,
                 cm.porcentaje_anticipo, cm.monto_acumulado_avances AS cm_acum_avances,
                 occ.numero AS numero_occ, cu.nombre AS cliente, m.moneda
          FROM certificados_avances_cabecera cac
          INNER JOIN certificados_maestros cm ON cm.id = cac.id_certificado_maestro
          INNER JOIN occ ON occ.id = cm.id_occ
          INNER JOIN cuentas cu ON cu.id = occ.id_cuenta_cliente
          INNER JOIN monedas m ON m.id = cm.id_moneda
          WHERE cac.id = ? AND cac.id_certificado_maestro = ?";
$qCA = $pdo->prepare($sqlCA);
$qCA->execute([$idCA, $idCM]);
$ca = $qCA->fetch(PDO::FETCH_ASSOC);
if (!$ca) { Database::disconnect(); http_response_code(404); die('Certificado de Avance no encontrado.'); }

$idOcc = (int) $ca['id_occ'];
$nroCA = (int) $ca['nro_certificado'];

// 2. Proyectos agrupados
$qProy = $pdo->prepare("SELECT GROUP_CONCAT(DISTINCT p.nombre SEPARATOR ', ') FROM certificados_maestros_detalles cmd LEFT JOIN proyectos p ON p.id=cmd.id_proyecto WHERE cmd.id_certificado_maestro=?");
$qProy->execute([$idCM]);
$proyectosNombre = (string)($qProy->fetchColumn() ?: '-');

// 3. Obtener todos los ítems del OCC
$qOcc = $pdo->prepare("SELECT id, posicion, descripcion, cantidad, precio_unitario, descuento, subtotal FROM occ_detalles WHERE id_occ=? ORDER BY posicion, id");
$qOcc->execute([$idOcc]);
$occRows = $qOcc->fetchAll(PDO::FETCH_ASSOC);
$occMap = [];
foreach ($occRows as $or) { $occMap[(int)$or['id']] = $or; }

// 4. Obtener los CMD con sus avances (usando cantidad_acumulado guardado)
$sqlCmd = "SELECT cmd.id AS cmd_id, cmd.id_occ_detalle, cmd.descripcion, cmd.cantidad,
                  cmd.id_unidad_medida, um.unidad_medida,
                  cmd.precio_unitario, cmd.subtotal,
                  cmd.incidencia_porcentaje, cmd.monto_base_occ,
                  cmd.aperturado, cmd.lote, cmd.modo_generacion,
                  cad.cantidad_actual,
                  cad.cantidad_acumulado AS acumulado_actual
           FROM certificados_maestros_detalles cmd
           LEFT JOIN unidades_medida um ON um.id = cmd.id_unidad_medida
           LEFT JOIN certificados_avances_detalle cad
                  ON cad.id_certificado_maestro_detalle = cmd.id
                 AND cad.id_certificado_avance = ?
           WHERE cmd.id_certificado_maestro = ?
           ORDER BY cmd.lote, cmd.aperturado, cmd.id";
$qCmd = $pdo->prepare($sqlCmd);
$qCmd->execute([$idCA, $idCM]);
$cmdRows = $qCmd->fetchAll(PDO::FETCH_ASSOC);

// 5. Relaciones OCC (grupos por aperturado)
$qRel = $pdo->prepare("SELECT aperturado, id_occ_detalle FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro=? ORDER BY id");
$qRel->execute([$idCM]);
$relaciones = $qRel->fetchAll(PDO::FETCH_ASSOC);
$occIdsPorAperturado = [];
foreach ($relaciones as $r) {
    $ap = (string)$r['aperturado'];
    $oid = (int)$r['id_occ_detalle'];
    $occIdsPorAperturado[$ap][$oid] = $oid;
}

// 6. Construir grupos
$gruposAperturado = [];
foreach ($cmdRows as $fila) {
    $aperturado = trim((string)($fila['aperturado'] ?? ''));
    $clave = $aperturado !== '' ? $aperturado : 'legacy-' . $fila['cmd_id'];
    if (!isset($gruposAperturado[$clave])) {
        $gruposAperturado[$clave] = [
            'aperturado' => $aperturado,
            'lote' => (string)($fila['lote'] ?? ''),
            'modo_generacion' => (string)($fila['modo_generacion'] ?? 'legacy'),
            'monto_base_occ' => (float)($fila['monto_base_occ'] ?? 0),
            'subtotal_cm' => 0.0,
            'occ_ids' => [],
            'filas' => [],
        ];
    }
    $gruposAperturado[$clave]['subtotal_cm'] += (float)$fila['subtotal'];
    $gruposAperturado[$clave]['filas'][] = $fila;
    $oid = (int)($fila['id_occ_detalle'] ?? 0);
    if ($oid > 0) { $gruposAperturado[$clave]['occ_ids'][$oid] = $oid; }
}
foreach ($gruposAperturado as $clave => &$g) {
    $ap = $g['aperturado'];
    if ($ap !== '' && !empty($occIdsPorAperturado[$ap])) {
        $g['occ_ids'] = $occIdsPorAperturado[$ap];
    }
    $g['occ_ids'] = array_values($g['occ_ids']);
}
unset($g);

// 7. Orden de grupos y occ_ids
$grupos = [];
$ordenOccIds = [];
$vistos = [];
foreach ($gruposAperturado as $clave => $g) {
    if (empty($g['occ_ids'])) continue;
    $grupos[] = $g;
    foreach ($g['occ_ids'] as $oid) {
        if (!isset($vistos[$oid])) { $vistos[$oid] = 1; $ordenOccIds[] = $oid; }
    }
}
// Huérfanos
foreach ($occRows as $or) {
    $oid = (int)$or['id'];
    if (!isset($vistos[$oid])) {
        $vistos[$oid] = 1;
        $ordenOccIds[] = $oid;
        $grupos[] = [
            'aperturado' => '',
            'lote' => '',
            'modo_generacion' => 'legacy',
            'monto_base_occ' => 0,
            'subtotal_cm' => (float)$or['subtotal'],
            'occ_ids' => [$oid],
            'filas' => [],
        ];
    }
}
if (empty($ordenOccIds)) {
    foreach ($occRows as $or) { $ordenOccIds[] = (int)$or['id']; }
}

Database::disconnect();

// Función de formato de fecha
function fmtFecha($v) {
    if (empty($v) || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return '-';
    $t = strtotime($v);
    return $t ? date('d/m/Y', $t) : '-';
}

// ============================================================
// ESTILOS (idénticos a CM)
// ============================================================
$greyHeader = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BFBFBF']],
    'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => '000000']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
];
$celesteData = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9BC2E6']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
];
$yellowLight = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE080']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
];
$thinBorder = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]]];
$boxOutline = ['borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]];
$fmtNum = '#,##0.00';
$fmtPct = '0.00%';
$moneda = trim($ca['moneda'] ?? 'U$S');
$fmtMoney = '"' . str_replace('"','',$moneda) . '" #,##0.00';

function cmRowBorder($sheet, $rowNum, $colStart, $colEnd, $style = Border::BORDER_THIN, $color = 'B0B0B0') {
    $start = ord($colStart); $end = ord($colEnd);
    for ($c = $start; $c <= $end; $c++) {
        $col = chr($c);
        $borders = $sheet->getStyle($col . $rowNum)->getBorders();
        $borders->getTop()->setBorderStyle($style)->getColor()->setRGB($color);
        $borders->getBottom()->setBorderStyle($style)->getColor()->setRGB($color);
        $borders->getLeft()->setBorderStyle($style)->getColor()->setRGB($color);
        $borders->getRight()->setBorderStyle($style)->getColor()->setRGB($color);
    }
}

// ============================================================
// CREAR LIBRO
// ============================================================
$book = new Spreadsheet();
$book->getProperties()->setCreator('Grupo NH')->setTitle('Certificado de Avance CA' . $idCA);

// ==================== HOJA 1: Certificado 1 ====================
$sheet = $book->getActiveSheet();
$sheet->setTitle('Certificado 1');
$sheet->setShowGridLines(false);
$sheet->getSheetView()->setZoomScale(70);

$logoPath = __DIR__ . '/assets/images/logo.jpg';
if (is_file($logoPath)) {
    $d = new Drawing();
    $d->setPath($logoPath);
    $d->setHeight(48);
    $d->setCoordinates('A1');
    $d->setWorksheet($sheet);
}

$wCert = ['A' => 5.125, 'B' => 26.625, 'C' => 7.625, 'D' => 11.125, 'E' => 8.625, 'F' => 15.875, 'G' => 16.375,
          'H' => 8.625, 'I' => 6.125, 'J' => 14.625, 'K' => 8.625, 'L' => 9, 'M' => 16.375, 'N' => 8.625, 'O' => 6.75, 'P' => 16.375];
foreach ($wCert as $c => $w) $sheet->getColumnDimension($c)->setWidth($w);
$sheet->getColumnDimension('Q')->setWidth(11);

$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(2);
$sheet->getPageSetup()->setScale(77);
$sheet->getHeaderFooter()->setOddFooter('&RPágina &P de &N');
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(8, 9);

for ($r = 1; $r <= 6; $r++) $sheet->getRowDimension($r)->setRowHeight(11.1);
$sheet->getRowDimension(7)->setRowHeight(14);
$sheet->getRowDimension(15)->setRowHeight(28.5);
$sheet->getRowDimension(16)->setRowHeight(12.75);

// Título
$sheet->setCellValue('C1', 'CERTIFICADO DE AVANCE');
$sheet->getStyle('C1')->getFont()->setBold(true)->setSize(14)->setName('Cambria');
$sheet->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->mergeCells('C1:M6');
$sheet->mergeCells('A1:B6');
$sheet->mergeCells('N1:P6');
$sheet->getStyle('A1:P6')->applyFromArray($thinBorder);
$sheet->getStyle('C1:M6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

// Bloque izquierdo: Datos del Proyecto y OCC
$sheet->setCellValue('A8', 'Datos del Proyecto y Orden de Compra');
$sheet->mergeCells('A8:G8');
$sheet->getStyle('A8')->applyFromArray($greyHeader);
$sheet->setCellValue('A9', 'Cliente'); $sheet->setCellValue('C9', $ca['cliente']); $sheet->mergeCells('A9:B9'); $sheet->mergeCells('C9:G9');
$sheet->setCellValue('A10', 'Proyecto'); $sheet->setCellValue('C10', $proyectosNombre); $sheet->mergeCells('A10:B10'); $sheet->mergeCells('C10:G10');
$sheet->setCellValue('A11', 'Orden de Compra'); $sheet->setCellValue('C11', $ca['numero_occ']); $sheet->mergeCells('A11:B11'); $sheet->mergeCells('C11:G11');
$sheet->setCellValue('A12', 'Moneda y Monto OCC'); $sheet->setCellValue('C12', $ca['moneda'] . ' ' . number_format((float)$ca['monto_total_cm'], 2, ',', '.')); $sheet->mergeCells('A12:B12'); $sheet->mergeCells('C12:G12');
$sheet->setCellValue('A13', 'Fecha CM'); $sheet->setCellValue('C13', fmtFecha($ca['fecha_emision'])); $sheet->mergeCells('A13:B13'); $sheet->mergeCells('C13:G13');
foreach (['A9','A10','A11','A12','A13'] as $c) $sheet->getStyle($c)->applyFromArray($greyHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
foreach (['C9','C10','C11','C12','C13'] as $c) $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C13')->getNumberFormat()->setFormatCode('dd/mm/yy;@');

// Bloque derecho: Datos del CA
$sheet->setCellValue('K8', 'Datos del Certificado de Avance');
$sheet->mergeCells('K8:P8');
$sheet->getStyle('K8')->applyFromArray($greyHeader);
$sheet->setCellValue('K9', 'Número CA'); $sheet->setCellValue('N9', $ca['nro_certificado']); $sheet->mergeCells('K9:M9'); $sheet->mergeCells('N9:P9');
$sheet->setCellValue('K10', 'Revisión'); $sheet->setCellValue('N10', $ca['nro_revision']); $sheet->mergeCells('K10:M10'); $sheet->mergeCells('N10:P10');
$sheet->setCellValue('K11', 'Periodo desde'); $sheet->setCellValue('N11', fmtFecha($ca['fecha_inicio'])); $sheet->mergeCells('K11:M11'); $sheet->mergeCells('N11:P11');
$sheet->setCellValue('K12', 'Periodo hasta'); $sheet->setCellValue('N12', fmtFecha($ca['fecha_fin'])); $sheet->mergeCells('K12:M12'); $sheet->mergeCells('N12:P12');
$sheet->setCellValue('K13', 'Fecha emisión'); $sheet->setCellValue('N13', fmtFecha($ca['fecha_emision'])); $sheet->mergeCells('K13:M13'); $sheet->mergeCells('N13:P13');
foreach (['K9','K10','K11','K12','K13'] as $c) $sheet->getStyle($c)->applyFromArray($greyHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
foreach (['N9','N10','N11','N12','N13'] as $c) $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('N11')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$sheet->getStyle('N12')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$sheet->getStyle('A8:G13')->applyFromArray($thinBorder);
$sheet->getStyle('K8:P13')->applyFromArray($thinBorder);

// Header de la tabla (filas 15-16)
$sheet->setCellValue('A15', 'Pos'); $sheet->mergeCells('A15:A16');
$sheet->setCellValue('B15', 'Descripción'); $sheet->mergeCells('B15:B16');
$sheet->setCellValue('C15', 'Unidad'); $sheet->mergeCells('C15:C16');
$sheet->setCellValue('D15', 'Cantidad'); $sheet->mergeCells('D15:D16');
$sheet->setCellValue('E15', 'Incidencia (%)'); $sheet->mergeCells('E15:E16');
$sheet->setCellValue('F15', 'Precio Unitario'); $sheet->mergeCells('F15:F16');
$sheet->setCellValue('G15', 'Precio Total'); $sheet->mergeCells('G15:G16');
$sheet->setCellValue('H15', 'Anterior'); $sheet->mergeCells('H15:J15');
$sheet->setCellValue('K15', 'Actual'); $sheet->mergeCells('K15:M15');
$sheet->setCellValue('N15', 'Acumulado'); $sheet->mergeCells('N15:P15');
$sheet->getStyle('A15:P15')->getFont()->setBold(true)->setSize(10);
$sheet->getStyle('A15:P15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->setCellValue('H16', 'Cantidad'); $sheet->setCellValue('I16', '%'); $sheet->setCellValue('J16', 'Monto');
$sheet->setCellValue('K16', 'Cantidad'); $sheet->setCellValue('L16', '%'); $sheet->setCellValue('M16', 'Monto');
$sheet->setCellValue('N16', 'Cantidad'); $sheet->setCellValue('O16', '%'); $sheet->setCellValue('P16', 'Monto');
$sheet->getStyle('H16:P16')->getFont()->setBold(true)->setSize(8);
$sheet->getStyle('H16:P16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A15:P16')->applyFromArray($thinBorder);
$sheet->getStyle('G15:G16')->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
$sheet->getStyle('P15:P16')->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
$sheet->getStyle('A15:P15')->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');

// ============================================================
// RENDER DATOS Y CÁLCULO DE TOTALES GLOBALES
// ============================================================
$row = 17;
$totalMontoCM = 0;
$totalAntMonto = 0;
$totalActMonto = 0;
$totalAcuMonto = 0;
$totalCantCM = 0;
$totalAntCant = 0;
$totalActCant = 0;
$totalAcuCant = 0;

foreach ($grupos as $g) {
    // --- Renderizar padres (occ_ids) ---
    foreach ($g['occ_ids'] as $oid) {
        $occ = $occMap[$oid] ?? null;
        if (!$occ) continue;

        $esAgrupado = ($g['modo_generacion'] === 'agrupar');

        if ($esAgrupado) {
            // Para grupos agrupados: el padre muestra los totales del grupo (suma de todas sus filas)
            $cant = 0;
            $pu = 0;
            $pt = 0;
            $antCant = 0; $actCant = 0; $acuCant = 0;
            $antMonto = 0; $actMonto = 0; $acuMonto = 0;

            foreach ($g['filas'] as $f) {
                $cantFila = (float)($f['cantidad'] ?? 0);
                $puFila = (float)($f['precio_unitario'] ?? 0);
                $subtotalFila = (float)($f['subtotal'] ?? 0);
                $cantActual = (float)($f['cantidad_actual'] ?? 0);
                $acumuladoActual = (float)($f['acumulado_actual'] ?? 0);
                $anterior = $acumuladoActual - $cantActual;

                $cant += $cantFila;
                $pt += $subtotalFila;
                $antCant += $anterior;
                $actCant += $cantActual;
                $acuCant += $acumuladoActual;
                $antMonto += $anterior * $puFila;
                $actMonto += $cantActual * $puFila;
                $acuMonto += $acumuladoActual * $puFila;
            }
            $pu = ($cant > 0) ? ($pt / $cant) : 0;
            $pos = $occ['posicion'];
            $desc = (string)$occ['descripcion'];
        } else {
            // Comportamiento original: mostrar datos del OCC
            $pos = $occ['posicion'];
            $desc = (string)$occ['descripcion'];
            $cant = (float)$occ['cantidad'];
            $pu = (float)$occ['precio_unitario'];
            $pt = (float)$occ['subtotal'];

            // Sumar solo las filas que coinciden con este occ_id
            $antCant = 0; $actCant = 0; $acuCant = 0;
            $antMonto = 0; $actMonto = 0; $acuMonto = 0;
            foreach ($g['filas'] as $f) {
                if ((int)($f['id_occ_detalle'] ?? 0) === $oid) {
                    $cantActual = (float)($f['cantidad_actual'] ?? 0);
                    $acumuladoActual = (float)($f['acumulado_actual'] ?? 0);
                    $anterior = $acumuladoActual - $cantActual;
                    $puFila = (float)($f['precio_unitario'] ?? 0);
                    $antCant += $anterior;
                    $actCant += $cantActual;
                    $acuCant += $acumuladoActual;
                    $antMonto += $anterior * $puFila;
                    $actMonto += $cantActual * $puFila;
                    $acuMonto += $acumuladoActual * $puFila;
                }
            }
        }

        // Escribir la fila del padre
        $sheet->setCellValue('A' . $row, $pos);
        $sheet->setCellValue('B' . $row, $desc);
        $sheet->setCellValue('C' . $row, '');
        $sheet->setCellValue('D' . $row, $cant);
        $sheet->setCellValue('E' . $row, 0);
        $sheet->setCellValue('F' . $row, $pu);
        $sheet->setCellValue('G' . $row, $pt);
        $sheet->setCellValue('H' . $row, $antCant);
        $sheet->setCellValue('I' . $row, ($cant > 0) ? ($antCant / $cant) : 0);
        $sheet->setCellValue('J' . $row, $antMonto);
        $sheet->setCellValue('K' . $row, $actCant);
        $sheet->setCellValue('L' . $row, ($cant > 0) ? ($actCant / $cant) : 0);
        $sheet->setCellValue('M' . $row, $actMonto);
        $sheet->setCellValue('N' . $row, $acuCant);
        $sheet->setCellValue('O' . $row, ($cant > 0) ? ($acuCant / $cant) : 0);
        $sheet->setCellValue('P' . $row, $acuMonto);

        // Estilos
        $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray($thinBorder);
        $sheet->getStyle('C' . $row . ':F' . $row)->applyFromArray($celesteData);
        $sheet->getStyle('H' . $row . ':P' . $row)->applyFromArray($celesteData);
        $sheet->getStyle('A' . $row)->applyFromArray($yellowLight);
        $sheet->getStyle('B' . $row)->applyFromArray($yellowLight);
        $sheet->getStyle('G' . $row)->applyFromArray($yellowLight);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        foreach (['D','F','G','H','J','K','M','N','P'] as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode($fmtNum);
        }
        foreach (['I','L','O'] as $col) {
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode($fmtPct);
        }
        $sheet->getRowDimension($row)->setRowHeight(14.25);
        $row++;
    }

    // --- Desglose (filas del grupo) ---
    if (!empty($g['filas'])) {
        $ownerPos = $occMap[$g['occ_ids'][0]]['posicion'] ?? '10';
        foreach ($g['filas'] as $fi => $fila) {
            $posDes = $ownerPos . '.' . ($fi + 1);
            $desc = (string)$fila['descripcion'];
            $unidad = (string)($fila['unidad_medida'] ?? '');
            $cant = (float)$fila['cantidad'];
            $inc = (float)$fila['incidencia_porcentaje'];
            $pu = (float)$fila['precio_unitario'];
            $pt = (float)$fila['subtotal'];
            $antCant = (float)$fila['acumulado_actual'] - (float)$fila['cantidad_actual'];
            $actCant = (float)$fila['cantidad_actual'];
            $acuCant = (float)$fila['acumulado_actual'];
            $antMonto = $antCant * $pu;
            $actMonto = $actCant * $pu;
            $acuMonto = $acuCant * $pu;

            // Acumular totales globales (siempre usar los hijos para los totales)
            $totalMontoCM += $pt;
            $totalAntMonto += $antMonto;
            $totalActMonto += $actMonto;
            $totalAcuMonto += $acuMonto;
            $totalCantCM += $cant;
            $totalAntCant += $antCant;
            $totalActCant += $actCant;
            $totalAcuCant += $acuCant;

            $sheet->setCellValue('A' . $row, $posDes);
            $sheet->setCellValue('B' . $row, $desc);
            $sheet->setCellValue('C' . $row, $unidad);
            $sheet->setCellValue('D' . $row, $cant);
            $sheet->setCellValue('E' . $row, $inc / 100);
            $sheet->setCellValue('F' . $row, $pu);
            $sheet->setCellValue('G' . $row, $pt);
            $sheet->setCellValue('H' . $row, $antCant);
            $sheet->setCellValue('I' . $row, ($cant > 0) ? ($antCant / $cant) : 0);
            $sheet->setCellValue('J' . $row, $antMonto);
            $sheet->setCellValue('K' . $row, $actCant);
            $sheet->setCellValue('L' . $row, ($cant > 0) ? ($actCant / $cant) : 0);
            $sheet->setCellValue('M' . $row, $actMonto);
            $sheet->setCellValue('N' . $row, $acuCant);
            $sheet->setCellValue('O' . $row, ($cant > 0) ? ($acuCant / $cant) : 0);
            $sheet->setCellValue('P' . $row, $acuMonto);

            $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray($thinBorder);
            $sheet->getStyle('A' . $row)->applyFromArray($yellowLight);
            $sheet->getStyle('B' . $row)->applyFromArray($yellowLight);
            $sheet->getStyle('C' . $row)->applyFromArray($yellowLight);
            $sheet->getStyle('D' . $row)->applyFromArray($yellowLight);
            $sheet->getStyle('E' . $row)->applyFromArray($yellowLight);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            foreach (['D','F','G','H','J','K','M','N','P'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode($fmtNum);
            }
            foreach (['E','I','L','O'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode($fmtPct);
            }
            $sheet->getRowDimension($row)->setRowHeight(13);
            $row++;
        }
        // Total del desglose (opcional)
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('G' . $row, $g['subtotal_cm']);
        $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray($thinBorder);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
        $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode($fmtMoney);
        $sheet->getRowDimension($row)->setRowHeight(13);
        $row++;
        $row++; // separador entre lotes
    }
}

// ============================================================
// FILA TOTAL ORDEN DE COMPRA (usando totales globales)
// ============================================================
$sheet->setCellValue('A' . $row, 'Total Orden de Compra');
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->setCellValue('D' . $row, $totalCantCM);
$sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($fmtNum);
$sheet->setCellValue('E' . $row, 0);
$sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($fmtPct);
$sheet->setCellValue('G' . $row, $totalMontoCM);
$sheet->setCellValue('J' . $row, $totalAntMonto);
$sheet->setCellValue('M' . $row, $totalActMonto);
$sheet->setCellValue('P' . $row, $totalAcuMonto);
$totalRow = $row;
$sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $row . ':P' . $row)->getFont()->setBold(true);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
foreach (['G','J','M','P'] as $col) {
    $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode($fmtMoney);
    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}
$sheet->getRowDimension($row)->setRowHeight(15);
$endDataRow = $row;

// Bordes derechos gruesos para todas las filas de datos
for ($rr = 17; $rr <= $endDataRow; $rr++) {
    $sheet->getStyle('G' . $rr)->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
    $sheet->getStyle('P' . $rr)->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
}
$sheet->getStyle('A' . $endDataRow . ':P' . $endDataRow)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');

// ============================================================
// NOTAS Y FINANCIERAS (igual que CM)
// ============================================================
$rowNotas = $row + 3;
$sheet->setCellValue('A' . $rowNotas, 'Notas:');
$sheet->mergeCells('A' . $rowNotas . ':G' . $rowNotas);
$sheet->getStyle('A' . $rowNotas . ':G' . $rowNotas)->applyFromArray($greyHeader);
$sheet->getStyle('A' . $rowNotas . ':G' . $rowNotas)->applyFromArray($boxOutline);
$sheet->mergeCells('A' . ($rowNotas + 1) . ':G' . ($rowNotas + 5));
$sheet->setCellValue('A' . ($rowNotas + 1), (string)($ca['observaciones'] ?? ''));
$sheet->getStyle('A' . ($rowNotas + 1) . ':G' . ($rowNotas + 5))->applyFromArray($thinBorder);
$sheet->getStyle('A' . ($rowNotas + 1) . ':G' . ($rowNotas + 5))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
for ($rn = $rowNotas; $rn <= $rowNotas + 5; $rn++) $sheet->getRowDimension($rn)->setRowHeight(14.25);

// Total Certificado
$rowFin1 = $row + 3;
$sheet->setCellValue('H' . $rowFin1, 'Total Certificado');
$sheet->mergeCells('H' . $rowFin1 . ':K' . $rowFin1);
$sheet->setCellValue('L' . $rowFin1, 0);
$sheet->getStyle('L' . $rowFin1)->getNumberFormat()->setFormatCode($fmtPct);
$sheet->setCellValue('M' . $rowFin1, (float)$ca['monto_total']);
$sheet->getStyle('M' . $rowFin1)->getNumberFormat()->setFormatCode($fmtMoney);
$sheet->getStyle('H' . $rowFin1 . ':M' . $rowFin1)->applyFromArray($thinBorder);
$sheet->getStyle('H' . $rowFin1)->getFont()->setBold(true);

// Desacopio de anticipo
$rowFin2 = $rowFin1 + 1;
$sheet->setCellValue('H' . $rowFin2, 'Desacopio de anticipo');
$sheet->mergeCells('H' . $rowFin2 . ':K' . $rowFin2);
$sheet->setCellValue('L' . $rowFin2, 0);
$sheet->getStyle('L' . $rowFin2)->getNumberFormat()->setFormatCode($fmtPct);
$sheet->getStyle('L' . $rowFin2)->applyFromArray($yellowLight);
$sheet->setCellValue('M' . $rowFin2, (float)($ca['monto_acumulado_desacopios'] ?? 0) * -1);
$sheet->getStyle('M' . $rowFin2)->getNumberFormat()->setFormatCode($fmtMoney);
$sheet->getStyle('H' . $rowFin2 . ':M' . $rowFin2)->applyFromArray($thinBorder);
$sheet->getStyle('H' . $rowFin2)->getFont()->setBold(true);

// CAC (2 filas)
$rowCAC1 = $rowFin2 + 1;
$rowCAC2 = $rowFin2 + 2;
$sheet->setCellValue('H' . $rowCAC1, 'CAC');
$sheet->mergeCells('H' . $rowCAC1 . ':H' . $rowCAC2);
$sheet->getStyle('H' . $rowCAC1 . ':H' . $rowCAC2)->applyFromArray($thinBorder);
$sheet->setCellValue('I' . $rowCAC1, 'ENE');
$sheet->setCellValue('J' . $rowCAC1, 'ABR');
$sheet->setCellValue('K' . $rowCAC1, 'Indice');
$sheet->getStyle('I' . $rowCAC1 . ':K' . $rowCAC1)->applyFromArray($thinBorder);
$sheet->setCellValue('L' . $rowCAC1, '=ROUND(K' . $rowCAC2 . ',3)');
$sheet->mergeCells('L' . $rowCAC1 . ':L' . $rowCAC2);
$sheet->getStyle('L' . $rowCAC1 . ':L' . $rowCAC2)->getNumberFormat()->setFormatCode('0.0%');
$sheet->getStyle('L' . $rowCAC1 . ':L' . $rowCAC2)->applyFromArray($thinBorder);
$sheet->setCellValue('M' . $rowCAC1, 0);
$sheet->mergeCells('M' . $rowCAC1 . ':M' . $rowCAC2);
$sheet->getStyle('M' . $rowCAC1 . ':M' . $rowCAC2)->getNumberFormat()->setFormatCode($fmtMoney);
$sheet->getStyle('M' . $rowCAC1 . ':M' . $rowCAC2)->applyFromArray($thinBorder);
$sheet->setCellValue('I' . $rowCAC2, 0);
$sheet->getStyle('I' . $rowCAC2)->getNumberFormat()->setFormatCode('0.0');
$sheet->getStyle('I' . $rowCAC2)->applyFromArray($yellowLight);
$sheet->setCellValue('J' . $rowCAC2, 0);
$sheet->getStyle('J' . $rowCAC2)->getNumberFormat()->setFormatCode('0.0');
$sheet->getStyle('J' . $rowCAC2)->applyFromArray($yellowLight);
$sheet->setCellValue('K' . $rowCAC2, '=IFERROR(J' . $rowCAC2 . '/I' . $rowCAC2 . '-1,0)');
$sheet->getStyle('K' . $rowCAC2)->getNumberFormat()->setFormatCode('0.00%');
$sheet->getStyle('K' . $rowCAC2)->applyFromArray($thinBorder);
$sheet->getStyle('H' . ($rowCAC1 - 2) . ':M' . ($rowCAC1 + 3))->applyFromArray($boxOutline);
for ($r = $rowCAC1 - 2; $r <= $rowCAC1 + 3; $r++) $sheet->getRowDimension($r)->setRowHeight(14.25);

// Fondo de reparo
$rowFin3 = $rowFin2 + 3;
$sheet->setCellValue('H' . $rowFin3, 'Fondo de reparo');
$sheet->mergeCells('H' . $rowFin3 . ':K' . $rowFin3);
$sheet->setCellValue('L' . $rowFin3, 0);
$sheet->getStyle('L' . $rowFin3)->getNumberFormat()->setFormatCode($fmtPct);
$sheet->getStyle('L' . $rowFin3)->applyFromArray($yellowLight);
$sheet->setCellValue('M' . $rowFin3, 0);
$sheet->getStyle('M' . $rowFin3)->getNumberFormat()->setFormatCode($fmtMoney);
$sheet->getStyle('H' . $rowFin3 . ':M' . $rowFin3)->applyFromArray($thinBorder);
$sheet->getStyle('H' . $rowFin3)->getFont()->setBold(true);

// Firmas
$rowFirma = $rowFin3 + 4;
$boxOutline = ['borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]];
$sheet->getStyle('A' . $rowFirma . ':D' . ($rowFirma + 6))->applyFromArray($boxOutline);
$sheet->getStyle('E' . $rowFirma . ':K' . ($rowFirma + 6))->applyFromArray($boxOutline);
$sheet->getStyle('L' . $rowFirma . ':P' . ($rowFirma + 6))->applyFromArray($boxOutline);
for ($br = $rowFirma; $br <= $rowFirma + 6; $br++) $sheet->getRowDimension($br)->setRowHeight(14.25);
$sheet->setCellValue('A' . ($rowFirma + 7), 'FIRMA NH');
$sheet->mergeCells('A' . ($rowFirma + 7) . ':D' . ($rowFirma + 7));
$sheet->setCellValue('E' . ($rowFirma + 7), 'FIRMA CLIENTE');
$sheet->mergeCells('E' . ($rowFirma + 7) . ':K' . ($rowFirma + 7));
$sheet->setCellValue('L' . ($rowFirma + 7), 'FIRMA OTRO');
$sheet->mergeCells('L' . ($rowFirma + 7) . ':P' . ($rowFirma + 7));
$sheet->getStyle('A' . ($rowFirma + 7) . ':D' . ($rowFirma + 7))->applyFromArray($greyHeader);
$sheet->getStyle('E' . ($rowFirma + 7) . ':K' . ($rowFirma + 7))->applyFromArray($greyHeader);
$sheet->getStyle('L' . ($rowFirma + 7) . ':P' . ($rowFirma + 7))->applyFromArray($greyHeader);
$sheet->getStyle('A' . ($rowFirma + 7) . ':D' . ($rowFirma + 7))->applyFromArray($boxOutline);
$sheet->getStyle('E' . ($rowFirma + 7) . ':K' . ($rowFirma + 7))->applyFromArray($boxOutline);
$sheet->getStyle('L' . ($rowFirma + 7) . ':P' . ($rowFirma + 7))->applyFromArray($boxOutline);
$sheet->getStyle('A' . ($rowFirma + 7) . ':P' . ($rowFirma + 7))->getFont()->setBold(true)->setSize(9);
$sheet->getStyle('A' . ($rowFirma + 7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E' . ($rowFirma + 7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('L' . ($rowFirma + 7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getPageSetup()->setPrintArea('A1:P' . ($rowFirma + 7));

// ============================================================
// HOJA 2: Medicion (con la misma lógica de agrupación)
// ============================================================
$med = $book->createSheet();
$med->setTitle('Medicion');
$med->setShowGridLines(false);
$med->getSheetView()->setZoomScale(76);

$wMed = ['A' => 4.125, 'B' => 19.125, 'C' => 8.375, 'D' => 11, 'E' => 10.25, 'F' => 7.625, 'G' => 10.625, 'H' => 7.625, 'I' => 10.875, 'J' => 7.625, 'K' => 16.125];
foreach ($wMed as $c => $w) $med->getColumnDimension($c)->setWidth($w);
$med->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
$med->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$med->getPageSetup()->setFitToPage(true);
$med->getPageSetup()->setScale(34);
$med->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(15, 16);

if (is_file($logoPath)) {
    $d = new Drawing();
    $d->setPath($logoPath);
    $d->setHeight(32);
    $d->setCoordinates('A1');
    $d->setWorksheet($med);
}

$med->setCellValue('C1', 'MEDICION');
$med->mergeCells('C1:I6');
$med->getStyle('C1')->getFont()->setBold(true)->setSize(14)->setName('Cambria');
$med->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$med->mergeCells('A1:B6');
$med->mergeCells('J1:K6');
$med->getStyle('A1:K6')->applyFromArray($thinBorder);

// Header Medicion
$med->setCellValue('A8', 'Datos del Proyecto y Orden de Compra');
$med->mergeCells('A8:E8');
$med->getStyle('A8')->applyFromArray($greyHeader);
$med->setCellValue('H8', 'Datos de la Medición');
$med->mergeCells('H8:K8');
$med->getStyle('H8')->applyFromArray($greyHeader);
$med->setCellValue('A9', 'Cliente'); $med->setCellValue('C9', $ca['cliente']); $med->mergeCells('A9:B9'); $med->mergeCells('C9:E9');
$med->setCellValue('H9', 'Número CA'); $med->setCellValue('J9', $ca['nro_certificado']); $med->mergeCells('H9:I9'); $med->mergeCells('J9:K9');
$med->setCellValue('A10', 'Proyecto'); $med->setCellValue('C10', $proyectosNombre); $med->mergeCells('A10:B10'); $med->mergeCells('C10:E10');
$med->setCellValue('H10', 'Revisión'); $med->setCellValue('J10', $ca['nro_revision']); $med->mergeCells('H10:I10'); $med->mergeCells('J10:K10');
$med->setCellValue('A11', 'Orden de Compra'); $med->setCellValue('C11', $ca['numero_occ']); $med->mergeCells('A11:B11'); $med->mergeCells('C11:E11');
$med->setCellValue('H11', 'Periodo desde'); $med->setCellValue('J11', fmtFecha($ca['fecha_inicio'])); $med->mergeCells('H11:I11'); $med->mergeCells('J11:K11');
$med->setCellValue('A12', 'Moneda y Monto'); $med->setCellValue('C12', $ca['moneda'] . ' ' . number_format((float)$ca['monto_total_cm'], 2, ',', '.')); $med->mergeCells('A12:B12'); $med->mergeCells('C12:E12');
$med->setCellValue('H12', 'Periodo hasta'); $med->setCellValue('J12', fmtFecha($ca['fecha_fin'])); $med->mergeCells('H12:I12'); $med->mergeCells('J12:K12');
$med->setCellValue('A13', 'Fecha CM'); $med->setCellValue('C13', fmtFecha($ca['fecha_emision'])); $med->mergeCells('A13:B13'); $med->mergeCells('C13:E13');
$med->setCellValue('H13', 'Fecha emisión CA'); $med->setCellValue('J13', fmtFecha($ca['fecha_emision'])); $med->mergeCells('H13:I13'); $med->mergeCells('J13:K13');
foreach (['A9','A10','A11','A12','A13','H9','H10','H11','H12','H13'] as $c) $med->getStyle($c)->applyFromArray($greyHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
foreach (['C9','C10','C11','C12','C13','J9','J10','J11','J12','J13'] as $c) $med->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$med->getStyle('C13')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$med->getStyle('J13')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$med->getStyle('J11')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$med->getStyle('J12')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$med->getStyle('A8:E13')->applyFromArray($thinBorder);
$med->getStyle('H8:K13')->applyFromArray($thinBorder);

// Tabla Medicion header
$med->setCellValue('A15', 'Pos'); $med->mergeCells('A15:A16');
$med->setCellValue('B15', 'Descripción'); $med->mergeCells('B15:B16');
$med->setCellValue('C15', 'Unidad'); $med->mergeCells('C15:C16');
$med->setCellValue('D15', 'Cantidad'); $med->mergeCells('D15:D16');
$med->setCellValue('E15', 'Anterior'); $med->mergeCells('E15:F15');
$med->setCellValue('G15', 'Actual'); $med->mergeCells('G15:H15');
$med->setCellValue('I15', 'Acumulado'); $med->mergeCells('I15:J15');
$med->setCellValue('K15', 'Observaciones'); $med->mergeCells('K15:K16');
$med->getStyle('A15:K15')->getFont()->setBold(true)->setSize(10);
$med->getStyle('A15:K15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$med->setCellValue('E16', 'Cantidad'); $med->setCellValue('F16', '%');
$med->setCellValue('G16', 'Cantidad'); $med->setCellValue('H16', '%');
$med->setCellValue('I16', 'Cantidad'); $med->setCellValue('J16', '%');
$med->getStyle('E16:J16')->getFont()->setBold(true)->setSize(10);
$med->getStyle('E16:J16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$med->getStyle('A15:K16')->applyFromArray($thinBorder);
$med->getStyle('D15:D16')->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
$med->getStyle('K15:K16')->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
$med->getStyle('A15:K15')->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');

// Datos Medicion (con misma lógica de agrupación)
$mRow = 17;
foreach ($grupos as $g) {
    // Padres
    foreach ($g['occ_ids'] as $oid) {
        $occ = $occMap[$oid] ?? null;
        if (!$occ) continue;

        $esAgrupado = ($g['modo_generacion'] === 'agrupar');

        if ($esAgrupado) {
            $cant = 0;
            $antCant = 0; $actCant = 0; $acuCant = 0;
            foreach ($g['filas'] as $f) {
                $cant += (float)$f['cantidad'];
                $antCant += (float)$f['acumulado_actual'] - (float)$f['cantidad_actual'];
                $actCant += (float)$f['cantidad_actual'];
                $acuCant += (float)$f['acumulado_actual'];
            }
            $pos = $occ['posicion'];
            $desc = (string)$occ['descripcion'];
        } else {
            $pos = $occ['posicion'];
            $desc = (string)$occ['descripcion'];
            $cant = (float)$occ['cantidad'];
            $antCant = 0; $actCant = 0; $acuCant = 0;
            foreach ($g['filas'] as $f) {
                if ((int)($f['id_occ_detalle'] ?? 0) === $oid) {
                    $antCant += (float)$f['acumulado_actual'] - (float)$f['cantidad_actual'];
                    $actCant += (float)$f['cantidad_actual'];
                    $acuCant += (float)$f['acumulado_actual'];
                }
            }
        }

        $med->setCellValue('A' . $mRow, $pos);
        $med->setCellValue('B' . $mRow, $desc);
        $med->setCellValue('D' . $mRow, $cant);
        $med->setCellValue('E' . $mRow, $antCant);
        $med->setCellValue('F' . $mRow, ($cant > 0) ? ($antCant / $cant) : 0);
        $med->setCellValue('G' . $mRow, $actCant);
        $med->setCellValue('H' . $mRow, ($cant > 0) ? ($actCant / $cant) : 0);
        $med->setCellValue('I' . $mRow, $acuCant);
        $med->setCellValue('J' . $mRow, ($cant > 0) ? ($acuCant / $cant) : 0);
        $med->setCellValue('K' . $mRow, '');

        cmRowBorder($med, $mRow, 'A', 'K');
        $med->getStyle('C' . $mRow . ':J' . $mRow)->applyFromArray($celesteData);
        $med->getStyle('A' . $mRow)->applyFromArray($yellowLight);
        $med->getStyle('B' . $mRow)->applyFromArray($yellowLight);
        $med->getStyle('A' . $mRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $med->getStyle('B' . $mRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $med->getStyle('B' . $mRow)->getFont()->setBold(true);
        foreach (['D','E','G','I'] as $col) {
            $med->getStyle($col . $mRow)->getNumberFormat()->setFormatCode($fmtNum);
        }
        foreach (['F','H','J'] as $col) {
            $med->getStyle($col . $mRow)->getNumberFormat()->setFormatCode($fmtPct);
        }
        $med->getRowDimension($mRow)->setRowHeight(14.25);
        $mRow++;
    }

    // Desgloses
    if (!empty($g['filas'])) {
        $ownerPos = $occMap[$g['occ_ids'][0]]['posicion'] ?? '10';
        foreach ($g['filas'] as $fi => $fila) {
            $posDes = $ownerPos . '.' . ($fi + 1);
            $desc = (string)$fila['descripcion'];
            $unidad = (string)($fila['unidad_medida'] ?? '');
            $cant = (float)$fila['cantidad'];
            $antCant = (float)$fila['acumulado_actual'] - (float)$fila['cantidad_actual'];
            $actCant = (float)$fila['cantidad_actual'];
            $acuCant = (float)$fila['acumulado_actual'];

            $med->setCellValue('A' . $mRow, $posDes);
            $med->setCellValue('B' . $mRow, $desc);
            $med->setCellValue('C' . $mRow, $unidad);
            $med->setCellValue('D' . $mRow, $cant);
            $med->setCellValue('E' . $mRow, $antCant);
            $med->setCellValue('F' . $mRow, ($cant > 0) ? ($antCant / $cant) : 0);
            $med->setCellValue('G' . $mRow, $actCant);
            $med->setCellValue('H' . $mRow, ($cant > 0) ? ($actCant / $cant) : 0);
            $med->setCellValue('I' . $mRow, $acuCant);
            $med->setCellValue('J' . $mRow, ($cant > 0) ? ($acuCant / $cant) : 0);
            $med->setCellValue('K' . $mRow, '');

            cmRowBorder($med, $mRow, 'A', 'K');
            $med->getStyle('A' . $mRow)->applyFromArray($yellowLight);
            $med->getStyle('B' . $mRow)->applyFromArray($yellowLight);
            $med->getStyle('C' . $mRow)->applyFromArray($yellowLight);
            $med->getStyle('D' . $mRow)->applyFromArray($yellowLight);
            $med->getStyle('A' . $mRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $med->getStyle('B' . $mRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            foreach (['D','E','G','I'] as $col) {
                $med->getStyle($col . $mRow)->getNumberFormat()->setFormatCode($fmtNum);
            }
            foreach (['F','H','J'] as $col) {
                $med->getStyle($col . $mRow)->getNumberFormat()->setFormatCode($fmtPct);
            }
            $med->getRowDimension($mRow)->setRowHeight(13);
            $mRow++;
        }
        $mRow++; // separador
    }
}
$medEndDataRow = $mRow - 1;
for ($mr = 17; $mr < $mRow; $mr++) {
    $med->getStyle('D' . $mr)->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
    $med->getStyle('K' . $mr)->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
}
if ($medEndDataRow >= 17) {
    $med->getStyle('A' . $medEndDataRow . ':K' . $medEndDataRow)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('000000');
}

// Notas y Firmas en Medicion
$rowNotasMed = $medEndDataRow + 3;
$med->setCellValue('A' . $rowNotasMed, 'Notas:');
$med->mergeCells('A' . $rowNotasMed . ':K' . $rowNotasMed);
$med->getStyle('A' . $rowNotasMed . ':K' . $rowNotasMed)->applyFromArray($greyHeader);
$med->getStyle('A' . $rowNotasMed . ':K' . $rowNotasMed)->applyFromArray($boxOutline);
$notasContentStart = $rowNotasMed + 1;
$notasContentEnd = $rowNotasMed + 3;
$med->mergeCells('A' . $notasContentStart . ':K' . $notasContentEnd);
$med->setCellValue('A' . $notasContentStart, (string)($ca['observaciones'] ?? ''));
$med->getStyle('A' . $notasContentStart . ':K' . $notasContentEnd)->applyFromArray($thinBorder);
$med->getStyle('A' . $notasContentStart . ':K' . $notasContentEnd)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
for ($rn = $rowNotasMed; $rn <= $notasContentEnd; $rn++) $med->getRowDimension($rn)->setRowHeight(14.25);

$rowFirmaMed = $notasContentEnd + 3;
$rowFirmaMedEnd = $rowFirmaMed + 7;
$med->getStyle('A' . $rowFirmaMed . ':C' . $rowFirmaMedEnd)->applyFromArray($boxOutline);
$med->getStyle('D' . $rowFirmaMed . ':H' . $rowFirmaMedEnd)->applyFromArray($boxOutline);
$med->getStyle('I' . $rowFirmaMed . ':K' . $rowFirmaMedEnd)->applyFromArray($boxOutline);
for ($fr = $rowFirmaMed; $fr <= $rowFirmaMedEnd; $fr++) $med->getRowDimension($fr)->setRowHeight(14.25);

$rowFirmaLabelMed = $rowFirmaMedEnd + 1;
$med->setCellValue('A' . $rowFirmaLabelMed, 'FIRMA NH');
$med->mergeCells('A' . $rowFirmaLabelMed . ':C' . $rowFirmaLabelMed);
$med->setCellValue('D' . $rowFirmaLabelMed, 'FIRMA CLIENTE');
$med->mergeCells('D' . $rowFirmaLabelMed . ':H' . $rowFirmaLabelMed);
$med->setCellValue('I' . $rowFirmaLabelMed, 'FIRMA OTRO');
$med->mergeCells('I' . $rowFirmaLabelMed . ':K' . $rowFirmaLabelMed);
$med->getStyle('A' . $rowFirmaLabelMed . ':C' . $rowFirmaLabelMed)->applyFromArray($greyHeader);
$med->getStyle('D' . $rowFirmaLabelMed . ':H' . $rowFirmaLabelMed)->applyFromArray($greyHeader);
$med->getStyle('I' . $rowFirmaLabelMed . ':K' . $rowFirmaLabelMed)->applyFromArray($greyHeader);
$med->getStyle('A' . $rowFirmaLabelMed . ':C' . $rowFirmaLabelMed)->applyFromArray($boxOutline);
$med->getStyle('D' . $rowFirmaLabelMed . ':H' . $rowFirmaLabelMed)->applyFromArray($boxOutline);
$med->getStyle('I' . $rowFirmaLabelMed . ':K' . $rowFirmaLabelMed)->applyFromArray($boxOutline);
$med->getStyle('A' . $rowFirmaLabelMed . ':K' . $rowFirmaLabelMed)->getFont()->setBold(true)->setSize(9);
$med->getStyle('A' . $rowFirmaLabelMed)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$med->getStyle('D' . $rowFirmaLabelMed)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$med->getStyle('I' . $rowFirmaLabelMed)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$med->getPageSetup()->setPrintArea('A1:K' . $rowFirmaLabelMed);

// Activar primera hoja y descargar
$book->setActiveSheetIndex(0);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="certificado_avance_CA' . $idCA . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($book);
$writer->save('php://output');
exit;