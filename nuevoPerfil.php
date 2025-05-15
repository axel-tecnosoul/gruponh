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
        
        $sql = "INSERT INTO `perfiles`(`perfil`) VALUES (?)";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['perfil']]);

        $idPerfil = $pdo->lastInsertId();
        
        foreach ($_POST['permisos'] as $item) {
            $sql = "INSERT INTO `permisos_perfil`(`id_permiso`,`id_perfil`) VALUES (?,?)";
            $q = $pdo->prepare($sql);
            $q->execute([$item,$idPerfil]);
        }

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Perfil de Usuario','Perfiles','verPerfil.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

        Database::disconnect();
        
        header("Location: listarPerfiles.php");
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
          $ubicacion="Nuevo Perfil";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
          <form class="form theme-form" role="form" method="post" action="nuevoPerfil.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
            
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Perfil</label>
                <div class="col-sm-9"><input name="perfil" type="text" autofocus maxlength="99" class="form-control" value="" required="required"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Permisos</label>
                <div class="col-sm-9">
                <select class="js-example-basic-multiple col-sm-12" name="permisos[]" id="permisos[]" multiple="multiple" required="required">
                  <?php
                                    $pdo = Database::connect();
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $sqlZon = "SELECT id, permiso from permisos where anulado = 0 order by permiso";
                                    $q = $pdo->prepare($sqlZon);
                                    $q->execute();
                                    while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='".$fila['id']."'";
                                        echo ">".$fila['permiso']."</option>";
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
                        <button class="btn btn-primary" type="submit">Crear</button>
            <a href="listarPerfiles.php" class="btn btn-light">Volver</a>
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