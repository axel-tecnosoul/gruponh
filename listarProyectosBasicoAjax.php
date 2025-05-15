<?php
session_start();
include 'database.php';

$aContactos=[];

$pdo = Database::connect();

$orderBy = " ORDER BY ";
foreach ($_GET['order'] as $order) {
  $orderBy .= $order['column'] + 1 . " {$order['dir']}, ";
}

$orderBy = substr($orderBy, 0, -2);

$columns = $_GET['columns'];

$fields = [ 
	"p.`id`", 
	"s.nro_sitio", 
	"s.nro_subsitio", 
	"p.nombre", 
	"p.`descripcion`", 
	"c.nombre", 
	"ep.`estado`", 
	"c2.nombre", 
	"date_format(p.`fecha_pedido`,'%d/%m/%y')", 
	"date_format(p.`fecha_entrega`,'%d/%m/%y')", 
	"ln.`linea_negocio`", 
	"p.`tags`", 
	"date_format(p.`fecha_pedido`,'%Y%m%d')", 
	"date_format(p.`fecha_entrega`,'%Y%m%d')",
	"p.nro"
];

$where = ' 1 and p.anulado = 0 ';
if (!empty($_GET['linea'])) $where .= ' and ln.id = '.$_GET['linea'];
if (!empty($_GET['estado'])) {
	$where .= ' and ep.id in ('.$_GET['estado'].') ';
}
if (!empty($_GET['nro'])) $where .= ' and (p.nro = '.$_GET['nro'].' or s.nro_sitio = '.$_GET['nro'].') ';
if (!empty($_GET['nombre'])) $where .= " and p.nombre like '%".$_GET['nombre']."%' ";
if (!empty($_GET['cliente'])) $where .= " and c.nombre like '%".$_GET['cliente']."%' ";

$adicionales = false;



$length = $_GET['length'];
$start = $_GET['start'];

$from=" FROM `proyectos` p inner join lineas_negocio ln on ln.id = p.`id_linea_negocio` inner join tipos_proyecto tp on tp.id = p.`id_tipo_proyecto` inner join estados_proyecto ep on ep.id = p.`id_estado_proyecto` inner join sitios s on s.id = p.id_sitio inner join cuentas c on c.id = p.id_cliente inner join cuentas c2 on c2.id = p.id_gerente ";

$countSql = "SELECT count($fields[0]) as Total $from";
$countSt = $pdo->query($countSql);
$total = $countSt->fetch()['Total'];


$queryFiltered="SELECT COUNT($fields[0]) AS recordsFiltered $from ".($where ? "WHERE $where " : '');
$resFilterLength = $pdo->query($queryFiltered);
$recordsFiltered = $resFilterLength->fetch()['recordsFiltered'];

$campos=implode(",", $fields);

$sql = "SELECT $campos $from ".($where ? "WHERE $where " : '')."$orderBy LIMIT $length OFFSET $start";

$st = $pdo->query($sql);

if ($st) {
  foreach ($pdo->query($sql) as $row) {
	  
	$presupuestoS = 0;
	$presupuestoUSD = 0;
	$sql2 = " SELECT m.id, p.monto FROM proyectos_presupuestos pp inner join presupuestos p on p.id = pp.id_presupuesto inner join cuentas c on c.id = p.id_cuenta inner join monedas m on m.id = p.id_moneda WHERE p.anulado = 0 and p.adjudicado = 1 and pp.id_proyecto = ".$row[0];
	foreach ($pdo->query($sql2) as $row2) {
		if ($row2[0] == 1) { //dolares
			$presupuestoUSD += $row2[1];
		} else { //pesos
			$presupuestoS += $row2[1];
		}
	}
	
	$certificadoS = 0;
	$certificadoUSD = 0;
	$sql3 = " SELECT cm.id_moneda, cab.monto_total FROM proyectos_presupuestos pp inner join presupuestos p on p.id = pp.id_presupuesto inner join cuentas c on c.id = p.id_cuenta inner join monedas m on m.id = p.id_moneda inner join occ occ on occ.id_presupuesto = pp.id_presupuesto inner join certificados_maestros cm on cm.id_occ = occ.id inner join certificados_avances_cabecera cab on cab.id_certificado_maestro = cm.id WHERE p.anulado = 0 and p.adjudicado = 1 and pp.id_proyecto = ".$row[0];
	foreach ($pdo->query($sql3) as $row3) {
		if ($row3[0] == 1) { //dolares
			$certificadoUSD += $row3[1];
		} else { //pesos
			$certificadoS += $row3[1];
		}
	}
	
	$facturadoS = 0;
	$facturadoUSD = 0;
	$sql4 = " SELECT `id_moneda`, `total` FROM `facturas_venta` WHERE id_estado in (3,4) and id_proyecto = ".$row[0];
	foreach ($pdo->query($sql4) as $row4) {
		if ($row4[0] == 1) { //dolares
			$facturadoUSD += $row4[1];
		} else { //pesos
			$facturadoS += $row4[1];
		}
	}
	
	$pagadoS = 0;
	$pagadoUSD = 0;
	$sql5 = " SELECT `id_moneda`, `total` FROM `facturas_venta` WHERE id_estado = 4 and id_proyecto = ".$row[0];
	foreach ($pdo->query($sql5) as $row5) {
		if ($row5[0] == 1) { //dolares
			$pagadoUSD += $row5[1];
		} else { //pesos
			$pagadoS += $row5[1];
		}
	}
	
	$presupuestoPesos = number_format($presupuestoS,2);
	$certificadoPesos = number_format($certificadoS,2);
	$facturadoPesos = number_format($facturadoS,2);
	$pagadoPesos = number_format($pagadoS,2);
	$presupuestoDolares = number_format($presupuestoUSD,2);
	$certificadoDolares = number_format($certificadoUSD,2);
	$facturadoDolares = number_format($facturadoUSD,2);
	$pagadoDolares = number_format($pagadoUSD,2);
		
	$aContactos[]=[
      $row[1] .' / '.$row[2],
      $row[0],
	  $row[14],
	  $row[3],
	  $row[4],
	  $row[5],
	  $row[6],
	  $row[7],
	  '<span style="display: none;">'. $row[12] . '</span>'. $row[8],
	  '<span style="display: none;">'. $row[13] . '</span>'. $row[9],
	  $row[10],
	  $row[11]
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