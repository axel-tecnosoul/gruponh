<?php
require("config.php");
require 'database.php';

$id_ot = $_POST['id_ot'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT otd.id,lcc.nombre,lcc.cantidad AS cant_conj,lcp.posicion,lcp.cantidad AS cant_pos,otd.cantidad AS cant_pedida,m.concepto,GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos,eotp.estado,eotp.id AS id_estado,cant_liberadas,cant_proceso,cant_rechazadas,date_format(`fecha`,'%d/%m/%y') fecha, u.usuario FROM ordenes_trabajo_detalle otd left join usuarios u on u.id = otd.`id_usuario` INNER JOIN lista_corte_posiciones lcp ON otd.id_posicion=lcp.id INNER JOIN listas_corte_conjuntos lcc ON lcp.id_lista_corte_conjunto=lcc.id INNER JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion=lcp.id INNER JOIN tipos_procesos tp ON lcpr.id_tipo_proceso=tp.id INNER JOIN materiales m ON lcp.id_material=m.id INNER JOIN estados_orden_trabajo_posicion eotp ON otd.id_estado_orden_trabajo_posicion=eotp.id WHERE otd.id_orden_trabajo = $id_ot GROUP BY lcp.id";
$aConjuntos=[];
foreach ($pdo->query($sql) as $row) {
  $aConjuntos[]=[
    0=>$row["id"],
    1=>$row["nombre"],
    2=>$row["cant_conj"],
    3=>$row["posicion"],
    4=>$row["cant_pedida"],
    5=>$row["concepto"],
    6=>$row["procesos"],
    7=>$row["estado"],
    8=>$row["cant_liberadas"],
    9=>$row["cant_proceso"],
    10=>$row["cant_rechazadas"],
	11=>$row["fecha"],
	12=>$row["usuario"],
	13=>$row["id_estado"]
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>