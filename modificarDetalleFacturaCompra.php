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
	
		$subtotal = $_POST['precio']*$_POST['cantidad'];
		
		$sql = "update `facturas_compra_detalle` set `id_concepto_contable` = ?, `cantidad` = ?, `precio` = ?, `subtotal` = ? where id = ?";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_POST['id_concepto_contable'],$_POST['cantidad'],$_POST['precio'],$subtotal,$_GET['id']]);
        
		$gravado = 0;
		$noGravado = 0;
		$iva = 0;
		$total = 0;
		
		$sql = " SELECT `cantidad`, `precio`, `subtotal` FROM `facturas_compra_detalle` WHERE `id_factura_compra` = ".$_POST['fc'];    
		foreach ($pdo->query($sql) as $row) {
			$total += $row[2];
			$noGravadoParcial = $row[1]*$row[0];
			$noGravado += $noGravadoParcial;
			$iva += $noGravado *0.21;
			$gravado += $noGravado + $iva;
		}
		
		$sql = "update `facturas_compra` set  `subtotal_gravado` = ?, `subtotal_no_gravado` = ?, `iva` = ?, `total` = ? where id = ?";
		$q = $pdo->prepare($sql);		   
		$q->execute([$gravado, $noGravado, $iva, $total, $_POST['fc']]);
		$id = $_POST['fc'];
		
		$sql = "delete from facturas_compra_detalle_x_compras_detalle where id_factura_compra_detalle = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
		
		foreach ($_POST['imputaciones'] as $item) {
            $sql = "INSERT INTO `facturas_compra_detalle_x_compras_detalle`(`id_factura_compra_detalle`, `id_compra_detalle`) VALUES (?,?)";
            $q = $pdo->prepare($sql);
            $q->execute([$_GET['id'],$item]);
        }
        
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación Ítem Detalle de Factura de Compra','Facturas de Compra','verCompra.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        header("Location: listarFacturasCompra.php");
		
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_factura_compra`, id_concepto_contable, `cantidad`, `precio`, `subtotal` FROM `facturas_compra_detalle` WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_GET['id']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		
		$sql2 = "select c.total,c.id from compras c inner join facturas_compra f on f.id_orden_compra = c.id where f.id = ? ";
        $q2 = $pdo->prepare($sql2);
        $q2->execute([$data['id_factura_compra']]);
        $data2 = $q2->fetch(PDO::FETCH_ASSOC);
        $totalOC = $data2['total'];
		$idOC = $data2['id'];
		
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
          $ubicacion="Modificar Ítem Detalle de Factura Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="modificarDetalleFacturaCompra.php?id=<?php echo $_GET['id'];?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Concepto Contable(*)</label>
							<div class="col-sm-9">
							<select name="id_concepto_contable" id="id_concepto_contable" class="js-example-basic-single col-sm-12" required="required" autofocus>
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `codigo`, `descripcion` FROM `conceptos_contables` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($data['id_concepto_contable']==$fila['id']){
									echo " selected ";
								}
								echo ">".$fila['descripcion']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Imputaciones(*)</label>
							<div class="col-sm-9">
							<select class="js-example-basic-multiple col-sm-12" name="imputaciones[]" id="imputaciones[]" multiple="multiple" required="required">
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT d.`id`, m.concepto, d.`cantidad` FROM `compras_detalle` d inner join materiales m on m.id = d.`id_material` WHERE d.`id_compra` = ".$idOC;
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								
								$sql2 = " SELECT id from facturas_compra_detalle_x_compras_detalle where id_factura_compra_detalle = ? and id_compra_detalle = ? ";
								$q2 = $pdo->prepare($sql2);
								$q2->execute([$_GET['id'], $fila['id']]);
								$data2 = $q2->fetch(PDO::FETCH_ASSOC);
								if (!empty($data2)) {
									echo " selected ";
								}
								
								echo ">".$fila['concepto']." (x".$fila['cantidad'].")</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required" value="<?php echo $data['cantidad'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Precio(*)</label>
							<div class="col-sm-9"><input name="precio" type="number" step="0.01" class="form-control" required="required" value="<?php echo $data['precio'];?>"></div>
							</div>
											
							<input type="hidden" name="fc" value="<?php echo $data['id_factura_compra'];?>">
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						<a href="listarFacturasCompra.php" class="btn btn-light">Volver</a>
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