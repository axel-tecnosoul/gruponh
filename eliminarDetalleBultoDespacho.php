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
  header("Location: listarDespachos.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT id_bulto FROM bultos_detalle WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$id_bulto = $data['id_bulto'];

$sql = "DELETE from bultos_detalle WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Eliminación de detalle de bulto ID #$id en despacho','Despachos','')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();
  
header("Location: nuevoBultoDetalleDespacho.php?id_bulto=$id_bulto");
