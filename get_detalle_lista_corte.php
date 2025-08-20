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
    IFNULL(otd.cant_liberadas,0) AS cant_liberadas,
    IFNULL(otd.cant_proceso,0) AS cant_proceso,
    GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos,
    elcc.estado AS estado
  FROM listas_corte_conjuntos lcc
  INNER JOIN estados_lista_corte_conjuntos elcc ON elcc.id = lcc.id_estado_lista_corte_conjuntos
  LEFT JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto = lcc.id
  LEFT JOIN materiales m ON lcp.id_material = m.id
  LEFT JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion = lcp.id
  LEFT JOIN tipos_procesos tp ON lcpr.id_tipo_proceso = tp.id
  LEFT JOIN (
      SELECT id_posicion, SUM(cant_liberadas) AS cant_liberadas, SUM(cant_proceso) AS cant_proceso
      FROM ordenes_trabajo_detalle
      GROUP BY id_posicion
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
    6=>$row["cant_liberadas"],
    7=>$row["cant_proceso"],
    8=>$row["procesos"],
    9=>$row["estado"]
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>