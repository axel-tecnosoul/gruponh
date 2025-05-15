<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarOrdenesTrabajo.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE ordenes_trabajo_detalle SET cant_liberadas = ?, cant_proceso = ?, cant_rechazadas = ?, fecha = now(), id_usuario = ? WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$_POST["liberadas"],$_POST["enProceso"],$_POST["rechazadas"],$_SESSION['user']['id'],$_POST["id_posicion_ot"]]);

$sql = "INSERT INTO `ordenes_trabajo_detalle_log`(`id_ordenes_trabajo_detalle`, `cantidad_liberada`, `cantidad_reproceso`, `cantidad_rechazada`, `motivo`, `fecha`, `id_usuario`) VALUES (?,?,?,?,?,now(),?)";
$q = $pdo->prepare($sql);
$q->execute([$_POST["id_posicion_ot"],$_POST["liberadas"],$_POST["enProceso"],$_POST["rechazadas"],$_POST["motivo"],$_SESSION['user']['id']]);

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Cantidades en Orden de Trabajo','Orden de Trabajo')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

$sql = "SELECT id_orden_trabajo from ordenes_trabajo_detalle where id = ?";
$q = $pdo->prepare($sql);
$q->execute([$_POST["id_posicion_ot"]]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$idOT = $data['id_orden_trabajo'];

$sql = "SELECT sum(d.cantidad) total, sum(d.cant_liberadas) lib from ordenes_trabajo_detalle d where d.id_orden_trabajo = ?";
$q = $pdo->prepare($sql);
$q->execute([$idOT]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if ($data['lib'] >= $data['total']) {
	$sql = "update ordenes_trabajo set id_estado_orden_trabajo = 4 where id = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$idOT]);	
}

Database::disconnect();
header("Location: listarOrdenesTrabajo.php");
