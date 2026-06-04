<?php
require("config.php");
require 'database.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT MAX(nro_colada_interna) AS ultimo FROM ingresos_detalle WHERE nro_colada_interna IS NOT NULL AND nro_colada_interna <> ''";
$q = $pdo->prepare($sql);
$q->execute();
$ultimo = $q->fetchColumn();
Database::disconnect();
if ($ultimo && preg_match('/^[A-Z]{3}$/i', $ultimo)) {
    $ultimo = strtoupper($ultimo);
} else {
    $ultimo = '';
}
echo json_encode(['success' => true, 'ultimo' => $ultimo]);
