<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
if(!empty($_POST)){

  //var_dump($_POST);
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_FILES);
  }

  $sql = "INSERT INTO listas_corte (id_proyecto, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero) VALUES (?,?,?,1,0,0,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST['id_proyecto'],$_POST['fecha'],$_SESSION['user']['id'],$_POST['nombre'],$_POST['numero']]);
  $id_lista_corte = $pdo->lastInsertId();

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }

  $i=0;
  $j=0;
  while(isset($_POST["conjunto".$i])){
    $conjunto=$_POST["conjunto".$i];
    
    if ($modoDebug==1) {
      var_dump($conjunto);
    }
    
    $id_estado_lista_corte_conjunto=1;
    $peso_conjunto=0;
    $sql = "INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES (?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte,$conjunto['nombre'],$conjunto['cantidad'],$peso_conjunto,$id_estado_lista_corte_conjunto]);
    $id_conjunto = $pdo->lastInsertId();

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    //var_dump($conjunto["posicion".$j]);
    if($j!=0){
      $j--;
    }
    
    while(isset($conjunto["posicion".$j])){
      $posicion=$conjunto["posicion".$j];

      if ($modoDebug==1) {
        var_dump($posicion);
      }
    
      $idColada = null;
      $sql = "SELECT col.id FROM coladas col inner join compras com on com.id = col.id_compra inner join pedidos p on p.id = com.id_pedido inner join computos c on c.id = p.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos pr on pr.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = pr.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id WHERE col.id_material = ? and lcc.id = ? ";
      $q = $pdo->prepare($sql);
      $q->execute([$posicion['id_material'],$id_conjunto]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      if (!empty($data['id'])) {
        $idColada = $data['id'];
      }

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
  
      $sql = "INSERT INTO lista_corte_posiciones (id_lista_corte_conjunto, id_material, posicion, cantidad, largo, ancho, marca, peso, finalizado, id_colada, diametro, calidad) VALUES (?,?,?,?,?,?,?,?,0,?,?,'')";
      $q = $pdo->prepare($sql);
      $q->execute([$id_conjunto,$posicion['id_material'],$posicion['nombre_posicion'],$posicion['cantidad_posicion'],$posicion['largo'],$posicion['ancho'],$posicion['marca'],$posicion['peso'],$idColada,$posicion['diametro']]);
      $id_posicion = $pdo->lastInsertId();

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
      
      $idComputoDetalle = 0;
      $sql = "SELECT cd.id idComputoDetalle from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id where cd.cancelado = 0 and lcc.id = ? and m.id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_conjunto,$posicion['id_material']]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $idComputoDetalle = $data['idComputoDetalle'];

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
  
      $sql = "UPDATE computos_detalle set comprado = comprado + ?, reservado = reservado - ? where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$posicion['cantidad_posicion'],$posicion['cantidad_posicion'],$idComputoDetalle]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      //pasamos los procesos a un nuevo array y le agregamos el id_terminación que lo manejamos como un proceso mas
      $procesos=$posicion["proceso"];
      $procesos[]=$posicion["id_terminacion"];

      if ($modoDebug==1) {
        var_dump($procesos);
      }
      
      foreach ($procesos as $key => $id_proceso) {
        $observaciones="";

        $sql = "INSERT INTO lista_corte_procesos (id_lista_corte_posicion, id_tipo_proceso, id_estado_lista_corte_proceso, observaciones) VALUES (?,?,1,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_posicion,$id_proceso,$observaciones]);

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: ".$q->rowCount();
          echo "<br><br>";
        }
      }

      $j++;
    }
    $i++;
  }

  /*if (!empty($_FILES['adjunto']['name'])) {
    $filename = $_FILES['adjunto']['name'];
    move_uploaded_file($_FILES['adjunto']['tmp_name'],'adjuntos_lc/'.$id.'_'.$filename);
      
    $sql = "UPDATE listas_corte set adjunto = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute(array($id.'_'.$filename,$id));
  }*/

  /*$sql = "INSERT INTO listas_corte_revisiones(id_lista_corte, nro_revision, comentarios, fecha_hora) VALUES (?,0,'Emisión original',now())";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);*/

  $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva Lista de Corte','Listas de Corte','')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));


  //Database::disconnect();
  //header("Location: listarListasCorte.php");
  if ($modoDebug==1) {
    $pdo->rollBack();
    die();
  } else {
    Database::disconnect();
    header("Location: listarListasCorte.php");
  }
}
$fecha=date("Y-m-d");
$numero=1;
$nombre="Probando lista de corte";
$id_proyecto=1;
$nombre_conjunto="Conjunto 1";
$cantidad_conjunto="5";
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      #nuevo_conjunto:hover{
        cursor: pointer;
      }
      table th,td{
        border: 1px solid gray;
        /* border-collapse: collapse; */
      }
      .card-conjunto{
        /*background-color: lightblue;*/
        background-color: #94c021bf;
      }
      .card-posicion{
        /*background-color: lightcoral;*/
        background-color: #ff7f39bf;
      }
      .card-conjunto .card-header, .card-posicion .card-header, .card-posicion .card-body, .card-conjunto .card-body{
        padding: 10px;
        margin-top: 10px;
        border-radius: 8px;
        border-bottom: none;
      }
      .card-conjunto .card-header, .card-conjunto .card-body .btn, .card-conjunto .conjuntos-table th{
        /*background-color: cornflowerblue;
        border-color: cornflowerblue;*/
        background-color: darkgray;
        border-color: darkgray;
      }
      .card-posicion .card-header, .card-posicion .card-body .btn, .card-posicion .posiciones-table th{
        /*background-color: indianred;
        border-color: indianred;*/
        background-color: darkgray;
        border-color: darkgray;
      }
      .card-conjunto .conjuntos-table, .card-posicion .posiciones-table{
        margin-bottom: 20px;
      }
      .select2-container {
        /*width: 100%;*/
        display: block;
      }
      /*inicia borrar*/
      /*.page-body{margin-left: 0 !important;}
      .page-sidebar,.page-main-header{display: none !important;}*/

      /*termina borrar */
    </style>
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
          $ubicacion="Nueva Lista de Corte";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <form class="form theme-form" id="form_lista_corte" role="form" method="POST" action="demo4.php" enctype="multipart/form-data">
                  <!-- nuevaListaCorte -->
                  <div class="card" id="lista_de_corte">
                    <div class="card-header">
                      <h5><?=$ubicacion?></h5>
                    </div>
                    <div class="card-body">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha(*)</label>
                        <div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" autofocus class="form-control" required="required" value="<?=$fecha?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Número(*)</label>
                        <div class="col-sm-9"><input name="numero" type="text" maxlength="99" class="form-control" required="required" value="<?=$numero?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Nombre de la LC(*)</label>
                        <div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control" required="required" value="<?=$nombre?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Proyecto(*)</label>
                        <div class="col-sm-9">
                          <select name="id_proyecto" id="id_proyecto" class="form-control js-example-basic-single col-sm-12" required="required">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT p.id, s.nombre, p.descripcion FROM proyectos p inner join sitios s on s.id = p.id_sitio WHERE p.`anulado` = 0";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'";
                              echo ">".$fila['nombre']." / ".$fila['descripcion']."</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Adjuntar Plano</label>
                        <div class="col-sm-9"><input name="adjunto" type="file" value="" class="form-control"></div>
                      </div>
                      <div class="row">

                        <!-- INICIA CARD DE CONJUNTOS -->
                        <div class="card col-12 card-conjunto">
                          <div class="card-header">
                            <h5>
                              Conjuntos&nbsp;&nbsp;
                              <span class="botonera_conjunto">
                                <!-- <a href="#" id="link_ver_conjunto_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp; -->
                                <img src="img/icon_alta.png" id="nuevo_conjunto" width="24" height="25" border="0" alt="Nuevo conjunto" title="Nuevo conjunto">&nbsp;&nbsp;<?php
                                if (!empty(tienePermiso(329))) {?>
                                  <a href="#" id="link_modificar_conjunto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp;<?php
                                }
                                if (!empty(tienePermiso(330))) {?>
                                  <a href="#" id="link_eliminar_conjunto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                                }
                                if (!empty(tienePermiso(331))) {?>
                                  <a href="#" id="link_nueva_posicion"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nueva Posición" title="Nueva Posición"></a>&nbsp;&nbsp;<?php
                                }?>
                              </span>
                            </h5>
                          </div>
                          <div class="card-body">

                          <!-- tabla para mostrar los conjutnos agregados -->
                            <table class="table conjuntos-table">
                              <thead>
                                <tr>
                                  <th style="text-align:center;width:50%">Conjunto</th>
                                  <th style="text-align:center;width:50%">Cantidad</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr>
                                  <td colspan="2" style="text-align:center">No se han encontrado registros</td>
                                </tr>
                              </tbody>
                            </table>

                            <!-- contenedor de conjuntos -->
                            <div class="conjunto" style="display: none;">
                              <div class="form-group row">
                                <label class="col-sm-3 col-form-label">Nombre del conjunto(*)</label>
                                <div class="col-sm-9"><input name="conjunto0[nombre]" type="text" maxlength="99" class="form-control nombre_conjunto" required="required" value="<?=$nombre_conjunto?>"></div>
                              </div>
                              <div class="form-group row">
                                <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                                <div class="col-sm-9"><input name="conjunto0[cantidad]" type="text" maxlength="99" class="form-control cantidad_conjunto" required="required" value="<?=$cantidad_conjunto?>"></div>
                              </div>
                              <div class="row">

                                <!-- INICIA CARD DE POSICIONES -->
                                <div class="card col-12 card-posicion">
                                  <div class="card-header">
                                    <h5>Posiciones</h5>
                                  </div>
                                  <div class="card-body">

                                    <!-- table para mostrar las posiciones -->
                                    <table class="table posiciones-table">
                                      <thead>
                                        <tr style="text-align:center">
                                          <th>Posicion</th>
                                          <th>Cantidad</th>
                                          <th>Material</th>
                                          <th>Ancho</th>
                                          <th>Largo</th>
                                          <th>Diametro</th>
                                          <th>Marca</th>
                                          <th>Peso (Kg.)</th>
                                          <th>Procesos</th>
                                        </tr>
                                      </thead>
                                      <tbody>
                                        <tr>
                                          <td colspan="9" style="text-align:center">No se han encontrado registros</td>
                                        </tr>
                                      </tbody>
                                    </table>
                                    
                                    <!-- contenedor de posiones -->
                                    <div class="posicion">
                                      <div class="row">
                                        <div class="form-group col-3">
                                          <label>Nombre de la posicion(*)</label>
                                          <input name="conjunto0[posicion0][nombre_posicion]" type="text" maxlength="99" class="form-control nombre_posicion" required="required" value="">
                                        </div>
                                        <div class="form-group col-3">
                                          <label>Cantidad(*)</label>
                                          <input name="conjunto0[posicion0][cantidad_posicion]" type="text" maxlength="99" class="form-control cantidad_posicion" required="required" value="">
                                        </div>
                                        <div class="form-group col-6">
                                          <label>Concepto(*)</label><br>
                                          <select name="conjunto0[posicion0][id_material]" class="js-example-basic-single id_material" required="required">
                                            <option value="">Seleccione...</option><?php
                                            /*$_GET['id']=1;
                                            $pdo = Database::connect();
                                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                            $sqlZon = "SELECT m.id, m.codigo, m.concepto, cd.reservado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id where lcc.id = ".$_GET['id'];
                                            $q = $pdo->prepare($sqlZon);
                                            $q->execute();
                                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                              if ($fila['reservado'] > 0) {
                                                echo "<option value='".$fila['id']."'";
                                                echo ">".$fila['concepto']." (".$fila['codigo'].") - Reservado: ".$fila['reservado']."</option>";
                                              }
                                            }
                                            Database::disconnect();*/?>
                                          </select>
                                        </div>
                                      </div>
                                      <div class="row">
                                        <div class="form-group col-2">
                                          <label>Ancho</label>
                                          <input name="conjunto0[posicion0][ancho]" type="text" maxlength="99" class="form-control ancho" value="">
                                        </div>
                                        <div class="form-group col-2">
                                          <label>Largo</label>
                                          <input name="conjunto0[posicion0][largo]" type="text" maxlength="99" class="form-control largo" value="">
                                        </div>
                                        <div class="form-group col-2">
                                          <label>Diametro</label>
                                          <input name="conjunto0[posicion0][diametro]" type="text" maxlength="99" class="form-control diametro" value="">
                                        </div>
                                        <div class="form-group col-2">
                                          <label>Marca</label>
                                          <input name="conjunto0[posicion0][marca]" type="text" maxlength="99" class="form-control marca" value="">
                                        </div>
                                        <div class="form-group col-2">
                                          <label>Peso KG</label>
                                          <input name="conjunto0[posicion0][peso]" type="text" maxlength="99" class="form-control peso" value="">
                                        </div>
                                      </div>
                                      <div class="row">
                                        <div class="form-group col-9">
                                          <label>Procesos(*)</label><br><?php
                                          $pdo = Database::connect();
                                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                          $sqlZon = "SELECT id,tipo from tipos_procesos WHERE LENGTH(tipo)<=2";
                                          $q = $pdo->prepare($sqlZon);
                                          $q->execute();
                                          while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                            $id="conjunto0[posicion0][proceso_".$fila['id']."]"?>
                                          <div class="custom-control custom-checkbox d-inline-block pr-4">
                                            <input type="checkbox" name="conjunto0[posicion0][proceso][]" class="custom-control-input proceso" id="<?=$id?>" value="<?=$fila['id']?>">
                                            <label class="custom-control-label" for="<?=$id?>"><?=$fila['tipo']?></label>
                                          </div><?php
                                          }
                                          Database::disconnect();?>
                                        </div>
                                        <div class="form-group col-3">
                                          <label>Terminación(*)</label>
                                          <select name="conjunto0[posicion0][id_terminacion]" class="js-example-basic-single id_terminacion" required="required">
                                            <option value="">Seleccione...</option><?php
                                            $pdo = Database::connect();
                                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                            $sqlZon = "SELECT id,tipo from tipos_procesos WHERE LENGTH(tipo)>2";
                                            $q = $pdo->prepare($sqlZon);
                                            $q->execute();
                                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                              echo "<option value='".$fila['id']."'>".$fila['tipo']."</option>";
                                            }
                                            Database::disconnect();?>
                                          </select>
                                        </div>
                                      </div>
                                    </div>
                                    
                                    <!-- botones para agregar y editar posiciones -->
                                    <button type="button" class="btn add-posicion-btn" style="display: none;">Agregar otra posicion</button>
                                    <button type="button" class="btn cancel-add-posicion-btn" style="display: none;">Cancelar</button>
                                    <button type="button" class="btn edit-posicion-btn" style="display: none;">Editar posicion</button>

                                  </div>
                                </div>
                                <!-- FIN CARD POSICIONES -->
                                
                              </div>
                            </div>
                            
                            <!-- botones para agregar y editar conjuntos -->
                            <button type="button" class="btn" id="add-conjunto-btn" style="display: none;">Agregar otro conjunto</button>
                            <button type="button" class="btn" id="cancel-add-conjunto-btn" style="display: none;">Cancelar</button>
                            <button type="button" class="btn" id="edit-conjunto-btn" style="display: none;">Editar conjunto</button>

                          </div>
                        </div>
                        <!-- FIN CARD CONJUNTOS -->
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
                        <a href="listarListasCorte.php" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
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

    <script type="text/javascript">
      window.addEventListener('load', function() {
        
        // cargar select de provincias al seleccionar un país
        $('#id_proyecto').on('select2:select', function(e) {
          var id_proyecto = e.params.data.id; // obtener valor seleccionado
          // hacer petición ajax con el valor seleccionado como parámetro
          $.ajax({
            url: 'get_conceptos_proyecto.php',
            type: 'POST',
            data: { id_proyecto: id_proyecto },
            dataType: 'json',
            success: function(data) {
              console.log(data);
              // limpiar select de provincias y agregar opciones
              $('.id_material').empty();
              $('.id_material').append($('<option></option>').attr('value', 0).text("Seleccione..."));
              $.each(data, function(key, value) {
                /*console.log(key);
                console.log(value);*/
                $('.id_material').append($('<option></option>').attr('value', value.id).text(value.nombre));
              });
            }
          });
        });

        document.getElementById("nuevo_conjunto").addEventListener("click", () => {
          const conjuntoFieldset = document.querySelector(".conjunto:last-of-type");
          conjuntoFieldset.style.display = 'block';
          document.querySelector('.conjuntos-table').style.display = 'none';
          document.querySelector('.botonera_conjunto').style.display = 'none';
          document.querySelector('#add-conjunto-btn').style.display = 'inline-block';
          document.querySelector('#cancel-add-conjunto-btn').style.display = 'inline-block';
        })

        document.getElementById("cancel-add-conjunto-btn").addEventListener("click", () => {
          const conjuntoFieldset = document.querySelector(".conjunto:last-of-type");
          conjuntoFieldset.style.display = 'none';
          document.querySelector('.conjuntos-table').style.display = 'table';
          document.querySelector('.botonera_conjunto').style.display = 'inline-block';
          document.querySelector('#add-conjunto-btn').style.display = 'none';
          document.querySelector('#cancel-add-conjunto-btn').style.display = 'none';
        })

        //let form=document.getElementById("form_lista_corte")
        //console.log(form.elements);
        const lista_de_corte = document.getElementById("lista_de_corte");
        const addConjuntoBtn = document.getElementById("add-conjunto-btn");
        const campos_posicion=["nombre_posicion","cantidad_posicion","id_material","ancho","largo","diametro","marca","peso","proceso","id_terminacion"]

        addConjuntoBtn.addEventListener("click", () => {
          // OBTENEMOS EL ULTIMO CONJUNTO
          const conjuntoFieldset = document.querySelector(".conjunto:last-of-type");
          //console.log(conjuntoFieldset);
          // CLONAMOS EL ULTIMO CONJUNTO
          const newConjuntoFieldset = conjuntoFieldset.cloneNode(true);
          // LUEGO DE CLONARLO OCULTAMOS EL ULTIMO CONJUNTO
          conjuntoFieldset.style.display = 'none';
          // OBTENEMOS TODOS LOS INPUTS DEL NUEVO CONJUNTO PARA LIMPIARLOS
          const inputs = newConjuntoFieldset.querySelectorAll("input");

          // ELIMINAMOS LAS POSICIONES DEL CONJUNTO CLONADO
          const posiciones = newConjuntoFieldset.querySelectorAll(".posicion");
          posiciones.forEach((posicion, index) => {
            if(posicion.style.display=="none"){
              posicion.remove()
            }
          });

          // ELIMINAMOS LAS POSICIONES CARGADAS EN LA TABLA
          newConjuntoFieldset.querySelectorAll("table tbody tr").forEach((row) => {
            row.remove();
          });

          // LIMPIA LOS VALORES DE LOS CAMPOS DE TEXTO DEL NUEVO CONJUNTO
          inputs.forEach((input, index) => {
            if(input.type=="checkbox"){
              input.checked=false
            }else{
              input.value = "";
            }
          });

          // AGREGA EL NUEVO CONJUNTO DE CAMPOS DE TEXTO AL FORMULARIO
          lista_de_corte.querySelector(".card-conjunto .card-body").insertBefore(newConjuntoFieldset, addConjuntoBtn);
          
          // OBTENEMOS LA TABLA DE CONJUNTOS
          const conjuntosTableBody = document.querySelector('.conjuntos-table tbody');
          // INSERTAMOS UNA NUEVA FIJA
          const newConjuntoRow = conjuntosTableBody.insertRow();

          // OBTENEMOS EL NOMBRE DEL CONJUNTO
          const conjuntoName = conjuntoFieldset.querySelector('.nombre_conjunto').value;
          // INSERTAMOS UNA NUEVA CELDA
          const nameCell = newConjuntoRow.insertCell();
          // AGREGAMOS EL NOMBRE DEL CONJUNTO A LA NUEVA CELDA
          nameCell.textContent = conjuntoName;

          // OBTENEMOS LA CANTIDAD DE CONJUNTOS
          const conjuntoPopulation = conjuntoFieldset.querySelector('.cantidad_conjunto').value;
          // INSERTAMOS UNA NUEVA CELDA
          const populationCell = newConjuntoRow.insertCell();
          // AGREGAMOS LA CANDIDAD DE CONJUNTOS A LA NUEVA CELDA
          populationCell.textContent = conjuntoPopulation;

          const campos_conjunto = ["nombre_conjunto","cantidad_conjunto"].concat(campos_posicion);

          campos_conjunto.forEach(campo => {
            // ELEMENTOS DEL FIELDSET YA COMPLETADO
            let elemNew = newConjuntoFieldset.querySelectorAll('.'+campo);
            let posicionName
            //manejamos campos de texto y select

            if(campo=="proceso"){
              elemNew.forEach(proceso => {
                //console.log(proceso.name);
                const numConjunto = Number(proceso.name.match(/conjunto(\d+)/)[1])
                const newNumConjunto = numConjunto+1;
                //console.log("numConjunto: "+numConjunto);
                //console.log("newNumConjunto: "+newNumConjunto);
                newName=proceso.name.replace("conjunto"+numConjunto, "conjunto"+newNumConjunto);
                newId=proceso.id.replace("conjunto"+numConjunto, "conjunto"+newNumConjunto);
                proceso.name=newName;
                let label=newConjuntoFieldset.querySelector('label[for="'+proceso.id+'"]')
                //let newId=exNewId[0]+"-"+newIndex
                label.setAttribute("for", newId)
                proceso.id=newId
              });
            }else{
              elemNew=elemNew[0]
              //console.log(elemNew.name);
              const numConjunto = Number(elemNew.name.match(/conjunto(\d+)/)[1])
              const newNumConjunto = numConjunto+1;
              //console.log(numConjunto);
              newName=elemNew.name.replace("conjunto"+numConjunto, "conjunto"+newNumConjunto);
              elemNew.name=newName;
            }
          });

          // AÑADE UN EVENTO 'click' A CADA FILA DE LA TABLA PARA EDITAR SUS VALORES
          const rows = document.querySelectorAll(".conjuntos-table tbody tr");
          rows.forEach((row, index) => {
            row.addEventListener("click", () => {
              // OCULTAMOS TODOS LOS FIELDSET DE CONJUNTOS
              document.querySelectorAll(".conjunto").forEach((fieldset) => {
                fieldset.style.display = "none";
              });
              // MOSTRAMOS EL FIELDSET CORRESPONDIENTE AL INDICE DE LA FILA CLICKEADA
              document.querySelectorAll(".conjunto")[index].style.display = "block";
              // MOSTRAMOS EL BOTON DE EDITAR CONJUNTO
              document.querySelector("#edit-conjunto-btn").style.display = "block";
            });

          });

          // AGREGAMOS UN EVENTO 'click' AL BOTON DE AÑADIR POSICION
          newConjuntoFieldset.querySelector(".add-posicion-btn").addEventListener("click", function() {
            // MANEJAMOS LAS POSICIONES DEL NUEVO CONJUNTO AGREGADO
            handlePosicion(newConjuntoFieldset);
          });
          // HACEMOS FOCO EN EL PRIMER INPUT DEL FIELDSET
          inputs[0].focus()

        });

        // AGREGAMOS UN EVENTO 'click' AL BOTON DE EDITAR CONJUNTO
        document.querySelector("#edit-conjunto-btn").addEventListener("click", () => {
          // OBTENEMOS TODOS LOS CONJUNTOS Y LOS RECORREMOS
          const fieldsetConjuntos = document.querySelectorAll(".conjunto");
          fieldsetConjuntos.forEach((row, index) => {
            // SI ESTÁ VISIBLE NOS OCUPAMOS DE ÉL
            if(row.style.display=="block"){
              // OBTENEMOS LA FILA CORRESPONDIENTE DE LA TABLA
              const celdas=document.querySelectorAll(".conjuntos-table tbody tr")[index].querySelectorAll("td")
              // MODIFICAMOS LOS VALORES DE LAS CELDAS
              celdas[0].textContent=row.querySelector(".nombre_conjunto").value
              celdas[1].textContent=row.querySelector(".cantidad_conjunto").value
            }
          })
        });


        const conjuntos = document.querySelector(".conjunto:last-of-type")
        const addPosicionBtn = conjuntos.querySelector(".add-posicion-btn");
        //addPosicionBtn.addEventListener("click", handlePosicion(conjuntos));
        addPosicionBtn.addEventListener("click", function() {
          handlePosicion(conjuntos);
        });


        function handlePosicion(conjunto){
          //console.log(conjunto);
          const posicionFieldset = conjunto.querySelector(".posicion:last-of-type");
          let validado=1
          campos_posicion.forEach(campo => {
            let elem = posicionFieldset.querySelector('.'+campo);
            if(!elem.checkValidity()){
              validado=0
            }
          });
          if(validado==0){
            document.getElementById("form_lista_corte").reportValidity();
            //return false;//comentamos temporalmente para desarrollar
          }

          let checked_procesos=0
          posicionFieldset.querySelectorAll('.proceso').forEach(proceso => {
            if(proceso.checked){
              checked_procesos=1
            }
          });
          if(checked_procesos==0){
            //alert("Seleccione al menos un proceso")
            //return false;//comentamos temporalmente para desarrollar
          }

          //console.log(posicionFieldset);
          const newPosicionFieldset = posicionFieldset.cloneNode(true);
          //console.log(newPosicionFieldset);
          posicionFieldset.style.display = 'none';

          //const inputs = newPosicionFieldset.querySelectorAll("input");
          // Limpia los valores de los campos de texto de la nueva posicion
          /*inputs.forEach((input, index) => {
            input.value = "";
          });*/

          const addPosicionBtn = conjunto.querySelector(".add-posicion-btn");

          // Agrega el nuevo conjunto de campos de texto al formulario
          /*console.log(conjunto);
          console.log(conjunto.querySelector(".card .card-body"));
          console.log(newPosicionFieldset);
          console.log(addPosicionBtn);*/
          conjunto.querySelector(".card .card-body").insertBefore(newPosicionFieldset, addPosicionBtn);

          const posicionesTableBody = conjunto.querySelector('.posiciones-table tbody');
          const newPosicionRow = posicionesTableBody.insertRow();

          let lblProceso=[]
          campos_posicion.forEach(campo => {
            // ELEMENTOS DEL FIELDSET YA COMPLETADO
            let elemOld = posicionFieldset.querySelectorAll('.'+campo);
            let elemNew = newPosicionFieldset.querySelectorAll('.'+campo);
            let posicionName
            //manejamos campos de texto y select
            //if(elemOld.length==1 && campo!="id_terminacion"){
            if(elemOld.length==1){
              elemOld=elemOld[0]
              elemNew=elemNew[0]
              if(elemOld.type=="select-one"){
                posicionName = elemOld.options[elemOld.selectedIndex].text
                // RESETEAMOS EL NUEVO ELEMENTO
                elemNew.value=""
              
                if(campo=="id_terminacion"){
                  //OBTENEMOS LA TERMINACION DADA AL MATERIAL Y LA CONCATENAMOS CON LOS PROCESOS
                  let id_terminacion=posicionFieldset.querySelector("."+campo)
                  //if(id_terminacion.selectedIndex!=0){
                  if(posicionName!="Seleccione..."){
                    //lblProceso.push(id_terminacion.options[id_terminacion.selectedIndex].text)
                    lblProceso.push(posicionName)
                    //console.log(id_terminacion);
                  }
                  // RESETEAMOS EL SELECT DEL NUEVO FIELDSET
                  //newPosicionFieldset.querySelector('.'+campo).value=0

                  posicionName = lblProceso.join(",");
                }
              }else{
                posicionName = elemOld.value
                // VACIAMOS EL NUEVO ELEMENTO
                console.log(elemNew);
                console.log(elemNew.type);
                elemNew.value=""
              };

            }else{
              //manejamos los checkbox y el campo "terminacion"
              posicionName=""
              if(campo=="proceso"){
                // RECORREMOS LOS PROCESOS
                elemOld.forEach(proceso => {
                  if(proceso.checked){
                    // SI ESTÁN CHEQCKEADOS OBTENEMOS SU LABEL Y LOS CONCATENAMOS PARA MOSTRARLO EN LA TABLA
                    lblProceso.push(proceso.parentNode.querySelector("label").textContent)
                  }
                });

                // DESCHECKEAMOS LOS CHECKBOX DEL NUEVO FIELDSET
                elemNew.forEach(procesoNew => {
                  procesoNew.checked=false;
                });
              }
            }

            if(campo=="proceso"){
              elemNew.forEach(proceso => {
                /*let exNew=proceso.name.split("-")
                let oldIndex=parseInt(exNew[1])
                let newIndex=oldIndex+1

                let exNewId=proceso.id.split("-")
                let label=newPosicionFieldset.querySelector('label[for="'+proceso.id+'"]')
                let newId=exNewId[0]+"-"+newIndex
                label.setAttribute("for", newId)
                proceso.id=newId

                let newName=exNew[0]+"-"+newIndex
                if(exNew[2]!=undefined){
                  newName+="-"+exNew[2]
                }
                proceso.name=newName;*/
                
                console.log(proceso.name);
                const numPosicion = Number(proceso.name.match(/posicion(\d+)/)[1])
                const newNumPosicion = numPosicion+1;
                console.log("numPosicion: "+numPosicion);
                console.log("newNumPosicion: "+newNumPosicion);
                newName=proceso.name.replace("posicion"+numPosicion, "posicion"+newNumPosicion);
                newId=proceso.id.replace("posicion"+numPosicion, "posicion"+newNumPosicion);
                proceso.name=newName;
                let label=newPosicionFieldset.querySelector('label[for="'+proceso.id+'"]')
                //let newId=exNewId[0]+"-"+newIndex
                label.setAttribute("for", newId)
                proceso.id=newId
              });
            }else{
              //const numProvincia = Number(nombreInput.match(/\[provincia-(\d+)\]/)[1]) + 1;
              /*let exOld=elemOld.name.split("-")
              let exNew=elemNew.name.split("-")
              let newIndex=parseInt(exOld[1])+1
              console.log(newIndex);
              let newName=exNew[0]+"-"+newIndex
              if(exNew[2]!=undefined){
                newName+="-"+exNew[2]
              }*/
              console.log(elemNew.name);
              const numPosicion = Number(elemNew.name.match(/posicion(\d+)/)[1])
              const newNumPosicion = numPosicion+1;
              console.log(numPosicion);
              newName=elemNew.name.replace("posicion"+numPosicion, "posicion"+newNumPosicion);
              elemNew.name=newName;
            }
            // HACEMOS UNA EXCEPCION CON LA TERMINACION DEBIDO A QUE SE MANEJA JUNTO A LOS PROCESOS, DE ESTA FORMA NO AGREGA UNA CELDA DE MAS EN LA TABLA
            if(campo!="proceso"){
              // MOSTRAMOS LOS DATOS DEL VIEJO FIELDSET EN LA TABLA
              let nameCell = newPosicionRow.insertCell();
              nameCell.textContent = posicionName;
            }
          });

          /*const posicionName = posicionFieldset.querySelector('.nombre_posicion').value;
          const nameCell = newPosicionRow.insertCell();
          nameCell.textContent = posicionName;

          const posicionPopulation = posicionFieldset.querySelector('.cantidad_posicion').value;
          const populationCell = newPosicionRow.insertCell();
          populationCell.textContent = posicionPopulation;*/

          // Añade un evento 'click' a cada fila de la tabla
          const rows = conjunto.querySelectorAll(".posiciones-table tbody tr");
          rows.forEach((row, index) => {
            row.addEventListener("click", () => {
              // Oculta todos los fieldset de las posiciones
              conjunto.querySelectorAll(".posicion").forEach((fieldset) => {
                fieldset.style.display = "none";
              });
              // Muestra el fieldset correspondiente al índice de la fila
              conjunto.querySelectorAll(".posicion")[index].style.display = "block";
              conjunto.querySelector(".edit-posicion-btn").style.display = "block";
            });

          });

          conjunto.querySelector(".edit-posicion-btn").addEventListener("click", () => {
            const fieldsetConjuntos = conjunto.querySelectorAll(".posicion");
            fieldsetConjuntos.forEach((row, index) => {
              if(row.style.display=="block"){
                const celdas=conjunto.querySelectorAll(".posiciones-table tbody tr")[index].querySelectorAll("td")
                celdas[0].textContent=row.querySelector(".nombre_posicion").value
                celdas[1].textContent=row.querySelector(".cantidad_posicion").value
              }
            })
          });

          //hacemos foto en el primer input del fieldset
          const inputs = newPosicionFieldset.querySelectorAll("input");
          inputs[0].focus()
        }

      })

    </script>
  </body>
</html>