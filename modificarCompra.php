<?php
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

$modo = null;
$id_compra = null;
$id_pedido = null;

if (!empty($_GET['id_compra'])) {
  $modo = 'compra';
  $id_compra = $_GET['id_compra'];
} elseif (!empty($_GET['id_pedido'])) {
  $modo = 'pedido';
  $id_pedido = $_GET['id_pedido'];
}

if ($modo === null) {
  header("Location: listarCompras.php");
  exit();
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($modo === 'compra') {
  $sql_estado = "SELECT id_estado_compra FROM compras WHERE id = ?";
  $q_estado = $pdo->prepare($sql_estado);
  $q_estado->execute([$id_compra]);
  $estado_actual = $q_estado->fetch(PDO::FETCH_ASSOC);

  if (!$estado_actual || $estado_actual['id_estado_compra'] != 1) {
    Database::disconnect();
    header("Location: listarCompras.php");
    exit();
  }
} else {
  $sqlEstadoPedido = "SELECT id_estado FROM pedidos WHERE id = ?";
  $qEstadoPedido = $pdo->prepare($sqlEstadoPedido);
  $qEstadoPedido->execute([$id_pedido]);
  $estadoPedido = $qEstadoPedido->fetch(PDO::FETCH_ASSOC);
  $estadoPedidoId = $estadoPedido ? (int)$estadoPedido['id_estado'] : null;

  if (!in_array($estadoPedidoId, [3, 4], true)) {
    Database::disconnect();
    header("Location: listarPedidos.php");
    exit();
  }
}

Database::disconnect();

if (!empty($_POST)) {

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if ($modo === 'compra') {
    $pdo_calc = Database::connect();
    $sql_calc = "SELECT SUM(precio * cantidad) AS subtotal FROM compras_detalle WHERE id_compra = ?";
    $q_calc = $pdo_calc->prepare($sql_calc);
    $q_calc->execute([$id_compra]);
    $calc_data = $q_calc->fetch(PDO::FETCH_ASSOC);
    $subtotal = $calc_data['subtotal'] ?? 0;

    $porcentaje_iva = 0;
    if ($_POST['id_tipo_iva'] == 2) {
      $porcentaje_iva = 0.105;
    } elseif ($_POST['id_tipo_iva'] == 3) {
      $porcentaje_iva = 0.21;
    }

    $iva = $subtotal * $porcentaje_iva;
    $descuento_pct = floatval($_POST['descuento'] ?? 0);
    $descuento = $subtotal * ($descuento_pct / 100);
    $total = $subtotal + $iva - $descuento;
    Database::disconnect();

    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "UPDATE compras SET id_cuenta_proveedor = ?, fecha_emision = ?, fecha_entrega = ?, id_moneda = ?, tipo_cambio_dia = ?, descuento = ?, id_forma_pago = ?, comentarios = ?, total = ?, iva = ?, id_tipo_iva = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([
      $_POST['id_cuenta_proveedor'],
      $_POST['fecha_emision'],
      $_POST['fecha_entrega'],
      $_POST['id_moneda'],
      $_POST['tipo_cambio_dia'],
      $descuento_pct,
      $_POST['id_forma_pago'],
      $_POST['comentarios'],
      $total,
      $iva,
      $_POST['id_tipo_iva'],
      $id_compra
    ]);

    $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificacion de orden de compra','Compras','verCompra.php?id=$id_compra')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    Database::disconnect();
    header("Location: listarCompras.php");
    exit();

  } else {
    $pdo->beginTransaction();
    $transaccionExitosa = false;
    $error_message = '';

    if (isset($_POST['id_moneda']) && $_POST['id_moneda'] == 1) {
      if (empty($_POST['tipo_cambio_dia']) || !is_numeric($_POST['tipo_cambio_dia']) || (float)$_POST['tipo_cambio_dia'] <= 0) {
        $error_message = 'Para la moneda USD, es obligatorio ingresar un Tipo de Cambio valido.';
      }
    }
    if (empty($_POST['id_cuenta_proveedor'])) {
      $error_message = "Debe seleccionar un Proveedor.";
    }
    if (empty($_POST['id_moneda'])) {
      $error_message = "Debe seleccionar una Moneda.";
    }
    if (empty($_POST['id_forma_pago'])) {
      $error_message = "Debe seleccionar una Forma de Pago.";
    }
    if (empty($_POST['fecha_emision'])) {
      $error_message = "Debe ingresar la Fecha de Emision.";
    }

    if (empty($error_message)) {
      try {
        $sql_pedido_detalle = "SELECT d.id, d.id_material, m.concepto, d.cantidad, d.id_unidad_medida, m.peso_metro, m.largo
                               FROM pedidos_detalle d
                               INNER JOIN materiales m on m.id = d.id_material
                               WHERE d.id_pedido = ?";
        $q_pedido_detalle = $pdo->prepare($sql_pedido_detalle);
        $q_pedido_detalle->execute([$id_pedido]);

        $totalNeto = 0;
        $items_para_comprar = [];

        while ($row = $q_pedido_detalle->fetch(PDO::FETCH_ASSOC)) {
          $cantidadPedir = $_POST['cantidad_'.$row['id']] ?? 0;
          $precioUnitario = $_POST['precio_'.$row['id']] ?? 0;
          $precioKg = $_POST['preciokg_'.$row['id']] ?? 0;
          $descuentoItem = $_POST['descuento_'.$row['id']] ?? 0;

          if ($cantidadPedir > 0 && ($precioUnitario > 0 || $precioKg > 0)) {
            $precioParaGuardar = $precioUnitario;
            $subtotalLinea = 0;

            if ($precioKg > 0) {
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

          $descuento_gral_pct = floatval($_POST['descuento'] ?? 0);
          $descuento_gral_monto = $totalNeto * ($descuento_gral_pct / 100);
          $monto_iva = ($totalNeto - $descuento_gral_monto) * ($tasa_iva / 100);
          $totalFinal = $totalNeto - $descuento_gral_monto + $monto_iva;

          $id_estado_compra = 1;
          if ($totalFinal < $monto_limite) {
            $id_estado_compra = 3;
          }

          $nro_revision = 0;

          $sql = "INSERT INTO compras (id_pedido, id_cuenta_proveedor, fecha_emision, fecha_entrega, id_forma_pago, id_tipo_iva, id_estado_compra, nro_oc, total, iva, comentarios, id_moneda, tipo_cambio_dia, comentarios_revision, descuento, nro_revision) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Revision Original',?,?)";
          $params = [
            $id_pedido,
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
            $descuento_gral_pct,
            $nro_revision
          ];

          $q = $pdo->prepare($sql);
          $q->execute($params);
          $idCompra = $pdo->lastInsertId();

          $nroOC = $id_pedido . '/' . $idCompra;
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
            $q3->execute([$item['cantidad'], $id_pedido, $item['id_material']]);

            actualizarEstadoPedidoDetalle($pdo, $item['id_pedido_detalle']);

            $sql4 = "SELECT cd.id id from computos_detalle cd inner join computos c on c.id = cd.id_computo inner join pedidos p on p.id_computo = c.id where p.id = ? and cd.cancelado = 0 and cd.id_material = ?";
            $q4 = $pdo->prepare($sql4);
            $q4->execute([$id_pedido, $item['id_material']]);
            $data4 = $q4->fetch(PDO::FETCH_ASSOC);

            if ($data4) {
              $sql5 = "UPDATE computos_detalle set comprado = ? WHERE id = ?";
              $q5 = $pdo->prepare($sql5);
              $q5->execute([$item['cantidad'], $data4['id']]);
            }
          }

          $sqlContarOC = "SELECT COUNT(*) as total_oc FROM compras WHERE id_pedido = ?";
          $qContarOC = $pdo->prepare($sqlContarOC);
          $qContarOC->execute([$id_pedido]);
          $dataContarOC = $qContarOC->fetch(PDO::FETCH_ASSOC);

          if ($dataContarOC['total_oc'] == 1) {
            $pdo->prepare("UPDATE pedidos SET id_estado = 4 WHERE id = ?")->execute([$id_pedido]);
          }

          $sql_log = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva orden de compra','Compras','verCompra.php?id=$idCompra')";
          $q_log = $pdo->prepare($sql_log);
          $q_log->execute([$_SESSION['user']['id']]);

          $estado_texto = ($id_estado_compra == 3) ? "APROBADA (Automatica)" : "Pendiente de Aprobacion";
          $asuntoEmail = "Compras - Nueva OC #$idCompra ($estado_texto)";
          $cuerpoEmail = "Nueva compra generada.\nOC: #$idCompra\nEstado: $estado_texto\nNeto: $".number_format($totalNeto, 2)."\nIVA: $".number_format($monto_iva, 2)."\nTotal: $".number_format($totalFinal, 2);

          crearNotificacion($pdo, 4, $idCompra, "ID OC: #$idCompra - $estado_texto", $asuntoEmail, $cuerpoEmail);

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
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$proveedores = [];
$qProv = $pdo->prepare("SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta IN (5) AND activo = 1 AND anulado = 0");
$qProv->execute();
while ($f = $qProv->fetch(PDO::FETCH_ASSOC)) { $proveedores[] = $f; }

$monedas = [];
$qMon = $pdo->prepare("SELECT id, moneda FROM monedas WHERE 1");
$qMon->execute();
while ($f = $qMon->fetch(PDO::FETCH_ASSOC)) { $monedas[] = $f; }

$formasPago = [];
$qFp = $pdo->prepare("SELECT id, forma_pago FROM formas_pago WHERE 1");
$qFp->execute();
while ($f = $qFp->fetch(PDO::FETCH_ASSOC)) { $formasPago[] = $f; }

$tiposIva = [];
try {
  $qIva = $pdo->query("SELECT id, tasa FROM tipos_iva ORDER BY tasa");
  while ($f = $qIva->fetch(PDO::FETCH_ASSOC)) { $tiposIva[] = $f; }
} catch (Exception $e) { /* tabla no existe */ }

$urlVolver = ($modo === 'compra') ? 'listarCompras.php' : 'listarPedidos.php';
$textoVolver = ($modo === 'compra') ? 'Volver al Listado' : 'Volver';
$textoBotonSubmit = ($modo === 'compra') ? 'Guardar Modificacion' : 'Crear Orden de Compra';
$actionForm = ($modo === 'compra')
  ? "modificarCompra.php?id_compra=".$id_compra
  : "modificarCompra.php?id_pedido=".$id_pedido;

$val = [
  'proveedor'    => '',
  'fecha_emision'=> date('Y-m-d'),
  'fecha_entrega'=> '',
  'moneda'       => '',
  'tipo_cambio'  => '',
  'forma_pago'   => '',
  'tipo_iva'     => null,
  'descuento'    => '',
  'comentarios'  => ''
];

if ($modo === 'compra') {
  $sql = "SELECT c.id, c.id_pedido, c.id_cuenta_proveedor, c.fecha_emision, c.fecha_entrega,
    c.id_forma_pago, c.id_estado_compra, c.nro_oc, c.total, c.comentarios,
    c.id_moneda, c.tipo_cambio_dia, c.iva, c.descuento, c.nro_revision, c.id_tipo_iva
    FROM compras c WHERE c.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_compra]);
  $dataCompra = $q->fetch(PDO::FETCH_ASSOC);
  $id_pedido = $dataCompra['id_pedido'] ?? null;

  $q_est = $pdo->prepare("SELECT estado FROM estados_compra WHERE id = ?");
  $q_est->execute([$dataCompra['id_estado_compra']]);
  $estado_data = $q_est->fetch(PDO::FETCH_ASSOC);
  $estadoTexto = $estado_data ? $estado_data['estado'] : 'N/A';

  $q_sub = $pdo->prepare("SELECT SUM(precio * cantidad) AS subtotal FROM compras_detalle WHERE id_compra = ?");
  $q_sub->execute([$id_compra]);
  $sub_data = $q_sub->fetch(PDO::FETCH_ASSOC);
  $subtotalCompra = $sub_data['subtotal'] ?? 0;

  $val['proveedor']     = $dataCompra['id_cuenta_proveedor'];
  $val['fecha_emision'] = $dataCompra['fecha_emision'];
  $val['fecha_entrega'] = $dataCompra['fecha_entrega'];
  $val['moneda']        = $dataCompra['id_moneda'];
  $val['tipo_cambio']   = $dataCompra['tipo_cambio_dia'];
  $val['forma_pago']    = $dataCompra['id_forma_pago'];
  $val['tipo_iva']      = $dataCompra['id_tipo_iva'];
  $val['descuento']     = $dataCompra['descuento'];
  $val['comentarios']   = $dataCompra['comentarios'];

} else {
  $sql = "SELECT pe.id, pe.id_computo, pe.id_proyecto,
    DATE_FORMAT(pe.fecha, '%d/%m/%Y') AS fecha_formatted, pe.fecha,
    pe.lugar_entrega, pe.id_cuenta_recibe, pe.aprobado,
    c.id_tarea, c.id_cuenta_solicitante,
    COALESCE(pc.id, pd.id) AS proyecto_id,
    COALESCE(pc.nombre, pd.nombre) AS proyecto_nombre,
    COALESCE(pc.nro, pd.nro) AS proyecto_nro,
    COALESCE(sc.nro_sitio, sd.nro_sitio) AS nro_sitio,
    COALESCE(sc.nro_subsitio, sd.nro_subsitio) AS nro_subsitio,
    cu.nombre AS cuenta_solicitante,
    cu2.nombre AS cuenta_recibe,
    pe.id_estado,
    ep.estado AS estado_pedido
    FROM pedidos pe
    LEFT JOIN computos c ON c.id = pe.id_computo
    LEFT JOIN tareas t ON t.id = c.id_tarea
    LEFT JOIN proyectos pc ON pc.id = t.id_proyecto
    LEFT JOIN sitios sc ON sc.id = pc.id_sitio
    LEFT JOIN proyectos pd ON pd.id = pe.id_proyecto
    LEFT JOIN sitios sd ON sd.id = pd.id_sitio
    LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante
    LEFT JOIN cuentas cu2 ON cu2.id = pe.id_cuenta_recibe
    LEFT JOIN estados_pedidos ep ON ep.id = pe.id_estado
    WHERE pe.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_pedido]);
  $dataPedido = $q->fetch(PDO::FETCH_ASSOC);

  $proyectoDisplay = '';
  $tipoPedido = '';
  if ($dataPedido) {
    $codigoObraPartes = array_filter(
      [$dataPedido['nro_sitio'] ?? null, $dataPedido['nro_subsitio'] ?? null, $dataPedido['proyecto_nro'] ?? null],
      function($v){ return $v !== null && $v !== ''; }
    );
    $codigoObra = !empty($codigoObraPartes) ? implode('-', $codigoObraPartes) : '';
    $tipoPedido = !empty($dataPedido['id_computo']) ? 'de Computo' : 'Directo';

    if (!empty($dataPedido['proyecto_id'])) {
      if (!empty($codigoObra) && !empty($dataPedido['proyecto_nombre'])) {
        $proyectoDisplay = $codigoObra . ': ' . $dataPedido['proyecto_nombre'];
      } elseif (!empty($codigoObra)) {
        $proyectoDisplay = $codigoObra;
      } elseif (!empty($dataPedido['proyecto_nombre'])) {
        $proyectoDisplay = $dataPedido['proyecto_nombre'];
      }
    }
  }

  $pedidoAprobado = ($dataPedido['aprobado'] == 1);
  $val['fecha_entrega'] = $dataPedido['fecha'];
}

Database::disconnect();

$lblCol = ($modo === 'compra') ? 'col-sm-3' : 'col-sm-4';
$inpCol = ($modo === 'compra') ? 'col-sm-9' : 'col-sm-8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('head_forms.php');?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  <style>
    .form-control:disabled,
    .form-control[readonly] { background-color: #e9ecef; opacity: 1; }
    .form-group { margin-bottom: 1rem; }
    .card-body { padding: 1.5rem; }
    #dataTables-example667 { width: 100% !important; font-size: 0.75rem; table-layout: fixed !important; border-collapse: collapse !important; }
    #dataTables-example667 th,
    #dataTables-example667 td { padding: 5px 4px !important; vertical-align: middle; font-size: 0.75rem; overflow: hidden; text-overflow: ellipsis; box-sizing: border-box !important; }
    #dataTables-example667 thead th { white-space: nowrap !important; padding: 6px 4px !important; font-size: 0.7rem; font-weight: 600; line-height: 1.2; background-color: #f8f9fa; }
    <?php if ($modo === 'pedido') { ?>
    #dataTables-example667 th:nth-child(1), #dataTables-example667 td:nth-child(1) { width: 180px !important; min-width: 180px !important; max-width: 180px !important; white-space: normal; word-wrap: break-word; }
    #dataTables-example667 th:nth-child(2), #dataTables-example667 td:nth-child(2) { width: 85px !important; min-width: 85px !important; max-width: 85px !important; }
    #dataTables-example667 th:nth-child(3), #dataTables-example667 td:nth-child(3) { width: 85px !important; min-width: 85px !important; max-width: 85px !important; }
    #dataTables-example667 th:nth-child(4), #dataTables-example667 td:nth-child(4) { width: 90px !important; min-width: 90px !important; max-width: 90px !important; }
    #dataTables-example667 th:nth-child(5), #dataTables-example667 td:nth-child(5) { width: 90px !important; min-width: 90px !important; max-width: 90px !important; }
    #dataTables-example667 th:nth-child(6), #dataTables-example667 td:nth-child(6) { width: 60px !important; min-width: 60px !important; max-width: 60px !important; text-align: center; }
    #dataTables-example667 th:nth-child(7), #dataTables-example667 td:nth-child(7) { width: 65px !important; min-width: 65px !important; max-width: 65px !important; text-align: center; }
    #dataTables-example667 th:nth-child(8), #dataTables-example667 td:nth-child(8) { width: 80px !important; min-width: 80px !important; max-width: 80px !important; text-align: center; }
    #dataTables-example667 th:nth-child(9), #dataTables-example667 td:nth-child(9) { width: 75px !important; min-width: 75px !important; max-width: 75px !important; text-align: center; }
    #dataTables-example667 th:nth-child(10), #dataTables-example667 td:nth-child(10) { width: 95px !important; min-width: 95px !important; max-width: 95px !important; }
    #dataTables-example667 th:nth-child(11), #dataTables-example667 td:nth-child(11) { width: 80px !important; min-width: 80px !important; max-width: 80px !important; }
    #dataTables-example667 th:nth-child(12), #dataTables-example667 td:nth-child(12) { width: 80px !important; min-width: 80px !important; max-width: 80px !important; }
    #dataTables-example667 th:nth-child(13), #dataTables-example667 td:nth-child(13) { width: 70px !important; min-width: 70px !important; max-width: 70px !important; text-align: center; }
    #dataTables-example667 th:nth-child(14), #dataTables-example667 td:nth-child(14) { width: 90px !important; min-width: 90px !important; max-width: 90px !important; }
    #dataTables-example667 th:nth-child(15), #dataTables-example667 td:nth-child(15) { width: 90px !important; min-width: 90px !important; max-width: 90px !important; text-align: right; }
    <?php } ?>
    #custom-controls-container { display: inline-block; vertical-align: middle; flex: 1; max-width: calc(100% - 400px); margin: 0 20px; padding: 5px 0; }
    #custom-controls { display: flex !important; align-items: center; justify-content: flex-start; gap: 25px; margin: 0 !important; width: 100%; flex-wrap: nowrap; }
    #custom-controls .col-md-3 { flex: 0 0 auto; width: auto; padding: 0; margin: 0; min-width: 150px; }
    #custom-controls .form-label { font-size: 11px; font-weight: 500; margin-bottom: 3px; color: #666; display: block; white-space: nowrap; }
    #custom-controls .form-control { font-size: 11px !important; padding: 5px 8px !important; height: 30px !important; width: 130px; border: 1px solid #ccc; border-radius: 3px; }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    #custom-controls-container { display: inline-flex; align-items: center; vertical-align: middle; }
    .table-secondary { background-color: #f8f9fa !important; opacity: 0.8; }
    .table-secondary td { text-decoration: line-through; color: #6c757d; }
    .table-secondary .badge-danger { text-decoration: none; font-size: 0.7rem; padding: 0.5rem 1rem; }
    .cancelado-badge { display: inline-block; min-width: 120px; }
    td[style*="display: none"], td.hidden-cell { display: none !important; }
    #dataTables-example667 tbody td { white-space: nowrap; }
    #dataTables-example667 tbody td:nth-child(1) { white-space: normal; }
    #dataTables-example667 input.form-control { font-size: 0.75rem; padding: 0.25rem 0.35rem; height: 28px; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }
    .dataTables_wrapper .dataTables_scrollHead,
    .dataTables_wrapper .dataTables_scrollBody { overflow: visible !important; }
    .dataTables_wrapper { overflow-x: auto; }
    .dataTables_scrollBody { overflow: visible !important; }
    .dataTables_scrollHead table, .dataTables_scrollBody table { width: 100% !important; }
    .dataTables_length select, .dataTables_filter input { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
    .dataTables_info, .dataTables_length, .dataTables_filter { font-size: 0.8rem; }
    h6 { font-weight: 600; margin-bottom: 1rem; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
    .total-container { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin-top: 20px; }
    .total-row { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 5px; font-size: 14px; }
    .total-row.final { font-weight: bold; font-size: 18px; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 10px; }
    .total-label { margin-right: 20px; text-align: right; }
    .total-value { width: 150px; text-align: right; }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <?php include('header.php');?>
    <div class="page-body-wrapper">
      <?php include('menu.php');?>
      <div class="page-body">
        <?php include_once("head_page.php")?>
        <?php
        if ($modo === 'pedido') {
          $puedeCrearOC = ($pedidoAprobado && tienePermiso(298));
        } else {
          $puedeCrearOC = true;
        }
        $mostrarCamposOC = ($modo === 'compra') || ($modo === 'pedido' && $puedeCrearOC);
        ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <?php if ($modo === 'compra') { ?>
                  <h5>Modificar Orden de Compra</h5>
                  <?php } else { ?>
                  <h5>Informacion del Pedido <?=$tipoPedido?> N&deg; <?=$id_pedido?></h5>
                  <?php if (isset($error)) { ?>
                  <div class="alert alert-danger"><?=$error?></div>
                  <?php } ?>
                  <?php } ?>
                </div>

                <form class="form theme-form" role="form" name="form1" method="post" action="<?=$actionForm?>" id="form-unificado"<?php if ($modo === 'pedido') { ?> onsubmit="return validarFormularioCompra();"<?php } ?>>
                  <div class="card-body">
                    <div class="row">

                      <?php if ($modo === 'pedido') { ?>
                      <div class="col-md-6">
                        <h6 class="mb-3">Datos del Pedido</h6>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Fecha Pedido</label>
                          <div class="col-sm-8"><?=$dataPedido['fecha_formatted']?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Proyecto</label>
                          <div class="col-sm-8"><?=$proyectoDisplay?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Lugar de Entrega</label>
                          <div class="col-sm-8"><?=$dataPedido['lugar_entrega']?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Recibe</label>
                          <div class="col-sm-8"><?=$dataPedido['cuenta_recibe']?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Estado</label>
                          <div class="col-sm-8"><?=$dataPedido['estado_pedido']?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Solicitante</label>
                          <div class="col-sm-8"><?=$dataPedido['cuenta_solicitante']?></div>
                        </div>
                      </div>
                      <?php } ?>

                      <?php if ($mostrarCamposOC) { ?>
                      <div class="<?=($modo === 'pedido') ? 'col-md-6' : 'col'?>">
                        <?php if ($modo === 'pedido') { ?>
                        <h6 class="mb-3">Datos de la Orden de Compra</h6>
                        <?php } ?>

                        <?php if ($modo === 'compra') { ?>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label font-weight-bold">Nro OC</label>
                          <div class="<?=$inpCol?> col-form-label"><?=$dataCompra['id'].'/'.$dataCompra['nro_revision']?></div>
                        </div>
                        <?php } ?>

                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label">Proveedor(*)</label>
                          <div class="<?=$inpCol?>">
                            <select name="id_cuenta_proveedor" id="id_cuenta_proveedor" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php foreach ($proveedores as $p) { ?>
                              <option value="<?=$p['id']?>"<?=($p['id'] == $val['proveedor']) ? ' selected' : ''?>><?=$p['nombre']?></option>
                              <?php } ?>
                            </select>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label">Fecha Emision(*)</label>
                          <div class="<?=$inpCol?>">
                            <input name="fecha_emision" type="date" onfocus="this.showPicker()" value="<?=$val['fecha_emision']?>" class="form-control"<?=($modo === 'compra') ? ' readonly="readonly"' : ' required'?>>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label">Fecha Entrega</label>
                          <div class="<?=$inpCol?>">
                            <input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?=$val['fecha_entrega']?>" class="form-control">
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label">Moneda(*)</label>
                          <div class="<?=$inpCol?>">
                            <select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12"<?=($modo === 'pedido') ? ' required' : ''?>>
                              <option value="">Seleccione...</option>
                              <?php foreach ($monedas as $m) { ?>
                              <option value="<?=$m['id']?>"<?=($m['id'] == $val['moneda']) ? ' selected' : ''?>><?=$m['moneda']?></option>
                              <?php } ?>
                            </select>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label">Tipo de Cambio <?php if ($modo === 'pedido') { ?><span id="tc_required_star" style="color:red; display:none;">(*)</span><?php } ?></label>
                          <div class="<?=$inpCol?>">
                            <input name="tipo_cambio_dia" id="tipo_cambio_dia" type="number" step="0.01" class="form-control" value="<?=$val['tipo_cambio']?>">
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label">Forma de Pago(*)</label>
                          <div class="<?=$inpCol?>">
                            <select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single col-sm-12"<?=($modo === 'pedido') ? ' required' : ''?>>
                              <option value="">Seleccione...</option>
                              <?php foreach ($formasPago as $fp) { ?>
                              <option value="<?=$fp['id']?>"<?=($fp['id'] == $val['forma_pago']) ? ' selected' : ''?>><?=$fp['forma_pago']?></option>
                              <?php } ?>
                            </select>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label">IVA(*)</label>
                          <div class="<?=$inpCol?>">
                            <select name="id_tipo_iva" id="id_tipo_iva" class="form-control"<?=($modo === 'pedido') ? ' required' : ''?>>
                              <?php if (!empty($tiposIva)) { ?>
                              <?php foreach ($tiposIva as $ti) {
                                $sel = '';
                                if ($modo === 'compra' && $ti['id'] == $val['tipo_iva']) {
                                  $sel = ' selected';
                                } elseif ($modo === 'pedido' && $ti['tasa'] == 21.00) {
                                  $sel = ' selected';
                                }
                              ?>
                              <option value="<?=$ti['id']?>" data-tasa="<?=$ti['tasa']?>"<?=$sel?>><?=(float)$ti['tasa']?>%</option>
                              <?php } ?>
                              <?php } else { ?>
                              <option value="1">Exento (0%)</option>
                              <option value="2">10.5%</option>
                              <option value="3" selected>21%</option>
                              <?php } ?>
                            </select>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label"><?=($modo === 'compra') ? 'Descuento (%)' : 'Descuento General (%)'?></label>
                          <div class="<?=$inpCol?>">
                            <input name="descuento" type="number" step="0.01" min="0" max="100" class="form-control" value="<?=$val['descuento']?>" id="descuento-input">
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label">Comentarios</label>
                          <div class="<?=$inpCol?>">
                            <textarea name="comentarios" class="form-control" rows="2"><?=$val['comentarios']?></textarea>
                          </div>
                        </div>

                        <?php if ($modo === 'compra') { ?>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label font-weight-bold">Estado</label>
                          <div class="<?=$inpCol?> col-form-label"><?=$estadoTexto?></div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label font-weight-bold">Subtotal</label>
                          <div class="<?=$inpCol?> col-form-label">$<?=number_format($subtotalCompra, 2)?></div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label font-weight-bold">IVA</label>
                          <div class="<?=$inpCol?> col-form-label">$<span id="iva-calculado"><?=number_format($dataCompra['iva'], 2)?></span></div>
                        </div>
                        <div class="form-group row">
                          <label class="<?=$lblCol?> col-form-label font-weight-bold">Total</label>
                          <div class="<?=$inpCol?> col-form-label">$<span id="total-calculado"><?=number_format($dataCompra['total']+$dataCompra['iva']-($subtotalCompra*$dataCompra['descuento']/100), 2)?></span></div>
                        </div>
                        <?php } ?>
                      </div>
                      <?php } ?>

                    </div>

                    <hr class="mt-4 mb-4">
                    <div class="row">
                      <div class="col-sm-12">
                        <?php if ($modo === 'pedido') { ?>
                        <h6 class="mb-3">Detalle de Conceptos</h6>
                        <div id="custom-controls" class="row mb-3" style="display:none; text-align:center;">
                          <div class="col-md-6" style="text-align:center;">
                            <label>Fecha Entrega General:</label>
                            <input id="fecha_entrega_general" type="date" onfocus="this.showPicker()" value="<?=$dataPedido['fecha']?>" class="form-control d-inline-block" style="font-size:12px;">
                          </div>
                          <div class="col-md-6" style="text-align:center;">
                            <label>Descuento General (%):</label>
                            <input id="descuento_general" type="number" step="0.01" class="form-control d-inline-block" style="font-size:12px;">
                          </div>
                        </div>
                        <?php } ?>

                        <div class="table-responsive">
                          <table class="display" id="dataTables-example667" style="width:100%">
                            <thead>
                              <tr>
                                <?php if ($modo === 'compra') { ?>
                                <th>Concepto</th>
                                <th>Precio</th>
                                <th>Precio Kg</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Entregado</th>
                                <th>Opciones</th>
                                <?php } else { ?>
                                <th>Concepto</th>
                                <th>Fec. Necesidad</th>
                                <th>Fec. Ult. Compra</th>
                                <th>Ultimo Precio</th>
                                <th>Requerido</th>
                                <th>Stock</th>
                                <th>Reserv.</th>
                                <th>Comprado</th>
                                <?php if ($puedeCrearOC) { ?>
                                <th>Cant. Solic.</th>
                                <th>Cant. Pedir</th>
                                <th>P. Unit.</th>
                                <th>P. x Kg</th>
                                <th>Desc %</th>
                                <th>Subtotal</th>
                                <th>F. Entrega</th>
                                <?php } ?>
                                <?php } ?>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                              $pdo = Database::connect();

                              if ($modo === 'compra') {
                                $sql = "SELECT d.id, m.concepto, d.cantidad, u.unidad_medida, d.id_material, d.precio, d.entregado, d.precio_kg
                                        FROM compras_detalle d
                                        INNER JOIN materiales m ON m.id = d.id_material
                                        INNER JOIN unidades_medida u ON u.id = d.id_unidad_medida
                                        WHERE d.id_compra = ?";
                                $q = $pdo->prepare($sql);
                                $q->execute([$id_compra]);
                                while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo '<tr>';
                                  echo '<td>'.$row['concepto'].'</td>';
                                  echo '<td>$'.number_format($row['precio'],2).'</td>';
                                  echo '<td>$'.number_format($row['precio_kg'],2).'</td>';
                                  echo '<td>'.$row['cantidad'].'</td>';
                                  echo '<td>$'.number_format($row['precio']*$row['cantidad'],2).'</td>';
                                  echo '<td>'.$row['entregado'].'</td>';
                                  echo '<td>';
                                  if ($dataCompra['id_estado_compra'] == 1 || $dataCompra['id_estado_compra'] == 2) {
                                    echo '<a href="modificarConceptoCompra.php?id='.$row['id'].'"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                                    echo '&nbsp;&nbsp;';
                                    echo '<a href="eliminarConceptoCompra.php?id='.$row['id'].'&id_compra='.$dataCompra['id'].'"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                                  }
                                  echo '</td>';
                                  echo '</tr>';
                                }
                              } else {
                                $sql = "SELECT pd.id, m.concepto, pd.cantidad,
                                        DATE_FORMAT(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad,
                                        u.unidad_medida, pd.id_material, pd.reservado, pd.comprado,
                                        pd.cancelado, m.peso_metro, m.largo, pd.id_unidad_medida
                                        FROM pedidos_detalle pd
                                        INNER JOIN materiales m ON m.id = pd.id_material
                                        INNER JOIN unidades_medida u ON u.id = pd.id_unidad_medida
                                        WHERE pd.id_pedido = ?";
                                $q = $pdo->prepare($sql);
                                $q->execute([$id_pedido]);

                                while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                                  $id_material = (int)$row['id_material'];
                                  $pendiente = (float)$row['cantidad'] - (float)$row['comprado'];
                                  $canceladoClass = ($row['cancelado'] == 1) ? 'table-secondary' : '';
                                  $peso_metro = (float)$row['peso_metro'];
                                  $largo = (float)$row['largo'];

                                  $q2 = $pdo->prepare("SELECT d.precio, DATE_FORMAT(c.fecha_emision,'%d/%m/%y') fecha_emision
                                    FROM compras_detalle d INNER JOIN compras c ON c.id = d.id_compra
                                    WHERE d.id_material = ? ORDER BY c.id DESC LIMIT 0,1");
                                  $q2->execute([$id_material]);
                                  $data2 = $q2->fetch(PDO::FETCH_ASSOC);
                                  $fecha_emision = !empty($data2['fecha_emision']) ? $data2['fecha_emision'] : '';
                                  $precio = !empty($data2['precio']) ? '$'.number_format($data2['precio'], 2) : '';

                                  $qStock = $pdo->prepare("SELECT SUM(id.saldo) AS disponible FROM ingresos_detalle id WHERE id_material = ?");
                                  $qStock->execute([$id_material]);
                                  $dataStock = $qStock->fetch(PDO::FETCH_ASSOC);
                                  $disponible = !empty($dataStock['disponible']) ? $dataStock['disponible'] : 0;
                                  ?>
                                  <tr class="<?=$canceladoClass?>" data-id="<?=$row['id']?>" data-peso="<?=$peso_metro?>" data-largo="<?=$largo?>">
                                    <td><?=$row['concepto']?></td>
                                    <td><?=$row['fecha_necesidad']?></td>
                                    <td><?=$fecha_emision?></td>
                                    <td><?=$precio?></td>
                                    <td><?=(float)$row['cantidad'].' '.$row['unidad_medida']?></td>
                                    <td class="text-center"><?=$disponible?></td>
                                    <td class="text-center"><?=$row['reservado']?></td>
                                    <td class="text-center"><?=(float)$row['comprado']?></td>
                                    <?php if ($puedeCrearOC) { ?>
                                    <td class="text-center"><?=($row['cancelado']==1 ? '0' : $pendiente)?></td>
                                    <td class="cantidad-col" data-cancelado="<?=$row['cancelado']?>" data-cantidad="<?=$pendiente?>" data-id="<?=$row['id']?>">
                                      <?php if ($pendiente > 0 && $row['cancelado'] != 1) { ?>
                                      <input name="cantidad_<?=$row['id']?>" type="number" step="0.01" min="0" max="<?=$pendiente?>" class="form-control cantidad-input" value="<?=$pendiente?>">
                                      <?php } elseif ($row['cancelado'] == 1) { ?>
                                      <span class="badge badge-danger cancelado-badge">Cancelado</span>
                                      <?php } ?>
                                    </td>
                                    <td class="precio-col" data-cancelado="<?=$row['cancelado']?>">
                                      <?php if ($pendiente > 0 && $row['cancelado'] != 1) { ?>
                                      <input name="precio_<?=$row['id']?>" type="number" step="0.0001" class="form-control precio-input" value="0">
                                      <?php } ?>
                                    </td>
                                    <td class="preciokg-col" data-cancelado="<?=$row['cancelado']?>">
                                      <?php if ($pendiente > 0 && $row['cancelado'] != 1) { ?>
                                      <input name="preciokg_<?=$row['id']?>" type="number" step="0.0001" class="form-control preciokg-input" value="0">
                                      <?php } ?>
                                    </td>
                                    <td class="descuento-col" data-cancelado="<?=$row['cancelado']?>">
                                      <?php if ($pendiente > 0 && $row['cancelado'] != 1) { ?>
                                      <input name="descuento_<?=$row['id']?>" type="number" step="0.1" min="0" max="100" class="form-control descuento-input" value="0">
                                      <?php } ?>
                                    </td>
                                    <td class="subtotal-col text-right" data-cancelado="<?=$row['cancelado']?>">
                                      <?php if ($pendiente > 0 && $row['cancelado'] != 1) { ?>
                                      <span class="subtotal-cell">0.00</span>
                                      <?php } ?>
                                    </td>
                                    <td class="fecha-col" data-cancelado="<?=$row['cancelado']?>">
                                      <?php if ($pendiente > 0 && $row['cancelado'] != 1) { ?>
                                      <input name="fecha_entrega_<?=$row['id']?>" type="date" onfocus="this.showPicker()" class="form-control fecha-entrega-input" value="<?=$dataPedido['fecha']?>">
                                      <?php } ?>
                                    </td>
                                    <?php } ?>
                                  </tr>
                                <?php }
                              }
                              Database::disconnect();
                              ?>
                            </tbody>
                          </table>
                        </div>

                        <?php if ($modo === 'pedido' && $puedeCrearOC) { ?>
                        <div class="row justify-content-end">
                          <div class="col-md-4">
                            <div class="total-container">
                              <div class="total-row">
                                <span class="total-label">Subtotal Neto:</span>
                                <span class="total-value" id="lbl_neto">$ 0.00</span>
                              </div>
                              <div class="total-row">
                                <span class="total-label">Descuento (<span id="lbl_desc_pct">0</span>%):</span>
                                <span class="total-value" id="lbl_desc_monto">$ 0.00</span>
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
                          <i><strong>NOTA:</strong> Si ingresa Precio x KG > 0, el precio se calculara como: (Precio x KG) * (Peso por Metro * Largo). Si el largo no esta definido para el material, se usara solo el Peso por Metro.</i><br/>
                          <i>Para guardar una compra, debe ingresar al menos un concepto con cantidad mayor a 0 y al menos uno de los dos precios (Unitario o x Kg).</i>
                        </div>
                        <?php } ?>
                      </div>
                    </div>
                  </div>

                  <div class="card-footer">
                    <div class="col-sm-12 text-center">
                      <?php if ($modo === 'compra') { ?>
                      <button class="btn btn-success" type="submit" name="btn_guardar"><?=$textoBotonSubmit?></button>
                      <?php } elseif ($puedeCrearOC) { ?>
                      <button class="btn btn-success" type="submit"><?=$textoBotonSubmit?></button>
                      <?php } ?>
                      <a href="<?=$urlVolver?>" class="btn btn-light"><?=$textoVolver?></a>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php include("footer.php"); ?>
    </div>
  </div>

  <script src="assets/js/jquery-3.2.1.min.js"></script>
  <script src="assets/js/bootstrap/popper.min.js"></script>
  <script src="assets/js/bootstrap/bootstrap.js"></script>
  <script src="assets/js/icons/feather-icon/feather.min.js"></script>
  <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
  <script src="assets/js/sidebar-menu.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/chat-menu.js"></script>
  <script src="assets/js/tooltip-init.js"></script>
  <script src="assets/js/typeahead-search/handlebars.js"></script>
  <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
  <script src="assets/js/script.js"></script>
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
  var DT_LANG = {
    "decimal": "", "emptyTable": "No hay informacion",
    "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
    "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
    "infoFiltered": "(Filtrado de _MAX_ total registros)",
    "thousands": ",", "lengthMenu": "Mostrar _MENU_ Registros",
    "loadingRecords": "Cargando...", "processing": "Procesando...",
    "search": "Buscar:", "zeroRecords": "No hay resultados",
    "paginate": { "first": "Primero", "last": "Ultimo", "next": "Siguiente", "previous": "Anterior" }
  };

  $(document).ready(function() {
    var MODO = '<?=$modo?>';

    if (MODO === 'compra') {
      $('#dataTables-example667').DataTable({
        stateSave: false,
        responsive: false,
        language: DT_LANG
      });

      function calcularTotales() {
        var subtotal = <?=($modo === 'compra') ? ($subtotalCompra ?? 0) : 0?>;
        var tasa = parseFloat($('#id_tipo_iva option:selected').data('tasa')) || 0;
        var iva = subtotal * (tasa / 100);
        var descuentoPct = parseFloat($('#descuento-input').val()) || 0;
        var descuento = subtotal * (descuentoPct / 100);
        var total = subtotal + iva - descuento;
        $('#iva-calculado').text(iva.toFixed(2));
        $('#total-calculado').text(total.toFixed(2));
      }

      $('#descuento-input, #id_tipo_iva').on('input change', function() {
        calcularTotales();
      });

    } else {
      function handleCanceledRowCells($row) {
        var cantidadCol = $row.find('.cantidad-col');
        if (cantidadCol.data('cancelado') == 1) {
          $row.find('.precio-col, .preciokg-col, .descuento-col, .subtotal-col, .fecha-col').hide();
          cantidadCol.attr('colspan', '6').addClass('text-center');
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
        createdRow: function(row) {
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
          { width: "90px", targets: 13, orderable: false, className: "text-right" },
          { width: "90px", targets: 14, orderable: false }
        ],
        language: DT_LANG,
        drawCallback: function() {
          this.api().rows().every(function() {
            handleCanceledRowCells($(this.node()));
          });
        },
        initComplete: function() {
          var customControls = $('#custom-controls').detach();
          $('#custom-controls-container').append(customControls);
          customControls.show();

          var wrapper = $('#dataTables-example667_wrapper');
          var lengthDiv = wrapper.find('.dataTables_length');
          var filterDiv = wrapper.find('.dataTables_filter');
          var customContainer = $('#custom-controls-container');

          var topRow = $('<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px; flex-wrap:nowrap;"></div>');
          lengthDiv.css({'margin': '0', 'flex': '0 0 auto'});
          customContainer.css({'margin': '0 15px', 'flex': '1 1 auto', 'min-width': '300px'});
          filterDiv.css({'margin': '0', 'flex': '0 0 auto'});
          lengthDiv.parent().prepend(topRow);
          topRow.append(lengthDiv).append(customContainer).append(filterDiv);
          lengthDiv.parent().find('.dataTables_length').not(lengthDiv).hide();
          filterDiv.parent().find('.dataTables_filter').not(filterDiv).hide();
        }
      });

      $('#id_moneda').on('change', function() {
        var esUSD = $(this).val() == 1;
        $('#tipo_cambio_dia').prop('required', esUSD);
        if (esUSD) { $('#tc_required_star').show(); }
        else { $('#tc_required_star').hide(); }
      }).trigger('change');

      $('#descuento_general').on('input', function() {
        $('.descuento-input').val($(this).val()).trigger('input');
      });

      $('#fecha_entrega_general').on('change', function() {
        $('.fecha-entrega-input').val($(this).val());
      });

      function calcularFila(row) {
        var cantidad = parseFloat(row.find('.cantidad-input').val()) || 0;
        var precioUnit = parseFloat(row.find('.precio-input').val()) || 0;
        var precioKg = parseFloat(row.find('.preciokg-input').val()) || 0;
        var descuento = parseFloat(row.find('.descuento-input').val()) || 0;
        var pesoMetro = parseFloat(row.data('peso')) || 0;
        var largoMm = parseFloat(row.data('largo')) || 0;
        var precioParaCalculo = precioUnit;

        if (precioKg > 0) {
          var largoMetros = (largoMm > 0) ? largoMm / 1000 : 1;
          precioParaCalculo = precioKg * (pesoMetro * largoMetros);
        }

        var subtotalBruto = cantidad * precioParaCalculo;
        var subtotalNeto = subtotalBruto * (1 - descuento / 100);

        row.find('.subtotal-cell').text(subtotalNeto.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 4}));
        row.data('subtotal', subtotalNeto);
        calcularTotalesGenerales();
      }

      function calcularTotalesGenerales() {
        var totalNeto = 0;
        $('#dataTables-example667 tbody tr').each(function() {
          totalNeto += $(this).data('subtotal') || 0;
        });

        var tasaIva = parseFloat($('#id_tipo_iva option:selected').data('tasa')) || 0;
        var descuentoPct = parseFloat($('#descuento-input').val()) || 0;
        var descuentoMonto = totalNeto * (descuentoPct / 100);
        var baseIva = totalNeto - descuentoMonto;
        var montoIva = baseIva * (tasaIva / 100);
        var totalFinal = baseIva + montoIva;

        $('#lbl_neto').text('$ ' + totalNeto.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#lbl_desc_pct').text(descuentoPct % 1 === 0 ? descuentoPct : descuentoPct.toFixed(2));
        $('#lbl_desc_monto').text('$ ' + descuentoMonto.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#lbl_tasa_iva').text(tasaIva);
        $('#lbl_iva').text('$ ' + montoIva.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#lbl_total').text('$ ' + totalFinal.toLocaleString('es-AR', {minimumFractionDigits: 2}));
      }

      $(document).on('input', '.cantidad-input, .precio-input, .preciokg-input, .descuento-input', function() {
        var row = $(this).closest('tr');
        if ($(this).hasClass('precio-input')) {
          if ((parseFloat($(this).val()) || 0) > 0) row.find('.preciokg-input').val(0).prop('disabled', true);
          else row.find('.preciokg-input').prop('disabled', false);
        }
        if ($(this).hasClass('preciokg-input')) {
          if ((parseFloat($(this).val()) || 0) > 0) row.find('.precio-input').val(0).prop('disabled', true);
          else row.find('.precio-input').prop('disabled', false);
        }
        calcularFila(row);
      });

      $('#id_tipo_iva, #descuento-input').on('change input', function() {
        calcularTotalesGenerales();
      });
    }

    if ($('.js-example-basic-single').length) {
      $('.js-example-basic-single').select2();
    }
  });

  <?php if ($modo === 'pedido') { ?>
  function validarFormularioCompra() {
    if ($('#id_cuenta_proveedor').val() == '') {
      alert('Debe seleccionar un Proveedor.');
      $('#id_cuenta_proveedor').select2('open');
      return false;
    }
    var idMoneda = $('#id_moneda').val();
    if (idMoneda == '') {
      alert('Debe seleccionar una Moneda.');
      $('#id_moneda').select2('open');
      return false;
    }
    var tipoCambio = $('#tipo_cambio_dia').val();
    if (idMoneda == 1 && (tipoCambio == '' || parseFloat(tipoCambio) <= 0)) {
      alert('Debe ingresar un Tipo de Cambio valido para la moneda USD.');
      $('#tipo_cambio_dia').focus();
      return false;
    }
    if ($('#id_forma_pago').val() == '') {
      alert('Debe seleccionar una Forma de Pago.');
      $('#id_forma_pago').select2('open');
      return false;
    }
    if ($('#id_tipo_iva').val() == '') {
      alert('Debe seleccionar el tipo de IVA.');
      return false;
    }
    var hayConceptoValido = false;
    var valid = true;
    $('.cantidad-input').each(function() {
      var qty = parseFloat($(this).val()) || 0;
      var row = $(this).closest('tr');
      var prc = parseFloat(row.find('.precio-input').val()) || 0;
      var prcKg = parseFloat(row.find('.preciokg-input').val()) || 0;
      if (qty > 0) {
        if (prc <= 0 && prcKg <= 0) {
          alert('Hay items con cantidad seleccionada pero sin precio cargado.');
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
  <?php } ?>
  </script>
</body>
</html>