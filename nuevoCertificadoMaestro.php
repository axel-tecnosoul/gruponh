<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}

require 'database.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $id > 0;
$hoy = date("Y-m-d");

$formData = [
  'id_occ' => isset($_GET['id_occ']) ? (int) $_GET['id_occ'] : 0,
  'fecha_emision' => $hoy,
  'porcentaje_anticipo' => '0',
  'observaciones' => '',
  'id_moneda' => '',
];
$isApproved = false;

if ($isEdit) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT id, id_occ, fecha_emision, porcentaje_anticipo, observaciones, id_moneda, aprobado_cliente FROM certificados_maestros WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  Database::disconnect();

  if (empty($data)) {
    header("Location: listarCertificadosMaestros.php");
    exit;
  }

  $formData['id_occ'] = (int) ($data['id_occ'] ?? 0);
  $formData['fecha_emision'] = (string) ($data['fecha_emision'] ?? $hoy);
  $formData['porcentaje_anticipo'] = (string) ($data['porcentaje_anticipo'] ?? '0');
  $formData['observaciones'] = (string) ($data['observaciones'] ?? '');
  $formData['id_moneda'] = (string) ($data['id_moneda'] ?? '');
  $isApproved = (int) ($data['aprobado_cliente'] ?? 0) === 1;
}

