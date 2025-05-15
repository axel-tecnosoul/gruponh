<?php
session_start(); 
header('Content-Disposition: attachment; filename="proyectos.xls"');
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
							  <th>Línea de Negocio</th>
							  <th>Sitio</th>
							  <th>Descripción</th>
							  <th>Tipo</th>
							  <th>Fecha Pedido</th>
							  <th>Fecha Entrega</th>
							  <!--<th>Facturado</th>-->
							  <th>Solicitante</th>
							  <th>Info Entrada</th>
							  <th>Gerente</th>
							  <th>Observaciones</th>
							  <th>Estado</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php     
						$pdo = Database::connect();
						$sql = " SELECT p.`id`, ln.`linea_negocio`, s.`nombre`, p.`descripcion`, tp.`tipo`, date_format(p.`fecha_pedido`,'%d/%m/%y'), date_format(p.`fecha_entrega`,'%d/%m/%y'), p.`facturado`, p.`solicitante`, p.`informacion_entrada`, p.`id_gerente`, p.`observaciones`, ep.`estado` FROM `proyectos` p inner join lineas_negocio ln on ln.id = p.`id_linea_negocio` inner join tipos_proyecto tp on tp.id = p.`id_tipo_proyecto` inner join estados_proyecto ep on ep.id = p.`id_estado_proyecto` inner join sitios s on s.id = p.id_sitio inner join cuentas c on c.id = p.id_cliente inner join cuentas c2 on c2.id = p.id_gerente WHERE p.`anulado` = 0 ";
                        if (!empty($_GET['nro'])) {
							$sql .= " and p.nro = ".$_GET['nro'];
						}
						if (!empty($_GET['nombre'])) {
							$sql .= " and p.nombre like '%".$_GET['nombre']."%'";
						}
						if (!empty($_GET['cliente'])) {
							$sql .= " and c.`nombre` like '%".$_GET['cliente']."%'";
						}
						if (!empty($_GET['linea'])) {
							$sql .= " and p.`id_linea_negocio` = ".$_GET['linea'];
						}
						if (!empty($_GET['estado'])) {
							$sql .= " and p.`id_estado_proyecto` = ".$_GET['estado'];
						}
						
						foreach ($pdo->query($sql) as $row) {
							echo '<tr>';
								echo '<td>'. $row[0] . '</td>';
                                echo '<td>'. $row[1] . '</td>';
                                echo '<td>'. $row[2] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
								echo '<td>'. $row[6] . '</td>';
								/*
								if ($row[7] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
								*/
								echo '<td>'. $row[8] . '</td>';
								echo '<td>'. $row[9] . '</td>';
								echo '<td>'. $row[10] . '</td>';
								echo '<td>'. $row[11] . '</td>';
								echo '<td>'. $row[12] . '</td>';
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