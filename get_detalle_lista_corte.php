<?php
require("config.php");
require 'database.php';

$id_lc = $_POST['id_lc'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT 
    lcc.nombre,
    lcc.cantidad AS cant_conj,
    lcp.posicion,
    m.concepto,
    lcp.cantidad AS cant_pos,
    (lcc.cantidad * lcp.cantidad) AS cant_total,
    COALESCE(otd.cant_ot,0) AS cant_ot,
    COALESCE(otd.cant_liberadas,0) AS cant_liberadas,
    COALESCE(otd.cant_rechazadas,0) AS cant_rechazadas,
    COALESCE(otd.cant_reproceso,0) AS cant_reproceso,
    GROUP_CONCAT(DISTINCT tp.tipo SEPARATOR ',') AS procesos,
    elcc.estado AS estado
  FROM listas_corte_conjuntos lcc
  INNER JOIN estados_lista_corte_conjuntos elcc ON elcc.id = lcc.id_estado_lista_corte_conjuntos
  LEFT JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto = lcc.id
  LEFT JOIN materiales m ON lcp.id_material = m.id
  LEFT JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion = lcp.id
  LEFT JOIN tipos_procesos tp ON lcpr.id_tipo_proceso = tp.id
  LEFT JOIN (
      SELECT otd.id_posicion,
             SUM(otd.cant_liberadas) AS cant_liberadas,
             SUM(otd.cant_rechazadas) AS cant_rechazadas,
             SUM(otd.cant_proceso) AS cant_reproceso,
             SUM(CASE WHEN ot.id_estado_orden_trabajo = 3 THEN otd.cantidad ELSE 0 END) AS cant_ot
      FROM ordenes_trabajo_detalle otd
      INNER JOIN ordenes_trabajo ot ON ot.id = otd.id_orden_trabajo
      GROUP BY otd.id_posicion
  ) otd ON otd.id_posicion = lcp.id
  WHERE lcc.id_lista_corte = $id_lc
  GROUP BY lcp.id";
$aConjuntos=[];
foreach ($pdo->query($sql) as $row) {
  $aConjuntos[]=[
    /*"nombre"=>$row["nombre"],
    "cantidad"=>$row["cantidad"],
    "posicion"=>$row["posicion"],
    "cantidad"=>$row["cantidad"],
    "concepto"=>$row["concepto"],
    "procesos"=>$row["procesos"],*/
    0=>$row["nombre"],
    1=>$row["cant_conj"],
    2=>$row["posicion"],
    3=>$row["concepto"],
    4=>$row["cant_pos"],
    5=>$row["cant_total"],
    6=>$row["cant_ot"],
    7=>$row["cant_liberadas"],
    8=>$row["cant_rechazadas"],
    9=>$row["cant_reproceso"],
    10=>$row["procesos"],
    11=>$row["estado"]
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>