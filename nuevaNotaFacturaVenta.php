<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/FacturacionARCA.php';

if (empty($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}
if (empty(tienePermiso(337))) {
    http_response_code(403);
    exit('No tiene permiso para crear notas de crédito/débito.');
}

$idFacturaOrigen = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idFacturaOrigen || $idFacturaOrigen < 1) {
    http_response_code(400);
    exit('Factura de origen inválida.');
}
$tipoNota = (string)($_GET['tipo'] ?? '');
if (!in_array($tipoNota, ['credito', 'debito'], true)) {
    http_response_code(400);
    exit('Tipo de nota inválido.');
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$qOrigen = $pdo->prepare(
    "SELECT fv.id, fv.id_tipo_comprobante AS tipo_factura,
            fv.id_letra_comprobante, fv.id_proyecto, fv.numero AS numero_local,
            fv.id_cuenta_destino, fv.id_empresa, fv.actividad_arca_id,
            fv.id_condicion_pago, fv.id_moneda, fv.cotizacion, fv.id_estado,
            fv.pagada, fv.exportada, ca.id AS id_comprobante_arca,
            ca.homologacion, ca.emisor_cuit, ca.punto_venta,
            ca.tipo_comprobante, ca.numero_comprobante, ca.estado AS estado_arca,
            ca.receptor_numero_documento, ca.receptor_tipo_documento,
            ca.receptor_condicion_iva, ca.concepto, ca.detalle_comprobante, ca.fecha_servicio_desde,
            ca.fecha_servicio_hasta, ca.fecha_vencimiento_pago,
            ca.moneda, ca.cotizacion AS cotizacion_arca,
            c.nombre AS cliente, c.razon_social AS razon_social_cliente,
            c.cuit AS cuit_cliente, c.direccion AS direccion_cliente
     FROM facturas_venta fv
     INNER JOIN comprobantes_arca ca ON ca.id_factura_venta = fv.id
     INNER JOIN cuentas c ON c.id = fv.id_cuenta_destino
     WHERE fv.id = ? LIMIT 1"
);
$qOrigen->execute([$idFacturaOrigen]);
$origen = $qOrigen->fetch(PDO::FETCH_ASSOC);
$letrasPorTipo = [1 => 'A', 6 => 'B', 11 => 'C'];
if (!$origen || $origen['estado_arca'] !== 'autorizado'
    || (int)$origen['id_estado'] !== 3
    || !isset($letrasPorTipo[(int)$origen['tipo_comprobante']])) {
    Database::disconnect();
    http_response_code(422);
    exit('La nota solo puede originarse en una factura A, B o C definitiva y autorizada por ARCA.');
}
$letra = $letrasPorTipo[(int)$origen['tipo_comprobante']];
$codigosNota = [
    'A' => ['debito' => 2, 'credito' => 3],
    'B' => ['debito' => 7, 'credito' => 8],
    'C' => ['debito' => 12, 'credito' => 13],
];
$codigoNota = $codigosNota[$letra][$tipoNota];
$idLetraNota = derivarIdLetraComprobante($pdo, $codigoNota);
$nombreNota = $tipoNota === 'credito' ? 'Nota de Crédito ' : 'Nota de Débito ';
$nombreNota .= $letra;

$qTipos = $pdo->prepare(
    'SELECT codigo, descripcion FROM arca_tipos_comprobante WHERE codigo IN (?, ?, ?)'
);
$qTipos->execute([$codigosNota[$letra]['debito'], $codigosNota[$letra]['credito'], (int)$origen['tipo_comprobante']]);
$tiposEncontrados = $qTipos->fetchAll(PDO::FETCH_KEY_PAIR);
if (!isset($tiposEncontrados[$codigoNota])) {
    Database::disconnect();
    http_response_code(422);
    exit('El catálogo ARCA no tiene configurado el tipo de nota correspondiente a esta letra.');
}

function cargarComprobantesAsociablesNota(PDO $pdo, array $origen, int $tipoFactura): array {
    $q = $pdo->prepare(
        "SELECT ca.id, ca.tipo_comprobante, ca.punto_venta, ca.numero_comprobante,
                ca.fecha_comprobante, fv.numero AS numero_local, c.nombre AS cliente
         FROM comprobantes_arca ca
         INNER JOIN facturas_venta fv ON fv.id = ca.id_factura_venta
         INNER JOIN cuentas c ON c.id = fv.id_cuenta_destino
         WHERE ca.estado = 'autorizado' AND ca.homologacion = ?
           AND ca.emisor_cuit = ? AND ca.receptor_numero_documento = ?
           AND ca.tipo_comprobante = ? AND ca.id <> ?
         ORDER BY ca.fecha_comprobante DESC, ca.id DESC"
    );
    $q->execute([
        (int)$origen['homologacion'],
        $origen['emisor_cuit'],
        $origen['receptor_numero_documento'],
        $tipoFactura,
        (int)$origen['id_comprobante_arca'],
    ]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

$tipoFacturaOrigen = array_search($letra, $letrasPorTipo, true);
if ($tipoFacturaOrigen === false) {
    Database::disconnect();
    http_response_code(422);
    exit('No se pudo determinar la letra de la factura de origen.');
}

if (($_GET['ajax'] ?? '') === 'comprobantes_asociables') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        echo json_encode(cargarComprobantesAsociablesNota($pdo, $origen, (int)$tipoFacturaOrigen), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudieron buscar los comprobantes asociados.']);
    }
    Database::disconnect();
    exit;
}

$csrfToken = (string)($_SESSION['csrf_factura_arca'] ?? '');
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_factura_arca'] = $csrfToken;
}
$claveOperacion = (string)($_SESSION['nota_fv_clave_actual'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/D', $claveOperacion)) {
    $claveOperacion = bin2hex(random_bytes(16));
    $_SESSION['nota_fv_clave_actual'] = $claveOperacion;
}
$error = '';
$incluirOtrosComprobantes = $_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_POST['incluir_otros_comprobantes']);
$asociables = $incluirOtrosComprobantes
    ? cargarComprobantesAsociablesNota($pdo, $origen, (int)$tipoFacturaOrigen)
    : [];
$asociableIds = array_merge(
    [(int)$origen['id_comprobante_arca']],
    array_map(static fn(array $row): int => (int)$row['id'], $asociables)
);
$selectedAssociatedIds = $incluirOtrosComprobantes
    ? array_values(array_unique(array_map('intval', (array)($_POST['comprobantes_asociados'] ?? []))))
    : [(int)$origen['id_comprobante_arca']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('La sesión expiró. Vuelva a abrir la nota e intente nuevamente.');
        }
        $postedOperationKey = (string)($_POST['clave_operacion'] ?? '');
        $operacionesProcesadas = (array)($_SESSION['nota_fv_operaciones'] ?? []);
        if (preg_match('/^[a-f0-9]{32}$/D', $postedOperationKey)
            && !empty($operacionesProcesadas[$postedOperationKey])) {
            header('Location: verFacturaVenta.php?id=' . (int)$operacionesProcesadas[$postedOperationKey], true, 303);
            exit;
        }
        if (!hash_equals($claveOperacion, $postedOperationKey)) {
            throw new RuntimeException('La clave de operación no coincide. Recargue la página antes de emitir.');
        }
        if ((int)($_POST['id_factura_origen'] ?? 0) !== $idFacturaOrigen
            || (string)($_POST['tipo_nota'] ?? '') !== $tipoNota) {
            throw new RuntimeException('La factura de origen o el tipo de nota no coinciden con el formulario.');
        }
        if (!FacturacionARCA::habilitada()) {
            throw new RuntimeException('La emisión ARCA está deshabilitada.');
        }

        $asociados = $incluirOtrosComprobantes
            ? array_values(array_unique(array_filter(array_map('intval', (array)($_POST['comprobantes_asociados'] ?? [])))))
            : [(int)$origen['id_comprobante_arca']];
        if (!in_array((int)$origen['id_comprobante_arca'], $asociados, true)) {
            throw new RuntimeException('La factura seleccionada debe permanecer asociada a la nota.');
        }
        foreach ($asociados as $idAsociado) {
            if (!in_array($idAsociado, $asociableIds, true)) {
                throw new RuntimeException('Solo se pueden asociar facturas autorizadas de la misma letra y CUIT receptor.');
            }
        }

        $descripciones = (array)($_POST['detalle_descripcion'] ?? []);
        $conceptos = (array)($_POST['detalle_concepto'] ?? []);
        $cantidades = (array)($_POST['detalle_cantidad'] ?? []);
        $precios = (array)($_POST['detalle_precio'] ?? []);
        $lineas = [];
        $neto = 0.0;
        $ivaTotal = 0.0;
        $basesPorTasa = [];
        $qConcepto = $pdo->prepare(
            'SELECT cc.id, cc.descripcion, COALESCE(ti.tasa, 21) AS tasa
             FROM conceptos_contables cc LEFT JOIN tipos_iva ti ON ti.id = cc.id_alicuota_iva
             WHERE cc.id = ?'
        );
        foreach ($descripciones as $i => $descripcion) {
            $descripcion = trim((string)$descripcion);
            $idConcepto = filter_var($conceptos[$i] ?? null, FILTER_VALIDATE_INT);
            $cantidad = filter_var($cantidades[$i] ?? null, FILTER_VALIDATE_FLOAT);
            $precio = filter_var($precios[$i] ?? null, FILTER_VALIDATE_FLOAT);
            if ($descripcion === '' && $idConcepto === false && $cantidad === false && $precio === false) {
                continue;
            }
            if ($descripcion === '' || !$idConcepto || $cantidad === false || $cantidad <= 0
                || $precio === false || $precio <= 0) {
                throw new RuntimeException('Complete descripción, concepto, cantidad y precio positivo en cada renglón.');
            }
            $qConcepto->execute([(int)$idConcepto]);
            $concepto = $qConcepto->fetch(PDO::FETCH_ASSOC);
            if (!$concepto) {
                throw new RuntimeException('Uno de los conceptos contables seleccionados ya no existe.');
            }
            $subtotal = round($cantidad * $precio, 2);
            $tasa = $letra === 'C' || (int)$origen['receptor_condicion_iva'] === 5
                ? 0.0
                : (float)$concepto['tasa'];
            $lineas[] = [
                'descripcion' => $descripcion,
                'id_concepto' => (int)$concepto['id'],
                'cantidad' => $cantidad,
                'precio' => $precio,
                'subtotal' => $subtotal,
                'tasa' => $tasa,
            ];
            if ($tasa > 0) {
                $key = number_format($tasa, 4, '.', '');
                $basesPorTasa[$key] = ($basesPorTasa[$key] ?? 0) + (int)round($subtotal * 100);
            }
            $neto += $subtotal;
        }
        if (!$lineas) {
            throw new RuntimeException('Agregue al menos un renglón con importe propio para la nota.');
        }
        foreach ($basesPorTasa as $tasa => $baseCentavos) {
            $ivaTotal += round($baseCentavos * (float)$tasa / 100) / 100;
        }
        $neto = round($neto, 2);
        $ivaTotal = round($ivaTotal, 2);
        $fecha = trim((string)($_POST['fecha_emitida'] ?? ''));
        $fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d')) {
            throw new RuntimeException('Ingrese una fecha válida, no futura, para la nota.');
        }

        $arcaInput = [
            'concepto_arca' => (int)$origen['concepto'],
            'fecha_servicio_desde' => $origen['fecha_servicio_desde'],
            'fecha_servicio_hasta' => $origen['fecha_servicio_hasta'],
            'fecha_vencimiento_pago' => $fecha,
            'comprobantes_asociados' => $asociados,
            'comprobante_origen' => (int)$origen['id_comprobante_arca'],
        ];

        $pdo->beginTransaction();
        $qInsert = $pdo->prepare(
            'INSERT INTO facturas_venta
             (id_tipo_comprobante, id_letra_comprobante, id_proyecto, numero,
              id_cuenta_destino, id_empresa, actividad_arca_id, fecha_emitida, fecha_enviada,
              id_condicion_pago, subtotal_gravado, subtotal_no_gravado, otros, iva, total,
              id_moneda, cotizacion, observaciones, leyenda_impositiva, remito_punto_venta,
              remito_numero, id_usuario, id_estado, exportada)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, 3, 0)'
        );
        $qInsert->execute([
            $codigoNota,
            $idLetraNota,
            (int)$origen['id_proyecto'],
            str_pad((string)$origen['punto_venta'], 4, '0', STR_PAD_LEFT) . '-00000000',
            (int)$origen['id_cuenta_destino'],
            (int)$origen['id_empresa'],
            (int)$origen['actividad_arca_id'],
            $fecha,
            $fecha,
            (int)$origen['id_condicion_pago'],
            $neto,
            $ivaTotal,
            $neto + $ivaTotal,
            (int)$origen['id_moneda'],
            (float)$origen['cotizacion'],
            'Nota relacionada a comprobante ARCA ' . str_pad((string)$origen['punto_venta'], 5, '0', STR_PAD_LEFT)
                . '-' . str_pad((string)$origen['numero_comprobante'], 8, '0', STR_PAD_LEFT),
            '',
            (int)$_SESSION['user']['id'],
        ]);
        $idNota = (int)$pdo->lastInsertId();

        $qDetalle = $pdo->prepare(
            'INSERT INTO facturas_venta_detalle
             (id_factura_venta, id_concepto_contable, descripcion, ncm, cantidad, precio, subtotal, porcentaje_descuento)
             VALUES (?, ?, ?, NULL, ?, ?, ?, 0)'
        );
        foreach ($lineas as $linea) {
            $qDetalle->execute([
                $idNota,
                $linea['id_concepto'],
                $linea['descripcion'],
                $linea['cantidad'],
                $linea['precio'],
                $linea['subtotal'],
            ]);
        }

        $qConditions = $pdo->prepare(
            'SELECT id_forma_pago FROM facturas_venta_condiciones_pago WHERE id_factura_venta = ?'
        );
        $qConditions->execute([$idFacturaOrigen]);
        $conditionIds = $qConditions->fetchAll(PDO::FETCH_COLUMN);
        if ($conditionIds) {
            $qInsertCondition = $pdo->prepare(
                'INSERT INTO facturas_venta_condiciones_pago (id_factura_venta, id_forma_pago) VALUES (?, ?)'
            );
            foreach ($conditionIds as $conditionId) {
                $qInsertCondition->execute([$idNota, (int)$conditionId]);
            }
        }

        FacturacionARCA::validarFactura($pdo, $idNota, $arcaInput);
        $qLog = $pdo->prepare(
            "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
             VALUES (NOW(), ?, ?, 'Facturas de Venta', ?)"
        );
        $qLog->execute([
            (int)$_SESSION['user']['id'],
            $nombreNota . ' creada para factura ID #' . $idFacturaOrigen,
            'verFacturaVenta.php?id=' . $idNota,
        ]);
        $pdo->commit();
        $_SESSION['nota_fv_operaciones'][$claveOperacion] = $idNota;
        $_SESSION['nota_fv_clave_actual'] = bin2hex(random_bytes(16));
        Database::disconnect();

        try {
            $pdo = Database::connect();
            $resultado = FacturacionARCA::emitir($pdo, $idNota, $claveOperacion, $arcaInput);
            $_SESSION['factura_arca_mensaje'] = [
                'tipo' => $resultado['estado'] === 'autorizado' ? 'success' : 'warning',
                'texto' => $resultado['mensaje'],
            ];
        } catch (Throwable $e) {
            $_SESSION['factura_arca_mensaje'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
        } finally {
            Database::disconnect();
        }
        header('Location: verFacturaVenta.php?id=' . $idNota, true, 303);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$qConceptos = $pdo->query('SELECT id, codigo, descripcion FROM conceptos_contables ORDER BY descripcion');
$conceptosContables = $qConceptos->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include('head_forms.php'); ?>
  <style>
    .nota-lineas input, .nota-lineas select { min-width: 90px; }
    .nota-lineas .descripcion { min-width: 220px; }
  </style>
</head>
<body>
<div class="page-wrapper">
  <?php include('header.php'); ?>
  <div class="page-body-wrapper">
    <?php include('menu.php'); ?>
    <div class="page-body">
      <?php $ubicacion = $nombreNota . ' — ' . $origen['numero_local']; include_once('head_page.php'); ?>
      <div class="container-fluid">
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
        <form method="post" class="card" id="formNotaVenta">
          <div class="card-header"><h5>Crear <?=$nombreNota?></h5></div>
          <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8')?>">
            <input type="hidden" name="clave_operacion" value="<?=htmlspecialchars($claveOperacion, ENT_QUOTES, 'UTF-8')?>">
            <input type="hidden" name="id_factura_origen" value="<?=$idFacturaOrigen?>">
            <input type="hidden" name="tipo_nota" value="<?=htmlspecialchars($tipoNota, ENT_QUOTES, 'UTF-8')?>">
            <div class="alert alert-info">
              Se conservarán la letra <?=$letra?> y el punto de venta <?=str_pad((string)$origen['punto_venta'], 5, '0', STR_PAD_LEFT)?> de la factura seleccionada. La nota tendrá sus propios importes.
            </div>
            <div class="form-row">
              <div class="form-group col-md-4"><label>Factura de origen</label><input class="form-control" readonly value="<?=htmlspecialchars($origen['numero_local'], ENT_QUOTES, 'UTF-8')?>"></div>
              <div class="form-group col-md-4"><label>Receptor</label><input class="form-control" readonly value="<?=htmlspecialchars($origen['razon_social_cliente'] ?: $origen['cliente'], ENT_QUOTES, 'UTF-8')?>"></div>
              <div class="form-group col-md-4"><label>Fecha de emisión</label><input class="form-control" type="date" name="fecha_emitida" required value="<?=date('Y-m-d')?>" max="<?=date('Y-m-d')?>"></div>
            </div>

            <h6>Comprobantes asociados</h6>
            <p class="text-muted mb-2">La factura de origen se asociará siempre. La búsqueda de otras facturas es opcional.</p>
            <div class="alert alert-light py-2">
              Factura de origen: <strong><?=htmlspecialchars((string)$origen['numero_local'], ENT_QUOTES, 'UTF-8')?></strong>
              — <?=htmlspecialchars($nombreNota, ENT_QUOTES, 'UTF-8')?>
            </div>
            <div class="custom-control custom-checkbox mb-3">
              <input class="custom-control-input" type="checkbox" id="incluirOtrosComprobantes" name="incluir_otros_comprobantes" value="1" <?=$incluirOtrosComprobantes ? 'checked' : ''?>>
              <label class="custom-control-label" for="incluirOtrosComprobantes">Agregar otros comprobantes autorizados asociados</label>
            </div>
            <div id="comprobantesAsociablesContainer" class="table-responsive mb-4 <?=$incluirOtrosComprobantes ? '' : 'd-none'?>" data-loaded="<?=$incluirOtrosComprobantes ? '1' : '0'?>">
              <div id="comprobantesAsociablesEstado" class="text-muted mb-2" role="status"></div>
              <table class="table table-sm table-bordered">
                <thead><tr><th>Asociar</th><th>Factura</th><th>Tipo</th><th>Punto de venta / N.º ARCA</th><th>Fecha</th></tr></thead>
                <tbody id="comprobantesAsociablesBody">
                <?php foreach ($asociables as $asociable):
                  $tipoOriginal = ['A' => 'Factura A', 'B' => 'Factura B', 'C' => 'Factura C'][$letrasPorTipo[(int)$asociable['tipo_comprobante']] ?? ''] ?? 'Factura';
                  $comprobanteSeleccionado = in_array((int)$asociable['id'], $selectedAssociatedIds, true); ?>
                  <tr>
                    <td class="text-center"><input type="checkbox" name="comprobantes_asociados[]" value="<?=(int)$asociable['id']?>" <?=$comprobanteSeleccionado ? 'checked' : ''?>></td>
                    <td><?=htmlspecialchars((string)$asociable['numero_local'], ENT_QUOTES, 'UTF-8')?></td>
                    <td><?=htmlspecialchars($tipoOriginal, ENT_QUOTES, 'UTF-8')?></td>
                    <td><?=str_pad((string)$asociable['punto_venta'], 5, '0', STR_PAD_LEFT)?> / <?=str_pad((string)$asociable['numero_comprobante'], 8, '0', STR_PAD_LEFT)?></td>
                    <td><?=!empty($asociable['fecha_comprobante']) ? date('d/m/Y', strtotime($asociable['fecha_comprobante'])) : '—'?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($incluirOtrosComprobantes && !$asociables): ?>
                  <tr class="js-sin-comprobantes"><td colspan="5" class="text-muted">No se encontraron otras facturas autorizadas de la misma letra y CUIT receptor.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php // La factura de origen se envía siempre, aunque no se busquen comprobantes adicionales. ?>
            <input type="hidden" name="comprobantes_asociados[]" value="<?=(int)$origen['id_comprobante_arca']?>">

            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Renglones de la nota</h6>
              <button type="button" class="btn btn-sm btn-outline-primary" id="agregarLinea">Agregar renglón</button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-bordered nota-lineas" id="tablaLineas">
                <thead><tr><th>Descripción</th><th>Concepto</th><th>Cantidad</th><th>Precio unitario</th><th></th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <div class="card-footer">
            <button type="submit" class="btn btn-primary">Guardar y emitir en ARCA</button>
            <a href="listarFacturasVenta.php" class="btn btn-light">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
    <?php include('footer.php'); ?>
  </div>
</div>
<template id="plantillaLinea">
  <tr>
    <td><input class="form-control descripcion" name="detalle_descripcion[]" maxlength="255" required></td>
    <td><select class="form-control" name="detalle_concepto[]" required><option value="">Seleccione...</option><?php foreach ($conceptosContables as $concepto): ?><option value="<?=(int)$concepto['id']?>"><?=htmlspecialchars($concepto['codigo'] . ' — ' . $concepto['descripcion'], ENT_QUOTES, 'UTF-8')?></option><?php endforeach; ?></select></td>
    <td><input class="form-control" type="number" name="detalle_cantidad[]" min="0.01" step="0.01" required></td>
    <td><input class="form-control" type="number" name="detalle_precio[]" min="0.01" step="0.01" required></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger quitar-linea" aria-label="Quitar renglón">×</button></td>
  </tr>
</template>
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
<script>
  $(function() {
    const $incluirOtros = $('#incluirOtrosComprobantes');
    const $comprobantesContainer = $('#comprobantesAsociablesContainer');
    const $comprobantesBody = $('#comprobantesAsociablesBody');
    const $comprobantesEstado = $('#comprobantesAsociablesEstado');
    const tiposFactura = {1: 'Factura A', 6: 'Factura B', 11: 'Factura C'};

    function renderComprobantesAsociables(comprobantes) {
      $comprobantesBody.empty();
      if (!comprobantes.length) {
        $('<tr>').append($('<td>', {
          colspan: 5,
          class: 'text-muted',
          text: 'No se encontraron otras facturas autorizadas de la misma letra y CUIT receptor.'
        })).appendTo($comprobantesBody);
        return;
      }

      comprobantes.forEach(function(comprobante) {
        const fechaRaw = String(comprobante.fecha_comprobante || '').substring(0, 10);
        const fechaPartes = fechaRaw.split('-');
        const fecha = fechaPartes.length === 3 ? fechaPartes[2] + '/' + fechaPartes[1] + '/' + fechaPartes[0] : '—';
        const tipo = tiposFactura[Number(comprobante.tipo_comprobante)] || 'Factura';
        const puntoVenta = String(comprobante.punto_venta || '').padStart(5, '0');
        const numero = String(comprobante.numero_comprobante || '').padStart(8, '0');
        const $checkbox = $('<input>', {
          type: 'checkbox',
          name: 'comprobantes_asociados[]',
          value: Number(comprobante.id)
        });
        const $row = $('<tr>');
        $('<td>', {class: 'text-center'}).append($checkbox).appendTo($row);
        $('<td>').text(comprobante.numero_local || '').appendTo($row);
        $('<td>').text(tipo).appendTo($row);
        $('<td>').text(puntoVenta + ' / ' + numero).appendTo($row);
        $('<td>').text(fecha).appendTo($row);
        $row.appendTo($comprobantesBody);
      });
    }

    $incluirOtros.on('change', function() {
      if (!this.checked) {
        $comprobantesContainer.addClass('d-none').attr('data-loaded', '0');
        $comprobantesBody.empty();
        $comprobantesEstado.text('');
        return;
      }

      $comprobantesContainer.removeClass('d-none');
      if ($comprobantesContainer.attr('data-loaded') === '1') return;

      $comprobantesEstado.text('Buscando comprobantes autorizados…');
      $.ajax({
        url: 'nuevaNotaFacturaVenta.php',
        method: 'GET',
        data: {
          id: <?= (int)$idFacturaOrigen ?>,
          tipo: <?= json_encode($tipoNota) ?>,
          ajax: 'comprobantes_asociables'
        },
        dataType: 'json'
      }).done(function(data) {
        if (!Array.isArray(data)) {
          $comprobantesEstado.text((data && data.error) || 'No se pudieron cargar los comprobantes.');
          return;
        }
        renderComprobantesAsociables(data);
        $comprobantesContainer.attr('data-loaded', '1');
        $comprobantesEstado.text('');
      }).fail(function(xhr) {
        const respuesta = xhr.responseJSON;
        $comprobantesEstado.text((respuesta && respuesta.error) || 'No se pudieron cargar los comprobantes. Intente nuevamente.');
      });
    });

    function agregarLinea() {
      $('#tablaLineas tbody').append(document.importNode($('#plantillaLinea')[0].content, true));
    }
    $('#agregarLinea').on('click', agregarLinea);
    $(document).on('click', '.quitar-linea', function() {
      if ($('#tablaLineas tbody tr').length > 1) $(this).closest('tr').remove();
      else $(this).closest('tr').find('input, select').val('');
    });
    $('#formNotaVenta').on('submit', function() {
      $(this).find('button[type="submit"]').prop('disabled', true).text('Procesando…');
    });
    agregarLinea();
  });
</script>
</body>
</html>
