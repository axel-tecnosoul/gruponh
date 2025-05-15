<?php
require("config.php");
require 'database.php';

$id_bul = $_POST['id_bul'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT d.`id`, t.tipo, d.`id_origen_bulto`, d.`id_detalle_bulto`, d.`cantidad` FROM `bultos_detalle` d inner join tipos_bulto t on t.id = d.`id_tipo_bulto` WHERE d.`id_bulto` = ".$id_bul;
$aDetalles=[];
foreach ($pdo->query($sql) as $row) {
	
  $aDetalles[]=[
    0 => $row[0],
    1 => $row[1],
    2 => $row[2],
    3 => $row[3],
	4 => $row[4]
  ];
}

Database::disconnect();
echo json_encode($aDetalles);
?>