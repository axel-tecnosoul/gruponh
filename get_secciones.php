<?php
require("config.php");
require 'database.php';

$id_pl = $_POST['id_pl'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT pls.id, pls.cantidad, pls.observaciones,m.concepto,lcc.nombre,plc.cantidad,plc.observaciones,ecpl.estado,m.peso_metro, lcc.id FROM packing_lists_secciones pls left JOIN packing_lists_componentes plc ON plc.id_packing_list_seccion=pls.id left JOIN listas_corte_conjuntos lcc ON plc.id_conjunto_lista_corte=lcc.id left JOIN materiales m ON plc.id_concepto=m.id left JOIN estados_componentes_packing_list ecpl ON plc.id_estado_componente_packing_list=ecpl.id WHERE pls.id_packing_list_revision = ".$id_pl;

$aSecciones=[];
foreach ($pdo->query($sql) as $row) {
  $componente = $row[3].' '.$row[4];
  
  $pesoComponente = 0;
  if (!empty($row[8])) {
	$pesoComponente = $row[8]*$row[5];
  } 
  if (!empty($row[9])) {
	  $pesoComponente = 0;
	  $sql2 = " SELECT peso from lista_corte_posiciones where id_lista_corte_conjunto = ".$row[9];
	  foreach ($pdo->query($sql2) as $row2) {
		  $pesoComponente += $row2[0];
	  }
	  $pesoComponente *= $row[5];
  }
  
  $pesoSeccion = 0;
  $sql3 = " SELECT plc.`id_conjunto_lista_corte`, plc.`id_concepto`, plc.cantidad, m.peso_metro from packing_lists_componentes plc left join materiales m on m.id = plc.id_concepto where plc.id_packing_list_seccion = ".$row[0];
  foreach ($pdo->query($sql3) as $row3) {
	  if (!empty($row3[0])) {
		  $sql4 = " SELECT peso from lista_corte_posiciones where id_lista_corte_conjunto = ".$row3[0];
		  foreach ($pdo->query($sql4) as $row4) {
			  $pesoSeccion += $row4[0];
		  }
		  $pesoSeccion *= $row3[2];
	  }
	  if (!empty($row3[1])) {
		$pesoSeccion += $row3[3];
		$pesoSeccion *= $row3[2];
	  }
  }
  
  
  $aSecciones[]=[
    0 => $row[2],
    1 => $row[1],
	2 => $pesoSeccion,
	3 => $componente,
    4 => $row[5],
    5 => $pesoComponente,
    6 => $row[6],
    7 => $row[7],
  ];
}
Database::disconnect();
echo json_encode($aSecciones);
?>