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

  $modoDebug=0;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_GET);
    var_dump($_POST);
  }

  $column_names = [
    1 => "monto_acumulado_avances",
    2 => "monto_acumulado_anticipos",
    3 => "monto_acumulado_desacopios",
    4 => "monto_acumulado_descuentos",
    5 => "monto_acumulado_ajustes",
  ];

  //$id_tipo_item=$_POST["id_tipo_item"];

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

    /*$column_name = $column_names[$id_tipo_item];

    $sql = "UPDATE certificados_maestros SET $column_name = $column_name + ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal,$_GET['id_certificado_maestro']]);*/

    /*$precio_unitario=$_POST["precio_unitario"];
    $id_certificado_maestro_detalle=$_POST["id_certificado_maestro_detalle"];
    $avance=$_POST["avance"];
    $precio_unitario=$_POST["precio_unitario"];
    $subtotal=$_POST["subtotal"];*/

    $id_certificado_avance=$_GET['id_certificado_avance'];

    $sql = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_certificado_avance]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $id_certificado_maestro=$data["id_certificado_maestro"];

    $suma_subtotal=0;

    foreach ($_POST["id_certificado_maestro_detalle"] as $key => $id_certificado_maestro_detalle) {
      
      /*$cantidad_actual=$_POST['cantidad'][$key];*/
      $avance=$_POST['avance'][$key];

      //if($cantidad_actual>0 and $precio_unitario>0){
      if($avance>0){

        $precio_unitario=$_POST['precio_unitario'][$key];
        $id_tipo_item=$_POST['id_tipo_item'][$key];

        /*$cantidad_anterior=0;
        $cantidad_acumulado=$cantidad_anterior+$cantidad_actual;*/

        $sql = "SELECT cantidad_acumulado FROM certificados_avances_detalle WHERE id_certificado_maestro_detalle = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$id_certificado_maestro_detalle]);
        $data = $q->fetch(PDO::FETCH_ASSOC);

        $cantidad_acumulado_anterior=0;
        
        if($data and !is_null($data["cantidad_acumulado"])){
          $cantidad_acumulado_anterior=$data["cantidad_acumulado"];
        }

        
        $total_acumulado=$cantidad_acumulado_anterior+$avance;
        
        $subtotal=$avance*$precio_unitario;
        $suma_subtotal+=$subtotal;

        $sql = "INSERT INTO certificados_avances_detalle (id_certificado_avance, id_certificado_maestro_detalle, cantidad_anterior, cantidad_actual, cantidad_acumulado, precio_unitario, subtotal) VALUES (?,?,?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_certificado_avance,$id_certificado_maestro_detalle, $cantidad_acumulado_anterior, $avance, $total_acumulado, $precio_unitario,$subtotal]);

        $column_name = $column_names[$id_tipo_item];
        //sumamos el nuevo subtotal en la columna segun el nuevo tipo de detalle
        $sql = "UPDATE certificados_avances_cabecera SET $column_name = $column_name + ? WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$subtotal,$id_certificado_avance]);

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: " . $q->rowCount();
          echo "<br><br>";
        }
        //$id_certificados_maestros_detalles = $pdo->lastInsertId();
      }

    }

    $sql = "UPDATE certificados_avances_cabecera SET monto_total = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$suma_subtotal,$id_certificado_avance]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: " . $q->rowCount();
      echo "<br><br>";
    }
    
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Detalle Certificado de Avance #$id_certificado_avance','Certificado de Avance','verCertificadoAvance.php?id=$id_certificado_avance')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: " . $q->rowCount();
      echo "<br><br>";
    }

    if ($modoDebug==1) {
      $pdo->rollBack();
      die();
    }

    Database::disconnect();
    //header("Location: listarCertificadosMaestro.php?id_certificado_avance=".$_GET["id_certificado_avance"]);
    header("Location: listarCertificadosAvances.php?id_certificado_maestro=".$id_certificado_maestro);

  }

}

