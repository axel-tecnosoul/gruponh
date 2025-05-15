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

  /*var_dump($_POST);
  var_dump($_GET);
  die();*/

  //btn2 y btn3 son parar modificar
  if (isset($_POST['btn2']) or isset($_POST['btn3'])) {

    if(isset($_POST['btn2'])){
      $id_packing_list_seccion=$_POST['btn2'];
      $redirect="nuevaPackingListSecciones.php?id_packing_list_revision=".$_GET["id_packing_list_revision"];
    }
    if(isset($_POST['btn3'])){
      $id_packing_list_seccion=$_POST['btn3'];
      $redirect="nuevaPackingListComponentes.php?id_packing_list_seccion=".$id_packing_list_seccion;
    }

    $sql = "UPDATE packing_lists_secciones SET cantidad = ?, observaciones = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['cantidad'],$_POST['observaciones'],$id_packing_list_seccion]);
    
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificacion de Seccion ID #$id_packing_list_seccion de Packing List','Packing List','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

  }else{
    $peso=0;
    $id_estado_packing_list_secciones=1;

    $sql = "INSERT INTO packing_lists_secciones (id_packing_list_revision, cantidad, observaciones) VALUES (?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$_GET['id_packing_list_revision'],$_POST['cantidad'],$_POST['observaciones']]);
    $id_packing_list_seccion = $pdo->lastInsertId();
    
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Seccion ID #$id_packing_list_seccion de Packing List','Packing List','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    $redirect="nuevaPackingListComponentes.php?id_packing_list_seccion=".$id_packing_list_seccion;
  }

  Database::disconnect();
  header("Location: ".$redirect);

}

$id_packing_list_revision=$_GET['id_packing_list_revision'];
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT id_packing_list,numero FROM packing_lists_revisiones WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_packing_list_revision]);
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
          $ubicacion="Nuevo Seccion";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Secciones del Packing List #<?=$data['numero']?>
                      &nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(329))) {?>
                        <img src="img/icon_modificar.png" id="link_modificar_seccion" style="cursor: pointer;" width="24" height="25" border="0" alt="Modificar" title="Modificar">&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(330))) {?>
                        <a href="#" id="link_eliminar_seccion"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      /*if (!empty(tienePermiso(331))) {?>
                        <a href="#" id="link_nueva_componente"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nueva Posición" title="Nueva Posición"></a>&nbsp;&nbsp;<?php
                      }*/?>
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevaPackingListSecciones.php?id_packing_list_revision=<?=$id_packing_list_revision?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-12">
                              <table class="display" id="dataTables-example667">
                                <thead>
                                  <tr>
                                    <th>ID</th>
                                    <th>Nombre de la sección</th>
									<th>Cantidad</th>
                                  </tr>
                                </thead>
                                <tfoot>
                                  <tr>
                                    <th>ID</th>
                                    
                                    <th>Nombre de la sección</th>
									<th>Cantidad</th>
                                  </tr>
                                </tfoot>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  
                                  $sql = " SELECT id, cantidad, observaciones FROM packing_lists_secciones WHERE id_packing_list_revision = ".$id_packing_list_revision;
                                  foreach ($pdo->query($sql) as $row) {
                                    echo '<tr>';
                                    echo '<td>'. $row["id"] . '</td>';
									echo '<td>'. $row["observaciones"] . '</td>';
                                    echo '<td>'. $row["cantidad"] . '</td>';
                                    echo '</tr>';
                                  }
                                  Database::disconnect();?>
                                </tbody>
                              </table>
                            </div>
                          </div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nombre de la sección(*)</label>
                            <div class="col-sm-9"><textarea name="observaciones" class="form-control" required="required" autofocus></textarea></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9">
                              <input name="cantidad" data-original="" type="number" step="0.01" class="form-control" required="required">
                            </div>
                          </div>
                          
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <!-- <button class="btn btn-primary" type="submit">Crear</button>
						            <a href="listarPackingList.php" class="btn btn-light">Volver</a> -->

                        <button type="submit" value="1" name="btn1" id="addComponente" class="btn btn-success">Crear y Agregar Componentes</button>
                        <button type="submit" value="2" name="btn2" id="editSeccion" class="btn btn-success d-none">Modificar</button>
                        <button type="submit" value="3" name="btn3" id="editSeccionGoComponentes" class="btn btn-primary d-none">Modificar e ir a Componentes</button>
                        <button type="button" id="cancelEditSeccion" class="btn btn-light d-none">Cancelar Modificar</button>
                        <a href='listarPackingList.php' id="volverPackingList" class="btn btn-light">Volver al Packing list</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- Modal para eliminas secciones -->
        <div class="modal fade" id="eliminarSeccion" tabindex="-1" role="dialog" aria-labelledby="exampleModalSeccionLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalSeccionLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el seccion?</div>
              <div class="modal-footer">
                <a href="#" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
              </div>
            </div>
          </div>
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

          let id_seccion=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#link_ver_seccion_pl").attr("href","#");
            $("#link_modificar_seccion").attr("href","#");
            //$("#link_eliminar_seccion").attr("href","#");
            $("#link_eliminar_seccion").data("id","");
            $("#link_nueva_componente").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            $("#link_ver_seccion_pl").attr("href","verSeccionPackingList.php?id="+id_seccion);
            //$("#link_modificar_seccion").attr("href","modificarPackingListSeccion.php?id="+id_seccion);
            $("#link_modificar_seccion").on("click",function(){
              let cantidad = t.find("td:nth-child(3)").html();
              let observaciones = t.find("td:nth-child(2)").html();
              $("textarea[name='observaciones']").val(observaciones)
              $("input[name='cantidad']").val(cantidad).attr("data-original",cantidad).focus()
              $("#editSeccion").val(id_seccion)
              $("#editSeccionGoComponentes").val(id_seccion)

              if($("#editSeccion").hasClass("d-none")){
                $("#addComponente").toggleClass("d-none")
                $("#editSeccion").toggleClass("d-none")
                $("#editSeccionGoComponentes").toggleClass("d-none")
                $("#cancelEditSeccion").toggleClass("d-none")
                $("#volverPackingList").toggleClass("d-none")
              }
            })
            //$("#link_eliminar_seccion").attr("href","eliminarSeccionPackingList.php?id="+id_seccion);
            $("#link_eliminar_seccion").data("id",id_seccion);
            $("#link_nueva_componente").attr("href","nuevaPackingListComponentes.php?id_packing_list_seccion="+id_seccion);
          }
        });
    
      });

      $("#link_eliminar_seccion").on("click",function(){
        let id_seccion=$(this).data("id")
        if(id_seccion!="" && id_seccion>0){
          let modal=$("#eliminarSeccion")
          modal.modal("show")
          modal.find(".modal-footer a").attr("href","eliminarSeccionPackingList.php?id="+id_seccion)
        }
      });

      $("#cancelEditSeccion").on("click",function(){
        $("input[name='nombre']").val("")
        $("input[name='cantidad']").val("")
        $("#addComponente").toggleClass("d-none")
        $("#editSeccion").toggleClass("d-none")
        $("#editSeccionGoComponentes").toggleClass("d-none")
        $("#editSeccion").val("")
        $("#editSeccionGoComponentes").val("")
        $("#cancelEditSeccion").toggleClass("d-none")
        $("#volverPackingList").toggleClass("d-none")
      })

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    </script>
  </body>
</html>