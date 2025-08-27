<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
$modoDebug=0;

$editing=false;
$id_orden_trabajo=null;
$id_lista_corte=null;
$data_ot=[];
$conjuntos_ot=[];

if(isset($_GET['id'])){
  $editing=true;
  $id_orden_trabajo=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
  $pdoTmp=Database::connect();
  $pdoTmp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql="SELECT nro_orden_trabajo,fecha,id_lista_corte,nro_revision,titulo,numero,descripcion,notas FROM ordenes_trabajo WHERE id = ?";
  $q=$pdoTmp->prepare($sql);
  $q->execute([$id_orden_trabajo]);
  $data_ot=$q->fetch(PDO::FETCH_ASSOC);
  if($data_ot){
    $id_lista_corte=$data_ot['id_lista_corte'];
    $data_lc=obtenerDatosListaCorte($pdoTmp,$id_lista_corte);
    if($data_lc){
      $data_ot=array_merge($data_ot,$data_lc);
    }
    $sql="SELECT lcc.id AS id_conjunto, MIN(otd.cantidad/lcp.cantidad) AS cant_ot\n          FROM ordenes_trabajo_detalle otd\n          JOIN lista_corte_posiciones lcp ON otd.id_posicion = lcp.id\n          JOIN listas_corte_conjuntos lcc ON lcp.id_lista_corte_conjunto = lcc.id\n          WHERE otd.id_orden_trabajo = ?\n          GROUP BY lcc.id";
    $q=$pdoTmp->prepare($sql);
    $q->execute([$id_orden_trabajo]);
    $conjuntos_ot=$q->fetchAll(PDO::FETCH_ASSOC);
  }
  Database::disconnect();
}elseif(isset($_GET['id_lista_corte'])){
  $id_lista_corte=filter_input(INPUT_GET,'id_lista_corte',FILTER_VALIDATE_INT);
  $pdoTmp=Database::connect();
  $pdoTmp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  if(!LCPermiteOR($pdoTmp,$id_lista_corte)){
    Database::disconnect();
    header("Location: listarListasCorte.php?error=lc_no_aprobada");
    exit;
  }
  $data_ot=obtenerDatosListaCorte($pdoTmp,$id_lista_corte);
  Database::disconnect();
}

function obtenerSaldoPosicion($pdo,$id_posicion){
  $id_posicion = intval($id_posicion);
  $sql = "SELECT lcp.cantidad AS cant_pos, lcc.cantidad AS cant_conj, COALESCE(SUM(otd.cantidad),0) AS cant_bajada FROM lista_corte_posiciones lcp INNER JOIN listas_corte_conjuntos lcc ON lcp.id_lista_corte_conjunto=lcc.id LEFT JOIN ordenes_trabajo_detalle otd ON otd.id_posicion=lcp.id WHERE lcp.id = ? GROUP BY lcp.id,lcc.cantidad";
  $q = $pdo->prepare($sql);
  $q->execute([$id_posicion]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  return $data ? ($data['cant_pos']*$data['cant_conj']) - $data['cant_bajada'] : 0;
}

function LCPermiteOR($pdo, $id_lista_corte){
  $sql = "SELECT id_estado_lista_corte FROM listas_corte WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte]);
  $estadoLC = $q->fetch(PDO::FETCH_ASSOC);
  
  if(!$estadoLC){
    return false;
  }
  if(in_array($estadoLC['id_estado_lista_corte'],[3,4])){
    return true;
  } else {
    return false;
  }
}

function obtenerDatosListaCorte($pdo, $id_lista_corte){
  $sql = "SELECT id AS id_lista_corte_revision, nombre, numero AS numero_lc, id_estado_lista_corte, id_proyecto, nro_revision, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido FROM listas_corte WHERE id = ?"; 
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte]);
  return $q->fetch(PDO::FETCH_ASSOC);
}

