<?php
ob_start();

require("config.php");
require 'database.php';
require_once 'funciones.php';

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);

$id_pedido = $_POST['id_pedido'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT 
          pd.id AS id_pedido_detalle, 
          m.concepto, 
          pd.cantidad, 
          u.unidad_medida, 
          pd.id_material, 
          pd.reservado, 
          pd.comprado, -- Este dato a veces falla, lo recalcularemos abajo
          date_format(pd.fecha_necesidad,'%y%m%d') AS fecha_necesidad_iso,
          date_format(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad_formatted, 
          pd.id_estado, 
          epd.estado, 
          epd.descripcion 
        FROM pedidos_detalle pd 
        INNER JOIN materiales m ON m.id = pd.id_material 
        INNER JOIN unidades_medida u ON u.id = pd.id_unidad_medida 
        LEFT JOIN estados_pedidos_detalle epd ON epd.id = pd.id_estado 
        WHERE pd.id_pedido = ?";

$q = $pdo->prepare($sql);
$q->execute([$id_pedido]);

$aConceptos = [];

while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $id_pedido_detalle = $row["id_pedido_detalle"];
    $id_material = $row["id_material"];
    $id_estado = $row["id_estado"];
    $estado = $row["estado"];
    $descripcion_estado = $row["descripcion"];

    if ($id_estado === null || empty($estado)) { 
        $nuevoEstadoId = calcularEstadoPedidoDetalle($pdo, $id_pedido_detalle);
        if ($nuevoEstadoId) {
            actualizarEstadoPedidoDetalle($pdo, $id_pedido_detalle);
            $id_estado = $nuevoEstadoId;
            // Recargar info del estado
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
    
    $estadoTexto = $estado ?: '';
    $descripcion_estado = $descripcion_estado ?: '';
    $estadoColor = 'badge-light';
    
    if ($id_estado) {
        switch ((int)$id_estado) {
            case 1: $estadoColor = 'badge-secondary'; break;
            case 2: $estadoColor = 'badge-warning'; break;
            case 3: $estadoColor = 'badge-info'; break;
            case 4: $estadoColor = 'badge-primary'; break;
            case 5: $estadoColor = 'badge-success'; break;
            case 6: $estadoColor = 'badge-success'; break;
            case 7: $estadoColor = 'badge-success'; break;
            case 8: $estadoColor = 'badge-danger'; break;
            default: $estadoColor = 'badge-light';
        }
    }

    $estadoHtml = '';
    if ($estadoTexto) {
        $badgeDescription = "";
        if ($descripcion_estado) {
            $badgeDescription = "data-toggle='tooltip' data-placement='top' title='".htmlspecialchars($descripcion_estado)."'";
        }
        $estadoHtml = "<span class='badge $estadoColor' $badgeDescription>$estadoTexto</span>";
    }

    $sql2 = "SELECT d.precio, date_format(c.fecha_emision,'%d/%m/%Y') as fecha_emision 
             FROM compras_detalle d 
             INNER JOIN compras c ON c.id = d.id_compra 
             WHERE d.id_material = ? 
             ORDER BY c.fecha_emision DESC LIMIT 1";
    $q2 = $pdo->prepare($sql2);
    $q2->execute([$id_material]);
    $data2 = $q2->fetch(PDO::FETCH_ASSOC);
    $fechaEmision = $data2['fecha_emision'] ?? "";
    $precio = !empty($data2['precio']) ? "$". number_format($data2['precio'], 2, ',', '.') : "";

    $sqlComprado = "SELECT SUM(cd.cantidad) as total_comprado 
                    FROM compras_detalle cd 
                    INNER JOIN compras c ON c.id = cd.id_compra 
                    WHERE c.id_pedido = ? AND cd.id_material = ? AND c.id_estado_compra != 6";
    $qComprado = $pdo->prepare($sqlComprado);
    $qComprado->execute([$id_pedido, $id_material]);
    $dataComprado = $qComprado->fetch(PDO::FETCH_ASSOC);
    $compradoDisplay = $dataComprado['total_comprado'] ? (float)$dataComprado['total_comprado'] : 0;

    $cantidadEntregada = 0;
    try {
        $sqlEntregado = "SELECT SUM(id.cantidad) as total_entregado
                         FROM ingresos_detalle id
                         INNER JOIN ingresos i ON i.id = id.id_ingreso
                         INNER JOIN compras c ON c.id = i.id_oc
                         WHERE c.id_pedido = ? AND id.id_material = ?";
        $qEntregado = $pdo->prepare($sqlEntregado);
        $qEntregado->execute([$id_pedido, $id_material]);
        $dataEntregado = $qEntregado->fetch(PDO::FETCH_ASSOC);
        $cantidadEntregada = $dataEntregado['total_entregado'] ? (float)$dataEntregado['total_entregado'] : 0;
    } catch (PDOException $e) {
        // Si la tabla ingresos o ingresos_detalle no existe, simplemente dejamos cantidadEntregada en 0
        $cantidadEntregada = 0;
    }

    $acciones = '<a href="verPreciosMaterial.php?id='.$id_material.'&origen=pedidos" target="_blank" title="Ver Histórico de Precios"><img src="img/dolar.png" width="24" height="25" border="0" alt="Histórico"></a>';

    $requerido = (float)$row["cantidad"] .' '.$row["unidad_medida"];
    $reservado = (float)$row["reservado"];

    $aConceptos[] = [
        0 => $row["concepto"],
        1 => $requerido,
        2 => $compradoDisplay,
        3 => $cantidadEntregada,
        4 => $reservado,
        5 => "<span style='display: none;'>". $row["fecha_necesidad_iso"] . "</span>" . $row["fecha_necesidad_formatted"],
        6 => $fechaEmision,
        7 => $precio,
        8 => $estadoHtml,
        9 => $acciones
    ];
}

Database::disconnect();

ob_end_clean();
header('Content-Type: application/json');
echo json_encode($aConceptos);
?>
