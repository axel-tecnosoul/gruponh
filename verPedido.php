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
    header("Location: listarPedidos.php");
  }
  
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT pe.`id`, pe.`id_computo`, c.id_tarea, c.id_cuenta_solicitante, pe.`fecha`, pe.`lugar_entrega`, pe.`id_cuenta_recibe`, pe.`aprobado`, pe.`id_estado`, ep.`estado` AS estado_pedido FROM `pedidos` pe INNER JOIN computos c ON c.id = pe.`id_computo` INNER JOIN estados_pedidos ep ON ep.id = pe.id_estado WHERE pe.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  Database::disconnect();
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  <style>
    .form-control:disabled, 
    .form-control[readonly] {
      background-color: #e9ecef;
      opacity: 1;
    }
    
    .form-group {
      margin-bottom: 1rem;
    }
    
    .card-body {
      padding: 1.5rem;
    }
    
    /* Forzar alineación entre thead y tbody */
    #dataTables-example667 {
      width: 100% !important;
      font-size: 0.75rem;
      table-layout: fixed !important;
      border-collapse: collapse !important;
    }
    
    #dataTables-example667 th,
    #dataTables-example667 td {
      padding: 5px 4px !important;
      vertical-align: middle;
      font-size: 0.75rem;
      overflow: hidden;
      text-overflow: ellipsis;
      box-sizing: border-box !important;
    }
    
    /* Headers sin wrap */
    #dataTables-example667 thead th {
      white-space: nowrap !important;
      padding: 6px 4px !important;
      font-size: 0.7rem;
      font-weight: 600;
      line-height: 1.2;
      background-color: #f8f9fa;
    }
    
    /* Anchos EXACTOS para cada columna */
    #dataTables-example667 th:nth-child(1),
    #dataTables-example667 td:nth-child(1) {
      width: 180px !important;
      min-width: 180px !important;
      max-width: 180px !important;
      white-space: normal;
      word-wrap: break-word;
    }
    
    #dataTables-example667 th:nth-child(2),
    #dataTables-example667 td:nth-child(2) {
      width: 85px !important;
      min-width: 85px !important;
      max-width: 85px !important;
    }
    
    #dataTables-example667 th:nth-child(3),
    #dataTables-example667 td:nth-child(3) {
      width: 85px !important;
      min-width: 85px !important;
      max-width: 85px !important;
    }
    
    #dataTables-example667 th:nth-child(4),
    #dataTables-example667 td:nth-child(4) {
      width: 90px !important;
      min-width: 90px !important;
      max-width: 90px !important;
    }
    
    #dataTables-example667 th:nth-child(5),
    #dataTables-example667 td:nth-child(5) {
      width: 90px !important;
      min-width: 90px !important;
      max-width: 90px !important;
    }
    
    #dataTables-example667 th:nth-child(6),
    #dataTables-example667 td:nth-child(6) {
      width: 60px !important;
      min-width: 60px !important;
      max-width: 60px !important;
      text-align: center;
    }
    
    #dataTables-example667 th:nth-child(7),
    #dataTables-example667 td:nth-child(7) {
      width: 65px !important;
      min-width: 65px !important;
      max-width: 65px !important;
      text-align: center;
    }
    
    #dataTables-example667 th:nth-child(8),
    #dataTables-example667 td:nth-child(8) {
      width: 80px !important;
      min-width: 80px !important;
      max-width: 80px !important;
      text-align: center;
    }
    
    #dataTables-example667 th:nth-child(9),
    #dataTables-example667 td:nth-child(9) {
      width: 80px !important;
      min-width: 80px !important;
      max-width: 80px !important;
      text-align: center;
    }

    /* Resto de celdas sin wrap */
    #dataTables-example667 tbody td {
      white-space: nowrap;
    }
    
    /* Excepto Concepto */
    #dataTables-example667 tbody td:nth-child(1) {
      white-space: normal;
    }
    
    /* Importante: Eliminar scrolls de DataTables */
    .dataTables_wrapper .dataTables_scrollHead,
    .dataTables_wrapper .dataTables_scrollBody {
      overflow: visible !important;
    }
    
    .dataTables_wrapper {
      overflow-x: auto;
    }
    
    .dataTables_scrollBody {
      overflow: visible !important;
    }
    
    .dataTables_scrollHead table,
    .dataTables_scrollBody table {
      width: 100% !important;
    }
    
    /* Controles de DataTable más compactos */
    .dataTables_length select,
    .dataTables_filter input {
      font-size: 0.8rem;
      padding: 0.25rem 0.5rem;
    }
    
    .dataTables_info,
    .dataTables_length,
    .dataTables_filter {
      font-size: 0.8rem;
    }
    
    h6 {
      font-weight: 600;
      margin-bottom: 1rem;
    }
    
    /* Reducir espacio en paginación */
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      padding: 0.25rem 0.5rem;
      font-size: 0.8rem;
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
          $ubicacion="Gestión de Pedido de Cómputo y Nueva Orden de Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between w-100">
                      <div>
                        <h5 class="mb-1">Información del Pedido de Cómputo</h5>
                        <span class="badge badge-secondary">Estado: <?=htmlspecialchars($data['estado_pedido']);?></span>
                      </div>
                    </div>
                    <div id="estado-error" class="alert alert-danger mt-3 d-none"></div>
                  </div>
                  <div class="form theme-form" role="presentation" id="form-unificado">
                    <div class="card-body">
                      <div class="row">
                        <div class="col-lg-6 col-md-8 col-sm-12">
                          <h6 class="mb-3">Datos del Pedido de Cómputo</h6>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Fecha Pedido</label>
                            <div class="col-sm-8"><input name="fecha" type="date" onfocus="this.showPicker()" value="<?=$data['fecha'];?>" class="form-control" disabled></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Estado</label>
                            <div class="col-sm-8"><input type="text" class="form-control" value="<?=htmlspecialchars($data['estado_pedido']);?>" disabled></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Proyecto</label>
                            <div class="col-sm-8">
                              <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single w-100" disabled="disabled">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from computos c inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio where c.id = ".$data['id_computo'];
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                  <option value='<?=$fila['id']?>' selected><?=$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Solicitante</label>
                            <div class="col-sm-8">
                              <select name="id_cuenta_solicitante" id="id_cuenta_solicitante" class="js-example-basic-single w-100" disabled>
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_cuenta_solicitante']) {
                                      echo " selected ";
                                    }	
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Lugar de Entrega</label>
                            <div class="col-sm-8"><input name="lugar_entrega" type="text" maxlength="199" class="form-control" value="<?=$data['lugar_entrega'];?>" disabled></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Recibe</label>
                            <div class="col-sm-8">
                              <select name="id_cuenta_recibe" id="id_cuenta_recibe" class="js-example-basic-single w-100" disabled>
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_cuenta_recibe']) {
                                      echo " selected ";
                                    }
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                        </div>
                        
                      </div>

                      <hr class="mt-4 mb-4">

                      <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3">Detalle de Conceptos</h6>
                          <div class="table-responsive">
                          <table class="display" id="dataTables-example667" style="width:100%">
                            <thead>
                              <tr>
                                <th>Concepto</th>
                                <th>Fec. Necesidad</th>
                                <th>Fec. Últ. Compra</th>
                                <th>Último Precio</th>
                                <th>Requerido</th>
                                <th>Stock</th>
                                <th>Reserv.</th>
                                <th>Comprado</th>
                                <th>Pendiente</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                              $pdo = Database::connect();
                              $sql = " SELECT pd.id, m.concepto, pd.cantidad, date_format(pd.fecha_necesidad,'%d/%m/%y'), u.unidad_medida,pd.id_material,pd.reservado,pd.comprado FROM pedidos_detalle pd inner join materiales m on m.id = pd.id_material inner join unidades_medida u on u.id = pd.id_unidad_medida WHERE pd.id_pedido = ".$_GET['id'];
                              
                              foreach ($pdo->query($sql) as $row) {
                                $sql2 = "SELECT d.precio,date_format(c.fecha_emision,'%d/%m/%y') fecha_emision FROM compras_detalle d inner join compras c on c.id = d.id_compra WHERE d.id_material = ".$row[5]." order by c.id desc limit 0,1 ";
                                $q2 = $pdo->prepare($sql2);
                                $q2->execute();
                                $data2 = $q2->fetch(PDO::FETCH_ASSOC);
                                
                                $cantidadDisponible = $row[2] - $row[6] - $row[7];
                                
                                echo '<tr>';
                                echo '<td>'. $row[1] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                if (!empty($data2['fecha_emision'])) {
                                  echo '<td>'. $data2['fecha_emision'] . '</td>';	
                                } else {
                                  echo '<td>&nbsp;</td>';	
                                }
                                if (!empty($data2['precio'])) {
                                  echo '<td>$'. number_format($data2['precio'],2) . '</td>';	
                                } else {
                                  echo '<td>&nbsp;</td>';	
                                }
                                echo '<td>'. $row[2] .' '.$row[4]. '</td>';		
                                
                                $sql = "SELECT SUM(id.saldo) AS disponible FROM ingresos_detalle id WHERE id_material = ? ";
                                $q = $pdo->prepare($sql);
                                $q->execute([$row[5]]);
                                $data3 = $q->fetch(PDO::FETCH_ASSOC);
                                
                                if (empty($data3['disponible'])) {
                                  echo '<td>0</td>';	
                                } else {
                                  echo '<td>'.$data3['disponible'].'</td>';	
                                }
                                
                                echo '<td>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
                                echo '<td>'. $cantidadDisponible . '</td>';
                                
                                echo '</tr>';
                              }
                              Database::disconnect();
                              ?>
                            </tbody>
                          </table>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-12 text-center">
                        <a class="btn btn-primary" target="_blank" href="imprimirPedido.php?id=<?=$data['id']; ?>">Imprimir Pedido</a>
                        <?php if ($data['id_estado'] == 1 && tienePermiso(298)): ?>
                        <button type="button" class="btn btn-primary" id="btnEnviarAprobacion">Enviar a aprobación</button>
                        <?php endif; ?>
                        <a href="#" onclick="document.location.href='listarPedidos.php'" class="btn btn-light">Volver</a>
                      </div>
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
    <!-- Modal Enviar a aprobación -->
    <div class="modal fade" id="modalEnviarAprobacion" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirmar envío a aprobación</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>¿Desea enviar este pedido a aprobación?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="confirmEnviarAprobacion">Confirmar</button>
          </div>
        </div>
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
      $('#dataTables-example667').DataTable({
        stateSave: false,
        responsive: false,
        scrollX: false,
        scrollCollapse: false,
        autoWidth: false,
        paging: true,
        pageLength: 10,
        columnDefs: [
          { width: "180px", targets: 0, orderable: true },
          { width: "85px", targets: 1, orderable: true },
          { width: "85px", targets: 2, orderable: true },
          { width: "90px", targets: 3, orderable: true },
          { width: "90px", targets: 4, orderable: true },
          { width: "60px", targets: 5, orderable: true, className: "text-center" },
          { width: "65px", targets: 6, orderable: true, className: "text-center" },
          { width: "80px", targets: 7, orderable: true, className: "text-center" },
          { width: "80px", targets: 8, orderable: true, className: "text-center" }
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
      });

      var pedidoId = <?=intval($data['id']);?>;

      $('#btnEnviarAprobacion').on('click', function () {
        $('#estado-error').addClass('d-none');
        $('#modalEnviarAprobacion').modal('show');
      });

      $('#modalEnviarAprobacion').on('hidden.bs.modal', function () {
        $('#confirmEnviarAprobacion').prop('disabled', false);
      });

      $('#confirmEnviarAprobacion').on('click', function () {
        var $button = $(this);
        $button.prop('disabled', true);
        $.ajax({
          type: 'POST',
          url: 'modificarEstadoPedido.php',
          data: { idEstado: 2, idPosicion: pedidoId },
          success: function (response) {
            var trimmed = $.trim(response || '');
            var pattern = new RegExp('^2\\s*-\\s*' + pedidoId + '$');
            if (pattern.test(trimmed)) {
              window.location.href = 'listarPedidos.php';
            } else {
              mostrarErrorEstado('No se pudo actualizar el estado. Respuesta inesperada del servidor.');
            }
          },
          error: function () {
            mostrarErrorEstado('No se pudo actualizar el estado. Intente nuevamente.');
          },
          complete: function () {
            $button.prop('disabled', false);
          }
        });
      });

      function mostrarErrorEstado(mensaje) {
        $('#modalEnviarAprobacion').modal('hide');
        var $error = $('#estado-error');
        $error.text(mensaje).removeClass('d-none');
      }
    });

  </script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>