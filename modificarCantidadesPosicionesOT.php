<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$idDetalle = $_POST["id_posicion_ot"];
$idOT = $_POST["id_orden_trabajo"];
$fecha = isset($_POST['fecha']) ? date('Y-m-d H:i:s', strtotime($_POST['fecha'])) : date('Y-m-d H:i:s');

// Obtener totales actuales de la posición
$sql = "SELECT cantidad, cant_liberadas, cant_proceso, cant_rechazadas FROM ordenes_trabajo_detalle WHERE id = ? AND id_orden_trabajo = ?";
$q = $pdo->prepare($sql);
$q->execute([$idDetalle, $idOT]);
$data = $q->fetch(PDO::FETCH_ASSOC);

$n_liberadas  = $data['cant_liberadas'] + $_POST['liberadas'];
$n_proceso    = $data['cant_proceso'] + $_POST['enProceso'];
$n_rechazadas = $data['cant_rechazadas'] + $_POST['rechazadas'];

if(($n_liberadas + $n_rechazadas) > $data['cantidad']){
  Database::disconnect();
  header("Location: listarOrdenesTrabajo.php");
  exit;
}

// Actualizar totales
$sql = "UPDATE ordenes_trabajo_detalle SET cant_liberadas = ?, cant_proceso = ?, cant_rechazadas = ?, fecha = ?, id_usuario = ? WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$n_liberadas, $n_proceso, $n_rechazadas, $fecha, $_SESSION['user']['id'], $idDetalle]);

// Registrar movimiento en el log
$sql = "INSERT INTO ordenes_trabajo_detalle_log(id_ordenes_trabajo_detalle, cantidad_liberada, cantidad_reproceso, cantidad_rechazada, motivo, fecha, id_usuario) VALUES (?,?,?,?,?,?,?)";
$q = $pdo->prepare($sql);
$q->execute([$idDetalle, $_POST["liberadas"], $_POST["enProceso"], $_POST["rechazadas"], $_POST["motivo"], $fecha, $_SESSION['user']['id']]);

// Auditoría general
$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Cantidades en Orden de Trabajo','Orden de Trabajo')";
$q = $pdo->prepare($sql);
$q->execute([$_SESSION['user']['id']]);

$sql = "SELECT sum(d.cantidad) total, sum(d.cant_liberadas) lib, sum(d.cant_rechazadas) rech FROM ordenes_trabajo_detalle d where d.id_orden_trabajo = ?";
$q = $pdo->prepare($sql);
$q->execute([$idOT]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (($data['lib'] + $data['rech']) >= $data['total']) {
  $sql = "update ordenes_trabajo set id_estado_orden_trabajo = 4 where id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$idOT]);
}

Database::disconnect();
header("Location: listarOrdenesTrabajo.php");

