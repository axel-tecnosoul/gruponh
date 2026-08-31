<?php
require 'config.php';
require 'database.php';
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$idCm = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idCm) {
    http_response_code(400);
    die('Certificado Maestro inválido.');
}

// --------------------------------------------------------------
// Funciones auxiliares
// --------------------------------------------------------------
function cmPdfEsc($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function cmPdfMoney($value, string $currency): string {
    return cmPdfEsc($currency) . ' ' . number_format((float) $value, 2, ',', '.');
}
function cmPdfNum($value): string {
    return number_format((float) $value, 2, ',', '.');
}
function cmFmtFecha($v) {
    if (empty($v) || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return '-';
    $t = strtotime($v);
    return $t ? date('d/m/Y', $t) : '-';
}

// --------------------------------------------------------------
// Logo
// --------------------------------------------------------------
$logoPath = __DIR__ . '/assets/images/logo.jpg';
$logoData = is_file($logoPath) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath)) : '';

// --------------------------------------------------------------
// Conexión a la BD
// --------------------------------------------------------------
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cm.id, cm.id_occ, cm.fecha_emision, cm.fecha_inicio, cm.fecha_fin, cm.monto_total, cm.porcentaje_anticipo,
               cm.monto_acumulado_avances, cm.monto_acumulado_anticipos, cm.monto_acumulado_desacopios,
               cm.monto_acumulado_descuentos, cm.monto_acumulado_ajustes, cm.observaciones, cm.aprobado_cliente,
               occ.numero AS numero_occ, cu.nombre AS cliente, m.moneda
        FROM certificados_maestros cm
        INNER JOIN occ ON occ.id = cm.id_occ
        INNER JOIN cuentas cu ON cu.id = occ.id_cuenta_cliente
        INNER JOIN monedas m ON m.id = cm.id_moneda
        WHERE cm.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idCm]);
$cm = $q->fetch(PDO::FETCH_ASSOC);
if (!$cm) {
    Database::disconnect();
    http_response_code(404);
    die('Certificado Maestro no encontrado.');
}

$q = $pdo->prepare("SELECT GROUP_CONCAT(DISTINCT p.nombre SEPARATOR ', ') FROM certificados_maestros_detalles cmd LEFT JOIN proyectos p ON p.id = cmd.id_proyecto WHERE cmd.id_certificado_maestro = ?");
$q->execute([$idCm]);
$proyectosNombre = (string) ($q->fetchColumn() ?: '-');

// Items del CM
$sql = "SELECT
        od.posicion as posicion_occ,
        cmd.id as cmd_id,
        cmd.descripcion,
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
        LEFT JOIN occ_detalles od ON od.id = cmd.id_occ_detalle
        LEFT JOIN unidades_medida um ON um.id = cmd.id_unidad_medida
        LEFT JOIN certificados_avances_detalle cad ON cad.id_certificado_maestro_detalle = cmd.id
        WHERE cmd.id_certificado_maestro = ?
        GROUP BY cmd.id
        ORDER BY cmd.lote, cmd.aperturado, cmd.id";
$q = $pdo->prepare($sql);
$q->execute([$idCm]);
$items = $q->fetchAll(PDO::FETCH_ASSOC);

// Desglose agrupado (replica Excel)
$occStmt = $pdo->prepare("SELECT id, posicion, descripcion, cantidad, precio_unitario, descuento, subtotal FROM occ_detalles WHERE id_occ = ? ORDER BY posicion, id");
$occStmt->execute([$cm['id_occ']]);
$occRows = $occStmt->fetchAll(PDO::FETCH_ASSOC);
$occMap = [];
foreach ($occRows as $or) {
    $occMap[(int)$or['id']] = $or;
}

$qGrupos = $pdo->prepare("SELECT aperturado, lote, modo_generacion, COALESCE(MAX(monto_base_occ),0) as monto_base_occ, COALESCE(SUM(subtotal),0) as subtotal_lote, MIN(id) as min_id FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado IS NOT NULL AND aperturado != '' GROUP BY aperturado, lote, modo_generacion ORDER BY min_id");
$qGrupos->execute([$idCm]);
$rawGrupos = $qGrupos->fetchAll(PDO::FETCH_ASSOC);

