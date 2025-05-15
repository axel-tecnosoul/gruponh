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
  header("Location: listarOrdenesCompraClientes.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modoDebug=0;

if ($modoDebug==1) {
  $pdo->beginTransaction();
  var_dump($_POST);
  var_dump($_FILES);
}

$sql = "SELECT id_occ FROM occ_detalles WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$id_occ = $data['id_occ'];

$sql = "UPDATE occ SET monto = monto - (SELECT subtotal FROM occ_detalles WHERE id = ?) WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id,$id_occ]);

$sql = "DELETE from occ_detalles WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Eliminación de detalle ID #$id de Orden de Compra Clientes','Orden de Compra Clientes','verOrdenCompraCliente.php?id=$id_occ')";
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
  header("Location: nuevaOrdenCompraClienteDetalle.php?id_orden_compra_cliente=".$id_occ);
}
