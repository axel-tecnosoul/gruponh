<?php
// Modo Debug habilitado para ver consultas y resultados
$modoDebug = 0;

require("config.php");
if (empty($_SESSION['user'])) {
  if ($modoDebug == 0) {
    header("Location: index.php");
    die("Redirecting to index.php");
  } else {
    echo "<h3>DEBUG: Usuario no logueado (redireccion deshabilitada)</h3>";
  }
}
require 'database.php';
require_once('funciones.php');

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  if ($modoDebug == 0) {
    header("Location: listarCompras.php");
  } else {
    echo "<h3>DEBUG: ID no proporcionado (redireccion deshabilitada)</h3>";
    exit();
  }
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Iniciar transacción
$pdo->beginTransaction();
$transaccionExitosa = false;

if ($modoDebug == 1) {
  echo "<h2>🐛 MODO DEBUG ACTIVADO - marcarItemsEntregadoCompra.php</h2>";
  echo "<h3>⚙️ TRANSACCIÓN INICIADA</h3>";
  echo "<h3>Parámetros recibidos:</h3>";
  echo "<b>ID Compra:</b> " . htmlspecialchars($id) . "<br>";
  echo "<b>Reservado:</b> " . htmlspecialchars($_GET['reservado'] ?? 'No especificado') . "<br>";
  echo "<h3>POST Data:</h3>";
  echo "<pre>" . print_r($_POST, true) . "</pre>";
}

// Validar que se hayan enviado IDs de compra_detalle
if (empty($_POST['id_compra_detalle']) || !is_array($_POST['id_compra_detalle'])) {
  if ($modoDebug == 1) {
    echo "<h3>❌ ERROR: No se enviaron IDs de compra_detalle</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
  }
  $pdo->rollback();
  Database::disconnect();
  if ($modoDebug == 0) {
    header("Location: listarCompras.php");
  }
  exit();
}

$count = 1;
$idIngreso = 0;

