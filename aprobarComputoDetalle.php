<?php
//require("config.php");
/*if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}*/
/*require 'database.php';

$id = null;
if (!empty($_REQUEST['id_computo_detalle'])) {
  $id = $_REQUEST['id_computo_detalle'];
}

$idComputo=$_REQUEST['id_computo'];

if (null==$id) {
  header("Location: listarComputos.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE computos_detalle SET aprobado = 1 WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

$sql = "SELECT count(*) cant FROM computos_detalle WHERE aprobado = 0 and id_computo = ? ";
$q = $pdo->prepare($sql);
$q->execute([$idComputo]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if ($data['cant'] == 0) {
  $sql = "UPDATE computos SET id_estado = 3 WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$idComputo]);
}

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Aprobación de detalle de cómputo','Cómputos','verComputo.php?id=$id')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();*/
    
//header("Location: verComputo.php?id=".$idComputo);
//header("Location: listarComputos.php?id=".$idComputo);


require("config.php");
require 'database.php';

// Siempre devolvemos JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Leemos los parámetros
    $idDetalle = $_REQUEST['id_computo_detalle'] ?? null;
    $idComputo = $_REQUEST['id_computo']           ?? null;

    if (empty($idDetalle) || empty($idComputo)) {
        throw new Exception("Parámetros insuficientes.");
    }

    // Conexión
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1) Marcar detalle como aprobado
    $sql = "UPDATE computos_detalle SET aprobado = 1 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$idDetalle]);

    // 2) Verificar si quedan pendientes
    $sql = "SELECT COUNT(*) AS cant FROM computos_detalle WHERE aprobado = 0 AND id_computo = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$idComputo]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if ($data['cant'] == 0) {
        // Si no quedan pendientes, cambiar estado del cómputo
        $sql = "UPDATE computos SET id_estado = 3 WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$idComputo]);
    }

    // 3) Insertar log
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Aprobación de detalle de cómputo', 'Cómputos', 'verComputo.php?id={$idComputo}')";
    $q = $pdo->prepare($sql);
    $q->execute([ $_SESSION['user']['id'] ]);

    // Desconectar
    Database::disconnect();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Detalle aprobado correctamente.'
    ]);

} catch (Exception $e) {
    // En caso de error devolvemos error y mensaje
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
