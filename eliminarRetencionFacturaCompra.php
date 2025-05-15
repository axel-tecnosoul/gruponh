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
  header("Location: listarFacturasCompra.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "DELETE FROM facturas_compra_retenciones WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

$sql = "update `facturas_compra` set  `otros` = `otros` - ?, `total` = `total` - ? where id = ?";
$q = $pdo->prepare($sql);		   
$q->execute([$_GET['monto'], $_GET['monto'], $id]);

Database::disconnect();
  
header("Location: nuevaRetencionFacturaCompra.php?id=".$_GET["fc"]);
