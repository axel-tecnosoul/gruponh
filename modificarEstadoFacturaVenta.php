<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE facturas_venta SET id_estado = ".$_POST["idEstado"]." WHERE id = ".$_POST["idPosicion"];
$q = $pdo->prepare($sql);
$q->execute();

$idFactura = $_POST["idPosicion"];

$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificacion de Estado Factura de Venta ID #$idFactura','Facturas de Venta','')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();

?>