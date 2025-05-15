<?php
session_start();
include 'database.php';

$aContactos=[];

$pdo = Database::connect();

$columns = $_GET['columns'];

$fields = [ 
	"t.`id`", 
	"p.`nombre`", 
	"s.nombre", 
	"t.`estructura`", 
	"sec.`sector`", 
	"tt.`tipo`", 
	"c.`nombre`", 
	"date_format(t.`fecha_inicio_estimada`,'%d/%m/%y')", 
	"date_format(t.`fecha_fin_estimada`,'%d/%m/%y')", 
	"date_format(t.`fecha_inicio_real`,'%d/%m/%y')", 
	"date_format(t.`fecha_fin_real`,'%d/%m/%y')", 
	"c2.`nombre`", 
	"t.observaciones", 
	"s.nro_sitio", 
	"s.nro_subsitio", 
	"p.nro", 
	"p.nombre", 
	"date_format(t.`fecha_inicio_estimada`,'%Y%m%d')", 
	"date_format(t.`fecha_fin_estimada`,'%Y%m%d')", 
	"date_format(t.`fecha_inicio_real`,'%Y%m%d')", 
	"date_format(t.`fecha_fin_real`,'%Y%m%d')"
];

$where = ' 1 ';
if (!empty($_GET['id_tipo_tarea'])) $where .= ' and tt.id = '.$_GET['id_tipo_tarea'];
if (!empty($_GET['nro'])) $where .= ' and (p.nro = '.$_GET['nro'].' or s.nro_sitio = '.$_GET['nro'].') ';
if ((!empty($_GET['completada'])) && ($_GET['completada']==1)) $where .= ' and date_format(t.`fecha_fin_real`,\'%d/%m/%y\') != \'00/00/00\'';
$adicionales = false;

$order = ' order by '.$_GET['orden'];


$length = $_GET['length'];
$start = $_GET['start'];

$from=" FROM `tareas` t inner join proyectos p on p.id = t.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join sectores sec on sec.id = t.`id_sector` inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` left join cuentas c on c.id = t.`id_coordinador` left join cuentas c2 on c2.id = t.`id_recurso` ";

$countSql = "SELECT count($fields[0]) as Total $from";
$countSt = $pdo->query($countSql);
$total = $countSt->fetch()['Total'];


$queryFiltered="SELECT COUNT($fields[0]) AS recordsFiltered $from ".($where ? "WHERE $where " : '');
$resFilterLength = $pdo->query($queryFiltered);
$recordsFiltered = $resFilterLength->fetch()['recordsFiltered'];

$campos=implode(",", $fields);

$sql = "SELECT $campos $from ".($where ? "WHERE $where " : '')." $order LIMIT $length OFFSET $start";
$st = $pdo->query($sql);

if ($st) {
  foreach ($pdo->query($sql) as $row) {
	  
	$tieneComputo = 0;
	$sql2 = "SELECT `id` from computos where id_tarea = ? and id_estado <> 6 ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute([$row[0]]);
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	$idComputo = 0;
	if (!empty($data2)) {
		$tieneComputo = 1;	
		$idComputo = $data2['id'];
	}
	$tieneLC = 0;
	$sql2 = "SELECT `id` from listas_corte where id_tarea = ? ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute([$row[0]]);
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	if (!empty($data2)) {
		$tieneLC = 1;	
	}
	$tienePL = 0;
	$sql2 = "SELECT `id` from packing_lists where id_tarea = ? ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute([$row[0]]);
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	if (!empty($data2)) {
		$tienePL = 1;	
	}
	
	$completada = "No";
	if ($row[10] != '00/00/00') {
		$completada = "Si";
	}
	
	$contieneComputo = "No";
	$codigoComputo = "";
	if ($tieneComputo == 1) {
		$contieneComputo = "Si";
		$codigoComputo = $idComputo;
	}
	
	$contieneLC = "No";
	if ($tieneLC == 1) {
		$contieneLC = "Si";
	}

	$contienePL = "No";
	if ($tienePL == 1) {
		$contienePL = "Si";
	}
	
    
	$aContactos[]=[
      $row[0],
      $row[13],
      $row[14],
      $row[15],
      $row[16],
	  $row[3],
	  $row[4],
	  $row[5],
	  $row[11],
	  $row[6],
	  $row[12],
	  '<span style="display: none;">'. $row[17] . '</span>'. $row[7],
	  '<span style="display: none;">'. $row[18] . '</span>'. $row[8],
	  '<span style="display: none;">'. $row[19] . '</span>'. $row[9],
	  '<span style="display: none;">'. $row[20] . '</span>'. $row[10],
	  $completada,
	  $contieneComputo,
	  $codigoComputo,
	  $contieneLC,
	  $contienePL
    ];
  }

  Database::disconnect();

  echo json_encode([
    'data' => $aContactos,
    'recordsTotal' => $total,
    'recordsFiltered' => $recordsFiltered,
  ]);
} else {
  var_dump($pdo->errorInfo());
  die;
}