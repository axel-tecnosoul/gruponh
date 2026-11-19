<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}
require 'database.php';
require_once 'despachos_lib.php';

$idDetalle = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idDetalle || $idDetalle < 1) {
  header('Location: listarDespachos.php');
  exit;
}

$pdo = Database::connect();
$q = $pdo->prepare("SELECT id_bulto FROM bultos_detalle WHERE id = ?");
$q->execute([$idDetalle]);
$idBulto = (int)$q->fetchColumn();
$url = $idBulto > 0 ? despachoUrlDesdeBulto($pdo, $idBulto) : 'listarDespachos.php';
Database::disconnect();
header('Location: ' . $url);
exit;
