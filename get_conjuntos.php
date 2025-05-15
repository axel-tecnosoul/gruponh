<?php
require("config.php");
require 'database.php';

$id_lc = $_POST['id_lc'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT c.`id`, c.`nombre`, c.`cantidad`, c.`peso`, e.estado FROM `listas_corte_conjuntos` c inner join estados_lista_corte_conjuntos e on e.id = c.`id_estado_lista_corte_conjuntos` WHERE c.`id_lista_corte` = ".$id_lc;
$aConjuntos=[];
foreach ($pdo->query($sql) as $row) {
	
  $aConjuntos[]=[
    0 => $row[0],
    1 => $row[1],
    2 => $row[2],
    3 => $row[3],
	4 => $row[4]
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>