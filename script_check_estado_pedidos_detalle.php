<?php
/**
 * Script para recalcular todos los estados de pedidos_detalle
 * 
 * Este script recorre todos los registros de la tabla pedidos_detalle
 * y recalcula su estado usando la función actualizarEstadoPedidoDetalle().
 * 
 * Puede ejecutarse desde navegador o línea de comandos.
 */

// Configuración inicial
require_once("config.php");
require_once("database.php");
require_once("funciones.php");

// Verificar si se ejecuta desde navegador
$isWeb = !empty($_SERVER['HTTP_HOST']);

if ($isWeb) {
  echo "<html><head><title>Recalcular Estados Pedidos Detalle</title></head><body>";
  echo "<h1>Recalculando Estados de Pedidos Detalle</h1>";
  echo "<pre>";
}

// Función para imprimir log (compatible con web y CLI)
function printLog($message, $isWeb = false) {
  if ($isWeb) {
    echo htmlspecialchars($message) . "\n";
    flush();
  } else {
    echo $message . "\n";
  }
}

try {
  // Conectar a la base de datos
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  printLog("=== INICIANDO RECÁLCULO DE ESTADOS ===", $isWeb);
  printLog("Conectado a la base de datos correctamente.", $isWeb);
  
  $filtroId="";
  $filtroId=" AND id IN (96)";

  // Obtener todos los IDs de pedidos_detalle
  $sql = "SELECT id FROM pedidos_detalle WHERE 1 $filtroId ORDER BY id";
  echo $sql."<br>";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  $totalRegistros = count($registros);
  printLog("Total de registros a procesar: $totalRegistros", $isWeb);
  printLog("", $isWeb);
  
  $procesados = 0;
  $errores = 0;
  
  // Procesar cada registro
  foreach ($registros as $registro) {
    $idPedidoDetalle = (int)$registro['id'];

    echo calcularEstadoPedidoDetalle($pdo, $idPedidoDetalle);
    
  }
  
  // Resumen final
  printLog("", $isWeb);
  printLog("=== RESUMEN FINAL ===", $isWeb);
  printLog("Estados recalculados: $procesados registros", $isWeb);
  if ($errores > 0) {
    printLog("Errores encontrados: $errores", $isWeb);
  }
  printLog("Proceso completado exitosamente.", $isWeb);
  
} catch (Exception $e) {
  printLog("ERROR CRÍTICO: " . $e->getMessage(), $isWeb);
  exit(1);
} finally {
  // Cerrar conexión
  Database::disconnect();
}

if ($isWeb) {
  echo "</pre>";
  echo "<p><strong>Proceso completado.</strong> <a href='javascript:history.back()'>Volver</a></p>";
  echo "</body></html>";
}
?>