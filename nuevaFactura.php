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
                                <input type="hidden" name="id_empresa" value="<?= intval($proyectoDatos['id_empresa']) ?>">
                              <?php else: ?>
                                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresa ?>">
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
                              <input type="hidden" name="id_cuenta_destino" value="<?= intval($proyectoDatos['id_cliente']) ?>">
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

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Tipo Comprobante(*)</label>
                          <div class="col-sm-8">
                            <select name="id_tipo_comprobante" id="id_tipo_comprobante" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, tipo FROM tipos_comprobante WHERE 1");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>" . htmlspecialchars($f['tipo']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Letra(*)</label>
                          <div class="col-sm-8">
                            <select name="id_letra_comprobante" id="id_letra_comprobante" class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, letra FROM letras_comprobante WHERE 1");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>" . htmlspecialchars($f['letra']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Número(*)</label>
                          <div class="col-sm-8">
                            <div class="input-group">
                              <?php
                              $numeroCompleto = $fv('numero');
                              $pv = '';
                              $nc = '';
                              if (!empty($numeroCompleto) && str_contains($numeroCompleto, '-')) {
                                list($pv, $nc) = explode('-', $numeroCompleto, 2);
                              }
                              ?>
                              <input name="punto_venta" type="text" maxlength="4"
                                class="form-control" placeholder="0001" required
                                style="width:80px;flex:none;" value="<?= htmlspecialchars($pv) ?>"
                                oninput="this.value=this.value.replace(/\D/g,'')">
                              <div class="input-group-append">
                                <span class="input-group-text">-</span>
                              </div>
                              <input name="nro_comprobante" type="text" maxlength="8"
                                class="form-control" placeholder="00000001" required
                                value="<?= htmlspecialchars($nc) ?>"
                                oninput="this.value=this.value.replace(/\D/g,'')">
                            </div>
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
                            <?php if ($vista === 'compra' && $ocPreseleccionada): ?>
                              <p class="form-control-plaintext"><?= htmlspecialchars($monedaLabel) ?></p>
                              <input type="hidden" name="id_moneda" value="<?= (int)$idMoneda ?>">
                            <?php else: ?>
                            <select name="id_moneda" id="id_moneda"
                              class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, moneda FROM monedas WHERE 1");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $esDolar = (stripos($f['moneda'], 'dolar') !== false ||
                                  stripos($f['moneda'], 'dólar') !== false ||
                                  stripos($f['moneda'], 'usd')   !== false ||
                                  stripos($f['moneda'], 'u$d')   !== false ||
                                  stripos($f['moneda'], 'u$s')   !== false) ? 'true' : 'false';
                                if ($vista === 'venta') {
                                  echo "<option value='{$f['id']}' data-esusd='$esDolar'>" . htmlspecialchars($f['moneda']) . "</option>";
                                } else {
                                  $sel = ($idMoneda != 0 && $f['id'] == $idMoneda) ? ' selected' : '';
                                  echo "<option value='{$f['id']}' data-esusd='$esDolar'$sel>" . htmlspecialchars($f['moneda']) . "</option>";
                                }
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php endif; ?>
                          </div>
                        </div>

                        <?php if ($vista === 'venta'): ?>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Cotización(*)</label>
                          <div class="col-sm-8">
                            <div class="input-group">
                              <input name="cotizacion" id="cotizacion" type="number" step="0.01"
                                class="form-control" required placeholder="Seleccione una moneda...">
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
                          <label class="col-sm-4 col-form-label">Cotización(*)</label>
                          <div class="col-sm-8">
                            <div class="input-group">
                              <input name="cotizacion" id="cotizacion" type="number" step="0.01"
                                class="form-control" required placeholder="Seleccione una moneda..." value="<?= $fv('cotizacion') ?>">
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
                        "SELECT ca.id, cm.revision,
                                  DATE_FORMAT(ca.fecha_emision,'%d/%m/%Y') AS fecha_emision,
                                  ca.monto_total, ca.aprobado_cliente
                           FROM certificados_avances_cabecera ca
                           INNER JOIN certificados_maestros cm ON cm.id = ca.id_certificado_maestro
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
                                        <strong>Cert. #<?= htmlspecialchars($cr['id']) ?></strong>
                                        <span class="badge <?= $cr['aprobado_cliente'] ? 'badge-success' : 'badge-secondary' ?> ml-1">
                                          <?= $cr['aprobado_cliente'] ? 'Aprobado' : 'Pendiente' ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">Rev. <?= htmlspecialchars($cr['revision']) ?> &nbsp;|&nbsp; <?= htmlspecialchars($cr['fecha_emision']) ?></small>
                                      </div>
                                      <span class="font-weight-bold">$ <?= number_format($cr['monto_total'], 2, ',', '.') ?></span>
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
                      <div class="col-md-3 mb-2">
                        <label class="col-form-label">Descripción</label>
                        <textarea id="det_descripcion" class="form-control" rows="1" placeholder="Texto a mostrar impreso en la factura"></textarea>
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
                              return [
                                'value' => $imp['id'],
                                'text' => "OC #{$nro} - {$imp['concepto']} (queda " . number_format($rest, 2) . " de " . number_format($imp['oc_cantidad'], 2) . ")",
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
                      <div class="col-md-3 mb-2">
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
                            <th>Editar</th>
                            <th>Quitar</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                          <tr>
                            <th colspan="5" class="text-right">Total:</th>
                            <th id="totalRetenciones">$ 0.00</th>
                            <th></th>
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
                            <span class="input-group-text" id="ret_porcentaje_label">%</span>
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
                          <tr><td class="text-right">Retenciones:</td><td class="text-right" id="granRetenciones">$ 0.00</td></tr>
                          <tr class="font-weight-bold" style="border-top: 2px solid #000;"><td class="text-right">Total:</td><td class="text-right" id="granTotal">$ 0.00</td></tr>
                        </table>
                      </div>
                    </div>
                  </div>
                  <div class="card-footer">
                    <div class="col-sm-9 offset-sm-3">
                        <button type="submit" class="btn btn-primary" name="btn_definitivo" value="1">
                          Guardar Definitivo
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
          var porcDesc = parseFloat(item.porc_descuento || 0);
          var sub = totalSubImp * (1 - porcDesc / 100);
          total += sub;

          var imputacionesHtml = '';
          if (impsData.length > 0) {
            imputacionesHtml = '<table class="table table-sm table-borderless mb-0" style="font-size:12px;background:none;"><thead><th style="padding: 0 2px;">Concepto</th><th style="text-align:right; padding: 0 2px;">Cant.</th><th style="text-align:right; padding: 0 2px;">Precio</th><th style="text-align:right; padding: 0 2px;">Subtotal</th></thead>';
            impsData.forEach(function(imp) {
              var subImp = parseFloat(imp.cantidad||0) * parseFloat(imp.precio||0);
              imputacionesHtml += '<tr>';
              imputacionesHtml += '<td style="padding:2px 4px;">OC #' + (imp.nro_oc || '') + ' - ' + htmlEsc(imp.concepto_text) + '</td>';
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
            '<td>' + (item.descripcion ? htmlEsc(item.descripcion) : '') + '</td>' +
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
            '<td><a href="javascript:void(0)" onclick="editarRetencion(' + i + ')"><i data-feather="edit" style="color:#000;"></i></a></td>' +
            '<td><a href="javascript:void(0)" onclick="quitarRetencion(' + i + ')"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a></td>' +
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
      if (item.imputaciones_data) {
        var opts = JSON.parse($('#det_imputaciones').attr('data-all-options') || '[]');
        item.imputaciones_data.forEach(function(imp) {
          var found = opts.find(function(o) { return o.value == imp.id_cd; });
          if (found && $('#det_imputaciones option[value="' + found.value + '"]').length === 0) {
            var opt = new Option(found.text, found.value, false, false);
            $('#det_imputaciones').append(opt);
          }
        });
        $('#det_imputaciones').trigger('change');
      } else if (item.imputaciones && item.imputaciones.length) {
        var opts2 = JSON.parse($('#det_imputaciones').attr('data-all-options') || '[]');
        item.imputaciones.forEach(function(idv) {
          var idCd = typeof idv === 'object' ? (idv.id_cd || idv) : idv;
          var found = opts2.find(function(o) { return o.value == idCd; });
          if (found && $('#det_imputaciones option[value="' + found.value + '"]').length === 0) {
            var opt = new Option(found.text, found.value, false, false);
            $('#det_imputaciones').append(opt);
          }
        });
        $('#det_imputaciones').trigger('change');
      }
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
          '<div class="row imp-sub-row align-items-center" data-cd-id="' + item.id_cd + '" data-nro-oc="' + (item.nro_oc || '') + '" style="margin:0 0 6px 0; padding:6px 0; border-bottom:1px solid #eee;">' +
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
        $('#ret_porcentaje_label').text('-%');
        
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
      $('#btnAgregarRetencion').text('Modificar Retención');
      $('#btnCancelarRetencion').show();
    }

    function actualizarGranTotal() {
      var subTotal = parseFloat($('#totalDetalle').text().replace(/[^0-9.-]/g, '')) || 0;
      var retTotal = parseFloat($('#totalRetenciones').text().replace(/[^0-9.-]/g, '')) || 0;
      $('#granSubtotal').text(monedaSimbolo + ' ' + subTotal.toFixed(2));
      $('#granRetenciones').text(monedaSimbolo + ' ' + retTotal.toFixed(2));
      $('#granTotal').text(monedaSimbolo + ' ' + (subTotal + retTotal).toFixed(2));
    }

    function calcularRetencion() {
      var base = parseFloat($('#ret_base').val()) || 0;
      var porc = parseFloat($('#ret_regimen option:selected').data('porcentaje')) || 0;
      var signo = parseInt($('#ret_regimen option:selected').data('signo')) || 1;
      var monto = base * porc / 100;
      if (signo === 2) { monto = -monto; }
      $('#ret_monto').val(monto.toFixed(2));
    }

    function calcularBaseDesdeMonto() {
      var monto = parseFloat($('#ret_monto').val()) || 0;
      var porc = parseFloat($('#ret_regimen option:selected').data('porcentaje')) || 0;
      if (porc > 0) {
        var base = Math.abs(monto) / (porc / 100);
        $('#ret_base').val(base.toFixed(2));
      }
    }

    function editarDetalle(index) {
      if (editIndex >= 0 && editIndex !== index) {
        var prevItem = detalles[editIndex];
        var prevIds = [];
        if (prevItem.imputaciones_data) {
          prevItem.imputaciones_data.forEach(function(imp) { prevIds.push(imp.id_cd); });
        } else if (prevItem.imputaciones && prevItem.imputaciones.length) {
          prevItem.imputaciones.forEach(function(idv) {
            prevIds.push(typeof idv === 'object' ? (idv.id_cd || idv) : idv);
          });
        }
        prevIds.forEach(function(id) {
          $('#det_imputaciones option[value="' + id + '"]').remove();
        });
      }
      editIndex = index;
      var item = detalles[index];

      // Limpiar select2 para evitar duplicados al editar varias veces
      $('#det_imputaciones').val(null).trigger('change');

      // Eliminar opciones previamente agregadas para este ítem antes de re-agregarlas
      var idsToAdd = [];
      if (item.imputaciones_data) {
        item.imputaciones_data.forEach(function(imp) { idsToAdd.push(imp.id_cd); });
      } else if (item.imputaciones && item.imputaciones.length) {
        item.imputaciones.forEach(function(idv) {
          idsToAdd.push(typeof idv === 'object' ? (idv.id_cd || idv) : idv);
        });
      }
      idsToAdd.forEach(function(id) {
        $('#det_imputaciones option[value="' + id + '"]').remove();
      });

      // Add back current item's options to select2
      if (item.imputaciones_data) {
        var opts = JSON.parse($('#det_imputaciones').attr('data-all-options') || '[]');
        item.imputaciones_data.forEach(function(imp) {
          var found = opts.find(function(o) { return o.value == imp.id_cd; });
          if (found) {
            var opt = new Option(found.text, found.value, false, false);
            $('#det_imputaciones').append(opt);
          }
        });
      } else if (item.imputaciones && item.imputaciones.length) {
        var opts2 = JSON.parse($('#det_imputaciones').attr('data-all-options') || '[]');
        item.imputaciones.forEach(function(idv) {
          var idCd = typeof idv === 'object' ? (idv.id_cd || idv) : idv;
          var found = opts2.find(function(o) { return o.value == idCd; });
          if (found) {
            var opt = new Option(found.text, found.value, false, false);
            $('#det_imputaciones').append(opt);
          }
        });
      }

      $('#det_concepto').val(item.id_concepto).trigger('change');
      $('#det_descripcion').val(item.descripcion || '');
      $('#det_descuento').val(item.porc_descuento || 0);

      // Select imputaciones in select2
      var selIds = [];
      if (item.imputaciones_data) {
        item.imputaciones_data.forEach(function(imp) { selIds.push(imp.id_cd); });
      } else if (item.imputaciones) {
        item.imputaciones.forEach(function(x) {
          selIds.push(typeof x === 'object' ? (x.id_cd || x) : x);
        });
      }
      $('#det_imputaciones').val(selIds).trigger('change');

      if ($('#imp-sub-lines .imp-sub-row').length > 0) {
        if (item.imputaciones_data) {
          item.imputaciones_data.forEach(function(imp) {
            var row = $('#imp-sub-lines .imp-sub-row[data-cd-id="' + imp.id_cd + '"]');
            if (row.length) {
              row.find('.imp-sub-cant').val(imp.cantidad);
              row.find('.imp-sub-prec').val(imp.precio);
            }
          });
          actualizarSubTotalesImputacion();
        }
      } else if (item.imputaciones_data && item.imputaciones_data.length > 0) {
        buildSubLines(item.imputaciones_data, item.imputaciones_data);
      }

      $('#btnAgregarDetalle').text('Modificar Ítem');
      $('#btnCancelarDetalle').show();
    }

    $(document).ready(function() {

      <?php if ($vista === 'venta'): ?>
      $('#id_cuenta_destino').on('change', function() {
        var idCliente = $(this).val();
        var select = $('#det_imputaciones');
        select.empty();
        if (!idCliente) return;
        $.getJSON('ajax_imputaciones.php?id_cliente=' + idCliente, function(data) {
          $.each(data, function(i, row) {
            var texto = (i + 1) + ' - CM: ' + row.numero_cm +
              ' Emitido: ' + row.fecha_emision +
              ' / (' + row.fecha_inicio + '-' + row.fecha_fin + ') ' +
              row.moneda + parseFloat(row.monto_total).toFixed(2);
            select.append('<option value="' + row.id + '">' + texto + '</option>');
          });
        });
      });
      <?php endif; ?>

      $('#id_moneda').on('change', function() {
        var opcion = $(this).find('option:selected');
        monedaSimbolo = opcion.text() || '$';
        var esDolar = (opcion.data('esusd') === true || opcion.data('esusd') === 'true');
        var input = $('#cotizacion');
        var badge = $('#estadoDolar');
        var info = $('#infoCotizacion');

        if (esDolar) {
          badge.text('Cargando...').removeClass('text-danger text-success').addClass('text-secondary');
          input.prop('readonly', true);
          fetch('https://dolarapi.com/v1/dolares/oficial', {
              headers: {
                'Accept': 'application/json'
              }
            })
            .then(function(r) {
              if (!r.ok) throw new Error('HTTP ' + r.status);
              return r.json();
            })
            .then(function(d) {
              if (!d.venta) throw new Error('Sin venta');
              input.val(parseFloat(d.venta).toFixed(2));
              badge.html('Dólar Oficial').removeClass('text-secondary text-danger').addClass('text-success');
              var fecha = d.fechaActualizacion ? ' — Act: ' + new Date(d.fechaActualizacion).toLocaleString('es-AR') : '';
              info.html('Compra: <strong>$' + parseFloat(d.compra).toLocaleString('es-AR', {
                  minimumFractionDigits: 2
                }) + '</strong>' +
                ' | Venta: <strong>$' + parseFloat(d.venta).toLocaleString('es-AR', {
                  minimumFractionDigits: 2
                }) + '</strong>' + fecha);
              input.prop('readonly', false);
            })
            .catch(function() {
              badge.text('Error al obtener').removeClass('text-secondary text-success').addClass('text-danger');
              info.html('<span class="text-danger">No se pudo obtener la cotización. Ingrésela manualmente.</span>');
              input.val('').prop('readonly', false);
            });
        } else if (opcion.val() === '') {
          input.val('').prop('readonly', false);
          badge.text('Esperando moneda...').removeClass('text-danger text-success').addClass('text-secondary');
          info.html('');
        } else {
          input.val(1).prop('readonly', false);
          badge.text('Ingreso manual').removeClass('text-danger text-success').addClass('text-secondary');
          info.html('<span class="text-muted"></span>');
        }
      });

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
      $('#id_letra_comprobante').val('<?= $fv('id_letra_comprobante') ?>').trigger('change');
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
              concepto_text: found.text.split(' - ').slice(1).join(' - ').split(' (queda')[0],
              nro_oc: found.nro_oc || '',
              restante: found.restante,
              oc_precio: found.oc_precio
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

          if (cant > restante && restante > 0) {
            if (!confirm('La cantidad supera lo pendiente (' + restante.toFixed(2) + '). Desea continuar?')) {
              aborted = true;
              return false;
            }
          }

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
          descripcion: descripcion, porc_descuento: porcDescuento,
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
          var idsToRemove = [];
          if (canceledItem.imputaciones_data) {
            canceledItem.imputaciones_data.forEach(function(imp) { idsToRemove.push(imp.id_cd); });
          } else if (canceledItem.imputaciones && canceledItem.imputaciones.length) {
            canceledItem.imputaciones.forEach(function(idv) {
              idsToRemove.push(typeof idv === 'object' ? (idv.id_cd || idv) : idv);
            });
          }
          idsToRemove.forEach(function(id) {
            $('#det_imputaciones option[value="' + id + '"]').remove();
          });
        }
      });

      $('#btnAgregarRetencion').on('click', function() {
        var idRegimen = $('#ret_regimen').val();
        var regimenText = $('#ret_regimen option:selected').text();
        var porc = parseFloat($('#ret_regimen option:selected').data('porcentaje')) || 0;
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

        $('#ret_regimen').val('').trigger('change');
        $('#ret_base').val('');
        $('#ret_monto').val('');
        $('#ret_porcentaje_label').text('-%');
        
        renderRetenciones();
      });

      $('#btnCancelarRetencion').on('click', function() {
        editRetencionIndex = -1;
        $('#btnAgregarRetencion').text('Agregar Retención');
        $('#btnCancelarRetencion').hide();
        $('#ret_regimen').val('').trigger('change');
        $('#ret_base').val('');
        $('#ret_monto').val('');
        $('#ret_porcentaje_label').text('-%');
        
      });

      $('#ret_regimen').on('change', function() {
        var porc = parseFloat($(this).find('option:selected').data('porcentaje')) || 0;
        $('#ret_porcentaje_label').text(porc > 0 ? porc.toFixed(2) + '%' : '-%');
        if (!$('#ret_base').val()) {
          var neto = parseFloat($('#totalDetalle').text().replace(/[^0-9.-]/g, '')) || 0;
          if (neto > 0) $('#ret_base').val(neto.toFixed(2));
        }
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
      if (clienteInicial) {
        $('#id_cuenta_destino').trigger('change');
      }
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
      var badge = $('#estadoDolar');
      var input = $('#cotizacion');
      var info = $('#infoCotizacion');
      badge.text('Cargando...').removeClass('text-danger text-success').addClass('text-secondary');
      input.prop('readonly', true);
      fetch('https://dolarapi.com/v1/dolares/oficial', {
          headers: { 'Accept': 'application/json' }
        })
        .then(function(r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        })
        .then(function(d) {
          if (!d.venta) throw new Error('Sin venta');
          input.val(parseFloat(d.venta).toFixed(2));
          badge.html('Dólar Oficial').removeClass('text-secondary text-danger').addClass('text-success');
          var fecha = d.fechaActualizacion ? ' — Act: ' + new Date(d.fechaActualizacion).toLocaleString('es-AR') : '';
          info.html('Compra: <strong>$' + parseFloat(d.compra).toLocaleString('es-AR', {minimumFractionDigits:2}) + '</strong>' +
            ' | Venta: <strong>$' + parseFloat(d.venta).toLocaleString('es-AR', {minimumFractionDigits:2}) + '</strong>' + fecha);
          input.prop('readonly', false);
        })
        .catch(function() {
          badge.text('Error al obtener').removeClass('text-secondary text-success').addClass('text-danger');
          info.html('<span class="text-danger">No se pudo obtener la cotización. Ingrésela manualmente.</span>');
          input.val('').prop('readonly', false);
        });
      <?php else: ?>
      $('#cotizacion').val(1);
      $('#estadoDolar').text('Ingreso manual').removeClass('text-danger text-success').addClass('text-secondary');
      $('#infoCotizacion').html('');
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>

      $('#imp-sub-lines').on('input', '.imp-sub-cant, .imp-sub-prec', function() {
        actualizarSubTotalesImputacion();
      });

    });
  </script>

  <?php if ($vista === 'compra'): ?>
  <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.5.4"></script>
  <?php endif; ?>

</body>
</html>
