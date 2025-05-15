<?php
require("config.php");
require 'database.php';

$id_proyecto = $_POST['id_proyecto'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT t.`id`, p.`nombre`, s.nombre, t.`estructura`, sec.`sector`, tt.`tipo`, c.`nombre`, date_format(t.`fecha_inicio_estimada`,'%d/%m/%y'), date_format(t.`fecha_fin_estimada`,'%d/%m/%y'), date_format(t.`fecha_inicio_real`,'%d/%m/%y'), date_format(t.`fecha_fin_real`,'%d/%m/%y'), c2.`nombre`,t.observaciones, date_format(t.`fecha_inicio_estimada`,'%y%m%d'), date_format(t.`fecha_fin_estimada`,'%y%m%d'), date_format(t.`fecha_inicio_real`,'%y%m%d'), date_format(t.`fecha_fin_real`,'%y%m%d') FROM `tareas` t inner join proyectos p on p.id = t.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join sectores sec on sec.id = t.`id_sector` inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` left join cuentas c on c.id = t.`id_coordinador` left join cuentas c2 on c2.id = t.`id_recurso` WHERE t.`anulado` = 0 and p.anulado = 0 and p.id = ".$id_proyecto;
$aTareas=[];
foreach ($pdo->query($sql) as $row) {
	$completada = 'No';
	if ($row[10] != '00/00/00') {
		$completada = 'Si';
	}
	$tieneComputo = 'No';
	$sql2 = "SELECT `id` from computos where id_tarea = ? and id_estado <> 6 ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute([$row[0]]);
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	$idComputo = 0;
	if (!empty($data2)) {
		$tieneComputo = 'Si';	
		$idComputo = $data2['id'];
	}
	$tieneLC = 'No';
	$sql2 = "SELECT `id` from listas_corte where id_tarea = ?  ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute([$row[0]]);
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	if (!empty($data2)) {
		$tieneLC = 'Si';	
	}
	$tienePL = 'No';
	$sql2 = "SELECT `id` from packing_lists where id_tarea = ? ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute([$row[0]]);
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	if (!empty($data2)) {
		$tienePL = 'Si';	
	}
  $aTareas[]=[
    0 => $row[0],
    1 => $row[3],
    2 => $row[4],
    3 => $row[5],
	4 => $row[11],
	5 => $row[6],
	6 => $row[12],
	7 => "<span style='display: none;'>". $row[13] . "</span>".$row[7],
	8 => "<span style='display: none;'>". $row[14] . "</span>".$row[8],
	9 => "<span style='display: none;'>". $row[15] . "</span>".$row[9],
	10 => "<span style='display: none;'>". $row[16] . "</span>".$row[10],
	11 => $completada,
	12 => $tieneComputo,
	13 => '<a href="verComputo.php?id='.$idComputo.'">'.$idComputo.'</a>',
	14 => $tieneLC,
	15 => $tienePL
  ];
}

Database::disconnect();
echo json_encode($aTareas);
?>