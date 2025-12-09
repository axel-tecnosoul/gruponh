<?php
require("config.php");
require 'database.php';
require_once 'funciones.php';

$id_pedido = $_POST['id_pedido'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT d.id, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), u.unidad_medida,d.id_material,d.reservado,d.comprado, date_format(d.fecha_necesidad,'%y%m%d'), d.id_estado, epd.estado, epd.descripcion FROM pedidos_detalle d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida LEFT JOIN estados_pedidos_detalle epd ON epd.id = d.id_estado WHERE d.id_pedido = ".$id_pedido;//." AND d.cancelado = 0";
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
	// Verificar y asignar estado al principio del bucle
	if ($row[9] === null || empty($row[10])) { // No tiene id_estado válido o nombre de estado
		// Calcular y actualizar el estado
		$nuevoEstadoId = calcularEstadoPedidoDetalle($pdo, $row[0]);
		if ($nuevoEstadoId) {
			actualizarEstadoPedidoDetalle($pdo, $row[0]);
			// Actualizar los valores del row para usar el nuevo estado
			$row[9] = $nuevoEstadoId;
			// Obtener el nombre y descripción del estado calculado
			$sqlEstado = "SELECT estado, descripcion FROM estados_pedidos_detalle WHERE id = ?";
			$qEstado = $pdo->prepare($sqlEstado);
			$qEstado->execute([$nuevoEstadoId]);
			$dataEstado = $qEstado->fetch(PDO::FETCH_ASSOC);
			if ($dataEstado) {
				$row[10] = $dataEstado['estado'];
				$row[11] = $dataEstado['descripcion'];
			}
		}
	}
	
	// Ya tenemos id_estado y nombre del estado válidos
	$estadoTexto = $row[10] ?: '';
	$estadoDescripcion = $row[11] ?: '';
	$estadoColor = '';
	if ($row[9]) {
		switch ((int)$row[9]) {
			case 1: $estadoColor = 'badge-secondary'; break; // Pendiente
			case 2: $estadoColor = 'badge-warning'; break;   // Comprando
			case 3: $estadoColor = 'badge-info'; break;      // Comprando Parcial
			case 4: $estadoColor = 'badge-primary'; break;   // Comprando y Entregando
			case 5: $estadoColor = 'badge-success'; break;   // Comprando Total
			case 6: $estadoColor = 'badge-success'; break;   // Entregado Parcial
			case 7: $estadoColor = 'badge-success'; break;   // Entregado
			case 8: $estadoColor = 'badge-danger'; break;    // Cancelado
			default: $estadoColor = 'badge-light';
		}
	}

	$sql2 = "SELECT d.precio,date_format(c.fecha_emision,'%d/%m/%y') fecha_emision FROM compras_detalle d inner join compras c on c.id = d.id_compra WHERE d.id_material = ".$row[5]." order by c.id desc limit 0,1 ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute();
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	if (!empty($data2['fecha_emision'])) {
		$fechaEmision = $data2['fecha_emision'];
	} else {
		$fechaEmision = "";
	}
	if (!empty($data2['precio'])) {
		$precio = "$". number_format($data2['precio'],2);
	} else {
		$precio = "";
	}
	$requerido = $row[2] .' '.$row[4];	
	/*$sql3 = "SELECT disponible FROM stock WHERE id_material = ? ";
	$q3 = $pdo->prepare($sql3);
	$q3->execute([$row[5]]);
	$data3 = $q3->fetch(PDO::FETCH_ASSOC);
	if (empty($data3)) {
		$disponible = "";
	} else {
		$disponible = $data3['disponible'];
	}*/

  $sql3 = "SELECT SUM(saldo) AS disponible FROM ingresos_detalle WHERE id_material = ? ";
	$q3 = $pdo->prepare($sql3);
	$q3->execute([$row[5]]);
	$data3 = $q3->fetch(PDO::FETCH_ASSOC);

  $disponible = $data3['disponible'];
	
	$estadoHtml = '';
	if ($estadoTexto) {
    $badgeDescription="";
		if ($estadoDescripcion) {
		  $badgeDescription = "data-toggle='tooltip' data-placement='top' title='".htmlspecialchars($estadoDescripcion)."'";
		}
    $estadoHtml = "<span class='badge $estadoColor' $badgeDescription>$estadoTexto</span>";
	}
	
	$aConceptos[]=[
    0 => $row[1],
    1 => $requerido,
    2 => $disponible,
    3 => $row[6],
    4 => $row[7],
    5 => "<span style='display: none;'>". $row[8] . "</span>".$row[3],
    6 => $fechaEmision,
    7 => $precio,
    8 => $estadoHtml
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>
