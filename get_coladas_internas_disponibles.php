<?php
require('config.php');
require 'database.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_material = isset($_GET['id_material']) ? intval($_GET['id_material']) : 0;
if ($id_material <= 0) {
    echo json_encode(['success' => false, 'message' => 'Concepto inválido']);
    exit;
}

$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT id, id_material, nro_colada_interna, cantidad, saldo 
        FROM ingresos_detalle 
        WHERE id_material = ? 
          AND nro_colada_interna IS NOT NULL 
          AND nro_colada_interna <> '' 
          AND (id_colada_origen IS NULL OR id_colada_origen = ?)";
$q = $pdo->prepare($sql);
$q->execute([$id_material, $edit_id]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

if (!empty($rows)) {
    $sqlConcepto = "SELECT concepto FROM materiales WHERE id = ? LIMIT 1";
    $qConcepto = $pdo->prepare($sqlConcepto);
    $qConcepto->execute([$id_material]);
    $concepto = $qConcepto->fetchColumn();
    foreach ($rows as &$row) {
        $row['concepto'] = $concepto;
    }
}
Database::disconnect();

if (empty($rows)) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

echo json_encode(['success' => true, 'data' => $rows]);