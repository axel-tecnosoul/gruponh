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
          $ubicacion="Estados de Tareas ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(368))) { ?><a href="nuevoEstadoTarea.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a><?php } ?>
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_estado_tarea"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(368))) {
						echo '<a href="#" id="link_modificar_estado_tarea"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(368))) {
						echo '<a href="#" id="link_eliminar_estado_tarea"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
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
							<th>Sitio</th>
							<th>Proyecto</th>
							<th>Coordinador</th>
							<th>Estado Proyecto</th>
							<th>Tarea</th>
							<th>Observaciones</th>
							<th>Recurso</th>
							<th>F.I.P</th>		
							<th>F.F.P</th>		
							<th>F.I.R</th>
							<th>F.F.R</th>
							<th>Comenzó</th>
							<th>Terminó</th>
							<th>Presentado</th>
							<th>Verificado</th>
							<th>Aprobado</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                            include 'database.php';
                            $pdo = Database::connect();
                            $sql = " SELECT e.`id`, s.`nombre`, s.nro_sitio, s.nro_subsitio, p.`nombre`, c1.nombre, ep.`estado`, t.estructura, e.`observaciones`, c2.nombre, date_format(e.`fecha_inicio_prevista`,'%d/%m/%y'), date_format(e.`fecha_fin_prevista`,'%d/%m/%y'), date_format(e.`fecha_inicio_real`,'%d/%m/%y'), date_format(e.`fecha_fin_real`,'%d/%m/%y'), e.`comentarios_inicio`, e.`comentarios_fin`, e.`presentado`, e.`verificado`, e.`aprobado_cliente`, date_format(e.`fecha_inicio_prevista`,'%y%m%d'), date_format(e.`fecha_fin_prevista`,'%y%m%d'), date_format(e.`fecha_inicio_real`,'%y%m%d'), date_format(e.`fecha_fin_real`,'%y%m%d') FROM `estados_tareas` e inner join `proyectos` p on p.id = e.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join estados_proyecto ep on ep.id = e.`id_estado_proyecto` inner join cuentas c1 on c1.id = e.`id_cuenta_coordinador` inner join cuentas c2 on c2.id = e.`id_cuenta_recurso` inner join tareas t on t.id = e.`id_tarea` WHERE 1 ";
                            
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
								echo '<td>'. $row[0] . '</td>';
								echo '<td>'. $row[1] .' ('.$row[2] .' / '.$row[3] . ')</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
                                echo '<td>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
								echo '<td>'. $row[8] . '</td>';
								echo '<td>'. $row[9] . '</td>';
								echo '<td><span style="display: none;">'. $row[19] . '</span>'. $row[10] . '</td>';
								echo '<td><span style="display: none;">'. $row[20] . '</span>'. $row[11] . '</td>';
								echo '<td><span style="display: none;">'. $row[21] . '</span>'. $row[12] . '</td>';
								echo '<td><span style="display: none;">'. $row[22] . '</span>'. $row[13] . '</td>';
								echo '<td>'. $row[14] . '</td>';
								echo '<td>'. $row[15] . '</td>';
								if ($row[16] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
                                if ($row[17] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
                                if ($row[18] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
                                
                                echo '</tr>';
                            }
                           Database::disconnect();
                          ?>
                        </tbody>
						<tfoot>
                          <tr>
							<th>ID</th>
							<th>Sitio</th>
							<th>Proyecto</th>
							<th>Coordinador</th>
							<th>Estado Proyecto</th>
							<th>Tarea</th>
							<th>Observaciones</th>
							<th>Recurso</th>
							<th>F.I.P</th>		
							<th>F.F.P</th>		
							<th>F.I.R</th>
							<th>F.F.R</th>
							<th>Comenzó</th>
							<th>Terminó</th>
							<th>Presentado</th>
							<th>Verificado</th>
							<th>Aprobado</th>
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
    $sql = " SELECT e.`id`, s.`nombre`, s.nro_sitio, s.nro_subsitio, p.`descripcion`, c1.nombre, ep.`estado`, t.estructura, e.`observaciones`, c2.nombre, date_format(e.`fecha_inicio_prevista`,'%d/%m/%y'), date_format(e.`fecha_fin_prevista`,'%d/%m/%y'), date_format(e.`fecha_inicio_real`,'%d/%m/%y'), date_format(e.`fecha_fin_real`,'%d/%m/%y'), e.`comentarios_inicio`, e.`comentarios_fin`, e.`presentado`, e.`verificado`, e.`aprobado_cliente` FROM `estados_tareas` e inner join `proyectos` p on p.id = e.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join estados_proyecto ep on ep.id = e.`id_estado_proyecto` inner join cuentas c1 on c1.id = e.`id_cuenta_coordinador` inner join cuentas c2 on c2.id = e.`id_cuenta_recurso` inner join tareas t on t.id = e.`id_tarea` WHERE 1 ";
	foreach ($pdo->query($sql) as $row) {
        ?>
  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea eliminar el estado de tarea?</div>
      <div class="modal-footer">
      <a href="eliminarEstadoTarea.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
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
		
	$("#link_ver_estado_tarea").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un estado de tarea para ver detalle")
        }
      })
	  $("#link_modificar_estado_tarea").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un estado de tarea para modificar")
        }
      })
	 
	  $("#link_eliminar_estado_tarea").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un estado de tarea para eliminar")
        }
      })	  
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_estado_tarea=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_ver_estado_tarea").attr("href","#");
          $("#link_modificar_estado_tarea").attr("href","#");
		      $("#link_eliminar_estado_tarea").attr("data-target","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_ver_estado_tarea").attr("href","verEstadoTarea.php?id="+id_estado_tarea);
          $("#link_modificar_estado_tarea").attr("href","modificarEstadoTarea.php?id="+id_estado_tarea);
          $("#link_eliminar_estado_tarea").attr("data-toggle","modal");
          $("#link_eliminar_estado_tarea").attr("data-target","#eliminarModal_"+id_estado_tarea);
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
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>