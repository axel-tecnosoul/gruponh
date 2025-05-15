<?php
session_start(); 
header('Content-Disposition: attachment; filename="cuentas.xls"');
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
							  <th>Tipo</th>
							  <th>Nombre Corto</th>
							  <th>Razón Social</th>
							  <th>CUIT</th>
							  <th>Contacto</th>
							  <th>E-Mail</th>
							  <th>Teléfono</th>
							  <th>Puesto</th>
							  <th>Dirección</th>
							  <th>Código Postal</th>
							  <th>País</th>
							  <th>Provincia</th>
							  <th>Localidad</th>
							  <th>Observaciones</th>
							  <th>Activa</th>
							  <th>Es Recurso?</th>
							  <th>Cuenta Gestión</th>
							  <th>Código externo</th>
							  <th>Condición Ante Iva</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php     
						$pdo = Database::connect();
						$sql = " SELECT c.`id`, tc.`tipo_cuenta`, c.`nombre`, c.`razon_social`, c.`cuit`, c.`contacto`, c.`email`, c.`telefono`, p.`puesto`, c.`codigo_postal`, pa.`nombre`, prov.`provincia`, loc.`localidad`, c.`observaciones`, c.`activo`, c.`es_recurso`, c.`cuenta_gestion`, c.`codigo_externo`, ci.`condicion_iva`, c.`direccion` FROM `cuentas` c left join tipos_cuenta tc on tc.id = c.`id_tipo_cuenta` left join puestos p on p.id = c.`id_puesto` left join paises pa on pa.id = c.id_pais left join provincias prov on prov.id = c.id_provincia left join localidades loc on loc.id = c.id_localidad left join condiciones_iva ci on ci.id = c.`id_condicion_iva` WHERE c.`anulado` = 0 ";
						foreach ($pdo->query($sql) as $row) {
							echo '<tr>';
								echo '<td>'. $row[0] . '</td>';
                                echo '<td>'. $row[1] . '</td>';
                                echo '<td>'. $row[2] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
								echo '<td>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
                                echo '<td>'. $row[8] . '</td>';
                                echo '<td>'. $row[19] . '</td>';
								echo '<td>'. $row[9] . '</td>';
                                echo '<td>'. $row[10] . '</td>';
								echo '<td>'. $row[11] . '</td>';
								echo '<td>'. $row[12] . '</td>';
								echo '<td>'. $row[13] . '</td>';
								if ($row[14] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
								if ($row[15] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
								echo '<td>'. $row[16] . '</td>';
								echo '<td>'. $row[17] . '</td>';
								echo '<td>'. $row[18] . '</td>';
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