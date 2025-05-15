<?php
session_start(); 
header('Content-Disposition: attachment; filename="listas_corte.xls"');
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
		    <th>Sitio</th>
			<th>Subsitio</th>
			<th>Nro Proy</th>
			<th>Proyecto</th>
			<th>LC Nro.</th>
            <th>Revisión</th>
            <th>LC Nombre</th>
            <th>Fecha</th>
			<th>Plano</th>
            <th>Estado</th>
            <th>Descripción LC</th>
            <th>Descripción Proyecto</th>
            <th>Conjunto</th>
            <th>Cantidad</th>
            <th>Peso Kg</th>
			<th>Estado Conjunto</th>
			<th>Codigo</th>
			<th>Concepto</th>
			<th>Posición</th>
			<th>Cantidad</th>
			<th>Largo</th>
			<th>Ancho</th>
			<th>Marca</th>
			<th>Peso Kg</th>
			<th>Finalizado</th>
			<th>Diametro</th>
			<th>Calidad</th>
			<th>Proceso</th>
			<th>Observaciones</th>
			<th>Estado</th>
         </tr>
        </thead>
        <tbody><?php
			$pdo = Database::connect();
			$sql = "SELECT lcr.`numero`, lcr.`nro_revision`, lcr.`nombre`, lcr.`fecha`, elc.`estado`, lcr.`descripcion`, p.descripcion, lcc.`nombre`, lcc.`cantidad`, lcc.`peso`, elcc.`estado`, m.`codigo`, m.`concepto`, lcpo.`posicion`, lcpo.`cantidad`, lcpo.`largo`, lcpo.`ancho`, lcpo.`marca`, lcpo.`peso`, lcpo.`finalizado`, lcpo.`diametro`, lcpo.`calidad`, tp.`tipo`, lcp.`observaciones`, elcp.`estado`,p.nro,p.nombre,s.nro_sitio,s.nro_subsitio,lcr.adjunto FROM `lista_corte_procesos` lcp inner join estados_lista_corte_procesos elcp on elcp.id = lcp.`id_estado_lista_corte_proceso` inner join tipos_procesos tp on tp.id = lcp.`id_tipo_proceso` inner join lista_corte_posiciones lcpo on lcpo.id = lcp.`id_lista_corte_posicion` inner join materiales m on m.id = lcpo.`id_material` inner join listas_corte_conjuntos lcc on lcc.id = lcpo.`id_lista_corte_conjunto` inner join estados_lista_corte_conjuntos elcc on elcc.id = lcc.`id_estado_lista_corte_conjuntos` inner join listas_corte_revisiones lcr on lcr.id = lcc.`id_lista_corte` inner join estados_lista_corte elc on elc.id = lcr.`id_estado_lista_corte` inner join proyectos p on p.id = lcr.`id_proyecto` inner join sitios s on s.id = p.id_sitio WHERE lcr.`anulado` = 0 ";
			if (!empty($_GET['nro'])) {
				$sql .= " and (p.nro = ".$_GET['nro']." or s.nro_sitio = ".$_GET['nro'].") ";
			}
			if (!empty($_GET['fecha'])) {
				$sql .= " AND lcr.fecha >= '".$_GET['fecha']."' ";
			}
			if (!empty($_GET['fechah'])) {
				$sql .= " AND lcr.fecha <= '".$_GET['fechah']."' ";
			}
			
			if (!empty($_GET['estado'])) {
				$sql .= " and e.id = ".$_GET['estado'];
			}
			foreach ($pdo->query($sql) as $row) {
            echo '<tr>';
			if (empty($row[28])) {
			  echo '<td>'.$row[27].'</td>';
			  echo '<td>0</td>';
			} else {
			  echo '<td>'.$row[28].'</td>';
			  echo '<td>'.$row[27].'</td>';
			}
			echo '<td>'.$row[25].'</td>';
			echo '<td>'.$row[26].'</td>';
            echo '<td>'. $row[0] . '</td>';
            echo '<td>'. $row[1] . '</td>';
            echo '<td>'. $row[2] . '</td>';
            echo '<td>'. $row[3] . '</td>';
			echo '<td>'.$row[29].'</td>';
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
            echo '<td>'. $row[21] . '</td>';
            echo '<td>'. $row[22] . '</td>';
            echo '<td>'. $row[23] . '</td>';
            echo '<td>'. $row[24] . '</td>';
            echo '</tr>';
          }
          Database::disconnect();?>
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