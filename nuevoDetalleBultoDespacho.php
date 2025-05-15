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
		
		if (!empty($_POST['id_componente_concepto'])) {
			$tipoDetalle = 2;
			$detalleBulto = $_POST['id_componente_concepto'];
			$origenBulto = 0; //falta calcular
		} else {
			$tipoDetalle = 1;
			$detalleBulto = $_POST['id_componente_conjunto'];
			$origenBulto = 0; //falta calcular
		}
		
		$sql = "update packing_lists_componentes set cantidad_despachada = cantidad_despachada + ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['cantidad'],$detalleBulto]);
		
		$sql = "INSERT INTO `bultos_detalle`(`id_bulto`, `id_tipo_bulto`, `id_origen_bulto`, `id_detalle_bulto`, `cantidad`) VALUES (?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$_GET['id'],$tipoDetalle,$origenBulto,$detalleBulto,$_POST['cantidad']]);
        
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Detalle de Bulto en despacho','Despachos','verDespacho.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        header("Location: listarDespachos.php");
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
          $ubicacion="Nuevo Detalle de Bulto en Despacho";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" action="nuevoDetalleBultoDespacho.php?id=<?php echo $_GET['id']?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
						<div class="form-group row">
						<label class="col-sm-3 col-form-label">Conceptos de PL Componentes</label>
						<div class="col-sm-9">
						<select name="id_componente_concepto" id="id_componente_concepto" autofocus class="js-example-basic-single col-sm-12">
						<option value="">Seleccione...</option>
						<?php
						$pdo = Database::connect();
						$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$sqlZon = "SELECT c.`id`, m.concepto, c.`cantidad`, c.`cantidad_despachada` FROM `packing_lists_componentes` c inner join `materiales` m on m.id = c.`id_concepto` WHERE c.`id_concepto` is not null and c.id_estado_componente_packing_list = 1 ";
						$q = $pdo->prepare($sqlZon);
						$q->execute();
						while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
							echo "<option value='".$fila['id']."'";
							echo ">".$fila['concepto']." (Solicitado: ".$fila['cantidad']." - Despachado: ".$fila['cantidad_despachada'].")</option>";	
						}
						Database::disconnect();
						?>
						</select>
						</div>
						</div>
						<div class="form-group row">
						<label class="col-sm-3 col-form-label">Conjuntos de PL Componentes</label>
						<div class="col-sm-9">
						<select name="id_componente_conjunto" id="id_componente_conjunto" class="js-example-basic-single col-sm-12">
						<option value="">Seleccione...</option>
						<?php
						$pdo = Database::connect();
						$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$sqlZon = "SELECT c.`id`, lc.nombre, c.`cantidad`, c.cantidad_despachada FROM `packing_lists_componentes` c inner join `listas_corte_conjuntos` lc on lc.id = c.`id_conjunto_lista_corte` WHERE c.`id_conjunto_lista_corte` is not null and lc.id_estado_lista_corte_conjuntos = 4 and c.id_estado_componente_packing_list = 1 ";
						$q = $pdo->prepare($sqlZon);
						$q->execute();
						while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
							echo "<option value='".$fila['id']."'";
							echo ">".$fila['nombre']." (Solicitado: ".$fila['cantidad']." - Despachado: ".$fila['cantidad_despachada'].")</option>";	
						}
						Database::disconnect();
						?>
						</select>
						</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required"></div>
						  </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarDespachos.php" class="btn btn-light">Volver</a>
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