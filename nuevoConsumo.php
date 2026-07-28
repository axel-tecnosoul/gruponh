<?php
require("config.php");
require 'database.php';

if (isset($_POST['action']) && $_POST['action'] == 'get_stock') {
    ob_clean(); header('Content-Type: application/json');
    $pdo = Database::connect();
    $id_proyecto = $_POST['id_proyecto'] ?? 0;
    
    $sql = "SELECT id.id AS id_detalle, id.saldo, c.id AS id_colada, c.nro_colada, i.nro, ed.id AS id_egreso_detalle, ed.cantidad_reservada
            FROM ingresos_detalle id 
            INNER JOIN ingresos i ON id.id_ingreso = i.id
            LEFT JOIN coladas c ON id.id_colada = c.id
            INNER JOIN egresos_detalle ed ON ed.id_detalle_ingreso = id.id
            INNER JOIN egresos e ON ed.id_egreso = e.id
            WHERE id.id_material = ? AND ed.cantidad_reservada > 0 AND e.id_proyecto = ?"; 
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['id_material'], $id_proyecto]);
    $data = [];
    while($row = $q->fetch(PDO::FETCH_ASSOC)){
        $colada = !empty($row['nro_colada']) ? $row['nro_colada'] : '(Sin Colada)';
        $data[] = [
            'id' => $row['id_detalle'], 
            'text' => "Ingreso #".$row['nro']." - ".$colada." (Reservado: ".$row['cantidad_reservada'].")", 
            'id_colada' => $row['id_colada'], 
            'nro_colada' => $colada,
            'id_egreso_detalle' => $row['id_egreso_detalle'],
            'cantidad_reservada' => $row['cantidad_reservada']
        ];
    }
    echo json_encode($data);
    Database::disconnect();
    exit;
}

