<?php
require 'config.php';
require 'database.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$idCm = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idCm) { http_response_code(400); die('Certificado Maestro invalido.'); }
if (function_exists('esOperacionesSinEconomico') && esOperacionesSinEconomico()) { http_response_code(403); die('No tiene permisos para exportar el Certificado Maestro.'); }

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Datos del CM
$sql = "SELECT cm.id, cm.id_occ, cm.fecha_emision, cm.fecha_inicio, cm.fecha_fin, cm.monto_total, cm.porcentaje_anticipo,
               cm.monto_acumulado_avances, cm.monto_acumulado_anticipos, cm.monto_acumulado_desacopios,
               cm.monto_acumulado_descuentos, cm.monto_acumulado_ajustes, cm.observaciones, cm.aprobado_cliente,
               occ.numero AS numero_occ, cu.nombre AS cliente, m.moneda
        FROM certificados_maestros cm
        INNER JOIN occ ON occ.id=cm.id_occ
        INNER JOIN cuentas cu ON cu.id=occ.id_cuenta_cliente
        INNER JOIN monedas m ON m.id=cm.id_moneda
        WHERE cm.id=?";
$q = $pdo->prepare($sql);
$q->execute([$idCm]);
$cm = $q->fetch(PDO::FETCH_ASSOC);
if (!$cm) { Database::disconnect(); http_response_code(404); die('Certificado Maestro no encontrado.'); }

// Proyectos agrupados (para N3:P6 y Medicion)
$q = $pdo->prepare("SELECT GROUP_CONCAT(DISTINCT p.nombre SEPARATOR ', ') FROM certificados_maestros_detalles cmd LEFT JOIN proyectos p ON p.id=cmd.id_proyecto WHERE cmd.id_certificado_maestro=?");
$q->execute([$idCm]);
$proyectosNombre = (string)($q->fetchColumn() ?: '-');

// Items del CM con avance acumulado por CA (para totales y fallback)
$sql = "SELECT
        od.posicion as posicion_occ,
        cmd.id as cmd_id,
         cmd.descripcion,
         cmd.posicion_aperturado,
        cmd.cantidad,
        um.unidad_medida,
        cmd.incidencia_porcentaje,
        cmd.precio_unitario,
        cmd.subtotal,
        cmd.lote,
        cmd.aperturado,
        COALESCE(SUM(cad.cantidad_actual), 0) AS cantidad_avance,
        COALESCE(SUM(cad.subtotal), 0) AS monto_avance
        FROM certificados_maestros_detalles cmd
        LEFT JOIN occ_detalles od ON od.id=cmd.id_occ_detalle
        LEFT JOIN unidades_medida um ON um.id=cmd.id_unidad_medida
        LEFT JOIN certificados_avances_detalle cad ON cad.id_certificado_maestro_detalle=cmd.id
        WHERE cmd.id_certificado_maestro=?
        GROUP BY cmd.id
        ORDER BY cmd.lote, cmd.aperturado, cmd.id";
$q = $pdo->prepare($sql);
$q->execute([$idCm]);
$items = $q->fetchAll(PDO::FETCH_ASSOC);

// Construir desglose agrupado (replica get_detalle_certificado_maestro)
$occStmt = $pdo->prepare("SELECT id, posicion, descripcion, cantidad, precio_unitario, descuento, subtotal FROM occ_detalles WHERE id_occ=? ORDER BY posicion, id");
$occStmt->execute([$cm['id_occ']]);
$occRows = $occStmt->fetchAll(PDO::FETCH_ASSOC);
$occMap = [];
foreach($occRows as $or){ $occMap[(int)$or['id']] = $or; }

$qGrupos = $pdo->prepare("SELECT aperturado, lote, modo_generacion, COALESCE(MAX(monto_base_occ),0) as monto_base_occ, COALESCE(SUM(subtotal),0) as subtotal_lote, MIN(id) as min_id FROM certificados_maestros_detalles WHERE id_certificado_maestro=? AND aperturado IS NOT NULL AND aperturado!='' GROUP BY aperturado, lote, modo_generacion ORDER BY min_id");
$qGrupos->execute([$idCm]);
$rawGrupos = $qGrupos->fetchAll(PDO::FETCH_ASSOC);

$grupos = []; // cada grupo: occ_ids[], base, lote, aperturado, filas[]
$occToGrupoIdx = [];
foreach($rawGrupos as $rg){
    $ap = $rg['aperturado'];
    $qOccIds = $pdo->prepare("SELECT id_occ_detalle FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro=? AND aperturado=? ORDER BY id_occ_detalle");
    $qOccIds->execute([$idCm,$ap]);
    $occIds = array_map('intval', $qOccIds->fetchAll(PDO::FETCH_COLUMN));
    if(empty($occIds)){
        // fallback legacy: usar id_occ_detalle de las filas
        $qF = $pdo->prepare("SELECT DISTINCT id_occ_detalle FROM certificados_maestros_detalles WHERE id_certificado_maestro=? AND aperturado=? AND id_occ_detalle IS NOT NULL");
        $qF->execute([$idCm,$ap]);
        $occIds = array_map('intval', $qF->fetchAll(PDO::FETCH_COLUMN));
    }
    // ordenar occIds por posicion
    usort($occIds, function($a,$b) use($occMap){ $pa=(int)($occMap[$a]['posicion']??9999); $pb=(int)($occMap[$b]['posicion']??9999); return $pa<=>$pb ?: $a<=>$b; });
    $qFilas = $pdo->prepare("SELECT cmd.descripcion, um.unidad_medida, cmd.cantidad, cmd.incidencia_porcentaje, cmd.precio_unitario, cmd.subtotal, cmd.posicion_aperturado FROM certificados_maestros_detalles cmd LEFT JOIN unidades_medida um ON um.id=cmd.id_unidad_medida WHERE cmd.id_certificado_maestro=? AND cmd.aperturado=? ORDER BY cmd.id");
    $qFilas->execute([$idCm,$ap]);
    $filas = $qFilas->fetchAll(PDO::FETCH_ASSOC);
    $owner = !empty($occIds) ? $occIds[0] : null; // primer occ como owner (pedido usuario)
    $grupos[] = ['aperturado'=>$ap,'lote'=>$rg['lote'],'modo'=>$rg['modo_generacion'],'base'=>(float)$rg['monto_base_occ'],'subtotal'=>(float)$rg['subtotal_lote'],'occ_ids'=>$occIds,'owner'=>$owner,'filas'=>$filas];
    foreach($occIds as $oid){ if(!isset($occToGrupoIdx[$oid])) $occToGrupoIdx[$oid]=$rg['aperturado']; }
}
// Orden visual: occ agrupados primero en orden de grupos, luego huérfanos
$ordenOccIds = [];
$vistos = [];
foreach($grupos as $g){ foreach($g['occ_ids'] as $oid){ if(!isset($vistos[$oid])){ $vistos[$oid]=1; $ordenOccIds[]=$oid; } } }
foreach($occRows as $or){
    $oid=(int)$or['id'];
    if(!isset($vistos[$oid])){
        $qChk=$pdo->prepare("SELECT COUNT(*) FROM certificados_maestros_detalles WHERE id_certificado_maestro=? AND id_occ_detalle=? AND (aperturado IS NULL OR aperturado='')");
        $qChk->execute([$idCm,$oid]);
        if($qChk->fetchColumn()>0){ $vistos[$oid]=1; $ordenOccIds[]=$oid; }
    }
}
if(empty($ordenOccIds)){
    foreach($occRows as $or){ $ordenOccIds[]=(int)$or['id']; }
}
$gruposByOwner = [];
foreach($grupos as $g){ if($g['owner']!==null) $gruposByOwner[(int)$g['owner']]=$g; }

