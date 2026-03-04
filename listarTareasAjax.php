<?php
session_start();
include 'database.php';

$aContactos = [];
$pdo = Database::connect();

$draw   = isset($_GET['draw'])   ? intval($_GET['draw'])   : 1;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
$start  = isset($_GET['start'])  ? intval($_GET['start'])  : 0;

$nro           = isset($_GET['nro'])           ? trim($_GET['nro'])           : "";
$id_tipo_tarea = isset($_GET['id_tipo_tarea']) ? trim($_GET['id_tipo_tarea']) : "";
$completada    = isset($_GET['completada'])    ? trim($_GET['completada'])    : "";
$orden         = isset($_GET['orden'])         ? trim($_GET['orden'])         : "t.id asc";

$fields = [
    "t.`id`",
    "p.`nombre`",
    "s.nombre",
    "t.`estructura`",
    "sec.`sector`",
    "tt.`tipo`",
    "c.`nombre`",
    "date_format(t.`fecha_inicio_estimada`,'%d/%m/%y')",
    "date_format(t.`fecha_fin_estimada`,'%d/%m/%y')",
    "date_format(t.`fecha_inicio_real`,'%d/%m/%y')",
    "date_format(t.`fecha_fin_real`,'%d/%m/%y')",
    "c2.`nombre`",
    "t.observaciones",
    "s.nro_sitio",
    "s.nro_subsitio",
    "p.nro",
    "p.nombre",
    "date_format(t.`fecha_inicio_estimada`,'%Y%m%d')",
    "date_format(t.`fecha_fin_estimada`,'%Y%m%d')",
    "date_format(t.`fecha_inicio_real`,'%Y%m%d')",
    "date_format(t.`fecha_fin_real`,'%Y%m%d')"
];

$from = " FROM `tareas` t
          INNER JOIN proyectos p    ON p.id   = t.`id_proyecto`
          INNER JOIN sitios s       ON s.id   = p.id_sitio
          INNER JOIN sectores sec   ON sec.id  = t.`id_sector`
          INNER JOIN tipos_tarea tt ON tt.id   = t.`id_tipo_tarea`
          LEFT  JOIN cuentas c      ON c.id    = t.`id_coordinador`
          LEFT  JOIN cuentas c2     ON c2.id   = t.`id_recurso` ";

$where = " t.anulado = 0 AND p.anulado = 0 ";

if (!empty($id_tipo_tarea)) {
    $where .= " AND tt.id = " . intval($id_tipo_tarea);
}

if ($nro !== "") {
    $ex = explode("-", $nro);
    if (count($ex) >= 3) {
        $sitio    = intval($ex[0]);
        $subsitio = intval($ex[1]);
        $proyecto = intval($ex[2]);
        $where .= " AND s.nro_sitio = $sitio AND s.nro_subsitio = $subsitio AND p.nro = $proyecto ";
    } elseif (count($ex) == 2) {
        $sitio    = intval($ex[0]);
        $subsitio = intval($ex[1]);
        $where .= " AND s.nro_sitio = $sitio AND s.nro_subsitio = $subsitio ";
    } else {
        $val   = intval($nro);
        $where .= " AND (p.nro = $val OR s.nro_sitio = $val OR s.nro_subsitio = $val) ";
    }
}

if ($completada !== "") {
    if ($completada == "1") {
        $where .= " AND date_format(t.`fecha_fin_real`,'%d/%m/%y') != '00/00/00' ";
    } elseif ($completada == "0") {
        $where .= " AND date_format(t.`fecha_fin_real`,'%d/%m/%y') = '00/00/00' ";
    }
}

$colSearchMap = [
    1  => ["campo" => "s.nro_sitio",                                    "tipo" => "texto"],
    2  => ["campo" => "s.nro_subsitio",                                  "tipo" => "texto"],
    3  => ["campo" => "p.nro",                                           "tipo" => "texto"],
    4  => ["campo" => "p.nombre",                                        "tipo" => "texto"],
    5  => ["campo" => "t.estructura",                                    "tipo" => "texto"],
    6  => ["campo" => "sec.sector",                                      "tipo" => "texto"],
    7  => ["campo" => "tt.tipo",                                         "tipo" => "texto"],
    8  => ["campo" => "c2.nombre",                                       "tipo" => "texto"],
    9  => ["campo" => "c.nombre",                                        "tipo" => "texto"],
    10 => ["campo" => "t.observaciones",                                 "tipo" => "texto"],
    11 => ["campo" => "date_format(t.`fecha_inicio_estimada`,'%d/%m/%y')", "tipo" => "fecha"],
    12 => ["campo" => "date_format(t.`fecha_fin_estimada`,'%d/%m/%y')",    "tipo" => "fecha"],
    13 => ["campo" => "date_format(t.`fecha_inicio_real`,'%d/%m/%y')",     "tipo" => "fecha"],
    14 => ["campo" => "date_format(t.`fecha_fin_real`,'%d/%m/%y')",        "tipo" => "fecha"],
    15 => ["campo" => "IF(date_format(t.`fecha_fin_real`,'%d/%m/%y') != '00/00/00','Si','No')", "tipo" => "texto"],
];

