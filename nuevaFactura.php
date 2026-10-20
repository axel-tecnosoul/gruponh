<?php if (!isset($vista)) return;
$fv = function($key, $default = '') use ($facturaData) {
  if (!empty($facturaData) && isset($facturaData[$key])) {
    return htmlspecialchars($facturaData[$key]);
  }
  return htmlspecialchars($default);
};
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?php include('head_forms.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <style>
    .select2-container {
      width: 100% !important;
    }

    .select2-container--default .select2-selection--single {
      height: calc(1.5em + .75rem + 2px) !important;
      padding: .375rem .75rem !important;
      font-size: 1rem !important;
      line-height: 1.5 !important;
      color: #495057 !important;
      background-color: #fff !important;
      border: 1px solid #ced4da !important;
      border-radius: .25rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: calc(1.5em + .75rem + 2px) !important;
      top: 0 !important;
      right: 8px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: calc(1.5em + .75rem) !important;
      padding-left: 0 !important;
      color: #495057 !important;
    }

    .select2-container--disabled .select2-selection--single {
      background-color: #e9ecef !important;
      cursor: not-allowed !important;
    }
  </style>
</head>

<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body">
        <?php $ubicacion = ($vista === 'venta') ? "Nueva Factura de Venta" : "Nueva Factura Compra";
        include_once("head_page.php"); ?>
        <div class="container-fluid">

          <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
          <?php endif; ?>

          <form id="formFactura" class="form theme-form" role="form" method="post"
            action="<?= $vista === 'venta'
              ? 'nuevaFacturaVenta.php' . (!empty($_GET['id_proyecto']) ? '?id_proyecto=' . intval($_GET['id_proyecto']) : '')
              : 'nuevaFacturaCompra.php' ?>">
            <?php if (!empty($certIds)): ?>
            <?php foreach ($certIds as $cid): ?>
              <input type="hidden" name="certificados[]" value="<?= $cid ?>">
            <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($editMode) && !empty($facturaData['id'])): ?>
            <input type="hidden" name="id_factura" value="<?= (int)$facturaData['id'] ?>">
            <?php endif; ?>
            <input type="hidden" name="detalles_json" id="detalles_json" value="[]">
            <input type="hidden" name="retenciones_json" id="retenciones_json" value="[]">
            <?php if ($vista === 'venta'): ?>
              <input type="hidden" name="clave_operacion" value="<?= htmlspecialchars($claveOperacion ?? bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <div class="row">
              <div class="col-sm-12">

                <!-- CARD 1 - DATOS DE LA FACTURA -->
                <div class="card">
                  <div class="card-header">
                    <h5>Datos de la Factura</h5>
                  </div>
                  <div class="card-body">
                    <div class="row">

                      <!-- COLUMNA IZQUIERDA -->
                      <div class="col-md-6">

                        <?php if ($vista === 'compra'): ?>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Órdenes de Compra(*)</label>
                          <div class="col-sm-8">
                            <?php if ($ocPreseleccionada && !empty($ocLabels)): ?>
                              <p class="form-control-plaintext">
                                <?php foreach ($ocLabels as $ol): ?>
                                  <a href="verCompra.php?id=<?= (int)$ol['id'] ?>" target="_blank">
                                    OC #<?= htmlspecialchars($ol['nro_oc']) ?>
                                  </a>
                                  <input type="hidden" name="id_orden_compra[]" value="<?= (int)$ol['id'] ?>">
                                  <?php if (next($ocLabels)) echo ', '; ?>
                                <?php endforeach; ?>
                              </p>
                              <?php if (!empty($ocTotalFormatted)): ?>
                                <small class="text-muted">Total OC: <?= htmlspecialchars($ocTotalFormatted) ?></small>
                              <?php endif; ?>
                            <?php else: ?>
                            <select name="id_orden_compra" id="id_orden_compra" class="js-example-basic-single col-sm-12" required onchange="jsRecargar();">
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $q = $pdo->prepare("SELECT id, nro_oc FROM compras WHERE id_estado_compra NOT IN (1, 2, 4)");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>" . htmlspecialchars($f['nro_oc']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php endif; ?>
                          </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Empresa(*)</label>
                          <div class="col-sm-8">
                            <?php if (($vista === 'venta' && $preseleccionado) || ($vista === 'compra' && $ocPreseleccionada)): ?>
                              <p class="form-control-plaintext"><?= htmlspecialchars($empresaLabel) ?></p>
                              <?php if ($vista === 'venta'): ?>
                                <input type="hidden" name="id_empresa" id="id_empresa" value="<?= intval($proyectoDatos['id_empresa']) ?>">
                              <?php else: ?>
                                <input type="hidden" name="id_empresa" id="id_empresa" value="<?= (int)$idEmpresa ?>">
                              <?php endif; ?>
                            <?php else: ?>
                            <select name="id_empresa" id="id_empresa" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $q = $pdo->prepare("SELECT id, empresa FROM empresas WHERE 1");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                if ($vista === 'venta') {
                                  $sel = (!empty($proyectoDatos['id_empresa']) && $proyectoDatos['id_empresa'] == $f['id']) ? ' selected' : '';
                                } else {
                                  $sel = ($idEmpresa != 0 && $f['id'] == $idEmpresa) ? ' selected' : '';
                                }
                                echo "<option value='{$f['id']}'$sel>" . htmlspecialchars($f['empresa']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php endif; ?>
                          </div>
                        </div>

                        <?php if ($vista === 'venta'): ?>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Cliente(*)</label>
                          <div class="col-sm-8">
                            <?php if ($preseleccionado): ?>
                              <p class="form-control-plaintext"><?= htmlspecialchars($clienteLabel) ?></p>
                              <input type="hidden" name="id_cuenta_destino" id="id_cuenta_destino" value="<?= intval($proyectoDatos['id_cliente']) ?>">
                            <?php else: ?>
                            <select name="id_cuenta_destino" id="id_cuenta_destino" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta IN (1) AND activo = 1 AND anulado = 0");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>" . htmlspecialchars($f['nombre']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Proyecto(*)</label>
                          <div class="col-sm-8">
                            <?php $idProyectoGet = !empty($_GET['id_proyecto']) ? intval($_GET['id_proyecto']) : 0; ?>
                            <?php if ($preseleccionado): ?>
                              <p class="form-control-plaintext"><?= htmlspecialchars($proyectoLabel) ?></p>
                              <input type="hidden" name="id_proyecto" value="<?= $idProyectoGet ?>">
                            <?php else: ?>
                            <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre
                                                FROM proyectos p
                                                INNER JOIN sitios s ON s.id = p.id_sitio
                                                WHERE p.anulado = 0 ORDER BY p.nro DESC");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $label = $f['nro_sitio'] . '-' . $f['nro_subsitio'] . '-' . $f['nro'] . ': ' . htmlspecialchars($f['nombre']);
                                echo "<option value='{$f['id']}'>$label</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php endif; ?>
                          </div>
                        </div>
                        <?php else: ?>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Proveedor(*)</label>
                          <div class="col-sm-8">
                            <?php if ($ocPreseleccionada): ?>
                              <p class="form-control-plaintext"><?= htmlspecialchars($proveedorLabel) ?></p>
                              <input type="hidden" name="id_cuenta_origen" value="<?= (int)$idProveedor ?>">
                            <?php else: ?>
                            <select name="id_cuenta_origen" id="id_cuenta_origen" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $q = $pdo->prepare("SELECT p.id, p.razon_social, p.cuit, c.condicion_iva
                                                FROM cuentas p
                                                LEFT JOIN condiciones_iva c ON c.id = p.id_condicion_iva
                                                WHERE p.id_tipo_cuenta IN (5) AND p.activo = 1 AND p.anulado = 0");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>" . htmlspecialchars($f['razon_social']) . " (" . htmlspecialchars($f['cuit']) . ") - Iva: " . htmlspecialchars($f['condicion_iva']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php endif; ?>
                          </div>
                        </div>
                        <?php endif; ?>

                        <?php
                        // Punto de venta actual (alta: vacío; edición: prefijo de numero PPPP-NNNNNNNN).
                        $pvActual = '';
                        $ncActual = '';
                        $numeroCompletoPv = $fv('numero');
                        if (!empty($numeroCompletoPv) && str_contains($numeroCompletoPv, '-')) {
                          $partesPv = explode('-', $numeroCompletoPv, 2);
                          $pvActual = ltrim($partesPv[0], '0');
                          $ncActual = ltrim($partesPv[1], '0');
                        }
                        ?>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Punto de venta(*)</label>
                          <div class="col-sm-8">
                            <?php if ($vista === 'venta'): ?>
                            <select name="punto_venta" id="punto_venta" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione la empresa primero...</option>
                            </select>
                            <?php else: ?>
                            <div class="input-group">
                              <input name="punto_venta" type="text" maxlength="5"
                                class="form-control" required
                                style="width:80px;flex:none;" value="<?= htmlspecialchars($pvActual) ?>"
                                id="punto_venta" oninput="this.value=this.value.replace(/\D/g,'')">
                              <div class="input-group-append">
                                <span class="input-group-text">-</span>
                              </div>
                              <input name="nro_comprobante" type="text" maxlength="8"
                                class="form-control" required
                                value="<?= htmlspecialchars($ncActual) ?>"
                                id="nro_comprobante" oninput="this.value=this.value.replace(/\D/g,'')">
                            </div>
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Tipo Comprobante(*)</label>
                          <div class="col-sm-8">
                            <select name="id_tipo_comprobante" id="id_tipo_comprobante" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT codigo, descripcion FROM arca_tipos_comprobante ORDER BY codigo");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $codigoAfip = (int)$f['codigo'];
                                $letraOpt = letraComprobanteDesdeTipo($f['descripcion'], $codigoAfip);
                                echo "<option value='{$codigoAfip}' data-codigo='$codigoAfip' data-letra='$letraOpt'>" . str_pad($codigoAfip, 3, '0', STR_PAD_LEFT) . ' - ' . htmlspecialchars($f['descripcion']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <input type="hidden" name="id_letra_comprobante" id="id_letra_comprobante" value="">
                            <small id="tipoHabilitadosHint" class="text-danger d-none"></small>
                          </div>
                        </div>

                      </div>

                      <!-- COLUMNA DERECHA -->
                      <div class="col-md-6">

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha Emitida(*)</label>
                          <div class="col-sm-8">
                            <input name="fecha_emitida" id="fecha_emitida" type="date"
                              onfocus="this.showPicker()" class="form-control"
                              value="<?= $fv('fecha_emitida', date('Y-m-d')) ?>"
                              required>
                          </div>
                        </div>

                        <?php if ($vista === 'venta'): ?>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha Enviada(*)</label>
                          <div class="col-sm-8">
                            <input name="fecha_enviada" id="fecha_enviada" type="date"
                              onfocus="this.showPicker()" class="form-control"
                              value="<?= $fv('fecha_enviada', date('Y-m-d')) ?>" required>
                          </div>
                        </div>
                        <?php else: ?>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha Recibida(*)</label>
                          <div class="col-sm-8">
                            <input name="fecha_recibida" id="fecha_recibida" type="date"
                              onfocus="this.showPicker()" class="form-control"
                              value="<?= $fv('fecha_recibida', date('Y-m-d')) ?>" required>
                          </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Moneda(*)</label>
                          <div class="col-sm-8">
                            <?php $monedaBloqueada = ($vista === 'compra' && $ocPreseleccionada) || ($vista === 'venta' && !empty($certIds) && !empty($idMoneda)); ?>
                            <?php if ($monedaBloqueada): ?>
                              <p class="form-control-plaintext"><?= htmlspecialchars($monedaLabel) ?></p>
                              <input type="hidden" name="id_moneda" id="id_moneda" value="<?= (int)$idMoneda ?>">
                            <?php else: ?>
                            <select name="id_moneda" id="id_moneda"
                              class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, moneda FROM monedas WHERE 1");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $esDolar = esMonedaDolar($f['moneda']) ? 'true' : 'false';
                                $sel = (!empty($idMoneda) && (int)$f['id'] === (int)$idMoneda) ? ' selected' : '';
                                echo "<option value='{$f['id']}' data-esusd='$esDolar'$sel>" . htmlspecialchars($f['moneda']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php endif; ?>
                          </div>
                        </div>

                        <?php if ($vista === 'venta'): ?>
                        <div class="form-group row" id="cotizacionRow" style="display:none;">
                          <label class="col-sm-4 col-form-label" id="labelCotizacion">Cotización</label>
                          <div class="col-sm-8">
                            <select id="tipo_dolar" class="form-control mb-2" style="display:none;"></select>
                            <div class="input-group">
                              <input name="cotizacion" id="cotizacion" type="number" step="0.01"
                                class="form-control" placeholder="Seleccione una moneda...">
                              <div class="input-group-append">
                                <span class="input-group-text" id="estadoDolar" style="min-width:160px;font-size:.85rem;">
                                  Esperando moneda...
                                </span>
                              </div>
                            </div>
                            <small id="infoCotizacion" class="text-muted"></small>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Otros Regímenes</label>
                          <div class="col-sm-8">
                            <select name="regimenes[]" id="regimenes" multiple="multiple"
                              class="js-example-basic-multiple col-sm-12">
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, regimen, porcentaje FROM regimenes_facturacion WHERE anulado = 0 ORDER BY regimen");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $sel = (!empty($regimenesSeleccionados) && in_array($f['id'], $regimenesSeleccionados)) ? ' selected' : '';
                                echo "<option value='{$f['id']}'$sel>" . htmlspecialchars($f['regimen']) . " ({$f['porcentaje']}%)</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                          </div>
                        </div>
                        <?php else: ?>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label" id="labelCotizacion">Cotización</label>
                          <div class="col-sm-8">
                            <select id="tipo_dolar" class="form-control mb-2" style="display:none;"></select>
                            <div class="input-group">
                              <input name="cotizacion" id="cotizacion" type="number" step="0.01"
                                class="form-control" placeholder="Seleccione una moneda..." value="<?= $fv('cotizacion') ?>">
                              <div class="input-group-append">
                                <span class="input-group-text" id="estadoDolar" style="min-width:160px;font-size:.85rem;">
                                  Esperando moneda...
                                </span>
                              </div>
                            </div>
                            <small id="infoCotizacion" class="text-muted"></small>
                          </div>
                        </div>

                        <?php endif; ?>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Observaciones</label>
                          <div class="col-sm-8">
                            <textarea name="observaciones" class="form-control" rows="3"><?= $fv('observaciones') ?></textarea>
                          </div>
                        </div>

                      </div>
                    </div>

                    <?php if ($vista === 'venta' && !empty($certIds)):
                      $pdo = Database::connect();
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $placeholders = implode(',', array_fill(0, count($certIds), '?'));
                      $qCert = $pdo->prepare(
                        "SELECT ca.id, ca.nro_certificado, ca.nro_revision,
                                  DATE_FORMAT(ca.fecha_emision,'%d/%m/%Y') AS fecha_emision,
                                  ca.monto_total, ca.aprobado_cliente, m.moneda
                           FROM certificados_avances_cabecera ca
                           INNER JOIN certificados_maestros cm ON cm.id = ca.id_certificado_maestro
                           LEFT JOIN monedas m ON m.id = cm.id_moneda
                           WHERE ca.id IN ($placeholders)"
                      );
                      $qCert->execute($certIds);
                      $certRows = $qCert->fetchAll(PDO::FETCH_ASSOC);
                      Database::disconnect();
                    ?>
                      <div class="row mt-2">
                        <div class="col-sm-12">
                          <div class="form-group row">
                            <label class="col-sm-2 col-form-label">Certificados vinculados</label>
                            <div class="col-sm-10">
                              <div class="list-group">
                                <?php foreach ($certRows as $cr): ?>
                                  <div class="list-group-item py-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                      <div>
                                        <strong><?= htmlspecialchars(fmtCertificadoAvance($cr['nro_certificado'], $cr['nro_revision'])) ?></strong>
                                        <span class="badge <?= $cr['aprobado_cliente'] ? 'badge-success' : 'badge-secondary' ?> ml-1">
                                          <?= $cr['aprobado_cliente'] ? 'Aprobado' : 'Pendiente' ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">Emitido: <?= htmlspecialchars($cr['fecha_emision']) ?></small>
                                      </div>
                                      <span class="font-weight-bold"><?= htmlspecialchars($cr['moneda'] ?? '') ?> <?= number_format($cr['monto_total'], 2, ',', '.') ?></span>
                                    </div>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                              <small class="text-muted">Estos certificados quedarán vinculados a la factura al crearla.</small>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endif; ?>

                  </div>
                </div>

                <?php if ($vista === 'venta'): ?>
                <div class="card">
                  <div class="card-header">
                    <h5>Datos para ARCA</h5>
                  </div>
                  <div class="card-body">
                    <div class="form-group row">
                      <label class="col-sm-3 col-form-label" for="concepto_arca">Concepto(*)</label>
                      <div class="col-sm-9">
                        <?php $conceptoArca = (int)($_POST['concepto_arca'] ?? 2); ?>
                        <select class="form-control" id="concepto_arca" name="concepto_arca" required>
                          <option value="1" <?= $conceptoArca === 1 ? 'selected' : '' ?>>Productos</option>
                          <option value="2" <?= $conceptoArca === 2 ? 'selected' : '' ?>>Servicios</option>
                          <option value="3" <?= $conceptoArca === 3 ? 'selected' : '' ?>>Productos y servicios</option>
                        </select>
                        <small class="form-text text-muted">El concepto se informa a ARCA y no se deduce del texto de los renglones.</small>
                      </div>
                    </div>
                    <div id="arcaPeriodoServicio">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label" for="fecha_servicio_desde">Período del servicio(*)</label>
                        <div class="col-sm-4">
                          <input type="date" class="form-control" id="fecha_servicio_desde" name="fecha_servicio_desde"
                            value="<?= htmlspecialchars($_POST['fecha_servicio_desde'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          <small class="form-text text-muted">Desde</small>
                        </div>
                        <div class="col-sm-4">
                          <input type="date" class="form-control" id="fecha_servicio_hasta" name="fecha_servicio_hasta"
                            value="<?= htmlspecialchars($_POST['fecha_servicio_hasta'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          <small class="form-text text-muted">Hasta</small>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label" for="fecha_vencimiento_pago">Vencimiento de pago(*)</label>
                        <div class="col-sm-9">
                          <input type="date" class="form-control" id="fecha_vencimiento_pago" name="fecha_vencimiento_pago"
                            value="<?= htmlspecialchars($_POST['fecha_vencimiento_pago'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- CARD 2 - DETALLE -->
                <div class="card">
                  <div class="card-header">
                    <h5><?= $vista === 'venta' ? 'Detalle de Factura de Venta' : 'Detalle de Factura Compra' ?> <span id="countDetalles"></span></h5>
                  </div>
                  <div class="card-body">

                    <div id="contenedorTablaDetalles" class="table-responsive" style="display:none; background:#fff;">
                      <table class="table table-bordered" id="tablaDetalles">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>Concepto</th>
                            <th>Descripción</th>
                            <th>Imputación</th>
                            <th>Precio</th>
                            <th>Descuento</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                          <tr>
                            <th colspan="6" class="text-right">Total:</th>
                            <th id="totalDetalle">$ 0.00</th>
                            <th colspan="1"></th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>

                    <div id="topeMaximo" style="color:red; text-align:right; padding:4px 0; font-weight:bold;"></div>

                    <div class="form-group row">
                      <div class="col-md-3 mb-2">
                        <label class="col-form-label">Concepto Contable(*)</label>
                          <select id="det_concepto" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option>
                            <?php
                            $pdo = Database::connect();
                            $q = $pdo->prepare("SELECT cc.id, cc.descripcion, COALESCE(ti.tasa, 21) as tasa 
                                                     FROM conceptos_contables cc 
                                                     LEFT JOIN tipos_iva ti ON ti.id = cc.id_alicuota_iva 
                                                     WHERE 1");
                            $q->execute();
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='{$f['id']}' data-tasa='{$f['tasa']}'>" . htmlspecialchars($f['descripcion']) . "</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                          <small id="iva-info" class="text-info"></small>
                      </div>
                      <div class="<?= $vista === 'venta' ? 'col-md-4' : 'col-md-3' ?> mb-2">
                        <label class="col-form-label">Descripción</label>
                        <textarea id="det_descripcion" class="form-control" rows="3" placeholder="Texto a mostrar impreso en la factura"></textarea>
                      </div>
<?php if ($vista === 'venta' || ($vista === 'compra' && !empty($imputacionesOC))): ?>
                      <?php
                        $imputacionesOC = $imputacionesOC ?? [];
                        $ocMap = [];
                        if (!empty($ocVinculadas)) {
                          foreach ($ocVinculadas as $ov) $ocMap[$ov['id_compra']] = $ov['nro_oc'];
                        }
                        if ($vista === 'compra'):
                          $ocIdsUnicos = array_unique(array_column($imputacionesOC, 'id_compra'));
                          $ocIdsFaltantes = array_diff($ocIdsUnicos, array_keys($ocMap));
                          if (!empty($ocIdsFaltantes)) {
                            $pdoMap = Database::connect();
                            $placeholders = implode(',', array_fill(0, count($ocIdsFaltantes), '?'));
                            $qMap = $pdoMap->prepare("SELECT id, nro_oc FROM compras WHERE id IN ($placeholders)");
                            $qMap->execute(array_values($ocIdsFaltantes));
                            while ($r = $qMap->fetch(PDO::FETCH_ASSOC)) $ocMap[$r['id']] = $r['nro_oc'];
                            Database::disconnect();
                          }
                        endif;
                      ?>
                      <div class="col-md-3 mb-2">
                        <label class="col-form-label">Imputaciones</label>
                        <?php
                          $dataOptions = [];
                          if ($vista === 'compra') {
                            $dataOptions = array_map(function($imp) use ($ocMap) {
                              $nro = $ocMap[$imp['id_compra']] ?? $imp['id_compra'];
                              $rest = (float)($imp['restante'] ?? $imp['oc_cantidad']);
                              $concepto = (string)($imp['concepto'] ?? '');
                              return [
                                'value' => $imp['id'],
                                'text' => "OC #{$nro} - {$concepto} (queda " . number_format($rest, 2) . " de " . number_format($imp['oc_cantidad'], 2) . ")",
                                // Se viaja explícito para no depender del texto visible
                                'concepto_text' => $concepto,
                                'restante' => $rest,
                                'oc_precio' => (float)$imp['oc_precio'],
                                'nro_oc' => $nro,
                                'done' => $rest <= 0
                              ];
                            }, $imputacionesOC);
                          }
                        ?>
                        <select id="det_imputaciones" class="js-example-basic-multiple col-sm-12" multiple="multiple" data-all-options='<?= htmlspecialchars(json_encode($dataOptions, JSON_UNESCAPED_UNICODE)) ?>'>
                        </select>
                      </div>
                      <?php endif; ?>
                      <div class="<?= $vista === 'venta' ? 'col-md-2' : 'col-md-3' ?> mb-2">
                        <label class="col-form-label">Descuento (%)</label>
                        <div class="input-group" style="max-width:150px;">
                          <input id="det_descuento" type="number" min="0" max="100" step="0.01" class="form-control" value="0">
                          <div class="input-group-append"><span class="input-group-text">%</span></div>
                        </div>
                      </div>
                    </div>

                    <div id="imp-sub-lines" style="padding:8px 0;"></div>

                    <input type="hidden" id="ocTotalData" value="<?= (float)($ocTotal ?? 0) ?>">

                    <div class="form-group row">
                      <div class="col-sm-9 offset-sm-3">
                        <button type="button" class="btn btn-success" id="btnAgregarDetalle">Agregar Ítem</button>
                        <button type="button" class="btn btn-light ml-2" id="btnCancelarDetalle" style="display:none;">Cancelar</button>
                      </div>
                    </div>

                  </div>
                </div>

                <!-- CARD 3 - RETENCIONES -->
                <div class="card">
                  <div class="card-header">
                    <h5><?= $vista === 'venta' ? 'Retenciones de Factura de Venta' : 'Retenciones de Factura Compra' ?> <span id="countRetenciones"></span></h5>
                  </div>
                  <div class="card-body">

                    <div id="contenedorTablaRetenciones" class="table-responsive" style="display:none; background:#fff;">
                      <table class="table table-bordered table-sm" id="tablaRetenciones">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>Régimen</th>
                            <th>Base imponible</th>
                            <th>%</th>
                            <th>Retención</th>
                            <th>Acciones</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                          <tr>
                            <th colspan="4" class="text-right">Total:</th>
                            <th id="totalRetenciones">$ 0.00</th>
                            <th></th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>

                    <div class="form-group row">
                      <div class="col-md-4 mb-2">
                        <label class="col-form-label">Régimen de Facturación(*)</label>
                        <select id="ret_regimen" class="js-example-basic-single col-sm-12">
                          <option value="">Seleccione...</option>
                          <?php
                          $pdo = Database::connect();
                          $q = $pdo->prepare("SELECT id, regimen, porcentaje, signo_cpr, signo_vta FROM regimenes_facturacion WHERE anulado = 0 ORDER BY regimen");
                          $q->execute();
                          $signoCampo = $vista === 'venta' ? 'signo_vta' : 'signo_cpr';
                          while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$f['id']}' data-porcentaje='{$f['porcentaje']}' data-signo='{$f[$signoCampo]}'>" . htmlspecialchars($f['regimen']) . " ({$f['porcentaje']}%)</option>";
                          }
                          Database::disconnect();
                          ?>
                        </select>
                      </div>
                      <div class="col-md-4 mb-2">
                        <label class="col-form-label">Base imponible(*)</label>
                        <div class="input-group" style="max-width:250px;">
                          <input id="ret_base" type="number" step="0.01" min="0" class="form-control" placeholder="Base imponible">
                          <div class="input-group-append">
                          <input id="ret_porcentaje" type="number" step="0.01" min="0" class="form-control" placeholder="%" style="max-width:70px;">
                          </div>
                        </div>
                      </div>
                      <div class="col-md-4 mb-2">
                        <label class="col-form-label">Retención(*)</label>
                        <div class="input-group" style="max-width:250px;">
                          <div class="input-group-prepend">
                            <span class="input-group-text">$</span>
                          </div>
                          <input id="ret_monto" type="number" step="0.01" class="form-control" placeholder="Monto retención">
                        </div>
                      </div>
                    </div>

                    <div class="form-group row">
                      <div class="col-sm-9 offset-sm-3">
                        <button type="button" class="btn btn-success" id="btnAgregarRetencion">Agregar Retención</button>
                        <button type="button" class="btn btn-light ml-2" id="btnCancelarRetencion" style="display:none;">Cancelar</button>
                      </div>
                    </div>

                  </div>
                </div>

                <!-- CARD 4 - RESUMEN Y GUARDAR -->
                <div class="card">
                  <div class="card-header">
                    <h5>Resumen</h5>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-5 ml-auto">
                        <table class="table table-sm table-borderless mb-0">
                          <tr><td class="text-right">Subtotal:</td><td class="text-right" id="granSubtotal">$ 0.00</td></tr>
                          <?php if ($vista === 'venta'): ?>
                            <tr><td class="text-right">IVA:</td><td class="text-right" id="granIva">$ 0.00</td></tr>
                          <?php endif; ?>
                          <tr><td class="text-right">Retenciones:</td><td class="text-right" id="granRetenciones">$ 0.00</td></tr>
                          <tr class="font-weight-bold" style="border-top: 2px solid #000;"><td class="text-right">Total:</td><td class="text-right" id="granTotal">$ 0.00</td></tr>
                        </table>
                      </div>
                    </div>
                  </div>
                  <div class="card-footer">
                    <div class="col-sm-9 offset-sm-3">
                        <button type="submit" class="btn btn-primary" name="btn_definitivo" value="1">
                          <?= $vista === 'venta' ? 'Emitir y guardar definitivo' : 'Guardar Definitivo' ?>
                        </button>
                        <button type="submit" class="btn btn-secondary" name="btn_temporal" value="1">
                          Guardar Temporal
                        </button>
                      <a href="<?= $vista === 'venta' ? 'listarFacturasVenta.php' : 'listarFacturasCompra.php' ?>"
                        class="btn btn-light">
                        <?= $vista === 'venta' ? 'Volver al Listado' : 'Volver' ?>
                      </a>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </form>

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
  <script src="assets/js/script.js"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script src="assets/js/cotizacion-dolar.js"></script>

  <?php if ($vista === 'compra'): ?>
  <script src="assets/js/typeahead/handlebars.js"></script>
  <script src="assets/js/typeahead/typeahead.bundle.js"></script>
  <script src="assets/js/typeahead/typeahead.custom.js"></script>
  <script src="assets/js/typeahead-search/handlebars.js"></script>
  <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
  <?php endif; ?>

  <script>
    function htmlEsc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var detalles = [];
    var retenciones = [];
    var editIndex = -1;
    var editRetencionIndex = -1;
    var monedaSimbolo = "<?= !empty($monedaLabel) ? htmlspecialchars($monedaLabel) : '$' ?>";
    var PROYECTO_DETALLE = "<?= !empty($proyectoLabel) ? htmlspecialchars(preg_replace('/:\s*.*$/', '', $proyectoLabel)) : '' ?>";

    function actualizarProyectoDetalle() {
      if (PROYECTO_DETALLE) return;
      var opt = $('#id_proyecto option:selected');
      var txt = opt.text() || '';
      var idx = txt.indexOf(':');
      PROYECTO_DETALLE = idx > 0 ? txt.substring(0, idx).trim() : '';
    }

    <?php if ($vista === 'compra'): ?>
    function jsRecargar() {
      document.location.href = "nuevaFacturaCompra.php?oc=" + document.getElementById('id_orden_compra').value;
    }
    <?php endif; ?>

    function formatNumber(value) {
      return value.toLocaleString('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    function renderDetalles() {
      actualizarProyectoDetalle();
      var tbody = $('#tablaDetalles tbody');
      tbody.empty();
      var total = 0;

      if (detalles.length === 0) {
        $('#contenedorTablaDetalles').hide();
        $('#countDetalles').text('');
      } else {
        $('#contenedorTablaDetalles').show();
        detalles.forEach(function(item, i) {
          var impsData = item.imputaciones_data || [];
          var totalCant = 0, totalSubImp = 0;
          impsData.forEach(function(imp) {
            totalCant += parseFloat(imp.cantidad || 0);
            totalSubImp += parseFloat(imp.cantidad || 0) * parseFloat(imp.precio || 0);
          });
          var porcDesc = parseFloat(item.porcentaje_descuento || 0);
          var sub = totalSubImp * (1 - porcDesc / 100);
          total += sub;

          var imputacionesHtml = '';
          if (impsData.length > 0) {
            imputacionesHtml = '<table class="table table-sm table-borderless mb-0" style="font-size:12px;background:none;"><thead><th style="padding: 0 2px;">Concepto</th><th style="text-align:right; padding: 0 2px;">Cant.</th><th style="text-align:right; padding: 0 2px;">Precio</th><th style="text-align:right; padding: 0 2px;">Subtotal</th></thead>';
            impsData.forEach(function(imp) {
              var subImp = parseFloat(imp.cantidad||0) * parseFloat(imp.precio||0);
              // El prefijo "OC #" solo aplica a facturas de compra; en venta nro_oc viene vacío
              var prefijoOC = imp.nro_oc ? 'OC #' + imp.nro_oc + ' - ' : '';
              imputacionesHtml += '<tr>';
              imputacionesHtml += '<td style="padding:2px 4px;">' + prefijoOC + htmlEsc(imp.concepto_text) + '</td>';
              imputacionesHtml += '<td style="padding:2px 4px;text-align:right;">' + parseFloat(imp.cantidad||0).toFixed(2) + '</td>';
              imputacionesHtml += '<td style="padding:2px 4px;text-align:right;">' + monedaSimbolo + ' ' + parseFloat(imp.precio||0).toFixed(2) + '</td>';
              imputacionesHtml += '<td style="padding:2px 4px;text-align:right;">' + monedaSimbolo + ' ' + subImp.toFixed(2) + '</td>';
              imputacionesHtml += '</tr>';
            });
            imputacionesHtml += '</table>';
          } else if (item.imputaciones_text && item.imputaciones_text.length) {
            imputacionesHtml = item.imputaciones_text.join('<br>');
          }

          tbody.append(
            '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + item.concepto_text + '</td>' +
            '<td>' + (PROYECTO_DETALLE ? htmlEsc(PROYECTO_DETALLE) + ' ' : '') + (item.descripcion ? htmlEsc(item.descripcion) : '') + '</td>' +
            '<td>' + imputacionesHtml + '</td>' +
            '<td class="text-right">' + monedaSimbolo + ' ' + formatNumber(totalSubImp) + '</td>' +
            '<td class="text-right">' + porcDesc.toFixed(2) + '%</td>' +
            '<td class="text-right">' + monedaSimbolo + ' ' + formatNumber(sub) + '</td>' +
            '<td><img src="img/icon_modificar.png" onclick="editarDetalle(' + i + ')" style="cursor:pointer" width="24" height="25" border="0" alt="Modificar/Anular" title="Modificar/Anular">&nbsp;<a href="javascript:void(0)" onclick="quitarDetalle(' + i + ')"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a></td>' +
            '</tr>'//<a href="javascript:void(0)" onclick="editarDetalle(' + i + ')"><i data-feather="edit" style="color:#000;"></i></a>
          );
        });
        $('#countDetalles').text('(' + detalles.length + ')');
        if (typeof feather !== 'undefined') { feather.replace(); }
      }

      $('#totalDetalle').text(monedaSimbolo + ' ' + total.toFixed(2));

      var ocTotal = parseFloat($('#ocTotalData').val()) || 0;
      if (ocTotal > 0) {
        $('#topeMaximo').text('(*)Tope Maximo Recomendado: ' + monedaSimbolo + ' ' + (ocTotal - total).toFixed(2));
      } else {
        $('#topeMaximo').text('');
      }

      $('#detalles_json').val(JSON.stringify(detalles));

      actualizarGranTotal();
    }

    function renderRetenciones() {
      var tbody = $('#tablaRetenciones tbody');
      tbody.empty();
      var total = 0;

      if (retenciones.length === 0) {
        $('#contenedorTablaRetenciones').hide();
        $('#countRetenciones').text('');
      } else {
        $('#contenedorTablaRetenciones').show();
        retenciones.forEach(function(item, i) {
          total += item.monto;
          var base = item.base || (item.porcentaje > 0 ? item.monto / (item.porcentaje / 100) : item.monto);
          tbody.append(
            '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + htmlEsc(item.regimen_text) + '</td>' +
            '<td>' + monedaSimbolo + ' ' + parseFloat(base).toFixed(2) + '</td>' +
            '<td>' + parseFloat(item.porcentaje || 0).toFixed(2) + '%</td>' +
            '<td>' + monedaSimbolo + ' ' + parseFloat(item.monto).toFixed(2) + '</td>' +
            '<td><img src="img/icon_modificar.png" onclick="editarRetencion(' + i + ')" style="cursor:pointer" width="24" height="25" border="0" alt="Modificar/Anular" title="Modificar/Anular">&nbsp;<a href="javascript:void(0)" onclick="quitarRetencion(' + i + ')"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a></td>' +
            '</tr>'
          );
        });
        $('#countRetenciones').text('(' + retenciones.length + ')');
      }

      $('#totalRetenciones').text(monedaSimbolo + ' ' + total.toFixed(2));
      $('#retenciones_json').val(JSON.stringify(retenciones));

      actualizarGranTotal();
      if (typeof feather !== 'undefined') { feather.replace(); }
    }

    function quitarDetalle(index) {
      var item = detalles[index];
      // Add back imputaciones to select2 (only if not already present)
      agregarOpcionesImputaciones(idsImputacionDeDetalle(item));
      // Reset editing state if the removed item was being edited
      if (editIndex === index) {
        editIndex = -1;
        $('#btnAgregarDetalle').text('Agregar Ítem');
        $('#btnCancelarDetalle').hide();
        $('#det_concepto').val('').trigger('change');
        $('#det_descripcion').val('');
        $('#det_descuento').val(0);
        $('#imp-sub-lines').empty();
      }
      detalles.splice(index, 1);
      renderDetalles();
    }

    // Vuelca a #det_imputaciones las opciones de una lista de ids (o de objetos con id_cd),
    // tomándolas de data-all-options con fallback a imputaciones_data guardada. No duplica.
    // Las de distinta moneda que la factura quedan deshabilitadas (visibles, no elegibles),
    // salvo forzarHabilitadas (edición de un ítem que ya es parte de la factura).
    function agregarOpcionesImputaciones(ids, forzarHabilitadas) {
      var select = $('#det_imputaciones');
      if (!ids || ids.length === 0) return;
      var opts = JSON.parse(select.attr('data-all-options') || '[]');
      ids.forEach(function (idv) {
        var idCd = (typeof idv === 'object' && idv !== null) ? (idv.id_cd || idv) : idv;
        if (idCd == null || idCd === '') return;
        var $exist = select.find('option[value="' + idCd + '"]');
        var found = opts.find(function (o) { return o.value == idCd; });
        if ($exist.length > 0) {
          if (forzarHabilitadas) {
            $exist.prop('disabled', false);
          } else if (found) {
            $exist.prop('disabled', !imputacionHabilitadaPorMoneda(found.id_moneda));
          }
          return;
        }
        var texto, valor, deshab;
        if (found) {
          texto = found.full_text || found.text;
          valor = found.value;
          deshab = !forzarHabilitadas && !imputacionHabilitadaPorMoneda(found.id_moneda);
        } else {
          // Fallback: la opción aún no llegó por ajax (edit rápido) — usar el dato guardado.
          // Ya es parte de la factura, se agrega habilitada.
          var fb = null;
          (detalles || []).forEach(function (d) {
            (d.imputaciones_data || []).forEach(function (imp) {
              if (imp.id_cd == idCd) { fb = imp; }
            });
          });
          if (!fb) return;
          texto = fb.concepto_text || ('CA #' + idCd);
          valor = idCd;
          deshab = false;
          opts.push({ value: idCd, text: texto, concepto_text: fb.concepto_text || texto, restante: fb.restante != null ? fb.restante : 1, oc_precio: fb.oc_precio != null ? fb.oc_precio : (fb.precio || 0), id_moneda: '', moneda: '' });
          select.attr('data-all-options', JSON.stringify(opts));
        }
        var optEl = new Option(texto, valor, false, false);
        optEl.disabled = !!deshab;
        select.append(optEl);
      });
      try { select.trigger('change.select2'); } catch (e) {}
    }

    // Moneda de la factura (hidden bloqueado o select editable). '' si aún no elegida.
    function monedaFacturaActual() {
      var v = $('#id_moneda').val();
      return (v === undefined || v === null) ? '' : String(v);
    }

    // ¿El CA puede imputarse en esta factura? Compara por id_moneda.
    function imputacionHabilitadaPorMoneda(idMonedaCA) {
      var monedaFv = monedaFacturaActual();
      if (!monedaFv) return true;
      if (idMonedaCA === undefined || idMonedaCA === null || idMonedaCA === '') return true;
      return String(idMonedaCA) === monedaFv;
    }

    // Re-aplica disabled por moneda a las opciones actuales; saca de la selección
    // las que queden deshabilitadas (conservando lo tipeado en las restantes).
    function refrescarDisabledImputaciones() {
      var select = $('#det_imputaciones');
      if (!select.length) return;
      var opts = JSON.parse(select.attr('data-all-options') || '[]');
      var optMap = {};
      opts.forEach(function (o) { optMap[o.value] = o; });
      var seleccion = select.val() || [];
      var nuevaSel = [];
      seleccion.forEach(function (val) {
        var o = optMap[val];
        if (o ? imputacionHabilitadaPorMoneda(o.id_moneda) : true) {
          nuevaSel.push(val);
        }
      });
      select.find('option').each(function () {
        var val = $(this).attr('value');
        var o = optMap[val];
        $(this).prop('disabled', o ? !imputacionHabilitadaPorMoneda(o.id_moneda) : false);
      });
      try { select.trigger('change.select2'); } catch (e) {}
      if (nuevaSel.length !== seleccion.length) {
        var guardados = [];
        $('#imp-sub-lines .imp-sub-row').each(function () {
          guardados.push({
            id_cd: parseInt($(this).data('cd-id')),
            concepto_text: $(this).find('.imp-sub-label').text().trim(),
            cantidad: parseFloat($(this).find('.imp-sub-cant').val()) || 0,
            precio: parseFloat($(this).find('.imp-sub-prec').val()) || 0
          });
        });
        select.val(nuevaSel);
        var items2 = [];
        nuevaSel.forEach(function (id) {
          var o = optMap[id];
          if (o) {
            items2.push({
              id_cd: o.value,
              concepto_text: o.concepto_text || o.text,
              nro_oc: o.nro_oc || '',
              restante: (o.restante != null) ? o.restante : 0,
              oc_precio: o.oc_precio || 0
            });
          }
        });
        buildSubLines(items2, guardados);
      }
    }

    // Extrae los ids de imputación de un detalle, tolerate que vengan como ids sueltos
    // o como objetos {id_cd}.
    function idsImputacionDeDetalle(item) {
      if (!item) return [];
      var fuente = item.imputaciones_data || item.imputaciones || [];
      return fuente.map(function (idv) {
        return (typeof idv === 'object' && idv !== null) ? (idv.id_cd || idv) : idv;
      });
    }

    function buildSubLines(items, storedData) {
      var container = $('#imp-sub-lines');
      container.empty();
      if (!items || items.length === 0) return;

      var storedMap = {};
      if (storedData) {
        storedData.forEach(function(s) { storedMap[s.id_cd] = s; });
      }

      items.forEach(function(item) {
        var st = storedMap[item.id_cd] || {};
        var cant = st.cantidad || item.restante || 0;
        var prec = st.precio || item.oc_precio || 0;
        var sub = cant * prec;
        var label = ('') + htmlEsc(item.concepto_text);
        container.append(
          '<div class="row imp-sub-row align-items-center" data-cd-id="' + item.id_cd + '" data-nro-oc="' + (item.nro_oc || '') + '" data-monto="' + (item.oc_precio || 0) + '" style="margin:0 0 6px 0; padding:6px 0; border-bottom:1px solid #eee;">' +
          '<div class="col-md-5"><span class="imp-sub-label" style="font-size:13px;">' + label + '</span></div>' +
          '<div class="col-md-2"><span style="font-size:13px; color:#333; margin-right:6px;">Cant:</span><input type="number" step="0.01" min="0" class="form-control imp-sub-cant" style="display:inline-block; width:60%;" value="' + cant + '" data-restante="' + (item.restante || 0) + '"></div>' +
          '<div class="col-md-2"><span style="font-size:13px; color:#333; margin-right:6px;">Precio:</span><input type="number" step="0.01" min="0" class="form-control imp-sub-prec" style="display:inline-block; width:60%;" value="' + prec + '"></div>' +
          '<div class="col-md-3 text-right"><span class="imp-sub-subtotal" style="font-size:13px; font-weight:bold;">' + monedaSimbolo + ' ' + sub.toFixed(2) + '</span></div>' +
          '</div>'
        );
      });
      container.append(
        '<div class="row" id="imp-sub-total-row" style="margin:0; padding:8px 0; border-top:2px solid #333;">' +
        '<div class="col-md-9 text-right font-weight-bold">Total Imputación:</div>' +
        '<div class="col-md-3 text-right"><span id="imp-sub-total-value" style="font-weight:bold;">' + monedaSimbolo + ' 0.00</span></div>' +
        '</div>'
      );
      actualizarSubTotalesImputacion();
    }

    function actualizarSubTotalesImputacion() {
      var total = 0;
      $('#imp-sub-lines .imp-sub-row').each(function() {
        var cant = parseFloat($(this).find('.imp-sub-cant').val()) || 0;
        var prec = parseFloat($(this).find('.imp-sub-prec').val()) || 0;
        var sub = cant * prec;
        $(this).find('.imp-sub-subtotal').text(monedaSimbolo + ' ' + sub.toFixed(2));
        total += sub;
      });
      $('#imp-sub-total-value').text(monedaSimbolo + ' ' + total.toFixed(2));
    }

    function quitarRetencion(index) {
      if (editRetencionIndex === index) {
        editRetencionIndex = -1;
        $('#btnAgregarRetencion').text('Agregar Retención');
        $('#btnCancelarRetencion').hide();
        $('#ret_regimen').val('').trigger('change');
        $('#ret_base').val('');
        $('#ret_monto').val('');
        $('#ret_porcentaje').val('');

      }
      retenciones.splice(index, 1);
      renderRetenciones();
    }

    function editarRetencion(index) {
      if (editRetencionIndex >= 0 && editRetencionIndex !== index) {
        editRetencionIndex = -1;
        $('#btnAgregarRetencion').text('Agregar Retención');
        $('#btnCancelarRetencion').hide();
      }
      editRetencionIndex = index;
      var item = retenciones[index];
      var opt = $('#ret_regimen option[value="' + item.id_regimen + '"]');
      if (opt.length === 0) {
        var newOpt = new Option(item.regimen_text + ' (' + (parseFloat(item.porcentaje || 0).toFixed(2)) + '%)', item.id_regimen, false, false);
        $(newOpt).data('porcentaje', item.porcentaje || 0);
        $(newOpt).data('signo', item.signo || (item.monto < 0 ? 2 : 1));
        $('#ret_regimen').append(newOpt);
      }
      $('#ret_regimen').val(item.id_regimen).trigger('change');
      $('#ret_base').val(item.base);
      $('#ret_monto').val(item.monto);
      $('#ret_porcentaje').val(item.porcentaje || 0);
      $('#btnAgregarRetencion').text('Modificar Retención');
      $('#btnCancelarRetencion').show();
    }

    function actualizarGranTotal() {
      var subTotal = 0;
      var retTotal = retenciones.reduce(function(total, item) {
        return total + (parseFloat(item.monto) || 0);
      }, 0);
      var ivaTotal = 0;
      <?php if ($vista === 'venta'): ?>
      var basesPorAlicuota = {};
      var esFacturaC = ($('#id_tipo_comprobante option:selected').data('letra') || '').toString().toUpperCase() === 'C';
      detalles.forEach(function(item) {
        var tasa = parseFloat($('#det_concepto option[value="' + item.id_concepto + '"]').data('tasa'));
        if (!Number.isFinite(tasa)) tasa = 21;
        if (esFacturaC) tasa = 0;
        var sub = 0;
        var impsData = item.imputaciones_data || [];
        if (impsData.length) {
          impsData.forEach(function(imp) {
            sub += (parseFloat(imp.cantidad) || 0) * (parseFloat(imp.precio) || 0);
          });
        } else {
          sub = (parseFloat(item.cantidad) || 0) * (parseFloat(item.precio) || 0);
        }
        sub *= 1 - (parseFloat(item.porcentaje_descuento) || 0) / 100;
        subTotal += sub;
        var clave = tasa.toFixed(4);
        basesPorAlicuota[clave] = (basesPorAlicuota[clave] || 0) + Math.round(sub * 100);
      });
      Object.keys(basesPorAlicuota).forEach(function(tasa) {
        var t = parseFloat(tasa);
        if (t > 0) {
          ivaTotal += Math.round(basesPorAlicuota[tasa] * t / (100 + t)) / 100;
        }
      });
      ivaTotal = Math.round(ivaTotal * 100) / 100;
      var brutoTotal = subTotal;
      var netoTotal = Math.round((brutoTotal - ivaTotal) * 100) / 100;
      $('#granIva').text(monedaSimbolo + ' ' + ivaTotal.toFixed(2));
      $('#granSubtotal').text(monedaSimbolo + ' ' + netoTotal.toFixed(2));
      $('#granRetenciones').text(monedaSimbolo + ' ' + retTotal.toFixed(2));
      $('#granTotal').text(monedaSimbolo + ' ' + (brutoTotal + retTotal).toFixed(2));
      <?php else: ?>
      subTotal = parseFloat($('#totalDetalle').text().replace(/[^0-9.-]/g, '')) || 0;
      $('#granSubtotal').text(monedaSimbolo + ' ' + subTotal.toFixed(2));
      $('#granRetenciones').text(monedaSimbolo + ' ' + retTotal.toFixed(2));
      $('#granTotal').text(monedaSimbolo + ' ' + (subTotal + ivaTotal + retTotal).toFixed(2));
      <?php endif; ?>
    }

    // Letra derivada del tipo de comprobante (el alta ya no pide letra).
    // Convención de ids igual a la eliminada tabla letras_comprobante.
    var mapaLetras = { A: 1, B: 2, C: 3, E: 4, X: 5 };
    function actualizarLetraDesdeTipo() {
      var letra = ($('#id_tipo_comprobante option:selected').data('letra') || '').toString().toUpperCase();
      if (mapaLetras[letra] !== undefined) {
        $('#id_letra_comprobante').val(mapaLetras[letra]);
      } else {
        $('#id_letra_comprobante').val('');
      }
      actualizarGranTotal();
    }

    function calcularRetencion() {
      var base = parseFloat($('#ret_base').val()) || 0;
      var porc = parseFloat($('#ret_porcentaje').val()) || 0;
      var signo = parseInt($('#ret_regimen option:selected').data('signo')) || 1;
      var monto = base * porc / 100;
      if (signo === 2) { monto = -monto; }
      $('#ret_monto').val(monto.toFixed(2));
    }

    function calcularBaseDesdeMonto() {
      var monto = parseFloat($('#ret_monto').val()) || 0;
      var porc = parseFloat($('#ret_porcentaje').val()) || 0;
      if (porc > 0) {
        var base = Math.abs(monto) / (porc / 100);
        $('#ret_base').val(base.toFixed(2));
      }
    }

    function editarDetalle(index) {
      if (editIndex >= 0 && editIndex !== index) {
        idsImputacionDeDetalle(detalles[editIndex]).forEach(function(id) {
          $('#det_imputaciones option[value="' + id + '"]').remove();
        });
      }
      editIndex = index;
      var item = detalles[index];

      // Limpiar select2 para evitar duplicados al editar varias veces
      $('#det_imputaciones').val(null).trigger('change');

      // Eliminar opciones previamente agregadas para este ítem antes de re-agregarlas
      var idsToAdd = idsImputacionDeDetalle(item);
      idsToAdd.forEach(function(id) {
        $('#det_imputaciones option[value="' + id + '"]').remove();
      });

      // Add back current item's options to select2 (ya son parte de la factura: habilitadas)
      agregarOpcionesImputaciones(idsToAdd, true);

      $('#det_concepto').val(item.id_concepto).trigger('change');
      $('#det_descripcion').val(item.descripcion || '');
      $('#det_descuento').val(item.porcentaje_descuento || 0);

      // Select imputaciones in select2 y reconstruir sub-líneas con montos guardados
      $('#det_imputaciones').val(idsToAdd).trigger('change');

      if (item.imputaciones_data && item.imputaciones_data.length > 0) {
        // Reconstruir directo desde lo guardado para no perder cantidad/precio
        // aunque data-all-options aún no tenga el CA (ajax en curso).
        var itemsGuardados = item.imputaciones_data.map(function (imp) {
          return {
            id_cd: imp.id_cd,
            concepto_text: imp.concepto_text,
            nro_oc: imp.nro_oc || '',
            restante: (imp.restante != null) ? imp.restante : 1,
            oc_precio: (imp.oc_precio != null) ? imp.oc_precio : (imp.precio || 0)
          };
        });
        buildSubLines(itemsGuardados, item.imputaciones_data);
      }

      $('#btnAgregarDetalle').text('Modificar Ítem');
      $('#btnCancelarDetalle').show();
    }

    $(document).ready(function() {

      <?php if ($vista === 'venta'): ?>
      function actualizarCamposServicioARCA() {
        var requierePeriodo = $('#concepto_arca').val() !== '1';
        $('#arcaPeriodoServicio').toggle(requierePeriodo);
      }
      $('#concepto_arca').on('change', actualizarCamposServicioARCA);
      actualizarCamposServicioARCA();
      $('#id_tipo_comprobante').on('change', function() { actualizarLetraDesdeTipo(); });
      $('#formFactura').on('submit', function(e) {
        var submitter = e.originalEvent ? e.originalEvent.submitter : null;
        if (!submitter || submitter.name !== 'btn_definitivo' || $('#concepto_arca').val() === '1') {
          return;
        }
        var fechas = [
          $('#fecha_servicio_desde').val(),
          $('#fecha_servicio_hasta').val(),
          $('#fecha_vencimiento_pago').val()
        ];
        if (fechas.some(function(fecha) { return !fecha; })) {
          e.preventDefault();
          alert('Complete el período del servicio y el vencimiento de pago para emitir esta factura.');
        }
      });

      var certVinculados = <?= json_encode(array_values(array_map('intval', (array)($certIds ?? [])))) ?>;
      function cargarImputacionesVenta() {
        var idCliente = $('#id_cuenta_destino').val();
        var select = $('#det_imputaciones');
        if (!select.length) return;
        select.empty();
        if (!idCliente) return;
        $.getJSON('ajax_imputaciones.php?id_cliente=' + encodeURIComponent(idCliente) + (certVinculados.length ? '&incluir=' + certVinculados.join(',') : ''))
          .done(function(data) {
            var allOpts = [];
            var monedaFv = monedaFacturaActual();
            // Si hay CAs vinculados (preseleccionados), mostrar únicamente esos CAs
            var solo = certVinculados.length
              ? (data || []).filter(function(r) { return certVinculados.indexOf(parseInt(r.id, 10)) !== -1; })
              : (data || []);
            var preseleccionados = [];
            $.each(solo, function(i, row) {
              var concepto = row.cert_label;
              var texto = (i + 1) + ' - ' + concepto + ' - CM: ' + row.numero_cm +
                ' Emitido: ' + row.fecha_emision + ' ' +
                (row.moneda || '') + ' ' + parseFloat(row.monto_total).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              var idMonedaCA = (row.id_moneda === undefined || row.id_moneda === null) ? '' : row.id_moneda;
              var otraMoneda = monedaFv && idMonedaCA !== '' && String(idMonedaCA) !== monedaFv;
              if (otraMoneda) { texto += ' (otra moneda)'; }
              var seleccionado = certVinculados.indexOf(parseInt(row.id, 10)) !== -1;
              var optEl = new Option(texto, row.id, seleccionado, seleccionado);
              optEl.disabled = !!otraMoneda;
              select.append(optEl);
              if (seleccionado) { preseleccionados.push(String(row.id)); }
              allOpts.push({ value: row.id, text: concepto, full_text: texto, concepto_text: concepto, restante: 1, oc_precio: parseFloat(row.monto_total) || 0, id_moneda: idMonedaCA, moneda: row.moneda || '' });
            });
            select.attr('data-all-options', JSON.stringify(allOpts));
            if (preseleccionados.length) {
              // Preseleccionar los CAs vinculados; 'change' dispara el handler que arma las sub-líneas
              select.val(preseleccionados).trigger('change');
            } else {
              try { select.trigger('change.select2'); } catch (e) {}
            }
          })
          .fail(function() {
            if (window.console && console.error) { console.error('No se pudieron cargar las imputaciones (ajax_imputaciones).'); }
          });
      }
      $('#id_cuenta_destino').on('change', cargarImputacionesVenta);
      <?php endif; ?>

      // Cotización del dólar: selector de tipo + valor del día anterior (ver assets/js/cotizacion-dolar.js)
      var cotDolar;
      try {
        cotDolar = CotizacionDolar.montar({
          input: '#cotizacion',
          badge: '#estadoDolar',
          info: '#infoCotizacion',
          selectTipo: '#tipo_dolar',
          tipoInicial: 'oficial'
        });
      } catch (e) {
        if (window.console && console.error) { console.error('CotizacionDolar no disponible, sigo sin cotización automática.'); }
        cotDolar = { cargar: function () {}, mostrar: function () {} };
      }

      function aplicarMoneda(texto, esDolar) {
        monedaSimbolo = texto || '$';
        var input = $('#cotizacion');
        var badge = $('#estadoDolar');
        var info = $('#infoCotizacion');

        if (esDolar) {
          $('#cotizacionRow').show();
          cotDolar.mostrar(true);
          input.prop('required', true).prop('readonly', false);
          $('#labelCotizacion').text('Cotización(*)');
          cotDolar.cargar();
        } else if (texto === '') {
          $('#cotizacionRow').hide();
          cotDolar.mostrar(false);
          input.val('').prop('required', false).prop('readonly', false);
          $('#labelCotizacion').text('Cotización');
          badge.text('Esperando moneda...').removeClass('text-danger text-success').addClass('text-secondary');
          info.html('');
        } else {
          $('#cotizacionRow').hide();
          cotDolar.mostrar(false);
          input.val(1).prop('required', false).prop('readonly', true);
          $('#labelCotizacion').text('Cotización');
          badge.text('No aplica (moneda local)').removeClass('text-danger text-success').addClass('text-secondary');
          info.html('<span class="text-muted">Se informa cotización 1.</span>');
        }
      }

      $('#id_moneda').on('change', function() {
        var opcion = $(this).find('option:selected');
        if (!opcion.length) return;
        aplicarMoneda(opcion.text(), (opcion.data('esusd') === true || opcion.data('esusd') === 'true'));
        refrescarDisabledImputaciones();
      });
      <?php if ($vista === 'venta' && !empty($certIds) && !empty($idMoneda)): ?>
      // Moneda bloqueada por certificado: aplicar efecto sin select.
      aplicarMoneda(<?= json_encode($monedaLabel) ?>, <?= esMonedaDolar($monedaLabel) ? 'true' : 'false' ?>);
      <?php endif; ?>

      <?php if (!empty($editMode) && !empty($detallesExistentes)): ?>
      detalles = <?= json_encode($detallesExistentes) ?>;
      $('#detalles_json').val(JSON.stringify(detalles));
      <?php endif; ?>
      <?php if (!empty($editMode) && !empty($retencionesExistentes)): ?>
      retenciones = <?= json_encode($retencionesExistentes) ?>;
      $('#retenciones_json').val(JSON.stringify(retenciones));
      <?php endif; ?>
      <?php if (!empty($editMode)): ?>
      if (detalles.length > 0) renderDetalles();
      if (retenciones.length > 0) renderRetenciones();
      // Pre-fill select fields
      <?php if (!empty($facturaData)): ?>
      $('#id_tipo_comprobante').val('<?= $fv('id_tipo_comprobante') ?>').trigger('change');
      <?php if ($vista === 'venta'): ?>
      var puntoInicial = '<?= htmlspecialchars($pvActual) ?>';
      <?php endif; ?>
      $('#id_moneda').val('<?= $fv('id_moneda') ?>').trigger('change');
      $('#id_empresa').val('<?= $fv('id_empresa') ?>').trigger('change');
      <?php if ($vista === 'compra'): ?>
      $('#id_cuenta_origen').val('<?= $fv('id_cuenta_origen') ?>').trigger('change');
      <?php else: ?>
      $('#id_cuenta_destino').val('<?= $fv('id_cuenta_destino') ?>').trigger('change');
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>

      // Load all options into select2 if empty (compra)
      <?php if ($vista === 'compra'): ?>
      (function() {
        var select = $('#det_imputaciones');
        if (select.find('option').length === 0) {
          var opts = JSON.parse(select.attr('data-all-options') || '[]');
          opts.forEach(function(o) {
            if (!o.done) {
              var opt = new Option(o.text, o.value, false, false);
              select.append(opt);
            }
          });
        }
      })();
      <?php endif; ?>

      <?php if ($vista === 'compra'): ?>
      // Remove options already used by existing detalles
      if (detalles.length > 0) {
        detalles.forEach(function(d) {
          var imps = d.imputaciones_data || [];
          imps.forEach(function(imp) {
            $('#det_imputaciones option[value="' + imp.id_cd + '"]').remove();
          });
        });
      }
      <?php endif; ?>

      // Auto-fill descripcion and show IVA from concepto contable
      $('#det_concepto').on('change', function() {
        var opt = $(this).find(':selected');
        var texto = opt.text();
        if (texto) {
          $('#det_descripcion').val(texto);
        }
        var tasa = opt.data('tasa');
        if (tasa || tasa === 0) {
          $('#iva-info').text('IVA: ' + parseFloat(tasa).toFixed(2) + '%');
        } else {
          $('#iva-info').text('');
        }
      });

      // Select2 change → rebuild sub-lines
      // Shape único para ambas vistas. Ojo: 'restante' no significa lo mismo en las dos —
      // en venta vale 1 (un certificado) y en compra es la cantidad restante de la línea de OC.
      // Igual 'oc_precio': monto_total del CA en venta, precio de la línea en compra.
      $('#det_imputaciones').on('change', function() {
        var selectedIds = $(this).val() || [];
        var opts = JSON.parse($(this).attr('data-all-options') || '[]');
        // Build array of selected items data
        var items = [];
        selectedIds.forEach(function(id) {
          var found = opts.find(function(o) { return o.value == id; });
          if (found) {
            items.push({
              id_cd: found.value,
              concepto_text: found.concepto_text || found.text,
              nro_oc: found.nro_oc || '',
              restante: (found.restante != null) ? found.restante : 0,
              oc_precio: found.oc_precio || 0
            });
          }
        });
        buildSubLines(items, false);
      });

      $('#btnAgregarDetalle').on('click', function() {
        var idConcepto = $('#det_concepto').val();
        var conceptoText = $('#det_concepto option:selected').text();
        var descripcion = $('#det_descripcion').val() || '';
        var porcDescuento = parseFloat($('#det_descuento').val()) || 0;

        if (!idConcepto) {
          alert('Seleccione un concepto contable.');
          return;
        }

        // Collect from sub-lines
        var impsData = [];
        var allImpIds = [];
        var aborted = false;

        $('#imp-sub-lines .imp-sub-row').each(function() {
          var cdId = parseInt($(this).data('cd-id'));
          var cant = parseFloat($(this).find('.imp-sub-cant').val()) || 0;
          var prec = parseFloat($(this).find('.imp-sub-prec').val()) || 0;
          var restante = parseFloat($(this).find('.imp-sub-cant').data('restante')) || 0;
          var conceptoText = $(this).find('.imp-sub-label').text().trim();
          var nroOc = $(this).data('nro-oc') || '';

          if (cant <= 0) return;

          var dup = detalles.some(function(d, idx) {
            if (idx === editIndex) return false;
            var ids = (d.imputaciones_data || []).map(function(x) { return x.id_cd; });
            return ids.indexOf(cdId) >= 0;
          });
          if (dup) {
            alert('La imputacion "' + conceptoText + '" ya fue usada en otro detalle.');
            aborted = true;
            return false;
          }

          <?php if ($vista !== 'venta'): ?>
          if (cant > restante && restante > 0) {
            if (!confirm('La cantidad supera lo pendiente (' + restante.toFixed(2) + '). Desea continuar?')) {
              aborted = true;
              return false;
            }
          }

          <?php else: ?>
          var montoCa = parseFloat($(this).data('monto')) || 0;
          if (montoCa > 0 && (cant * prec) > montoCa) {
            if (!confirm('El monto (' + (cant * prec).toFixed(2) + ') supera el total del CA ($' + montoCa.toFixed(2) + '). Desea continuar?')) {
              aborted = true;
              return false;
            }
          }
          <?php endif; ?>

          impsData.push({ id_cd: cdId, nro_oc: nroOc, concepto_text: conceptoText, cantidad: cant, precio: prec, restante: restante });
          allImpIds.push(cdId);
        });

        if (aborted) return;

        var imputacionesText = [];
        impsData.forEach(function(imp) {
          imputacionesText.push(imp.concepto_text + ' (' + parseFloat(imp.cantidad).toFixed(2) + ' x $' + parseFloat(imp.precio).toFixed(2) + ')');
        });

        var item = {
          id_concepto: idConcepto, concepto_text: conceptoText,
          descripcion: descripcion, porcentaje_descuento: porcDescuento,
          cantidad: impsData.reduce(function(s, x) { return s + x.cantidad; }, 0), precio: 0,
          imputaciones: allImpIds, imputaciones_data: impsData, imputaciones_text: imputacionesText
        };

        if (editIndex >= 0) {
          detalles[editIndex] = item;
          editIndex = -1;
          $('#btnAgregarDetalle').text('Agregar Ítem');
          $('#btnCancelarDetalle').hide();
        } else {
          detalles.push(item);
        }

        // Remove selected options from select2
        allImpIds.forEach(function(cdId) {
          $('#det_imputaciones option[value="' + cdId + '"]').remove();
        });
        $('#det_imputaciones').val(null).trigger('change');

        // Reset form
        $('#det_concepto').val('').trigger('change');
        $('#det_descripcion').val('');
        $('#det_descuento').val(0);
        $('#imp-sub-lines').empty();
        renderDetalles();
      });

      $('#btnCancelarDetalle').on('click', function() {
        var canceledItem = editIndex >= 0 ? detalles[editIndex] : null;
        editIndex = -1;
        $('#btnAgregarDetalle').text('Agregar Ítem');
        $('#btnCancelarDetalle').hide();
        $('#det_concepto').val('').trigger('change');
        $('#det_descripcion').val('');
        $('#det_descuento').val(0);
        $('#det_imputaciones').val(null).trigger('change');
        $('#imp-sub-lines').empty();
        if (canceledItem) {
          // Liberar de nuevo las imputaciones del ítem que se estaba editando.
          agregarOpcionesImputaciones(idsImputacionDeDetalle(canceledItem));
        }
      });

      $('#btnAgregarRetencion').on('click', function() {
        var idRegimen = $('#ret_regimen').val();
        var regimenText = $('#ret_regimen option:selected').text();
        var porc = parseFloat($('#ret_porcentaje').val()) || 0;
        var base = parseFloat($('#ret_base').val());

        if (!idRegimen) {
          alert('Seleccione un régimen.');
          return;
        }
        if (isNaN(base) || base <= 0) {
          alert('Ingrese una base imponible válida.');
          return;
        }

      var monto = parseFloat($('#ret_monto').val()) || (base * porc / 100);
      var signo = parseInt($('#ret_regimen option:selected').data('signo')) || 1;
      if (signo === 2) { monto = -Math.abs(monto); }

      if (editRetencionIndex >= 0) {
        retenciones[editRetencionIndex] = {
          id_regimen: idRegimen,
          regimen_text: regimenText,
          monto: monto,
          base: base,
          porcentaje: porc,
          signo: signo
        };
        editRetencionIndex = -1;
        $('#btnAgregarRetencion').text('Agregar Retención');
        $('#btnCancelarRetencion').hide();
      } else {
        retenciones.push({
          id_regimen: idRegimen,
          regimen_text: regimenText,
          monto: monto,
          base: base,
          porcentaje: porc,
          signo: signo
        });
      }

        // Actualiza el % en la opción para que la próxima selección ya traiga el último valor
        var $opt = $('#ret_regimen option[value="' + idRegimen + '"]');
        if ($opt.length) {
          $opt.data('porcentaje', porc);
          $opt.attr('data-porcentaje', porc);
        }

        $('#ret_regimen').val('').trigger('change');
        $('#ret_base').val('');
        $('#ret_monto').val('');
        $('#ret_porcentaje').val('');

        renderRetenciones();
      });

      $('#btnCancelarRetencion').on('click', function() {
        editRetencionIndex = -1;
        $('#btnAgregarRetencion').text('Agregar Retención');
        $('#btnCancelarRetencion').hide();
        $('#ret_regimen').val('').trigger('change');
        $('#ret_base').val('');
        $('#ret_monto').val('');
        $('#ret_porcentaje').val('');

      });

      $('#ret_regimen').on('change', function() {
        var porc = parseFloat($(this).find('option:selected').data('porcentaje')) || 0;
        $('#ret_porcentaje').val(porc > 0 ? porc : '');
        if (!$('#ret_base').val()) {
          var neto = parseFloat($('#totalDetalle').text().replace(/[^0-9.-]/g, '')) || 0;
          if (neto > 0) $('#ret_base').val(neto.toFixed(2));
        }
        calcularRetencion();
      });

      $('#ret_porcentaje').on('input', function() {
        calcularRetencion();
      });

      $('#ret_base').on('input', function() {
        calcularRetencion();
      });

      $('#ret_monto').on('input', function() {
        calcularBaseDesdeMonto();
      });

      <?php if ($vista === 'venta' || $vista === 'compra'): ?>
      $('#formFactura').on('submit', function(e) {
        if (detalles.length === 0) {
          e.preventDefault();
          alert('Debe agregar al menos un ítem de detalle.');
          return false;
        }
      });
      <?php endif; ?>

      <?php if ($vista === 'venta'): ?>
      $('#fecha_enviada').on('change', function() {
        var desde = $('#fecha_emitida').val();
        var hasta = $(this).val();
        if (desde && hasta && Date.parse(hasta) < Date.parse(desde)) {
          alert('La fecha enviada debe ser mayor o igual a la fecha emitida');
          $(this).val('');
        }
      });

      var clienteInicial = $('#id_cuenta_destino').val();
      if (clienteInicial && typeof cargarImputacionesVenta === 'function') {
        cargarImputacionesVenta();
      } else if (clienteInicial) {
        $('#id_cuenta_destino').trigger('change');
      }

      var puntosPorNumero = {};

      var tiposCache = null;

      // Hace que las opciones del tipo de comprobante reflejen los tipos
      // asociados al punto de venta: sin punto elegido no se ve ninguna.
      function filtrarTiposPorPunto() {
        var $tc = $('#id_tipo_comprobante');
        var puntoVal = $('#punto_venta').val();
        var tipos = puntoVal && puntosPorNumero[puntoVal] ? puntosPorNumero[puntoVal].tipos : null;
        var tiposStr = Array.isArray(tipos) ? tipos.map(function (t) { return String(t).trim(); }) : [];

        // Cache de las opciones generadas por PHP (se reusan en cada repoblado).
        if (!tiposCache) {
          tiposCache = [];
          $tc.find('option').each(function () {
            var codigo = $(this).data('codigo');
            if (codigo === undefined || codigo === '' || codigo === null) return;
            tiposCache.push($(this).detach());
          });
        }

        var nodos = [];
        if (puntoVal && tiposStr.length) {
          tiposCache.forEach(function ($op) {
            if (tiposStr.indexOf(String($op.data('codigo'))) !== -1) {
              nodos.push($op);
            }
          });
        }

        var valorPrevio = $tc.val();
        $tc.empty();
        $tc.append(new Option('Seleccione...', ''));
        nodos.forEach(function ($op) { $tc.append($op); });

        // Si el tipo previamente seleccionado ya no está entre los
        // habilitados para este punto, se limpia la selección.
        if (valorPrevio) {
          var sigue = nodos.some(function ($op) { return String($op.val()) === String(valorPrevio); });
          if (!sigue) {
            $tc.val('');
          }
        }

        // Aviso cuando el punto elegido no tiene tipos asociados.
        var $hint = $('#tipoHabilitadosHint');
        if ($hint.length) {
          if (puntoVal && !tiposStr.length) {
            $hint.text('El punto de venta elegido no tiene tipos de comprobante asociados.').removeClass('d-none');
          } else {
            $hint.addClass('d-none');
          }
        }

        // Refrescar Select2: repuebla las opciones y el widget se reinicia.
        $tc.trigger('change');
        try {
          if ($tc.hasClass('select2-hidden-accessible')) {
            $tc.select2('destroy');
          }
          $tc.select2();
        } catch (e) {
          if (window.console && console.error) { console.error(e); }
        }
        if (typeof actualizarLetraDesdeTipo === 'function') { actualizarLetraDesdeTipo(); }
      }

      function cargarPuntosVentaEmpresa() {
        var idEmpresa = $('#id_empresa').val();
        var select = document.getElementById('punto_venta');
        if (!select) return;
        puntosPorNumero = {};
        select.replaceChildren(new Option('Seleccione la empresa primero...', ''));
        $('#id_tipo_comprobante').val('').trigger('change');
        if (!idEmpresa) {
          filtrarTiposPorPunto();
          return;
        }
        fetch('obtenerPuntosVentaEmpresa.php?id_empresa=' + encodeURIComponent(idEmpresa))
          .then(function(response) {
            return response.json().then(function(data) {
              if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudieron cargar los puntos de venta.');
              return data;
            });
          })
          .then(function(data) {
            select.replaceChildren(new Option('Seleccione...', ''));
            data.puntos_venta.forEach(function(point) {
              puntosPorNumero[String(point.numero)] = point;
              var etiqueta = String(point.numero).padStart(4, '0')
                + (point.emision_tipo ? ' — ' + point.emision_tipo : '');
              select.appendChild(new Option(etiqueta, String(point.numero)));
            });
            if (typeof puntoInicial !== 'undefined' && puntoInicial && puntosPorNumero[puntoInicial]) {
              select.value = puntoInicial;
              try { $(select).trigger('change'); } catch (e) {}
            }
            filtrarTiposPorPunto();
          })
          .catch(function(error) {
            if (window.console && console.error) { console.error(error); }
            filtrarTiposPorPunto();
          });
      }
      $('#id_empresa').on('change', cargarPuntosVentaEmpresa);
      $('#punto_venta').on('change', filtrarTiposPorPunto);
      cargarPuntosVentaEmpresa();

      // Moneda precargada desde el certificado: dispara los efectos de símbolo, tipo de dólar y cotización
      <?php if ($vista === 'venta' && empty($editMode) && !empty($idMoneda)): ?>
      $('#id_moneda').val(<?= (int)$idMoneda ?>).trigger('change');
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($vista === 'compra'): ?>
      var ocTotal = parseFloat($('#ocTotalData').val()) || 0;
      if (detalles.length === 0 && ocTotal > 0) {
        $('#topeMaximo').text('(*)Tope Máximo Recomendado: $ ' + ocTotal.toFixed(2));
      }

      $("#fecha_recibida").change(function() {
        var startDate = document.getElementById("fecha_emitida").value;
        var endDate = document.getElementById("fecha_recibida").value;
        if ((Date.parse(startDate) > Date.parse(endDate))) {
          alert("La fecha de fin debe ser mayor a la fecha de inicio");
          document.getElementById("fecha_recibida").value = "";
        }
      });

      <?php if ($ocPreseleccionada): ?>
      <?php if ($monedaEsDolar): ?>
      // La OC viene con la moneda fijada: se muestra el selector de tipo y se carga el día anterior
      $('#cotizacion').prop('required', true);
      $('#labelCotizacion').text('Cotización(*)');
      cotDolar.mostrar(true);
      cotDolar.cargar();
      <?php else: ?>
      $('#cotizacion').val(1).prop('required', false).prop('readonly', true);
      $('#labelCotizacion').text('Cotización');
      $('#estadoDolar').text('No aplica (moneda local)').removeClass('text-danger text-success').addClass('text-secondary');
      $('#infoCotizacion').html('<span class="text-muted">Se informa cotización 1.</span>');
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>

      $('#imp-sub-lines').on('input', '.imp-sub-cant, .imp-sub-prec', function() {
        actualizarSubTotalesImputacion();
      });

      <?php if ($vista === 'compra'): ?>
      $('#id_tipo_comprobante').on('change', function() { actualizarLetraDesdeTipo(); });
      actualizarLetraDesdeTipo();
      // Relleno de ceros al salir de punto/número (como al inicio).
      $('#punto_venta').on('blur', function() {
        var v = $(this).val().replace(/\D/g, '');
        if (v !== '') { $(this).val(v.padStart(4, '0')); }
      });
      $('#nro_comprobante').on('blur', function() {
        var v = $(this).val().replace(/\D/g, '');
        if (v !== '') { $(this).val(v.padStart(8, '0')); }
      });
      <?php endif; ?>

    });
  </script>

  <?php if ($vista === 'compra'): ?>
  <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.5.4"></script>
  <?php endif; ?>

</body>
</html>
