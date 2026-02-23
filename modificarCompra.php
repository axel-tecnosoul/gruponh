<?php
$modoDebug = 0;

require("config.php");
require_once("funciones.php");
require_once("PHPMailer/class.phpmailer.php");
require_once("PHPMailer/class.smtp.php");

if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
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
  $sql = "SELECT c.id_estado_compra, c.id_pedido FROM compras c WHERE c.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_compra]);
  $data_estado = $q->fetch(PDO::FETCH_ASSOC);

  if (!$data_estado || $data_estado['id_estado_compra'] != 1) {
    Database::disconnect();
    header("Location: listarCompras.php");
    exit();
  }
  $id_pedido = $data_estado['id_pedido'];
} else {
  $sql = "SELECT id_estado FROM pedidos WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_pedido]);
  $est = $q->fetch(PDO::FETCH_ASSOC);
  if (!in_array($est['id_estado'], [3, 4])) {
    Database::disconnect();
    header("Location: listarPedidos.php");
    exit();
  }
}

Database::disconnect();

if (!empty($_POST)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->beginTransaction();

  try {
    $transaccionExitosa = false;
    $error_message = '';

    if ($_POST['id_moneda'] == 1 && (empty($_POST['tipo_cambio_dia']) || $_POST['tipo_cambio_dia'] <= 0)) {
      $error_message = 'Para USD, el Tipo de Cambio es obligatorio.';
    }
    if (empty($_POST['id_cuenta_proveedor'])) $error_message = "Seleccione un Proveedor.";
    if (empty($_POST['id_moneda'])) $error_message = "Seleccione una Moneda.";
    if (empty($_POST['id_forma_pago'])) $error_message = "Seleccione Forma de Pago.";
    if (empty($_POST['fecha_emision'])) $error_message = "Ingrese Fecha de Emisión.";

    if (!empty($error_message)) throw new Exception($error_message);

    $tasa_iva = 0;
    $qTasa = $pdo->prepare("SELECT tasa FROM tipos_iva WHERE id = ?");
    $qTasa->execute([$_POST['id_tipo_iva']]);
    $dt = $qTasa->fetch(PDO::FETCH_ASSOC);
    if ($dt) $tasa_iva = (float)$dt['tasa'];

    $items_procesar = [];
    $totalNeto = 0;

    foreach ($_POST as $key => $val) {
      if (strpos($key, 'cantidad_') === 0) {
        $id_ref = substr($key, 9);
        $cantidad = floatval($val);

        if ($cantidad > 0) {
          $precio = floatval($_POST['precio_' . $id_ref] ?? 0);
          $precioKg = floatval($_POST['preciokg_' . $id_ref] ?? 0);
          $descuentoItem = floatval($_POST['descuento_' . $id_ref] ?? 0);
          $fechaEntrega = $_POST['fecha_entrega_' . $id_ref] ?? $_POST['fecha_entrega'];

          $id_material = $_POST['id_material_' . $id_ref];
          $id_unidad = $_POST['id_unidad_' . $id_ref];
          $peso_metro = floatval($_POST['peso_' . $id_ref] ?? 0);
          $largo = floatval($_POST['largo_' . $id_ref] ?? 0);

          $precioGuardar = $precio;
          $subtotalLinea = 0;

          if ($precioKg > 0) {
            $peso_calc = $peso_metro;
            if ($largo > 0) $peso_calc = $peso_metro * ($largo / 1000);

            $precioUnitarioCalc = $precioKg * $peso_calc;
            $subtotalBruto = $cantidad * $precioUnitarioCalc;
            $precioGuardar = 0;
          } else {
            $subtotalBruto = $cantidad * $precio;
          }

          $subtotalLinea = $subtotalBruto * (1 - ($descuentoItem / 100));
          $totalNeto += $subtotalLinea;

          $items_procesar[] = [
            'id_ref' => $id_ref,
            'id_material' => $id_material,
            'cantidad' => $cantidad,
            'id_unidad' => $id_unidad,
            'precio' => $precioGuardar,
            'precio_kg' => $precioKg,
            'subtotal' => $subtotalLinea,
            'descuento' => $descuentoItem,
            'fecha_entrega' => $fechaEntrega
          ];
        }
      }
    }

    if (empty($items_procesar)) throw new Exception("Debe haber al menos un ítem con cantidad mayor a 0.");

    $desc_gral_pct = floatval($_POST['descuento'] ?? 0);
    $desc_gral_monto = $totalNeto * ($desc_gral_pct / 100);
    $monto_iva = ($totalNeto - $desc_gral_monto) * ($tasa_iva / 100);
    $totalFinal = $totalNeto - $desc_gral_monto + $monto_iva;

    $id_param = ($_POST['id_moneda'] == 1) ? 11 : 10;
    $qP = $pdo->prepare("SELECT valor FROM parametros WHERE id = ?");
    $qP->execute([$id_param]);
    $limite = $qP->fetchColumn() ?: 0;

    $nuevo_estado = ($totalFinal < $limite) ? 3 : 1;

    if ($modo === 'compra') {
      $sql = "UPDATE compras SET id_cuenta_proveedor=?, fecha_emision=?, fecha_entrega=?, id_moneda=?, tipo_cambio_dia=?, descuento=?, id_forma_pago=?, comentarios=?, total=?, iva=?, id_tipo_iva=?, id_estado_compra=? WHERE id=?";
      $pdo->prepare($sql)->execute([
        $_POST['id_cuenta_proveedor'],
        $_POST['fecha_emision'],
        $_POST['fecha_entrega'],
        $_POST['id_moneda'],
        $_POST['tipo_cambio_dia'],
        $desc_gral_pct,
        $_POST['id_forma_pago'],
        $_POST['comentarios'],
        $totalFinal,
        $monto_iva,
        $_POST['id_tipo_iva'],
        $nuevo_estado,
        $id_compra
      ]);

      foreach ($items_procesar as $item) {
        $sqlD = "UPDATE compras_detalle SET cantidad=?, precio=?, precio_kg=?, subtotal=?, descuento=?, fecha_entrega=? WHERE id=?";
        $pdo->prepare($sqlD)->execute([
          $item['cantidad'],
          $item['precio'],
          $item['precio_kg'],
          $item['subtotal'],
          $item['descuento'],
          $item['fecha_entrega'],
          $item['id_ref']
        ]);
      }
      $targetId = $id_compra;
      $accionLog = "Modificacion de orden de compra";
    } else {
      $sql = "INSERT INTO compras (id_pedido, id_cuenta_proveedor, fecha_emision, fecha_entrega, id_forma_pago, id_tipo_iva, id_estado_compra, nro_oc, total, iva, comentarios, id_moneda, tipo_cambio_dia, comentarios_revision, descuento, nro_revision) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)";
      $pdo->prepare($sql)->execute([
        $id_pedido,
        $_POST['id_cuenta_proveedor'],
        $_POST['fecha_emision'],
        $_POST['fecha_entrega'],
        $_POST['id_forma_pago'],
        $_POST['id_tipo_iva'],
        $nuevo_estado,
        '',
        $totalNeto,
        $monto_iva,
        $_POST['comentarios'],
        $_POST['id_moneda'],
        $_POST['tipo_cambio_dia'],
        'Revision Original',
        $desc_gral_pct
      ]);
      $targetId = $pdo->lastInsertId();

      $nroOC = $id_pedido . '/' . $targetId;
      $pdo->prepare("UPDATE compras SET nro_oc=? WHERE id=?")->execute([$nroOC, $targetId]);

      foreach ($items_procesar as $item) {
        $sqlI = "INSERT INTO compras_detalle(id_compra, id_material, cantidad, id_unidad_medida, precio, precio_kg, subtotal, descuento, fecha_entrega) VALUES (?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sqlI)->execute([
          $targetId,
          $item['id_material'],
          $item['cantidad'],
          $item['id_unidad'],
          $item['precio'],
          $item['precio_kg'],
          $item['subtotal'],
          $item['descuento'],
          $item['fecha_entrega']
        ]);
      }
      $accionLog = "Nueva orden de compra";
    }

    foreach ($items_procesar as $item) {
      $idMat = $item['id_material'];
      $sqlSum = "SELECT SUM(cd.cantidad) FROM compras_detalle cd JOIN compras c ON c.id=cd.id_compra WHERE c.id_pedido=? AND cd.id_material=? AND c.id_estado_compra NOT IN (5)";
      $qSum = $pdo->prepare($sqlSum);
      $qSum->execute([$id_pedido, $idMat]);
      $totalComprado = $qSum->fetchColumn() ?: 0;

      $pdo->prepare("UPDATE pedidos_detalle SET comprado=? WHERE id_pedido=? AND id_material=?")->execute([$totalComprado, $id_pedido, $idMat]);

      $sqlComp = "SELECT cd.id FROM computos_detalle cd JOIN computos c ON c.id=cd.id_computo JOIN pedidos p ON p.id_computo=c.id WHERE p.id=? AND cd.id_material=?";
      $qComp = $pdo->prepare($sqlComp);
      $qComp->execute([$id_pedido, $idMat]);
      $compDet = $qComp->fetch(PDO::FETCH_ASSOC);
      if ($compDet) {
        $pdo->prepare("UPDATE computos_detalle SET comprado=? WHERE id=?")->execute([$totalComprado, $compDet['id']]);
      }
    }

    $cntOC = $pdo->query("SELECT COUNT(*) FROM compras WHERE id_pedido=$id_pedido")->fetchColumn();
    if ($cntOC > 0) $pdo->query("UPDATE pedidos SET id_estado=4 WHERE id=$id_pedido");

    $pdo->prepare("INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(), ?, ?, 'Compras', ?)")
      ->execute([$_SESSION['user']['id'], $accionLog, "verCompra.php?id=$targetId"]);

    if ($modo !== 'compra') {
      $estTxt = ($nuevo_estado == 3) ? "APROBADA (Automatica)" : "Pendiente";
      $asunto = "Compras - Nueva OC #$targetId ($estTxt)";
      $body = "OC Generada #$targetId. Total: $" . number_format($totalFinal, 2);
      crearNotificacion($pdo, 4, $targetId, "OC #$targetId - $estTxt", $asunto, $body);
    }

    $pdo->commit();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Operación exitosa.'];
    header("Location: listarCompras.php");
    exit();
  } catch (Exception $e) {
    $pdo->rollback();
    $error = $e->getMessage();
  }
  Database::disconnect();
}


