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

  $column_names = [
    1 => "monto_acumulado_avances",
    2 => "monto_acumulado_anticipos",
    3 => "monto_acumulado_desacopios",
    4 => "monto_acumulado_descuentos",
    5 => "monto_acumulado_ajustes",
  ];

  $id_tipo_item=$_POST["id_tipo_item"];
  $subtotal=$_POST['cantidad']*$_POST['precio_unitario'];

  //btn2 y btn3 son parar modificar
  if (isset($_POST['btn3'])) {

    //obtenemos la informacion del detalle del certificado antes de editarlo
    $sql = "SELECT id_tipo_item_certificado,subtotal FROM certificados_maestros_detalles WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['id_certificado_maestro_detalle']]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $id_tipo_item_old=$data["id_tipo_item_certificado"];
    $subtotal_old=$data["subtotal"];

    //obtenemos el nombre de la columna del tipo de detalle en la tabla certificado_maestro para restar el subtotal
    $column_name_old = $column_names[$id_tipo_item_old];
    //restamos el viejo subtotal en la columna segun el viejo tipo de detalle
    $sql = "UPDATE certificados_maestros SET $column_name_old = $column_name_old - ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal_old,$_GET['id_certificado_maestro']]);

    //obtenemos el nombre de la columna en la tabla certificado_maestro para sumar el subtotal
    $column_name = $column_names[$id_tipo_item];
    //sumamos el nuevo subtotal en la columna segun el nuevo tipo de detalle
    $sql = "UPDATE certificados_maestros SET $column_name = $column_name + ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal,$_GET['id_certificado_maestro']]);

    $sql = "UPDATE certificados_maestros_detalles SET id_proyecto=?, id_tipo_item_certificado=?, descripcion=?, cantidad=?, id_unidad_medida=?, precio_unitario=?, subtotal=? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST["id_proyecto"], $id_tipo_item, $_POST["descripcion"], $_POST["cantidad"], $_POST["id_unidad_medida"], $_POST["precio_unitario"],$subtotal,$_POST['id_certificado_maestro_detalle']]);
    
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificacion de Detalle ID #".$_POST['id_certificado_maestro_detalle']." de Certificado Maestro','Certificado Maestro','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=".$_GET['id_certificado_maestro']);

  }else{

    $column_name = $column_names[$id_tipo_item];

    $sql = "UPDATE certificados_maestros SET $column_name = $column_name + ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal,$_GET['id_certificado_maestro']]);

    $sql = "INSERT INTO certificados_maestros_detalles (id_certificado_maestro, id_proyecto, id_tipo_item_certificado, descripcion, cantidad, id_unidad_medida, precio_unitario, subtotal) VALUES (?,?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$_GET['id_certificado_maestro'],$_POST["id_proyecto"], $id_tipo_item, $_POST["descripcion"], $_POST["cantidad"], $_POST["id_unidad_medida"], $_POST["precio_unitario"],$subtotal]);
    $id_certificados_maestros_detalles = $pdo->lastInsertId();
    
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Detalle #$id_certificados_maestros_detalles de Certificado Maestro','Certificado Maestro','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    Database::disconnect();
    if (isset($_POST['btn1'])) {
      header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=".$_GET["id_certificado_maestro"]);
    } else {
      header("Location: listarCertificadosMaestros.php");
    }
  }

}

