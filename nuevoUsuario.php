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
        
        $sql = "SELECT count(id) cant from usuarios where usuario = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['usuario']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        if ($data['cant'] == 0) {
            $sql = "INSERT INTO `usuarios`(`usuario`, `contrasenia`, `id_perfil`, `email`, `fecha_alta`, `activo`) VALUES (?,?,?,?,now(),1)";
            $q = $pdo->prepare($sql);
            $q->execute([$_POST['usuario'],md5($_POST['clave1']),$_POST['id_perfil'],$_POST['email']]);
        }
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Usuario','Usuarios','verUsuario.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        header("Location: listarUsuarios.php");
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
          $ubicacion="Nuevo Usuario";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
          <form class="form theme-form" role="form" method="post" action="nuevoUsuario.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
            
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Perfil</label>
                <div class="col-sm-9">
                <select name="id_perfil" id="id_perfil" class="js-example-basic-single col-sm-12" required="required" autofocus>
                <option value="">Seleccione...</option>
                <?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `perfil` FROM `perfiles` WHERE anulado = 0 AND 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='".$fila['id']."'";
                                    echo ">".$fila['perfil']."</option>";
                                }
                                Database::disconnect();
                                ?>
                </select>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Usuario</label>
                <div class="col-sm-9"><input name="usuario" type="text" maxlength="99" class="form-control" required="required"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Contraseña</label>
                <div class="col-sm-9"><input name="clave1" id="password" type="password" maxlength="99" class="form-control" required="required"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Repetir Contraseña</label>
                <div class="col-sm-9"><input name="clave2" id="confirm_password" type="password" maxlength="99" class="form-control" required="required"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Email</label>
                <div class="col-sm-9"><input name="email" type="email" maxlength="99" class="form-control" required="required"></div>
              </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
            <a href="listarUsuarios.php" class="btn btn-light">Volver</a>
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
  <script>
  var password = document.getElementById("password")
    , confirm_password = document.getElementById("confirm_password");

  function validatePassword(){
    if(password.value != confirm_password.value) {
    confirm_password.setCustomValidity("Las claves no coinciden");
    } else {
    confirm_password.setCustomValidity('');
    }
  }

  password.onchange = validatePassword;
  confirm_password.onkeyup = validatePassword;
  </script>
  </body>
</html>