<?php
require("config.php");
require 'database.php';

$id_sec = $_POST['id_sec'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT plc.`id`, lcc.nombre, m.concepto, plc.`cantidad`, plc.`observaciones`, e.estado FROM `packing_lists_componentes` plc LEFT JOIN listas_corte_conjuntos lcc on lcc.id = plc.`id_conjunto_lista_corte` LEFT JOIN materiales m on m.id = plc.`id_concepto` INNER JOIN estados_componentes_packing_list e on e.id = plc.`id_estado_componente_packing_list` WHERE plc.`id_packing_list_seccion` = ".$id_sec;
$aComponentes=[];
foreach ($pdo->query($sql) as $row) {
	
  $aComponentes[]=[
    0 => $row[0],
    1 => $row[1],
    2 => $row[2],
    3 => $row[3],
	4 => $row[4],
	5 => $row[5]
  ];
}

Database::disconnect();
echo json_encode($aComponentes);
?>