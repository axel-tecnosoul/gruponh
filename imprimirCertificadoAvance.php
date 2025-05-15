<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarOrdenesCompraClientes.php");
}

if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT cac.id,cm.numero AS numero_cm,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cac.monto_total,cac.monto_acumulado_avances,cac.monto_acumulado_anticipos,cac.monto_acumulado_desacopios,cac.monto_acumulado_descuentos,cac.monto_acumulado_ajustes,cac.observaciones FROM certificados_avances_cabecera cac INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cac.id_certificado_maestro = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
    
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Certificado de Avance";?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Emisión(*)</label>
                            <div class="col-sm-9"><?=$data['fecha_emision'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Inicio(*)</label>
                            <div class="col-sm-9"><?=$data['fecha_inicio'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Fin(*)</label>
                            <div class="col-sm-9"><?=$data['fecha_fin'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Monto total(*)</label>
                            <div class="col-sm-9">$<?=number_format($data['monto_total'],2);?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                            <div class="col-sm-9"><?=$data['observaciones'];?></div>
                          </div>
                          <div class="row">
                            <div class="col-sm-12">
                              <h5>Detalle del Certificado de Avance</h5>
                            </div>
                          </div>
                          <div class="row">
                            <!-- Zero Configuration  Starts-->
                            <div class="col-sm-12">
                              <table class="display" id="dataTables-example667">
                                <thead>
                                  <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Descripcion</th>
                                    <th>Cantidad</th>
                                    <th>Unidad</th>
                                    <th>Avance Actual</th>
                                    <th>Monto Avance</th>
                                    <th>Precio U.</th>
                                    <th>Subtotal</th>
                                  </tr>
                                </thead>
                                <tbody><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                $sql = " SELECT cad.id AS id_certificado_avance_detalle, cmd.id AS id_certificado_maestro_detalle,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario AS precio_unitario_cm,cmd.subtotal AS subtotal_cm,m.moneda,cad.cantidad_anterior,cad.cantidad_actual,cad.cantidad_acumulado,cad.precio_unitario AS precio_unitario_ca,cad.subtotal AS subtotal_ca FROM certificados_maestros_detalles cmd INNER JOIN certificados_maestros cm ON cmd.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id LEFT JOIN certificados_avances_detalle cad ON cad.id_certificado_maestro_detalle=cmd.id WHERE cad.id_certificado_avance=$id OR cad.id_certificado_avance IS NULL";

                                foreach ($pdo->query($sql) as $row) {
                                  echo '<tr>';
                                  echo '<td>'.$row["id_certificado_maestro_detalle"].'</td>';
                                  echo '<td data-id="'.$row["id_tipo_item_certificado"].'">'.$row["tipo"].'</td>';
                                  echo '<td>'.$row["descripcion"].'</td>';
                                  echo '<td style="text-align:right">'.$row["cantidad"].'</td>';
                                  echo '<td data-id="'.$row["id_unidad_medida"].'">'.$row["unidad_medida"].'</td>';
                                  echo '<td>'.$row["cantidad_actual"].'</td>';
                                  echo '<td>'.$row["precio_unitario_ca"].'</td>';
                                  echo '<td style="text-align:right">'.$row["moneda"]." ".number_format($row["precio_unitario_cm"],2).'</td>';
                                  echo '<td style="text-align:right">'.$row["moneda"]." ".number_format($row["subtotal_cm"],2).'</td>';
                                  //echo '<td>'.$row["observaciones"].'</td>';
                                  echo '</tr>';
                                }?></tbody>
                                <tfoot>
                                  <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Descripcion</th>
                                    <th>Cantidad</th>
                                    <th>Unidad</th>
                                    <th>Avance Actual</th>
                                    <th>Monto Avance</th>
                                    <th>Precio U.</th>
                                    <th>Subtotal</th>
                                  </tr>
                                </tfoot>
                              </table>
                            </div>
                            <!-- Zero Configuration  Ends-->
                            <!-- Feature Unable /Disable Order Starts-->
                          </div>
                        </div>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
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
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	  <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
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
    <!-- Plugins JS Ends-->
	<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
	  $('#dataTables-example667').DataTable({
        stateSave: false,
        responsive: false,
        language: {
         "decimal": "",
        "emptyTable": "No hay información",
        "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
        "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
        "infoFiltered": "(Filtrado de _MAX_ total registros)",
        "infoPostFix": "",
        "thousands": ",",
        "lengthMenu": "Mostrar _MENU_ Registros",
        "loadingRecords": "Cargando...",
        "processing": "Procesando...",
        "search": "Buscar:",
        "zeroRecords": "No hay resultados",
        "paginate": {
            "first": "Primero",
            "last": "Ultimo",
            "next": "Siguiente",
            "previous": "Anterior"
        }}
      });
 
    // DataTable
    var table = $('#dataTables-example667').DataTable();
 
	} );
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>
<script>window.print();</script>