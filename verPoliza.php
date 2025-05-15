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
  header("Location: listarPolizas.php");
}

if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT p.id AS id_poliza,occ.numero AS numero_occ, date_format(p.fecha_solicitud,'%d/%m/%y') AS fecha_solicitud,c1.nombre AS usuario_solicitante,p.numero,c2.nombre AS proveedor,c3.nombre as beneficiario,tcp.tipo,date_format(p.vigencia_desde,'%d/%m/%y') AS vigencia_desde,date_format(p.vigencia_hasta,'%d/%m/%y') AS vigencia_hasta,p.monto_garantia,m.moneda,p.descripcion_objetivo,IF(p.activa=1,'Si','No') AS activa,p.adjunto , date_format(p.fecha_renovacion,'%d/%m/%y') AS fecha_renovacion, e.empresa FROM polizas p INNER JOIN occ ON p.id_occ=occ.id left JOIN cuentas c1 ON p.id_cuenta_solicitante=c1.id INNER JOIN cuentas c2 ON p.id_cuenta_proveedor_aseguradora=c2.id INNER JOIN cuentas c3 ON p.id_cuenta_cliente_beneficiario=c3.id INNER JOIN tipos_cobertura_polizas tcp ON p.id_tipo_cobertura=tcp.id INNER JOIN monedas m ON p.id_moneda=m.id left join empresas e on e.id = p.id_empresa WHERE p.id = ? ";
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
          $ubicacion="Ver Poliza";
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
                            <div class="col-sm-9"><?=$data['numero'];?></div>
                          </div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Empresa(*)</label>
                            <div class="col-sm-9"><?=$data['empresa'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Solicitud(*)</label>
                            <div class="col-sm-9"><?=$data['fecha_solicitud'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha renovación</label>
                            <div class="col-sm-9"><?=$data['fecha_renovacion'];?></div>
                          </div>
                          
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Aseguradora(*)</label>
                            <div class="col-sm-9"><?=$data['proveedor']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Beneficiario(*)</label>
                            <div class="col-sm-9"><?=$data['beneficiario']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipo de Cobertura(*)</label>
                            <div class="col-sm-9"><?=$data['tipo']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Vigencia desde(*)</label>
                            <div class="col-sm-9"><?=$data['vigencia_desde'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Vigencia hasta(*)</label>
                            <div class="col-sm-9"><?=$data['vigencia_hasta'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Moneda(*)</label>
                            <div class="col-sm-9"><?=$data['moneda']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Monto de la Garantía(*)</label>
                            <div class="col-sm-9">$<?=number_format($data['monto_garantia'],2);?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Objeto del seguro</label>
                            <div class="col-sm-9"><?=$data['descripcion_objetivo'];?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Activo(*)</label>
                            <div class="col-sm-9"><?=$data['activa'];?></div>
                          </div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Adjunto</label>
                            <div class="col-sm-9"><?=$data['adjunto'];?></div>
                          </div>

                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						            <a class="btn btn-primary" target="_blank" href="imprimirPoliza.php?id=<?=$id?>">Imprimir</a>
                        <a href="listarPolizas.php" class="btn btn-light">Volver</a>
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