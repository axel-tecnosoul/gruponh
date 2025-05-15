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

$sql = "SELECT id_packing_list_seccion FROM packing_lists_componentes WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$id_packing_list_seccion = $data['id_packing_list_seccion'];

$sql = "DELETE from packing_lists_componentes WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Eliminación de componente ID #$id de sección en packing list','Packing List','')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();
  
//header("Location: listarPackingList.php");
header("Location: nuevaPackingListComponentes.php?id_packing_list_seccion=".$id_packing_list_seccion);
