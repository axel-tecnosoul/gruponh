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
    
$idPedido = null;
if (!empty($_GET['idPedido'])) {
  $idPedido = $_REQUEST['idPedido'];
}
    
if (null == $id || null == $idPedido) {
  header("Location: listarPedidos.php");
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  $sql_log = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Se ha eliminado un item (ID: $id) del pedido','Pedidos','verPedido.php?id=$idPedido')";
  $q_log = $pdo->prepare($sql_log);
  $q_log->execute(array($_SESSION['user']['id']));

  $sql = "DELETE FROM pedidos_detalle WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute(array($id));
  
  Database::disconnect();
  
  header("Location: itemsPedidoDirecto.php?id=" . $idPedido);
}
?>