$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$proveedores = $pdo->query("SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta IN (5) AND activo=1 AND anulado=0")->fetchAll();
$monedas = $pdo->query("SELECT id, moneda FROM monedas")->fetchAll();
$formasPago = $pdo->query("SELECT id, forma_pago FROM formas_pago")->fetchAll();
$tiposIva = $pdo->query("SELECT id, tasa FROM tipos_iva ORDER BY tasa")->fetchAll();

$sql = "SELECT pe.id, pe.id_computo, pe.id_proyecto, DATE_FORMAT(pe.fecha, '%d/%m/%Y') AS fecha_formatted, pe.fecha, pe.lugar_entrega, pe.id_cuenta_recibe, pe.aprobado, c.id_tarea, c.id_cuenta_solicitante, c.nro_revision AS computo_revision, c.nro AS computo_numero, COALESCE(pc.id, pd.id) AS proyecto_id, COALESCE(pc.nombre, pd.nombre) AS proyecto_nombre, COALESCE(pc.nro, pd.nro) AS proyecto_nro, COALESCE(sc.nro_sitio, sd.nro_sitio) AS nro_sitio, COALESCE(sc.nro_subsitio, sd.nro_subsitio) AS nro_subsitio, cu.nombre AS cuenta_solicitante, cu2.nombre AS cuenta_recibe, pe.id_estado, ep.estado AS estado_pedido FROM pedidos pe LEFT JOIN computos c ON c.id = pe.id_computo LEFT JOIN tareas t ON t.id = c.id_tarea LEFT JOIN proyectos pc ON pc.id = t.id_proyecto LEFT JOIN sitios sc ON sc.id = pc.id_sitio LEFT JOIN proyectos pd ON pd.id = pe.id_proyecto LEFT JOIN sitios sd ON sd.id = pd.id_sitio LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante LEFT JOIN cuentas cu2 ON cu2.id = pe.id_cuenta_recibe LEFT JOIN estados_pedidos ep ON ep.id = pe.id_estado WHERE pe.id = ?";
$qPed = $pdo->prepare($sql);
$qPed->execute([$id_pedido]);
$data = $qPed->fetch(PDO::FETCH_ASSOC);

