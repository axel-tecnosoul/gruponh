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
        header("Location: listarComputos.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
		//if ($_GET['modo'] == "nuevo") {
			$sql = "update `computos_detalle` set `id_material`= ?, `cantidad`= ?, `fecha_necesidad`= ?, `aprobado` = 0, comentarios=? where id = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$_POST['id_material'],$_POST['cantidad'],$_POST['fecha_necesidad'],$_POST['comentarios'],$id]);
		/*
		} else if ($_GET['modo'] == "update") {
			
			$sql = "select id,nro_revision,id_tarea,fecha,id_cuenta_solicitante,nro FROM `computos` where id = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$_GET['idRetorno']]);
			$dataC = $q->fetch(PDO::FETCH_ASSOC);
				
			$nro = $_POST['nro_revision']+1;
			
			$sql = "insert into `computos` (`nro_revision`, `id_tarea`, `fecha`, `id_cuenta_solicitante`, `id_estado`, `nro_computo`, `comentarios_revision`, `fecha_hora_revision`,nro) values (?,?,?,?,2,?,?,now(),?)";
			$q = $pdo->prepare($sql);
			$q->execute([$nro,$dataC['id_tarea'],$dataC['fecha'],$dataC['id_cuenta_solicitante'],$dataC['id'],$_POST['comentarios'],$dataC['nro']]);
			
			$idNuevoComputo = $pdo->lastInsertId();
			$sqlList = " SELECT `id_material`, `cantidad`, `fecha_necesidad`, `aprobado`, `reservado`, `comprado`, `cancelado` FROM `computos_detalle` WHERE `id_computo` = ".$id;
			foreach ($pdo->query($sqlList) as $row) {
				$sql = "INSERT INTO `computos_detalle`(`id_computo`, `id_material`, `cantidad`, `fecha_necesidad`, `aprobado`, `reservado`, `comprado`, `cancelado`) VALUES (?,?,?,?,?,?,?,?)";
				$q = $pdo->prepare($sql);
				$q->execute([$idNuevoComputo,$row[0],$row[1],$row[2],$row[3],$row[4],$row[5],$row[6]]);
			}
			
			$sql = "update `computos_detalle` set `id_material`= ?, `cantidad`= ?, `fecha_necesidad`= ?, `aprobado` = 0, comentarios=? where id = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$_POST['id_material'],$_POST['cantidad'],$_POST['fecha_necesidad'],$_POST['comentarios'],$idNuevoComputo]);

			
		}

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Se ha modificado un item de un cómputo','Cómputos','verComputo.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		*/
        Database::disconnect();
        
        header("Location: itemsComputo.php?id=".$_GET['idRetorno']."&modo=".$_GET['modo']."&revision=".$nro);
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_computo`, `id_material`, `cantidad`, `fecha_necesidad`, `aprobado`, `reservado`, `comprado`, `cancelado`, comentarios FROM `computos_detalle` WHERE id = ? ";
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
          $ubicacion="Modificar Item Cómputo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarItemComputo.php?id=<?php echo $id?>&idRetorno=<?php echo $_GET['idRetorno']?>&modo=<?php echo $_GET['modo']?>&revision=<?php echo $_GET['revision']?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Concepto(*)</label>
							<div class="col-sm-9">
							<select name="id_material" id="id_material"  autofocus class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `concepto`, `codigo` FROM `materiales` WHERE anulado = 0 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($data['id_material']==$fila['id']) {
									echo " selected ";
								}
								echo ">".$fila['concepto']." (".$fila['codigo'].")</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" step="0.01" min="0.01" type="number" class="form-control" required="required" value="<?php echo $data['cantidad'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Necesidad(*)</label>
							<div class="col-sm-9"><input name="fecha_necesidad" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?php echo $data['fecha_necesidad'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Comentarios</label>
							<div class="col-sm-9"><textarea name="comentarios" class="form-control"><?php echo $data['comentarios'];?></textarea></div>
							<input type="hidden" name="nro_revision" value="<?php if (!empty($_GET['revision'])) { echo $_GET['revision']; }else { echo "0"; } ?>">
							<input type="hidden" name="modo" value="<?php echo $_GET['modo']; ?>">
						  </div>
						</div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						<a onclick="document.location.href='listarComputos.php'" class="btn btn-light">Volver</a>
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