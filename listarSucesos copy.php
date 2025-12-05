<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
require_once 'manejarFiltros.php';

$filters = gestionarFiltros('listarSucesos');

$entidad_tipo = $filters['entidad_tipo'] ?? $_GET['entidad_tipo'] ?? '';
$fecha_desde = $filters['fecha_desde'] ?? "";
$fecha_hasta = $filters['fecha_hasta'] ?? "";
$id_tipo_suceso = $filters['id_tipo_suceso'] ?? [];
$entidad_id = $filters['entidad_id'] ?? "";

// Manejar limpiar filtros
if (isset($_GET['clear_filters'])) {
  // Limpiar filtros en sesión
  if (isset($_SESSION['filtros']['listarSucesos'])) {
    unset($_SESSION['filtros']['listarSucesos']);
  }
  header("Location: listarSucesos.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_tables.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <!-- Page Header Start-->
      <?php include('header.php');?>
      <!-- Page Header Ends-->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion = "Listar Sucesos";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <!-- Filtros superiores -->
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-body">
                    <form class="form-inline theme-form mt-3" method="GET" action="listarSucesos.php">
                      <div class='form-group mb-12' style='width: 100%;'>
                        <div class="form-group mb-0">
                          Entidad:&nbsp;
                          <select name="entidad_tipo" class="js-example-basic-single form-control">
                            <option value="">Todas las entidades</option>
                            <option value="proyectos"<?= $entidad_tipo === 'proyectos' ? ' selected' : '' ?>>Proyectos</option>
                            <option value="pedidos"<?= $entidad_tipo === 'pedidos' ? ' selected' : '' ?>>Pedidos</option>
                            <option value="compras"<?= $entidad_tipo === 'compras' ? ' selected' : '' ?>>Compras</option>
                          </select>
                        </div>
                        <div class="form-group mb-0">
                          ID Entidad:&nbsp;
                          <input type="number" name="entidad_id" class="form-control" size="5" value="<?= htmlspecialchars($entidad_id) ?>" placeholder="ID específico">
                        </div>
                        <div class="form-group mb-0">
                          Rango:&nbsp;
                          <input type="date" name="fecha_desde" class="form-control" size="10" value="<?= htmlspecialchars($fecha_desde) ?>">-
                          <input type="date" name="fecha_hasta" class="form-control" size="10" value="<?= htmlspecialchars($fecha_hasta) ?>">
                        </div>
                        <div class="form-group mb-0">
                          Tipo:&nbsp;
                          <select name="id_tipo_suceso[]" class="js-example-basic-multiple" multiple="multiple"><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sql = "SELECT id, tipo FROM tipos_suceso ORDER BY tipo";
                            $q = $pdo->prepare($sql);
                            $q->execute();
                            while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                              $selected = in_array($row['id'], $id_tipo_suceso) ? ' selected' : '';
                              echo "<option value='{$row['id']}'$selected>{$row['tipo']}</option>";
                            }?>
                          </select>
                        </div>
                        <div class="form-group mb-0">
                          <button type="submit" class="btn btn-primary">Buscar</button>
                          <a href="listarSucesos.php?clear_filters=1" class="btn btn-secondary ml-2">Limpiar</a>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <!-- Listado principal -->
            <div class="row" style='min-width: 100%;'>
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?>&nbsp;&nbsp;
                      <a href="#" id="link_nuevo_suceso"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Agregar Suceso" title="Agregar Suceso"></a>&nbsp;&nbsp;
                      <!-- Exportaciones comentadas para uso futuro
                      <a href="#" id="exportar_excel"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar Excel" title="Exportar Excel"></a>&nbsp;&nbsp;
                      <a href="#" id="exportar_pdf"><img src="img/pdf.png" width="24" height="25" border="0" alt="Exportar PDF" title="Exportar PDF"></a>&nbsp;&nbsp;
                      <a href="#" id="imprimir"><img src="img/print.png" width="24" height="25" border="0" alt="Imprimir" title="Imprimir"></a>&nbsp;&nbsp;
                      -->
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                    
                      <table class="display" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Entidad</th>
                            <th>ID Entidad</th>
                            <th>Tipo Suceso</th>
                            <th>Título</th>
                            <th>Suceso</th>
                            <th>Usuario</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          // Construir la consulta con filtros
                          $where_conditions = [];
                          $params = [];
                          
                          if (!empty($entidad_tipo)) {
                            $where_conditions[] = "s.entidad_tipo = ?";
                            $params[] = $entidad_tipo;
                          }
                          
                          if (!empty($entidad_id)) {
                            $where_conditions[] = "s.entidad_id = ?";
                            $params[] = $entidad_id;
                          }
                          
                          if (!empty($fecha_desde)) {
                            $where_conditions[] = "DATE(s.fecha_hora) >= ?";
                            $params[] = $fecha_desde;
                          }
                          
                          if (!empty($fecha_hasta)) {
                            $where_conditions[] = "DATE(s.fecha_hora) <= ?";
                            $params[] = $fecha_hasta;
                          }
                          
                          if (!empty($id_tipo_suceso)) {
                            $placeholders = str_repeat('?,', count($id_tipo_suceso) - 1) . '?';
                            $where_conditions[] = "s.id_tipo_suceso IN ($placeholders)";
                            $params = array_merge($params, $id_tipo_suceso);
                          }
                          
                          $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
                          
                          $sql = "
                            SELECT 
                              s.id,
                              DATE_FORMAT(s.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_formateada,
                              s.entidad_tipo,
                              s.entidad_id,
                              ts.tipo AS tipo_suceso,
                              s.titulo,
                              s.suceso,
                              u.usuario AS nombre_usuario,
                              s.fecha_hora
                            FROM sucesos s
                            INNER JOIN tipos_suceso ts ON ts.id = s.id_tipo_suceso
                            LEFT JOIN usuarios u ON u.id = s.id_usuario
                            $where_clause
                            ORDER BY s.fecha_hora DESC, s.id DESC
                          ";
                          
                          $q = $pdo->prepare($sql);
                          $q->execute($params);
                          
                          while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                            // Determinar el enlace de la entidad
                            $link_entidad = '#';
                            switch ($row['entidad_tipo']) {
                              case 'proyectos':
                                $link_entidad = "verProyecto.php?id=" . $row['entidad_id'];
                                break;
                              case 'pedidos':
                                $link_entidad = "verPedido.php?id=" . $row['entidad_id'];
                                break;
                              case 'compras':
                                $link_entidad = "verCompra.php?id=" . $row['entidad_id'];
                                break;
                            }?>
                            
                            <tr data-entidad-tipo='<?=htmlspecialchars($row['entidad_tipo'])?>' data-entidad-id='<?=htmlspecialchars($row['entidad_id'])?>'>
                              <td><?=htmlspecialchars($row['id'])?></td>
                              <td><?=htmlspecialchars($row['fecha_formateada'])?></td>
                              <td><?=ucfirst(htmlspecialchars($row['entidad_tipo']))?></td>
                              <td>
                                <a href='<?=$link_entidad?>' target='_blank'>
                                  <i class="fa fa-file-text-o" style="margin-right: 5px;"></i><?=htmlspecialchars($row['entidad_id'])?></a>
                              </td>
                              <td><?=htmlspecialchars($row['tipo_suceso'])?></td>
                              <td><?=htmlspecialchars($row['titulo'])?></td>
                              <td><span class='text-truncate d-inline-block' style='max-width: 300px;' title='<?=htmlspecialchars($row['suceso'])?>'><?=htmlspecialchars($row['suceso'])?></span></td>
                              <td><?=htmlspecialchars($row['nombre_usuario'] ?? 'N/A')?></td>
                            </tr><?php
                          }
                          Database::disconnect();?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Entidad</th>
                            <th>ID Entidad</th>
                            <th>Tipo Suceso</th>
                            <th>Título</th>
                            <th>Suceso</th>
                            <th>Usuario</th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
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
    
    <!-- Scripts -->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    
    <!-- DataTables -->
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
    
    <script>
      $(document).ready(function() {
        // Inicializar Select2
        $('.js-example-basic-single').select2({
          placeholder: "Seleccionar...",
          allowClear: true
        });
        
        $('.js-example-basic-multiple').select2({
          placeholder: "Seleccionar tipos...",
          allowClear: true
        });
        
        // Setup - add a text input to each footer cell
        $('#dataTables-example666 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
        
        // Inicializar DataTable
        $('#dataTables-example666').DataTable({
          stateSave: false,
          responsive: false,
          "dom": 'rtip',
          order: [[1, 'desc']], // Ordenar por fecha descendente
          select: {
            style: 'single'
          },
          columnDefs: [
            { 
              targets: [6], // Columna de suceso
              orderable: false 
            }
          ],
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
          /* Exportaciones e impresión comentadas para uso futuro
          ,dom: 'Bfrtip',
          buttons: [
            {
              extend: 'excel',
              text: 'Exportar Excel',
              className: 'btn btn-success btn-sm'
            },
            {
              extend: 'pdf',
              text: 'Exportar PDF', 
              className: 'btn btn-danger btn-sm'
            },
            {
              extend: 'print',
              text: 'Imprimir',
              className: 'btn btn-info btn-sm'
            }
          ]
          */
        });
        
        // DataTable
        var table = $('#dataTables-example666').DataTable();
        
        // Apply the search
        table.columns().every( function () {
          var that = this;
          
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that
                .search( this.value )
                .draw();
            }
          } );
        } );
        
        // Manejar el botón de nuevo suceso
        $('#link_nuevo_suceso').click(function(e) {
          e.preventDefault();
          
          var selectedRows = table.rows('.selected').data();
          
          if (selectedRows.length === 0) {
            alert('Debe seleccionar una fila para agregar un suceso.');
            return;
          }
          
          var selectedRow = table.row('.selected');
          var entidadTipo = selectedRow.node().getAttribute('data-entidad-tipo');
          var entidadId = selectedRow.node().getAttribute('data-entidad-id');
          
          if (!entidadTipo || !entidadId) {
            alert('Error: No se pudo obtener la información de la fila seleccionada.');
            return;
          }
          
          window.location.href = 'nuevoSuceso.php?entidad_tipo=' + entidadTipo + '&entidad_id=' + entidadId;
        });
        
        // Manejar selección de filas
        $('#dataTables-example666 tbody').on('click', 'tr', function () {
          if ($(this).hasClass('selected')) {
            $(this).removeClass('selected');
          } else {
            table.$('tr.selected').removeClass('selected');
            $(this).addClass('selected');
          }
        });
      });
    </script>
    
    <style>
      .selected {
        background-color: #b0bed9 !important;
      }
    </style>
  </body>
</html>