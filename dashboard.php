<?php 
require("config.php");
require 'database.php';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_tables.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/calendar.css">
	<link rel="stylesheet" href="assets/css/colResize.css">
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
        <div class="page-body">
          <div class="container-fluid">
            <div style="padding-top:10px">
              
            </div>
          </div>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
		  <div class="calendar-wrap">
            <div class="row">
                <div class="col-sm-4">
                  <div class="card">
                    <div class="card-header">
                      <h5>Calendario de Tareas y Actividades</h5>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col-md-12">
                          <div id="cal-basic"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
				<div class="col-sm-8">
                  <div class="card">
                  <div class="card-header">
                    <h5>Panel de Noticias / Novedades
					</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display" id="dataTables-example667">
                        <thead>
                          <tr>
						  <th>ID</th>
						  <th>Fecha</th>
						  <th>Título</th>
						  <th>Resumen</th>
						  <th>Importancia</th>
						  </tr>
                        </thead>
                        <tbody>
                          <?php 
							$pdo = Database::connect();
							$sql = " SELECT a.`id`, date_format(a.`fecha`,'%d/%m/%y'), a.`titulo`, a.`resumen`, r.relevancia  FROM anuncios_dashboard_cuentas ac inner join `anuncios_dashboard` a on a.id = ac.id_anuncio inner join relevancias r on r.id = a.`id_relevancia` inner join cuentas c on c.id = ac.id_cuenta_destino WHERE 1 and c.id_usuario = ".$_SESSION['user']['id'];
                            
							foreach ($pdo->query($sql) as $row) {
								echo '<tr>';
								echo '<td><a href="verEvento.php?id='. $row[0] . '">'. $row[0] . '</a></td>';
                                echo '<td>'. $row[1] . '</td>';
                                echo '<td>'. $row[2] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
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
              </div>
			</div> 
			<div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo "Estados de Tareas"; echo '&nbsp;&nbsp;'; /*if (!empty(tienePermiso(281))) { ?><a href="nuevaTarea.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva"></a><?php } */?>&nbsp;<a href="exportTareas.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_tarea"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(290))) {
						echo '<a href="#" id="link_nuevo_computo"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo Cómputo / LC / Packing" title="Nuevo Cómputo / LC / Packing"></a>';
						echo '&nbsp;&nbsp;';
					}										
					if (!empty(tienePermiso(282))) {
						echo '<a href="#" id="link_modificar_tarea"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(283))) {
						echo '<a href="#" id="link_eliminar_tarea"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					?>
					</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display" id="dataTables-example666">
                        <thead>
                          <tr>
							  <th class="d-none">ID</th>
							  <th>Sitio</th>
							  <th>Sub</th>
							  <th>Proy</th>
							  <th>Nombre</th>
							  <th>Estructura</th>
							  <th>Sector</th>
							  <th>Tarea</th>
							  <th>Recurso</th>
							  <th>Coordinador</th>
							  <th>Observaciones</th>
							  <th>FIP</th>
							  <th>FFP</th>
							  <th>FIR</th>
							  <th>FFR</th>
							  <th>Completada</th>
							  <th class="d-none">Cómputo</th>
							  <th class="d-none">LC</th>
							  <th class="d-none">PL</th>

                          </tr>
                        </thead>
                        <tbody>
                          <?php
                            $pdo = Database::connect();
							$sql = " SELECT t.`id`, p.`descripcion`, s.nombre, t.`estructura`, sec.`sector`, tt.`tipo`, c.`nombre`, date_format(t.`fecha_inicio_estimada`,'%d/%m/%y'), date_format(t.`fecha_fin_estimada`,'%d/%m/%y'), date_format(t.`fecha_inicio_real`,'%d/%m/%y'), date_format(t.`fecha_fin_real`,'%d/%m/%y'), c2.`nombre`,t.observaciones,s.nro_sitio,s.nro_subsitio,p.nro,p.nombre FROM `tareas` t inner join proyectos p on p.id = t.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join sectores sec on sec.id = t.`id_sector` inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` left join cuentas c on c.id = t.`id_coordinador` left join cuentas c2 on c2.id = t.`id_recurso` WHERE t.`anulado` = 0 and p.anulado = 0 and (c2.id_usuario = ".$_SESSION['user']['id']." or c.id_usuario = ".$_SESSION['user']['id'].")";
							
                            foreach ($pdo->query($sql) as $row) {
								
								$tieneComputo = 0;
								$sql2 = "SELECT `id` from computos where id_tarea = ? and id_estado <> 6 ";
								$q2 = $pdo->prepare($sql2);
								$q2->execute([$row[0]]);
								$data2 = $q2->fetch(PDO::FETCH_ASSOC);
								if (!empty($data2)) {
									$tieneComputo = 1;	
								}
								$tieneLC = 0;
								$sql2 = "SELECT `id` from listas_corte where id_tarea = ? ";
								$q2 = $pdo->prepare($sql2);
								$q2->execute([$row[0]]);
								$data2 = $q2->fetch(PDO::FETCH_ASSOC);
								if (!empty($data2)) {
									$tieneLC = 1;	
								}
								$tienePL = 0;
								$sql2 = "SELECT `id` from packing_lists where id_tarea = ? ";
								$q2 = $pdo->prepare($sql2);
								$q2->execute([$row[0]]);
								$data2 = $q2->fetch(PDO::FETCH_ASSOC);
								if (!empty($data2)) {
									$tienePL = 1;	
								}
								
                                echo '<tr>';
                                echo '<td class="d-none">'. $row[0] . '</td>';
                                echo '<td>'. $row[13] . '</td>';
								echo '<td>'. $row[14] . '</td>';
								echo '<td>'. $row[15] . '</td>';
								echo '<td>'. $row[16] . '</td>';
								echo '<td>'. $row[3] . '</td>';
								echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
                                echo '<td>'. $row[11] . '</td>';
                                echo '<td>'. $row[6] . '</td>';
								echo '<td>'. $row[12] . '</td>';
								echo '<td>'. $row[7] . '</td>';
								echo '<td>'. $row[8] . '</td>';
								echo '<td>'. $row[9] . '</td>';
								echo '<td>'. $row[10] . '</td>';
								if ($row[10] != '00/00/00') {
									echo '<td>Si</td>';	
								} else {
									echo '<td>No</td>';	
								}
                                if ($tieneComputo == 1) {
									echo '<td class="d-none">Si</td>';	
								} else {
									echo '<td class="d-none">No</td>';	
								}
								if ($tieneLC == 1) {
									echo '<td class="d-none">Si</td>';	
								} else {
									echo '<td class="d-none">No</td>';	
								}
								if ($tienePL == 1) {
									echo '<td class="d-none">Si</td>';	
								} else {
									echo '<td class="d-none">No</td>';	
								}
                                echo '</tr>';
                            }
                           Database::disconnect();
                          ?>
                        </tbody>
						<tfoot>
                          <tr>
							  <th class="d-none">ID</th>
							  <th>Sitio</th>
							  <th>Sub</th>
							  <th>Proy</th>
							  <th>Nombre</th>
							  <th>Estructura</th>
							  <th>Sector</th>
							  <th>Tarea</th>
							  <th>Recurso</th>
							  <th>Coordinador</th>
							  <th>Observaciones</th>
							  <th>FIP</th>
							  <th>FFP</th>
							  <th>FIR</th>
							  <th>FFR</th>
							  <th>Completada</th>
							  <th class="d-none">Cómputo</th>
							  <th class="d-none">LC</th>
							  <th class="d-none">PL</th>
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
          <!-- Container-fluid Ends-->
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
	<?php
    $pdo = Database::connect();
    $sql = " SELECT t.`id`, p.`descripcion`, s.nombre, t.`estructura`, sec.`sector`, tt.`tipo`, c.`nombre`, date_format(t.`fecha_inicio_estimada`,'%d/%m/%y'), date_format(t.`fecha_fin_estimada`,'%d/%m/%y'), date_format(t.`fecha_inicio_real`,'%d/%m/%y'), date_format(t.`fecha_fin_real`,'%d/%m/%y') FROM `tareas` t inner join proyectos p on p.id = t.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join sectores sec on sec.id = t.`id_sector` inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` left join cuentas c on c.id = t.`id_coordinador` WHERE t.`anulado` = 0 and p.anulado = 0 ";
    foreach ($pdo->query($sql) as $row) {
    ?>
  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea eliminar la tarea?</div>
      <div class="modal-footer">
      <a href="eliminarTarea.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
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
	<script src="assets/js/bootstrap/tableExport.js"></script>
	<script src="assets/js/bootstrap/jquery.base64.js"></script>
    <script src="assets/js/chart/chartist/chartist.js"></script>
    <script src="assets/js/chart/morris-chart/raphael.js"></script>
    <script src="assets/js/chart/morris-chart/morris.js"></script>
    <script src="assets/js/chart/morris-chart/prettify.min.js"></script>
    <script src="assets/js/chart/chartjs/chart.min.js"></script>
    <script src="assets/js/chart/flot-chart/excanvas.js"></script>
    <script src="assets/js/chart/flot-chart/jquery.flot.js"></script>
    <script src="assets/js/chart/flot-chart/jquery.flot.time.js"></script>
    <script src="assets/js/chart/flot-chart/jquery.flot.categories.js"></script>
    <script src="assets/js/chart/flot-chart/jquery.flot.stack.js"></script>
    <script src="assets/js/chart/flot-chart/jquery.flot.pie.js"></script>
    <script src="assets/js/chart/flot-chart/jquery.flot.symbol.js"></script>
    <script src="assets/js/chart/google/google-chart-loader.js"></script>
    <script src="assets/js/chart/peity-chart/peity.jquery.js"></script>
    <script src="assets/js/prism/prism.min.js"></script>
    <script src="assets/js/clipboard/clipboard.min.js"></script>
    <script src="assets/js/counter/jquery.waypoints.min.js"></script>
    <script src="assets/js/counter/jquery.counterup.min.js"></script>
    <script src="assets/js/counter/counter-custom.js"></script>
    <script src="assets/js/custom-card/custom-card.js"></script>
    <script src="assets/js/dashboard/project-custom.js"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
	<script src="assets/js/calendar/moment.min.js"></script>
    <script src="assets/js/calendar/fullcalendar.min.js"></script>
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
	<script src="assets/js/chart/morris-chart/raphael.js"></script>
    <script src="assets/js/chart/morris-chart/morris.js"></script>
    <script src="assets/js/chart/morris-chart/prettify.min.js"></script>
    <script src="assets/js/chart/morris-chart/morris-script.js"></script>
	<script src="assets/js/colResize.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example666 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example666').DataTable({
        stateSave: false,
        responsive: false,
		"dom": 'rtip',
		"colResize": {
			isEnabled: true,
			saveState: false,
			hoverClass: 'dt-colresizable-hover',
			hasBoundCheck: true,
			minBoundClass: 'dt-colresizable-bound-min',
			maxBoundClass: 'dt-colresizable-bound-max',
			isResizable: function (column) {
				return true;
			},
			onResizeStart: function (column, columns) {
			},
			onResize: function (column) {
			},
			onResizeEnd: function (column, columns) {
			},
			getMinWidthOf: function ($thNode) {
			},
			stateSaveCallback: function (settings, data) {
			},
			stateLoadCallback: function (settings) {
			}
		},
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
	$("#link_ver_tarea").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una tarea para ver detalle")
        }
      })
	  $("#link_modificar_tarea").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una tarea para modificar")
        }
      })
	  $("#link_nuevo_computo").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Ya tiene uno asociado")
        }
      })
	  $("#link_eliminar_tarea").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione una tarea para eliminar")
        }
      })
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
  $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_tarea=t.find("td:first-child").html();
		let tiene_computo = t.find("td:nth-child(17)").html();
		let tiene_lc = t.find("td:nth-child(18)").html();
		let tiene_packing = t.find("td:nth-child(19)").html();
		let tt = t.find("td:nth-child(8)").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_ver_tarea").attr("href","#");
          $("#link_modificar_tarea").attr("href","#");
          $("#link_nuevo_computo").attr("href","#");
          $("#link_eliminar_tarea").attr("data-target","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_ver_tarea").attr("href","verTarea.php?id="+id_tarea);
          $("#link_modificar_tarea").attr("href","modificarTarea.php?id="+id_tarea);
          if (tt == 'Computos') {
			if (tiene_computo == 'No') {
				$("#link_nuevo_computo").attr("href","nuevoComputo.php?id="+id_tarea);  
			}
		  } else if (tt == 'Planos y LC') {
			if (tiene_lc == 'No') {
				$("#link_nuevo_computo").attr("href","nuevaListaCorte.php?idTarea="+id_tarea);  
			}
		  } else if (tt == 'Packing List') {
			if (tiene_packing == 'No') {
				$("#link_nuevo_computo").attr("href","nuevaPackingList.php?idTarea="+id_tarea);  
			}
		  } else {
			$("#link_nuevo_computo").attr("href","#");
          }
          $("#link_eliminar_tarea").attr("data-toggle","modal");
          $("#link_eliminar_tarea").attr("data-target","#eliminarModal_"+id_tarea);
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
	
	
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
	<script>
	"use strict";
	var basic_calendar = {
		init: function() {
			$('#cal-basic').fullCalendar({
				defaultDate: '<?php echo date('Y-m-d');?>',
				editable: false,
				selectable: false,
				selectHelper: true,
				droppable: true,
				eventLimit: true,
				select: function(start, end, allDay) {
					var title = prompt('Event Title:');
					if (title) {
						$('#cal-basic').fullCalendar('renderEvent',
						{
							title: title,
							start: start._d,
							end: end._d,
							allDay: allDay
						},
						true
						);
					}
					$('#cal-basic').fullCalendar('unselect');
				},
				events: [
				<?php 
				
				$pdo = Database::connect();
				$sql = " SELECT a.`id`, a.`fecha`, a.`titulo`, r.color  FROM anuncios_dashboard_cuentas ac inner join `anuncios_dashboard` a on a.id = ac.id_anuncio inner join relevancias r on r.id = a.`id_relevancia` inner join cuentas c on c.id = ac.id_cuenta_destino WHERE muestra_calendario = 1 and c.id_usuario = ".$_SESSION['user']['id'];
				foreach ($pdo->query($sql) as $row) {
				?>
				{
					title: '<?php echo $row[2]; ?>',
					start: '<?php echo $row[1]; ?>',
					color: '<?php echo $row[3]; ?>',
					url: 'verEvento.php?id=<?php echo $row[0]; ?>'
				},
				<?php 
				}
				Database::disconnect();
				?>
				]
			});
		}
	};
	(function($) {
		"use strict";
		basic_calendar.init()
	})(jQuery);
	
	
	</script>
	
  </body>
</html>