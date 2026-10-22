<?php
session_start();
include 'database.php';

$aContactos=[];

$pdo = Database::connect();

// Mapeo: columna visible del DataTable -> campo(s) SQL por los que se ordena.
// Las columnas de montos (10-17) se calculan en PHP y no tienen campo en la DB.
$camposOrden = [
    0  => ['s.`nro_sitio`', 's.`nro_subsitio`'],   // Sitio/Sub
    1  => ['p.`id`'],                              // Proy (oculta: ID)
    2  => ['p.`nro`'],                             // Proy
    3  => ['p.`nombre`'],                          // Nombre
    4  => ['p.`descripcion`'],                     // Descripción
    5  => ['c.`nombre`'],                          // Cliente
    6  => ['ep.`estado`'],                         // Estado
    7  => ['c2.`nombre`'],                         // Responsable
    8  => ['p.`fecha_pedido`'],                    // F Adjudicado (fecha real, no el texto dd/mm/yy)
    9  => ['p.`fecha_entrega`'],                   // F Entrega (fecha real)
    10 => [], 11 => [], 12 => [], 13 => [],        // Presup$/Cert$/Facturado$/Pagado$ (PHP)
    14 => [], 15 => [], 16 => [], 17 => [],        // USD (PHP)
    18 => ['ln.`linea_negocio`'],                  // Línea de Negocio
    19 => ['p.`tags`'],                            // Claves
];

$orderBy = " ORDER BY ";
$hayOrden = false;
$dirDefault = 'ASC';
if (isset($_GET['order']) && is_array($_GET['order'])) {
  foreach ($_GET['order'] as $order) {
    $col = isset($order['column']) ? (int)$order['column'] : -1;
    $dir = (isset($order['dir']) && strtolower($order['dir']) === 'desc') ? 'DESC' : 'ASC';
    if (empty($hayOrden) && $col >= 0) {
      $dirDefault = $dir; // dirección elegida, por si la columna no tiene campo SQL
    }
    if ($col < 0 || empty($camposOrden[$col])) continue;
    foreach ($camposOrden[$col] as $campo) {
      $orderBy .= $campo . " " . $dir . ", ";
      $hayOrden = true;
    }
  }
}
if ($hayOrden) {
  $orderBy = substr($orderBy, 0, -2);
} else {
  $orderBy = " ORDER BY p.`id` " . $dirDefault . " ";
}

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

// Buscadores del pie de la tabla: búsqueda global y por columna (serverSide).
// El índice es el de la columna visible; los montos se calculan en PHP y no son filtrables por SQL.
$columnasBusqueda = [
	"concat(s.nro_sitio,' / ',s.nro_subsitio)",  // 0 Sitio/Sub
	"p.`id`",                                   // 1 Proy (oculta)
	"p.nro",                                    // 2 Proy
	"p.nombre",                                 // 3 Nombre
	"p.`descripcion`",                          // 4 Descripción
	"c.nombre",                                 // 5 Cliente
	"ep.`estado`",                              // 6 Estado
	"c2.nombre",                                // 7 Responsable
	"date_format(p.`fecha_pedido`,'%d/%m/%y')", // 8 F Adjudicado
	"date_format(p.`fecha_entrega`,'%d/%m/%y')",// 9 F Entrega
	null, null, null, null,                     // 10-13 Presup$/Certificado$/Facturado$/Pagado$
	null, null, null, null,                     // 14-17 PresupUSD/CertificadoUSD/FacturadoUSD/PagadoUSD
	"ln.`linea_negocio`",                       // 18 Línea de Negocio
	"p.`tags`",                                 // 19 Claves
];

// Buscador global del DataTable.
$searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
if ($searchValue !== '') {
	$like = $pdo->quote('%' . $searchValue . '%');
	$likes = array();
	foreach ($columnasBusqueda as $campo) {
		if ($campo === null) continue;
		$likes[] = $campo . ' LIKE ' . $like;
	}
	if (!empty($likes)) {
		$where .= ' and (' . implode(' or ', $likes) . ') ';
	}
}

// Buscadores del pie: DataTables serverSide envía columns[i][search][value].
if (isset($_GET['columns']) && is_array($_GET['columns'])) {
	foreach ($columnasBusqueda as $indice => $campo) {
		if ($campo === null) continue;
		$valor = isset($_GET['columns'][$indice]['search']['value'])
			? trim($_GET['columns'][$indice]['search']['value'])
			: '';
		if ($valor !== '') {
			$where .= ' and ' . $campo . ' LIKE ' . $pdo->quote('%' . $valor . '%') . ' ';
		}
	}
}


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
	$sql2 = " SELECT m.id, p.monto FROM proyectos_presupuestos pp inner join presupuestos p on p.id = pp.id_presupuesto inner join monedas m on m.id = p.id_moneda WHERE p.anulado = 0 and p.adjudicado = 1 and pp.id_proyecto = ".$row[0];
	foreach ($pdo->query($sql2) as $row2) {
		if ($row2[0] == 1) { //dolares
			$presupuestoUSD += $row2[1];
		} else { //pesos
			$presupuestoS += $row2[1];
		}
	}
	
	$certificadoS = 0;
	$certificadoUSD = 0;
	$sql3 = " SELECT cm.id_moneda, cab.monto_total FROM proyectos_presupuestos pp inner join presupuestos p on p.id = pp.id_presupuesto inner join monedas m on m.id = p.id_moneda inner join occ occ on occ.id_presupuesto = pp.id_presupuesto inner join certificados_maestros cm on cm.id_occ = occ.id inner join certificados_avances_cabecera cab on cab.id_certificado_maestro = cm.id WHERE p.anulado = 0 and p.adjudicado = 1 and pp.id_proyecto = ".$row[0];
	foreach ($pdo->query($sql3) as $row3) {
		if ($row3[0] == 1) { //dolares
			$certificadoUSD += $row3[1];
		} else { //pesos
			$certificadoS += $row3[1];
		}
	}
	
	$facturadoS = 0;
	$facturadoUSD = 0;
	$sql4 = " SELECT `id_moneda`, `total` FROM `facturas_venta` WHERE id_estado = 3 and id_proyecto = ".$row[0];
	foreach ($pdo->query($sql4) as $row4) {
		if ($row4[0] == 1) { //dolares
			$facturadoUSD += $row4[1];
		} else { //pesos
			$facturadoS += $row4[1];
		}
	}
	
	$pagadoS = 0;
	$pagadoUSD = 0;
	$sql5 = " SELECT `id_moneda`, `total` FROM `facturas_venta` WHERE id_estado = 3 AND pagada = 1 and id_proyecto = ".$row[0];
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
	  $presupuestoPesos,
	  $certificadoPesos,
	  $facturadoPesos,
	  $pagadoPesos,
	  $presupuestoDolares,
	  $certificadoDolares,
	  $facturadoDolares,
	  $pagadoDolares,
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