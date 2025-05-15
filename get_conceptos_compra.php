<?php
require("config.php");
require 'database.php';

$id_compra = $_POST['id_compra'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, u.`unidad_medida`,d.id_material,d.precio,d.entregado,d.precio_kg,m.peso_metro,m.largo FROM `compras_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_compra = ".$id_compra;
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
	
	$precio = number_format($row[5],2);
	$preciokg = number_format($row[7],2);
	$subtotal = number_format($row[5]*$row[2],2);
	
	$peso = $row[8]*($row[9]/1000);
	
	$remitos = "";
	$sql2 = " SELECT i.nro_remito FROM `ingresos_detalle` id inner join ingresos i on i.id = id.id_ingreso WHERE id.id_material = ".$row[4]." and id.id_compra = ".$id_compra;
	foreach ($pdo->query($sql2) as $row2) {
		$remitos = $row2[0]." | ";	
	}
	
	$facturas = "";
	$sql2 = " SELECT f.numero FROM `facturas_compra_detalle_x_compras_detalle` fd inner join facturas_compra_detalle d on d.id = fd.id_factura_compra_detalle inner join facturas_compra f on f.id = d.id_factura_compra inner join compras_detalle cd on cd.id = fd.id_compra_detalle WHERE cd.id_material = ".$row[4]." and f.id_orden_compra = ".$id_compra;
	foreach ($pdo->query($sql2) as $row2) {
		$facturas = $row2[0]." | ";	
	}
	
	$aConceptos[]=[
    0 => $row[1],
    1 => $row[2],
	2 => $row[3],
	3 => $peso,
	4 => $precio,
	5 => $subtotal,
	6 => $row[6],
	7 => $remitos,
	8 => $facturas
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>
