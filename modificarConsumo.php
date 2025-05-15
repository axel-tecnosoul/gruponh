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
  header("Location: listarColadas.php");
}

if (!empty($_POST)) {
  
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  $sql = "UPDATE consumos_detalle set situacion = ?, cantidad = ?, id_unidad_medida = ?, observacion = ? where id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST['situacion'],$_POST['cantidad'],$_POST['id_unidad_medida'],$_POST['observacion'],$_GET['id']]);

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificación de Consumo','Consumos')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));
  
  Database::disconnect();

  header("Location: listarConsumos.php");
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT m.concepto, id_colada, situacion, cantidad, cd.id_unidad_medida, observacion FROM consumos_detalle cd INNER JOIN materiales m ON cd.id_material=m.id WHERE cd.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $checkedConsumo="";
  if($data["situacion"]=="Consumo"){
    $checkedConsumo="checked";
  }
  $checkedSobrante="";
  if($data["situacion"]=="Sobrante"){
    $checkedSobrante="checked";
  }
  
  Database::disconnect();
}?>

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
          $ubicacion="Modificar Consumo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				          <form class="form theme-form" role="form" method="post" action="modificarConsumo.php?id=<?php echo $id?>" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Material</label>
                            <div class="col-sm-9"><?=$data["concepto"]?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Situacion(*)</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="edo-ani">
                                <input class="radio_animated" value="Consumo" <?=$checkedConsumo?> required id="edo-ani" type="radio" name="situacion"><label for="edo-ani">Consumo</label>
                              </label>
                              <label class="d-block" for="edo-ani1">
                                <input class="radio_animated" value="Sobrante" <?=$checkedSobrante?> required id="edo-ani1" type="radio" name="situacion"><label for="edo-ani1">Sobrante</label>
                              </label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9"><input type="number" name="cantidad" step="0.01" value="<?=$data["cantidad"]?>" required class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Medida(*)</label>
                            <div class="col-sm-9">
                              <select name="id_unidad_medida" id="id_unidad_medida" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id,unidad_medida FROM unidades_medida";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if($fila['id']==$data['id_unidad_medida']){
                                    echo "selected";
                                  }
                                  echo ">".$fila['unidad_medida']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observacion</label>
                            <div class="col-sm-9"><input type="text" name="observacion" class="form-control"><?=$data["observacion"]?></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						            <a href='listarConsumos.php' class="btn btn-light">Volver</a>
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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	  <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
	
  </body>
</html>