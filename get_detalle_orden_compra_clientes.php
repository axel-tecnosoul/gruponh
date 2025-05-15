<?php
require("config.php");
require 'database.php';

$id_occ = $_POST['id_occ'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT d.id, d.descripcion, d.cantidad, d.precio_unitario, d.descuento, d.subtotal, c.id_moneda FROM occ_detalles d inner join occ c on c.id = d.id_occ WHERE d.id_occ = ".$id_occ;
//echo $sql;
$aConjuntos=[];
foreach ($pdo->query($sql) as $row) {
  
  $signo = '$';
  if ($row["id_moneda"] == 1) {
	$signo = 'u$s';  
  }
	
  $aConjuntos[]=[
    0=>$row["id"],
    1=>$row["descripcion"],
    2=>$row["cantidad"],
    3=>$signo.number_format($row["precio_unitario"],2),
    4=>$signo.number_format($row["descuento"],2),
    5=>$signo.number_format($row["subtotal"],2),
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>