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

$sqlZon = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = $id ";
$q = $pdo->prepare($sqlZon);
$q->execute();
$fila = $q->fetch(PDO::FETCH_ASSOC);

$sql = "DELETE FROM certificados_avances_detalle WHERE id_certificado_avance = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

$sql = "DELETE FROM certificados_avances_cabecera WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
  
$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Eliminación de Certificado de Avance ID #$id','Certificado de Avance','')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();
  
header("Location: listarCertificadosAvances.php?id_certificado_maestro=".$fila["id_certificado_maestro"]);
