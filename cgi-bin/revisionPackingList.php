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

  if($modoDebug==1){
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_GET);
  }

  $nuevo_nro_revision=$_POST["revision"];
  $id_packing_list_revision=$_GET["id_packing_list_revision"];

  $sql = "SELECT id_packing_list,nro_revision FROM packing_lists_revisiones WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_packing_list_revision]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }

  if ($modoDebug==1) {
    echo $data["nro_revision"]."==".$nuevo_nro_revision."<br>";
    echo "data[nro_revision]==nuevo_nro_revision<br>";
  }

  if($data["nro_revision"]==$nuevo_nro_revision){
    //modificamos los datos de la revision
    if ($modoDebug==1) {
      echo "MODIFICAMOS LA REVISION<br><br>";
    }

    $redirect="revisionPackingList.php?id_packing_list=".$data["id_packing_list"]."&nro_revision=".$nuevo_nro_revision;

    $sql = "UPDATE packing_lists_revisiones SET fecha = ?, descripcion = ?, id_cuenta_realizo = ?, id_cuenta_reviso = ?, id_cuenta_valido = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['fecha'],$_POST['descripcion'],$_POST['id_cuenta_realizo'],$_POST['id_cuenta_reviso'],$_POST['id_cuenta_valido'],$id_packing_list_revision]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
    
    if($_POST["idSeccion"]>0){
      
      $id_packing_list_seccion=$_POST['idSeccion'];
      //modificamos la seccion
      $sql = "UPDATE packing_lists_secciones SET cantidad = ?, observaciones = ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST["cantidad_seccion"],$_POST["observaciones_seccion"],$id_packing_list_seccion]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Seccion en Revision de Packing List','Packing List')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));

    }elseif($_POST["cantidad_seccion"]!="" and $_POST["observaciones_seccion"]!=""){
      //creamos una nueva
      //INSERTAMOS LA SECCION AL PACKING LIST
      $sql = "INSERT INTO packing_lists_secciones (id_packing_list_revision, cantidad, observaciones) VALUES (?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_packing_list_revision,$_POST["cantidad_seccion"],$_POST["observaciones_seccion"]]);
      $id_packing_list_seccion = $pdo->lastInsertId();

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nuevo Seccion en Revision de Packing List','Packing List')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));
    }

    if($_POST["id_componente"]>0){
      //modificamos la posicion

      $id_packing_list_componente=$_POST['id_componente'];

      $sql = "UPDATE packing_lists_componentes set id_conjunto_lista_corte=?, id_concepto=?, cantidad=?, observaciones=? where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST['id_conjunto_lista_corte'],$_POST['id_concepto'],$_POST['cantidad_componente'],$_POST['observaciones_componente'],$id_packing_list_componente]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      $sql="SELECT cd.id AS idComputoDetalle FROM packing_lists_componentes plc INNER JOIN packing_lists_secciones pls ON plc.id_packing_list_seccion=pls.id INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id INNER JOIN proyectos p ON plr.id_proyecto=p.id INNER JOIN tareas t ON t.id_proyecto=p.id INNER JOIN computos c ON c.id_tarea=t.id INNER JOIN computos_detalle cd ON cd.id_computo=c.id WHERE plc.id = ? AND cd.cancelado = 0 and cd.id_material = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_packing_list_componente,$_POST['id_concepto']]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $idComputoDetalle = $data['idComputoDetalle'];

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      if($idComputoDetalle>0){
        $sql = "UPDATE packing_lists_componentes SET id_computo_detalle = ? WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute(array($idComputoDetalle,$id_packing_list_componente));

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: ".$q->rowCount();
          echo "<br><br>";
        }
      }

    }elseif($_POST["cantidad_componente"]!="" and $_POST["observaciones_componente"]!=""){
      //INSERTAMOS LA POSICION DEL CONJUNTO
      $idComputoDetalle = 0;

      $sql = "INSERT INTO packing_lists_componentes (id_packing_list_seccion, id_conjunto_lista_corte, id_concepto, cantidad, id_computo_detalle, observaciones, id_estado_componente_packing_list) VALUES (?,?,?,?,?,?,1)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_packing_list_seccion,$_POST['id_conjunto_lista_corte'],$_POST['id_concepto'],$_POST['cantidad_componente'], $idComputoDetalle,$_POST['observaciones_componente']]);
      $id_componente = $pdo->lastInsertId();

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      //$sql = "SELECT cd.id idComputoDetalle from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join packing_lists_revisiones plr on plr.id_proyecto = p.id inner join packing_lists_secciones pls on pls.id_packing_list_revision = plr.id where pls.id = ? and m.id = ?";
      $sql="SELECT cd.id AS idComputoDetalle FROM packing_lists_componentes plc INNER JOIN packing_lists_secciones pls ON plc.id_packing_list_seccion=pls.id INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id INNER JOIN proyectos p ON plr.id_proyecto=p.id INNER JOIN tareas t ON t.id_proyecto=p.id INNER JOIN computos c ON c.id_tarea=t.id INNER JOIN computos_detalle cd ON cd.id_computo=c.id WHERE plc.id = ? AND cd.cancelado = 0 and cd.id_material = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_componente,$_POST['id_concepto']]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $idComputoDetalle = $data['idComputoDetalle'];

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      if($idComputoDetalle>0){
        $sql = "UPDATE packing_lists_componentes SET id_computo_detalle = ? WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute(array($idComputoDetalle,$id_componente));

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: ".$q->rowCount();
          echo "<br><br>";
        }
      }

      $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nuevo Componente en Revision de Packing List','Packing List')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));

    }

  }else{

    $redirect="revisionPackingList.php?id_packing_list=".$data["id_packing_list"]."&nro_revision=".$nuevo_nro_revision;

    //creamos una nueva revision
    if ($modoDebug==1) {
      echo "CREAMOS UNA NUEVA REVISION<br><br>";
    }

    $id_packing_list=$data["id_packing_list"];
    $ultimo_nro_revision=$data["nro_revision"];

    $sql = "UPDATE packing_lists set ultimo_nro_revision = ? where id = ? and ultimo_nro_revision = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$nuevo_nro_revision,$id_packing_list,$ultimo_nro_revision]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "SELECT plr.id, plr.id_packing_list, plr.id_proyecto, plr.fecha, plr.id_usuario, plr.id_estado_packing_list, plr.anulado, plr.id_cuenta_realizo, plr.id_cuenta_reviso, plr.id_cuenta_valido, plr.numero FROM packing_lists_revisiones plr WHERE plr.id_packing_list = ? AND plr.nro_revision = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_packing_list,$ultimo_nro_revision]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "INSERT INTO packing_lists_revisiones (id_packing_list, id_proyecto, fecha, id_usuario, id_estado_packing_list, nro_revision, anulado, descripcion, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido,numero) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$data["id_packing_list"],$data['id_proyecto'],$_POST['fecha'],$data['id_usuario'],$data['id_estado_packing_list'],$nuevo_nro_revision,$data['anulado'],$_POST['descripcion'],$_POST['id_cuenta_realizo'],$_POST['id_cuenta_reviso'],$_POST['id_cuenta_valido'],$data['numero']]);
    $id_packing_list_revision = $pdo->lastInsertId();

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    //OBTENEMOS LAS SECCIONES DEL PACKING LIST
    $sql = "SELECT pls.id,pls.id_packing_list_revision,pls.cantidad,pls.observaciones FROM packing_lists_secciones pls WHERE pls.id_packing_list_revision = ".$data['id'];
    foreach ($pdo->query($sql) as $row) {
      
      $observacionesSeccion=$row['observaciones'];
      $cantidadConjunto=$row['cantidad'];

      if ($modoDebug==1) {
        echo "observacionesSeccion: $observacionesSeccion<br>";
        echo "cantidadConjunto: $cantidadConjunto<br><br>";
      }

      if($_POST["idSeccion"]==$row['id']){
        //modificamos el conjunto
        $observacionesSeccion=$_POST['observaciones_seccion'];
        $cantidadConjunto=$_POST['cantidad_seccion'];

        if ($modoDebug==1) {
          echo "idSeccion ENCONTRADO, MODIFICAMOS LOS DATOS<br>";
          echo "observacionesSeccion: $observacionesSeccion<br>";
          echo "cantidadConjunto: $cantidadConjunto<br><br>";
        }
      }

      //INSERTAMOS LAS SECCIONES DEL PACKING LIST
      $sql = "INSERT INTO packing_lists_secciones (id_packing_list_revision, cantidad, observaciones) VALUES (?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_packing_list_revision,$cantidadConjunto,$observacionesSeccion]);
      $id_packing_list_seccion = $pdo->lastInsertId();

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      //OBTENEMOS LOS COMPONENTES DE LA SECCION
      $sql = "SELECT plc.id,plc.id_packing_list_seccion,plc.id_conjunto_lista_corte,plc.id_concepto,plc.cantidad,plc.id_computo_detalle,plc.observaciones,plc.id_estado_componente_packing_list FROM packing_lists_componentes plc WHERE plc.id_packing_list_seccion = ".$row["id"];
      foreach ($pdo->query($sql) as $row) {

        $id_conjunto_lista_corte=$row["id_conjunto_lista_corte"];
        $id_concepto=$row["id_concepto"];
        $cantidad=$row["cantidad"];
        $id_computo_detalle=$row["id_computo_detalle"];
        $observaciones=$row["observaciones"];
        $id_estado_componente_packing_list=$row["id_estado_componente_packing_list"];

        if ($modoDebug==1) {
          echo "id_conjunto_lista_corte: $id_conjunto_lista_corte<br>";
          echo "id_concepto: $id_concepto<br>";
          echo "cantidad: $cantidad<br>";
          echo "id_computo_detalle: $id_computo_detalle<br>";
          echo "observaciones: $observaciones<br>";
          echo "id_estado_componente_packing_list: $id_estado_componente_packing_list<br>";
        }

        $actualizar_computo_detalle=0;

        if($_POST["id_componente"]==$row['id']){
          //modificamos el conjunto
          $id_conjunto_lista_corte=$_POST["id_conjunto_lista_corte"];
          $id_concepto=$_POST["id_concepto"];
          $cantidad=$_POST["cantidad_componente"];
          //$id_computo_detalle=$_POST["id_computo_detalle"];
          $observaciones=$_POST["observaciones_componente"];
          //$id_estado_componente_packing_list=$_POST["id_estado_componente_packing_list"];

          $actualizar_computo_detalle=1;

          if ($modoDebug==1) {
            echo "id_componente ENCONTRADO, MODIFICAMOS LOS DATOS<br>";
            echo "id_conjunto_lista_corte: $id_conjunto_lista_corte<br>";
            echo "id_concepto: $id_concepto<br>";
            echo "cantidad: $cantidad<br>";
            //echo "id_computo_detalle: $id_computo_detalle<br>";
            echo "observaciones: $observaciones<br>";
            //echo "id_estado_componente_packing_list: $id_estado_componente_packing_list<br>";
          }

        }

        //INSERTAMOS LOS COMPONENTES DE LA SECCION
        $sql = "INSERT INTO packing_lists_componentes (id_packing_list_seccion,id_conjunto_lista_corte,id_concepto,cantidad,id_computo_detalle,observaciones,id_estado_componente_packing_list) VALUES (?,?,?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_packing_list_seccion,$id_conjunto_lista_corte,$id_concepto,$cantidad,$id_computo_detalle,$observaciones,$id_estado_componente_packing_list]);
        $id_packing_list_componente = $pdo->lastInsertId();

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: ".$q->rowCount();
          echo "<br><br>";
        }

        if($actualizar_computo_detalle==1){
          $sql="SELECT cd.id AS idComputoDetalle FROM packing_lists_componentes plc INNER JOIN packing_lists_secciones pls ON plc.id_packing_list_seccion=pls.id INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id INNER JOIN proyectos p ON plr.id_proyecto=p.id INNER JOIN tareas t ON t.id_proyecto=p.id INNER JOIN computos c ON c.id_tarea=t.id INNER JOIN computos_detalle cd ON cd.id_computo=c.id WHERE plc.id = ? AND cd.cancelado = 0 and cd.id_material = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$id_packing_list_componente,$id_concepto]);
          $data = $q->fetch(PDO::FETCH_ASSOC);
          $idComputoDetalle = $data['idComputoDetalle'];
          
          if ($modoDebug==1) {
            $q->debugDumpParams();
            echo "<br><br>Afe: ".$q->rowCount();
            echo "<br><br>";
          }
      
          if($idComputoDetalle>0){
            $sql = "UPDATE packing_lists_componentes SET id_computo_detalle = ? WHERE id = ?";
            $q = $pdo->prepare($sql);
            $q->execute(array($idComputoDetalle,$id_packing_list_componente));
      
            if ($modoDebug==1) {
              $q->debugDumpParams();
              echo "<br><br>Afe: ".$q->rowCount();
              echo "<br><br>";
            }
          }
        }

      }
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nueva Revision de Lista de Corte','Listas de Corte')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

  }

  if ($modoDebug==1) {
    echo "redirect: ".$redirect;
    $pdo->rollBack();
    die();
  } else {
    Database::disconnect();
    header("Location: ".$redirect);
  }

}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT id AS id_packing_list_revision, id_packing_list, fecha, id_estado_packing_list, descripcion, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido, nro_revision, id_proyecto FROM packing_lists_revisiones WHERE ";

