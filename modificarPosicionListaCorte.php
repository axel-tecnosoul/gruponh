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
  header("Location: listarComputos.php");
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

  $sql = "SELECT pos.cantidad, id_material, id_lista_corte_conjunto, id_lista_corte FROM lista_corte_posiciones pos INNER JOIN listas_corte_conjuntos lcc ON pos.id_lista_corte_conjunto=lcc.id WHERE pos.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
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
  $q->execute([$_POST['largo'],$_POST['ancho'],$_POST['marca'],$_POST['peso'],$_POST['diametro'],$id]);

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }

  $sql = "DELETE from lista_corte_procesos WHERE id_lista_corte_posicion = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  
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
    $q->execute([$id,$id_proceso,$observaciones]);

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
		
  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de posición ID #$id en conjunto de lista de corte','Listas de Corte')";
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
      header("Location: nuevoConjuntoListaCorte.php?id_lista_corte=".$id_lista_corte);
    } else {
      header("Location: nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto);
    }
  }
  
}else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT id, id_lista_corte_conjunto, posicion, id_material, posicion, cantidad, largo, ancho, marca, peso, finalizado, id_colada, diametro, calidad FROM lista_corte_posiciones WHERE id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  Database::disconnect();
}

//$id_lista_corte_conjunto=$_GET['id_lista_corte_conjunto'];
/*$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT pos.id, pos.posicion, pos.cantidad, pos.ancho, pos.largo, pos.diametro, pos.marca, pos.peso, pos.finalizado, con.nombre, con.id AS id_lista_corte_conjunto FROM lista_corte_posiciones pos inner join listas_corte_conjuntos con on con.id = pos.id_lista_corte_conjunto WHERE pos.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
Database::disconnect();*/
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
          $ubicacion="Modificar Posicion";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="modificarPosicionListaCorte.php?id=<?=$id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="form-group col-3">
                          <label>Posicion(*): <?=$data['posicion']?></label>
                        </div>
                        <div class="form-group col-3">
                          <label>Cantidad(*): <?=$data['cantidad']?></label>
                          <!-- <input name="cantidad_posicion" type="number" step="0.01" maxlength="99" class="form-control cantidad_posicion" required="required" value="<?=$data['cantidad']?>"> -->
                        </div>
                        <div class="form-group col-6">
                          <label>Concepto(*)</label><br>
                          <select name="id_material" class="js-example-basic-single id_material" autofocus required="required">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT m.id, m.codigo, m.concepto, cd.reservado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id where cd.cancelado = 0 and lcc.id = ".$data["id_lista_corte_conjunto"];
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              //if ($fila['reservado'] > 0) {
                                echo "<option value='".$fila['id']."'";
                                if($data["id_material"]==$fila['id']){
                                  echo "selected";
                                }
                                echo ">".$fila['concepto']." (".$fila['codigo'].") - Reservado: ".$fila['reservado']."</option>";
                              //}
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="row">
                        <div class="form-group col-2">
                          <label>Ancho</label>
                          <input name="ancho" type="number" step="0.01" maxlength="99" class="form-control ancho" value="<?=$data["ancho"]?>">
                        </div>
                        <div class="form-group col-2">
                          <label>Largo</label>
                          <input name="largo" type="number" step="0.01" maxlength="99" class="form-control largo" value="<?=$data["largo"]?>">
                        </div>
                        <div class="form-group col-2">
                          <label>Diametro</label>
                          <input name="diametro" type="number" step="0.01" maxlength="99" class="form-control diametro" value="<?=$data["diametro"]?>">
                        </div>
                        <div class="form-group col-2">
                          <label>Marca</label>
                          <input name="marca" type="text" maxlength="99" class="form-control marca" value="<?=$data["marca"]?>">
                        </div>
                        <div class="form-group col-2">
                          <label>Peso KG</label>
                          <input name="peso" type="number" step="0.01" maxlength="99" class="form-control peso" value="<?=$data["peso"]?>">
                        </div>
                      </div>
                      <div class="row">
                        <div class="form-group col-9">
                          <label>Procesos(*)</label><br><?php
                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                          $sqlZon = "SELECT GROUP_CONCAT(lcp.id_tipo_proceso SEPARATOR ',') AS procesos from lista_corte_procesos lcp INNER JOIN tipos_procesos tp ON lcp.id_tipo_proceso=tp.id WHERE lcp.id_lista_corte_posicion=$id AND LENGTH(tp.tipo)<=2";
                          $q = $pdo->prepare($sqlZon);
                          //echo $sqlZon;
                          $q->execute();
                          $fila = $q->fetch(PDO::FETCH_ASSOC);
                          $aProcesos=explode(",",$fila["procesos"]);
                          
                          
                          $sqlZon = "SELECT id,tipo from tipos_procesos WHERE LENGTH(tipo)<=2";
                          $q = $pdo->prepare($sqlZon);
                          $q->execute();
                          while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                            $checked="";
                            if(in_array($fila['id'],$aProcesos)){
                              $checked="checked";
                            }
                            $for_id="proceso_".$fila['id']?>
                            <div class="custom-control custom-checkbox d-inline-block pr-4">
                              <input type="checkbox" name="proceso[]" class="custom-control-input proceso" id="<?=$for_id?>" value="<?=$fila['id']?>" <?=$checked?>>
                              <label class="custom-control-label" for="<?=$for_id?>"><?=$fila['tipo']?></label>
                            </div><?php
                          }
                          Database::disconnect();?>
                        </div>
                        <div class="form-group col-3">
                          <label>Terminación(*)</label><br>
                          <select name="id_terminacion" class="js-example-basic-single id_terminacion w-100" required="required">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                            $sqlZon = "SELECT lcp.id_tipo_proceso AS id_terminacion from lista_corte_procesos lcp INNER JOIN tipos_procesos tp ON lcp.id_tipo_proceso=tp.id WHERE lcp.id_lista_corte_posicion=$id AND LENGTH(tp.tipo)>2";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            $fila = $q->fetch(PDO::FETCH_ASSOC);
                            $id_terminacion=(isset($fila["id_terminacion"])) ? $fila["id_terminacion"] : "";

                            $sqlZon = "SELECT id,tipo from tipos_procesos WHERE LENGTH(tipo)>2";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              $selected="";
                              if($id_terminacion==$fila["id"]){
                                $selected="selected";
                              }
                              echo "<option value='".$fila['id']."' ".$selected.">".$fila['tipo']."</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <!-- <button class="btn btn-primary" type="submit">Crear</button>
						            <a href="listarListasCorte.php" class="btn btn-light">Volver</a> -->

                        <button class="btn btn-success" type="submit" value="1" name="btn1">Modificar y volver a Posiciones</button>
                        <button class="btn btn-primary" type="submit" value="2" name="btn2">Modificar e ir a Conjuntos</button>
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
      });
    </script>
  </body>
</html>