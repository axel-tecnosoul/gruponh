<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE listas_corte_revisiones SET id_estado_lista_corte = ".$_POST["idEstado"]." WHERE id = ".$_POST["idPosicion"];
$q = $pdo->prepare($sql);
$q->execute();

if ($_POST["idEstado"] == 1) {
	$sql = "UPDATE listas_corte_conjuntos SET id_estado_lista_corte_conjuntos = 1 WHERE id_lista_corte = ".$_POST["idPosicion"];
	$q = $pdo->prepare($sql);
	$q->execute();	
} else if ($_POST["idEstado"] == 2) {
	$sql = "UPDATE listas_corte_conjuntos SET id_estado_lista_corte_conjuntos = 1 WHERE id_lista_corte = ".$_POST["idPosicion"];
	$q = $pdo->prepare($sql);
	$q->execute();	
} else if ($_POST["idEstado"] == 3) {
	$sql = "UPDATE listas_corte_conjuntos SET id_estado_lista_corte_conjuntos = 1 WHERE id_lista_corte = ".$_POST["idPosicion"];
	$q = $pdo->prepare($sql);
	$q->execute();		
} else if ($_POST["idEstado"] == 4) {
	$sql = "UPDATE listas_corte_conjuntos SET id_estado_lista_corte_conjuntos = 4 WHERE id_lista_corte = ".$_POST["idPosicion"];
	$q = $pdo->prepare($sql);
	$q->execute();			
	
	$sql = "UPDATE tareas SET fecha_fin_real = now() WHERE id = (select lc.id_tarea from listas_corte_revisiones lcr inner join listas_corte lc on lc.id = lcr.id_lista_corte where lcr.id = ?)";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST["idPosicion"]]);
	
} else if ($_POST["idEstado"] == 5) {
	$sql = "UPDATE listas_corte_conjuntos SET id_estado_lista_corte_conjuntos = 1 WHERE id_lista_corte = ".$_POST["idPosicion"];
	$q = $pdo->prepare($sql);
	$q->execute();				
}

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Estado de Lista de Corte','Lista de Corte')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();

?>