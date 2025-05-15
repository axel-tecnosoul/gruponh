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
        header("Location: listarEstadosTareas.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$sql = "SELECT p.id_estado_proyecto, p.id_gerente, t.`id_tipo_tarea` FROM `tareas` t inner join proyectos p on p.id = t.id_proyecto WHERE t.id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['id_tarea']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		$id_estado_proyecto = $data['id_estado_proyecto'];
		$id_cuenta_gerente = $data['id_gerente'];
		$id_tipo_tarea = $data['id_tipo_tarea'];
        
        $sql = "UPDATE `estados_tareas` set `id_proyecto` = ?, `id_estado_proyecto` = ?, `id_cuenta_gerente` = ?, `id_cuenta_coordinador` = ?, `id_tipo_tarea` = ?, `id_cuenta_recurso` = ?, `id_tarea` = ?, `observaciones` = ?, `fecha_inicio_prevista` = ?, `fecha_fin_prevista` = ?, `fecha_inicio_real` = ?, `fecha_fin_real` = ?, `comentarios_inicio` = ?, `comentarios_fin` = ?, `presentado` = ?, `verificado` = ?, `aprobado_cliente` = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['id_proyecto'],$id_estado_proyecto,$id_cuenta_gerente,$_SESSION['user']['id'],$id_tipo_tarea,$_POST['id_cuenta_recurso'],$_POST['id_tarea'],$_POST['observaciones'],$_POST['fecha_inicio_prevista'],$_POST['fecha_fin_prevista'],$_POST['fecha_inicio_real'],$_POST['fecha_fin_real'],$_POST['comentarios_inicio'],$_POST['comentarios_fin'],$_POST['presentado'],$_POST['verificado'],$_POST['aprobado_cliente'],$_GET['id']]);
        
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de Estado de Tarea','Dashboard','verTarea.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        
        header("Location: listarEstadosTareas.php");
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_proyecto`, `id_estado_proyecto`, `id_cuenta_gerente`, `fecha_hora`, `id_cuenta_coordinador`, `id_tipo_tarea`, `id_cuenta_recurso`, `id_tarea`, `observaciones`, `fecha_inicio_prevista`, `fecha_fin_prevista`, `fecha_inicio_real`, `fecha_fin_real`, `comentarios_inicio`, `comentarios_fin`, `presentado`, `verificado`, `aprobado_cliente` FROM `estados_tareas` WHERE id = ? ";
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
          $ubicacion="Modificar Estado de Tarea";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarEstadoTarea.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
							<select name="id_proyecto" id="id_proyecto" autofocus class="js-example-basic-single col-sm-12" required="required" onChange="jsListarTareas(this.value);">
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
								<label class="col-sm-3 col-form-label">Tarea(*)</label>
								<div class="col-sm-9">
								<select name="id_tarea" id="id_tarea" class="js-example-basic-single col-sm-12" required="required">
								<option value="">Seleccione...</option>
								<?php
								$pdo = Database::connect();
								$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
								$sqlZon = "SELECT t.id,tt.tipo,t.observaciones FROM `tareas` t inner join tipos_tarea tt on tt.id = t.id_tipo_tarea WHERE t.`anulado` = 0";
								$q = $pdo->prepare($sqlZon);
								$q->execute();
								while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
									echo "<option value='".$fila['id']."'";
									if ($fila['id'] == $data['id_tarea']) {
											echo " selected ";
									}
									echo ">".$fila['tipo']." / ".$fila['observaciones']."</option>";
								}
								Database::disconnect();
								?>
								</select>
								</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Recurso(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_recurso" id="id_cuenta_recurso" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 4 and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_cuenta_recurso']) {
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
							<label class="col-sm-3 col-form-label">F.I.P</label>
							<div class="col-sm-9"><input name="fecha_inicio_prevista" id="fecha_inicio_prevista" type="date" onfocus="this.showPicker()" class="form-control" value="<?php echo $data['fecha_inicio_prevista']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">F.F.P</label>
							<div class="col-sm-9"><input name="fecha_fin_prevista" id="fecha_fin_prevista" type="date" onfocus="this.showPicker()" class="form-control" value="<?php echo $data['fecha_fin_prevista']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">F.I.R</label>
							<div class="col-sm-9"><input name="fecha_inicio_real" id="fecha_inicio_real" type="date" onfocus="this.showPicker()" class="form-control" value="<?php echo $data['fecha_inicio_real']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">F.F.R</label>
							<div class="col-sm-9"><input name="fecha_fin_real" id="fecha_fin_real" type="date"  onfocus="this.showPicker()" class="form-control" value="<?php echo $data['fecha_fin_real']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Comentarios de Inicio</label>
							<div class="col-sm-9"><textarea name="comentarios_inicio" maxlength="199" class="form-control"><?php echo $data['comentarios_inicio']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Comentarios de Fin</label>
							<div class="col-sm-9"><textarea name="comentarios_fin" maxlength="199" class="form-control"><?php echo $data['comentarios_fin']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">¿Presentado?</label>
							<div class="col-sm-9">
							<select name="presentado" id="presentado" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<option value="1" <?php if ($data['presentado']==1) {
                                    echo " selected ";
                                }?>>Si</option>
								<option value="0" <?php if ($data['presentado']==0) {
                                    echo " selected ";
                                }?>>No</option>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">¿Validado?</label>
							<div class="col-sm-9">
							<select name="verificado" id="verificado" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<option value="1" <?php if ($data['verificado']==1) {
                                    echo " selected ";
                                }?>>Si</option>
								<option value="0" <?php if ($data['verificado']==0) {
                                    echo " selected ";
                                }?>>No</option>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">¿Aprobado por el Cliente?</label>
							<div class="col-sm-9">
							<select name="aprobado_cliente" id="aprobado_cliente" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<option value="1" <?php if ($data['aprobado_cliente']==1) {
                                    echo " selected ";
                                }?>>Si</option>
								<option value="0" <?php if ($data['aprobado_cliente']==0) {
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
						<a onclick="document.location.href='listarEstadosTareas.php'" class="btn btn-light">Volver</a>
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
	function jsListarTareas(val) { 
		$.ajax({
			type: "GET",
			url: "ajaxTareas.php",
			data: "id_proyecto="+val,
			success: function(resp){
				$("#id_tarea").html(resp);
			}
		});
	}
	</script>
	<script>
	$("#fecha_fin_prevista").change(function () {
		var startDate = document.getElementById("fecha_inicio_prevista").value;
		var endDate = document.getElementById("fecha_fin_prevista").value;

		if ((Date.parse(startDate) > Date.parse(endDate))) {
			alert("La fecha de fin prevista debe ser mayor a la fecha de inicio prevista");
			document.getElementById("fecha_fin_prevista").value = "";
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