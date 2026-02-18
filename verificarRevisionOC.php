<?php
require("config.php");
if (empty($_SESSION['user'])) {
    echo json_encode(['puede_revisar' => false, 'mensaje' => 'Sesión expirada.']);
    exit;
}
require 'database.php';

header('Content-Type: application/json');

$id_compra = isset($_POST['id_compra']) ? (int)$_POST['id_compra'] : 0;

if ($id_compra <= 0) {
    echo json_encode(['puede_revisar' => false, 'mensaje' => 'ID de OC inválido.']);
    exit;
}

$EST_ENVIADA   = 3;
$EST_CANCELADA = 4;
$EST_SUPERADO  = 5;

try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener datos de la OC
    $stmt = $pdo->prepare("SELECT id, nro_oc, nro_revision, id_estado_compra FROM compras WHERE id = ?");
    $stmt->execute([$id_compra]);
    $oc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oc) {
        echo json_encode(['puede_revisar' => false, 'mensaje' => 'OC no encontrada.']);
        exit;
    }

    // Verificar que la OC esté en estado Enviada
    if ((int)$oc['id_estado_compra'] !== $EST_ENVIADA) {
        echo json_encode([
            'puede_revisar' => false,
            'mensaje' => 'La OC no está en estado Enviada.'
        ]);
        exit;
    }

    // Verificar que NO exista ya una revisión posterior activa
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cantidad 
        FROM compras 
        WHERE nro_oc = ? 
          AND nro_revision > ? 
          AND id_estado_compra NOT IN (?, ?)
    ");
    $stmt->execute([
        $oc['nro_oc'],
        $oc['nro_revision'],
        $EST_SUPERADO,
        $EST_CANCELADA
    ]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    Database::disconnect();

    if ((int)$resultado['cantidad'] > 0) {
        echo json_encode([
            'puede_revisar' => false,
            'mensaje' => 'Ya existe una revisión en curso para esta OC. Debe esperar a que la OC enviada actual pase a estado "Superado".'
        ]);
    } else {
        echo json_encode([
            'puede_revisar' => true,
            'mensaje' => ''
        ]);
    }

} catch (Exception $e) {
    Database::disconnect();
    echo json_encode([
        'puede_revisar' => false,
        'mensaje' => 'Error: ' . $e->getMessage()
    ]);
}