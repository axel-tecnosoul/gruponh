<?php
require("config.php");
require 'database.php';

$id_pedido = $_POST['id_pedido'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, date_format(d.`fecha_necesidad`,'%d/%m/%y'), u.`unidad_medida`,d.id_material,d.reservado,d.comprado, date_format(d.`fecha_necesidad`,'%y%m%d') FROM `pedidos_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_pedido = ".$id_pedido;
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
	$sql2 = "SELECT d.`precio`,date_format(c.`fecha_emision`,'%d/%m/%y') fecha_emision FROM `compras_detalle` d inner join compras c on c.id = d.id_compra WHERE d.id_material = ".$row[5]." order by c.id desc limit 0,1 ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute();
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	if (!empty($data2['fecha_emision'])) {
		$fechaEmision = $data2['fecha_emision'];
	} else {
		$fechaEmision = "";
	}
	if (!empty($data2['precio'])) {
		$precio = "$". number_format($data2['precio'],2);
	} else {
		$precio = "";
	}
	$requerido = $row[2] .' '.$row[4];	
	/*$sql3 = "SELECT `disponible` FROM `stock` WHERE `id_material` = ? ";
	$q3 = $pdo->prepare($sql3);
	$q3->execute([$row[5]]);
	$data3 = $q3->fetch(PDO::FETCH_ASSOC);
	if (empty($data3)) {
		$disponible = "";
	} else {
		$disponible = $data3['disponible'];
	}*/

  $sql3 = "SELECT SUM(saldo) AS disponible FROM ingresos_detalle WHERE id_material = ? ";
	$q3 = $pdo->prepare($sql3);
	$q3->execute([$row[5]]);
	$data3 = $q3->fetch(PDO::FETCH_ASSOC);

  $disponible = $data3['disponible'];
	
	$aConceptos[]=[
    0 => $row[1],
    1 => $requerido,
	2 => $disponible,
	3 => $row[6],
	4 => $row[7],
	5 => "<span style='display: none;'>". $row[8] . "</span>".$row[3],
	6 => $fechaEmision,
	7 => $precio
	
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>
