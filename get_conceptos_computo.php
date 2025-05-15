<?php
require("config.php");
require 'database.php';

$id_computo = $_POST['id_computo'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT d.id, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), d.aprobado, d.id_material, d.reservado, d.comprado,SUM(id.saldo) AS disponible,d.comentarios, date_format(d.fecha_necesidad,'%y%m%d'), d.cancelado FROM computos_detalle d inner join materiales m on m.id = d.id_material left JOIN ingresos_detalle id ON id.id_material=m.id WHERE d.cancelado = 0 and d.id_computo = $id_computo GROUP BY m.id";
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
	if (empty($row[8])) {
		$row[8] = 0;
	}
	if ($row[4]==1) {
		$row[4] = 'Si';
	} else {
		$row[4] = 'No';
	}
	$saldo = $row[2]-$row[8]-$row[6]-$row[7];


	$cancelado = "No";
	if ($row[11]==1) {
		$cancelado = "Si";	
	}
  $aConceptos[]=[
    0 => $row[1],
	1 => $row[2],
	2 => "<span style='display: none;'>". $row[10] . "</span>".$row[3],
	3 => $row[4],
	4 => $row[8],
    5 => $row[6],
    6 => $row[7],
    7 => $saldo,
	8 => $row[9]
    
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>