function obtenerDatosConjuntos($pdo,$id_lista_corte){
  $conjuntos=[];
  $sql="SELECT id, nombre, cantidad FROM listas_corte_conjuntos WHERE id_lista_corte = ? ORDER BY id";
  $q=$pdo->prepare($sql);
  $q->execute([$id_lista_corte]);
  while($conj=$q->fetch(PDO::FETCH_ASSOC)){
    $sqlPos="SELECT lcp.id, lcp.posicion, lcp.cantidad AS cant_pos, m.concepto, GROUP_CONCAT(DISTINCT tp.tipo SEPARATOR ',') AS procesos, COALESCE(otd.cant_bajada_total,0) AS cant_bajada_total
              FROM lista_corte_posiciones lcp
              JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion = lcp.id
              JOIN materiales m ON lcp.id_material = m.id
              JOIN tipos_procesos tp ON lcpr.id_tipo_proceso = tp.id
              LEFT JOIN (
                SELECT id_posicion, SUM(cantidad) AS cant_bajada_total
                FROM ordenes_trabajo_detalle otd
                JOIN ordenes_trabajo ot ON ot.id = otd.id_orden_trabajo
                WHERE ot.id_estado_orden_trabajo IN (1,2,3)
                GROUP BY id_posicion
              ) otd ON otd.id_posicion = lcp.id
              WHERE lcp.id_lista_corte_conjunto = ?
              GROUP BY lcp.id";
    $qPos=$pdo->prepare($sqlPos);
    $qPos->execute([$conj['id']]);
    $posiciones=[];
    $saldo_conj=$conj['cantidad'];
    while($pos=$qPos->fetch(PDO::FETCH_ASSOC)){
      $cant_total=$conj['cantidad']*$pos['cant_pos'];
      $saldo=$cant_total-$pos['cant_bajada_total'];
      $pos['cant_total']=$cant_total;
      $pos['cant_bajada']=$pos['cant_bajada_total'];
      $pos['saldo']=$saldo;
      unset($pos['cant_bajada_total']);
      $posiciones[]=$pos;
      $sets_disp=$pos['cant_pos']>0 ? floor($saldo/$pos['cant_pos']) : 0;
      if($sets_disp<$saldo_conj){
        $saldo_conj=$sets_disp;
      }
    }
    $conj['posiciones']=$posiciones;
    $conj['cant_bajada']=$conj['cantidad']-$saldo_conj;
    $conj['saldo']=$saldo_conj;
    $conjuntos[]=$conj;
  }
  return $conjuntos;
}

