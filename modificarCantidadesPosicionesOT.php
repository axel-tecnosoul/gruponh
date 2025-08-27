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
$liberadas = $_POST['liberadas'] ?: 0;
$reproceso = $_POST['reproceso'] ?: 0;
$rechazadas = $_POST['rechazadas'] ?: 0;

// Obtener totales actuales de la posición
$sql = "SELECT id, cantidad, cant_liberadas, cant_reproceso, cant_rechazadas FROM ordenes_trabajo_detalle WHERE id_posicion = ? AND id_orden_trabajo = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_posicion, $id_orden_trabajo]);
$data = $q->fetch(PDO::FETCH_ASSOC);

if($modoDebug==1){
  var_dump($data);
}

$idDetalle = $data['id'];

$n_liberadas  = $data['cant_liberadas'] ?: 0;
$n_proceso    = $data['cant_reproceso'] ?: 0;
$n_rechazadas = $data['cant_rechazadas'] ?: 0;

if($modoDebug==1){
  var_dump($n_liberadas, $n_proceso, $n_rechazadas);
  var_dump($liberadas, $reproceso, $rechazadas);
}

$n_liberadas += $liberadas;
$n_proceso += $reproceso;
$n_rechazadas += $rechazadas;

if(($n_liberadas + $n_rechazadas) > $data['cantidad']){
  Database::disconnect();
  header("Location: listarOrdenesTrabajo.php");
  exit;
}

// Actualizar totales
$sql = "UPDATE ordenes_trabajo_detalle SET cant_liberadas = ?, cant_reproceso = ?, cant_rechazadas = ?, fecha = ?, id_usuario = ? WHERE id = ?";
$q = $pdo->prepare($sql);
$params = [$n_liberadas, $n_proceso, $n_rechazadas, $fecha, $_SESSION['user']['id'], $idDetalle];
$q->execute($params);

if($modoDebug==1){
  echo debugQuery($pdo, $sql, $params) . "<br><br>Afe: " . $q->rowCount() . "<br><br>";
}

// Registrar movimiento en el log
$sql = "INSERT INTO ordenes_trabajo_detalle_log (id_ordenes_trabajo_detalle, cantidad_liberada, cantidad_reproceso, cantidad_rechazada, motivo, fecha, id_usuario) VALUES (?,?,?,?,?,?,?)";
$q = $pdo->prepare($sql);
$params = [$idDetalle, $liberadas, $reproceso, $rechazadas, $_POST["motivo"], $fecha, $_SESSION['user']['id']];
$q->execute($params);
if($modoDebug==1){
  echo debugQuery($pdo, $sql, $params) . "<br><br>Afe: " . $q->rowCount() . "<br><br>";
}

// Auditoría general
$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Cantidades en Orden de Trabajo','Orden de Trabajo')";
$q = $pdo->prepare($sql);
$params = [$_SESSION['user']['id']];
$q->execute($params);
if($modoDebug==1){
  echo debugQuery($pdo, $sql, $params) . "<br><br>Afe: " . $q->rowCount() . "<br><br>";
}

$sql = "SELECT SUM(d.cantidad) total, SUM(d.cant_liberadas) lib, SUM(d.cant_rechazadas) rech FROM ordenes_trabajo_detalle d where d.id_orden_trabajo = ?";
$q = $pdo->prepare($sql);
$params = [$id_orden_trabajo];
$q->execute($params);
$data = $q->fetch(PDO::FETCH_ASSOC);
if($modoDebug==1){
  echo debugQuery($pdo, $sql, $params) . "<br><br>Afe: " . $q->rowCount() . "<br><br>";
  var_dump($data);
}
if (($data['lib'] + $data['rech']) >= $data['total']) {
  $sql = "UPDATE ordenes_trabajo set id_estado_orden_trabajo = 4 where id = ?";
  $q = $pdo->prepare($sql);
  $params = [$id_orden_trabajo];
  $q->execute($params);

  if($modoDebug==1){
    echo debugQuery($pdo, $sql, $params) . "<br><br>Afe: " . $q->rowCount() . "<br><br>";
  }
}

if($modoDebug==1){
  echo "DEBUG - ROLLBACK";
  $pdo->rollBack();
  die();
}

$pdo->commit();

Database::disconnect();
header("Location: listarOrdenesTrabajo.php");

