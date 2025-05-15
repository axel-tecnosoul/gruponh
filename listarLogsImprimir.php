<?php 
ini_set( "session.gc_maxlifetime", 600 );
session_start(); 
if(empty($_SESSION['user']))
{
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
      
      <!-- Page Header Ends                              -->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <!-- Page Sidebar Ends-->
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body">
          <!-- Container-fluid starts-->
          <div class="container-fluid">
			<div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarLogs.php">
					  <div class="form-group mb-0">
						Fecha Desde:&nbsp;<input class="form-control" type="date" onfocus="this.showPicker()" name="fechaDesde" value="<?php echo $_POST['fechaDesde']; ?>" readonly="readonly">
					  </div>
					  <div class="form-group mb-0">
						Fecha Hasta:&nbsp;<input class="form-control" type="date" onfocus="this.showPicker()" name="fechaHasta" value="<?php echo $_POST['fechaHasta']; ?>" readonly="readonly">
					  </div>
					</form>
				</div>
			  </div>
			</div>
			</div>
			
			<?php
			include 'database.php';
			?>			
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Logs&nbsp;
					</h5><span>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" border="1" cellpadding="10" id="dataTables-example666">
                        <thead>
                          <tr>
						  <th>ID</th>
						  <th>Fecha/Hora</th>
						  <th>Usuario</th>
						  <th>Módulo</th>
						  <th>Detalle</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php 
							$pdo = Database::connect();
							$sql = " SELECT l.`id`, date_format(l.`fecha_hora`,'%d/%m/%y %H:%i'), u.`usuario`, l.`detalle_accion`,l.modulo FROM logs l inner join usuarios u on u.id = l.id_usuario WHERE 1 ";
							if (!empty($_POST['fechaDesde'])) {
								$sql .= " AND l.`fecha_hora` >= '".$_POST['fechaDesde']."'";
							}
							if (!empty($_POST['fechaHasta'])) {
								$sql .= " AND l.`fecha_hora` <= '".$_POST['fechaHasta']."'";
							}
							if (!empty($_POST['id_usuario'])) {
								$sql .= " AND l.`id_usuario` = ".$_POST['id_usuario'];
							}
							$sql .= " order by id desc ";
							foreach ($pdo->query($sql) as $row) {
								echo '<tr>';
								echo '<td>'. $row[0] . '</td>';
								echo '<td>'. $row[1] . 'hs</td>';
								echo '<td>'. $row[2] . '</td>';
								echo '<td>'. $row[4] . '</td>';
								echo '<td>'. $row[3] . '</td>';
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
              <!-- Zero Configuration  Ends-->
              <!-- Feature Unable /Disable Order Starts-->
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        
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
			$('#dataTables-example667').DataTable({
				stateSave: true,
				responsive: true,
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
		});
		
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
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
	<script src="assets/js/script.js"></script>
    <!-- Plugin used-->
  </body>
</html>