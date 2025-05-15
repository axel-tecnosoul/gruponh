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
  header("Location: listarOrdenesCompraClientes.php");
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

  $sql = "UPDATE occ SET numero=?, fecha_emision=?, fecha_recepcion=?, id_cuenta_cliente=?, id_moneda=?, id_condicion_iva=?, percepcion=?, otros_importes=?, id_forma_pago=?, id_presupuesto=?, requiere_polizas=?, abierta=?, fecha_vencimiento=?, fecha_entrega=?, lugar_entrega=?, observaciones=?, activa=? WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([
  $_POST["numero"], $_POST["fecha_emision"], $_POST["fecha_recepcion"], $_POST["id_cuenta_cliente"], $_POST["id_moneda"], $_POST["id_condicion_iva"], 0, 0, $_POST["id_forma_pago"], $_POST["id_presupuesto"], $_POST["requiere_polizas"], $_POST["tipo_oc"], $_POST["fecha_vencimiento"], $_POST["fecha_entrega"], $_POST["lugar_entrega"], $_POST["observaciones"], $_POST["activo"], $id]);

	$sql = "delete from occ_tipos_cobertura where id_occ = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$id]);

	foreach ($_POST['id_tipo_cobertura'] as $item) {
		if ($item != 0) {
			$sql = "INSERT INTO `occ_tipos_cobertura`(`id_tipo_cobertura`,`id_occ`) VALUES (?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$item,$id]);
		}
	}

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de Orden de Compra Clientes','Ordenes de Compra Clientes','verOrdenCompraCliente.php?id=$id')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  if(isset($_POST['btn1'])){
    $redirect="listarOrdenesCompraClientes.php";
  }elseif(isset($_POST['btn2'])){
    $redirect="nuevaOrdenCompraClienteDetalle.php?id_orden_compra_cliente=".$id;
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
  $sql = "SELECT numero,fecha_emision,fecha_recepcion,id_cuenta_cliente,monto,id_moneda,id_condicion_iva,percepcion,otros_importes,id_forma_pago,id_presupuesto,requiere_polizas,abierta,fecha_vencimiento,fecha_entrega,lugar_entrega,observaciones,activa FROM occ WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $checked_requiere_polizas_si=($data["requiere_polizas"]==1) ? "checked" : "";
  $checked_requiere_polizas_no=($data["requiere_polizas"]==0) ? "checked" : "";
  $checked_tipo_oc_abierta=($data["abierta"]==1) ? "checked" : "";
  $checked_tipo_oc_cerrada=($data["abierta"]==0) ? "checked" : "";
  $checked_activo_si=($data["activa"]==1) ? "checked" : "";
  $checked_activo_no=($data["activa"]==0) ? "checked" : "";

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
          $ubicacion="Modificar Orden de Compra Clientes";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" id="form1" action="modificarOrdenCompraCliente.php?id=<?=$id?>" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Emisión(*)</label>
                            <div class="col-sm-9"><input name="fecha_emision" id="fecha_emision" autofocus type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?=$data['fecha_emision']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Recepción(*)</label>
                            <div class="col-sm-9"><input name="fecha_recepcion" id="fecha_recepcion" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?=$data['fecha_recepcion']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Número(*)</label>
                            <div class="col-sm-9"><input name="numero" type="text" maxlength="99" class="form-control" required="required" value="<?=$data['numero']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cliente(*)</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta_cliente" id="id_cuenta_cliente" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (1) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if($fila["id"]==$data['id_cuenta_cliente']){
                                    echo "selected";
                                  }
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Moneda(*)</label>
                            <div class="col-sm-9">
                              <select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, moneda FROM monedas";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if($fila["id"]==$data['id_moneda']){
                                    echo "selected";
                                  }
                                  echo ">".$fila['moneda']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Condición IVA(*)</label>
                            <div class="col-sm-9">
							<select name="id_condicion_iva" id="id_condicion_iva" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `condicion_iva` FROM `condiciones_iva` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_condicion_iva']) {
                                        echo " selected ";
								}
								echo ">".$fila['condicion_iva']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							  </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Forma de pago(*)</label>
                            <div class="col-sm-9">
                              <select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, forma_pago FROM formas_pago";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if($fila["id"]==$data['id_forma_pago']){
                                    echo "selected";
                                  }
                                  echo ">".$fila['forma_pago']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Presupuesto(*)</label>
                            <div class="col-sm-9">
                              <select name="id_presupuesto" id="id_presupuesto" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT p.id, p.nro_revision,c.nombre,p.descripcion,m.moneda,p.monto FROM presupuestos p inner join cuentas c on c.id = p.id_cuenta inner join monedas m on m.id = p.id_moneda WHERE p.anulado = 0 ";
								$q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if($fila["id"]==$data['id_presupuesto']){
                                    echo "selected";
                                  }
                                  echo ">".$fila['id'].' / '.$fila['nro_revision'].' - '.$fila['nombre'].' - '.$fila['descripcion'].' - '.$fila['moneda'].number_format($fila['monto'],2)."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Requiere Poliza(*)</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="requiere_polizas_si">
                                <input class="radio_animated" value="1" required id="requiere_polizas_si" type="radio" name="requiere_polizas" <?=$checked_requiere_polizas_si?>><label for="requiere_polizas_si">Si</label>
                              </label>
                              <label class="d-block" for="requiere_polizas_no">
                                <input class="radio_animated" value="0" required id="requiere_polizas_no" type="radio" name="requiere_polizas" <?=$checked_requiere_polizas_no?>><label for="requiere_polizas_no">No</label>
                              </label>
                            </div>
                          </div>
						   <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipos de Cobertura(*)</label>
                            <div class="col-sm-9">
                              <select name="id_tipo_cobertura[]" id="id_tipo_cobertura[]" multiple="multiple" class="js-example-basic-single col-sm-12" required="required">
                                <option value="0">No Requiere</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, tipo FROM tipos_cobertura_polizas  ";
								$q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
									$sql2 = " SELECT id from occ_tipos_cobertura where id_occ = ? and id_tipo_cobertura = ? ";
									$q2 = $pdo->prepare($sql2);
									$q2->execute([$id, $fila['id']]);
									$data22 = $q2->fetch(PDO::FETCH_ASSOC);
									if (!empty($data22)) {
										echo " selected ";
									}
                                  echo ">".$fila['tipo']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipo OC(*)</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="tipo_oc_abierta">
                                <input class="radio_animated" value="1" required id="tipo_oc_abierta" type="radio" name="tipo_oc" <?=$checked_tipo_oc_abierta?>><label for="tipo_oc_abierta">Abierta</label>
                              </label>
                              <label class="d-block" for="tipo_oc_cerrada">
                                <input class="radio_animated" value="0" required id="tipo_oc_cerrada" type="radio" name="tipo_oc" <?=$checked_tipo_oc_cerrada?>><label for="tipo_oc_cerrada">Cerrada</label>
                              </label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Vencimiento</label>
                            <div class="col-sm-9"><input name="fecha_vencimiento" id="fecha_vencimiento" type="date" onfocus="this.showPicker()" class="form-control" value="<?=$data['fecha_vencimiento']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Entrega(*)</label>
                            <div class="col-sm-9"><input name="fecha_entrega" id="fecha_entrega" type="date" onfocus="this.showPicker()" required class="form-control" value="<?=$data['fecha_entrega']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Lugar de Entrega(*)</label>
                            <div class="col-sm-9"><input name="lugar_entrega" type="text" required class="form-control" value="<?=$data['lugar_entrega']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                            <div class="col-sm-9"><textarea name="observaciones" name="observaciones" class="form-control"><?=$data['observaciones']; ?></textarea></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Activo(*)</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="activo_si">
                                <input class="radio_animated" value="1" required id="activo_si" type="radio" name="activo" <?=$checked_activo_si?>><label for="activo_si">Si</label>
                              </label>
                              <label class="d-block" for="activo_no">
                                <input class="radio_animated" value="0" required id="activo_no" type="radio" name="activo" <?=$checked_activo_no?>><label for="activo_no">No</label>
                              </label>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion">Modificar e ir a Ordenes</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Modificar y ver Detalle</button>
                        <a href='listarOrdenesCompraClientes.php' class="btn btn-light">Volver</a>
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
    <!-- Plugin used-->
	<script>
		$("#form1").submit(function () {
			var startDate1 = document.getElementById("fecha_emision").value;
			var endDate1 = document.getElementById("fecha_recepcion").value;
			var startDate2 = document.getElementById("fecha_vencimiento").value;
			var endDate2 = document.getElementById("fecha_entrega").value;

			if ((Date.parse(startDate1) > Date.parse(endDate1))) {
				alert("La fecha de recepcion debe ser mayor a la fecha de emision");
				document.getElementById("fecha_recepcion").value = "";
				event.preventDefault(); // Evita que se envíe el formulario
			}
			if ((Date.parse(startDate2) > Date.parse(endDate2))) {
				alert("La fecha de entrega debe ser mayor a la fecha de vencimiento");
				document.getElementById("fecha_entrega").value = "";
				event.preventDefault(); // Evita que se envíe el formulario
			}
		});
		</script>
  </body>
</html>