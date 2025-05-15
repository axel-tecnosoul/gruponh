<?php
session_start(); 
header('Content-Disposition: attachment; filename="certificados_maestro.xls"');
$id=0;
if($_GET["id"]){
  $id=$_GET["id"];
}?>
<!doctype html>
<html lang="en">
<head>
	<?php //include('head_tables.php');?>
</head>
<body>
  <div class="row">
    <div class="table-responsive">
      <a href="#" id="aExportar" onclick="$('.example').tableExport({type:'excel',escape:'false'});"></a>
      <table border="1" class="example" name="formularios" style="visibility:hidden;">
        <thead>
          <tr>
            <th>ID</th>
            <th><?=htmlentities("N° CM")?></th>
            <th><?=htmlentities("N° OCC")?></th>
            <th>Fecha emision</th>
            <th>Fecha inicio</th>
            <th>Fecha fin</th>
            <th>Moneda</th>
            <th>Monto</th>
            <th>Cotiz. dolar</th>
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
          $sql = "SELECT cm.id,cm.numero AS numero_cm,occ.numero AS numero_occ,date_format(cm.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cm.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cm.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cm.cotizacion_dolar,cm.monto_total,cm.monto_acumulado_avances,cm.monto_acumulado_anticipos,cm.monto_acumulado_desacopios,cm.monto_acumulado_descuentos,cm.monto_acumulado_ajustes,cm.observaciones FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ=occ.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cm.id = ".$id;
          //echo $sql;
          foreach ($pdo->query($sql) as $row) {
            echo '<tr>';
            echo '<td>'.$row["id"].'</td>';
            echo '<td>'.$row["numero_cm"].'</td>';
            echo '<td>'.$row["numero_occ"].'</td>';
            echo '<td>'.$row["fecha_emision"].'</td>';
            echo '<td>'.$row["fecha_inicio"].'</td>';
            echo '<td>'.$row["fecha_fin"].'</td>';
            echo '<td>'.$row["moneda"].'</td>';
            echo '<td>'.number_format($row["monto_total"],2,",",".").'</td>';
            echo '<td>'.$row["cotizacion_dolar"].'</td>';
            echo '<td>'.number_format($row["monto_acumulado_avances"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_anticipos"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_desacopios"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_descuentos"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_ajustes"],2,",",".")."</td>";
            echo '<td>'. $row["observaciones"] . '</td>';
            echo '</tr>';
          }?>
        </tbody>
      </table>
      <table class="example" name="formularios" style="visibility:hidden;">
        <thead>
          <tr>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
          </tr>
        </thead>
      </table>
      <table border="1" class="example" name="formularios" style="visibility:hidden;">
        <thead>
          <tr>
            <th colspan="10">Detalle</th>
          </tr>
          <tr>
            <th>ID</th>
            <th>Proyecto</th>
            <th>Sitio</th>
            <th>Subsitio</th>
            <th>Tipo</th>
            <th>Descripcion</th>
            <th>Cantidad</th>
            <th>Unidad de Medida</th>
            <th>Precio Unitario</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody><?php
          $sql = "SELECT cmd.id,s.nombre AS sitio,s2.nombre AS subsitio,cmd.id_proyecto,p.nombre AS proyecto,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cmd.subtotal FROM certificados_maestros_detalles cmd INNER JOIN proyectos p ON cmd.id_proyecto=p.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id left join sitios s on s.id = p.id_sitio left join sitios s2 on s2.id = s.id_sitio_superior WHERE id_certificado_maestro = ".$id;
          //echo $sql;
          foreach ($pdo->query($sql) as $row) {
            echo '<tr>';
            echo '<td>'.$row["id"].'</td>';
            echo '<td>'.htmlentities($row["proyecto"]).'</td>';
            echo '<td>'.htmlentities($row["subsitio"]).'</td>';
            echo '<td>'.htmlentities($row["sitio"]).'</td>';
            echo '<td>'.$row["tipo"].'</td>';
            echo '<td>'.htmlentities($row["descripcion"]).'</td>';
            echo '<td>'.$row["cantidad"].'</td>';
            echo '<td>'.$row["unidad_medida"].'</td>';
            echo '<td>'.number_format($row["precio_unitario"],2,",",".")."</td>";
            echo '<td>'.number_format($row["subtotal"],2,",",".")."</td>";
            echo '</tr>';
          }
          Database::disconnect();?>
        </tbody>
      </table>
      <table class="example" name="formularios" style="visibility:hidden;">
        <thead>
          <tr>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
          </tr>
        </thead>
      </table>
      <table border="1" class="example" name="formularios" style="visibility:hidden;">
        <thead>
          <tr>
            <th colspan="20">Certificados de avance</th>
          </tr>
          <tr>
            <th>ID</th>
            <th>Fecha de emision</th>
            <th>Fecha de inicio</th>
            <th>Fecha de fin</th>
            <th>Monto total</th>
            <th>Monto acumulado avances</th>
            <th>Monto acumulado anticipos</th>
            <th>Monto acumulado desacopios</th>
            <th>Monto acumulado descuentos</th>
            <th>Monto acumulado ajustes</th>
            <th>Observaciones</th>
            <th>Aprobado cliente</th>
            <th>ID detalle</th>
            <th>Tipo</th>
            <th>Descripcion</th>
            <th>Cantidad</th>
            <th>Unidad de medida</th>
            <th>Precio Unitario</th>
            <th>Subtotal</th>
            <th>ID comprobante</th>
          </tr>
        </thead>
        <tbody><?php
          $sql = "SELECT cac.id,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,cac.monto_total,cac.monto_acumulado_avances,cac.monto_acumulado_anticipos,cac.monto_acumulado_desacopios,cac.monto_acumulado_descuentos,cac.monto_acumulado_ajustes,cac.observaciones,IF(cac.aprobado_cliente=1,'Si','No') AS aprobado_cliente,cad.id AS id_certificados_avances_detalle,tic.tipo,cmd.descripcion,cad.cantidad_actual,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cad.subtotal,cad.id_comprobante FROM certificados_avances_detalle cad INNER JOIN certificados_maestros_detalles cmd ON cad.id_certificado_maestro_detalle=cmd.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id INNER JOIN certificados_avances_cabecera cac ON cad.id_certificado_avance=cac.id WHERE cac.id_certificado_maestro = ".$id;
          //echo $sql;
          foreach ($pdo->query($sql) as $row) {
            echo '<tr>';
            echo '<td>'.$row["id"].'</td>';
            echo '<td>'.$row["fecha_emision"].'</td>';
            echo '<td>'.$row["fecha_inicio"].'</td>';
            echo '<td>'.$row["fecha_fin"].'</td>';
            echo '<td>'.number_format($row["monto_total"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_avances"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_anticipos"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_desacopios"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_descuentos"],2,",",".")."</td>";
            echo '<td>'.number_format($row["monto_acumulado_ajustes"],2,",",".")."</td>";
            echo '<td>'.htmlentities($row["observaciones"]).'</td>';
            echo '<td>'.$row["aprobado_cliente"].'</td>';
            echo '<td>'.$row["id_certificados_avances_detalle"].'</td>';
            echo '<td>'.$row["tipo"].'</td>';
            echo '<td>'.htmlentities($row["descripcion"]).'</td>';
            echo '<td>'.$row["cantidad_actual"].'</td>';
            echo '<td>'.$row["unidad_medida"].'</td>';
            echo '<td>'.number_format($row["precio_unitario"],2,",",".")."</td>";
            echo '<td>'.number_format($row["subtotal"],2,",",".")."</td>";
            echo '<td>'.$row["id_comprobante"].'</td>';
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