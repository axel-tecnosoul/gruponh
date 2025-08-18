<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php'; 

$id_lista_corte = null;
if (!empty($_GET['id_lista_corte'])) {
  $id_lista_corte = $_GET['id_lista_corte'];
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE listas_corte SET id_estado_lista_corte = 2 WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte]);

$sql = "SELECT nombre, numero, id_proyecto FROM listas_corte WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte]);
$data = $q->fetch(PDO::FETCH_ASSOC);

$descripcionProyecto = getDescripcionProyecto($pdo, $data["id_proyecto"]);
$descripcion = "lista de corte #".$data["numero"]." - ".$data["nombre"]." ".$descripcionProyecto;

/*$descProyecto = getDescripcionProyecto($pdo, $id_proyecto);
$descripcion_lista_corte = " N° ".$numero_lc. "Rev. N° ".$nro_revision.$descProyecto;*/

$idTipoNotificacion=5;
$idEntidad=$id_lista_corte;
$detalleNotificacion="ID Computo: #".$id_lista_corte;
$asuntoEmail="Producción - Aprobación de ".$descripcion;
$cuerpoEmail="La .$descripcion está lista para aprobación.";
crearNotificacion($pdo,$idTipoNotificacion,$idEntidad,$detalleNotificacion,$asuntoEmail,$cuerpoEmail);

Database::disconnect();
  
header("Location: listarListasCorte.php");
