<?php
require("config.php");
require_once "permisos.php";

if (empty($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}

if (!tienePermiso(375)) {
  http_response_code(403);
  die("No tiene permisos para aprobar un Certificado Maestro.");
}
if (esOperacionesSinEconomico()) {
  http_response_code(403);
  die("Perfil Operaciones no puede aprobar Certificados Maestros.");
}

require 'database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
  header("Location: listarCertificadosMaestros.php");
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $pdo->beginTransaction();

  $sql = "UPDATE certificados_maestros
          SET aprobado_cliente = 1
          WHERE id = ? AND aprobado_cliente = 0";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);

  if ($q->rowCount() === 0) {
    throw new Exception("El Certificado Maestro no existe o ya está aprobado.");
  }

  $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
          VALUES (NOW(), ?, ?, 'Certificado Maestro', ?)";
  $q = $pdo->prepare($sql);
  $q->execute([
    $_SESSION['user']['id'],
    "Aprobación de Certificado Maestro #$id",
    "verCertificadosMaestro.php?id=$id",
  ]);

  $pdo->commit();
} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  Database::disconnect();
  die("Error al aprobar el Certificado Maestro: " . $e->getMessage());
}

Database::disconnect();
header("Location: listarCertificadosMaestros.php");
exit;
