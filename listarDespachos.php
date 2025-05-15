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
          $ubicacion="Despachos ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(358))) { ?><a href="nuevoDespacho.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a>&nbsp;<?php } ?><a href="exportDespachos.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_des"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Detalle" title="Ver Detalle"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(360))) {
						echo '<a href="#" id="link_eliminar_des"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(359))) {
						echo '<a href="#" id="link_modificar_des"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
					}
					/*if (!empty(tienePermiso(361))) {
						echo '<a href="#" id="link_nuevo_bulto"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Bulto" title="Nuevo Bulto"></a>';
						echo '&nbsp;&nbsp;';
					}*/
					?>
					</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
							<th>Sitio</th>
                            <th>Subsitio</th>
                            <th>Nro Proy</th>
							<th>Cliente</th>
                            <th>Fecha</th>
                            <th>Nro. Remito</th>
                            <th>Transporte</th>
                            <th>Chofer</th>
                            <th>Estado</th>
							<th>Usuario</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php 
                            include 'database.php'; 
                            $pdo = Database::connect();
                            $sql = "SELECT des.`id`, date_format(des.`fecha`,'%d/%m/%y'), c.`nombre`, des.`nro_remito`, des.`transporte`, des.`chofer`, e.estado,s.nro_sitio,s.nro_subsitio,p.nro,c2.nombre FROM `despachos` des inner join cuentas c on c.id = des.`id_cuenta_operario` inner join estados_despacho e on e.id = des.`id_estado_despacho` inner join proyectos p on p.id = des.id_proyecto inner join sitios s on s.id = p.id_sitio inner join cuentas c2 on c2.id = p.id_cliente WHERE 1 "; 
									 
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
                                echo '<td class="d-none">'. $row[0] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
								echo '<td>'. $row[8] . '</td>';
								echo '<td>'. $row[9] . '</td>';
								echo '<td>'. $row[10] . '</td>';
								echo '<td>'. $row[1] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
                                echo '<td>'. $row[6] . '</td>';
								echo '<td>'. $row[2] . '</td>';
                                echo '</tr>';?>

                                <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                  <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                                        <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                      </div>
                                      <div class="modal-body">¿Está seguro que desea eliminar el Despacho?</div>
                                      <div class="modal-footer">
                                        <a href="eliminarDespacho.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
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
                            <th class="d-none">ID</th>
							<th>Sitio</th>
                            <th>Subsitio</th>
                            <th>Nro Proy</th>
							<th>Cliente</th>
                            <th>Fecha</th>
                            <th>Nro. Remito</th>
                            <th>Transporte</th>
                            <th>Chofer</th>
                            <th>Estado</th>
							<th>Usuario</th>
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
                    <h5>Bultos&nbsp;&nbsp;<?php 
                      echo '<a href="#" id="link_ver_bulto_des"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                      echo '&nbsp;&nbsp;';
                      if (!empty(tienePermiso(362))) {
                        echo '<a href="#" id="link_modificar_bulto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(363))) {
                        echo '<a href="#" id="link_eliminar_bulto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(364))) {
                        echo '<a href="#" id="link_nuevo_detalle_bulto"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Detalle Bulto" title="Nuevo Detalle Bulto"></a>';
                        echo '&nbsp;&nbsp;';
                      }?>
					          </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th>ID Bulto</th>
                            <th>Número</th>
                            <th>Nombre</th>
                            <th>Color</th>
                            <th>Estado</th>
                            <th>ID detalle bulto</th>
                            <th>Tipo</th>
                            <th>ID Origen</th>
                            <th>ID Detalle</th>
                            <th>Cantidad</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
						            <tfoot>
                          <tr>
                            <th>ID Bulto</th>
                            <th>Número</th>
                            <th>Nombre</th>
                            <th>Color</th>
                            <th>Estado</th>
                            <th>ID detalle bulto</th>
                            <th>Tipo</th>
                            <th>ID Origen</th>
                            <th>ID Detalle</th>
                            <th>Cantidad</th>
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
            <div class="row d-none">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Detalle Bulto&nbsp;&nbsp;<?php 
                      /*
                      LA MODIFICACIÓN ES MUY ENGORROSA DADO QUE TODO SE DEBERIA RECALCULAR, MAS FACIL ELIMINAR y CREAR NUEVAMENTE EL REGISTRO (ademas pide solo 2 datos)
                      echo '<a href="#" id="link_ver_detalle_bulto_des"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                      echo '&nbsp;&nbsp;';
                      if (!empty(tienePermiso(365))) {
                        echo '<a href="#" id="link_modificar_detalle_bulto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Actualizar" title="Actualizar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      */
                      if (!empty(tienePermiso(366))) {
                        echo '<a href="#" id="link_eliminar_detalle_bulto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }?>
                    </h5>
                </div>
                <div class="card-body">
                  <div class="dt-ext table-responsive">
                    <table class="display truncate" id="dataTables-example668">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Tipo</th>
                          <th>ID Origen</th>
                          <th>ID Detalle</th>
                          <th>Cantidad</th>
                        </tr>
                      </thead>
                      <tbody>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th>ID</th>
                          <th>Tipo</th>
                          <th>ID Origen</th>
                          <th>ID Detalle</th>
                          <th>Cantidad</th>
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
    </div><?php
    /*$pdo = Database::connect();
    $sql = "SELECT des.`id`, date_format(des.`fecha`,'%d/%m/%y'), c.`nombre`, des.`nro_remito`, des.`transporte`, des.`chofer`, e.estado FROM `despachos` des inner join cuentas c on c.id = des.`id_cuenta_operario` inner join estados_despacho e on e.id = des.`id_estado_despacho` WHERE 1 "; 
	foreach ($pdo->query($sql) as $row) {
        ?>
  
  <?php
    }
    Database::disconnect();*/
    ?>
		<?php
		$pdo = Database::connect();
		$sql = "SELECT `id`, `id_bulto`, `id_tipo_bulto`, `id_origen_bulto`, `id_detalle_bulto`, `cantidad` FROM `bultos_detalle` WHERE 1 "; 
		foreach ($pdo->query($sql) as $row) {
			?>
		  <div class="modal fade" id="eliminarModalDetalle_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalDetalleLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
			<div class="modal-content">
			  <div class="modal-header">
			  <h5 class="modal-title" id="exampleModalDetalleLabel">Confirmación</h5>
			  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
			  </div>
			  <div class="modal-body">¿Está seguro que desea eliminar el Detalle del Bulto?</div>
			  <div class="modal-footer">
			  <a href="eliminarDetalleBultoDespacho.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
			  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
			  </div>
			</div>
			</div>
		  </div>
		  <?php
			}
			Database::disconnect();
			?>
			<?php
			$pdo = Database::connect();
			$sql = "SELECT `id`, `id_despacho`, `numero`, `nombre`, `color`, `id_estado_bulto` FROM `bultos` WHERE 1 "; 
			foreach ($pdo->query($sql) as $row) {
				$sql2 = "select count(*) cant from bultos_detalle where id_bulto = ".$row[0];
				$q2 = $pdo->prepare($sql2);
				$q2->execute();
				$data2 = $q2->fetch(PDO::FETCH_ASSOC);
				if (empty($data2['cant'])) {
				?>
				<div class="modal fade" id="eliminarModalBulto_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalBultoLabel" aria-hidden="true">
				<div class="modal-dialog" role="document">
				<div class="modal-content">
				  <div class="modal-header">
				  <h5 class="modal-title" id="exampleModalBultoLabel">Confirmación</h5>
				  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				  </div>
				  <div class="modal-body">¿Está seguro que desea eliminar el Bulto?</div>
				  <div class="modal-footer">
				  <a href="eliminarBultoDespacho.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
				  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
				  </div>
				</div>
				</div>
			  </div>
			  <?php
				} else {
			?>
				<div class="modal fade" id="eliminarModalBulto_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalBultoLabel" aria-hidden="true">
				<div class="modal-dialog" role="document">
				<div class="modal-content">
				  <div class="modal-header">
				  <h5 class="modal-title" id="exampleModalBultoLabel">Alerta</h5>
				  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				  </div>
				  <div class="modal-body">El Bulto no puede ser eliminado debido a que tiene Detalles sin cancelar.</div>
				  <div class="modal-footer">
				  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
				  </div>
				</div>
				</div>
			  </div>
			  <?php
				}
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
		$("#link_ver_des").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un despacho para ver detalle")
			}
		  })
		$("#link_eliminar_des").on("click",function(){
			/*let l=document.location.href;
			if(this.href==l || this.href==l+"#"){*/
      let target=this.dataset.target;
      if(target==undefined || target=="#"){
			  alert("Por favor seleccione un despacho para cancelar")
			}
		  })
		$("#link_modificar_des").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un despacho para modificar")
			}
		})
		$("#link_nuevo_bulto").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un despacho para crear un nuevo bulto")
			}
		})
		
		
		//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
      $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_des=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
		      get_bultos(id_des)
          $("#link_ver_des").attr("href","#");
          $("#link_eliminar_des").attr("data-target","#");
          $("#link_modificar_des").attr("href","#");
          $("#link_nuevo_bulto").attr("href","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_bultos(id_des)
          $("#link_ver_des").attr("href","verDespacho.php?id="+id_des);
          $("#link_nuevo_bulto").attr("href","nuevoBultoDespacho.php?id="+id_des);
          $("#link_modificar_des").attr("href","modificarDespacho.php?id="+id_des);
          $("#link_eliminar_des").attr("data-toggle","modal");
          $("#link_eliminar_des").attr("data-target","#eliminarModal_"+id_des);
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
	
	function get_bultos(id_des){
      let datosUpdate = new FormData();
      datosUpdate.append('id_des', id_des);
      $.ajax({
        data: datosUpdate,
        url: 'get_bultos.php',
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
		  
		  $("#link_ver_bulto_des").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un bulto para ver detalle")
			}
		  })
		  $("#link_modificar_bulto").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione un bulto para modificar")
				}
			})
			$("#link_eliminar_bulto").on("click",function(){
				/*let l=document.location.href;
				if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
				  alert("Por favor seleccione un bulto para eliminar")
				}
			})
			$("#link_nuevo_detalle_bulto").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione un bulto para agregar detalle")
				}
			})
			
          //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example667 tbody tr td", function(){
			var t=$(this).parent();
			//t.parent().find("tr").removeClass("selected");

			let id_bul=t.find("td:first-child").html();
			if(t.hasClass('selected')){
			  deselectRow(t);
			  get_detalles_bulto(id_bul)
			  $("#link_ver_bulto_des").attr("href","#");
			  $("#link_modificar_bulto").attr("href","#");
			  $("#link_eliminar_bulto").attr("data-target","#");
			  $("#link_nuevo_detalle_bulto").attr("href","#");
			}else{
        table.rows().nodes().each( function (rowNode, index) {
          $(rowNode).removeClass("selected");
        });
			  selectRow(t);
			  get_detalles_bulto(id_bul)
			  $("#link_ver_bulto_des").attr("href","verBultoDespacho.php?id="+id_bul);
			  $("#link_modificar_bulto").attr("href","modificarBultoDespacho.php?id="+id_bul);
			  $("#link_nuevo_detalle_bulto").attr("href","nuevoDetalleBultoDespacho.php?id="+id_bul);
			  $("#link_eliminar_bulto").attr("data-toggle","modal");
			  $("#link_eliminar_bulto").attr("data-target","#eliminarModalBulto_"+id_bul);
			}
		  });
          
        }
      });
    }
    
    </script>
	
	<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example668 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example668').DataTable({
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
    var table = $('#dataTables-example668').DataTable();
 
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
	
	function get_detalles_bulto(id_bul){
      let datosUpdate = new FormData();
      datosUpdate.append('id_bul', id_bul);
      $.ajax({
        data: datosUpdate,
        url: 'get_detalles_bulto.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          console.log(data);
          data = JSON.parse(data);
          console.log(data);

          $('#dataTables-example668').DataTable().destroy();
          $('#dataTables-example668').DataTable({
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
          var table = $('#dataTables-example668').DataTable();
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
		  
		  $("#link_ver_detalle_bulto_des").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un detalle para ver")
			}
		  })
		  $("#link_modificar_detalle_bulto").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione un detalle para actualizar")
				}
			})
			$("#link_eliminar_detalle_bulto").on("click",function(){
				/*let l=document.location.href;
				if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
				  alert("Por favor seleccione un detalle para eliminar")
				}
			})
			
          //$('#dataTables-example668').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example668 tbody tr td", function(){
			var t=$(this).parent();
			//t.parent().find("tr").removeClass("selected");

			let id_detalle=t.find("td:first-child").html();
			if(t.hasClass('selected')){
			  deselectRow(t);
			  $("#link_ver_detalle_bulto_des").attr("href","#");
			  $("#link_modificar_detalle_bulto").attr("href","#");
			  $("#link_eliminar_detalle_bulto").attr("data-target","#");
			}else{
        table.rows().nodes().each( function (rowNode, index) {
          $(rowNode).removeClass("selected");
        });
			  selectRow(t);
			  $("#link_ver_detalle_bulto_des").attr("href","verDetalleBultoDespacho.php?id="+id_detalle);
			  $("#link_modificar_detalle_bulto").attr("href","modificarDetalleBultoDespacho.php?id="+id_detalle);
			  $("#link_eliminar_detalle_bulto").attr("data-toggle","modal");
			  $("#link_eliminar_detalle_bulto").attr("data-target","#eliminarModalDetalle_"+id_detalle);
			}
		  });
          
        }
      });
    }
    
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>