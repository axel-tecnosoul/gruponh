<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}
if (empty($_SESSION['csrf_factura_arca'])
    || !hash_equals($_SESSION['csrf_factura_arca'], (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('La sesión de emisión expiró. Vuelva a abrir la factura e intente nuevamente.');
}

require_once __DIR__ . '/FacturacionARCA.php';

$idFactura = filter_input(INPUT_POST, 'id_factura', FILTER_VALIDATE_INT);
if (!$idFactura || $idFactura < 1) {
    http_response_code(400);
    exit('Factura inválida.');
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $result = FacturacionARCA::reintentarHomologacion($pdo, $idFactura);
    $_SESSION['factura_arca_mensaje'] = [
        'tipo' => $result['estado'] === 'autorizado' ? 'success' : 'warning',
        'texto' => $result['mensaje'],
    ];
} catch (Throwable $e) {
    $_SESSION['factura_arca_mensaje'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
} finally {
    Database::disconnect();
}

header('Location: verFacturaVenta.php?id=' . $idFactura, true, 303);
exit;
