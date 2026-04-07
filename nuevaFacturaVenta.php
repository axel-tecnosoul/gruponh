<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
require 'database.php';

// Datos pre-cargados desde proyecto (si viene ?id_proyecto=X)
$proyectoDatos = [];
if (!empty($_GET['id_proyecto'])) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Traemos proyecto + sitio (para id_empresa) + cliente del proyecto
    $sql = "SELECT p.id, p.nombre, p.id_cliente, p.solicitante,
                   s.id_empresa,
                   COALESCE(cu.nombre, p.solicitante) AS nombre_cliente
            FROM proyectos p
            INNER JOIN sitios s ON s.id = p.id_sitio
            LEFT JOIN cuentas cu ON cu.id = p.id_cliente
            WHERE p.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_GET['id_proyecto']]);
    $proyectoDatos = $q->fetch(PDO::FETCH_ASSOC) ?: [];
    Database::disconnect();
}

if (!empty($_POST)) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
    $idFactura = $pdo->lastInsertId();

    // Regímenes seleccionados
    if (!empty($_POST['regimenes'])) {
        foreach ($_POST['regimenes'] as $idRegimen) {
            $qp = $pdo->prepare("SELECT porcentaje FROM regimenes_facturacion WHERE id = ?");
            $qp->execute([$idRegimen]);
            $reg = $qp->fetch(PDO::FETCH_ASSOC);
            $porcentaje = $reg ? $reg['porcentaje'] : 0;

            $qi = $pdo->prepare("INSERT INTO facturas_venta_otros (id_factura_venta, id_regimen, porcentaje) VALUES (?,?,?)");
            $qi->execute([$idFactura, $idRegimen, $porcentaje]);
        }
    }

    // Certificados seleccionados → vincular via id_comprobante en certificados_avances_detalle
    if (!empty($_POST['certificados'])) {
        foreach ($_POST['certificados'] as $idCert) {
            $idCert = intval($idCert);
            if ($idCert > 0) {
                $qc = $pdo->prepare(
                    "UPDATE certificados_avances_detalle
                     SET id_comprobante = ?
                     WHERE id_certificado_avance = ?"
                );
                $qc->execute([$idFactura, $idCert]);
            }
        }
    }

    $ql = $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
                          VALUES (now(), ?, 'Nueva Factura de Venta ID #$idFactura', 'Facturas de Venta', '')");
    $ql->execute([$_SESSION['user']['id']]);

    Database::disconnect();
    header("Location: nuevoDetalleFacturaVenta.php?id=" . $idFactura);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include('head_forms.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <style>
    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single {
      height: calc(1.5em + .75rem + 2px) !important;
      padding: .375rem .75rem !important;
      font-size: 1rem !important; line-height: 1.5 !important;
      color: #495057 !important; background-color: #fff !important;
      border: 1px solid #ced4da !important; border-radius: .25rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: calc(1.5em + .75rem + 2px) !important; top: 0 !important; right: 8px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: calc(1.5em + .75rem) !important; padding-left: 0 !important; color: #495057 !important;
    }
  </style>
</head>
<body>
<div class="page-wrapper">
  <?php include('header.php'); ?>
  <div class="page-body-wrapper">
    <?php include('menu.php'); ?>
    <div class="page-body">
      <?php $ubicacion = "Nueva Factura Venta"; include_once("head_page.php"); ?>
      <div class="container-fluid">
        <div class="row">
          <div class="col-sm-12">
            <div class="card">
              <div class="card-header">
                <h5><?= $ubicacion ?></h5>
              </div>
              <form class="form theme-form" role="form" method="post" action="nuevaFacturaVenta.php">
                <div class="card-body">
                  <div class="row">
                    <div class="col">

                      <!-- Empresa -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Empresa(*)</label>
                        <div class="col-sm-9">
                          <select name="id_empresa" id="id_empresa" class="js-example-basic-single col-sm-12" required>
                            <option value="">Seleccione...</option>
                            <?php
                            $pdo = Database::connect();
                            $q   = $pdo->prepare("SELECT id, empresa FROM empresas WHERE 1");
                            $q->execute();
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $sel = (!empty($proyectoDatos['id_empresa']) && $proyectoDatos['id_empresa'] == $f['id']) ? ' selected' : '';
                                echo "<option value='{$f['id']}'$sel>".htmlspecialchars($f['empresa'])."</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                        </div>
                      </div>

                      <!-- Cliente -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Cliente(*)</label>
                        <div class="col-sm-9">
                          <select name="id_cuenta_destino" id="id_cuenta_destino" class="js-example-basic-single col-sm-12" required>
                            <option value="">Seleccione...</option>
                            <?php
                            $pdo = Database::connect();
                            $q   = $pdo->prepare("SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta IN (1) AND activo = 1 AND anulado = 0");
                            $q->execute();
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $sel = (!empty($proyectoDatos['id_cliente']) && $proyectoDatos['id_cliente'] == $f['id']) ? ' selected' : '';
                                echo "<option value='{$f['id']}'$sel>".htmlspecialchars($f['nombre'])."</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                        </div>
                      </div>

                      <!-- Tipo Comprobante -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Tipo Comprobante(*)</label>
                        <div class="col-sm-9">
                          <select name="id_tipo_comprobante" id="id_tipo_comprobante" class="js-example-basic-single col-sm-12" required>
                            <option value="">Seleccione...</option>
                            <?php
                            $pdo = Database::connect();
                            $q   = $pdo->prepare("SELECT id, tipo FROM tipos_comprobante WHERE 1");
                            $q->execute();
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>".htmlspecialchars($f['tipo'])."</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                        </div>
                      </div>

                      <!-- Letra -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Letra(*)</label>
                        <div class="col-sm-9">
                          <select name="id_letra_comprobante" id="id_letra_comprobante" class="js-example-basic-single col-sm-12" required>
                            <option value="">Seleccione...</option>
                            <?php
                            $pdo = Database::connect();
                            $q   = $pdo->prepare("SELECT id, letra FROM letras_comprobante WHERE 1");
                            $q->execute();
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>".htmlspecialchars($f['letra'])."</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                        </div>
                      </div>

                      <!-- Número -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Número(*)</label>
                        <div class="col-sm-9">
                          <input name="numero" id="numero" oninput="applyMask(this)"
                            placeholder="0001-00000001" type="text" maxlength="20"
                            class="form-control" required>
                        </div>
                      </div>

                      <!-- Proyecto (pre-seleccionado) -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Proyecto(*)</label>
                        <div class="col-sm-9">
                          <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required>
                            <option value="">Seleccione...</option>
                            <?php
                            $pdo = Database::connect();
                            $q   = $pdo->prepare("SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre
                                                  FROM proyectos p
                                                  INNER JOIN sitios s ON s.id = p.id_sitio
                                                  WHERE p.anulado = 0
                                                  ORDER BY p.nro DESC");
                            $q->execute();
                            $idProyectoGet = !empty($_GET['id_proyecto']) ? intval($_GET['id_proyecto']) : 0;
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $sel = ($idProyectoGet && $idProyectoGet == $f['id']) ? ' selected' : '';
                                $label = $f['nro_sitio'].'-'.$f['nro_subsitio'].'-'.$f['nro'].': '.htmlspecialchars($f['nombre']);
                                echo "<option value='{$f['id']}'$sel>$label</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                        </div>
                      </div>

                      <!-- Certificados vinculados -->
                      <?php
                      $certIds = [];
                      if (!empty($_GET['certificados'])) {
                          $certIds = array_filter(array_map('intval', (array)$_GET['certificados']));
                      }
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
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Certificados vinculados</label>
                        <div class="col-sm-9">
                          <?php foreach ($certIds as $cid): ?>
                            <input type="hidden" name="certificados[]" value="<?= $cid ?>">
                          <?php endforeach; ?>
                          <div class="list-group">
                            <?php foreach ($certRows as $cr): ?>
                              <div class="list-group-item py-2">
                                <div class="d-flex align-items-center justify-content-between">
                                  <div>
                                    <strong class="badge-success">Cert. #<?= htmlspecialchars($cr['numero']) ?></strong>
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
                      <?php endif; ?>

                      <!-- Descripción -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Descripción(*)</label>
                        <div class="col-sm-9">
                          <textarea name="descripcion" class="form-control" rows="3" required
                          ><?= !empty($proyectoDatos['nombre']) ? htmlspecialchars($proyectoDatos['nombre']) : '' ?></textarea>
                        </div>
                      </div>

                      <!-- Fecha Emitida -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha Emitida(*)</label>
                        <div class="col-sm-9">
                          <input name="fecha_emitida" id="fecha_emitida" type="date"
                            onfocus="this.showPicker()" class="form-control" required>
                        </div>
                      </div>

                      <!-- Fecha Enviada -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha Enviada(*)</label>
                        <div class="col-sm-9">
                          <input name="fecha_enviada" id="fecha_enviada" type="date"
                            onfocus="this.showPicker()" class="form-control" required>
                        </div>
                      </div>

                      <!-- Forma de Pago -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Forma de Pago</label>
                        <div class="col-sm-9">
                          <select name="id_condicion_pago" id="id_condicion_pago" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option>
                            <?php
                            $pdo = Database::connect();
                            $q   = $pdo->prepare("SELECT id, forma_pago FROM formas_pago WHERE 1");
                            $q->execute();
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>".htmlspecialchars($f['forma_pago'])."</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                        </div>
                      </div>

                      <!-- Moneda -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Moneda(*)</label>
                        <div class="col-sm-9">
                          <select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12" required>
                            <option value="">Seleccione...</option>
                            <?php
                            $pdo = Database::connect();
                            $q   = $pdo->prepare("SELECT id, moneda FROM monedas WHERE 1");
                            $q->execute();
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                $esDolar = (stripos($f['moneda'], 'dolar') !== false ||
                                            stripos($f['moneda'], 'dólar') !== false ||
                                            stripos($f['moneda'], 'usd')   !== false ||
                                            stripos($f['moneda'], 'u$d')   !== false ||
                                            stripos($f['moneda'], 'u$s')   !== false) ? 'true' : 'false';
                                echo "<option value='{$f['id']}' data-esusd='$esDolar'>".htmlspecialchars($f['moneda'])."</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                        </div>
                      </div>

                      <!-- Cotización -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Cotización(*)</label>
                        <div class="col-sm-9">
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

                      <!-- Regímenes de Facturación -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Otros Regímenes</label>
                        <div class="col-sm-9">
                          <select name="regimenes[]" id="regimenes" multiple="multiple"
                            class="js-example-basic-multiple col-sm-12">
                            <?php
                            $pdo = Database::connect();
                            $q   = $pdo->prepare("SELECT id, regimen, porcentaje FROM regimenes_facturacion WHERE anulado = 0 ORDER BY regimen");
                            $q->execute();
                            while ($f = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$f['id']}'>".htmlspecialchars($f['regimen'])." ({$f['porcentaje']}%)</option>";
                            }
                            Database::disconnect();
                            ?>
                          </select>
                        </div>
                      </div>

                      <!-- Observaciones -->
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Observaciones</label>
                        <div class="col-sm-9">
                          <textarea name="observaciones" class="form-control" rows="3"></textarea>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>
                <div class="card-footer">
                  <div class="col-sm-9 offset-sm-3">
                    <button class="btn btn-primary" type="submit">Crear y Agregar Detalle</button>
                    <a href="listarFacturasVenta.php" class="btn btn-light">Volver</a>
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
<script src="assets/js/script.js"></script>
<script src="assets/js/select2/select2.full.min.js"></script>
<script src="assets/js/select2/select2-custom.js"></script>

<script>
  // Validación fecha enviada >= fecha emitida
  $('#fecha_enviada').on('change', function () {
    var desde = $('#fecha_emitida').val();
    var hasta = $(this).val();
    if (desde && hasta && Date.parse(hasta) < Date.parse(desde)) {
      alert('La fecha enviada debe ser mayor o igual a la fecha emitida');
      $(this).val('');
    }
  });

  // Máscara número comprobante XXXX-XXXXXXXX
  function applyMask(input) {
    var v = input.value.replace(/\D/g, '');
    if (v.length > 4) v = v.substring(0, 4) + '-' + v.substring(4, 12);
    input.value = v;
  }

  // Cotización automática para dólar
  $('#id_moneda').on('change', function () {
    var opcion   = $(this).find('option:selected');
    var esDolar  = (opcion.data('esusd') === true || opcion.data('esusd') === 'true');
    var input    = $('#cotizacion');
    var badge    = $('#estadoDolar');
    var info     = $('#infoCotizacion');

    if (esDolar) {
      badge.text('Cargando...').removeClass('text-danger text-success').addClass('text-secondary');
      input.prop('readonly', true);
      fetch('https://dolarapi.com/v1/dolares/oficial', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (d) {
          if (!d.venta) throw new Error('Sin venta');
          input.val(parseFloat(d.venta).toFixed(2));
          badge.html('Dólar Oficial').removeClass('text-secondary text-danger').addClass('text-success');
          var fecha = d.fechaActualizacion ? ' — Act: ' + new Date(d.fechaActualizacion).toLocaleString('es-AR') : '';
          info.html('Compra: <strong>$' + parseFloat(d.compra).toLocaleString('es-AR', {minimumFractionDigits:2}) + '</strong>'
            + ' | Venta: <strong>$' + parseFloat(d.venta).toLocaleString('es-AR', {minimumFractionDigits:2}) + '</strong>' + fecha);
          input.prop('readonly', false);
        })
        .catch(function () {
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
</script>
</body>
</html>
