<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
require_once 'manejarFiltros.php';

$filters = gestionarFiltros('listarSucesos');

// Priorizar POST para formularios, GET solo para precarga desde otros lugares
$entidad_tipo = $filters['entidad_tipo'] ?? $_POST['entidad_tipo'] ?? $_GET['entidad_tipo'] ?? '';
$fecha_desde = $filters['fecha_desde'] ?? $_POST['fecha_desde'] ?? $_GET['fecha_desde'] ?? "";
$fecha_hasta = $filters['fecha_hasta'] ?? $_POST['fecha_hasta'] ?? $_GET['fecha_hasta'] ?? "";
$id_tipo_suceso = $filters['id_tipo_suceso'] ?? $_POST['id_tipo_suceso'] ?? $_GET['id_tipo_suceso'] ?? [];
$entidad_id = $filters['entidad_id'] ?? $_POST['entidad_id'] ?? $_GET['entidad_id'] ?? "";
$id_proyecto = $filters['id_proyecto'] ?? $_POST['id_proyecto'] ?? $_GET['id_proyecto'] ?? "";

// Manejar limpiar filtros
if (isset($_GET['clear_filters'])) {
  // Limpiar filtros en sesión
  if (isset($_SESSION['filtros']['listarSucesos'])) {
    unset($_SESSION['filtros']['listarSucesos']);
  }
  header("Location: listarSucesos.php");
  exit;
}

