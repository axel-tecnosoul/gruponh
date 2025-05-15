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
          $ubicacion="Facturas Venta ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarFacturasVenta.php">
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
						Estado:&nbsp;
						<select name="id_estado[]" id="id_estado[]" class="js-example-basic-multiple" multiple="multiple">
							<option value="">Todos</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_factura` WHERE 1 order by estado ";
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
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarFacturasVenta.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(337))) { ?><a href="nuevaFacturaVenta.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva"></a><?php } ?>
					&nbsp;&nbsp;
					<?php 
					if (!empty(tienePermiso(339))) {
						echo '<a href="#" id="link_modificar_fv"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar/Anular" title="Modificar/Anular"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(337))) {
						echo '<a href="#" id="link_nuevo_detalle_fv"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Añadir ítem Detalle" title="Añadir ítem Detalle"></a>';
						echo '&nbsp;&nbsp;';									
						echo '<a href="#" id="link_nuevo_retencion_fv"><img src="img/edit3.png" width="24" height="25" border="0" alt="Añadir Retenciones" title="Añadir Retenciones"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(343))) {
						echo '<a href="exportFacturasVenta.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>';
						echo '&nbsp;&nbsp;';	
						echo '<a href="exportFacturasVentaBejerman.php"><img src="img/import.png" width="24" height="25" border="0" alt="Bejerman TXT" title="Bejerman TXT"></a>';
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
							  <th class="d-none">ID</th>
							  <th>Descripción</th>
							  <th>Tipo</th>
							  <th>Letra</th>
							  <th>Número</th>
							  <th>Cliente</th>
							  <th>Fecha</th>
							  <th>Condición</th>
							  <th>Moneda</th>
							  <th>Total s/Imp</th>
							  <th>IVA</th>
							  <th>Otros</th>
							  <th>Total Neto</th>
							  <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                            if (!empty($_POST)) {
                            $pdo = Database::connect();
                            $sql = " SELECT fv.`id`, fv.`descripcion`, tc.`tipo`, lc.`letra`, fv.`numero`, c.nombre, date_format(fv.`fecha_emitida`,'%d/%m/%y'), fp.forma_pago, fv.`subtotal_no_gravado`, m.`moneda`, ef.estado,fv.iva,fv.otros,fv.total, date_format(fv.`fecha_emitida`,'%y%m%d')  FROM `facturas_venta` fv inner join tipos_comprobante tc on tc.id = fv.`id_tipo_comprobante` inner join letras_comprobante lc on lc.id = fv.`id_letra_comprobante` inner join cuentas c on c.id = fv.`id_cuenta_destino` inner join formas_pago fp on fp.id = fv.`id_condicion_pago` inner join monedas m on m.id = fv.`id_moneda` inner join estados_factura ef on ef.id = fv.`id_estado` WHERE 1 ";
                            if (!empty($_POST['nro'])) {
								$sql .= " AND fv.numero = '".$_POST['nro']."' ";
							}
							if (!empty($_POST['fecha'])) {
								$sql .= " AND fv.fecha_emitida >= '".$_POST['fecha']."' ";
							}
							if (!empty($_POST['fechah'])) {
								$sql .= " AND fv.fecha_emitida <= '".$_POST['fechah']."' ";
							}
							if (!empty($_POST['cliente'])) {
								$sql .= " AND c.nombre like '%".$_POST['cliente']."%' ";
							}
							if (!empty($_POST['id_estado'][0])) {
								$sql .= " AND ef.id in (".implode(', ',$_POST['id_estado']).") ";
							}
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
								echo '<td class="d-none">'. $row[0] . '</td>';
                                echo '<td>'. $row[1] . '</td>';
								echo '<td>'. $row[2] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
                                echo '<td><span style="display: none;">'. $row[14] . '</span>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
                                echo '<td>'. $row[9] . '</td>';
                                echo '<td>'. number_format($row[8],2) . '</td>';
                                echo '<td>'. number_format($row[11],2) . '</td>';
								echo '<td>'. number_format($row[12],2) . '</td>';
								echo '<td>'. number_format($row[13],2) . '</td>';
								echo '<td>'. $row[10] . '</td>';
                                echo '</tr>';
                            }
							Database::disconnect();
							}
                          ?>
                        </tbody>
						<tfoot>
                          <tr>
							  <th class="d-none">ID</th>
							  <th>Descripción</th>
							  <th>Tipo</th>
							  <th>Letra</th>
							  <th>Número</th>
							  <th>Cliente</th>
							  <th>Fecha</th>
							  <th>Condición</th>
							  <th>Moneda</th>
							  <th>Total s/Imp</th>
							  <th>IVA</th>
							  <th>Otros</th>
							  <th>Total Neto</th>
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
					if (!empty(tienePermiso(339))) {
						echo '<a href="#" id="link_modificar_detalle_fv"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
						echo '<a href="#" id="link_eliminar_detalle_fv"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
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
							  <th class="d-none">ID</th>
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
	
	<div style="width: 0;height: 0;display: none;">
      <select id="select_estado_base"><?php
        $pdo = Database::connect();
        $sql = "SELECT id,estado FROM estados_factura";
        foreach ($pdo->query($sql) as $row) {
          echo '<option value="'.$row["id"].'">'.$row["estado"].'</option>';
        }
        Database::disconnect();?>
      </select>
    </div>
	
  <?php
    $pdo = Database::connect();
    $sql = " SELECT `id`, `id_factura_venta`, `cantidad`, `precio`, `subtotal` FROM `facturas_venta_detalle` WHERE 1 ";
    foreach ($pdo->query($sql) as $row) {
    ?>
  <div class="modal fade" id="eliminarModalDetalle_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelDetalle" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabelDetalle">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea eliminar el Ítem de Detalle de la Factura de Venta?</div>
      <div class="modal-footer">
      <a href="eliminarDetalleFacturaVenta.php?id=<?php echo $row[0]; ?>&fv=<?php echo $row[1]; ?>" class="btn btn-primary">Eliminar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <?php
    }
    Database::disconnect();
    ?>
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
        }},
		"fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                $('td:eq(13)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
              },
              initComplete: function(){
                $('[title]').tooltip();
              }
      });
 
    // DataTable
    var table = $('#dataTables-example666').DataTable();
 
    
	  $("#link_modificar_fv").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una Factura de Venta para modificar")
        }
      })
	  $("#link_nuevo_detalle_fv").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una Factura de Venta para añadir ítem de detalle")
        }
      })
	  $("#link_nuevo_retencion_fv").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una Factura de Venta para añadir ítem de retención")
        }
      })
	   
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_fv=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
		      get_detalles(id_fv)
          $("#link_modificar_fv").attr("href","#");
		      $("#link_nuevo_detalle_fv").attr("href","#");
			  $("#link_nuevo_retencion_fv").attr("href","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_detalles(id_fv)
          $("#link_modificar_fv").attr("href","modificarFacturaVenta.php?id="+id_fv);
		      $("#link_nuevo_detalle_fv").attr("href","nuevoDetalleFacturaVenta.php?id="+id_fv);
			  $("#link_nuevo_retencion_fv").attr("href","nuevaRetencionFacturaVenta.php?id="+id_fv);
        }
      });
	  
	  
    
	} );
	
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
		"columnDefs": [
			{
              "targets": [0],
			  "className": 'd-none'
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
        }}
      });
 
    // DataTable
    var table3 = $('#dataTables-example667').DataTable();
 
    // Apply the search
    table3.columns().every( function () {
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
	
	function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }
	
	// DataTable
    var table2 = $('#dataTables-example667');
	
    function get_detalles(id_fv){
      let datosUpdate = new FormData();
      datosUpdate.append('id_fv', id_fv);
      $.ajax({
        data: datosUpdate,
        url: 'get_detalles_factura_venta.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          console.log(data);
          data = JSON.parse(data);
          console.log(data);

          table2.DataTable().destroy();
          table2.DataTable({
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
            }
          });
      
          
          // Apply the search
          table2.DataTable().columns().every( function () {
            var that = this;
            $( 'input', this.footer() ).on( 'keyup change', function () {
              if ( that.search() !== this.value ) {
                that
                  .search( this.value )
                  .draw();
              }
            });
          });
		  
		  $("#link_modificar_detalle_fv").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un detalle para modificar")
			}
		  })
		  
		  $("#link_eliminar_detalle_fv").on("click",function(){
			/*let l=document.location.href;
			if(this.href==l || this.href==l+"#"){*/
      let target=this.dataset.target;
      if(target==undefined || target=="#"){
			  alert("Por favor seleccione un detalle para eliminar")
			}
		  })
		  
          //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
    
          
        }
      });
    }
	/*
    $(document).on("click","#dataTables-example667 tbody tr td", function(){
			var t=$(this).parent();
			//t.parent().find("tr").removeClass("selected");

			let id_detalle=t.find("td:first-child").html();
			if(t.hasClass('selected')){
			  deselectRow(t);
			  $("#link_modificar_detalle_fv").attr("href","#");
			  $("#link_eliminar_detalle_fv").attr("data-target","#");
			}else{
        table.rows().nodes().each( function (rowNode, index) {
          $(rowNode).removeClass("selected");
        });
			  selectRow(t);
			  $("#link_modificar_detalle_fv").attr("href","modificarDetalleFacturaVenta.php?id="+id_detalle);
			  $("#link_eliminar_detalle_fv").attr("data-toggle","modal");
			  $("#link_eliminar_detalle_fv").attr("data-target","#eliminarModalDetalle_"+id_detalle);
			}
		  });
		  */
	 
      $(document).on("click","#dataTables-example667 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_detalle=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_modificar_detalle_fv").attr("href","#");
		  $("#link_eliminar_detalle_fv").attr("data-target","#");
        }else{
          table2.DataTable().rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_modificar_detalle_fv").attr("href","modificarDetalleFacturaVenta.php?id="+id_detalle);
		  $("#link_eliminar_detalle_fv").attr("data-toggle","modal");
		  $("#link_eliminar_detalle_fv").attr("data-target","#eliminarModalDetalle_"+id_detalle);
        }
		  });
    $("body").on('dblclick',".editable", function(event) {
          var t=$(this);
          
          let old_padding=t.css("padding");
          t.css({padding: '0'});
          t.find('input[type="hidden"]');
          
		  var idPosicion=t.data("idPosicion");
		  console.log(idPosicion);
		  var idEstado=t.data("idEstado");
          dataString="idPosicion="+idPosicion;

          let nuevo_select_estado=$("#select_estado_base").clone()
          nuevo_select_estado.id="id_estado_nuevo"

          t.html(nuevo_select_estado);
          nuevo_select_estado.val(idEstado)

          nuevo_select_estado.on('blur', function(event) {
            nuevaEstado=nuevo_select_estado.val();
            // Obtener el texto correspondiente al valor seleccionado
            var textoSeleccionado = nuevo_select_estado.find('option[value="'+nuevaEstado+'"]').text();

            $.ajax({
              type: "POST",
              url: "modificarEstadoFacturaVenta.php",
              data: "idPosicion="+idPosicion+"&idEstado="+nuevaEstado,
              success: function(data) {
                if(data){
                  t.css({padding: old_padding});
                  t.html(textoSeleccionado)
                }else{
                  t.css({padding: old_padding});
                  t.html(textoSeleccionado)
					
				} 
              }
            });
          });
        });
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
	<script src="assets/js/select2/select2.full.min.js"></script>
<script src="assets/js/select2/select2-custom.js"></script>

    <!-- Plugin used-->
  </body>
</html>