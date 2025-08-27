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
$q->execute([$_POST["idEstado"], $_POST["idPosicion"]]);

// Si la OT pasa a "Para aprobar" (2) o "En producción" (3),
// actualizamos también el estado de todas sus posiciones
if (in_array((int)$_POST["idEstado"], [2, 3])) {
    $sql = "UPDATE ordenes_trabajo_detalle SET id_estado_orden_trabajo_posicion = ? WHERE id_orden_trabajo = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST["idEstado"], $_POST["idPosicion"]]);
}

Database::disconnect();

?>