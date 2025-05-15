<?php
require("config.php");
require 'database.php';

$id_con = $_POST['id_con'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT pos.`id`, m.`concepto`, pos.`posicion`, pos.`cantidad`, pos.`finalizado` FROM `lista_corte_posiciones` pos inner join materiales m on m.id = pos.`id_material` WHERE pos.`id_lista_corte_conjunto` = ".$id_con;
$aPosiciones=[];
foreach ($pdo->query($sql) as $row) {
	$estado = "";
	if ($row[4]==0) {
		$estado = "En proceso";
	} else {
		$estado = "Finalizado";
	}
	
  $aPosiciones[]=[
    0 => $row[0],
    1 => $row[1],
    2 => $row[2],
    3 => $row[3],
	4 => $estado
  ];
}

Database::disconnect();
echo json_encode($aPosiciones);
?>