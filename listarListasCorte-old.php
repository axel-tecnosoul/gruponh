<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
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
          $ubicacion="Listas de Corte ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(315))) { ?><a href="nuevaListaCorte.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva"></a>&nbsp;<?php } ?><a href="exportListasCorte.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Detalle" title="Ver Detalle"></a>';
					echo '&nbsp;&nbsp;';
					echo '<a href="#" id="link_imprimir_lc"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(317))) {
						echo '<a href="#" id="link_eliminar_lc"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(316))) {
						echo '<a href="#" id="link_modificar_lc"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(328))) {
						echo '<a href="#" id="link_nuevo_conjunto"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Conjunto" title="Nuevo Conjunto"></a>';
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
							  <th>Número</th>
							  <th>Revisión</th>
							  <th>Nombre</th>
							  <th>Proyecto</th>
							  <th>Fecha</th>
							  <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php 
                            include 'database.php';
                            $pdo = Database::connect();
                            $sql = "SELECT lc.`id`, lc.`numero`, lc.`nro_revision`, lc.`nombre`,p.`nombre`, date_format(lc.`fecha`,'%d/%m/%y'), e.`estado` FROM `listas_corte` lc inner join estados_lista_corte e on e.id = lc.`id_estado_lista_corte` inner join proyectos p on p.id = lc.id_proyecto WHERE lc.`anulado` = 0 "; 
									 
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
                                echo '<td>'. $row[0] . '</td>';
								echo '<td>'. $row[1] . '</td>';
								echo '<td>'. $row[2] . '</td>';
								echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
								echo '<td>'. $row[6] . '</td>';
                                echo '</tr>';
                            }
                           Database::disconnect();
                          ?>
                        </tbody>
						<tfoot>
                          <tr>
							  <th>ID</th>
							  <th>Número</th>
							  <th>Revisión</th>
							  <th>Nombre</th>
							  <th>Proyecto</th>
							  <th>Fecha</th>
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
                    <h5>Conjuntos
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_conjunto_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(329))) {
						echo '<a href="#" id="link_modificar_conjunto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(330))) {
						echo '<a href="#" id="link_eliminar_conjunto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(331))) {
						echo '<a href="#" id="link_nueva_posicion"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nueva Posición" title="Nueva Posición"></a>';
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
							  <th>Nombre</th>
							  <th>Cantidad</th>
							  <th>Peso kg</th>
							  <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
						<tfoot>
                          <tr>
							  <th>ID</th>
							  <th>Nombre</th>
							  <th>Cantidad</th>
							  <th>Peso kg</th>
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
					<h5>Conceptos y Posiciones
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_posicion_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(332))) {
						echo '<a href="#" id="link_modificar_posicion"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Actualizar" title="Actualizar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(333))) {
						echo '<a href="#" id="link_eliminar_posicion"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(344))) {
						echo '<a href="#" id="link_nuevo_proceso"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Proceso" title="Nuevo Proceso"></a>';
						echo '&nbsp;&nbsp;';
					}
					?>
					</h5>
				  </div>
				  <div class="card-body">
					<div class="dt-ext table-responsive">
					  <table class="display truncate" id="dataTables-example668">
						<thead>
						  <tr>
							  <th>ID</th>
							  <th>Concepto</th>
							  <th>Cantidad</th>
							  <th>Posición</th>
							  <th>Estado</th>
						  </tr>
						</thead>
						<tbody>
						</tbody>
						<tfoot>
						  <tr>
							  <th>ID</th>
							  <th>Concepto</th>
							  <th>Cantidad</th>
							  <th>Posición</th>
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
					<h5>Procesos
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_proceso_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(345))) {
						echo '<a href="#" id="link_modificar_proceso"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Actualizar" title="Actualizar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(346))) {
						echo '<a href="#" id="link_eliminar_proceso"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					?>
					</h5>
				  </div>
				  <div class="card-body">
					<div class="dt-ext table-responsive">
					  <table class="display truncate" id="dataTables-example669">
						<thead>
						  <tr>
							  <th>ID</th>
							  <th>Tipo</th>
							  <th>Estado</th>
						  </tr>
						</thead>
						<tbody>
						</tbody>
						<tfoot>
						  <tr>
							  <th>ID</th>
							  <th>Tipo</th>
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
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
	<?php
    $pdo = Database::connect();
    $sql = "SELECT lc.`id`, lc.`numero`, lc.`nro_revision`, lc.`nombre`,p.`nombre`, date_format(lc.`fecha`,'%d/%m/%y'), e.`estado` FROM `listas_corte` lc inner join estados_lista_corte e on e.id = lc.`id_estado_lista_corte` inner join proyectos p on p.id = lc.id_proyecto WHERE lc.`anulado` = 0 "; 
	foreach ($pdo->query($sql) as $row) {
        ?>
  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea cancelar la Lista de Corte?</div>
      <div class="modal-footer">
      <a href="eliminarListaCorte.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
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
    $sql = "SELECT `id`, `id_lista_corte_posicion`, `id_tipo_proceso`, `fecha_inicio_estimada`, `fecha_fin_estimada`, `fecha_inicio_real`, `fecha_fin_real`, `finalizado`, `observaciones` FROM `lista_corte_procesos` WHERE 1 "; 
	foreach ($pdo->query($sql) as $row) {
        ?>
	  <div class="modal fade" id="eliminarModalProceso_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalProcesoLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
		  <h5 class="modal-title" id="exampleModalProcesoLabel">Confirmación</h5>
		  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		  </div>
		  <div class="modal-body">¿Está seguro que desea eliminar el Proceso?</div>
		  <div class="modal-footer">
		  <a href="eliminarProcesoListaCorte.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
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
		$sql = "SELECT `id`, `id_lista_corte_conjunto`, `id_material`, `posicion`, `cantidad`, `largo`, `ancho`, `marca`, `peso`, `finalizado`, `id_colada`, `diametro`, `calidad` FROM `lista_corte_posiciones` WHERE 1 "; 
		foreach ($pdo->query($sql) as $row) {
			?>
		  <div class="modal fade" id="eliminarModalPosicion_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalPosicionLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
			<div class="modal-content">
			  <div class="modal-header">
			  <h5 class="modal-title" id="exampleModalPosicionLabel">Confirmación</h5>
			  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
			  </div>
			  <div class="modal-body">¿Está seguro que desea eliminar la Posición?</div>
			  <div class="modal-footer">
			  <a href="eliminarPosicionListaCorte.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
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
			$sql = "SELECT `id`, `id_lista_corte`, `nombre`, `cantidad`, `peso`, `id_estado_lista_corte_conjuntos` FROM `listas_corte_conjuntos` WHERE 1 "; 
			foreach ($pdo->query($sql) as $row) {
				$sql2 = "select count(*) cant from lista_corte_posiciones where id_lista_corte_conjunto = ".$row[0];
				$q2 = $pdo->prepare($sql2);
				$q2->execute();
				$data2 = $q2->fetch(PDO::FETCH_ASSOC);
				if (empty($data2['cant'])) {
				?>
				<div class="modal fade" id="eliminarModalConjunto_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
				<div class="modal-dialog" role="document">
				<div class="modal-content">
				  <div class="modal-header">
				  <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
				  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				  </div>
				  <div class="modal-body">¿Está seguro que desea eliminar el Conjunto?</div>
				  <div class="modal-footer">
				  <a href="eliminarConjuntoListaCorte.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
				  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
				  </div>
				</div>
				</div>
			  </div>
			  <?php
				} else {
			?>
				<div class="modal fade" id="eliminarModalConjunto_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
				<div class="modal-dialog" role="document">
				<div class="modal-content">
				  <div class="modal-header">
				  <h5 class="modal-title" id="exampleModalConjuntoLabel">Alerta</h5>
				  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				  </div>
				  <div class="modal-body">El Conjunto no puede ser eliminado debido a que tiene Posiciones sin cancelar.</div>
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
		$("#link_ver_lc").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione una lista de corte para ver detalle")
			}
		  })
		  $("#link_imprimir_lc").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione una lista de corte para imprimir")
			}
		  })
		$("#link_eliminar_lc").on("click",function(){
			/*let l=document.location.href;
			if(this.href==l || this.href==l+"#"){*/
      let target=this.dataset.target;
      if(target==undefined || target=="#"){
			  alert("Por favor seleccione una lista de corte para cancelar")
			}
		  })
		$("#link_modificar_lc").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione una lista de corte para modificar/revisar")
			}
		})
		$("#link_nuevo_conjunto").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione una lista de corte para crear un conjunto")
			}
		})
		
		
		//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
      $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_lc=t.find("td:first-child").html();
		    let nro_revision = t.find("td:nth-child(3)").html();
        if(t.hasClass('selected')){
          deselectRow(t);
		      get_conjuntos(id_lc)
          $("#link_ver_lc").attr("href","#");
          $("#link_imprimir_lc").attr("href","#");
          $("#link_eliminar_lc").attr("data-target","#");
          $("#link_modificar_lc").attr("href","#");
          $("#link_nuevo_conjunto").attr("href","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_conjuntos(id_lc)
          $("#link_ver_lc").attr("href","verListaCorte.php?id="+id_lc);
          $("#link_imprimir_lc").attr("href","imprimirListaCorte.php?id="+id_lc);
          $("#link_nuevo_conjunto").attr("href","nuevoConjuntoListaCorte.php?id="+id_lc);
          $("#link_modificar_lc").attr("href","modificarListaCorte.php?id="+id_lc+"&revision="+nro_revision);
          $("#link_eliminar_lc").attr("data-toggle","modal");
          $("#link_eliminar_lc").attr("data-target","#eliminarModal_"+id_lc);
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
	
	function get_conjuntos(id_lc){
      let datosUpdate = new FormData();
      datosUpdate.append('id_lc', id_lc);
      $.ajax({
        data: datosUpdate,
        url: 'get_conjuntos.php',
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
		  
		  $("#link_ver_conjunto_lc").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un conjunto para ver detalle")
			}
		  })
		  $("#link_modificar_conjunto").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione un conjunto para modificar")
				}
			})
			$("#link_eliminar_conjunto").on("click",function(){
				/*let l=document.location.href;
				if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
				  alert("Por favor seleccione un conjunto para eliminar")
				}
			})
			$("#link_nueva_posicion").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione un conjunto para agregar conceptos y posiciones")
				}
			})
			
          $('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
			var t=$(this).parent();
			t.parent().find("tr").removeClass("selected");

			let id_con=t.find("td:first-child").html();
			if(t.hasClass('selected')){
			  deselectRow(t);
			  get_posiciones(id_con)
			  $("#link_ver_conjunto_lc").attr("href","#");
			  $("#link_modificar_conjunto").attr("href","#");
			  $("#link_eliminar_conjunto").attr("data-target","#");
			  $("#link_nueva_posicion").attr("href","#");
			}else{
			  selectRow(t);
			  get_posiciones(id_con)
			  $("#link_ver_conjunto_lc").attr("href","verConjuntoListaCorte.php?id="+id_con);
			  $("#link_modificar_conjunto").attr("href","modificarConjuntoListaCorte.php?id="+id_con);
			  $("#link_nueva_posicion").attr("href","nuevaPosicionListaCorte.php?id="+id_con);
			  $("#link_eliminar_conjunto").attr("data-toggle","modal");
			  $("#link_eliminar_conjunto").attr("data-target","#eliminarModalConjunto_"+id_con);
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
	
	function get_posiciones(id_con){
      let datosUpdate = new FormData();
      datosUpdate.append('id_con', id_con);
      $.ajax({
        data: datosUpdate,
        url: 'get_posiciones.php',
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
		  
		  $("#link_ver_posicion_lc").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione una posición para ver detalle")
			}
		  })
		  $("#link_modificar_posicion").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione una posición para actualizar")
				}
			})
			$("#link_eliminar_posicion").on("click",function(){
				/*let l=document.location.href;
				if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
				  alert("Por favor seleccione una posición para eliminar")
				}
			})
			$("#link_nuevo_proceso").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione una posición para agregar proceso")
				}
			})
			
          $('#dataTables-example668').find("tbody tr td").not(":last-child").on( 'click', function () {
			var t=$(this).parent();
			t.parent().find("tr").removeClass("selected");

			let id_posicion=t.find("td:first-child").html();
			if(t.hasClass('selected')){
			  deselectRow(t);
			  get_procesos(id_posicion)
			  $("#link_ver_posicion_lc").attr("href","#");
			  $("#link_modificar_posicion").attr("href","#");
			  $("#link_eliminar_posicion").attr("data-target","#");
			  $("#link_nuevo_proceso").attr("href","#");
			}else{
			  selectRow(t);
			  get_procesos(id_posicion)
			  $("#link_ver_posicion_lc").attr("href","verPosicionConjuntoListaCorte.php?id="+id_posicion);
			  $("#link_modificar_posicion").attr("href","modificarPosicionConjuntoListaCorte.php?id="+id_posicion);
			  $("#link_nuevo_proceso").attr("href","nuevoProcesoPosicionListaCorte.php?id="+id_posicion);
			  $("#link_eliminar_posicion").attr("data-toggle","modal");
			  $("#link_eliminar_posicion").attr("data-target","#eliminarModalPosicion_"+id_posicion);
			}
		  });
          
        }
      });
    }
    
    </script>
	
	<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example669 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example669').DataTable({
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
    var table = $('#dataTables-example669').DataTable();
 
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
	
	function get_procesos(id_pos){
      let datosUpdate = new FormData();
      datosUpdate.append('id_pos', id_pos);
      $.ajax({
        data: datosUpdate,
        url: 'get_procesos.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          console.log(data);
          data = JSON.parse(data);
          console.log(data);

          $('#dataTables-example669').DataTable().destroy();
          $('#dataTables-example669').DataTable({
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
          var table = $('#dataTables-example669').DataTable();
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
		  
		  $("#link_ver_proceso_lc").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un proceso para ver detalle")
			}
		  })
		  $("#link_modificar_proceso").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione un proceso para actualizar")
				}
			})
			$("#link_eliminar_proceso").on("click",function(){
				/*let l=document.location.href;
				if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
				  alert("Por favor seleccione un proceso para eliminar")
				}
			})
			
			
          $('#dataTables-example669').find("tbody tr td").not(":last-child").on( 'click', function () {
			var t=$(this).parent();
			t.parent().find("tr").removeClass("selected");

			let id_proceso=t.find("td:first-child").html();
			if(t.hasClass('selected')){
			  deselectRow(t);
			  $("#link_ver_proceso_lc").attr("href","#");
			  $("#link_modificar_proceso").attr("href","#");
			  $("#link_eliminar_proceso").attr("data-target","#");
			}else{
			  selectRow(t);
			  $("#link_ver_proceso_lc").attr("href","verProcesoPosicionConjuntoListaCorte.php?id="+id_proceso);
			  $("#link_modificar_proceso").attr("href","modificarProcesoPosicionConjuntoListaCorte.php?id="+id_proceso);
			  $("#link_eliminar_proceso").attr("data-toggle","modal");
			  $("#link_eliminar_proceso").attr("data-target","#eliminarModalProceso_"+id_proceso);	
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