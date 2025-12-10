<?php
require("config.php");
require 'database.php';
require_once 'funciones.php';

$id_pedido = $_POST['id_pedido'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT pd.id AS id_pedido_detalle, m.concepto, pd.cantidad, date_format(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad_formatted, u.unidad_medida, pd.id_material, pd.reservado, pd.comprado, date_format(pd.fecha_necesidad,'%y%m%d') AS fecha_necesidad, pd.id_estado, epd.estado, epd.descripcion FROM pedidos_detalle pd inner join materiales m on m.id = pd.id_material inner join unidades_medida u on u.id = pd.id_unidad_medida LEFT JOIN estados_pedidos_detalle epd ON epd.id = pd.id_estado WHERE pd.id_pedido = ".$id_pedido;//." AND d.cancelado = 0";
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
  $id_pedido_detalle = $row["id_pedido_detalle"];
  $id_estado = $row["id_estado"];
  $estado = $row["estado"];
  $descripcion_estado = $row["descripcion"];
  $id_material = $row["id_material"];

	// Verificar y asignar estado al principio del bucle
	if ($id_estado === null || empty($estado)) { // No tiene id_estado válido o nombre de estado
		// Calcular y actualizar el estado
		$nuevoEstadoId = calcularEstadoPedidoDetalle($pdo, $id_pedido_detalle);
		if ($nuevoEstadoId) {
			actualizarEstadoPedidoDetalle($pdo, $id_pedido_detalle);
			// Actualizar los valores del row para usar el nuevo estado
			$id_estado = $nuevoEstadoId;
			// Obtener el nombre y descripción del estado calculado
			$sqlEstado = "SELECT estado, descripcion FROM estados_pedidos_detalle WHERE id = ?";
			$qEstado = $pdo->prepare($sqlEstado);
			$qEstado->execute([$nuevoEstadoId]);
			$dataEstado = $qEstado->fetch(PDO::FETCH_ASSOC);
			if ($dataEstado) {
				$estado = $dataEstado['estado'];
				$descripcion_estado = $dataEstado['descripcion'];
			}
		}
	}
	
	// Ya tenemos id_estado y nombre del estado válidos
	$estadoTexto = $estado ?: '';
	$descripcion_estado = $descripcion_estado ?: '';
	$estadoColor = '';
	if ($id_estado) {
		switch ((int)$id_estado) {
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

	$sql2 = "SELECT d.precio, date_format(c.fecha_emision,'%d/%m/%y') fecha_emision FROM compras_detalle d inner join compras c on c.id = d.id_compra WHERE d.id_material = $id_material order by c.id desc limit 0,1 ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute();
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);

  $fechaEmision = "";
	if (!empty($data2['fecha_emision'])) {
		$fechaEmision = $data2['fecha_emision'];
	}

  $precio = "";
	if (!empty($data2['precio'])) {
		$precio = "$". number_format($data2['precio'],2);
	}

	$requerido = $row["cantidad"] .' '.$row["unidad_medida"];	

  $sql3 = "SELECT SUM(saldo) AS disponible FROM ingresos_detalle WHERE id_material = ? ";
	$q3 = $pdo->prepare($sql3);
	$q3->execute([$id_material]);
	$data3 = $q3->fetch(PDO::FETCH_ASSOC);

  $disponible = $data3['disponible'];
	
	$estadoHtml = '';
	if ($estadoTexto) {
    $badgeDescription="";
		if ($descripcion_estado) {
		  $badgeDescription = "data-toggle='tooltip' data-placement='top' title='".htmlspecialchars($descripcion_estado)."'";
		}
    $estadoHtml = "<span class='badge $estadoColor' $badgeDescription>$estadoTexto</span>";
	}
	
	$aConceptos[]=[
    0 => $row["concepto"],
    1 => $requerido,
    2 => $disponible,
    3 => $row["reservado"],
    4 => $row["comprado"],
    5 => "<span style='display: none;'>". $row["fecha_necesidad"] . "</span>".$row["fecha_necesidad_formatted"],
    6 => $fechaEmision,
    7 => $precio,
    8 => $estadoHtml
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>
