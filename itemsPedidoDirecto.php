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

if (!empty($_POST)) {
    
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if (isset($_POST['btn_modificar'])) {
      $id_pedido_detalle = $_POST['id_pedido_detalle'];

      $sql = "UPDATE pedidos_detalle SET id_material = ?, cantidad = ?, fecha_necesidad = ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST['id_material'], $_POST['cantidad'], $_POST['fecha_necesidad'], $id_pedido_detalle]);
      
      $sql_log = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Se ha modificado un item (ID: $id_pedido_detalle) de un pedido','Pedidos','verPedido.php?id=$id')";
      $q_log = $pdo->prepare($sql_log);
      $q_log->execute(array($_SESSION['user']['id']));
      
      Database::disconnect();
      header("Location: itemsPedidoDirecto.php?id=".$id);
      exit();
  }else {
    $sql = "SELECT id FROM pedidos_detalle where id_pedido = ? and id_material = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id, $_POST['id_material']]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if (empty($data)) {
      $sql_um = "SELECT id_unidad_medida FROM materiales where id = ?";
      $q_um = $pdo->prepare($sql_um);
      $q_um->execute([$_POST['id_material']]);
      $data_um = $q_um->fetch(PDO::FETCH_ASSOC);

      $sql_insert = "INSERT INTO pedidos_detalle (id_pedido, id_material, cantidad, fecha_necesidad, id_unidad_medida) VALUES (?,?,?,?,?)";
      $q_insert = $pdo->prepare($sql_insert);
      $q_insert->execute([$id, $_POST['id_material'], $_POST['cantidad'], $_POST['fecha_necesidad'], $data_um['id_unidad_medida']]);
      
      $sql_log = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Se ha agregado un nuevo item a un pedido','Pedidos','verPedido.php?id=$id')";
      $q_log = $pdo->prepare($sql_log);
      $q_log->execute(array($_SESSION['user']['id']));
      
      Database::disconnect();
      if (!empty($_POST['btn2'])) {
        header("Location: listarPedidos.php");	
      } else {
        header("Location: itemsPedidoDirecto.php?id=".$id);	
      }
    } else {
      header("Location: itemsPedidoDirecto.php?id=".$id."&error=1");	
    }
  }
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
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
          $ubicacion="Ver/Añadir Items Pedido Directo N° ".$id;
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>
                      <?=$ubicacion?>
                      &nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(291))){?>
                        <img src="img/icon_modificar.png" id="link_modificar_item" style="cursor: pointer;" width="24" height="25" border="0" alt="Modificar" title="Modificar">&nbsp;&nbsp;
                        
                        <a href="#" id="link_eliminar_item"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a><?php
                      }?>
                    </h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="itemsPedidoDirecto.php?id=<?=$id?>">
                    <input type="hidden" name="id_pedido_detalle" id="id_pedido_detalle" value="">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-sm-12">
                              <table class="display" id="dataTables-example667">
                                <thead>
                                  <tr>
                                    <th>Concepto</th>
                                    <th>Cantidad</th>
                                    <th>Fecha Necesidad</th>
                                  </tr>
                                </thead>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  $sql = " SELECT d.id, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y') AS fecha_formateada, d.fecha_necesidad AS fecha_necesidad_raw, d.id_material FROM pedidos_detalle d inner join materiales m on m.id = d.id_material WHERE d.id_pedido = ".$_GET['id'];
                                  foreach ($pdo->query($sql) as $row) {?>
                                    <tr data-id="<?=$row['id']?>" data-material-id="<?=$row['id_material']?>" data-cantidad="<?=$row['cantidad']?>" data-fecha="<?=$row['fecha_necesidad_raw']?>" data-concepto="<?=htmlspecialchars($row['concepto'])?>">
                                      <td><?=$row['concepto']?></td>
                                      <td><?=$row['cantidad']?></td>
                                      <td><span style="display: none;"><?=$row['fecha_necesidad_raw']?></span><?=$row['fecha_formateada']?></td>
                                    </tr><?php
                                  }
                                  Database::disconnect();?>
                                </tbody>
                                <tfoot>
                                  <tr>
                                    <th>Concepto</th>
                                    <th>Cantidad</th>
                                    <th>Fecha Necesidad</th>
                                  </tr>
                                </tfoot>
                              </table>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Concepto(*)</label>
                            <div class="col-sm-9">
                              <select name="id_material" id="id_material" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, concepto, codigo FROM materiales WHERE anulado = 0 ";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                  <option value='<?=$fila['id']?>'";><?=$fila['concepto']?> (<?=$fila['codigo']?>)</option><?php
                                }
                                Database::disconnect();?>
                              </select><?php
                              if (isset($_GET['error'])) { ?>
                                <div class="checkbox p-0">
                                  <b><font color='red'>No se puede agregar un concepto repetido!</font></b>
                                </div><?php
                              }?>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9">
                              <input name="cantidad" step="0.01" min="0.01" type="number" class="form-control" required="required" value="">
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Necesidad(*)</label>
                            <div class="col-sm-9">
                              <input name="fecha_necesidad" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="">
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-success" type="submit" value="1" name="btn1">Crear y Agregar Otro</button>
                        <button class="btn btn-primary" type="submit" value="2" name="btn2">Crear y Volver al Listado</button>
                        <button class="btn btn-success d-none" type="submit" name="btn_modificar">Modificar Item</button>
                        <button class="btn btn-light d-none" type="button" id="btn_cancelar_modificacion">Cancelar</button>
                        <a href='listarPedidos.php' class="btn btn-danger">Volver al Listado</a>
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
    
    <!-- Modal único para eliminar items -->
    <div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="eliminarModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="eliminarModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">
            ¿Está seguro que desea quitar el ítem "<span id="item-concepto"></span>" del pedido?
          </div>
          <div class="modal-footer">
            <a href="#" id="btn-confirmar-eliminar" class="btn btn-primary">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
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
	  <!-- Plugins JS start-->
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
      // Setup - add a text input to each footer cell
      $('#dataTables-example667 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      } );
      
      $('#dataTables-example667').DataTable({
        stateSave: false,
        responsive: false,
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
 
      // DataTable
      var table = $('#dataTables-example667').DataTable();

      // Apply the search
      table.columns().every( function () {
        var that = this;
        $('input', this.footer()).on('keyup change', function () {
          if ( that.search() !== this.value ) {
            that.search( this.value ).draw();
          }
        });
      });
      
      var table = $('#dataTables-example667').DataTable();
      
      $('#dataTables-example667 tbody').on('click', 'tr', function () {
          var fila = $(this);

          if (fila.hasClass('selected')) {
            fila.removeClass('selected');
          } else {
            table.$('tr.selected').removeClass('selected');
            fila.addClass('selected');
          }
        });

        $('#link_modificar_item').on('click', function() {
          var filaSeleccionada = table.row('.selected').node();
          if (!filaSeleccionada) {
            alert("Por favor, seleccione un ítem para modificar.");
            return;
          }
          var $fila = $(filaSeleccionada);
          
          $('#id_pedido_detalle').val($fila.data('id'));
          $('#id_material').val($fila.data('material-id')).trigger('change');
          $('input[name="cantidad"]').val($fila.data('cantidad'));
          $('input[name="fecha_necesidad"]').val($fila.data('fecha'));

          $('button[name="btn1"], button[name="btn2"]').addClass('d-none');
          $('button[name="btn_modificar"], #btn_cancelar_modificacion').removeClass('d-none');
          
          document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
        });
    
        $('#btn_cancelar_modificacion').on('click', function() {
          $('#id_pedido_detalle').val('');
          $('#id_material').val('').trigger('change');
          $('input[name="cantidad"]').val('');
          $('input[name="fecha_necesidad"]').val('');
          $('button[name="btn1"], button[name="btn2"]').removeClass('d-none');
          $('button[name="btn_modificar"], #btn_cancelar_modificacion').addClass('d-none');
        });

        $('#link_eliminar_item').on('click', function(e) {
          e.preventDefault();
          var filaSeleccionada = table.row('.selected').node();
          if (!filaSeleccionada) {
            alert("Por favor, seleccione un ítem para eliminar.");
            return;
          }
          var $fila = $(filaSeleccionada);
          var id_item = $fila.data('id');
          var concepto = $fila.data('concepto');
          
          // Actualizar el modal con los datos del item seleccionado
          $('#item-concepto').text(concepto);
          $('#btn-confirmar-eliminar').attr('href', 'eliminarItemPedidoDirecto.php?id=' + id_item + '&idPedido=<?=$id?>');
          
          $('#eliminarModal').modal('show');
        });
      });
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>