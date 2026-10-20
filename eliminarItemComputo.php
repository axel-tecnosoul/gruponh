<?php
require("config.php");
/*if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}*/

require 'database.php';
$prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodQuery = $prod ? '?prod=' . $prod : '';
$prodParam = $prod ? '&prod=' . $prod : '';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarComputos.php$prodQuery");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//$sql = "UPDATE computos_detalle set cancelado = 1 where id = ? ";
$sql = "DELETE FROM computos_detalle WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute(array($id));

// Touch: el computo fue modificado (se elimino un item)
if (!empty($_GET['idComputo'])) {
    $qt = $pdo->prepare("UPDATE computos SET fecha_hora_ultima_modificacion = NOW() WHERE id = ?");
    $qt->execute(array((int)$_GET['idComputo']));
}
    
Database::disconnect();
    
header("Location: itemsComputo.php?id=".$_GET['idComputo']."&modo=update&revision=".$_GET['revision'].$prodParam);