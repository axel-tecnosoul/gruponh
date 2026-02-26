<?php
require("config.php");
if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'mensaje' => 'Sesión expirada.']);
    exit;
}
require 'database.php';
require_once('funciones.php');

header('Content-Type: application/json');

$id_compra = isset($_POST['id_compra']) ? (int)$_POST['id_compra'] : 0;
$motivo    = isset($_POST['motivoRevision']) ? trim($_POST['motivoRevision']) : '';

if ($id_compra <= 0) {
    echo json_encode(['success' => false, 'mensaje' => 'ID de OC inválido.']);
    exit;
}
if ($motivo === '') {
    echo json_encode(['success' => false, 'mensaje' => 'Debe ingresar el motivo de la revisión.']);
    exit;
}

$EST_ELABORACION = 1;
$EST_ENVIADA     = 3;
$EST_CANCELADA   = 4;
$EST_SUPERADO    = 5;

try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // 1. Obtener la OC original
    $stmt = $pdo->prepare("
        SELECT id, id_pedido, id_cuenta_proveedor, fecha_emision, fecha_entrega,
              id_forma_pago, id_tipo_iva, id_estado_compra, nro_oc, total, iva,
              comentarios, id_moneda, tipo_cambio_dia, comentarios_revision, 
              descuento, nro_revision, aprobado
        FROM compras WHERE id = ?
    ");
    $stmt->execute([$id_compra]);
    $oc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oc) {
        throw new Exception("OC no encontrada.");
    }

    if ((int)$oc['id_estado_compra'] !== $EST_ENVIADA) {
        throw new Exception("La OC no está en estado Enviada.");
    }

    // 2. Verificar que no haya revisión en curso
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cantidad 
        FROM compras 
        WHERE nro_oc = ? 
          AND nro_revision > ? 
          AND id_estado_compra NOT IN (?, ?)
    ");
    $stmt->execute([$oc['nro_oc'], $oc['nro_revision'], $EST_SUPERADO, $EST_CANCELADA]);
    $check = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((int)$check['cantidad'] > 0) {
        throw new Exception("Ya existe una revisión en curso para esta OC.");
    }

    // 3. Nueva revisión
    $nueva_revision = (int)$oc['nro_revision'] + 1;

    // 4. Duplicar la OC
    $sql = "INSERT INTO compras (
                id_pedido, id_cuenta_proveedor, fecha_emision, fecha_entrega,
                id_forma_pago, id_tipo_iva, id_estado_compra, nro_oc,
                total, iva, comentarios, id_moneda, tipo_cambio_dia,
                comentarios_revision, descuento, nro_revision, aprobado
            ) VALUES (
                ?, ?, NOW(), ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, 0
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $oc['id_pedido'], $oc['id_cuenta_proveedor'], $oc['fecha_entrega'],
        $oc['id_forma_pago'], ($oc['id_tipo_iva'] ?? null), $EST_ELABORACION, $oc['nro_oc'],
        $oc['total'], $oc['iva'], $oc['comentarios'], $oc['id_moneda'], $oc['tipo_cambio_dia'],
        $motivo, $oc['descuento'], $nueva_revision
    ]);
    $id_nueva_oc = $pdo->lastInsertId();

    // 5. Duplicar detalles
    $stmt = $pdo->prepare("SELECT * FROM compras_detalle WHERE id_compra = ?");
    $stmt->execute([$id_compra]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sqlDet = "INSERT INTO compras_detalle (
                  id_compra, id_material, cantidad, id_unidad_medida,
                  precio, precio_kg, subtotal, descuento, fecha_entrega
               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtDet = $pdo->prepare($sqlDet);

    foreach ($detalles as $det) {
        $stmtDet->execute([
            $id_nueva_oc, $det['id_material'], $det['cantidad'], $det['id_unidad_medida'],
            $det['precio'], $det['precio_kg'], $det['subtotal'], $det['descuento'], $det['fecha_entrega']
        ]);
    }

    // 6. Log
    $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, ?, 'Compras', ?)")
        ->execute([
            $_SESSION['user']['id'],
            "Nueva revisión (Rev.$nueva_revision) de OC {$oc['nro_oc']} - Motivo: $motivo",
            "verCompra.php?id=$id_nueva_oc"
        ]);

    // 7. Notificación
    $idTipoNotificacion = 4;
    $idEntidad = $id_nueva_oc;
    $detalleNotificacion = "ID OC: #$id_nueva_oc - Revisión $nueva_revision";
    $asuntoEmail = "Compras - Nueva Revisión (Rev.$nueva_revision) de OC {$oc['nro_oc']}";
    $cuerpoEmail = "Se ha generado una nueva revisión de la Orden de Compra.\n"
                 . "OC: {$oc['nro_oc']}\n"
                 . "Revisión: $nueva_revision\n"
                 . "Motivo: $motivo\n"
                 . "Usuario: " . ($_SESSION['user']['usuario'] ?? '');
    crearNotificacion($pdo, $idTipoNotificacion, $idEntidad, $detalleNotificacion, $asuntoEmail, $cuerpoEmail);

    $pdo->commit();
    Database::disconnect();

    echo json_encode([
        'success'  => true,
        'mensaje'  => "Revisión $nueva_revision creada exitosamente.",
        'id_nueva' => $id_nueva_oc,
        'redirect' => "modificarCompra.php?id_compra=" . $id_nueva_oc
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Database::disconnect();
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
}