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
	"s.id", 
	"s.nro_sitio", 
	"s.nro_subsitio", 
	"s.nombre", 
	"cue.nombre", 
	"p.nombre", 
	"pro.provincia", 
	"loc.localidad", 
	"s.direccion"
];

$where = ' 1 ';
if (!empty($_GET['nro'])) $where .= ' and s.nro_sitio = '.$_GET['nro'].' ';
if (!empty($_GET['nombre'])) $where .= " and s.nombre like '%".$_GET['nombre']."%' ";
if (!empty($_GET['cliente'])) $where .= " and cue.nombre like '%".$_GET['cliente']."%' ";
$adicionales = false;

$length = $_GET['length'];
$start = $_GET['start'];

$from=" FROM sitios s left join sitios s2 on s2.id = s.id_sitio_superior inner join paises p on p.id = s.id_pais inner join provincias pro on pro.id = s.id_provincia inner join localidades loc on loc.id = s.id_localidad inner join cuentas cue on cue.id = s.id_cuenta_duenio ";

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
						   
    $aContactos[]=[
      $row[0],
      $row[1],
      $row[2],
      $row[3],
      $row[4],
	  $row[5],
	  $row[6],
	  $row[7],
	  $row[8]
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