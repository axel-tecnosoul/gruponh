<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
require_once 'manejarFiltros.php';
$filters = gestionarFiltros('listarFacturasVenta');
$nro = $filters['nro'] ?? '';
$fecha = $filters['fecha'] ?? '';
$fechah = $filters['fechah'] ?? '';
$cliente = $filters['cliente'] ?? '';
$tipo_letra = $filters['tipo_letra'] ?? '';
$id_estado = $filters['id_estado'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?php include('head_tables.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <style>
    .truncate {
      max-width: 120px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .faClass {
      width: 24px;
      height: 20px;
      color: midnightblue;
    }

  </style>
</head>

<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body">
        <?php $ubicacion = "Facturas Venta";
        include_once("head_page.php"); ?>

        <div class="container-fluid">

          <!-- Filtros -->
          <div class="row">
            <div class="col-md-12">
              <div class="card">
                <div class="card-body">
                  <form class="form-inline theme-form mt-3" name="form1" method="post" action="listarFacturasVenta.php">
                    <div class="form-group mb-0">
                      Nro:&nbsp;<input class="form-control" size="3" type="text"
                        value="<?= htmlspecialchars($nro) ?>" name="nro">
                    </div>
                    <div class="form-group mb-0">
                      Rango:&nbsp;
                      <input class="form-control" size="20" type="date"
                        value="<?= htmlspecialchars($fecha) ?>" name="fecha">
                      &nbsp;-&nbsp;
                      <input class="form-control" size="20" type="date"
                        value="<?= htmlspecialchars($fechah) ?>" name="fechah">
                    </div>
                    <div class="form-group mb-0">
                      Cliente:&nbsp;<input class="form-control" size="20" type="text"
                        value="<?= htmlspecialchars($cliente) ?>" name="cliente">
                    </div>
                    <div class="form-group mb-0">
                      Tipo:&nbsp;
                      <select name="tipo_letra" class="form-control">
                        <option value="">Todos</option>
                        <?php
                        $pdoTL = Database::connect();
                        $qTL = $pdoTL->prepare("SELECT DISTINCT CONCAT(tc.tipo, ' ', lc.letra) as tipo_letra FROM facturas_venta fv INNER JOIN tipos_comprobante tc ON tc.id = fv.id_tipo_comprobante INNER JOIN letras_comprobante lc ON lc.id = fv.id_letra_comprobante WHERE 1 ORDER BY tipo_letra");
                        $qTL->execute();
                        while ($filaTL = $qTL->fetch(PDO::FETCH_ASSOC)) {
                          echo "<option value='".$filaTL['tipo_letra']."'";
                          if ($tipo_letra === $filaTL['tipo_letra']) echo " selected";
                          echo ">".$filaTL['tipo_letra']."</option>";
                        }
                        Database::disconnect();
                        ?>
                      </select>
                    </div>
                    <div class="form-group mb-0">
                      Estado:&nbsp;
                      <select name="id_estado[]" id="id_estado" class="js-example-basic-multiple" multiple="multiple">
                        <option value="">Todos</option>
                        <?php
                        $pdo = Database::connect();
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $q = $pdo->prepare("SELECT id, estado FROM estados_factura ORDER BY id ASC");
                        $q->execute();
                        while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                          $sel = in_array($fila['id'], $id_estado) ? ' selected' : '';
                          echo "<option value='{$fila['id']}'$sel>{$fila['estado']}</option>";
                        }
                        Database::disconnect();
                        ?>
                      </select>
                    </div>
                    <div class="form-group mb-0">
                      <button class="btn btn-primary" type="submit">Buscar</button>
                      <a href="listarFacturasVenta.php?clear_filters=1" class="btn btn-secondary ml-2">Limpiar</a>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabla principal -->
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5>
                    <?= $ubicacion ?>
                    <?php if (!empty(tienePermiso(337))): ?>
                      &nbsp;<a href="#" id="btn_nueva_factura" title="Nueva Factura">
                        <img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva">
                      </a>
                    <?php endif; ?>
                    &nbsp;
                    <?php if (!empty(tienePermiso(339))): ?>
                      <a href="#" id="link_modificar_fv" title="Modificar/Anular">
                        <img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar/Anular">
                      </a>&nbsp;
                    <?php endif; ?>
                    <?php if (!empty(tienePermiso(339))): ?>
                      <a href="#" id="link_pagar_fv" title="Marcar Pagada">
                        <img src="img/tratoHecho.png" width="24" height="25" border="0" alt="Marcar Pagada">
                      </a>&nbsp;
                    <?php endif; ?>
                    <?php if (!empty(tienePermiso(339))): ?>
                      <a href="#" id="link_ver_fv" title="Ver Factura">
                        <img src="img/eye.png" width="24" height="15" border="0" alt="Ver Factura">
                      </a>&nbsp;
                    <?php endif; ?>
                    <?php if (!empty(tienePermiso(337))): ?>
                      <?php /* <a href="#" id="link_nuevo_detalle_fv" title="Añadir ítem Detalle">
                        <img src="img/venc.jpg" width="24" height="25" border="0" alt="Añadir ítem Detalle">
                      </a>&nbsp;
                      <a href="#" id="link_nuevo_retencion_fv" title="Añadir Retenciones">
                        <img src="img/edit3.png" width="24" height="25" border="0" alt="Añadir Retenciones">
                      </a>&nbsp; */ ?>
                    <?php endif; ?>
                    <?php if (!empty(tienePermiso(343))): ?>
                      <a href="#" id="link_exportar_fv" title="Exportar">
                        <img src="img/xls.png" width="24" height="25" border="0" alt="Exportar">
                      </a>&nbsp;
                      <a href="#" id="link_exportar_bejerman_fv" title="Bejerman TXT">
                        <img src="img/import.png" width="24" height="25" border="0" alt="Bejerman TXT">
                      </a>&nbsp;
                    <?php endif; ?>
                  </h5>
                </div>
                <div class="card-body">
                  <div class="dt-ext table-responsive">
                    <table class="display truncate" id="dataTables-example666">
                      <thead>
                        <tr>
                          <th style="width:1%; white-space:nowrap"><input type="checkbox" id="select-all-fv"></th>
                          <th class="d-none">ID</th>
                          <th>Tipo</th>
                          <th>Número</th>
                          <th>Cliente</th>
                          <th>Fecha</th>
                          <th>Condición</th>
                          <th>Total s/Imp</th>
                          <th>IVA</th>
                          <th>Otros</th>
                          <th>Total Neto</th>
                          <th>Observación</th>
                          <th>Estado</th>
                          <th>Exportada</th>
                          <th>Pagada</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $hasSearched = isset($_SESSION['filtros_listarFacturasVenta']);
                        if ($hasSearched):
                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $sql = "SELECT fv.id, fv.descripcion, tc.tipo, lc.letra, fv.numero,
                                         c.nombre, DATE_FORMAT(fv.fecha_emitida,'%d/%m/%y'), fp.forma_pago,
                                          fv.subtotal_no_gravado, m.moneda,
                                          ef.estado,
                                          fv.iva, fv.otros, fv.total,
                                          DATE_FORMAT(fv.fecha_emitida,'%y%m%d'),
                                          ef.id, fv.pagada, fv.exportada, fv.observaciones
                                  FROM facturas_venta fv
                                  INNER JOIN tipos_comprobante   tc  ON tc.id  = fv.id_tipo_comprobante
                                  INNER JOIN letras_comprobante  lc  ON lc.id  = fv.id_letra_comprobante
                                  INNER JOIN cuentas             c   ON c.id   = fv.id_cuenta_destino
                                  INNER JOIN formas_pago         fp  ON fp.id  = fv.id_condicion_pago
                                  INNER JOIN monedas             m   ON m.id   = fv.id_moneda
                                  INNER JOIN estados_factura     ef  ON ef.id  = fv.id_estado
                                  WHERE 1=1";
                          $params = [];
                          if (!empty($nro)) {
                            $sql .= " AND fv.numero = ?";
                            $params[] = $nro;
                          }
                          if (!empty($fecha)) {
                            $sql .= " AND fv.fecha_emitida >= ?";
                            $params[] = $fecha;
                          }
                          if (!empty($fechah)) {
                            $sql .= " AND fv.fecha_emitida <= ?";
                            $params[] = $fechah;
                          }
                          if (!empty($cliente)) {
                            $sql .= " AND c.nombre LIKE ?";
                            $params[] = '%' . $cliente . '%';
                          }
                          if (!empty($tipo_letra)) {
                            $sql .= " AND CONCAT(tc.tipo, ' ', lc.letra) = ?";
                            $params[] = $tipo_letra;
                          }
                          if (!empty($id_estado[0])) {
                            $placeholders = implode(',', array_fill(0, count($id_estado), '?'));
                            $sql .= " AND ef.id IN ($placeholders)";
                            $params = array_merge($params, $id_estado);
                          }
                          $q = $pdo->prepare($sql);
                          $q->execute($params);
                          while ($row = $q->fetch(PDO::FETCH_NUM)) {
                            echo '<tr data-id-estado="' . (int)$row[15] . '" data-pagada="' . (int)$row[16] . '" data-exportada="' . (int)$row[17] . '">';
                            echo '<td class="text-center"><input type="checkbox" class="chk-factura" value="' . $row[0] . '"></td>';
                            echo '<td class="d-none">' . $row[0] . '</td>';
                            echo '<td>' . htmlspecialchars($row[2]) . ' ' . htmlspecialchars($row[3]) . '</td>';
                            echo '<td>' . htmlspecialchars($row[4]) . '</td>';
                            echo '<td>' . htmlspecialchars($row[5]) . '</td>';
                            echo '<td><span style="display:none;">' . $row[14] . '</span>' . htmlspecialchars($row[6]) . '</td>';
                            echo '<td>' . htmlspecialchars($row[7]) . '</td>';
                            echo '<td class="text-right">' . htmlspecialchars($row[9]) . ' ' . number_format($row[8]  ?? 0, 2) . '</td>';
                            echo '<td class="text-right">' . htmlspecialchars($row[9]) . ' ' . number_format($row[11] ?? 0, 2) . '</td>';
                            echo '<td class="text-right">' . htmlspecialchars($row[9]) . ' ' . number_format($row[12] ?? 0, 2) . '</td>';
                            echo '<td class="text-right">' . htmlspecialchars($row[9]) . ' ' . number_format($row[13] ?? 0, 2) . '</td>';
                            echo '<td>' . htmlspecialchars($row[18]) . '</td>';
                            echo '<td>' . htmlspecialchars($row[10]) . '</td>';
                            echo '<td class="text-center">' . ($row[17] ? 'Sí' : 'No') . '</td>';
                            echo '<td class="text-center">' . ((int)$row[16] ? 'Sí' : 'No') . '</td>';
                            echo '</tr>';
                          }
                          Database::disconnect();
                        endif;
                        ?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th style="width:1%"></th>
                          <th class="d-none">ID</th>
                          <th>Tipo</th>
                          <th>Número</th>
                          <th>Cliente</th>
                          <th>Fecha</th>
                          <th>Condición</th>
                          <th>Total s/Imp</th>
                          <th>IVA</th>
                          <th>Otros</th>
                          <th>Total Neto</th>
                          <th>Observación</th>
                          <th>Estado</th>
                          <th>Exportada</th>
                          <th>Pagada</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Detalles de Factura -->
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5>
                    Detalles de Factura&nbsp;&nbsp;
                    <?php if (!empty(tienePermiso(339))): ?>
                      <a href="#" id="link_modificar_detalle_fv" title="Modificar">
                        <img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar">
                      </a>&nbsp;
                      <a href="#" id="link_eliminar_detalle_fv" title="Eliminar">
                        <img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar">
                      </a>&nbsp;
                    <?php endif; ?>
                  </h5>
                </div>
                <div class="card-body">
                  <div class="dt-ext table-responsive">
                    <table class="display truncate" id="dataTables-example667">
                      <thead>
                        <tr>
                          <th class="d-none">ID</th>
                          <th>Descripción</th>
                          <th>Precio</th>
                          <th>Cantidad</th>
                          <th>Subtotal</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                      <tfoot>
                        <tr>
                          <th class="d-none">ID</th>
                          <th>Descripción</th>
                          <th>Precio</th>
                          <th>Cantidad</th>
                          <th>Subtotal</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /container-fluid -->
      </div><!-- /page-body -->
      <?php include("footer.php"); ?>
    </div>
  </div>

  <div class="modal fade" id="modalExportarFV" tabindex="-1" role="dialog" aria-labelledby="modalExportarFVLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalExportarFVLabel">Exportar facturas de venta</h5>
          <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body">
          <p>Hay <strong id="cantSeleccionadosFV">0</strong> facturas seleccionadas.</p>
          <p>¿Qué desea exportar?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="btnExportarSeleccionadosFV">Exportar seleccionados</button>
          <button type="button" class="btn btn-secondary" id="btnExportarTodosFV">Exportar todos</button>
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalElegirProyecto" tabindex="-1" role="dialog" aria-labelledby="modalElegirProyectoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalElegirProyectoLabel">Seleccionar Proyecto para Nueva Factura</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="pasoProyecto">
            <div class="form-group">
              <input type="text" id="buscarProyecto" class="form-control" placeholder="Filtrar proyectos...">
            </div>
            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
              <table class="table table-hover table-sm" id="tablaProyectos">
                <thead class="thead-light">
                  <tr>
                    <th>Obra</th>
                    <th>Nombre</th>
                    <th>Cliente</th>
                    <!-- <th>Sitio</th> -->
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $pdo = Database::connect();
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                  $sql = "SELECT p.id, p.nro, p.nombre, p.solicitante,
                               s.nro_sitio, s.nro_subsitio, s.id_empresa,
                               COALESCE(cu.nombre, p.solicitante) AS nombre_cliente
                        FROM proyectos p
                        INNER JOIN sitios s ON s.id = p.id_sitio
                        LEFT JOIN cuentas cu ON cu.id = p.id_cliente
                        WHERE p.anulado = 0
                        ORDER BY p.nro DESC";
                  $q = $pdo->prepare($sql);
                  $q->execute();
                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                    $codigo = $fila['nro_sitio'] . '_' . $fila['nro_subsitio'] . '_' . $fila['nro'];
                    echo '<tr class="fila-proyecto" style="cursor:pointer;"
                               data-id="' . $fila['id'] . '"
                               data-codigo="' . htmlspecialchars($codigo) . '"
                               data-nombre="' . htmlspecialchars($fila['nombre']) . '"
                               data-id-empresa="' . intval($fila['id_empresa']) . '"
                               data-id-cliente="' . intval($fila['id'] ?? 0) . '">';
                    echo '<td>' . htmlspecialchars($codigo) . '</td>';
                    echo '<td>' . htmlspecialchars($fila['nombre']) . '</td>';
                    echo '<td>' . htmlspecialchars($fila['nombre_cliente']) . '</td>';
                    // echo '<td>' . $fila['nro_sitio'] . '</td>';
                    echo '</tr>';
                  }
                  Database::disconnect();
                  ?>
                </tbody>
              </table>
            </div>
          </div>

          <div id="pasoCertificados" style="display:none;">
            <div class="d-flex align-items-center mb-3">
              <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="btnVolverProyectos">
                &larr; Volver
              </button>
              <div>
                <strong id="labelProyectoSeleccionado"></strong><br>
                <small class="text-muted">Seleccione uno o más certificados para vincular a la factura (opcional)</small>
              </div>
            </div>
            <div id="listaCertificados" style="max-height: 360px; overflow-y: auto;">
              <div class="text-center py-3"><i>Cargando certificados...</i></div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btnContinuarConCertificados" style="display:none;">
            Continuar con certificados seleccionados
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modales de confirmación eliminación detalles -->
  <?php
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $q = $pdo->prepare("SELECT id, id_factura_venta FROM facturas_venta_detalle WHERE 1");
  $q->execute();
  while ($row = $q->fetch(PDO::FETCH_ASSOC)):
  ?>
    <div class="modal fade" id="eliminarModalDetalle_<?= $row['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea eliminar el ítem de detalle?</div>
          <div class="modal-footer">
            <a href="eliminarDetalleFacturaVenta.php?id=<?= $row['id'] ?>&fv=<?= $row['id_factura_venta'] ?>" class="btn btn-danger">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal">Cancelar</button>
          </div>
        </div>
      </div>
    </div>
  <?php endwhile;
  Database::disconnect(); ?>



  <!-- Scripts -->
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
  <script src="assets/js/select2/select2.full.min.js"></script>
  <script src="assets/js/select2/select2-custom.js"></script>

  <script>
    $(document).ready(function() {

      // ── DataTable facturas (666) ─────────────────────────────────────
      $('#dataTables-example666 tfoot th').each(function(i) {
        if (i === 0) return;
        var t = $(this).text();
        $(this).html('<input type="text" size="' + t.length + '" placeholder="' + t + '" />');
      });

      var table = $('#dataTables-example666').DataTable({
        stateSave: false,
        responsive: false,
        dom: 'Bfrtp<"bottom"l>',
        buttons: ['excel'],
        lengthMenu: [
          [10, 25, 50, 100, 500, 1000],
          [10, 25, 50, 100, 500, 1000]
        ],
        language: dtLang(),
        columnDefs: [
          { targets: 0, width: '1%', className: 'dt-center' },
          { targets: 1, className: 'd-none' }
        ]
      });

      // ── DataTable detalles (667) ─────────────────────────────────────
      $('#dataTables-example667 tfoot th').each(function() {
        var t = $(this).text();
        $(this).html('<input type="text" size="' + t.length + '" placeholder="' + t + '" />');
      });

      var table3 = $('#dataTables-example667').DataTable({
        stateSave: false,
        responsive: false,
        columnDefs: [{
          targets: [0],
          className: 'd-none'
        }],
        language: dtLang()
      });

      $('#btn_nueva_factura').on('click', function(e) {
        e.preventDefault();
        $('#pasoProyecto').show();
        $('#pasoCertificados').hide();
        $('#btnContinuarConCertificados').hide();
        $('#buscarProyecto').val('');
        $('.fila-proyecto').show();
        $('#modalElegirProyecto').modal('show');
      });

      $('#buscarProyecto').on('keyup', function() {
        var val = $(this).val().toLowerCase();
        $('.fila-proyecto').each(function() {
          $(this).toggle($(this).text().toLowerCase().indexOf(val) !== -1);
        });
      });

      $('#btnVolverProyectos').on('click', function() {
        $('#pasoCertificados').hide();
        $('#btnContinuarConCertificados').hide();
        $('#pasoProyecto').show();
      });

      var idProyectoActual = null;
      $(document).on('click', '.fila-proyecto', function() {
        idProyectoActual = $(this).data('id');
        var nombreProyecto = $(this).data('codigo') + ' — ' + $(this).data('nombre');
        $('#labelProyectoSeleccionado').text(nombreProyecto);
        $('#listaCertificados').html('<div class="text-center py-3"><i>Cargando certificados...</i></div>');
        $('#pasoProyecto').hide();
        $('#pasoCertificados').show();
        $('#btnContinuarConCertificados').show();

        $.ajax({
          url: 'get_certificados_proyecto.php',
          method: 'POST',
          data: {
            id_proyecto: idProyectoActual
          },
          dataType: 'json',
          success: function(certificados) {
            if (!certificados || certificados.length === 0) {
              $('#listaCertificados').html(
                '<div class="alert alert-info mb-0">Este proyecto no tiene certificados registrados.<br>' +
                'Puede continuar sin seleccionar certificados.</div>'
              );
              return;
            }
            var html = '<div class="list-group">';
            $.each(certificados, function(i, cert) {
              var certId = 'cert_' + cert.id;
              var badgeClass = cert.aprobado == 1 ? 'badge-success' : 'badge-secondary';
              var badgeText = cert.aprobado == 1 ? 'Aprobado' : 'Pendiente';
              html += '<div class="list-group-item p-0 border-0 mb-1">';
              html += '<div class="d-flex align-items-center p-2 bg-light rounded" style="cursor:pointer;" data-toggle="collapse" data-target="#collapse_' + cert.id + '">';
              html += '<input type="checkbox" class="chk-certificado mr-2" id="' + certId + '" value="' + cert.id + '" onclick="event.stopPropagation()">';
              html += '<label for="' + certId + '" class="mb-0 mr-2 font-weight-bold text-dark" style="cursor:pointer;" onclick="event.stopPropagation()">Cert. #' + cert.id + '</label>';
              html += '<span class="badge ' + badgeClass + ' mr-2">' + badgeText + '</span>';
              html += '<small class="text-dark mr-auto">Rev. ' + htmlEsc(cert.revision) + ' | ' + htmlEsc(cert.fecha_emision) + '</small>';
              html += '<small class="text-dark ml-2">$ ' + parseFloat(cert.monto_total).toLocaleString('es-AR', {
                minimumFractionDigits: 2
              }) + '</small>';
              html += '<i class="feather icon-chevron-down ml-2"></i>';
              html += '</div>';
              // Panel colapsable con detalles
              html += '<div id="collapse_' + cert.id + '" class="collapse">';
              html += '<div class="p-2 border rounded mt-1 bg-white">';
              if (cert.detalles && cert.detalles.length > 0) {
                html += '<table class="table table-sm table-bordered mb-0">';
                html += '<thead class="thead-light"><tr>';
                html += '<th>Descripción</th><th class="text-right">Cant. Ant.</th><th class="text-right">Cant. Act.</th><th class="text-right">Precio U.</th><th class="text-right">Subtotal</th>';
                html += '</tr></thead><tbody>';
                $.each(cert.detalles, function(j, det) {
                  html += '<tr>';
                  html += '<td>' + htmlEsc(det.descripcion) + '</td>';
                  html += '<td class="text-right">' + parseFloat(det.cantidad_anterior).toLocaleString('es-AR', {
                    minimumFractionDigits: 2
                  }) + '</td>';
                  html += '<td class="text-right">' + parseFloat(det.cantidad_actual).toLocaleString('es-AR', {
                    minimumFractionDigits: 2
                  }) + '</td>';
                  html += '<td class="text-right">$ ' + parseFloat(det.precio_unitario).toLocaleString('es-AR', {
                    minimumFractionDigits: 2
                  }) + '</td>';
                  html += '<td class="text-right">$ ' + parseFloat(det.subtotal).toLocaleString('es-AR', {
                    minimumFractionDigits: 2
                  }) + '</td>';
                  html += '</tr>';
                });
                html += '</tbody></table>';
              } else {
                html += '<small class="text-muted">Sin detalles disponibles.</small>';
              }
              if (cert.observaciones) {
                html += '<div class="mt-1"><small><strong>Obs.:</strong> ' + htmlEsc(cert.observaciones) + '</small></div>';
              }
              html += '</div></div>';
              html += '</div>';
            });
            html += '</div>';
            $('#listaCertificados').html(html);
          },
          error: function() {
            $('#listaCertificados').html('<div class="alert alert-danger">Error al cargar certificados.</div>');
          }
        });
      });

      $(document).on('click', '[data-target^="#collapse_"]', function() {
        $(this).find('.icon-chevron-down, .icon-chevron-up')
          .toggleClass('icon-chevron-down icon-chevron-up');
      });

      $('#btnContinuarConCertificados').on('click', function() {
        var certificados = [];
        $('.chk-certificado:checked').each(function() {
          certificados.push($(this).val());
        });
        var url = 'nuevaFacturaVenta.php?id_proyecto=' + idProyectoActual;
        if (certificados.length > 0) {
          url += '&certificados[]=' + certificados.join('&certificados[]=');
        }
        $('#modalElegirProyecto').modal('hide');
        window.location.href = url;
      });

      function htmlEsc(str) {
        if (!str) return '';
        return String(str)
          .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      }

      $(document).on('click', '#dataTables-example666 tbody tr td', function() {
        var t = $(this).parent();
        var id_fv = t.find('td:nth-child(2)').html();
        var id_estado = parseInt(t.data('id-estado'));
        var pagada = parseInt(t.data('pagada'));
        var exportada = parseInt(t.data('exportada'));

        if (t.hasClass('selected')) {
          t.removeClass('selected');
          get_detalles(id_fv);
          resetLinks();
        } else {
          table.rows().nodes().each(function(rowNode) {
            $(rowNode).removeClass('selected');
          });
          t.addClass('selected');
          get_detalles(id_fv);
          if (id_estado !== 3 && pagada !== 1 && exportada !== 1) {
            $('#link_modificar_fv').attr('href', 'nuevaFacturaVenta.php?id=' + id_fv);
            $('#link_nuevo_detalle_fv').attr('href', 'nuevoDetalleFacturaVenta.php?id=' + id_fv);
            $('#link_nuevo_retencion_fv').attr('href', 'nuevaRetencionFacturaVenta.php?id=' + id_fv);
          } else {
            $('#link_modificar_fv').attr('href', '#');
            $('#link_nuevo_detalle_fv').attr('href', '#');
            $('#link_nuevo_retencion_fv').attr('href', '#');
          }
          if (id_estado === 3 && pagada !== 1) {
            $('#link_pagar_fv').attr('href', '#').data('id-fv', id_fv);
          } else {
            $('#link_pagar_fv').attr('href', '#').data('id-fv', '');
          }
          $('#link_ver_fv').attr('href', 'verFacturaVenta.php?id=' + id_fv);
        }
      });

      function resetLinks() {
        $('#link_modificar_fv, #link_nuevo_detalle_fv, #link_nuevo_retencion_fv, #link_pagar_fv, #link_ver_fv').attr('href', '#');
      }

      $("#link_modificar_fv").on("click", function(e) {
        var l = document.location.href;
        if (this.href == l || this.href == l + "#") {
          if ($('#dataTables-example666 tbody tr.selected').length === 0) {
            alert("Por favor seleccione una Factura de Venta para modificar");
          } else {
            var exp = $('#dataTables-example666 tbody tr.selected').data('exportada');
            var pag = $('#dataTables-example666 tbody tr.selected').data('pagada');
            var motivo = exp ? 'exportada' : (pag ? 'pagada' : 'definitiva');
            alert("Esta factura ya fue " + motivo + " y no puede editarse.");
          }
          e.preventDefault();
        }
      });
      $("#link_ver_fv").on("click", function(e) {
        var l = document.location.href;
        if (this.href == l || this.href == l + "#") {
          if ($('#dataTables-example666 tbody tr.selected').length === 0) {
            alert("Por favor seleccione una Factura de Venta para ver");
          }
          e.preventDefault();
        }
      });
      $("#link_nuevo_detalle_fv").on("click", function(e) {
        var l = document.location.href;
        if (this.href == l || this.href == l + "#") {
          if ($('#dataTables-example666 tbody tr.selected').length === 0) {
            alert("Por favor seleccione una Factura de Venta para añadir ítem de detalle");
          } else {
            var exp = $('#dataTables-example666 tbody tr.selected').data('exportada');
            var pag = $('#dataTables-example666 tbody tr.selected').data('pagada');
            var motivo = exp ? 'exportada' : (pag ? 'pagada' : 'definitiva');
            alert("Esta factura ya fue " + motivo + " y no puede editarse.");
          }
          e.preventDefault();
        }
      });
      $("#link_nuevo_retencion_fv").on("click", function(e) {
        var l = document.location.href;
        if (this.href == l || this.href == l + "#") {
          if ($('#dataTables-example666 tbody tr.selected').length === 0) {
            alert("Por favor seleccione una Factura de Venta para añadir retención");
          } else {
            var exp = $('#dataTables-example666 tbody tr.selected').data('exportada');
            var pag = $('#dataTables-example666 tbody tr.selected').data('pagada');
            var motivo = exp ? 'exportada' : (pag ? 'pagada' : 'definitiva');
            alert("Esta factura ya fue " + motivo + " y no puede editarse.");
          }
          e.preventDefault();
        }
      });

      $('#link_pagar_fv').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id-fv');
        if (!id) {
          alert('Seleccione una factura definitiva no pagada.');
          return;
        }
        if (confirm('¿Está seguro que desea marcar esta factura como pagada?')) {
          window.location.href = 'pagarFacturaVenta.php?id=' + id;
        }
      });

      $(document).on('click', '#dataTables-example667 tbody tr td', function() {
        var t = $(this).parent();
        var id_det = t.find('td:first-child').html();

        if (t.hasClass('selected')) {
          t.removeClass('selected');
          $('#link_modificar_detalle_fv').attr('href', '#');
          $('#link_eliminar_detalle_fv').removeAttr('data-toggle data-target');
        } else {
          table2.DataTable().rows().nodes().each(function(r) {
            $(r).removeClass('selected');
          });
          t.addClass('selected');
          $('#link_modificar_detalle_fv').attr('href', 'modificarDetalleFacturaVenta.php?id=' + id_det);
          $('#link_eliminar_detalle_fv').attr('data-toggle', 'modal').attr('data-target', '#eliminarModalDetalle_' + id_det);
        }
      });

      $('#link_modificar_detalle_fv, #link_eliminar_detalle_fv').on('click', function() {
        if (!$(this).attr('data-target') && (this.href || '').replace(/#$/, '') === window.location.href.replace(/#$/, '')) {
          alert('Por favor seleccione un detalle primero');
        }
      });

      $('#select-all-fv').on('change', function() {
        var checked = this.checked;
        $('.chk-factura').each(function() {
          if (!$(this).prop('disabled')) $(this).prop('checked', checked);
        });
      });

      $('#link_exportar_fv').on('click', function(e) {
        e.preventDefault();
        var ids = $('.chk-factura:checked').map(function() { return $(this).val(); }).get();
        if (ids.length === 0) {
          window.open('exportFacturasVenta.php', '_blank');
          return;
        }
        $('#modalExportarFVLabel').text('Exportar facturas de venta (Excel)');
        $('#cantSeleccionadosFV').text(ids.length);
        $('#modalExportarFV').modal('show');
        $('#btnExportarSeleccionadosFV').off('click').on('click', function() {
          window.open('exportFacturasVenta.php?ids=' + ids.join(','), '_blank');
          $('#modalExportarFV').modal('hide');
        });
        $('#btnExportarTodosFV').off('click').on('click', function() {
          window.open('exportFacturasVenta.php', '_blank');
          $('#modalExportarFV').modal('hide');
        });
      });

      $('#link_exportar_bejerman_fv').on('click', function(e) {
        e.preventDefault();
        var ids = $('.chk-factura:checked').map(function() { return $(this).val(); }).get();
        if (ids.length === 0) {
          window.open('exportFacturasVentaBejerman.php', '_blank');
          return;
        }
        $('#modalExportarFVLabel').text('Exportar facturas de venta (Bejerman)');
        $('#cantSeleccionadosFV').text(ids.length);
        $('#modalExportarFV').modal('show');
        $('#btnExportarSeleccionadosFV').off('click').on('click', function() {
          window.open('exportFacturasVentaBejerman.php?ids=' + ids.join(','), '_blank');
          $('#modalExportarFV').modal('hide');
        });
        $('#btnExportarTodosFV').off('click').on('click', function() {
          window.open('exportFacturasVentaBejerman.php', '_blank');
          $('#modalExportarFV').modal('hide');
        });
      });

      $(document).on('click', '#dataTables-example666 tbody .chk-factura', function(e) {
        e.stopPropagation();
      });

    });

    var table2 = $('#dataTables-example667');

    function get_detalles(id_fv) {
      var fd = new FormData();
      fd.append('id_fv', id_fv);
      $.ajax({
        url: 'get_detalles_factura_venta.php',
        method: 'POST',
        data: fd,
        cache: false,
        contentType: false,
        processData: false,
        success: function(raw) {
          var data = JSON.parse(raw);
          table2.DataTable().destroy();
          table2.DataTable({
            stateSave: false,
            responsive: false,
            columnDefs: [{
              targets: [0],
              className: 'd-none'
            }],
            data: data,
            language: dtLang()
          });
        }
      });
    }

    function dtLang() {
      return {
        decimal: '',
        emptyTable: 'No hay información',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Mostrando 0 de 0 registros',
        infoFiltered: '(filtrado de _MAX_ total)',
        lengthMenu: 'Mostrar _MENU_ registros',
        loadingRecords: 'Cargando...',
        processing: 'Procesando...',
        search: 'Buscar:',
        zeroRecords: 'No hay resultados',
        paginate: {
          first: 'Primero',
          last: 'Último',
          next: 'Siguiente',
          previous: 'Anterior'
        }
      };
    }
  </script>
</body>

</html>