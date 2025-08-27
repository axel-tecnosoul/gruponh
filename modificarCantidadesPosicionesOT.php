<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modoDebug = 0;
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

$sql = "SELECT id_estado_orden_trabajo FROM ordenes_trabajo WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_orden_trabajo]);
$estadoOT = $q->fetchColumn();
if($estadoOT != 3){
  $pdo->rollBack();
  Database::disconnect();
  header("Location: listarOrdenesTrabajo.php");
  exit;
}

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

// Si la suma de liberadas y rechazadas completa la cantidad, marcar la posición como Terminada
if(($n_liberadas + $n_rechazadas) == $data['cantidad']){
  $sql = "UPDATE ordenes_trabajo_detalle SET id_estado_orden_trabajo_posicion = 4 WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$idDetalle]);
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

// Si todas las posiciones de la OT están terminadas, actualizar estado de la OT
$sql = "SELECT COUNT(*) total, SUM(CASE WHEN id_estado_orden_trabajo_posicion = 4 THEN 1 ELSE 0 END) terminadas FROM ordenes_trabajo_detalle WHERE id_orden_trabajo = ?";
$q = $pdo->prepare($sql);
$params = [$id_orden_trabajo];
$q->execute($params);
$data = $q->fetch(PDO::FETCH_ASSOC);

if($modoDebug==1){
  echo debugQuery($pdo, $sql, $params) . "<br><br>Afe: " . $q->rowCount() . "<br><br>";
  var_dump($data);
}

if ($data['total'] > 0 && $data['total'] == $data['terminadas']) {
  $sql = "UPDATE ordenes_trabajo SET id_estado_orden_trabajo = 4 WHERE id = ?";
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

