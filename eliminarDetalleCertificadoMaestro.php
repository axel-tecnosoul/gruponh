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

$id_lote = null;
if (!empty($_GET['id_lote'])) {
  $id_lote = trim((string) $_GET['id_lote']);
}

if (null == $id && $id_lote === null) {
  header("Location: listarCertificadosMaestros.php");
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modoDebug=0;

if ($modoDebug==1) {
  $pdo->beginTransaction();
  var_dump($_POST);
  var_dump($_FILES);
}

$column_names = [
  1 => "monto_acumulado_avances",
  2 => "monto_acumulado_anticipos",
  3 => "monto_acumulado_desacopios",
  4 => "monto_acumulado_descuentos",
  5 => "monto_acumulado_ajustes",
];

$detalle_accion = '';

if ($id_lote !== null) {
  $sql = "SELECT id_certificado_maestro, COALESCE(SUM(subtotal),0) AS subtotal_lote, COUNT(*) AS cantidad_filas FROM certificados_maestros_detalles WHERE lote_aperturado = ? GROUP BY id_certificado_maestro";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lote]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  if (empty($data) || (int) ($data['cantidad_filas'] ?? 0) <= 0) {
    Database::disconnect();
    header("Location: listarCertificadosMaestros.php");
    exit;
  }

  $id_certificado_maestro = (int) $data['id_certificado_maestro'];
  $subtotal_lote = (float) $data['subtotal_lote'];

  $pdo->beginTransaction();

  $sql = "UPDATE certificados_maestros SET monto_acumulado_avances = monto_acumulado_avances - ? WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$subtotal_lote, $id_certificado_maestro]);

  $sql = "DELETE FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro = ? AND lote_aperturado = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_maestro, $id_lote]);

  $sql = "DELETE FROM certificados_maestros_detalles WHERE lote_aperturado = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lote]);

  $detalle_accion = "Eliminación de lote #$id_lote de Certificado Maestro";
} else {
  $sql = "SELECT id_certificado_maestro,id_tipo_item_certificado,subtotal FROM certificados_maestros_detalles WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_certificado_maestro = $data['id_certificado_maestro'];
  $id_tipo_item_old=$data["id_tipo_item_certificado"];
  $subtotal_old=$data["subtotal"];

  //obtenemos el nombre de la columna del tipo de detalle en la tabla certificado_maestro para restar el subtotal
  $column_name_old = $column_names[$id_tipo_item_old];
  //restamos el viejo subtotal en la columna segun el viejo tipo de detalle
  $sql = "UPDATE certificados_maestros SET $column_name_old = $column_name_old - ? WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$subtotal_old,$id_certificado_maestro]);

  $sql = "DELETE from certificados_maestros_detalles WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);

  $detalle_accion = "Eliminación de detalle ID #$id de Certificado Maestro";
}

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,?,'Certificado Maestro','')";
$q = $pdo->prepare($sql);
$q->execute([$_SESSION['user']['id'], $detalle_accion]);

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

if ($modoDebug==1) {
  $pdo->rollBack();
  die();
} else {
  if ($modoDebug != 1 && $pdo->inTransaction()) {
    $pdo->commit();
  }
  Database::disconnect();
  header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=".$id_certificado_maestro);
}
