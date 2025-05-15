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
  $sql = "SELECT cm.id,cm.numero AS numero_cm,occ.numero AS numero_occ,date_format(cm.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cm.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cm.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cm.cotizacion_dolar,cm.monto_total,cm.monto_acumulado_avances,cm.monto_acumulado_anticipos,cm.monto_acumulado_desacopios,cm.monto_acumulado_descuentos,cm.monto_acumulado_ajustes,cm.observaciones FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ=occ.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cm.id = ? ";
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
          $ubicacion="Ver Certificado Maestro";
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
                            <label class="col-sm-3 col-form-label">Orden de Compra Cliente(*)</label>
                            <div class="col-sm-9"><?=$data['numero_occ']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Número(*)</label>
                            <div class="col-sm-9"><?=$data['numero_cm'];?></div>
                          </div>
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
                            <label class="col-sm-3 col-form-label">Moneda(*)</label>
                            <div class="col-sm-9"><?=$data['moneda']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cotizacion Dolar</label>
                            <div class="col-sm-9">$<?=$data['cotizacion_dolar'];?></div>
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
                            <!-- Zero Configuration  Starts-->
                            <div class="col-sm-12">
                              <div class="card">
                                <div class="card-header">
                                  <h5>Detalle del Certificado Maestro</h5>
                                </div>
                                <div class="card-body">
                                  <div class="dt-ext table-responsive">
                                    <table class="display" id="dataTables-example667">
                                      <thead>
                                        <tr>
                                          <th class="d-none">ID</th>
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
                                      $pdo = Database::connect();
                                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                      
                                      $sql = "SELECT cmd.id,s.nombre AS sitio,s2.nombre AS subsitio,cmd.id_proyecto,p.nombre AS proyecto,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cmd.subtotal FROM certificados_maestros_detalles cmd INNER JOIN proyectos p ON cmd.id_proyecto=p.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id inner join sitios s on s.id = p.id_sitio left join sitios s2 on s2.id = s.id_sitio_superior WHERE id_certificado_maestro = ".$id;
                                      foreach ($pdo->query($sql) as $row) {
                                        echo "<tr>";
                                        echo "<td class='d-none'>".$row["id"]."</td>";
                                        echo "<td>".$row["proyecto"]."</td>";
                                        echo "<td>".$row["subsitio"]."</td>";
                                        echo "<td>".$row["sitio"]."</td>";
                                        echo "<td>".$row["tipo"]."</td>";
                                        echo "<td>".$row["descripcion"]."</td>";
                                        echo "<td>".$row["cantidad"]."</td>";
                                        echo "<td>".$row["unidad_medida"]."</td>";
                                        echo "<td>$".number_format($row["precio_unitario"],2)."</td>";
                                        echo "<td>$".number_format($row["subtotal"],2)."</td>";
                                        echo "</tr>";
                                      }?></tbody>
                                      <tfoot>
                                        <tr>
                                          <th class="d-none">ID</th>
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
                                      </tfoot>
                                    </table>
                                  </div>
                                </div>
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
						            <a class="btn btn-primary" target="_blank" href="imprimirCertificadoMaestro.php?id=<?=$id?>">Imprimir</a>
                        <a href="listarCertificadosMaestros.php" class="btn btn-light">Volver</a>
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
	} );
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>