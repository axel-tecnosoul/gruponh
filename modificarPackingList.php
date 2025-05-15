<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$id_packing_list_revision = null;
if (!empty($_GET['id_packing_list_revision'])) {
  $id_packing_list_revision = $_REQUEST['id_packing_list_revision'];
}

if (null==$id_packing_list_revision) {
  header("Location: listarListasCorte.php");
}

if (!empty($_POST)) {

  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_GET);
    var_dump($_FILES);
  }

  $id_packing_list_revision=$_GET['id_packing_list_revision'];
  //$ultimo_nro_revision=$_GET['revision'];

  if(isset($_POST['btn1'])){
    //EN ESTADO ELABORACION MODIFICAMOS LA LISTA DE CORTE

    $sql = "UPDATE packing_lists_revisiones set fecha = ?, id_proyecto = ?, id_estado_packing_list = ?, nombre = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST["fecha"],$_POST["id_proyecto"],$_POST["id_estado_packing_list"],$_POST["nombre"],$id_packing_list_revision]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificación de Lista de Corte','Listas de Corte')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    if($_POST['btn1']=="modificar"){
      
      //chequear si cambia el estado y llevar al listado de listas de corte o permitir modificar conjuntos

      $redirect="nuevaPackingListSecciones.php?id_packing_list_revision=".$id_packing_list_revision;
    }elseif($_POST['btn1']=="revisar"){

      $redirect="revisionPackingList.php?id_packing_list_revision=".$id_packing_list_revision;
    }

  }else{
    //AÑADIMOS UNA REVISION
    $nuevo_nro_revision=$ultimo_nro_revision+1;

    $sql = "UPDATE listas_corte set ultimo_nro_revision = ? where id = ? and ultimo_nro_revision = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$nuevo_nro_revision,$id_lista_corte,$ultimo_nro_revision]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "SELECT lcr.id, lcr.id_lista_corte, lcr.id_proyecto, lcr.fecha, lcr.id_usuario, lcr.id_estado_lista_corte, lcr.anulado, lcr.nombre, lcr.numero, lcr.adjunto, lcr.id_cuenta_realizo, lcr.id_cuenta_reviso, lcr.id_cuenta_valido FROM listas_corte_revisiones lcr WHERE lcr.id_lista_corte = ? AND lcr.nro_revision = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte,$ultimo_nro_revision]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "INSERT INTO listas_corte_revisiones (id_lista_corte, id_proyecto, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero, descripcion, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$data["id_lista_corte"],$data['id_proyecto'],$_POST['fecha'],$data['id_usuario'],$_POST['id_estado_lista_corte'],$nuevo_nro_revision,$data['anulado'],$_POST['nombre'],$_POST['numero'],$_POST['descripcion'],$_POST['id_cuenta_realizo'],$_POST['id_cuenta_reviso'],$_POST['id_cuenta_valido']]);
    $id_packing_list_revision = $pdo->lastInsertId();

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "SELECT lcc.id,lcc.id_lista_corte,lcc.nombre,lcc.cantidad,lcc.peso,lcc.id_estado_lista_corte_conjuntos FROM listas_corte_conjuntos lcc WHERE lcc.id_lista_corte = ".$data['id'];
    foreach ($pdo->query($sql) as $row) {
      $sql = "INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES (?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_packing_list_revision,$row['nombre'],$row['cantidad'],$row['peso'],$row['id_estado_lista_corte_conjuntos']]);
      $id_lista_corte_conjunto = $pdo->lastInsertId();

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      $sql = "SELECT lcp.id,lcp.id_lista_corte_conjunto,lcp.id_material,lcp.posicion,lcp.cantidad,lcp.largo,lcp.ancho,lcp.marca,lcp.peso,lcp.finalizado,lcp.id_colada,lcp.diametro,lcp.calidad FROM lista_corte_posiciones lcp WHERE lcp.id_lista_corte_conjunto = ".$row["id"];
      foreach ($pdo->query($sql) as $row) {
        $sql = "INSERT INTO lista_corte_posiciones (id_lista_corte_conjunto,id_material,posicion,cantidad,largo,ancho,marca,peso,finalizado,id_colada,diametro,calidad) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_lista_corte_conjunto,$row["id_material"],$row["posicion"],$row["cantidad"],$row["largo"],$row["ancho"],$row["marca"],$row["peso"],$row["finalizado"],$row["id_colada"],$row["diametro"],$row["calidad"]]);
        $id_lista_corte_posicion = $pdo->lastInsertId();

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: ".$q->rowCount();
          echo "<br><br>";
        }

        $sql = "SELECT lcp.id_lista_corte_posicion,lcp.id_tipo_proceso,lcp.observaciones,lcp.id_estado_lista_corte_proceso FROM lista_corte_procesos lcp WHERE lcp.id_lista_corte_posicion = ".$row["id"];
        foreach ($pdo->query($sql) as $row) {
          $sql = "INSERT INTO lista_corte_procesos (id_lista_corte_posicion, id_tipo_proceso, observaciones, id_estado_lista_corte_proceso) VALUES (?,?,?,?)";
          $q = $pdo->prepare($sql);
          $q->execute([$id_lista_corte_posicion,$row['id_tipo_proceso'],$row['observaciones'],$row['id_estado_lista_corte_proceso']]);

          if ($modoDebug==1) {
            $q->debugDumpParams();
            echo "<br><br>Afe: ".$q->rowCount();
            echo "<br><br>";
          }
        }
      }
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nueva Revision de Lista de Corte','Listas de Corte')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    $redirect="nuevaPackingListSeccion.php?id_packing_list_revision=".$id_packing_list_revision;
  }

  if ($modoDebug==1) {
    $pdo->rollBack();
    die();
  } else {
    Database::disconnect();
    //header("Location: listarListasCorte.php");
    header("Location: ".$redirect);
  }

} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  //$sql = "SELECT lc.id AS id_lista_corte, lcr.id AS id_packing_list_revision, lcr.id_proyecto, lcr.fecha, lcr.id_usuario, lcr.id_estado_lista_corte, lcr.nro_revision, lcr.anulado, lcr.nombre, lcr.numero, lcr.adjunto, lcr.id_cuenta_realizo, lcr.id_cuenta_reviso, lcr.id_cuenta_valido FROM listas_corte_revisiones lcr INNER JOIN listas_corte lc ON lcr.id_lista_corte=lc.id AND lcr.nro_revision=lc.ultimo_nro_revision WHERE lcr.id = ?";
  $sql = "SELECT * FROM packing_lists_revisiones plr INNER JOIN packing_lists pl ON plr.id_packing_list=pl.id AND plr.nro_revision=pl.ultimo_nro_revision WHERE plr.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_packing_list_revision]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $id_estado_packing_list=$data["id_estado_packing_list"];
  $nro_revision=$data["nro_revision"];
  $accion="revisar";
  if($id_estado_packing_list==1){//PERMITIMOS MODIFICAR LA lc
    $accion="modificar";
  }
  //$id_estado_packing_list=0;

  /*$q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();*/

  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      .titulo{
        margin-bottom: 15px;
      }
    </style>
  </head>
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
          $ubicacion="Modificar Packing List";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="modificarPackingList.php?id_packing_list_revision=<?=$id_packing_list_revision?>" enctype="multipart/form-data">
                    <div class="card-body"><?php
                      if($id_estado_packing_list!=1){?>
                        <div class="row">
                          <div class="col-12 titulo">
                            <h4>Historial de revisiones</h4>
                          </div>
                        </div>
                        <div class="row">
                          <div class="col-12">
                            <table class="table table-bordered mb-3">
                              <thead>
                                <th>Revision</th>
                                <th>Fecha</th>
                                <th>Descripcion</th>
                                <!-- <th>Elaboro</th>
                                <th>Reviso</th>
                                <th>Aprobo</th> -->
                              </thead>
                              <tbody><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                //$b=0;
                                //$sql = " SELECT nro_revision,date_format(fecha,'%d/%m/%y') AS fecha,descripcion,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_realizo=c.id) AS realizo,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_reviso=c.id) AS reviso,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_valido=c.id) AS valido FROM listas_corte_revisiones lcr WHERE lcr.id_packing_list = $data[id_packing_list] ORDER BY nro_revision ASC";
                                $sql = " SELECT nro_revision,date_format(fecha,'%d/%m/%y') AS fecha,descripcion FROM packing_lists_revisiones plr WHERE plr.id_packing_list = $data[id_packing_list] ORDER BY nro_revision ASC";//,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_realizo=c.id) AS realizo,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_reviso=c.id) AS reviso,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_valido=c.id) AS valido
                                foreach ($pdo->query($sql) as $row) {
                                  //$b=1;
                                  echo '<tr>';
                                  echo '<td>'. $row["nro_revision"] . '</td>';
                                  echo '<td>'. $row["fecha"] . '</td>';
                                  echo '<td>'. $row["descripcion"] . '</td>';
                                  /*echo '<td>'. $row["realizo"] . '</td>';
                                  echo '<td>'. $row["reviso"] . '</td>';
                                  echo '<td>'. $row["valido"] . '</td>';*/
                                  echo '</tr>';
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                        </div><?php
                      }?>
                      <div class="row">
                        <div class="col"><?php
                          if($id_estado_packing_list!=1){?>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Revision(*)</label>
                              <div class="col-sm-9"><input name="revision" type="text" maxlength="99" class="form-control" required="required" value="<?=$data['nro_revision']+1?>" readonly></div>
                            </div><?php
                          }?>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha(*)</label>
                            <div class="col-sm-9"><input name="fecha" type="date" autofocus onfocus="this.showPicker()" maxlength="99" class="form-control" required="required" value="<?=$data['fecha']; ?>"></div>
                          </div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nombre PL(*)</label>
                            <div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control" required="required" value="<?=$data['nombre']; ?>"></div>
                          </div><?php
                          if($accion=="modificar"){?>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Proyecto(*)</label>
                              <div class="col-sm-9">
                                <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required="required">
                                  <option value="">Seleccione...</option><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='".$fila['id']."'";
                                    if($fila['id']==$data["id_proyecto"]){
                                      echo "selected";
                                    }
                                    echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
                                  }
                                  Database::disconnect();?>
                                </select>
                              </div>
                            </div><?php
                          }else{?>
                          <input type="hidden" name="id_proyecto" value="<?=$data["id_proyecto"]?>"><?php
                          }?>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Estado(*)</label>
                            <div class="col-sm-9">
                              <select name="id_estado_packing_list" id="id_estado_packing_list" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, estado FROM estados_packing_list WHERE 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id']==$id_estado_packing_list) {
                                    echo " selected ";
                                  }
                                  echo ">".$fila['estado']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
					              <button class="btn btn-primary" value="<?=$accion?>" name="btn1" type="submit">Modificar</button>
                        <a href='listarPackingList.php' class="btn btn-light">Volver</a>
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

    <div class="modal fade" id="modalConjuntos" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
		  <div class="modal-dialog" role="document">
		    <div class="modal-content">
          <form action="modificarConjuntoPackingListSeccion?id_packing_list=<?=$id_packing_list_revision?>&revision=<?=$nro_revision?>" method="POST">
            <div class="modal-header">
              <h5 class="modal-title" id="exampleModalConjuntoLabel">Modificacion de conjunto</h5>
              <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
              <div class="form-group row">
                <input type="hidden" name="id_conjunto">
                <label class="col-sm-3 col-form-label">Nombre(*)</label>
                <div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control" required="required" value=""></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                <div class="col-sm-9"><input name="cantidad" type="number" step="0.01" value="" class="form-control" required="required"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Estado(*)</label>
                <div class="col-sm-9">
                  <select name="id_estado_conjunto" id="id_estado_conjunto" class="js-example-basic-single form-control w-100" required="required">
                    <option value="">Seleccione...</option><?php
                    /*$pdo = Database::connect();
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $sql = " SELECT id, estado FROM estados_packing_list_conjuntos";
                    foreach ($pdo->query($sql) as $row) {?>
                      <option value="<?=$row["id"]?>"><?=$row["estado"]?></option><?php
                    }
                    Database::disconnect();*/?>
                  </select>
                </div>
              </div>
              
            </div>
            <div class="modal-footer">
              <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion">Modificar</button>
              <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Modificar y ver Posiciones</button>
            </div>
          </form>
        </div>
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

        $(".selectOneFromTable").on("click",function(e){
          var nodes = $('#dataTables-example667').DataTable().rows().nodes();
          var selectedRows = $(nodes).filter('.selected');
          console.log(selectedRows);
          if(selectedRows.length!=1){
            e.preventDefault();
          }
          if(selectedRows.length==0){
            alert("Seleccione una fila")
          }
          if(selectedRows.length>1){
            alert("Seleccione solamente una fila")
          }
        })

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
            $("#link_eliminar_conjunto").attr("data-target","#");
            $("#link_nueva_posicion").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            $("#link_ver_conjunto_lc").attr("href","verConjuntoPackingListSeccion?id="+id_conjunto);
            //$("#link_modificar_conjunto").attr("href","nuevaPackingListSeccion.php?id_packing_list="+id_conjunto);
            //$("#link_modificar_conjunto").attr("href","#");
            $("#link_modificar_conjunto").on("click",function(){
              let id_conjunto = t.find("td:nth-child(1)").html();
              let nombre = t.find("td:nth-child(2)").html();
              let cantidad = t.find("td:nth-child(3)").html();
              let id_estado_conjunto = t.find("td:nth-child(6)").html();

              let modal=$("#modalConjuntos")
              modal.modal("show")
              modal.find("input[name='id_conjunto']").val(id_conjunto)
              modal.find("input[name='nombre']").val(nombre)
              modal.find("input[name='cantidad']").val(cantidad)
              modal.find("select[name='id_estado_conjunto']").val(id_estado_conjunto).trigger('change')
            })
            $("#link_eliminar_conjunto").attr("href","eliminarPackingListSeccion.php?id="+id_conjunto);
            $("#link_nueva_posicion").attr("href","nuevoPackingListSeccion.php?id="+id_conjunto);
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
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->

  </body>
</html>