<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

if (!empty($_POST) && ($_POST['_accion'] ?? '') === 'guardar_todo') {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->beginTransaction();

  try {
    $sql = "INSERT INTO facturas_venta
                  (descripcion, id_tipo_comprobante, id_letra_comprobante, id_proyecto,
                   numero, id_cuenta_destino, id_empresa, fecha_emitida, fecha_enviada,
                   id_condicion_pago, id_moneda, cotizacion, observaciones, id_usuario, id_estado)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)";
    $q = $pdo->prepare($sql);

    $idCondicionPago = !empty($_POST['id_condicion_pago']) ? intval($_POST['id_condicion_pago']) : null;
    $idProyecto      = !empty($_POST['id_proyecto'])       ? intval($_POST['id_proyecto'])       : null;
    $idEmpresa       = !empty($_POST['id_empresa'])        ? intval($_POST['id_empresa'])        : null;
    $idCuentaDest    = !empty($_POST['id_cuenta_destino']) ? intval($_POST['id_cuenta_destino']) : null;

    $q->execute([
      $_POST['descripcion'],
      intval($_POST['id_tipo_comprobante']),
      intval($_POST['id_letra_comprobante']),
      $idProyecto,
      $_POST['numero'],
      $idCuentaDest,
      $idEmpresa,
      $_POST['fecha_emitida'],
      $_POST['fecha_enviada'],
      $idCondicionPago,
      intval($_POST['id_moneda']),
      $_POST['cotizacion'],
      $_POST['observaciones'],
      $_SESSION['user']['id']
    ]);
    $idNueva = $pdo->lastInsertId();

    // Detalles
    $gravado = 0;
    $noGravado = 0;
    $iva = 0;
    $totalFv = 0;
    $detalles = !empty($_POST['detalles_json']) ? json_decode($_POST['detalles_json'], true) : [];

    if (is_array($detalles)) {
      $qDet = $pdo->prepare("INSERT INTO facturas_venta_detalle
                                   (id_factura_venta, id_concepto_contable, cantidad, precio, subtotal)
                                   VALUES (?,?,?,?,?)");
      $qImp = $pdo->prepare("INSERT INTO facturas_venta_detalle_x_certificados_avance
                                   (id_factura_venta_detalle, id_certificado_avance) VALUES (?,?)");

      foreach ($detalles as $det) {
        $cant     = floatval($det['cantidad']);
        $precio   = floatval($det['precio']);
        $subtotal = $cant * $precio;

        $qDet->execute([$idNueva, intval($det['id_concepto']), $cant, $precio, $subtotal]);
        $idDetalle = $pdo->lastInsertId();

        if (!empty($det['imputaciones']) && is_array($det['imputaciones'])) {
          foreach ($det['imputaciones'] as $idImp) {
            $qImp->execute([$idDetalle, intval($idImp)]);
          }
        }

        $totalFv   += $subtotal;
        $parNoGrav  = $precio * $cant;
        $noGravado += $parNoGrav;
        $iva       += $parNoGrav * 0.21;
        $gravado   += $parNoGrav + ($parNoGrav * 0.21);
      }
    }

    // Retenciones
    $totalOtros = 0;
    $retenciones = !empty($_POST['retenciones_json']) ? json_decode($_POST['retenciones_json'], true) : [];

    if (is_array($retenciones)) {
      $qRet = $pdo->prepare("INSERT INTO facturas_venta_retenciones
                                   (id_factura_venta, id_regimen_facturacion, monto) VALUES (?,?,?)");
      foreach ($retenciones as $ret) {
        $monto = floatval($ret['monto']);
        $qRet->execute([$idNueva, intval($ret['id_regimen']), $monto]);
        $totalOtros += $monto;
      }
    }

    // Totales
    $qu = $pdo->prepare("UPDATE facturas_venta
                             SET subtotal_gravado=?, subtotal_no_gravado=?, iva=?, otros=?, total=?
                             WHERE id=?");
    $qu->execute([$gravado, $noGravado, $iva, $totalOtros, $totalFv + $totalOtros, $idNueva]);

    // Regímenes
    if (!empty($_POST['regimenes'])) {
      foreach ($_POST['regimenes'] as $idRegimen) {
        $qp = $pdo->prepare("SELECT porcentaje FROM regimenes_facturacion WHERE id = ?");
        $qp->execute([$idRegimen]);
        $reg = $qp->fetch(PDO::FETCH_ASSOC);
        $porcentaje = $reg ? $reg['porcentaje'] : 0;
        $qi = $pdo->prepare("INSERT INTO facturas_venta_otros (id_factura_venta, id_regimen, porcentaje) VALUES (?,?,?)");
        $qi->execute([$idNueva, $idRegimen, $porcentaje]);
      }
    }

    // Certificados
    if (!empty($_POST['certificados'])) {
      foreach ($_POST['certificados'] as $idCert) {
        $idCert = intval($idCert);
        if ($idCert > 0) {
          $qc = $pdo->prepare("UPDATE certificados_avances_detalle SET id_comprobante = ? WHERE id_certificado_avance = ?");
          $qc->execute([$idNueva, $idCert]);
        }
      }
    }

    $ql = $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
                              VALUES (now(), ?, ?, 'Facturas de Venta', '')");
    $ql->execute([$_SESSION['user']['id'], "Nueva Factura de Venta ID #$idNueva"]);

    $pdo->commit();
    Database::disconnect();
    header("Location: listarFacturasVenta.php");
    exit;
  } catch (Exception $e) {
    $pdo->rollBack();
    Database::disconnect();
    $errorMsg = "Error al guardar: " . $e->getMessage();
  }
}

// Datos pre-cargados desde proyecto
$proyectoDatos = [];
if (!empty($_GET['id_proyecto'])) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $q = $pdo->prepare("SELECT p.id, p.nombre, p.id_cliente, p.solicitante,
                               s.id_empresa,
                               COALESCE(cu.nombre, p.solicitante) AS nombre_cliente
                        FROM proyectos p
                        INNER JOIN sitios s ON s.id = p.id_sitio
                        LEFT JOIN cuentas cu ON cu.id = p.id_cliente
                        WHERE p.id = ?");
  $q->execute([$_GET['id_proyecto']]);
  $proyectoDatos = $q->fetch(PDO::FETCH_ASSOC) ?: [];
  Database::disconnect();
}
$preseleccionado = !empty($proyectoDatos);

$certIds = [];
if (!empty($_GET['certificados'])) {
  $certIds = array_filter(array_map('intval', (array)$_GET['certificados']));
}
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
        <?php $ubicacion = "Nueva Factura de Venta";
        include_once("head_page.php"); ?>
        <div class="container-fluid">

          <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
          <?php endif; ?>

          <form id="formFactura" class="form theme-form" role="form" method="post"
            action="nuevaFacturaVenta.php<?= !empty($_GET['id_proyecto']) ? '?id_proyecto=' . intval($_GET['id_proyecto']) : '' ?>">
            <input type="hidden" name="_accion" value="guardar_todo">
            <input type="hidden" name="detalles_json" id="detalles_json" value="[]">
            <input type="hidden" name="retenciones_json" id="retenciones_json" value="[]">

            <?php foreach ($certIds as $cid): ?>
              <input type="hidden" name="certificados[]" value="<?= $cid ?>">
            <?php endforeach; ?>

            <div class="row">
              <div class="col-sm-12">

                <!--  DATOS DE LA FACTURA -->
                <div class="card">
                  <div class="card-header">
                    <h5>Datos de la Factura</h5>
                  </div>
                  <div class="card-body">
                    <div class="row">

                      <!-- COLUMNA IZQUIERDA -->
                      <div class="col-md-6">

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Empresa(*)</label>
                          <div class="col-sm-8">
                            <select name="id_empresa" id="id_empresa"
                              class="js-example-basic-single col-sm-12" required
                              <?= $preseleccionado ? 'disabled' : '' ?>>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, empresa FROM empresas WHERE 1");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $sel = (!empty($proyectoDatos['id_empresa']) && $proyectoDatos['id_empresa'] == $f['id']) ? ' selected' : '';
                                echo "<option value='{$f['id']}'$sel>" . htmlspecialchars($f['empresa']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php if ($preseleccionado): ?>
                              <input type="hidden" name="id_empresa" value="<?= intval($proyectoDatos['id_empresa']) ?>">
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Cliente(*)</label>
                          <div class="col-sm-8">
                            <select name="id_cuenta_destino" id="id_cuenta_destino"
                              class="js-example-basic-single col-sm-12" required
                              <?= $preseleccionado ? 'disabled' : '' ?>>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta IN (1) AND activo = 1 AND anulado = 0");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $sel = (!empty($proyectoDatos['id_cliente']) && $proyectoDatos['id_cliente'] == $f['id']) ? ' selected' : '';
                                echo "<option value='{$f['id']}'$sel>" . htmlspecialchars($f['nombre']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php if ($preseleccionado): ?>
                              <input type="hidden" name="id_cuenta_destino" value="<?= intval($proyectoDatos['id_cliente']) ?>">
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Proyecto(*)</label>
                          <div class="col-sm-8">
                            <?php $idProyectoGet = !empty($_GET['id_proyecto']) ? intval($_GET['id_proyecto']) : 0; ?>
                            <select name="id_proyecto" id="id_proyecto"
                              class="js-example-basic-single col-sm-12" required
                              <?= $preseleccionado ? 'disabled' : '' ?>>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre
                                                FROM proyectos p
                                                INNER JOIN sitios s ON s.id = p.id_sitio
                                                WHERE p.anulado = 0 ORDER BY p.nro DESC");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $sel = ($idProyectoGet && $idProyectoGet == $f['id']) ? ' selected' : '';
                                $label = $f['nro_sitio'] . '-' . $f['nro_subsitio'] . '-' . $f['nro'] . ': ' . htmlspecialchars($f['nombre']);
                                echo "<option value='{$f['id']}'$sel>$label</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                            <?php if ($preseleccionado && $idProyectoGet): ?>
                              <input type="hidden" name="id_proyecto" value="<?= $idProyectoGet ?>">
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Tipo Comprobante(*)</label>
                          <div class="col-sm-8">
                            <select name="id_tipo_comprobante" id="id_tipo_comprobante"
                              class="js-example-basic-single col-sm-12" required>
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
                            <select name="id_letra_comprobante" id="id_letra_comprobante"
                              class="js-example-basic-single col-sm-12" required>
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
                            <input name="numero" id="numero" oninput="applyMask(this)"
                              placeholder="0001-00000001" type="text" maxlength="20"
                              class="form-control" required>
                          </div>
                        </div>

                      </div>

                      <!-- COLUMNA DERECHA -->
                      <div class="col-md-6">

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Descripción(*)</label>
                          <div class="col-sm-8">
                            <textarea name="descripcion" class="form-control" rows="3" required><?= !empty($proyectoDatos['nombre']) ? htmlspecialchars($proyectoDatos['nombre']) : '' ?></textarea>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha Emitida(*)</label>
                          <div class="col-sm-8">
                            <input name="fecha_emitida" id="fecha_emitida" type="date"
                              onfocus="this.showPicker()" class="form-control" required>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha Enviada(*)</label>
                          <div class="col-sm-8">
                            <input name="fecha_enviada" id="fecha_enviada" type="date"
                              onfocus="this.showPicker()" class="form-control" required>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Forma de Pago(*)</label>
                          <div class="col-sm-8">
                            <select name="id_condicion_pago" id="id_condicion_pago"
                              class="js-example-basic-single col-sm-12" required>
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $q = $pdo->prepare("SELECT id, forma_pago FROM formas_pago WHERE 1");
                              $q->execute();
                              while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>" . htmlspecialchars($f['forma_pago']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Moneda(*)</label>
                          <div class="col-sm-8">
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
                                echo "<option value='{$f['id']}' data-esusd='$esDolar'>" . htmlspecialchars($f['moneda']) . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                          </div>
                        </div>

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
                                echo "<option value='{$f['id']}'>" . htmlspecialchars($f['regimen']) . " ({$f['porcentaje']}%)</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Observaciones</label>
                          <div class="col-sm-8">
                            <textarea name="observaciones" class="form-control" rows="3"></textarea>
                          </div>
                        </div>

                      </div>
                    </div>

                    <?php
                    if (!empty($certIds)):
                      $pdo = Database::connect();
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $placeholders = implode(',', array_fill(0, count($certIds), '?'));
                      $qCert = $pdo->prepare(
                        "SELECT ca.id, cm.numero, cm.revision,
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
                                        <strong>Cert. #<?= htmlspecialchars($cr['numero']) ?></strong>
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

                <!-- ============================================================
                   CARD 2 — DETALLE DE FACTURA
                   ============================================================ -->
                <div class="card">
                  <div class="card-header">
                    <h5>Detalle de Factura de Venta <span id="countDetalles"></span></h5>
                  </div>
                  <div class="card-body">

                    <div id="contenedorTablaDetalles" style="display:none; background:#fff;">
                      <table class="table table-bordered table-sm" id="tablaDetalles">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>Concepto</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Subtotal</th>
                            <th>Quitar</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                          <tr>
                            <th colspan="4" class="text-right">Total:</th>
                            <th id="totalDetalle">$ 0.00</th>
                            <th></th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>

                    <div class="form-group row">
                      <label class="col-sm-3 col-form-label">Concepto Contable(*)</label>
                      <div class="col-sm-9">
                        <select id="det_concepto" class="js-example-basic-single col-sm-12">
                          <option value="">Seleccione...</option>
                          <?php
                          $pdo = Database::connect();
                          $q = $pdo->prepare("SELECT id, descripcion FROM conceptos_contables WHERE 1");
                          $q->execute();
                          while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$f['id']}'>" . htmlspecialchars($f['descripcion']) . "</option>";
                          }
                          Database::disconnect();
                          ?>
                        </select>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label class="col-sm-3 col-form-label">Imputaciones</label>
                      <div class="col-sm-9">
                        <select id="det_imputaciones" class="js-example-basic-multiple col-sm-12" multiple="multiple">
                        </select>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                      <div class="col-sm-9">
                        <input id="det_cantidad" type="number" step="0.01" class="form-control">
                      </div>
                    </div>

                    <div class="form-group row">
                      <label class="col-sm-3 col-form-label">Precio(*)</label>
                      <div class="col-sm-9">
                        <input id="det_precio" type="number" step="0.01" class="form-control">
                      </div>
                    </div>

                    <div class="form-group row">
                      <div class="col-sm-9 offset-sm-3">
                        <button type="button" class="btn btn-success" id="btnAgregarDetalle">Agregar Ítem</button>
                      </div>
                    </div>

                  </div>
                </div>

                <!-- ============================================================
                   CARD 3 — RETENCIONES
                   ============================================================ -->
                <div class="card">
                  <div class="card-header">
                    <h5>Retenciones de Factura de Venta <span id="countRetenciones"></span></h5>
                  </div>
                  <div class="card-body">

                    <div id="contenedorTablaRetenciones" style="display:none; background:#fff;">
                      <table class="table table-bordered table-sm" id="tablaRetenciones">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>Régimen</th>
                            <th>Monto</th>
                            <th>Quitar</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                          <tr>
                            <th colspan="2" class="text-right">Total:</th>
                            <th id="totalRetenciones">$ 0.00</th>
                            <th></th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>

                    <div class="form-group row">
                      <label class="col-sm-3 col-form-label">Régimen de Facturación(*)</label>
                      <div class="col-sm-9">
                        <select id="ret_regimen" class="js-example-basic-single col-sm-12">
                          <option value="">Seleccione...</option>
                          <?php
                          $pdo = Database::connect();
                          $q = $pdo->prepare("SELECT id, regimen FROM regimenes_facturacion WHERE anulado = 0 ORDER BY regimen");
                          $q->execute();
                          while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$f['id']}'>" . htmlspecialchars($f['regimen']) . "</option>";
                          }
                          Database::disconnect();
                          ?>
                        </select>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label class="col-sm-3 col-form-label">Monto(*)</label>
                      <div class="col-sm-9">
                        <input id="ret_monto" type="number" step="0.01" class="form-control">
                      </div>
                    </div>

                    <div class="form-group row">
                      <div class="col-sm-9 offset-sm-3">
                        <button type="button" class="btn btn-success" id="btnAgregarRetencion">Agregar Retención</button>
                      </div>
                    </div>

                  </div>
                </div>

                <!-- ============================================================
                   CARD 4 — GUARDAR
                   ============================================================ -->
                <div class="card">
                  <div class="card-footer">
                    <div class="col-sm-9 offset-sm-3">
                      <button type="submit" class="btn btn-primary" id="btnGuardarTodo">Guardar Factura</button>
                      <a href="listarFacturasVenta.php" class="btn btn-light">Volver al Listado</a>
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

  <script>
    var detalles = [];
    var retenciones = [];

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
          var sub = item.cantidad * item.precio;
          total += sub;
          tbody.append(
            '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + item.concepto_text + '</td>' +
            '<td>' + parseFloat(item.cantidad).toFixed(2) + '</td>' +
            '<td>$ ' + parseFloat(item.precio).toFixed(2) + '</td>' +
            '<td>$ ' + sub.toFixed(2) + '</td>' +
            '<td><a href="javascript:void(0)" onclick="quitarDetalle(' + i + ')"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a></td>' +
            '</tr>'
          );
        });
        $('#countDetalles').text('(' + detalles.length + ')');
      }

      $('#totalDetalle').text('$ ' + total.toFixed(2));
      $('#detalles_json').val(JSON.stringify(detalles));
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
          tbody.append(
            '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + item.regimen_text + '</td>' +
            '<td>$ ' + parseFloat(item.monto).toFixed(2) + '</td>' +
            '<td><a href="javascript:void(0)" onclick="quitarRetencion(' + i + ')"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a></td>' +
            '</tr>'
          );
        });
        $('#countRetenciones').text('(' + retenciones.length + ')');
      }

      $('#totalRetenciones').text('$ ' + total.toFixed(2));
      $('#retenciones_json').val(JSON.stringify(retenciones));
    }

    function quitarDetalle(index) {
      detalles.splice(index, 1);
      renderDetalles();
    }

    function quitarRetencion(index) {
      retenciones.splice(index, 1);
      renderRetenciones();
    }

    $(document).ready(function() {

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

      $('#btnAgregarDetalle').on('click', function() {
        var idConcepto = $('#det_concepto').val();
        var conceptoText = $('#det_concepto option:selected').text();
        var cantidad = parseFloat($('#det_cantidad').val());
        var precio = parseFloat($('#det_precio').val());

        if (!idConcepto) {
          alert('Seleccione un concepto contable.');
          return;
        }
        if (isNaN(cantidad) || cantidad <= 0) {
          alert('Ingrese una cantidad válida.');
          return;
        }
        if (isNaN(precio) || precio <= 0) {
          alert('Ingrese un precio válido.');
          return;
        }

        var imputaciones = $('#det_imputaciones').val() || [];

        detalles.push({
          id_concepto: idConcepto,
          concepto_text: conceptoText,
          cantidad: cantidad,
          precio: precio,
          imputaciones: imputaciones
        });

        $('#det_concepto').val('').trigger('change');
        $('#det_imputaciones').val(null).trigger('change');
        $('#det_cantidad').val('');
        $('#det_precio').val('');
        renderDetalles();
      });

      $('#btnAgregarRetencion').on('click', function() {
        var idRegimen = $('#ret_regimen').val();
        var regimenText = $('#ret_regimen option:selected').text();
        var monto = parseFloat($('#ret_monto').val());

        if (!idRegimen) {
          alert('Seleccione un régimen.');
          return;
        }
        if (isNaN(monto) || monto <= 0) {
          alert('Ingrese un monto válido.');
          return;
        }

        retenciones.push({
          id_regimen: idRegimen,
          regimen_text: regimenText,
          monto: monto
        });

        $('#ret_regimen').val('').trigger('change');
        $('#ret_monto').val('');
        renderRetenciones();
      });

      $('#formFactura').on('submit', function(e) {
        if (detalles.length === 0) {
          e.preventDefault();
          alert('Debe agregar al menos un ítem de detalle.');
          return false;
        }
      });

      $('#fecha_enviada').on('change', function() {
        var desde = $('#fecha_emitida').val();
        var hasta = $(this).val();
        if (desde && hasta && Date.parse(hasta) < Date.parse(desde)) {
          alert('La fecha enviada debe ser mayor o igual a la fecha emitida');
          $(this).val('');
        }
      });

      $('#id_moneda').on('change', function() {
        var opcion = $(this).find('option:selected');
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
          info.html('<span class="text-muted">Ingrese la cotización manualmente.</span>');
        }
      });

      var clienteInicial = $('#id_cuenta_destino').val();
      if (clienteInicial) {
        $('#id_cuenta_destino').trigger('change');
      }
    });

    function applyMask(input) {
      var v = input.value.replace(/\D/g, '');
      if (v.length > 4) v = v.substring(0, 4) + '-' + v.substring(4, 12);
      input.value = v;
    }
  </script>
</body>

</html>