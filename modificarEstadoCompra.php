<?php
require("config.php");
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
  echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
  die();
}

require 'database.php';

try {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Manejar tanto los parámetros antiguos como los nuevos
  $estado = isset($_POST["nuevo_estado"]) ? $_POST["nuevo_estado"] : $_POST["idEstado"];
  $compraId = isset($_POST["id_compra"]) ? $_POST["id_compra"] : $_POST["idPosicion"];

  if (empty($estado) || empty($compraId)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    die();
  }

  // Actualizar estado de la compra
  $sql = "UPDATE compras SET id_estado_compra = ? WHERE id = ?";
  $q = $pdo->prepare($sql);
  $result = $q->execute([$estado, $compraId]);

  if ($result) {
    // Registrar en logs
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(), ?, 'Modificación de Estado de Compra', 'Compras', ?)";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id'], "verCompra.php?id=".$compraId]);

    // Enviar notificaciones si se está enviando a aprobación (estado 2)
    if ($estado == 2) {
      require_once('funciones.php');
      $detalleNotificacion = "ID Compra: #".$compraId;
      $asuntoEmail = "Módulo Compras - Envío a Aprobación";
      $cuerpoEmail = "La OC #".$compraId." ha sido enviada para aprobación en el sistema";
      
      crearNotificacion($pdo, 2, $compraId, $detalleNotificacion, $asuntoEmail, $cuerpoEmail);
    }

    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
  }

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} finally {
  Database::disconnect();
}
?>