if (!empty($_POST)) {

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $redirect="listarOrdenesTrabajo.php";

  if(!empty($_POST['id_orden_trabajo'])){
    $id_orden_trabajo=intval($_POST['id_orden_trabajo']);
    $enviarAprobacion = !empty($_POST['enviar_aprobacion']);
    $pdo->beginTransaction();

    $sql = "UPDATE ordenes_trabajo set fecha = ?, titulo = ?, notas = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST["fecha"],$_POST["titulo"],$_POST["notas"],$id_orden_trabajo]);

    // posiciones actuales en la OT
    $sql = "SELECT id_posicion FROM ordenes_trabajo_detalle WHERE id_orden_trabajo = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_orden_trabajo]);
    $posicionesActuales = $q->fetchAll(PDO::FETCH_COLUMN,0);
    $posicionesProcesadas = [];

    foreach ($_POST["cantidad_bajar"] as $key => $cantidad) {
      if($cantidad!="" and $cantidad>0){
        $id_posicion=$_POST['id_posicion'][$key];
        $posicionesProcesadas[] = $id_posicion;

        $cant_liberadas=0;
        $cant_proceso=0;
        $cant_rechazadas=0;
        $id_estado_orden_trabajo_posicion=1;

        $sql = "SELECT otd.id_orden_trabajo,otd.id_posicion,otd.cantidad,otd.cant_liberadas,otd.cant_proceso,otd.cant_rechazadas,otd.id_estado_orden_trabajo_posicion FROM ordenes_trabajo_detalle otd WHERE otd.id_posicion = ? AND otd.id_orden_trabajo = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$id_posicion,$id_orden_trabajo]);
        $data = $q->fetch(PDO::FETCH_ASSOC);

        if($data){
          $cant_liberadas=$data["cant_liberadas"];
          $cant_proceso=$data["cant_proceso"];
          $cant_rechazadas=$data["cant_rechazadas"];
          $id_estado_orden_trabajo_posicion=$data["id_estado_orden_trabajo_posicion"];

          $sql = "UPDATE ordenes_trabajo_detalle SET cantidad = ?, cant_liberadas = ?, cant_proceso = ?, cant_rechazadas = ?, id_estado_orden_trabajo_posicion = ? WHERE id_orden_trabajo = ? AND id_posicion = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$cantidad,$cant_liberadas,$cant_proceso,$cant_rechazadas,$id_estado_orden_trabajo_posicion,$id_orden_trabajo,$id_posicion]);
        }else{
          $sql = "INSERT INTO ordenes_trabajo_detalle (id_orden_trabajo, id_posicion, cantidad, cant_liberadas, cant_proceso, cant_rechazadas, id_estado_orden_trabajo_posicion) VALUES (?,?,?,?,?,?,?)";
          $q = $pdo->prepare($sql);
          $q->execute([$id_orden_trabajo,$id_posicion,$cantidad,$cant_liberadas,$cant_proceso,$cant_rechazadas,$id_estado_orden_trabajo_posicion]);
        }
      }
    }

    // eliminar posiciones que fueron quitadas en la edición
    $posicionesEliminar = array_diff($posicionesActuales, $posicionesProcesadas);
    if(!empty($posicionesEliminar)){
      $marks = implode(',', array_fill(0, count($posicionesEliminar), '?'));
      $sql = "DELETE FROM ordenes_trabajo_detalle WHERE id_orden_trabajo = ? AND id_posicion IN ($marks)";
      $params = array_merge([$id_orden_trabajo], $posicionesEliminar);
      $q = $pdo->prepare($sql);
      $q->execute($params);
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Orden de Trabajo','Orden de Trabajo')";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id']]);

    if ($enviarAprobacion) {
      $sql = "UPDATE ordenes_trabajo SET id_estado_orden_trabajo = 2 WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_orden_trabajo]);

      // Actualizar estado de todas las posiciones
      $sql = "UPDATE ordenes_trabajo_detalle SET id_estado_orden_trabajo_posicion = 2 WHERE id_orden_trabajo = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_orden_trabajo]);

      $sql = "SELECT l.numero AS numero_lc, l.id_proyecto, ot.nro_orden_trabajo FROM ordenes_trabajo ot JOIN listas_corte l ON l.id = ot.id_lista_corte WHERE ot.id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_orden_trabajo]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $descProyecto = getDescripcionProyecto($pdo, $data['id_proyecto']);
      $descripcion_orden_trabajo = " LC".$data['numero_lc']."-OT".$data['nro_orden_trabajo'].$descProyecto;

      $idTipoNotificacion=19;
      $idEntidad=$id_orden_trabajo;
      $detalleNotificacion="ID Orden de Trabajo: #".$idEntidad;
      $asuntoEmail="Producción - Aprobación de Orden de Trabajo $descripcion_orden_trabajo";
      $cuerpoEmail="La orden de trabajo $descripcion_orden_trabajo está lista para aprobación.";
      crearNotificacion($pdo,$idTipoNotificacion,$idEntidad,$detalleNotificacion,$asuntoEmail,$cuerpoEmail);
    }

    if ($modoDebug==1) {
      $pdo->rollBack();
      die();
    } else {
      $pdo->commit();
      Database::disconnect();
      header("Location: ".$redirect);
    }
    exit;
  }

  $id_lista_corte = filter_input(INPUT_POST, 'id_lista_corte', FILTER_VALIDATE_INT);

  if(!LCPermiteOR($pdo, $id_lista_corte)){
    Database::disconnect();
    header("Location: listarListasCorte.php?error=lc_no_aprobada");
    exit;
  }

  $pdo->beginTransaction();

  if ($modoDebug==1) {
    var_dump($_POST);
  }

  $id_estado_orden_trabajo=1;
  $nro_revision=0;
  $anulado=0;
  $numero="";
  $descripcion="Emision original";

  $sql = "SELECT COUNT(*) FROM ordenes_trabajo WHERE id_lista_corte = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte]);
  $cant_ot_previas = (int)$q->fetchColumn();

  $sql = "SELECT max(nro_orden_trabajo) AS nro_orden_trabajo FROM ordenes_trabajo where id_lista_corte = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $nro_orden_trabajo = $data['nro_orden_trabajo']+1;

  $sql = "INSERT INTO ordenes_trabajo (nro_orden_trabajo,id_lista_corte, fecha, id_usuario, id_estado_orden_trabajo, nro_revision, anulado, titulo, numero, descripcion, notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
  $params = [
    $nro_orden_trabajo,
    $id_lista_corte,
    $_POST['fecha'],
    $_SESSION["user"]["id"],
    $id_estado_orden_trabajo,
    $nro_revision,
    $anulado,
    $_POST['titulo'],
    $numero,
    $descripcion,
    $_POST['notas']
  ];
  $q = $pdo->prepare($sql);
  $q->execute($params);
  if ($modoDebug==1) {
    echo debugQuery($pdo, $sql, $params) . "<br><br>Afe: " . $q->rowCount() . "<br><br>";
  }
  $id_orden_trabajo = $pdo->lastInsertId();

  $sql = "SELECT COUNT(*) FROM ordenes_trabajo WHERE id_lista_corte = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte]);
  $cant_ot_actuales = (int)$q->fetchColumn();

  //if ($cant_ot_previas == 0 && $estadoLC['id_estado_lista_corte'] == 3) {
  if ($cant_ot_previas == 0 && LCPermiteOR($pdo, $id_lista_corte)) {
    $sql = "UPDATE listas_corte SET id_estado_lista_corte = 4 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte]);

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo) VALUES (now(), ?, 'Lista de Corte pasada a En Proceso al crear primera OT', 'Lista de Corte')";
    $params = [$_SESSION['user']['id']];
    $q = $pdo->prepare($sql);
    $q->execute($params);
  }

  foreach ($_POST["cantidad_bajar"] as $key => $cantidad) {
    if($cantidad!=""){
      $id_posicion = intval($_POST['id_posicion'][$key]);
      $saldo = obtenerSaldoPosicion($pdo,$id_posicion);
      if(!is_numeric($cantidad) || $cantidad <= 0 || $cantidad > $saldo){
        $pdo->rollBack();
        Database::disconnect();
        header("Location: nuevaOrdenTrabajo.php?error=1&id_lista_corte=".$id_lista_corte);
        exit;
      }
      $id_estado_orden_trabajo_posicion=1;

      $sql = "INSERT INTO ordenes_trabajo_detalle (id_orden_trabajo, id_posicion, cantidad, id_estado_orden_trabajo_posicion) VALUES (?,?,?,?)";
      $params = [$id_orden_trabajo,$id_posicion,$cantidad,$id_estado_orden_trabajo_posicion];
      $q = $pdo->prepare($sql);
      $q->execute($params);
    }
  }

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nueva Orden de Trabajo','Orden de Trabajo')";
  $params = [$_SESSION['user']['id']];
  $q = $pdo->prepare($sql);
  $q->execute($params);

  /*$sql = "SELECT id_proyecto FROM listas_corte WHERE id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $descProyecto = getDescripcionProyecto($pdo, $data["id_proyecto"]);
  $descripcion_orden_trabajo = " LC".$id_lista_corte."-OT".$id_orden_trabajo.$descProyecto;

  $idTipoNotificacion=8;
  $idEntidad=$id_orden_trabajo;
  $detalleNotificacion="ID Orden de Trabajo: #".$idEntidad;
  $asuntoEmail="Módulo Producción - Nueva Orden de Trabajo $descripcion_orden_trabajo";
  $cuerpoEmail="Nueva orden de trabajo dada de alta en el sistema: $descripcion_orden_trabajo";
  crearNotificacion($pdo,$idTipoNotificacion,$idEntidad,$detalleNotificacion,$asuntoEmail,$cuerpoEmail);*/

  if ($modoDebug==1) {
    $pdo->rollBack();
    die();
  } else {
    $pdo->commit();
    Database::disconnect();
    header("Location: ".$redirect);
  }

}

