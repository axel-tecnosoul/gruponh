<?php
require("config.php");
require 'database.php';

$id_certificado_avance = $_POST['id_certificado_avance'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cad.id AS id_certificados_avances_detalle,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cad.cantidad_actual,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cad.subtotal,cad.id_comprobante FROM certificados_avances_detalle cad INNER JOIN certificados_maestros_detalles cmd ON cad.id_certificado_maestro_detalle=cmd.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id WHERE id_certificado_avance = ".$id_certificado_avance;
//echo $sql;
$aConjuntos=[];
foreach ($pdo->query($sql) as $row) {
  $aConjuntos[]=[
    0=>$row["id_certificados_avances_detalle"],
    1=>$row["tipo"],
    2=>$row["descripcion"],
    3=>$row["cantidad_actual"],
    4=>$row["unidad_medida"],
    5=>number_format($row["precio_unitario"],2),
    6=>number_format($row["subtotal"],2),
    7=>$row["id_comprobante"],
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>