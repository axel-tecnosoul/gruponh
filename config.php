<?php

/*$host = "localhost";
$username = "root";
$password = "";
$dbname = "gruponh";*/

/*$options = [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'];
try {
    $db = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8", $username, $password, $options);
} catch (PDOException $ex) {
    die("Failed to connect to the database: " . $ex->getMessage());
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');
session_start();
if (isset($_SESSION['LAST_REQUEST_TIME'])) {
  if (time() - $_SESSION['LAST_REQUEST_TIME'] > 900) { //15 minutos
    unset($_SESSION['user']);
    header("Location: login.php?sesion=1");
  }
}
$_SESSION['LAST_REQUEST_TIME'] = time();*/

//$_GET['debug']=1; // DEBUG
// Activa DEBUG temporalmente con ?debug=1 en la URL
$debug = isset($_GET['debug']) && $_GET['debug'] == 1;

// Tiempo de inactividad (s)
define('SESSION_TIMEOUT', $debug ? 20 : 900);
// Offset de aviso (s) antes de expirar
define('WARNING_OFFSET', $debug ? 10 : 60);
// Frecuencia de regeneración de ID (s)
define('REGEN_OFFSET', $debug ? 15 : 600);

// 1. Forzar UTF-8 antes de cualquier salida
header('Content-Type: text/html; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);


//$tiempo_inactividad = 2 * 60; // 15 minutos * 60 segundos por minuto

// 2. Ajustar vida útil de la sesión y cookie a 15 minutos (900 s)
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
session_set_cookie_params(SESSION_TIMEOUT);

// 3. Iniciar sesión
session_start();

// 4. Regenerar ID de sesión cada 10 minutos (600 s)
if (!isset($_SESSION['CREATED'])) {
  $_SESSION['CREATED'] = time();
} elseif (time() - $_SESSION['CREATED'] > REGEN_OFFSET) {
  session_regenerate_id(true);
  $_SESSION['CREATED'] = time();
}

// 5. Timeout por inactividad de 15 minutos (SESSION_TIMEOUT s)
if (isset($_SESSION['LAST_REQUEST_TIME']) && (time()-$_SESSION['LAST_REQUEST_TIME']>SESSION_TIMEOUT)) {
  $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

  session_unset();
  session_destroy();

  if ($isAjax) {
    header('Content-Type: application/json', true, 401);
    echo json_encode(['error' => 'session_expired']);
  } else {
    header('Location: login.php?sesion=1');
  }
  exit;
}

// Actualizar timestamp de la última petición
$_SESSION['LAST_REQUEST_TIME'] = time();

// 6. Comprobación de login
if (empty($_SESSION['user'])) {
  header('Location: login.php?sesion=1');
  exit;
}

// 7. Definir zona horaria
date_default_timezone_set("America/Argentina/Buenos_Aires");

// Preparar variable para el JS
$expiresAt = $_SESSION['LAST_REQUEST_TIME'] + SESSION_TIMEOUT;
