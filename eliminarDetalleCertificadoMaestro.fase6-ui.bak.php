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
  header("Location: listarCertificadosMaestros.php");
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

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Eliminación de detalle ID #$id de Certificado Maestro','Certificado Maestro','')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

if ($modoDebug==1) {
  $pdo->rollBack();
  die();
} else {
  Database::disconnect();
  header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=".$id_certificado_maestro);
}
