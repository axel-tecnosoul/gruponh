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
		
		$sql = "INSERT INTO `packing_lists_componentes`(`id_packing_list_seccion`, `id_conjunto_lista_corte`, `id_concepto`, `cantidad`, `observaciones`, `id_estado_componente_packing_list`) VALUES (?,?,?,?,?,1)";
        $q = $pdo->prepare($sql);
        $q->execute([$_GET['id'],$_POST['id_conjunto_lista_corte'],$_POST['id_concepto'],$_POST['cantidad'],$_POST['observaciones']]);
        
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Componente de Sección en Packing List','Packing List','verPackingList.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        header("Location: listarPackingList.php");
    } 
	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$sql = "select plr.id_proyecto, pls.observaciones from packing_lists_secciones pls inner join packing_lists_revisiones plr on plr.id = pls.id_packing_list_revision where pls.id = ".$_GET['id'];
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$idProyecto=$data['id_proyecto'];
	$nombreSeccion=$data['observaciones'];

    
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
          $ubicacion="Nuevo Componente de Sección: ".$nombreSeccion;
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" action="nuevoComponentePackingList.php?id=<?php echo $_GET['id']?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
						<div class="form-group row">
						<label class="col-sm-3 col-form-label">Concepto</label>
						<div class="col-sm-9">
						<select name="id_concepto" id="id_concepto" class="js-example-basic-single col-sm-12" autofocus>
						<option value="">Seleccione...</option>
						<?php
						$pdo = Database::connect();
						$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$sqlZon ="SELECT m.id, m.codigo, m.concepto, cd.reservado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join packing_lists_revisiones plr on plr.id_proyecto = p.id INNER JOIN packing_lists pl ON plr.id_packing_list=pl.id AND pl.ultimo_nro_revision=plr.nro_revision inner join packing_lists_secciones pls on pls.id_packing_list_revision = plr.id where cd.cancelado = 0 and p.id = ".$idProyecto;
						$q = $pdo->prepare($sqlZon);
						$q->execute();
						while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
							if ($fila['reservado'] > 0) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['concepto']." (".$fila['codigo'].") - Reservado: ".$fila['reservado']."</option>";	
							}							
						}
						Database::disconnect();
						?>
						</select>
						</div>
						</div>
						<div class="form-group row">
						<label class="col-sm-3 col-form-label">Conjunto de LC</label>
						<div class="col-sm-9">
						<select name="id_conjunto_lista_corte" id="id_conjunto_lista_corte" class="js-example-basic-single col-sm-12" onchange="jsCompletarCant(this.value);">
                              <option value="">Seleccione...</option><?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $sqlZon = "SELECT lcc.`id`, lcc.`nombre`, lcc.`cantidad` FROM `listas_corte_conjuntos` lcc inner join listas_corte_revisiones lcr on lcr.id = lcc.id_lista_corte inner join proyectos p on p.id = lcr.id_proyecto WHERE lcr.id_proyecto = ".$idProyecto;
                              $q = $pdo->prepare($sqlZon);
                              $q->execute();
                              while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='".$fila['id']."'";
                                echo ">".$fila['nombre']." (".$fila['cantidad'].")</option>";
                              }
                              Database::disconnect();?>
                            </select>
						</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones(*)</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control" required="required"></textarea></div>
						  </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarPackingList.php" class="btn btn-light">Volver</a>
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