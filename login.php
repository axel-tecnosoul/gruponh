<?php
// 1) Arranca la sesión (necesario antes de usar $_SESSION)
session_start();

// 2) Si ya está logueado, lo mandamos al dashboard
if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

// 3) Conectar BD (sin incluir aquí todo el middleware de sesión)
require 'database.php';

$submitted_username = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = Database::connect();

    try {
        $sql = "SELECT id, usuario, contrasenia, id_perfil, fecha_alta, activo
                FROM usuarios
                WHERE activo = 1
                  AND usuario = :user";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user' => $_POST['user']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && md5($_POST['pass']) === $row['contrasenia']) {
            // 4) Éxito: guardar datos en sesión
            unset($row['contrasenia']);
            $_SESSION['user'] = $row;

            // 5) Cargar permisos
            $permisos = [];
            $permSql = "SELECT ap.id_accion
                        FROM acciones_permisos ap
                        JOIN permisos_perfil pp ON ap.id_permiso = pp.id_permiso
                        WHERE pp.id_perfil = :perfil";
            $permStmt = $pdo->prepare($permSql);
            $permStmt->execute([':perfil' => $row['id_perfil']]);
            while ($r = $permStmt->fetch(PDO::FETCH_NUM)) {
                $permisos[] = $r[0];
            }
            $_SESSION['user']['permisos'] = $permisos;

            // 6) Redirigir al dashboard
            header('Location: dashboard.php');
            exit;

        } else {
            $error = 'Usuario o contraseña incorrectos';
            $submitted_username = htmlentities($_POST['user'], ENT_QUOTES, 'UTF-8');
        }

    } catch (PDOException $ex) {
        die('Error en la consulta: ' . $ex->getMessage());
    } finally {
        Database::disconnect();
    }
}
/*$submitted_username = '';
if (!empty($_POST)) {
  //require("config.php");
  require 'database.php';

  $pdo = Database::connect();

  try {
    $sql = "SELECT `id`, `usuario`, `contrasenia`, `id_perfil`, `fecha_alta`, `activo` 
            FROM `usuarios` 
            WHERE activo = 1 AND usuario = :user";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user' => $_POST['user']]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $login_ok = false;

    if ($row) {
      $check_pass = md5($_POST['pass']); // Recomendado: cambiar a password_hash en el futuro
      if ($check_pass === $row['contrasenia']) {
        $login_ok = true;
      }
    }

  } catch (PDOException $ex) {
    die("Error en la consulta: " . $ex->getMessage());
  } finally {
    Database::disconnect();
  }
  
  $error = "";
  if ($login_ok) {
    unset($row['contrasenia']);
    $_SESSION['user'] = $row;
    
    $pdo = Database::connect();
    $sql = " SELECT ap.id_accion FROM `acciones_permisos` ap  inner join permisos_perfil pp on ap.id_permiso = pp.id_permiso WHERE pp.id_perfil =  ".$row['id_perfil'];
    //var_dump($sql);
    $permisos = null;
    foreach ($pdo->query($sql) as $row2) {
      $permisos[] = $row2[0];
    }
    
    Database::disconnect();
    $_SESSION['user']['permisos'] = $permisos;

    header("Location: dashboard.php");
    die("Redirecting to: dashboard.php");
  } else {
    $error = "Usuario o Contraseña incorrecto!";
    $submitted_username = htmlentities($_POST['user'], ENT_QUOTES, 'UTF-8');
  }
}*/?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="endless admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities.">
    <meta name="keywords" content="admin template, endless admin template, dashboard template, flat admin template, responsive admin template, web app">
    <meta name="author" content="pixelstrap">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    <title>GrupoNH - Sistema de Gestión</title>
    <!-- Google font-->
    <link href="https://fonts.googleapis.com/css?family=Work+Sans:100,200,300,400,500,600,700,800,900" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Poppins:100,100i,200,200i,300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <!-- Font Awesome-->
    <link rel="stylesheet" type="text/css" href="assets/css/fontawesome.css">
    <!-- ico-font-->
    <link rel="stylesheet" type="text/css" href="assets/css/icofont.css">
    <!-- Themify icon-->
    <link rel="stylesheet" type="text/css" href="assets/css/themify.css">
    <!-- Flag icon-->
    <link rel="stylesheet" type="text/css" href="assets/css/flag-icon.css">
    <!-- Feather icon-->
    <link rel="stylesheet" type="text/css" href="assets/css/feather-icon.css">
    <!-- Plugins css start-->
    <!-- Plugins css Ends-->
    <!-- Bootstrap css-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.css">
    <!-- App css-->
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link id="color" rel="stylesheet" href="assets/css/light-1.css" media="screen">
    <!-- Responsive css-->
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
  </head>
  <body>
    <!-- Loader starts-->
    <div class="loader-wrapper">
      <div class="loader bg-white">
        <div class="whirly-loader"> </div>
      </div>
    </div>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <div class="container-fluid p-0">
        <!-- login page start-->
        <div class="authentication-main">
          <div class="row">
            <div class="col-md-12">
              <div class="auth-innerright">
                <div class="authentication-box">
                  <div class="text-center"><img src="assets/images/logo.jpg" width="150px" alt=""></div>
                  <div class="card mt-4">
                    <div class="card-body">
                      <div class="text-center">
                        <h4>INGRESAR</h4>
                        <h6>Ingrese su usuario y contraseña </h6>
                      </div>
                      <form class="theme-form" role="form" name="flogin" action="login.php" method="post">
                        <div class="form-group">
                          <label class="col-form-label pt-0">Usuario</label>
                          <input class="form-control" placeholder="usuario" name="user" type="text" autofocus>
                        </div>
                        <div class="form-group">
                          <label class="col-form-label">Contraseña</label>
                          <input class="form-control" placeholder="contraseña" name="pass" type="password" value="">
                        </div>
                        <div class="form-group form-row mt-3 mb-0">
                          <!-- <a href="#" onclick="document.flogin.submit()" class="btn btn-primary btn-block">Ingresar</a> -->
                          <input type="submit" class="btn btn-primary btn-block" value="Ingresar">
                        </div><?php
                        if ($error) { ?>
                          <div class="checkbox p-0"><?php
                            print("<b><font color='red'>Usuario o Contraseña incorrecto!</font></b>");  ?>
                          </div><?php
                        }
                        if (!empty($_GET['sesion'])) {
                          print("<div class='checkbox p-0'><b><font color='red'>Sesión vencida!</font></b></div>");
                        } ?>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- login page end-->
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
  </body>
</html>