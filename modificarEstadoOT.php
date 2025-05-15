<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE ordenes_trabajo SET id_estado_orden_trabajo = ? WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$_POST["idEstado"],$_POST["idPosicion"]]);

Database::disconnect();

?>