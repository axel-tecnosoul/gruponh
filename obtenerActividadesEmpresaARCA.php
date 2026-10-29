<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'La sesión expiró. Vuelva a iniciar sesión.']);
    exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/permisos.php';

try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $guard = $pdo->query("SELECT id FROM acciones WHERE accion='Menu_Empresas' LIMIT 1")->fetchColumn();
    if ($guard && !tienePermiso((int)$guard)) {
        http_response_code(403);
        throw new RuntimeException('No tiene permiso para consultar actividades de empresas.');
    }

    $companyId = filter_input(INPUT_GET, 'id_empresa', FILTER_VALIDATE_INT);
    if (!$companyId || $companyId < 1) {
        http_response_code(400);
        throw new RuntimeException('La empresa seleccionada no es válida.');
    }

    $query = $pdo->prepare(
        'SELECT codigo, MAX(descripcion) AS descripcion, MIN(regimen) AS regimen,
                MIN(nomenclador) AS nomenclador, MIN(orden) AS orden
         FROM arca_actividades_empresa WHERE id_empresa = ?
         GROUP BY codigo ORDER BY MIN(orden), codigo'
    );
    $query->execute([$companyId]);
    echo json_encode(['ok' => true, 'actividades' => $query->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($pdo) && $pdo instanceof PDO) {
        Database::disconnect();
    }
}
