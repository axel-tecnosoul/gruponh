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
        header("Location: listarUsuarios.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "delete from usuarios_tipos_notificacion where id_usuario = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        
        foreach ($_POST['notificaciones'] as $item) {
            $sql = "INSERT INTO `usuarios_tipos_notificacion`(`id_usuario`,`id_tipo_notificacion`) VALUES (?,?)";
            $q = $pdo->prepare($sql);
            $q->execute([$id,$item]);
        }
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de notificaciones de usuario','Perfiles','modificarUsuario.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
        
        Database::disconnect();
        
        header("Location: listarUsuarios.php");
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `usuario`, `contrasenia`, `id_perfil`, `email`, `fecha_alta`, `activo` FROM `usuarios` WHERE id = ? ";
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
          $ubicacion="Modificar Notificaciones x Usuario";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarNotificacionesUsuario.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
            
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Usuario</label>
                <div class="col-sm-9"><input name="perfil" type="text" maxlength="99" autofocus class="form-control" value="<?php echo $data['usuario']; ?>" readonly="readonly"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Notificaciones</label>
                <div class="col-sm-9">
                <select class="js-example-basic-multiple col-sm-12" name="notificaciones[]" id="notificaciones[]" multiple="multiple" required="required">
                  <?php
                                    $pdo = Database::connect();
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $sqlZon = "SELECT id, tipo from tipos_notificacion where 1 ";
                                    $q = $pdo->prepare($sqlZon);
                                    $q->execute();
                                    while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='".$fila['id']."'";
                                        
                                        $sql2 = " SELECT id from usuarios_tipos_notificacion where id_usuario = ? and id_tipo_notificacion = ? ";
                                        $q2 = $pdo->prepare($sql2);
                                        $q2->execute([$id, $fila['id']]);
                                        $data = $q2->fetch(PDO::FETCH_ASSOC);
                                        if (!empty($data)) {
                                            echo " selected ";
                                        }
                                        
                                        echo ">".$fila['tipo']."</option>";
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
                        <button class="btn btn-primary" type="submit">Configurar</button>
            <a onclick="document.location.href='listarUsuarios.php'" class="btn btn-light">Volver</a>
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