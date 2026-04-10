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

$destino = isset($_GET['destino']) ? (int)$_GET['destino'] : 0;
if (isset($_GET['reservado']) && !isset($_GET['destino'])) {
    $destino = ($_GET['reservado'] == 1) ? 1 : 0;
}

// Validar que la compra esté en un estado que permita ingreso
$sqlEstado = "SELECT id_estado_compra, ec.estado FROM compras c LEFT JOIN estados_compra ec ON ec.id = c.id_estado_compra WHERE c.id = ?";
$qEstado = $pdo->prepare($sqlEstado);
$qEstado->execute([$id]);
$estadoData = $qEstado->fetch(PDO::FETCH_ASSOC);

if (!$estadoData) {
  Database::disconnect();
  if ($modoDebug == 0) {
    header("Location: listarCompras.php");
  } else {
    echo "<h3>DEBUG: Compra no encontrada</h3>";
  }
  exit();
}

// Estados permitidos para ingreso: 3 (Enviada), 6 (Entrega parcial)
$estadosPermitidos = [3, 6];
if (!in_array((int)$estadoData['id_estado_compra'], $estadosPermitidos)) {
  Database::disconnect();
  if ($modoDebug == 1) {
    echo "<h3>❌ ERROR: Estado no permitido para ingreso</h3>";
    echo "<b>Estado actual:</b> " . $estadoData['estado'] . " (ID: " . $estadoData['id_estado_compra'] . ")<br>";
    echo "<b>Estados permitidos:</b> Enviada (3), Entrega parcial (6)<br>";
    exit();
  } else {
    $_SESSION['flash_message'] = [
      'type' => 'error', 
      'message' => 'No se puede ingresar material para esta orden de compra. Estado actual: ' . $estadoData['estado'] . '. Estados permitidos: Enviada, Entrega parcial.'
    ];
    header("Location: listarCompras.php");
    exit();
  }
}

// Iniciar transacción
$pdo->beginTransaction();
$transaccionExitosa = false;

