<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
require 'database.php';

$id_bulto = null;
if (!empty($_GET['id_bulto'])) {
  $id_bulto = $_REQUEST['id_bulto'];
}

if (null==$id_bulto) {
  header("Location: listarDespachos.php");
}

if (!empty($_POST)) {
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_FILES);
  }

  if (!empty($_POST['btn3'])) {
    //editar posicion
    $id_lista_corte_posicion=$_POST['btn3'];

    $sql = "SELECT pos.cantidad, id_material, id_lista_corte_conjunto, id_lista_corte FROM lista_corte_posiciones pos INNER JOIN listas_corte_conjuntos lcc ON pos.id_lista_corte_conjunto=lcc.id WHERE pos.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte_posicion]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $id_lista_corte=$data['id_lista_corte'];
    $id_lista_corte_conjunto=$data['id_lista_corte_conjunto'];

    $sql = "UPDATE listas_corte_conjuntos set peso = peso - (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$data['cantidad'],$data['id_material'],$id_lista_corte_conjunto]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
    
    /*$sql = "UPDATE lista_corte_posiciones set largo=?,ancho=?,marca=?,peso=?,finalizado=?,diametro= ?,calidad= ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['largo'],$_POST['ancho'],$_POST['marca'],$_POST['peso'],$_POST['finalizado'],$_POST['diametro'],$_POST['calidad'],$id]);*/
    $sql = "UPDATE lista_corte_posiciones set largo=?,ancho=?,marca=?,peso=?,diametro=? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['largo'],$_POST['ancho'],$_POST['marca'],$_POST['peso'],$_POST['diametro'],$id_lista_corte_posicion]);

    if(isset($_POST["cantidad_posicion"]) and isset($_POST["nombre_posicion"])){
      $sql = "UPDATE lista_corte_posiciones set cantidad=?, posicion=? where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST['cantidad_posicion'],$_POST['nombre_posicion'],$id_lista_corte_posicion]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "DELETE from lista_corte_procesos WHERE id_lista_corte_posicion = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte_posicion]);
    
    //pasamos los procesos a un nuevo array y le agregamos el id_terminación que lo manejamos como un proceso mas
    $procesos=$_POST["proceso"];
    $procesos[]=$_POST["id_terminacion"];

    if ($modoDebug==1) {
      var_dump($procesos);
    }
    
    foreach ($procesos as $key => $id_proceso) {
      $observaciones="";

      $sql = "INSERT INTO lista_corte_procesos (id_lista_corte_posicion, id_tipo_proceso, id_estado_lista_corte_proceso, observaciones) VALUES (?,?,1,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_lista_corte_posicion,$id_proceso,$observaciones]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }

    $sql = "UPDATE listas_corte_conjuntos set peso = peso + (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['cantidad_posicion'],$_POST['id_material'],$id_lista_corte_conjunto]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
      
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de posición ID #$id_lista_corte_posicion en conjunto de lista de corte','Listas de Corte','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
      
    if ($modoDebug==1) {
      $pdo->rollBack();
      die();
    } else {
      Database::disconnect();
      header("Location: nuevaListaCorteDetallees.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto);
      /*if (!empty($_POST['btn2'])) {
        header("Location: nuevoConjuntoListaCorte.php?id_lista_corte=".$id_lista_corte);
      } else {
        header("Location: nuevaListaCorteDetallees.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto);
      }*/
    }

  }else{
    //insertar posicion
      
    if (!empty($_POST['id_componente_concepto'])) {
		$tipoDetalle = 2;
		$detalleBulto = $_POST['id_componente_concepto'];
		$origenBulto = 0; //falta calcular
	} else {
		$tipoDetalle = 1;
		$detalleBulto = $_POST['id_componente_conjunto'];
		$origenBulto = 0; //falta calcular
	}
		
	$sql = "update packing_lists_componentes set cantidad_despachada = cantidad_despachada + ? where id = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['cantidad'],$detalleBulto]);


    $id_bulto=$_GET['id_bulto'];
		
	$sql = "INSERT INTO bultos_detalle (id_bulto, id_tipo_bulto, id_origen_bulto, id_detalle_bulto, cantidad) VALUES (?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$id_bulto,$tipoDetalle,$origenBulto,$detalleBulto,$_POST['cantidad']]);
        
	$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Detalle de Bulto en despacho','Despachos','verDespacho.php?id=$id')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
      
    if ($modoDebug==1) {
      $pdo->rollBack();
      die();
    } else {
      Database::disconnect();
      if (!empty($_POST['btn2'])) {
        $sql = "SELECT id_despacho FROM bultos WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id_bulto]);
        $data = $q->fetch(PDO::FETCH_ASSOC);

        header("Location: nuevoBultoDespacho.php?id_despacho=".$data["id_despacho"]);
      } else {
        header("Location: nuevoBultoDetalleDespacho.php?id_bulto=".$id_bulto);
      }
    }
  }
  
}

