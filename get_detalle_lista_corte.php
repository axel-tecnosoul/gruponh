<?php
require("config.php");
require 'database.php';

$id_lc = $_POST['id_lc'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//$sql = " SELECT c.id, c.nombre, c.cantidad, c.peso, e.estado FROM listas_corte_conjuntos c inner join estados_lista_corte_conjuntos e on e.id = c.id_estado_lista_corte_conjuntos WHERE c.id_lista_corte = ".$id_lc;
$sql = " SELECT lcc.nombre,lcc.cantidad AS cant_conj,lcp.posicion,lcp.cantidad AS cant_pos,m.concepto,GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos,elcc.estado as estado FROM listas_corte_conjuntos lcc inner join estados_lista_corte_conjuntos elcc on elcc.id = lcc.id_estado_lista_corte_conjuntos LEFT JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto=lcc.id LEFT JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion=lcp.id LEFT JOIN materiales m ON lcp.id_material=m.id LEFT JOIN tipos_procesos tp ON lcpr.id_tipo_proceso=tp.id WHERE lcc.id_lista_corte = $id_lc GROUP BY lcp.id";
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
    5=>$row["procesos"],
	6=>$row["estado"]
  ];
}

Database::disconnect();
echo json_encode($aConjuntos);
?>