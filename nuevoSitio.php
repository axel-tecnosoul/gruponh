<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		if (!empty($_POST['idSitioSuperior'])) {
		//subsitio
		
			$sql = "SELECT nro_sitio from sitios WHERE id = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$_POST['idSitioSuperior']]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$nroSitio = $data['nro_sitio'];
			
			$sql = "SELECT max(nro_subsitio) nro_subsitio from sitios WHERE nro_sitio = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$nroSitio]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$nroSubsitio = $data['nro_subsitio']+1;
			
			$sql = "SELECT id idSitioPadre from sitios WHERE nro_subsitio = 0 and nro_sitio = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$nroSitio]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$idSitioPadre = $data['idSitioPadre'];
			
		} else {
		
			$idSitioPadre = null;
			$nroSubsitio = 0;
			
			$sql = "SELECT max(nro_sitio) nro_sitio from sitios WHERE 1 ";
			$q = $pdo->prepare($sql);
			$q->execute();
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$nroSitio = $data['nro_sitio']+1;
			
		}
		
		if (empty($_POST['id_tipo_montaje'])) {
			$_POST['id_tipo_montaje'] = null;
		}
		if (empty($_POST['id_tipo_estructura'])) {
			$_POST['id_tipo_estructura'] = null;
		}

		$sql = "INSERT INTO `sitios`(`id_sitio_superior`, `nombre`, `direccion`, `latitud`, `longitud`, `observaciones`, `id_pais`, `id_provincia`, `id_localidad`, `id_tipo_estructura`, `altura`, `ancho_cara`, `peso_estructura`, `id_tipo_montaje`, `paso`, `beta`, `rugosidad`, `id_cuenta_duenio`, `nro_subsitio`, `nro_sitio`, `id_empresa`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$idSitioPadre,$_POST['nombre'],$_POST['direccion'],$_POST['latitud'],$_POST['longitud'],$_POST['observaciones'],$_POST['id_pais'],$_POST['id_provincia'],$_POST['id_localidad'],$_POST['id_tipo_estructura'],$_POST['altura'],$_POST['ancho_cara'],$_POST['peso_estructura'],$_POST['id_tipo_montaje'],$_POST['paso'],$_POST['beta'],$_POST['rugosidad'],$_POST['id_cuenta_duenio'],$nroSubsitio,$nroSitio,$_POST['id_empresa']]);
        
		$idNew = $pdo->lastInsertId();
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Sitio','Sitios','verSitio.php?id=$idNew')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        header("Location: listarSitios.php");
    } else {
		if (!empty($_GET['id'])) {
			$pdo = Database::connect();
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$sql = "SELECT `id`, `id_sitio_superior`, `nombre`, `direccion`, `latitud`, `longitud`, `observaciones`, `id_pais`, `id_provincia`, `id_localidad`, `id_tipo_estructura`, `altura`, `ancho_cara`, `peso_estructura`, `id_tipo_montaje`, `paso`, `beta`, `rugosidad`, `id_cuenta_duenio`, `nro_subsitio`, `nro_sitio`, `id_empresa` FROM `sitios` WHERE id = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$_GET['id']]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			
			Database::disconnect();
		}
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
          $ubicacion="Nuevo Sitio / Subsitio";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevoSitio.php">
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
								if (!empty($data)) {
									if ($fila['id'] == $data['id_empresa']) {
											echo " selected ";
									}
								}
								echo ">".$fila['empresa']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nombre Sitio(*)</label>
							<div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control" autofocus required="required" value="<?php if (!empty($data)) echo $data['nombre']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Dueño(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_duenio" id="id_cuenta_duenio" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 1 and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Dirección(*)</label>
							<div class="col-sm-9"><input name="direccion" type="text" maxlength="99" class="form-control" required="required" value="<?php if (!empty($data)) echo $data['direccion']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Latitud</label>
							<div class="col-sm-9"><input name="latitud" type="text" maxlength="99" class="form-control" value="<?php if (!empty($data)) echo $data['latitud']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Longitud</label>
							<div class="col-sm-9"><input name="longitud" type="text" maxlength="99" class="form-control" value="<?php if (!empty($data)) echo $data['longitud']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"><?php if (!empty($data)) echo $data['observaciones']; ?></textarea></div>
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
								if (!empty($data)) {
									if ($fila['id'] == $data['id_pais']) {
										echo " selected ";
									}
								} else {
									if ($fila['id']==13) {
										echo " selected ";
									}
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
								if (!empty($data)) {
									if ($fila['id'] == $data['id_provincia']) {
											echo " selected ";
									}	
								}
								echo ">".$fila['provincia']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<?php
							if (!empty($data)) {
							?>
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
							<?php							
							} else {
							?>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Localidad(*)</label>
							<div class="col-sm-9">
							<select name="id_localidad" id="id_localidad" class="js-example-basic-single col-sm-12" required="required">
							</select>
							</div>
							</div>
							<?php							
							}
							?>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo Estructura</label>
							<div class="col-sm-9">
							<select name="id_tipo_estructura" id="id_tipo_estructura" class="js-example-basic-single col-sm-12" >
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_estructura` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (!empty($data)) {
									if ($fila['id'] == $data['id_tipo_estructura']) {
											echo " selected ";
									}
								}
								echo ">".$fila['tipo']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Altura</label>
							<div class="col-sm-9"><input name="altura" type="number" step="0.01" class="form-control" value="<?php if (!empty($data)) echo $data['altura']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Ancho Cara</label>
							<div class="col-sm-9"><input name="ancho_cara" type="number" step="0.01" class="form-control" value="<?php if (!empty($data)) echo $data['ancho_cara']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Peso Estructura</label>
							<div class="col-sm-9"><input name="peso_estructura" type="number" step="0.01" class="form-control" value="<?php if (!empty($data)) echo $data['peso_estructura']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo Montante</label>
							<div class="col-sm-9">
							<select name="id_tipo_montaje" id="id_tipo_montaje" class="js-example-basic-single col-sm-12" >
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_montaje` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (!empty($data)) {
									if ($fila['id'] == $data['id_tipo_montaje']) {
											echo " selected ";
									}
								}
								echo ">".$fila['tipo']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Paso</label>
							<div class="col-sm-9"><input name="paso" type="number" step="0.01" class="form-control" value="<?php if (!empty($data)) echo $data['paso']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Beta</label>
							<div class="col-sm-9"><input name="beta" type="number" step="0.01" class="form-control" value="<?php if (!empty($data)) echo $data['beta']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Rugosidad</label>
							<div class="col-sm-9"><input name="rugosidad" type="text" maxlength="99" class="form-control" value="<?php if (!empty($data)) echo $data['rugosidad']; ?>"></div>
							</div>
							<input type="hidden" name="idSitioSuperior" value="<?php if (!empty($_GET['id'])) echo $_GET['id']; ?>">
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarSitios.php" class="btn btn-light">Volver</a>
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