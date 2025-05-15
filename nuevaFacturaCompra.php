<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$idEmpresa = 0;
$idProveedor = 0;
$idFormaPago = 0;
$idMoneda = 0;
if (!empty($_POST)) {
  
	// insert data
	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$sql = "INSERT INTO `facturas_compra`(`descripcion`, `id_tipo_comprobante`, `id_letra_comprobante`, `id_orden_compra`, `numero`, `id_cuenta_origen`, `id_empresa`, `fecha_emitida`, `fecha_recibida`, `id_condicion_pago`, `id_moneda`, `cotizacion`, `observaciones`, `id_usuario`, `id_estado`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['descripcion'],$_POST['id_tipo_comprobante'],$_POST['id_letra_comprobante'],$_POST['id_orden_compra'],$_POST['numero'],$_POST['id_cuenta_origen'],$_POST['id_empresa'],$_POST['fecha_emitida'],$_POST['fecha_recibida'],$_POST['id_condicion_pago'],$_POST['id_moneda'],$_POST['cotizacion'],$_POST['observaciones'],$_SESSION['user']['id'],$_POST['id_estado']]);
	$idFactura = $pdo->lastInsertId();
	
	$sql = "SELECT `id_pedido` FROM `compras` WHERE id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['id_orden_compra']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$sql = "update pedidos set id_estado = 4 where id_estado = 3 and id  = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$data['id_pedido']]);
	$sql = "update compras set id_estado_compra = 5 where id  = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['id_orden_compra']]);

	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva Factura de Compra ID #$idFactura','Facturas de Compra','')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));

	Database::disconnect();
	header("Location: nuevoDetalleFacturaCompra.php?id=".$idFactura);
} else {
	if (!empty($_GET['oc'])) {
		$pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT c.`id_cuenta_proveedor`, c.`id_forma_pago`, c.`id_moneda`, p.id_computo, p.id_proyecto FROM `compras` c inner join pedidos p on p.id = c.id_pedido WHERE c.`id` = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_GET['oc']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		
		if (!empty($data['id_cuenta_proveedor'])) {
			$idProveedor = $data['id_cuenta_proveedor'];
		}
		if (!empty($data['id_forma_pago'])) {
			$idFormaPago = $data['id_forma_pago'];
		}
		if (!empty($data['id_moneda'])) {
			$idMoneda = $data['id_moneda'];
		}
		
		$idComputo = $data['id_computo'];
		$idProyecto = $data['id_proyecto'];
		if (!empty($idComputo)) {
			$sql = " SELECT s.id_empresa FROM computos c inner join tareas t on t.id = c.id_tarea inner join proyectos pr on pr.id = t.id_proyecto inner join sitios s on s.id = pr.id_sitio WHERE c.id = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$idComputo]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$idEmpresa = $data['id_empresa'];
		} else {
			$sql = " SELECT s.id_empresa FROM proyectos pr inner join sitios s on s.id = pr.id_sitio WHERE pr.id = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$idProyecto]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$idEmpresa = $data['id_empresa'];
		}
		
		
        Database::disconnect();
	}
	
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  
  <script>
  function jsRecargar() {
	  document.location.href = "nuevaFacturaCompra.php?oc="+document.getElementById('id_orden_compra').value;
  }
  </script>
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
          $ubicacion="Nueva Factura Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevaFacturaCompra.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Orden de Compra(*)</label>
							<div class="col-sm-9">
							<select name="id_orden_compra" id="id_orden_compra" class="js-example-basic-single col-sm-12" required="required" onchange="jsRecargar();">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nro_oc` FROM `compras` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ((!empty($_GET['oc'])) && ($_GET['oc']==$fila['id'])) {
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
								if (($idEmpresa != 0) && ($fila['id']==$idEmpresa)){
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
							<label class="col-sm-3 col-form-label">Proveedor(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_origen" id="id_cuenta_origen" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT p.`id`, p.`razon_social`, p.`cuit`, c.`condicion_iva` FROM `cuentas` p left join condiciones_iva c on c.id = p.`id_condicion_iva` WHERE p.id_tipo_cuenta in (5) and p.activo = 1 and p.anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (($idProveedor != 0) && ($fila['id']==$idProveedor)){
									echo " selected ";
								}
								echo ">".$fila['razon_social']." (".$fila['cuit'].") - Iva: ".$fila['condicion_iva']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
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
								if (($idFormaPago != 0) && ($fila['id']==$idFormaPago)){
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
								if (($idMoneda != 0) && ($fila['id']==$idMoneda)){
									echo " selected ";
								}
								echo ">".$fila['moneda']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
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
								echo ">".$fila['letra']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>	
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Número(*)</label>
							<div class="col-sm-9"><input name="numero" id="customInput" oninput="applyMask(this)" placeholder="000x-0000xxxx" type="text" maxlength="99" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción(*)</label>
							<div class="col-sm-9"><textarea name="descripcion" class="form-control" required="required" autofocus></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Emitida(*)</label>
							<div class="col-sm-9"><input name="fecha_emitida" id="fecha_emitida" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo date('Y-m-d');?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Recibida(*)</label>
							<div class="col-sm-9"><input name="fecha_recibida" id="fecha_recibida" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo date('Y-m-d');?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cotización(*)</label>
							<div class="col-sm-9"><input name="cotizacion" type="number" step="0.01" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"></textarea></div>
							</div>	
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado(*)</label>
							<div class="col-sm-9">
							<select name="id_estado" id="id_estado" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_factura` WHERE id in (1,2)";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == 2) {
									echo " selected ";
								}
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
                        <button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarFacturasCompra.php" class="btn btn-light">Volver</a>
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
		<script>
        function applyMask(input) {
            let value = input.value.replace(/\D/g, ''); // Eliminar cualquier caracter que no sea un número
            if (value.length > 4) {
                value = value.substring(0, 4) + '-' + value.substring(4, 12); // Agregar guion después de 4 números y limitar a 8 más
            }
            input.value = value;
        }
		</script>
		<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.5.4"></script>
  </body>
</html>