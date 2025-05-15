<?php
require("config.php");
require 'database.php';

$id_proyecto = $_POST['id_proyecto'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//$sql = " SELECT m.id, m.codigo, m.concepto, cd.reservado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id where lcc.id = = ".$id_proyecto;
$sql = " SELECT m.id, m.codigo, m.concepto, cd.reservado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea where cd.cancelado = 0 and t.id_proyecto = ".$id_proyecto;
//echo $sql;
$aConceptos=[];
foreach ($pdo->query($sql) as $row) {
	//if ($row['reservado'] > 0) {
    $nombre=$row['concepto']." (".$row['codigo'].") - Reservado: ".$row['reservado'];

    $aConceptos[]=[
      "id" => $row['id'],
      "nombre" => $nombre,
    ];
  //}
}

Database::disconnect();
echo json_encode($aConceptos);
?>