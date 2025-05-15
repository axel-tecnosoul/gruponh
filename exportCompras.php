<?php
session_start(); 
header('Content-Disposition: attachment; filename="compras.xls"');
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
							<th>Nro. Proy</th>
							<th>Nro. OC</th>
							<th>Proveedor</th>
							<th>Cantidad Pedida</th>
							<th>Concepto</th>
							<th>Cantidad Entregada</th>
							<th>Unidad de Medida</th>
							<th>Precio</th>
							<th>Moneda</th>
							<th>Subtotal</th>
							<th>IVA</th>
							<th>Descuento</th>
							<th>Total</th>
							<th>Fecha Emisión</th>
							<th>Fecha Pactada</th>
							<th>Estado</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php     
						$pdo = Database::connect();
								 
						$sql = " SELECT s.nro_sitio, s.nro_subsitio, p.nro, c.`nro_oc`, cu.`nombre`, cd.`cantidad`, m.`concepto`, cd.`entregado`, um.`unidad_medida`, cd.`precio`, mo.`moneda`, c.`total`, c.`fecha_emision`, e.`estado`, c.`fecha_entrega`, c.`iva`, c.`descuento` FROM compras_detalle cd inner join `compras` c on c.id = cd.id_compra inner join cuentas cu on cu.id = c.`id_cuenta_proveedor` inner join estados_compra e on e.id = c.id_estado_compra inner join pedidos pe on pe.id = c.id_pedido inner join `computos` co on co.id = pe.id_computo inner join tareas t on t.id = co.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio inner join materiales m on m.id = cd.id_material inner join unidades_medida um on um.id = cd.id_unidad_medida inner join monedas mo on mo.id = c.id_moneda WHERE e.id in (1,2) ";
                        foreach ($pdo->query($sql) as $row) {
								echo '<tr>';
								echo '<td>'.$row[0].'-'.$row[1].'-'.$row[2].'</td>';
								echo '<td>'.$row[3].'</td>';
								echo '<td>'.$row[4].'</td>';
								echo '<td>'.$row[5].'</td>';
								echo '<td>'.$row[6].'</td>';
								echo '<td>'.$row[7].'</td>';
								echo '<td>'.$row[8].'</td>';
								echo '<td>'.$row[9].'</td>';
								echo '<td>'.$row[10].'</td>';
								echo '<td>'.$row[11].'</td>';
								echo '<td>'.$row[15].'</td>';
								echo '<td>'.$row[16].'</td>';
								echo '<td>'.$row[11]+$row[15]-$row[16].'</td>';
								echo '<td>'.$row[12].'</td>';
								echo '<td>'.$row[14].'</td>';
								echo '<td>'.$row[13].'</td>';
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