$grupos = [];
foreach ($rawGrupos as $rg) {
    $ap = $rg['aperturado'];
    $qOccIds = $pdo->prepare("SELECT id_occ_detalle FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro = ? AND aperturado = ? ORDER BY id_occ_detalle");
    $qOccIds->execute([$idCm, $ap]);
    $occIds = array_map('intval', $qOccIds->fetchAll(PDO::FETCH_COLUMN));
    if (empty($occIds)) {
        $qF = $pdo->prepare("SELECT DISTINCT id_occ_detalle FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ? AND id_occ_detalle IS NOT NULL");
        $qF->execute([$idCm, $ap]);
        $occIds = array_map('intval', $qF->fetchAll(PDO::FETCH_COLUMN));
    }
    usort($occIds, function ($a, $b) use ($occMap) {
        $pa = (int)($occMap[$a]['posicion'] ?? 9999);
        $pb = (int)($occMap[$b]['posicion'] ?? 9999);
        return $pa <=> $pb ?: $a <=> $b;
    });
    $qFilas = $pdo->prepare("SELECT cmd.descripcion, um.unidad_medida, cmd.cantidad, cmd.incidencia_porcentaje, cmd.precio_unitario, cmd.subtotal FROM certificados_maestros_detalles cmd LEFT JOIN unidades_medida um ON um.id = cmd.id_unidad_medida WHERE cmd.id_certificado_maestro = ? AND cmd.aperturado = ? ORDER BY cmd.id");
    $qFilas->execute([$idCm, $ap]);
    $filas = $qFilas->fetchAll(PDO::FETCH_ASSOC);
    $owner = !empty($occIds) ? $occIds[0] : null;
    $grupos[] = [
        'aperturado' => $ap,
        'lote' => $rg['lote'],
        'modo' => $rg['modo_generacion'],
        'base' => (float)$rg['monto_base_occ'],
        'subtotal' => (float)$rg['subtotal_lote'],
        'occ_ids' => $occIds,
        'owner' => $owner,
        'filas' => $filas
    ];
}

// Orden visual
$ordenOccIds = [];
$vistos = [];
foreach ($grupos as $g) {
    foreach ($g['occ_ids'] as $oid) {
        if (!isset($vistos[$oid])) {
            $vistos[$oid] = 1;
            $ordenOccIds[] = $oid;
        }
    }
}
foreach ($occRows as $or) {
    $oid = (int)$or['id'];
    if (!isset($vistos[$oid])) {
        $qChk = $pdo->prepare("SELECT COUNT(*) FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND id_occ_detalle = ? AND (aperturado IS NULL OR aperturado = '')");
        $qChk->execute([$idCm, $oid]);
        if ($qChk->fetchColumn() > 0) {
            $vistos[$oid] = 1;
            $ordenOccIds[] = $oid;
        }
    }
}
if (empty($ordenOccIds)) {
    foreach ($occRows as $or) {
        $ordenOccIds[] = (int)$or['id'];
    }
}

Database::disconnect();

