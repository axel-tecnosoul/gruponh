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
        header("Location: listarCompras.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE `compras_detalle` set `precio`= ?, `precio_kg`= ?, `cantidad`= ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['precio'],$_POST['precio_kg'],$_POST['cantidad'],$_GET['id']]);
		
		$sql = " select cantidad,precio from compras_detalle where id_compra = ".$_POST['id_compra'];
		
		$total = 0;
		foreach ($pdo->query($sql) as $row) {
			$total += ($row[0]*$row[1]);
		}
		
		$sql = "update `compras` set total = ? where id = ?";
		$q = $pdo->prepare($sql);		   
		$q->execute([$total, $_POST['id_compra']]);
        
		Database::disconnect();
        
        header("Location: modificarCompra.php?id=".$_POST['id_compra']);
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT d.`id`, d.`id_compra`, m.`concepto`, d.`cantidad`, d.`id_unidad_medida`, d.`precio`, d.`entregado`, d.`precio_kg` FROM `compras_detalle` d inner join materiales m on m.id = d.id_material WHERE d.id = ? ";
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
          $ubicacion="Modificar Concepto de Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarConceptoCompra.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<input type="hidden" name="id_compra" value="<?php echo $data['id_compra']; ?>" />
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Concepto</label>
								<div class="col-sm-9"><input name="concepto" type="text" class="form-control" value="<?php echo $data['concepto']; ?>" readonly="readonly"></div>
							</div>
							
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Cantidad</label>
								<div class="col-sm-9"><input name="cantidad" type="number" step="1" class="form-control" value="<?php echo $data['cantidad']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Precio</label>
								<div class="col-sm-9"><input name="precio" type="number" step="0.01" class="form-control" value="<?php echo $data['precio']; ?>" required="required"></div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Precio x Kg</label>
								<div class="col-sm-9"><input name="precio_kg" type="number" step="0.01" class="form-control" value="<?php echo $data['precio_kg']; ?>" required="required"></div>
							</div>

                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						<a onclick="document.location.href='listarCompras.php'" class="btn btn-light">Volver</a>
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