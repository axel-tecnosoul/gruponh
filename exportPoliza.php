<?php
session_start(); 
header('Content-Disposition: attachment; filename="polizas.xls"');?>
<!doctype html>
<html lang="en">
<head>
	<?php //include('head_tables.php');?>
</head>
<body>
  <div class="row">
    <div class="table-responsive">
      <a href="#" id="aExportar" onclick="$('#example2').tableExport({type:'excel',escape:'false'});"></a>
				<table id="example2" name="formularios" style="visibility:hidden;">
					<thead>
		        <tr>
              <th>ID</th>
              <th>OCC</th>
              <th>Empresa</th>
              <th>Fecha solicitud</th>
              <th>Fecha renovacion</th>
              <th>Nro Poliza</th>
              <th>Aseguradora</th>
              <th>Beneficiario</th>
              <th>Tipo de cobertura</th>
              <th>Vigencia desde</th>
              <th>Vigencia hasta</th>
              <th>Monto garantia</th>
              <th>Moneda</th>
              <th>Objeto del seguro</th>
              <th>Activa</th>
            </tr>
          </thead>
          <tbody><?php 
            include 'database.php';
            $pdo = Database::connect();
            $sql = "SELECT p.id AS id_poliza,occ.numero AS numero_occ, date_format(p.fecha_solicitud,'%d/%m/%y') AS fecha_solicitud,c1.nombre AS usuario_solicitante,p.numero,c2.nombre AS proveedor,c3.nombre as beneficiario,tcp.tipo,date_format(p.vigencia_desde,'%d/%m/%y') AS vigencia_desde,date_format(p.vigencia_hasta,'%d/%m/%y') AS vigencia_hasta,p.monto_garantia,m.moneda,p.descripcion_objetivo,IF(p.activa=1,'Si','No') AS activa, e.empresa, p.fecha_renovacion FROM polizas p INNER JOIN occ ON p.id_occ=occ.id left JOIN cuentas c1 ON p.id_cuenta_solicitante=c1.id INNER JOIN cuentas c2 ON p.id_cuenta_proveedor_aseguradora=c2.id INNER JOIN cuentas c3 ON p.id_cuenta_cliente_beneficiario=c3.id INNER JOIN tipos_cobertura_polizas tcp ON p.id_tipo_cobertura=tcp.id INNER JOIN monedas m ON p.id_moneda=m.id left join empresas e on e.id = p.id_empresa WHERE p.activa = 1";
            //echo $sql;
            foreach ($pdo->query($sql) as $row) {
              echo '<tr>';
              echo '<td>'.$row["id_poliza"].'</td>';
              echo '<td>'.$row["numero_occ"].'</td>';
              echo '<td>'.$row["empresa"].'</td>';
              echo '<td>'.$row["fecha_solicitud"].'</td>';
              echo '<td>'.$row["fecha_renovacion"].'</td>';
              echo '<td>'.$row["numero"].'</td>';
              echo '<td>'.$row["proveedor"].'</td>';
              echo '<td>'.$row["beneficiario"].'</td>';
              echo '<td>'.$row["tipo"].'</td>';
              echo '<td>'.$row["vigencia_desde"].'</td>';
              echo '<td>'.$row["vigencia_hasta"]."</td>";
              echo '<td>$'.number_format($row["monto_garantia"],2)."</td>";
              echo '<td>'.$row["moneda"]."</td>";
              echo '<td>'.$row["descripcion_objetivo"]."</td>";
              echo '<td>'.$row["activa"]."</td>";
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