<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = !empty($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
  header("Location: listarFacturasCompra.php");
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT fc.*, tc.tipo AS tipo_comprobante, lc.letra, m.moneda AS moneda_text,
               cu.razon_social, e.empresa
        FROM facturas_compra fc
        INNER JOIN tipos_comprobante tc ON tc.id = fc.id_tipo_comprobante
        INNER JOIN letras_comprobante lc ON lc.id = fc.id_letra_comprobante
        INNER JOIN monedas m ON m.id = fc.id_moneda
        INNER JOIN cuentas cu ON cu.id = fc.id_cuenta_origen
        INNER JOIN empresas e ON e.id = fc.id_empresa
        WHERE fc.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

if (!$data) {
  Database::disconnect();
  header("Location: listarFacturasCompra.php");
  exit;
}

$estadoTexto = ($data['id_estado'] == 3) ? 'Definitivo' : 'Temporal';

$qOC = $pdo->prepare("SELECT fcxc.id_compra, c.nro_oc
    FROM facturas_compra_x_compras fcxc
    INNER JOIN compras c ON c.id = fcxc.id_compra
    WHERE fcxc.id_factura_compra = ?");
$qOC->execute([$id]);
$ocs = $qOC->fetchAll(PDO::FETCH_ASSOC);

$qDet = $pdo->prepare("SELECT d.*, cc.descripcion AS concepto_text
    FROM facturas_compra_detalle d
    INNER JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
    WHERE d.id_factura_compra = ?");
$qDet->execute([$id]);
$detalles = $qDet->fetchAll(PDO::FETCH_ASSOC);

$detallesConImputaciones = [];
foreach ($detalles as $det) {
  $qImp = $pdo->prepare("SELECT fcdx.cantidad, fcdx.precio, m.concepto, c.nro_oc
      FROM facturas_compra_detalle_x_compras_detalle fcdx
      INNER JOIN compras_detalle cd ON cd.id = fcdx.id_compra_detalle
      INNER JOIN materiales m ON m.id = cd.id_material
      INNER JOIN compras c ON c.id = cd.id_compra
      WHERE fcdx.id_factura_compra_detalle = ?");
  $qImp->execute([$det['id']]);
  $det['imputaciones'] = $qImp->fetchAll(PDO::FETCH_ASSOC);
  $detallesConImputaciones[] = $det;
}

$qRet = $pdo->prepare("SELECT r.*, COALESCE(r.regimen_text, r.codigo, r.articulo, '') AS regimen_text
    FROM facturas_compra_retenciones r
    WHERE r.id_factura_compra = ?");
$qRet->execute([$id]);
$retenciones = $qRet->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

function fmt($val) {
  return number_format((float)$val, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include('head_forms.php'); ?>
  <style>
    .ver-factura .form-control-plaintext { padding-top: 0; padding-bottom: 0; }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body">
        <?php $ubicacion = "Ver Factura de Compra";
        include_once("head_page.php"); ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card ver-factura">
                <div class="card-header">
                  <h5>
                    <?= $ubicacion ?> N° <?= htmlspecialchars($data['numero']) ?>
                    - <?= htmlspecialchars($data['tipo_comprobante']) ?> <?= htmlspecialchars($data['letra']) ?>
                  </h5>
                </div>
                <div class="card-body">

                  <!-- DATOS DE LA FACTURA -->
                  <h6 class="mb-3 font-weight-bold">Datos de la Factura</h6>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Empresa</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['empresa']) ?></div>
                    <label class="col-sm-2 font-weight-bold">Proveedor</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['razon_social']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Tipo Comprobante</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['tipo_comprobante']) ?></div>
                    <label class="col-sm-2 font-weight-bold">Letra</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['letra']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Número</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['numero']) ?></div>
                    <label class="col-sm-2 font-weight-bold">Fecha Emitida</label>
                    <div class="col-sm-4"><?= date('d/m/Y', strtotime($data['fecha_emitida'])) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Fecha Recibida</label>
                    <div class="col-sm-4"><?= date('d/m/Y', strtotime($data['fecha_recibida'])) ?></div>
                    <label class="col-sm-2 font-weight-bold">Moneda</label>
                    <div class="col-sm-4"><?= htmlspecialchars($data['moneda_text']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Cotización</label>
                    <div class="col-sm-4"><?= fmt($data['cotizacion']) ?></div>
                    <label class="col-sm-2 font-weight-bold">Estado</label>
                    <div class="col-sm-4"><?= $estadoTexto ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Exportada</label>
                    <div class="col-sm-4"><?= $data['exportada'] ? 'Sí' : 'No' ?></div>
                  </div>

                  <?php if (!empty($ocs)): ?>
                  <div class="form-group row mt-1">
                    <label class="col-sm-2 font-weight-bold">Órdenes de Compra</label>
                    <div class="col-sm-10">
                      <?php foreach ($ocs as $i => $oc): ?>
                        <a href="verCompra.php?id=<?= (int)$oc['id_compra'] ?>" target="_blank">
                          OC #<?= htmlspecialchars($oc['nro_oc']) ?>
                        </a><?= $i < count($ocs) - 1 ? ', ' : '' ?>
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
                      <table class="table table-sm table-bordered w-100" style="font-size:13px;background:#fff;">
                        <thead class="thead-light">
                          <tr>
                            <th>#</th>
                            <th>Concepto</th>
                            <th>Descripción</th>
                            <th>Imputaciones</th>
                            <th class="text-right">% Desc</th>
                            <th class="text-right">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($detallesConImputaciones as $i => $det): ?>
                            <?php
                              $subtotalDet = (float)$det['subtotal'];
                              $sumaSubtotal += $subtotalDet;
                              $porcDesc = (float)($det['porc_descuento'] ?? 0);
                              $impsHtml = '';
                              if (!empty($det['imputaciones'])) {
                                $impsHtml = '<table class="table table-sm table-borderless mb-0" style="font-size:12px;background:#fff;">';
                                $impsHtml .= '<thead><tr>';
                                $impsHtml .= '<th style="padding:0 2px;">Concepto</th>';
                                $impsHtml .= '<th style="text-align:right;padding:0 2px;">Cant.</th>';
                                $impsHtml .= '<th style="text-align:right;padding:0 2px;">Precio</th>';
                                $impsHtml .= '<th style="text-align:right;padding:0 2px;">Subtotal</th>';
                                $impsHtml .= '</tr></thead><tbody>';
                                foreach ($det['imputaciones'] as $imp) {
                                  $impsHtml .= '<tr>';
                                  $impsHtml .= '<td style="padding:2px 4px;">OC #' . htmlspecialchars($imp['nro_oc']) . ' - ' . htmlspecialchars($imp['concepto']) . '</td>';
                                  $impsHtml .= '<td style="padding:2px 4px;text-align:right;">' . fmt($imp['cantidad']) . '</td>';
                                  $impsHtml .= '<td style="padding:2px 4px;text-align:right;">' . htmlspecialchars($data['moneda_text']) . ' ' . fmt($imp['precio']) . '</td>';
                                  $impsHtml .= '<td style="padding:2px 4px;text-align:right;">' . htmlspecialchars($data['moneda_text']) . ' ' . fmt((float)$imp['cantidad'] * (float)$imp['precio']) . '</td>';
                                  $impsHtml .= '</tr>';
                                }
                                $impsHtml .= '</tbody></table>';
                              }
                            ?>
                            <tr>
                              <td><?= $i + 1 ?></td>
                              <td><?= htmlspecialchars($det['concepto_text']) ?></td>
                              <td><?= htmlspecialchars($det['descripcion'] ?? '') ?></td>
                              <td><?= $impsHtml ?></td>
                              <td class="text-right"><?= fmt($porcDesc) ?>%</td>
                              <td class="text-right"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmt($subtotalDet) ?></td>
                            </tr>
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
                          <table class="table table-sm table-bordered mb-0 w-100" style="font-size:13px;background:#fff;">
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
                          <?php $totalRet = 0; ?>
                          <?php foreach ($retenciones as $i => $ret): ?>
                            <?php
                              $porc = (float)$ret['porcentaje'];
                              $base = $porc > 0 ? round((float)$ret['monto'] / ($porc / 100), 2) : (float)$ret['monto'];
                              $totalRet += (float)$ret['monto'];
                            ?>
                            <tr>
                              <td><?= $i + 1 ?></td>
                              <td><?= htmlspecialchars($ret['regimen_text']) ?></td>
                              <td class="text-right"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmt($base) ?></td>
                              <td class="text-right"><?= fmt($porc) ?>%</td>
                              <td class="text-right"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmt($ret['monto']) ?></td>
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
                    <div class="col-sm-3"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmt($data['subtotal_no_gravado']) ?></div>
                    <label class="col-sm-3 font-weight-bold">Retenciones</label>
                    <div class="col-sm-3"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmt($data['otros']) ?></div>
                  </div>
                  <div class="form-group row mt-1">
                    <label class="col-sm-3 font-weight-bold">IVA</label>
                    <div class="col-sm-3"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmt($data['iva']) ?></div>
                    <label class="col-sm-3 font-weight-bold text-success">TOTAL</label>
                    <div class="col-sm-3 text-success font-weight-bold"><?= htmlspecialchars($data['moneda_text']) ?> <?= fmt($data['total']) ?></div>
                  </div>

                </div>
                <div class="card-footer">
                  <div class="col-sm-12 text-center">
                    <?php if ($data['id_estado'] != 3 && $data['exportada'] != 1): ?>
                      <a href="nuevaFacturaCompra.php?id=<?= $id ?>" class="btn btn-primary">Modificar</a>
                    <?php endif; ?>
                    <a href="listarFacturasCompra.php" class="btn btn-light">Volver</a>
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
