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

if (!empty($_POST)) {

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $redirect="listarOrdenesTrabajo.php";

  if(!empty($_POST['id_orden_trabajo'])){
    $id_orden_trabajo=intval($_POST['id_orden_trabajo']);
    $pdo->beginTransaction();

    $sql = "UPDATE ordenes_trabajo set fecha = ?, titulo = ?, notas = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST["fecha"],$_POST["titulo"],$_POST["notas"],$id_orden_trabajo]);

    foreach ($_POST["cantidad_bajar"] as $key => $cantidad) {
      if($cantidad!="" and $cantidad>0){
        $id_posicion=$_POST['id_posicion'][$key];

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
        }

        $sql = "INSERT INTO ordenes_trabajo_detalle (id_orden_trabajo, id_posicion, cantidad, cant_liberadas, cant_proceso, cant_rechazadas, id_estado_orden_trabajo_posicion) VALUES (?,?,?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_orden_trabajo,$id_posicion,$cantidad,$cant_liberadas,$cant_proceso,$cant_rechazadas,$id_estado_orden_trabajo_posicion]);
      }
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Orden de Trabajo','Orden de Trabajo')";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id']]);

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

  $sql = "SELECT id_proyecto FROM listas_corte WHERE id = ? ";
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
  crearNotificacion($pdo,$idTipoNotificacion,$idEntidad,$detalleNotificacion,$asuntoEmail,$cuerpoEmail);

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
                      <div class="form-group row">
                        <div class="dt-ext table-responsive">
                          <table class="display" id="tablaLC">
                            <thead>
                              <tr>
                                <th class="d-none">ID Posicion</th>
                                <th>Conjunto</th>
                                <th>Cant. conj.</th>
                                <th>Posicion</th>
                                <th>Cant. pos.</th>
                                <th>Cant. total</th>
                                <th>Material</th>
                                <th>Procesos</th>
                                <th>Cant. bajada</th>
                                <th>Saldo</th>
                              </tr>
                            </thead>
                            <tfoot>
                              <tr>
                                <th class="d-none">ID Posicion</th>
                                <th>Conjunto</th>
                                <th>Cant. conj.</th>
                                <th>Posicion</th>
                                <th>Cant. pos.</th>
                                <th>Cant. total</th>
                                <th>Material</th>
                                <th>Procesos</th>
                                <th>Cant. bajada</th>
                                <th>Saldo</th>
                              </tr>
                            </tfoot>
                            <tbody><?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $posiciones_agregadas=[];
                              //$sql = " SELECT lcc.nombre,lcc.cantidad AS cant_conj,lcp.posicion,lcp.cantidad AS cant_pos,m.concepto,GROUP_CONCAT(tp.tipo SEPARATOR ',' ) AS procesos, lcp.id AS id_posicion,COALESCE(SUM(otd.cantidad),0) AS cant_bajada_total,COALESCE(SUM(CASE WHEN otd.id_orden_trabajo = ? THEN otd.cantidad END),0) AS cant_bajada_ot FROM listas_corte_conjuntos lcc INNER JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto=lcc.id INNER JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion=lcp.id INNER JOIN materiales m ON lcp.id_material=m.id INNER JOIN tipos_procesos tp ON lcpr.id_tipo_proceso=tp.id LEFT JOIN ordenes_trabajo_detalle otd ON otd.id_posicion=lcp.id WHERE lcc.id_lista_corte = ? GROUP BY otd.id_orden_trabajo, lcp.id";
                              $sql = "SELECT lcc.nombre, lcc.cantidad AS cant_conj,
                                lcp.posicion, lcp.cantidad AS cant_pos,
                                m.concepto,
                                GROUP_CONCAT(DISTINCT tp.tipo SEPARATOR ',') AS procesos,
                                lcp.id AS id_posicion,
                                COALESCE(otd.cant_bajada_total,0) AS cant_bajada_total,
                                COALESCE(otd.cant_bajada_ot,0)     AS cant_bajada_ot
                              FROM listas_corte_conjuntos lcc
                              JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto = lcc.id
                              JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion = lcp.id
                              JOIN materiales m ON lcp.id_material = m.id
                              JOIN tipos_procesos tp ON lcpr.id_tipo_proceso = tp.id
                              LEFT JOIN (
                                SELECT id_posicion,
                                  SUM(cantidad) AS cant_bajada_total,
                                  SUM(CASE WHEN id_orden_trabajo = ? THEN cantidad END) AS cant_bajada_ot
                                FROM ordenes_trabajo_detalle
                                GROUP BY id_posicion
                              ) otd ON otd.id_posicion = lcp.id
                              WHERE lcc.id_lista_corte = ?
                              GROUP BY lcp.id";

                              //echo $sql;
                              $q = $pdo->prepare($sql);
                              $q->execute([$editing ? $id_orden_trabajo : 0,$id_lista_corte]);
                              while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                                $total_bajada = $row['cant_bajada_total'];
                                $cant_ot      = $row['cant_bajada_ot'];
                                $cant_otros   = $total_bajada - $cant_ot;
                                $cant_total   = $row['cant_conj'] * $row['cant_pos'];
                                $saldo        = $cant_total - $total_bajada;
                                if ($editing && $cant_ot > 0) {
                                  $posiciones_agregadas[] = [
                                    'id_posicion'       => $row['id_posicion'],
                                    'nombre'            => $row['nombre'],
                                    'cant_conj'         => $row['cant_conj'],
                                    'posicion'          => $row['posicion'],
                                    'cant_pos'          => $row['cant_pos'],
                                    'concepto'          => $row['concepto'],
                                    'procesos'          => $row['procesos'],
                                    'cant_bajada'       => $cant_ot,
                                    'cant_bajada_otros' => $cant_otros
                                  ];
                                  $cant_bajada=$cant_otros;
                                } else {
                                  $cant_bajada=$total_bajada;
                                }?>
                                <tr id="<?=$row['id_posicion']?>" data-cant-bajada="<?=$cant_bajada?>" data-cant-total="<?=$cant_total?>">
                                  <td class="d-none"><?=$row['id_posicion']?></td>
                                  <td><?=$row['nombre']?></td>
                                  <td class="text-end"><?=$row['cant_conj']?></td>
                                  <td class="text-end"><?=$row['posicion']?></td>
                                  <td class="text-end"><?=$row['cant_pos']?></td>
                                  <td class="text-end"><?=$cant_total?></td>
                                  <td><?=$row['concepto']?></td>
                                  <td><?=$row['procesos']?></td>
                                  <td class="text-end"><?=$cant_bajada?></td>
                                  <td class="text-end"><?=$saldo?></td>
                                </tr><?php
                              }
                              Database::disconnect();?>
                            </tbody>
                          </table>
                        </div>
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
                                <th>Conjunto</th>
                                <th style="width:80px">Cant. conj.</th>
                                <th style="width:80px">Posicion</th>
                                <th style="width:80px">Cant. pos.</th>
                                <th style="width:90px">Cant. total</th>
                                <th style="min-width:220px">Material</th>
                                <th style="min-width:150px">Procesos</th>
                                <th style="width:90px">Saldo</th>
                                <th style="width:90px">Cant. a Bajar</th>
                              </tr>
                            </thead>
                            <tfoot>
                              <tr>
                                <th>Conjunto</th>
                                <th>Cant. conj.</th>
                                <th>Posicion</th>
                                <th>Cant. pos.</th>
                                <th>Cant. total</th>
                                <th>Material</th>
                                <th>Procesos</th>
                                <th>Saldo</th>
                                <th>Cant. a Bajar</th>
                              </tr>
                            </tfoot>
                            <tbody><?php
                              if($editing){
                                foreach($posiciones_agregadas as $row){
                                  $cant_total = $row['cant_conj']*$row['cant_pos'];
                                  $max = $cant_total - $row['cant_bajada_otros'];
                                  $saldo = $max - $row['cant_bajada'];?>
                                  <tr id="<?=$row['id_posicion']?>">
                                    <td><?=$row['nombre']?></td>
                                    <td class="text-end"><?=$row['cant_conj']?></td>
                                    <td class="text-end"><?=$row['posicion']?></td>
                                    <td class="text-end"><?=$row['cant_pos']?></td>
                                    <td class="text-end"><?=$cant_total?></td>
                                    <td><?=$row['concepto']?></td>
                                    <td><?=$row['procesos']?></td>
                                    <td class="text-end"><?=$saldo?></td>
                                    <td>
                                      <input type="hidden" name="id_posicion[]" value="<?=$row['id_posicion']?>">
                                      <input type="number" step="0.01" class="form-control cantidad-bajar" name="cantidad_bajar[]" value="<?=$row['cant_bajada']?>" max="<?=$max?>" data-saldo="<?=$max?>" min="0">
                                    </td>
                                  </tr><?php
                                }
                              }?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?=$editing ? 'Modificar' : 'Crear'?></button>
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
      var tablaLC = $('#tablaLC');
      var tablaOT = $('#tablaOT');
      var tablaLCDT;

      $(document).ready(function () {

        let datatableDefault={
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
        }

        // Setup - add a text input to each footer cell
        tablaLC.find('tfoot th').each( function () {
          var title = $(this).text();
          if(title !== '' && !$(this).hasClass('d-none')){
            $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
          }
        } );

        tablaLCDT = tablaLC.DataTable(Object.assign({}, datatableDefault, {
          columnDefs: [
            { targets: 0, visible: false },
            { targets: 1, width: '100px' },
            { targets: 2, width: '40px' },
            { targets: 3, width: '40px' },
            { targets: 4, width: '40px' },
            { targets: 5, width: '40px' },
            { targets: 6, width: '220px' },
            { targets: 7, width: '100px' },
            { targets: 8, width: '40px' },
            { targets: 9, width: '40px' }
          ],
          select: {
            style: 'multi'
          },
          order: [[0, 'asc']]
        }));

        tablaLCDT.on('select deselect', updateToggleButton);

        //populate filtros
        var conjuntos = tablaLCDT.column(1).data().unique().sort();
        //$('#filtro_conjunto').append('<option value="">- Todos los conjuntos -</option>');
        conjuntos.each(function(d){
          $('#filtro_conjunto').append('<option value="'+d+'">'+d+'</option>');
        });
        var materiales = tablaLCDT.column(6).data().unique().sort();
        materiales.each(function(m){
          $('#filtro_material').append('<option value="'+m+'">'+m+'</option>');
        });
        var procesosSet = new Set();
        tablaLCDT.column(7).data().each(function(d){
          d.split(',').forEach(function(p){
            procesosSet.add(p.trim());
          });
        });
        //$('#filtro_proceso').append('<option value="">- Todos los procesos -</option>');
        Array.from(procesosSet).sort().forEach(function(p){
          $('#filtro_proceso').append('<option value="'+p+'">'+p+'</option>');
        });

        $('#filtro_conjunto').select2({placeholder:'Conjunto',allowClear:true});
        $('#filtro_proceso').select2({placeholder:'Proceso',allowClear:true});
        $('#filtro_material').select2({placeholder:'Material',allowClear:true});

        $('#filtro_conjunto').on('change', function () {
          var selected = $(this).val(); // array de valores seleccionados
          var search = selected && selected.length
            ? selected.map(val => '^' + $.fn.dataTable.util.escapeRegex(val) + '$').join('|')
            : '';
          tablaLCDT.column(1).search(search, true, false).draw(); // regex=true, smart=false
        });

        $('#filtro_proceso').on('change', function () {
          var selected = $(this).val();
          var search = selected && selected.length
            ? selected.map(val => $.fn.dataTable.util.escapeRegex(val)).join('|')
            : '';
          tablaLCDT.column(7).search(search, true, false).draw();
        });

        $('#filtro_material').on('change', function () {
          var selected = $(this).val();
          var search = selected && selected.length
            ? selected.map(val => '^' + $.fn.dataTable.util.escapeRegex(val) + '$').join('|')
            : '';
          tablaLCDT.column(6).search(search, true, false).draw();
        });


        // Apply the search
        tablaLCDT.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );
        // Botón para seleccionar o deseleccionar según estado
        $('#toggle_seleccion').on('click', function(){
          var rows = tablaLCDT.rows({search:'applied'});
          var allSelected = rows.count() > 0 && rows.count() === tablaLCDT.rows({search:'applied', selected:true}).count();

          if(allSelected){
            rows.deselect();
          } else {
            rows.select();
          }

          updateToggleButton();
        });

        tablaLCDT.on('draw', updateToggleButton);
        updateToggleButton();
        $("#link_agregar_posiciones").on("click",function(){
          var selectedRowsLC = tablaLCDT.rows({ selected: true });
          if(selectedRowsLC.count()>0){
            let newData=selectedRowsLC.data().map(function(elemento){
              let saldo = elemento[9];
              let inputCantidad = `
                <input type="hidden" name="id_posicion[]" value="${elemento["DT_RowId"]}">
                <input type="number" step="0.01" class="form-control cantidad-bajar" name="cantidad_bajar[]" value="${saldo}" data-saldo="${saldo}" max="${saldo}" min="0">
                <div class="invalid-feedback">La cantidad no puede superar el saldo (${saldo}).</div>
              `;
              return [
                elemento[1],
                elemento[2],
                elemento[3],
                elemento[4],
                elemento[5],
                elemento[6],
                elemento[7],
                saldo,
                inputCantidad
              ];
            })
            tablaOT.DataTable().rows.add(newData).draw();
            $(selectedRowsLC.nodes()).hide();
            selectedRowsLC.deselect();
            console.log("entro 3");
            refreshCantidades();
          /*if(selectedRowsLC.count()>0){
            let newData=selectedRowsLC.data().toArray().map(function(elemento){
              return [
                elemento[1],
                elemento[2],
                elemento[3],
                elemento[4],
                elemento[5],
                `<input type="hidden" name="id_posicion[]" value="${elemento[1]}">
                <input type="number" step="0.01" class="form-control" name="cantidad_bajar[]">`
              ];
            });*/
          }else{
            alert("Por favor seleccione una posicion para agregar a la Orden de trabajo")
          }
        });
		
		    // Setup - add a text input to each footer cell
        tablaOT.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );

	      tablaOT.DataTable({
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
		
		   //$(document).find(tablaOT).find(" tbody tr td").not(":last-child").on( 'click', function () {
        //tablaOT.on('click',"tbody tr td", function () {
        $(document).on("click","#tablaOT tbody tr td", function(){
          var t=$(this).parent();
          let celdaClickeado=$(this)[0];
          let celdaConInput=t.find("td:nth-child(9)")[0];
          if(celdaConInput!=celdaClickeado){
            if(t.hasClass('selected')){
              deselectRow(t);
            }else{
              tablaOT.DataTable().rows().nodes().each( function (rowNode, index) {
                $(rowNode).removeClass("selected");
              });
              selectRow(t);
            }
          }
        });

        // Apply the search
        tablaOT.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        $("#link_eliminar_posiciones").on("click",function(){
          var selectedRowsOT = tablaOT.DataTable().rows('.selected');
          if(selectedRowsOT[0].length>0){
            $(selectedRowsOT.nodes()).find("input[name='id_posicion[]']").each(function() {
              tablaLC.find("#"+$(this).val()).show()
            });
            //$(selectedRowsOT.nodes()).remove().draw();
            selectedRowsOT.remove().draw();
            console.log("entro 4");
            refreshCantidades();
          }else{
            alert("Por favor seleccione una posicion para eliminar")
          }
        });
    
        $(document).on('input change',"input[name='cantidad_bajar[]']",function(){
          var saldo = parseFloat($(this).data('saldo'));
          var valor = parseFloat($(this).val());
          if(valor > saldo){
            $(this).val(saldo);
            $(this).addClass('is-invalid');
          }else{
            $(this).removeClass('is-invalid');
          }
          console.log("entro 1");
          refreshCantidades();
        });

        console.log("entro 2");
        refreshCantidades();
      });

      function refreshCantidades(){
        tablaLCDT.rows().every(function(){
          let row = $(this.node());
          let cantidad_bajada = parseFloat(row.data('cant-bajada')) || 0;
          let cantTotalPosiciones = parseFloat(row.data('cant-total')) || 0;
          let idPos = row.attr('id');
          let extra = 0;
          let input = tablaOT.find("input[name='id_posicion[]'][value='"+idPos+"']");
          if(input.length){
            let val = parseFloat(input.closest('tr').find("input[name='cantidad_bajar[]']").val());
            if(!isNaN(val)) extra = val;
          }
          let total = cantidad_bajada + extra;
          let saldo = cantTotalPosiciones - total;
          /*let cells = row.children('td');
          $(cells[8]).text(total);
          $(cells[9]).text(saldo);*/
          const rowIdx = tablaLCDT.row(row).index();
          tablaLCDT.cell(rowIdx, 8).data(total); // Col 8 = Cant. bajada (contando la oculta)
          tablaLCDT.cell(rowIdx, 9).data(saldo); // Col 9 = Saldo

        });

        tablaOT.find('tbody tr').each(function(){
          let input = $(this).find("input[name='cantidad_bajar[]']");
          let saldo = parseFloat(input.data('saldo')) || 0;
          let val = parseFloat(input.val()) || 0;
          let restante = saldo - val;
          $(this).find('td:nth-child(8)').text(restante);
        });
      }
	  
	    function order(a, b) {
        return b.age - a.age;
      }
      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
      function updateToggleButton(){
        var rows = tablaLCDT.rows({search:'applied'});
        var btn = $('#toggle_seleccion');
        var allSelected = rows.count() > 0 && tablaLCDT.rows({search:'applied', selected:true}).count() === rows.count();
        if(allSelected){
          btn.text('Deseleccionar todo').removeClass('btn-primary').addClass('btn-secondary');
        }else{
          btn.text('Seleccionar visibles').removeClass('btn-secondary').addClass('btn-primary');
        }
      }
    </script>
  </body>
</html>
