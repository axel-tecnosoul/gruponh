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
        header("Location: listarTareas.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		if (empty($_POST['id_recurso'])) {
			$_POST['id_recurso'] = null;
		}
		if (empty($_POST['id_coordinador'])) {
			$_POST['id_coordinador'] = null;
		}
		
		$sql = "UPDATE `tareas` set `id_proyecto` = ?, `estructura` = ?, `id_sector` = ?, `id_tipo_tarea` = ?, `id_recurso` = ?, `observaciones` = ?, `id_coordinador` = ?, `fecha_inicio_estimada` = ?, `fecha_fin_estimada` = ?, `fecha_inicio_real` = ?, `fecha_fin_real` = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['id_proyecto'],$_POST['estructura'],$_POST['id_sector'],$_POST['id_tipo_tarea'],$_POST['id_recurso'],$_POST['observaciones'],$_POST['id_coordinador'],$_POST['fecha_inicio_estimada'],$_POST['fecha_fin_estimada'],$_POST['fecha_inicio_real'],$_POST['fecha_fin_real'],$_GET['id']]);

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de tarea','Tareas','verTarea.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
        
        Database::disconnect();
        
        header("Location: listarTareas.php");
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_proyecto`, `estructura`, `id_sector`, `id_tipo_tarea`, `id_recurso`, `observaciones`, `id_coordinador`, `fecha_inicio_estimada`, `fecha_fin_estimada`, `fecha_inicio_real`, `fecha_fin_real`, `anulado` FROM `tareas` WHERE id = ? ";
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
          $ubicacion="Modificar Tarea";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarTarea.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
							<select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" autofocus required="required">
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
							<label class="col-sm-3 col-form-label">Estructura(*)</label>
							<div class="col-sm-9"><input name="estructura" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['estructura']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Sector(*)</label>
							<div class="col-sm-9">
							<select name="id_sector" id="id_sector" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `sector` FROM `sectores` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_sector']) {
                                        echo " selected ";
								}
								echo ">".$fila['sector']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo(*)</label>
							<div class="col-sm-9">
							<select name="id_tipo_tarea" id="id_tipo_tarea" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_tarea` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_tipo_tarea']) {
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
							<label class="col-sm-3 col-form-label">Recurso</label>
							<div class="col-sm-9">
							<select name="id_recurso" id="id_recurso" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (2,4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_recurso']) {
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
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"><?php echo $data['observaciones']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Coordinador</label>
							<div class="col-sm-9">
							<select name="id_coordinador" id="id_coordinador" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 4 and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_coordinador']) {
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
							<label class="col-sm-3 col-form-label">Fecha Inicio Estimada</label>
							<div class="col-sm-9"><input name="fecha_inicio_estimada" id="fecha_inicio_estimada" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_inicio_estimada']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Fin Estimada</label>
							<div class="col-sm-9"><input name="fecha_fin_estimada" id="fecha_fin_estimada" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_fin_estimada']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Inicio Real</label>
							<div class="col-sm-9"><input name="fecha_inicio_real" id="fecha_inicio_real" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_inicio_real']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Fin Real</label>
							<div class="col-sm-9"><input name="fecha_fin_real" id="fecha_fin_real" type="date"  onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_fin_real']; ?>"></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						<a onclick="document.location.href='listarProyectos.php'" class="btn btn-light">Volver</a>
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
	$("#fecha_fin_estimada").change(function () {
		var startDate = document.getElementById("fecha_inicio_estimada").value;
		var endDate = document.getElementById("fecha_fin_estimada").value;

		if ((Date.parse(startDate) > Date.parse(endDate))) {
			alert("La fecha de fin estimada debe ser mayor a la fecha de inicio estimada");
			document.getElementById("fecha_fin_estimada").value = "";
		}
	});
	$("#fecha_fin_real").change(function () {
		var startDate2 = document.getElementById("fecha_inicio_real").value;
		var endDate2 = document.getElementById("fecha_fin_real").value;

		if ((Date.parse(startDate2) > Date.parse(endDate2))) {
			alert("La fecha de fin real debe ser mayor a la fecha de inicio real");
			document.getElementById("fecha_fin_real").value = "";
		}
	});
	</script>
  </body>
</html>