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

$qAsociables = $pdo->prepare(
    "SELECT ca.id, ca.tipo_comprobante, ca.punto_venta, ca.numero_comprobante,
            ca.fecha_comprobante, fv.numero AS numero_local, c.nombre AS cliente
     FROM comprobantes_arca ca
     INNER JOIN facturas_venta fv ON fv.id = ca.id_factura_venta
     INNER JOIN cuentas c ON c.id = fv.id_cuenta_destino
     WHERE ca.estado = 'autorizado' AND ca.homologacion = ?
       AND ca.emisor_cuit = ? AND ca.receptor_numero_documento = ?
       AND ca.tipo_comprobante IN (1, 6, 11)
     ORDER BY ca.fecha_comprobante DESC, ca.id DESC"
);
$qAsociables->execute([
    (int)$origen['homologacion'],
    $origen['emisor_cuit'],
    $origen['receptor_numero_documento'],
]);
$asociables = [];
foreach ($qAsociables->fetchAll(PDO::FETCH_ASSOC) as $comprobante) {
    if (($letrasPorTipo[(int)$comprobante['tipo_comprobante']] ?? '') === $letra) {
        $asociables[] = $comprobante;
    }
}
$asociableIds = array_map(static fn(array $row): int => (int)$row['id'], $asociables);
if (!in_array((int)$origen['id_comprobante_arca'], $asociableIds, true)) {
    $asociables[] = [
        'id' => $origen['id_comprobante_arca'],
        'tipo_comprobante' => $origen['tipo_comprobante'],
        'punto_venta' => $origen['punto_venta'],
        'numero_comprobante' => $origen['numero_comprobante'],
        'fecha_comprobante' => '',
        'numero_local' => $origen['numero_local'],
        'cliente' => $origen['cliente'],
    ];
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

        $asociados = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['comprobantes_asociados'] ?? [])))));
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
            <p class="text-muted">La factura de origen es obligatoria. Puede asociar otras facturas autorizadas de la misma letra y CUIT receptor.</p>
            <div class="table-responsive mb-4">
              <table class="table table-sm table-bordered">
                <thead><tr><th>Asociar</th><th>Factura</th><th>Tipo</th><th>Punto de venta / N.º ARCA</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($asociables as $asociable):
                  $isOrigin = (int)$asociable['id'] === (int)$origen['id_comprobante_arca'];
                  $tipoOriginal = ['A' => 'Factura A', 'B' => 'Factura B', 'C' => 'Factura C'][$letrasPorTipo[(int)$asociable['tipo_comprobante']] ?? ''] ?? 'Factura'; ?>
                  <tr>
                    <td class="text-center"><input type="checkbox" name="comprobantes_asociados[]" value="<?=(int)$asociable['id']?>" <?=$isOrigin ? 'checked disabled' : ''?>></td>
                    <td><?=htmlspecialchars((string)$asociable['numero_local'], ENT_QUOTES, 'UTF-8')?><?=$isOrigin ? ' (origen)' : ''?></td>
                    <td><?=htmlspecialchars($tipoOriginal, ENT_QUOTES, 'UTF-8')?></td>
                    <td><?=str_pad((string)$asociable['punto_venta'], 5, '0', STR_PAD_LEFT)?> / <?=str_pad((string)$asociable['numero_comprobante'], 8, '0', STR_PAD_LEFT)?></td>
                    <td><?=!empty($asociable['fecha_comprobante']) ? date('d/m/Y', strtotime($asociable['fecha_comprobante'])) : '—'?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php // disabled HTML checkboxes do not post; this hidden field guarantees the origin association. ?>
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
<script>
  $(function() {
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
