<?php
require("config.php");
require 'database.php';

$id_consumo = $_POST['id_consumo'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cd.id,m.concepto,c.nro_colada,cd.situacion,cd.cantidad,um.unidad_medida,cd.observacion FROM consumos_detalle cd INNER JOIN materiales m ON cd.id_material=m.id INNER JOIN coladas c ON cd.id_colada=c.id INNER JOIN unidades_medida um ON cd.id_unidad_medida=um.id WHERE cd.id_consumo = ".$id_consumo;
//echo $sql;
$aConjuntos=[];
foreach ($pdo->query($sql) as $row) {
  $aConjuntos[]=[
    0=>$row["id"],
    1=>$row["concepto"],
    2=>$row["nro_colada"],
    3=>$row["situacion"],
    4=>$row["cantidad"],
    5=>$row["unidad_medida"],
    6=>$row["observacion"],
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>