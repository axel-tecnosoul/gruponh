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

if (!$fila) {
  Database::disconnect();
  die("El Certificado de Avance no existe.");
}

$sqlUlt = "SELECT COUNT(*) FROM certificados_avances_cabecera c
           WHERE c.id_certificado_maestro = ?
             AND c.nro_certificado = (SELECT nro_certificado FROM certificados_avances_cabecera WHERE id = ?)
             AND c.nro_revision > (SELECT nro_revision FROM certificados_avances_cabecera WHERE id = ?)";
$qUlt = $pdo->prepare($sqlUlt);
$qUlt->execute([$fila["id_certificado_maestro"], $id, $id]);
if ((int) $qUlt->fetchColumn() > 0) {
  Database::disconnect();
  die("Solo la ultima revision del certificado puede eliminarse.");
}

$sqlAprob = "SELECT aprobado_cliente FROM certificados_avances_cabecera WHERE id = ?";
$qAprob = $pdo->prepare($sqlAprob);
$qAprob->execute([$id]);
if ((int) $qAprob->fetchColumn() === 1) {
  Database::disconnect();
  die("El certificado esta aprobado y no puede ser eliminado.");
}

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