$id_proyecto=$data_ot['id_proyecto'];
$descProyecto=getDescripcionProyecto($pdoTmp,$id_proyecto);

$pdoList = Database::connect();
$pdoList->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conjuntosLC = obtenerDatosConjuntos($pdoList,$id_lista_corte);
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
          $ubicacion = $editing ? "Modificar OT ".$data_ot['nro_orden_trabajo'].' - ' : "Nueva Orden de Trabajo para la ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12"><?php
                if(isset($_GET['error']) && $_GET['error']==1){?>
                  <div class="alert alert-danger">La cantidad a bajar debe ser un número positivo y no superar el saldo disponible.</div><?php
                }?>
                <form class="form theme-form" role="form" method="post" action="nuevaOrdenTrabajo.php">
                  <div class="card mb-0">
                    <div class="card-header">
                      <h5><?=$ubicacion.' LC N° '.$data_ot['numero_lc'].' - Rev '.$data_ot['nro_revision'].' '.htmlspecialchars($descProyecto)?></h5>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <input type="hidden" name="id_lista_corte" id="id_lista_corte" value="<?=$id_lista_corte?>"><?php
                            if($editing){?>
                              <input type="hidden" name="id_orden_trabajo" value="<?=$id_orden_trabajo?>"><?php
                            }?>
                            <label class="col-sm-3 col-form-label">Fecha(*)</label>
                            <div class="col-sm-3">
                              <input name="fecha" type="date" onfocus="this.showPicker()" value="<?=$editing ? $data_ot['fecha'] : date('Y-m-d');?>" class="form-control">
                            </div>
                            <label class="col-sm-3 col-form-label">Titulo(*)</label>
                            <div class="col-sm-3">
                              <input name="titulo" type="text" class="form-control" value="<?=$editing ? htmlspecialchars($data_ot['titulo']) : ''?>" autofocus>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Notas de la OT(*)</label>
                            <div class="col-sm-9">
                              <textarea name="notas" class="form-control"><?=$editing ? htmlspecialchars($data_ot['notas']) : ''?></textarea>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>Detalle de la LC
                        &nbsp;&nbsp;
                        <img src="img/icon_alta.png" id="link_agregar_posiciones" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Agregar" title="Agregar">
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="form-group row mb-3">
                        <div class="col-sm-3 mb-2 mb-sm-0">
                          <select id="filtro_conjunto" class="form-control" style="width:100%" multiple></select>
                        </div>
                        <div class="col-sm-3 mb-2 mb-sm-0">
                          <select id="filtro_proceso" class="form-control" style="width:100%" multiple></select>
                        </div>
                        <div class="col-sm-3 mb-2 mb-sm-0">
                          <select id="filtro_material" class="form-control" style="width:100%" multiple></select>
                        </div>
                        <div class="col-sm-3">
                          <button type="button" id="toggle_seleccion" class="btn btn-primary w-100">Seleccionar visibles</button>
                        </div>
                      </div>
                      <!--Listado de conjuntos-->
                      <div class="dt-ext table-responsive">
                        <table class="display" id="tablaLC">
                          <thead>
                            <tr>
                              <th>Conjunto</th>
                              <th>Cant. conj.</th>
                              <th>Cant. bajada</th>
                              <th>Saldo</th>
                              <th>Posiciones</th>
                            </tr>
                          </thead>
                          <tbody><?php
                            foreach($conjuntosLC as $conj){
                              $json = htmlspecialchars(json_encode($conj['posiciones']), ENT_QUOTES, 'UTF-8'); ?>
                              <tr data-id='<?=$conj['id']?>' data-posiciones='<?=$json?>' data-nombre='<?=htmlspecialchars($conj['nombre'],ENT_QUOTES)?>' data-cantconj='<?=$conj['cantidad']?>' data-cantbajada='<?=$conj['cant_bajada']?>' data-saldo='<?=$conj['saldo']?>'>
                                <td><?=htmlspecialchars($conj['nombre'])?></td>
                                <td class="text-end"><?=$conj['cantidad']?></td>
                                <td class="text-end"><?=$conj['cant_bajada']?></td>
                                <td class="text-end"><?=$conj['saldo']?></td>
                                <td>
                                  <table class="table table-sm mb-0">
                                    <thead>
                                      <tr>
                                        <th class="d-none">ID</th>
                                        <th>Posición</th>
                                        <th>Material</th>
                                        <th>Procesos</th>
                                        <th>Cant. pos.</th>
                                        <th>Total</th>
                                        <th>Bajadas</th>
                                        <th>Saldo</th>
                                      </tr>
                                    </thead>
                                    <tbody><?php
                                      foreach($conj['posiciones'] as $p){ ?>
                                        <tr>
                                          <td class="d-none"><?=$p['id']?></td>
                                          <td><?=$p['posicion']?></td>
                                          <td><?=$p['concepto']?></td>
                                          <td><?=$p['procesos']?></td>
                                          <td class="text-end"><?=$p['cant_pos']?></td>
                                          <td class="text-end"><?=$p['cant_total']?></td>
                                          <td class="text-end"><?=$p['cant_bajada']?></td>
                                          <td class="text-end"><?=$p['saldo']?></td>
                                        </tr><?php
                                      }?>
                                    </tbody>
                                  </table>
                                </td>
                              </tr><?php
                            }?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>
                        Detalle de la OT
                        <img src="img/icon_baja.png" id="link_eliminar_posiciones" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">&nbsp;&nbsp;
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="form-group row">
                        <div class="dt-ext table-responsive">
                          <table class="display" id="tablaOT">
                            <thead>
                              <tr>
                                <th class="d-none">ID</th>
                                <th>Conjunto</th>
                                <th>Cant. conj.</th>
                                <th>Cant. bajada</th>
                                <th>Saldo conj.</th>
                                <th>Cant. conj. a bajar</th>
                                <th>Posiciones</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php // al editar no se soporta la vista por conjuntos, se mostrará vacío ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" class="btn btn-success"><?=$editing ? 'Modificar' : 'Crear'?></button>
                        <?php if($editing){?>
                          <button type="submit" name="enviar_aprobacion" value="1" class="btn btn-primary">Enviar a aprobación</button>
                        <?php }?>
                        <a href='listarOrdenesTrabajo.php' class="btn btn-light">Volver</a>
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

        <!-- Modal para eliminar posiciones -->
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
      $(function(){
        var tablaLC = $('#tablaLC');
        var tablaOT = $('#tablaOT');
        var tablaLCDT, dtOT;
        var selectedProcesos = [];
        var selectedMateriales = [];
        var conjuntosEdit = <?=json_encode($conjuntos_ot)?>;

        tablaLCDT = tablaLC.DataTable({
          order:[[0,'asc']],
          autoWidth: false,
          columnDefs: [
            { width: '50px', targets: [1,2,3] },
            { width: '100px', targets: [0] },
            //{ width: '300px', targets: [6] }
          ],
        });

        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
          if(settings.nTable !== tablaLC[0]) return true;
          var tr = tablaLCDT.row(dataIndex).node();
          var posiciones = $(tr).data('posiciones') || [];
          if(selectedProcesos.length){
            var matchProc = posiciones.some(function(p){
              return selectedProcesos.some(function(sel){
                return p.procesos.split(',').map(function(x){return x.trim();}).includes(sel);
              });
            });
            if(!matchProc) return false;
          }
          if(selectedMateriales.length){
            var matchMat = posiciones.some(function(p){
              return selectedMateriales.includes(p.concepto.trim());
            });
            if(!matchMat) return false;
          }
          return true;
        });

        function updateToggleButton(){
          var rows = tablaLCDT.rows({search:'applied'}).nodes();
          var allSelected = rows.length>0 && $(rows).filter('.selected').length === rows.length;
          var btn = $('#toggle_seleccion');
          if(allSelected){
            btn.text('Deseleccionar todo').removeClass('btn-primary').addClass('btn-secondary');
          }else{
            btn.text('Seleccionar visibles').removeClass('btn-secondary').addClass('btn-primary');
          }
        }

        $('#tablaLC tbody').on('click','tr',function(){
          $(this).toggleClass('selected');
          updateToggleButton();
        });

        $('#toggle_seleccion').on('click', function(){
          var rows = tablaLCDT.rows({search:'applied'}).nodes();
          var allSelected = rows.length>0 && $(rows).filter('.selected').length === rows.length;
          if(allSelected){
            $(rows).removeClass('selected');
          } else {
            $(rows).addClass('selected');
          }
          updateToggleButton();
        });

        tablaLCDT.on('draw', updateToggleButton);

        var conjuntos = tablaLCDT.column(0).data().unique().sort();
        conjuntos.each(function(d){
          $('#filtro_conjunto').append('<option value="'+d+'">'+d+'</option>');
        });

        var materialesSet = new Set();
        var procesosSet = new Set();
        tablaLCDT.rows().every(function(){
          var posiciones = $(this.node()).data('posiciones') || [];
          posiciones.forEach(function(p){
            if(p.concepto && p.concepto.trim() !== '') materialesSet.add(p.concepto.trim());
            p.procesos.split(',').forEach(function(pr){
              if(pr.trim() !== '') procesosSet.add(pr.trim());
            });
          });
        });
        Array.from(materialesSet).sort().forEach(function(m){
          $('#filtro_material').append('<option value="'+m+'">'+m+'</option>');
        });
        Array.from(procesosSet).sort().forEach(function(p){
          $('#filtro_proceso').append('<option value="'+p+'">'+p+'</option>');
        });

        $('#filtro_conjunto').select2({placeholder:'Conjunto',allowClear:true});
        $('#filtro_proceso').select2({placeholder:'Proceso',allowClear:true});
        $('#filtro_material').select2({placeholder:'Material',allowClear:true});

        $('#filtro_conjunto').on('change', function(){
          var selected = $(this).val();
          var search = selected && selected.length ? selected.map(val => '^'+$.fn.dataTable.util.escapeRegex(val)+'$').join('|') : '';
          tablaLCDT.column(0).search(search,true,false).draw();
        });

        $('#filtro_proceso').on('change', function(){
          selectedProcesos = $(this).val() || [];
          tablaLCDT.draw();
        });

        $('#filtro_material').on('change', function(){
          selectedMateriales = $(this).val() || [];
          tablaLCDT.draw();
        });

        dtOT = tablaOT.DataTable({
          order:[[1,'asc']],
          autoWidth: false,
          columnDefs: [
            { visible:false, targets:0},
            { orderable:false, targets:[5,6]},
            { width: '50px', targets: [0,2,3,4] },
            { width: '80px', targets: [1,5] },
            //{ width: '300px', targets: [6] }
          ],
        });

        if(Array.isArray(conjuntosEdit)){
          conjuntosEdit.forEach(function(c){
            var lcrow = $('#tablaLC tbody tr').filter(function(){ return $(this).data('id') == c.id_conjunto; });
            if(lcrow.length){
              var nombre = lcrow.data('nombre');
              var cantConj = parseInt(lcrow.data('cantconj'),10);
              var cantBajada = parseInt(lcrow.data('cantbajada'),10);
              var saldo = parseInt(lcrow.data('saldo'),10);
              var cantOT = parseInt(c.cant_ot,10);
              var saldoDisp = saldo + cantOT;
              var cantBajadaSin = cantBajada - cantOT;
              lcrow.data('cantbajada', cantBajadaSin);
              lcrow.find('td:eq(2)').text(cantBajadaSin);
              lcrow.data('saldo', saldoDisp);
              lcrow.find('td:eq(3)').text(saldoDisp);
              var posiciones = lcrow.data('posiciones');
              var input = `<input type="number" class="form-control cant-conj" value="${cantOT}" data-max="${saldoDisp}" min="0">`;
              var posHtml = formatPosicionesOT(posiciones,cantOT);
              var rowNode = dtOT.row.add([0,nombre,cantConj,cantBajadaSin,saldoDisp,input,posHtml]).draw(false).node();
              $(rowNode).data('posiciones',posiciones).data('lcrow',lcrow);
              lcrow.addClass('d-none');
            }
          });
        }

        function formatPosicionesOT(posiciones,cant){
          console.log(posiciones,cant);
          var table = $('<table class="table table-sm mb-0 w-100"><thead><tr>\n' +
                    '<th class="d-none">ID</th><th>Posición</th><th>Material</th><th>Procesos</th><th>Cant. pos.</th><th>Cant. total</th><th>Cant. bajada</th><th>Saldo</th><th>Cant. a bajar</th>' +
                    '</tr></thead><tbody></tbody></table>');
          table.addClass('w-100');
          var tbody = table.find('tbody');
          tbody.empty();
          posiciones.forEach(function(p){
            var cantidad=p.cant_pos*cant;
            var row = $('<tr></tr>');
            row.append(`<td class="d-none"><input type="hidden" name="id_posicion[]" value="${p.id}"></td>`);
            row.append(`<td>${p.posicion}</td>`);
            row.append(`<td>${p.concepto}</td>`);
            row.append(`<td>${p.procesos}</td>`);
            row.append(`<td class="text-end">${p.cant_pos}</td>`);
            row.append(`<td class="text-end">${p.cant_total}</td>`);
            row.append(`<td class="text-end">${p.cant_bajada}</td>`);
            row.append(`<td class="text-end">${p.saldo}</td>`);
            row.append(`<td class="text-end">${cantidad}<input type="hidden" name="cantidad_bajar[]" value="${cantidad}"></td>`);
            tbody.append(row);
          });
          return table.prop('outerHTML');
        }

        $('#link_agregar_posiciones').on('click',function(){
          var selected=tablaLCDT.rows('.selected');
          if(selected.count()===0){alert('Por favor seleccione un conjunto para agregar a la Orden de trabajo');return;}
          selected.nodes().each(function(node){
            var tr=$(node);
            var nombre=tr.data('nombre');
            var cantConj=parseInt(tr.data('cantconj'),10);
            var cantBajada=parseInt(tr.data('cantbajada'),10);
            var saldo=parseInt(tr.data('saldo'),10);
            var posiciones=tr.data('posiciones');
            console.log(posiciones);
            var input=`<input type="number" class="form-control cant-conj" value="${saldo}" data-max="${saldo}" min="0">`;
            var posHtml=formatPosicionesOT(posiciones,saldo);
            var rowNode=dtOT.row.add([0,nombre,cantConj,cantBajada,saldo,input,posHtml]).draw(false).node();
            $(rowNode).data('posiciones',posiciones).data('lcrow',tr);
            tr.addClass('d-none').removeClass('selected');
          });
        });

        $('#link_eliminar_posiciones').on('click',function(){
          var selected=dtOT.rows('.selected');
          if(selected.count()===0){alert('Por favor seleccione un conjunto para eliminar');return;}
          selected.nodes().each(function(node){
            var lcrow=$(node).data('lcrow');
            if(lcrow){ lcrow.removeClass('d-none'); }
          });
          selected.remove().draw();
        });

        $('#tablaOT tbody').on('click','tr',function(){
          if($(this).hasClass('selected')){
            $(this).removeClass('selected');
          }else{
            dtOT.rows().nodes().to$().removeClass('selected');
            $(this).addClass('selected');
          }
        });

        tablaOT.on('input','.cant-conj',function(){
          var val=parseInt($(this).val(),10);
          var max=parseInt($(this).data('max'),10);
          if(isNaN(val)||val<0) val=0;
          if(val>max){val=max;$(this).val(max);}
          var tr=$(this).closest('tr');
          var posiciones=tr.data('posiciones');
          var posHtml=formatPosicionesOT(posiciones,val);
          dtOT.cell(tr,6).data(posHtml).draw(false);
        });

        updateToggleButton();

      });
    </script>
  </body>
</html>
