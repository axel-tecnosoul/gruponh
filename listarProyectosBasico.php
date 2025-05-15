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
  <link rel="stylesheet" href="assets/css/colResize.css">
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
          $ubicacion="Proyectos ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarProyectosBasico.php">
					  <div class="form-group mb-0">
						N.Sitio/N.Proy:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro" id="nro">
					  </div>
					  <div class="form-group mb-0">
						Nombre:&nbsp;<input class="form-control" size="15" type="text" value="<?php if (isset($_POST['nombre'])) echo $_POST['nombre'] ?>" name="nombre" id="nombre">
					  </div>
					  <div class="form-group mb-0">
						Cliente:&nbsp;
						<select name="cliente" id="cliente" class="js-example-basic-single">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 1 and activo = 1 and anulado = 0 order by nombre";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['nombre']."'";
								if ((isset($_POST['cliente']) && $fila['nombre']==$_POST['cliente'])) echo " selected ";
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
					  </div>
					  <div class="form-group mb-0">
						Linea:&nbsp;
						<select name="id_linea_negocio" id="id_linea_negocio" class="form-control">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `linea_negocio` FROM `lineas_negocio` WHERE 1 order by linea_negocio ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (isset($_POST['id_linea_negocio'])) {
									if ($fila['id']==$_POST['id_linea_negocio']) {
										echo " selected ";
									}
								}
								echo ">".$fila['linea_negocio']."</option>";
							}
							Database::disconnect();
							?>
							</select>
					  </div>
					  <div class="form-group mb-0">
						Estado:&nbsp;
						<select name="id_estado[]" id="id_estado" class="js-example-basic-multiple" multiple="multiple">
							<option value="">Todos</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_proyecto` WHERE 1 order by estado ";
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
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarProyectosBasico.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(277))) { ?><a href="nuevoProyecto.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a><?php } ?>&nbsp;<a href="#" onclick="jsExportar();"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;<a href="#" onclick="jsMapa();"><img src="img/copa.png" width="24" height="25" border="0" alt="Mapa Proyectos" title="Mapa Proyectos"></a>
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_proyecto"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(281))) {
						echo '<a href="#" id="link_nueva_tarea"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva Tarea" title="Nueva Tarea"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(278))) {
						echo '<a href="#" id="link_modificar_proyecto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
						echo '<a href="#" id="link_adjuntar_proyecto"><img src="img/import.png" width="24" height="25" border="0" alt="Adjuntar" title="Adjuntar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(284))) {
						echo '<a href="#" id="link_nuevo_suceso"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Agregar Suceso" title="Agregar Suceso"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(306))) {
						echo '<a href="#" id="link_agregar_presupuesto"><img src="img/dolar.png" width="24" height="25" border="0" alt="Añadir Presupuesto/s" title="Añadir Presupuesto/s"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(279))) {
						echo '<a href="#" id="link_eliminar_proyecto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
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
							  <th>Sitio/Sub</th>
							  <th class="d-none">Proy</th>
							  <th>Proy</th>
							  <th>Nombre</th>
							  <th>Descripción</th>
							  <th>Cliente</th>
							  <th>Estado</th>
							  <th>Responsable</th>
							  <th>F Adjudicado</th>
							  <th>F Entrega</th>
							  <th>Línea de Negocio</th>
							  <th>Claves</th>
                          </tr>
                        </thead>
                        <tbody>
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
                    <h5>Tareas
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
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
							  <th class="d-none">ID</th>
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
							  <th>Cómputo</th>
							  <th class="d-none">LC</th>
							  <th class="d-none">PL</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
						<tfoot>
                          <tr>
							  <th class="d-none">ID</th>
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
							  <th>Cómputo</th>
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
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
  <?php
    $pdo = Database::connect();
    $sql = " SELECT p.`id`, s.nro_sitio, s.nro_subsitio, p.nombre, p.`descripcion`, c.nombre, ep.`estado`, c2.nombre, date_format(p.`fecha_pedido`,'%d/%m/%y'), date_format(p.`fecha_entrega`,'%d/%m/%y'), ln.`linea_negocio`, p.`tags` FROM `proyectos` p inner join lineas_negocio ln on ln.id = p.`id_linea_negocio` inner join tipos_proyecto tp on tp.id = p.`id_tipo_proyecto` inner join estados_proyecto ep on ep.id = p.`id_estado_proyecto` inner join sitios s on s.id = p.id_sitio inner join cuentas c on c.id = p.id_cliente inner join cuentas c2 on c2.id = p.id_gerente WHERE p.`anulado` = 0 ";
	foreach ($pdo->query($sql) as $row) {
    ?>
  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea eliminar el proyecto?</div>
      <div class="modal-footer">
      <a href="eliminarProyecto.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
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
    $sql = " SELECT t.`id`, p.`descripcion`, s.nombre, t.`estructura`, sec.`sector`, tt.`tipo`, c.`nombre`, date_format(t.`fecha_inicio_estimada`,'%d/%m/%y'), date_format(t.`fecha_fin_estimada`,'%d/%m/%y'), date_format(t.`fecha_inicio_real`,'%d/%m/%y'), date_format(t.`fecha_fin_real`,'%d/%m/%y') FROM `tareas` t inner join proyectos p on p.id = t.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join sectores sec on sec.id = t.`id_sector` inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` left join cuentas c on c.id = t.`id_coordinador` WHERE t.`anulado` = 0 and p.anulado = 0 ";
    foreach ($pdo->query($sql) as $row) {
    ?>
  <div class="modal fade" id="eliminarModalTarea_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTarea" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabelTarea">Confirmación</h5>
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
	<script src="assets/js/colResize.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
    
	$('#dataTables-example666').DataTable({
       stateSave: false,
	   <?php
		if (!empty($_POST)) {
		?>
		'ajax': 'listarProyectosBasicoAjax.php?nro=<?php echo $_POST['nro'];?>&nombre=<?php echo $_POST['nombre'];?>&cliente=<?php echo $_POST['cliente'];?>&linea=<?php echo $_POST['id_linea_negocio'];?>&estado=<?php if (!empty($_POST['id_estado'])) echo implode(",",$_POST['id_estado']);?>',	
		<?php
		} else {
		?>
		'ajax': 'listarProyectosBasicoAjax.php?nro=252525&nombre=252525&cliente=252525&linea=252525&estado=252525',	
		<?php
		}
		?>
        responsive: false,
		serverSide: true,
		searching: false,
		
        processing: true,
		"createdRow": function(row, data, dataIndex) {
            // Limitar el texto de la columna 0 (Name)
            var maxLength = 20; // Número máximo de caracteres
            var cell = $('td', row).eq(4); // Obtener la celda de la primera columna
            var cellText = cell.text();

            if (cellText.length > maxLength) {
                var truncatedText = cellText.substring(0, maxLength) + '...';
                cell.html('<span title="' + cellText + '">' + truncatedText + '</span>');
            }
        },
		"colResize": {
			isEnabled: true,
			saveState: true,
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
		rowCallback: function(row, data, index) {
			$('td:eq(1)', row).addClass('d-none');
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
 
    
		
	$("#link_ver_proyecto").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un proyecto para ver detalle")
        }
      })
	  $("#link_nueva_tarea").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un proyecto para añadir una nueva tarea")
        }
      })
	  $("#link_modificar_proyecto").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un proyecto para modificar")
        }
      })
	  $("#link_adjuntar_proyecto").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un proyecto para adjuntar documentación")
        }
      })	
	    $("#link_nuevo_suceso").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un proyecto para añadir un nuevo suceso")
        }
      })
	  $("#link_agregar_presupuesto").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un proyecto para añadir un presupuesto")
        }
      })
	  $("#link_nueva_lc").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un proyecto para nueva lista de corte")
        }
      })
	  $("#link_nuevo_packing").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un proyecto para nuevo packing list")
        }
      })
	  $("#link_eliminar_proyecto").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un proyecto para eliminar")
        }
      })
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

		let id_proyecto=t.find("td:nth-child(2)").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          get_tareas(id_proyecto)
          $("#link_ver_proyecto").attr("href","#");
          $("#link_nueva_tarea").attr("href","#");
          $("#link_modificar_proyecto").attr("href","#");
          $("#link_adjuntar_proyecto").attr("href","#");
          $("#link_nuevo_suceso").attr("href","#");
          $("#link_agregar_presupuesto").attr("href","#");
		  $("#link_nueva_lc").attr("href","#");
		  $("#link_nuevo_packing").attr("href","#");
          $("#link_eliminar_proyecto").attr("data-target","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          get_tareas(id_proyecto)
          $("#link_ver_proyecto").attr("href","verProyecto.php?id="+id_proyecto);
          $("#link_nueva_tarea").attr("href","nuevaTarea.php?id="+id_proyecto);
          $("#link_modificar_proyecto").attr("href","modificarProyecto.php?id="+id_proyecto);
          $("#link_adjuntar_proyecto").attr("href","adjuntarProyecto.php?id="+id_proyecto);
          $("#link_nuevo_suceso").attr("href","nuevoSuceso.php?id="+id_proyecto);
          $("#link_agregar_presupuesto").attr("href","agregarPresupuestoProyecto.php?id="+id_proyecto);
		  $("#link_nueva_lc").attr("href","nuevaListaCorte.php?idProyecto="+id_proyecto);
		  $("#link_nuevo_packing").attr("href","nuevaPackingList.php?idProyecto="+id_proyecto);
          $("#link_eliminar_proyecto").attr("data-toggle","modal");
          $("#link_eliminar_proyecto").attr("data-target","#eliminarModal_"+id_proyecto);
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

    function get_tareas(id_proyecto){
      let datosUpdate = new FormData();
      datosUpdate.append('id_proyecto', id_proyecto);
      $.ajax({
        data: datosUpdate,
        url: 'get_tareas.php',
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
            data: data,
		  "columnDefs": [
			{
              "targets": [0,12,14,15],
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
          
        }
      });
    }

    //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
      $(document).on("click","#dataTables-example667 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_tarea=t.find("td:first-child").html();
        let tiene_computo = t.find("td:nth-child(13)").html();
		let tiene_lc = t.find("td:nth-child(15)").html();
		let tiene_packing  = t.find("td:nth-child(16)").html();
		let tt = t.find("td:nth-child(4)").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_ver_tarea").attr("href","#");
          $("#link_modificar_tarea").attr("href","#");
          $("#link_nuevo_computo").attr("href","#");
          $("#link_eliminar_tarea").attr("data-target","#");
        }else{
          table2.DataTable().rows().nodes().each( function (rowNode, index) {
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
          $("#link_eliminar_tarea").attr("data-target","#eliminarModalTarea_"+id_tarea);
        }
		  });
    
	function jsExportar() {
		document.location.href="exportProyectos.php?nro="+document.getElementById('nro').value+"&nombre="+document.getElementById('nombre').value+"&cliente="+document.getElementById('cliente').value+"&linea="+document.getElementById('id_linea_negocio').value+"&estado="+document.getElementById('id_estado').value;
	}
	function jsMapa() {
		document.location.href="mapaProyectos.php?nro="+document.getElementById('nro').value+"&nombre="+document.getElementById('nombre').value+"&cliente="+document.getElementById('cliente').value+"&linea="+document.getElementById('id_linea_negocio').value+"&estado="+document.getElementById('id_estado').value;
	}
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
<script src="assets/js/select2/select2-custom.js"></script>
<!-- Plugin used-->
  </body>
</html>