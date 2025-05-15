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

$sql = "SELECT id_lista_corte FROM listas_corte_conjuntos WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

$sql = "DELETE from listas_corte_conjuntos WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Eliminación de conjunto ID #$id en lista de corte','Listas de Corte','')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();
    
header("Location: nuevaListaCorteConjuntos.php?id_lista_corte_revision=".$data["id_lista_corte"]);
