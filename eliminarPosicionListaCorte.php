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
    header("Location: listarListasCorte.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modoDebug=0;

if ($modoDebug==1) {
  $pdo->beginTransaction();
  var_dump($_POST);
  var_dump($_FILES);
}

$sql = "DELETE from lista_corte_procesos WHERE id_lista_corte_posicion = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "SELECT id_lista_corte_conjunto, id_material, cantidad FROM lista_corte_posiciones WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$idConjunto = $data['id_lista_corte_conjunto'];
$idMaterial = $data['id_material'];
$cantidad = $data['cantidad'];

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "UPDATE listas_corte_conjuntos set peso = peso - (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
$q = $pdo->prepare($sql);
$q->execute([$cantidad,$idMaterial,$idConjunto]);

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "DELETE from lista_corte_posiciones WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$idComputoDetalle = 0;
$sql = "SELECT cd.id idComputoDetalle from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte_revisiones lcr on lcr.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lcr.id where lcc.id = ? and m.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idConjunto,$idMaterial]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$idComputoDetalle = $data['idComputoDetalle'];

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "UPDATE `computos_detalle` set comprado = comprado - ?, reservado = reservado + ?  where id = ?";
$q = $pdo->prepare($sql);
$q->execute([$cantidad,$cantidad,$idComputoDetalle]);

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Eliminación de posición ID #$id de conjunto en lista de corte','Listas de Corte','')";
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
  header("Location: nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$idConjunto);
}
