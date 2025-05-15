<?php
require("config.php");
require 'database.php';

$id_fv = $_POST['id_fv'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT d.`id`, cc.`descripcion`, d.`precio`, d.`cantidad`, d.`subtotal` FROM `facturas_venta_detalle` d inner join conceptos_contables cc on cc.id = d.id_concepto_contable WHERE d.id_factura_venta = ".$id_fv;
$aDetalles=[];
foreach ($pdo->query($sql) as $row) {
	
  $aDetalles[]=[
    0 => $row[0],
    1 => $row[1],
    2 => number_format($row[2],2),
    3 => $row[3],
	4 => number_format($row[4],2)
  ];
}

Database::disconnect();
echo json_encode($aDetalles);
?>