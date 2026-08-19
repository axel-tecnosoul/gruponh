<?php
require("config.php");
require 'database.php';

$id_certificado_avance = $_POST['id_certificado_avance'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cad.id AS id_certificados_avances_detalle,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cad.cantidad_actual,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cad.subtotal,cad.id_comprobante,m.moneda FROM certificados_avances_detalle cad INNER JOIN certificados_maestros_detalles cmd ON cad.id_certificado_maestro_detalle=cmd.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id INNER JOIN certificados_maestros cm ON cmd.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE id_certificado_avance = ".$id_certificado_avance;
//echo $sql;
$aConjuntos=[];
foreach ($pdo->query($sql) as $row) {
  $aConjuntos[]=[
    0=>$row["id_certificados_avances_detalle"],
    1=>$row["descripcion"],
    2=>$row["cantidad_actual"],
    3=>$row["unidad_medida"],
    4=>$row["moneda"]." ".number_format($row["precio_unitario"],2),
    5=>$row["moneda"]." ".number_format($row["subtotal"],2),
    6=>$row["id_comprobante"],
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>