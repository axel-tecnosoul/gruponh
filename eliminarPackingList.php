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
  header("Location: listarPackingList.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
$sql = "UPDATE packing_lists_revisiones SET id_estado_packing_list = 6 WHERE id = ".$id;
$q = $pdo->prepare($sql);
$q->execute();

$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 1 WHERE id_packing_list_seccion in (SELECT id FROM packing_lists_secciones where id_packing_list_revision = ".$id.") ";
$q = $pdo->prepare($sql);
$q->execute();	
  
 
$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Eliminación de packing list ID #$id','Packing List','')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();
  
header("Location: listarPackingList.php");