Database::disconnect();

function cmFmtFecha($v){ if(empty($v) || $v==='0000-00-00' || $v==='0000-00-00 00:00:00' || $v==='-') return '-'; $t=strtotime($v); return $t ? date('d/m/Y',$t) : '-'; }

// Fuerza el borde thin en CADA celda individual de un rango de una fila (A..K, A..P, etc),
// para evitar que quede sin bordes verticales cuando se aplica un rango amplio de una sola vez.
function cmRowBorder($sheet, $rowNum, $colStart, $colEnd, $style = Border::BORDER_THIN, $color = 'B0B0B0') {
    $start = ord($colStart);
    $end = ord($colEnd);
    for ($c = $start; $c <= $end; $c++) {
        $col = chr($c);
        $borders = $sheet->getStyle($col.$rowNum)->getBorders();
        $borders->getTop()->setBorderStyle($style)->getColor()->setRGB($color);
        $borders->getBottom()->setBorderStyle($style)->getColor()->setRGB($color);
        $borders->getLeft()->setBorderStyle($style)->getColor()->setRGB($color);
        $borders->getRight()->setBorderStyle($style)->getColor()->setRGB($color);
    }
}

// Helpers de estilo - fondos aclarados ~30%
$greyHeader = [
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'D9E2EC']],
    'font' => ['bold'=>true,'size'=>10,'name'=>'Calibri','color'=>['rgb'=>'000000']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0B0B0']]],
];
$blueHeader = $greyHeader; // compatibilidad: todo lo celeste ahora es gris medio
$celesteData = [
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E6F2FF']],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0B0B0']]],
];
$blueBlock = [
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'DAEEF3']],
    'font' => ['bold'=>true,'size'=>9,'name'=>'Calibri'],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0B0B0']]],
];
$yellowLight = [
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF4CC']],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0B0B0']]],
];
$yellowPosDesc = $yellowLight; // mismo amarillo aclarado para Pos/Descripcion
$thinBorder = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0B0B0']]]];
$mediumBorder = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'000000']]]];
$boxOutline = ['borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]]];
$mediumOutline = ['borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'000000']]]];
$celesteStrong = [
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E6F2FF']],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0B0B0']]],
];
$celesteFill = [
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E6F2FF']],
];
$boldCenter = ['font'=>['bold'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true]];
$wrapTop = ['alignment'=>['vertical'=>Alignment::VERTICAL_TOP,'wrapText'=>true]];
$fmtEuro = '#,##0.00\ [$€-x-euro]';
$fmtPct = '0.00%';
$fmtNum = '#,##0.00';
$monedaFmt = trim($cm['moneda'] ?? 'U$S');
$monedaFmt = str_replace('"','', $monedaFmt);
$fmtMoney = '"' . $monedaFmt . '" #,##0.00';
$fmtAccounting = NumberFormat::FORMAT_ACCOUNTING_USD;

$book = new Spreadsheet();
$book->getProperties()->setCreator('Grupo NH')->setTitle('Certificado Maestro '.$idCm);

// =====================================================
// HOJA 1: Certificado 1 (A:P)
// =====================================================
$sheet = $book->getActiveSheet();
$sheet->setTitle('Certificado 1');
$sheet->setShowGridLines(false);
$sheet->getSheetView()->setZoomScale(70);
$sheet->getSheetView()->setZoomScaleNormal(70);

// Logo placeholders (2 imágenes como en plantilla)
$logoPaths = [
    __DIR__.'/assets/images/logo.jpg',
    __DIR__.'/assets/images/logo_nh.png',
    __DIR__.'/assets/images/logo_cliente.png',
];
$drawCoords = ['A1','N1'];
foreach (['A1','N1'] as $idx=>$coord) {
    $p = $logoPaths[$idx] ?? null;
    if ($p && is_file($p)) {
        $d = new Drawing();
        $d->setPath($p);
        $d->setResizeProportional(false);
        if ($idx==0) { $d->setWidth(104); $d->setHeight(104); } else { $d->setHeight(36); }
        $d->setCoordinates($coord);
        $d->setOffsetX(2); $d->setOffsetY(2);
        $d->setWorksheet($sheet);
    }
}

// Column widths (test.txt 14-30 + 250 antiguos)
$wCert = ['A'=>3.5,'B'=>30.25,'C'=>0,'D'=>10.5,'E'=>7.25,'F'=>15.875,'G'=>16.375,'H'=>7.5,'I'=>5,'J'=>14.625,'K'=>7.5,'L'=>5,'M'=>16.375,'N'=>7.5,'O'=>5,'P'=>16.375];
foreach ($wCert as $c=>$w) $sheet->getColumnDimension($c)->setWidth($w);
$sheet->getColumnDimension('Q')->setWidth(11); // overflow

// Page setup - landscape A4 scale 77 fitToHeight 2
$sheet->getPageMargins()->setTop(1)->setRight(0.25)->setBottom(0.52)->setLeft(0.29)->setHeader(0)->setFooter(0);
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(2);
$sheet->getPageSetup()->setScale(77);
$sheet->getHeaderFooter()->setOddFooter('&RPágina &P de &N');
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(8, 9);

// Row heights - header uses full height for logo + company text in B1:B6
for ($r=1;$r<=6;$r++) $sheet->getRowDimension($r)->setRowHeight(13.5);
$sheet->getRowDimension(7)->setRowHeight(14);
$sheet->getRowDimension(15)->setRowHeight(28.5);
$sheet->getRowDimension(16)->setRowHeight(12.75);

