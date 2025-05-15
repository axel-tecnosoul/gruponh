<?php
require("config.php");
require 'database.php';

$id_des = $_POST['id_des'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT b.id, b.numero, b.nombre, b.color, e.estado, bd.id, t.tipo, bd.id_origen_bulto, bd.id_detalle_bulto, bd.cantidad FROM bultos b inner join estados_bulto e on e.id = b.id_estado_bulto INNER JOIN bultos_detalle bd ON bd.id_bulto=b.id inner join tipos_bulto t on t.id = bd.id_tipo_bulto WHERE b.id_despacho = ".$id_des;
$aBultos=[];
foreach ($pdo->query($sql) as $row) {
	
  $aBultos[]=[
    0 => $row[0],
    1 => $row[1],
    2 => $row[2],
    3 => $row[3],
    4 => $row[4],
    5 => $row[5],
    6 => $row[6],
    7 => $row[7],
    8 => $row[8],
    9 => $row[9],
  ];
}

Database::disconnect();
echo json_encode($aBultos);
?>