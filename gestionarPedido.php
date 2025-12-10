<?php
// Modo Debug habilitado para ver consultas y resultados
$modoDebug = 0;

require("config.php");
require_once("PHPMailer/class.phpmailer.php");
require_once("PHPMailer/class.smtp.php");

if (empty($_SESSION['user'])) {
  if ($modoDebug == 0) {
    header("Location: index.php");
    die("Redirecting to index.php");
  } else {
    echo "<h3>DEBUG: Usuario no logueado (redireccion deshabilitada)</h3>";
  }
}

require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  if ($modoDebug == 0) {
    header("Location: listarPedidos.php");
  } else {
    echo "<h3>DEBUG: ID no proporcionado (redireccion deshabilitada)</h3>";
    exit();
  }
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlEstadoPedido = "SELECT id_estado FROM pedidos WHERE id = ?";
$qEstadoPedido = $pdo->prepare($sqlEstadoPedido);
$qEstadoPedido->execute([$id]);
$estadoPedido = $qEstadoPedido->fetch(PDO::FETCH_ASSOC);
$estadoPedidoId = $estadoPedido ? (int)$estadoPedido['id_estado'] : null;
Database::disconnect();

if (!in_array($estadoPedidoId, [3, 4], true)) {
  header("Location: listarPedidos.php");
  exit;
}

