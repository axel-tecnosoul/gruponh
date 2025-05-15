<?php
session_start(); 
header('Content-Disposition: attachment; filename="estados-tareas.xls"');
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
							<th>Sitio</th>
							<th>Proyecto</th>
							<th>Coordinador</th>
							<th>Estado Proyecto</th>
							<th>Tarea</th>
							<th>Observaciones</th>
							<th>Recurso</th>
							<th>F.I.P</th>		
							<th>F.F.P</th>		
							<th>F.I.R</th>
							<th>F.F.R</th>
							<th>Comenzó</th>
							<th>Terminó</th>
							<th>Presentado</th>
							<th>Verificado</th>
							<th>Aprobado</th>
                          </tr>
                        </thead>
		             <tbody>
                          <?php
                            $pdo = Database::connect();
                            $sql = " SELECT e.`id`, s.`nombre`, s.nro_sitio, s.nro_subsitio, p.`nombre`, c1.nombre, ep.`estado`, t.estructura, e.`observaciones`, c2.nombre, date_format(e.`fecha_inicio_prevista`,'%d/%m/%y'), date_format(e.`fecha_fin_prevista`,'%d/%m/%y'), date_format(e.`fecha_inicio_real`,'%d/%m/%y'), date_format(e.`fecha_fin_real`,'%d/%m/%y'), e.`comentarios_inicio`, e.`comentarios_fin`, e.`presentado`, e.`verificado`, e.`aprobado_cliente` FROM `estados_tareas` e inner join `proyectos` p on p.id = e.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join estados_proyecto ep on ep.id = e.`id_estado_proyecto` inner join cuentas c1 on c1.id = e.`id_cuenta_coordinador` inner join cuentas c2 on c2.id = e.`id_cuenta_recurso` inner join tareas t on t.id = e.`id_tarea` WHERE 1 ";
                            
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
								echo '<td>'. $row[0] . '</td>';
								echo '<td>'. $row[1] .' ('.$row[2] .' / '.$row[3] . ')</td>';
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
								if ($row[16] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
                                if ($row[17] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
                                if ($row[18] == 1) {
                                    echo '<td>Si</td>';
                                } else {
                                    echo '<td>No</td>';
                                }
                                
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