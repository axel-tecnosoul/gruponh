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
    $guard = $pdo->query("SELECT id FROM acciones WHERE accion='Menu_Empresas' LIMIT 1")->fetchColumn();
    if ($guard && !tienePermiso((int)$guard)) {
        http_response_code(403);
        throw new RuntimeException('No tiene permiso para consultar empresas.');
    }
    $companyId = filter_input(INPUT_GET, 'id_empresa', FILTER_VALIDATE_INT);
    if (!$companyId || $companyId < 1) {
        http_response_code(400);
        throw new RuntimeException('La empresa seleccionada no es válida.');
    }
    $query = $pdo->prepare(
        'SELECT p.numero, p.emision_tipo, t.codigo_tipo_comprobante
         FROM arca_puntos_venta p
         LEFT JOIN arca_punto_venta_tipos t ON t.id_punto_venta = p.id
         WHERE p.id_empresa = ? AND p.bloqueado = 0 AND p.fecha_baja IS NULL
         ORDER BY p.numero, t.codigo_tipo_comprobante'
    );
    $query->execute([$companyId]);
    $points = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $number = (int)$row['numero'];
        if (!isset($points[$number])) {
            $points[$number] = [
                'numero' => $number,
                'emision_tipo' => (string)$row['emision_tipo'],
                'tipos' => [],
            ];
        }
        if ($row['codigo_tipo_comprobante'] !== null) {
            $points[$number]['tipos'][] = (int)$row['codigo_tipo_comprobante'];
        }
    }
    echo json_encode(['ok' => true, 'puntos_venta' => array_values($points)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
