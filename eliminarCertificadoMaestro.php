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

$sql = "DELETE FROM certificados_maestros_detalles WHERE id_certificado_maestro = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

$sql = "DELETE FROM certificados_maestros WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
  
$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Eliminación de Certificado Maestro ID #$id','Certificados Maestros','')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();
  
header("Location: listarCertificadosMaestros.php");