try {
  // Obtener datos básicos de la compra una sola vez
  $sqlCompra = "SELECT c.id, c.comentarios, c.nro_oc, c.id_cuenta_proveedor, c.id_pedido, p.lugar_entrega, p.id_cuenta_recibe, p.id_computo FROM compras c INNER JOIN pedidos p ON p.id = c.id_pedido WHERE c.id = ?";
  $paramsCompra = [$_GET['id']];
  if ($modoDebug == 1) {
    echo "<b>Datos básicos de la compra:</b><br>" . debugQuery($pdo, $sqlCompra, $paramsCompra) . "<br><br>";
  }
  $qCompra = $pdo->prepare($sqlCompra);
  $qCompra->execute($paramsCompra);
  $compraData = $qCompra->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug == 1) {
    echo "<h3>📋 Datos de la Compra:</h3>";
    echo "<pre>" . print_r($compraData, true) . "</pre>";
    echo "<h3>📦 IDs a procesar:</h3>";
    echo "<pre>" . print_r($_POST['id_compra_detalle'], true) . "</pre>";
  }

  // Variables globales del pedido
  $idPedido = $compraData['id_pedido'];
  $idComputo = $compraData['id_computo'];

  // Procesar cada ID enviado desde el formulario
  foreach ($_POST['id_compra_detalle'] as $row => $idCompraDetalle) {
    $cantidadIngresar = $_POST['cantidadIngresar'][$row] ?? 0;
    
    if ($cantidadIngresar > 0) {
      // Obtener datos específicos de este item
      $sqlItem = "SELECT cd.id, cd.id_material, cd.cantidad, cd.id_unidad_medida, cd.entregado, pd.id as idPedidoDetalle FROM compras_detalle cd INNER JOIN pedidos_detalle pd ON pd.id_pedido = ? AND pd.id_material = cd.id_material AND pd.cancelado = 0 WHERE cd.id = ?";
      $paramsItem = [$compraData['id_pedido'], $idCompraDetalle];
      if ($modoDebug == 1) {
        echo "<b>Datos del item ID: $idCompraDetalle:</b><br>" . debugQuery($pdo, $sqlItem, $paramsItem) . "<br><br>";
      }
      $qItem = $pdo->prepare($sqlItem);
      $qItem->execute($paramsItem);
      $itemData = $qItem->fetch(PDO::FETCH_ASSOC);
      
      if (!$itemData) {
        if ($modoDebug == 1) {
          echo "<h3>⚠️ SALTANDO Item ID: $idCompraDetalle (no encontrado en pedidos_detalle)</h3>";
        }
        continue;
      }
      
      $idMaterial = $itemData['id_material'];
      $idPedidoDetalle = $itemData['idPedidoDetalle'];

      if ($modoDebug == 1) {
        echo "<h3>🔄 Procesando Item ID: $idCompraDetalle</h3>";
        echo "<b>Cantidad a ingresar:</b> $cantidadIngresar<br>";
        echo "<b>ID Pedido:</b> $idPedido<br>";
        echo "<b>ID Material:</b> $idMaterial<br>";
        echo "<b>ID Pedido Detalle:</b> $idPedidoDetalle<br>";
        echo "<b>ID Cómputo:</b> " . ($idComputo ?? 'NULL (Pedido Directo)') . "<br>";
      }

      $sql = "UPDATE compras_detalle SET entregado = entregado + ? WHERE id = ?";
      $params = [$cantidadIngresar, $idCompraDetalle];
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 1 - Actualizar compras_detalle:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
      }
      $q = $pdo->prepare($sql);
      $q->execute($params);
      if ($modoDebug == 1) {
        echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br><br>";
      }
      
      // Actualizar estado del pedido_detalle directamente (sin consulta adicional)
      if ($modoDebug == 1) {
        echo "<b>🔄 Actualizando estado pedido_detalle ID:</b> $idPedidoDetalle<br>";
      }
      actualizarEstadoPedidoDetalle($pdo, $idPedidoDetalle);
      
      // El pedido mantiene estado "Gestionando" (4) hasta completarse
      
      $sql = "UPDATE compras set id_estado_compra = 6 where id = ?";
      $params = [$_GET['id']];
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 2 - Actualizar estado compra:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
      }
      $q = $pdo->prepare($sql);
      $q->execute($params);
      if ($modoDebug == 1) {
        echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br><br>";
      }
      
      // Solo actualizar cómputos si existe un cómputo asociado (no es pedido directo)
      if ($idComputo) {
        if ($modoDebug == 1) {
          echo "<b>📊 Actualizando cómputos (ID: $idComputo)</b><br>";
        }
        
        if ($_GET['reservado'] == 0) {
          // Actualizar directamente computos_detalle usando el idComputo ya obtenido
          $sql = "UPDATE computos_detalle SET comprado = comprado - ? WHERE id_computo = ? AND id_material = ? AND cancelado = 0";
          $params = [$cantidadIngresar, $idComputo, $idMaterial];
          if ($modoDebug == 1) {
            echo "<b>✅ SQL 3 - Descontar de comprado (disponibles):</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
          }
          $q = $pdo->prepare($sql);
          $q->execute($params);
          if ($modoDebug == 1) {
            echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br><br>";
          }
        } else {
          // Actualizar computos_detalle directamente usando el idComputo ya obtenido  
          $sql = "UPDATE computos_detalle SET comprado = comprado - ?, reservado = reservado + ? WHERE id_computo = ? AND id_material = ? AND cancelado = 0";
          $params = [$cantidadIngresar, $cantidadIngresar, $idComputo, $idMaterial];
          if ($modoDebug == 1) {
            echo "<b>✅ SQL 3 - Actualizar computos (reservado):</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
          }
          $q = $pdo->prepare($sql);
          $q->execute($params);
          if ($modoDebug == 1) {
            echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br><br>";
          }
        }
      } else {
        if ($modoDebug == 1) {
          echo "<b>⚠️ Sin cómputo asociado - Saltando actualización de computos_detalle</b><br><br>";
        }
      }
      
      // Actualizar reservado en pedidos_detalle si corresponde (independiente de si hay cómputo)
      if ($_GET['reservado'] == 1) {
        $sql = "UPDATE pedidos_detalle SET reservado = reservado + ? WHERE id = ?";
        $params = [$cantidadIngresar, $idPedidoDetalle];
        if ($modoDebug == 1) {
          echo "<b>✅ SQL 4 - Actualizar reservado pedidos_detalle:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
        }
        $q = $pdo->prepare($sql);
        $q->execute($params);
        if ($modoDebug == 1) {
          echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br><br>";
        }
      }
      
      if ($count == 1) {
        $sql = "INSERT into ingresos (fecha_hora, id_tipo_ingreso, nro, id_cuenta_recibe, lugar_entrega, observaciones, fecha_remito, nro_remito) values (now(),1,?,?,?,?,?,?)";
        $params = [$idPedido,$compraData["id_cuenta_recibe"],$compraData["lugar_entrega"],$compraData["comentarios"],$_POST['fecha_remito'],$_POST['nro_remito']];
        if ($modoDebug == 1) {
          echo "<b>✅ SQL 5 - Insertar ingreso:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
        }
        $q = $pdo->prepare($sql);
        $q->execute($params);
        $idIngreso = $pdo->lastInsertId();
        
        if ($modoDebug == 1) {
          echo "<b>ID Ingreso creado:</b> $idIngreso<br><br>";
        }
      }
      
      $sql3 = "SELECT s.nro_sitio,s.nro_subsitio,p.nro from pedidos pe inner join proyectos p on p.id = pe.id_proyecto inner join sitios s on s.id = p.id_sitio where pe.id = ? ";
      $params3 = [$idPedido];
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 6 - Consultar datos proyecto:</b><br>" . debugQuery($pdo, $sql3, $params3) . "<br>";
      }
      $q3 = $pdo->prepare($sql3);
      $q3->execute($params3);
      $data3 = $q3->fetch(PDO::FETCH_ASSOC);
      
      if ($modoDebug == 1) {
        echo "<b>Resultado:</b> " . print_r($data3, true) . "<br>";
      }
      
      $colada = $data3['nro_sitio']."/".$data3['nro_subsitio']."/".$data3['nro']."-".$count;
      
      if ($modoDebug == 1) {
        echo "<b>Colada generada:</b> $colada<br>";
      }
      
      $sql = "INSERT into ingresos_detalle (id_ingreso, id_material, id_unidad_medida, cantidad, saldo, id_compra, id_proveedor,nro_colada_interna) values (?,?,?,?,?,?,?,?)";
      $params = [$idIngreso,$idMaterial,$itemData["id_unidad_medida"],$cantidadIngresar,$cantidadIngresar,$_GET['id'],$compraData["id_cuenta_proveedor"],$colada];
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 7 - Insertar ingreso_detalle:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
      }
      $q = $pdo->prepare($sql);
      $q->execute($params);
      if ($modoDebug == 1) {
        echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br>";
        echo "<hr>";
      }
      
      $count++;
    }
  }

  if ($modoDebug == 1) {
    echo "<h3>🔍 Verificando completitud de OC</h3>";
  }

  // Verificar si esta OC está completamente entregada
  $sql = "SELECT count(*) cant FROM compras_detalle where id_compra = ? and entregado < cantidad ";
  $params = [$_GET['id']];
  if ($modoDebug == 1) {
    echo "<b>✅ SQL 8 - Verificar completitud OC:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
  }
  $q = $pdo->prepare($sql);
  $q->execute($params);
  $data2 = $q->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug == 1) {
    echo "<b>Items pendientes:</b> " . $data2['cant'] . "<br>";
  }

  if ($data2['cant'] == 0) {
    if ($modoDebug == 1) {
      echo "<b>✅ OC completamente entregada - Marcando como Terminada (ID 7)</b><br>";
    }
    
    // Esta OC está completamente entregada - marcarla como "Terminada" (id 7)
    $sql = "UPDATE compras SET id_estado_compra = 7 WHERE id = ?";
    $params = [$_GET['id']];
    if ($modoDebug == 1) {
      echo "<b>✅ SQL 9 - Marcar OC como terminada:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
    }
    $q = $pdo->prepare($sql);
    $q->execute($params);
    if ($modoDebug == 1) {
      echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br><br>";
    }

    // Actualizar estados de todos los pedidos_detalle afectados por el cambio de estado de compra
    $sqlAllItems = "SELECT DISTINCT pd.id FROM pedidos_detalle pd INNER JOIN compras_detalle cd ON pd.id_pedido = (SELECT id_pedido FROM compras WHERE id = ?) AND pd.id_material = cd.id_material INNER JOIN compras c ON c.id = cd.id_compra WHERE cd.id_compra = ?";
    $paramsAllItems = [$_GET['id'], $_GET['id']];
    if ($modoDebug == 1) {
      echo "<b>✅ SQL 10 - Obtener items afectados:</b><br>" . debugQuery($pdo, $sqlAllItems, $paramsAllItems) . "<br>";
    }
    $qAllItems = $pdo->prepare($sqlAllItems);
    $qAllItems->execute($paramsAllItems);
    
    if ($modoDebug == 1) {
      $allItemsData = $qAllItems->fetchAll(PDO::FETCH_ASSOC);
      echo "<b>Items a actualizar:</b> " . count($allItemsData) . "<br>";
      echo "<pre>" . print_r($allItemsData, true) . "</pre>";
      
      // Reiniciar consulta
      $qAllItems = $pdo->prepare($sqlAllItems);
      $qAllItems->execute($paramsAllItems);
    }
    
    while ($item = $qAllItems->fetch(PDO::FETCH_ASSOC)) {
      if ($modoDebug == 1) {
        echo "<b>🔄 Actualizando estado pedido_detalle ID:</b> " . $item['id'] . "<br>";
      }
      actualizarEstadoPedidoDetalle($pdo, $item['id']);
    }

    // Ahora verificar si TODAS las OC del pedido están terminadas (estado 7 u 8)
    $sqlVerificarPedido = "SELECT count(*) cant_pendientes FROM compras WHERE id_pedido = ? AND id_estado_compra NOT IN (7, 8)";
    $paramsVerificarPedido = [$compraData['id_pedido']];
    if ($modoDebug == 1) {
      echo "<b>✅ SQL 11 - Verificar OC pendientes:</b><br>" . debugQuery($pdo, $sqlVerificarPedido, $paramsVerificarPedido) . "<br>";
    }
    $qVerificarPedido = $pdo->prepare($sqlVerificarPedido);
    $qVerificarPedido->execute($paramsVerificarPedido);
    $dataPedido = $qVerificarPedido->fetch(PDO::FETCH_ASSOC);
    
    if ($modoDebug == 1) {
      echo "<h3>🔍 Verificando si pedido puede finalizarse</h3>";
      echo "<b>Parámetros:</b> [$idPedido]<br>";
      echo "<b>OC pendientes:</b> " . $dataPedido['cant_pendientes'] . "<br>";
    }
    
    // También verificar que no se puedan crear más OC (todos los items activos del pedido están comprados)
    $sqlVerificarComprado = "SELECT count(*) cant_sin_comprar FROM pedidos_detalle pd WHERE pd.id_pedido = ? AND pd.cancelado = 0 AND pd.cantidad > pd.comprado";
    $paramsVerificarComprado = [$idPedido];
    if ($modoDebug == 1) {
      echo "<b>✅ SQL 12 - Verificar items sin comprar:</b><br>" . debugQuery($pdo, $sqlVerificarComprado, $paramsVerificarComprado) . "<br>";
    }
    $qVerificarComprado = $pdo->prepare($sqlVerificarComprado);
    $qVerificarComprado->execute($paramsVerificarComprado);
    $dataComprado = $qVerificarComprado->fetch(PDO::FETCH_ASSOC);
    
    if ($modoDebug == 1) {
      echo "<b>Items sin comprar:</b> " . $dataComprado['cant_sin_comprar'] . "<br>";
    }
    
    // Si no hay OC pendientes Y no se pueden crear más OC, el pedido pasa a "Terminado"
    if ($dataPedido['cant_pendientes'] == 0 && $dataComprado['cant_sin_comprar'] == 0) {
      if ($modoDebug == 1) {
        echo "<b>🎉 PEDIDO COMPLETAMENTE TERMINADO - Cambiando a estado 5</b><br>";
      }
      
      $sql = "UPDATE pedidos set id_estado = 5 where id = ?";
      $params = [$idPedido];
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 13 - Finalizar pedido:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
      }
      $q = $pdo->prepare($sql);
      $q->execute($params);
      if ($modoDebug == 1) {
        echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br>";
      }
    } else {
      if ($modoDebug == 1) {
        echo "<b>⏳ Pedido AÚN NO completamente terminado (pendientes: " . $dataPedido['cant_pendientes'] . ", sin comprar: " . $dataComprado['cant_sin_comprar'] . ")</b><br>";
      }
    }
  } else {
    if ($modoDebug == 1) {
      echo "<b>⏳ OC AÚN NO completamente entregada (" . $data2['cant'] . " items pendientes)</b><br>";
    }
  }

  if ($modoDebug == 1) {
    echo "<h3>📝 Registrando en logs</h3>";
  }

  $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Recepción de items de orden de compra','Compras','verCompra.php?id=$id')";
  $params = array($_SESSION['user']['id']);
  if ($modoDebug == 1) {
    echo "<b>✅ SQL 14 - Registrar log:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
  }
  $q = $pdo->prepare($sql);
  $q->execute($params);
  if ($modoDebug == 1) {
    echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br>";
  }

  // Si llegamos aquí, todo salió bien
  $transaccionExitosa = true;
  
  if ($modoDebug == 1) {
    echo "<h3>✅ PROCESO COMPLETADO EXITOSAMENTE</h3>";
  }
  
} catch (Exception $e) {
  // Error en el procesamiento
  if ($modoDebug == 1) {
    echo "<h3>❌ ERROR EN EL PROCESAMIENTO</h3>";
    echo "<b>Mensaje:</b> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<b>Línea:</b> " . $e->getLine() . "<br>";
    echo "<b>Archivo:</b> " . $e->getFile() . "<br>";
  }
  $transaccionExitosa = false;
}

// Finalizar transacción
if ($modoDebug == 1) {
  // En modo debug, siempre rollback para no afectar la BD
  echo "<h3>🔄 ROLLBACK - Transacción revertida en modo debug</h3>";
  $pdo->rollback();
  echo "<p><b>⚠️ IMPORTANTE:</b> Todos los cambios han sido revertidos para mantener integridad en modo debug.</p>";
} else {
  // En modo normal, commit solo si todo salió bien
  if ($transaccionExitosa) {
    $pdo->commit();
    if ($modoDebug == 1) {
      echo "<h3>✅ COMMIT - Transacción confirmada exitosamente</h3>";
    }
  } else {
    $pdo->rollback();
    if ($modoDebug == 1) {
      echo "<h3>🔄 ROLLBACK - Transacción revertida por errores</h3>";
    }
  }
}

Database::disconnect();

if ($modoDebug == 0) {
  header("Location: listarCompras.php");
} else {
  echo "<h3>🔚 PROCESO COMPLETADO</h3>";
  echo "<p><b>En modo normal se redirigiría a:</b> listarCompras.php</p>";
}
