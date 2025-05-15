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
        header("Location: listarCuentas.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
		if (empty($_POST['id_usuario'])) {
			$_POST['id_usuario'] = null;	
		}
		
		if (empty($_POST['id_puesto'])) {
			$_POST['id_puesto'] = null;
		}
		
        $sql = "UPDATE `cuentas` set `id_tipo_cuenta` = ?, `nombre` = ?, `razon_social` = ?, `cuit` = ?, `contacto` = ?, `email` = ?, `telefono` = ?, `id_puesto` = ?, `codigo_postal` = ?, `id_pais` = ?, `id_provincia` = ?, `id_localidad` = ?, `observaciones` = ?, `es_recurso` = ?, `cuenta_gestion` = ?, `codigo_externo` = ?, `id_condicion_iva` = ?, `direccion` = ?, `id_usuario` = ?, activo = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['id_tipo_cuenta'],$_POST['nombre'],$_POST['razon_social'],$_POST['cuit'],$_POST['contacto'],$_POST['email'],$_POST['telefono'],$_POST['id_puesto'],$_POST['codigo_postal'],$_POST['id_pais'],$_POST['id_provincia'],$_POST['id_localidad'],$_POST['observaciones'],$_POST['es_recurso'],$_POST['cuenta_gestion'],$_POST['codigo_externo'],$_POST['id_condicion_iva'],$_POST['direccion'],$_POST['id_usuario'],$_POST['activo'],$_GET['id']]);

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de cuenta','Cuentas','verCuenta.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
        
        Database::disconnect();
        
        header("Location: listarCuentas.php");
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_tipo_cuenta`, `nombre`, `razon_social`, `cuit`, `contacto`, `email`, `telefono`, `id_puesto`, `codigo_postal`, `id_pais`, `id_provincia`, `id_localidad`, `observaciones`, `activo`, `es_recurso`, `anulado`, `cuenta_gestion`, `codigo_externo`, `id_condicion_iva`, `direccion`, `id_usuario` FROM `cuentas` WHERE id = ? ";
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
          $ubicacion="Modificar Cuenta";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarCuenta.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo de Cuenta(*)</label>
							<div class="col-sm-9">
							<select name="id_tipo_cuenta" id="id_tipo_cuenta" autofocus class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo_cuenta` FROM `tipos_cuenta` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_tipo_cuenta']) {
                                        echo " selected ";
								}
								echo ">".$fila['tipo_cuenta']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nombre Corto(*)</label>
							<div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['nombre']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Razón Social(*)</label>
							<div class="col-sm-9"><input name="razon_social" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['razon_social']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">CUIT / CUIL(*)</label>
							<div class="col-sm-9"><input name="cuit" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['cuit']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Contacto</label>
							<div class="col-sm-9"><input name="contacto" type="text" maxlength="99" class="form-control" value="<?php echo $data['contacto']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">E-Mail</label>
							<div class="col-sm-9"><input name="email" type="email" maxlength="99" class="form-control" value="<?php echo $data['email']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Teléfono</label>
							<div class="col-sm-9"><input name="telefono" type="text" maxlength="99" class="form-control" value="<?php echo $data['telefono']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Puesto</label>
							<div class="col-sm-9">
							<select name="id_puesto" id="id_puesto" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `puesto` FROM `puestos` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_puesto']) {
                                        echo " selected ";
								}
								echo ">".$fila['puesto']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Dirección</label>
							<div class="col-sm-9"><input name="direccion" type="text" maxlength="199" class="form-control" value="<?php echo $data['direccion']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Código Postal</label>
							<div class="col-sm-9"><input name="codigo_postal" type="text" maxlength="99" class="form-control" value="<?php echo $data['codigo_postal']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">País(*)</label>
							<div class="col-sm-9">
							<select name="id_pais" id="id_pais" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `paises` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_pais']) {
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
							<label class="col-sm-3 col-form-label">Provincia(*)</label>
							<div class="col-sm-9">
							<select name="id_provincia" id="id_provincia" class="js-example-basic-single col-sm-12" required="required" onChange="jsListarLocalidades(this.value);">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `provincia` FROM `provincias` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_provincia']) {
                                        echo " selected ";
								}
								echo ">".$fila['provincia']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Localidad(*)</label>
							<div class="col-sm-9">
							<select name="id_localidad" id="id_localidad" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `localidad` FROM `localidades` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_localidad']) {
                                        echo " selected ";
								}
								echo ">".$fila['localidad']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"><?php echo $data['observaciones']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Es Recurso?</label>
							<div class="col-sm-9">
							<select name="es_recurso" id="es_recurso" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
								<option value="1" <?php if ($data['es_recurso']==1) {
                                    echo " selected ";
                                }?>>Si</option>
								<option value="0" <?php if ($data['es_recurso']==0) {
                                    echo " selected ";
                                }?>>No</option>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Usuario del Sistema</label>
							<div class="col-sm-9">
							<select name="id_usuario" id="id_usuario" class="js-example-basic-single col-sm-12" autofocus>
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `usuario` FROM `usuarios` WHERE activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_usuario']) {
                                        echo " selected ";
								}
								echo ">".$fila['usuario']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cuenta Gestión</label>
							<div class="col-sm-9"><input name="cuenta_gestion" type="text" maxlength="99" class="form-control" value="<?php echo $data['cuenta_gestion']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Código Externo</label>
							<div class="col-sm-9"><input name="codigo_externo" type="text" maxlength="99" class="form-control" value="<?php echo $data['codigo_externo']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Condición ante IVA(*)</label>
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
								<label class="col-sm-3 col-form-label">Activo(*)</label>
								<div class="col-sm-9">
								<select name="activo" id="activo" class="js-example-basic-single col-sm-12" required="required">
								<option value="">Seleccione...</option>
								<option value="1" <?php if ($data['activo']==1) {
                                    echo " selected ";
                                }?>>Si</option>
								<option value="0" <?php if ($data['activo']==0) {
                                    echo " selected ";
                                }?>>No</option>
								</select>
								</div>
							</div>
							
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						<a onclick="document.location.href='listarCuentas.php'" class="btn btn-light">Volver</a>
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
		
	function jsListarLocalidades(val) { 
	$.ajax({
		type: "POST",
		url: "ajaxLocalidades.php",
		data: "id_provincia="+val,
		success: function(resp){
			$("#id_localidad").html(resp);
		}
	});
	}
	
	</script>
  </body>
</html>