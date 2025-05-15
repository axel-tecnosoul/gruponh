<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE pedidos SET id_estado = ? WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$_POST["idEstado"],$_POST["idPosicion"]]);
echo $_POST["idEstado"];
echo " - ";
echo $_POST["idPosicion"];
$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Estado de Pedido','Pedidos')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();

?>