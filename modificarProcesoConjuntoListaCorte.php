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
        header("Location: listarListasCorte.php");
    }
    
    if (!empty($_POST)) {
		
		 // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE `listas_corte_detalle_posiciones_procesos` set `id_terminacion` = ?, `detalle_proceso` = ?, `id_cuenta_operario` = ?, `fecha_inicio_proyectada` = ?, `fecha_fin_proyectada` = ?, `fecha_inicio_real` = ?, `fecha_fin_real` = ?, `id_estado_detalle_lista_corte_posiciones_procesos` = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['id_terminacion'],$_POST['detalle_proceso'],$_POST['id_cuenta_operario'],$_POST['fecha_inicio_proyectada'],$_POST['fecha_fin_proyectada'],$_POST['fecha_inicio_real'],$_POST['fecha_fin_real'],$_POST['id_estado_detalle_lista_corte_posiciones_procesos'],$_GET['id']]);

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de proceso ID #$id de conjunto de lista de corte','Listas de Corte','')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

        
        Database::disconnect();
		
		header("Location: procesosConjuntosListaCorte.php?id=".$_GET['idLCC']);
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_lista_corte_detalle_posicion`, `id_terminacion`, `detalle_proceso`, `id_cuenta_operario`, `fecha_inicio_proyectada`, `fecha_fin_proyectada`, `fecha_inicio_real`, `fecha_fin_real`, `id_estado_detalle_lista_corte_posiciones_procesos` FROM `listas_corte_detalle_posiciones_procesos` WHERE id = ? ";
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
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
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
          $ubicacion="Modificar Proceso de Conjunto de Lista de Corte";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" action="modificarProcesoConjuntoListaCorte.php?id=<?php echo $id;?>&idLCC=<?php echo $_GET['idLCC'];?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Terminación(*)</label>
							<div class="col-sm-9">
							<select name="id_terminacion" id="id_terminacion" class="js-example-basic-single col-sm-12" autofocus required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `terminacion` FROM `terminaciones_lista_corte` WHERE 1 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_terminacion']) {
									echo " selected ";
								}
								echo ">".$fila['terminacion']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Detalle(*)</label>
							<div class="col-sm-9"><input name="detalle_proceso" type="text" class="form-control" required="required" value="<?php echo $data['detalle_proceso'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Operario(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_operario" id="id_cuenta_operario" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_cuenta_operario']) {
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
							<label class="col-sm-3 col-form-label">FIP</label>
							<div class="col-sm-9"><input name="fecha_inicio_proyectada" id="fecha_inicio_proyectada" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha_inicio_proyectada'];?>" class="form-control" ></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">FFP</label>
							<div class="col-sm-9"><input name="fecha_fin_proyectada" id="fecha_fin_proyectada" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha_fin_proyectada'];?>" class="form-control" ></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">FIR</label>
							<div class="col-sm-9"><input name="fecha_inicio_real" id="fecha_inicio_real" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha_inicio_real'];?>" class="form-control" ></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">FFR</label>
							<div class="col-sm-9"><input name="fecha_fin_real" id="fecha_fin_real" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha_fin_real'];?>" class="form-control"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado(*)</label>
							<div class="col-sm-9">
							<select name="id_estado_detalle_lista_corte_posiciones_procesos" id="id_estado_detalle_lista_corte_posiciones_procesos" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_detalle_lista_corte_posiciones_procesos` WHERE 1 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_estado_detalle_lista_corte_posiciones_procesos']) {
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
					    <button class="btn btn-primary" type="submit">Modificar</button>
                        <a href="#" onclick="document.location.href='procesosConjuntosListaCorte.php?id=<?php echo $_GET['idLCC'];?>'" class="btn btn-light">Volver</a>
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
	<script>
	$("#fecha_fin_proyectada").change(function () {
		var startDate = document.getElementById("fecha_inicio_proyectada").value;
		var endDate = document.getElementById("fecha_fin_proyectada").value;

		if ((Date.parse(startDate) > Date.parse(endDate))) {
			alert("La fecha de fin proyectada debe ser mayor a la fecha de inicio proyectada");
			document.getElementById("fecha_fin_proyectada").value = "";
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
    <!-- Plugins JS Ends-->
	
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->

  </body>
</html>