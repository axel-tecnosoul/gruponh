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
       
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_lista_corte_posicion`, `id_tipo_proceso`, `id_estado_lista_corte_proceso`, `observaciones` FROM `lista_corte_procesos` WHERE id = ? ";
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
          $ubicacion="Actualizar Proceso de Posición de Conjunto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
						<div class="col">
						
						<div class="form-group row">
						<label class="col-sm-3 col-form-label">Tipo(*)</label>
						<div class="col-sm-9">
						<select name="id_tipo_proceso" id="id_tipo_proceso" class="js-example-basic-single col-sm-12" disabled="disabled">
						<option value="">Seleccione...</option>
						<?php
						$pdo = Database::connect();
						$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$sqlZon = "SELECT `id`, `tipo` FROM `tipos_procesos` WHERE 1";
						$q = $pdo->prepare($sqlZon);
						$q->execute();
						while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
							echo "<option value='".$fila['id']."'";
							if ($fila['id'] == $data['id_tipo_proceso']) {
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
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" type="text" class="form-control" disabled="disabled"><?php echo $data['observaciones']; ?></textarea></div>
						  </div>
						  
						  <div class="form-group row">
						<label class="col-sm-3 col-form-label">Estado(*)</label>
						<div class="col-sm-9">
						<select name="id_estado_lista_corte_proceso" id="id_estado_lista_corte_proceso" class="js-example-basic-single col-sm-12" disabled="disabled">
						<option value="">Seleccione...</option>
						<?php
						$pdo = Database::connect();
						$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$sqlZon = "SELECT `id`, `estado` FROM `estados_lista_corte_procesos` WHERE 1";
						$q = $pdo->prepare($sqlZon);
						$q->execute();
						while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
							echo "<option value='".$fila['id']."'";
							if ($fila['id'] == $data['id_estado_lista_corte_proceso']) {
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
						<a onclick="document.location.href='listarListasCorte.php'" class="btn btn-light">Volver</a>
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