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
  header("Location: listarCertificadosMaestros.php");
}

if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  //$sql = "SELECT cac.id,cm.numero AS numero_cm,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cac.monto_total,cac.monto_acumulado_avances,cac.monto_acumulado_anticipos,cac.monto_acumulado_desacopios,cac.monto_acumulado_descuentos,cac.monto_acumulado_ajustes,cac.observaciones FROM certificados_avances_cabecera cac INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cac.id_certificado_maestro = ? ";
  $sql = "SELECT cac.id,cm.numero AS numero_cm,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cac.monto_total,cac.monto_acumulado_avances,cac.monto_acumulado_anticipos,cac.monto_acumulado_desacopios,cac.monto_acumulado_descuentos,cac.monto_acumulado_ajustes,cac.observaciones FROM certificados_avances_cabecera cac INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cac.id = ? ";
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
    <?php include('header.php');?>
    
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Ver Certificado de Avance";
          include_once("head_page.php")?>
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
                          <div class="form-group row my-4">
                            <div class="col-sm-12">
                              <h5 class="font-weight-bold">Detalle del Certificado de Avance</h5>
                            </div>
                          </div>
                          <div class="row">
                            <!-- Zero Configuration  Starts-->
                            <div class="col-sm-12">
                              <div class="dt-ext table-responsive">
                                <table class="display" id="dataTables-example667">
                                  <thead>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Tipo</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
                                      <th>Unidad</th>
                                      <th>Avance Actual</th>
                                      <!-- <th>Monto Avance</th> -->
                                      <th>Precio U.</th>
                                      <th>Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  
                                  $sql = " SELECT cad.id AS id_certificado_avance_detalle, cmd.id AS id_certificado_maestro_detalle,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario AS precio_unitario_cm,cmd.subtotal AS subtotal_cm,m.moneda,cad.cantidad_anterior,cad.cantidad_actual,cad.cantidad_acumulado,cad.precio_unitario AS precio_unitario_ca,cad.subtotal AS subtotal_ca,cad.id_comprobante FROM certificados_maestros_detalles cmd INNER JOIN certificados_maestros cm ON cmd.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id LEFT JOIN certificados_avances_detalle cad ON cad.id_certificado_maestro_detalle=cmd.id WHERE cad.id_certificado_avance=$id";// OR cad.id_certificado_avance IS NULL
                                  //$sql = "SELECT cad.id AS id_certificados_avances_detalle,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cmd.subtotal,cad.id_comprobante FROM certificados_avances_detalle cad INNER JOIN certificados_maestros_detalles cmd ON cad.id_certificado_maestro_detalle=cmd.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id WHERE id_certificado_avance = ";
                                  //echo $sql;
                                  $aIdComprobantes=[];
                                  foreach ($pdo->query($sql) as $row) {
                                    $aIdComprobantes[]=$row["id_comprobante"];
                                    echo '<tr>';
                                    echo '<td class="d-none">'.$row["id_certificado_maestro_detalle"].'</td>';
                                    echo '<td data-id="'.$row["id_tipo_item_certificado"].'">'.$row["tipo"].'</td>';
                                    echo '<td>'.$row["descripcion"].'</td>';
                                    echo '<td style="text-align:right">'.$row["cantidad"].'</td>';
                                    echo '<td data-id="'.$row["id_unidad_medida"].'">'.$row["unidad_medida"].'</td>';
                                    echo '<td>'.$row["cantidad_actual"].'</td>';
                                    //echo '<td>'.$row["precio_unitario_ca"].'</td>';
                                    echo '<td style="text-align:right">'.$row["moneda"]." ".number_format($row["precio_unitario_cm"],2).'</td>';
                                    echo '<td style="text-align:right">'.$row["moneda"]." ".number_format($row["subtotal_ca"],2).'</td>';
                                    //echo '<td>'.$row["observaciones"].'</td>';
                                    echo '</tr>';
                                  }?></tbody>
                                  <tfoot>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Tipo</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
                                      <th>Unidad</th>
                                      <th>Avance Actual</th>
                                      <!-- <th>Monto Avance</th> -->
                                      <th>Precio U.</th>
                                      <th>Subtotal</th>
                                    </tr>
                                  </tfoot>
                                </table>
                              </div>
                            </div>
                            <!-- Zero Configuration  Ends-->
                            <!-- Feature Unable /Disable Order Starts-->
                          </div>
                          <div class="row my-4">
                            <!-- Zero Configuration  Starts-->
                            <div class="col-sm-12">
                              <h5 class="font-weight-bold">Facturas</h5>
                            </div>
                          </div>
                          <div class="row">
                            <!-- Zero Configuration  Starts-->
                            <div class="col-sm-12">
                              <div class="dt-ext table-responsive">
                                <table class="display" id="dataTables-example668">
                                  <thead>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Descripción</th>
                                      <th>Tipo</th>
                                      <th>Letra</th>
                                      <th>Número</th>
                                      <th>Proveedor</th>
                                      <th>Fecha</th>
                                      <th>Condición</th>
                                      <th>Total</th>
                                      <th>Moneda</th>
                                      <th>Estado</th>
                                    </tr>
                                  </thead>
                                  <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                  if(count($aIdComprobantes)>0){
                                    $sql = " SELECT distinct fc.id, fc.descripcion, tc.tipo, lc.letra, fc.numero, c.razon_social, date_format(fc.fecha_emitida,'%d/%m/%y'), fp.forma_pago, fc.total, m.moneda, ef.estado  FROM facturas_venta_detalle_x_certificados_avance fxc inner join facturas_venta_detalle fvd on fvd.id = fxc.id_factura_venta_detalle inner join facturas_venta fc on fc.id = fvd.id_factura_venta inner join tipos_comprobante tc on tc.id = fc.id_tipo_comprobante inner join letras_comprobante lc on lc.id = fc.id_letra_comprobante inner join cuentas c on c.id = fc.id_cuenta_destino inner join formas_pago fp on fp.id = fc.id_condicion_pago inner join monedas m on m.id = fc.id_moneda inner join estados_factura ef on ef.id = fc.id_estado WHERE fxc.id_certificado_avance = ".$_GET['id'];
                                    foreach ($pdo->query($sql) as $row) {
                                      echo '<tr>';
                                      echo '<td class="d-none">'. $row[0] . '</td>';
                                      echo '<td>'. $row[1] . '</td>';
                                      echo '<td>'. $row[2] . '</td>';
                                      echo '<td>'. $row[3] . '</td>';
                                      echo '<td>'. $row[4] . '</td>';
                                      echo '<td>'. $row[5] . '</td>';
                                      echo '<td>'. $row[6] . '</td>';
                                      echo '<td>'. $row[7] . '</td>';
                                      echo '<td>'. number_format($row[8],2) . '</td>';
                                      echo '<td>'. $row[9] . '</td>';
                                      echo '<td>'. $row[10] . '</td>';
                                      echo '</tr>';
                                    }
                                  }?></tbody>
                                  <tfoot>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Descripción</th>
                                      <th>Tipo</th>
                                      <th>Letra</th>
                                      <th>Número</th>
                                      <th>Proveedor</th>
                                      <th>Fecha</th>
                                      <th>Condición</th>
                                      <th>Total</th>
                                      <th>Moneda</th>
                                      <th>Estado</th>
                                    </tr>
                                  </tfoot>
                                </table>
                              </div>
                            </div>
                            <!-- Zero Configuration  Ends-->
                            <!-- Feature Unable /Disable Order Starts-->
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						            <a class="btn btn-primary" target="_blank" href="imprimirCertificadoAvance.php?id=<?=$id?>">Imprimir</a>
                        <a href="listarCertificadosAvances.php?id_certificado_maestro=<?=$id?>" class="btn btn-light">Volver</a>
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
        <?php include("footer.php"); ?>
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
      $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      } );
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
  
      // Apply the search
      table.columns().every( function () {
          var that = this;
  
          $( 'input', this.footer() ).on( 'keyup change', function () {
              if ( that.search() !== this.value ) {
                  that
                      .search( this.value )
                      .draw();
              }
          } );
      } );

      $('#dataTables-example668 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      } );
      $('#dataTables-example668').DataTable({
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
      var table = $('#dataTables-example668').DataTable();
  
      // Apply the search
      table.columns().every( function () {
          var that = this;
  
          $( 'input', this.footer() ).on( 'keyup change', function () {
              if ( that.search() !== this.value ) {
                  that
                      .search( this.value )
                      .draw();
              }
          } );
      } );
    } );
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>