<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}

require 'database.php';

$id = !empty($_GET['id']) ? intval($_GET['id']) : null;
$id_compra = !empty($_GET['id_compra']) ? intval($_GET['id_compra']) : null;

if ($id === null || $id_compra === null) {
    header("Location: listarCompras.php");
    exit();
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->beginTransaction();

try {
    $sqlItem = "SELECT cd.id_material, cd.cantidad, c.id_pedido 
                FROM compras_detalle cd 
                JOIN compras c ON c.id = cd.id_compra 
                WHERE cd.id = ? AND cd.id_compra = ?";
    $qItem = $pdo->prepare($sqlItem);
    $qItem->execute([$id, $id_compra]);
    $itemData = $qItem->fetch(PDO::FETCH_ASSOC);

    if (!$itemData) {
        throw new Exception("Item no encontrado.");
    }

    $id_material = $itemData['id_material'];
    $id_pedido = $itemData['id_pedido'];

    $sqlDel = "DELETE FROM compras_detalle WHERE id = ?";
    $pdo->prepare($sqlDel)->execute([$id]);

    $sqlCount = "SELECT COUNT(*) FROM compras_detalle WHERE id_compra = ?";
    $qCount = $pdo->prepare($sqlCount);
    $qCount->execute([$id_compra]);
    $quedanItems = (int)$qCount->fetchColumn();

    if ($quedanItems == 0) {
        $pdo->prepare("UPDATE compras SET id_estado_compra = 4 WHERE id = ?")->execute([$id_compra]);
    } else {
        $sqlTotales = "SELECT SUM(total) as total_neto FROM compras_detalle WHERE id_compra = ?";
        $qTotales = $pdo->prepare($sqlTotales);
        $qTotales->execute([$id_compra]);
        $totalNeto = (float)($qTotales->fetchColumn() ?: 0);

        $sqlCompra = "SELECT id_tipo_iva, descuento FROM compras WHERE id = ?";
        $qCompra = $pdo->prepare($sqlCompra);
        $qCompra->execute([$id_compra]);
        $compraData = $qCompra->fetch(PDO::FETCH_ASSOC);

        $tasa_iva = 0;
        $qTasa = $pdo->prepare("SELECT tasa FROM tipos_iva WHERE id = ?");
        $qTasa->execute([$compraData['id_tipo_iva']]);
        $dt = $qTasa->fetch(PDO::FETCH_ASSOC);
        if ($dt) $tasa_iva = (float)$dt['tasa'];

        $desc_pct = (float)$compraData['descuento'];
        $desc_monto = $totalNeto * ($desc_pct / 100);
        $monto_iva = ($totalNeto - $desc_monto) * ($tasa_iva / 100);
        $totalFinal = $totalNeto - $desc_monto + $monto_iva;

        $pdo->prepare("UPDATE compras SET total = ?, iva = ? WHERE id = ?")
            ->execute([$totalFinal, $monto_iva, $id_compra]);
    }

    $sqlSum = "SELECT COALESCE(SUM(cd.cantidad), 0) 
               FROM compras_detalle cd 
               JOIN compras c ON c.id = cd.id_compra 
               WHERE c.id_pedido = ? AND cd.id_material = ? AND c.id_estado_compra NOT IN (5)";
    $qSum = $pdo->prepare($sqlSum);
    $qSum->execute([$id_pedido, $id_material]);
    $totalComprado = (float)($qSum->fetchColumn() ?: 0);

    $pdo->prepare("UPDATE pedidos_detalle SET comprado = ? WHERE id_pedido = ? AND id_material = ?")
        ->execute([$totalComprado, $id_pedido, $id_material]);

    $sqlComp = "SELECT cd.id 
                FROM computos_detalle cd 
                JOIN computos c ON c.id = cd.id_computo 
                JOIN pedidos p ON p.id_computo = c.id 
                WHERE p.id = ? AND cd.id_material = ?";
    $qComp = $pdo->prepare($sqlComp);
    $qComp->execute([$id_pedido, $id_material]);
    $compDet = $qComp->fetch(PDO::FETCH_ASSOC);
    if ($compDet) {
        $pdo->prepare("UPDATE computos_detalle SET comprado = ? WHERE id = ?")
            ->execute([$totalComprado, $compDet['id']]);
    }

    $pdo->prepare("INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(), ?, ?, 'Compras', ?)")
        ->execute([
            $_SESSION['user']['id'],
            "Eliminación de concepto de OC #$id_compra",
            "verCompra.php?id=$id_compra"
        ]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error al eliminar: ' . $e->getMessage()];
}

Database::disconnect();

if ($quedanItems == 0) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'La OC fue cancelada porque no quedaban conceptos.'];
    header("Location: listarCompras.php");
} else {
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Concepto eliminado exitosamente.'];
    header("Location: gestionarPedido.php?id_compra=" . $id_compra);
}
exit();
