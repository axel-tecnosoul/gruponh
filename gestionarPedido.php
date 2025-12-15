<?php
// Modo Debug habilitado para ver consultas y resultados
$modoDebug = 0;

require("config.php");
require_once("funciones.php"); 
require_once("PHPMailer/class.phpmailer.php");
require_once("PHPMailer/class.smtp.php");

if (empty($_SESSION['user'])) {
  if ($modoDebug == 0) {
    header("Location: index.php");
    die("Redirecting to index.php");
  } else {
    echo "<h3>DEBUG: Usuario no logueado</h3>";
  }
}

require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarPedidos.php");
  exit();
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Validar estado del pedido
$sqlEstadoPedido = "SELECT id_estado FROM pedidos WHERE id = ?";
$qEstadoPedido = $pdo->prepare($sqlEstadoPedido);
$qEstadoPedido->execute([$id]);
$estadoPedido = $qEstadoPedido->fetch(PDO::FETCH_ASSOC);
$estadoPedidoId = $estadoPedido ? (int)$estadoPedido['id_estado'] : null;

if (!in_array($estadoPedidoId, [3, 4], true)) {
  header("Location: listarPedidos.php");
  exit;
}

if (!empty($_POST)) {
  $pdo->beginTransaction();
  $transaccionExitosa = false;
  $error_message = '';

  // Validaciones
  if (isset($_POST['id_moneda']) && $_POST['id_moneda'] == 1) {
    if (empty($_POST['tipo_cambio_dia']) || !is_numeric($_POST['tipo_cambio_dia']) || (float)$_POST['tipo_cambio_dia'] <= 0) {
      $error_message = 'Para la moneda USD, es obligatorio ingresar un Tipo de Cambio válido.';
    }
  }

  if (empty($error_message)) {
    try {
      // Obtener datos de detalle y pesos para calcular correctamente si el usuario mandó precio x kg
      $sql_pedido_detalle = "SELECT d.id, d.id_material, m.concepto, d.cantidad, d.id_unidad_medida, m.peso_metro, m.largo 
                             FROM pedidos_detalle d 
                             INNER JOIN materiales m on m.id = d.id_material 
                             WHERE d.id_pedido = ?";
      $q_pedido_detalle = $pdo->prepare($sql_pedido_detalle);
      $q_pedido_detalle->execute([$id]);
      
      $totalNeto = 0;
      $items_para_comprar = [];

      while ($row = $q_pedido_detalle->fetch(PDO::FETCH_ASSOC)) {
        $cantidadPedir = $_POST['cantidad_'.$row['id']] ?? 0;
        // Permitimos 4 decimales
        $precioUnitario = $_POST['precio_'.$row['id']] ?? 0;
        $precioKg = $_POST['preciokg_'.$row['id']] ?? 0;
        $descuentoItem = $_POST['descuento_'.$row['id']] ?? 0;
        
        if ($cantidadPedir > 0 && ($precioUnitario > 0 || $precioKg > 0)) {
          $precioParaGuardar = $precioUnitario;
          $subtotalLinea = 0;

          if ($precioKg > 0) {
            // Lógica precio por KG
            $peso_total_unitario = (float)$row['peso_metro'];
            $largo = isset($row['largo']) ? (float)$row['largo'] : 0;
            
            if ($largo > 0) {
              $largo_metros = $largo / 1000;
              $peso_total_unitario = $peso_total_unitario * $largo_metros;
            }
            
            $precioUnitarioCalculado = $precioKg * $peso_total_unitario;
            
            $subtotalBruto = $cantidadPedir * $precioUnitarioCalculado;
            
            $precioParaGuardar = 0; 
            
            $subtotalLinea = $subtotalBruto * (1 - ($descuentoItem / 100));

          } else {
            $subtotalBruto = $cantidadPedir * $precioUnitario;
            $subtotalLinea = $subtotalBruto * (1 - ($descuentoItem / 100));
          }

          $totalNeto += $subtotalLinea;
          
          $fechaEntregaItem = $_POST['fecha_entrega_'.$row['id']] ?? $_POST['fecha_entrega'];
          
          $items_para_comprar[] = [
            'id_material' => $row['id_material'],
            'cantidad' => $cantidadPedir,
            'id_unidad_medida' => $row['id_unidad_medida'],
            'precio' => $precioParaGuardar,
            'precio_kg' => $precioKg,
            'subtotal' => $subtotalLinea,
            'descuento' => $descuentoItem,
            'fecha_entrega' => $fechaEntregaItem,
            'id_pedido_detalle' => $row['id']
          ];
        }
      }

      if (!empty($items_para_comprar)) {
        
        $id_parametro_limite = ($_POST['id_moneda'] == 1) ? 11 : 10;
        $sql_p = "SELECT valor FROM parametros WHERE id = ?";
        $q_p = $pdo->prepare($sql_p);
        $q_p->execute([$id_parametro_limite]);
        $data_p = $q_p->fetch(PDO::FETCH_ASSOC);
        $monto_limite = $data_p ? (float)$data_p['valor'] : 0;
        
        $id_tipo_iva = $_POST['id_tipo_iva'];
        $tasa_iva = 0;
        
        $sqlTasa = "SELECT tasa FROM tipos_iva WHERE id = ?";
        $qTasa = $pdo->prepare($sqlTasa);
        $qTasa->execute([$id_tipo_iva]);
        $dataTasa = $qTasa->fetch(PDO::FETCH_ASSOC);
        if ($dataTasa) {
          $tasa_iva = (float)$dataTasa['tasa'];
        }
        
        $monto_iva = $totalNeto * ($tasa_iva / 100);
        $totalFinal = $totalNeto + $monto_iva;

        $id_estado_compra = 1; // Pendiente
        if ($totalFinal < $monto_limite) {
            $id_estado_compra = 3; // Aprobada directa
        }

        $nro_revision = 0;

        $sql = "INSERT INTO compras (id_pedido, id_cuenta_proveedor, fecha_emision, fecha_entrega, id_forma_pago, id_tipo_iva, id_estado_compra, nro_oc, total, iva, comentarios, id_moneda, tipo_cambio_dia, comentarios_revision, descuento, nro_revision) VALUES (?,?,?,?,?, ?, ?, ?, ?, ?,?,?,?, 'Revisión Original',?,?)";
        
        $params = [
            $id, 
            $_POST['id_cuenta_proveedor'], 
            $_POST['fecha_emision'], 
            $_POST['fecha_entrega'], 
            $_POST['id_forma_pago'], 
            $id_tipo_iva,
            $id_estado_compra, 
            '', 
            $totalNeto,
            $monto_iva, 
            $_POST['comentarios'], 
            $_POST['id_moneda'], 
            $_POST['tipo_cambio_dia'], 
            $_POST['descuento'], 
            $nro_revision
        ];
        
        $q = $pdo->prepare($sql);
        $q->execute($params);   
        $idCompra = $pdo->lastInsertId();
        
        $nroOC = $id . '/' . $idCompra;
        $sql = "UPDATE compras SET nro_oc = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$nroOC, $idCompra]);

        foreach ($items_para_comprar as $item) {
          $sql2 = "INSERT INTO compras_detalle(id_compra, id_material, cantidad, id_unidad_medida, precio, precio_kg, subtotal, descuento, fecha_entrega) VALUES (?,?,?,?,?,?,?,?,?)";
          $params2 = [$idCompra, $item['id_material'], $item['cantidad'], $item['id_unidad_medida'], $item['precio'], $item['precio_kg'], $item['subtotal'], $item['descuento'], $item['fecha_entrega']];
          $q2 = $pdo->prepare($sql2);
          $q2->execute($params2);

          $sql3 = "UPDATE pedidos_detalle SET comprado = ? WHERE id_pedido=? AND id_material=?";
          $q3 = $pdo->prepare($sql3);
          $q3->execute([$item['cantidad'], $id, $item['id_material']]);
          
          actualizarEstadoPedidoDetalle($pdo, $item['id_pedido_detalle']);
          
          $sql4 = "SELECT cd.id id from computos_detalle cd inner join computos c on c.id = cd.id_computo inner join pedidos p on p.id_computo = c.id where p.id = ? and cd.cancelado = 0 and cd.id_material = ? ";
          $q4 = $pdo->prepare($sql4);
          $q4->execute([$id, $item['id_material']]);
          $data4 = $q4->fetch(PDO::FETCH_ASSOC);
          
          if ($data4) {
            $sql5 = "UPDATE computos_detalle set comprado = ? WHERE id = ?";
            $q5 = $pdo->prepare($sql5);
            $q5->execute([$item['cantidad'], $data4['id']]);
          }
        }

        $sql_log = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva orden de compra','Compras','verCompra.php?id=$idCompra')";
        $q_log = $pdo->prepare($sql_log);
        $q_log->execute([$_SESSION['user']['id']]);
        
        $estado_texto = ($id_estado_compra == 3) ? "APROBADA (Automática)" : "Pendiente de Aprobación";
        $asuntoEmail = "Compras - Nueva OC #$idCompra ($estado_texto)";
        $cuerpoEmail = "Nueva compra generada.\nOC: #$idCompra\nEstado: $estado_texto\nNeto: $".number_format($totalNeto, 2)."\nIVA: $".number_format($monto_iva, 2)."\nTotal: $".number_format($totalFinal, 2);

        crearNotificacion($pdo, 4, $idCompra, "ID OC: #$idCompra - $estado_texto", $asuntoEmail, $cuerpoEmail);

        $sqlContarOC = "SELECT COUNT(*) as total_oc FROM compras WHERE id_pedido = ?";
        $qContarOC = $pdo->prepare($sqlContarOC);
        $qContarOC->execute([$id]);
        $dataContarOC = $qContarOC->fetch(PDO::FETCH_ASSOC);
        
        if ($dataContarOC['total_oc'] == 1) {
          $pdo->prepare("UPDATE pedidos SET id_estado = 4 WHERE id = ?")->execute([$id]);
        }

        $transaccionExitosa = true;

      } else {
        $error = "Debe ingresar al menos un concepto con cantidad mayor a 0 y precio.";
      }
    } catch (Exception $e) {
      $transaccionExitosa = false;
      $error = "Error al procesar: " . $e->getMessage();
    }

    if ($transaccionExitosa) {
      $pdo->commit();
      Database::disconnect();
      $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Orden de Compra creada exitosamente.'];
      header("Location: listarCompras.php");
      exit();
    } else {
      $pdo->rollback();
    }
    Database::disconnect();
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
  $codigoObraPartes = array_filter([$data['nro_sitio'] ?? null, $data['nro_subsitio'] ?? null, $data['proyecto_nro'] ?? null], function($v){ return $v!==null && $v!=='';});
  $codigoObra = !empty($codigoObraPartes) ? implode('-', $codigoObraPartes) : '';
  $tieneComputo = !empty($data['id_computo']);
  $tipoPedido = $tieneComputo ? 'de Cómputo' : 'Directo';
  
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

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <!-- Agregados estilos de select2 y datatables como en el archivo original -->
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
      #dataTables-example667 td:nth-child(2),
      #dataTables-example667 th:nth-child(3),
      #dataTables-example667 td:nth-child(3),
      #dataTables-example667 th:nth-child(4),
      #dataTables-example667 td:nth-child(4) {
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
        text-align: center;
      }
      
      #dataTables-example667 th:nth-child(5),
      #dataTables-example667 td:nth-child(5),
      #dataTables-example667 th:nth-child(6),
      #dataTables-example667 td:nth-child(6),
      #dataTables-example667 th:nth-child(7),
      #dataTables-example667 td:nth-child(7) {
        width: 80px !important;
        min-width: 80px !important;
        max-width: 80px !important;
      }
      
      #dataTables-example667 th:nth-child(8),
      #dataTables-example667 td:nth-child(8) {
        width: 60px !important;
        min-width: 60px !important;
        max-width: 60px !important;
      }
      
      #dataTables-example667 th:nth-child(9),
      #dataTables-example667 td:nth-child(9) {
        width: 90px !important;
        min-width: 90px !important;
        max-width: 90px !important;
        text-align: right;
      }
      
      #dataTables-example667 th:nth-child(10),
      #dataTables-example667 td:nth-child(10) {
        width: 100px !important;
        min-width: 100px !important;
        max-width: 100px !important;
      }
      
      #dataTables-example667 tbody td {
        white-space: nowrap;
      }
      
      #dataTables-example667 tbody td:nth-child(1) {
        white-space: normal;
      }
      
      #dataTables-example667 input.form-control {
        font-size: 0.75rem;
        padding: 0.25rem 0.35rem;
        height: 28px;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }
      
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
      
      .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
      }
      
      .table-secondary { 
        background-color: #f8f9fa !important; 
        opacity: 0.8; 
      }
      .table-secondary td { 
        text-decoration: line-through; 
        color: #6c757d; 
      }
      
      .total-container {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 15px;
        margin-top: 20px;
      }
      .total-row {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-bottom: 5px;
        font-size: 14px;
      }
      .total-row.final {
        font-weight: bold;
        font-size: 18px;
        margin-top: 10px;
        border-top: 1px solid #ccc;
        padding-top: 10px;
      }
      .total-label {
        margin-right: 20px;
        text-align: right;
      }
      .total-value {
        width: 150px;
        text-align: right;
      }
    </style>
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php');?>
      <!-- Page Body Wrapper Start-->
      <div class="page-body-wrapper">
        <!-- Agregado include del menu para la barra lateral -->
        <?php include('menu.php');?>
        <!-- Page Sidebar End-->
        <!-- Page Body Start-->
        <div class="page-body">
          <?php
          $ubicacion="Gestión de Pedido y Nueva Orden de Compra";
          include_once("head_page.php");?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Información del Pedido <?=$tipoPedido." N° ".$id;?></h5>
                    <?php if (isset($error)){ ?>
                      <div class="alert alert-danger"><?=$error;?></div>
                    <?php } ?>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#" id="form-unificado" onsubmit="return validarFormularioCompra();">
                    <div class="card-body">
                      <div class="row">
                        <div class="col-md-6">
                          <h6 class="mb-3">Datos del Pedido</h6>
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
                        </div>
                        <?php if ($data['aprobado']==1 && tienePermiso(298)){?>
                          <div class="col-md-6">
                            <h6 class="mb-3">Datos de la Orden de Compra</h6>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Proveedor(*)</label>
                              <div class="col-sm-8">
                                <select name="id_cuenta_proveedor" id="id_cuenta_proveedor" class="js-example-basic-single w-100" required>
                                  <option value="">Seleccione...</option>
                                  <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (5) and activo = 1 and anulado = 0";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                    <option value='<?=$fila['id']?>'><?=$fila['nombre']?></option>
                                  <?php }
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
                              <label class="col-sm-4 col-form-label">Fecha Entrega</label>
                              <div class="col-sm-8">
                                <input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?=$data["fecha"]?>" class="form-control">
                              </div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Moneda(*)</label>
                              <div class="col-sm-8">
                                <select name="id_moneda" id="id_moneda" class="js-example-basic-single w-100" required>
                                  <option value="">Seleccione...</option>
                                  <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT id, moneda FROM monedas WHERE 1";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                    <option value='<?=$fila['id']?>'><?=$fila['moneda']?></option>
                                  <?php }
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
                            <!-- IVA -->
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">IVA(*)</label>
                              <div class="col-sm-8">
                                <select name="id_tipo_iva" id="id_tipo_iva" class="form-control" required>
                                  <?php
                                  $pdo = Database::connect();
                                  try {
                                      $q = $pdo->query("SELECT id, tasa FROM tipos_iva ORDER BY tasa");
                                      while ($f = $q->fetch()) { 
                                          $sel = ($f['tasa'] == 21.00) ? 'selected' : '';
                                          echo "<option value='".$f['id']."' data-tasa='".$f['tasa']."' $sel>".(float)$f['tasa']."%</option>"; 
                                      }
                                  } catch (Exception $e) {
                                      echo "<option value='0' data-tasa='0'>ERROR: Cree tabla tipos_iva</option>";
                                  }
                                  Database::disconnect();
                                  ?>
                                </select>
                              </div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Descuento General</label>
                              <div class="col-sm-8">
                                <input name="descuento" type="number" step="0.01" class="form-control">
                              </div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Forma de Pago(*)</label>
                              <div class="col-sm-8">
                                <select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single w-100" required>
                                  <option value="">Seleccione...</option>
                                  <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT `id`, `forma_pago` FROM `formas_pago` WHERE 1";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                    <option value='<?=$fila['id']?>'><?=$fila['forma_pago']?></option>
                                  <?php }
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
                          </div>
                        <?php } ?>
                      </div>
                      <hr class="mt-4 mb-4">
                      <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3">Detalle de Conceptos</h6>
                          <div class="table-responsive">
                            <table class="display" id="dataTables-example667" style="width:100%">
                              <thead>
                                <tr>
                                  <th>Concepto</th>
                                  <th>Requerido</th>
                                  <th>Comprado</th>
                                  <th>Pendiente</th>
                                  <?php if ($data['aprobado']==1 && tienePermiso(298)){?>
                                    <th>A Comprar</th>
                                    <th>P. Unit.</th>
                                    <th>P. x Kg</th>
                                    <th>Desc %</th>
                                    <th>Subtotal</th>
                                    <th>F. Entrega</th>
                                  <?php } ?>
                                </tr>
                              </thead>
                              <tbody>
                                <?php
                                $pdo = Database::connect();
                                $sql = "SELECT pd.id, m.concepto, pd.cantidad, u.unidad_medida, pd.comprado, pd.cancelado, m.peso_metro, m.largo, pd.id_material
                                        FROM pedidos_detalle pd 
                                        INNER JOIN materiales m on m.id = pd.id_material 
                                        INNER JOIN unidades_medida u on u.id = pd.id_unidad_medida 
                                        WHERE pd.id_pedido = ".$id;
                                
                                foreach ($pdo->query($sql) as $row) {
                                  $pendiente = (float)$row["cantidad"] - (float)$row["comprado"];
                                  $canceladoClass = ($row["cancelado"]==1) ? 'table-secondary' : '';
                                  
                                  $peso_metro = (float)$row['peso_metro'];
                                  $largo = (float)$row['largo'];
                                  ?>
                                  <tr class="<?=$canceladoClass?>" data-id="<?=$row['id']?>" data-peso="<?=$peso_metro?>" data-largo="<?=$largo?>">
                                    <td><?=$row["concepto"]?></td>
                                    <td class="text-center"><?=(float)$row["cantidad"].' '.$row["unidad_medida"]?></td>
                                    <td class="text-center"><?=(float)$row["comprado"]?></td>
                                    <td class="text-center"><?=($row["cancelado"]==1 ? '0' : $pendiente)?></td>
                                    
                                    <?php if ($data['aprobado']==1 && tienePermiso(298)) { ?>
                                      <?php if ($row["cancelado"] != 1 && $pendiente > 0) { ?>
                                        <td><input name="cantidad_<?=$row["id"]?>" type="number" step="0.01" class="form-control cantidad-input" value="<?=$pendiente?>" max="<?=$pendiente?>"></td>
                                        <td><input name="precio_<?=$row["id"]?>" type="number" step="0.0001" class="form-control precio-input" value="0"></td>
                                        <td><input name="preciokg_<?=$row["id"]?>" type="number" step="0.0001" class="form-control preciokg-input" value="0"></td>
                                        <td><input name="descuento_<?=$row["id"]?>" type="number" step="0.1" class="form-control descuento-input" value="0"></td>
                                        <td class="text-right"><span class="subtotal-cell">0.00</span></td>
                                        <td><input name="fecha_entrega_<?=$row["id"]?>" type="date" class="form-control fecha-entrega-input" value="<?=$data['fecha']?>"></td>
                                      <?php } else { ?>
                                        <td colspan="6" class="text-center"><span class="badge badge-secondary"><?=($row["cancelado"]==1?'Cancelado':'Completo')?></span></td>
                                      <?php } ?>
                                    <?php } ?>
                                  </tr>
                                <?php }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                          <?php if ($data['aprobado']==1 && tienePermiso(298)){?>
                            <!-- Sección de Totales -->
                            <div class="row justify-content-end">
                              <div class="col-md-4">
                                <div class="total-container">
                                  <div class="total-row">
                                    <span class="total-label">Subtotal Neto:</span>
                                    <span class="total-value" id="lbl_neto">$ 0.00</span>
                                  </div>
                                  <div class="total-row">
                                    <span class="total-label">IVA (<span id="lbl_tasa_iva">21</span>%):</span>
                                    <span class="total-value" id="lbl_iva">$ 0.00</span>
                                  </div>
                                  <div class="total-row final">
                                    <span class="total-label">TOTAL:</span>
                                    <span class="total-value" id="lbl_total">$ 0.00</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <div class="mt-3">
                              <i><strong>NOTA:</strong> Si ingresa Precio x KG > 0, el precio se calculará como: (Precio x KG) * (Peso por Metro * Largo). Si el largo no está definido para el material, se usará solo el Peso por Metro.</i><br/>
                              <i>Para guardar una compra, debe ingresar al menos un concepto con cantidad mayor a 0 y al menos uno de los dos precios (Unitario o x Kg).</i>
                            </div>
                          <?php } ?>
                        </div>
                      </div>
                    </div>

                    <div class="card-footer">
                      <div class="col-sm-12 text-center">
                        <?php if ($data['aprobado']==1 && tienePermiso(298)){?>
                          <button class="btn btn-success" type="submit">Crear Orden de Compra</button>
                        <?php } ?>
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
    <script>
      $(document).ready(function() {
        // Inicialización de DataTables con configuración del archivo original
        $('#dataTables-example667').DataTable({
          stateSave: false,
          responsive: false,
          scrollX: false,
          scrollCollapse: false,
          autoWidth: false,
          paging: true,
          pageLength: 10,
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
          }
        });

        // Moneda y Tipo de Cambio
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

        // Propagación masiva del descuento general
        $('input[name="descuento"]').on('input', function() { 
          $('.descuento-input').val($(this).val()).trigger('input'); 
        });
        
        // Propagación masiva de fecha de entrega
        $('input[name="fecha_entrega"]').on('change', function() { 
          $('.fecha-entrega-input').val($(this).val()); 
        });

        // CÁLCULOS EN TIEMPO REAL
        function calcularFila(row) {
          let cantidad = parseFloat(row.find('.cantidad-input').val()) || 0;
          let precioUnit = parseFloat(row.find('.precio-input').val()) || 0;
          let precioKg = parseFloat(row.find('.preciokg-input').val()) || 0;
          let descuento = parseFloat(row.find('.descuento-input').val()) || 0;
          
          let pesoMetro = parseFloat(row.data('peso')) || 0;
          let largoMm = parseFloat(row.data('largo')) || 0;
          
          if (precioKg > 0) {
            let largoMetros = (largoMm > 0) ? largoMm / 1000 : 1;
            let pesoUnitario = pesoMetro * largoMetros; 
            precioUnit = precioKg * pesoUnitario;
            row.find('.precio-input').val(precioUnit.toFixed(4));
          }
          
          let subtotalBruto = cantidad * precioUnit;
          let montoDescuento = subtotalBruto * (descuento / 100);
          let subtotalNeto = subtotalBruto - montoDescuento;
          
          row.find('.subtotal-cell').text(subtotalNeto.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 4}));
          row.data('subtotal', subtotalNeto);
          
          calcularTotalesGenerales();
        }

        function calcularTotalesGenerales() {
          let totalNeto = 0;
          
          $('#dataTables-example667 tbody tr').each(function() {
            let sub = $(this).data('subtotal') || 0;
            totalNeto += sub;
          });
          
          let opcionIva = $('#id_tipo_iva').find(':selected');
          let tasaIva = parseFloat(opcionIva.data('tasa')) || 0;
          let montoIva = totalNeto * (tasaIva / 100);
          let totalFinal = totalNeto + montoIva;
          
          $('#lbl_neto').text('$ ' + totalNeto.toLocaleString('es-AR', {minimumFractionDigits: 2}));
          $('#lbl_tasa_iva').text(tasaIva);
          $('#lbl_iva').text('$ ' + montoIva.toLocaleString('es-AR', {minimumFractionDigits: 2}));
          $('#lbl_total').text('$ ' + totalFinal.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        }

        // Event Listeners para inputs de tabla
        $(document).on('input', '.cantidad-input, .precio-input, .preciokg-input, .descuento-input', function() {
          let row = $(this).closest('tr');
          
          if ($(this).hasClass('precio-input')) {
            row.find('.preciokg-input').val(0);
          }
          
          calcularFila(row);
        });
        
        // Cambio de tasa de IVA recalculate totales
        $('#id_tipo_iva').on('change', function() {
          calcularTotalesGenerales();
        });

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
        var valid = true;
        
        $('.cantidad-input').each(function() {
          var qty = parseFloat($(this).val()) || 0;
          var row = $(this).closest('tr');
          var prc = parseFloat(row.find('.precio-input').val()) || 0;
          
          if (qty > 0) {
            if (prc <= 0) {
              alert('Hay items con cantidad pero sin precio.');
              $(this).focus();
              valid = false;
              return false;
            }
            hayConceptoValido = true;
          }
        });
        
        if (!valid) return false;
        
        if (!hayConceptoValido) {
          alert('Debe ingresar al menos un concepto con cantidad mayor a 0 y al menos uno de los dos precios (Precio Unitario o Precio x Kg)');
          return false;
        }
        
        return true;
      }
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>