$id_certificado_avance=$_GET['id_certificado_avance'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_avance]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$id_certificado_maestro=$data["id_certificado_maestro"];

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
          $ubicacion="Certificado de avance";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Nuevo Detalle del Certificado de Avance #<?=$id_certificado_avance?>
                      &nbsp;&nbsp;
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevoCertificadoAvanceDetalle.php?id_certificado_avance=<?=$id_certificado_avance?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-12">
                              <div class="dt-ext table-responsive">
                                
                                <table class="display" id="dataTables-example667">
                                  <thead>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Tipo</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
									  <th>Acumulado</th>
									  <th>Saldo</th>
                                      <th>Unidad</th>
                                      <th>Precio U.</th>
                                      <th>Avance Actual</th>
                                      <th>Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tfoot>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Tipo</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
									  <th>Acumulado</th>
									  <th>Saldo</th>
                                      <th>Unidad</th>
                                      <th>Precio U.</th>
                                      <th>Avance Actual</th>
                                      <th>Subtotal</th>
                                    </tr>
                                  </tfoot>
                                  <tbody><?php
                                    $pdo = Database::connect();
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                    $sql = " SELECT cmd.id AS id_certificado_maestro_detalle,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario AS precio_unitario_cm,cmd.subtotal AS subtotal_cm,m.moneda FROM certificados_maestros_detalles cmd INNER JOIN certificados_maestros cm ON cmd.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id WHERE cmd.id_certificado_maestro=$id_certificado_maestro";

                                    foreach ($pdo->query($sql) as $row) {
										$acumulado = 0;
										$sql2 = "select sum(cantidad_actual) acumulado from certificados_avances_detalle where id_certificado_maestro_detalle = ? ";
										$q2 = $pdo->prepare($sql2);
										$q2->execute([$row["id_certificado_maestro_detalle"]]);
										$data2 = $q2->fetch(PDO::FETCH_ASSOC);
										if (!empty($data2['acumulado'])) {
											$acumulado = $data2['acumulado'];
										}
									   ?>
                                      <tr>
                                        <td class="d-none"><?=$row["id_certificado_maestro_detalle"]?></td>
                                        <td data-id="<?=$row["id_tipo_item_certificado"]?>">
                                          <input type="hidden" name="id_tipo_item[]" value="<?=$row["id_tipo_item_certificado"]?>">
                                          <?=$row["tipo"]?>
                                        </td>
                                        <td><?=$row["descripcion"]?></td>
                                        <td style="text-align:right"><?=$row["cantidad"]?></td>
										<td style="text-align:right"><?=$acumulado?></td>
										<td style="text-align:right"><?=$row["cantidad"]-$acumulado;?></td>
                                        <td data-id="<?=$row["id_unidad_medida"]?>"><?=$row["unidad_medida"]?></td>
                                        <td style="text-align:right">
                                          <?=$row["moneda"]." ".number_format($row["precio_unitario_cm"],2)?>
                                          <input type="hidden" name="precio_unitario[]" value="<?=$row["precio_unitario_cm"]?>">
                                        </td>
                                        <td>
                                          <input type="hidden" name="id_certificado_maestro_detalle[]" value="<?=$row["id_certificado_maestro_detalle"]?>">
                                          <input type="number" step="0.01" class="form-control" name="avance[]" placeholder="Avance" min="0" max="<?=$row["cantidad"]-$acumulado;?>">
                                        </td>
                                        <td style="text-align:right">
                                          <?=$row["moneda"]?> <label class='subtotal_formatted'>0.00</label>
                                          <input type="hidden" name="subtotal[]">
                                        </td>
                                      </tr><?php
                                    }
                                    Database::disconnect();?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">

                        <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion">Crear Certificado de Avance</button>
                        <!-- <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Crear e ir a Certificados</button> -->
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

        $("form").on("submit",function(e){
          e.preventDefault();
          let ok=0;
          $("#dataTables-example667 tbody tr").each(function(){
            let avance=$(this).find("input[name='avance[]']").val()
            //let precio_unitario=$(this).find("input[name='precio_unitario[]']").val()
            if(avance.length>0){// && precio_unitario.length>0
              ok=1;
            }
          })
          if(ok==0){
            alert("Debe completar el avance de al menos una fila");
          }else{
            this.submit();
            //console.log("submit");
          }
        })

        $(document).on("input","input[name='avance[]']", function(){
          let avance=this.value
          if(isNaN(avance)){
            avance=0;
          }
          let fila=$(this).parents("tr");
          console.log(fila);
          let precio_unitario=fila.find("input[name='precio_unitario[]']").val();
          let subtotal=avance*parseFloat(precio_unitario)
          
          console.log(subtotal);
          subtotal_formatted = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          console.log(subtotal_formatted);
          
          fila.find("input[name='subtotal[]']").val(subtotal);
          fila.find(".subtotal_formatted").html(subtotal_formatted);

        });

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
            //selectRow(t);
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