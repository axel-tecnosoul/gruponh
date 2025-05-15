<?php
session_start(); 
header('Content-Disposition: attachment; filename="pedidos.xls"');
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
							  <th>Concepto</th>
							  <th>Fecha Necesidad</th>
							  <th>Cantidad Pedida</th>
							  <th>Cantidad Comprada</th>
							  <th>Unidad de Medida</th>
							  <th>Sitio</th>
							  <th>Proyecto</th>
							  <th>Tarea</th>
							  <th>Fecha</th>
							  <th>Recibe</th>
							  <th>Lugar Entrega</th>
							  <th>Aprobado</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php     
						$pdo = Database::connect();
								 
						$sql = " SELECT pe.`id`, s.nombre, p.descripcion, t.`estructura`, date_format(pe.`fecha`,'%d/%m/%y'), cu.`nombre`, pe.`lugar_entrega`, pe.`aprobado`, m.`concepto`, pd.`fecha_necesidad`, pd.`cantidad`, um.`unidad_medida`, pd.`comprado` FROM pedidos_detalle pd inner join pedidos pe on pe.id = pd.id_pedido inner join `computos` c on c.id = pe.id_computo inner join cuentas cu on cu.id = pe.id_cuenta_recibe inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio inner join materiales m on m.id = pd.id_material inner join unidades_medida um on um.id = pd.id_unidad_medida WHERE 1 ";
                        foreach ($pdo->query($sql) as $row) {
							
								$sql2 = "SELECT sum(cantidad) cant FROM `pedidos_detalle` WHERE id_pedido = ? ";
								$q2 = $pdo->prepare($sql2);
								$q2->execute([$row[0]]);
								$data2 = $q2->fetch(PDO::FETCH_ASSOC);
								$pedidosTotal = $data2['cant'];
								
								$sql2 = "SELECT sum(cd.cantidad) cant FROM `compras_detalle` cd inner join compras c on c.id = cd.id_compra WHERE c.id_pedido = ? ";
								$q2 = $pdo->prepare($sql2);
								$q2->execute([$row[0]]);
								$data2 = $q2->fetch(PDO::FETCH_ASSOC);
								$comprasTotal = $data2['cant'];
								
								if ($comprasTotal < $pedidosTotal) {
									echo '<tr>';
									echo '<td>'. $row[0] . ' </td>';
									echo '<td>'. $row[8] . '</td>';
									echo '<td>'. $row[9] . '</td>';
									echo '<td>'. $row[10] . '</td>';
									echo '<td>'. $row[12] . '</td>';
									echo '<td>'. $row[11] . '</td>';
									echo '<td>'. $row[1] . '</td>';
									echo '<td>'. $row[2] . '</td>';
									echo '<td>'. $row[3] . '</td>';
									echo '<td>'. $row[4] . '</td>';
									echo '<td>'. $row[5] . '</td>';
									echo '<td>'. $row[6] . '</td>';
									if ($row[7] == 1) {
										echo '<td>Si</td>';
									} else {
										echo '<td>No</td>';
									}
									echo '</tr>';
								}
							
								
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