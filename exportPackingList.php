<?php
session_start(); 
header('Content-Disposition: attachment; filename="packing_list.xls"');
include 'database.php';
?>
<!doctype html>
<html lang="en">
<head>
	<?php include('head_tables.php');?>
</head>
<body>

			<div class="row">
				<div class="table-responsive">
				<a href="#" id="aExportar" onclick="$('#example2').tableExport({type:'excel',escape:'false'});"></a>
				<table id="example2" name="formularios" style="visibility:hidden;">
					<thead>
		                <tr>
							  <th>ID</th>
							  <th>Revisión</th>
							  <th>Proyecto</th>
							  <th>Fecha</th>
							  <th>Estado</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php
                            $pdo = Database::connect();
                           //$sql = "SELECT pl.`id`, pl.`nro_revision`, p.`nombre`, date_format(pl.`fecha`,'%d/%m/%y'), e.`estado` FROM `packing_lists` pl inner join proyectos p on p.id = pl.id_proyecto inner join estados_packing_list e on e.id = pl.`id_estado_packing_list` WHERE pl.`anulado` = 0 "; 
                           $sql = "SELECT pl.id, plr.nro_revision, p.nombre, date_format(plr.fecha,'%d/%m/%y'), e.estado FROM packing_lists pl INNER JOIN packing_lists_revisiones plr ON pl.id=plr.id_packing_list AND pl.ultimo_nro_revision=plr.nro_revision inner join proyectos p on p.id = plr.id_proyecto inner join estados_packing_list e on e.id = plr.id_estado_packing_list WHERE pl.anulado = 0 "; 
									 
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
                                echo '<td>'. $row[0] . '</td>';
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
		<!-- Plugins JS Ends-->
		<!-- Plugins JS Ends-->
		<!-- Theme js-->
		<script src="assets/js/script.js"></script>
</body>
</html>
<script>document.getElementById("aExportar").click();</script>