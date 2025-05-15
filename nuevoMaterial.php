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
        
        $sql = "INSERT INTO `materiales`(`codigo`, `concepto`, `descripcion`, `largo`, `peso_metro`, `id_categoria`, `activo`, `id_unidad_medida`, `stock_minimo`, `anulado`, `calidad`) VALUES (?,?,?,?,?,?,1,?,?,0,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['codigo'],$_POST['concepto'],$_POST['descripcion'],$_POST['largo'],$_POST['peso_metro'],$_POST['id_categoria'],$_POST['id_unidad_medida'],$_POST['stock_minimo'],$_POST['calidad']]);
        
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Concepto','Conceptos','verConcepto.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        header("Location: listarMateriales.php");
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
          $ubicacion="Nuevo Concepto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
          <form class="form theme-form" role="form" method="post" action="nuevoMaterial.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
						<div class="form-group row">
							<label class="col-sm-3 col-form-label">Categoría(*)</label>
							<div class="col-sm-9">
							<select name="id_categoria" id="id_categoria" autofocus class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
											$pdo = Database::connect();
											$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
											$sqlZon = "SELECT `id`, `categoria` FROM `categorias` WHERE 1 order by categoria ";
											$q = $pdo->prepare($sqlZon);
											$q->execute();
											while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
												echo "<option value='".$fila['id']."'";
												echo ">".$fila['categoria']."</option>";
											}
											Database::disconnect();
											?>
							</select>
							</div>
						  </div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Código(*)</label>
							<div class="col-sm-9"><input name="codigo" type="text" maxlength="99" class="form-control" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Concepto(*)</label>
							<div class="col-sm-9"><input name="concepto" type="text" maxlength="99" class="form-control" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción</label>
							<div class="col-sm-9"><textarea name="descripcion" class="form-control"></textarea></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Unidad Medida(*)</label>
							<div class="col-sm-9">
							<select name="id_unidad_medida" id="id_unidad_medida" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
											$pdo = Database::connect();
											$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
											$sqlZon = "SELECT `id`, `unidad_medida` FROM `unidades_medida` WHERE 1 ";
											$q = $pdo->prepare($sqlZon);
											$q->execute();
											while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
												echo "<option value='".$fila['id']."'";
												echo ">".$fila['unidad_medida']."</option>";
											}
											Database::disconnect();
											?>
							</select>
							</div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Largo(*)</label>
							<div class="col-sm-9"><input name="largo" type="number" step="0.01" class="form-control" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Peso x Metro(*)</label>
							<div class="col-sm-9"><input name="peso_metro" type="number" step="0.01" class="form-control" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Stock Mínimo(*)</label>
							<div class="col-sm-9"><input name="stock_minimo" type="number" step="0.01" class="form-control" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Calidad</label>
							<div class="col-sm-9"><input name="calidad" type="text" maxlength="99" class="form-control"></div>
						  </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
            <a href="listarMateriales.php" class="btn btn-light">Volver</a>
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
  </body>
</html>