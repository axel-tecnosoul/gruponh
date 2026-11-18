<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}
require 'database.php';
require_once 'despachos_lib.php';

$idBulto = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idBulto || $idBulto < 1) {
  header('Location: listarDespachos.php');
  exit;
}

$pdo = Database::connect();
$url = despachoUrlDesdeBulto($pdo, $idBulto);
Database::disconnect();
header('Location: ' . $url);
exit;
