<?php
require("config.php");
require 'database.php';

$id_egreso = $_POST['id_egreso'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT m.`codigo`, m.`concepto`, cat.`categoria`, um.unidad_medida, id.`cantidad`,id.id FROM `egresos_detalle` id inner join unidades_medida um on um.id = id.`id_unidad_medida` inner join egresos i on i.id = id.`id_egreso` inner join tipos_egreso ti on ti.id = i.`id_tipo_egreso` inner join cuentas c on c.id = i.`id_cuenta_retira` inner join materiales m on m.id = id.id_material inner join categorias cat on cat.id = m.`id_categoria` WHERE id.`id_egreso` = ".$id_egreso;
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
	
	$aConceptos[]=[
    0 => $row[5],
    1 => $row[0],
    2 => $row[1],
    3 => $row[2],
    4 => $row[3],
    5 => $row[4]
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>
