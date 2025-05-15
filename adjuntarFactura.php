<?php
    require("config.php");
    if(empty($_SESSION['user']))
    {
        header("Location: index.php");
        die("Redirecting to index.php"); 
    }
	
	require 'database.php';

	$id = null;
	if ( !empty($_GET['id'])) {
		$id = $_REQUEST['id'];
	}
	
	if ( null==$id ) {
		header("Location: listarCompras.php");
	}
	
	if ( !empty($_POST)) {
		
		// insert data
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		/*
		$filename = $_FILES['adjunto']['name'];
		move_uploaded_file($_FILES['adjunto']['tmp_name'],'adjuntos_compras/'.$id.'_'.$filename);
			
		$sql = "update `compras` set adjunto_factura = ? where id = ?";
		$q = $pdo->prepare($sql);
		$q->execute(array($id.'_'.$filename,$id));
		*/
		$sql = "update `compras` set adjunto_factura = ? where id = ?";
		$q = $pdo->prepare($sql);
		$q->execute(array($_POST['adjunto'],$id));
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva factura adjunta a orden de compra','Compras','verCompra.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
		Database::disconnect();
		header("Location: listarCompras.php?id=".$id);	
		
	} else {
		
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = "SELECT `id`, `id_pedido`, `id_cuenta_proveedor`, `fecha_emision`, `fecha_entrega`, `id_forma_pago`, `id_estado_compra`, `nro_oc`, `total`, `comentarios`, `adjunto_factura` FROM `compras` WHERE id = ? ";
        $q = $pdo->prepare($sql);
		$q->execute(array($id));
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
        <div class="page-body">
          <div class="container-fluid">
            <div style="padding-top:10px">
              
            </div>
          </div>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Adjuntar Factura</h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="adjuntarFactura.php?id=<?php echo $id?>" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<!--
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Archivo(*)</label>
								<div class="col-sm-9"><input name="adjunto" type="file" value="" class="form-control" required="required"></div>
								<input type="hidden" name="hId" value="1" />
							</div>
							-->
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Archivo(*)</label>
								<div class="col-sm-9"><input name="adjunto" type="text" value="" class="form-control" required="required"></div>
								<input type="hidden" name="hId" value="1" />
							</div>
							
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Adjuntar Factura</button>
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
	
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
  </body>
</html>