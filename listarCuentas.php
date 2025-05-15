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
          $ubicacion="Cuentas ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
		  <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarCuentas.php">
					  <div class="form-group mb-0">
						Tipo:&nbsp;
						<select name="id_tipo_cuenta" id="id_tipo_cuenta" class="form-control">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo_cuenta` FROM `tipos_cuenta` WHERE 1 order by tipo_cuenta ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (isset($_POST['id_tipo_cuenta'])) {
									if ($fila['id']==$_POST['id_tipo_cuenta']) {
										echo " selected ";
									}
								}
								echo ">".$fila['tipo_cuenta']."</option>";
							}
							Database::disconnect();
							?>
							</select>
					  </div>
					  <div class="form-group mb-0">
						Nombre Corto:&nbsp;<input class="form-control" size="15" type="text" value="<?php if (isset($_POST['nombre_corto'])) echo $_POST['nombre_corto'] ?>" name="nombre_corto">
					  </div>
					  <div class="form-group mb-0">
						Razón Social:&nbsp;<input class="form-control" size="15" type="text" value="<?php if (isset($_POST['razon_social'])) echo $_POST['razon_social'] ?>" name="razon_social">
					  </div>
					  
					  <div class="form-group mb-0">
						Recurso:&nbsp;
						<select name="es_recurso" id="es_recurso" class="form-control" >
							<option value="" selected>Seleccione...</option>
							<option value="1" <?php if (isset($_POST['es_recurso'])) { if ($_POST['es_recurso']==1) { echo " selected "; } } ?>>Si</option>
							<option value="2" <?php if (isset($_POST['es_recurso'])) { if ($_POST['es_recurso']==2) { echo " selected "; } } ?>>No</option>
							</select>
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarCuentas.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(260))) { ?><a href="nuevaCuenta.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva"></a><?php } ?>
					&nbsp;&nbsp;
					<a href="exportCuentas.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
					&nbsp;&nbsp;
					<a href="#" id="link_ver_cuenta"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>
                    &nbsp;&nbsp;
					<?php
					if (!empty(tienePermiso(261))) {
					?>
						<a href="#" id="link_modificar_cuenta"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>
						&nbsp;&nbsp;
					<?php
					}
					?>
					<?php
					if (!empty(tienePermiso(262))) {
					?>
						<a href="#" id="link_eliminar_cuenta"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>
						&nbsp;&nbsp;
					<?php
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
							  <th>Tipo</th>
							  <th>Nombre Corto</th>
							  <th>Razón Social</th>
							  <th>E-Mail</th>
							  <th>Teléfono</th>
							  <th>Es Recurso?</th>
							  <th>Activa</th>
                          </tr>
                        </thead>
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
    $sql = " SELECT c.`id`, tc.`tipo_cuenta`, c.`nombre`, c.`razon_social`, c.`email`, c.`telefono`, c.`activo`, c.`es_recurso` FROM `cuentas` c inner join tipos_cuenta tc on tc.id = c.`id_tipo_cuenta` WHERE c.`anulado` = 0 ";
    foreach ($pdo->query($sql) as $row) {
        ?>
  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea eliminar la cuenta?</div>
      <div class="modal-footer">
      <a href="eliminarCuenta.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
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
    $('#dataTables-example666').DataTable({
		stateSave: false,
		<?php
		if (!empty($_POST)) {
		?>
		'ajax': 'listarCuentasAjax.php?id_tipo_cuenta=<?php echo $_POST['id_tipo_cuenta'];?>&nombre_corto=<?php echo $_POST['nombre_corto'];?>&razon_social=<?php echo $_POST['razon_social'];?>&es_recurso=<?php echo $_POST['es_recurso'];?>',	
		<?php
		} else {
		?>
		'ajax': 'listarCuentasAjax.php?id_tipo_cuenta=222&nombre_corto=222&razon_social=222&es_recurso=222',	
		<?php
		}
		?>
        responsive: false,
		serverSide: true,
		searching: false,
		
        processing: true,
		dom: 'Bfrtp<"bottom"l>',
		"columnDefs": [
			{
              "targets": [0],
			  "className": 'd-none'
            }
		  ],
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
 
    
	$("#link_ver_cuenta").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una cuenta para ver detalle")
        }
      })
	  $("#link_modificar_cuenta").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una cuenta para modificarla")
        }
      })
	  $("#link_eliminar_cuenta").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione una cuenta para eliminar")
        }
      })	
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_cuenta=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_ver_cuenta").attr("href","#");
          $("#link_modificar_cuenta").attr("href","#");
		      $("#link_eliminar_cuenta").attr("data-target","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_ver_cuenta").attr("href","verCuenta.php?id="+id_cuenta);
          $("#link_modificar_cuenta").attr("href","modificarCuenta.php?id="+id_cuenta);
          $("#link_eliminar_cuenta").attr("data-toggle","modal");
		      $("#link_eliminar_cuenta").attr("data-target","#eliminarModal_"+id_cuenta);
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