<?php
session_start(); 
header('Content-Disposition: attachment; filename="ordenes_compra_clientes.xls"');
include 'database.php';?>
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
              <th>Numero</th>
              <th>Fecha emision</th>
              <th>Fecha recepcion</th>
              <th>Cliente</th>
              <th>Monto</th>
              <th>Forma de Pago</th>
              <th>Presupuesto</th>
            </tr>
          </thead>
          <tbody><?php
            $pdo = Database::connect();
            $sql = "SELECT occ.id,occ.numero,date_format(occ.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(occ.fecha_recepcion,'%d/%m/%y') AS fecha_recepcion,c.nombre AS cliente,occ.monto,m.moneda,occ.id_condicion_iva,occ.percepcion,occ.otros_importes,fp.forma_pago,CONCAT(p.nro,'/',p.nro_revision) AS presupuesto,occ.requiere_polizas,occ.abierta,date_format(occ.fecha_vencimiento,'%d/%m/%y') AS fecha_vencimiento,date_format(occ.fecha_entrega,'%d/%m/%y') AS fecha_entrega,occ.lugar_entrega,occ.observaciones,occ.monto_total_certificados,occ.monto_total_facturados, occ.activa FROM occ INNER JOIN cuentas c ON occ.id_cuenta_cliente=c.id INNER JOIN monedas m ON occ.id_moneda=m.id INNER JOIN formas_pago fp ON occ.id_forma_pago=fp.id INNER JOIN presupuestos p ON occ.id_presupuesto=p.id WHERE  1";
            //echo $sql;
            foreach ($pdo->query($sql) as $row) {
              echo '<tr>';
              echo '<td>'.$row["id"].'</td>';
              echo '<td>'.$row["numero"].'</td>';
              /*if (empty($row["subsitio"])) {
                echo '<td>'.$row["sitio"].'</td>';
                echo '<td>&nbsp;</td>';
              } else {
                echo '<td>'.$row["subsitio"].'</td>';
                echo '<td>'.$row["sitio"].'</td>';
              }*/
              echo '<td>'.$row["fecha_emision"].'</td>';
              echo '<td>'.$row["fecha_recepcion"].'</td>';
              echo '<td>'.$row["cliente"].'</td>';
              echo '<td>'.$row["moneda"].number_format($row["monto"],2).'</td>';
              echo '<td>'.$row["forma_pago"].'</td>';
              echo '<td>'. $row["presupuesto"] . '</td>';
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