<?php
session_start(); 
header('Content-Disposition: attachment; filename="sitios.xls"');
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
							  <th>Sitio / Subsitio</th>
							  <th>Nombre</th>
							  <th>Dueño</th>
							  <th>Dirección</th>
							  <th>Latitud</th>
							  <th>Longitud</th>
							  <th>Observaciones</th>
							  <th>País</th>
							  <th>Provincia</th>
							  <th>Localidad</th>
							  <th>Tipo Estructura</th>
							  <th>Altura</th>
							  <th>Ancho Cara</th>
							  <th>Peso Estructura</th>
							  <th>Tipo Montante</th>
							  <th>Paso</th>
							  <th>Beta</th>
							  <th>Rugosidad</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php     
						$pdo = Database::connect();
						$sql = " SELECT s.`id`, s.nro_sitio, s.nro_subsitio, s.`nombre`, cue.`nombre`, s.`direccion`, s.`latitud`, s.`longitud`, s.`observaciones`, p.`nombre`, prov.`provincia`, loc.`localidad`, te.`tipo`, s.`altura`, s.`ancho_cara`, s.`peso_estructura`, tm.`tipo`, s.`paso`, s.`beta`, s.`rugosidad` FROM `sitios` s left join sitios s2 on s2.id = s.id_sitio_superior inner join paises p on p.id = s.id_pais inner join provincias prov on prov.id = s.id_provincia inner join localidades loc on loc.id = s.id_localidad left join tipos_estructura te on te.id = s.id_tipo_estructura left join tipos_montaje tm on tm.id = s.id_tipo_montaje inner join cuentas cue on cue.id = s.`id_cuenta_duenio` WHERE 1 ";	
						if (!empty($_GET['nro'])) {
							$sql .= " and s.nro_sitio = ".$_GET['nro'];
						}
						if (!empty($_GET['nombre'])) {
							$sql .= " and s.nombre like '%".$_GET['nombre']."%'";
						}
						if (!empty($_GET['cliente'])) {
							$sql .= " and cue.`nombre` like '%".$_GET['cliente']."%'";
						}
						
						foreach ($pdo->query($sql) as $row) {
							echo '<tr>';
								echo '<td>'. $row[0] . '</td>';
                                echo '<td>'. $row[1] . ' / '. $row[2] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
								echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
                                echo '<td>'. "'".$row[6] . '</td>';
                                echo '<td>'. "'".$row[7] . '</td>';
                                echo '<td>'. $row[8] . '</td>';
                                echo '<td>'. $row[9] . '</td>';
                                echo '<td>'. $row[10] . '</td>';
								echo '<td>'. $row[11] . '</td>';
								echo '<td>'. $row[12] . '</td>';
								echo '<td>'. $row[13] . '</td>';
								echo '<td>'. $row[14] . '</td>';
								echo '<td>'. $row[15] . '</td>';
								echo '<td>'. $row[16] . '</td>';
								echo '<td>'. $row[17] . '</td>';
								echo '<td>'. $row[18] . '</td>';
								echo '<td>'. $row[19] . '</td>';
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