if (!empty($_POST)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Iniciar transacción
  $pdo->beginTransaction();
  $transaccionExitosa = false;

  if ($modoDebug == 1) {
    echo "<h2>🐛 MODO DEBUG ACTIVADO - gestionarPedido.php</h2>";
    echo "<h3>⚙️ TRANSACCIÓN INICIADA</h3>";
    echo "<h3>Parámetros recibidos:</h3>";
    echo "<b>ID Pedido:</b> " . htmlspecialchars($id) . "<br>";
    echo "<h3>POST Data:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
  }

  $error_message = '';
  if (isset($_POST['id_moneda']) && $_POST['id_moneda'] == 1) {
    if (empty($_POST['tipo_cambio_dia']) || !is_numeric($_POST['tipo_cambio_dia']) || (float)$_POST['tipo_cambio_dia'] <= 0) {
      $error_message = 'Para la moneda USD, es obligatorio ingresar un Tipo de Cambio válido y mayor a cero.';
    }
  }

  if (empty($error_message)) {
    require_once('funciones.php');
    
    $sql_pedido_detalle = "SELECT d.id, d.id_material, m.concepto, d.cantidad, d.id_unidad_medida, m.peso_metro, m.largo FROM pedidos_detalle d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_pedido = ?";
    $q_pedido_detalle = $pdo->prepare($sql_pedido_detalle);
    $q_pedido_detalle->execute([$id]);
    
    $total = 0;
    $items_para_comprar = [];

    while ($row = $q_pedido_detalle->fetch(PDO::FETCH_ASSOC)) {
      $cantidadPedir = $_POST['cantidad_'.$row['id']] ?? 0;
      $precioUnitario = $_POST['precio_'.$row['id']] ?? 0;
      $precioKg = $_POST['preciokg_'.$row['id']] ?? 0;
      
      if ($modoDebug == 1) {
        echo "<b>📊 PROCESANDO ITEM:</b> " . htmlspecialchars($row['concepto']) . " (ID: " . $row['id_material'] . ")<br>";
        echo "<b>Cantidad a pedir:</b> " . $cantidadPedir . "<br>";
        echo "<b>Precio unitario:</b> $" . number_format($precioUnitario, 2) . "<br>";
        echo "<b>Precio por kg:</b> $" . number_format($precioKg, 2) . "<br>";
      }
      
      if ($cantidadPedir > 0 && ($precioUnitario > 0 || $precioKg > 0)) {
        $precioParaGuardar = $precioUnitario;
        
        if ($precioKg > 0) {
          // Cuando es precio por kilo, calcular subtotal pero NO guardar precio unitario
          $peso_total_unitario = (float)$row['peso_metro']; // El peso_metro ya está en kg
          
          if ($modoDebug == 1) {
            echo "<b>🧮 CÁLCULO POR KILOGRAMO:</b><br>";
            echo "<b>Peso por metro:</b> " . $row['peso_metro'] . " kg/m<br>";
            echo "<b>Peso unitario inicial:</b> " . number_format($peso_total_unitario, 6) . " kg<br>";
          }
          
          $largo = isset($row['largo']) ? (float)$row['largo'] : 0;
          if ($largo > 0) {
            $largo_metros = $largo / 1000; // Convertir de mm a metros
            $peso_total_unitario = $peso_total_unitario * $largo_metros;
            
            if ($modoDebug == 1) {
              echo "<b>Largo:</b> " . $largo . " mm = " . number_format($largo_metros, 3) . " m<br>";
              echo "<b>Peso total unitario final:</b> " . number_format($peso_total_unitario, 6) . " kg<br>";
            }
          }
          
          $precioUnitarioCalculado = $precioKg * $peso_total_unitario;
          $subtotal = $cantidadPedir * $precioUnitarioCalculado;
          $precioParaGuardar = 0; // No guardar precio unitario cuando es por kilo
          
          if ($modoDebug == 1) {
            echo "<b>Precio unitario calculado:</b> $" . number_format($precioUnitarioCalculado, 4) . "<br>";
            echo "<b>Subtotal:</b> " . $cantidadPedir . " × $" . number_format($precioUnitarioCalculado, 4) . " = $" . number_format($subtotal, 2) . "<br>";
          }
        } else {
          $subtotal = $cantidadPedir * $precioUnitario;
          
          if ($modoDebug == 1) {
            echo "<b>🧮 CÁLCULO POR PRECIO UNITARIO:</b><br>";
            echo "<b>Subtotal:</b> " . $cantidadPedir . " × $" . number_format($precioUnitario, 2) . " = $" . number_format($subtotal, 2) . "<br>";
          }
        }
        $total += $subtotal;
        
        if ($modoDebug == 1) {
          echo "<b>Total acumulado:</b> $" . number_format($total, 2) . "<br><br>";
        }
        
        $descuentoItem = $_POST['descuento_'.$row['id']] ?? 0;
        $fechaEntregaItem = $_POST['fecha_entrega_'.$row['id']] ?? $_POST['fecha_entrega'];
        
        $items_para_comprar[] = [
          'id_material' => $row['id_material'],
          'cantidad' => $cantidadPedir,
          'id_unidad_medida' => $row['id_unidad_medida'],
          'precio' => $precioParaGuardar,
          'precio_kg' => $precioKg,
          'subtotal' => $subtotal,
          'descuento' => $descuentoItem,
          'fecha_entrega' => $fechaEntregaItem,
          'id_pedido_detalle' => $row['id']
        ];
      }
    }

    if (!empty($items_para_comprar)) {
      try {
      $id_parametro_limite = ($_POST['id_moneda'] == 1) ? 11 : 10;
      
      $sql_p = "SELECT valor FROM parametros WHERE id = ?";
      $q_p = $pdo->prepare($sql_p);
      $q_p->execute([$id_parametro_limite]);
      $data_p = $q_p->fetch(PDO::FETCH_ASSOC);
      
      $monto_limite = $data_p ? (float)$data_p['valor'] : 0;
      
      $id_estado_compra = 1; 
      $mensaje_extra_email = "";
      
      if ($total < $monto_limite) {
          $id_estado_compra = 3;
          $mensaje_extra_email = " (Aprobada Automáticamente por monto menor a límite)";
      }

      $iva = $total * 0.21;
      $nro_revision = 0; // Revisión inicial
      
      $sql = "INSERT INTO compras (id_pedido, id_cuenta_proveedor, fecha_emision, fecha_entrega, id_forma_pago, id_estado_compra, nro_oc, total, iva, comentarios, id_moneda, tipo_cambio_dia, comentarios_revision, descuento, nro_revision) VALUES (?,?,?,?,?, ?, ?, ?, ?,?,?,?,'Revisión Original',?,?)";
      $params = [$id, $_POST['id_cuenta_proveedor'], $_POST['fecha_emision'], $_POST['fecha_entrega'], $_POST['id_forma_pago'], $id_estado_compra, '', $total, $iva, $_POST['comentarios'], $_POST['id_moneda'], $_POST['tipo_cambio_dia'], $_POST['descuento'], $nro_revision];
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 1 - Crear orden de compra:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
      }
      $q = $pdo->prepare($sql);
      $q->execute($params);   
      
      $idCompra = $pdo->lastInsertId();
      
      $nroOC = $id . '/' . $idCompra;
      $sql = "UPDATE compras SET nro_oc = ? where id = ?";
      $params = [$nroOC, $idCompra];
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 2 - Actualizar número OC:</b><br>" . debugQuery($pdo, $sql, $params) . "<br>";
      }
      $q = $pdo->prepare($sql);
      $q->execute($params);
      if ($modoDebug == 1) {
        echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br><br>";
      }

      foreach ($items_para_comprar as $item) {
        $sql2 = "INSERT INTO compras_detalle(id_compra, id_material, cantidad, id_unidad_medida, precio, precio_kg, subtotal, descuento, fecha_entrega) VALUES (?,?,?,?,?,?,?,?,?)";
        $params2 = [$idCompra, $item['id_material'], $item['cantidad'], $item['id_unidad_medida'], $item['precio'], $item['precio_kg'], $item['subtotal'], $item['descuento'], $item['fecha_entrega']];
        if ($modoDebug == 1) {
          echo "<b>✅ SQL 3 - Insertar detalle compra (Material ID: " . $item['id_material'] . "):</b><br>" . debugQuery($pdo, $sql2, $params2) . "<br>";
        }
        $q2 = $pdo->prepare($sql2);
        $q2->execute($params2);

        $sql3 = "UPDATE pedidos_detalle SET comprado = ? WHERE id_pedido=? AND id_material=?";
        $params3 = [$item['cantidad'], $id, $item['id_material']];
        if ($modoDebug == 1) {
          echo "<b>✅ SQL 4 - Actualizar comprado en pedido_detalle:</b><br>" . debugQuery($pdo, $sql3, $params3) . "<br>";
        }
        $q3 = $pdo->prepare($sql3);
        $q3->execute($params3);
        if ($modoDebug == 1) {
          echo "<b>Filas afectadas:</b> " . $q3->rowCount() . "<br><br>";
        }
        
        // Actualizar estado del pedido_detalle después de crear la compra
        if (isset($item['id_pedido_detalle'])) {
          require_once('funciones.php');
          actualizarEstadoPedidoDetalle($pdo, $item['id_pedido_detalle']);
        }
        
        $sql4 = "SELECT cd.id id from computos_detalle cd inner join computos c on c.id = cd.id_computo inner join pedidos p on p.id_computo = c.id where p.id = ? and cd.cancelado = 0 and cd.id_material = ? ";
        $params4 = [$id, $item['id_material']];
        if ($modoDebug == 1) {
          echo "<b>✅ SQL 5 - Buscar computo_detalle:</b><br>" . debugQuery($pdo, $sql4, $params4) . "<br>";
        }
        $q4 = $pdo->prepare($sql4);
        $q4->execute($params4);
        $data4 = $q4->fetch(PDO::FETCH_ASSOC);
        
        if ($data4) {
          $sql5 = "UPDATE computos_detalle set comprado = ? WHERE id = ?";
          $params5 = [$item['cantidad'], $data4['id']];
          if ($modoDebug == 1) {
            echo "<b>✅ SQL 6 - Actualizar comprado en computos_detalle:</b><br>" . debugQuery($pdo, $sql5, $params5) . "<br>";
          }
          $q5 = $pdo->prepare($sql5);
          $q5->execute($params5);
          if ($modoDebug == 1) {
            echo "<b>Filas afectadas:</b> " . $q5->rowCount() . "<br><br>";
          }
        }
      }

      $sql_log = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva orden de compra','Compras','verCompra.php?id=$idCompra')";
      $params_log = [$_SESSION['user']['id']];
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 7 - Registrar log:</b><br>" . debugQuery($pdo, $sql_log, $params_log) . "<br>";
      }
      $q_log = $pdo->prepare($sql_log);
      $q_log->execute($params_log);
      if ($modoDebug == 1) {
        echo "<b>Filas afectadas:</b> " . $q_log->rowCount() . "<br><br>";
      }
      
      // Enviar notificaciones usando la función crearNotificacion
      $estado_texto = ($id_estado_compra == 3) ? "Aprobada automáticamente" : "Pendiente de Aprobación";
      $detalleNotificacion = "OC: " . $idCompra . "/" . $nro_revision . " - " . $estado_texto;
      $asuntoEmail = "Compras - Nueva OC " . $idCompra . "/" . $nro_revision . " (" . $estado_texto . ")";
      $cuerpoEmail = "Nueva compra generada en el sistema.\n";
      $cuerpoEmail .= "OC: " . $idCompra . "/" . $nro_revision . "\n";
      $cuerpoEmail .= "Estado: " . $estado_texto . "\n";
      $cuerpoEmail .= "Monto Total: $" . number_format($total, 2);
      
      if ($modoDebug == 1) {
        echo "<b>✅ SQL 8 - Crear notificaciones con crearNotificacion():</b><br>";
        echo "<b>Tipo Notificación:</b> 4<br>";
        echo "<b>ID Entidad:</b> " . $idCompra . "<br>";
        echo "<b>Detalle:</b> " . htmlspecialchars($detalleNotificacion) . "<br>";
        echo "<b>Asunto:</b> " . htmlspecialchars($asuntoEmail) . "<br>";
        echo "<b>Cuerpo:</b> " . htmlspecialchars($cuerpoEmail) . "<br><br>";
      }
      
      crearNotificacion($pdo, 4, $idCompra, $detalleNotificacion, $asuntoEmail, $cuerpoEmail);

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
      $error = "Error al procesar la orden de compra: " . $e->getMessage();
    }

    // Finalizar transacción
    if ($modoDebug == 1) {
      // En modo debug, siempre rollback para no afectar la BD
      echo "<h3>🔄 ROLLBACK - Transacción revertida en modo debug</h3>";
      $pdo->rollback();
      echo "<p><b>⚠️ IMPORTANTE:</b> Todos los cambios han sido revertidos para mantener integridad en modo debug.</p>";
      die();
    } else {
      // En modo normal, commit solo si todo salió bien
      if ($transaccionExitosa) {
        $pdo->commit();
        Database::disconnect();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => '¡Orden de Compra ' . $idCompra . '/' . $nro_revision . ' creada exitosamente!'];
        header("Location: listarCompras.php");
        exit();
      } else {
        $pdo->rollback();
      }
    }

    Database::disconnect();

    } else {
      $error = "Debe ingresar al menos un concepto con cantidad mayor a 0 y precio.";
    }

  } else {
    $error = $error_message;
  }
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT pe.id, pe.id_computo, pe.id_proyecto, DATE_FORMAT(pe.fecha, '%d/%m/%Y') AS fecha_formatted, pe.fecha, pe.lugar_entrega, pe.id_cuenta_recibe, pe.aprobado, c.id_tarea, c.id_cuenta_solicitante, c.nro_revision AS computo_revision, c.nro AS computo_numero, COALESCE(pc.id, pd.id) AS proyecto_id, COALESCE(pc.nombre, pd.nombre) AS proyecto_nombre, COALESCE(pc.nro, pd.nro) AS proyecto_nro, COALESCE(sc.nro_sitio, sd.nro_sitio) AS nro_sitio, COALESCE(sc.nro_subsitio, sd.nro_subsitio) AS nro_subsitio, cu.nombre AS cuenta_solicitante, cu2.nombre AS cuenta_recibe, pe.id_estado, ep.estado AS estado_pedido FROM pedidos pe LEFT JOIN computos c ON c.id = pe.id_computo LEFT JOIN tareas t ON t.id = c.id_tarea LEFT JOIN proyectos pc ON pc.id = t.id_proyecto LEFT JOIN sitios sc ON sc.id = pc.id_sitio LEFT JOIN proyectos pd ON pd.id = pe.id_proyecto LEFT JOIN sitios sd ON sd.id = pd.id_sitio LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante LEFT JOIN cuentas cu2 ON cu2.id = pe.id_cuenta_recibe LEFT JOIN estados_pedidos ep ON ep.id = pe.id_estado WHERE pe.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

