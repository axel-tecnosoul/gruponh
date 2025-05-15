<?php
session_start(); 
header('Content-Disposition: attachment; filename="ingresos.xls"');
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
							  <th>Fecha/Hora</th>
							  <th>Tipo</th>
							  <th>Nro.</th>
							  <th>Recibe</th>
							  <th>Lugar</th>
							  <th>Código</th>
							  <th>Concepto</th>
							  <th>Categoría</th>
							  <th>Unidad Medida</th>
							  <th>Cantidad</th>
                <th>Cantidad egresada</th>
                <th>Saldo</th>
							  <th>Observaciones</th>
							  <th>Fecha Remito</th>
							  <th>Nro Remito</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php     
						$pdo = Database::connect();
								 
						$sql = " SELECT id.id, i.fecha_hora, ti.tipo, i.nro, c.nombre, i.lugar_entrega, m.codigo, m.concepto, cat.categoria, um.unidad_medida, id.cantidad,id.cantidad_egresada,id.saldo, i.observaciones, date_format(i.`fecha_remito`,'%d/%m/%Y'), i.`nro_remito` FROM ingresos_detalle id inner join unidades_medida um on um.id = id.id_unidad_medida inner join ingresos i on i.id = id.id_ingreso inner join tipos_ingreso ti on ti.id = i.id_tipo_ingreso inner join cuentas c on c.id = i.id_cuenta_recibe inner join materiales m on m.id = id.id_material inner join categorias cat on cat.id = m.id_categoria WHERE 1 ";
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
								echo '<td>'. $row[9] . '</td>';
								echo '<td>'. $row[10] . '</td>';
								echo '<td>'. $row[11] . '</td>';
                echo '<td>'. $row[12] . '</td>';
                echo '<td>'. $row[13] . '</td>';
				echo '<td>'. $row[14] . '</td>';
				echo '<td>'. $row[15] . '</td>';
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