<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';
$esOpCA = function_exists('esOperacionesSinEconomico') ? esOperacionesSinEconomico() : false;

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarCertificadosMaestros.php");
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqlUlt = "SELECT COUNT(*) FROM certificados_avances_cabecera c
           WHERE c.id_certificado_maestro = (SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?)
             AND c.nro_certificado = (SELECT nro_certificado FROM certificados_avances_cabecera WHERE id = ?)
             AND c.nro_revision > (SELECT nro_revision FROM certificados_avances_cabecera WHERE id = ?)";
$qUlt = $pdo->prepare($sqlUlt);
$qUlt->execute([$id, $id, $id]);
if ((int) $qUlt->fetchColumn() > 0) {
  Database::disconnect();
  die("Solo la ultima revision del certificado puede modificarse.");
}

$sqlAprob = "SELECT aprobado_cliente FROM certificados_avances_cabecera WHERE id = ?";
$qAprob = $pdo->prepare($sqlAprob);
$qAprob->execute([$id]);
if ((int) $qAprob->fetchColumn() === 1) {
  Database::disconnect();
  die("El Certificado de Avance esta aprobado. Genere una nueva revision desde el listado para modificarlo.");
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

  $id=$_GET['id'];
  $sql = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_certificado_maestro=$data["id_certificado_maestro"];

  $cotizacionDolar = $esOpCA ? 0 : (!empty($_POST['cotizacion_dolar']) ? (float) $_POST['cotizacion_dolar'] : 0);
  $sql = "UPDATE certificados_avances_cabecera SET fecha_emision=?, fecha_inicio=?, fecha_fin=?, cotizacion_dolar=?, observaciones=? WHERE id=?";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST["fecha_emision"], $_POST["fecha_inicio"], $_POST["fecha_fin"], $cotizacionDolar, $_POST["observaciones"], $id]);

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }

  $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de Certificado de Avance','Certificado de Avance','verCertificadoAvance.php?id=$id')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  $redirect = "listarCertificadosAvances.php?id_certificado_maestro=" . $id_certificado_maestro;
  if(isset($_POST['btn1'])){
    $redirect="listarCertificadosAvances.php?id_certificado_maestro=".$id_certificado_maestro;
  }elseif(isset($_POST['btn2'])){
    $redirect="nuevoCertificadoAvanceDetalle.php?id_certificado_avance=".$id;
  }

  if ($modoDebug==1) {
    $pdo->rollBack();
    die();
  } else {
    Database::disconnect();
    header("Location: ".$redirect);
  }

} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT id_certificado_maestro,fecha_emision,fecha_inicio,fecha_fin,cotizacion_dolar,monto_total,observaciones FROM certificados_avances_cabecera WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_certificado_maestro=$data["id_certificado_maestro"];
  $qMoneda = $pdo->prepare("SELECT m.moneda FROM certificados_maestros cm INNER JOIN monedas m ON m.id = cm.id_moneda WHERE cm.id = ?");
  $qMoneda->execute([$id_certificado_maestro]);
  $esDolar = strtoupper((string) $qMoneda->fetchColumn()) !== 'ARS';

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
          $ubicacion="Modificar Certificado de Avance";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="modificarCertificadoAvance.php?id=<?=$id?>" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Emisión(*)</label>
                            <div class="col-sm-9"><input name="fecha_emision" type="date" onfocus="this.showPicker()" class="form-control" required="required" autofocus value="<?=$data['fecha_emision'];?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Inicio(*)</label>
                            <div class="col-sm-9"><input name="fecha_inicio" id="fecha_inicio" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?=$data['fecha_inicio'];?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Fin(*)</label>
                            <div class="col-sm-9"><input name="fecha_fin" id="fecha_fin" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?=$data['fecha_fin'];?>"></div>
                          </div>
                          <?php if (!$esOpCA) { ?>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cotización Dólar</label>
                            <div class="col-sm-9">
                              <div class="input-group">
                                <input name="cotizacion_dolar" id="cotizacion_dolar" type="number" step="0.01" min="0" class="form-control" value="<?=htmlspecialchars((string) $data['cotizacion_dolar'], ENT_QUOTES, 'UTF-8');?>">
                                <div class="input-group-append"><span class="input-group-text" id="estadoDolar" style="min-width:160px;font-size:.85rem;"></span></div>
                              </div>
                              <small id="infoCotizacion" class="text-muted"></small>
                            </div>
                          </div>
                          <?php } ?>
                          <!-- <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Monto total(*)</label>
                            <div class="col-sm-9"><input name="monto_total" type="number" min="0" step="0.01" class="form-control" required="required" value="<?=$data['monto_total'];?>"></div>
                          </div> -->
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                            <div class="col-sm-9"><textarea name="observaciones" class="form-control"><?=$data['observaciones'];?></textarea></div>
                          </div>

                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion">Modificar e ir a Certificados</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Modificar y ver Detalle</button>
                        <a href='listarCertificadosAvances.php?id_certificado_maestro=<?=$id_certificado_maestro?>' class="btn btn-light">Volver</a>
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
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
	<script>
		$("#fecha_fin").change(function () {
			var startDate = document.getElementById("fecha_inicio").value;
			var endDate = document.getElementById("fecha_fin").value;

			if ((Date.parse(startDate) > Date.parse(endDate))) {
				alert("La fecha de fin debe ser mayor a la fecha de inicio");
				document.getElementById("fecha_fin").value = "";
			}
		});

    var esDolar = <?= !empty($esDolar) ? 'true' : 'false' ?>;
    if (esDolar) {
      var badge = $('#estadoDolar');
      var input = $('#cotizacion_dolar');
      var info = $('#infoCotizacion');
      badge.text('Cotización guardada').removeClass('text-danger').addClass('text-success');
      input.prop('readonly', true);
      fetch('https://dolarapi.com/v1/dolares/blue', {headers: {'Accept': 'application/json'}})
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(d) {
          if (!d.venta) throw new Error('Sin venta');
          badge.text('Dólar Blue');
          info.text('Cotización actual disponible: $' + parseFloat(d.venta).toFixed(2));
          input.prop('readonly', false);
        })
        .catch(function() { badge.text('Manual').removeClass('text-success').addClass('text-secondary'); input.prop('readonly', false); });
    }
		</script>
    <!-- Plugin used-->

  </body>
</html>