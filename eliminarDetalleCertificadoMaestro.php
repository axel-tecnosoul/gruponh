<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_POST['aperturado']);

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

$id_lote = null;
if (!empty($_GET['id_lote'])) {
  $id_lote = trim((string) $_GET['id_lote']);
}
if (!empty($_POST['aperturado'])) {
  $id_lote = trim((string) $_POST['aperturado']);
}

if (null == $id && $id_lote === null) {
  if ($isAjax) {
    echo json_encode(['success' => false, 'message' => 'No se especificó el lote a eliminar.']);
    exit;
  }
  header("Location: listarCertificadosMaestros.php");
  exit;
}

$column_names = [
  1 => "monto_acumulado_avances",
  2 => "monto_acumulado_anticipos",
  3 => "monto_acumulado_desacopios",
  4 => "monto_acumulado_descuentos",
  5 => "monto_acumulado_ajustes",
];

try {
  $detalle_accion = '';

  if ($id_lote !== null) {
    $sql = "SELECT id_certificado_maestro, COALESCE(SUM(subtotal),0) AS subtotal_lote, COUNT(*) AS cantidad_filas FROM certificados_maestros_detalles WHERE aperturado = ? GROUP BY id_certificado_maestro";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lote]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if (empty($data) || (int) ($data['cantidad_filas'] ?? 0) <= 0) {
      if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el lote a eliminar.']);
        exit;
      }
      Database::disconnect();
      header("Location: listarCertificadosMaestros.php");
      exit;
    }

    $id_certificado_maestro = (int) $data['id_certificado_maestro'];
    $subtotal_lote = (float) $data['subtotal_lote'];

    $pdo->beginTransaction();

    // Verificar avances antes de eliminar
    $sqlAv = "SELECT COUNT(*) FROM certificados_avances_detalle 
              WHERE id_certificado_maestro_detalle IN (
                SELECT id FROM certificados_maestros_detalles 
                WHERE id_certificado_maestro = ? AND aperturado = ?
              )";
    $qAv = $pdo->prepare($sqlAv);
    $qAv->execute([$id_certificado_maestro, $id_lote]);
    if ((int) $qAv->fetchColumn() > 0) {
      throw new Exception("No se puede eliminar este lote porque tiene avances registrados.");
    }

    $sql = "UPDATE certificados_maestros SET monto_acumulado_avances = monto_acumulado_avances - ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal_lote, $id_certificado_maestro]);

    $sql = "DELETE FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro = ? AND aperturado = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_certificado_maestro, $id_lote]);

    $sql = "DELETE FROM certificados_maestros_detalles WHERE aperturado = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lote]);

    $detalle_accion = "Eliminación de lote #$id_lote de Certificado Maestro";
  } else {
    $sql = "SELECT id_certificado_maestro,id_tipo_item_certificado,subtotal FROM certificados_maestros_detalles WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $id_certificado_maestro = $data['id_certificado_maestro'];
    $id_tipo_item_old=$data["id_tipo_item_certificado"];
    $subtotal_old=$data["subtotal"];

    $column_name_old = $column_names[$id_tipo_item_old];
    $sql = "UPDATE certificados_maestros SET $column_name_old = $column_name_old - ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal_old,$id_certificado_maestro]);

    $sql = "DELETE from certificados_maestros_detalles WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);

    $detalle_accion = "Eliminación de detalle ID #$id de Certificado Maestro";
  }

  if ($detalle_accion !== '') {
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,?,'Certificado Maestro','')";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id'], $detalle_accion]);
  }

  if ($pdo->inTransaction()) {
    $pdo->commit();
  }
  Database::disconnect();

  if ($isAjax) {
    echo json_encode(['success' => true, 'message' => 'Lote eliminado correctamente.']);
    exit;
  }
  header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=".$id_certificado_maestro);
} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  Database::disconnect();
  if ($isAjax) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
  }
  die("Error al eliminar: " . $e->getMessage());
}
