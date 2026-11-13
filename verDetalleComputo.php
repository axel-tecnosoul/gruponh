<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

require 'database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$prod = !empty($_GET['prod']) ? (int)$_GET['prod'] : 0;
$urlListado = 'listarComputos.php' . ($prod ? '?prod=' . $prod : '');
if (!$id || $id < 1) {
  header('Location: ' . $urlListado);
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqlComputo = "SELECT c.id, c.nro, c.nro_computo, c.nro_revision, c.id_tarea,
                      DATE_FORMAT(c.fecha, '%d/%m/%Y') AS fecha,
                      DATE_FORMAT(c.fecha_hora_ultima_modificacion, '%d/%m/%Y %H:%i') AS ultima_modificacion,
                      c.id_estado, ec.estado, c.comentarios_revision,
                      t.observaciones AS tarea_observaciones, tt.tipo AS tipo_tarea,
                      p.id AS id_proyecto, p.nro AS nro_proyecto, p.nombre AS proyecto,
                      s.nro_sitio, s.nro_subsitio,
                      cliente.nombre AS cliente,
                      solicitante.nombre AS solicitante,
                      realizo.nombre AS realizo, reviso.nombre AS reviso, valido.nombre AS valido
               FROM computos c
               INNER JOIN tareas t ON t.id = c.id_tarea
               INNER JOIN tipos_tarea tt ON tt.id = t.id_tipo_tarea
               INNER JOIN proyectos p ON p.id = t.id_proyecto
               INNER JOIN sitios s ON s.id = p.id_sitio
               LEFT JOIN cuentas cliente ON cliente.id = p.id_cliente
               LEFT JOIN cuentas solicitante ON solicitante.id = c.id_cuenta_solicitante
               LEFT JOIN cuentas realizo ON realizo.id = c.id_cuenta_realizo
               LEFT JOIN cuentas reviso ON reviso.id = c.id_cuenta_reviso
               LEFT JOIN cuentas valido ON valido.id = c.id_cuenta_valido
               LEFT JOIN estados_computos ec ON ec.id = c.id_estado
               WHERE c.id = ?";
$q = $pdo->prepare($sqlComputo);
$q->execute([$id]);
$computo = $q->fetch(PDO::FETCH_ASSOC);

if (!$computo) {
  Database::disconnect();
  header('Location: ' . $urlListado);
  exit;
}

$whereHistorial = !empty($computo['nro_computo']) ? 'c.nro_computo = ?' : 'c.id = ?';
$valorHistorial = !empty($computo['nro_computo']) ? $computo['nro_computo'] : $id;
$sqlHistorial = "SELECT c.id, c.nro, c.nro_revision,
                        DATE_FORMAT(c.fecha, '%d/%m/%Y') AS fecha,
                        DATE_FORMAT(c.fecha_hora_revision, '%d/%m/%Y') AS fecha_revision,
                        COALESCE(NULLIF(c.comentarios_revision, ''), 'Emisión original') AS motivo,
                        ec.estado, realizo.nombre AS realizo, reviso.nombre AS reviso, valido.nombre AS valido
                 FROM computos c
                 LEFT JOIN estados_computos ec ON ec.id = c.id_estado
                 LEFT JOIN cuentas realizo ON realizo.id = c.id_cuenta_realizo
                 LEFT JOIN cuentas reviso ON reviso.id = c.id_cuenta_reviso
                 LEFT JOIN cuentas valido ON valido.id = c.id_cuenta_valido
                 WHERE $whereHistorial
                 ORDER BY c.nro_revision ASC, c.id ASC";
$qHistorial = $pdo->prepare($sqlHistorial);
$qHistorial->execute([$valorHistorial]);
$historial = $qHistorial->fetchAll(PDO::FETCH_ASSOC);

$qNotas = $pdo->prepare(
  "SELECT nro_revision, comentarios, DATE_FORMAT(fecha_hora, '%d/%m/%Y %H:%i') AS fecha_hora
   FROM computos_revisiones WHERE id_computo = ? ORDER BY nro_revision, id"
);
$qNotas->execute([$id]);
$notasRevision = $qNotas->fetchAll(PDO::FETCH_ASSOC);

$sqlDetalle = "SELECT cd.id, m.codigo, cat.categoria, m.concepto, um.unidad_medida,
                      cd.cantidad, m.largo, m.peso_metro,
                      DATE_FORMAT(cd.fecha_necesidad, '%d/%m/%Y') AS fecha_necesidad,
                      cd.aprobado, cd.cancelado, cd.reservado, cd.comprado, cd.saldo, cd.comentarios,
                      COALESCE((
                        SELECT SUM(pd.cantidad)
                        FROM pedidos p
                        INNER JOIN pedidos_detalle pd ON pd.id_pedido = p.id
                        WHERE p.id_computo = cd.id_computo AND p.id_estado <> 7 AND pd.cancelado = 0
                          AND (pd.id_computo_detalle = cd.id OR (pd.id_computo_detalle IS NULL AND pd.id_material = cd.id_material))
                      ), 0) AS cantidad_pedida,
                      COALESCE((SELECT SUM(i.saldo) FROM ingresos_detalle i WHERE i.id_material = cd.id_material), 0) AS stock_actual
               FROM computos_detalle cd
               INNER JOIN materiales m ON m.id = cd.id_material
               LEFT JOIN categorias cat ON cat.id = m.id_categoria
               LEFT JOIN unidades_medida um ON um.id = m.id_unidad_medida
               WHERE cd.id_computo = ?
               ORDER BY cd.cancelado, cat.categoria, m.concepto";
$qDetalle = $pdo->prepare($sqlDetalle);
$qDetalle->execute([$id]);
$detalles = $qDetalle->fetchAll(PDO::FETCH_ASSOC);

$sqlPedidos = "SELECT p.id, DATE_FORMAT(p.fecha, '%d/%m/%Y') AS fecha,
                      p.lugar_entrega, p.aprobado, ep.estado,
                      recibe.nombre AS recibe,
                      COUNT(pd.id) AS cantidad_items,
                      COALESCE(SUM(pd.cantidad), 0) AS cantidad_total
               FROM pedidos p
               LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
               LEFT JOIN cuentas recibe ON recibe.id = p.id_cuenta_recibe
               LEFT JOIN pedidos_detalle pd ON pd.id_pedido = p.id AND pd.cancelado = 0
               WHERE p.id_computo = ?
               GROUP BY p.id, p.fecha, p.lugar_entrega, p.aprobado, ep.estado, recibe.nombre
               ORDER BY p.fecha DESC, p.id DESC";
$qPedidos = $pdo->prepare($sqlPedidos);
$qPedidos->execute([$id]);
$pedidos = $qPedidos->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

$escape = static function ($value): string {
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$formatNumber = static function ($value): string {
  return number_format((float)($value ?? 0), 2, ',', '.');
};
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <?php include 'head_tables.php'; ?>
    <style>
      .dato-label { font-weight: 600; color: #495057; }
      .dato-valor { overflow-wrap: anywhere; }
      .detalle-tabla th, .detalle-tabla td { vertical-align: middle; }
      .badge-estado { font-size: 0.9rem; }
    </style>
  </head>
  <body>
    <div class="page-wrapper">
      <?php include 'header.php'; ?>
      <div class="page-body-wrapper">
        <?php include 'menu.php'; ?>
        <div class="page-body">
          <?php $ubicacion = 'Detalle de Cómputo'; include_once 'head_page.php'; ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$escape($ubicacion)?> Nº <?=$escape($computo['nro'])?> — Revisión <?=$escape($computo['nro_revision'])?></h5>
                  </div>
                  <div class="card-body">
                    <h5 class="mb-3">Información general</h5>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group row"><div class="col-sm-4 dato-label">Estado</div><div class="col-sm-8 dato-valor"><span class="badge badge-primary badge-estado"><?=$escape($computo['estado'] ?: 'Sin estado')?></span></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Fecha de emisión</div><div class="col-sm-8 dato-valor"><?=$escape($computo['fecha'])?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Última modificación</div><div class="col-sm-8 dato-valor"><?=$escape($computo['ultima_modificacion'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Tarea</div><div class="col-sm-8 dato-valor"><a href="verTarea.php?id=<?=(int)$computo['id_tarea']?>" target="_blank" rel="noopener"><?=$escape($computo['tipo_tarea'])?> #<?=(int)$computo['id_tarea']?></a><?php if (!empty($computo['tarea_observaciones'])): ?> — <?=$escape($computo['tarea_observaciones'])?><?php endif; ?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Solicitó</div><div class="col-sm-8 dato-valor"><?=$escape($computo['solicitante'] ?: '—')?></div></div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group row"><div class="col-sm-4 dato-label">Proyecto</div><div class="col-sm-8 dato-valor"><a href="verProyecto.php?id=<?=(int)$computo['id_proyecto']?>" target="_blank" rel="noopener"><?=$escape($computo['nro_sitio'])?>-<?=$escape($computo['nro_subsitio'])?>-<?=$escape($computo['nro_proyecto'])?>: <?=$escape($computo['proyecto'])?></a></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Cliente</div><div class="col-sm-8 dato-valor"><?=$escape($computo['cliente'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Realizó</div><div class="col-sm-8 dato-valor"><?=$escape($computo['realizo'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Revisó</div><div class="col-sm-8 dato-valor"><?=$escape($computo['reviso'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Validó</div><div class="col-sm-8 dato-valor"><?=$escape($computo['valido'] ?: '—')?></div></div>
                      </div>
                    </div>
                    <?php if (!empty($computo['comentarios_revision'])): ?>
                      <div class="alert alert-light border"><span class="dato-label">Comentarios de esta revisión:</span> <?=$escape($computo['comentarios_revision'])?></div>
                    <?php endif; ?>

                    <h5 class="mt-4 mb-3">Historial de revisiones</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaHistorial">
                        <thead class="thead-light"><tr><th>Revisión</th><th>Fecha</th><th>Motivo / comentarios</th><th>Estado</th><th>Realizó</th><th>Revisó</th><th>Validó</th></tr></thead>
                        <tbody>
                          <?php foreach ($historial as $revision): ?>
                            <tr>
                              <td><?=$escape($revision['nro_revision'])?></td>
                              <td><?=$escape($revision['fecha_revision'] ?: $revision['fecha'])?></td>
                              <td><?=$escape($revision['motivo'])?></td>
                              <td><?=$escape($revision['estado'] ?: '—')?></td>
                              <td><?=$escape($revision['realizo'] ?: '—')?></td>
                              <td><?=$escape($revision['reviso'] ?: '—')?></td>
                              <td><?=$escape($revision['valido'] ?: '—')?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>

                    <?php if ($notasRevision): ?>
                      <h5 class="mt-4 mb-3">Notas de revisión</h5>
                      <div class="dt-ext table-responsive">
                        <table class="table table-sm table-bordered display detalle-tabla" id="tablaNotasRevision">
                          <thead class="thead-light"><tr><th>Revisión</th><th>Fecha y hora</th><th>Comentarios</th></tr></thead>
                          <tbody><?php foreach ($notasRevision as $nota): ?>
                            <tr><td><?=$escape($nota['nro_revision'])?></td><td><?=$escape($nota['fecha_hora'])?></td><td><?=$escape($nota['comentarios'])?></td></tr>
                          <?php endforeach; ?></tbody>
                        </table>
                      </div>
                    <?php endif; ?>

                    <h5 class="mt-4 mb-3">Materiales y seguimiento</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaMateriales">
                        <thead class="thead-light">
                          <tr><th>Categoría</th><th>Código</th><th>Concepto</th><th>Unidad</th><th>Solicitado</th><th>Necesidad</th><th>Aprobado</th><th>Cancelado</th><th>En stock</th><th>Reservado</th><th>Pedido</th><th>Comprado</th><th>Saldo</th><th>Largo</th><th>Peso/metro</th><th>Observaciones</th></tr>
                        </thead>
                        <tbody>
                          <?php foreach ($detalles as $detalle): ?>
                            <?php $saldoCalculado = max(0, (float)$detalle['cantidad'] - max(0, (float)$detalle['reservado']) - (float)$detalle['cantidad_pedida']); ?>
                            <tr>
                              <td><?=$escape($detalle['categoria'] ?: '—')?></td>
                              <td><?=$escape($detalle['codigo'] ?: '—')?></td>
                              <td><?=$escape($detalle['concepto'])?></td>
                              <td><?=$escape($detalle['unidad_medida'] ?: '—')?></td>
                              <td class="text-right"><?=$formatNumber($detalle['cantidad'])?></td>
                              <td><?=$escape($detalle['fecha_necesidad'])?></td>
                              <td><?=$detalle['aprobado'] ? 'Sí' : 'No'?></td>
                              <td><?=$detalle['cancelado'] ? 'Sí' : 'No'?></td>
                              <td class="text-right"><?=$formatNumber($detalle['stock_actual'])?></td>
                              <td class="text-right"><?=$formatNumber($detalle['reservado'])?></td>
                              <td class="text-right"><?=$formatNumber($detalle['cantidad_pedida'])?></td>
                              <td class="text-right"><?=$formatNumber($detalle['comprado'])?></td>
                              <td class="text-right"><?=$formatNumber($saldoCalculado)?></td>
                              <td class="text-right"><?=$formatNumber($detalle['largo'])?></td>
                              <td class="text-right"><?=$formatNumber($detalle['peso_metro'])?></td>
                              <td><?=$escape($detalle['comentarios'])?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>

                    <h5 class="mt-4 mb-3">Pedidos asociados</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaPedidos">
                        <thead class="thead-light"><tr><th>Nro. Pedido</th><th>Fecha</th><th>Estado</th><th>Destino</th><th>Recibe</th><th>Aprobado</th><th>Ítems</th><th>Cantidad total</th></tr></thead>
                        <tbody>
                          <?php foreach ($pedidos as $pedido): ?>
                            <tr>
                              <td><a href="verPedido.php?id=<?=(int)$pedido['id']?>" target="_blank" rel="noopener" title="Ver Pedido"><i class="fa fa-file-text-o" style="margin-right: 5px;"></i><?=$escape($pedido['id'])?></a></td>
                              <td><?=$escape($pedido['fecha'])?></td>
                              <td><?=$escape($pedido['estado'] ?: '—')?></td>
                              <td><?=$escape($pedido['lugar_entrega'] ?: '—')?></td>
                              <td><?=$escape($pedido['recibe'] ?: '—')?></td>
                              <td><?=$pedido['aprobado'] ? 'Sí' : 'No'?></td>
                              <td class="text-right"><?=$escape($pedido['cantidad_items'])?></td>
                              <td class="text-right"><?=$formatNumber($pedido['cantidad_total'])?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <div class="card-footer">
                    <a href="imprimirComputo.php?id=<?=(int)$computo['id']?>" class="btn btn-primary" target="_blank" rel="noopener">Imprimir</a>
                    <a href="<?=$escape($urlListado)?>" class="btn btn-light">Volver al listado</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include 'footer.php'; ?>
      </div>
    </div>
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
      $(function() {
        const idioma = {
          decimal: "",
          emptyTable: "No hay información",
          info: "Mostrando _START_ a _END_ de _TOTAL_ Registros",
          infoEmpty: "Mostrando 0 a 0 de 0 Registros",
          infoFiltered: "(Filtrado de _MAX_ total registros)",
          lengthMenu: "Mostrar _MENU_ Registros",
          loadingRecords: "Cargando...",
          processing: "Procesando...",
          search: "Buscar:",
          zeroRecords: "No hay resultados",
          paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
        };
        ['#tablaHistorial', '#tablaNotasRevision', '#tablaMateriales', '#tablaPedidos'].forEach(function(selector) {
          if ($(selector).length) {
            $(selector).DataTable({
              stateSave: false,
              responsive: false,
              pageLength: 25,
              lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
              language: idioma
            });
          }
        });
      });
    </script>
  </body>
</html>
