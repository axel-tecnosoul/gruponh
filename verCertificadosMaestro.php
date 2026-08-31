<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarCertificadosMaestros.php");
}

if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT cm.id, occ.numero AS numero_occ, c.nombre AS cliente, date_format(cm.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cm.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cm.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cm.monto_total,cm.monto_acumulado_avances,cm.monto_acumulado_anticipos,cm.monto_acumulado_desacopios,cm.monto_acumulado_descuentos,cm.monto_acumulado_ajustes,cm.observaciones,cm.aprobado_cliente FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ=occ.id INNER JOIN cuentas c ON c.id=occ.id_cuenta_cliente INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cm.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  Database::disconnect();
}

$id_occ = 0;
$moneda_occ = '';
$occ_detalles = [];
$unidades_medida = [];
$lotes_editables = [];
$proyectos_map = [];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cm.id_occ, m.moneda FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ = occ.id INNER JOIN monedas m ON occ.id_moneda = m.id WHERE cm.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data_occ = $q->fetch(PDO::FETCH_ASSOC);
if (!empty($data_occ)) {
  $id_occ = (int) $data_occ['id_occ'];
  $moneda_occ = (string) $data_occ['moneda'];
}

if ($id_occ > 0) {
  $sql = "SELECT id, posicion, descripcion, cantidad, precio_unitario, descuento, subtotal FROM occ_detalles WHERE id_occ = ? ORDER BY posicion, id";
  $q = $pdo->prepare($sql);
  $q->execute([$id_occ]);
  $occ_detalles = $q->fetchAll(PDO::FETCH_ASSOC);
}

$sql = "SELECT id, unidad_medida FROM unidades_medida ORDER BY unidad_medida";
$q = $pdo->prepare($sql);
$q->execute();
$unidades_medida = $q->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre FROM proyectos p INNER JOIN sitios s ON s.id = p.id_sitio WHERE p.anulado = 0";
$q = $pdo->prepare($sql);
$q->execute();
while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
  $proyectos_map[(int) $fila['id']] = $fila['nro_sitio'] . '-' . $fila['nro_subsitio'] . '-' . $fila['nro'] . ': ' . $fila['nombre'];
}

$sql = "SELECT aperturado, lote, modo_generacion, id_proyecto, COALESCE(MAX(monto_base_occ),0) AS monto_base_occ, COALESCE(SUM(subtotal),0) AS subtotal_lote, COUNT(*) AS cantidad_filas FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado IS NOT NULL AND aperturado <> '' AND modo_generacion IN ('agrupar','separar') GROUP BY aperturado, lote, modo_generacion, id_proyecto ORDER BY MAX(id) DESC";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$lotes_base = $q->fetchAll(PDO::FETCH_ASSOC);

foreach ($lotes_base as $lote_row) {
  $lote_id = $lote_row['aperturado'];
  $lote_nro = $lote_row['lote'] ?? '';

  $sql = "SELECT id_occ_detalle FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro = ? AND aperturado = ? ORDER BY id_occ_detalle";
  $q = $pdo->prepare($sql);
  $q->execute([$id, $lote_id]);
  $occ_rows_rel = $q->fetchAll(PDO::FETCH_COLUMN, 0);

  $sql = "SELECT id_occ_detalle, descripcion, id_unidad_medida, cantidad, incidencia_porcentaje FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ? ORDER BY id";
  $q = $pdo->prepare($sql);
  $q->execute([$id, $lote_id]);
  $rows_lote = $q->fetchAll(PDO::FETCH_ASSOC);

  $occ_ids = [];
  foreach ($occ_rows_rel as $occ_rel) {
    $tmp_rel = (int) $occ_rel;
    if ($tmp_rel > 0) {
      $occ_ids[$tmp_rel] = $tmp_rel;
    }
  }

  $ap_rows = [];
  foreach ($rows_lote as $r) {
    if (empty($occ_ids) && !empty($r['id_occ_detalle'])) {
      $occ_ids[(int) $r['id_occ_detalle']] = (int) $r['id_occ_detalle'];
    }
    $ap_rows[] = [
      'descripcion' => (string) ($r['descripcion'] ?? ''),
      'id_unidad_medida' => (int) ($r['id_unidad_medida'] ?? 0),
      'cantidad' => (float) ($r['cantidad'] ?? 0),
      'incidencia' => (float) ($r['incidencia_porcentaje'] ?? 0),
    ];
  }

  $lotes_editables[] = [
    'aperturado' => $lote_id,
    'lote' => $lote_nro,
    'modo_generacion' => (string) $lote_row['modo_generacion'],
    'id_proyecto' => (int) ($lote_row['id_proyecto'] ?? 0),
    'monto_base_occ' => (float) ($lote_row['monto_base_occ'] ?? 0),
    'subtotal_lote' => (float) ($lote_row['subtotal_lote'] ?? 0),
    'cantidad_filas' => (int) ($lote_row['cantidad_filas'] ?? 0),
    'occ_ids' => array_values($occ_ids),
    'aplica_global' => empty($occ_ids),
    'aperturado_rows' => $ap_rows,
  ];
}

