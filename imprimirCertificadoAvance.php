<?php
require 'config.php';
require 'database.php';
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$idCA = filter_input(INPUT_GET, 'id_certificado_avance', FILTER_VALIDATE_INT);
$idCM = filter_input(INPUT_GET, 'id_certificado_maestro', FILTER_VALIDATE_INT);
if (!$idCA || !$idCM) {
    http_response_code(400);
    die('Certificado de Avance o Maestro inválido.');
}
if (function_exists('esOperacionesSinEconomico') && esOperacionesSinEconomico()) {
    http_response_code(403);
    die('No tiene permisos para imprimir el Certificado de Avance.');
}

// --------------------------------------------------------------
// Funciones auxiliares
// --------------------------------------------------------------
function caPdfEsc($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function caPdfMoney($value, string $currency): string {
    return caPdfEsc($currency) . ' ' . number_format((float) $value, 2, ',', '.');
}
function caPdfNum($value): string {
    return number_format((float) $value, 2, ',', '.');
}
function caFmtFecha($v) {
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
// Conexión a la BD (misma lógica que el Excel)
// --------------------------------------------------------------
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Datos del CA
$sqlCA = "SELECT cac.id, cac.id_certificado_maestro, cac.nro_certificado, cac.nro_revision,
                 cac.fecha_emision, cac.fecha_inicio, cac.fecha_fin,
                 cac.cotizacion_dolar, cac.monto_total,
                 cac.monto_acumulado_avances, cac.monto_acumulado_anticipos,
                 cac.monto_acumulado_desacopios, cac.monto_acumulado_descuentos,
                 cac.monto_acumulado_ajustes, cac.aprobado_cliente, cac.observaciones,
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
if (!$ca) {
    Database::disconnect();
    http_response_code(404);
    die('Certificado de Avance no encontrado.');
}

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

// 4. Obtener los CMD con sus avances
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

// 6. Construir grupos (misma lógica que el Excel)
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

// --------------------------------------------------------------
// CONSTANTES
// --------------------------------------------------------------
$moneda = (string) $ca['moneda'];
$nroCM = (int) $ca['id_certificado_maestro'];
$nroCA = (int) $ca['nro_certificado'];
$nroOCC = (string) $ca['numero_occ'];

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
// CABECERA FIJA (logo + título + datos empresa)
// --------------------------------------------------------------
$logoHtml = $logoData !== '' ? '<img class="pdf-header-logo" src="' . $logoData . '">' : '';
$html .= '<div class="pdf-header">';
$html .= '<table>';
$html .= '<tr>';
$html .= '<td style="width:20%;">' . $logoHtml . '</td>';
$html .= '<td class="header-center" style="width:50%;">';
$html .= '<span class="header-title">CERTIFICADO DE AVANCE</span>';
$html .= '<span class="header-sub">CM #' . $nroCM . ' | CA #' . $nroCA . ' | OCC #' . $nroOCC . '</span>';
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

// Pie de página
$html .= '<div class="pdf-footer"><span>Grupo NH | Certificado de Avance</span><span class="pdf-footer-right">Generado el ' . date('d/m/Y H:i') . ' | Página </span></div>';

// --------------------------------------------------------------
// HOJA 1: CERTIFICADO
// --------------------------------------------------------------
$html .= '<table class="ca-table">';
$html .= '<thead>';

// Fila de datos del proyecto y OCC (izq) / Datos del CA (der)
$html .= '<tr class="sub-header">';
$html .= '<td colspan="8" style="border-right: 2px solid #000;">';
$html .= '<table style="width:100%; border-collapse:collapse; font-size:6pt;">';
$html .= '<tr><td class="label" style="width:20%;">Cliente</td><td class="value" style="width:30%;">' . caPdfEsc($ca['cliente']) . '</td>'
       . '<td class="label" style="width:20%;">Proyecto</td><td class="value" style="width:30%;">' . caPdfEsc($proyectosNombre) . '</td></tr>';
$html .= '<tr><td class="label">Orden de Compra</td><td class="value">' . caPdfEsc($ca['numero_occ']) . '</td>'
       . '<td class="label">Moneda y Monto OCC</td><td class="value">' . caPdfMoney($ca['monto_total_cm'], $moneda) . '</td></tr>';
$html .= '<tr><td class="label">Fecha CM</td><td class="value">' . caFmtFecha($ca['fecha_emision']) . '</td>'
       . '<td class="label"></td><td class="value"></td></tr>';
$html .= '</table>';
$html .= '</td>';
$html .= '<td colspan="8">';
$html .= '<table style="width:100%; border-collapse:collapse; font-size:6pt;">';
$html .= '<tr><td class="label" style="width:20%;">Número CA</td><td class="value" style="width:30%;">' . $nroCA . '</td>'
       . '<td class="label" style="width:20%;">Revisión</td><td class="value" style="width:30%;">' . (int)$ca['nro_revision'] . '</td></tr>';
$html .= '<tr><td class="label">Periodo desde</td><td class="value">' . caFmtFecha($ca['fecha_inicio']) . '</td>'
       . '<td class="label">Periodo hasta</td><td class="value">' . caFmtFecha($ca['fecha_fin']) . '</td></tr>';
$html .= '<tr><td class="label">Fecha emisión</td><td class="value">' . caFmtFecha($ca['fecha_emision']) . '</td>'
       . '<td class="label"></td><td class="value"></td></tr>';
$html .= '</table>';
$html .= '</td>';
$html .= '</tr>';

// Encabezados de columna
$html .= '<tr>';
$html .= '<th rowspan="2" style="width:3%;">Pos</th>';
$html .= '<th rowspan="2" style="width:27%;">Descripción</th>';
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

// Variables de totales globales
$totalMontoCM = 0;
$totalAntMonto = 0;
$totalActMonto = 0;
$totalAcuMonto = 0;
$totalCantCM = 0;
$totalAntCant = 0;
$totalActCant = 0;
$totalAcuCant = 0;

if (empty($grupos) && empty($ordenOccIds)) {
    $html .= '<tr><td colspan="16" class="text-center">Sin ítems</td></tr>';
} else {
    foreach ($grupos as $g) {
        // --- Padres (occ_ids) ---
        foreach ($g['occ_ids'] as $oid) {
            $occ = $occMap[$oid] ?? null;
            if (!$occ) continue;

            $esAgrupado = ($g['modo_generacion'] === 'agrupar');

            if ($esAgrupado) {
                $cant = 0; $pu = 0; $pt = 0;
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
                $pos = $occ['posicion'];
                $desc = (string)$occ['descripcion'];
                $cant = (float)$occ['cantidad'];
                $pu = (float)$occ['precio_unitario'];
                $pt = (float)$occ['subtotal'];
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

            // Fila padre
            $html .= '<tr>';
            $html .= '<td class="text-center parent-yellow">' . caPdfEsc($pos) . '</td>';
            $html .= '<td class="text-left parent-yellow" style="font-weight:bold;">' . caPdfEsc($desc) . '</td>';
            $html .= '<td class="parent-blue"></td>';
            $html .= '<td class="num parent-blue">' . caPdfNum($cant) . '</td>';
            $html .= '<td class="num parent-blue">0,00%</td>';
            $html .= '<td class="num parent-blue">' . caPdfMoney($pu, $moneda) . '</td>';
            $html .= '<td class="num parent-yellow col-precio-total">' . caPdfMoney($pt, $moneda) . '</td>';
            $html .= '<td class="num parent-blue">' . caPdfNum($antCant) . '</td>';
            $html .= '<td class="num parent-blue">' . caPdfNum($cant > 0 ? ($antCant / $cant * 100) : 0) . '%</td>';
            $html .= '<td class="num parent-blue">' . caPdfMoney($antMonto, $moneda) . '</td>';
            $html .= '<td class="num parent-blue">' . caPdfNum($actCant) . '</td>';
            $html .= '<td class="num parent-blue">' . caPdfNum($cant > 0 ? ($actCant / $cant * 100) : 0) . '%</td>';
            $html .= '<td class="num parent-blue">' . caPdfMoney($actMonto, $moneda) . '</td>';
            $html .= '<td class="num parent-blue">' . caPdfNum($acuCant) . '</td>';
            $html .= '<td class="num parent-blue">' . caPdfNum($cant > 0 ? ($acuCant / $cant * 100) : 0) . '%</td>';
            $html .= '<td class="num parent-blue col-acu-monto">' . caPdfMoney($acuMonto, $moneda) . '</td>';
            $html .= '</tr>';
        }

        // --- Desglose (hijos) ---
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

                // Acumular totales globales (solo hijos)
                $totalMontoCM += $pt;
                $totalAntMonto += $antMonto;
                $totalActMonto += $actMonto;
                $totalAcuMonto += $acuMonto;
                $totalCantCM += $cant;
                $totalAntCant += $antCant;
                $totalActCant += $actCant;
                $totalAcuCant += $acuCant;

                $html .= '<tr>';
                $html .= '<td class="text-center child-yellow">' . caPdfEsc($posDes) . '</td>';
                $html .= '<td class="text-left child-yellow">' . caPdfEsc($desc . ($unidad !== '' ? ' (' . $unidad . ')' : '')) . '</td>';
                $html .= '<td class="child-yellow">' . caPdfEsc($unidad) . '</td>';
                $html .= '<td class="num child-yellow">' . caPdfNum($cant) . '</td>';
                $html .= '<td class="num child-yellow">' . caPdfNum($inc) . '%</td>';
                $html .= '<td class="num">' . caPdfMoney($pu, $moneda) . '</td>';
                $html .= '<td class="num col-precio-total">' . caPdfMoney($pt, $moneda) . '</td>';
                $html .= '<td class="num">' . caPdfNum($antCant) . '</td>';
                $html .= '<td class="num">' . caPdfNum($cant > 0 ? ($antCant / $cant * 100) : 0) . '%</td>';
                $html .= '<td class="num">' . caPdfMoney($antMonto, $moneda) . '</td>';
                $html .= '<td class="num">' . caPdfNum($actCant) . '</td>';
                $html .= '<td class="num">' . caPdfNum($cant > 0 ? ($actCant / $cant * 100) : 0) . '%</td>';
                $html .= '<td class="num">' . caPdfMoney($actMonto, $moneda) . '</td>';
                $html .= '<td class="num">' . caPdfNum($acuCant) . '</td>';
                $html .= '<td class="num">' . caPdfNum($cant > 0 ? ($acuCant / $cant * 100) : 0) . '%</td>';
                $html .= '<td class="num col-acu-monto">' . caPdfMoney($acuMonto, $moneda) . '</td>';
                $html .= '</tr>';
            }

            // Total del lote
            $html .= '<tr class="subtotal-row">';
            $html .= '<td colspan="6" class="text-right">Total Lote</td>';
            $html .= '<td class="num col-precio-total">' . caPdfMoney($g['subtotal_cm'], $moneda) . '</td>';
            $html .= '<td colspan="9"></td>';
            $html .= '</tr>';
        }
    }
}

// Total Orden de Compra (usando totales globales de los hijos)
$html .= '<tr class="total-row">';
$html .= '<td colspan="6" class="text-right">Total Orden de Compra</td>';
$html .= '<td class="num col-precio-total">' . caPdfMoney($totalMontoCM, $moneda) . '</td>';
$html .= '<td class="num">' . caPdfNum($totalAntCant) . '</td>';
$html .= '<td class="num"></td>';
$html .= '<td class="num">' . caPdfMoney($totalAntMonto, $moneda) . '</td>';
$html .= '<td class="num">' . caPdfNum($totalActCant) . '</td>';
$html .= '<td class="num"></td>';
$html .= '<td class="num">' . caPdfMoney($totalActMonto, $moneda) . '</td>';
$html .= '<td class="num">' . caPdfNum($totalAcuCant) . '</td>';
$html .= '<td class="num"></td>';
$html .= '<td class="num col-acu-monto">' . caPdfMoney($totalAcuMonto, $moneda) . '</td>';
$html .= '</tr>';

$html .= '</tbody></table>';

// --------------------------------------------------------------
// BLOQUES FINALES (Notas, Totales, Firmas)
// --------------------------------------------------------------
$html .= '<table class="footer-block">';

// Notas
$html .= '<tr><td class="label" colspan="4">Notas:</td></tr>';
$html .= '<tr><td colspan="4" class="notas-cell" style="min-height:8mm;">' . caPdfEsc($ca['observaciones'] ?? '') . '</td></tr>';

// Total Certificado y Desacopio
$html .= '<tr>';
$html .= '<td class="label" style="width:25%;">Total Certificado</td>';
$html .= '<td class="num" style="width:25%;">' . caPdfMoney($ca['monto_total'], $moneda) . '</td>';
$html .= '<td class="label" style="width:25%;">Desacopio de anticipo</td>';
$html .= '<td class="num" style="width:25%;">' . caPdfMoney(-1 * (float)$ca['monto_acumulado_desacopios'], $moneda) . '</td>';
$html .= '</tr>';

// CAC (dos filas)
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

// Fondo de reparo
$html .= '<tr>';
$html .= '<td class="label">Fondo de reparo</td>';
$html .= '<td class="num">' . caPdfMoney(0, $moneda) . '</td>';
$html .= '<td colspan="2"></td>';
$html .= '</tr>';

// Firmas
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

// Cabecera para medición
$html .= '<div class="pdf-header">';
$html .= '<table>';
$html .= '<tr>';
$html .= '<td style="width:20%;">' . $logoHtml . '</td>';
$html .= '<td class="header-center" style="width:50%;">';
$html .= '<span class="header-title">MEDICION</span>';
$html .= '<span class="header-sub">CM #' . $nroCM . ' | CA #' . $nroCA . ' | OCC #' . $nroOCC . '</span>';
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

// Subcabecera
$html .= '<tr class="sub-header">';
$html .= '<td colspan="5" style="border-right: 2px solid #000;">';
$html .= '<table style="width:100%; border-collapse:collapse; font-size:6pt;">';
$html .= '<tr><td class="label" style="width:20%;">Cliente</td><td class="value" style="width:30%;">' . caPdfEsc($ca['cliente']) . '</td>'
       . '<td class="label" style="width:20%;">Proyecto</td><td class="value" style="width:30%;">' . caPdfEsc($proyectosNombre) . '</td></tr>';
$html .= '<tr><td class="label">Orden de Compra</td><td class="value">' . caPdfEsc($ca['numero_occ']) . '</td>'
       . '<td class="label">Moneda y Monto</td><td class="value">' . caPdfMoney($ca['monto_total_cm'], $moneda) . '</td></tr>';
$html .= '<tr><td class="label">Fecha CM</td><td class="value">' . caFmtFecha($ca['fecha_emision']) . '</td>'
       . '<td class="label"></td><td class="value"></td></tr>';
$html .= '</table>';
$html .= '</td>';
$html .= '<td colspan="6">';
$html .= '<table style="width:100%; border-collapse:collapse; font-size:6pt;">';
$html .= '<tr><td class="label" style="width:20%;">Número CA</td><td class="value" style="width:30%;">' . $nroCA . '</td>'
       . '<td class="label" style="width:20%;">Revisión</td><td class="value" style="width:30%;">' . (int)$ca['nro_revision'] . '</td></tr>';
$html .= '<tr><td class="label">Periodo desde</td><td class="value">' . caFmtFecha($ca['fecha_inicio']) . '</td>'
       . '<td class="label">Periodo hasta</td><td class="value">' . caFmtFecha($ca['fecha_fin']) . '</td></tr>';
$html .= '<tr><td class="label">Fecha emisión CA</td><td class="value">' . caFmtFecha($ca['fecha_emision']) . '</td>'
       . '<td class="label"></td><td class="value"></td></tr>';
$html .= '</table>';
$html .= '</td>';
$html .= '</tr>';

// Encabezados de columnas de medición
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

// Datos de medición
foreach ($grupos as $g) {
    // Padres
    foreach ($g['occ_ids'] as $oid) {
        $occ = $occMap[$oid] ?? null;
        if (!$occ) continue;

        $esAgrupado = ($g['modo_generacion'] === 'agrupar');

        if ($esAgrupado) {
            $cant = 0; $antCant = 0; $actCant = 0; $acuCant = 0;
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

        $html .= '<tr>';
        $html .= '<td class="text-center parent-yellow">' . caPdfEsc($pos) . '</td>';
        $html .= '<td class="text-left parent-yellow" style="font-weight:bold;">' . caPdfEsc($desc) . '</td>';
        $html .= '<td class="parent-blue"></td>';
        $html .= '<td class="num parent-blue">' . caPdfNum($cant) . '</td>';
        $html .= '<td class="num parent-blue">' . caPdfNum($antCant) . '</td>';
        $html .= '<td class="num parent-blue">' . caPdfNum($cant > 0 ? ($antCant / $cant * 100) : 0) . '%</td>';
        $html .= '<td class="num parent-blue">' . caPdfNum($actCant) . '</td>';
        $html .= '<td class="num parent-blue">' . caPdfNum($cant > 0 ? ($actCant / $cant * 100) : 0) . '%</td>';
        $html .= '<td class="num parent-blue">' . caPdfNum($acuCant) . '</td>';
        $html .= '<td class="num parent-blue">' . caPdfNum($cant > 0 ? ($acuCant / $cant * 100) : 0) . '%</td>';
        $html .= '<td></td>';
        $html .= '</tr>';
    }

    // Hijos
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

            $html .= '<tr>';
            $html .= '<td class="text-center child-yellow">' . caPdfEsc($posDes) . '</td>';
            $html .= '<td class="text-left child-yellow">' . caPdfEsc($desc . ($unidad !== '' ? ' (' . $unidad . ')' : '')) . '</td>';
            $html .= '<td class="child-yellow">' . caPdfEsc($unidad) . '</td>';
            $html .= '<td class="num child-yellow">' . caPdfNum($cant) . '</td>';
            $html .= '<td class="num">' . caPdfNum($antCant) . '</td>';
            $html .= '<td class="num">' . caPdfNum($cant > 0 ? ($antCant / $cant * 100) : 0) . '%</td>';
            $html .= '<td class="num">' . caPdfNum($actCant) . '</td>';
            $html .= '<td class="num">' . caPdfNum($cant > 0 ? ($actCant / $cant * 100) : 0) . '%</td>';
            $html .= '<td class="num">' . caPdfNum($acuCant) . '</td>';
            $html .= '<td class="num">' . caPdfNum($cant > 0 ? ($acuCant / $cant * 100) : 0) . '%</td>';
            $html .= '<td></td>';
            $html .= '</tr>';
        }
    }
}

$html .= '</tbody></table>';

// Notas y Firmas para medición
$html .= '<table class="footer-block">';
$html .= '<tr><td class="label" colspan="4">Notas:</td></tr>';
$html .= '<tr><td colspan="4" class="notas-cell" style="min-height:8mm;">' . caPdfEsc($ca['observaciones'] ?? '') . '</td></tr>';
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
// La medición queda desactivada temporalmente; el bloque se conserva para reactivarlo.
$incluirMedicion = false;
if (!$incluirMedicion) {
    $html = substr($html, 0, strpos($html, '<div class="page-break"></div>'));
}
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

$dompdf->stream('certificado_avance_CA' . $idCA . '.pdf', ['Attachment' => false]);