if(isset($_GET['id_packing_list_revision'])){
  //nueva revision
  $sql.=" id = ? ";

  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id_packing_list_revision']]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $fecha=date("Y-m-d");
  $id_packing_list=$data['id_packing_list'];
  $id_packing_list_revision=$data['id_packing_list_revision'];
  $nro_revision=$data['nro_revision']+1;
  $descripcion="";
  $idProyecto = $data['id_proyecto'];
  /*$id_cuenta_realizo="";
  $id_cuenta_reviso="";
  $id_cuenta_valido="";*/

  $titleSubmit="Crear revision";
  $classSubmit="btn-success";

}elseif(isset($_GET['id_packing_list']) and isset($_GET['nro_revision'])){
  //modificamos revision
  $sql.=" id_packing_list = ? AND nro_revision = ?";

  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id_packing_list'],$_GET['nro_revision']]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $fecha=$data["fecha"];
  $id_packing_list=$data['id_packing_list'];
  $id_packing_list_revision=$data['id_packing_list_revision'];
  $nro_revision=$data['nro_revision'];
  $descripcion=$data['descripcion'];
  /*$id_cuenta_realizo=$data["id_cuenta_realizo"];
  $id_cuenta_reviso=$data["id_cuenta_reviso"];
  $id_cuenta_valido=$data["id_cuenta_valido"];*/

  $titleSubmit="Modificar revision";
  $classSubmit="btn-primary";
}