$loteOccGroup = [];
foreach ($lotes_editables as $le) {
  $loteKey = $le['lote'];
  if (!isset($loteOccGroup[$loteKey])) {
    $loteOccGroup[$loteKey] = [];
  }
  foreach ($le['occ_ids'] as $oid) {
    $loteOccGroup[$loteKey][$oid] = $oid;
  }
}
foreach ($lotes_editables as &$le) {
  $le['todos_occ_ids'] = array_values($loteOccGroup[$le['lote']] ?? []);
}
unset($le);

$occ_orden_meta = [];
foreach ($lotes_editables as $idx => $le) {
  if ((string) $le['modo_generacion'] !== 'agrupar') continue;
  foreach ($le['todos_occ_ids'] as $oid) {
    $oid = (int) $oid;
    if (!isset($occ_orden_meta[$oid])) {
      $occ_orden_meta[$oid] = ['grupo' => 1, 'clave' => $idx];
    }
  }
}
foreach ($occ_detalles as &$occ_row) {
  $occ_id = (int) $occ_row['id'];
  $meta = $occ_orden_meta[$occ_id] ?? ['grupo' => 3, 'clave' => $occ_row['posicion']];
  $occ_row['__grupo'] = $meta['grupo'];
  $occ_row['__clave'] = $meta['clave'];
}
unset($occ_row);
usort($occ_detalles, function ($a, $b) {
  if ($a['__grupo'] != $b['__grupo']) {
    return $a['__grupo'] <=> $b['__grupo'];
  }
  if ($a['__clave'] != $b['__clave']) {
    return $a['__clave'] <=> $b['__clave'];
  }
  return ((int) $a['posicion'] <=> (int) $b['posicion']) ?: ((int) $a['id'] <=> (int) $b['id']);
});

Database::disconnect();?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
	<style>
	  #tabla_occ_detalles tbody tr.occ-grouped-member td {
	    background-color: #eef8ff;
	  }

	  #tabla_occ_detalles tbody tr.occ-grouped-start td,
	  #tabla_occ_detalles tbody tr.occ-grouped-middle td,
	  #tabla_occ_detalles tbody tr.occ-grouped-end td,
	  #tabla_occ_detalles tbody tr.occ-grouped-single td {
	    border-left: 3px solid #2b8dbf;
	  }

	  #tabla_occ_detalles tbody tr.occ-grouped-start td {
	    border-top: 2px solid #2b8dbf;
	  }

	  #tabla_occ_detalles tbody tr.occ-grouped-end td {
	    border-bottom: 2px solid #2b8dbf;
	  }

	  #tabla_occ_detalles tbody tr.occ-grouped-single td {
	    border-top: 2px solid #2b8dbf;
	    border-bottom: 2px solid #2b8dbf;
	  }

	  .occ-group-aperturado-wrap {
	    border-left: 4px solid #2b8dbf;
	    background: #f7fcff;
	    border-radius: 4px;
	    padding: 8px 10px;
	  }
	</style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
    <?php include('header.php');?>
    
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Ver Certificado Maestro";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?> #<?=$data['id']?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="row">
                            <div class="col-lg-6">
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Orden de Compra Cliente</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=$data['numero_occ']?></span></div>
                              </div>