// --- Bloque superior --- A1 imagen, B1:B6 empresa a la derecha de la imagen
$sheet->setCellValue('C1', 'CERTIFICADO MAESTRO');
$sheet->getStyle('C1')->getFont()->setBold(true)->setSize(14)->setName('Cambria');
$sheet->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->mergeCells('C1:M6');
$sheet->mergeCells('N1:P6');
$sheet->mergeCells('B1:B6');
$sheet->setCellValue('B1', "NH Construcciones SRL\nRicardo Gutiérrez 2874\n(C1417EBL) - CABA\nTel./Fax (54 11) 4505-8300");
$sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('B1')->getFont()->setSize(7)->setName('Calibri');
$sheet->getStyle('A1:P6')->applyFromArray($thinBorder);
$sheet->getStyle('C1:M6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

// Datos del Proyecto y Orden de Compra (A8)
$sheet->setCellValue('A8', 'Datos del Proyecto y Orden de Compra');
$sheet->mergeCells('A8:G8');
$sheet->getStyle('A8')->applyFromArray($blueHeader);
$sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Labels A9-A13 (A:B unidas)
$sheet->setCellValue('A9', 'Cliente');  $sheet->setCellValue('C9', $cm['cliente']); $sheet->mergeCells('A9:B9'); $sheet->mergeCells('C9:G9');
$sheet->setCellValue('A10','Proyecto'); $sheet->setCellValue('C10', $proyectosNombre); $sheet->mergeCells('A10:B10'); $sheet->mergeCells('C10:G10');
$sheet->setCellValue('A11','Orden de Compra'); $sheet->setCellValue('C11', $cm['numero_occ']); $sheet->mergeCells('A11:B11'); $sheet->mergeCells('C11:G11');
$sheet->setCellValue('A12','Moneda y Monto'); $sheet->setCellValue('C12', $cm['moneda'].' '.number_format((float)$cm['monto_total'],2,',','.')); $sheet->mergeCells('A12:B12'); $sheet->mergeCells('C12:G12');
$sheet->setCellValue('A13','Fecha'); $sheet->setCellValue('C13', cmFmtFecha($cm['fecha_emision'])); $sheet->mergeCells('A13:B13'); $sheet->mergeCells('C13:G13');
foreach (['A9','A10','A11','A12','A13'] as $c) $sheet->getStyle($c)->applyFromArray($blueHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
foreach (['C9','C10','C11','C12','C13'] as $c) $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('C13')->getNumberFormat()->setFormatCode('dd/mm/yy;@');

// Datos del Certificado (K:L:M unidas)
$sheet->setCellValue('K8', 'Datos del Certificado');
$sheet->mergeCells('K8:P8');
$sheet->getStyle('K8')->applyFromArray($blueHeader);
$sheet->setCellValue('K9', 'Numero'); $sheet->setCellValue('N9', $cm['id']); $sheet->mergeCells('K9:M9'); $sheet->mergeCells('N9:P9');
$sheet->setCellValue('K10','Revisión'); $sheet->setCellValue('N10','1'); $sheet->mergeCells('K10:M10'); $sheet->mergeCells('N10:P10');
$sheet->setCellValue('K11','Periodo desde'); $sheet->setCellValue('N11', cmFmtFecha($cm['fecha_inicio'])); $sheet->mergeCells('K11:M11'); $sheet->mergeCells('N11:P11');
$sheet->setCellValue('K12','Periodo hasta'); $sheet->setCellValue('N12', cmFmtFecha($cm['fecha_fin'])); $sheet->mergeCells('K12:M12'); $sheet->mergeCells('N12:P12');
$sheet->setCellValue('K13','Fecha emisión'); $sheet->setCellValue('N13', cmFmtFecha($cm['fecha_emision'])); $sheet->mergeCells('K13:M13'); $sheet->mergeCells('N13:P13');
foreach (['K9','K10','K11','K12','K13'] as $c) $sheet->getStyle($c)->applyFromArray($blueHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
foreach (['N9','N10','N11','N12','N13'] as $c) $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('N11')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$sheet->getStyle('N12')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$sheet->getStyle('A8:G13')->applyFromArray($thinBorder);
$sheet->getStyle('K8:P13')->applyFromArray($thinBorder);

// Header tabla Row15-16
$sheet->setCellValue('A15','Pos'); $sheet->mergeCells('A15:A16');
$sheet->setCellValue('B15','Descripción'); $sheet->mergeCells('B15:B16');
$sheet->setCellValue('C15',''); $sheet->mergeCells('C15:C16');
$sheet->setCellValue('D15','Cantidad'); $sheet->mergeCells('D15:D16');
$sheet->setCellValue('E15','Incidencia (%)'); $sheet->mergeCells('E15:E16');
$sheet->setCellValue('F15','Precio Unitario'); $sheet->mergeCells('F15:F16');
$sheet->setCellValue('G15','Precio Total'); $sheet->mergeCells('G15:G16');
$sheet->setCellValue('H15','Anterior'); $sheet->mergeCells('H15:J15');
$sheet->setCellValue('K15','Actual'); $sheet->mergeCells('K15:M15');
$sheet->setCellValue('N15','Acumulado'); $sheet->mergeCells('N15:P15');
$sheet->getStyle('A15:P15')->getFont()->setBold(true)->setSize(10);
$sheet->getStyle('A15:P15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('A15:P15')->getAlignment()->setWrapText(true);

// Sub-headers Row16
$sheet->setCellValue('H16','Cantidad'); $sheet->setCellValue('I16','%'); $sheet->setCellValue('J16','Monto');
$sheet->setCellValue('K16','Cantidad'); $sheet->setCellValue('L16','%'); $sheet->setCellValue('M16','Monto');
$sheet->setCellValue('N16','Cantidad'); $sheet->setCellValue('O16','%'); $sheet->setCellValue('P16','Monto');
$sheet->getStyle('H16:P16')->getFont()->setBold(true)->setSize(8);
$sheet->getStyle('H16:P16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A15:P16')->applyFromArray($thinBorder);
$sheet->getStyle('A15:P16')->getFont()->setSize(8);

// === Datos dinámicos agrupados (owner = primero, formato 10.1, solo Precio Total) ===
$row = 17;
$totalMontoCM = 0;
$totalCantCM = 0;
$totalAcumAnt = 0; $totalAcumAct = 0; $totalAcumAcu = 0;
if (empty($grupos) && empty($ordenOccIds)) {
    $sheet->setCellValue('A17','-'); $sheet->setCellValue('B17','Sin items');
    $sheet->getStyle('A17:P17')->applyFromArray($thinBorder);
    $row = 18;
    $totalMontoCM = 0; $totalCantCM = 0;
} else {
    // Agrupados juntos: iterar por grupo, primero todos los padres del grupo, luego su desglose
    foreach ($grupos as $g) {
        // Padres del grupo juntos
        foreach ($g['occ_ids'] as $oid) {
            $occ = $occMap[$oid] ?? null;
            if (!$occ) continue;
            $pos = $occ['posicion'];
            $descOcc = (string)$occ['descripcion'];
            $cantOcc = (float)$occ['cantidad'];
            $puOcc = (float)$occ['precio_unitario'];
            $ptOcc = (float)$occ['subtotal'];
            $totalMontoCM += $ptOcc;
            $totalCantCM += $cantOcc;
            $isAgrupado = ($g['modo']==='agrupar' && count($g['occ_ids'])>1);
            $sheet->setCellValue('A'.$row, $pos);
            $sheet->setCellValue('B'.$row, $descOcc);
            $sheet->setCellValue('G'.$row, $ptOcc); // solo Precio Total con amarillo
            $sheet->getStyle('A'.$row.':P'.$row)->applyFromArray($thinBorder);
            $sheet->getStyle('C'.$row.':F'.$row)->applyFromArray($celesteData);
            $sheet->getStyle('H'.$row.':P'.$row)->applyFromArray($celesteData);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle('B'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row)->applyFromArray($yellowLight);
            $sheet->getStyle('B'.$row)->applyFromArray($yellowLight);
            $sheet->getStyle('G'.$row)->applyFromArray($yellowLight);
            $sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode($fmtMoney);
            // (Se quitaron los bordes gruesos internos entre Anterior/Actual/Acumulado en filas de items)
            $sheet->getRowDimension($row)->setRowHeight(14.25);
            $row++;
        }
        // Desglose único por grupo (sin repetir títulos Pos/Descripción, ya están en A15:P16)
        if (!empty($g['filas'])) {
            $ownerPos = $occMap[$g['occ_ids'][0]]['posicion'] ?? '10';
            $sumDesglose = 0;
            foreach ($g['filas'] as $fi => $fila) {
                $posDes = (string)($fila['posicion_aperturado'] ?? '') ?: ($ownerPos . '.' . ($fi+1));
                $descDes = (string)$fila['descripcion'];
                $unidadDes = (string)($fila['unidad_medida'] ?? '');
                $cantDes = (float)$fila['cantidad'];
                $incDes = (float)$fila['incidencia_porcentaje'];
                $puDes = (float)$fila['precio_unitario'];
                $totalDes = (float)$fila['subtotal'];
                if ($totalDes==0) $totalDes = $g['base'] * $incDes/100;
                if ($puDes==0 && $cantDes>0) $puDes = $totalDes / $cantDes;
                $sumDesglose += $totalDes;
                $sheet->setCellValueExplicit('A'.$row, (string)$posDes, DataType::TYPE_STRING);
                $sheet->getStyle('A'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->setCellValue('B'.$row, $descDes . ($unidadDes !== '' ? ' (' . $unidadDes . ')' : ''));
                $sheet->setCellValue('C'.$row, '');
                $sheet->setCellValue('D'.$row, $cantDes);
                $sheet->setCellValue('E'.$row, $incDes/100);
                $sheet->setCellValue('F'.$row, $puDes);
                $sheet->setCellValue('G'.$row, $totalDes);
                $sheet->setCellValue('H'.$row, 0); $sheet->setCellValue('I'.$row, 0); $sheet->setCellValue('J'.$row, 0);
                $sheet->setCellValue('K'.$row, 0); $sheet->setCellValue('L'.$row, 0); $sheet->setCellValue('M'.$row, 0);
                $sheet->setCellValue('N'.$row, 0); $sheet->setCellValue('O'.$row, 0); $sheet->setCellValue('P'.$row, 0);
                $sheet->getStyle('A'.$row.':P'.$row)->applyFromArray($thinBorder);
                $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('A'.$row)->applyFromArray($yellowLight);
                $sheet->getStyle('B'.$row)->applyFromArray($yellowLight);
                $sheet->getStyle('C'.$row)->applyFromArray($yellowLight);
                $sheet->getStyle('D'.$row)->applyFromArray($yellowLight);
                $sheet->getStyle('E'.$row)->applyFromArray($yellowLight);
                $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode($fmtNum);
                $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($fmtPct);
                $sheet->getStyle('F'.$row)->getNumberFormat()->setFormatCode($fmtMoney);
                $sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode($fmtMoney);
                foreach(['I','L','O'] as $pc) $sheet->getStyle($pc.$row)->getNumberFormat()->setFormatCode($fmtPct);
                foreach(['H','K','N'] as $pc) $sheet->getStyle($pc.$row)->getNumberFormat()->setFormatCode($fmtNum);
                foreach(['J','M','P'] as $pc) $sheet->getStyle($pc.$row)->getNumberFormat()->setFormatCode($fmtAccounting);
                // (Sin bordes gruesos internos entre Anterior/Actual/Acumulado)
                $sheet->getRowDimension($row)->setRowHeight(13);
                $row++;
            }
            // Total desglose
            $sheet->setCellValue('A'.$row, 'Total');
            $sheet->mergeCells('A'.$row.':F'.$row);
            $sheet->setCellValue('G'.$row, $sumDesglose);
            $sheet->setCellValue('H'.$row, 0); $sheet->setCellValue('I'.$row, 0); $sheet->setCellValue('J'.$row, 0);
            $sheet->setCellValue('K'.$row, 0); $sheet->setCellValue('L'.$row, 0); $sheet->setCellValue('M'.$row, 0);
            $sheet->setCellValue('N'.$row, 0); $sheet->setCellValue('O'.$row, 0); $sheet->setCellValue('P'.$row, 0);
            $sheet->getStyle('A'.$row.':P'.$row)->applyFromArray($thinBorder);
            $sheet->getStyle('A'.$row.':G'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode($fmtMoney);
            foreach(['H','K','N'] as $pc) $sheet->getStyle($pc.$row)->getNumberFormat()->setFormatCode($fmtNum);
            foreach(['J','M','P'] as $pc) $sheet->getStyle($pc.$row)->getNumberFormat()->setFormatCode($fmtAccounting);
            foreach(['I','L','O'] as $pc) $sheet->getStyle($pc.$row)->getNumberFormat()->setFormatCode($fmtPct);
            $sheet->getRowDimension($row)->setRowHeight(13);
            $row++;
            $row++; // separador entre lotes
        }
    }
    // Huérfanos (si los hubiera, occ sin grupo) - solo Pos/Desc/Total con amarillo
    $groupedIds = []; foreach($grupos as $gg) foreach($gg['occ_ids'] as $oid) $groupedIds[$oid]=1;
    foreach($ordenOccIds as $oid){
        if(isset($groupedIds[$oid])) continue;
        $occ = $occMap[$oid] ?? null; if(!$occ) continue;
        $pos = $occ['posicion']; $descOcc=(string)$occ['descripcion']; $ptOcc=(float)$occ['subtotal']; $cantOcc=(float)$occ['cantidad'];
        $totalMontoCM+=$ptOcc; $totalCantCM+=$cantOcc;
        $sheet->setCellValue('A'.$row,$pos); $sheet->setCellValue('B'.$row,$descOcc); $sheet->setCellValue('G'.$row,$ptOcc);
        $sheet->getStyle('A'.$row.':P'.$row)->applyFromArray($thinBorder);
        $sheet->getStyle('C'.$row.':F'.$row)->applyFromArray($celesteData);
        $sheet->getStyle('H'.$row.':P'.$row)->applyFromArray($celesteData);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('B'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row)->applyFromArray($yellowLight);
        $sheet->getStyle('B'.$row)->applyFromArray($yellowLight);
        $sheet->getStyle('G'.$row)->applyFromArray($yellowLight);
        $sheet->getRowDimension($row)->setRowHeight(14.25);
        $row++;
    }
}
$endDataRow = $row - 1;
// Bordes derechos gruesos en los extremos externos de la tabla, para cada fila de datos (G y P), sin tocar J/M
// Bordes gruesos eliminados (G/P y cierre)

// Fila TOTAL ORDEN DE COMPRA - texto ocupa A:F (sin D)
$sheet->setCellValue('A'.$row, 'Total Orden de Compra');
$sheet->mergeCells('A'.$row.':F'.$row);
$sheet->setCellValue('G'.$row, $totalMontoCM);
$sheet->setCellValue('J'.$row, $totalAcumAnt);
$sheet->setCellValue('M'.$row, $totalAcumAct);
$sheet->setCellValue('P'.$row, $totalAcumAcu);
$totalRow = $row;
$sheet->getStyle('A'.$row.':P'.$row)->applyFromArray($thinBorder);
$sheet->getStyle('A'.$row.':P'.$row)->getFont()->setBold(true);
$sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
foreach (['G'] as $cc) { $sheet->getStyle($cc.$row)->getNumberFormat()->setFormatCode($fmtMoney); $sheet->getStyle($cc.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER); }
foreach (['J','M','P'] as $cc) { $sheet->getStyle($cc.$row)->getNumberFormat()->setFormatCode($fmtAccounting); $sheet->getStyle($cc.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER); }
$sheet->getRowDimension($row)->setRowHeight(15);

// Notas 100% ancho - igual en ambas hojas (recomendada)
$rowNotas = $row + 3;
$sheet->setCellValue('A'.$rowNotas, 'Notas:');
$sheet->mergeCells('A'.$rowNotas.':P'.$rowNotas);
$sheet->getStyle('A'.$rowNotas.':P'.$rowNotas)->applyFromArray($greyHeader);
$sheet->getStyle('A'.$rowNotas.':P'.$rowNotas)->applyFromArray($boxOutline);
$sheet->mergeCells('A'.($rowNotas+1).':P'.($rowNotas+5));
$sheet->setCellValue('A'.($rowNotas+1), (string)($cm['observaciones'] ?? ''));
$sheet->getStyle('A'.($rowNotas+1).':P'.($rowNotas+5))->applyFromArray($thinBorder);
$sheet->getStyle('A'.($rowNotas+1).':P'.($rowNotas+5))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
for($rn=$rowNotas;$rn<=$rowNotas+5;$rn++) $sheet->getRowDimension($rn)->setRowHeight(14.25);

// Filas financieras debajo de Notas 100% - ancho completo A:M
$rowFin1 = $rowNotas + 7; // Total Certificado debajo de Notas
$sheet->setCellValue('A'.$rowFin1, 'Total Certificado');
$sheet->mergeCells('A'.$rowFin1.':K'.$rowFin1);
$sheet->setCellValue('L'.$rowFin1, 0);
$sheet->getStyle('L'.$rowFin1)->getNumberFormat()->setFormatCode($fmtPct);
$sheet->getStyle('L'.$rowFin1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->setCellValue('M'.$rowFin1, 0);
$sheet->getStyle('M'.$rowFin1)->getNumberFormat()->setFormatCode($fmtMoney);
$sheet->getStyle('M'.$rowFin1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A'.$rowFin1.':P'.$rowFin1)->applyFromArray($thinBorder);
$sheet->getStyle('A'.$rowFin1)->getFont()->setBold(true);

$rowFin2 = $rowFin1 + 1; // Desacopio - % y monto del anticipo sobre total CM
$pctAnticipoCM = (float)($cm['porcentaje_anticipo'] ?? 0) / 100;
$baseCMMaestro = (float)($cm['monto_total'] ?? 0);
$montoDesacopioCM = round($baseCMMaestro * $pctAnticipoCM, 2);
$sheet->setCellValue('A'.$rowFin2, 'Desacopio de anticipo');
$sheet->mergeCells('A'.$rowFin2.':K'.$rowFin2);
$sheet->setCellValue('L'.$rowFin2, $pctAnticipoCM);
$sheet->getStyle('L'.$rowFin2)->getNumberFormat()->setFormatCode($fmtPct);
$sheet->getStyle('L'.$rowFin2)->applyFromArray($yellowLight);
$sheet->getStyle('L'.$rowFin2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->setCellValue('M'.$rowFin2, $montoDesacopioCM);
$sheet->getStyle('M'.$rowFin2)->getNumberFormat()->setFormatCode($fmtMoney);
$sheet->getStyle('M'.$rowFin2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A'.$rowFin2.':P'.$rowFin2)->applyFromArray($thinBorder);
$sheet->getStyle('A'.$rowFin2)->getFont()->setBold(true);

// CAC vacío - subir Fondo de reparo
$rowFin3 = $rowFin2 + 1; // Fondo inmediato tras Desacopio
$sheet->setCellValue('A'.$rowFin3, 'Fondo de reparo');
$sheet->mergeCells('A'.$rowFin3.':K'.$rowFin3);
$sheet->setCellValue('L'.$rowFin3, 0);
$sheet->getStyle('L'.$rowFin3)->getNumberFormat()->setFormatCode($fmtPct);
$sheet->getStyle('L'.$rowFin3)->applyFromArray($yellowLight);
$sheet->getStyle('L'.$rowFin3)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->setCellValue('M'.$rowFin3, 0);
$sheet->getStyle('M'.$rowFin3)->getNumberFormat()->setFormatCode($fmtMoney);
$sheet->getStyle('M'.$rowFin3)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A'.$rowFin3.':P'.$rowFin3)->applyFromArray($thinBorder);
$sheet->getStyle('A'.$rowFin3)->getFont()->setBold(true);
// Borde derecho continuo desde última celda con fondo Notas (G) hasta Fondo de reparo (M) - incluye hueco
for($r=$rowNotas; $r<=$rowFin3; $r++) {
    $sheet->getStyle('M'.$r)->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B0B0B0');
}
// Borde exterior derecho del bloque completo (P) para hueco entre bloques
for($r=$rowNotas; $r<=$rowFin3; $r++) {
    $sheet->getStyle('P'.$r)->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B0B0B0');
    if($r <= $rowNotas+5) $sheet->getStyle('G'.$r)->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B0B0B0');
}
$rowFirma = $rowFin3 + 4;
$boxOutline = ['borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]]];
$sheet->getStyle('A'.$rowFirma.':D'.($rowFirma+6))->applyFromArray($boxOutline);
$sheet->getStyle('E'.$rowFirma.':K'.($rowFirma+6))->applyFromArray($boxOutline);
$sheet->getStyle('L'.$rowFirma.':P'.($rowFirma+6))->applyFromArray($boxOutline);
for($br=$rowFirma;$br<=$rowFirma+6;$br++) $sheet->getRowDimension($br)->setRowHeight(14.25);
$sheet->setCellValue('A'.($rowFirma+7), 'FIRMA NH'); $sheet->mergeCells('A'.($rowFirma+7).':D'.($rowFirma+7));
$sheet->setCellValue('E'.($rowFirma+7), 'FIRMA CLIENTE'); $sheet->mergeCells('E'.($rowFirma+7).':K'.($rowFirma+7));
$sheet->setCellValue('L'.($rowFirma+7), 'FIRMA OTRO'); $sheet->mergeCells('L'.($rowFirma+7).':P'.($rowFirma+7));
$sheet->getStyle('A'.($rowFirma+7).':D'.($rowFirma+7))->applyFromArray($greyHeader);
$sheet->getStyle('E'.($rowFirma+7).':K'.($rowFirma+7))->applyFromArray($greyHeader);
$sheet->getStyle('L'.($rowFirma+7).':P'.($rowFirma+7))->applyFromArray($greyHeader);
$sheet->getStyle('A'.($rowFirma+7).':D'.($rowFirma+7))->applyFromArray($boxOutline);
$sheet->getStyle('E'.($rowFirma+7).':K'.($rowFirma+7))->applyFromArray($boxOutline);
$sheet->getStyle('L'.($rowFirma+7).':P'.($rowFirma+7))->applyFromArray($boxOutline);
$sheet->getStyle('A'.($rowFirma+7).':P'.($rowFirma+7))->getFont()->setBold(true)->setSize(9);
$sheet->getStyle('A'.($rowFirma+7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E'.($rowFirma+7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('L'.($rowFirma+7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Ajustar PrintArea (sin Observaciones en esta hoja)
$sheet->getPageSetup()->setPrintArea('A1:P'.($rowFirma+7));

// =====================================================
// HOJA 2: Medicion (A:K) - espejo con IFERROR
// =====================================================
// La hoja de medición queda desactivada temporalmente; conservar el bloque permite reactivarla.
$incluirMedicion = false;
$med = $book->createSheet();
$med->setTitle('Medicion');
$med->setShowGridLines(false);
$med->getSheetView()->setZoomScale(76);
$wMed = ['A'=>4.125,'B'=>26.625,'C'=>8.375,'D'=>11,'E'=>10.25,'F'=>7.625,'G'=>10.625,'H'=>7.625,'I'=>10.875,'J'=>7.625,'K'=>16.125];
foreach ($wMed as $c=>$w) $med->getColumnDimension($c)->setWidth($w);
$med->getPageMargins()->setTop(0.748)->setRight(0.708)->setBottom(0.748)->setLeft(0.708);
$med->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
$med->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$med->getPageSetup()->setFitToPage(true);
$med->getPageSetup()->setScale(34);
$med->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(15, 16);

// Logo en Medicion también - 2,75cm cuadrado + empresa en B1:B6
foreach (['A1'] as $coord) {
    $p = __DIR__.'/assets/images/logo.jpg';
    if (is_file($p)) {
        $d = new Drawing();
        $d->setPath($p);
        $d->setResizeProportional(false);
        $d->setWidth(104); $d->setHeight(104);
        $d->setCoordinates($coord);
        $d->setOffsetX(2); $d->setOffsetY(2);
        $d->setWorksheet($med);
    }
}
for ($r=1;$r<=6;$r++) $med->getRowDimension($r)->setRowHeight(13.5);
$med->mergeCells('B1:B6');
$med->setCellValue('B1', "NH Construcciones SRL\nRicardo Gutiérrez 2874\n(C1417EBL) - CABA\nTel./Fax (54 11) 4505-8300");
$med->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$med->getStyle('B1')->getFont()->setSize(7)->setName('Calibri');
$med->setCellValue('C1','MEDICION');
$med->mergeCells('C1:I6');
$med->getStyle('C1')->getFont()->setBold(true)->setSize(14)->setName('Cambria');
$med->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$med->mergeCells('J1:K6');
$med->getStyle('A1:K6')->applyFromArray($thinBorder);

// Header Medicion (row8-13) espejo Certificado
$med->setCellValue('A8','Datos del Proyecto y Orden de Compra'); $med->mergeCells('A8:E8');
$med->getStyle('A8')->applyFromArray($blueHeader);
$med->setCellValue('H8','Datos de la Medición'); $med->mergeCells('H8:K8');
$med->getStyle('H8')->applyFromArray($blueHeader);
$med->setCellValue('A9','Cliente'); $med->setCellValue('C9', $cm['cliente']); $med->mergeCells('A9:B9'); $med->mergeCells('C9:E9');
$med->setCellValue('H9','Numero'); $med->setCellValue('J9', $cm['id']); $med->mergeCells('H9:I9'); $med->mergeCells('J9:K9');
$med->setCellValue('A10','Proyecto'); $med->setCellValue('C10', $proyectosNombre); $med->mergeCells('A10:B10'); $med->mergeCells('C10:E10');
$med->setCellValue('H10','Revisión'); $med->setCellValue('J10','1'); $med->mergeCells('H10:I10'); $med->mergeCells('J10:K10');
$med->setCellValue('A11','Orden de Compra'); $med->setCellValue('C11', $cm['numero_occ']); $med->mergeCells('A11:B11'); $med->mergeCells('C11:E11');
$med->setCellValue('H11','Periodo desde'); $med->setCellValue('J11', cmFmtFecha($cm['fecha_inicio'])); $med->mergeCells('H11:I11'); $med->mergeCells('J11:K11');
$med->setCellValue('A12','Moneda y Monto'); $med->setCellValue('C12', $cm['moneda'].' '.number_format((float)$cm['monto_total'],2,',','.')); $med->mergeCells('A12:B12'); $med->mergeCells('C12:E12');
$med->setCellValue('H12','Periodo hasta'); $med->setCellValue('J12', cmFmtFecha($cm['fecha_fin'])); $med->mergeCells('H12:I12'); $med->mergeCells('J12:K12');
$med->setCellValue('A13','Fecha'); $med->setCellValue('C13', cmFmtFecha($cm['fecha_emision'])); $med->mergeCells('A13:B13'); $med->mergeCells('C13:E13');
$med->setCellValue('H13','Fecha emisión'); $med->setCellValue('J13', cmFmtFecha($cm['fecha_emision'])); $med->mergeCells('H13:I13'); $med->mergeCells('J13:K13');
foreach (['A9','A10','A11','A12','A13','H9','H10','H11','H12','H13'] as $c) $med->getStyle($c)->applyFromArray($greyHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
foreach (['C9','C10','C11','C12','C13','J9','J10','J11','J12','J13'] as $c) $med->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$med->getStyle('C13')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$med->getStyle('J13')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$med->getStyle('J11')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$med->getStyle('J12')->getNumberFormat()->setFormatCode('dd/mm/yy;@');
$med->getStyle('A8:E13')->applyFromArray($thinBorder);
$med->getStyle('H8:K13')->applyFromArray($thinBorder);

// Tabla Medicion header 15-16
$med->setCellValue('A15','Pos'); $med->mergeCells('A15:A16');
$med->setCellValue('B15','Descripción'); $med->mergeCells('B15:B16');
$med->setCellValue('C15','Unidad'); $med->mergeCells('C15:C16');
$med->setCellValue('D15','Cantidad'); $med->mergeCells('D15:D16');
$med->setCellValue('E15','Anterior'); $med->mergeCells('E15:F15');
$med->setCellValue('G15','Actual'); $med->mergeCells('G15:H15');
$med->setCellValue('I15','Acumulado'); $med->mergeCells('I15:J15');
$med->setCellValue('K15','Observaciones'); $med->mergeCells('K15:K16');
$med->getStyle('A15:K15')->getFont()->setBold(true)->setSize(10);
$med->getStyle('A15:K15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$med->setCellValue('E16','Cantidad'); $med->setCellValue('F16','%');
$med->setCellValue('G16','Cantidad'); $med->setCellValue('H16','%');
$med->setCellValue('I16','Cantidad'); $med->setCellValue('J16','%');
$med->getStyle('E16:J16')->getFont()->setBold(true)->setSize(10);
$med->getStyle('E16:J16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$med->getStyle('A15:K16')->applyFromArray($thinBorder);

// Datos Medicion agrupados juntos (sin repetir títulos Pos/Descripción)
$mRow = 17;
if (empty($grupos) && empty($ordenOccIds)) {
    $med->setCellValue('A17','-'); $med->setCellValue('B17','Sin items');
    cmRowBorder($med, 17, 'A', 'K');
    $mRow = 18;
} else {
    foreach ($grupos as $g) {
        foreach ($g['occ_ids'] as $oid) {
            $occ = $occMap[$oid] ?? null; if (!$occ) continue;
            $pos = $occ['posicion']; $descOcc=(string)$occ['descripcion'];
            $med->setCellValue('A'.$mRow, $pos);
            $med->setCellValue('B'.$mRow, $descOcc);
            $med->setCellValue('E'.$mRow, 0);
            $med->setCellValue('F'.$mRow, 0);
            $med->setCellValue('G'.$mRow, 0);
            $med->setCellValue('H'.$mRow, 0);
            $med->setCellValue('I'.$mRow, 0);
            $med->setCellValue('J'.$mRow, 0);
            $med->getStyle('E'.$mRow)->getNumberFormat()->setFormatCode($fmtNum);
            $med->getStyle('G'.$mRow)->getNumberFormat()->setFormatCode($fmtNum);
            $med->getStyle('I'.$mRow)->getNumberFormat()->setFormatCode($fmtNum);
            $med->getStyle('F'.$mRow)->getNumberFormat()->setFormatCode($fmtPct);
            $med->getStyle('H'.$mRow)->getNumberFormat()->setFormatCode($fmtPct);
            $med->getStyle('J'.$mRow)->getNumberFormat()->setFormatCode($fmtPct);
            // Borde thin en TODO el rango A:K de la fila (padre de grupo), celda por celda
            cmRowBorder($med, $mRow, 'A', 'K');
            $med->getStyle('A'.$mRow.':K'.$mRow)->applyFromArray($celesteFill);
            $med->getStyle('A'.$mRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $med->getStyle('B'.$mRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $med->getStyle('B'.$mRow)->getFont()->setBold(true);
            $med->getRowDimension($mRow)->setRowHeight(14.25);
            $mRow++;
        }
        if (!empty($g['filas'])) {
            $ownerPos = $occMap[$g['occ_ids'][0]]['posicion'] ?? '10';
            foreach ($g['filas'] as $fi => $fila) {
                $posDes = (string)($fila['posicion_aperturado'] ?? '') ?: ($ownerPos . '.' . ($fi+1));
                $descDes = (string)$fila['descripcion'];
                $unidadDes = (string)($fila['unidad_medida'] ?? '');
                $cantDes = (float)$fila['cantidad'];
                $med->setCellValueExplicit('A'.$mRow, (string)$posDes, DataType::TYPE_STRING);
                $med->getStyle('A'.$mRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $med->setCellValue('B'.$mRow, $descDes);
                $med->setCellValue('C'.$mRow, $unidadDes);
                $med->setCellValue('D'.$mRow, $cantDes);
                $med->setCellValue('E'.$mRow, 0); $med->setCellValue('F'.$mRow, 0);
                $med->setCellValue('G'.$mRow, 0); $med->setCellValue('H'.$mRow, 0);
                $med->setCellValue('I'.$mRow, 0); $med->setCellValue('J'.$mRow, 0);
                // Borde thin en TODO el rango A:K de la fila (desglose), celda por celda
                cmRowBorder($med, $mRow, 'A', 'K');
                $med->getStyle('E'.$mRow)->applyFromArray($yellowLight);
                $med->getStyle('G'.$mRow)->applyFromArray($yellowLight);
                $med->getStyle('A'.$mRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $med->getStyle('B'.$mRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $med->getStyle('D'.$mRow)->getNumberFormat()->setFormatCode($fmtNum);
                $med->getStyle('E'.$mRow)->getNumberFormat()->setFormatCode($fmtNum);
                $med->getStyle('F'.$mRow)->getNumberFormat()->setFormatCode($fmtPct);
                $med->getStyle('G'.$mRow)->getNumberFormat()->setFormatCode($fmtNum);
                $med->getStyle('H'.$mRow)->getNumberFormat()->setFormatCode($fmtPct);
                $med->getStyle('I'.$mRow)->getNumberFormat()->setFormatCode($fmtNum);
                $med->getStyle('J'.$mRow)->getNumberFormat()->setFormatCode($fmtPct);
                $med->getRowDimension($mRow)->setRowHeight(13);
                $mRow++;
            }
            $mRow++;
        }
    }
    $groupedIds = []; foreach($grupos as $gg) foreach($gg['occ_ids'] as $oid) $groupedIds[$oid]=1;
    foreach($ordenOccIds as $oid){
        if(isset($groupedIds[$oid])) continue;
        $occ=$occMap[$oid]??null; if(!$occ) continue;
        $pos=$occ['posicion']; $descOcc=(string)$occ['descripcion'];
        $med->setCellValue('A'.$mRow,$pos); $med->setCellValue('B'.$mRow,$descOcc);
        // Borde thin en TODO el rango A:K de la fila (huérfano), celda por celda
        cmRowBorder($med, $mRow, 'A', 'K');
        $med->getStyle('C'.$mRow.':J'.$mRow)->applyFromArray($celesteFill);
        $med->getStyle('A'.$mRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $med->getStyle('B'.$mRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $med->getStyle('B'.$mRow)->getFont()->setBold(true);
        $med->getStyle('A'.$mRow)->applyFromArray($yellowLight);
        $med->getStyle('B'.$mRow)->applyFromArray($yellowLight);
        $med->getRowDimension($mRow)->setRowHeight(14.25);
        $mRow++;
    }
}
// Bordes derechos gruesos en los extremos de cada sección (igual criterio que Certificado 1: G y P allí, D y K aquí)
$medEndDataRow = $mRow - 1;

// =====================================================
// Notas y Firmas al final de la hoja Medicion
// =====================================================
// Notas: cuadro A:K, 4 filas de alto (1 fila de título + 3 filas de contenido)
$rowNotasMed = $medEndDataRow + 3;
$med->setCellValue('A'.$rowNotasMed, 'Notas:');
$med->mergeCells('A'.$rowNotasMed.':K'.$rowNotasMed);
$med->getStyle('A'.$rowNotasMed.':K'.$rowNotasMed)->applyFromArray($greyHeader);
$med->getStyle('A'.$rowNotasMed.':K'.$rowNotasMed)->applyFromArray($boxOutline);
$notasContentStart = $rowNotasMed + 1;
$notasContentEnd = $rowNotasMed + 3; // 3 filas de contenido; junto al título suman 4 filas de alto
$med->mergeCells('A'.$notasContentStart.':K'.$notasContentEnd);
$med->setCellValue('A'.$notasContentStart, (string)($cm['observaciones'] ?? ''));
$med->getStyle('A'.$notasContentStart.':K'.$notasContentEnd)->applyFromArray($thinBorder);
$med->getStyle('A'.$notasContentStart.':K'.$notasContentEnd)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
for ($rn=$rowNotasMed; $rn<=$notasContentEnd; $rn++) $med->getRowDimension($rn)->setRowHeight(14.25);
// Borde derecho continuo Medición desde Notas hasta Fondo (K)
for($r=$rowNotasMed; $r<=$rowNotasMed+3; $r++) $med->getStyle('K'.$r)->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B0B0B0');

for ($rn=$rowNotasMed; $rn<=$notasContentEnd; $rn++) $med->getRowDimension($rn)->setRowHeight(14.25);

// Firmas: 2 filas de diferencia respecto al cuadro de Notas
$rowFirmaMed = $notasContentEnd + 3; // deja 2 filas vacías (notasContentEnd+1 y +2) antes de empezar
$rowFirmaMedEnd = $rowFirmaMed + 7; // campos de firma: 8 filas de alto
$med->getStyle('A'.$rowFirmaMed.':C'.$rowFirmaMedEnd)->applyFromArray($boxOutline);
$med->getStyle('D'.$rowFirmaMed.':H'.$rowFirmaMedEnd)->applyFromArray($boxOutline);
$med->getStyle('I'.$rowFirmaMed.':K'.$rowFirmaMedEnd)->applyFromArray($boxOutline);
for ($fr=$rowFirmaMed; $fr<=$rowFirmaMedEnd; $fr++) $med->getRowDimension($fr)->setRowHeight(14.25);

// Títulos debajo de cada caja de firma
$rowFirmaLabelMed = $rowFirmaMedEnd + 1;
$med->setCellValue('A'.$rowFirmaLabelMed, 'FIRMA NH'); $med->mergeCells('A'.$rowFirmaLabelMed.':C'.$rowFirmaLabelMed);
$med->setCellValue('D'.$rowFirmaLabelMed, 'FIRMA CLIENTE'); $med->mergeCells('D'.$rowFirmaLabelMed.':H'.$rowFirmaLabelMed);
$med->setCellValue('I'.$rowFirmaLabelMed, 'FIRMA OTRO'); $med->mergeCells('I'.$rowFirmaLabelMed.':K'.$rowFirmaLabelMed);
$med->getStyle('A'.$rowFirmaLabelMed.':C'.$rowFirmaLabelMed)->applyFromArray($greyHeader);
$med->getStyle('D'.$rowFirmaLabelMed.':H'.$rowFirmaLabelMed)->applyFromArray($greyHeader);
$med->getStyle('I'.$rowFirmaLabelMed.':K'.$rowFirmaLabelMed)->applyFromArray($greyHeader);
$med->getStyle('A'.$rowFirmaLabelMed.':C'.$rowFirmaLabelMed)->applyFromArray($boxOutline);
$med->getStyle('D'.$rowFirmaLabelMed.':H'.$rowFirmaLabelMed)->applyFromArray($boxOutline);
$med->getStyle('I'.$rowFirmaLabelMed.':K'.$rowFirmaLabelMed)->applyFromArray($boxOutline);
$med->getStyle('A'.$rowFirmaLabelMed.':K'.$rowFirmaLabelMed)->getFont()->setBold(true)->setSize(9);
$med->getStyle('A'.$rowFirmaLabelMed)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$med->getStyle('D'.$rowFirmaLabelMed)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$med->getStyle('I'.$rowFirmaLabelMed)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Ajustar PrintArea de Medicion para incluir Notas y Firmas
$med->getPageSetup()->setPrintArea('A1:K'.$rowFirmaLabelMed);

if (!$incluirMedicion) {
    $book->removeSheetByIndex($book->getIndex($med));
}

// Activar primera hoja
$book->setActiveSheetIndex(0);

// --- Output ---
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="certificado_maestro_'.$idCm.'.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($book);
$writer->save('php://output');
exit;
