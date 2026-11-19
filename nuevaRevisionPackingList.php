<?php
require 'config.php';
require 'database.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'La sesión expiró. Vuelva a ingresar.']);
  exit;
}

$idRevisionActual = filter_input(INPUT_POST, 'id_packing_list_revision', FILTER_VALIDATE_INT);
$motivo = trim((string)($_POST['motivoRevision'] ?? ''));
$prod = !empty($_POST['prod']) ? (int)$_POST['prod'] : 0;

if (!$idRevisionActual || $motivo === '') {
  http_response_code(422);
  echo json_encode(['error' => 'Seleccione una revisión e indique el motivo de la nueva revisión.']);
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $pdo->beginTransaction();

  $sql = "SELECT plr.id, plr.id_packing_list, plr.id_proyecto, plr.nombre, plr.fecha, plr.id_usuario,
                 plr.id_estado_packing_list, plr.nro_revision, plr.anulado, plr.numero,
                 plr.id_cuenta_realizo, plr.id_cuenta_reviso, plr.id_cuenta_valido,
                 pl.ultimo_nro_revision
          FROM packing_lists_revisiones plr
          INNER JOIN packing_lists pl ON pl.id = plr.id_packing_list
          WHERE plr.id = ? AND plr.anulado = 0 AND pl.anulado = 0
          FOR UPDATE";
  $q = $pdo->prepare($sql);
  $q->execute([$idRevisionActual]);
  $revisionActual = $q->fetch(PDO::FETCH_ASSOC);

  if (empty($revisionActual)) {
    throw new DomainException('No se encontró la revisión seleccionada.');
  }

  if ((int)$revisionActual['nro_revision'] !== (int)$revisionActual['ultimo_nro_revision']) {
    throw new DomainException('Solo se puede generar una revisión desde la última revisión de la Packing List.');
  }

  if (!in_array((int)$revisionActual['id_estado_packing_list'], [3, 4], true)) {
    throw new DomainException('Solo se puede generar una revisión desde una Packing List Aprobada o En Proceso.');
  }

  $nuevoNroRevision = (int)$revisionActual['nro_revision'] + 1;
  $sql = "INSERT INTO packing_lists_revisiones
            (id_packing_list, id_proyecto, nombre, fecha, id_usuario, id_estado_packing_list,
             nro_revision, anulado, descripcion, id_cuenta_realizo, id_cuenta_reviso,
             id_cuenta_valido, numero)
          VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";
  $q = $pdo->prepare($sql);
  $q->execute([
    $revisionActual['id_packing_list'],
    $revisionActual['id_proyecto'],
    $revisionActual['nombre'],
    $revisionActual['fecha'],
    $revisionActual['id_usuario'],
    $nuevoNroRevision,
    $revisionActual['anulado'],
    $motivo,
    $revisionActual['id_cuenta_realizo'],
    $revisionActual['id_cuenta_reviso'],
    $revisionActual['id_cuenta_valido'],
    $revisionActual['numero']
  ]);
  $idNuevaRevision = (int)$pdo->lastInsertId();

  $q = $pdo->prepare("UPDATE packing_lists_revisiones SET id_estado_packing_list = 7 WHERE id = ? AND id_estado_packing_list IN (3, 4)");
  $q->execute([$idRevisionActual]);
  if ($q->rowCount() !== 1) {
    throw new DomainException('La revisión cambió de estado. Actualice el listado e intente nuevamente.');
  }

  $q = $pdo->prepare("UPDATE packing_lists SET ultimo_nro_revision = ? WHERE id = ? AND ultimo_nro_revision = ?");
  $q->execute([$nuevoNroRevision, $revisionActual['id_packing_list'], $revisionActual['nro_revision']]);
  if ($q->rowCount() !== 1) {
    throw new DomainException('Se generó otra revisión en paralelo. Actualice el listado e intente nuevamente.');
  }

  $qSecciones = $pdo->prepare("SELECT id, cantidad, observaciones FROM packing_lists_secciones WHERE id_packing_list_revision = ? ORDER BY id");
  $qSecciones->execute([$idRevisionActual]);
  $qInsertarSeccion = $pdo->prepare("INSERT INTO packing_lists_secciones (id_packing_list_revision, cantidad, observaciones) VALUES (?, ?, ?)");
  $qComponentes = $pdo->prepare("SELECT id_conjunto_lista_corte, id_concepto, cantidad, id_computo_detalle, observaciones, id_estado_componente_packing_list FROM packing_lists_componentes WHERE id_packing_list_seccion = ? ORDER BY id");
  $qInsertarComponente = $pdo->prepare("INSERT INTO packing_lists_componentes (id_packing_list_seccion, id_conjunto_lista_corte, id_concepto, cantidad, id_computo_detalle, observaciones, id_estado_componente_packing_list) VALUES (?, ?, ?, ?, ?, ?, ?)");

  while ($seccion = $qSecciones->fetch(PDO::FETCH_ASSOC)) {
    $qInsertarSeccion->execute([$idNuevaRevision, $seccion['cantidad'], $seccion['observaciones']]);
    $idNuevaSeccion = (int)$pdo->lastInsertId();

    $qComponentes->execute([$seccion['id']]);
    while ($componente = $qComponentes->fetch(PDO::FETCH_ASSOC)) {
      $qInsertarComponente->execute([
        $idNuevaSeccion,
        $componente['id_conjunto_lista_corte'],
        $componente['id_concepto'],
        $componente['cantidad'],
        $componente['id_computo_detalle'],
        $componente['observaciones'],
        $componente['id_estado_componente_packing_list']
      ]);
    }
  }

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link)
          VALUES (NOW(), ?, ?, 'Packing List', ?)";
  $q = $pdo->prepare($sql);
  $q->execute([
    $_SESSION['user']['id'],
    'Nueva revisión de Packing List #' . $revisionActual['numero'] . ' - Motivo: ' . $motivo,
    'nuevaPackingListSecciones.php?id_packing_list_revision=' . $idNuevaRevision . ($prod ? '&prod=' . $prod : '')
  ]);

  $pdo->commit();

  echo json_encode([
    'redirect' => 'nuevaPackingListSecciones.php?id_packing_list_revision=' . $idNuevaRevision . ($prod ? '&prod=' . $prod : '')
  ]);
} catch (DomainException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(409);
  echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('No se pudo generar la revisión del Packing List: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'No se pudo generar la revisión de la Packing List.']);
} finally {
  Database::disconnect();
}
