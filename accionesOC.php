<?php
require("config.php");
if (empty($_SESSION['user'])) {
    die("Sesión expirada");
}
require 'database.php';
require_once('funciones.php');

if (empty($_POST['ajax'])) {
    die("Acceso no permitido");
}

$accion    = $_POST['accion'] ?? '';
$id_compra = (int)($_POST['id_compra'] ?? 0);

if ($id_compra <= 0) die("ID de OC inválido");

$EST_ELABORACION  = 1;
$EST_PARA_APROBAR = 2;
$EST_ENVIADA      = 3;
$EST_CANCELADA    = 4;
$EST_SUPERADO     = 5;

try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM compras WHERE id = ?");
    $stmt->execute([$id_compra]);
    $oc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oc) die("OC no encontrada");

    switch ($accion) {

        case 'enviar':
            if ((int)$oc['id_estado_compra'] !== $EST_APROBADA) {
                die("La OC debe estar Aprobada para poder enviarla.");
            }

            $pdo->beginTransaction();

            // Cambiar a Enviada
            $pdo->prepare("UPDATE compras SET id_estado_compra = ? WHERE id = ?")
                ->execute([$EST_ENVIADA, $id_compra]);

            // Pasar revisiones anteriores enviadas a Superado
            $stmt = $pdo->prepare("
                UPDATE compras 
                SET id_estado_compra = ? 
                WHERE nro_oc = ? 
                  AND nro_revision < ? 
                  AND id_estado_compra = ?
            ");
            $stmt->execute([
                $EST_SUPERADO,
                $oc['nro_oc'],
                $oc['nro_revision'],
                $EST_ENVIADA
            ]);
            $superadas = $stmt->rowCount();

            // Log
            $detLog = "Envío de OC #{$oc['nro_oc']} (Rev.{$oc['nro_revision']})";
            if ($superadas > 0) {
                $detLog .= " - Se superó $superadas revisión(es) anterior(es)";
            }
            $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, ?, 'Compras', ?)")
                ->execute([$_SESSION['user']['id'], $detLog, "verCompra.php?id=$id_compra"]);

            crearNotificacion($pdo, 2, $id_compra, "ID Compra: #$id_compra", "Compras - OC Enviada", "La OC #{$oc['nro_oc']} Rev.{$oc['nro_revision']} ha sido enviada.");

            $pdo->commit();
            echo "ok";
            break;

        case 'cancelar':
            if (in_array((int)$oc['id_estado_compra'], [$EST_CANCELADA, $EST_SUPERADO])) {
                die("La OC ya está cancelada o superada.");
            }

            $pdo->prepare("UPDATE compras SET id_estado_compra = ? WHERE id = ?")
                ->execute([$EST_CANCELADA, $id_compra]);

            $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, ?, 'Compras', ?)")
                ->execute([$_SESSION['user']['id'], "Cancelación de OC #$id_compra", "verCompra.php?id=$id_compra"]);

            echo "ok";
            break;

        default:
            die("Acción no reconocida");
    }

    Database::disconnect();

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Database::disconnect();
    die("Error: " . $e->getMessage());
}