function pluralToSingular(string $word): string {
  // casos simples: terminados en "es"
  if (preg_match('/(ces)$/', $word)) {
    // luces -> luz, veces -> vez
    return preg_replace('/ces$/', 'z', $word);
  }

  if (preg_match('/(es)$/', $word)) {
    // perros -> perro, meses -> mes
    return preg_replace('/es$/', '', $word);
  }

  // terminados en "s"
  if (preg_match('/s$/', $word)) {
    // casas -> casa
    return preg_replace('/s$/', '', $word);
  }

  return $word; // fallback: no se modifica
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
                    <form class="form-inline theme-form mt-3" method="POST" action="listarSucesos.php">
                      <div class='form-group mb-12' style='width: 100%;'>
                        <!-- Filtro rápido por proyecto -->
                        <div class="row" id="filtro-proyecto" style="width: 100%; padding: 10px; background: #f8f9fa; border-radius: 5px; margin-bottom: 15px;">
                          <strong style="align-content: center;" class="col-2">🎯 Filtro Rápido por Proyecto:</strong>&nbsp;
                          <select name="id_proyecto" id="select-proyecto" class="js-example-basic-single form-control col-8">
                            <option value="">Seleccionar proyecto...</option><?php
                            $pdo = Database::connect();
                            $sql = "SELECT DISTINCT p.id, CONCAT(s.nro_sitio, '/', s.nro_subsitio, '/', p.nro, ' - ', p.descripcion) as proyecto_completo FROM proyectos p LEFT JOIN sitios s ON s.id = p.id_sitio ORDER BY s.nro_sitio, p.nro";
                            foreach ($pdo->query($sql) as $row) {
                              $selected = ($row['id'] == $id_proyecto) ? ' selected' : '';?>
                              <option value="<?= $row['id'] ?>"<?= $selected ?>><?= htmlspecialchars($row['proyecto_completo']) ?></option><?php
                            }
                            Database::disconnect();?>
                          </select>
                          <button type="button" id="limpiar-proyecto" class="btn btn-sm btn-outline-secondary ml-2 col-1" style="display: none;">✕ Limpiar</button>
                        </div>
                        
                        <!-- Filtros detallados -->
                        <!-- <div id="filtros-detallados" style="<?= !empty($id_proyecto) ? 'display: none;' : '' ?>"> -->
                          <div class="form-group mb-0">
                            Entidad:&nbsp;
                            <select name="entidad_tipo" id="select-entidad" class="js-example-basic-single form-control">
                              <option value="">Todas las entidades</option>
                              <option value="proyectos"<?= $entidad_tipo === 'proyectos' ? ' selected' : '' ?>>Proyectos</option>
                              <option value="pedidos"<?= $entidad_tipo === 'pedidos' ? ' selected' : '' ?>>Pedidos</option>
                              <option value="compras"<?= $entidad_tipo === 'compras' ? ' selected' : '' ?>>Compras</option>
                            </select>
                          </div>
                          <div class="form-group mb-0" id="grupo-entidad-id" style="<?= empty($entidad_tipo) ? 'display: none;' : '' ?>">
                            <label id="label-entidad-id">ID Entidad:</label>&nbsp;
                            <input type="number" name="entidad_id" id="input-entidad-id" class="form-control" style="width: 180px;" size="5" value="<?= htmlspecialchars($entidad_id) ?>" placeholder="ID específico">
                          </div>
                        <!-- </div> -->
                        <div class="form-group mb-0">
                          Rango:&nbsp;
                          <input type="date" name="fecha_desde" class="form-control" size="10" value="<?= htmlspecialchars($fecha_desde) ?>">-
                          <input type="date" name="fecha_hasta" class="form-control" size="10" value="<?= htmlspecialchars($fecha_hasta) ?>">
                        </div>
                        <div class="form-group mb-0">
                          Tipo:&nbsp;
                          <select name="id_tipo_suceso[]" class="js-example-basic-multiple" multiple="multiple" data-placeholder="Seleccionar tipos..."><?php
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
                    </form><?php
                    
                    /*if (!empty($id_proyecto)){?>
                    <div class="alert alert-info mt-3" role="alert">
                      <strong>🎯 Mostrando todos los eventos del proyecto seleccionado</strong><br>
                      Incluye: sucesos directos, sucesos de pedidos y sucesos de compras relacionados a este proyecto.
                    </div><?php
                    } */?>
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
                            <!-- <th>Entidad</th>
                            <th>ID Entidad</th> -->
                            <th>Origen</th>
                            <th>Tipo Suceso</th>
                            <th>Título</th>
                            <th>Suceso</th>
                            <th>Usuario</th>
                          </tr>
                        </thead>
                        <tbody><?php
                          // Construir la consulta con filtros
                          $where_conditions = [];
                          $params = [];
                          
                          // Filtro por proyecto (prioritario)
                          /*if (!empty($id_proyecto)) {
                            // UNION de consultas para todos los eventos relacionados al proyecto
                            $sql = "
                              (SELECT 
                                s.id,
                                DATE_FORMAT(s.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_formateada,
                                s.entidad_tipo,
                                s.entidad_id,
                                ts.tipo AS tipo_suceso,
                                s.titulo,
                                s.suceso,
                                u.usuario AS nombre_usuario,
                                s.fecha_hora,
                                'Directo del proyecto' as origen
                              FROM sucesos s
                              INNER JOIN tipos_suceso ts ON ts.id = s.id_tipo_suceso
                              LEFT JOIN usuarios u ON u.id = s.id_usuario
                              WHERE s.entidad_tipo = 'proyectos' AND s.entidad_id = ?)
                              
                              UNION ALL
                              
                              (SELECT 
                                s.id,
                                DATE_FORMAT(s.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_formateada,
                                s.entidad_tipo,
                                s.entidad_id,
                                ts.tipo AS tipo_suceso,
                                s.titulo,
                                s.suceso,
                                u.usuario AS nombre_usuario,
                                s.fecha_hora,
                                'Pedido del proyecto' as origen
                              FROM sucesos s
                              INNER JOIN tipos_suceso ts ON ts.id = s.id_tipo_suceso
                              LEFT JOIN usuarios u ON u.id = s.id_usuario
                              INNER JOIN pedidos pe ON pe.id = s.entidad_id AND s.entidad_tipo = 'pedidos'
                              LEFT JOIN computos co ON co.id = pe.id_computo
                              LEFT JOIN tareas t ON t.id = co.id_tarea
                              WHERE (t.id_proyecto = ? OR pe.id_proyecto = ?))
                              
                              UNION ALL
                              
                              (SELECT 
                                s.id,
                                DATE_FORMAT(s.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_formateada,
                                s.entidad_tipo,
                                s.entidad_id,
                                ts.tipo AS tipo_suceso,
                                s.titulo,
                                s.suceso,
                                u.usuario AS nombre_usuario,
                                s.fecha_hora,
                                'Compra del proyecto' as origen
                              FROM sucesos s
                              INNER JOIN tipos_suceso ts ON ts.id = s.id_tipo_suceso
                              LEFT JOIN usuarios u ON u.id = s.id_usuario
                              INNER JOIN compras c ON c.id = s.entidad_id AND s.entidad_tipo = 'compras'
                              INNER JOIN pedidos pe ON pe.id = c.id_pedido
                              LEFT JOIN computos co ON co.id = pe.id_computo
                              LEFT JOIN tareas t ON t.id = co.id_tarea
                              WHERE (t.id_proyecto = ? OR pe.id_proyecto = ?))";
                            
                            $params = [$id_proyecto, $id_proyecto, $id_proyecto, $id_proyecto, $id_proyecto];
                            
                            // Aplicar filtros adicionales si existen
                            $having_conditions = [];
                            if (!empty($fecha_desde)) {
                              $having_conditions[] = "DATE(fecha_hora) >= ?";
                              $params[] = $fecha_desde;
                            }
                            if (!empty($fecha_hasta)) {
                              $having_conditions[] = "DATE(fecha_hora) <= ?";
                              $params[] = $fecha_hasta;
                            }
                            if (!empty($id_tipo_suceso)) {
                              $placeholders = str_repeat('?,', count($id_tipo_suceso) - 1) . '?';
                              $having_conditions[] = "id_tipo_suceso IN ($placeholders)";
                              $params = array_merge($params, $id_tipo_suceso);
                            }
                            
                            if (!empty($having_conditions)) {
                              $sql = "SELECT * FROM ($sql) as eventos WHERE " . implode(" AND ", $having_conditions);
                            }
                            
                            $sql .= " ORDER BY fecha_hora DESC";
                          } else {*/

                            if (!empty($id_proyecto)) {
                              $where_conditions[] = " s.id_proyecto = ? ";
                              $params[] = $id_proyecto;
                            }

                            // Filtros tradicionales
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
                            
                            $sql = "SELECT 
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
                              ORDER BY s.fecha_hora DESC, s.id DESC";
                          //}
                          
                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          
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
                            }
                            
                            $origen=ucfirst(pluralToSingular(htmlspecialchars($row['entidad_tipo'])))." Nro ".$row['entidad_id']?>
                            
                            <tr data-entidad-tipo='<?=htmlspecialchars($row['entidad_tipo'])?>' data-entidad-id='<?=htmlspecialchars($row['entidad_id'])?>'>
                              <td><?=htmlspecialchars($row['id'])?></td>
                              <td><?=htmlspecialchars($row['fecha_formateada'])?></td>
                              <!-- <td><?=ucfirst(htmlspecialchars($row['entidad_tipo']))?></td>
                              <td>
                                <a href='<?=$link_entidad?>' target='_blank'>
                                  <i class="fa fa-file-text-o" style="margin-right: 5px;"></i><?=htmlspecialchars($row['entidad_id'])?></a>
                              </td> -->
                              <td>
                                <a href='<?=$link_entidad?>' target='_blank'>
                                  <i class="fa fa-file-text-o" style="margin-right: 5px;"></i><?=$origen?>
                                </a>
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
                            <!-- <th>Entidad</th>
                            <th>ID Entidad</th> -->
                            <th>Origen</th>
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
              that.search( this.value ).draw();
            }
          });
        });
        
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
      
      // Manejo del filtro por proyecto y labels dinámicos
      $(document).ready(function() {
        // Función para actualizar label y visibilidad según tipo de entidad
        function actualizarLabelEntidad() {
          var tipoEntidad = $('#select-entidad').val();
          var label = $('#label-entidad-id');
          var input = $('#input-entidad-id');
          var grupo = $('#grupo-entidad-id');
          
          if (tipoEntidad) {
            // Mostrar el input cuando hay una entidad seleccionada
            grupo.show();
            
            switch(tipoEntidad) {
              case 'proyectos':
                label.text('Proyecto Nro:');
                input.attr('placeholder', 'Número de proyecto');
                break;
              case 'pedidos':
                label.text('Pedido Nro:');
                input.attr('placeholder', 'Número de pedido');
                break;
              case 'compras':
                label.text('Compra Nro:');
                input.attr('placeholder', 'Número de compra');
                break;
            }
          } else {
            // Ocultar el input cuando no hay entidad seleccionada
            grupo.hide();
            input.val(''); // Limpiar el valor
          }
        }
        
        // Evento para cambio de tipo de entidad
        $('#select-entidad').on('change', actualizarLabelEntidad);
        
        // Inicializar label al cargar la página
        actualizarLabelEntidad();
        
        // Manejo del filtro por proyecto
        $('#select-proyecto').on('change', function() {
          var proyectoSeleccionado = $(this).val();
          
          if (proyectoSeleccionado) {
            // Ocultar filtros detallados y mostrar botón limpiar
            //$('#filtros-detallados').hide();
            $('#limpiar-proyecto').show();
          } else {
            // Mostrar filtros detallados y ocultar botón limpiar
            //$('#filtros-detallados').show();
            $('#limpiar-proyecto').hide();
          }
        });
        
        // Botón para limpiar filtro por proyecto
        $('#limpiar-proyecto').on('click', function() {
          $('#select-proyecto').val('').trigger('change');
          //$('#filtros-detallados').show();
          $(this).hide();
        });
        
        // Estado inicial
        var proyectoInicial = $('#select-proyecto').val();
        if (proyectoInicial) {
          $('#limpiar-proyecto').show();
        }
      });
    </script>
    
    <style>
      .selected {
        background-color: #b0bed9 !important;
      }
    </style>
  </body>
</html>