$proyectoDisplay = '';
$codigoObra = '';
$tipoPedido = '';
if ($data) {
  $codigoObraPartes = array_filter([$data['nro_sitio'] ?? null, $data['nro_subsitio'] ?? null, $data['proyecto_nro'] ?? null], function ($v) {
    return $v !== null && $v !== '';
  });
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

$form = [
  'prov' => '',
  'f_emision' => date('Y-m-d'),
  'f_entrega' => $data['fecha'],
  'moneda' => '',
  'tc' => '',
  'pago' => '',
  'iva' => 3,
  'desc' => '',
  'obs' => ''
];

if ($modo === 'compra') {
  $sqlC = "SELECT * FROM compras WHERE id=?";
  $qC = $pdo->prepare($sqlC);
  $qC->execute([$id_compra]);
  $dataC = $qC->fetch(PDO::FETCH_ASSOC);

  $form['prov'] = $dataC['id_cuenta_proveedor'];
  $form['f_emision'] = $dataC['fecha_emision'];
  $form['f_entrega'] = $dataC['fecha_entrega'];
  $form['moneda'] = $dataC['id_moneda'];
  $form['tc'] = $dataC['tipo_cambio_dia'];
  $form['pago'] = $dataC['id_forma_pago'];
  $form['iva'] = $dataC['id_tipo_iva'];
  $form['desc'] = $dataC['descuento'];
  $form['obs'] = $dataC['comentarios'];
}

$titulo = "Gestionar Pedido $tipoPedido N°$id_pedido";

if ($modo === 'compra') {
  $titulo = "Modificar Orden de Compra del Pedido $tipoPedido N°$id_pedido";
}

$action = ($modo === 'compra') ? "?id_compra=$id_compra" : "?id_pedido=$id_pedido";
$btnTxt = ($modo === 'compra') ? "Guardar Cambios" : "Generar OC";

?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?php include('head_forms.php'); ?>
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

    #dataTables-example667 {
      table-layout: fixed !important;
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

    #dataTables-example667 th:nth-child(15),
    #dataTables-example667 td:nth-child(15) {
      width: 90px !important;
      min-width: 90px !important;
      max-width: 90px !important;
      text-align: right;
    }

    <?php if ($modo === 'compra') { ?>#dataTables-example667 th:nth-child(16),
    #dataTables-example667 td:nth-child(16) {
      width: 40px !important;
      min-width: 40px !important;
      max-width: 40px !important;
      text-align: center;
    }

    <?php } ?>#custom-controls-container {
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

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    #custom-controls-container {
      display: inline-flex;
      align-items: center;
    }

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

    .cancelado-badge {
      display: inline-block;
      min-width: 120px;
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

    /* Estilo para el botón eliminar */
    .btn-eliminar-item {
      cursor: pointer;
      display: inline-block;
    }

    .btn-eliminar-item img {
      pointer-events: none;
    }
  </style>
</head>

<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body">
        <?php
        $ubicacion = ($modo === 'compra') ? "Modificar Orden de Compra" : "Gestión de Pedido y Nueva Orden de Compra";
        include_once("head_page.php"); ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5><?= $titulo ?></h5>
                  <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                  <?php if (!empty($_SESSION['flash_message'])) { ?>
                    <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?>"><?= $_SESSION['flash_message']['message'] ?></div>
                    <?php unset($_SESSION['flash_message']); ?>
                  <?php } ?>
                </div>
                <form class="form theme-form" role="form" method="post" action="<?= $action ?>" id="form-unificado" onsubmit="return validarFormularioCompra();">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6">
                        <h6 class="mb-3">Datos del Pedido</h6>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Fecha Pedido</label>
                          <div class="col-sm-8"><?= $data['fecha_formatted']; ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Proyecto</label>
                          <div class="col-sm-8"><?= $proyectoDisplay; ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Lugar de Entrega</label>
                          <div class="col-sm-8"><?= $data['lugar_entrega']; ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Recibe</label>
                          <div class="col-sm-8"><?= $data['cuenta_recibe'] ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Estado</label>
                          <div class="col-sm-8"><?= $data['estado_pedido']; ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Solicitante</label>
                          <div class="col-sm-8"><?= $data['cuenta_solicitante'] ?></div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <h6 class="mb-3">Datos de la Orden de Compra</h6>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Proveedor(*)</label>
                          <div class="col-sm-8">
                            <select name="id_cuenta_proveedor" id="id_cuenta_proveedor" class="js-example-basic-single w-100" required <?= ($modo === 'compra') ? 'disabled' : '' ?>>
                              <option value="">Seleccione...</option>
                              <?php foreach ($proveedores as $p) echo "<option value='{$p['id']}' " . ($p['id'] == $form['prov'] ? 'selected' : '') . ">{$p['nombre']}</option>"; ?>
                            </select>
                            <?php if ($modo === 'compra') { ?><input type="hidden" name="id_cuenta_proveedor" value="<?= $form['prov'] ?>"><?php } ?>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha Emisión(*)</label>
                          <div class="col-sm-8">
                            <input name="fecha_emision" type="date" onfocus="this.showPicker()" value="<?= $form['f_emision'] ?>" class="form-control" required <?= ($modo === 'compra') ? 'readonly' : '' ?>>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha Entrega</label>
                          <div class="col-sm-8">
                            <input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?= $form['f_entrega'] ?>" class="form-control" <?= ($modo === 'compra') ? 'readonly' : '' ?>>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Moneda(*)</label>
                          <div class="col-sm-8">
                            <select name="id_moneda" id="id_moneda" class="js-example-basic-single w-100" required <?= ($modo === 'compra') ? 'disabled' : '' ?>>
                              <option value="">Seleccione...</option>
                              <?php foreach ($monedas as $m) echo "<option value='{$m['id']}' " . ($m['id'] == $form['moneda'] ? 'selected' : '') . ">{$m['moneda']}</option>"; ?>
                            </select>
                            <?php if ($modo === 'compra') { ?><input type="hidden" name="id_moneda" value="<?= $form['moneda'] ?>"><?php } ?>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Tipo de Cambio <span id="tc_required_star" style="color:red; display: none;">(*)</span></label>
                          <div class="col-sm-8">
                            <input name="tipo_cambio_dia" id="tipo_cambio_dia" type="number" step="0.01" class="form-control" value="<?= $form['tc'] ?>" <?= ($modo === 'compra') ? 'readonly' : '' ?>>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">IVA(*)</label>
                          <div class="col-sm-8">
                            <select name="id_tipo_iva" id="id_tipo_iva" class="form-control" required <?= ($modo === 'compra') ? 'disabled' : '' ?>>
                              <?php foreach ($tiposIva as $ti) echo "<option value='{$ti['id']}' data-tasa='{$ti['tasa']}' " . ($ti['id'] == $form['iva'] ? 'selected' : '') . ">" . (float)$ti['tasa'] . "%</option>"; ?>
                            </select>
                            <?php if ($modo === 'compra') { ?><input type="hidden" name="id_tipo_iva" value="<?= $form['iva'] ?>"><?php } ?>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Descuento General</label>
                          <div class="col-sm-8">
                            <input name="descuento" type="number" step="0.01" class="form-control" value="<?= $form['desc'] ?>" <?= ($modo === 'compra') ? 'readonly' : '' ?>>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Forma de Pago(*)</label>
                          <div class="col-sm-8">
                            <select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single w-100" required <?= ($modo === 'compra') ? 'disabled' : '' ?>>
                              <option value="">Seleccione...</option>
                              <?php foreach ($formasPago as $fp) echo "<option value='{$fp['id']}' " . ($fp['id'] == $form['pago'] ? 'selected' : '') . ">{$fp['forma_pago']}</option>"; ?>
                            </select>
                            <?php if ($modo === 'compra') { ?><input type="hidden" name="id_forma_pago" value="<?= $form['pago'] ?>"><?php } ?>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Comentarios</label>
                          <div class="col-sm-8">
                            <textarea name="comentarios" class="form-control" rows="2" <?= ($modo === 'compra') ? 'readonly' : '' ?>><?= $form['obs'] ?></textarea>
                          </div>
                        </div>
                      </div>
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
                                <th>Fec. Necesidad</th>
                                <th>Fec. Últ. Compra</th>
                                <th>Último Precio</th>
                                <th>Requerido</th>
                                <th>Stock</th>
                                <th>Reserv.</th>
                                <th>Comprado</th>
                                <th>Cant. Solic.</th>
                                <th>Cant. Pedir</th>
                                <th>P. Unit.</th>
                                <th>P. x Kg</th>
                                <th>Desc %</th>
                                <th>Subtotal</th>
                                <th>F. Entrega</th>
                                <?php if ($modo === 'compra') { ?>
                                  <th style="text-align:center;">
                                    <img src="img/icon_baja.png" width="18" height="18" border="0" alt="Eliminar" title="Eliminar">
                                  </th>
                                <?php } ?>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                              if ($modo === 'compra') {
                                $sqlItems = "SELECT pd.id as pd_id, pd.cantidad as pd_cantidad, pd.comprado as pd_comprado, pd.reservado, pd.cancelado,
                                                                     date_format(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad,
                                                                     m.id as id_material, m.concepto, m.peso_metro, m.largo,
                                                                     u.unidad_medida, pd.id_unidad_medida,
                                                                     cd.id as cd_id, cd.cantidad as cd_cantidad, cd.precio as cd_precio, cd.precio_kg as cd_precio_kg, 
                                                                     cd.descuento as cd_descuento, cd.fecha_entrega as cd_fecha_entrega
                                                                     FROM pedidos_detalle pd
                                                                     JOIN materiales m ON m.id = pd.id_material
                                                                     JOIN unidades_medida u ON u.id = pd.id_unidad_medida
                                                                     LEFT JOIN compras_detalle cd ON cd.id_compra = ? AND cd.id_material = pd.id_material
                                                                     WHERE pd.id_pedido = ?";
                                $qItems = $pdo->prepare($sqlItems);
                                $qItems->execute([$id_compra, $id_pedido]);
                              } else {
                                $sqlItems = "SELECT pd.id as pd_id, pd.cantidad as pd_cantidad, pd.comprado as pd_comprado, pd.reservado, pd.cancelado,
                                                                     date_format(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad,
                                                                     m.id as id_material, m.concepto, m.peso_metro, m.largo,
                                                                     u.unidad_medida, pd.id_unidad_medida,
                                                                     NULL as cd_id, NULL as cd_cantidad, NULL as cd_precio, NULL as cd_precio_kg,
                                                                     NULL as cd_descuento, NULL as cd_fecha_entrega
                                                                     FROM pedidos_detalle pd
                                                                     JOIN materiales m ON m.id = pd.id_material
                                                                     JOIN unidades_medida u ON u.id = pd.id_unidad_medida
                                                                     WHERE pd.id_pedido = ?";
                                $qItems = $pdo->prepare($sqlItems);
                                $qItems->execute([$id_pedido]);
                              }

                              while ($row = $qItems->fetch(PDO::FETCH_ASSOC)) {
                                $id_material = (int)$row['id_material'];
                                $peso_metro = (float)$row['peso_metro'];
                                $largo = (float)$row['largo'];
                                $cancelado = (int)$row['cancelado'];
                                $canceladoClass = ($cancelado == 1) ? 'table-secondary' : '';

                                $pendiente = (float)$row['pd_cantidad'] - (float)$row['pd_comprado'];

                                if ($modo === 'compra' && $row['cd_id']) {
                                  $saldo_max = $pendiente + (float)$row['cd_cantidad'];
                                  $cant_actual = (float)$row['cd_cantidad'];
                                  $precio_actual = (float)$row['cd_precio'];
                                  $preciokg_actual = (float)$row['cd_precio_kg'];
                                  $descuento_actual = (float)$row['cd_descuento'];
                                  $fecha_entrega_actual = $row['cd_fecha_entrega'];
                                  $id_ref = $row['cd_id'];
                                } else {
                                  $saldo_max = $pendiente;
                                  $cant_actual = $pendiente;
                                  $precio_actual = 0;
                                  $preciokg_actual = 0;
                                  $descuento_actual = 0;
                                  $fecha_entrega_actual = $form['f_entrega'];
                                  $id_ref = $row['pd_id'];
                                }

                                $sql2 = "SELECT d.precio, date_format(c.fecha_emision,'%d/%m/%y') fecha_emision 
                                                                FROM compras_detalle d 
                                                                INNER JOIN compras c ON c.id = d.id_compra 
                                                                WHERE d.id_material = ? 
                                                                ORDER BY c.id DESC LIMIT 0,1";
                                $q2 = $pdo->prepare($sql2);
                                $q2->execute([$id_material]);
                                $data2 = $q2->fetch(PDO::FETCH_ASSOC);

                                $fecha_ult_compra = !empty($data2['fecha_emision']) ? $data2['fecha_emision'] : '';
                                $precio_ult = !empty($data2['precio']) ? "$" . number_format($data2['precio'], 2) : '';

                                $qStock = $pdo->prepare("SELECT SUM(saldo) FROM ingresos_detalle WHERE id_material = ?");
                                $qStock->execute([$id_material]);
                                $disponible = $qStock->fetchColumn() ?: 0;
                              ?>
                                <tr class="<?= $canceladoClass ?>" data-id="<?= $id_ref ?>" data-peso="<?= $peso_metro ?>" data-largo="<?= $largo ?>">
                                  <td>
                                    <?= $row['concepto'] ?>
                                    <input type="hidden" name="id_material_<?= $id_ref ?>" value="<?= $id_material ?>">
                                    <input type="hidden" name="id_unidad_<?= $id_ref ?>" value="<?= $row['id_unidad_medida'] ?>">
                                    <input type="hidden" name="peso_<?= $id_ref ?>" value="<?= $peso_metro ?>">
                                    <input type="hidden" name="largo_<?= $id_ref ?>" value="<?= $largo ?>">
                                  </td>
                                  <td><?= $row['fecha_necesidad'] ?></td>
                                  <td><?= $fecha_ult_compra ?></td>
                                  <td><?= $precio_ult ?></td>
                                  <td><?= (float)$row['pd_cantidad'] . ' ' . $row['unidad_medida'] ?></td>
                                  <td class="text-center"><?= $disponible ?></td>
                                  <td class="text-center"><?= $row['reservado'] ?></td>
                                  <td class="text-center"><?= (float)$row['pd_comprado'] ?></td>

                                  <td class="text-center"><?= ($cancelado == 1 ? '0' : (float)$pendiente) ?></td>

                                  <td class="cantidad-col" data-cancelado="<?= $cancelado ?>" data-cantidad="<?= $saldo_max ?>" data-id="<?= $id_ref ?>">
                                    <?php if ($saldo_max > 0 && $cancelado != 1) { ?>
                                      <input name="cantidad_<?= $id_ref ?>" type="number" step="0.01" min="0" max="<?= $saldo_max ?>" class="form-control cantidad-input" value="<?= (float)$cant_actual ?>">
                                    <?php } elseif ($cancelado == 1) { ?>
                                      <span class="badge badge-danger cancelado-badge">Cancelado</span>
                                    <?php } ?>
                                  </td>

                                  <td class="precio-col" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($saldo_max > 0 && $cancelado != 1) { ?>
                                      <input name="precio_<?= $id_ref ?>" type="number" step="0.0001" class="form-control precio-input" value="<?= (float)$precio_actual ?>">
                                    <?php } ?>
                                  </td>

                                  <td class="preciokg-col" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($saldo_max > 0 && $cancelado != 1) { ?>
                                      <input name="preciokg_<?= $id_ref ?>" type="number" step="0.0001" class="form-control preciokg-input" value="<?= (float)$preciokg_actual ?>">
                                    <?php } ?>
                                  </td>

                                  <td class="descuento-col" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($saldo_max > 0 && $cancelado != 1) { ?>
                                      <input name="descuento_<?= $id_ref ?>" type="number" step="0.1" min="0" max="100" class="form-control descuento-input" value="<?= (float)$descuento_actual ?>">
                                    <?php } ?>
                                  </td>

                                  <td class="subtotal-col text-right" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($saldo_max > 0 && $cancelado != 1) { ?>
                                      <span class="subtotal-cell">0.00</span>
                                    <?php } ?>
                                  </td>

                                  <td class="fecha-col" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($saldo_max > 0 && $cancelado != 1) { ?>
                                      <input name="fecha_entrega_<?= $id_ref ?>" type="date" onfocus="this.showPicker()" class="form-control fecha-entrega-input" value="<?= $fecha_entrega_actual ?>">
                                    <?php } ?>
                                  </td>

                                  <?php if ($modo === 'compra') { ?>
                                    <td class="text-center">
                                      <?php if ($saldo_max > 0 && $cancelado != 1 && $row['cd_id']) { ?>
                                        <a href="javascript:void(0);" class="btn-eliminar-item"
                                          data-id="<?= $row['cd_id'] ?>"
                                          data-id-compra="<?= $id_compra ?>"
                                          data-concepto="<?= htmlspecialchars($row['concepto']) ?>">
                                          <img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">
                                        </a>
                                      <?php } ?>
                                    </td>
                                  <?php } ?>
                                </tr>
                              <?php }
                              Database::disconnect();
                              ?>
                            </tbody>
                          </table>
                        </div>
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
                          <i><strong>NOTA:</strong> Si ingresa Precio x KG > 0, el precio se calculará como: (Precio x KG) * (Peso por Metro * Largo). Si el largo no está definido para el material, se usará solo el Peso por Metro.</i><br />
                          <i>Para guardar, debe ingresar al menos un concepto con cantidad mayor a 0 y al menos uno de los dos precios (Unitario o x Kg).</i>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="card-footer">
                    <div class="col-sm-12 text-center">
                      <div class="card-footer">
                          <div class="col-sm-12 text-center">
                            <button class="btn btn-success" type="submit"><?= $btnTxt ?></button>
                            <a href="<?= $modo == 'compra' ? 'listarCompras.php' : 'listarPedidos.php' ?>" class="btn btn-light">Volver</a>
                          </div>
                      </div>
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

  <?php if ($modo === 'compra') { ?>
  <div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-labelledby="modalEliminarLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEliminarLabel">Confirmar Eliminación</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          ¿Está seguro que desea eliminar el ítem <strong id="modalConceptoNombre"></strong>?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">Eliminar</button>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>

  <script src="assets/js/jquery-3.2.1.min.js"></script>
  <script src="assets/js/bootstrap/popper.min.js"></script>
  <script src="assets/js/bootstrap/bootstrap.js"></script>
  <script src="assets/js/icons/feather-icon/feather.min.js"></script>
  <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
  <script src="assets/js/sidebar-menu.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/chat-menu.js"></script>
  <script src="assets/js/tooltip-init.js"></script>
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
    $(document).ready(function() {

      function handleCanceledRowCells($row) {
        var cantidadCol = $row.find('.cantidad-col');
        if (cantidadCol.data('cancelado') == 1) {
          $row.find('.precio-col, .preciokg-col, .descuento-col, .subtotal-col, .fecha-col').hide();
          cantidadCol.attr('colspan', '6').addClass('text-center');
        }
      }

      var numColumns = $('#dataTables-example667 thead th').length;

      var colDefs = [
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
      ];

      <?php if ($modo === 'compra') { ?>
      colDefs.push({ width: "40px", targets: 15, orderable: false, className: "text-center" });
      <?php } ?>

      $('#dataTables-example667').DataTable({
        dom: 'lfrtip',
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
        columnDefs: colDefs,
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
          $('#dataTables-example667 tbody tr').each(function() {
            calcularFila($(this));
          });
        }
      });

      // Moneda - Tipo de cambio requerido para USD
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

      // Descuento y fecha general -> propagar
      $('input[name="descuento"]').on('input', function() {
        var valorDescuento = $(this).val();
        $('.descuento-input').val(valorDescuento).trigger('input');
      });

      $('input[name="fecha_entrega"]').on('change', function() {
        var valorFecha = $(this).val();
        $('.fecha-entrega-input').val(valorFecha);
      });

      // Validar cantidad max
      $(document).on('change', '.cantidad-input', function() {
        var val = parseFloat($(this).val()) || 0;
        var max = parseFloat($(this).attr('max')) || 0;
        if (val < 0) {
          $(this).val(0);
          calcularFila($(this).closest('tr'));
        }
        if (val > max && max > 0) {
          $(this).val(max);
          calcularFila($(this).closest('tr'));
        }
      });

      // Calcular fila
      function calcularFila(row) {
        let cantidad = parseFloat(row.find('.cantidad-input').val()) || 0;
        let precioUnit = parseFloat(row.find('.precio-input').val()) || 0;
        let precioKg = parseFloat(row.find('.preciokg-input').val()) || 0;
        let descuento = parseFloat(row.find('.descuento-input').val()) || 0;
        let pesoMetro = parseFloat(row.data('peso')) || 0;
        let largoMm = parseFloat(row.data('largo')) || 0;
        let precioParaCalculo = precioUnit;

        if (precioKg > 0) {
          let largoMetros = (largoMm > 0) ? largoMm / 1000 : 1;
          let pesoUnitario = pesoMetro * largoMetros;
          precioParaCalculo = precioKg * pesoUnitario;
        }

        let subtotalBruto = cantidad * precioParaCalculo;
        let montoDescuento = subtotalBruto * (descuento / 100);
        let subtotalNeto = subtotalBruto - montoDescuento;

        row.find('.subtotal-cell').text(subtotalNeto.toLocaleString('es-AR', {
          minimumFractionDigits: 2, maximumFractionDigits: 4
        }));
        row.data('subtotal', subtotalNeto);
        calcularTotalesGenerales();
      }

      function calcularTotalesGenerales() {
        let totalNeto = 0;
        $('#dataTables-example667 tbody tr').each(function() {
          totalNeto += $(this).data('subtotal') || 0;
        });

        let opcionIva = $('#id_tipo_iva').find(':selected');
        let tasaIva = parseFloat(opcionIva.data('tasa')) || 0;
        let montoIva = totalNeto * (tasaIva / 100);
        let totalFinal = totalNeto + montoIva;

        $('#lbl_neto').text('$ ' + totalNeto.toLocaleString('es-AR', { minimumFractionDigits: 2 }));
        $('#lbl_tasa_iva').text(tasaIva);
        $('#lbl_iva').text('$ ' + montoIva.toLocaleString('es-AR', { minimumFractionDigits: 2 }));
        $('#lbl_total').text('$ ' + totalFinal.toLocaleString('es-AR', { minimumFractionDigits: 2 }));
      }

      // Input precios - exclusión mutua
      $(document).on('input', '.cantidad-input, .precio-input, .preciokg-input, .descuento-input', function() {
        let row = $(this).closest('tr');

        if ($(this).hasClass('precio-input')) {
          if ((parseFloat($(this).val()) || 0) > 0) {
            row.find('.preciokg-input').val(0).prop('disabled', true);
          } else {
            row.find('.preciokg-input').prop('disabled', false);
          }
        }
        if ($(this).hasClass('preciokg-input')) {
          if ((parseFloat($(this).val()) || 0) > 0) {
            row.find('.precio-input').val(0).prop('disabled', true);
          } else {
            row.find('.precio-input').prop('disabled', false);
          }
        }
        calcularFila(row);
      });

      $('#id_tipo_iva').on('change', function() {
        calcularTotalesGenerales();
      });

      // Eliminar concepto (solo modo compra)
      <?php if ($modo === 'compra') { ?>
      var eliminarItemId = null;
      var eliminarItemIdCompra = null;

      $(document).on('click', '.btn-eliminar-item', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this).closest('.btn-eliminar-item');
        eliminarItemId = $btn.attr('data-id');
        eliminarItemIdCompra = $btn.attr('data-id-compra');
        var concepto = $btn.attr('data-concepto');

        if (eliminarItemId && eliminarItemIdCompra) {
          $('#modalConceptoNombre').text(concepto);
          $('#modalEliminar').modal('show');
        } else {
          alert('Error: No se pudieron obtener los datos del ítem a eliminar.');
        }
      });

      $(document).on('click', '#btnConfirmarEliminar', function() {
        if (eliminarItemId && eliminarItemIdCompra) {
          window.location.href = 'eliminarConceptoCompra.php?id=' + eliminarItemId + '&id_compra=' + eliminarItemIdCompra;
        }
      });
      <?php } ?>

    });

    function validarFormularioCompra() {
      if ($("#id_cuenta_proveedor").val() == "") {
        alert('Debe seleccionar un Proveedor.');
        $("#id_cuenta_proveedor").select2('open');
        return false;
      }
      var idMoneda = $("#id_moneda").val();
      if (idMoneda == "") {
        alert('Debe seleccionar una Moneda.');
        $("#id_moneda").select2('open');
        return false;
      }
      var tipoCambio = $("#tipo_cambio_dia").val();
      if (idMoneda == 1 && (tipoCambio == "" || parseFloat(tipoCambio) <= 0)) {
        alert('Debe ingresar un Tipo de Cambio válido para la moneda USD.');
        $("#tipo_cambio_dia").focus();
        return false;
      }
      if ($("#id_forma_pago").val() == "") {
        alert('Debe seleccionar una Forma de Pago.');
        $("#id_forma_pago").select2('open');
        return false;
      }
      if ($("#id_tipo_iva").val() == "") {
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
  </script>
  <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
</body>
</html>