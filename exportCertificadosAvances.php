<?php
session_start(); 
header('Content-Disposition: attachment; filename="certificados_de_avance.xls"');?>
<!doctype html>
<html lang="en">
<head>
	<?php //include('head_tables.php');?>
</head>
<body>
  <div class="row">
    <div class="table-responsive">
      <a href="#" id="aExportar" onclick="$('#example2').tableExport({type:'excel',escape:'false'});"></a>
				<table border="1" id="example2" name="formularios" style="visibility:hidden;">
					<thead>
		        <tr>
              <th>ID</th>
              <th><?=htmlentities("N° CM")?></th>
              <th>Fecha emision</th>
              <th>Fecha inicio</th>
              <th>Fecha fin</th>
              <th>Moneda</th>
              <th>Monto</th>
              <th>Acumulado avances</th>
              <th>Acumulado anticipos</th>
              <th>Acumulado desacopios</th>
              <th>Acumulado descuentos</th>
              <th>Acumulado ajustes</th>
              <th>Observaciones</th>
            </tr>
          </thead>
          <tbody><?php 
            include 'database.php';
            $pdo = Database::connect();
            $sql = "SELECT cac.id,cm.numero AS numero_cm,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cac.monto_total,cac.monto_acumulado_avances,cac.monto_acumulado_anticipos,cac.monto_acumulado_desacopios,cac.monto_acumulado_descuentos,cac.monto_acumulado_ajustes,cac.observaciones FROM certificados_avances_cabecera cac INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cac.id_certificado_maestro = ".$_GET["id_certificado_maestro"];
            //echo $sql;
            foreach ($pdo->query($sql) as $row) {
              echo '<tr>';
              echo '<td>'.$row["id"].'</td>';
              echo '<td>'.$row["numero_cm"].'</td>';
              echo '<td>'.$row["fecha_emision"].'</td>';
              echo '<td>'.$row["fecha_inicio"].'</td>';
              echo '<td>'.$row["fecha_fin"].'</td>';
              echo '<td>'.$row["moneda"].'</td>';
              echo '<td>'.number_format($row["monto_total"],2,",",".").'</td>';
              echo '<td>'.number_format($row["monto_acumulado_avances"],2,",",".")."</td>";
              echo '<td>'.number_format($row["monto_acumulado_anticipos"],2,",",".")."</td>";
              echo '<td>'.number_format($row["monto_acumulado_desacopios"],2,",",".")."</td>";
              echo '<td>'.number_format($row["monto_acumulado_descuentos"],2,",",".")."</td>";
              echo '<td>'.number_format($row["monto_acumulado_ajustes"],2,",",".")."</td>";
              echo '<td>'. $row["observaciones"] . '</td>';
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