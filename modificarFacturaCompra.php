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
        header("Location: listarFacturasCompra.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE `facturas_compra` set `descripcion` = ?, `id_tipo_comprobante` = ?, `id_letra_comprobante` = ?, `id_orden_compra` = ?, `numero` = ?, `id_cuenta_origen` = ?, `id_empresa` = ?, `fecha_emitida` = ?, `fecha_recibida` = ?, `id_condicion_pago` = ?, `id_moneda` = ?, `cotizacion` = ?, `observaciones` = ?, `id_estado` = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['descripcion'],$_POST['id_tipo_comprobante'],$_POST['id_letra_comprobante'],$_POST['id_orden_compra'],$_POST['numero'],$_POST['id_cuenta_origen'],$_POST['id_empresa'],$_POST['fecha_emitida'],$_POST['fecha_recibida'],$_POST['id_condicion_pago'],$_POST['id_moneda'],$_POST['cotizacion'],$_POST['observaciones'],$_POST['id_estado'],$_GET['id']]);
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación/Anulación de Factura de Compra','Facturas de Compra','verCompra.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        
        header("Location: listarFacturasCompra.php");
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `descripcion`, `id_tipo_comprobante`, `id_letra_comprobante`, `id_orden_compra`, `numero`, `id_cuenta_origen`, `id_empresa`, `fecha_emitida`, `fecha_recibida`, `id_condicion_pago`, `subtotal_gravado`, `subtotal_no_gravado`, `otros`, `iva`, `total`, `id_moneda`, `cotizacion`, `observaciones`, `id_usuario`, `id_estado` FROM `facturas_compra` WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        
        Database::disconnect();
    }
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
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
          $ubicacion="Modificar/Anular Factura de Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarFacturaCompra.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción(*)</label>
							<div class="col-sm-9"><textarea name="descripcion" autofocus class="form-control" required="required"><?php echo $data['descripcion']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo Comprobante(*)</label>
							<div class="col-sm-9">
							<select name="id_tipo_comprobante" id="id_tipo_comprobante" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_comprobante` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_tipo_comprobante']) {
                                        echo " selected ";
								}
								echo ">".$fila['tipo']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>	
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Letra(*)</label>
							<div class="col-sm-9">
							<select name="id_letra_comprobante" id="id_letra_comprobante" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `letra` FROM `letras_comprobante` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_letra_comprobante']) {
                                        echo " selected ";
								}
								echo ">".$fila['letra']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>	
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Orden de Compra(*)</label>
							<div class="col-sm-9">
							<select name="id_orden_compra" id="id_orden_compra" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nro_oc` FROM `compras` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_orden_compra']) {
                                        echo " selected ";
								}
								echo ">".$fila['nro_oc']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>	
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Número(*)</label>
							<div class="col-sm-9"><input name="numero" type="text" maxlength="99" class="form-control" value="<?php echo $data['numero']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proveedor(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_origen" id="id_cuenta_origen" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (5) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_cuenta_origen']) {
                                        echo " selected ";
								}
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Empresa(*)</label>
							<div class="col-sm-9">
							<select name="id_empresa" id="id_empresa" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `empresa` FROM `empresas` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_empresa']) {
                                        echo " selected ";
								}
								echo ">".$fila['empresa']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Emitida(*)</label>
							<div class="col-sm-9"><input name="fecha_emitida" id="fecha_emitida" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_emitida']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Recibida(*)</label>
							<div class="col-sm-9"><input name="fecha_recibida" id="fecha_recibida" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_recibida']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Forma de Pago</label>
							<div class="col-sm-9">
							<select name="id_condicion_pago" id="id_condicion_pago" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `forma_pago` FROM `formas_pago` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_condicion_pago']) {
                                        echo " selected ";
								}
								echo ">".$fila['forma_pago']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Moneda(*)</label>
							<div class="col-sm-9">
							<select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `moneda` FROM `monedas` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_moneda']) {
                                        echo " selected ";
								}
								echo ">".$fila['moneda']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							//TODO agregar total para modificar
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cotización(*)</label>
							<div class="col-sm-9"><input name="cotizacion" type="number" step="0.01" class="form-control" value="<?php echo $data['cotizacion']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"><?php echo $data['observaciones']; ?></textarea></div>
							</div>	
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado(*)</label>
							<div class="col-sm-9">
							<select name="id_estado" id="id_estado" class="js-example-basic-single col-sm-12" required="required">
							<option value="" selected>Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_factura` WHERE id in (3,4)";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['estado']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						<a onclick="document.location.href='listarFacturasCompra.php'" class="btn btn-light">Volver</a>
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
	<script>
		$("#fecha_recibida").change(function () {
			var startDate = document.getElementById("fecha_emitida").value;
			var endDate = document.getElementById("fecha_recibida").value;

			if ((Date.parse(startDate) > Date.parse(endDate))) {
				alert("La fecha de fin debe ser mayor a la fecha de inicio");
				document.getElementById("fecha_recibida").value = "";
			}
		});
		</script>
  </body>
</html>