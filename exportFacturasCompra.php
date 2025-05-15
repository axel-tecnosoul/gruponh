<?php
session_start(); 
header('Content-Disposition: attachment; filename="facturas_compra.xls"');
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
							  <th>Descripción</th>
							  <th>Tipo Comprobante</th>
							  <th>Letra</th>
							  <th>Orden de Compra</th>
							  <th>Número Factura</th>
							  <th>Razon Social</th>
							  <th>Empresa</th>
							  <th>Fecha Emitida</th>
							  <th>Fecha Recibida</th>
							  <th>Condición Pago</th>
							  <th>Subtotal Gravado</th>
							  <th>Subtotal No Gravado</th>
							  <th>Otros</th>
							  <th>Iva</th>
							  <th>Total</th>
							  <th>Moneda</th>
							  <th>Cotización</th>
							  <th>Observaciones</th>
							  <th>Usuario</th>
							  <th>Estado</th>
		                </tr>
		              </thead>
		             <tbody>
		              <?php     
						$pdo = Database::connect();
								 
						$sql = " SELECT fc.`id`, fc.`descripcion`, tp.tipo , lc.`letra`, c.nro_oc, fc.`numero`, cu.razon_social, e.empresa, fc.`fecha_emitida`, fc.`fecha_recibida`, fp.forma_pago, fc.`subtotal_gravado`, fc.`subtotal_no_gravado`, fc.`otros`, fc.`iva`, fc.`total`, m.moneda, fc.`cotizacion`, fc.`observaciones`, u.usuario, ef.estado FROM `facturas_compra` fc inner join tipos_comprobante tp on tp.id = fc.`id_tipo_comprobante` inner join letras_comprobante lc on lc.id = fc.`id_letra_comprobante` inner join compras c on c.id = fc.`id_orden_compra` inner join cuentas cu on cu.id = fc.`id_cuenta_origen` inner join empresas e on e.id = fc.`id_empresa` inner join formas_pago fp on fp.id = fc.`id_condicion_pago` inner join monedas m on m.id = fc.`id_moneda` inner join usuarios u on u.id = fc.`id_usuario` inner join estados_factura ef on ef.id = fc.`id_estado` WHERE 1 ";
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
								echo '<td>'. $row[16] . '</td>';
								echo '<td>'. $row[17] . '</td>';
								echo '<td>'. $row[18] . '</td>';
								echo '<td>'. $row[19] . '</td>';
								echo '<td>'. $row[20] . '</td>';
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