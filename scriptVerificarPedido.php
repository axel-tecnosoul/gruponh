<?php
require("config.php");
require 'database.php';
require_once('funciones.php');

// ID del pedido a verificar
$idPedido = 230;

echo "<h2>🔍 Verificación del Pedido ID: $idPedido</h2>";

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  // Obtener información básica del pedido
  echo "<h3>📋 Información del Pedido</h3>";
  $sqlPedido = "SELECT p.id, p.id_estado, ep.estado, p.fecha, p.lugar_entrega FROM pedidos p LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado WHERE p.id = ?";
  $qPedido = $pdo->prepare($sqlPedido);
  $qPedido->execute([$idPedido]);
  $pedidoInfo = $qPedido->fetch(PDO::FETCH_ASSOC);
  
  if (!$pedidoInfo) {
    echo "<b>❌ ERROR: Pedido $idPedido no encontrado</b><br>";
    exit();
  }
  
  echo "<b>Estado actual:</b> " . $pedidoInfo['estado'] . " (ID: " . $pedidoInfo['id_estado'] . ")<br>";
  echo "<b>Fecha:</b> " . $pedidoInfo['fecha'] . "<br>";
  echo "<b>Lugar entrega:</b> " . $pedidoInfo['lugar_entrega'] . "<br><br>";
  
  // Verificar si el pedido debe actualizarse
  echo "<h3>🔄 Verificando si debe actualizarse el estado</h3>";
  $seActualizo = verificarYActualizarEstadoPedido($pdo, $idPedido, true);
  
  if ($seActualizo) {
    echo "<h3>✅ El pedido se actualizó al estado 5 (Terminado)</h3>";
  } else {
    echo "<h3>⏳ El pedido mantiene su estado actual</h3>";
  }
  
  // Mostrar estado final
  echo "<h3>📊 Estado Final del Pedido</h3>";
  $qPedido->execute([$idPedido]);
  $pedidoFinal = $qPedido->fetch(PDO::FETCH_ASSOC);
  echo "<b>Estado final:</b> " . $pedidoFinal['estado'] . " (ID: " . $pedidoFinal['id_estado'] . ")<br>";
  
} catch (Exception $e) {
  echo "<b>❌ ERROR:</b> " . $e->getMessage() . "<br>";
}

Database::disconnect();

echo "<br><a href='listarPedidos.php'>Volver al listado de pedidos</a>";
?>