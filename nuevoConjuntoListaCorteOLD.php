<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
if (!empty($_POST)) {
    
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  /*$sql = "INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES (?,?,?,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id_lista_corte'],$_POST['nombre'],$_POST['cantidad'],$_POST['peso'],$_POST['id_estado_lista_corte_conjuntos']]);*/

  $peso=0;
  $id_estado_lista_corte_conjuntos=1;
  $sql = "INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES (?,?,?,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id_lista_corte'],$_POST['nombre'],$_POST['cantidad'],$peso,$id_estado_lista_corte_conjuntos]);
  $id_lista_corte_conjunto = $pdo->lastInsertId();
  
  $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Conjunto ID #$id_lista_corte_conjunto de Lista de Corte','Listas de Corte','')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  Database::disconnect();
  header("Location: nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto);

}

$id_lista_corte=$_GET['id_lista_corte'];
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT nombre, numero FROM listas_corte WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte]);
$data = $q->fetch(PDO::FETCH_ASSOC);
Database::disconnect();
?>
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
          $ubicacion="Nuevo Conjunto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row d-none">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>
                      Conjuntos de la Lista de Corte #<?=$data['numero']." - ".$data['nombre']?>&nbsp;&nbsp;
                      <a href="#" id="link_ver_conjunto_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(329))) {?>
                        <a href="#" id="link_modificar_conjunto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(330))) {?>
                        <a href="#" id="link_eliminar_conjunto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(331))) {?>
                        <a href="#" id="link_nueva_posicion"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nueva Posición" title="Nueva Posición"></a>&nbsp;&nbsp;<?php
                      }?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Cantidad</th>
                            <th>Peso kg</th>
                            <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Cantidad</th>
                            <th>Peso kg</th>
                            <th>Estado</th>
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
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevoConjuntoListaCorte.php?id_lista_corte=<?=$id_lista_corte?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nombre(*)</label>
                            <div class="col-sm-9"><input name="nombre" type="text" maxlength="99" autofocus class="form-control" required="required"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button class="btn btn-success" type="submit" value="1" name="btn1">Crear y Agregar Posiciones</button>
                        <a href='listarListasCorte.php' class="btn btn-light">Volver al Listas de corte</a>
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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
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
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script>
      $(document).ready(function () {
        // Setup - add a text input to each footer cell
        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
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
              that.search( this.value ).draw();
            }
          });
        } );

        //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#dataTables-example667 tbody tr td", function(){
          var t=$(this).parent();
          //t.parent().find("tr").removeClass("selected");

          let id_lc=t.find("td:first-child").html();
          let nro_revision = t.find("td:nth-child(3)").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            get_conjuntos(id_lc)
            $("#link_ver_lc").attr("href","#");
            $("#link_imprimir_lc").attr("href","#");
            $("#link_eliminar_lc").attr("data-target","#");
            $("#link_modificar_lc").attr("href","#");
            $("#link_nuevo_conjunto").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_conjuntos(id_lc)
            $("#link_ver_lc").attr("href","verListaCorte.php?id="+id_lc);
            $("#link_imprimir_lc").attr("href","imprimirListaCorte.php?id="+id_lc);
            $("#link_nuevo_conjunto").attr("href","nuevoConjuntoListaCorte.php?id="+id_lc);
            $("#link_modificar_lc").attr("href","modificarListaCorte.php?id="+id_lc+"&revision="+nro_revision);
            $("#link_eliminar_lc").attr("data-toggle","modal");
            $("#link_eliminar_lc").attr("data-target","#eliminarModal_"+id_lc);
          }
        });
    
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    </script>
  </body>
</html>