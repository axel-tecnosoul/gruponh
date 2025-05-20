<?php
// keepalive.php
header('Content-Type: application/json; charset=utf-8');
require 'config.php';   // Carga SESSION_TIMEOUT 

// Si la sesión ya expiró o no hay user, devolvemos expired
if (empty($_SESSION['user'])) {
    echo json_encode(['status' => 'expired']);
    exit;
}

// Renovamos timestamp
$_SESSION['LAST_REQUEST_TIME'] = time();

// Volver a emitir la cookie de sesión para “resetear” su caducidad en el navegador:
if (PHP_SAPI !== 'cli') {
  // El path y domain deben coincidir con los que uses en session_set_cookie_params
  setcookie(
    session_name(),           // nombre de la cookie, p.ej. PHPSESSID
    session_id(),             // mismo ID
    time() + SESSION_TIMEOUT, // nueva expiración
    '/'                       // path (ajústalo si es otro)
  );
}

echo json_encode([
    'status'     => 'ok',
    'expires_at' => $_SESSION['LAST_REQUEST_TIME'] + SESSION_TIMEOUT
]);
