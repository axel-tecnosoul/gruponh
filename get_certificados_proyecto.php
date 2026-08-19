<?php
session_start();
if (empty($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}
require 'database.php';

$id_proyecto = intval($_POST['id_proyecto'] ?? 0);
if ($id_proyecto <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Traer cabeceras de avances del proyecto
    // Los certificados_maestros se relacionan con proyectos a través de certificados_maestros_detalles (id_proyecto)
    // y los avances son certificados_avances_cabecera (id_certificado_maestro)
    $sql = "SELECT DISTINCT
                ca.id,
                cm.revision,
                DATE_FORMAT(ca.fecha_emision, '%d/%m/%Y') AS fecha_emision,
                DATE_FORMAT(ca.fecha_inicio,  '%d/%m/%Y') AS fecha_inicio,
                DATE_FORMAT(ca.fecha_fin,     '%d/%m/%Y') AS fecha_fin,
                ca.monto_total,
                ca.monto_acumulado_avances,
                ca.observaciones,
                ca.aprobado_cliente
            FROM certificados_avances_cabecera ca
            INNER JOIN certificados_maestros cm
                ON cm.id = ca.id_certificado_maestro
            INNER JOIN certificados_maestros_detalles cmd
                ON cmd.id_certificado_maestro = cm.id
            WHERE cmd.id_proyecto = ?
            ORDER BY ca.id ASC, ca.fecha_emision DESC";

    $q = $pdo->prepare($sql);
    $q->execute([$id_proyecto]);
    $certificados = $q->fetchAll(PDO::FETCH_ASSOC);

    // Para cada certificado traer los detalles del avance
    foreach ($certificados as &$cert) {
        $qd = $pdo->prepare(
            "SELECT
                 COALESCE(cmd.descripcion, '') AS descripcion,
                 cad.cantidad_anterior,
                 cad.cantidad_actual,
                 cad.cantidad_acumulado,
                 cad.precio_unitario,
                 cad.subtotal
             FROM certificados_avances_detalle cad
             LEFT JOIN certificados_maestros_detalles cmd
                 ON cmd.id = cad.id_certificado_maestro_detalle
             WHERE cad.id_certificado_avance = ?
             ORDER BY cad.id ASC"
        );
        $qd->execute([$cert['id']]);
        $cert['detalles'] = $qd->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($cert);

    Database::disconnect();
    header('Content-Type: application/json');
    echo json_encode($certificados);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
