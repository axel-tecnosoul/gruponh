<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modoDebug = 1;
//start a pdf transaction
$pdo->beginTransaction();

if($modoDebug==1){
  var_dump($_POST);
}

$id_posicion = $_POST["id_posicion_ot"];
$id_orden_trabajo = $_POST["id_orden_trabajo"];
$fecha = isset($_POST['fecha']) ? date('Y-m-d H:i:s', strtotime($_POST['fecha'])) : date('Y-m-d H:i:s');

// Obtener totales actuales de la posición
$sql = "SELECT id, cantidad, cant_liberadas, cant_proceso, cant_rechazadas FROM ordenes_trabajo_detalle WHERE id_posicion = ? AND id_orden_trabajo = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_posicion, $id_orden_trabajo]);
$data = $q->fetch(PDO::FETCH_ASSOC);

if($modoDebug==1){
  var_dump($data);
}

$idDetalle = $data['id'];

$n_liberadas  = $data['cant_liberadas'] || 0;
$n_proceso    = $data['cant_proceso'] || 0;
$n_rechazadas = $data['cant_rechazadas'] || 0;

$n_liberadas += $_POST['liberadas'];
$n_proceso += $_POST['enProceso'];
$n_rechazadas += $_POST['rechazadas'];

if(($n_liberadas + $n_rechazadas) > $data['cantidad']){
  Database::disconnect();
  header("Location: listarOrdenesTrabajo.php");
  exit;
}

// Actualizar totales
$sql = "UPDATE ordenes_trabajo_detalle SET cant_liberadas = ?, cant_proceso = ?, cant_rechazadas = ?, fecha = ?, id_usuario = ? WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$n_liberadas, $n_proceso, $n_rechazadas, $fecha, $_SESSION['user']['id'], $idDetalle]);

// Si la suma de liberadas y rechazadas completa la cantidad, marcar la posición como Terminada
if(($n_liberadas + $n_rechazadas) == $data['cantidad']){
  $sql = "UPDATE ordenes_trabajo_detalle SET id_estado_orden_trabajo_posicion = 4 WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$idDetalle]);
}

// Registrar movimiento en el log
$sql = "INSERT INTO ordenes_trabajo_detalle_log(id_ordenes_trabajo_detalle, cantidad_liberada, cantidad_reproceso, cantidad_rechazada, motivo, fecha, id_usuario) VALUES (?,?,?,?,?,?,?)";
$q = $pdo->prepare($sql);
$q->execute([$idDetalle, $_POST["liberadas"], $_POST["enProceso"], $_POST["rechazadas"], $_POST["motivo"], $fecha, $_SESSION['user']['id']]);

// Auditoría general
$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Cantidades en Orden de Trabajo','Orden de Trabajo')";
$q = $pdo->prepare($sql);
$q->execute([$_SESSION['user']['id']]);

// Si todas las posiciones de la OT están terminadas, actualizar estado de la OT
$sql = "SELECT COUNT(*) total, SUM(CASE WHEN id_estado_orden_trabajo_posicion = 4 THEN 1 ELSE 0 END) terminadas FROM ordenes_trabajo_detalle WHERE id_orden_trabajo = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_orden_trabajo]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if ($data['total'] > 0 && $data['total'] == $data['terminadas']) {
  $sql = "UPDATE ordenes_trabajo SET id_estado_orden_trabajo = 4 WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_orden_trabajo]);
}

if($modoDebug==1){
  $pdo->rollBack();
  die();
}

$pdo->commit();

Database::disconnect();
header("Location: listarOrdenesTrabajo.php");

