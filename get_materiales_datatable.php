<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$draw    = isset($_GET['draw'])    ? (int)$_GET['draw']    : 1;
$start   = isset($_GET['start'])   ? (int)$_GET['start']   : 0;
$length  = isset($_GET['length'])  ? (int)$_GET['length']  : 25;
$search  = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';

$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir  = isset($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';

$columns = ['m.id', 'm.codigo', 'm.concepto', 'c.categoria', 'stock', 'reservado', 'm.activo'];
$orderBy = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 'm.id';

$stockSubquery = "(SELECT COALESCE(SUM(saldo), 0) FROM ingresos_detalle WHERE id_material = m.id)";
$reservadoSubquery = "(SELECT COALESCE(SUM(cantidad_reservada), 0) FROM egresos_detalle WHERE id_material = m.id)";

$sqlSelect = "SELECT m.id, m.codigo, m.concepto, c.categoria,
                     COALESCE($stockSubquery, 0) AS stock,
                     COALESCE($reservadoSubquery, 0) AS reservado,
                     CASE WHEN m.activo = 1 THEN 'Si' ELSE 'No' END AS activo
              FROM materiales m
              INNER JOIN categorias c ON c.id = m.id_categoria
              WHERE m.anulado = 0";

$sqlCount  = "SELECT COUNT(*) FROM materiales m WHERE m.anulado = 0";

$whereClauses = [];
$havingClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(m.codigo LIKE :s OR m.concepto LIKE :s2 OR c.categoria LIKE :s3)";
    $params[':s']  = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

$stockOp  = $_GET['stock_op'] ?? '';
$stockVal = $_GET['stock_val'] ?? '';
if ($stockOp !== '' && $stockVal !== '') {
    $allowedOps = ['<', '<=', '>', '>=', '=', '!='];
    $stockOp = in_array(str_replace('==', '=', $stockOp), $allowedOps) ? str_replace('==', '=', $stockOp) : '=';
    $havingClauses[] = "stock $stockOp :stock_val";
    $params[':stock_val'] = floatval($stockVal);
}

$reservadoOp  = $_GET['reservado_op'] ?? '';
$reservadoVal = $_GET['reservado_val'] ?? '';
if ($reservadoOp !== '' && $reservadoVal !== '') {
    $allowedOps = ['<', '<=', '>', '>=', '=', '!='];
    $reservadoOp = in_array(str_replace('==', '=', $reservadoOp), $allowedOps) ? str_replace('==', '=', $reservadoOp) : '=';
    $havingClauses[] = "reservado $reservadoOp :reservado_val";
    $params[':reservado_val'] = floatval($reservadoVal);
}

$where = !empty($whereClauses) ? ' AND ' . implode(' AND ', $whereClauses) : '';
$having = !empty($havingClauses) ? ' HAVING ' . implode(' AND ', $havingClauses) : '';

$sqlCountFiltered = "SELECT COUNT(*) FROM materiales m INNER JOIN categorias c ON c.id = m.id_categoria WHERE m.anulado = 0 $where";
$qCount = $pdo->prepare($sqlCountFiltered);
$qCount->execute($params);
$recordsFiltered = (int)$qCount->fetchColumn();

$qTotal = $pdo->query($sqlCount);
$recordsTotal = (int)$qTotal->fetchColumn();

$sqlData = "$sqlSelect $where $having ORDER BY $orderBy $orderDir LIMIT " . (int)$start . ", " . (int)$length;
$qData = $pdo->prepare($sqlData);
$qData->execute($params);
$rows = $qData->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

header('Content-Type: application/json');
echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $rows
]);
