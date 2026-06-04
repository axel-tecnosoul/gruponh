<?php
require("config.php");
require 'database.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (empty($_POST['assignments'])) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron asignaciones']);
    exit;
}

$assignments = json_decode($_POST['assignments'], true);
if (!is_array($assignments)) {
    echo json_encode(['success' => false, 'message' => 'Formato de asignaciones inválido']);
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->beginTransaction();

try {
    $sql = "UPDATE ingresos_detalle SET nro_colada_interna = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    foreach ($assignments as $assign) {
        if (empty($assign['id']) || empty($assign['codigo'])) {
            continue;
        }
        $q->execute([$assign['codigo'], $assign['id']]);
    }
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

Database::disconnect();
