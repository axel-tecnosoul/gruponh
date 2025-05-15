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
      $id_lista_corte_conjunto=$_POST['btn2'];
      $redirect="nuevaListaCorteConjuntos.php?id_lista_corte_revision=".$_GET["id_lista_corte_revision"];
    }
    if(isset($_POST['btn3'])){
      $id_lista_corte_conjunto=$_POST['btn3'];
      $redirect="nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto;
    }
	
	$sql = "SELECT count(*) cant FROM `listas_corte_conjuntos` WHERE nombre = ? and id_lista_corte = ? and id <> ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['nombre'],$_GET['id_lista_corte_revision'],$id_lista_corte_conjunto]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	
	if ($data['cant'] == 0) {
		$sql = "UPDATE listas_corte_conjuntos SET nombre = ?, cantidad = ? WHERE id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['nombre'],$_POST['cantidad'],$id_lista_corte_conjunto]);
		
		$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificacion de Conjunto ID #$id_lista_corte_conjunto de Lista de Corte','Listas de Corte','')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
	} else {
		$redirect="nuevaListaCorteConjuntos.php?error=1&id_lista_corte_revision=".$_GET["id_lista_corte_revision"];
	}
    

  }else{
    $peso=0;
    $id_estado_lista_corte_conjuntos=1;
	
	$sql = "SELECT count(*) cant FROM `listas_corte_conjuntos` WHERE nombre = ? and id_lista_corte = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['nombre'],$_GET['id_lista_corte_revision']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	
	if ($data['cant'] == 0) {
		$sql = "INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES (?,?,?,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$_GET['id_lista_corte_revision'],$_POST['nombre'],$_POST['cantidad'],$peso,$id_estado_lista_corte_conjuntos]);
		$id_lista_corte_conjunto = $pdo->lastInsertId();
		
		$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Conjunto ID #$id_lista_corte_conjunto de Lista de Corte','Listas de Corte','')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

		$redirect="nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto;
	} else {
		$redirect="nuevaListaCorteConjuntos.php?error=1&id_lista_corte_revision=".$_GET["id_lista_corte_revision"];
	}
  }

  Database::disconnect();
  header("Location: ".$redirect);

}

