<?php
session_start(); 
header('Content-Disposition: attachment; filename="computos.xls"');
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
							  <th>Número</th>
							  <th>Revisión</th>
							  <th>Concepto</th>
							  <th>Necesidad</th>
							  <th>Solicitado</th>
							  <th>Stock</th>
							  <th>Reservado</th>
							  <th>Comprando</th>
							  <th>Saldo</th>
							  <th>Sitio/Sub/Proy</th>
							  <th>Proyecto</th>
							  <th>Tarea</th>
							  <th>Fecha</th>
							  <th>Realizo</th>
							  <th>Estado</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php     
						$pdo = Database::connect();
						$sql = " SELECT c.`id`, c.`nro`, c.`nro_revision`, m.concepto, d.fecha_necesidad, d.cantidad,SUM(id.saldo) AS disponible, d.reservado, d.comprado, d.aprobado, s.nro_sitio, s.nro_subsitio, p.nombre,t.`observaciones`, c.`fecha`, cu.`nombre`, ec.`estado`, p.nro FROM computos_detalle d inner join materiales m on m.id = d.id_material left JOIN ingresos_detalle id ON id.id_material=m.id inner join `computos` c on c.id = d.id_computo inner join estados_computos ec on ec.id = c.id_estado inner join cuentas cu on cu.id = c.id_cuenta_solicitante inner join tareas t on t.id = c.id_tarea inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE d.cancelado = 0 ";
						if (!empty($_GET['nro'])) {
							$sql .= " and (p.nro = ".$_GET['nro']." or s.nro_sitio = ".$_GET['nro'].") ";
						}
						if (!empty($_GET['fecha'])) {
							$sql .= " AND c.fecha >= '".$_GET['fecha']."' ";
						}
						if (!empty($_GET['fechah'])) {
							$sql .= " AND c.fecha <= '".$_GET['fechah']."' ";
						}
						
						if (!empty($_GET['estado'])) {
							$sql .= " and c.id_estado = ".$_GET['estado'];
						}
						$sql .= " GROUP BY m.id ";
						
						foreach ($pdo->query($sql) as $row) {
							echo '<tr>';
							echo '<td>'. $row[1] . '</td>';
							echo '<td>'. $row[2] . '</td>'; 				
							echo '<td>'. $row[3] . '</td>';
							echo '<td>'. $row[4] . '</td>';
							echo '<td>'. $row[5] . '</td>';
							echo '<td>'. $row[6] . '</td>';
							echo '<td>'. $row[7] . '</td>';
							echo '<td>'. $row[8] . '</td>';
							echo '<td>'. $row[5]-$row[6]-$row[7]-$row[8] . '</td>';
							echo '<td>'. $row[10] .' / '.$row[11] .' / '.$row[17] . '</td>'; //sitio
							echo '<td>'. $row[12] . '</td>'; //proyecto
							echo '<td>'. $row[13] . '</td>'; //tarea
							echo '<td>'. $row[14] . '</td>'; //fecha
							echo '<td>'. $row[15] . '</td>'; //cuenta
							echo '<td>'. $row[16] . '</td>'; //estado
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