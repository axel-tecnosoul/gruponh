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
  $sql = "SELECT cac.id,cac.id_certificado_maestro,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cac.monto_total,cac.monto_acumulado_avances,cac.monto_acumulado_anticipos,cac.monto_acumulado_desacopios,cac.monto_acumulado_descuentos,cac.monto_acumulado_ajustes,cac.observaciones FROM certificados_avances_cabecera cac INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cac.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $esOpVer = function_exists('esOperacionesSinEconomico') ? esOperacionesSinEconomico() : false;
  
  Database::disconnect();
  
  if (empty($data)) {
    header("Location: listarCertificadosMaestros.php");
    die("Redirecting to listarCertificadosMaestros.php");
  }
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      #detalle_ca_ver .occ-breakdown-table { width: max-content; min-width: 100%; table-layout: auto; }
      #detalle_ca_ver .occ-breakdown-table th,
      #detalle_ca_ver .occ-breakdown-table td { padding: .3rem .5rem; vertical-align: middle; white-space: nowrap; }
      #detalle_ca_ver .occ-breakdown-table th:first-child,
      #detalle_ca_ver .occ-breakdown-table td:first-child { min-width: 52px; white-space: normal; }
      #detalle_ca_ver .occ-breakdown-table th:nth-child(2),
      #detalle_ca_ver .occ-breakdown-table td:nth-child(2) { min-width: 220px; max-width: 420px; white-space: normal; }
      #detalle_ca_ver .avance-cantidad-col { min-width: 82px; }
      #detalle_ca_ver .avance-porcentaje-col { min-width: 62px; text-align: center; }
      #detalle_ca_ver .avance-periodo { border-left: 2px solid #2b8dbf; }
      #detalle_ca_ver .avance-periodo-anterior { background-color: #f1f3f5; }
      #detalle_ca_ver .avance-periodo-actual { background-color: #e9f6fd; }
      #detalle_ca_ver .avance-periodo-acumulado { background-color: #eef7ee; }
      #detalle_ca_ver .avance-periodo-saldo { background-color: #fdf3f3; }
    </style>
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
                    <h5><?=$ubicacion?> #<?=$data['id']?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">

                         <div class="row">
                            <div class="col-md-4 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Fecha Emisión:</label>
                              <span><?=$data['fecha_emision'];?></span>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Fecha Inicio:</label>
                              <span><?=$data['fecha_inicio'];?></span>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Fecha Fin:</label>
                              <span><?=$data['fecha_fin'];?></span>
                            </div>
                          </div>
                          <div class="row mt-3">
                            <?php if (!$esOpVer) { ?><div class="col-md-4 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Monto total:</label>
                              <span><?=$data['moneda']." ".number_format($data['monto_total'],2);?></span>
                            </div><?php } ?>
                            <div class="<?php echo $esOpVer ? 'col-md-12' : 'col-md-8'; ?> d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Observaciones:</label>
                              <span><?=$data['observaciones'];?></span>
                            </div>
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
                                  <div id="detalle_ca_ver">
                                  <table class="table table-sm table-bordered display" id="dataTables-example667">
                                  <thead>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Posición</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
                                      <th>Unidad</th>
                                      <th>Avance Actual</th>
                                      <?php if (!$esOpVer) { ?><th>Precio U.</th>
                                      <th>Subtotal</th><?php } ?>
                                    </tr>
                                  </thead>
                                  <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  
                                  $sql = " SELECT cad.id AS id_certificado_avance_detalle, cmd.id AS id_certificado_maestro_detalle, cmd.posicion_aperturado, cmd.id_tipo_item_certificado, tic.tipo, cmd.descripcion, cmd.cantidad, cmd.id_unidad_medida, um.unidad_medida, cmd.precio_unitario AS precio_unitario_cm, cmd.subtotal AS subtotal_cm, m.moneda, COALESCE(cad.cantidad_actual, 0) AS cantidad_actual, COALESCE(cad.subtotal, 0) AS subtotal_ca FROM certificados_maestros_detalles cmd INNER JOIN certificados_maestros cm ON cmd.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id LEFT JOIN certificados_avances_detalle cad ON cad.id_certificado_maestro_detalle=cmd.id AND cad.id_certificado_avance=$id WHERE cmd.id_certificado_maestro=".(int) $data['id_certificado_maestro']." ORDER BY cmd.lote, cmd.aperturado, cmd.id";
                                  //$sql = "SELECT cad.id AS id_certificados_avances_detalle,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cmd.subtotal,cad.id_comprobante FROM certificados_avances_detalle cad INNER JOIN certificados_maestros_detalles cmd ON cad.id_certificado_maestro_detalle=cmd.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id WHERE id_certificado_avance = ";
                                  //echo $sql;
                                  foreach ($pdo->query($sql) as $row) {
                                    echo '<tr>';
                                    echo '<td class="d-none">'.$row["id_certificado_maestro_detalle"].'</td>';
                                    echo '<td>'.$row["posicion_aperturado"].'</td>';
                                    echo '<td>'.$row["descripcion"].'</td>';
                                    echo '<td style="text-align:right">'.$row["cantidad"].'</td>';
                                    echo '<td data-id="'.$row["id_unidad_medida"].'">'.$row["unidad_medida"].'</td>';
                                    echo '<td>'.$row["cantidad_actual"].'</td>';
                                    if (!$esOpVer) { echo '<td style="text-align:right">'.$row["moneda"]." ".number_format($row["precio_unitario_cm"],2).'</td>'; echo '<td style="text-align:right">'.$row["moneda"]." ".number_format($row["subtotal_ca"],2).'</td>'; }
                                    //echo '<td>'.$row["observaciones"].'</td>';
                                    echo '</tr>';
                                  }?></tbody>
                                  <tfoot>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Posición</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
                                      <th>Unidad</th>
                                      <th>Avance Actual</th>
                                      <?php if (!$esOpVer) { ?><th>Precio U.</th>
                                      <th>Subtotal</th><?php } ?>
                                    </tr>
                                  </tfoot>
                                  </table>
                                  </div>
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
                                <table class="table table-sm table-bordered display" id="dataTables-example668">
                                  <thead>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Observación</th>
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

                                  {
                                    $sql = " SELECT distinct fc.id, fc.observaciones, tc.tipo, lc.letra, fc.numero, c.razon_social, date_format(fc.fecha_emitida,'%d/%m/%y'), fp.forma_pago, fc.total, m.moneda, ef.estado  FROM facturas_venta_detalle_x_certificados_avance fxc inner join facturas_venta_detalle fvd on fvd.id = fxc.id_factura_venta_detalle inner join facturas_venta fc on fc.id = fvd.id_factura_venta inner join tipos_comprobante tc on tc.id = fc.id_tipo_comprobante inner join letras_comprobante lc on lc.id = fc.id_letra_comprobante inner join cuentas c on c.id = fc.id_cuenta_destino inner join formas_pago fp on fp.id = fc.id_condicion_pago inner join monedas m on m.id = fc.id_moneda inner join estados_factura ef on ef.id = fc.id_estado WHERE fxc.id_certificado_avance = ".(int) $_GET['id'];
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
                                      <th>Observación</th>
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
                        <a href="listarCertificadosAvances.php?id_certificado_maestro=<?=$data['id_certificado_maestro']?>" class="btn btn-light">Volver</a>
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
          order: [[1, 'asc']],
          pageLength: 25,
          lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
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
    <script>
      function escapeDetalleVer(value) {
        return $('<div>').text(value == null ? '' : value).html();
      }

      function numeroDetalleVer(value) {
        var parsed = parseFloat(String(value == null ? 0 : value).replace(',', '.'));
        return isNaN(parsed) ? 0 : parsed;
      }

      function formatoDetalleVer(value) {
        return numeroDetalleVer(value).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      }

      var esOpVerJs = <?= $esOpVer ? 'true' : 'false' ?>;

      function renderDesgloseVer(grupos, moneda) {
        var html = '';
        Object.keys(grupos || {}).forEach(function(clave) {
          var grupo = grupos[clave];
          html += '<div class="table-responsive"><table class="table table-sm table-bordered occ-breakdown-table">';
          html += '<thead><tr><th>Posición</th><th>Descripcion</th><th>Cantidad</th><th>Incidencia</th>';
          if (!esOpVerJs) html += '<th>Precio unitario</th><th>Total CM</th>';
          ['Anterior', 'Actual', 'Acumulado', 'Saldo'].forEach(function(periodo) { html += '<th colspan="' + (esOpVerJs ? 2 : 3) + '" class="avance-periodo">' + periodo + '</th>'; });
          html += '</tr><tr><th></th><th></th><th></th><th></th>' + (!esOpVerJs ? '<th></th><th></th>' : '');
          (esOpVerJs ? ['Cantidad', '%', 'Cantidad', '%', 'Cantidad', '%', 'Cantidad', '%'] : ['Cantidad', '%', 'Monto', 'Cantidad', '%', 'Monto', 'Cantidad', '%', 'Monto', 'Cantidad', '%', 'Monto']).forEach(function(titulo) { html += '<th>' + titulo + '</th>'; });
          html += '</tr></thead><tbody>';
          (grupo.filas || []).forEach(function(fila) {
            var cantidad = numeroDetalleVer(fila.cantidad);
            var actual = numeroDetalleVer(fila.cantidad_actual);
            var acumulado = numeroDetalleVer(fila.acumulado);
            var anterior = Math.max(0, acumulado - actual);
            var precio = numeroDetalleVer(fila.precio_unitario_cm);
            var totalCm = numeroDetalleVer(fila.subtotal_cm);
            var saldo = Math.max(0, cantidad - acumulado);
            var porcentaje = function(valor) { return cantidad > 0 ? valor / cantidad * 100 : 0; };
            var monto = function(valor) { return moneda + ' ' + formatoDetalleVer(valor * precio); };
            html += '<tr><td>' + escapeDetalleVer(fila.posicion_aperturado) + '</td>';
            html += '<td>' + escapeDetalleVer(fila.descripcion) + (fila.unidad_medida ? ' (' + escapeDetalleVer(fila.unidad_medida) + ')' : '') + '</td>';
            html += '<td>' + formatoDetalleVer(cantidad) + '</td><td>' + formatoDetalleVer(fila.incidencia_porcentaje) + '%</td>';
            if (!esOpVerJs) html += '<td>' + moneda + ' ' + formatoDetalleVer(precio) + '</td><td>' + moneda + ' ' + formatoDetalleVer(totalCm) + '</td>';
            [[anterior, porcentaje(anterior)], [actual, porcentaje(actual)], [acumulado, porcentaje(acumulado)], [saldo, porcentaje(saldo)]].forEach(function(item) {
              html += '<td>' + formatoDetalleVer(item[0]) + '</td><td>' + formatoDetalleVer(item[1]) + '%</td>';
              if (!esOpVerJs) html += '<td>' + monto(item[0]) + '</td>';
            });
            html += '</tr>';
          });
          html += '</tbody></table></div>';
        });
        return html;
      }

      function cargarDetalleAgrupadoVer() {
        $.post('get_detalle_certificado_avance.php', {id_certificado_avance: <?= (int) $id ?>}, function(data) {
          var html = '<div class="table-responsive"><table class="table table-sm table-bordered display" id="tablaDetalleVer"><thead><tr><th>ID</th><th>Posición</th><th>Descripcion</th><th>Cantidad</th>' + (!esOpVerJs ? '<th>Precio unitario</th><th>Descuento</th><th>Subtotal</th>' : '') + '</tr></thead><tbody>';
          var grupos = data.grupos_por_occ || {};
          (data.occ_detalles || []).forEach(function(occ) {
            html += '<tr><td>' + escapeDetalleVer(occ.id) + '</td><td>' + escapeDetalleVer(occ.posicion) + '</td><td>' + escapeDetalleVer(occ.descripcion) + '</td><td class="text-right">' + formatoDetalleVer(occ.cantidad) + '</td>';
            if (!esOpVerJs) html += '<td class="text-right">' + data.moneda + ' ' + formatoDetalleVer(occ.precio_unitario) + '</td><td class="text-right">' + data.moneda + ' ' + formatoDetalleVer(occ.descuento) + '</td><td class="text-right">' + data.moneda + ' ' + formatoDetalleVer(occ.subtotal) + '</td>';
            html += '</tr>';
            if (grupos[occ.id]) html += '<tr><td colspan="' + (esOpVerJs ? 4 : 7) + '">' + renderDesgloseVer(grupos[occ.id], data.moneda || 'U$S') + '</td></tr>';
          });
          html += '</tbody></table></div>';
          $('#detalle_ca_ver').html(html);
          $('#tablaDetalleVer').DataTable({paging: false, searching: true, info: false, order: [[1, 'asc']], language: {search: 'Buscar:', zeroRecords: 'No hay resultados', emptyTable: 'No hay datos'}});
        }, 'json');
      }

      $(function() { cargarDetalleAgrupadoVer(); });
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>