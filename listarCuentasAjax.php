<?php
session_start();
include 'database.php';

$aContactos=[];

$pdo = Database::connect();

$orderBy = " ORDER BY ";
foreach ($_GET['order'] as $order) {
  $orderBy .= $order['column'] + 1 . " {$order['dir']}, ";
}

$orderBy = substr($orderBy, 0, -2);

$columns = $_GET['columns'];

$fields = [ 
	"c.`id`", 
	"tc.`tipo_cuenta`", 
	"c.`nombre`", 
	"c.`razon_social`", 
	"c.`email`", 
	"c.`telefono`", 
	"c.`es_recurso`", 
	"c.`activo`"
];

$where = ' 1 ';
if (!empty($_GET['id_tipo_cuenta'])) $where .= ' and c.id_tipo_cuenta = '.$_GET['id_tipo_cuenta'];
if (!empty($_GET['es_recurso'])) $where .= ' and c.es_recurso = '.$_GET['es_recurso'];
if (!empty($_GET['es_recurso']) && ($_GET['es_recurso'])==1) {
	$where .= " AND c.es_recurso = 1 ";
} else if (!empty($_GET['es_recurso']) && ($_GET['es_recurso'])==2) {
	$where .= " AND c.es_recurso = 0 ";
}

if (!empty($_GET['nombre_corto'])) $where .= " and c.nombre like '%".$_GET['nombre_corto']."%' ";
if (!empty($_GET['razon_social'])) $where .= " and c.razon_social like '%".$_GET['razon_social']."%' ";
$adicionales = false;


$length = $_GET['length'];
$start = $_GET['start'];

$from=" FROM `cuentas` c inner join tipos_cuenta tc on tc.id = c.`id_tipo_cuenta` ";

$countSql = "SELECT count($fields[0]) as Total $from";
$countSt = $pdo->query($countSql);
$total = $countSt->fetch()['Total'];


$queryFiltered="SELECT COUNT($fields[0]) AS recordsFiltered $from ".($where ? "WHERE $where " : '');
$resFilterLength = $pdo->query($queryFiltered);
$recordsFiltered = $resFilterLength->fetch()['recordsFiltered'];

$campos=implode(",", $fields);

$sql = "SELECT $campos $from ".($where ? "WHERE $where " : '')."$orderBy LIMIT $length OFFSET $start";

$st = $pdo->query($sql);

if ($st) {
  foreach ($pdo->query($sql) as $row) {
	
	$recurso = "";
	if ($row[6] == 1) {
		$recurso = "Si";
	} else {
		$recurso = "No";
	}
	$activo = "";
	if ($row[7] == 1) {
		$activo = "Si";
	} else {
		$activo = "No";
	}
		
	$aContactos[]=[
      $row[0],
      $row[1],
      $row[2],
      $row[3],
      $row[4],
      $row[5],
      $recurso,
      $activo
    ];
  }

  Database::disconnect();

  echo json_encode([
    'data' => $aContactos,
    'recordsTotal' => $total,
    'recordsFiltered' => $recordsFiltered,
  ]);
} else {
  var_dump($pdo->errorInfo());
  die;
}