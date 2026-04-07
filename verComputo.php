<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_exception_handler(function($e) {
  http_response_code(500);
  echo "<div style='padding:20px; background:#f2dede; border:1px solid red; color:red;'>";
  echo "<h3>[DEBUG] Excepcion no manejada</h3>";
  echo "<p><b>Archivo:</b> " . htmlspecialchars($e->getFile()) . " linea " . $e->getLine() . "</p>";
  echo "<p><b>Mensaje:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
  echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
  echo "</div>";
  exit();
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
  echo "<div style='padding:10px; background:#fff3cd; border:1px solid orange; color:#856404;'>";
  echo "<b>[DEBUG] Error PHP ($errno):</b> " . htmlspecialchars($errstr);
  echo " en " . htmlspecialchars($errfile) . " linea $errline";
  echo "</div>";
  return true;
});

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

if (null == $id) {
  header("Location: listarComputos.php");
}

if (!empty($_POST)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  try {
    $pdo->beginTransaction();

    $idComputo = $_GET['id'];
    $fecha = !empty($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');
    $lugar_entrega = !empty($_POST['lugar_entrega']) ? $_POST['lugar_entrega'] : '';
    $id_cuenta_recibe = !empty($_POST['id_cuenta_recibe']) ? $_POST['id_cuenta_recibe'] : 0;

    $userId = $_SESSION['user']['id'];
    $userCuentaId = 1;

    if (!empty($_POST['reservas_lote'])) {
      $sqlProy = "SELECT t.id_proyecto, p.id_sitio FROM computos c JOIN tareas t ON t.id = c.id_tarea JOIN proyectos p ON p.id = t.id_proyecto WHERE c.id = ?";
      $qProy = $pdo->prepare($sqlProy);
      $qProy->execute([$idComputo]);
      $rowProy = $qProy->fetch(PDO::FETCH_ASSOC);
      $idProyecto = $rowProy ? $rowProy['id_proyecto'] : null;
      $idSitio = $rowProy ? (int)$rowProy['id_sitio'] : 0;

      $sqlEgreso = "INSERT INTO egresos (fecha_hora, id_tipo_egreso, nro, id_cuenta_retira, id_sitio_destino, observaciones, id_proyecto) VALUES (NOW(), 2, ?, ?, ?, 'Reserva automatica', ?)";
      $qEgreso = $pdo->prepare($sqlEgreso);
      $qEgreso->execute([$idComputo, $userCuentaId, $idSitio, $idProyecto]);
      $idEgreso = $pdo->lastInsertId();

      $sqlInsDet  = "INSERT INTO egresos_detalle (id_egreso, id_material, id_detalle_ingreso, cantidad, cantidad_reservada, id_unidad_medida) VALUES (?,?,?,?,?,?)";
      $sqlUpdIng  = "UPDATE ingresos_detalle SET saldo = saldo - ?, cantidad_egresada = cantidad_egresada + ? WHERE id = ?";
      $sqlUpdComp = "UPDATE computos_detalle SET reservado = reservado + ? WHERE id = ?";

      $qInsDet = $pdo->prepare($sqlInsDet);
      $qUpdIng = $pdo->prepare($sqlUpdIng);
      $qUpdComp = $pdo->prepare($sqlUpdComp);

      foreach ($_POST['reservas_lote'] as $idCompDet => $lotes) {
        $totalReservadoItem = 0;
        foreach ($lotes as $loteKey => $cant) {
          $cant = (float)$cant;
          if ($cant > 0) {
            $idIngDet = (int)str_replace('ing_', '', $loteKey);

            $sqlMat = "SELECT i.id_material, m.id_unidad_medida FROM ingresos_detalle i JOIN materiales m ON i.id_material = m.id WHERE i.id = ?";
            $stmtMat = $pdo->prepare($sqlMat);
            $stmtMat->execute([$idIngDet]);
            $matRow = $stmtMat->fetch(PDO::FETCH_ASSOC);

            if ($matRow) {
              $qInsDet->execute([$idEgreso, $matRow['id_material'], $idIngDet, $cant, $cant, $matRow['id_unidad_medida']]);
              $qUpdIng->execute([$cant, $cant, $idIngDet]);
              $totalReservadoItem += $cant;
            }
          }
        }
        if ($totalReservadoItem > 0) {
          $qUpdComp->execute([$totalReservadoItem, $idCompDet]);
        }
      }
    }

    $items_pedidos = [];
    if (!empty($_POST['cantidad_pedir'])) {
      foreach ($_POST['cantidad_pedir'] as $id_detalle => $cantidad) {
        if ($cantidad > 0) {
          $sqlMat = "SELECT id_material FROM computos_detalle WHERE id = ?";
          $qMat = $pdo->prepare($sqlMat);
          $qMat->execute([$id_detalle]);
          $dMat = $qMat->fetch(PDO::FETCH_ASSOC);

          if ($dMat) {
            $items_pedidos[] = [
              'id_material' => $dMat['id_material'],
              'cantidad' => $cantidad
            ];
          }
        }
      }
    }

    if (!empty($items_pedidos)) {
      $sqlCab = "INSERT INTO pedidos (fecha, lugar_entrega, id_computo, id_cuenta_solicitante, id_cuenta_recibe, id_estado, fecha_carga) VALUES (?, ?, ?, ?, ?, 1, NOW())";
      $qCab = $pdo->prepare($sqlCab);
      $id_usuario = $_SESSION['user']['id_perfil'];
      $qCab->execute([$fecha, $lugar_entrega, $idComputo, $id_usuario, $id_cuenta_recibe]);
      $idPedido = $pdo->lastInsertId();

      $sqlDet = "INSERT INTO pedidos_detalle (id_pedido, id_material, cantidad, precio, id_proveedor) VALUES (?, ?, ?, 0, 0)";
      $qDet = $pdo->prepare($sqlDet);
      foreach ($items_pedidos as $item) {
        $qDet->execute([$idPedido, $item['id_material'], $item['cantidad']]);
      }
    }

    $sqlCheck = "SELECT 
            cd.cantidad AS solicitada, 
            cd.reservado, 
            cd.comprado, 
            COALESCE(SUM(pd.cantidad), 0) AS pedido_total
          FROM computos_detalle cd
          LEFT JOIN pedidos p ON p.id_computo = cd.id_computo AND p.anulado = 0
          LEFT JOIN pedidos_detalle pd ON pd.id_pedido = p.id AND pd.id_material = cd.id_material
          WHERE cd.id_computo = ? AND cd.cancelado = 0
          GROUP BY cd.id";

    $qCheck = $pdo->prepare($sqlCheck);
    $qCheck->execute([$idComputo]);
    $items = $qCheck->fetchAll(PDO::FETCH_ASSOC);

    $computoTerminado = true;
    foreach ($items as $row) {
      $reservado = ($row['reservado'] < 0) ? 0 : $row['reservado'];
      $pendiente = $row['solicitada'] - $reservado - $row['comprado'] - $row['pedido_total'];

      if (round($pendiente, 2) > 0) {
        $computoTerminado = false;
        break;
      }
    }

    $nuevoEstado = $computoTerminado ? 5 : 2;

    $sqlUpdate = "UPDATE computos SET id_estado = ? WHERE id = ?";
    $qUpdate = $pdo->prepare($sqlUpdate);
    $qUpdate->execute([$nuevoEstado, $idComputo]);

    $pdo->commit();
    Database::disconnect();

    if ($computoTerminado) {
      header("Location: listarComputos.php");
      exit();
    } else {
      header("Location: modificarComputo.php?id=" . $idComputo);
      exit();
    }

  } catch (Exception $e) {
    $pdo->rollBack();
    Database::disconnect();
    echo "<div style='padding:20px; background:#f2dede; border:1px solid red; color:red;'>";
    echo "<h3>Error Detectado</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    exit();
  }

} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT c.id AS id_computo, c.nro_revision, c.id_tarea, tt.tipo, t.observaciones, date_format(c.fecha,'%d/%m/%y') AS fecha_computo, c.id_cuenta_solicitante, cu.nombre AS cuenta_realizo, c.id_estado, ec.estado, s.nro_sitio AS sitio, s.nro_subsitio AS subsitio, p.nro AS nro_proyecto, p.nombre AS proyecto, c.nro AS nro_computo FROM computos c LEFT JOIN tareas t ON c.id_tarea=t.id LEFT JOIN tipos_tarea tt on tt.id = t.id_tipo_tarea LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante LEFT JOIN estados_computos ec ON ec.id = c.id_estado INNER JOIN proyectos p on p.id = t.id_proyecto INNER JOIN sitios s on s.id = p.id_sitio WHERE c.id = ? ";

  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  if ($data && $data['id_estado'] == 1) {
    header("Location: listarComputos.php");
    die("Redirigiendo a la lista de computos.");
  }

  $estadosGestionables = [3, 4];
  if ($data && !in_array((int)$data['id_estado'], $estadosGestionables)) {
    header("Location: listarComputos.php");
    die("Redirigiendo a la lista de computos.");
  }

  $computoAprobado = ($data && $data['id_estado'] == 5);

  Database::disconnect();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php'); ?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      .valid {
        border: 2px solid green !important;
      }
      .invalid {
        border: 2px solid red !important;
      }
      #pedidoModal .select2-container {
        width: 100% !important;
      }
      #pedidoModal .select2-dropdown {
        z-index: 2100 !important;
      }
      .abrirModalCancelarReservaItem {
        cursor: pointer;
      }
      .cantidad-reservada-visual {
        cursor: pointer;
      }
      #dataTables-example667 {
        table-layout: fixed !important;
        width: 100% !important;
      }
      th.text-narrow,
      td.text-narrow {
        white-space: normal !important;
        word-break: break-word;
        overflow: visible !important;
        text-overflow: unset !important;
        vertical-align: middle;
        text-align: center;
        padding: 8px;
        line-height: 1.2;
      }
      th.text-left,
      td.text-left {
        text-align: left !important;
      }
      th.text-center,
      td.text-center {
        text-align: center !important;
      }
      .badge-colada {
        font-size: 14px;
        font-weight: bold;
        color: #fff;
        background-color: #17a2b8;
        padding: 4px 10px;
        border-radius: 4px;
        display: inline-block;
      }
      .badge-colada-interna {
        font-size: 14px;
        font-weight: bold;
        color: #fff;
        background-color: #6c757d;
        padding: 4px 10px;
        border-radius: 4px;
        display: inline-block;
      }
      #modalStockBody table td,
      #modalStockBody table th {
        font-size: 14px !important;
        padding: 10px 8px !important;
        vertical-align: middle !important;
      }
      #modalStockBody table th {
        font-weight: bold !important;
        background-color: #f8f9fa !important;
      }
      #modalStockBody .input-reserva {
        font-size: 15px !important;
        height: 36px !important;
        width: 90px !important;
        text-align: center !important;
      }
      #modalStockBody #totalReservaLive {
        font-size: 16px !important;
        font-weight: bold !important;
      }
      #modalStockBody .fila-total-reserva {
        display: none !important;
      }
      #modalStockTotales {
        font-size: 14px;
      }
      #modalStockTotales strong {
        font-weight: 600;
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php'); ?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php'); ?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body">
          <?php
          $ubicacion = "Gestión de Cómputo";
          include_once("head_page.php") ?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?= $ubicacion . " N° " . $data["nro_computo"] . " Rev. N° " . $data["nro_revision"] . " (" . $data["sitio"] . "_" . $data["subsitio"] . "_" . $data["nro_proyecto"] . ")" ?></h5>
                    <?php if ($computoAprobado): ?>
                      <div class="alert alert-warning mt-2 mb-0" role="alert">
                        <strong>Cómputo aprobado/terminado.</strong> No se pueden realizar modificaciones.
                      </div>
                    <?php endif; ?>
                  </div>
                  <form class="form theme-form" role="form" method="post" name="form1" id="form1" action="modificarComputo.php?id=<?= $data['id_computo']; ?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-sm-5">
                              <label class="col-form-label font-weight-bold">Fecha:</label>
                              <label class="col-form-label"><?= $data['fecha_computo']; ?></label>
                            </div>
                            <div class="col-sm-7">
                              <label class="col-form-label font-weight-bold">Tarea:</label>
                              <label class="col-form-label"><?= $data['tipo'] . " / " . $data['observaciones'] ?></label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <div class="col-sm-5">
                              <label class="col-form-label font-weight-bold">Estado:</label>
                              <label class="col-form-label"><?= $data['estado'] ?></label>
                            </div>
                            <div class="col-sm-7">
                              <label class="col-form-label font-weight-bold">Realizó:</label>
                              <label class="col-form-label"><?= $data['cuenta_realizo'] ?></label>
                            </div>
                          </div>
                          <?php
                          $tienePermisoParaReservar = false;
                          if (tienePermiso(310)) {
                            $tienePermisoParaReservar = true;
                          }
                          $tienePermisoParaPedir = false;
                          if (tienePermiso(295)) {
                            $tienePermisoParaPedir = true;
                          } ?>

                          <div class="form-group row">
                            <div class="col-sm-12">
                              <input type="hidden" name="idComputo" value="<?= $_GET['id']; ?>" />
                              <div class="dt-ext table-responsive">
                                <table class="display" id="dataTables-example667">
                                  <thead>
                                    <tr>
                                      <th class="text-narrow" title="Concepto">Concepto</th>
                                      <th class="text-narrow" title="Solicitado">Solicitado</th>
                                      <th class="text-narrow" title="Necesidad">Necesidad</th>
                                      <th class="text-narrow" title="En Stock">En Stock</th>
                                      <th class="text-narrow" title="Reservado">Reservado</th>
                                      <th class="text-narrow" title="Pedido">Pedido</th>
                                      <th class="text-narrow" title="Comprando">Comprando</th>
                                      <th class="text-narrow" title="Saldo">Saldo</th>
                                      <?php
                                      if ($tienePermisoParaReservar) { ?>
                                        <th class="text-narrow" title="Reservar">Reservar</th>
                                      <?php
                                      }
                                      if ($tienePermisoParaPedir) { ?>
                                        <th class="text-narrow" title="Pedir">Pedir</th>
                                      <?php
                                      } ?>
                                      <th class="text-narrow" title="Opciones">Opciones</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <?php
                                    $pdo = Database::connect();

                                    $sql = "SELECT cd.id AS id_computo_detalle, m.concepto, cd.cantidad AS cantidad_solicitada, date_format(cd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad, cd.aprobado, cd.id_material, cd.reservado, SUM(pd.cantidad) AS cantidad_pedida, cd.comprado, m.id AS id_material FROM computos_detalle cd inner join materiales m on m.id = cd.id_material LEFT JOIN pedidos p ON cd.id_computo=p.id_computo LEFT JOIN pedidos_detalle pd ON pd.id_pedido=p.id AND pd.id_material=m.id WHERE cd.cancelado = 0 and cd.id_computo = " . $_GET['id'] . " GROUP BY cd.id";

                                    foreach ($pdo->query($sql) as $row) {
                                      $id_computo_detalle  = $row["id_computo_detalle"];
                                      $cantidad_solicitada = $row["cantidad_solicitada"];
                                      $aprobado           = $row["aprobado"];

                                      $reservado          = ($row["reservado"] < 0) ? 0 : $row["reservado"];

                                      $cantidad_pedida    = $row["cantidad_pedida"];
                                      $comprado           = $row["comprado"];

                                      $id_material        = $row["id_material"];

                                      if ($cantidad_pedida < 1) {
                                        $cantidad_pedida = 0;
                                      }

                                      $stockInfo = obtenerStockYReservado($pdo, $id_material);
                                      $enStock = $stockInfo['stock'];

                                      $pedido_neto = $cantidad_pedida - $comprado;
                                      if ($pedido_neto < 0) $pedido_neto = 0;

                                      $saldo = $cantidad_solicitada - $reservado - $cantidad_pedida;

                                      if ($saldo < 0) $saldo = 0;

                                      $inputReservar = "";
                                      $inputPedir    = "";

                                      if ($aprobado == 1 && !$computoAprobado) {
                                        $maxReservar  = min($saldo, $enStock);

                                         if ($tienePermisoParaReservar) {

                                          $inputReservar = "
                                            <div style='width:110px; margin:auto;'>
                                              <input type='text' readonly class='form-control form-control-sm text-center cantidad-reservada-visual' id='txt_reserva_vis_$id_computo_detalle' value='0'
                                                style='background:#fff; cursor:pointer;'
                                                onclick='abrirModalStock($id_computo_detalle, $id_material, $maxReservar, $saldo)'
                                                title='Elegir Lote (Disp: $enStock)'>
                                            <div id='container_reservas_$id_computo_detalle'></div>";
                                        }

                                        if ($tienePermisoParaPedir) {
                                          $valorPedir = $saldo;

                                          $inputPedir = "<input type='number' class='form-control form-control-sm text-center' style='width:110px; margin:auto;' name='cantidad_pedir[$id_computo_detalle]' min='0' max='$saldo' step='1' value='$valorPedir' onkeyup='validateMax(this)' required>";
                                        }
                                      } ?>
                                      <tr>
                                        <td><?= $row["concepto"] ?></td>
                                        <td><?= $cantidad_solicitada ?></td>
                                        <td><?= $row["fecha_necesidad"] ?></td>
                                        <td><?= $enStock ?></td>
                                        <td><?= $reservado ?></td>
                                        <td><?= $pedido_neto ?></td>
                                        <td><?= $comprado ?></td>
                                        <td>
                                          <input type="hidden" name="saldo[<?= $id_computo_detalle ?>]" value="<?= $saldo ?>">
                                          <span class="saldo"><?= $saldo ?></span>
                                        </td>
                                        <?php
                                        if ($tienePermisoParaReservar) { ?>
                                          <td><?= $inputReservar ?></td>
                                        <?php
                                        }
                                        if ($tienePermisoParaPedir) { ?>
                                          <td><?= $inputPedir ?></td>
                                        <?php
                                        } ?>
                                        <td>
                                          <?php
                                          if (!empty(tienePermiso(311)) && !$computoAprobado) {
                                            if ($reservado > 0) { ?>
                                              <span class='abrirModalCancelarReservaItem' data-id_computo='<?= $_GET['id'] ?>' data-id_computo_detalle='<?= $id_computo_detalle ?>'><img src="img/neg.png" width="24" height="25" border="0" alt="Cancelar Reserva" title="Cancelar Reserva"></span>
                                              &nbsp;&nbsp;
                                          <?php
                                            }
                                          } ?>
                                        </td>
                                      </tr>
                                    <?php
                                    }
                                    Database::disconnect(); ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <input type="hidden" name="fecha" id="hiddenFecha" value="<?= date('Y-m-d') ?>">
                    <input type="hidden" name="lugar_entrega" id="hiddenLugar">
                    <input type="hidden" name="id_cuenta_recibe" id="hiddenRecibe">

                    <!-- Contenedor para inputs hidden de reservas -->
                    <div id="reservas_container" style="display:none;"></div>

                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <input type="hidden" name="modo_debug" value="1">
                        <?php if (tienePermiso(290) && !$computoAprobado) { ?> <button class="btn btn-success" type="submit">Ejecutar</button><?php } ?>
                        <?php /*if(tienePermiso(295)){?> <a class="btn btn-warning" id="pedido-masivo" onclick="pedir();">Hacer Pedido</a><?php }*/ ?>
                        <?php /*if(tienePermiso(310)){?> <a class="btn btn-danger" id="reserva-masivo" onclick="reservar();">Hacer Reserva</a><?php }*/ ?>
                        <a class="btn btn-primary" target="_blank" href="imprimirComputo.php?id=<?= $data['id_computo']; ?>">Imprimir</a>
                        <a href="listarComputos.php" class="btn btn-light">Volver</a>
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

    <div class="modal fade" id="pedidoModal" tabindex="-1" role="dialog" aria-labelledby="pedidoModalLabel">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <form id="pedidoForm">
            <div class="modal-header">
              <h5 class="modal-title" id="pedidoModalLabel">Datos del Pedido</h5>
              <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">

              <div class="form-group row">
                <label for="inputFecha" class="col-sm-3 col-form-label">Fecha Pedido(*)</label>
                <div class="col-sm-9">
                  <?php
                  $fecha_actual = date('Y-m-d'); ?>
                  <input name="fecha" id="inputFecha" type="date" 
                        autofocus onfocus="this.showPicker()" value="<?= $fecha_actual ?>" 
                        class="form-control" required="required">
                </div>
              </div>
              <div class="form-group row">
                <label for="inputLugar" class="col-sm-3 col-form-label">Lugar de Entrega(*)</label>
                <div class="col-sm-9">
                  <?php
                  $pdo = Database::connect();
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                  $sql = "SELECT valor FROM parametros WHERE id = 8 ";
                  $q = $pdo->prepare($sql);
                  $q->execute();
                  $dataParam = $q->fetch(PDO::FETCH_ASSOC);
                  $direccion = $dataParam['valor'];?>
                  <input name="lugar_entrega" id="inputLugar" type="text" maxlength="199" class="form-control" required="required" value="<?= $direccion; ?>">
                </div>
              </div>
              <div class="form-group row">
                <label for="inputRecibe" class="col-sm-3 col-form-label">Recibe(*)</label>
                <div class="col-sm-9">
                  <select name="id_cuenta_recibe" id="inputRecibe" class="js-example-basic-single col-sm-12" required="required">
                    <option value="">Seleccione...</option>
                    <?php
                    $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
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
              <button type="submit" class="btn btn-primary">Confirmar Pedido</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="cancelarReservaModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea cancelar la reserva de este ítem del cómputo?</div>
          <div class="modal-footer">
            <a href="#" class="btn btn-primary">Cancelar reserva</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
    </div>
    <?php

    $pdo = Database::connect();
    $sql = " SELECT d.id AS id_computo_detalle, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), d.aprobado,d.id_computo FROM computos_detalle d inner join materiales m on m.id = d.id_material WHERE d.id_computo = " . $_GET['id'];
    foreach ($pdo->query($sql) as $row) { ?>
      <div class="modal fade" id="aprobarModal_<?= $row["id_computo_detalle"] ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
              <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">¿Está seguro que desea aprobar el ítem del cómputo?</div>
            <div class="modal-footer">
              <a href="aprobarComputoDetalle.php?id=<?= $row["id_computo_detalle"] ?>&idComputo=<?= $row["id_computo"] ?>" class="btn btn-primary">Aprobar</a>
              <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
            </div>
          </div>
        </div>
      </div>
    <?php
    }
    Database::disconnect(); ?>

    <div class="modal fade" id="modalStock" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              Seleccionar ingresos/devoluciones
              <small class="text-muted ml-2" id="modalStockSaldo" style="font-size:14px;"></small>
            </h5>
            <button class="close" type="button" data-dismiss="modal">×</button>
          </div>
          <div class="modal-body" id="modalStockBody">
            Cargando...
          </div>
          <div class="modal-footer d-flex align-items-center justify-content-between w-100">
            <div id="modalStockTotales" style="font-size:14px;">
              <strong>Totales:</strong>
              <span class="ml-2">Disponibles: <strong id="totalDisponibleLive">0.00</strong></span>
              <span class="ml-3">Total a reservar: <strong id="totalReservaLive2">0.00</strong></span>
              <span class="ml-3" id="pendienteFooter"></span>
            </div>
            <div>
              <button class="btn btn-primary" type="button" onclick="confirmarStock()">Confirmar</button>
              <button class="btn btn-light" type="button" data-dismiss="modal">Cerrar</button>
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
    <!-- Plugins JS Ends-->
    <script>
      $(document).ready(function() {
        $('#dataTables-example667').DataTable().destroy();
        $('#dataTables-example667').DataTable({
          stateSave: false,
          autoWidth: false,
          responsive: false,
          columnDefs: [{
              targets: 0,
              width: '28%',
              className: 'text-left text-narrow'
            },
            {
              targets: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
              width: '2%',
              className: 'text-center text-narrow'
            }
          ],
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

        /*
        // 3) **Al cargar la página**, validar todas las filas
        $('#dataTables-example667 tbody tr').each(function() {
          validarFila($(this));
        });

        // Detectar cambios en cualquiera de los inputs de reservar/pedir
        $('#dataTables-example667 tbody').on('input', 'input[name^="cantidad_reservar"], input[name^="cantidad_pedir"]', function() {
          const $tr = $(this).closest('tr');
          validarFila($tr);
        });

        // Al enviar el formulario, revisar todas las filas
        $("form").on("submit", function(e) {
          let todoValido = true;
          $('#dataTables-example667 tbody tr').each(function() {
            const valido = validarFila($(this));
            if (!valido) {
              todoValido = false;
            }
          });

          if (!todoValido) {
            e.preventDefault();
            alert('Hay al menos una fila donde reservar + pedir supera el saldo. Por favor, corrige antes de enviar.');
          } else {
            // opcional: mostrar feedback o dejar que el formulario continúe
            console.log('Formulario válido, enviando…');
          }
        });*/
  
      });

      function validateMax(e) {
        if (parseFloat(e.value) > parseFloat(e.max)) {
          e.value = e.max;
        } 
      }

      function reservar() {
        document.getElementById('form1').action = "reservarStockPedido.php";
        document.getElementById('form1').submit();
      }
      function pedir() {
        document.getElementById('form1').method = "get";
        document.getElementById('form1').action = "nuevoPedido.php";
        document.getElementById('form1').submit();
      }
		
      $(function() {
        let datosPedidoListos = false;

        function validarFila($tr) {
          const saldo = parseInt($tr.find('.saldo').text(), 10) || 0;
            
          let reservar = parseFloat($tr.find('.cantidad-reservada-visual').val()) || 0;
          if (reservar === 0) {
            reservar = parseFloat($tr.find('input[name^="cantidad_reservar"]').val()) || 0;
          }

          const pedir = parseFloat($tr.find('input[name^="cantidad_pedir"]').val()) || 0;
          const suma = reservar + pedir;

          const $inputs = $tr.find('.cantidad-reservada-visual, input[name^="cantidad_reservar"], input[name^="cantidad_pedir"]');

          if (suma > saldo) {
            $inputs.addClass('invalid').removeClass('valid');
            return false;
          } else {
            $inputs.addClass('valid').removeClass('invalid');
            return true;
          }
        }

        function hayPedido() {
          let tiene = false;
          $('input[name^="cantidad_pedir"]').each(function() {
            if (parseInt(this.value, 10) > 0) {
              tiene = true;
              return false;
            }
          });
          return tiene;
        }

        $(".abrirModalCancelarReservaItem").on("click", function() {
          let id_computo_detalle = this.dataset.id_computo_detalle;
          let id_computo = this.dataset.id_computo;
          let modal = $("#cancelarReservaModal");
          modal.modal("show");
          modal.find(".btn-primary").attr("href", "cancelarStockPedido.php?id=" + id_computo_detalle + "&idComputo=" + id_computo);
        });

        // Cuando se abre el modal, inicializa (o reinicializa) Select2
        $('#pedidoModal').on('shown.bs.modal', function() {
          $('#inputRecibe').select2({
            dropdownParent: $('#pedidoModal'),
            width: '100%',
            placeholder: 'Seleccione...',
            allowClear: true
          });
        });

        // validación en tiempo real
        $('#dataTables-example667 tbody').on('input', 'input[name^="cantidad_reservar"], input[name^="cantidad_pedir"]', function() {
          validarFila($(this).closest('tr'));
        });

        // handler de submit
        $('#form1').on('submit', function(e) {
          e.preventDefault(); // frenamos siempre la primera vez

          // 1) validar sumas
          let todoValido = true;
          $('#dataTables-example667 tbody tr').each(function() {
            if (!validarFila($(this))) {
              todoValido = false;
            }
          });
          if (!todoValido) {
            return alert('Hay al menos una fila donde reservar + pedir supera el saldo.');
          }

          // 2) si ya estamos retomando tras el modal, enviamos
          if (datosPedidoListos) {
            // quitamos el handler para no bloquear el envío
            $(this).off('submit');
            return this.submit();
          }

          // 3) si no hay pedido, enviamos directamente
          if (!hayPedido()) {
            $(this).off('submit');
            return this.submit();
          }

          // 4) hay pedido y aún no tenemos datos -> abrimos modal
          $('#pedidoModal').modal('show');
        });

        // al confirmar modal
        $('#pedidoForm').on('submit', function(e) {
          e.preventDefault();
          const fecha = $('#inputFecha').val();
          const lugar = $('#inputLugar').val().trim();
          const recibe = $('#inputRecibe').val();

          if (!fecha || !lugar || !recibe) {
            return alert('Completa todos los campos del pedido.');
          }

          // llenamos los hidden del form principal
          $('#hiddenFecha').val(fecha);
          $('#hiddenLugar').val(lugar);
          $('#hiddenRecibe').val(recibe);

          datosPedidoListos = true;
          $('#pedidoModal').modal('hide');
          // disparamos de nuevo el submit para recargar el flujo
          $('#form1').submit();
        });

        // al cargar página, marcar filas existentes
        $('#dataTables-example667 tbody tr').each(function() {
          validarFila($(this));
        });
      });

      document.addEventListener("DOMContentLoaded", function() {
        document.querySelector('.page-main-header').classList.add('open');
        document.querySelector('.page-sidebar').classList.add('open');
        
        // Con DataTables, ajustamos el render para que no se "rompa"
        setTimeout(() => {
          if ($.fn.DataTable) {
            let table = $('#dataTables-example667');
            if ($.fn.DataTable.isDataTable(table)) {
              table.DataTable().columns.adjust().draw();
            }
          }
        }, 300);
      });


      let curDetalle = 0; 
      let curMax = 0;
      let curSaldo = 0;

      function recalcularTotalesModal() {
        let totalDisponible = 0;
        let totalReserva    = 0;

        let dispColIndex = -1;
        $('#modalStockBody table thead tr th').each(function(i) {
          let txt = $(this).text().trim().toLowerCase();
          if (txt === 'disponible') {
            dispColIndex = i;
            return false;
          }
        });

        $('#modalStockBody table tbody tr').not('.fila-total-reserva').each(function() {
          if (dispColIndex >= 0) {
            let dispTxt = $(this).find('td').eq(dispColIndex).text().trim().replace(',','.');
            totalDisponible += parseFloat(dispTxt) || 0;
          } else {
            let dispTxt = $(this).find('td[data-disponible], td.disponible-col').text().trim().replace(',','.');
            totalDisponible += parseFloat(dispTxt) || 0;
          }

          let inp = $(this).find('.input-reserva');
          if (inp.length) {
            totalReserva += parseFloat(inp.val()) || 0;
          }
        });

        $('#totalDisponibleLive').text(totalDisponible.toFixed(2));
        $('#totalReservaLive2').text(totalReserva.toFixed(2));

        let pendiente = curSaldo - totalReserva;
        $('#pendienteFooter').html('Pendiente: ' + pendiente.toFixed(2));
      }

      function abrirModalStock(idDetalle, idMaterial, maximo, saldoComputo) {
        curDetalle = idDetalle;
        curMax = maximo;
        curSaldo = (saldoComputo !== undefined) ? parseFloat(saldoComputo) : parseFloat(maximo);

        $('#modalStockSaldo').text('(saldo a reservar: ' + curSaldo + ')');

        $('#totalDisponibleLive').text('0.00');
        $('#totalReservaLive2').text('0.00');
        $('#pendienteFooter').html('Pendiente: <strong style="color:#856404">' + curSaldo.toFixed(2) + ' - 0.00 = ' + curSaldo.toFixed(2) + '</strong>');

        $('#modalStock').modal('show');
        $('#modalStockBody').html('Cargando lotes...');
        
        $.post('ajaxGetStockIngresos.php', {
          id_material: idMaterial,
          id_detalle_computo: idDetalle
        }, function(resp){
          $('#modalStockBody').html(resp);

          $('#modalStockBody tr').each(function() {
            let txt = $(this).text().toLowerCase();
            if (txt.indexOf('total a reservar') !== -1 || $(this).find('#totalReservaLive').length) {
              $(this).addClass('fila-total-reserva').hide();
            }
          });

          $('#modalStockBody #totalReservaLive').closest('tr, p, div').filter(function() {
            return !$(this).is('#modalStockBody');
          }).hide();

          recalcularTotalesModal();

          $('#modalStockBody').off('input', '.input-reserva').on('input', '.input-reserva', function() {
            recalcularTotalesModal();
          });
        });
      }

      function confirmarStock() {
        let total = 0;
        let inputsHtml = "";
        let contadorInputs = 0;
        
        $('#modalStockBody .input-reserva').each(function() {
          let val = parseFloat($(this).val()) || 0;
          if (val > 0) {
            total += val;
            let name = $(this).attr('name');
            inputsHtml += `<input type="hidden" name="${name}" value="${val}">`;
            contadorInputs++;
            console.log('Input agregado:', name, 'valor:', val);
          }
        });

        console.log('Total procesado:', total, 'Max permitido:', curMax, 'Inputs creados:', contadorInputs);

        if(total > curMax) {
          alert("No puedes reservar más del saldo pendiente ("+curMax+")");
          return;
        }

        $('#reservas_container input[name^="reservas_lote[' + curDetalle + ']"]').remove();

        if(inputsHtml) {
          $('#reservas_container').append(inputsHtml);
        }
        
        $('#txt_reserva_vis_' + curDetalle).val(total);

        var $fila = $('#txt_reserva_vis_' + curDetalle).closest('tr');
        var saldo = parseFloat($fila.find('.saldo').text()) || 0;
        var nuevoPedir = saldo - total;
        if (nuevoPedir < 0) nuevoPedir = 0;

        var $inputPedir = $fila.find('input[name^="cantidad_pedir"]');
        if ($inputPedir.length) {
            $inputPedir.val(nuevoPedir);
            $inputPedir.attr('max', nuevoPedir);
        }
        
        $('#txt_reserva_vis_' + curDetalle).trigger('input');
        
        console.log('Inputs agregados al contenedor #reservas_container - Total: ' + total);
        console.log('Cantidad a pedir ajustada a: ' + nuevoPedir);
        
        $('#modalStock').modal('hide');
      }
    </script>

		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>