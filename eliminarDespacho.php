<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
  header("Location: listarDespachos.php");
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $pdo->beginTransaction();

  $qExiste = $pdo->prepare("SELECT id, id_estado_despacho FROM despachos WHERE id = ? FOR UPDATE");
  $qExiste->execute([$id]);
  $despacho = $qExiste->fetch(PDO::FETCH_ASSOC);
  if (!$despacho) {
    $pdo->rollBack();
    Database::disconnect();
    header("Location: listarDespachos.php");
    exit;
  }
  if ((int)$despacho['id_estado_despacho'] !== 1) {
    throw new RuntimeException('Un despacho confirmado no se puede eliminar.');
  }

  // Primero se quitan los detalles porque dependen de los bultos.
  $q = $pdo->prepare(
    "DELETE FROM bultos_detalle
     WHERE id_bulto IN (SELECT id FROM bultos WHERE id_despacho = ?)"
  );
  $q->execute([$id]);

  // Luego los bultos, que dependen del despacho.
  $q = $pdo->prepare("DELETE FROM bultos WHERE id_despacho = ?");
  $q->execute([$id]);

  // Las relaciones con OCC se eliminan por la clave foránea en cascada.
  $q = $pdo->prepare("DELETE FROM despachos WHERE id = ?");
  $q->execute([$id]);

  $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Eliminación de despacho ID #$id','Despachos','')";
  $q = $pdo->prepare($sql);
  $q->execute([$_SESSION['user']['id']]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  Database::disconnect();
  $_SESSION['error_despacho'] = $e->getMessage();
  header('Location: listarDespachos.php');
  exit;
}

Database::disconnect();
  
header("Location: listarDespachos.php");
