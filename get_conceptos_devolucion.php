<?php
require("config.php");
require 'database.php';

$id_proyecto = $_POST['id_proyecto'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = " SELECT distinct m.`id`, m.`codigo`, m.`concepto`, c.categoria FROM computos_detalle cod inner join `materiales` m on m.id = cod.id_material inner join categorias c on c.id = m.`id_categoria` inner join computos co on co.id = cod.id_computo inner join tareas t on t.id = co.id_tarea WHERE m.`activo` = 1 and m.`anulado` = 0 and t.id_proyecto = ".$id_proyecto;

$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
	
	$stock = 0;
	$sql = "SELECT sum(`cantidad`)-sum(`cantidad_egresada`) as stock FROM `ingresos_detalle` WHERE `id_material` = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$row[0]]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	if (!empty($data['stock'])) {
		$stock = $data['stock'];
	}
	
	$aConceptos[]=[
    0 => $row[1],
    1 => $row[2],
	2 => $row[3],
	3 => $stock,
	4 => "<input name='cantidad_".$row[0]."' type='number' step='0.01' min='0.01' class='form-control'>"
	
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>
