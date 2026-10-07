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
require_once __DIR__ . '/FacturacionARCA.php';

try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $guard = $pdo->query("SELECT id FROM acciones WHERE accion='Menu_Empresas' LIMIT 1")->fetchColumn();
    if ($guard && !tienePermiso((int)$guard)) {
        http_response_code(403);
        throw new RuntimeException('No tiene permiso para configurar empresas.');
    }
    $postedToken = $_POST['csrf_token'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || empty($_SESSION['csrf_empresa_arca'])
        || !is_string($postedToken)
        || !hash_equals($_SESSION['csrf_empresa_arca'], $postedToken)) {
        http_response_code(400);
        throw new RuntimeException('La solicitud no es válida. Actualice la página e intente nuevamente.');
    }
    $companyId = filter_input(INPUT_POST, 'id_empresa', FILTER_VALIDATE_INT);
    if (!$companyId || $companyId < 1) {
        http_response_code(400);
        throw new RuntimeException('La empresa seleccionada no es válida.');
    }

    $catalogs = FacturacionARCA::consultarCatalogosEmpresa($pdo, $companyId);
    $pdo->beginTransaction();
    $saveType = $pdo->prepare(
        'INSERT INTO arca_tipos_comprobante (codigo, descripcion, fecha_desde, fecha_hasta)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion),
           fecha_desde = VALUES(fecha_desde), fecha_hasta = VALUES(fecha_hasta)'
    );
    foreach ($catalogs['tipos_comprobante'] as $type) {
        $saveType->execute([
            $type['codigo'],
            $type['descripcion'],
            substr($type['fecha_desde'], 0, 10),
            substr($type['fecha_hasta'], 0, 10),
        ]);
    }
    $pdo->commit();
    echo json_encode(['ok' => true] + $catalogs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($pdo) && $pdo instanceof PDO) {
        Database::disconnect();
    }
}
