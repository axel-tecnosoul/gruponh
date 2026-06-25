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
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
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
          $ubicacion="Facturas Compra ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarFacturasCompra.php">
					  <div class="form-group mb-0">
						Nro:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro">
					  </div>
					  <div class="form-group mb-0">
						Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fecha'])) echo $_POST['fecha'] ?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fechah'])) echo $_POST['fechah'] ?>" name="fechah">
					  </div>
					  <div class="form-group mb-0">
						Proveedor:&nbsp;<input class="form-control" size="20" type="text" value="<?php if (isset($_POST['proveedor'])) echo $_POST['proveedor'] ?>" name="proveedor">
					  </div>
					  <div class="form-group mb-0">
						Estado:&nbsp;
						<select name="id_estado[]" id="id_estado[]" class="js-example-basic-multiple" multiple="multiple">
							<option value="">Todos</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_factura` WHERE 1 ORDER BY id ASC";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (isset($_POST['id_estado'])) {
									if (in_array($fila['id'],$_POST['id_estado'])) {
										echo " selected ";
									}
								}
								echo ">".$fila['estado']."</option>";
							}
							Database::disconnect();
							?>
							</select>
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarFacturasCompra.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(336))) { ?><a href="#" id="btn_nueva_factura_compra" title="Nueva Factura"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva"></a><?php } ?>
					&nbsp;&nbsp;
					<?php 
					if (!empty(tienePermiso(338))) {
						echo '<a href="#" id="link_modificar_fc"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar/Anular" title="Modificar/Anular"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(336))) {
						echo '<a href="#" id="link_nuevo_detalle_fc"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Añadir ítem Detalle" title="Añadir ítem Detalle"></a>';
						echo '&nbsp;&nbsp;';
						echo '<a href="#" id="link_nuevo_retencion_fc"><img src="img/edit3.png" width="24" height="25" border="0" alt="Añadir Retenciones" title="Añadir Retenciones"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(342))) {
						echo '<a href="exportFacturasCompra.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>';
						echo '&nbsp;&nbsp;';									
						echo '<a href="exportFacturasCompraBejerman.php"><img src="img/import.png" width="24" height="25" border="0" alt="Bejerman TXT" title="Bejerman TXT"></a>';
						echo '&nbsp;&nbsp;';									
					}
					?>
					</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
							  <th>ID</th>
							  <th>Descripción</th>
							  <th>Tipo</th>
							  <th>Letra</th>
							  <th>Número</th>
							  <th>Proveedor</th>
							  <th>Fecha</th>
							  <th>Condición</th>
							  <th>Total</th>
							  <th>Moneda</th>
							  <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                            if (!empty($_POST)) {
                            $pdo = Database::connect();
                            $sql = " SELECT fc.`id`, fc.`descripcion`, tc.`tipo`, lc.`letra`, fc.`numero`, c.razon_social, date_format(fc.`fecha_emitida`,'%d/%m/%y'), fp.forma_pago, fc.`total`, m.`moneda`, ef.estado, date_format(fc.`fecha_emitida`,'%y%m%d') FROM `facturas_compra` fc inner join tipos_comprobante tc on tc.id = fc.`id_tipo_comprobante` inner join letras_comprobante lc on lc.id = fc.`id_letra_comprobante` inner join cuentas c on c.id = fc.`id_cuenta_origen` inner join formas_pago fp on fp.id = fc.`id_condicion_pago` inner join monedas m on m.id = fc.`id_moneda` inner join estados_factura ef on ef.id = fc.`id_estado` WHERE 1 ";
                            if (!empty($_POST['nro'])) {
								$sql .= " AND fc.numero = '".$_POST['nro']."' ";
							}
							if (!empty($_POST['fecha'])) {
								$sql .= " AND fc.fecha_emitida >= '".$_POST['fecha']."' ";
							}
							if (!empty($_POST['fechah'])) {
								$sql .= " AND fc.fecha_emitida <= '".$_POST['fechah']."' ";
							}
							if (!empty($_POST['proveedor'])) {
								$sql .= " AND c.razon_social like '%".$_POST['proveedor']."%' ";
							}
							if (!empty($_POST['id_estado'][0])) {
								$sql .= " AND ef.id in (".implode(', ',$_POST['id_estado']).") ";
							}
							
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
								echo '<td>'. $row[0] . '</td>';
                                echo '<td>'. $row[1] . '</td>';
								echo '<td>'. $row[2] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
                                echo '<td><span style="display: none;">'. $row[11] . '</span>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
                                echo '<td>'. number_format($row[8] ?? 0,2) . '</td>';
                                echo '<td>'. $row[9] . '</td>';
                                echo '<td>'. $row[10] . '</td>';
                                echo '</tr>';
                            }
							Database::disconnect();
							}
                          ?>
                        </tbody>
						<tfoot>
                          <tr>
							  <th>ID</th>
							  <th>Descripción</th>
							  <th>Tipo</th>
							  <th>Letra</th>
							  <th>Número</th>
							  <th>Proveedor</th>
							  <th>Fecha</th>
							  <th>Condición</th>
							  <th>Total</th>
							  <th>Moneda</th>
							  <th>Estado</th>
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
                    <h5>Detalles de Factura
					&nbsp;&nbsp;
					<?php 
					if (!empty(tienePermiso(338))) {
						echo '<a href="#" id="link_modificar_detalle_fc"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
						echo '<a href="#" id="link_eliminar_detalle_fc"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					?>
					</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
							  <th>ID</th>
							  <th>Descripción</th>
							  <th>Precio</th>
							  <th>Cantidad</th>
							  <th>Subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
						<tfoot>
                          <tr>
							  <th>ID</th>
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
  <?php
    $pdo = Database::connect();
    $sql = " SELECT `id`, `id_factura_compra`, `cantidad`, `precio`, `subtotal` FROM `facturas_compra_detalle` WHERE 1 ";
    foreach ($pdo->query($sql) as $row) {
    ?>
  <div class="modal fade" id="eliminarModalDetalle_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelDetalle" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabelDetalle">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea eliminar el Ítem de Detalle de la Factura de Compra?</div>
      <div class="modal-footer">
      <a href="eliminarDetalleFacturaCompra.php?id=<?php echo $row[0]; ?>&fc=<?php echo $row[1]; ?>" class="btn btn-primary">Eliminar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <?php
    }
    Database::disconnect();
    ?>

<!-- Modal Elegir Proveedor y OC para Nueva Factura de Compra -->
<div class="modal fade" id="modalElegirProveedorOC" tabindex="-1" role="dialog" aria-labelledby="modalElegirProveedorOCLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalElegirProveedorOCLabel">Seleccionar Proveedor y Orden de Compra</h5>
        <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">

        <!-- PASO 1: Selección de Proveedor -->
        <div id="pasoProveedor">
          <div class="form-group">
            <input type="text" id="buscarProveedor" class="form-control" placeholder="Filtrar proveedores...">
          </div>
          <div id="listaProveedores" style="max-height: 400px; overflow-y: auto;">
            <div class="text-center py-3"><i>Cargando proveedores...</i></div>
          </div>
        </div>

        <!-- PASO 2: Selección de OC (oculto inicialmente) -->
        <div id="pasoOC" style="display:none;">
          <div class="d-flex align-items-center mb-3">
            <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="btnVolverProveedores">
              &larr; Volver
            </button>
            <div>
              <strong id="labelProveedorSeleccionado"></strong><br>
              <small class="text-muted">Seleccione una orden de compra para crear la factura</small>
            </div>
          </div>
          <div id="listaOC" style="max-height: 400px; overflow-y: auto;">
            <div class="text-center py-3"><i>Cargando órdenes de compra...</i></div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnContinuarConOC" style="display:none;">
          Continuar con OC seleccionada
        </button>
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
    // Setup - add a text input to each footer cell
    $('#dataTables-example666 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example666').DataTable({
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
        }}
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
		
	
	  $("#link_modificar_fc").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una Factura de Compra para modificar")
        }
      })
	  $("#link_nuevo_detalle_fc").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una Factura de Compra para añadir ítem de detalle")
        }
      })
	  $("#link_nuevo_retencion_fc").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una Factura de Compra para añadir ítem de retención")
        }
      })
	   
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_fc=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
		      get_detalles(id_fc)
          $("#link_modificar_fc").attr("href","#");
		      $("#link_nuevo_detalle_fc").attr("href","#");
			  $("#link_nuevo_retencion_fc").attr("href","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_detalles(id_fc)
          $("#link_modificar_fc").attr("href","modificarFacturaCompra.php?id="+id_fc);
		      $("#link_nuevo_detalle_fc").attr("href","nuevoDetalleFacturaCompra.php?id="+id_fc);
			  $("#link_nuevo_retencion_fc").attr("href","nuevaRetencionFacturaCompra.php?id="+id_fc);
        }
      });
    
	} );
	
	function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }
    
    </script>
	<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example667 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
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
        }}
      });
 
    // DataTable
    var table = $('#dataTables-example667').DataTable();
 
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
	} );
	
    function get_detalles(id_fc){
      let datosUpdate = new FormData();
      datosUpdate.append('id_fc', id_fc);
      $.ajax({
        data: datosUpdate,
        url: 'get_detalles_factura_compra.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          console.log(data);
          data = JSON.parse(data);
          console.log(data);

          $('#dataTables-example667').DataTable().destroy();
          $('#dataTables-example667').DataTable({
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
            }
          });
      
          // DataTable
          var table = $('#dataTables-example667').DataTable();
          // Apply the search
          table.columns().every( function () {
            var that = this;
            $( 'input', this.footer() ).on( 'keyup change', function () {
              if ( that.search() !== this.value ) {
                that
                  .search( this.value )
                  .draw();
              }
            });
          });
		  
		  $("#link_modificar_detalle_fc").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un detalle para modificar")
			}
		  })
		  
		  $("#link_eliminar_detalle_fc").on("click",function(){
			/*let l=document.location.href;
			if(this.href==l || this.href==l+"#"){*/
      let target=this.dataset.target;
      if(target==undefined || target=="#"){
			  alert("Por favor seleccione un detalle para eliminar")
			}
		  })
		  
          //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
      $(document).on("click","#dataTables-example667 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_detalle=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_modificar_detalle_fc").attr("href","#");
          $("#link_eliminar_detalle_fc").attr("data-target","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_modificar_detalle_fc").attr("href","modificarDetalleFacturaCompra.php?id="+id_detalle);
          $("#link_eliminar_detalle_fc").attr("data-toggle","modal");
          $("#link_eliminar_detalle_fc").attr("data-target","#eliminarModalDetalle_"+id_detalle);
        }
		  });
          
        }
      });
    }
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
	<script src="assets/js/select2/select2.full.min.js"></script>
<script src="assets/js/select2/select2-custom.js"></script>

<script>
// --- Modal elegir proveedor y OC para nueva factura de compra ---
function htmlEsc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

var idOCSeleccionada = null;

$('#btn_nueva_factura_compra').on('click', function(e) {
  e.preventDefault();
  idOCSeleccionada = null;
  $('#pasoProveedor').show();
  $('#pasoOC').hide();
  $('#btnContinuarConOC').hide();
  $('#buscarProveedor').val('');
  $('#listaProveedores').html('<div class="text-center py-3"><i>Cargando proveedores...</i></div>');
  $('#modalElegirProveedorOC').modal('show');

  // Cargar proveedores con OC pendientes
  $.ajax({
    url: 'get_oc_pendientes_proveedor.php',
    method: 'POST',
    dataType: 'json',
    success: function(proveedores) {
      if (!proveedores || proveedores.length === 0) {
        $('#listaProveedores').html('<div class="alert alert-info mb-0">No hay proveedores con órdenes de compra pendientes de facturar.</div>');
        return;
      }
      var html = '<table class="table table-hover table-sm" id="tablaProveedores"><thead class="thead-light"><tr>';
      html += '<th>Proveedor</th><th>CUIT</th><th class="text-center">OC Pendientes</th>';
      html += '</tr></thead><tbody>';
      $.each(proveedores, function(i, prov) {
        html += '<tr class="fila-proveedor" style="cursor:pointer;" data-id="' + prov.id + '" data-nombre="' + htmlEsc(prov.razon_social) + '">';
        html += '<td>' + htmlEsc(prov.razon_social) + '</td>';
        html += '<td>' + htmlEsc(prov.cuit) + '</td>';
        html += '<td class="text-center"><span class="badge badge-primary">' + prov.cant_oc + '</span></td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
      $('#listaProveedores').html(html);
    },
    error: function() {
      $('#listaProveedores').html('<div class="alert alert-danger">Error al cargar proveedores.</div>');
    }
  });
});

// Filtro de búsqueda de proveedores
$('#buscarProveedor').on('keyup', function() {
  var val = $(this).val().toLowerCase();
  $('.fila-proveedor').each(function() {
    $(this).toggle($(this).text().toLowerCase().indexOf(val) !== -1);
  });
});

// Click en un proveedor: cargar sus OC pendientes
$(document).on('click', '.fila-proveedor', function() {
  var idProveedor = $(this).data('id');
  var nombreProveedor = $(this).data('nombre');
  idOCSeleccionada = null;

  $('#labelProveedorSeleccionado').text(nombreProveedor);
  $('#listaOC').html('<div class="text-center py-3"><i>Cargando órdenes de compra...</i></div>');
  $('#pasoProveedor').hide();
  $('#pasoOC').show();
  $('#btnContinuarConOC').show().prop('disabled', true);

  $.ajax({
    url: 'get_oc_pendientes_proveedor.php',
    method: 'POST',
    data: { id_proveedor: idProveedor },
    dataType: 'json',
    success: function(ordenes) {
      if (!ordenes || ordenes.length === 0) {
        $('#listaOC').html('<div class="alert alert-info mb-0">Este proveedor no tiene órdenes de compra pendientes de facturar.</div>');
        $('#btnContinuarConOC').hide();
        return;
      }

      var html = '<div class="list-group">';
      $.each(ordenes, function(i, oc) {
        var ocId = 'oc_' + oc.id;
        html += '<div class="list-group-item p-0 border-0 mb-1">';

        // Cabecera con radio button y datos principales
        html += '<div class="d-flex align-items-center p-2 bg-light rounded" style="cursor:pointer;" data-toggle="collapse" data-target="#collapse_oc_' + oc.id + '">';
        html += '<input type="radio" name="oc_seleccionada" class="radio-oc mr-2" id="' + ocId + '" value="' + oc.id + '" onclick="event.stopPropagation()">';
        html += '<label for="' + ocId + '" class="mb-0 mr-2 font-weight-bold" style="color:#000;" onclick="event.stopPropagation()">OC #' + htmlEsc(oc.nro_oc) + '</label>';
        html += '<span class="badge badge-secondary mr-2">' + htmlEsc(oc.estado) + '</span>';
        html += '<small class="text-dark mr-auto">' + htmlEsc(oc.fecha_emision);
        if (oc.fecha_entrega) {
          html += ' &rarr; ' + htmlEsc(oc.fecha_entrega);
        }
        html += '</small>';
        if (oc.total) {
          html += '<small class="text-dark ml-2"><strong>' + htmlEsc(oc.moneda) + ' ' + parseFloat(oc.total).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</strong></small>';
        }
        html += '<i class="feather icon-chevron-down ml-2"></i>';
        html += '</div>';

        // Panel colapsable con detalle de items
        html += '<div id="collapse_oc_' + oc.id + '" class="collapse">';
        html += '<div class="p-2 border rounded mt-1 bg-white">';
        if (oc.detalles && oc.detalles.length > 0) {
          html += '<table class="table table-sm table-bordered mb-0">';
          html += '<thead class="thead-light"><tr>';
          html += '<th>Material</th>';
          html += '<th class="text-right">Cantidad</th>';
          html += '<th class="text-right">Entregado</th>';
          html += '<th>Unidad</th>';
          html += '<th class="text-right">Precio</th>';
          html += '<th class="text-right">Subtotal</th>';
          html += '</tr></thead><tbody>';
          $.each(oc.detalles, function(j, det) {
            html += '<tr>';
            html += '<td>' + htmlEsc(det.descripcion) + '</td>';
            html += '<td class="text-right">' + parseFloat(det.cantidad).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
            html += '<td class="text-right">' + parseFloat(det.entregado).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
            html += '<td>' + htmlEsc(det.unidad_medida) + '</td>';
            html += '<td class="text-right">$ ' + parseFloat(det.precio).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
            html += '<td class="text-right">$ ' + parseFloat(det.subtotal).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
            html += '</tr>';
          });
          html += '</tbody></table>';
        } else {
          html += '<small class="text-muted">Sin detalles disponibles.</small>';
        }
        if (oc.comentarios) {
          html += '<div class="mt-1"><small><strong>Comentarios:</strong> ' + htmlEsc(oc.comentarios) + '</small></div>';
        }
        html += '</div></div>';
        html += '</div>';
      });
      html += '</div>';
      $('#listaOC').html(html);
    },
    error: function() {
      $('#listaOC').html('<div class="alert alert-danger">Error al cargar órdenes de compra.</div>');
    }
  });
});

// Habilitar botón Continuar al seleccionar una OC
$(document).on('change', '.radio-oc', function() {
  idOCSeleccionada = $(this).val();
  $('#btnContinuarConOC').prop('disabled', false);
});

// Volver a la lista de proveedores
$('#btnVolverProveedores').on('click', function() {
  $('#pasoOC').hide();
  $('#btnContinuarConOC').hide();
  $('#pasoProveedor').show();
  idOCSeleccionada = null;
});

// Continuar: ir al formulario de nueva factura con la OC seleccionada
$('#btnContinuarConOC').on('click', function() {
  if (!idOCSeleccionada) {
    alert('Por favor seleccione una orden de compra.');
    return;
  }
  $('#modalElegirProveedorOC').modal('hide');
  window.location.href = 'nuevaFacturaCompra.php?oc=' + idOCSeleccionada;
});
</script>

    <!-- Plugin used-->
  </body>
</html>