<?php 
ini_set( "session.gc_maxlifetime", 600 );
session_start(); 
if(empty($_SESSION['user']))
{
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
        <div class="page-body">
          <div class="container-fluid">
            <div style="padding-top:10px">
              
            </div>
          </div>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
			<div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarLogs.php">
					  <div class="form-group mb-0">
						Fecha Desde:&nbsp;<input class="form-control" type="date" onfocus="this.showPicker()" name="fechaDesde" required="required">
					  </div>
					  <div class="form-group mb-0">
						Fecha Hasta:&nbsp;<input class="form-control" type="date"  onfocus="this.showPicker()"name="fechaHasta" required="required">
					  </div>
					  <div class="form-group mb-0">
						Módulos:&nbsp;<select name="modulo" id="modulo" class="form-control">
										<option value="">Seleccione...</option>
										<?php 
										$pdo = Database::connect();
										$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
										$sqlZon = "SELECT distinct `modulo` FROM logs WHERE modulo <> '' order by modulo ";
										$q = $pdo->prepare($sqlZon);
										$q->execute();
										while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
											echo "<option value='".$fila['modulo']."'";
											echo ">".$fila['modulo']."</option>";
										}
										Database::disconnect();
										?>
										</select>
					  </div>
					  <div class="form-group mb-0">
						Usuarios:&nbsp;<select name="id_usuario" id="id_usuario" class="form-control">
										<option value="">Seleccione...</option>
										<?php 
										$pdo = Database::connect();
										$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
										$sqlZon = "SELECT `id`, `usuario` FROM `usuarios` WHERE activo = 1 order by usuario ";
										$q = $pdo->prepare($sqlZon);
										$q->execute();
										while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
											echo "<option value='".$fila['id']."'";
											echo ">".$fila['usuario']."</option>";
										}
										Database::disconnect();
										?>
										</select>
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarLogs.php'">Buscar</button>
						&nbsp;
						<button class="btn btn-secondary" onclick="document.form1.target='_blank';document.form1.action='listarLogsImprimir.php'">Imprimir</button>
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
                    <h5>Logs&nbsp;<a href="exportLogs.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a></h5><span>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
						  <th>ID</th>
						  <th>Fecha/Hora</th>
						  <th>Usuario</th>
						  <th>Módulo</th>
						  <th>Detalle</th>
						  <th>Acciones</th>
						  </tr>
                        </thead>
                        <tbody>
                          <?php 
							if (!empty($_POST)) {
								$pdo = Database::connect();
								$sql = " SELECT l.id, date_format(l.fecha_hora,'%d/%m/%y %H:%i'), u.usuario, l.detalle_accion, l.modulo, l.link FROM logs l inner join usuarios u on u.id = l.id_usuario WHERE 1 ";
								if (!empty($_POST['fechaDesde'])) {
									$sql .= " AND l.fecha_hora >= '".$_POST['fechaDesde']."'";
								}
								if (!empty($_POST['fechaHasta'])) {
									$sql .= " AND l.fecha_hora <= '".$_POST['fechaHasta']."'";
								}
								if (!empty($_POST['id_usuario'])) {
									$sql .= " AND l.id_usuario = ".$_POST['id_usuario'];
								}
								if (!empty($_POST['modulo'])) {
									$sql .= " AND l.modulo = '".$_POST['modulo']."' ";
								}
								$sql .= " order by id desc ";
								foreach ($pdo->query($sql) as $row) {
									echo '<tr>';
									echo '<td>'. $row[0] . '</td>';
									echo '<td>'. $row[1] . 'hs</td>';
									echo '<td>'. $row[2] . '</td>';
									echo '<td>'. $row[4] . '</td>';
									echo '<td>'. $row[3] . '</td>';
									if(!empty($row[5])){
									  echo '<td> <a href="'. $row[5] . '" id="link_ver_material"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a> </td>';
									}else{
									  echo '<td> </td>';
									}
									echo '</tr>';
								}	
								Database::disconnect();	
							}
							
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
        <?php include("footer.php"); ?>
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
			$('#dataTables-example666').DataTable({
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
    <!-- Plugin used-->
  </body>
</html>