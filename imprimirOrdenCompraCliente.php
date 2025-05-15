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
  $sql = "SELECT occ.id,occ.numero,date_format(occ.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(occ.fecha_recepcion,'%d/%m/%y') AS fecha_recepcion,c.nombre AS cliente,occ.monto,m.moneda,occ.iva,occ.percepcion,occ.otros_importes,fp.forma_pago,CONCAT(p.id,'/',p.nro_revision) AS presupuesto,IF(occ.requiere_polizas=1,'Si','No') AS requiere_polizas,occ.abierta,date_format(occ.fecha_vencimiento,'%d/%m/%y') AS fecha_vencimiento,date_format(occ.fecha_entrega,'%d/%m/%y') AS fecha_entrega,occ.lugar_entrega,occ.observaciones,occ.monto_total_certificados,occ.monto_total_facturados,IF(occ.abierta=1,'Abierta','Cerrada') AS tipo_oc,IF(occ.activa=1,'Si','No') AS activa, occ.id_moneda FROM occ INNER JOIN cuentas c ON occ.id_cuenta_cliente=c.id INNER JOIN monedas m ON occ.id_moneda=m.id INNER JOIN formas_pago fp ON occ.id_forma_pago=fp.id INNER JOIN presupuestos p ON occ.id_presupuesto=p.id WHERE occ.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  $signo = '$';
  if ($data["id_moneda"] == 1) {
	$signo = 'u$s';  
  }
  
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
          $ubicacion="Orden de Compra Cliente";
          ?>
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
                            <div class="col-sm-9"><?=$data['fecha_emision']; ?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Recepción(*)</label>
                            <div class="col-sm-9"><?=$data['fecha_recepcion'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Número(*)</label>
                            <div class="col-sm-9"><?=$data['numero']; ?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cliente(*)</label>
                            <div class="col-sm-9"><?=$data['cliente']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Moneda(*)</label>
                            <div class="col-sm-9"><?=$data['moneda']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">IVA(*)</label>
                            <div class="col-sm-9"><?=$data['iva'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Percepcion(*)</label>
                            <div class="col-sm-9"><?=$data['percepcion']; ?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Otros importes</label>
                            <div class="col-sm-9"><?=$data['otros_importes'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Forma de pago(*)</label>
                            <div class="col-sm-9"><?=$data['forma_pago']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Presupuesto(*)</label>
                            <div class="col-sm-9"><?=$data['presupuesto']?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Requiere Poliza(*)</label>
                            <div class="col-sm-9"><?=$data["requiere_polizas"]?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipo OC(*)</label>
                            <div class="col-sm-9"><?=$data["tipo_oc"]?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Vencimiento</label>
                            <div class="col-sm-9"><?=$data['fecha_vencimiento'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Entrega(*)</label>
                            <div class="col-sm-9"><?=$data['fecha_entrega'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Lugar de Entrega(*)</label>
                            <div class="col-sm-9"><?=$data['lugar_entrega'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                            <div class="col-sm-9"><?=$data['observaciones'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Activo(*)</label>
                            <div class="col-sm-9"><?=$data["activa"]?></div>
                          </div>
                          <div class="form-group row">
                            <div class="col-sm-12">
                              <table class="display" id="dataTables-example667">
                                <thead>
                                  <tr>
                                    <th>ID</th>
                                    <th>Descripcion</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unitario</th>
                                    <th>Descuento</th>
                                    <th>Subtotal</th>
                                  </tr>
                                </thead>
                                <tfoot>
                                  <tr>
                                    <th>ID</th>
                                    <th>Descripcion</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unitario</th>
                                    <th>Descuento</th>
                                    <th>Subtotal</th>
                                  </tr>
                                </tfoot>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  
                                  $sql = " SELECT id, descripcion, cantidad, precio_unitario, descuento, subtotal FROM occ_detalles WHERE id_occ = ".$id;
                                  foreach ($pdo->query($sql) as $row) {
                                    echo '<tr>';
                                    echo '<td>'. $row["id"] . '</td>';
                                    echo '<td>'. $row["descripcion"] . '</td>';
                                    echo '<td>'. $row["cantidad"] . '</td>';
                                    echo '<td>'.$signo. number_format($row["precio_unitario"],2) . '</td>';
                                    echo '<td>'.$signo. number_format($row["descuento"],2) . '</td>';
                                    echo '<td>'.$signo. number_format($row["subtotal"],2) . '</td>';
                                    echo '</tr>';
                                  }
                                  Database::disconnect();?>
                                </tbody>
                              </table>
                            </div>
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