if (!empty($_POST)) {
  try {
    // insert data
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $modoDebug=0;

    if($modoDebug==1){
      $pdo->beginTransaction();
      var_dump($_POST);
      var_dump($_GET);
    }

    $redirect="listarConsumos.php";

    $nro_revision=0;
    $descripcion="Emision original";

    $sql = "INSERT INTO consumos (fecha,id_orden_trabajo_revision,nro_revision,descripcion,id_usuario,anulado) VALUES (NOW(),?,?,?,?,0)";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST["id_orden_trabajo"],$nro_revision,$descripcion,$_SESSION["user"]["id"]]);
    $id_consumo = $pdo->lastInsertId();

    $aEgresos=[];
    $aIngresos=[];
    foreach ($_POST["id_material"] as $key => $id_material) {

      $situacion=$_POST['situacion'][$key];
      $id_detalle_ingreso = $_POST['id_detalle_ingreso'][$key] ?? null;
      $id_egreso_detalle = $_POST['id_egreso_detalle'][$key] ?? null;

      $id_colada_val = !empty($_POST['id_colada'][$key]) ? intval($_POST['id_colada'][$key]) : null;

      $sql = "INSERT INTO consumos_detalle (id_consumo, id_material, id_colada, situacion, cantidad, id_unidad_medida, observacion) VALUES (?,?,?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_consumo, $id_material, $id_colada_val, $situacion, $_POST['cantidad'][$key], $_POST['id_unidad_medida'][$key], $_POST['observacion'][$key]]);

      $aux=[
        "id_material"=>$id_material,
        "id_unidad_medida"=>$_POST['id_unidad_medida'][$key],
        "cantidad"=>$_POST['cantidad'][$key],
        "id_colada"=>$id_colada_val,
        "observacion"=>$_POST['observacion'][$key],
        "id_detalle_ingreso" => $id_detalle_ingreso,
        "id_egreso_detalle" => $id_egreso_detalle
      ];
      if($situacion=="Consumo"){
        //cargamos los datos para registrar un egreso
        $aEgresos[]=$aux;
      }else{//Sobrante
        //cargamos los datos para registrar un ingreso
        $aIngresos[]=$aux;
      }
    }

    if(count($aEgresos)>0){
    
      $sqlUpdEgresoDetalle = "UPDATE egresos_detalle SET cantidad_reservada = cantidad_reservada - ?, cantidad_efectivizada = cantidad_efectivizada + ? WHERE id = ?";
      $qUpdEgresoDetalle = $pdo->prepare($sqlUpdEgresoDetalle);
      
      $sqlUpdIngreso = "UPDATE ingresos_detalle SET saldo = saldo - ?, cantidad_egresada = cantidad_egresada + ? WHERE id = ?";
      $qUpdIngreso = $pdo->prepare($sqlUpdIngreso);
      
      foreach ($aEgresos as $key => $value) {
          
          $id_origen = $value['id_detalle_ingreso'];
          $id_egreso_det = $value['id_egreso_detalle'];
          $cantidad = $value["cantidad"];
          
          if($id_egreso_det){
              $qUpdEgresoDetalle->execute([$cantidad, $cantidad, $id_egreso_det]);
          } else {
              if($id_origen){
                  $qUpdIngreso->execute([$cantidad, $cantidad, $id_origen]);
              }
          }
      }

      $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Consumo efectivizado de reserva','Egresos','')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));
    }

    if(count($aIngresos)>0){
      $id_tipo_ingreso=2;
      $nro=1;
      $lugar_entrega="";
      $id_cuenta_recibe = !empty($_POST['id_cuenta_recibe_sobrante']) ? intval($_POST['id_cuenta_recibe_sobrante']) : 1;
      
      $observaciones="Ingreso por sobrante de consumo";

      $sql = "INSERT INTO ingresos (fecha_hora, id_tipo_ingreso, nro, id_cuenta_recibe, lugar_entrega, observaciones) VALUES (now(),?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_tipo_ingreso, $nro, $id_cuenta_recibe, $lugar_entrega, $observaciones]);
      $idIngreso = $pdo->lastInsertId();
      
      foreach ($aIngresos as $key => $value) {
        $sql = "INSERT INTO ingresos_detalle (id_ingreso, id_material, id_unidad_medida, cantidad, cantidad_egresada, saldo) VALUES (?,?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$idIngreso,$value["id_material"],$value["id_unidad_medida"],$value['cantidad'],0,$value['cantidad']]);

        if (!empty($value['id_egreso_detalle'])) {
          $sqlUpdReservaDev = "UPDATE egresos_detalle SET cantidad_reservada = cantidad_reservada - ? WHERE id = ?";
          $qUpdReservaDev = $pdo->prepare($sqlUpdReservaDev);
          $qUpdReservaDev->execute([$value['cantidad'], $value['id_egreso_detalle']]);
        }
      }
      
      $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo ingreso por devolución','Ingresos','verIngreso.php?id=$idIngreso')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva Consumo','Consumos','verConsumo.php?id=$id_consumo')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      echo "redirect: ".$redirect;
      $pdo->rollBack();
      die();
    } else {
      Database::disconnect();
      header("Location: ".$redirect);
    }
  } catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
      $pdo->rollBack();
    }

    $idOrdenTrabajoErr = isset($_POST['id_orden_trabajo']) ? intval($_POST['id_orden_trabajo']) : 0;
    error_log('Error nuevoConsumo.php: '.$e->getMessage());

    $_SESSION['error_message'] = 'No se pudo crear el consumo. Revise si la reserva seleccionada tiene colada.';
    Database::disconnect();
    header('Location: nuevoConsumo.php?id_orden_trabajo='.$idOrdenTrabajoErr);
    exit;
  }
}

Database::disconnect();