<div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Fecha Emisión</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=$data['fecha_emision'];?></span></div>
                              </div>
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Fecha Inicio</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=$data['fecha_inicio'];?></span></div>
                              </div>
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Fecha Fin</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=$data['fecha_fin'];?></span></div>
                              </div>
                            </div>
                            <div class="col-lg-6">
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Moneda</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=$data['moneda']?></span></div>
                              </div>
                               <div class="form-group row mb-2">
                                 <label class="col-sm-5 col-form-label font-weight-bold">Cliente</label>
                                 <div class="col-sm-7"><span class="form-control-plaintext"><?=htmlspecialchars($data['cliente'] ?? '', ENT_QUOTES, 'UTF-8')?></span></div>
                               </div>
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Monto total</label>
                                <div class="col-sm-7"><span class="form-control-plaintext">$<?=number_format($data['monto_total'],2);?></span></div>
                              </div>
                               <div class="form-group row mb-2">
                                 <label class="col-sm-5 col-form-label font-weight-bold">Observaciones</label>
                                 <div class="col-sm-7"><span class="form-control-plaintext"><?=$data['observaciones'];?></span></div>
                               </div>
                               <div class="form-group row mb-2">
                                 <label class="col-sm-5 col-form-label font-weight-bold">Estado CM</label>
                                 <div class="col-sm-7"><span class="badge <?=$data['aprobado_cliente'] ? 'badge-success' : 'badge-warning'?>"><?=$data['aprobado_cliente'] ? 'Aprobado' : 'Pendiente'?></span></div>
                               </div>
                            </div>
                          </div>
                          <div class="row">
                            <!-- Items OCC con desglose Starts-->
                            <div class="col-sm-12">
                              <div class="card">
                                <div class="card-header">
                                  <h5>Detalle del Certificado Maestro</h5>
                                </div>
                                <div class="card-body">
                                  <h6 class="mb-3 font-weight-bold">Items OCC</h6>
                                  <div class="table-responsive">
                                    <table class="table table-sm table-bordered display" id="tabla_occ_detalles" style="width:100%">
                                      <thead>
                                        <tr>
                                          <th>ID</th>
                                          <th>Posición</th>
                                          <th>Descripcion</th>
                                          <th>Cantidad</th>
                                          <th class="text-right">Precio unitario</th>
                                          <th class="text-right">Descuento</th>
                                          <th class="text-right">Subtotal</th>
                                          <th class="text-center occ-desglose-col" style="width:150px;">Acciones</th>
                                        </tr>
                                      </thead>
                                      <tbody><?php
                                        if (empty($occ_detalles)) { ?>
                                          <tr>
                                            <td colspan="8">La Orden de Compra seleccionada no tiene items.</td>
                                          </tr><?php
                                        } else {
                                          foreach ($occ_detalles as $row) { ?>
                                          <tr class="occ-item-row" data-id="<?= $row['id'] ?>" data-subtotal="<?= $row['subtotal'] ?>">
                                            <td><?= $row['id'] ?></td>
                                            <td><?= $row['posicion'] ?></td>
                                            <td><?= htmlspecialchars($row['descripcion']) ?></td>
                                            <td><?= number_format($row['cantidad'], 2, ',', '.') ?></td>
                                            <td class="text-right"><?= $moneda_occ ?> <?= number_format($row['precio_unitario'], 2, ',', '.') ?></td>
                                            <td class="text-right"><?= $moneda_occ ?> <?= number_format($row['descuento'], 2, ',', '.') ?></td>
                                            <td class="text-right"><?= $moneda_occ ?> <?= number_format($row['subtotal'], 2, ',', '.') ?></td>
                                            <td class="text-center occ-desglose-cell"></td>
                                          </tr><?php
                                          }
                                        } ?>
                                      </tbody>
                                    </table>
                                  </div>
                                  <div class="form-group row mt-2" id="occ_breakdown_controls" style="display:none;">
                                    <div class="col-sm-12 text-right">
                                      <button type="button" id="btn_toggle_todos_desgloses" class="btn btn-secondary btn-sm">Ocultar desglose de todos</button>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <!-- Items OCC con desglose Ends-->
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						            <a class="btn btn-primary" target="_blank" href="imprimirCertificadoMaestro.php?id=<?=$id?>">Imprimir</a>
                        <a href="listarCertificadosMaestros.php" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
    <!-- latest jquery-->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap js-->
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <!-- feather icon js-->
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <!-- Sidebar jquery-->
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <!-- Plugins JS start-->
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
	<script>
    $(document).ready(function() {
      const simboloMonedaOcc = <?= json_encode($moneda_occ) ?>;
      const unidadesMedida = <?= json_encode($unidades_medida) ?>;
      const lotesEditablesData = <?= json_encode($lotes_editables) ?>;
      const proyectosLabelsMap = <?= json_encode($proyectos_map) ?>;
      const occSubtotalPorId = {};
      const desglosePrecargadoPorItem = {};
      const hiddenDesglosePorItem = {};
      let ocultarTodosDesgloses = false;
      let occDataTable = null;

      lotesEditablesData.forEach(function(lote) {
        lote.occ_ids = lote.occ_ids || [];
        lote.aperturado_rows = lote.aperturado_rows || [];
        (lote.occ_ids || []).forEach(function(occId) {
          desglosePrecargadoPorItem[String(occId)] = true;
        });
      });

      function formatNumber(value) {
        return value.toLocaleString('es-AR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function escapeHtml(text) {
        return String(text || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function getProyectoLabelById(idProyecto) {
        const key = String(idProyecto || '');
        if (!key) {
          return '';
        }
        return proyectosLabelsMap[key] || key;
      }

      function buildGroupedRenderContext() {
        const groupedByOwner = {};
        const groupedMembershipByItem = {};
        const groupedRowClassByItem = {};
        const groupedSortOrderById = {};

        lotesEditablesData
          .filter(function(lote) {
            return String(lote.modo_generacion || '') === 'agrupar';
          })
          .forEach(function(lote) {
            const idsDeGrupo = Array.isArray(lote.occ_ids) ? lote.occ_ids.map(String) : [];
            if (!idsDeGrupo.length) {
              return;
            }

            const ownerOccId = idsDeGrupo[idsDeGrupo.length - 1];
            if (!groupedByOwner[ownerOccId]) {
              groupedByOwner[ownerOccId] = [];
            }
            groupedByOwner[ownerOccId].push({
              lote: lote,
              ids_grupo: idsDeGrupo,
            });

            idsDeGrupo.forEach(function(id, idx) {
              if (!groupedMembershipByItem[id]) {
                groupedMembershipByItem[id] = [];
              }
              groupedMembershipByItem[id].push({
                aperturado: lote.aperturado,
                ids_grupo: idsDeGrupo,
                is_owner: id === ownerOccId,
              });

              if (!groupedRowClassByItem[id]) {
                if (idsDeGrupo.length === 1) {
                  groupedRowClassByItem[id] = 'occ-grouped-single';
                } else if (idx === 0) {
                  groupedRowClassByItem[id] = 'occ-grouped-start';
                } else if (idx === idsDeGrupo.length - 1) {
                  groupedRowClassByItem[id] = 'occ-grouped-end';
                } else {
                  groupedRowClassByItem[id] = 'occ-grouped-middle';
                }
              }
              groupedSortOrderById[id] = idx;
            });
          });

        return {
          groupedByOwner: groupedByOwner,
          groupedMembershipByItem: groupedMembershipByItem,
          groupedRowClassByItem: groupedRowClassByItem,
          groupedSortOrderById: groupedSortOrderById,
        };
      }

      function buildOccBreakdownHtml(occId, baseIndividual, groupedContext) {
        const groupMemberInfo = (groupedContext && groupedContext.groupedMembershipByItem && groupedContext.groupedMembershipByItem[String(occId)]) || [];
        const groupedLotesForThisItem = (groupedContext && groupedContext.groupedByOwner && groupedContext.groupedByOwner[String(occId)]) || [];
        const hasGroupedOwnership = groupedLotesForThisItem.length > 0;

        const lotesSeparados = lotesEditablesData.filter(function(lote) {
          if (String(lote.modo_generacion || '') === 'agrupar') {
            return false;
          }
          const occIds = Array.isArray(lote.occ_ids) ? lote.occ_ids.map(String) : [];
          return occIds.indexOf(String(occId)) >= 0;
        });

        function renderLoteHtml(lote) {
          const rowsLote = Array.isArray(lote.aperturado_rows) ? lote.aperturado_rows : [];
          const baseLote = parseFloat(lote.monto_base_occ) || 0;
          const filasHtml = rowsLote.length ?
            rowsLote.map(function(row) {
              const cantidad = parseFloat(row.cantidad) || 0;
              const incidencia = parseFloat(row.incidencia) || 0;
              const totalFila = baseLote * (incidencia / 100);
              const precioUnitario = cantidad > 0 ? (totalFila / cantidad) : 0;
              return `
                        <tr>
                          <td>${escapeHtml(row.descripcion)}</td>
                          <td>${escapeHtml((unidadesMedida.find(function (u) { return String(u.id) === String(row.id_unidad_medida); }) || {}).unidad_medida || '')}</td>
                          <td class="text-right">${formatNumber(cantidad)}</td>
                          <td class="text-right">${formatNumber(incidencia)}%</td>
                          <td class="text-right">${simboloMonedaOcc} ${formatNumber(precioUnitario)}</td>
                          <td class="text-right">${simboloMonedaOcc} ${formatNumber(totalFila)}</td>
                        </tr>`;
            }).join('') :
            '<tr><td colspan="6" class="text-muted">Sin filas de detalle guardadas.</td></tr>';

          const sumaTotal = rowsLote.reduce(function(sum, row) {
            const incidencia = parseFloat(row.incidencia) || 0;
            return sum + (baseLote * (incidencia / 100));
          }, 0);

          return `
                <div class="border rounded px-2 py-2 mb-2 occ-lote-inline-row">
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 occ-breakdown-table">
                      <thead>
                        <tr>
                          <th>Descripcion</th>
                          <th>Unidad</th>
                          <th class="text-right">Cantidad</th>
                          <th class="text-right">Incidencia</th>
                          <th class="text-right">Precio Unitario</th>
                          <th class="text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${filasHtml}
                      </tbody>
                      <tfoot class="bg-light">
                        <tr class="font-weight-bold">
                          <td colspan="5" class="text-right">Total</td>
                          <td class="text-right">${simboloMonedaOcc} ${formatNumber(sumaTotal)}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>`;
        }

        const loteAgrupadoDetalleHtml = groupedLotesForThisItem.length ?
          groupedLotesForThisItem.map(function(entry) {
            const idsGrupo = entry.ids_grupo || [];
            return `
                  <div class="occ-group-aperturado-wrap mb-2">
                   ${renderLoteHtml(entry.lote)}
                  </div>`;
          }).join('') :
          '';

        if (!hasGroupedOwnership && groupMemberInfo.length === 0 && lotesSeparados.length === 0) {
          return '';
        }

        if (!hasGroupedOwnership && groupMemberInfo.length > 0 && lotesSeparados.length === 0) {
          return '';
        }

        const lotesSeparadosHtml = lotesSeparados.length ?
          lotesSeparados.map(renderLoteHtml).join('') :
          '';

        const lotesHtml = (groupedLotesForThisItem.length || lotesSeparados.length) ?
          `
              ${loteAgrupadoDetalleHtml}
              ${lotesSeparadosHtml}
            ` :
          '<small class="text-muted d-block mb-2">Sin detalles asociados para este item OCC.</small>';

        return `
            <div class="occ-breakdown-panel">
              <div class="occ-lote-inline-actions mb-2">
                ${lotesHtml}
              </div>
            </div>`;
      }

      function syncOccRowStyles() {
        const groupedContext = buildGroupedRenderContext();

        $('#tabla_occ_detalles tbody tr.occ-item-row').each(function() {
          const rowId = String($(this).data('id') || '');
          const toggleState = hiddenDesglosePorItem[rowId];
          const isHidden = ocultarTodosDesgloses || toggleState === 'hidden';
          const actionCell = $(this).find('td.occ-desglose-cell');

          let loteIdx = -1;
          for (let i = 0; i < lotesEditablesData.length; i++) {
            const lt = lotesEditablesData[i];
            if (String(lt.modo_generacion || '') !== 'agrupar') continue;
            const ids = (lt.todos_occ_ids || lt.occ_ids || []).map(String);
            if (ids.indexOf(rowId) >= 0) { loteIdx = i; break; }
          }
          const pIdx = String(Math.max(0, loteIdx)).padStart(3, '0');
          if (loteIdx >= 0) {
            $(this).find('td:first').attr('data-order', '1' + pIdx + String(rowId).padStart(10, '0'));
          } else {
            $(this).find('td:first').attr('data-order', '3' + String(rowId).padStart(10, '0'));
          }

          $(this)
            .removeClass('occ-grouped-member occ-grouped-start occ-grouped-middle occ-grouped-end occ-grouped-single');
          actionCell.find('.btn-toggle-desglose-item').remove();

          const tieneBreakdownGuardado = !!buildOccBreakdownHtml(rowId, parseFloat(occSubtotalPorId[rowId]) || 0, groupedContext);
          if (tieneBreakdownGuardado) {
            const textBtn = isHidden ? 'Mostrar desglose' : 'Ocultar desglose';
            actionCell.append('<button type="button" class="btn btn-secondary btn-sm btn-toggle-desglose-item" data-occ-id="' + rowId + '">' + textBtn + '</button>');
          }
        });

        $('#tabla_occ_detalles tbody tr.occ-item-row').each(function() {
          const rowId = String($(this).data('id') || '');
          const rowClass = groupedContext.groupedRowClassByItem[rowId] || '';
          if (!rowClass) {
            return;
          }
          $(this).addClass('occ-grouped-member ' + rowClass);
        });
      }

      function renderOccBreakdowns() {
        if (!occDataTable) return;
        syncOccRowStyles();

        const groupedContext = buildGroupedRenderContext();

        occDataTable.rows().every(function() {
          const rowNode = $(this.node());
          const occId = String(rowNode.data('id') || '');
          if (!occId) {
            this.child.hide();
            return;
          }

          const toggleState = hiddenDesglosePorItem[occId];
          const isHidden = ocultarTodosDesgloses || toggleState === 'hidden';
          if (isHidden) {
            this.child.hide();
            return;
          }

          const baseIndividual = parseFloat(occSubtotalPorId[occId]) || 0;
          const breakdownHtml = buildOccBreakdownHtml(occId, baseIndividual, groupedContext);
          if (breakdownHtml) {
            this.child(breakdownHtml).show();
          } else {
            this.child.hide();
          }
        });
      }

      function updateOccBreakdownControls() {
        const hasItems = Object.keys(desglosePrecargadoPorItem).length > 0;
        $('#occ_breakdown_controls').toggle(hasItems);
        if (!hasItems) {
          return;
        }

        let allHidden = ocultarTodosDesgloses;
        if (!allHidden) {
          const ids = Object.keys(desglosePrecargadoPorItem);
          allHidden = ids.length > 0 && ids.every(function(id) {
            return hiddenDesglosePorItem[id] === 'hidden';
          });
        }

        $('#btn_toggle_todos_desgloses').text(allHidden ? 'Mostrar desgloses' : 'Ocultar desglose de todos');
      }

      $(document).on('click', '.btn-toggle-desglose-item', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const occId = String($(this).data('occ-id') || '');
        if (!occId) {
          return;
        }

        const currentState = hiddenDesglosePorItem[occId];
        if (ocultarTodosDesgloses) {
          ocultarTodosDesgloses = false;
          Object.keys(desglosePrecargadoPorItem).forEach(function(id) {
            hiddenDesglosePorItem[id] = 'hidden';
          });
          delete hiddenDesglosePorItem[occId];
        } else if (currentState === 'hidden') {
          delete hiddenDesglosePorItem[occId];
        } else if (currentState === 'shown') {
          hiddenDesglosePorItem[occId] = 'hidden';
        } else {
          const btnText = $(this).text().trim();
          hiddenDesglosePorItem[occId] = btnText.indexOf('Ocultar') >= 0 ? 'hidden' : 'shown';
        }

        syncOccRowStyles();
        renderOccBreakdowns();
      });

      $('#btn_toggle_todos_desgloses').on('click', function() {
        let allHidden = ocultarTodosDesgloses;
        if (!allHidden) {
          const ids = Object.keys(desglosePrecargadoPorItem);
          allHidden = ids.length > 0 && ids.every(function(id) {
            return hiddenDesglosePorItem[id] === 'hidden';
          });
        }

        if (allHidden) {
          ocultarTodosDesgloses = false;
          Object.keys(hiddenDesglosePorItem).forEach(function(id) {
            delete hiddenDesglosePorItem[id];
          });
        } else {
          ocultarTodosDesgloses = true;
        }

        syncOccRowStyles();
        renderOccBreakdowns();
      });

      occDataTable = $('#tabla_occ_detalles').DataTable({
        stateSave: false,
        responsive: false,
        paging: false,
        order: [],
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
          "zeroRecords": "No hay resultados"
        }
      });

      $('#tabla_occ_detalles tbody tr.occ-item-row').each(function() {
        const rowId = String($(this).data('id') || '');
        const subtotal = parseFloat($(this).data('subtotal')) || 0;
        if (rowId) {
          occSubtotalPorId[rowId] = subtotal;
        }
      });

      renderOccBreakdowns();
      updateOccBreakdownControls();

      $('#tabla_occ_detalles').on('draw.dt', function() {
        syncOccRowStyles();
        renderOccBreakdowns();
      });

      if (window.feather) {
        feather.replace();
      }
    });
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>