$id_lista_corte_revision=$_GET['id_lista_corte_revision'];
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT nombre, numero FROM listas_corte_revisiones WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte_revision]);
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
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Conjuntos de la Lista de Corte #<?=$data['numero']." - ".$data['nombre']?>
                      &nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(329))) {?>
                        <img src="img/icon_modificar.png" id="link_modificar_conjunto" style="cursor: pointer;" width="24" height="25" border="0" alt="Modificar" title="Modificar">&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(330))) {?>
                        <a href="#" id="link_eliminar_conjunto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      /*if (!empty(tienePermiso(331))) {?>
                        <a href="#" id="link_nueva_posicion"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nueva Posición" title="Nueva Posición"></a>&nbsp;&nbsp;<?php
                      }*/?>
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevaListaCorteConjuntos.php?id_lista_corte_revision=<?=$id_lista_corte_revision?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-12">
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
                                <tfoot>
                                  <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Cantidad</th>
                                    <th>Peso kg</th>
                                    <th>Estado</th>
                                  </tr>
                                </tfoot>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  
                                  $sql = " SELECT c.id, c.nombre, c.cantidad, c.peso, e.estado FROM listas_corte_conjuntos c inner join estados_lista_corte_conjuntos e on e.id = c.id_estado_lista_corte_conjuntos WHERE c.id_lista_corte = ".$id_lista_corte_revision;
                                  foreach ($pdo->query($sql) as $row) {
                                    echo '<tr>';
                                    echo '<td>'. $row["id"] . '</td>';
                                    echo '<td>'. $row["nombre"] . '</td>';
                                    echo '<td>'. $row["cantidad"] . '</td>';
                                    echo '<td>'. $row["peso"] . '</td>';
                                    echo '<td>'. $row["estado"] . '</td>';
                                    echo '</tr>';
                                  }
                                  Database::disconnect();?>
                                </tbody>
                              </table>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nombre del Conjunto(*)</label>
                            <div class="col-sm-9"><input name="nombre" type="text" maxlength="99" autofocus class="form-control" required="required">
							<?php
							if (!empty($_GET['error'])) {
								echo "*El nombre del conjunto ya existe";
							}
							?>
							</div>
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
						            <a href="listarListasCorte.php" class="btn btn-light">Volver</a> -->

                        <button type="submit" value="1" name="btn1" id="addConjunto" class="btn btn-success">Crear y Agregar Posiciones</button>
                        <button type="submit" value="2" name="btn2" id="editConjunto" class="btn btn-success d-none">Modificar</button>
                        <button type="submit" value="3" name="btn3" id="editConjuntoGoPosiciones" class="btn btn-primary d-none">Modificar e ir a Posiciones</button>
                        <button type="button" id="cancelEditConjunto" class="btn btn-light d-none">Cancelar Modificar</button>
                        <a href='listarListasCorte.php' id="volverListaCorte" class="btn btn-light">Volver al Listas de corte</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- Modal para eliminas conjuntos -->
        <div class="modal fade" id="eliminarConjunto" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el conjunto?</div>
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

          let id_conjunto=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#link_ver_conjunto_lc").attr("href","#");
            $("#link_modificar_conjunto").attr("href","#");
            //$("#link_eliminar_conjunto").attr("href","#");
            $("#link_eliminar_conjunto").data("id","");
            $("#link_nueva_posicion").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            $("#link_ver_conjunto_lc").attr("href","verConjuntoListaCorte.php?id="+id_conjunto);
            //$("#link_modificar_conjunto").attr("href","modificarListaCorteConjunto.php?id="+id_conjunto);
            $("#link_modificar_conjunto").on("click",function(){
              let nombre = t.find("td:nth-child(2)").html();
              let cantidad = t.find("td:nth-child(3)").html();
              $("input[name='nombre']").val(nombre).focus()
              $("input[name='cantidad']").val(cantidad).attr("data-original",cantidad)
              $("#editConjunto").val(id_conjunto)
              $("#editConjuntoGoPosiciones").val(id_conjunto)

              if($("#editConjunto").hasClass("d-none")){
                $("#addConjunto").toggleClass("d-none")
                $("#editConjunto").toggleClass("d-none")
                $("#editConjuntoGoPosiciones").toggleClass("d-none")
                $("#cancelEditConjunto").toggleClass("d-none")
                $("#volverListaCorte").toggleClass("d-none")
              }
            })
            //$("#link_eliminar_conjunto").attr("href","eliminarConjuntoListaCorte.php?id="+id_conjunto);
            $("#link_eliminar_conjunto").data("id",id_conjunto);
            $("#link_nueva_posicion").attr("href","nuevaListaCortePosiciones.php?id_lista_corte_conjunto="+id_conjunto);
          }
        });
    
      });

      $("#link_eliminar_conjunto").on("click",function(){
        let id_conjunto=$(this).data("id")
        if(id_conjunto!="" && id_conjunto>0){
          let modal=$("#eliminarConjunto")
          modal.modal("show")
          modal.find(".modal-footer a").attr("href","eliminarConjuntoListaCorte.php?id="+id_conjunto)
        }
      });

      $("#cancelEditConjunto").on("click",function(){
        $("input[name='nombre']").val("")
        $("input[name='cantidad']").val("")
        $("#addConjunto").toggleClass("d-none")
        $("#editConjunto").toggleClass("d-none")
        $("#editConjuntoGoPosiciones").toggleClass("d-none")
        $("#editConjunto").val("")
        $("#editConjuntoGoPosiciones").val("")
        $("#cancelEditConjunto").toggleClass("d-none")
        $("#volverListaCorte").toggleClass("d-none")
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