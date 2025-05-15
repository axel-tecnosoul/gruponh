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
  $sql = "SELECT id, id_lista_corte_conjunto, id_material, posicion, cantidad, largo, ancho, marca, peso, finalizado, id_colada, diametro, calidad FROM lista_corte_posiciones WHERE id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_lista_corte_conjunto=$data["id_lista_corte_conjunto"];
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
          $ubicacion="Ver Posición de Conjunto";
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
                            <label class="col-sm-3 col-form-label">Concepto(*)</label>
                              <div class="col-sm-9">
                                <select name="id_material" id="id_material" class="js-example-basic-single col-sm-12" disabled="disabled">
                                  <option value="">Seleccione...</option><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "select m.id, m.codigo, m.concepto from materiales m ";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='".$fila['id']."'";
                                    if ($fila['id'] == $data['id_material']) {
                                      echo " selected ";
                                    }
                                    echo ">".$fila['concepto']." (".$fila['codigo'].") </option>";	
                                  }
                                  Database::disconnect();?>
                                </select>
                              </div>
						                </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Posición(*)</label>
                              <div class="col-sm-9"><input name="posicion" type="text" maxlength="99" class="form-control" readonly="readonly" value="<?php echo $data['posicion']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                              <div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" readonly="readonly" value="<?php echo $data['cantidad']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Largo</label>
                              <div class="col-sm-9"><input name="largo" type="number" step="0.01" class="form-control" readonly="readonly" value="<?php echo $data['largo']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Ancho</label>
                              <div class="col-sm-9"><input name="ancho" type="number" step="0.01" class="form-control" readonly="readonly" value="<?php echo $data['ancho']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Diámetro</label>
                              <div class="col-sm-9"><input name="diametro" type="number" step="0.01" class="form-control" readonly="readonly" value="<?php echo $data['diametro']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Peso kg</label>
                              <div class="col-sm-9"><input name="peso" type="number" step="0.01" class="form-control" readonly="readonly" value="<?php echo $data['peso']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Marca</label>
                              <div class="col-sm-9"><input name="marca" type="text" maxlength="99" class="form-control" readonly="readonly" value="<?php echo $data['marca']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Calidad</label>
                              <div class="col-sm-9"><input name="calidad" type="text" maxlength="99" class="form-control" readonly="readonly" value="<?php echo $data['calidad']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">¿Finalizado?(*)</label>
                              <div class="col-sm-9">
                                <select name="finalizado" id="finalizado" class="js-example-basic-single col-sm-12" disabled="disabled">
                                  <option value="">Seleccione...</option>
                                  <option value="0" <?php if ($data['finalizado']==0) { echo "selected"; } ?>>No</option>
                                  <option value="1" <?php if ($data['finalizado']==1) { echo "selected"; } ?>>Si</option>
                                </select>
                              </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						          <a href='nuevaListaCortePosiciones.php?id_lista_corte_conjunto=<?=$id_lista_corte_conjunto?>' class="btn btn-light">Volver</a>
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