$id_ot = $_GET['id_orden_trabajo'] ?? null;
$infoOT = '';
$id_proyecto_vista = 0;
if ($id_ot) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sqlInfo = "SELECT ot.nro_orden_trabajo, lc.numero AS numero_lc, lc.nro_revision, lc.id_proyecto
              FROM ordenes_trabajo ot
              JOIN listas_corte lc ON ot.id_lista_corte = lc.id
              WHERE ot.id = ?";
  $q = $pdo->prepare($sqlInfo);
  $q->execute([$id_ot]);
  $data_ot = $q->fetch(PDO::FETCH_ASSOC);
  $id_proyecto_vista = $data_ot['id_proyecto'];
  $descProyecto = getDescripcionProyecto($pdo, $data_ot['id_proyecto']);
  $infoOT = 'OT N° '.$data_ot['nro_orden_trabajo'].' - LC N° '.$data_ot['numero_lc'].' - Rev '.$data_ot['nro_revision'].' '.htmlspecialchars($descProyecto);
  Database::disconnect();
}
$ubicacion = "Nuevo Consumo" . ($infoOT ? ' ' . $infoOT : '');
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>table.dataTable tbody tr.selected {background-color:#b0bed9 !important;}</style>
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
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card mb-0">
                  <div class="card-header">
                    <h5>Resumen <?=$infoOT?></h5>
                  </div>
                  <div class="card-body">
                    <div class="form-group row">
                      <div class="dt-ext table-responsive">
                        <table class="display" id="tablaMaterialesOT">
                          <thead>
                            <tr>
                              <th>Material</th>
                              <th>Largo (mm)</th>
                              <th>M2</th>
                              <th>Barras/Chapas</th>
                              <th>Area pintable</th>
                            </tr>
                          </thead>
                          <tfoot>
                            <tr>
                              <th>Material</th>
                              <th>Largo (mm)</th>
                              <th>M2</th>
                              <th>Barras/Chapas</th>
                              <th>Area pintable</th>
                            </tr>
                          </tfoot>
                          <tbody><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sql = "SELECT m.concepto, SUM(lcp.largo) AS largo, lcp.id_material
                                    FROM ordenes_trabajo_detalle otd
                                    INNER JOIN lista_corte_posiciones lcp ON otd.id_posicion = lcp.id
                                    INNER JOIN materiales m ON lcp.id_material = m.id
                                    WHERE otd.id_orden_trabajo = ?
                                    GROUP BY lcp.id_material";
                            $q = $pdo->prepare($sql);
                            $q->execute([$id_ot]);
                            while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo '<tr data-id-material="'.$row['id_material'].'" style="cursor:pointer">';
                              echo '<td>' . htmlspecialchars($row["concepto"]) . '</td>';
                              echo '<td>' . htmlspecialchars($row["largo"]) . '</td>';
                              echo '<td>0</td>';
                              echo '<td>0</td>';
                              echo '<td>0</td>';
                              echo '</tr>';
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
                    <h5><?=$ubicacion?></h5>
                  </div><?php
                  if (!empty($_SESSION['error_message'])){?>
                    <div class="alert alert-warning" role="alert" style="margin:15px; padding:8px 12px; font-size:13px;">
                      <?=htmlspecialchars($_SESSION['error_message'])?>
                    </div><?php
                    unset($_SESSION['error_message']);
                  }?>
                  <form id="formInput">
                    <input type="hidden" id="id_proyecto_ajax" value="<?=$id_proyecto_vista?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row" style="display:none;">
                              <label class="col-sm-3 col-form-label">Material(*)</label>
                              <div class="col-sm-9">
                                <select name="id_material" id="id_material" class="col-sm-12" autofocus required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                // Parametrizamos el id de OT para evitar inyección
                                $sqlZon = "SELECT lcp.id_material, m.concepto
                                           FROM ordenes_trabajo_detalle otd
                                           INNER JOIN lista_corte_posiciones lcp ON otd.id_posicion = lcp.id
                                           INNER JOIN materiales m ON lcp.id_material = m.id
                                           WHERE otd.id_orden_trabajo = ? GROUP BY lcp.id_material";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute([$id_ot]);
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  $concepto = htmlspecialchars($fila['concepto']);
                                  ?>
                                  <option value='<?=$fila['id_material']?>' data-concepto='<?=$concepto?>'><?=$concepto?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Reserva (*)</label>
                            <div class="col-sm-9">
                              <select id="id_ingreso_seleccionado" class="js-example-basic-single col-sm-12" required>
                                <option value="">Seleccione material primero...</option>
                              </select>
                              <small id="msgSinColada" class="form-text text-muted" style="display:none;">Esta reserva no tiene colada asignada.</small>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Situacion(*)</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="edo-ani">
                                <input class="radio_animated" value="Consumo" required id="edo-ani" type="radio" name="situacion"><label for="edo-ani">Consumo</label>
                              </label>
                              <label class="d-block" for="edo-ani1">
                                <input class="radio_animated" value="Sobrante" required id="edo-ani1" type="radio" name="situacion"><label for="edo-ani1">Sobrante</label>
                              </label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9"><input type="number" step="0.01" name="cantidad" required class="form-control"></div>
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
                            <label class="col-sm-3 col-form-label">Observacion</label>
                            <div class="col-sm-9"><input type="text" name="observacion" class="form-control"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" id="btnAgregarConsumo" class="btn btn-primary">Agregar</button>
                        <!-- <button type="submit" id="btnEditarConsumo" class="btn btn-primary">Agregar</button> -->
                      </div>
                    </div>
                  </form>
                </div>

                <div class="card mb-0">
                  <div class="card-header">
                    <h5>
                      Detalle de Consumos y Sobrantes
                      <img src="img/icon_baja.png" id="link_eliminar_consumo" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">&nbsp;&nbsp;
                      <!-- <img src="img/icon_modificar.png" id="link_modificar_consumo" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Modificar" title="Modificar">&nbsp;&nbsp; -->
                    </h5>
                  </div>
                  <form id="formCrearConsumo" action="nuevoConsumo.php" method="post">
                    <!-- Alineado: ahora enviamos id_orden_trabajo (no *_revision) -->
                    <input type="hidden" name="id_orden_trabajo" id="id_orden_trabajo" value="<?=$id_ot?>">
                    <!-- Hidden que se llena desde el modal de sobrante -->
                    <input type="hidden" name="id_cuenta_recibe_sobrante" id="hiddenCuentaRecibeSobrante" value="">
                    <div class="card-body">
                      <div class="form-group row">
                        <div class="dt-ext table-responsive">
                          <table class="display" id="tablaConsumos">
                          <thead>
                              <tr>
                                <th>Material</th>
                                <th>Colada</th>
                                <th>Situacion</th>
                                <th>Cantidad</th>
                                <th>Medida</th>
                                <th>Observacion</th>
                              </tr>
                            </thead>
                            <tfoot>
                              <tr>
                                <th>Material</th>
                                <th>Colada</th>
                                <th>Situacion</th>
                                <th>Cantidad</th>
                                <th>Medida</th>
                                <th>Observacion</th>
                              </tr>
                            </tfoot>
                            <tbody></tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" class="btn btn-primary">Crear</button>
                        <a href='listarOrdenesTrabajo.php' class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>

              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        
        <!-- Modal Eliminar Conjunto -->
        <div class="modal fade" id="eliminarConjunto" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el conjunto?</div>
              <div class="modal-footer">
                <a href="#" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal">Volver</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Eliminar Posición -->
        <div class="modal fade" id="eliminarPosicion" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar la posicion?</div>
              <div class="modal-footer">
                <a href="#" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal">Volver</button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal fade" id="sobranteModal" tabindex="-1" role="dialog" aria-labelledby="sobranteModalLabel">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <form id="sobranteForm">
                <div class="modal-header">
                  <h5 class="modal-title" id="sobranteModalLabel">Datos de Devolución por Sobrante</h5>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                  <div class="form-group row">
                    <label for="inputRecibeSobrante" class="col-sm-3 col-form-label">Recibe(*)</label>
                    <div class="col-sm-9">
                      <select name="id_cuenta_recibe_sobrante" id="inputRecibeSobrante" class="js-example-basic-single col-sm-12" required="required">
                        <option value="">Seleccione...</option>
                        <?php
                        $pdo = Database::connect();
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta IN (4) AND activo = 1 AND anulado = 0";
                        $q = $pdo->prepare($sqlZon);
                        $q->execute();
                        while ($fila = $q->fetch(PDO::FETCH_ASSOC)) { ?>
                          <option value='<?= $fila['id'] ?>'><?= $fila['nombre'] ?></option>
                        <?php
                        }
                        Database::disconnect(); ?>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-primary">Confirmar y Crear</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <?php include("footer.php"); ?>
      </div>
    </div>
    <!-- Scripts -->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
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
    <script src="assets/js/script.js"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script>
      $(document).ready(function () {
        var tablaMaterialesOT = $('#tablaMaterialesOT');
        var tablaConsumos = $('#tablaConsumos');

        let datatableDefault = {
          stateSave: false,
          responsive: false,
          language: {
            "decimal": "",
            "emptyTable": "No hay información",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
            "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
            "infoFiltered": "(Filtrado de _MAX_ total registros)",
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
        };

        // Tabla Materiales OT
        tablaMaterialesOT.find('tfoot th').each(function () {
          var title = $(this).text();
          $(this).html('<input type="text" size="'+title.length+'" placeholder="'+title+'" />');
        });
        tablaMaterialesOT.DataTable(datatableDefault);
        tablaMaterialesOT.DataTable().columns().every(function () {
          var that = this;
          $('input', this.footer()).on('keyup change', function () {
            if (that.search() !== this.value) {
              that.search(this.value).draw();
            }
          });
        });

        // Click en tabla materiales OT
        $(document).on("click", "#tablaMaterialesOT tbody tr td", function () {
          var t = $(this).parent();
          let idMaterial = t.data('id-material');
          if (t.hasClass('selected')) {
            deselectRow(t);
          } else {
            tablaMaterialesOT.DataTable().rows().nodes().each(function (rowNode) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            if (idMaterial) {
              $('select[name="id_material"]').val(idMaterial).trigger('change');
            }
          }
        });

        // Select2 para ingreso
        $('#id_ingreso_seleccionado').select2();

        function actualizarAvisoColada() {
          let idColadaSeleccionada = $('#id_ingreso_seleccionado').find('option:selected').data('id-colada') || '';
          if (idColadaSeleccionada) {
            $('#msgSinColada').hide();
          } else {
            let textoSeleccionado = $('#id_ingreso_seleccionado').find('option:selected').text() || '';
            if (textoSeleccionado && textoSeleccionado.indexOf('Seleccione') !== 0 && textoSeleccionado.indexOf('No hay reservas') !== 0 && textoSeleccionado.indexOf('Error al cargar') !== 0 && textoSeleccionado.indexOf('Todas las reservas') !== 0) {
              $('#msgSinColada').show();
            } else {
              $('#msgSinColada').hide();
            }
          }
        }

        $('#id_ingreso_seleccionado').on('change', function () {
          actualizarAvisoColada();
        });

        function getUsedReserves() {
          let used = [];
          $("#tablaConsumos tbody input[name='id_egreso_detalle[]']").each(function () {
            let val = $(this).val();
            if (val && val != "0") used.push(val);
          });
          return used;
        }

        // Cambio de material -> cargar reservas
        $('select[name="id_material"]').on('change', function () {
          var id_material = $(this).val();
          var id_proyecto = $('#id_proyecto_ajax').val();
          var selectIngresos = $('#id_ingreso_seleccionado');
          selectIngresos.empty().trigger('change');

          if (id_material) {
            selectIngresos.append(new Option("Cargando...", "", true, true));
            $.ajax({
              url: 'nuevoConsumo.php',
              type: 'POST',
              data: { action: 'get_stock', id_material: id_material, id_proyecto: id_proyecto },
              dataType: 'json',
              success: function (data) {
                selectIngresos.empty();
                let usedReserves = getUsedReserves();
                if (data.length > 0) {
                  selectIngresos.append(new Option("Seleccione Reserva...", ""));
                  let countAvailable = 0;
                  $.each(data, function (index, item) {
                    if (usedReserves.includes(item.id_egreso_detalle.toString())) return;
                    var option = new Option(item.text, item.id);
                    $(option).data('id-colada', item.id_colada);
                    $(option).data('nro-colada', item.nro_colada);
                    $(option).data('id-egreso-detalle', item.id_egreso_detalle);
                    $(option).data('cantidad-reservada', item.cantidad_reservada);
                    selectIngresos.append(option);
                    countAvailable++;
                  });
                  if (countAvailable === 0) {
                    selectIngresos.append(new Option("Todas las reservas disponibles ya han sido agregadas", ""));
                  }
                } else {
                  selectIngresos.append(new Option("No hay reservas para este proyecto", ""));
                }
                selectIngresos.trigger('change');
                actualizarAvisoColada();
              },
              error: function () {
                selectIngresos.empty();
                selectIngresos.append(new Option("Error al cargar datos", ""));
                actualizarAvisoColada();
              }
            });
          } else {
            selectIngresos.append(new Option("Seleccione material primero...", ""));
            actualizarAvisoColada();
          }
        });

        // Click en tabla consumos
        $(document).on("click", "#tablaConsumos tbody tr td", function () {
          var t = $(this).parent();
          if (t.hasClass('selected')) {
            deselectRow(t);
          } else {
            tablaConsumos.DataTable().rows().nodes().each(function (rowNode) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
          }
        });

        // Tabla Consumos
        tablaConsumos.find('tfoot th').each(function () {
          var title = $(this).text();
          $(this).html('<input type="text" size="'+title.length+'" placeholder="'+title+'" />');
        });
        tablaConsumos.DataTable(datatableDefault);
        tablaConsumos.DataTable().columns().every(function () {
          var that = this;
          $('input', this.footer()).on('keyup change', function () {
            if (that.search() !== this.value) {
              that.search(this.value).draw();
            }
          });
        });

        // Eliminar consumo de la tabla
        $("#link_eliminar_consumo").on("click", function () {
          var selectedRowsOT = tablaConsumos.DataTable().rows('.selected');
          if (selectedRowsOT[0].length > 0) {
            selectedRowsOT.remove().draw();
            var currentMaterial = $('select[name="id_material"]').val();
            if (currentMaterial) {
              $('select[name="id_material"]').trigger('change');
            }
          } else {
            alert("Por favor seleccione una posicion para eliminar");
          }
        });

        // Agregar ítem al detalle
        $("#formInput").on("submit", function (e) {
          e.preventDefault();
          let select_id_material = $("select[name='id_material']");
          let id_material = select_id_material.val();
          let selected_option_id_material = select_id_material.find("option[value='" + id_material + "']");
          let concepto = selected_option_id_material.data("concepto");

          let select_ingreso = $('#id_ingreso_seleccionado');
          let id_detalle_ingreso = select_ingreso.val();
          let nro_colada = select_ingreso.find('option:selected').data('nro-colada') || "(Sin Colada)";
          let id_colada = select_ingreso.find('option:selected').data('id-colada') || '';
          let id_egreso_detalle = select_ingreso.find('option:selected').data('id-egreso-detalle') || 0;
          let cantidad_reservada = select_ingreso.find('option:selected').data('cantidad-reservada') || 0;

          let situacion = $("input[name='situacion']:checked").val();

          if (situacion == "Consumo" && !id_detalle_ingreso) {
            alert("Seleccione una reserva para consumir");
            return;
          }

          let cantidad = $("input[name='cantidad']").val();

          if (situacion == "Consumo" && parseFloat(cantidad) > parseFloat(cantidad_reservada)) {
            alert("La cantidad no puede exceder lo reservado (" + cantidad_reservada + ")");
            return;
          }

          let select_id_unidad_medida = $("select[name='id_unidad_medida']");
          let id_unidad_medida = select_id_unidad_medida.val();
          let selected_option_id_unidad_medida = select_id_unidad_medida.find("option[value='" + id_unidad_medida + "']").text();

          let observacion = $("input[name='observacion']").val();

          let newConsumo = [
            `<input type="hidden" name="id_material[]" class="id_material" value="${id_material}"><input type="hidden" name="id_detalle_ingreso[]" value="${id_detalle_ingreso}"><input type="hidden" name="id_egreso_detalle[]" value="${id_egreso_detalle}">` + concepto,
            `<input type="hidden" name="id_colada[]" class="id_colada" value="${id_colada}">` + nro_colada,
            `<input type="hidden" name="situacion[]" class="situacion" value="${situacion}">` + situacion,
            `<input type="hidden" name="cantidad[]" class="cantidad" value="${cantidad}">` + cantidad,
            `<input type="hidden" name="id_unidad_medida[]" class="id_unidad_medida" value="${id_unidad_medida}">` + selected_option_id_unidad_medida,
            `<input type="hidden" name="observacion[]" class="observacion" value="${observacion}">` + observacion
          ];

          tablaConsumos.DataTable().row.add(newConsumo).draw();

          if (id_detalle_ingreso) {
            $("#id_ingreso_seleccionado option:selected").remove();
            $("#id_ingreso_seleccionado").trigger('change');
          }

          limpiarFormInput();
        });

        // =====================================================================
        // LÓGICA DEL MODAL DE SOBRANTE AL CREAR
        // =====================================================================

        // Función para detectar si hay sobrantes en la tabla
        function haySobrantes() {
          let tiene = false;
          $("#tablaConsumos tbody .situacion").each(function () {
            if ($(this).val() === "Sobrante") {
              tiene = true;
              return false;
            }
          });
          return tiene;
        }

        // Inicializar Select2 en el modal cuando se abre
        $('#sobranteModal').on('shown.bs.modal', function () {
          $('#inputRecibeSobrante').select2({
            dropdownParent: $('#sobranteModal'),
            width: '100%',
            placeholder: 'Seleccione...',
            allowClear: true
          });
        });

        // Variable para saber si el modal ya fue confirmado
        let datosRecibeSobranteListos = false;

        // Interceptar submit del form principal
        $('#formCrearConsumo').on('submit', function (e) {
          // Validar que hay al menos un ítem
          let totalItems = tablaConsumos.DataTable().rows().count();
          if (totalItems === 0) {
            e.preventDefault();
            alert("Agregue al menos un ítem de consumo o sobrante.");
            return;
          }

          // Si ya confirmó el modal, dejamos pasar
          if (datosRecibeSobranteListos) {
            $(this).off('submit');
            return true;
          }

          // Si hay sobrantes, abrimos el modal
          if (haySobrantes()) {
            e.preventDefault();
            $('#sobranteModal').modal('show');
            return;
          }

          // Si no hay sobrantes, dejamos pasar directamente
        });

        // Al confirmar el modal de sobrante
        $('#sobranteForm').on('submit', function (e) {
          e.preventDefault();
          const recibe = $('#inputRecibeSobrante').val();

          if (!recibe) {
            return alert('Seleccione quién recibe la devolución.');
          }

          // Llenar el hidden del form principal
          $('#hiddenCuentaRecibeSobrante').val(recibe);

          datosRecibeSobranteListos = true;
          $('#sobranteModal').modal('hide');

          // Disparar de nuevo el submit
          $('#formCrearConsumo').submit();
        });
      });

      function limpiarFormInput() {
        $("input[name='situacion']").prop("checked", false);
        $("input[name='cantidad']").val("");
        $("input[name='observacion']").val("");
        $("#id_ingreso_seleccionado").val("").trigger("change");
        $('#msgSinColada').hide();
      }

      function selectRow(t) {
        t.addClass('selected');
      }
      function deselectRow(t) {
        t.removeClass('selected');
      }
    </script>
  </body>
</html>