//$id_lista_corte_conjunto=$_GET['id_lista_corte_conjunto'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT id_despacho, nombre, numero FROM bultos WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_bulto]);
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
          $ubicacion="Nuevo Detalle de Bulto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Detalle del Bulto <?=$data['nombre']?> #<?=$data['numero']?>
                      &nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(330))) {?>
                        <a href="#" id="link_eliminar_posicion" data-id=""><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                      }
					  ?>
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevoBultoDetalleDespacho.php?id_bulto=<?=$id_bulto?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="form-group col-12">
                          <div class="dt-ext table-responsive">
                            <table class="display" id="dataTables-example667">
                              <thead>
                                <tr>
                                  <th>ID</th>
                                  <th>Tipo</th>
                                  <th>ID Origen</th>
                                  <th>ID Detalle</th>
                                  <th>Cantidad</th>
                                </tr>
                              </thead>
                              <tfoot>
                                <tr>
                                  <th>ID</th>
                                  <th>Tipo</th>
                                  <th>ID Origen</th>
                                  <th>ID Detalle</th>
                                  <th>Cantidad</th>
                                </tr>
                              </tfoot>
                              <tbody><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                $sql = " SELECT bd.id, t.tipo, bd.id_origen_bulto, bd.id_detalle_bulto, bd.cantidad FROM bultos_detalle bd inner join tipos_bulto t on t.id = bd.id_tipo_bulto WHERE bd.id_bulto = ".$id_bulto;
                                foreach ($pdo->query($sql) as $row) {
                                  echo '<tr>';
                                  echo '<td>'. $row["id"] . '</td>';
                                  echo '<td>'. $row["tipo"] . '</td>';
                                  echo '<td>'. $row["id_origen_bulto"] . '</td>';
                                  echo '<td data-id="'.$row["id_detalle_bulto"].'">'. $row["id_detalle_bulto"] . '</td>';
                                  echo '<td>'. $row["cantidad"] . '</td>';
                                  echo '</tr>';
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Conceptos de PL Componentes</label>
                        <div class="col-sm-9">
                          <select name="id_componente_concepto" id="id_componente_concepto" class="js-example-basic-single col-sm-12" autofocus>
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT c.`id`, m.concepto, c.`cantidad`, c.`cantidad_despachada` FROM `packing_lists_componentes` c inner join `materiales` m on m.id = c.`id_concepto` WHERE c.`id_concepto` is not null and c.id_estado_componente_packing_list = 1 ";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'";
                              echo ">".$fila['concepto']." (Solicitado: ".$fila['cantidad']." - Despachado: ".$fila['cantidad_despachada'].")</option>";	
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Conjuntos de PL Componentes</label>
                        <div class="col-sm-9">
                          <select name="id_componente_conjunto" id="id_componente_conjunto" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT c.`id`, lc.nombre, c.`cantidad`, c.cantidad_despachada FROM `packing_lists_componentes` c inner join `listas_corte_conjuntos` lc on lc.id = c.`id_conjunto_lista_corte` WHERE c.`id_conjunto_lista_corte` is not null and lc.id_estado_lista_corte_conjuntos = 4 and c.id_estado_componente_packing_list = 1 ";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'";
							  echo ">".$fila['nombre']." (Solicitado: ".$fila['cantidad']." - Despachado: ".$fila['cantidad_despachada'].")</option>";	
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                        <div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required"></div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" value="1" name="btn1" class="btn btn-success addDetalle">Crear y Agregar otro Detalle</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addDetalle">Crear y volver a Bultos</button>
                        <button type="submit" value="3" name="btn3" id="editDetalle" class="btn btn-primary d-none">Modificar</button>
                        <button type="button" id="cancelEditDetalle" class="btn btn-danger d-none">Cancelar Modificar</button>
                        <a href='nuevoBultoDespacho.php?id_despacho=<?=$data["id_despacho"]?>' class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- Modal para eliminas posiciones -->
        <div class="modal fade" id="eliminarDetalle" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el detalle del bulto?</div>
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

          let id_posicion=t.find("td:first-child").html();
          let nro_revision = t.find("td:nth-child(3)").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#link_modificar_posicion").attr("href","#");
            $("#link_eliminar_posicion").data("id","");
            $("#link_ver_posicion_lc").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            $("#link_modificar_posicion").attr("href","modificarDetalleListaCorte.php?id="+id_posicion);
            $("#link_modificar_posicion").on("click",function(){
              let posicion = t.find("td:nth-child(2)").html();
              let cantidad = t.find("td:nth-child(3)").html();
              let id_material = t.find("td:nth-child(4)").data("id");
              let ancho = t.find("td:nth-child(5)").html();

              $("select[name='id_material']").val(id_material).trigger('change');
              $("select[name='id_material']").val(id_material).trigger('change');
              $("input[name='ancho']").val(ancho).focus()
              $("input[name='largo']").val(largo)
              $("input[name='diametro']").val(diametro)
              $("input[name='marca']").val(marca)
              $("input[name='peso']").val(peso)

              $("#editDetalle").val(id_posicion)
              if($("#editDetalle").hasClass("d-none")){
                $(".addDetalle").toggleClass("d-none")
                $("#editDetalle").toggleClass("d-none")
                $("#cancelEditDetalle").toggleClass("d-none")
              }
            })
            //$("#link_eliminar_posicion").attr("href","eliminarDetalleListaCorte.php?id="+id_posicion);
            $("#link_eliminar_posicion").data("id",id_posicion);
            $("#link_ver_posicion_lc").attr("href","verDetalleConjuntoListaCorte.php?id="+id_posicion);
          }
        });
    
      });

      $("#link_eliminar_posicion").on("click",function(){
        let id_posicion=$(this).data("id")
        if(id_posicion!="" && id_posicion>0){
          let modal=$("#eliminarDetalle")
          modal.modal("show")
          modal.find(".modal-footer a").attr("href","eliminarDetalleBultoDespacho.php?id="+id_posicion)
        }
      });

      $("#cancelEditDetalle").on("click",function(){
        $("input[name='nombre_posicion']").val("").attr("readonly",false)
        $("input[name='cantidad_posicion']").val("").attr("readonly",false)
        $("select[name='id_material']").val("").trigger('change');
        $("input[name='ancho']").val("")
        $("input[name='largo']").val("")
        $("input[name='diametro']").val("")
        $("input[name='marca']").val("")
        $("input[name='peso']").val("")
        $("input[name='proceso[]']").each(function(){
          this.checked=false;
        })
        $("select[name='id_terminacion']").val("").trigger('change');

        $(".addDetalle").toggleClass("d-none")
        $("#editDetalle").toggleClass("d-none")
        $("#editDetalle").val("")
        $("#cancelEditDetalle").toggleClass("d-none")
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