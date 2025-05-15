
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
          $ubicacion="Tareas ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarTareas.php">
					  <div class="form-group mb-0">
						N.Sitio/N.Proy:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro">
					  </div>
					  <div class="form-group mb-0">
						Tipo:&nbsp;
						<select name="id_tipo_tarea" id="id_tipo_tarea" class="form-control">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_tarea` WHERE 1 order by tipo ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (isset($_POST['id_tipo_tarea'])) {
									if ($fila['id']==$_POST['id_tipo_tarea']) {
										echo " selected ";
									}
								}
								echo ">".$fila['tipo']."</option>";
							}
							Database::disconnect();
							?>
							</select>
					  </div>
					  <div class="form-group mb-0">
						Completada:&nbsp;
						<select name="completada" id="completada" class="form-control">
							<option value="">Seleccione...</option>
							<option value="1" <?php if (isset($_POST['completada'])) { if ($_POST['completada']==1) { echo " selected "; } } ?> >Si</option>
							<option value="0" <?php if (isset($_POST['completada'])) { if ($_POST['completada']==0) { echo " selected "; } } ?> >No</option>
							</select>
					  </div>
					  <div class="form-group mb-0">
						Orden:&nbsp;
						<select name="orden" id="orden" class="form-control">
							<option value="t.id asc" <?php if (isset($_POST['orden'])) { if ($_POST['orden']=="t.id asc") { echo " selected "; } } ?>>Fecha Creación Asc</option>
							<option value="t.id desc" <?php if (isset($_POST['orden'])) { if ($_POST['orden']=="t.id desc") { echo " selected "; } } ?>>Fecha Creación Desc</option>
							<option value="tt.tipo asc" <?php if (isset($_POST['orden'])) { if ($_POST['orden']=="tt.tipo asc") { echo " selected "; } } ?>>Tipo Tarea Asc</option>
							<option value="tt.tipo desc" <?php if (isset($_POST['orden'])) { if ($_POST['orden']=="tt.tipo desc") { echo " selected "; } } ?>>Tipo Tarea Desc</option>
						</select>
					  </div>
					  
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarTareas.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(281))) { ?><a href="nuevaTareaTarea.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva"></a><?php } ?>&nbsp;<a href="exportTareas.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
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
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
							  <th class="d-none">ID</th>
							  <th>Sitio</th>
							  <th>Subsitio</th>
							  <th>Nro.Proy</th>
							  <th>Proyecto</th>
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
							  <th>Cómputo</th>
							  <th>Cómputo ID</th>
							  <th>LC</th>
							  <th>PL</th>
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
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
  <?php
    $pdo = Database::connect();
    $sql = " SELECT t.`id`, p.`descripcion`, s.nombre, t.`estructura`, sec.`sector`, tt.`tipo`, c.`nombre`, date_format(t.`fecha_inicio_estimada`,'%d/%m/%y'), date_format(t.`fecha_fin_estimada`,'%d/%m/%y'), date_format(t.`fecha_inicio_real`,'%d/%m/%y'), date_format(t.`fecha_fin_real`,'%d/%m/%y'), c2.`nombre`,t.observaciones FROM `tareas` t inner join proyectos p on p.id = t.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join sectores sec on sec.id = t.`id_sector` inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` left join cuentas c on c.id = t.`id_coordinador` left join cuentas c2 on c2.id = t.`id_recurso` WHERE t.`anulado` = 0 and p.anulado = 0 ";
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
	<script src="assets/js/colResize.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
   
	$('#dataTables-example666').DataTable({
        ordering: false,
		stateSave: false,
		'ajax': 'listarTareasAjax.php?nro=<?php echo $_POST['nro'];?>&id_tipo_tarea=<?php echo $_POST['id_tipo_tarea'];?>&completada=<?php echo $_POST['completada'];?>&orden=<?php echo $_POST['orden'];?>',
        responsive: false,
		serverSide: true,
		searching: false,
        processing: true,
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
			$('td:eq(0)', row).addClass('d-none');
		},
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
			let tiene_lc = t.find("td:nth-child(19)").html();
			let tiene_packing = t.find("td:nth-child(20)").html();
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
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>