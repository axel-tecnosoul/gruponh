<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = !empty($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
  header("Location: listarFacturasVenta.php");
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT fv.*, tc.tipo AS tipo_comprobante, lc.letra, m.moneda AS moneda_text,
               cu.nombre AS cliente, e.empresa,
               CONCAT(s.nro_sitio, '-', s.nro_subsitio, '-', p.nro, ': ', p.nombre) AS proyecto
        FROM facturas_venta fv
        INNER JOIN tipos_comprobante tc ON tc.id = fv.id_tipo_comprobante
        INNER JOIN letras_comprobante lc ON lc.id = fv.id_letra_comprobante
        INNER JOIN monedas m ON m.id = fv.id_moneda
        LEFT JOIN proyectos p ON p.id = fv.id_proyecto
        LEFT JOIN sitios s ON s.id = p.id_sitio
        LEFT JOIN cuentas cu ON cu.id = fv.id_cuenta_destino
        LEFT JOIN empresas e ON e.id = fv.id_empresa
        WHERE fv.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

if (!$data) {
  Database::disconnect();
  header("Location: listarFacturasVenta.php");
  exit;
}

$estadoTexto = ($data['id_estado'] == 3) ? 'Definitivo' : 'Temporal';

$qDet = $pdo->prepare("SELECT d.*, cc.descripcion AS concepto_text
    FROM facturas_venta_detalle d
    INNER JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
    WHERE d.id_factura_venta = ?");
$qDet->execute([$id]);
$detalles = $qDet->fetchAll(PDO::FETCH_ASSOC);

$detallesConImputaciones = [];
foreach ($detalles as $det) {
  $qImp = $pdo->prepare("SELECT ca.id, cm.numero, ca.monto_total, ca.fecha_emision,
          ca.aprobado_cliente, cm.revision
      FROM facturas_venta_detalle_x_certificados_avance fvdxca
      INNER JOIN certificados_avances_cabecera ca ON ca.id = fvdxca.id_certificado_avance
      INNER JOIN certificados_maestros cm ON cm.id = ca.id_certificado_maestro
      WHERE fvdxca.id_factura_venta_detalle = ?");
  $qImp->execute([$det['id']]);
  $det['imputaciones'] = $qImp->fetchAll(PDO::FETCH_ASSOC);
  $detallesConImputaciones[] = $det;
}

$qRet = $pdo->prepare("SELECT r.*, rf.regimen AS regimen_text
    FROM facturas_venta_retenciones r
    INNER JOIN regimenes_facturacion rf ON rf.id = r.id_regimen_facturacion
    WHERE r.id_factura_venta = ?");
$qRet->execute([$id]);
$retenciones = $qRet->fetchAll(PDO::FETCH_ASSOC);

$qReg = $pdo->prepare("SELECT r.id_regimen, rf.regimen, rf.porcentaje
    FROM facturas_venta_otros r
    INNER JOIN regimenes_facturacion rf ON rf.id = r.id_regimen
    WHERE r.id_factura_venta = ?");
$qReg->execute([$id]);
$regimenes = $qReg->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

function fmtV($val) {
  return number_format((float)$val, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include('head_forms.php'); ?>
</head>
<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body">
        <?php $ubicacion = "Ver Factura de Venta";
        include_once("head_page.php"); ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5>
                    <?= $ubicacion ?> N° <?= htmlspecialchars($data['numero']) ?>
                    - <?= htmlspecialchars($data['tipo_comprobante']) ?> <?= htmlspecialchars($data['letra']) ?>
                  </h5>
                </div>
                <div class="card-body">

                  <h6 class="mb-3 font-weight-bold">Datos de la Factura</h6>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Empresa</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['empresa']) ?></div>
                    <label class="col-sm-2 font-weight-bold">Cliente</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['cliente']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Proyecto</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['proyecto']) ?></div>
                    <label class="col-sm-2 font-weight-bold">Tipo Comprobante</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['tipo_comprobante']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Letra</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['letra']) ?></div>
                    <label class="col-sm-2 font-weight-bold">Número</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['numero']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Fecha Emitida</label>
                    <div class="col-sm-4"><?= date('d/m/Y', strtotime($data['fecha_emitida'])) ?></div>
                    <label class="col-sm-2 font-weight-bold">Fecha Enviada</label>
                    <div class="col-sm-4"><?= date('d/m/Y', strtotime($data['fecha_enviada'])) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Moneda</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['moneda_text']) ?></div>
                    <label class="col-sm-2 font-weight-bold">Cotización</label>
                    <div class="col-sm-4"><?= fmtV($data['cotizacion']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Estado</label>
                    <div class="col-sm-4"><?= $estadoTexto ?></div>
                    <label class="col-sm-2 font-weight-bold">Pagada</label>
                    <div class="col-sm-4"><?= $data['pagada'] ? 'Sí' : 'No' ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Exportada</label>
                    <div class="col-sm-4"><?= $data['exportada'] ? 'Sí' : 'No' ?></div>
                  </div>

                  <?php if (!empty($regimenes)): ?>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Regímenes</label>
                    <div class="col-sm-10">
                      <?php foreach ($regimenes as $i => $reg): ?>
                        <?= htmlspecialchars($reg['regimen']) ?> (<?= fmtV($reg['porcentaje']) ?>%)<?= $i < count($regimenes) - 1 ? ', ' : '' ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                  <?php if (!empty($data['observaciones'])): ?>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Observaciones</label>
                    <div class="col-sm-10"><?= nl2br(htmlspecialchars($data['observaciones'])) ?></div>
                  </div>
                  <?php endif; ?>

                  <hr class="mt-4 mb-4">

                  <!-- DETALLE -->
                  <h6 class="mb-3 font-weight-bold">Detalle de Factura</h6>
                  <?php if (!empty($detallesConImputaciones)): ?>
                    <?php $sumaSubtotal = 0; ?>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered w-100" style="font-size:13px;">
                        <thead class="thead-light">
                          <tr>
                            <th>#</th>
                            <th>Concepto</th>
                            <th>Descripción</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($detallesConImputaciones as $i => $det): ?>
                            <?php
                              $subtotalDet = (float)$det['subtotal'];
                              $sumaSubtotal += $subtotalDet;
                            ?>
                            <tr>
                              <td><?= $i + 1 ?></td>
                              <td><?= htmlspecialchars($det['concepto_text']) ?></td>
                              <td><?= htmlspecialchars($det['descripcion'] ?? '') ?></td>
                              <td class="text-right"><?= fmtV($det['cantidad']) ?></td>
                              <td class="text-right"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($det['precio']) ?></td>
                              <td class="text-right"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($subtotalDet) ?></td>
                            </tr>
                            <?php if (!empty($det['imputaciones'])): ?>
                            <tr>
                              <td colspan="6" class="p-2">
                                <small class="text-muted font-weight-bold">Certificados vinculados</small>
                                <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                                  <thead class="thead-light">
                                    <tr>
                                      <th>Cert #</th>
                                      <th>Revisión</th>
                                      <th>Fecha Emisión</th>
                                      <th class="text-right">Monto</th>
                                      <th class="text-center">Aprobado</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <?php foreach ($det['imputaciones'] as $imp): ?>
                                    <tr>
                                      <td><?= htmlspecialchars($imp['numero']) ?></td>
                                      <td><?= htmlspecialchars($imp['revision']) ?></td>
                                      <td><?= date('d/m/Y', strtotime($imp['fecha_emision'])) ?></td>
                                      <td class="text-right"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($imp['monto_total']) ?></td>
                                      <td class="text-center">
                                        <span class="badge <?= $imp['aprobado_cliente'] ? 'badge-success' : 'badge-secondary' ?>">
                                          <?= $imp['aprobado_cliente'] ? 'Aprobado' : 'Pendiente' ?>
                                        </span>
                                      </td>
                                    </tr>
                                    <?php endforeach; ?>
                                  </tbody>
                                </table>
                              </td>
                            </tr>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <p class="text-muted">Sin ítems de detalle.</p>
                  <?php endif; ?>

                  <hr class="mt-4 mb-4">

                  <!-- RETENCIONES -->
                  <h6 class="mb-3 font-weight-bold">Retenciones</h6>
                  <?php if (!empty($retenciones)): ?>
                    <div class="table-responsive">
                          <table class="table table-sm table-bordered mb-0 w-100" style="font-size:13px;">
                        <thead class="thead-light">
                          <tr>
                            <th>#</th>
                            <th>Régimen</th>
                            <th class="text-right">Base imponible</th>
                            <th class="text-right">%</th>
                            <th class="text-right">Monto</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($retenciones as $i => $ret): ?>
                            <?php
                              $porc = (float)$ret['porcentaje'];
                              $base = $porc > 0 ? round((float)$ret['monto'] / ($porc / 100), 2) : (float)$ret['monto'];
                            ?>
                            <tr>
                              <td><?= $i + 1 ?></td>
                              <td><?= htmlspecialchars($ret['regimen_text']) ?></td>
                              <td class="text-right"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($base) ?></td>
                              <td class="text-right"><?= fmtV($porc) ?>%</td>
                              <td class="text-right"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($ret['monto']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <p class="text-muted">Sin retenciones.</p>
                  <?php endif; ?>

                  <hr class="mt-4 mb-4">

                  <!-- RESUMEN FINANCIERO -->
                  <h6 class="mb-3 font-weight-bold">Resumen Financiero</h6>
                  <div class="form-group row mt-1">
                    <label class="col-sm-3 font-weight-bold">Subtotal</label>
                    <div class="col-sm-3"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($data['subtotal_no_gravado']) ?></div>
                    <label class="col-sm-3 font-weight-bold">Retenciones</label>
                    <div class="col-sm-3"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($data['otros']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-3 font-weight-bold">IVA</label>
                    <div class="col-sm-3"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($data['iva']) ?></div>
                    <label class="col-sm-3 font-weight-bold text-success">TOTAL</label>
                    <div class="col-sm-3 text-success font-weight-bold"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmtV($data['total']) ?></div>
                  </div>

                </div>
                <div class="card-footer">
                  <div class="col-sm-12 text-center">
                    <?php if ($data['id_estado'] != 3 && $data['pagada'] != 1 && $data['exportada'] != 1): ?>
                      <a href="nuevaFacturaVenta.php?id=<?= $id ?>" class="btn btn-primary">Modificar</a>
                    <?php endif; ?>
                    <a href="listarFacturasVenta.php" class="btn btn-light">Volver</a>
                  </div>
                </div>

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
  <script src="assets/js/script.js"></script>
</body>
</html>