$proyectoDisplay = '';
$codigoObra = '';
$tipoPedido = '';
if ($data) {
  $codigoObraPartes = array_filter([
    $data['nro_sitio'] ?? null,
    $data['nro_subsitio'] ?? null,
    $data['proyecto_nro'] ?? null
  ], function ($valor) {
    return $valor !== null && $valor !== '';
  });
  $codigoObra = !empty($codigoObraPartes) ? implode('-', $codigoObraPartes) : '';

  $tieneComputo = !empty($data['id_computo']);
  $tipoPedido = "Directo";
  if($tieneComputo){
    $tipoPedido = 'de Cómputo';
  }

  $proyectoDisplay = '';
  if (!empty($data['proyecto_id'])) {
    if (!empty($codigoObra) && !empty($data['proyecto_nombre'])) {
      $proyectoDisplay = $codigoObra . ': ' . $data['proyecto_nombre'];
    } elseif (!empty($codigoObra)) {
      $proyectoDisplay = $codigoObra;
    } elseif (!empty($data['proyecto_nombre'])) {
      $proyectoDisplay = $data['proyecto_nombre'];
    }
  }
}

Database::disconnect();?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      .form-control:disabled, 
      .form-control[readonly] {
        background-color: #e9ecef;
        opacity: 1;
      }
      
      .form-group {
        margin-bottom: 1rem;
      }
      
      .card-body {
        padding: 1.5rem;
      }
      
      #dataTables-example667 {
        width: 100% !important;
        font-size: 0.75rem;
        table-layout: fixed !important;
        border-collapse: collapse !important;
      }
      
      #dataTables-example667 th,
      #dataTables-example667 td {
        padding: 5px 4px !important;
        vertical-align: middle;
        font-size: 0.75rem;
        overflow: hidden;
        text-overflow: ellipsis;
        box-sizing: border-box !important;
      }
      
      #dataTables-example667 thead th {
        white-space: nowrap !important;
        padding: 6px 4px !important;
        font-size: 0.7rem;
        font-weight: 600;
        line-height: 1.2;
        background-color: #f8f9fa;
      }
      
      #dataTables-example667 th:nth-child(1),
      #dataTables-example667 td:nth-child(1) {
        width: 180px !important;
        min-width: 180px !important;
        max-width: 180px !important;
        white-space: normal;
        word-wrap: break-word;
      }
      
      #dataTables-example667 th:nth-child(2),
      #dataTables-example667 td:nth-child(2) {
        width: 85px !important;
        min-width: 85px !important;
        max-width: 85px !important;
      }
      
      #dataTables-example667 th:nth-child(3),
      #dataTables-example667 td:nth-child(3) {
        width: 85px !important;
        min-width: 85px !important;
        max-width: 85px !important;
      }
      
      #dataTables-example667 th:nth-child(4),
      #dataTables-example667 td:nth-child(4) {
        width: 90px !important;
        min-width: 90px !important;
        max-width: 90px !important;
      }
      
      #dataTables-example667 th:nth-child(5),
      #dataTables-example667 td:nth-child(5) {
        width: 90px !important;
        min-width: 90px !important;
        max-width: 90px !important;
      }
      
      #dataTables-example667 th:nth-child(6),
      #dataTables-example667 td:nth-child(6) {
        width: 60px !important;
        min-width: 60px !important;
        max-width: 60px !important;
        text-align: center;
      }
      
      #dataTables-example667 th:nth-child(7),
      #dataTables-example667 td:nth-child(7) {
        width: 65px !important;
        min-width: 65px !important;
        max-width: 65px !important;
        text-align: center;
      }
      
      #dataTables-example667 th:nth-child(8),
      #dataTables-example667 td:nth-child(8) {
        width: 80px !important;
        min-width: 80px !important;
        max-width: 80px !important;
        text-align: center;
      }
      
      #dataTables-example667 th:nth-child(9),
      #dataTables-example667 td:nth-child(9) {
        width: 75px !important;
        min-width: 75px !important;
        max-width: 75px !important;
        text-align: center;
      }
      
      #dataTables-example667 th:nth-child(10),
      #dataTables-example667 td:nth-child(10) {
        width: 95px !important;
        min-width: 95px !important;
        max-width: 95px !important;
      }
      
      #dataTables-example667 th:nth-child(11),
      #dataTables-example667 td:nth-child(11) {
        width: 80px !important;
        min-width: 80px !important;
        max-width: 80px !important;
      }
      
      #dataTables-example667 th:nth-child(12),
      #dataTables-example667 td:nth-child(12) {
        width: 80px !important;
        min-width: 80px !important;
        max-width: 80px !important;
      }
      
      #dataTables-example667 th:nth-child(13),
      #dataTables-example667 td:nth-child(13) {
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
        text-align: center;
      }
      
      #dataTables-example667 th:nth-child(14),
      #dataTables-example667 td:nth-child(14) {
        width: 90px !important;
        min-width: 90px !important;
        max-width: 90px !important;
      }
      
      /* Resto de celdas sin wrap */
      #dataTables-example667 tbody td {
        white-space: nowrap;
      }
      
      /* Excepto Concepto */
      #dataTables-example667 tbody td:nth-child(1) {
        white-space: normal;
      }
      
      /* Inputs compactos */
      #dataTables-example667 input.form-control {
        font-size: 0.75rem;
        padding: 0.25rem 0.35rem;
        height: 28px;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }
      
      /* Importante: Eliminar scrolls de DataTables */
      .dataTables_wrapper .dataTables_scrollHead,
      .dataTables_wrapper .dataTables_scrollBody {
        overflow: visible !important;
      }
      
      .dataTables_wrapper {
        overflow-x: auto;
      }
      
      .dataTables_scrollBody {
        overflow: visible !important;
      }
      
      .dataTables_scrollHead table,
      .dataTables_scrollBody table {
        width: 100% !important;
      }
      
      /* Controles de DataTable más compactos */
      .dataTables_length select,
      .dataTables_filter input {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
      }
      
      .dataTables_info,
      .dataTables_length,
      .dataTables_filter {
        font-size: 0.8rem;
      }
      
      h6 {
        font-weight: 600;
        margin-bottom: 1rem;
      }
      
      /* Reducir espacio en paginación */
      .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
      }
      
      /* Custom controls styling in DataTables toolbar */
      .dataTables_wrapper .dataTables_length,
      .dataTables_wrapper .dataTables_filter {
        display: inline-block;
        vertical-align: middle;
      }
      
      #custom-controls-container {
        display: inline-block;
        vertical-align: middle;
        flex: 1;
        max-width: calc(100% - 400px);
        margin: 0 20px;
        padding: 5px 0;
      }
      
      #custom-controls {
        display: flex !important;
        align-items: center;
        justify-content: flex-start;
        gap: 25px;
        margin: 0 !important;
        width: 100%;
        flex-wrap: nowrap;
      }
      
      #custom-controls .col-md-3 {
        flex: 0 0 auto;
        width: auto;
        padding: 0;
        margin: 0;
        min-width: 150px;
      }
      
      #custom-controls .form-label {
        font-size: 11px;
        font-weight: 500;
        margin-bottom: 3px;
        color: #666;
        display: block;
        white-space: nowrap;
      }
      
      #custom-controls .form-control {
        font-size: 11px !important;
        padding: 5px 8px !important;
        height: 30px !important;
        width: 130px;
        border: 1px solid #ccc;
        border-radius: 3px;
      }
      
      /* Ensure DataTables wrapper uses flexbox layout */
      .dataTables_wrapper .dataTables_length {
        float: none !important;
        margin-right: 10px;
      }
      
      .dataTables_wrapper .dataTables_filter {
        float: none !important;
        margin-left: 10px;
      }
      
      /* Make the top row of DataTables a flexbox */
      .dataTables_wrapper .dataTables_length,
      .dataTables_wrapper .dataTables_filter,
      #custom-controls-container {
        display: inline-flex;
        align-items: center;
      }
      
      /* Estilos para items cancelados */
      .table-secondary {
        background-color: #f8f9fa !important;
        opacity: 0.8;
      }
      
      .table-secondary td {
        text-decoration: line-through;
        color: #6c757d;
      }
      
      .table-secondary .badge-danger {
        text-decoration: none;
        font-size: 0.7rem;
        padding: 0.5rem 1rem;
      }
      
      .text-muted {
        color: #6c757d !important;
      }
      
      /* Estilos para las celdas combinadas */
      .cancelado-badge {
        display: inline-block;
        min-width: 120px;
      }
      
      /* Asegurar que las celdas ocultas no interfieran */
      td[style*="display: none"], td.hidden-cell {
        display: none !important;
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php');?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Gestión de Pedido y Nueva Orden de Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Información del Pedido <?=$tipoPedido." N° ".$id;?></h5><?php
                    if (isset($error)){?>
                      <div class="alert alert-danger"><?=$error;?></div><?php
                    }?>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#" id="form-unificado" onsubmit="return validarFormularioCompra();">
                    <div class="card-body">
                      <div class="row">
                        <div class="col-md-6">
                          <h6 class="mb-3">Datos del Pedido</h6>
                          <!--<div class="form-group row">
                            <label class="col-sm-4 col-form-label">Fecha Pedido</label>
                            <div class="col-sm-8"><input name="fecha" type="date" onfocus="this.showPicker()" value="<?=$data['fecha'];?>" class="form-control" disabled></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Proyecto</label>
                            <div class="col-sm-8">
                              <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single w-100" disabled="disabled">
                                <option value="">Seleccione...</option><?php
                                /*$pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from computos c inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio where c.id = ".$data['id_computo'];
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                  <option value='<?=$fila['id']?>' selected><?=$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']?></option><?php
                                }
                                Database::disconnect();*/?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Solicitante</label>
                            <div class="col-sm-8">
                              <select name="id_cuenta_solicitante" id="id_cuenta_solicitante" class="js-example-basic-single w-100" disabled>
                                <option value="">Seleccione...</option><?php
                                /*$pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_cuenta_solicitante']) {
                                      echo " selected ";
                                    }	
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();*/?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Lugar de Entrega</label>
                            <div class="col-sm-8"><input name="lugar_entrega" type="text" maxlength="199" class="form-control" value="<?=$data['lugar_entrega'];?>" disabled></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Recibe</label>
                            <div class="col-sm-8">
                              <select name="id_cuenta_recibe" id="id_cuenta_recibe" class="js-example-basic-single w-100">
                                <option value="">Seleccione...</option><?php
                                /*$pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_cuenta_recibe']) {
                                      echo " selected ";
                                    }
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();*/?>
                              </select>
                            </div>
                          </div> -->

                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label font-weight-bold">Fecha Pedido</label>
                            <div class="col-sm-8"><?=$data['fecha_formatted'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label font-weight-bold">Proyecto</label>
                            <div class="col-sm-8"><?=$proyectoDisplay;?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label font-weight-bold">Lugar de Entrega</label>
                            <div class="col-sm-8"><?=$data['lugar_entrega'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label font-weight-bold">Recibe</label>
                            <div class="col-sm-8"><?=$data['cuenta_recibe']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label font-weight-bold">Estado</label>
                            <div class="col-sm-8"><?=$data['estado_pedido'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label font-weight-bold">Solicitante</label>
                            <div class="col-sm-8"><?=$data['cuenta_solicitante']?></div>
                          </div>
                        </div><?php

                        if ($data['aprobado']==1 && tienePermiso(298)){?>
                          <div class="col-md-6">
                            <h6 class="mb-3">Datos de la Orden de Compra</h6>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Proveedor(*)</label>
                              <div class="col-sm-8">
                                <select name="id_cuenta_proveedor" id="id_cuenta_proveedor" class="js-example-basic-single w-100" required>
                                  <option value="">Seleccione...</option><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (5) and activo = 1 and anulado = 0";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                    <option value='<?=$fila['id']?>'><?=$fila['nombre']?></option><?php
                                  }
                                  Database::disconnect();?>
                                </select>
                              </div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Fecha Emisión(*)</label>
                              <div class="col-sm-8">
                                <input name="fecha_emision" type="date" onfocus="this.showPicker()" value="<?=date('Y-m-d');?>" class="form-control" required>
                              </div>
                            </div>

                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Moneda(*)</label>
                              <div class="col-sm-8">
                                <select name="id_moneda" id="id_moneda" class="js-example-basic-single w-100" required>
                                  <option value="">Seleccione...</option><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT id, moneda FROM monedas WHERE 1";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                    <option value='<?=$fila['id']?>'><?=$fila['moneda']?></option><?php
                                  }
                                  Database::disconnect();?>
                                </select>
                              </div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Tipo de Cambio <span id="tc_required_star" style="color:red; display: none;">(*)</span></label>
                              <div class="col-sm-8">
                                <input name="tipo_cambio_dia" id="tipo_cambio_dia" type="number" step="0.01" class="form-control">
                              </div>
                            </div>

                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Forma de Pago(*)</label>
                              <div class="col-sm-8">
                                <select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single w-100" required>
                                  <option value="">Seleccione...</option><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT `id`, `forma_pago` FROM `formas_pago` WHERE 1";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                    <option value='<?=$fila['id']?>'><?=$fila['forma_pago']?></option><?php
                                  }
                                  Database::disconnect();?>
                                </select>
                              </div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Comentarios</label>
                              <div class="col-sm-8">
                                <textarea name="comentarios" class="form-control" rows="2"></textarea>
                              </div>
                            </div>
                          </div><?php
                        }?>
                      </div>
                      <hr class="mt-4 mb-4">
                      <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3">Detalle de Conceptos</h6>
                          <div id="custom-controls" class="row mb-3" style="display: none; text-align: center;">
                            <div class="col-md-6" style="text-align: center;">
                              <label class="">Fecha Entrega General:</label>
                              <input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?=$data["fecha"]?>" class="form-control d-inline-block" style="font-size: 12px;">
                            </div>
                            <div class="col-md-6" style="text-align: center;">
                              <label class="">Descuento General (%):</label>
                              <input name="descuento" type="number" step="0.01" class="form-control d-inline-block" style="font-size: 12px;">
                            </div>
                          </div>
                          <div class="table-responsive">
                            <table class="display" id="dataTables-example667" style="width:100%">
                              <thead>
                                <tr>
                                  <th>Concepto</th>
                                  <th>Fec. Necesidad</th>
                                  <th>Fec. Últ. Compra</th>
                                  <th>Último Precio</th>
                                  <th>Requerido</th>
                                  <th>Stock</th>
                                  <th>Reserv.</th>
                                  <th>Comprado</th><?php
                                  if ($data['aprobado']==1 && tienePermiso(298)){?>
                                    <th>Cant. Solic.</th>
                                    <th>Cant. Pedir</th>
                                    <th>P. Unit.</th>
                                    <th>P. x Kg</th>
                                    <th>Desc. %</th>
                                    <th>F. Entrega</th><?php
                                  }?>
                                </tr>
                              </thead>
                              <tbody><?php
                                $pdo = Database::connect();
                                $sql = " SELECT pd.id, m.concepto, pd.cantidad, date_format(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad, u.unidad_medida,pd.id_material,pd.reservado,pd.comprado,pd.cancelado FROM pedidos_detalle pd inner join materiales m on m.id = pd.id_material inner join unidades_medida u on u.id = pd.id_unidad_medida WHERE pd.id_pedido = ".$id;
                                foreach ($pdo->query($sql) as $row) {
                                  $id_material=(int)$row["id_material"];

                                  $cantidadComparar = $row["cantidad"] - $row["reservado"] - $row["comprado"];

                                  $sql2 = "SELECT d.precio,date_format(c.fecha_emision,'%d/%m/%y') fecha_emision FROM compras_detalle d inner join compras c on c.id = d.id_compra WHERE d.id_material = $id_material ORDER BY c.id DESC LIMIT 0,1 ";
                                  $q2 = $pdo->prepare($sql2);
                                  $q2->execute();
                                  $data2 = $q2->fetch(PDO::FETCH_ASSOC);

                                  $fecha_emision="";
                                  if (!empty($data2['fecha_emision'])) {
                                    $fecha_emision=$data2['fecha_emision'];
                                  }

                                  $precio="";
                                  if (!empty($data2['precio'])) {
                                    $precio="$".number_format($data2['precio'],2);
                                  }

                                  $sql = "SELECT SUM(id.saldo) AS disponible FROM ingresos_detalle id WHERE id_material = ? ";
                                  $q = $pdo->prepare($sql);
                                  $q->execute([$id_material]);
                                  $data3 = $q->fetch(PDO::FETCH_ASSOC);

                                  $disponible=0;
                                  if (empty($data3['disponible'])) {
                                    $disponible=$data3['disponible'];
                                  }

                                  ?>

                                  <tr data-id="<?=$row["id"]?>" <?=($row["cancelado"] == 1) ? 'class="table-secondary"' : ''?>>
                                    <td><?=$row["concepto"]?></td>
                                    <td><?=$row["fecha_necesidad"]?></td>
                                    <td><?=$fecha_emision?></td>
                                    <td><?=$precio?></td>	
                                    <td><?=$row["cantidad"] .' '.$row["unidad_medida"]?></td>
                                    <td><?=$disponible?></td>
                                    <td><?=$row["reservado"]?></td>
                                    <td><?=$row["comprado"]?></td>
                                    <td><?=$cantidadComparar?></td><?php
                                    if ($data['aprobado']==1 && tienePermiso(298)) {?>
                                      <td class="cantidad-col" data-cancelado="<?=$row["cancelado"]?>" data-cantidad="<?=$cantidadComparar?>" data-id="<?=$row["id"]?>" data-fecha="<?=$data['fecha']?>"><?php
                                        if ($cantidadComparar > 0 && $row["cancelado"] != 1) {?>
                                          <input name="cantidad_<?=$row["id"]?>" type="number" step="0.01" min="0" max="<?=$cantidadComparar?>" class="form-control cantidad-input" value="<?=$cantidadComparar?>"><?php
                                        } elseif ($row["cancelado"] == 1) { ?>
                                          <span class="badge badge-danger cancelado-badge">Concepto cancelado</span><?php
                                        }?>
                                      </td>
                                      <td class="precio-col" data-cancelado="<?=$row["cancelado"]?>"><?php
                                        if ($cantidadComparar > 0 && $row["cancelado"] != 1) {?>
                                          <input name="precio_<?=$row["id"]?>" type="number" step="0.01" class="form-control precio-input" value="0"><?php
                                        }?>
                                      </td>
                                      <td class="preciokg-col" data-cancelado="<?=$row["cancelado"]?>"><?php
                                        if ($cantidadComparar > 0 && $row["cancelado"] != 1) {?>
                                          <input name="preciokg_<?=$row["id"]?>" type="number" step="0.01" class="form-control preciokg-input" value="0"><?php
                                        }?>
                                      </td>
                                      <td class="descuento-col" data-cancelado="<?=$row["cancelado"]?>"><?php
                                        if ($cantidadComparar > 0 && $row["cancelado"] != 1) {?>
                                          <input name="descuento_<?=$row["id"]?>" type="number" step="0.1" min="0" max="100" class="form-control descuento-input" value="0"><?php
                                        }?>
                                      </td>
                                      <td class="fecha-col" data-cancelado="<?=$row["cancelado"]?>"><?php
                                        if ($cantidadComparar > 0 && $row["cancelado"] != 1) {?>
                                          <input name="fecha_entrega_<?=$row["id"]?>" type="date" onfocus="this.showPicker()" class="form-control fecha-entrega-input" value="<?=$data['fecha']?>"><?php
                                        }?>
                                      </td><?php
                                    }?>
                                  </tr><?php
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div><?php
                          if ($data['aprobado']==1 && tienePermiso(298)){?>
                            <div class="mt-3">
                              <i><strong>NOTA:</strong> Si ingresa Precio x KG > 0, el precio se calculará como: (Precio x KG / 1000 ) * (Peso por Metro * Largo). Si el largo no está definido para el material, se usará solo el Peso por Metro.</i><br/>
                              <i>Para guardar una compra, debe ingresar al menos un concepto con cantidad mayor a 0 y al menos uno de los dos precios (Unitario o x Kg).</i>
                            </div><?php
                          }?>
                        </div>
                      </div>
                    </div>

                    <div class="card-footer">
                      <div class="col-sm-12 text-center">
                        <!-- <a class="btn btn-primary" target="_blank" href="imprimirPedido.php?id=<?=$data['id']; ?>">Imprimir Pedido</a> --><?php
                        if ($data['aprobado']==1 && tienePermiso(298)){?>
                          <button class="btn btn-success" type="submit">Crear Orden de Compra</button><?php
                        } ?>
                        <a href='listarPedidos.php' class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
    <!-- latest jquery-->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap js-->
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <!-- feather icon js-->
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <!-- Sidebar jquery-->
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <!-- Plugins JS start-->
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	  <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
	  <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.buttons.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/jszip.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.colVis.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/pdfmake.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/vfs_fonts.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.autoFill.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.select.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.html5.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.print.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.responsive.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/responsive.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.keyTable.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.colReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.fixedHeader.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.rowReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.scroller.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <script>
      $(document).ready(function() {
        <?php if ($data['aprobado']==1 && tienePermiso(298)){ ?>
        // Show custom controls only when editing is allowed
        $('#custom-controls').show();
        <?php } ?>
        
        // Función común para manejar celdas de items cancelados
        function handleCanceledRowCells($row) {
          var cantidadCol = $row.find('.cantidad-col');
          
          if (cantidadCol.data('cancelado') == 1) {
            // Ocultar las otras columnas de input
            $row.find('.precio-col, .preciokg-col, .descuento-col, .fecha-col').hide();
            
            // Expandir la columna de cantidad para ocupar el espacio
            cantidadCol.attr('colspan', '5').addClass('text-center');
          }
        }
        
        $('#dataTables-example667').DataTable({
          dom: 'l<"#custom-controls-container">frtip',
          stateSave: false,
          responsive: false,
          scrollX: false,
          scrollCollapse: false,
          autoWidth: false,
          paging: true,
          pageLength: 10,
          createdRow: function(row, data, dataIndex) {
            handleCanceledRowCells($(row));
          },
          columnDefs: [
            { width: "180px", targets: 0, orderable: true },
            { width: "85px", targets: 1, orderable: true },
            { width: "85px", targets: 2, orderable: true },
            { width: "90px", targets: 3, orderable: true },
            { width: "90px", targets: 4, orderable: true },
            { width: "60px", targets: 5, orderable: true, className: "text-center" },
            { width: "65px", targets: 6, orderable: true, className: "text-center" },
            { width: "80px", targets: 7, orderable: true, className: "text-center" },
            { width: "75px", targets: 8, orderable: true, className: "text-center" },
            { width: "95px", targets: 9, orderable: false },
            { width: "80px", targets: 10, orderable: false },
            { width: "80px", targets: 11, orderable: false },
            { width: "70px", targets: 12, orderable: false, className: "text-center" },
            { width: "90px", targets: 13, orderable: false }
          ],
          language: {
            "decimal": "",
            "emptyTable": "No hay información",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
            "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
            "infoFiltered": "(Filtrado de _MAX_ total registros)",
            "infoPostFix": "",
            "thousands": ",",
            "lengthMenu": "Mostrar _MENU_ Registros",
            "loadingRecords": "Cargando...",
            "processing": "Procesando...",
            "search": "Buscar:",
            "zeroRecords": "No hay resultados",
            "paginate": {
              "first": "Primero",
              "last": "Ultimo",
              "next": "Siguiente",
              "previous": "Anterior"
            }
          },
          drawCallback: function() {
            var api = this.api();
            api.rows().every(function() {
              handleCanceledRowCells($(this.node()));
            });
          },
          initComplete: function() {
            // Move custom controls to DataTables toolbar
            var customControls = $('#custom-controls').detach();
            $('#custom-controls-container').append(customControls);
            customControls.show();
            
            // Reorganize the DataTables top elements to use available space better
            var wrapper = $('#dataTables-example667_wrapper');
            var lengthDiv = wrapper.find('.dataTables_length');
            var filterDiv = wrapper.find('.dataTables_filter');
            var customContainer = $('#custom-controls-container');
            
            // Create a flex container for the top row
            var topRow = $('<div class="datatables-top-row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; flex-wrap: nowrap;"></div>');
            
            // Move elements to the flex container
            lengthDiv.css({'margin': '0', 'flex': '0 0 auto'});
            customContainer.css({'margin': '0 15px', 'flex': '1 1 auto', 'min-width': '300px'});
            filterDiv.css({'margin': '0', 'flex': '0 0 auto'});
            
            // Insert the new structure
            lengthDiv.parent().prepend(topRow);
            topRow.append(lengthDiv);
            topRow.append(customContainer);
            topRow.append(filterDiv);
            
            // Hide the original container divs that are now empty
            lengthDiv.parent().find('.dataTables_length').not(lengthDiv).hide();
            filterDiv.parent().find('.dataTables_filter').not(filterDiv).hide();
          }
        });

        $("#id_moneda").on("change", function() {
          var esUSD = $(this).val() == 1; 

          if (esUSD) {
            $('#tipo_cambio_dia').prop('required', true);
            $('#tc_required_star').show();
          } else {
            $('#tipo_cambio_dia').prop('required', false);
            $('#tc_required_star').hide();
          }
        }).trigger('change');

        // Propagar descuento global a todos los conceptos
        $('input[name="descuento"]').on('input', function() {
          var valorDescuento = $(this).val();
          $('.descuento-input').val(valorDescuento);
        });

        // Propagar fecha de entrega global a todos los conceptos
        $('input[name="fecha_entrega"]').on('change', function() {
          var valorFecha = $(this).val();
          $('.fecha-entrega-input').val(valorFecha);
        });

        // Inicializar valores de descuento y fecha en conceptos al cargar
        var descuentoInicial = $('input[name="descuento"]').val() || '0';
        var fechaInicial = $('input[name="fecha_entrega"]').val();
        
        $('.descuento-input').val(descuentoInicial);
        if (fechaInicial) {
          $('.fecha-entrega-input').val(fechaInicial);
        }
      });

      function validarFormularioCompra() {
        var idMoneda = $("#id_moneda").val();
        var tipoCambio = $("#tipo_cambio_dia").val();

        if (idMoneda == 1 && (tipoCambio == "" || parseFloat(tipoCambio) <= 0)) {
          alert('Debe ingresar un Tipo de Cambio válido para la moneda USD.');
          $("#tipo_cambio_dia").focus();
          return false;
        }

        var hayConceptoValido = false;
        $('.cantidad-input').each(function() {
          var id_concepto = $(this).closest("tr").data('id');
          var cantidad = parseFloat($(this).val()) || 0;
          var precioUnitario = parseFloat($('input[name="precio_'+id_concepto+'"]').val()) || 0;
          var precioKg = parseFloat($('input[name="preciokg_'+id_concepto+'"]').val()) || 0;
          precioKg = precioKg / 1000;
          if (cantidad > 0 && (precioUnitario > 0 || precioKg > 0)) {
            hayConceptoValido = true;
            return false;
          }
        });
        
        if (!hayConceptoValido) {
          alert('Debe ingresar al menos un concepto con cantidad mayor a 0 y al menos uno de los dos precios (Precio Unitario o Precio x Kg)');
          return false;
        }
        
        return true;
      }
    </script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>