if ($modoDebug == 1) {
  echo "<h2>🐛 MODO DEBUG ACTIVADO - marcarItemsEntregadoCompra.php</h2>";
  echo "<h3>⚙️ TRANSACCIÓN INICIADA</h3>";
  echo "<h3>Parámetros recibidos:</h3>";
  echo "<b>ID Compra:</b> " . htmlspecialchars($id) . "<br>";
  echo "<b>Destino:</b> " . $destino . " (0=Stock, 1=Reserva, 2=Obra)<br>";
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
$idEgresoReserva = 0;

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
      $sqlItem = "SELECT cd.id, cd.id_material, cd.cantidad, cd.id_unidad_medida, cd.entregado, cd.precio, pd.id as idPedidoDetalle FROM compras_detalle cd INNER JOIN pedidos_detalle pd ON pd.id_pedido = ? AND pd.id_material = cd.id_material AND pd.cancelado = 0 WHERE cd.id = ?";
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
      $precioMaterial = $itemData['precio'];

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
      $q->execute([$cantidadIngresar, $idCompraDetalle]);
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
      $q->execute([$_GET['id']]);
      if ($modoDebug == 1) {
        echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br><br>";
      }
      
      if ($count == 1) {
        $rutaDocumento = isset($_POST['ruta_documento']) && !empty($_POST['ruta_documento']) ? $_POST['ruta_documento'] : "null";
        
        $sql = "INSERT into ingresos (fecha_hora, id_tipo_ingreso, nro, id_cuenta_recibe, lugar_entrega, observaciones, fecha_remito, nro_remito, ruta_documento) values (now(),1,?,?,?,?,?,?,?)";
        $params = [$idPedido,$compraData["id_cuenta_recibe"],$compraData["lugar_entrega"],$compraData["comentarios"],$_POST['fecha_remito'],$_POST['nro_remito'],$rutaDocumento];

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
      
      $sql3 = "SELECT s.nro_sitio,s.nro_subsitio,p.nro, em.empresa from pedidos pe inner join proyectos p on p.id = pe.id_proyecto inner join sitios s on s.id = p.id_sitio LEFT JOIN empresas em ON em.id = s.id_empresa where pe.id = ? ";

      $q3 = $pdo->prepare($sql3);
      $q3->execute([$idPedido]);
      $data3 = $q3->fetch(PDO::FETCH_ASSOC);
      $empresa_corta = !empty($data3['empresa']) ? ' ('.substr($data3['empresa'], 0, 4).')' : '';
      $colada = $data3['nro_sitio']."/".$data3['nro_subsitio']."/".$data3['nro']."-".$count.$empresa_corta;
            
      $sql = "INSERT INTO ingresos_detalle 
              (id_ingreso, id_material, id_unidad_medida, cantidad, cantidad_egresada, saldo, 
              id_compra, id_proveedor, nro_colada_interna) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

      $params = [
          $idIngreso,                          // 1 - id_ingreso
          $idMaterial,                         // 2 - id_material
          $itemData["id_unidad_medida"],        // 3 - id_unidad_medida
          $cantidadIngresar,                   // 4 - cantidad
          0,                                   // 5 - cantidad_egresada
          $cantidadIngresar,                   // 6 - saldo
          $_GET['id'],                         // 7 - id_compra
          $compraData["id_cuenta_proveedor"],  // 8 - id_proveedor
          $colada                              // 9 - nro_colada_interna
      ];

      if ($modoDebug == 1) {
          echo "<b>✅ SQL 6 - Insertar ingresos_detalle:</b><br>";
          echo "<pre>Parámetros (" . count($params) . "):\n" . print_r($params, true) . "</pre>";
      }

      $q = $pdo->prepare($sql);
      $q->execute($params);

      $idIngresoDetalleReal = $pdo->lastInsertId();

      if ($modoDebug == 1) {
          echo "<b>ID ingresos_detalle creado:</b> $idIngresoDetalleReal<br><br>";
      }

      if ($idComputo) {
        
        $sql = "UPDATE computos_detalle SET comprado = comprado - ? WHERE id_computo = ? AND id_material = ? AND cancelado = 0";
        $q = $pdo->prepare($sql);
        $q->execute([$cantidadIngresar, $idComputo, $idMaterial]);

            if ($destino == 1) {
                $sql = "UPDATE computos_detalle SET reservado = reservado + ? WHERE id_computo = ? AND id_material = ? AND cancelado = 0";
                $q = $pdo->prepare($sql);
                $q->execute([$cantidadIngresar, $idComputo, $idMaterial]);
            }
      }
      if ($destino > 0) {
        
            if ($idEgresoReserva == 0) {
            
            $id_sitio_destino = null;
            $id_tarea = null;
            $id_proyecto = null;

            if ($idComputo) {
                $sqlInfo = "SELECT t.id_proyecto, p.id_sitio, t.id AS id_tarea FROM computos c INNER JOIN tareas t ON t.id = c.id_tarea INNER JOIN proyectos p ON p.id = t.id_proyecto WHERE c.id = ?";
                $qInfo = $pdo->prepare($sqlInfo);
                $qInfo->execute([$idComputo]);
                $info = $qInfo->fetch(PDO::FETCH_ASSOC);
                
                if($info){
                    $id_sitio_destino = $info['id_sitio'];
                    $id_tarea = $info['id_tarea'];
                    $id_proyecto = $info['id_proyecto'];
                }
            } 
            
            if (empty($id_sitio_destino)) {
                $sqlInfo = "SELECT p.id as id_proyecto, p.id_sitio FROM pedidos pe INNER JOIN proyectos p ON p.id = pe.id_proyecto WHERE pe.id = ?";
                $qInfo = $pdo->prepare($sqlInfo);
                $qInfo->execute([$idPedido]);
                $info = $qInfo->fetch(PDO::FETCH_ASSOC);

                if($info){
                    $id_sitio_destino = $info['id_sitio'];
                    $id_proyecto = $info['id_proyecto'];
                }
            }

                $obsEgreso = ($destino == 1) ? 'Reserva desde ingreso OC' : 'Directo a obra desde OC';

                $sqlEgresoCab = "INSERT INTO egresos (fecha_hora, id_tipo_egreso, nro, id_cuenta_retira, id_sitio_destino, id_tarea, id_proyecto, observaciones) VALUES (NOW(), 2, ?, ?, ?, ?, ?, ?)";
                $stmtCab = $pdo->prepare($sqlEgresoCab);
                $stmtCab->execute([$idPedido, $compraData['id_cuenta_recibe'], $id_sitio_destino, $id_tarea, $id_proyecto, $obsEgreso]);
                $idEgresoReserva = $pdo->lastInsertId();
            }

            $cantReservada = ($destino == 1) ? $cantidadIngresar : 0;
            $cantEfectivizada = ($destino == 2) ? $cantidadIngresar : 0;
            $subtotalEgreso = $precioMaterial * $cantidadIngresar;

            $sqlInsEgresoDet = "INSERT INTO egresos_detalle 
                                (id_egreso, id_material, id_detalle_ingreso, id_unidad_medida, cantidad, cantidad_reservada, cantidad_efectivizada, precio, subtotal) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $paramsDet = [$idEgresoReserva, $idMaterial, $idIngresoDetalleReal, $itemData['id_unidad_medida'], $cantidadIngresar, $cantReservada, $cantEfectivizada, $precioMaterial, $subtotalEgreso];
            
            $stmtInsEgresoDet = $pdo->prepare($sqlInsEgresoDet);
            $stmtInsEgresoDet->execute($paramsDet);
            $sqlUpdIngreso = "UPDATE ingresos_detalle 
                          SET cantidad_egresada = cantidad_egresada + ?, 
                              saldo = saldo - ? 
                          WHERE id = ?";
        $qUpd = $pdo->prepare($sqlUpdIngreso);
        $qUpd->execute([$cantidadIngresar, $cantidadIngresar, $idIngresoDetalleReal]);
      }
      
      if ($destino == 1) {
        $sql = "UPDATE pedidos_detalle SET reservado = reservado + ? WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$cantidadIngresar, $idPedidoDetalle]);
      }
      
      $count++;
    }
  }

  if ($modoDebug == 1) {
    echo "<h3>🔍 Verificando completitud de OC</h3>";
  }

  // Verificar si esta OC está completamente entregada (excluyendo conceptos cancelados)
  $sql = "SELECT count(*) cant FROM compras_detalle cd 
          INNER JOIN pedidos_detalle pd ON pd.id_pedido = (SELECT id_pedido FROM compras WHERE id = ?) 
          AND pd.id_material = cd.id_material 
          WHERE cd.id_compra = ? AND pd.cancelado = 0 AND cd.entregado < cd.cantidad";
  $params = [$_GET['id'], $_GET['id']];
  if ($modoDebug == 1) {
    echo "<b>✅ SQL 8 - Verificar completitud OC (excluyendo cancelados):</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
  }
  $q = $pdo->prepare($sql);
  $q->execute($params);
  $data2 = $q->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug == 1) {
    echo "<b>Items pendientes (no cancelados):</b> " . $data2['cant'] . "<br>";
    
    // Información adicional de debug
    $sqlDebug = "SELECT cd.id, cd.id_material, cd.cantidad, cd.entregado, pd.cancelado, m.concepto 
                 FROM compras_detalle cd 
                 INNER JOIN pedidos_detalle pd ON pd.id_pedido = (SELECT id_pedido FROM compras WHERE id = ?) 
                 AND pd.id_material = cd.id_material 
                 INNER JOIN materiales m ON m.id = cd.id_material
                 WHERE cd.id_compra = ?";
    $paramsDebug = [$_GET['id'], $_GET['id']];
    echo "<b>🔍 DETALLE DE TODOS LOS CONCEPTOS:</b><br>" . debugQuery($pdo, $sqlDebug, $paramsDebug) . "<br>";
    
    $qDebug = $pdo->prepare($sqlDebug);
    $qDebug->execute($paramsDebug);
    echo "<table border='1'><tr><th>ID</th><th>Material</th><th>Concepto</th><th>Cantidad</th><th>Entregado</th><th>Cancelado</th><th>Estado</th></tr>";
    while ($rowDebug = $qDebug->fetch(PDO::FETCH_ASSOC)) {
      $estado = $rowDebug['cancelado'] ? 'CANCELADO' : ($rowDebug['entregado'] >= $rowDebug['cantidad'] ? 'COMPLETO' : 'PENDIENTE');
      $color = $rowDebug['cancelado'] ? '#ffcccc' : ($rowDebug['entregado'] >= $rowDebug['cantidad'] ? '#ccffcc' : '#ffffcc');
      echo "<tr style='background-color: $color'>";
      echo "<td>" . $rowDebug['id'] . "</td>";
      echo "<td>" . $rowDebug['id_material'] . "</td>";
      echo "<td>" . htmlspecialchars($rowDebug['concepto']) . "</td>";
      echo "<td>" . $rowDebug['cantidad'] . "</td>";
      echo "<td>" . $rowDebug['entregado'] . "</td>";
      echo "<td>" . ($rowDebug['cancelado'] ? 'SÍ' : 'NO') . "</td>";
      echo "<td>" . $estado . "</td>";
      echo "</tr>";
    }
    echo "</table><br>";
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

    // Verificar si el pedido puede marcarse como terminado
    verificarYActualizarEstadoPedido($pdo, $idPedido, $modoDebug == 1);
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
?>