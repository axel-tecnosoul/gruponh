<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
?>
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
          $ubicacion="Ordenes de Compra Clientes ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarOrdenesCompraClientes.php">
					  <div class="form-group mb-0">
						Nro:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro">
					  </div>
					  <div class="form-group mb-0">
						Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fecha'])) echo $_POST['fecha'] ?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fechah'])) echo $_POST['fechah'] ?>" name="fechah">
					  </div>
					  <div class="form-group mb-0">
						Cliente:&nbsp;<input class="form-control" size="20" type="text" value="<?php if (isset($_POST['cliente'])) echo $_POST['cliente'] ?>" name="cliente">
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarOrdenesCompraClientes.php'">Buscar</button>
					  </div>
					</form>
				</div>
			  </div>
			</div>
			</div>
			<div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php
                      echo $ubicacion; 
                      if (!empty(tienePermiso(370))) { ?>
                        &nbsp;
                        <a href="nuevaOrdenCompraCliente.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva"></a>&nbsp;&nbsp;<?php
                      }
                      echo '<a href="exportOrdenCompraClientes.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;';
                      if (!empty(tienePermiso(371))) {
                        echo '<a href="#" id="link_modificar_ot"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      ?>

                      <a href="#" id="link_ver_occ"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>
                      <a href="#" id="link_nuevo_consumo" title="Nuevo Certificado Maestro"><i style="width: 72px; height: 20px;color: midnightblue;" class='fa fa-lg fa-certificate'>CM</i></a>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="tablaOCC">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <!-- <th>Sitio</th>
                            <th>Subsitio</th> -->
                            <th>Numero</th>
                            <th>Fecha emision</th>
                            <th>Fecha recepcion</th>
                            <th>Cliente</th>
                            <th>Monto</th>
                            <th>Forma de Pago</th>
                            <th>Presupuesto</th>
							<th>Activa</th>
                          </tr>
                        </thead>
                        <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <!-- <th>Sitio</th>
                            <th>Subsitio</th> -->
                            <th>Numero</th>
                            <th>Fecha emision</th>
                            <th>Fecha recepcion</th>
                            <th>Cliente</th>
                            <th>Monto</th>
                            <th>Forma de Pago</th>
                            <th>Presupuesto</th>
							<th>Activa</th>
                          </tr>
                        </tfoot>
                        <tbody><?php 
                          if (!empty($_POST)) {
                          $pdo = Database::connect();
                          $sql = "SELECT occ.id,occ.numero,date_format(occ.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(occ.fecha_recepcion,'%d/%m/%y') AS fecha_recepcion,c.nombre AS cliente,occ.monto,m.moneda,occ.id_condicion_iva,occ.percepcion,occ.otros_importes,fp.forma_pago,CONCAT(p.nro,'/',p.nro_revision) AS presupuesto,occ.requiere_polizas,occ.abierta,date_format(occ.fecha_vencimiento,'%d/%m/%y') AS fecha_vencimiento,date_format(occ.fecha_entrega,'%d/%m/%y') AS fecha_entrega,occ.lugar_entrega,occ.observaciones,occ.monto_total_certificados,occ.monto_total_facturados, occ.activa FROM occ INNER JOIN cuentas c ON occ.id_cuenta_cliente=c.id INNER JOIN monedas m ON occ.id_moneda=m.id INNER JOIN formas_pago fp ON occ.id_forma_pago=fp.id INNER JOIN presupuestos p ON occ.id_presupuesto=p.id WHERE  1";
                          
							if (!empty($_POST['nro'])) {
								$sql .= " AND occ.numero = '".$_POST['nro']."' ";
							}
							if (!empty($_POST['fecha'])) {
								$sql .= " AND occ.fecha_emision >= '".$_POST['fecha']."' ";
							}
							if (!empty($_POST['fechah'])) {
								$sql .= " AND occ.fecha_emision <= '".$_POST['fechah']."' ";
							}
							if (!empty($_POST['cliente'])) {
								$sql .= " AND c.nombre like '%".$_POST['cliente']."%' ";
							}
						  
                          foreach ($pdo->query($sql) as $row) {
                            echo '<tr>';
                            echo '<td class="d-none">'.$row["id"].'</td>';
                            echo '<td>'.$row["numero"].'</td>';
                            echo '<td>'.$row["fecha_emision"].'</td>';
                            echo '<td>'.$row["fecha_recepcion"].'</td>';
                            echo '<td>'.$row["cliente"].'</td>';
                            echo '<td>'.$row["moneda"]." ".number_format($row["monto"],2).'</td>';
                            echo '<td>'.$row["forma_pago"].'</td>';
                            echo '<td>'. $row["presupuesto"] . '</td>';
							if ($row["activa"]==1)
							echo '<td>Si</td>'; else echo '<td>No</td>'; 
                            echo '</tr>';?>

                            <!-- <div class="modal fade" id="eliminarModal_<?=$row["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                              <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                                    <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                  </div>
                                  <div class="modal-body">¿Está seguro que desea cancelar la Lista de Corte?</div>
                                  <div class="modal-footer">
                                    <a href="eliminarOrdenTrabajo.php?id=<?=$row["id"]; ?>" class="btn btn-primary">Eliminar</a>
                                    <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
                                  </div>
                                </div>
                              </div>
                            </div> --><?php
                          }
                          Database::disconnect();
						  }
						  ?>
                        </tbody>
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
                    <h5>Detalle de la Orden de Compra
                      <!-- &nbsp;&nbsp;
                      <span id="btnAbrirModalModificarCantidades" title="Modificar Cantidades" style="cursor: pointer;"><i class='faClass fa fa-lg fa-cogs'></i></span>&nbsp;&nbsp; -->
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="tablaDetalleOCC">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Descripcion</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Descuento</th>
                            <th>Subtotal</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Descripcion</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Descuento</th>
                            <th>Subtotal</th>
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

        // Setup - add a text input to each footer cell
        $('#tablaOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $('#tablaOCC').DataTable({
          stateSave: false,
		  searching: false,
		  
          responsive: false,
		  dom: 'Bfrtp<"bottom"l>',
        buttons: [
            'excel'
        ],
		lengthMenu: [
        [10, 25, 50, 100, 500, 1000], // Cantidades de registros disponibles
        [10, 25, 50, 100, 500, 1000]  // Texto mostrado en el menú desplegable
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
    
        // DataTable
        var table = $('#tablaOCC').DataTable();
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });
        
        //$('#tablaOCC').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaOCC tbody tr td", function(){
          var t=$(this).parent();

          let id_occ=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            get_detalle_orden_compra_clientes(0)

            $("#link_modificar_ot").attr("href","#");
            $("#link_nuevo_consumo").attr("href","#");
            $("#link_ver_occ").attr("href","#");
          }else{
            //t.parent().find("tr").removeClass("selected");
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_detalle_orden_compra_clientes(id_occ)
            
            $("#link_modificar_ot").attr("href","modificarOrdenCompraCliente.php?id="+id_occ);
            $("#link_nuevo_consumo").attr("href","nuevoCertificadoMaestro.php?id_occ="+id_occ);
            $("#link_ver_occ").attr("href","verOrdenCompraCliente.php?id="+id_occ);
          }
        });

        get_detalle_orden_compra_clientes(0)

        $('#tablaDetalleOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $("#link_modificar_ot").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de compra cliente para modificarla")
          }
        })

        $("#link_cancelar_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de compra cliente para eliminarla")
          }
        })

        $("#link_nuevo_consumo").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de compra cliente para agregar un certificado")
          }
        })

        $("#link_ver_occ").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de compra cliente para ver detalle")
          }
        })
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    
      function get_detalle_orden_compra_clientes(id_occ){
        let datosUpdate = new FormData();
        datosUpdate.append('id_occ', id_occ);
        $.ajax({
          data: datosUpdate,
          url: 'get_detalle_orden_compra_clientes.php',
          method: "post",
          cache: false,
          contentType: false,
          processData: false,
          success: function(data){
            //console.log(data);
            data = JSON.parse(data);
            //console.log(data);
            $('#tablaDetalleOCC').DataTable().destroy();
            $('#tablaDetalleOCC').DataTable({
              stateSave: false,
              responsive: false,
			  "columnDefs": [
				{
				  "targets": [0],
				  "className": 'd-none'
				}
			  ],
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
              /*"fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                console.log(nRow);
                console.log(aData);
                $('td:eq(7)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
              },*/
              initComplete: function(){
                $('[title]').tooltip();
              }
            });
        
            // DataTable
            var table = $('#tablaDetalleOCC').DataTable();
            // Apply the search
            table.columns().every( function () {
              var that = this;
              $( 'input', this.footer() ).on( 'keyup change', function () {
                if ( that.search() !== this.value ) {
                  that.search( this.value ).draw();
                }
              });
            });
        
            //$('#tablaDetalleOCC').find("tbody tr td").not(":last-child").on( 'click', function () {
            /*$(document).on("click","#tablaDetalleOCC tbody tr td", function(){
              var t=$(this).parent();
              //t.parent().find("tr").removeClass("selected");

              let id_pos_ot=t.find("td:first-child").html();
              let cantMaxima=t.find("td:nth-child(5)").html();
              if(t.hasClass('selected')){
                deselectRow(t);
                $("#btnAbrirModalModificarCantidades").data("id","");
                $("#cantMaxima").html("")
                $("#id_posicion_ot").val("")
              }else{
                table.rows().nodes().each( function (rowNode, index) {
                  $(rowNode).removeClass("selected");
                });
                selectRow(t);
                $("#btnAbrirModalModificarCantidades").data("id",id_pos_ot);
                $("#cantMaxima").html(cantMaxima)
                $("#id_posicion_ot").val(id_pos_ot)
              }
            });*/
          }
        });
      }
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>