<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require_once __DIR__ . "/permisos.php";
include 'database.php';
// Control de acceso: si existe la acción Menu_Empresas y el usuario no la tiene, redirigir
try {
  $pdoGuard = Database::connect();
  $qGuard = $pdoGuard->prepare("SELECT id FROM acciones WHERE accion='Menu_Empresas' LIMIT 1");
  $qGuard->execute();
  $idAccEmpresas = $qGuard->fetchColumn();
  if ($idAccEmpresas && empty(tienePermiso((int)$idAccEmpresas))) {
    header("Location: dashboard.php");
    die("Redirecting to dashboard.php");
  }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_tables.php');?>
    <style>
      .truncate {
        max-width:50px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    </style>
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <!-- Page Header Start-->
      <?php include('header.php');?>
      <!-- Page Header Ends                              -->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Empresas ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo $ubicacion; ?>
                      &nbsp;&nbsp;
                      <a href="#" class="btn btn-sm btn-outline-primary ml-2" id="link_empresa">Editar</a>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Empresa</th>
                            <th>CUIT</th>
                          </tr>
                        </thead>
                        <tbody><?php
                          $pdo = Database::connect();
                          $sql = " SELECT id, empresa, cuit FROM empresas ORDER BY empresa ";
                          foreach ($pdo->query($sql) as $row) {?>
                            <tr>
                              <td><?=$row["id"]; ?></td>
                              <td><?=$row["empresa"]; ?></td>
                              <td><?=$row["cuit"]; ?></td>
                            </tr><?php
                          }
                          Database::disconnect();?>
                        </tbody>
					            <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Empresa</th>
                            <th>CUIT</th>
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function() {
      // Setup - add a text input to each footer cell
      $('#dataTables-example666 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      } );

	    $('#dataTables-example666').DataTable({
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
          }
        }
      });

      // DataTable
      var table = $('#dataTables-example666').DataTable();

      // Apply the search
      table.columns().every( function () {
        var that = this;

        $( 'input', this.footer() ).on( 'keyup change', function () {
          if ( that.search() !== this.value ) {
            that.search( this.value ).draw();
          }
        });
	  });

    $("#link_empresa").on("click",function(event){
      let l=document.location.href;
      if(this.href==l || this.href==l+"#"){
        event.preventDefault();
        alert("Por favor seleccione una empresa para editarla")
      }
    })

    $(document).on("click","#dataTables-example666 tbody tr td", function(){
      var t=$(this).parent();

      let id_empresa=t.find("td:first-child").html();
      if(t.hasClass('selected')){
        deselectRow(t);
        $("#link_empresa").attr("href","#");
      }else{
      table.rows().nodes().each( function (rowNode, index) {
        $(rowNode).removeClass("selected");
      });
      selectRow(t);
      $("#link_empresa").attr("href","configurarEmpresaARCA.php?id="+id_empresa);
      }
    });

	  } );

    function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }

    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>
