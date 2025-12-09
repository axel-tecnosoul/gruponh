<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
require 'database.php';
require_once('funciones.php');

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarCompras.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Actualizar tanto el campo aprobado (legacy) como el estado de compra
$sql = "UPDATE compras SET aprobado = 1, id_estado_compra = 3 WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

// Actualizar estados de todos los pedidos_detalle afectados por la aprobación de la compra
$sqlAllItems = "SELECT DISTINCT pd.id FROM pedidos_detalle pd INNER JOIN compras_detalle cd ON pd.id_pedido = (SELECT id_pedido FROM compras WHERE id = ?) AND pd.id_material = cd.id_material INNER JOIN compras c ON c.id = cd.id_compra WHERE cd.id_compra = ?";
$qAllItems = $pdo->prepare($sqlAllItems);
$qAllItems->execute([$id, $id]);

while ($item = $qAllItems->fetch(PDO::FETCH_ASSOC)) {
  actualizarEstadoPedidoDetalle($pdo, $item['id']);
}

$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Aprobación de compra','Compras','verCompra.php?id=$id')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

// Enviar notificaciones usando la función crearNotificacion
$detalleNotificacion = "ID Compra: #".$id;
$asuntoEmail = "Módulo Compras - Aprobación de Compra";
$cuerpoEmail = "La OC #".$id." ha sido aprobada en el sistema";

crearNotificacion($pdo, 2, $id, $detalleNotificacion, $asuntoEmail, $cuerpoEmail);

Database::disconnect();

header("Location: listarCompras.php");
