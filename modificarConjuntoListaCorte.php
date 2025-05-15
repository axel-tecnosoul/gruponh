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
    if (!empty($_POST['id_conjunto'])) {
      $id = $_REQUEST['id_conjunto'];
    }
    
    
    if (null==$id) {
        header("Location: listarListasCorte.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if(isset($_POST["btn1"]) or isset($_POST["btn2"])){
        
          $sql = "UPDATE listas_corte_conjuntos set nombre = ?, cantidad = ?, id_estado_lista_corte_conjuntos = ? where id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$_POST['nombre'],$_POST['cantidad'],$_POST['id_estado_conjunto'],$_POST['id_conjunto']]);

          $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de conjunto de lista de corte','Listas de Corte','imprimirListaCorte.php?id=".$_GET["id_lista_corte"]."')";
          $q = $pdo->prepare($sql);
          $q->execute(array($_SESSION['user']['id']));

          Database::disconnect();

          /*var_dump($_POST);
          var_dump($_GET);
          echo $id;
          var_dump(isset($_POST["btn1"]));
          var_dump(isset($_POST["btn2"]));
          die();*/
        
          if(isset($_POST["btn1"])){
            //echo "btn1";
            //echo "modificarListaCorte.php?id=".$_GET["id_lista_corte"]."&revision=".$_GET["revision"];
            //die();
            header("Location: modificarListaCorte.php?id=".$_GET["id_lista_corte"]."&revision=".$_GET["revision"]);
          }else{
            //echo "btn2";
            //die();
            header("Location: nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$_POST['id_conjunto']);
          }
        
        }else{
          
          $sql = "UPDATE listas_corte_conjuntos set nombre = ?, cantidad = ?, peso = ?, id_estado_lista_corte_conjuntos = ? where id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$_POST['nombre'],$_POST['cantidad'],$_POST['peso'],$_POST['id_estado_lista_corte_conjuntos'],$_GET['id']]);

          $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de conjunto de lista de corte','Listas de Corte','verConjunto.php?id=$id')";
          $q = $pdo->prepare($sql);
          $q->execute(array($_SESSION['user']['id']));

          Database::disconnect();
        
          header("Location: listarListasCorte.php");

        }
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT id, id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos FROM listas_corte_conjuntos WHERE id = ? ";
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
          $ubicacion="Modificar Conjunto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarConjuntoListaCorte.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nombre(*)</label>
							<div class="col-sm-9"><input name="nombre" type="text" maxlength="99" autofocus class="form-control" required="required" value="<?php echo $data['nombre']; ?>"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required" value="<?php echo $data['cantidad']; ?>"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Peso kg</label>
							<div class="col-sm-9"><input name="peso" type="number" step="0.01" class="form-control" value="<?php echo $data['peso']; ?>"></div>
						  </div>
						<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado(*)</label>
							<div class="col-sm-9">
							<select name="id_estado_lista_corte_conjuntos" id="id_estado_lista_corte_conjuntos" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_lista_corte_conjuntos` WHERE 1 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_estado_lista_corte_conjuntos']) {
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