$id_certificado_maestro=$_GET['id_certificado_maestro'];
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
                    <h5>Detalle del Certificado Maestro #<?=$id_certificado_maestro?>
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
					        <form class="form theme-form" role="form" method="post" action="nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=<?=$id_certificado_maestro?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-12">
                              <div class="dt-ext table-responsive">
                                <table class="display" id="dataTables-example667">
                                  <thead>
                                    <tr>
                                      <th>ID</th>
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
                                  <tfoot>
                                    <tr>
                                      <th>ID</th>
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
                                  <tbody><?php
                                    $pdo = Database::connect();
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    
                                    $sql = " SELECT cmd.id,s.nombre AS sitio,s2.nombre AS subsitio,cmd.id_proyecto,p.nombre AS proyecto,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cmd.subtotal FROM certificados_maestros_detalles cmd INNER JOIN proyectos p ON cmd.id_proyecto=p.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id left join sitios s on s.id = p.id_sitio left join sitios s2 on s2.id = s.id_sitio_superior WHERE id_certificado_maestro = ".$id_certificado_maestro;
                                    foreach ($pdo->query($sql) as $row) {
                                      echo '<tr>';
                                      echo '<td>'.$row["id"].'</td>';
                                      echo '<td data-id="'.$row["id_proyecto"].'">'.$row["proyecto"].'</td>';
                                      if (empty($row["subsitio"])) {
                                        echo '<td>'.$row["sitio"].'</td>';
                                        echo '<td>&nbsp;</td>';
                                      } else {
                                        echo '<td>'.$row["subsitio"].'</td>';
                                        echo '<td>'.$row["sitio"].'</td>';
                                      }
                                      echo '<td data-id="'.$row["id_tipo_item_certificado"].'">'.$row["tipo"].'</td>';
                                      echo '<td>'.$row["descripcion"].'</td>';
                                      echo '<td>'.$row["cantidad"].'</td>';
                                      echo '<td data-id="'.$row["id_unidad_medida"].'">'.$row["unidad_medida"].'</td>';
                                      echo '<td>$'.number_format($row["precio_unitario"],2).'</td>';
                                      echo '<td>$'.number_format($row["subtotal"],2).'</td>';
                                      echo '</tr>';
                                    }
                                    Database::disconnect();?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                          <div class="form-group row">
                            <input type="hidden" name="id_certificado_maestro_detalle">
                            <label class="col-sm-3 col-form-label">Proyecto(*)</label>
                            <div class="col-sm-9">
                             <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" autofocus required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  /*if (!empty($_GET['id'])) {
                                    if ($fila['id'] == $_GET['id']) {
                                      echo " selected ";
                                    }
                                  }*/
                                  echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipo Item(*)</label>
                            <div class="col-sm-9">
                             <select name="id_tipo_item" id="id_tipo_item" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id,tipo FROM tipos_item_certificado";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  /*if (!empty($_GET['id'])) {
                                    if ($fila['id'] == $_GET['id']) {
                                      echo " selected ";
                                    }
                                  }*/
                                  echo ">".$fila['tipo']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Descripcion</label>
                            <div class="col-sm-9"><input name="descripcion" type="text" maxlength="199" class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Medida(*)</label>
                            <div class="col-sm-9">
                              <select name="id_unidad_medida" id="id_unidad_medida" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id,unidad_medida FROM unidades_medida";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['unidad_medida']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Precio unitario(*)</label>
                            <div class="col-sm-9"><input name="precio_unitario" step="0.01" type="number" class="form-control" required></div>
                          </div>
                          <!-- <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Subtotal(*)</label>
                            <div class="col-sm-9"><input name="subtotal" step="0.01" type="number" class="form-control" required></div>
                          </div> -->
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">

                        <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion">Crear y Agregar otro Detalle</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Crear e ir a Certificados</button>
                        <button type="submit" value="3" name="btn3" id="editPosicion" class="btn btn-primary d-none">Modificar</button>
                        <button type="button" id="cancelEditPosicion" class="btn btn-danger d-none">Cancelar Modificar</button>
                        <a href='listarCertificadosMaestros.php' class="btn btn-light">Volver</a>

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
              <div class="modal-body">¿Está seguro que desea eliminar el detalle?</div>
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
            }
          }
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
              let id_certificado_maestro_detalle = t.find("td:nth-child(1)").html();
              let id_proyecto = t.find("td:nth-child(2)").data("id");
              let id_tipo = t.find("td:nth-child(5)").data("id");
              let descripcion = t.find("td:nth-child(6)").html();
              let cantidad = t.find("td:nth-child(7)").html();
              let id_unidad_medida = t.find("td:nth-child(8)").data("id");
              let precio_unitario = t.find("td:nth-child(9)").html();
              $("input[name='id_certificado_maestro_detalle']").val(id_certificado_maestro_detalle)
              $("select[name='id_proyecto']").val(id_proyecto).trigger('change');
              $("select[name='id_tipo_item']").val(id_tipo).trigger('change');
              $("input[name='descripcion']").val(descripcion).focus()
              $("input[name='cantidad']").val(cantidad);
              $("select[name='id_unidad_medida']").val(id_unidad_medida).trigger('change');
              $("input[name='precio_unitario']").val(precio_unitario);
              $("#editPosicion").val(id_conjunto)

              if($("#editPosicion").hasClass("d-none")){
                $(".addPosicion").toggleClass("d-none")
                $("#editPosicion").toggleClass("d-none")
                $("#cancelEditPosicion").toggleClass("d-none")
                $("#volverListaCorte").toggleClass("d-none")
              }
            })
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
          modal.find(".modal-footer a").attr("href","eliminarDetalleCertificadoMaestro.php?id="+id_conjunto)
        }
      });

      $("#cancelEditPosicion").on("click",function(){
        $("input[name='id_certificado_maestro_detalle']").val("")
        $("select[name='id_proyecto']").val("").trigger('change');
        $("select[name='id_tipo_item']").val("").trigger('change');
        $("input[name='descripcion']").val("").focus()
        $("input[name='cantidad']").val("");
        $("select[name='id_unidad_medida']").val("").trigger('change');
        $("input[name='precio_unitario']").val("");

        $(".addPosicion").toggleClass("d-none")
        $("#editPosicion").toggleClass("d-none")
        $("#editPosicion").val("")
        $("#cancelEditPosicion").toggleClass("d-none")

        /*$("#addConjunto").toggleClass("d-none")
        $("#cancelEditPosicion").toggleClass("d-none")
        $("#volverListaCorte").toggleClass("d-none")*/
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