// --------------------------------------------------------------
// CSS
// --------------------------------------------------------------
$css = '
<style>
    @page {
        size: A4 landscape;
        margin: 24mm 8mm 16mm 8mm;
    }
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 6.5pt;
        color: #20252b;
        margin: 0;
    }
    /* Cabecera fija (logo + título + datos empresa) */
    .pdf-header {
        position: fixed;
        top: -18mm;
        left: 0;
        right: 0;
        height: 14mm;
        background: white;
        padding: 1mm 8mm 0;
        border-bottom: 1px solid #17365d;
        width: 100%;
    }
    .pdf-header table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        height: 100%;
    }
    .pdf-header td {
        vertical-align: middle;
        padding: 0 2mm;
    }
    .pdf-header-logo {
        width: 16mm;
        height: 10mm;
        object-fit: contain;
    }
    .header-center {
        text-align: center;
    }
    .header-title {
        color: #111;
        font-size: 13pt;
        font-weight: bold;
        display: block;
    }
    .header-sub {
        font-size: 6.5pt;
        color: #555;
        margin-top: 0.5mm;
    }
    .header-company {
        border: 1px solid #999;
        padding: 0.8mm 1.5mm;
        font-size: 6pt;
        color: #555;
        line-height: 1.3;
        text-align: right;
    }
    /* Pie de página */
    .pdf-footer {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        height: 7mm;
        border-top: 1px solid #999;
        color: #666;
        font-size: 6.5pt;
        padding: 1mm 8mm 0;
        background: white;
    }
    .pdf-footer-right { float: right; }

    /* Tablas principales */
    .ca-table, .med-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0;
        table-layout: fixed;
        font-size: 6.5pt;
    }
    .ca-table th, .ca-table td,
    .med-table th, .med-table td {
        border: 1px solid #B0B0B0;
        padding: 0.8mm 0.5mm;
        vertical-align: middle;
        word-wrap: break-word;
    }
    .ca-table th, .med-table th {
        background: #BFBFBF;
        font-weight: bold;
        text-align: center;
    }
    .parent-yellow { background: #FFE080; }
    .parent-blue   { background: #9BC2E6; }
    .child-yellow  { background: #FFE080; }
    .total-row td  { background: #EEF3F8 !important; font-weight: bold; }
    .subtotal-row td { background: #E8E8E8 !important; font-weight: bold; }

    .col-precio-total { border-right: 2px solid #000 !important; }
    .col-acu-monto    { border-right: 2px solid #000 !important; }

    .text-center { text-align: center; }
    .text-right  { text-align: right; }
    .text-left   { text-align: left; }
    .num         { text-align: right; font-variant-numeric: tabular-nums; }

    .sub-header td {
        background: #FFFFFF;
        border: 1px solid #B0B0B0;
        padding: 0.5mm 0.5mm;
    }
    .sub-header .label {
        background: #BFBFBF;
        font-weight: bold;
        padding: 0.5mm 0.5mm;
    }
    .sub-header .value {
        font-weight: bold;
        padding: 0.5mm 0.5mm;
    }

    .footer-block {
        width: 100%;
        border-collapse: collapse;
        margin-top: 3mm;
        font-size: 6.5pt;
    }
    .footer-block td {
        border: 1px solid #B0B0B0;
        padding: 0.8mm 0.8mm;
        vertical-align: top;
    }
    .footer-block .label {
        background: #BFBFBF;
        font-weight: bold;
        text-align: left;
    }
    .footer-block .num {
        text-align: right;
    }
    .notas-cell {
        min-height: 6mm;
        font-size: 6pt;
    }
    .page-break {
        page-break-before: always;
    }
</style>
';

$html = '<!doctype html><html lang="es"><head><meta charset="UTF-8">' . $css . '</head><body>';

// --------------------------------------------------------------
// Datos del CM
// --------------------------------------------------------------
$moneda = (string) $cm['moneda'];
$nroCM = (int) $cm['id'];
$nroOCC = (string) $cm['numero_occ'];
$logoHtml = $logoData !== '' ? '<img class="pdf-header-logo" src="' . $logoData . '">' : '';

// --------------------------------------------------------------
// CABECERA FIJA (usando tabla)
// --------------------------------------------------------------
$html .= '<div class="pdf-header">';
$html .= '<table>';
$html .= '<tr>';
// Columna 1: Logo (20%)
$html .= '<td style="width:20%;">' . $logoHtml . '</td>';
// Columna 2: Título (50%)
$html .= '<td class="header-center" style="width:50%;">';
$html .= '<span class="header-title">CERTIFICADO MAESTRO</span>';
$html .= '<span class="header-sub">CM #' . $nroCM . ' | OCC #' . $nroOCC . '</span>';
$html .= '</td>';
// Columna 3: Datos empresa (30%)
$html .= '<td style="width:30%;">';
$html .= '<div class="header-company">';
$html .= 'NH Construcciones SRL<br>';
$html .= 'Ricardo Gutiérrez 2874 (C1417EBL) - CABA<br>';
$html .= 'Tel./Fax (54 11) 4505-8300';
$html .= '</div>';
$html .= '</td>';
$html .= '</tr>';
$html .= '</table>';
$html .= '</div>';

// Pie de página
$html .= '<div class="pdf-footer"><span>Grupo NH | Certificado Maestro</span><span class="pdf-footer-right">Generado el ' . date('d/m/Y H:i') . ' | Página </span></div>';

// --------------------------------------------------------------
// HOJA 1: CERTIFICADO
// --------------------------------------------------------------
$html .= '<table class="ca-table">';
$html .= '<thead>';

// Fila de datos del proyecto y CA
$html .= '<tr class="sub-header">';
$html .= '<td colspan="8" style="border-right: 2px solid #000;">';
$html .= '<table style="width:100%; border-collapse:collapse; font-size:6pt;">';
$html .= '<tr><td class="label" style="width:20%;">Cliente</td><td class="value" style="width:30%;">' . cmPdfEsc($cm['cliente']) . '</td>'
       . '<td class="label" style="width:20%;">Proyecto</td><td class="value" style="width:30%;">' . cmPdfEsc($proyectosNombre) . '</td></tr>';
$html .= '<tr><td class="label">Orden de Compra</td><td class="value">' . cmPdfEsc($cm['numero_occ']) . '</td>'
       . '<td class="label">Moneda y Monto</td><td class="value">' . cmPdfMoney($cm['monto_total'], $moneda) . '</td></tr>';
$html .= '<tr><td class="label">Fecha</td><td class="value">' . cmFmtFecha($cm['fecha_emision']) . '</td>'
       . '<td class="label"></td><td class="value"></td></tr>';
$html .= '</table>';
$html .= '</td>';
$html .= '<td colspan="8">';
$html .= '<table style="width:100%; border-collapse:collapse; font-size:6pt;">';
$html .= '<tr><td class="label" style="width:20%;">Número</td><td class="value" style="width:30%;">' . $nroCM . '</td>'
       . '<td class="label" style="width:20%;">Revisión</td><td class="value" style="width:30%;">1</td></tr>';
$html .= '<tr><td class="label">Periodo desde</td><td class="value">' . cmFmtFecha($cm['fecha_inicio']) . '</td>'
       . '<td class="label">Periodo hasta</td><td class="value">' . cmFmtFecha($cm['fecha_fin']) . '</td></tr>';
$html .= '<tr><td class="label">Fecha emisión</td><td class="value">' . cmFmtFecha($cm['fecha_emision']) . '</td>'
       . '<td class="label"></td><td class="value"></td></tr>';
$html .= '</table>';
$html .= '</td>';
$html .= '</tr>';

// Encabezados de columnas
$html .= '<tr>';
$html .= '<th rowspan="2" style="width:4%;">Pos</th>';
$html .= '<th rowspan="2" style="width:22%;">Descripción</th>';
$html .= '<th rowspan="2" style="width:5%;">Unidad</th>';
$html .= '<th rowspan="2" style="width:5%;">Cantidad</th>';
$html .= '<th rowspan="2" style="width:5%;">Incidencia (%)</th>';
$html .= '<th rowspan="2" style="width:7%;">Precio Unitario</th>';
$html .= '<th rowspan="2" style="width:7%;" class="col-precio-total">Precio Total</th>';
$html .= '<th colspan="3" style="width:12%; background:#F1F3F5;">Anterior</th>';
$html .= '<th colspan="3" style="width:12%; background:#E9F6FD;">Actual</th>';
$html .= '<th colspan="3" style="width:13%; background:#EEF7EE;">Acumulado</th>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<th style="width:4%; background:#F1F3F5;">Cant.</th>';
$html .= '<th style="width:3%; background:#F1F3F5;">%</th>';
$html .= '<th style="width:5%; background:#F1F3F5;">Monto</th>';
$html .= '<th style="width:4%; background:#E9F6FD;">Cant.</th>';
$html .= '<th style="width:3%; background:#E9F6FD;">%</th>';
$html .= '<th style="width:5%; background:#E9F6FD;">Monto</th>';
$html .= '<th style="width:4%; background:#EEF7EE;">Cant.</th>';
$html .= '<th style="width:3%; background:#EEF7EE;">%</th>';
$html .= '<th style="width:6%; background:#EEF7EE;" class="col-acu-monto">Monto</th>';
$html .= '</tr>';

$html .= '</thead>';
$html .= '<tbody>';

$totalMontoCM = 0;
$totalCantCM = 0;

if (empty($grupos) && empty($ordenOccIds)) {
    $html .= '<tr><td colspan="16" class="text-center">Sin ítems</td></tr>';
} else {
    foreach ($grupos as $g) {
        // Padres
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

            $html .= '<tr>';
            $html .= '<td class="text-center parent-yellow">' . cmPdfEsc($pos) . '</td>';
            $html .= '<td class="text-left parent-yellow" style="font-weight:bold;">' . cmPdfEsc($descOcc) . '</td>';
            $html .= '<td class="parent-blue"></td>';
            $html .= '<td class="num parent-blue">' . cmPdfNum($cantOcc) . '</td>';
            $html .= '<td class="num parent-blue">0,00%</td>';
            $html .= '<td class="num parent-blue">' . cmPdfMoney($puOcc, $moneda) . '</td>';
            $html .= '<td class="num parent-yellow col-precio-total">' . cmPdfMoney($ptOcc, $moneda) . '</td>';
            $html .= '<td class="num parent-blue">0,00</td>';
            $html .= '<td class="num parent-blue">0,00%</td>';
            $html .= '<td class="num parent-blue">' . cmPdfMoney(0, $moneda) . '</td>';
            $html .= '<td class="num parent-blue">0,00</td>';
            $html .= '<td class="num parent-blue">0,00%</td>';
            $html .= '<td class="num parent-blue">' . cmPdfMoney(0, $moneda) . '</td>';
            $html .= '<td class="num parent-blue">0,00</td>';
            $html .= '<td class="num parent-blue">0,00%</td>';
            $html .= '<td class="num parent-blue col-acu-monto">' . cmPdfMoney(0, $moneda) . '</td>';
            $html .= '</tr>';
        }

        // Hijos
        if (!empty($g['filas'])) {
            $ownerPos = $occMap[$g['occ_ids'][0]]['posicion'] ?? '10';
            foreach ($g['filas'] as $fi => $fila) {
                $posDes = $ownerPos . '.' . ($fi + 1);
                $descDes = (string)$fila['descripcion'];
                $unidadDes = (string)($fila['unidad_medida'] ?? '');
                $cantDes = (float)$fila['cantidad'];
                $incDes = (float)$fila['incidencia_porcentaje'];
                $puDes = (float)$fila['precio_unitario'];
                $totalDes = (float)$fila['subtotal'];
                if ($totalDes == 0) $totalDes = $g['base'] * $incDes / 100;
                if ($puDes == 0 && $cantDes > 0) $puDes = $totalDes / $cantDes;

                $totalMontoCM += $totalDes;
                $totalCantCM += $cantDes;

                $html .= '<tr>';
                $html .= '<td class="text-center child-yellow">' . cmPdfEsc($posDes) . '</td>';
                $html .= '<td class="text-left child-yellow">' . cmPdfEsc($descDes) . '</td>';
                $html .= '<td class="child-yellow">' . cmPdfEsc($unidadDes) . '</td>';
                $html .= '<td class="num child-yellow">' . cmPdfNum($cantDes) . '</td>';
                $html .= '<td class="num child-yellow">' . cmPdfNum($incDes) . '%</td>';
                $html .= '<td class="num">' . cmPdfMoney($puDes, $moneda) . '</td>';
                $html .= '<td class="num col-precio-total">' . cmPdfMoney($totalDes, $moneda) . '</td>';
                $html .= '<td class="num">0,00</td>';
                $html .= '<td class="num">0,00%</td>';
                $html .= '<td class="num">' . cmPdfMoney(0, $moneda) . '</td>';
                $html .= '<td class="num">0,00</td>';
                $html .= '<td class="num">0,00%</td>';
                $html .= '<td class="num">' . cmPdfMoney(0, $moneda) . '</td>';
                $html .= '<td class="num">0,00</td>';
                $html .= '<td class="num">0,00%</td>';
                $html .= '<td class="num col-acu-monto">' . cmPdfMoney(0, $moneda) . '</td>';
                $html .= '</tr>';
            }

            // Subtotal lote
            $html .= '<tr class="subtotal-row">';
            $html .= '<td colspan="6" class="text-right">Total Lote</td>';
            $html .= '<td class="num col-precio-total">' . cmPdfMoney($g['subtotal'], $moneda) . '</td>';
            $html .= '<td colspan="9"></td>';
            $html .= '</tr>';
        }
    }

    // Huérfanos
    $groupedIds = [];
    foreach ($grupos as $gg) {
        foreach ($gg['occ_ids'] as $oid) {
            $groupedIds[$oid] = 1;
        }
    }
    foreach ($ordenOccIds as $oid) {
        if (isset($groupedIds[$oid])) continue;
        $occ = $occMap[$oid] ?? null;
        if (!$occ) continue;
        $pos = $occ['posicion'];
        $descOcc = (string)$occ['descripcion'];
        $ptOcc = (float)$occ['subtotal'];
        $cantOcc = (float)$occ['cantidad'];
        $totalMontoCM += $ptOcc;
        $totalCantCM += $cantOcc;

        $html .= '<tr>';
        $html .= '<td class="text-center parent-yellow">' . cmPdfEsc($pos) . '</td>';
        $html .= '<td class="text-left parent-yellow" style="font-weight:bold;">' . cmPdfEsc($descOcc) . '</td>';
        $html .= '<td class="parent-blue"></td>';
        $html .= '<td class="num parent-blue">' . cmPdfNum($cantOcc) . '</td>';
        $html .= '<td class="num parent-blue">0,00%</td>';
        $html .= '<td class="num parent-blue">' . cmPdfMoney(0, $moneda) . '</td>';
        $html .= '<td class="num parent-yellow col-precio-total">' . cmPdfMoney($ptOcc, $moneda) . '</td>';
        $html .= '<td class="num parent-blue">0,00</td>';
        $html .= '<td class="num parent-blue">0,00%</td>';
        $html .= '<td class="num parent-blue">' . cmPdfMoney(0, $moneda) . '</td>';
        $html .= '<td class="num parent-blue">0,00</td>';
        $html .= '<td class="num parent-blue">0,00%</td>';
        $html .= '<td class="num parent-blue">' . cmPdfMoney(0, $moneda) . '</td>';
        $html .= '<td class="num parent-blue">0,00</td>';
        $html .= '<td class="num parent-blue">0,00%</td>';
        $html .= '<td class="num parent-blue col-acu-monto">' . cmPdfMoney(0, $moneda) . '</td>';
        $html .= '</tr>';
    }
}

// Total Orden de Compra
$html .= '<tr class="total-row">';
$html .= '<td colspan="6" class="text-right">Total Orden de Compra</td>';
$html .= '<td class="num col-precio-total">' . cmPdfMoney($totalMontoCM, $moneda) . '</td>';
$html .= '<td class="num">0,00</td>';
$html .= '<td class="num"></td>';
$html .= '<td class="num">' . cmPdfMoney(0, $moneda) . '</td>';
$html .= '<td class="num">0,00</td>';
$html .= '<td class="num"></td>';
$html .= '<td class="num">' . cmPdfMoney(0, $moneda) . '</td>';
$html .= '<td class="num">0,00</td>';
$html .= '<td class="num"></td>';
$html .= '<td class="num col-acu-monto">' . cmPdfMoney(0, $moneda) . '</td>';
$html .= '</tr>';

$html .= '</tbody></table>';

// --------------------------------------------------------------
// BLOQUES FINALES (Notas, Totales, Firmas)
// --------------------------------------------------------------
$html .= '<table class="footer-block">';

$html .= '<tr><td class="label" colspan="4">Notas:</td></tr>';
$html .= '<tr><td colspan="4" class="notas-cell" style="min-height:8mm;">' . cmPdfEsc($cm['observaciones'] ?? '') . '</td></tr>';

$html .= '<tr>';
$html .= '<td class="label" style="width:25%;">Total Certificado</td>';
$html .= '<td class="num" style="width:25%;">' . cmPdfMoney(0, $moneda) . '</td>';
$html .= '<td class="label" style="width:25%;">Desacopio de anticipo</td>';
$html .= '<td class="num" style="width:25%;">' . cmPdfMoney(0, $moneda) . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td class="label" rowspan="2" style="width:25%;">CAC</td>';
$html .= '<td style="width:25%;">ENE</td>';
$html .= '<td style="width:25%;">ABR</td>';
$html .= '<td style="width:25%;">Indice</td>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<td class="num">0,00</td>';
$html .= '<td class="num">0,00</td>';
$html .= '<td class="num">0,00%</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td class="label">Fondo de reparo</td>';
$html .= '<td class="num">' . cmPdfMoney(0, $moneda) . '</td>';
$html .= '<td colspan="2"></td>';
$html .= '</tr>';

$html .= '<tr><td colspan="4" style="padding:0; border:0;">';
$html .= '<table style="width:100%; border-collapse:collapse;">';
$html .= '<tr><td style="border:1px solid #000; padding:3mm 0; text-align:center; font-weight:bold; font-size:8pt;">FIRMA NH</td>';
$html .= '<td style="border:1px solid #000; padding:3mm 0; text-align:center; font-weight:bold; font-size:8pt;">FIRMA CLIENTE</td>';
$html .= '<td style="border:1px solid #000; padding:3mm 0; text-align:center; font-weight:bold; font-size:8pt;">FIRMA OTRO</td></tr>';
$html .= '</table>';
$html .= '</td></tr>';

$html .= '</table>';

// --------------------------------------------------------------
// HOJA DE MEDICIÓN
// --------------------------------------------------------------
$html .= '<div class="page-break"></div>';

// Cabecera para medición (mismo formato pero título "MEDICION")
$html .= '<div class="pdf-header">';
$html .= '<table>';
$html .= '<tr>';
$html .= '<td style="width:20%;">' . $logoHtml . '</td>';
$html .= '<td class="header-center" style="width:50%;">';
$html .= '<span class="header-title">MEDICION</span>';
$html .= '<span class="header-sub">CM #' . $nroCM . ' | OCC #' . $nroOCC . '</span>';
$html .= '</td>';
$html .= '<td style="width:30%;">';
$html .= '<div class="header-company">';
$html .= 'NH Construcciones SRL<br>';
$html .= 'Ricardo Gutiérrez 2874 (C1417EBL) - CABA<br>';
$html .= 'Tel./Fax (54 11) 4505-8300';
$html .= '</div>';
$html .= '</td>';
$html .= '</tr>';
$html .= '</table>';
$html .= '</div>';

$html .= '<div class="pdf-footer"><span>Grupo NH | Medición</span><span class="pdf-footer-right">Generado el ' . date('d/m/Y H:i') . ' | Página </span></div>';

// Tabla de medición
$html .= '<table class="med-table">';
$html .= '<thead>';

$html .= '<tr class="sub-header">';
$html .= '<td colspan="5" style="border-right: 2px solid #000;">';
$html .= '<table style="width:100%; border-collapse:collapse; font-size:6pt;">';
$html .= '<tr><td class="label" style="width:20%;">Cliente</td><td class="value" style="width:30%;">' . cmPdfEsc($cm['cliente']) . '</td>'
       . '<td class="label" style="width:20%;">Proyecto</td><td class="value" style="width:30%;">' . cmPdfEsc($proyectosNombre) . '</td></tr>';
$html .= '<tr><td class="label">Orden de Compra</td><td class="value">' . cmPdfEsc($cm['numero_occ']) . '</td>'
       . '<td class="label">Moneda y Monto</td><td class="value">' . cmPdfMoney($cm['monto_total'], $moneda) . '</td></tr>';
$html .= '<tr><td class="label">Fecha</td><td class="value">' . cmFmtFecha($cm['fecha_emision']) . '</td>'
       . '<td class="label"></td><td class="value"></td></tr>';
$html .= '</table>';
$html .= '</td>';
$html .= '<td colspan="6">';
$html .= '<table style="width:100%; border-collapse:collapse; font-size:6pt;">';
$html .= '<tr><td class="label" style="width:20%;">Número</td><td class="value" style="width:30%;">' . $nroCM . '</td>'
       . '<td class="label" style="width:20%;">Revisión</td><td class="value" style="width:30%;">1</td></tr>';
$html .= '<tr><td class="label">Periodo desde</td><td class="value">' . cmFmtFecha($cm['fecha_inicio']) . '</td>'
       . '<td class="label">Periodo hasta</td><td class="value">' . cmFmtFecha($cm['fecha_fin']) . '</td></tr>';
$html .= '<tr><td class="label">Fecha emisión</td><td class="value">' . cmFmtFecha($cm['fecha_emision']) . '</td>'
       . '<td class="label"></td><td class="value"></td></tr>';
$html .= '</table>';
$html .= '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<th rowspan="2" style="width:4%;">Pos</th>';
$html .= '<th rowspan="2" style="width:22%;">Descripción</th>';
$html .= '<th rowspan="2" style="width:7%;">Unidad</th>';
$html .= '<th rowspan="2" style="width:7%;">Cantidad</th>';
$html .= '<th colspan="2" style="width:12%; background:#F1F3F5;">Anterior</th>';
$html .= '<th colspan="2" style="width:12%; background:#E9F6FD;">Actual</th>';
$html .= '<th colspan="2" style="width:13%; background:#EEF7EE;">Acumulado</th>';
$html .= '<th style="width:10%;">Observaciones</th>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<th style="width:6%; background:#F1F3F5;">Cant.</th>';
$html .= '<th style="width:6%; background:#F1F3F5;">%</th>';
$html .= '<th style="width:6%; background:#E9F6FD;">Cant.</th>';
$html .= '<th style="width:6%; background:#E9F6FD;">%</th>';
$html .= '<th style="width:6%; background:#EEF7EE;">Cant.</th>';
$html .= '<th style="width:7%; background:#EEF7EE;">%</th>';
$html .= '<th style="width:10%;">Observaciones</th>';
$html .= '</tr>';

$html .= '</thead>';
$html .= '<tbody>';

foreach ($grupos as $g) {
    foreach ($g['occ_ids'] as $oid) {
        $occ = $occMap[$oid] ?? null;
        if (!$occ) continue;
        $pos = $occ['posicion'];
        $descOcc = (string)$occ['descripcion'];

        $html .= '<tr>';
        $html .= '<td class="text-center parent-yellow">' . cmPdfEsc($pos) . '</td>';
        $html .= '<td class="text-left parent-yellow" style="font-weight:bold;">' . cmPdfEsc($descOcc) . '</td>';
        $html .= '<td class="parent-blue"></td>';
        $html .= '<td class="num parent-blue">' . cmPdfNum(0) . '</td>';
        $html .= '<td class="num parent-blue">0,00</td>';
        $html .= '<td class="num parent-blue">0,00%</td>';
        $html .= '<td class="num parent-blue">0,00</td>';
        $html .= '<td class="num parent-blue">0,00%</td>';
        $html .= '<td class="num parent-blue">0,00</td>';
        $html .= '<td class="num parent-blue">0,00%</td>';
        $html .= '<td></td>';
        $html .= '</tr>';
    }

    if (!empty($g['filas'])) {
        $ownerPos = $occMap[$g['occ_ids'][0]]['posicion'] ?? '10';
        $i = 1;
        foreach ($g['filas'] as $fila) {
            $posDes = $ownerPos . '.' . $i++;
            $descDes = (string)$fila['descripcion'];
            $unidadDes = (string)($fila['unidad_medida'] ?? '');
            $cantDes = (float)$fila['cantidad'];

            $html .= '<tr>';
            $html .= '<td class="text-center child-yellow">' . cmPdfEsc($posDes) . '</td>';
            $html .= '<td class="text-left child-yellow">' . cmPdfEsc($descDes) . '</td>';
            $html .= '<td class="child-yellow">' . cmPdfEsc($unidadDes) . '</td>';
            $html .= '<td class="num child-yellow">' . cmPdfNum($cantDes) . '</td>';
            $html .= '<td class="num">0,00</td>';
            $html .= '<td class="num">0,00%</td>';
            $html .= '<td class="num">0,00</td>';
            $html .= '<td class="num">0,00%</td>';
            $html .= '<td class="num">0,00</td>';
            $html .= '<td class="num">0,00%</td>';
            $html .= '<td></td>';
            $html .= '</tr>';
        }
    }
}

$html .= '</tbody></table>';

// Notas y Firmas para Medición
$html .= '<table class="footer-block">';
$html .= '<tr><td class="label" colspan="4">Notas:</td></tr>';
$html .= '<tr><td colspan="4" class="notas-cell" style="min-height:8mm;">' . cmPdfEsc($cm['observaciones'] ?? '') . '</td></tr>';
$html .= '<tr><td colspan="4" style="padding:0; border:0;">';
$html .= '<table style="width:100%; border-collapse:collapse;">';
$html .= '<tr><td style="border:1px solid #000; padding:3mm 0; text-align:center; font-weight:bold; font-size:8pt;">FIRMA NH</td>';
$html .= '<td style="border:1px solid #000; padding:3mm 0; text-align:center; font-weight:bold; font-size:8pt;">FIRMA CLIENTE</td>';
$html .= '<td style="border:1px solid #000; padding:3mm 0; text-align:center; font-weight:bold; font-size:8pt;">FIRMA OTRO</td></tr>';
$html .= '</table>';
$html .= '</td></tr>';
$html .= '</table>';

// --------------------------------------------------------------
// Generación del PDF
// --------------------------------------------------------------
$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
$canvas->page_text(760, 570, '{PAGE_NUM} de {PAGE_COUNT}', $font, 7.5, [0.4, 0.4, 0.4]);

$dompdf->stream('certificado_maestro_' . $idCm . '.pdf', ['Attachment' => false]);