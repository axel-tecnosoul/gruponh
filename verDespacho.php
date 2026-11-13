<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

require 'database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
  header('Location: listarDespachos.php');
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT des.id, des.detalle_oc, DATE_FORMAT(des.fecha, '%d/%m/%Y') AS fecha,
               des.nro_remito, des.transporte, des.chofer, e.estado,
               p.nombre AS proyecto, p.nro AS nro_proyecto,
               s.nro_sitio, s.nro_subsitio,
               operario.nombre AS operario, cliente.nombre AS cliente
        FROM despachos des
        INNER JOIN estados_despacho e ON e.id = des.id_estado_despacho
        INNER JOIN proyectos p ON p.id = des.id_proyecto
        INNER JOIN sitios s ON s.id = p.id_sitio
        INNER JOIN cuentas operario ON operario.id = des.id_cuenta_operario
        INNER JOIN cuentas cliente ON cliente.id = p.id_cliente
        WHERE des.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$despacho = $q->fetch(PDO::FETCH_ASSOC);

if (empty($despacho)) {
  Database::disconnect();
  header('Location: listarDespachos.php');
  exit;
}

$qBultos = $pdo->prepare(
  "SELECT b.id AS id_bulto, b.numero, b.nombre, b.color, eb.estado AS estado_bulto,
          bd.id AS id_detalle, tb.tipo, bd.id_origen_bulto, bd.id_detalle_bulto, bd.cantidad
   FROM bultos b
   INNER JOIN estados_bulto eb ON eb.id = b.id_estado_bulto
   LEFT JOIN bultos_detalle bd ON bd.id_bulto = b.id
   LEFT JOIN tipos_bulto tb ON tb.id = bd.id_tipo_bulto
   WHERE b.id_despacho = ?
   ORDER BY b.numero, b.id, bd.id"
);
$qBultos->execute([$id]);
$detallesBultos = $qBultos->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

$proyecto = implode('-', array_filter([
  $despacho['nro_sitio'],
  $despacho['nro_subsitio'],
  $despacho['nro_proyecto'],
], static function ($parte) {
  return $parte !== null && $parte !== '';
}));
if ($proyecto !== '' && !empty($despacho['proyecto'])) {
  $proyecto .= ': ' . $despacho['proyecto'];
} elseif ($proyecto === '') {
  $proyecto = (string)$despacho['proyecto'];
}

$escape = static function ($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <?php include 'head_forms.php'; ?>
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      #dataTables-example666 thead th {
        padding-right: 20px;
      }
    </style>
  </head>
  <body>
    <div class="page-wrapper">
      <?php include 'header.php'; ?>
      <div class="page-body-wrapper">
        <?php include 'menu.php'; ?>
        <div class="page-body">
          <?php $ubicacion = 'Ver Despacho'; include_once 'head_page.php'; ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?> #<?=$escape($despacho['id'])?></h5>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Proyecto</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($proyecto)?></div></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Cliente</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['cliente'])?></div></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">OCC</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['detalle_oc'])?></div></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Fecha</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['fecha'])?></div></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Operario</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['operario'])?></div></div>
                        </div>
                      </div>
                      <div class="col-lg-6">
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Nro. Remito</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['nro_remito'])?></div></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Transporte</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['transporte'])?></div></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Chofer</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['chofer'])?></div></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-4 col-form-label">Estado</label>
                          <div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['estado'])?></div></div>
                        </div>
                      </div>
                    </div>

                    <h5 class="mt-3">Bultos y detalles</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th>Número de bulto</th>
                            <th>Nombre</th>
                            <th>Color</th>
                            <th>Estado</th>
                            <th>Tipo de detalle</th>
                            <th>Origen</th>
                            <th>ID detalle origen</th>
                            <th>Cantidad</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if (!$detallesBultos): ?>
                            <tr><td colspan="8" class="text-center text-muted">Este despacho no tiene bultos.</td></tr>
                          <?php else: foreach ($detallesBultos as $detalle): ?>
                            <tr>
                              <td><?=$escape($detalle['numero'])?></td>
                              <td><?=$escape($detalle['nombre'])?></td>
                              <td><?=$escape($detalle['color'])?></td>
                              <td><?=$escape($detalle['estado_bulto'])?></td>
                              <td><?=$escape($detalle['tipo'] ?? '—')?></td>
                              <td><?=$escape($detalle['id_origen_bulto'] ?? '—')?></td>
                              <td><?=$escape($detalle['id_detalle_bulto'] ?? '—')?></td>
                              <td><?=$escape($detalle['cantidad'] ?? '—')?></td>
                            </tr>
                          <?php endforeach; endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <div class="card-footer">
                    <a href="listarDespachos.php" class="btn btn-light">Volver</a>
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
    <script src="assets/js/script.js"></script>
    <script>
      $(document).ready(function() {
        $('#dataTables-example666').DataTable({
          stateSave: false,
          responsive: false,
          pageLength: 25,
          lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
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
            "zeroRecords": "No hay resultados",
            "paginate": {
              "first": "Primero",
              "last": "Ultimo",
              "next": "Siguiente",
              "previous": "Anterior"
            }
          }
        });
      });
    </script>
  </body>
</html>
