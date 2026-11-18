<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}
require 'database.php';
require_once 'despachos_lib.php';

$idDespacho = filter_input(INPUT_POST, 'id_despacho', FILTER_VALIDATE_INT);
if (!$idDespacho || $idDespacho < 1) {
  header('Location: listarDespachos.php');
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $pdo->beginTransaction();
  $qDespacho = $pdo->prepare(
    "SELECT id_proyecto, id_estado_despacho
     FROM despachos
     WHERE id = ?
     FOR UPDATE"
  );
  $qDespacho->execute([$idDespacho]);
  $despacho = $qDespacho->fetch(PDO::FETCH_ASSOC);
  if (!$despacho || (int)$despacho['id_estado_despacho'] !== 1) {
    throw new RuntimeException('El despacho no existe o ya no está pendiente.');
  }

  $idProyecto = (int)$despacho['id_proyecto'];
  $qProyecto = $pdo->prepare("SELECT id FROM proyectos WHERE id = ? FOR UPDATE");
  $qProyecto->execute([$idProyecto]);
  if (!$qProyecto->fetchColumn()) {
    throw new RuntimeException('No se encontró el proyecto del despacho.');
  }

  $qLineas = $pdo->prepare(
    "SELECT bd.id_detalle_bulto AS id_componente,
            bd.id_tipo_bulto AS tipo,
            SUM(bd.cantidad) AS cantidad
     FROM bultos b
     INNER JOIN bultos_detalle bd ON bd.id_bulto = b.id
     WHERE b.id_despacho = ?
     GROUP BY bd.id_detalle_bulto, bd.id_tipo_bulto"
  );
  $qLineas->execute([$idDespacho]);
  $lineasDespacho = $qLineas->fetchAll(PDO::FETCH_ASSOC);
  if (!$lineasDespacho) {
    throw new RuntimeException('Agrega al menos un componente antes de despachar.');
  }

  $componentes = despachoObtenerComponentesProyecto($pdo, $idProyecto, $idDespacho);
  $componentesPorId = [];
  foreach ($componentes as $componente) {
    $componentesPorId[(int)$componente['id']] = $componente;
  }

  $idsComponentes = [];
  foreach ($lineasDespacho as $linea) {
    $idComponente = (int)$linea['id_componente'];
    $componente = $componentesPorId[$idComponente] ?? null;
    if (!$componente || (int)$linea['tipo'] !== (int)$componente['id_tipo_bulto']) {
      throw new RuntimeException('Un componente del despacho ya no pertenece a un packing list vigente del proyecto.');
    }
    if ((float)$linea['cantidad'] <= 0 || (float)$linea['cantidad'] > (float)$componente['saldo']) {
      throw new RuntimeException(
        'No se puede despachar el componente ' . $componente['nombre_componente'] .
        ': la cantidad supera el saldo disponible. Vuelve a la carga y actualízala.'
      );
    }
    $idsComponentes[] = $idComponente;
  }

  $pdo->prepare(
    "UPDATE despachos
     SET id_estado_despacho = 2, fecha_hora_despachado = NOW()
     WHERE id = ? AND id_estado_despacho = 1"
  )->execute([$idDespacho]);

  $idsComponentes = array_values(array_unique($idsComponentes));
  $qActualizarDespachado = $pdo->prepare(
    "UPDATE packing_lists_componentes plc
     SET cantidad_despachada = (
       SELECT COALESCE(SUM(bd.cantidad), 0)
       FROM bultos_detalle bd
       INNER JOIN bultos b ON b.id = bd.id_bulto
       INNER JOIN despachos des ON des.id = b.id_despacho
       WHERE bd.id_detalle_bulto = plc.id
         AND bd.id_tipo_bulto IN (1, 2)
         AND des.id_estado_despacho = 2
     )
     WHERE plc.id = ?"
  );
  foreach ($idsComponentes as $idComponente) {
    $qActualizarDespachado->execute([$idComponente]);
  }

  $qLog = $pdo->prepare(
    "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
     VALUES (NOW(), ?, ?, 'Despachos', ?)"
  );
  $qLog->execute([
    $_SESSION['user']['id'],
    'Despacho #' . $idDespacho . ' marcado como despachado',
    'verDespacho.php?id=' . $idDespacho,
  ]);

  $pdo->commit();
  Database::disconnect();
  header('Location: listarDespachos.php');
  exit;
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  Database::disconnect();
  $_SESSION['error_despacho'] = $e->getMessage();
  header('Location: listarDespachos.php');
  exit;
}