Database::disconnect();?>

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
          $ubicacion="Modificar Packing List";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <form class="form theme-form" role="form" method="post" action="revisionPackingList.php?id_packing_list_revision=<?=$data["id_packing_list_revision"]?>">
                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>Secciones de la Packing List #<?=$id_packing_list?>
                        &nbsp;&nbsp;
                        <a href="#" id="link_ver_conjunto_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp;<?php
                        if (!empty(tienePermiso(330))) {?>
                          <img src="img/icon_baja.png" id="link_eliminar_conjunto" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">&nbsp;&nbsp;<?php
                        }?>
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-12">
                              <table class="display" id="tablaSecciones">
                                <thead>
                                  <tr>
                                    <th>ID</th>
                                    <th>Cantidad</th>
                                    <th>Observaciones</th>
                                  </tr>
                                </thead>
                                <tfoot>
                                  <tr>
                                    <th>ID</th>
                                    <th>Cantidad</th>
                                    <th>Observaciones</th>
                                  </tr>
                                </tfoot>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  
                                  $sql = " SELECT plc.id, plc.cantidad, plc.observaciones FROM packing_lists_secciones plc WHERE plc.id_packing_list_revision = ".$data["id_packing_list_revision"];
                                  foreach ($pdo->query($sql) as $row) {
                                    echo '<tr>';
                                    echo '<td>'. $row["id"] . '</td>';
                                    echo '<td>'. $row["cantidad"] . '</td>';
                                    echo '<td>'. $row["observaciones"] . '</td>';
                                    echo '</tr>';
                                  }
                                  Database::disconnect();?>
                                </tbody>
                              </table>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad</label>
                            <div class="col-sm-9">
                              <input name="cantidad_seccion" data-original="" type="number" step="0.01" class="form-control">
                            </div>
                          </div>
                          <div class="form-group row">
                            <input type="hidden" name="idSeccion" id="idSeccion">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                            <div class="col-sm-9"><textarea name="observaciones_seccion" class="form-control"></textarea></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>Componentes para la Seccion ID#<span id="lblIidSeccion"></span>
                        &nbsp;&nbsp;
                        <a href="#" id="link_ver_posicion_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp;<?php
                        if (!empty(tienePermiso(330))) {?>
                          <img src="img/icon_baja.png" id="link_eliminar_posicion" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">&nbsp;&nbsp;
                          <?php
                        }?>
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="form-group col-12">
                          <div class="dt-ext table-responsive">
                            <table class="display" id="tablaComponentes">
                              <thead>
                                <tr>
                                  <th>ID</th>
                                  <th class="d-none">ID Seccion</th>
                                  <th>Concepto</th>
                                  <th>Conjunto LC</th>
                                  <th>Cantidad</th>
                                  <th>Observaciones</th>
                                  <th>Estado</th>
                                </tr>
                              </thead>
                              <tfoot>
                                <tr>
                                  <th>ID</th>
                                  <th class="d-none">ID Seccion</th>
                                  <th>Concepto</th>
                                  <th>Conjunto LC</th>
                                  <th>Cantidad</th>
                                  <th>Observaciones</th>
                                  <th>Estado</th>
                                </tr>
                              </tfoot>
                              <tbody><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                $sql = " SELECT pls.id AS id_seccion, plc.id AS id_componente,plc.id_concepto,m.concepto,lcc.id AS id_conjunto,lcc.nombre AS conjunto,plc.cantidad,plc.observaciones,ecpl.estado FROM packing_lists_componentes plc INNER JOIN packing_lists_secciones pls ON plc.id_packing_list_seccion=pls.id INNER JOIN listas_corte_conjuntos lcc ON plc.id_conjunto_lista_corte=lcc.id INNER JOIN materiales m ON plc.id_concepto=m.id INNER JOIN estados_componentes_packing_list ecpl ON plc.id_estado_componente_packing_list=ecpl.id WHERE pls.id_packing_list_revision = ".$id_packing_list_revision;
                                //echo $sql;
                                foreach ($pdo->query($sql) as $row) {
                                  echo '<tr>';
                                  echo '<td>'.$row["id_componente"].'</td>';
                                  echo '<td class="d-none">'.$row["id_seccion"].'</td>';
                                  echo '<td data-id="'.$row["id_concepto"].'">'.$row["concepto"].'</td>';
                                  echo '<td data-id="'.$row["id_conjunto"].'">'.$row["conjunto"].'</td>';
                                  echo '<td>'.$row["cantidad"].'</td>';
                                  echo '<td>'.$row["observaciones"].'</td>';
                                  echo '<td>'.$row["estado"].'</td>';
                                  echo '</tr>';
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                      <!-- <span id="datosPosicion" class="d-none"> -->
                      <div class="row">
                        <div class="form-group col-sm-6">
                          <input name="id_componente" id="id_componente" type="hidden" class="form-control">
                          <label for="id_concepto">Concepto</label>
                          <select name="id_concepto" id="id_concepto" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon ="SELECT m.id, m.codigo, m.concepto, cd.reservado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join packing_lists_revisiones plr on plr.id_proyecto = p.id INNER JOIN packing_lists pl ON plr.id_packing_list=pl.id AND pl.ultimo_nro_revision=plr.nro_revision inner join packing_lists_secciones pls on pls.id_packing_list_revision = plr.id where cd.cancelado = 0 and p.id = ".$idProyecto;
                            //echo $sqlZon;
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              if ($fila['reservado'] > 0) {
                                echo "<option value='".$fila['id']."'";
                                echo ">".$fila['concepto']." (".$fila['codigo'].") - Reservado: ".$fila['reservado']."</option>";	
                              }
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                        <div class="form-group col-sm-6">
                          <label for="id_conjunto_lista_corte">Conjunto de LC</label>
                          <div class="col-sm-9">
                            <select name="id_conjunto_lista_corte" id="id_conjunto_lista_corte" class="js-example-basic-single col-sm-12">
                              <option value="">Seleccione...</option><?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $sqlZon = "SELECT id, nombre FROM listas_corte_conjuntos WHERE id_estado_lista_corte_conjuntos = 4 ";
                              $q = $pdo->prepare($sqlZon);
                              $q->execute();
                              while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='".$fila['id']."'";
                                echo ">".$fila['nombre']."</option>";
                              }
                              Database::disconnect();?>
                            </select>
                          </div>
                        </div>
                        <div class="form-group col-sm-6">
                          <label for="cantidad">Cantidad</label>
                          <input name="cantidad_componente" id="cantidad_componente" type="number" step="0.01" class="form-control">
                        </div>
                        <div class="form-group col-sm-6">
                          <label for="observaciones">Observaciones</label>
                          <textarea name="observaciones_componente" id="observaciones_componente" class="form-control"></textarea>
                        </div>
                      </div>
                      <!-- </span> -->
                    </div>
                  </div>

                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>Datos de la revision</h5>
                    </div>
                    <div class="card-body">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha(*)</label>
                        <div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?=$fecha?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Revision(*)</label>
                        <div class="col-sm-9"><input name="revision" type="text" maxlength="99" class="form-control" required="required" value="<?=$nro_revision?>" readonly></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Comentarios(*)</label>
                        <div class="col-sm-9"><textarea name="descripcion" required="required" class="form-control"><?=$descripcion?></textarea></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Realizó(*)</label>
                        <div class="col-sm-9">
                          <select name="id_cuenta_realizo" id="id_cuenta_realizo" class="js-example-basic-single col-sm-12" required="required">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (2,3,4) and activo = 1 and anulado = 0";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'";
                              if ($fila['id']==$data['id_cuenta_realizo']) {
                                echo " selected ";
                              }
                              echo ">".$fila['nombre']."</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Revisó</label>
                        <div class="col-sm-9">
                          <select name="id_cuenta_reviso" id="id_cuenta_reviso" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (2,3,4) and activo = 1 and anulado = 0";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'";
                              if ($fila['id']==$data['id_cuenta_reviso']) {
                                echo " selected ";
                              }
                              echo ">".$fila['nombre']."</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Validó</label>
                        <div class="col-sm-9">
                          <select name="id_cuenta_valido" id="id_cuenta_valido" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (2,3,4) and activo = 1 and anulado = 0";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'";
                              if ($fila['id']==$data['id_cuenta_valido']) {
                                echo " selected ";
                              }
                              echo ">".$fila['nombre']."</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <!-- <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion">Crear y Agregar otra Posicion</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Crear y volver a Secciones</button> -->
                        <button type="submit" class="btn <?=$classSubmit?>"><?=$titleSubmit?></button>
                        <a href='listarPackingList.php' class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </div>

                </form>
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

        <!-- Modal para eliminas posiciones -->
        <div class="modal fade" id="eliminarPosicion" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar la posicion?</div>
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
        var tablaSecciones = $('#tablaSecciones');
        // Setup - add a text input to each footer cell
        tablaSecciones.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaSecciones.DataTable({
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
 
        // Apply the search
        tablaSecciones.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        //tablaSecciones.find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaSecciones tbody tr td", function(){
          var t=$(this).parent();

          let id_seccion=t.find("td:first-child").html();
          let tablaComponentes=$('#tablaComponentes').DataTable()
          if(t.hasClass('selected')){
            deselectRow(t);
            //volvemos a ocultar todas las posiciones
            tablaComponentes.rows().nodes().each(function(row) {
              $(row).hide();
            });
            //$("#datosPosicion").addClass("d-none")
            //volvemos a vaciar los links
            $("#link_ver_conjunto_lc").attr("href","#");
            $("#link_eliminar_conjunto").data("id","");
            $("#link_nueva_posicion").attr("href","#");
            //$("#link_modificar_conjunto").attr("href","#");

            //vaciamos los input para permitir crear un nuevo conjunto
            $("#lblIidSeccion").html("")
            $("#idSeccion").val("")
            $("input[name='cantidad_seccion']").val("").attr("data-original","")
            $("textarea[name='observaciones_seccion']").val("")

          }else{
            //t.parent().find("tr").removeClass("selected");
            tablaSecciones.DataTable().rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            //mostramos las posiciones del conjunto seleccionado
            tablaComponentes.rows().nodes().each(function(row) {
              var fila = tablaComponentes.row(row);
              var datos = fila.data();
              if (datos[1] == id_seccion) {
                $(row).show();
              } else {
                $(row).hide();
              }
            });
            //agregamos los links con el id_seccion correspondiente
            $("#link_ver_conjunto_lc").attr("href","verConjuntoListaCorte.php?id="+id_seccion);
            $("#link_eliminar_conjunto").data("id",id_seccion);
            $("#link_nueva_posicion").attr("href","nuevaListaCorteComponentes.php?id_packing_list_conjunto="+id_seccion);
            //$("#link_modificar_conjunto").attr("href","modificarListaCorteConjunto.php?id="+id_seccion);

            //agregamos la funcionalidad para que traiga los datos de la tabla
            let cantidad = t.find("td:nth-child(2)").html();
            let observaciones = t.find("td:nth-child(3)").html();
            $("#lblIidSeccion").html(id_seccion)
            $("#idSeccion").val(id_seccion)
            $("input[name='cantidad_seccion']").val(cantidad).attr("data-original",cantidad).focus()
            $("textarea[name='observaciones_seccion']").val(observaciones)
            
          }
        });

        $("#link_eliminar_conjunto").on("click",function(){
          let id_conjunto=$(this).data("id")
          if(id_conjunto!="" && id_conjunto>0){
            let modal=$("#eliminarConjunto")
            modal.modal("show")
            modal.find(".modal-footer a").attr("href","eliminarConjuntoListaCorte.php?id="+id_conjunto)
          }else{
            alert("Por favor seleccione un conjunto para eliminar")
          }
        });

        $("#link_ver_conjunto_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un conjunto para ver el detalle")
            e.preventDefault()
          }
        })

        var tablaComponentes = $('#tablaComponentes');
        // Setup - add a text input to each footer cell
        tablaComponentes.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaComponentes.DataTable({
          stateSave: false,
          responsive: false,
          "rowCallback": function(row, data) {
            $(row).hide();
          },
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
 
        // Apply the search
        tablaComponentes.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        //tablaComponentes.find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaComponentes tbody tr td", function(){
          var t=$(this).parent();

          let id_componente=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#link_modificar_posicion").attr("href","#");
            $("#link_eliminar_posicion").data("id","");
            $("#link_ver_posicion_lc").attr("href","#");

            $("#id_componente").val("")
            $("select[name='id_concepto']").val("").trigger('change');
            $("select[name='id_conjunto_lista_corte']").val("").trigger('change')
            $("input[name='cantidad_componente']").val("")
            $("textarea[name='observaciones_componente']").val("")

          }else{
            //t.parent().find("tr").removeClass("selected");
            tablaComponentes.DataTable().rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            //agregamos los links con el id_componente correspondiente
            $("#link_modificar_posicion").attr("href","modificarPosicionListaCorte.php?id="+id_componente);
            //$("#link_eliminar_posicion").attr("href","eliminarPosicionListaCorte.php?id="+id_componente);
            $("#link_eliminar_posicion").data("id",id_componente);
            $("#link_ver_posicion_lc").attr("href","verPosicionConjuntoListaCorte.php?id="+id_componente);

            let id_concepto = t.find("td:nth-child(3)").data("id");
            let id_conjunto_lista_corte = t.find("td:nth-child(4)").data("id");
            let cantidad_componente = t.find("td:nth-child(5)").html();
            let observaciones_componente = t.find("td:nth-child(6)").html();

            $("#id_componente").val(id_componente)
            $("select[name='id_concepto']").val(id_concepto).trigger('change');
            $("select[name='id_conjunto_lista_corte']").val(id_conjunto_lista_corte).trigger('change')
            $("input[name='cantidad_componente']").val(cantidad_componente)
            $("textarea[name='observaciones_componente']").val(observaciones_componente)
          }
        });

        /*$("#link_eliminar_posicion").on("click",function(){
          let id_componente=$(this).data("id")
          if(id_componente!="" && id_componente>0){
            let modal=$("#eliminarPosicion")
            modal.modal("show")
            modal.find(".modal-footer a").attr("href","eliminarPosicionListaCorte.php?id="+id_componente)
          }
        });*/

        $("#link_eliminar_posicion").on("click",function(){
          let id_componente=$(this).data("id")
          if(id_componente!="" && id_componente>0){
            let modal=$("#eliminarPosicion")
            modal.modal("show")
            modal.find(".modal-footer a").attr("href","eliminarPosicionListaCorte.php?id="+id_componente)
          }else{
            alert("Por favor seleccione una posicion para eliminar")
          }
        });

        $("#link_ver_posicion_lc").on("click",function(e){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            e.preventDefault()
            alert("Por favor seleccione una posicion para ver el detalle")
          }
        })

        /*$("#cancelEditPosicion").on("click",function(){
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

          $(".addPosicion").toggleClass("d-none")
          $("#editPosicion").toggleClass("d-none")
          $("#editPosicion").val("")
          $("#cancelEditPosicion").toggleClass("d-none")
        })*/
    
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