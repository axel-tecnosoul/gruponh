<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE ordenes_trabajo_detalle SET id_estado_orden_trabajo_posicion = ? WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$_POST["idEstado"],$_POST["idPosicion"]]);

$afe = $q->rowCount();

echo $afe;

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Estado de Posicion en Orden de Trabajo','Orden de Trabajo')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();

?>