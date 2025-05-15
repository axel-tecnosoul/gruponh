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
        header("Location: listarFacturasVenta.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE `facturas_venta` set `descripcion` = ?, `id_tipo_comprobante` = ?, `id_letra_comprobante` = ?, `id_proyecto` = ?, `numero` = ?, `id_cuenta_destino` = ?, `id_empresa` = ?, `fecha_emitida` = ?, `fecha_enviada` = ?, `id_condicion_pago` = ?, `id_moneda` = ?, `cotizacion` = ?, `observaciones` = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['descripcion'],$_POST['id_tipo_comprobante'],$_POST['id_letra_comprobante'],$_POST['id_proyecto'],$_POST['numero'],$_POST['id_cuenta_destino'],$_POST['id_empresa'],$_POST['fecha_emitida'],$_POST['fecha_enviada'],$_POST['id_condicion_pago'],$_POST['id_moneda'],$_POST['cotizacion'],$_POST['observaciones'],$_GET['id']]);
		
		$sql = "delete from facturas_venta_otros where id_factura_venta = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$_GET['id']]);

		foreach ($_POST['regimenes'] as $item) {
			
			$sql = "SELECT porcentaje FROM `regimenes_facturacion` WHERE id = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$item]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$porcentaje = $data['porcentaje'];
			
			$sql = "INSERT INTO `facturas_venta_otros`(`id_factura_venta`, `id_regimen`, `porcentaje`) VALUES (?,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$_GET['id'],$item,$porcentaje]);
		}
		
		$gravado = 0;
		$noGravado = 0;
		$otros = 0;
		$iva = 0;
		$total = 0;
		
		$sql = " SELECT `subtotal`, `iva`, `neto` FROM `facturas_venta_detalle` WHERE `id_factura_venta` = ".$_GET['id'];    
		foreach ($pdo->query($sql) as $row) {
			$gravado += $row[2];
			$noGravado += $row[0];
			$iva += ($row[0]*($row[1]/100));
		}
		
		$porc = 0;
		$sql = "SELECT sum(`porcentaje`) porc FROM `facturas_venta_otros` WHERE `id_factura_venta` = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_GET['id']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		if (!empty($data)) {
			$porc = $data['porc'];	
		}
		$otros = $noGravado*($porc/100);
		$total = $noGravado + $iva + $otros;
		
		$sql = "update `facturas_venta` set  `subtotal_gravado` = ?, `subtotal_no_gravado` = ?, `iva` = ?, `otros` = ?, `total` = ? where id = ?";
		$q = $pdo->prepare($sql);		   
		$q->execute([$gravado, $noGravado, $iva, $otros, $total, $_GET['id']]);
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación/Anulación de Factura de Venta ID #$id','Facturas de Venta','')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        
        header("Location: listarFacturasVenta.php");
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `descripcion`, `id_tipo_comprobante`, `id_letra_comprobante`, `id_proyecto`, `numero`, `id_cuenta_destino`, `id_empresa`, `fecha_emitida`, `fecha_enviada`, `id_condicion_pago`, `subtotal_gravado`, `subtotal_no_gravado`, `otros`, `iva`, `total`, `id_moneda`, `cotizacion`, `observaciones`, `id_usuario` FROM `facturas_venta` WHERE id = ? ";
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
          $ubicacion="Modificar/Anular Factura de Venta";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarFacturaVenta.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
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
							<label class="col-sm-3 col-form-label">Cliente(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_destino" id="id_cuenta_destino" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (1) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_cuenta_destino']) {
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
							<label class="col-sm-3 col-form-label">Número(*)</label>
							<div class="col-sm-9"><input name="numero" id="customInput" oninput="applyMask(this)" placeholder="000x-0000xxxx" type="text" maxlength="99" class="form-control" value="<?php echo $data['numero']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
							<select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_proyecto']) {
                                        echo " selected ";
								}
								echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>	
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción(*)</label>
							<div class="col-sm-9"><textarea name="descripcion" autofocus class="form-control" required="required"><?php echo $data['descripcion']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Emitida(*)</label>
							<div class="col-sm-9"><input name="fecha_emitida" id="fecha_emitida" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_emitida']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Enviada(*)</label>
							<div class="col-sm-9"><input name="fecha_enviada" id="fecha_enviada" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_enviada']; ?>" required="required"></div>
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
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cotización(*)</label>
							<div class="col-sm-9"><input name="cotizacion" type="number" step="0.01" class="form-control" value="<?php echo $data['cotizacion']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
                            <label class="col-sm-3 col-form-label">Otros Regimenes</label>
                            <div class="col-sm-9">
                              <select name="regimenes[]" id="regimenes[]" multiple="multiple" class="js-example-basic-single col-sm-12">
                                <?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, regimen FROM regimenes_facturacion  ";
								$q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
									$sql2 = " SELECT id from facturas_venta_otros where id_factura_venta = ? and id_regimen = ? ";
									$q2 = $pdo->prepare($sql2);
									$q2->execute([$id, $fila['id']]);
									$data22 = $q2->fetch(PDO::FETCH_ASSOC);
									if (!empty($data22)) {
										echo " selected ";
									}
                                  echo ">".$fila['regimen']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"><?php echo $data['observaciones']; ?></textarea></div>
							</div>	
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						<a onclick="document.location.href='listarFacturasVenta.php'" class="btn btn-light">Volver</a>
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
		$("#fecha_enviada").change(function () {
			var startDate = document.getElementById("fecha_emitida").value;
			var endDate = document.getElementById("fecha_enviada").value;

			if ((Date.parse(startDate) > Date.parse(endDate))) {
				alert("La fecha de fin debe ser mayor a la fecha de inicio");
				document.getElementById("fecha_enviada").value = "";
			}
		});
		</script>
		
		<script>
        function applyMask(input) {
            let value = input.value.replace(/\D/g, ''); // Eliminar cualquier caracter que no sea un número
            if (value.length > 3) {
                value = value.substring(0, 3) + '-' + value.substring(3, 11); // Agregar guion después de 4 números y limitar a 8 más
            }
            input.value = value;
        }
    </script>
	<script src="https://cdn.jsdelivr.net/npm/autonumeric@4.5.4"></script>
  </body>
</html>