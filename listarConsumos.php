<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_tables.php');?>
	<style>
	.truncate {
	  max-width:50px;
	  white-space: nowrap;
	  overflow: hidden;
	  text-overflow: ellipsis;
	}
  </style>
    <style>
      .faClass{
        width: 24px;
        height: 20px;
        color: midnightblue;
      }
      .editable {
        text-decoration: underline;
        cursor: default;
      }
    </style>
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <!-- Page Header Start-->
      <?php include('header.php');?>
     
      <!-- Page Header Ends                              -->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Consumos";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php
                      echo $ubicacion; 
                      if (!empty(tienePermiso(315))) { ?>
                        &nbsp;
                        <?php
                      }
                      if (!empty(tienePermiso(317))) {
                        echo '<a href="#" id="link_eliminar_consumo"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Cancelar" title="Cancelar"></a>';
                        echo '&nbsp;&nbsp;';
                      }?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="tablaConsumos">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Orden de Trabajo</th>
                          </tr>
                        </thead>
                        <tbody><?php 
                          include 'database.php';
                          $pdo = Database::connect();
                          $sql = "SELECT c.id, date_format(c.fecha,'%d/%m/%y') AS fecha,otr.id_orden_trabajo FROM consumos c INNER JOIN ordenes_trabajo otr ON c.id_orden_trabajo_revision=otr.id WHERE c.anulado = 0";
                          //echo $sql;
                          foreach ($pdo->query($sql) as $row) {
                            echo '<tr>';
                            echo '<td>'.$row["id"].'</td>';
                            echo '<td>'.$row["fecha"].'</td>';
                            echo '<td>'.$row["id_orden_trabajo"].'</td>';
                            echo '</tr>';?>

                            <div class="modal fade" id="eliminarModal_<?=$row["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                              <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                                    <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                  </div>
                                  <div class="modal-body">¿Está seguro que desea anular el consumo?</div>
                                  <div class="modal-footer">
                                    <a href="eliminarConsumo.php?id=<?=$row["id"]; ?>" class="btn btn-primary">Eliminar</a>
                                    <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
                                  </div>
                                </div>
                              </div>
                            </div><?php
                          }
                          Database::disconnect();?>
                        </tbody>
						            <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Orden de Trabajo</th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Zero Configuration  Ends-->
              <!-- Feature Unable /Disable Order Starts-->
            </div>
			      <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Detalle del Consumo
                      &nbsp;&nbsp;
                      <span id="btnModificar" title="Modificar" style="cursor: pointer;"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></span>&nbsp;&nbsp;
                      <span id="link_eliminar_material_consumo" title="Eliminar" style="cursor: pointer;"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></span>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="tablaDetalleConsumo">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Material</th>
                            <th>Colada</th>
                            <th>Situacion</th>
                            <th>Cantidad</th>
                            <th>Medida</th>
                            <th>Observacion</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Material</th>
                            <th>Colada</th>
                            <th>Situacion</th>
                            <th>Cantidad</th>
                            <th>Medida</th>
                            <th>Observacion</th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Zero Configuration  Ends-->
              <!-- Feature Unable /Disable Order Starts-->
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
  
    <div class="modal fade" id="confirmEliminarConsumo" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea eliminar el material del consumo?</div>
          <div class="modal-footer">
            <a href="#" class="btn btn-primary">Eliminar</a>
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <script>
      $(document).ready(function() {

        let datatableBasic={
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
        }

        // Setup - add a text input to each footer cell
        $('#tablaConsumos tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $('#tablaConsumos').DataTable(datatableBasic);
    
        // DataTable
        var table = $('#tablaConsumos').DataTable();
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });
        
        //$('#tablaConsumos').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaConsumos tbody tr td", function(){
          var t=$(this).parent();
          //t.parent().find("tr").removeClass("selected");

          let id_consumo=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            get_detalle_consumo(0)
            $("#link_eliminar_consumo").data("id","");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_detalle_consumo(id_consumo)
            $("#link_eliminar_consumo").data("id",id_consumo);
          }
        });

        get_detalle_consumo(0)
        $('#tablaDetalleConsumo tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $("#link_eliminar_consumo").on("click",function(){
          let id_consumo=$(this).data("id");
          console.log(id_consumo);
          if(id_consumo!="" && id_consumo>0){
            $("#eliminarModal_"+id_consumo).modal("show")
          }else{
            alert("Por favor seleccione un consumo para eliminarlo")
          }
          
        })

        $("#btnModificar").on("click",function(e){
          let id_consumo=$(this).data("id");
          e.preventDefault();
          if(id_consumo!="" && id_consumo>0){
            window.location.href="modificarConsumo.php?id="+id_consumo
          }else{
            alert("Por favor seleccione un consumo para modificarlo")
          }
        })

        $("#link_eliminar_material_consumo").on("click",function(e){
          let id_consumo=$(this).data("id");
          e.preventDefault();
          if(id_consumo!="" && id_consumo>0){
            let modal=$("#confirmEliminarConsumo")
            modal.modal("show")
            modal.find(".modal-footer a").attr("href","eliminarMaterialConsumo.php?id="+id_consumo)
          }else{
            alert("Por favor seleccione un material para eliminarlo")
          }
        })
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    
      function get_detalle_consumo(id_consumo){
        let datosUpdate = new FormData();
        datosUpdate.append('id_consumo', id_consumo);
        $.ajax({
          data: datosUpdate,
          url: 'get_detalle_consumo.php',
          method: "post",
          cache: false,
          contentType: false,
          processData: false,
          //rowsGroup:[0,1],
          //order: [[2, 'asc']],
          /*rowGroup: {
              dataSrc: 0
          },*/
          success: function(data){
            //console.log(data);
            data = JSON.parse(data);
            //console.log(data);

            $('#tablaDetalleConsumo').DataTable().destroy();
            //$('#tablaDetalleConsumo').DataTable(datatableBasic);
            $('#tablaDetalleConsumo').DataTable({
              stateSave: false,
              responsive: false,
              data: data,
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
              },
              "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                console.log(nRow);
                console.log(aData);
                $('td:eq(7)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
              },
              initComplete: function(){
                $('[title]').tooltip();
              }
            });
        
            // DataTable
            var table = $('#tablaDetalleConsumo').DataTable();
            // Apply the search
            table.columns().every( function () {
              var that = this;
              $( 'input', this.footer() ).on( 'keyup change', function () {
                if ( that.search() !== this.value ) {
                  that.search( this.value ).draw();
                }
              });
            });
        
          }
        });
      }

      //$('#tablaDetalleConsumo').find("tbody tr td").on( 'click', function () {
        $(document).on("click","#tablaDetalleConsumo tbody tr td", function(){
          var t=$(this).parent();

          let id_consumo=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#btnModificar").data("id","");
            $("#link_eliminar_material_consumo").data("id","");
          }else{
            //t.parent().find("tr").removeClass("selected");
            $('#tablaDetalleConsumo').DataTable().rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            $("#btnModificar").data("id",id_consumo);
            $("#link_eliminar_material_consumo").data("id",id_consumo);
          }
        });
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>