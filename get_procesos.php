<?php
require("config.php");
require 'database.php';

$id_pos = $_POST['id_pos'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT lp.`id`, tp.tipo, ep.`estado` FROM `lista_corte_procesos` lp inner join tipos_procesos tp on tp.id = lp.`id_tipo_proceso` inner join estados_lista_corte_procesos ep on ep.id = lp.id_estado_lista_corte_proceso WHERE lp.`id_lista_corte_posicion` = ".$id_pos;
$aProcesos=[];
foreach ($pdo->query($sql) as $row) {
	
  $aProcesos[]=[
    0 => $row[0],
    1 => $row[1],
    2 => $row[2]
  ];
}

Database::disconnect();
echo json_encode($aProcesos);
?>