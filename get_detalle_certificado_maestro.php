<?php
require("config.php");
require 'database.php';

$id_certificado_maestro = $_POST['id_certificado_maestro'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cmd.id,s.nombre AS sitio,s2.nombre AS subsitio,cmd.id_proyecto,p.nombre AS proyecto,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cmd.subtotal FROM certificados_maestros_detalles cmd INNER JOIN proyectos p ON cmd.id_proyecto=p.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id left join sitios s on s.id = p.id_sitio left join sitios s2 on s2.id = s.id_sitio_superior WHERE id_certificado_maestro = ".$id_certificado_maestro;
//echo $sql;
$aConjuntos=[];
foreach ($pdo->query($sql) as $row) {
  $aConjuntos[]=[
    0=>$row["id"],
    1=>$row["proyecto"],
    2=>$row["subsitio"],
    3=>$row["sitio"],
    4=>$row["tipo"],
    5=>$row["descripcion"],
    6=>$row["cantidad"],
    7=>$row["unidad_medida"],
    8=>number_format($row["precio_unitario"],2),
    9=>number_format($row["subtotal"],2),
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>