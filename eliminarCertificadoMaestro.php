<?php
require("config.php");
if (function_exists('esOperacionesSinEconomico') && esOperacionesSinEconomico()) {
  http_response_code(403);
  die("Perfil Operaciones no puede eliminar Certificados Maestros.");
}
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
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT aprobado_cliente FROM certificados_maestros WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$aprobado = $q->fetchColumn();
if ($aprobado === false) {
  Database::disconnect();
  die("El Certificado Maestro no existe.");
}
if ((int) $aprobado === 1) {
  Database::disconnect();
  die("El Certificado Maestro está aprobado y no puede eliminarse.");
}

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