if (isset($_GET['columns']) && is_array($_GET['columns'])) {
    foreach ($colSearchMap as $idx => $info) {
        $val = isset($_GET['columns'][$idx]['search']['value'])
               ? trim($_GET['columns'][$idx]['search']['value'])
               : '';
        if ($val !== '') {
            $valQ   = $pdo->quote("%" . $val . "%");
            $where .= " AND {$info['campo']} LIKE $valQ ";
        }
    }
}

$searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
if ($searchValue !== '') {
    $s = $pdo->quote("%" . $searchValue . "%");
    $where .= " AND (
        s.nro_sitio                                          LIKE $s OR
        s.nro_subsitio                                       LIKE $s OR
        p.nro                                                LIKE $s OR
        p.nombre                                             LIKE $s OR
        t.estructura                                         LIKE $s OR
        sec.sector                                           LIKE $s OR
        tt.tipo                                              LIKE $s OR
        c2.nombre                                            LIKE $s OR
        c.nombre                                             LIKE $s OR
        t.observaciones                                      LIKE $s OR
        date_format(t.`fecha_inicio_estimada`,'%d/%m/%y')   LIKE $s OR
        date_format(t.`fecha_fin_estimada`,'%d/%m/%y')       LIKE $s OR
        date_format(t.`fecha_inicio_real`,'%d/%m/%y')        LIKE $s OR
        date_format(t.`fecha_fin_real`,'%d/%m/%y')           LIKE $s
    )";
}

$colOrderMap = [
    1  => 's.nro_sitio',
    2  => 's.nro_subsitio',
    3  => 'p.nro',
    4  => 'p.nombre',
    5  => 't.estructura',
    6  => 'sec.sector',
    7  => 'tt.tipo',
    8  => 'c2.nombre',
    9  => 'c.nombre',
    10 => 't.observaciones',
    11 => 't.fecha_inicio_estimada',
    12 => 't.fecha_fin_estimada',
    13 => 't.fecha_inicio_real',
    14 => 't.fecha_fin_real',
];

$ordenesPermitidos = ["t.id asc", "t.id desc", "tt.tipo asc", "tt.tipo desc"];
$ordenSQL = in_array($orden, $ordenesPermitidos) ? $orden : "t.id asc";

if (
    isset($_GET['order'][0]['column']) &&
    isset($_GET['order'][0]['dir'])
) {
    $orderColIdx = intval($_GET['order'][0]['column']);
    $orderDir    = ($_GET['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';

    if ($orderColIdx >= 1 && isset($colOrderMap[$orderColIdx])) {
        $ordenSQL = $colOrderMap[$orderColIdx] . " " . $orderDir;
    }
}

$countSql        = "SELECT COUNT(t.`id`) AS Total $from WHERE t.anulado = 0 AND p.anulado = 0";
$total           = $pdo->query($countSql)->fetch()['Total'];

$queryFiltered   = "SELECT COUNT(t.`id`) AS recordsFiltered $from WHERE $where";
$recordsFiltered = $pdo->query($queryFiltered)->fetch()['recordsFiltered'];

$campos = implode(",", $fields);
$sql    = "SELECT $campos $from WHERE $where ORDER BY $ordenSQL LIMIT $length OFFSET $start";
$st     = $pdo->query($sql);

if ($st) {
    while ($row = $st->fetch(PDO::FETCH_NUM)) {

        $tieneComputo = 0;
        $idComputo    = 0;
        $q2 = $pdo->prepare("SELECT `id` FROM computos WHERE id_tarea = ? AND id_estado <> 6");
        $q2->execute([$row[0]]);
        $data2 = $q2->fetch(PDO::FETCH_ASSOC);
        if (!empty($data2)) {
            $tieneComputo = 1;
            $idComputo    = $data2['id'];
        }

        $tieneLC = 0;
        $q2 = $pdo->prepare("SELECT `id` FROM listas_corte WHERE id_tarea = ?");
        $q2->execute([$row[0]]);
        if (!empty($q2->fetch(PDO::FETCH_ASSOC))) $tieneLC = 1;

        $tienePL = 0;
        $q2 = $pdo->prepare("SELECT `id` FROM packing_lists WHERE id_tarea = ?");
        $q2->execute([$row[0]]);
        if (!empty($q2->fetch(PDO::FETCH_ASSOC))) $tienePL = 1;

        $completadaTexto = ($row[10] != '00/00/00' && $row[10] != null) ? "Si" : "No";
        $contieneComputo = ($tieneComputo == 1) ? "Si" : "No";
        $codigoComputo   = ($tieneComputo == 1) ? $idComputo : "";
        $contieneLC      = ($tieneLC == 1)      ? "Si" : "No";
        $contienePL      = ($tienePL == 1)      ? "Si" : "No";

        $aContactos[] = [
            $row[0],
            $row[13],
            $row[14],
            $row[15],
            $row[16],
            $row[3],
            $row[4],
            $row[5],
            $row[11],
            $row[6],
            $row[12],
            '<span style="display:none;">' . $row[17] . '</span>' . $row[7],
            '<span style="display:none;">' . $row[18] . '</span>' . $row[8],
            '<span style="display:none;">' . $row[19] . '</span>' . $row[9],
            '<span style="display:none;">' . $row[20] . '</span>' . $row[10],
            $completadaTexto,
            $contieneComputo,
            $codigoComputo,
            $contieneLC,
            $contienePL,
        ];
    }

    Database::disconnect();

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $total,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $aContactos,
    ]);

} else {
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => implode(' | ', $pdo->errorInfo()),
    ]);
}