if (!empty($_POST)) {

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if ($isEdit) {
    $sql = "SELECT aprobado_cliente FROM certificados_maestros WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    $approvalData = $q->fetch(PDO::FETCH_ASSOC);

    if (empty($approvalData)) {
      Database::disconnect();
      die("El Certificado Maestro no existe.");
    }

    if ((int) ($approvalData['aprobado_cliente'] ?? 0) === 1) {
      Database::disconnect();
      die("El Certificado Maestro está aprobado y no puede modificarse.");
    }
  }

  $idOcc = (int) ($_POST["id_occ"] ?? 0);
  $fechaEmision = (string) ($_POST["fecha_emision"] ?? '');
  $porcentajeAnticipo = isset($_POST["porcentaje_anticipo"]) && $_POST["porcentaje_anticipo"] !== '' ? (float) $_POST["porcentaje_anticipo"] : 0;
  $observaciones = (string) ($_POST["observaciones"] ?? '');

  if ($idOcc <= 0 || $fechaEmision === '') {
    Database::disconnect();
    die("Complete los campos obligatorios de cabecera.");
  }

  $sql = "SELECT fecha_emision, fecha_vencimiento, monto, id_moneda FROM occ WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$idOcc]);
  $dataOcc = $q->fetch(PDO::FETCH_ASSOC);

  if (empty($dataOcc)) {
    Database::disconnect();
    die("La OCC seleccionada no es valida.");
  }

  $fechaInicio = $dataOcc['fecha_emision'];
  $fechaFin = $dataOcc['fecha_vencimiento'];
  $idMoneda = $dataOcc['id_moneda'];
  $montoTotal = $dataOcc['monto'];
  $cotizacionDolar = 0;

  if ($isEdit) {
    $sql = "UPDATE certificados_maestros SET id_occ=?, fecha_emision=?, fecha_inicio=?, fecha_fin=?, id_moneda=?, cotizacion_dolar=?, porcentaje_anticipo=?, monto_total=?, observaciones=? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$idOcc, $fechaEmision, $fechaInicio, $fechaFin, $idMoneda, $cotizacionDolar, $porcentajeAnticipo, $montoTotal, $observaciones, $id]);

    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de Certificado Maestro','Certificado Maestro','verCertificadoMaestro.php?id=$id')";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id']]);

    Database::disconnect();
    if (isset($_POST['btn_ver_detalle'])) {
      header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=" . $id);
    } else {
      header("Location: listarCertificadosMaestros.php");
    }
    exit;
  }

  $sql = "INSERT INTO certificados_maestros (id_occ, revision, fecha_emision, fecha_inicio, fecha_fin, id_moneda, cotizacion_dolar, porcentaje_anticipo, monto_total, monto_acumulado_avances, monto_acumulado_anticipos, monto_acumulado_desacopios, monto_acumulado_descuentos, monto_acumulado_ajustes, observaciones) VALUES (?,0,?,?,?,?,?,?,?,0,0,0,0,0,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$idOcc, $fechaEmision, $fechaInicio, $fechaFin, $idMoneda, $cotizacionDolar, $porcentajeAnticipo, $montoTotal, $observaciones]);

  $id_certificado_maestro = $pdo->lastInsertId();

  $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Certificado Maestro','Certificado Maestro','verCertificadoMaestro.php?id=$id_certificado_maestro')";
  $q = $pdo->prepare($sql);
  $q->execute([$_SESSION['user']['id']]);

  Database::disconnect();
  header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=" . $id_certificado_maestro);
  exit;
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      
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
          $ubicacion = $isEdit ? ("Modificar Certificado Maestro N° " . $id) : "Nuevo Certificado Maestro";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="nuevoCertificadoMaestro.php<?= $isEdit ? ('?id=' . $id) : '' ?>" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Orden de Compra Cliente(*)</label>
                            <div class="col-sm-9">
                              <select name="id_occ" id="id_occ" class="js-example-basic-single col-sm-12" required="required" autofocus <?=$isApproved ? 'disabled' : ''?>>
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT o.id, o.numero, o.id_moneda, m.moneda, c.nombre FROM occ o INNER JOIN cuentas c ON c.id = o.id_cuenta_cliente INNER JOIN monedas m ON m.id = o.id_moneda WHERE o.activa = 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  $selected="";
                                  if($formData["id_occ"]==$fila["id"]){
                                    $selected="selected";
                                  }?>
                                  <option value='<?=$fila['id']?>' data-id-moneda='<?=$fila['id_moneda']?>' data-moneda='<?=htmlspecialchars($fila['moneda'], ENT_QUOTES)?>' <?=$selected?>><?= $fila['numero'].' - '.$fila['nombre']?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <!--
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Número(*)</label>
                            <div class="col-sm-9"><input name="numero" type="text" maxlength="99" class="form-control" required="required" value="0"></div>
                          </div>
                          -->
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Emisión(*)</label>
                            <div class="col-sm-9">
                               <input name="fecha_emision" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?=htmlspecialchars($formData['fecha_emision'], ENT_QUOTES)?>" <?=$isApproved ? 'readonly' : ''?>>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Moneda(*)</label>
                            <div class="col-sm-9">
                              <input type="text" id="moneda_occ_text" class="form-control" value="" readonly>
                              <input type="hidden" name="id_moneda" id="id_moneda" value="<?=htmlspecialchars($formData['id_moneda'], ENT_QUOTES)?>">
                            </div>
                          </div>
                          <!--
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cotizacion Dolar</label>
                            <div class="col-sm-9"><input name="cotizacion_dolar" type="number" step="0.01" min="0" class="form-control" value=""></div>
                          </div>
                          -->
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">% Anticipo</label>
                             <div class="col-sm-9"><input name="porcentaje_anticipo" type="number" step="0.01" min="0" max="100" class="form-control" value="<?=htmlspecialchars($formData['porcentaje_anticipo'], ENT_QUOTES)?>" <?=$isApproved ? 'readonly' : ''?>></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                             <div class="col-sm-9"><textarea name="observaciones" class="form-control" <?=$isApproved ? 'readonly' : ''?>><?=htmlspecialchars($formData['observaciones'], ENT_QUOTES)?></textarea></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3"><?php
                        if ($isApproved) {?>
                          <div class="alert alert-warning d-inline-block mb-0 mr-2">Este Certificado Maestro está aprobado y no puede modificarse.</div><?php
                        } elseif ($isEdit) {?>
                          <button class="btn btn-success" type="submit" name="btn_ir_certificados" value="1">Guardar e ir a Certificados</button>
                          <button class="btn btn-primary" type="submit" name="btn_ver_detalle" value="1">Guardar y ver Detalle</button><?php
                        } else {?>
                          <button class="btn btn-primary" type="submit">Crear y agregar Detalle</button><?php
                        }?>
						            <a href="listarCertificadosMaestros.php" class="btn btn-light">Volver</a>
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
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
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
        function setMonedaFromOcc() {
          var selected = $('#id_occ option:selected');
          var idMoneda = selected.attr('data-id-moneda') || '';
          var moneda = selected.attr('data-moneda') || '';
          $('#id_moneda').val(idMoneda);
          $('#moneda_occ_text').val(moneda);
        }

        $('#id_occ').on('change', setMonedaFromOcc);
        setMonedaFromOcc();
      });

      </script>
      <!-- Plugin used-->
	
	    <!-- Page-Level Demo Scripts - Tables - Use for reference -->
   
  </body>
</html>
