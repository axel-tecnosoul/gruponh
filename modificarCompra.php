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
  $id_compra = (int)$_GET['id_compra'];
} elseif (!empty($_GET['id_pedido'])) {
  $modo = 'pedido';
  $id_pedido = (int)$_GET['id_pedido'];
} elseif (!empty($_GET['id'])) {
  $modo = 'pedido';
  $id_pedido = (int)$_GET['id'];
}

if ($modo === null) {
  header("Location: listarPedidos.php");
  exit();
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$esCompra = ($modo === 'compra');

if ($esCompra) {
  $q = $pdo->prepare("SELECT id_estado_compra, id_pedido FROM compras WHERE id = ?");
  $q->execute([$id_compra]);
  $dataEstado = $q->fetch(PDO::FETCH_ASSOC);
  if (!$dataEstado || (int)$dataEstado['id_estado_compra'] !== 1) {
    Database::disconnect();
    header("Location: listarCompras.php");
    exit();
  }
  $id_pedido = (int)$dataEstado['id_pedido'];
} else {
  $q = $pdo->prepare("SELECT id_estado FROM pedidos WHERE id = ?");
  $q->execute([$id_pedido]);
  $est = $q->fetch(PDO::FETCH_ASSOC);
  if (!$est || !in_array((int)$est['id_estado'], [3, 4], true)) {
    Database::disconnect();
    header("Location: listarPedidos.php");
    exit();
  }
}

Database::disconnect();

if (!empty($_POST)) {

  $tipoCambio   = (isset($_POST['tipo_cambio_dia']) && $_POST['tipo_cambio_dia'] !== '')
    ? (float)$_POST['tipo_cambio_dia']
    : 0;
  $fechaEntrega = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->beginTransaction();

  try {

    // Validaciones
    $error_message = '';
    if (empty($_POST['id_cuenta_proveedor'])) $error_message = "Debe seleccionar un Proveedor.";
    if (empty($_POST['id_moneda']))           $error_message = "Debe seleccionar una Moneda.";
    if (empty($_POST['id_forma_pago']))       $error_message = "Debe seleccionar una Forma de Pago.";
    if (empty($_POST['fecha_emision']))       $error_message = "Debe ingresar la Fecha de Emisión.";
    if ($_POST['id_moneda'] == 1 && $tipoCambio <= 0) {
      $error_message = 'Para la moneda USD, es obligatorio ingresar un Tipo de Cambio válido.';
    }
    if (!empty($error_message)) throw new Exception($error_message);

    // Tasa IVA
    $id_tipo_iva = $_POST['id_tipo_iva'];
    $tasa_iva    = 0;
    $qTasa = $pdo->prepare("SELECT tasa FROM tipos_iva WHERE id = ?");
    $qTasa->execute([$id_tipo_iva]);
    $dtTasa = $qTasa->fetch(PDO::FETCH_ASSOC);
    if ($dtTasa) $tasa_iva = (float)$dtTasa['tasa'];

    // Procesar ítems
    $items_procesar = [];
    $totalNeto      = 0;

    foreach ($_POST as $key => $val) {
      if (strpos($key, 'cantidad_') !== 0) continue;
      $id_ref   = substr($key, 9);
      $cantidad = floatval($val);
      if ($cantidad <= 0) continue;

      $precio      = floatval($_POST['precio_'    . $id_ref] ?? 0);
      $precioKg    = floatval($_POST['preciokg_'  . $id_ref] ?? 0);
      $descItem    = floatval($_POST['descuento_' . $id_ref] ?? 0);
      $fechaEnt    = $_POST['fecha_entrega_' . $id_ref] ?? ($fechaEntrega ?? '');
      $id_material = $_POST['id_material_' . $id_ref] ?? null;
      $id_unidad   = $_POST['id_unidad_'   . $id_ref] ?? null;
      $peso_metro  = floatval($_POST['peso_'  . $id_ref] ?? 0);
      $largo       = floatval($_POST['largo_' . $id_ref] ?? 0);

      $precioGuardar = $precio;
      if ($precioKg > 0) {
        $peso_calc     = ($largo > 0) ? $peso_metro * ($largo / 1000) : $peso_metro;
        $subtotalBruto = $cantidad * ($precioKg * $peso_calc);
        $precioGuardar = 0;
      } else {
        $subtotalBruto = $cantidad * $precio;
      }

      $totalLinea     = $subtotalBruto * (1 - ($descItem / 100));
      $totalNeto     += $totalLinea;

      $items_procesar[] = [
        'id_material'   => $id_material,
        'cantidad'      => $cantidad,
        'id_unidad'     => $id_unidad,
        'precio'        => $precioGuardar,
        'precio_kg'     => $precioKg,
        'subtotal'      => $subtotalBruto,
        'total'         => $totalLinea,
        'descuento'     => $descItem,
        'fecha_entrega' => $fechaEnt
      ];
    }

    if (empty($items_procesar)) throw new Exception("Debe haber al menos un ítem con cantidad mayor a 0 y precio.");

    $desc_gral_pct   = floatval($_POST['descuento'] ?? 0);
    $baseIva         = $totalNeto * (1 - ($desc_gral_pct / 100));
    $monto_iva       = $baseIva * ($tasa_iva / 100);
    $totalFinal      = $baseIva + $monto_iva;

    // Parámetros comunes para UPDATE e INSERT
    $datosOC = [
      'id_cuenta_proveedor' => $_POST['id_cuenta_proveedor'],
      'fecha_emision'       => $_POST['fecha_emision'],
      'fecha_entrega'       => $fechaEntrega,
      'id_forma_pago'       => $_POST['id_forma_pago'],
      'id_tipo_iva'         => $id_tipo_iva,
      'id_moneda'           => $_POST['id_moneda'],
      'tipo_cambio_dia'     => $tipoCambio,
      'total'               => $totalNeto,
      'iva'                 => $monto_iva,
      'comentarios'         => $_POST['comentarios'],
      'descuento'           => $desc_gral_pct,
    ];

    if ($esCompra) {

      $pdo->prepare(
        "UPDATE compras SET
           id_cuenta_proveedor = ?, fecha_emision = ?, fecha_entrega = ?,
           id_forma_pago = ?, id_tipo_iva = ?, id_moneda = ?,
           tipo_cambio_dia = ?, total = ?, iva = ?,
           comentarios = ?, descuento = ?
         WHERE id = ?"
      )->execute([...array_values($datosOC), $id_compra]);

      $pdo->prepare("DELETE FROM compras_detalle WHERE id_compra = ?")->execute([$id_compra]);

      $targetId  = $id_compra;
      $accionLog = "Modificación de orden de compra";

    } else {

      $id_param_limite  = ($_POST['id_moneda'] == 1) ? 11 : 10;
      $qP = $pdo->prepare("SELECT valor FROM parametros WHERE id = ?");
      $qP->execute([$id_param_limite]);
      $monto_limite     = (float)($qP->fetchColumn() ?: 0);
      $id_estado_compra = ($totalFinal < $monto_limite) ? 3 : 1;

      $pdo->prepare(
        "INSERT INTO compras
           (id_pedido, id_cuenta_proveedor, fecha_emision, fecha_entrega,
            id_forma_pago, id_tipo_iva, id_estado_compra, nro_oc,
            total, iva, comentarios, id_moneda, tipo_cambio_dia,
            comentarios_revision, descuento, nro_revision)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Revisión Original',?,0)"
      )->execute([
        $id_pedido,
        $datosOC['id_cuenta_proveedor'],
        $datosOC['fecha_emision'],
        $datosOC['fecha_entrega'],
        $datosOC['id_forma_pago'],
        $datosOC['id_tipo_iva'],
        $id_estado_compra,
        '',
        $datosOC['total'],
        $datosOC['iva'],
        $datosOC['comentarios'],
        $datosOC['id_moneda'],
        $datosOC['tipo_cambio_dia'],
        $datosOC['descuento']
      ]);

      $targetId = (int)$pdo->lastInsertId();
      if (!$targetId) throw new Exception("Error al crear la Orden de Compra.");

      $qMaxOc = $pdo->query("SELECT COALESCE(MAX(CAST(nro_oc AS UNSIGNED)), 0) AS max_oc FROM compras");
      $nroOC  = (int)$qMaxOc->fetch(PDO::FETCH_ASSOC)['max_oc'] + 1;
      $pdo->prepare("UPDATE compras SET nro_oc = ? WHERE id = ?")->execute([$nroOC, $targetId]);

      $accionLog    = "Nueva orden de compra";
      $estado_texto = ($id_estado_compra == 3) ? "APROBADA (Automática)" : "Pendiente de Aprobación";
      crearNotificacion(
        $pdo, 4, $targetId,
        "ID OC: #$targetId - $estado_texto",
        "Compras - Nueva OC #$targetId ($estado_texto)",
        "Nueva compra generada.\nOC: #$targetId\nEstado: $estado_texto\n"
          . "Neto: $" . number_format($totalNeto, 2, ',', '.')
          . "\nIVA: $" . number_format($monto_iva, 2, ',', '.')
          . "\nTotal: $" . number_format($totalFinal, 2, ',', '.')
      );
    }

    // ── INSERT detalle unificado ──────────────────────────────────────────
    $idCompraGuardar = $esCompra ? $id_compra : $targetId;
    $stmtDetalle = $pdo->prepare(
      "INSERT INTO compras_detalle
         (id_compra, id_material, cantidad, id_unidad_medida, precio, precio_kg,
          subtotal, total, descuento, fecha_entrega, entregado)
       VALUES (?,?,?,?,?,?,?,?,?,?,0)"
    );
    foreach ($items_procesar as $item) {
      $stmtDetalle->execute([
        $idCompraGuardar,
        $item['id_material'],
        $item['cantidad'],
        $item['id_unidad'],
        $item['precio'],
        $item['precio_kg'],
        $item['subtotal'],
        $item['total'],
        $item['descuento'],
        $item['fecha_entrega']
      ]);
    }

    // ── Actualizar comprado ───────────────────────────────────────────────
    $stmtSum = $pdo->prepare(
      "SELECT SUM(cd.cantidad)
       FROM compras_detalle cd
       JOIN compras c ON c.id = cd.id_compra
       WHERE c.id_pedido = ? AND cd.id_material = ? AND c.id_estado_compra NOT IN (5)"
    );
    $stmtUpdPD   = $pdo->prepare("UPDATE pedidos_detalle SET comprado=? WHERE id_pedido=? AND id_material=?");
    $stmtSelPD   = $pdo->prepare("SELECT id FROM pedidos_detalle WHERE id_pedido=? AND id_material=?");
    $stmtSelComp = $pdo->prepare(
      "SELECT cd.id FROM computos_detalle cd
       JOIN computos c ON c.id = cd.id_computo
       JOIN pedidos p ON p.id_computo = c.id
       WHERE p.id = ? AND cd.cancelado = 0 AND cd.id_material = ?"
    );
    $stmtUpdComp = $pdo->prepare("UPDATE computos_detalle SET comprado=? WHERE id=?");

    foreach ($items_procesar as $item) {
      $idMat = $item['id_material'];

      $stmtSum->execute([$id_pedido, $idMat]);
      $totalComprado = $stmtSum->fetchColumn() ?: 0;

      $stmtUpdPD->execute([$totalComprado, $id_pedido, $idMat]);

      if (function_exists('actualizarEstadoPedidoDetalle')) {
        $stmtSelPD->execute([$id_pedido, $idMat]);
        $pdRow = $stmtSelPD->fetch(PDO::FETCH_ASSOC);
        if ($pdRow) actualizarEstadoPedidoDetalle($pdo, $pdRow['id']);
      }

      $stmtSelComp->execute([$id_pedido, $idMat]);
      $compDet = $stmtSelComp->fetch(PDO::FETCH_ASSOC);
      if ($compDet) $stmtUpdComp->execute([$totalComprado, $compDet['id']]);
    }

    if (!$esCompra) {
      $cntOC = $pdo->prepare("SELECT COUNT(*) FROM compras WHERE id_pedido = ?");
      $cntOC->execute([$id_pedido]);
      if ($cntOC->fetchColumn() > 0) {
        $pdo->prepare("UPDATE pedidos SET id_estado = 4 WHERE id = ?")->execute([$id_pedido]);
      }
    }

    $pdo->prepare(
      "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,?,'Compras',?)"
    )->execute([$_SESSION['user']['id'], $accionLog, "verCompra.php?id=$targetId"]);

    $pdo->commit();
    Database::disconnect();

    $_SESSION['flash_message'] = [
      'type'    => 'success',
      'message' => $esCompra
        ? 'Orden de Compra modificada exitosamente.'
        : 'Orden de Compra creada exitosamente (Nro/Revisión: ' . $nroOC . '/0).'
    ];
    header("Location: listarCompras.php");
    exit();

  } catch (Exception $e) {
    $pdo->rollback();
    $error = $e->getMessage();
    Database::disconnect();
  }
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$proveedores = $pdo->query("SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta IN (5) AND activo = 1 AND anulado = 0 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$monedas     = $pdo->query("SELECT id, moneda FROM monedas")->fetchAll(PDO::FETCH_ASSOC);
$formasPago  = $pdo->query("SELECT id, forma_pago FROM formas_pago")->fetchAll(PDO::FETCH_ASSOC);
$tiposIva    = $pdo->query("SELECT id, tasa FROM tipos_iva ORDER BY tasa")->fetchAll(PDO::FETCH_ASSOC);

$sqlPedido = "SELECT pe.id, pe.id_computo, pe.id_proyecto, DATE_FORMAT(pe.fecha, '%d/%m/%Y') AS fecha_formatted, pe.fecha, pe.lugar_entrega, pe.id_cuenta_recibe, pe.aprobado, 
  c.id_tarea, c.id_cuenta_solicitante, c.nro_revision AS computo_revision, c.nro AS computo_numero, 
  COALESCE(pc.id, pd.id) AS proyecto_id, COALESCE(pc.nombre, pd.nombre) AS proyecto_nombre, COALESCE(pc.nro, pd.nro) AS proyecto_nro, 
  COALESCE(sc.nro_sitio, sd.nro_sitio) AS nro_sitio, COALESCE(sc.nro_subsitio, sd.nro_subsitio) AS nro_subsitio, 
  COALESCE(ec.empresa, ed.empresa) AS empresa,
  cu.nombre AS cuenta_solicitante, cu2.nombre AS cuenta_recibe, pe.id_estado, ep.estado AS estado_pedido 
  FROM pedidos pe 
  LEFT JOIN computos c ON c.id = pe.id_computo 
  LEFT JOIN tareas t ON t.id = c.id_tarea 
  LEFT JOIN proyectos pc ON pc.id = t.id_proyecto 
  LEFT JOIN sitios sc ON sc.id = pc.id_sitio 
  LEFT JOIN empresas ec ON ec.id = sc.id_empresa
  LEFT JOIN proyectos pd ON pd.id = pe.id_proyecto 
  LEFT JOIN sitios sd ON sd.id = pd.id_sitio 
  LEFT JOIN empresas ed ON ed.id = sd.id_empresa
  LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante 
  LEFT JOIN cuentas cu2 ON cu2.id = pe.id_cuenta_recibe 
  LEFT JOIN estados_pedidos ep ON ep.id = pe.id_estado 
  WHERE pe.id = ?";
$qPed = $pdo->prepare($sqlPedido);
$qPed->execute([$id_pedido]);
$data = $qPed->fetch(PDO::FETCH_ASSOC);

$proyectoDisplay = '';
$codigoObra = '';
$tipoPedido = '';
if ($data) {
  $codigoObraPartes = array_filter(
    [$data['nro_sitio'] ?? null, $data['nro_subsitio'] ?? null, $data['proyecto_nro'] ?? null],
    function($v){ return $v !== null && $v !== ''; }
  );
  $codigoObra = !empty($codigoObraPartes) ? implode('_', $codigoObraPartes) : '';
  $tieneComputo = !empty($data['id_computo']);
  $tipoPedido   = $tieneComputo ? 'de Cómputo' : 'Directo';

  if (!empty($data['proyecto_id'])) {
    if (!empty($codigoObra) && !empty($data['proyecto_nombre'])) {
      $proyectoDisplay = $codigoObra . ': ' . $data['proyecto_nombre'];
    } elseif (!empty($codigoObra)) {
      $proyectoDisplay = $codigoObra;
    } elseif (!empty($data['proyecto_nombre'])) {
      $proyectoDisplay = $data['proyecto_nombre'];
    }
  }
  if (!empty($data['empresa'])) {
    $proyectoDisplay .= ' (' . substr($data['empresa'], 0, 4) . ')';
  }
}

$form = [
  'prov'      => '',
  'f_emision' => date('Y-m-d'),
  'f_entrega' => $data['fecha'] ?? '',
  'moneda'    => '',
  'tc'        => '',
  'pago'      => '',
  'iva'       => 3,
  'desc'      => '',
  'obs'       => '',
  'nro_oc'    => '',
  'estado'    => ''
];

if ($esCompra) {
  $qC = $pdo->prepare("SELECT c.*, ec.estado AS estado_nombre FROM compras c LEFT JOIN estados_compra ec ON ec.id = c.id_estado_compra WHERE c.id = ?");
  $qC->execute([$id_compra]);
  $dataC = $qC->fetch(PDO::FETCH_ASSOC);

  $form['prov']         = $dataC['id_cuenta_proveedor'];
  $form['f_emision']    = $dataC['fecha_emision'];
  $form['f_entrega']    = $dataC['fecha_entrega'];
  $form['moneda']       = $dataC['id_moneda'];
  $form['tc']           = $dataC['tipo_cambio_dia'];
  $form['pago']         = $dataC['id_forma_pago'];
  $form['iva']          = $dataC['id_tipo_iva'];
  $form['desc']         = $dataC['descuento'];
  $form['obs']          = $dataC['comentarios'];
  $form['nro_oc']       = $dataC['nro_oc'];
  $form['estado']       = $dataC['estado_nombre'] ?? '';
  $form['nro_revision'] = $dataC['nro_revision'];
}

// ── Títulos ──────────────────────────────────────────────────────────────────
// Header del card  → gestión / modificación
$tituloPrincipal = $esCompra
  ? "Modificar Orden de Compra N° {$form['nro_oc']} Rev {$form['nro_revision']} del Pedido $tipoPedido N° $id_pedido"
  : "Gestión del Pedido $tipoPedido N° $id_pedido";

// H6 de la columna izquierda → siempre muestra info del pedido
$tituloInfoPedido = "Información del Pedido";

$action    = $esCompra ? "?id_compra=$id_compra" : "?id_pedido=$id_pedido";
$btnTxt    = $esCompra ? "Guardar Cambios" : "Crear Orden de Compra";
$urlVolver = $esCompra ? "listarCompras.php" : "listarPedidos.php";

$tienePermisoCompra = function_exists('tienePermiso') ? tienePermiso(298) : true;
$puedeEditar = $esCompra || ($data['aprobado'] == 1 && $tienePermisoCompra);

// Obtener montos mínimos de aprobación de OC para el modal de confirmación
$pdo_params = Database::connect();
$pdo_params->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$q_params = $pdo_params->prepare("SELECT id, valor FROM parametros WHERE id IN (10, 11)");
$q_params->execute();
$montos_minimos = ['pesos' => 0, 'usd' => 0];
while ($fp = $q_params->fetch(PDO::FETCH_ASSOC)) {
  if ($fp['id'] == 10) $montos_minimos['pesos'] = (float)$fp['valor'];
  if ($fp['id'] == 11) $montos_minimos['usd'] = (float)$fp['valor'];
}
Database::disconnect();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include('head_forms.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  <style>
    .form-control:disabled, .form-control[readonly] { background-color: #e9ecef; opacity: 1; }
    .form-group { margin-bottom: 1rem; }
    .card-body { padding: 1.5rem; }

    #dataTables-example667 { width: 100% !important; font-size: 0.75rem; border-collapse: collapse !important; }
    #dataTables-example667 th, #dataTables-example667 td { padding: 5px 4px !important; vertical-align: middle; font-size: 0.75rem; overflow: hidden; text-overflow: ellipsis; box-sizing: border-box !important; }
    #dataTables-example667 thead th { white-space: nowrap !important; padding: 6px 4px !important; font-size: 0.7rem; font-weight: 600; line-height: 1.2; background-color: #f8f9fa; }
    #dataTables-example667 tbody td { white-space: nowrap; }
    #dataTables-example667 tbody td:nth-child(1) { white-space: normal; }
    #dataTables-example667 input.form-control { font-size: 0.75rem; padding: 0.25rem 0.35rem; height: 28px; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }

    #custom-controls-container { display: inline-block; vertical-align: middle; flex: 1; max-width: calc(100% - 400px); margin: 0 20px; padding: 5px 0; }
    #custom-controls { display: flex !important; align-items: center; justify-content: flex-start; gap: 25px; margin: 0 !important; width: 100%; flex-wrap: nowrap; }
    #custom-controls .col-md-3 { flex: 0 0 auto; width: auto; padding: 0; margin: 0; min-width: 150px; }
    #custom-controls .form-label { font-size: 11px; font-weight: 500; margin-bottom: 3px; color: #666; display: block; white-space: nowrap; }
    #custom-controls .form-control { font-size: 11px !important; padding: 5px 8px !important; height: 30px !important; width: 130px; border: 1px solid #ccc; border-radius: 3px; }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    #custom-controls-container { display: inline-flex; align-items: center; }
    .table-secondary { background-color: #f8f9fa !important; opacity: 0.8; }
    .table-secondary td { text-decoration: line-through; color: #6c757d; }
    .table-secondary .badge-danger { text-decoration: none; font-size: 0.7rem; padding: 0.5rem 1rem; }
    .cancelado-badge { display: inline-block; min-width: 120px; }
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

    .btn-eliminar-item { cursor: pointer; display: inline-block; }
    .btn-eliminar-item img { pointer-events: none; }
    #dataTables-example667 .cantidad-input,
    #dataTables-example667 .precio-input,
    #dataTables-example667 .preciokg-input,
    #dataTables-example667 .descuento-input { text-align: right; }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body">
        <?php
        $ubicacion = $esCompra ? "Modificar Orden de Compra" : "Gestión de Pedido y Nueva Orden de Compra";
        include_once("head_page.php"); ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <!-- Título principal: siempre visible -->
                  <h5><?= htmlspecialchars($tituloPrincipal) ?></h5>

                  <?php if (isset($error)) { ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                  <?php } ?>
                  <?php if (!empty($_SESSION['flash_message'])) { ?>
                    <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?>">
                      <?= $_SESSION['flash_message']['message'] ?>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                  <?php } ?>
                </div>

                <form class="form theme-form" role="form" method="post"
                      action="<?= $action ?>" id="form-unificado"
                      onsubmit="return validarFormularioCompra();">
                  <div class="card-body">
                    <div class="row">

                      <!-- ── Columna izquierda: datos del pedido ───────────── -->
                      <div class="col-md-6">
                        <!-- Título de sección: ahora siempre "Información del Pedido..." -->
                        <h6 class="mb-3"><?= htmlspecialchars($tituloInfoPedido) ?></h6>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Fecha Pedido</label>
                          <div class="col-sm-8"><?= $data['fecha_formatted'] ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Proyecto</label>
                          <div class="col-sm-8"><?= $proyectoDisplay ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Lugar de Entrega</label>
                          <div class="col-sm-8"><?= $data['lugar_entrega'] ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Recibe</label>
                          <div class="col-sm-8"><?= $data['cuenta_recibe'] ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Estado</label>
                          <div class="col-sm-8"><?= $data['estado_pedido'] ?></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label font-weight-bold">Solicitante</label>
                          <div class="col-sm-8"><?= $data['cuenta_solicitante'] ?></div>
                        </div>
                        <?php if ($esCompra && !empty($form['nro_oc'])) { ?>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label font-weight-bold">Nro OC / Rev</label>
                            <div class="col-sm-8"><?= $form['nro_oc'] ?> / <?= $form['nro_revision'] ?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label font-weight-bold">Estado OC</label>
                            <div class="col-sm-8"><?= $form['estado'] ?></div>
                          </div>
                        <?php } ?>
                      </div>

                      <!-- ── Columna derecha: datos editables de la OC ─────── -->
                      <?php if ($puedeEditar) { ?>
                      <div class="col-md-6">
                        <h6 class="mb-3">Datos de la Orden de Compra</h6>

                        <!-- Proveedor -->
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Proveedor(*)</label>
                          <div class="col-sm-8">
                            <select name="id_cuenta_proveedor" id="id_cuenta_proveedor"
                                    class="js-example-basic-single w-100" required>
                              <option value="">Seleccione...</option>
                              <?php foreach ($proveedores as $p) {
                                $sel = ($p['id'] == $form['prov']) ? 'selected' : '';
                                echo "<option value='{$p['id']}' $sel>{$p['nombre']}</option>";
                              } ?>
                            </select>
                          </div>
                        </div>

                        <!-- Fecha Emisión -->
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha Emisión(*)</label>
                          <div class="col-sm-8">
                            <input name="fecha_emision" type="date" onfocus="this.showPicker()"
                                   value="<?= $form['f_emision'] ?>" class="form-control" required>
                          </div>
                        </div>

                        <!-- Moneda -->
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Moneda(*)</label>
                          <div class="col-sm-8">
                            <select name="id_moneda" id="id_moneda"
                                    class="js-example-basic-single w-100" required>
                              <option value="">Seleccione...</option>
                              <?php foreach ($monedas as $m) {
                                $sel = ($m['id'] == $form['moneda']) ? 'selected' : '';
                                $esDolar = (stripos($m['moneda'], 'dolar') !== false ||
                                            stripos($m['moneda'], 'dólar') !== false ||
                                            stripos($m['moneda'], 'usd')   !== false ||
                                            stripos($m['moneda'], 'u$d')   !== false ||
                                            stripos($m['moneda'], 'u$s')   !== false) ? 'true' : 'false';
                                echo "<option value='{$m['id']}' data-esusd='$esDolar' $sel>{$m['moneda']}</option>";
                              } ?>
                            </select>
                          </div>
                        </div>

                        <!-- Tipo de Cambio -->
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">
                            Tipo de Cambio
                            <span id="tc_required_star" style="color:red; display:none;">(*)</span>
                          </label>
                          <div class="col-sm-8">
                            <input name="tipo_cambio_dia" id="tipo_cambio_dia" type="number"
                                  step="0.01" class="form-control" value="<?= $form['tc'] ?>">
                            <small id="estadoDolar" class="text-secondary"></small>
                            <div id="infoCotizacion" style="font-size:11px; margin-top:3px;"></div>
                          </div>
                        </div>

                        <!-- IVA -->
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">IVA(*)</label>
                          <div class="col-sm-8">
                            <select name="id_tipo_iva" id="id_tipo_iva" class="form-control" required>
                              <?php foreach ($tiposIva as $ti) {
                                $sel = ($ti['id'] == $form['iva']) ? 'selected' : '';
                                echo "<option value='{$ti['id']}' data-tasa='{$ti['tasa']}' $sel>"
                                   . (float)$ti['tasa'] . "%</option>";
                              } ?>
                            </select>
                          </div>
                        </div>

                        <!-- Forma de Pago -->
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Forma de Pago(*)</label>
                          <div class="col-sm-8">
                            <select name="id_forma_pago" id="id_forma_pago"
                                    class="js-example-basic-single w-100" required>
                              <option value="">Seleccione...</option>
                              <?php foreach ($formasPago as $fp) {
                                $sel = ($fp['id'] == $form['pago']) ? 'selected' : '';
                                echo "<option value='{$fp['id']}' $sel>{$fp['forma_pago']}</option>";
                              } ?>
                            </select>
                          </div>
                        </div>

                        <!-- Comentarios -->
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Comentarios</label>
                          <div class="col-sm-8">
                            <textarea name="comentarios" class="form-control"
                                      rows="2"><?= htmlspecialchars($form['obs']) ?></textarea>
                          </div>
                        </div>

                      </div><!-- /col-md-6 derecha -->
                      <?php } ?>

                    </div><!-- /row principal -->

                    <hr class="mt-4 mb-4">

                    <div class="row">
                      <div class="col-sm-12">
                        <h6 class="mb-3">Detalle de Conceptos</h6>

                        <?php if ($puedeEditar) { ?>
                        <!--
                          custom-controls: Fecha Entrega General y Descuento General.
                          Estos son los ÚNICOS controles de ese tipo; están ocultos aquí
                          y el JS los mueve dentro del wrapper de DataTables.
                        -->
                        <div id="custom-controls" class="row mb-3" style="display:none; text-align:center;">
                          <div class="col-md-6" style="text-align:center;">
                            <label>Fecha Entrega General:</label>
                            <input type="date" onfocus="this.showPicker()"
                                   value="<?= $form['f_entrega'] ?>"
                                   class="form-control d-inline-block"
                                   style="font-size:12px;"
                                   id="fecha_entrega_general_ctrl">
                          </div>
                          <div class="col-md-6" style="text-align:center;">
                            <label>Descuento General (%):</label>
                            <input type="number" step="0.01"
                                   class="form-control d-inline-block"
                                   style="font-size:12px;"
                                   id="descuento_general_ctrl"
                                   value="<?= $form['desc'] ?>">
                          </div>
                        </div>

                        <!-- Campos hidden que se sincronizan con los controles anteriores -->
                        <input type="hidden" name="fecha_entrega" value="<?= $form['f_entrega'] ?>">
                        <input type="hidden" name="descuento"     value="<?= $form['desc'] ?>">
                        <?php } ?>

                        <div class="table-responsive">
                          <table class="display" id="dataTables-example667" style="width:100%">
                            <thead>
                              <tr>
                                <th>Concepto</th>
                                <th>Fec. Necesidad</th>
                                <!-- <th>Fec. Últ. Compra</th> -->
                                <!-- <th>Último Precio</th> -->
                                <th>Requerido</th>
                                <!-- <th>Stock</th> -->
                                <!-- <th>Reserv.</th> -->
                                <th>Comprado</th>
                                <?php if ($puedeEditar) { ?>
                                  <th>Cant. Solic.</th>
                                  <th>Cant. Pedir</th>
                                  <th>P. Unit.</th>
                                  <th>P. x Kg</th>
                                  <th>Desc %</th>
                                  <th>Subtotal</th>
                                  <th>F. Entrega</th>
                                  <?php if ($esCompra) { ?>
                                    <th style="text-align:center;">
                                      <img src="img/icon_baja.png" width="18" height="18"
                                           border="0" alt="Eliminar" title="Eliminar">
                                    </th>
                                  <?php } ?>
                                <?php } ?>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                              if ($esCompra) {
                                $sqlItems = "SELECT pd.id as pd_id, pd.cantidad as pd_cantidad,
                                  pd.comprado as pd_comprado, pd.reservado, pd.cancelado,
                                  date_format(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad,
                                  m.id as id_material, m.concepto, m.peso_metro, m.largo,
                                  u.unidad_medida, pd.id_unidad_medida,
                                  cd.id as cd_id, cd.cantidad as cd_cantidad,
                                  cd.precio as cd_precio, cd.precio_kg as cd_precio_kg,
                                  cd.descuento as cd_descuento, cd.fecha_entrega as cd_fecha_entrega
                                  FROM pedidos_detalle pd
                                  JOIN materiales m ON m.id = pd.id_material
                                  JOIN unidades_medida u ON u.id = pd.id_unidad_medida
                                  INNER JOIN compras_detalle cd
                                    ON cd.id_compra = ? AND cd.id_material = pd.id_material
                                  WHERE pd.id_pedido = ?";
                                $qItems = $pdo->prepare($sqlItems);
                                $qItems->execute([$id_compra, $id_pedido]);
                              } else {
                                $sqlItems = "SELECT pd.id as pd_id, pd.cantidad as pd_cantidad,
                                  pd.comprado as pd_comprado, pd.reservado, pd.cancelado,
                                  date_format(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad,
                                  m.id as id_material, m.concepto, m.peso_metro, m.largo,
                                  u.unidad_medida, pd.id_unidad_medida,
                                  NULL as cd_id, NULL as cd_cantidad, NULL as cd_precio,
                                  NULL as cd_precio_kg, NULL as cd_descuento,
                                  NULL as cd_fecha_entrega
                                  FROM pedidos_detalle pd
                                  JOIN materiales m ON m.id = pd.id_material
                                  JOIN unidades_medida u ON u.id = pd.id_unidad_medida
                                  WHERE pd.id_pedido = ?";
                                $qItems = $pdo->prepare($sqlItems);
                                $qItems->execute([$id_pedido]);
                              }

                              while ($row = $qItems->fetch(PDO::FETCH_ASSOC)) {
                                $id_material  = (int)$row['id_material'];
                                $peso_metro   = (float)$row['peso_metro'];
                                $largo        = (float)$row['largo'];
                                $cancelado    = (int)$row['cancelado'];
                                $canceladoClass = ($cancelado == 1) ? 'table-secondary' : '';
                                $pendiente    = (float)$row['pd_cantidad'] - (float)$row['pd_comprado'];

                                if ($esCompra && $row['cd_id']) {
                                  $saldo_max       = max($pendiente + (float)$row['cd_cantidad'], (float)$row['cd_cantidad']);
                                  $cant_actual     = (float)$row['cd_cantidad'];
                                  $precio_actual   = (float)$row['cd_precio'];
                                  $preciokg_actual = (float)$row['cd_precio_kg'];
                                  $descuento_actual= (float)$row['cd_descuento'];
                                  $fecha_ent_actual= $row['cd_fecha_entrega'];
                                  $id_ref          = $row['cd_id'];
                                } else {
                                  $saldo_max       = $pendiente;
                                  $cant_actual     = ($pendiente > 0) ? $pendiente : 0;
                                  $precio_actual   = 0;
                                  $preciokg_actual = 0;
                                  $descuento_actual= 0;
                                  $fecha_ent_actual= $form['f_entrega'];
                                  $id_ref          = $row['pd_id'];
                                }

                                $q2 = $pdo->prepare(
                                  "SELECT d.precio, date_format(c.fecha_emision,'%d/%m/%y') fecha_emision
                                   FROM compras_detalle d
                                   INNER JOIN compras c ON c.id = d.id_compra
                                   WHERE d.id_material = ? ORDER BY c.id DESC LIMIT 1"
                                );
                                $q2->execute([$id_material]);
                                $data2 = $q2->fetch(PDO::FETCH_ASSOC);
                                $fecha_ult_compra = !empty($data2['fecha_emision']) ? $data2['fecha_emision'] : '';
                                $precio_ult       = !empty($data2['precio']) ? "$" . number_format($data2['precio'], 2, ',', '.') : '';

                                $qStock = $pdo->prepare("SELECT SUM(saldo) FROM ingresos_detalle WHERE id_material = ?");
                                $qStock->execute([$id_material]);
                                $disponible = $qStock->fetchColumn() ?: 0;

                                $tieneItems = ($saldo_max > 0 && $cancelado != 1);
                              ?>
                              <tr class="<?= $canceladoClass ?>"
                                  data-id="<?= $id_ref ?>"
                                  data-peso="<?= $peso_metro ?>"
                                  data-largo="<?= $largo ?>">
                                <td>
                                  <?= $row['concepto'] ?>
                                  <?php if ($puedeEditar && $tieneItems) { ?>
                                    <input type="hidden" name="id_material_<?= $id_ref ?>" value="<?= $id_material ?>">
                                    <input type="hidden" name="id_unidad_<?= $id_ref ?>"   value="<?= $row['id_unidad_medida'] ?>">
                                    <input type="hidden" name="peso_<?= $id_ref ?>"         value="<?= $peso_metro ?>">
                                    <input type="hidden" name="largo_<?= $id_ref ?>"        value="<?= $largo ?>">
                                  <?php } ?>
                                </td>
                                <td class="text-center"><?= $row['fecha_necesidad'] ?></td>
                                <!-- <td class="text-center"><?= $fecha_ult_compra ?></td> -->
                                <!-- <td class="text-right"><?= $precio_ult ?></td> -->
                                <td class="text-right"><?= (float)$row['pd_cantidad'] . ' ' . $row['unidad_medida'] ?></td>
                                <!-- <td class="text-right"><?= $disponible ?></td> -->
                                <!-- <td class="text-right"><?= $row['reservado'] ?></td> -->
                                <td class="text-right"><?= (float)$row['pd_comprado'] ?></td>

                                <?php if ($puedeEditar) { ?>
                                  <td class="text-right">
                                    <?php
                                      if ($cancelado == 1) {
                                        echo '0';
                                      } elseif ($esCompra && $row['cd_id']) {
                                        echo (float)$row['cd_cantidad'];
                                      } else {
                                        echo max(0, (float)$pendiente);
                                      }
                                    ?>
                                  </td>

                                  <td class="cantidad-col"
                                      data-cancelado="<?= $cancelado ?>"
                                      data-cantidad="<?= $saldo_max ?>"
                                      data-id="<?= $id_ref ?>">
                                    <?php if ($tieneItems) { ?>
                                      <input name="cantidad_<?= $id_ref ?>" type="number"
                                             step="0.01" min="0" max="<?= $saldo_max ?>"
                                             class="form-control cantidad-input"
                                             value="<?= (float)$cant_actual ?>">
                                    <?php } elseif ($cancelado == 1) { ?>
                                      <span class="badge badge-danger cancelado-badge">Cancelado</span>
                                    <?php } ?>
                                  </td>

                                  <td class="precio-col" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($tieneItems) { ?>
                                      <input name="precio_<?= $id_ref ?>" type="number"
                                             step="0.0001" class="form-control precio-input"
                                             value="<?= (float)$precio_actual ?>">
                                    <?php } ?>
                                  </td>

                                  <td class="preciokg-col" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($tieneItems) { ?>
                                      <input name="preciokg_<?= $id_ref ?>" type="number"
                                             step="0.0001" class="form-control preciokg-input"
                                             value="<?= (float)$preciokg_actual ?>">
                                    <?php } ?>
                                  </td>

                                  <td class="descuento-col" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($tieneItems) { ?>
                                      <input name="descuento_<?= $id_ref ?>" type="number"
                                             step="0.1" min="0" max="100"
                                             class="form-control descuento-input"
                                             value="<?= (float)$descuento_actual ?>">
                                    <?php } ?>
                                  </td>

                                  <td class="subtotal-col text-right" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($tieneItems) { ?>
                                      <span class="subtotal-cell">0.00</span>
                                    <?php } ?>
                                  </td>

                                  <td class="fecha-col" data-cancelado="<?= $cancelado ?>">
                                    <?php if ($tieneItems) { ?>
                                      <input name="fecha_entrega_<?= $id_ref ?>" type="date"
                                             onfocus="this.showPicker()"
                                             class="form-control fecha-entrega-input"
                                             value="<?= $fecha_ent_actual ?>">
                                    <?php } ?>
                                  </td>

                                  <?php if ($esCompra) { ?>
                                    <td class="text-center">
                                      <?php if ($tieneItems && $row['cd_id']) { ?>
                                        <a href="javascript:void(0);" class="btn-eliminar-item"
                                           data-id="<?= $row['cd_id'] ?>"
                                           data-id-compra="<?= $id_compra ?>"
                                           data-concepto="<?= htmlspecialchars($row['concepto']) ?>">
                                          <img src="img/icon_baja.png" width="24" height="25"
                                               border="0" alt="Eliminar" title="Eliminar">
                                        </a>
                                      <?php } ?>
                                    </td>
                                  <?php } ?>
                                <?php } ?>
                              </tr>
                              <?php } ?>
                            </tbody>
                          </table>
                        </div><!-- /table-responsive -->

                        <?php if ($puedeEditar) { ?>
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
                          <i><strong>NOTA:</strong> Si ingresa Precio x KG &gt; 0, el precio se calculará como:
                            (Precio x KG) * (Peso por Metro * Largo).
                            Si el largo no está definido para el material, se usará solo el Peso por Metro.</i><br>
                          <i>Para guardar, debe ingresar al menos un concepto con cantidad mayor a 0
                            y al menos uno de los dos precios (Unitario o x Kg).</i>
                        </div>
                        <?php } ?>

                      </div>
                    </div>
                  </div><!-- /card-body -->

                  <div class="card-footer">
                    <div class="col-sm-12 text-center">
                      <?php if ($puedeEditar) { ?>
                        <?php if (!$esCompra) { ?>
                          <button class="btn btn-success" type="button" id="btn-crear-oc"><?= $btnTxt ?></button>
                        <?php } else { ?>
                          <button class="btn btn-success" type="submit"><?= $btnTxt ?></button>
                        <?php } ?>
                      <?php } ?>
                      <a href="<?= $urlVolver ?>" class="btn btn-light">Volver</a>
                    </div>
                  </div>
                </form>

                <?php if (!$esCompra && $puedeEditar) { ?>
                <div class="modal fade" id="modalConfirmarOC" tabindex="-1" role="dialog" aria-labelledby="modalConfirmarOCLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="modalConfirmarOCLabel">Confirmar Creación de Orden de Compra</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body" id="modalConfirmarOCBody"></div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" id="btn-confirmar-crear-oc">Confirmar</button>
                      </div>
                    </div>
                  </div>
                </div>
                <?php } ?>

              </div>
            </div>
          </div>
        </div>
      </div>
      <?php include("footer.php"); ?>
    </div>
  </div>

  <?php if ($esCompra) { ?>
  <div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog"
       aria-labelledby="modalEliminarLabel" aria-hidden="true">
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
  var esCompra  = <?= $esCompra    ? 'true' : 'false' ?>;
  var puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

  $(document).ready(function() {

    // ── Cálculo por fila ────────────────────────────────────────────────────
    function calcularFila(row) {
      var cantidad   = parseFloat(row.find('.cantidad-input').val())   || 0;
      var precioUnit = parseFloat(row.find('.precio-input').val())     || 0;
      var precioKg   = parseFloat(row.find('.preciokg-input').val())   || 0;
      var descuento  = parseFloat(row.find('.descuento-input').val())  || 0;
      var pesoMetro  = parseFloat(row.data('peso'))  || 0;
      var largoMm    = parseFloat(row.data('largo')) || 0;

      var precioParaCalculo = precioUnit;
      if (precioKg > 0) {
        var largoMetros       = (largoMm > 0) ? largoMm / 1000 : 1;
        precioParaCalculo     = precioKg * pesoMetro * largoMetros;
      }

      var subtotalBruto = cantidad * precioParaCalculo;
      var subtotalNeto  = subtotalBruto * (1 - (descuento / 100));

      row.find('.subtotal-cell').text(
        subtotalNeto.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 4 })
      );
      row.data('subtotal', subtotalNeto);
      calcularTotalesGenerales();
    }

    // ── Totales generales ───────────────────────────────────────────────────
    function calcularTotalesGenerales() {
      var totalNeto = 0;
      $('#dataTables-example667 tbody tr').each(function() {
        totalNeto += $(this).data('subtotal') || 0;
      });

      var opcionIva  = $('#id_tipo_iva').find(':selected');
      var tasaIva    = parseFloat(opcionIva.data('tasa')) || 0;
      var montoIva   = totalNeto * (tasaIva / 100);
      var totalFinal = totalNeto + montoIva;

      $('#lbl_neto').text('$ '  + totalNeto.toLocaleString('es-AR',  { minimumFractionDigits: 2 }));
      $('#lbl_tasa_iva').text(tasaIva);
      $('#lbl_iva').text('$ '   + montoIva.toLocaleString('es-AR',   { minimumFractionDigits: 2 }));
      $('#lbl_total').text('$ ' + totalFinal.toLocaleString('es-AR', { minimumFractionDigits: 2 }));
    }

    // ── Ocultar celdas de filas canceladas ──────────────────────────────────
    function handleCanceledRowCells($row) {
      var cantidadCol = $row.find('.cantidad-col');
      if (cantidadCol.data('cancelado') == 1) {
        $row.find('.precio-col, .preciokg-col, .descuento-col, .subtotal-col, .fecha-col').hide();
        cantidadCol.attr('colspan', '6').addClass('text-center');
      }
    }

    // ── Definición de columnas según modo ───────────────────────────────────
    var colDefs = [];

    if (puedeEditar) {
      colDefs = [
        { width: "180px", targets: 0,  orderable: true },
        { width: "80px",  targets: 1,  orderable: true,  className: "text-center" },
        // { width: "80px",  targets: 2,  orderable: true,  className: "text-center" }, // Fec. Últ. Compra
        // { width: "85px",  targets: 3,  orderable: true,  className: "text-right"  }, // Último Precio
        { width: "85px",  targets: 2,  orderable: true,  className: "text-right"  },
        // { width: "55px",  targets: 5,  orderable: true,  className: "text-right"  }, // Stock
        // { width: "55px",  targets: 6,  orderable: true,  className: "text-right"  }, // Reserv.
        { width: "70px",  targets: 3,  orderable: true,  className: "text-right"  },
        { width: "70px",  targets: 4,  orderable: true,  className: "text-right"  },
        { width: "85px",  targets: 5,  orderable: false },
        { width: "80px",  targets: 6, orderable: false },
        { width: "80px",  targets: 7, orderable: false },
        { width: "65px",  targets: 8, orderable: false, className: "text-center" },
        { width: "85px",  targets: 9, orderable: false, className: "text-right"  },
        { width: "95px",  targets: 10, orderable: false }
      ];
      if (esCompra) {
        colDefs.push({ width: "40px", targets: 11, orderable: false, className: "text-center" });
      }
    } else {
      colDefs = [
        { width: "250px", targets: 0, orderable: true },
        { width: "90px",  targets: 1, orderable: true, className: "text-center" },
        // { width: "90px",  targets: 2, orderable: true, className: "text-center" }, // Fec. Últ. Compra
        // { width: "90px",  targets: 3, orderable: true, className: "text-right"  }, // Último Precio
        { width: "90px",  targets: 2, orderable: true, className: "text-right"  },
        // { width: "70px",  targets: 5, orderable: true, className: "text-right"  }, // Stock
        // { width: "70px",  targets: 6, orderable: true, className: "text-right"  }, // Reserv.
        { width: "80px",  targets: 3, orderable: true, className: "text-right"  }
      ];
    }

    // ── Inicializar DataTable ───────────────────────────────────────────────
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
      columnDefs: colDefs,
      language: {
        "decimal":        "",
        "emptyTable":     "No hay información",
        "info":           "Mostrando _START_ a _END_ de _TOTAL_ Registros",
        "infoEmpty":      "Mostrando 0 to 0 of 0 Registros",
        "infoFiltered":   "(Filtrado de _MAX_ total registros)",
        "infoPostFix":    "",
        "thousands":      ",",
        "lengthMenu":     "Mostrar _MENU_ Registros",
        "loadingRecords": "Cargando...",
        "processing":     "Procesando...",
        "search":         "Buscar:",
        "zeroRecords":    "No hay resultados",
        "paginate": {
          "first":    "Primero",
          "last":     "Ultimo",
          "next":     "Siguiente",
          "previous": "Anterior"
        }
      },
      drawCallback: function() {
        this.api().rows().every(function() {
          handleCanceledRowCells($(this.node()));
        });
      },
      initComplete: function() {
        // Mover custom-controls dentro del wrapper de DataTables
        var customControls = $('#custom-controls').detach();
        $('#custom-controls-container').append(customControls);
        customControls.show();

        var wrapper        = $('#dataTables-example667_wrapper');
        var lengthDiv      = wrapper.find('.dataTables_length');
        var filterDiv      = wrapper.find('.dataTables_filter');
        var customContainer= $('#custom-controls-container');

        var topRow = $('<div class="datatables-top-row" style="display:flex;align-items:center;'
                     + 'justify-content:space-between;margin-bottom:15px;flex-wrap:nowrap;"></div>');

        lengthDiv.css({ 'margin': '0', 'flex': '0 0 auto' });
        customContainer.css({ 'margin': '0 15px', 'flex': '1 1 auto', 'min-width': '300px' });
        filterDiv.css({ 'margin': '0', 'flex': '0 0 auto' });

        lengthDiv.parent().prepend(topRow);
        topRow.append(lengthDiv).append(customContainer).append(filterDiv);

        // Ocultar duplicados que DataTables pueda haber generado
        lengthDiv.parent().find('.dataTables_length').not(lengthDiv).hide();
        filterDiv.parent().find('.dataTables_filter').not(filterDiv).hide();

        // Calcular subtotales iniciales
        $('#dataTables-example667 tbody tr').each(function() {
          calcularFila($(this));
        });
      }
    });

    $('#id_moneda').on('change', function () {
      var opcion  = $(this).find('option:selected');
      var esDolar = (opcion.data('esusd') === true || opcion.data('esusd') === 'true');
      var input   = $('#tipo_cambio_dia');
      var badge   = $('#estadoDolar');
      var info    = $('#infoCotizacion');

      if (esDolar) {
        input.prop('required', true);
        $('#tc_required_star').show();
        badge.text('Cargando...')
            .removeClass('text-danger text-success')
            .addClass('text-secondary');
        input.prop('readonly', true);

        fetch('https://dolarapi.com/v1/dolares/oficial', {
          headers: { 'Accept': 'application/json' }
        })
        .then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        })
        .then(function (d) {
          if (!d.venta) throw new Error('Sin venta');
          input.val(parseFloat(d.venta).toFixed(2));
          badge.html('Dólar Oficial')
              .removeClass('text-secondary text-danger')
              .addClass('text-success');
          var fecha = d.fechaActualizacion
            ? ' — Act: ' + new Date(d.fechaActualizacion).toLocaleString('es-AR')
            : '';
          info.html(
            'Compra: <strong>$' + parseFloat(d.compra).toLocaleString('es-AR', {minimumFractionDigits:2}) + '</strong>'
            + ' | Venta: <strong>$' + parseFloat(d.venta).toLocaleString('es-AR', {minimumFractionDigits:2}) + '</strong>'
            + fecha
          );
          input.prop('readonly', false);
        })
        .catch(function () {
          badge.text('Error al obtener')
              .removeClass('text-secondary text-success')
              .addClass('text-danger');
          info.html('<span class="text-danger">No se pudo obtener la cotización. Ingrésela manualmente.</span>');
          input.val('').prop('readonly', false);
        });

      } else if (opcion.val() === '') {
        input.val('').prop('readonly', false).prop('required', false);
        $('#tc_required_star').hide();
        badge.text('').removeClass('text-danger text-success text-secondary');
        info.html('');

      } else {
        input.val(1).prop('readonly', false).prop('required', false);
        $('#tc_required_star').hide();
        badge.text('Ingreso manual')
            .removeClass('text-danger text-success')
            .addClass('text-secondary');
        info.html('<span class="text-muted"></span>');
      }
    }).trigger('change');

    // ── Sincronizar controles generales con campos hidden y con la tabla ────
    $(document).on('input', '#descuento_general_ctrl', function() {
      var v = $(this).val();
      $('input[name="descuento"]').val(v);
      $('.descuento-input').val(v).trigger('input');
    });

    $(document).on('change', '#fecha_entrega_general_ctrl', function() {
      var v = $(this).val();
      $('input[name="fecha_entrega"]').val(v);
      $('.fecha-entrega-input').val(v);
    });

    // ── Cambios en cantidad ─────────────────────────────────────────────────
    $(document).on('change', '.cantidad-input', function() {
      var val = parseFloat($(this).val()) || 0;
      var max = parseFloat($(this).attr('max')) || 0;
      if (val < 0)            $(this).val(0);
      if (val > max && max > 0) $(this).val(max);
      calcularFila($(this).closest('tr'));
    });

    // ── Cambios en precio / precioKg / descuento / cantidad ────────────────
    $(document).on('input', '.cantidad-input, .precio-input, .preciokg-input, .descuento-input',
    function() {
      var row = $(this).closest('tr');

      if ($(this).hasClass('precio-input')) {
        var v = parseFloat($(this).val()) || 0;
        if (v > 0) {
          row.find('.preciokg-input').val(0).prop('disabled', true);
        } else {
          row.find('.preciokg-input').prop('disabled', false);
        }
      }

      if ($(this).hasClass('preciokg-input')) {
        var v = parseFloat($(this).val()) || 0;
        if (v > 0) {
          row.find('.precio-input').val(0).prop('disabled', true);
        } else {
          row.find('.precio-input').prop('disabled', false);
        }
      }

      calcularFila(row);
    });

    // ── Cambio de tasa IVA → recalcular totales ��────────────────────────────
    $('#id_tipo_iva').on('change', function() {
      calcularTotalesGenerales();
    });

    // ── Modal eliminar ítem (solo modo compra) ──────────────────────────────
    <?php if ($esCompra) { ?>
    var eliminarItemId       = null;
    var eliminarItemIdCompra = null;

    $(document).on('click', '.btn-eliminar-item', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var $btn             = $(this).closest('.btn-eliminar-item');
      eliminarItemId       = $btn.attr('data-id');
      eliminarItemIdCompra = $btn.attr('data-id-compra');
      var concepto         = $btn.attr('data-concepto');
      if (eliminarItemId && eliminarItemIdCompra) {
        $('#modalConceptoNombre').text(concepto);
        $('#modalEliminar').modal('show');
      } else {
        alert('Error: No se pudieron obtener los datos del ítem a eliminar.');
      }
    });

    $(document).on('click', '#btnConfirmarEliminar', function() {
      if (eliminarItemId && eliminarItemIdCompra) {
        window.location.href = 'eliminarConceptoCompra.php?id=' + eliminarItemId
                             + '&id_compra=' + eliminarItemIdCompra;
      }
    });
    <?php } ?>

  }); // fin document.ready

  // ── Validación del formulario antes de enviar ───────────────────────────
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
      var qty   = parseFloat($(this).val()) || 0;
      var row   = $(this).closest('tr');
      var prc   = parseFloat(row.find('.precio-input').val())   || 0;
      var prcKg = parseFloat(row.find('.preciokg-input').val()) || 0;

      if (qty > 0) {
        if (prc <= 0 && prcKg <= 0) {
          alert('Hay ítems con cantidad seleccionada pero sin precio cargado.');
          $(this).focus();
          valid = false;
          return false; // rompe el .each
        }
        hayConceptoValido = true;
      }
    });

    if (!valid) return false;

    if (!hayConceptoValido) {
      alert('Debe ingresar al menos un concepto con cantidad mayor a 0 '
          + 'y al menos uno de los dos precios (Precio Unitario o Precio x Kg).');
      return false;
    }

    return true;
  }

  <?php if (!$esCompra && $puedeEditar) { ?>
  var montosMinimos = {
    pesos: <?= $montos_minimos['pesos'] ?>,
    usd: <?= $montos_minimos['usd'] ?>
  };

  $('#btn-crear-oc').on('click', function() {
    if (!validarFormularioCompra()) return;

    var totalNeto = 0;
    $('#dataTables-example667 tbody tr').each(function() {
      var sub = $(this).data('subtotal') || 0;
      totalNeto += sub;
    });
    var descGral = parseFloat($('#descuento_general_ctrl').val()) || 0;
    var baseIva = totalNeto * (1 - (descGral / 100));
    var opcionIva = $('#id_tipo_iva').find(':selected');
    var tasaIva = parseFloat(opcionIva.data('tasa')) || 0;
    var montoIva = baseIva * (tasaIva / 100);
    var totalFinal = baseIva + montoIva;

    var idMoneda = $('#id_moneda').val();
    var monedaTexto = $('#id_moneda option:selected').text();
    var montoLimite = (idMoneda == 1) ? montosMinimos.usd : montosMinimos.pesos;
    var simbolo = (idMoneda == 1) ? 'U$S' : '$';

    var totalFormateado = simbolo + ' ' + totalFinal.toLocaleString('es-AR', { minimumFractionDigits: 2 });
    var limiteFormateado = simbolo + ' ' + montoLimite.toLocaleString('es-AR', { minimumFractionDigits: 2 });

    var mensaje = '';
    if (totalFinal < montoLimite) {
      mensaje = '<div class="alert mb-0">' +
        '<strong>Total de la OC:</strong> ' + totalFormateado + '<br>' +
        '<strong>Monto mínimo de aprobación (' + monedaTexto + '):</strong> ' + limiteFormateado + '<br><br>' +
        'El monto es <strong>menor</strong> al mínimo de aprobación. La OC se <strong>aprobará automáticamente</strong>.' +
        '</div>';
    } else {
      mensaje = '<div class="alert mb-0">' +
        '<strong>Total de la OC:</strong> ' + totalFormateado + '<br>' +
        '<strong>Monto mínimo de aprobación (' + monedaTexto + '):</strong> ' + limiteFormateado + '<br><br>' +
        'El monto es <strong>igual o superior</strong> al mínimo de aprobación. La OC deberá ser <strong>enviada a aprobación</strong>.' +
        '</div>';
    }

    $('#modalConfirmarOCBody').html(mensaje);
    $('#modalConfirmarOC').modal('show');
  });

  $('#btn-confirmar-crear-oc').on('click', function() {
    $('#modalConfirmarOC').modal('hide');
    $('#form-unificado').submit();
  });
  <?php } ?>
  </script>

  <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
</body>
</html>
<?php Database::disconnect(); ?>
