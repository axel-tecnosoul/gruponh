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

  /*var_dump($_POST);
  var_dump($_GET);
  die();*/

  $column_names = [
    1 => "monto_acumulado_avances",
    2 => "monto_acumulado_anticipos",
    3 => "monto_acumulado_desacopios",
    4 => "monto_acumulado_descuentos",
    5 => "monto_acumulado_ajustes",
  ];

  $id_certificado_maestro_post = (int) ($_GET['id_certificado_maestro'] ?? 0);
  if ($id_certificado_maestro_post <= 0) {
    Database::disconnect();
    if (isset($_POST['btn_crear_otro_aperturado'])) {
      header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=" . $id_certificado_maestro_post);
    } else {
      header("Location: listarCertificadosMaestros.php");
    }
    exit;
  }

  // Fase 1: tipo de item fijo interno en Avance (id=1)
  $id_tipo_item = 1;

  try {

    // Detecta edicion legacy por id de detalle enviado, no por name del boton.
    $id_detalle_edicion = (int) ($_POST['id_certificado_maestro_detalle'] ?? 0);
    if ($id_detalle_edicion > 0) {

      $subtotal = (float) ($_POST['cantidad'] ?? 0) * (float) ($_POST['precio_unitario'] ?? 0);

      //obtenemos la informacion del detalle del certificado antes de editarlo
      $sql = "SELECT id_tipo_item_certificado,subtotal FROM certificados_maestros_detalles WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_detalle_edicion]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $id_tipo_item_old = $data["id_tipo_item_certificado"];
      $subtotal_old = $data["subtotal"];

      //obtenemos el nombre de la columna del tipo de detalle en la tabla certificado_maestro para restar el subtotal
      $column_name_old = $column_names[$id_tipo_item_old];
      //restamos el viejo subtotal en la columna segun el viejo tipo de detalle
      $sql = "UPDATE certificados_maestros SET $column_name_old = $column_name_old - ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$subtotal_old, $id_certificado_maestro_post]);

      //obtenemos el nombre de la columna en la tabla certificado_maestro para sumar el subtotal
      $column_name = $column_names[$id_tipo_item];
      //sumamos el nuevo subtotal en la columna segun el nuevo tipo de detalle
      $sql = "UPDATE certificados_maestros SET $column_name = $column_name + ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$subtotal, $id_certificado_maestro_post]);

      $sql = "UPDATE certificados_maestros_detalles SET id_proyecto=?, id_tipo_item_certificado=?, descripcion=?, cantidad=?, id_unidad_medida=?, precio_unitario=?, subtotal=? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST["id_proyecto"], $id_tipo_item, $_POST["descripcion"], $_POST["cantidad"], $_POST["id_unidad_medida"], $_POST["precio_unitario"], $subtotal, $id_detalle_edicion]);

      $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificacion de Detalle ID #" . $id_detalle_edicion . " de Certificado Maestro','Certificado Maestro','')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));

      Database::disconnect();
      header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=" . $id_certificado_maestro_post);
      exit;
    }

    // Fase 6: edicion por lote de aperturado.
    $id_aperturado_edicion = trim((string) ($_POST['id_aperturado_edicion'] ?? ''));

    // Fase 5: persistencia masiva con trazabilidad OCC + modo de generacion.
    $requiredColumns = ['id_occ_detalle', 'incidencia_porcentaje', 'monto_base_occ', 'aperturado', 'lote', 'modo_generacion'];
    foreach ($requiredColumns as $requiredColumn) {
      $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $requiredColumn);
      $sql = "SHOW COLUMNS FROM certificados_maestros_detalles LIKE '$safeColumn'";
      $q = $pdo->prepare($sql);
      $q->execute();
      if (!$q->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception("Falta la columna '" . $safeColumn . "' en certificados_maestros_detalles. Ejecutar consultas.sql antes de continuar.");
      }
    }

    $id_proyecto = (int) ($_POST['id_proyecto'] ?? 0);
    if ($id_proyecto <= 0) {
      throw new Exception("Debe seleccionar un proyecto valido.");
    }

    $modo_generacion = trim((string) ($_POST['modo_generacion'] ?? ''));
    if (!in_array($modo_generacion, ['agrupar', 'separar'], true)) {
      throw new Exception("Debe seleccionar un modo de generacion valido.");
    }

    $solo_editar_aperturado = (trim((string) ($_POST['solo_editar_aperturado'] ?? '')) === '1');

    $idsRaw = trim((string) ($_POST['ids_occ_detalle_seleccionados'] ?? ''));
    $ids_occ_detalle = [];
    if ($idsRaw !== '') {
      foreach (explode(',', $idsRaw) as $tmpId) {
        $tmp = (int) trim($tmpId);
        if ($tmp > 0) {
          $ids_occ_detalle[$tmp] = $tmp;
        }
      }
      $ids_occ_detalle = array_values($ids_occ_detalle);
    }
    $permite_sin_items_occ = ($id_aperturado_edicion !== '' && ($modo_generacion === 'agrupar' || $solo_editar_aperturado));
    if (empty($ids_occ_detalle) && !$permite_sin_items_occ) {
      throw new Exception("Debe seleccionar al menos un item de la OCC.");
    }

    // ========== VALIDACIÓN: evitar duplicidad de items OCC en distintos lotes ==========
    if (!empty($ids_occ_detalle) && !$solo_editar_aperturado) {
      // Si estamos editando, eliminamos automáticamente otros lotes que contengan esos ítems
      if ($id_aperturado_edicion !== '') {
        // Obtener los lotes conflictivos (excluyendo el lote actual)
        $sqlConflict = "SELECT DISTINCT aperturado 
                        FROM certificados_maestros_lotes_occ_detalle 
                        WHERE id_certificado_maestro = ? 
                          AND id_occ_detalle IN (" . implode(',', array_fill(0, count($ids_occ_detalle), '?')) . ")
                          AND aperturado != ?";
        $paramsConflict = array_merge([$id_certificado_maestro_post], $ids_occ_detalle, [$id_aperturado_edicion]);
        $qConflict = $pdo->prepare($sqlConflict);
        $qConflict->execute($paramsConflict);
        $lotesConflicto = $qConflict->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!empty($lotesConflicto)) {
          // Por cada lote conflictivo, restar su subtotal y eliminar sus registros
          $placeholdersLotes = implode(',', array_fill(0, count($lotesConflicto), '?'));
          // Obtener subtotales por lote
          $sqlSubtotales = "SELECT aperturado, COALESCE(SUM(subtotal),0) AS subtotal_lote 
                              FROM certificados_maestros_detalles 
                              WHERE id_certificado_maestro = ? 
                                AND aperturado IN ($placeholdersLotes)
                              GROUP BY aperturado";
          $qSub = $pdo->prepare($sqlSubtotales);
          $qSub->execute(array_merge([$id_certificado_maestro_post], $lotesConflicto));
          $subtotalesConflictos = $qSub->fetchAll(PDO::FETCH_KEY_PAIR);

          foreach ($lotesConflicto as $lote) {
            $sub = floatval($subtotalesConflictos[$lote] ?? 0);
            // Restar del acumulado
            $sqlUpdate = "UPDATE certificados_maestros SET monto_acumulado_avances = monto_acumulado_avances - ? WHERE id = ?";
            $qUpdate = $pdo->prepare($sqlUpdate);
            $qUpdate->execute([round($sub, 6), $id_certificado_maestro_post]);

            // Eliminar avances que referencien a este lote (cascada) antes de borrar los detalles
            $sqlDelAv = "DELETE FROM certificados_avances_detalle 
                         WHERE id_certificado_maestro_detalle IN (
                           SELECT id FROM certificados_maestros_detalles 
                           WHERE id_certificado_maestro = ? AND aperturado = ?
                         )";
            $qDelAv = $pdo->prepare($sqlDelAv);
            $qDelAv->execute([$id_certificado_maestro_post, $lote]);

            // Eliminar relaciones y detalles de ese lote
            $sqlDelRel = "DELETE FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro = ? AND aperturado = ?";
            $qDelRel = $pdo->prepare($sqlDelRel);
            $qDelRel->execute([$id_certificado_maestro_post, $lote]);

            $sqlDelDet = "DELETE FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ?";
            $qDelDet = $pdo->prepare($sqlDelDet);
            $qDelDet->execute([$id_certificado_maestro_post, $lote]);
          }
        }
      } else {
        // Modo no edición o modo separar: validación estricta (error si ya existen)
        $sqlCheck = "SELECT cmlod.id_occ_detalle, cmlod.aperturado 
                     FROM certificados_maestros_lotes_occ_detalle cmlod 
                     WHERE cmlod.id_certificado_maestro = ? 
                     AND cmlod.id_occ_detalle IN (" . implode(',', array_fill(0, count($ids_occ_detalle), '?')) . ")
                     AND cmlod.aperturado IS NOT NULL 
                     AND cmlod.aperturado <> ''";
        $paramsCheck = array_merge([$id_certificado_maestro_post], $ids_occ_detalle);
        if ($id_aperturado_edicion !== '') {
          $sqlCheck .= " AND cmlod.aperturado != ?";
          $paramsCheck[] = $id_aperturado_edicion;
        }
        $qCheck = $pdo->prepare($sqlCheck);
        $qCheck->execute($paramsCheck);
        $conflicting = $qCheck->fetchAll(PDO::FETCH_ASSOC);
        $conflictingItems = [];
        if (!empty($conflicting)) {
          foreach ($conflicting as $row) {
            $conflictingItems[(int) $row['id_occ_detalle']] = $row['aperturado'];
          }
        }
      }
    }

    $arrDescripcion = $_POST['aperturado_descripcion'] ?? [];
    $arrUnidad = $_POST['aperturado_id_unidad_medida'] ?? [];
    $arrCantidad = $_POST['aperturado_cantidad'] ?? [];
    $arrIncidencia = $_POST['aperturado_incidencia'] ?? [];

    $rowCount = count($arrDescripcion);
    if ($rowCount === 0 || $rowCount !== count($arrUnidad) || $rowCount !== count($arrCantidad) || $rowCount !== count($arrIncidencia)) {
      throw new Exception("La grilla de aperturado no es valida.");
    }

    $aperturadoRows = [];
    $incidenciaTotal = 0.0;
    for ($i = 0; $i < $rowCount; $i++) {
      $descripcion = trim((string) ($arrDescripcion[$i] ?? ''));
      $id_unidad_medida = (int) ($arrUnidad[$i] ?? 0);
      $cantidad = (float) ($arrCantidad[$i] ?? 0);
      $incidencia = (float) ($arrIncidencia[$i] ?? 0);

      if ($descripcion === '' || $id_unidad_medida <= 0 || $cantidad <= 0 || $incidencia < 0 || $incidencia > 100) {
        throw new Exception("Complete correctamente descripcion, unidad, cantidad e incidencia en todas las filas del aperturado.");
      }

      $aperturadoRows[] = [
        'descripcion' => $descripcion,
        'id_unidad_medida' => $id_unidad_medida,
        'cantidad' => $cantidad,
        'incidencia' => $incidencia,
      ];
      $incidenciaTotal += $incidencia;
    }

    if (abs($incidenciaTotal - 100) >= 0.001) {
      throw new Exception("La incidencia total del aperturado debe sumar 100%.");
    }

    $pdo->beginTransaction();

    // Resolver conflictos: desvincular items OCC de sus aperturados anteriores
    if (!empty($conflictingItems) && $id_aperturado_edicion === '') {
      $occPorAperturado = [];
      foreach ($conflictingItems as $occId => $aperturado) {
        $occPorAperturado[$aperturado][] = $occId;
      }
      foreach ($occPorAperturado as $aperturado => $occIds) {
        $placeholdersRm = implode(',', array_fill(0, count($occIds), '?'));
        $sqlRm = "DELETE FROM certificados_maestros_lotes_occ_detalle 
                  WHERE id_certificado_maestro = ? AND aperturado = ? AND id_occ_detalle IN ($placeholdersRm)";
        $qRm = $pdo->prepare($sqlRm);
        $qRm->execute(array_merge([$id_certificado_maestro_post, $aperturado], $occIds));

        $sqlCnt = "SELECT COUNT(*) FROM certificados_maestros_lotes_occ_detalle 
                   WHERE id_certificado_maestro = ? AND aperturado = ?";
        $qCnt = $pdo->prepare($sqlCnt);
        $qCnt->execute([$id_certificado_maestro_post, $aperturado]);
        $restanItems = (int) $qCnt->fetchColumn() > 0;

        if (!$restanItems) {
          $sqlAv = "SELECT COUNT(*) FROM certificados_avances_detalle 
                    WHERE id_certificado_maestro_detalle IN (
                      SELECT id FROM certificados_maestros_detalles 
                      WHERE id_certificado_maestro = ? AND aperturado = ?
                    )";
          $qAv = $pdo->prepare($sqlAv);
          $qAv->execute([$id_certificado_maestro_post, $aperturado]);
          if ((int) $qAv->fetchColumn() > 0) {
            throw new Exception("No se puede desvincular items del aperturado '$aperturado' porque tiene avances registrados.");
          }

          $sqlSub = "SELECT COALESCE(SUM(subtotal),0) FROM certificados_maestros_detalles 
                     WHERE id_certificado_maestro = ? AND aperturado = ?";
          $qSub = $pdo->prepare($sqlSub);
          $qSub->execute([$id_certificado_maestro_post, $aperturado]);
          $subtotalRestar = (float) $qSub->fetchColumn();

          if ($subtotalRestar > 0) {
            $sqlUpd = "UPDATE certificados_maestros SET monto_acumulado_avances = monto_acumulado_avances - ? WHERE id = ?";
            $qUpd = $pdo->prepare($sqlUpd);
            $qUpd->execute([$subtotalRestar, $id_certificado_maestro_post]);
          }

          $sqlDelDet = "DELETE FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ?";
          $qDelDet = $pdo->prepare($sqlDelDet);
          $qDelDet->execute([$id_certificado_maestro_post, $aperturado]);
        }
      }
    }

    $subtotal_lote_anterior = 0.0;
    $monto_base_lote_anterior = 0.0;
    $occ_ids_lote_anterior = [];
    $lote_existente = '';
    $oldDetalleIds = [];
    if ($id_aperturado_edicion !== '') {
      $sql = "SELECT COALESCE(SUM(subtotal),0) AS subtotal_lote, COALESCE(MAX(monto_base_occ),0) AS monto_base_lote, COUNT(*) AS cantidad_filas, MAX(lote) AS lote_existente FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_certificado_maestro_post, $id_aperturado_edicion]);
      $loteData = $q->fetch(PDO::FETCH_ASSOC);

      if (empty($loteData) || (int) ($loteData['cantidad_filas'] ?? 0) <= 0) {
        throw new Exception("No se encontro el lote a editar.");
      }

      $subtotal_lote_anterior = (float) ($loteData['subtotal_lote'] ?? 0);
      $monto_base_lote_anterior = (float) ($loteData['monto_base_lote'] ?? 0);
      $lote_existente = (string) ($loteData['lote_existente'] ?? '');

      $sql = "SELECT id FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ? ORDER BY id";
      $q = $pdo->prepare($sql);
      $q->execute([$id_certificado_maestro_post, $id_aperturado_edicion]);
      $oldDetalleIds = $q->fetchAll(PDO::FETCH_COLUMN, 0);
      $oldDetalleIds = array_map('intval', $oldDetalleIds);

      $sql = "SELECT id_occ_detalle FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro = ? AND aperturado = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_certificado_maestro_post, $id_aperturado_edicion]);
      $occ_rows_lote = $q->fetchAll(PDO::FETCH_COLUMN, 0);
      foreach ($occ_rows_lote as $occ_id_row) {
        $tmp_occ = (int) $occ_id_row;
        if ($tmp_occ > 0) {
          $occ_ids_lote_anterior[$tmp_occ] = $tmp_occ;
        }
      }

      $sql = "UPDATE certificados_maestros SET monto_acumulado_avances = monto_acumulado_avances - ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([round($subtotal_lote_anterior, 6), $id_certificado_maestro_post]);

      $sql = "DELETE FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro = ? AND aperturado = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_certificado_maestro_post, $id_aperturado_edicion]);
    }

    if ($solo_editar_aperturado && !empty($occ_ids_lote_anterior)) {
      $ids_occ_detalle = array_values($occ_ids_lote_anterior);
    }

    // Sincronizar otros aperturados del mismo lote: remover items OCC no seleccionados
    if ($lote_existente !== '' && !$solo_editar_aperturado && !empty($ids_occ_detalle)) {
      $sqlOtros = "SELECT DISTINCT aperturado FROM certificados_maestros_lotes_occ_detalle 
                   WHERE id_certificado_maestro = ? AND lote = ? AND aperturado != ?";
      $qOtros = $pdo->prepare($sqlOtros);
      $qOtros->execute([$id_certificado_maestro_post, $lote_existente, $id_aperturado_edicion]);
      $otrosAperturados = $qOtros->fetchAll(PDO::FETCH_COLUMN, 0);

      foreach ($otrosAperturados as $otroAp) {
        $sqlItems = "SELECT id_occ_detalle FROM certificados_maestros_lotes_occ_detalle 
                     WHERE id_certificado_maestro = ? AND aperturado = ?";
        $qItems = $pdo->prepare($sqlItems);
        $qItems->execute([$id_certificado_maestro_post, $otroAp]);
        $itemsOtro = array_map('intval', $qItems->fetchAll(PDO::FETCH_COLUMN, 0));
        if (empty($itemsOtro)) continue;

        $itemsRemover = array_diff($itemsOtro, $ids_occ_detalle);
        if (empty($itemsRemover)) continue;

        $placeholdersRm = implode(',', array_fill(0, count($itemsRemover), '?'));
        $sqlRm = "DELETE FROM certificados_maestros_lotes_occ_detalle 
                  WHERE id_certificado_maestro = ? AND aperturado = ? 
                  AND id_occ_detalle IN ($placeholdersRm)";
        $qRm = $pdo->prepare($sqlRm);
        $qRm->execute(array_merge([$id_certificado_maestro_post, $otroAp], $itemsRemover));

        $sqlCnt = "SELECT COUNT(*) FROM certificados_maestros_lotes_occ_detalle 
                   WHERE id_certificado_maestro = ? AND aperturado = ?";
        $qCnt = $pdo->prepare($sqlCnt);
        $qCnt->execute([$id_certificado_maestro_post, $otroAp]);
        $restanItems = (int) $qCnt->fetchColumn() > 0;

        if (!$restanItems) {
          $sqlAvCheck = "SELECT COUNT(*) FROM certificados_avances_detalle 
                         WHERE id_certificado_maestro_detalle IN (
                           SELECT id FROM certificados_maestros_detalles 
                           WHERE id_certificado_maestro = ? AND aperturado = ?
                         )";
          $qAvCheck = $pdo->prepare($sqlAvCheck);
          $qAvCheck->execute([$id_certificado_maestro_post, $otroAp]);
          if ((int) $qAvCheck->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el aperturado '$otroAp' porque tiene avances registrados.");
          }

          $sqlSubOtro = "SELECT COALESCE(SUM(subtotal),0) FROM certificados_maestros_detalles 
                         WHERE id_certificado_maestro = ? AND aperturado = ?";
          $qSubOtro = $pdo->prepare($sqlSubOtro);
          $qSubOtro->execute([$id_certificado_maestro_post, $otroAp]);
          $subtotalOtro = (float) $qSubOtro->fetchColumn();

          if ($subtotalOtro > 0) {
            $sqlUpd = "UPDATE certificados_maestros SET monto_acumulado_avances = monto_acumulado_avances - ? WHERE id = ?";
            $qUpd = $pdo->prepare($sqlUpd);
            $qUpd->execute([$subtotalOtro, $id_certificado_maestro_post]);
          }

          $sqlDelDet = "DELETE FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ?";
          $qDelDet = $pdo->prepare($sqlDelDet);
          $qDelDet->execute([$id_certificado_maestro_post, $otroAp]);
        }
      }
    }

    $sql = "SELECT id_occ FROM certificados_maestros WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_certificado_maestro_post]);
    $cmData = $q->fetch(PDO::FETCH_ASSOC);
    if (empty($cmData) || (int) $cmData['id_occ'] <= 0) {
      throw new Exception("El certificado maestro no tiene OCC asociada.");
    }
    $id_occ_certificado = (int) $cmData['id_occ'];

    $occRows = [];
    if (!empty($ids_occ_detalle)) {
      $placeholders = implode(',', array_fill(0, count($ids_occ_detalle), '?'));
      $sql = "SELECT id, subtotal FROM occ_detalles WHERE id_occ = ? AND id IN ($placeholders)";
      $params = array_merge([$id_occ_certificado], $ids_occ_detalle);
      $q = $pdo->prepare($sql);
      $q->execute($params);
      $occRows = $q->fetchAll(PDO::FETCH_ASSOC);

      if (count($occRows) !== count($ids_occ_detalle)) {
        throw new Exception("Uno o mas items seleccionados no pertenecen a la OCC del certificado.");
      }
    }

    $occSubtotales = [];
    $baseTotalOccSeleccionada = 0.0;
    foreach ($occRows as $occRow) {
      $occId = (int) $occRow['id'];
      $occSubtotal = (float) $occRow['subtotal'];
      $occSubtotales[$occId] = $occSubtotal;
      $baseTotalOccSeleccionada += $occSubtotal;
    }

    if ($baseTotalOccSeleccionada <= 0 && $permite_sin_items_occ && $monto_base_lote_anterior > 0) {
      $baseTotalOccSeleccionada = $monto_base_lote_anterior;
    }

    // Generar o reutilizar lote
    $loteGenerado = $lote_existente;
    if ($loteGenerado === '' && $id_aperturado_edicion !== '') {
      $loteGenerado = $id_aperturado_edicion;
    }
    if ($solo_editar_aperturado && $modo_generacion === 'separar') {
      $loteGenerado = '';
    }
    if ($loteGenerado === '') {
      $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(lote, '-LOTE-', -1) AS UNSIGNED)), 0) + 1 AS next_lote FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND lote LIKE 'CM%-LOTE-%'";
      $q = $pdo->prepare($sql);
      $q->execute([$id_certificado_maestro_post]);
      $nextLote = (int) $q->fetch(PDO::FETCH_ASSOC)['next_lote'];
      $loteGenerado = 'CM' . $id_certificado_maestro_post . '-LOTE-' . $nextLote;
    }

    $aperturadoIdentificador = $id_aperturado_edicion !== '' ? $id_aperturado_edicion : ('CM' . $id_certificado_maestro_post . '-' . date('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 8));
    $rowsInserted = 0;
    $montoTotalInsertado = 0.0;

    // Auto-eliminar lote si se deseleccionaron todos los items OCC
    if ($id_aperturado_edicion !== '' && empty($ids_occ_detalle) && !$solo_editar_aperturado) {
      $sqlAvDel = "SELECT COUNT(*) FROM certificados_avances_detalle 
                   WHERE id_certificado_maestro_detalle IN (
                     SELECT id FROM certificados_maestros_detalles 
                     WHERE id_certificado_maestro = ? AND aperturado = ?
                   )";
      $qAvDel = $pdo->prepare($sqlAvDel);
      $qAvDel->execute([$id_certificado_maestro_post, $id_aperturado_edicion]);
      if ((int) $qAvDel->fetchColumn() > 0) {
        throw new Exception("No se puede eliminar este lote porque tiene avances registrados.");
      }

      $sql = "DELETE FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_certificado_maestro_post, $id_aperturado_edicion]);

      $detalleLog = "Eliminación de lote aperturado $id_aperturado_edicion de Certificado Maestro (sin items OCC).";
      $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,?,'Certificado Maestro','')";
      $q = $pdo->prepare($sql);
      $q->execute([$_SESSION['user']['id'], $detalleLog]);
      $pdo->commit();
      Database::disconnect();
      header("Location: listarCertificadosMaestros.php");
      exit;
    }

    $sqlInsert = "INSERT INTO certificados_maestros_detalles (id_certificado_maestro, id_occ_detalle, id_proyecto, id_tipo_item_certificado, descripcion, cantidad, id_unidad_medida, precio_unitario, subtotal, incidencia_porcentaje, monto_base_occ, aperturado, lote, modo_generacion) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $qInsert = $pdo->prepare($sqlInsert);

    $sqlUpdate = "UPDATE certificados_maestros_detalles SET id_certificado_maestro=?, id_occ_detalle=?, id_proyecto=?, id_tipo_item_certificado=?, descripcion=?, cantidad=?, id_unidad_medida=?, precio_unitario=?, subtotal=?, incidencia_porcentaje=?, monto_base_occ=?, aperturado=?, lote=?, modo_generacion=? WHERE id=?";
    $qUpdate = $pdo->prepare($sqlUpdate);

    $sqlInsertRel = "INSERT IGNORE INTO certificados_maestros_lotes_occ_detalle (id_certificado_maestro, aperturado, lote, id_occ_detalle, modo_generacion) VALUES (?,?,?,?,?)";
    $qInsertRel = $pdo->prepare($sqlInsertRel);

    $rowIdx = 0;
    if ($modo_generacion === 'agrupar') {
      $aperturadoFinal = $id_aperturado_edicion !== '' ? $aperturadoIdentificador : ($aperturadoIdentificador . '-AGR');
      foreach ($aperturadoRows as $apRow) {
        $subtotalFila = round($baseTotalOccSeleccionada * ($apRow['incidencia'] / 100), 6);
        $precioUnitarioFila = $apRow['cantidad'] > 0 ? round($subtotalFila / $apRow['cantidad'], 6) : 0;

        $commonValues = [
          $id_certificado_maestro_post,
          null,
          $id_proyecto,
          $id_tipo_item,
          $apRow['descripcion'],
          $apRow['cantidad'],
          $apRow['id_unidad_medida'],
          $precioUnitarioFila,
          $subtotalFila,
          $apRow['incidencia'],
          $baseTotalOccSeleccionada,
          $aperturadoFinal,
          $loteGenerado,
          $modo_generacion,
        ];

        if ($id_aperturado_edicion !== '' && $rowIdx < count($oldDetalleIds)) {
          $qUpdate->execute(array_merge($commonValues, [$oldDetalleIds[$rowIdx]]));
        } else {
          $qInsert->execute($commonValues);
        }

        $rowsInserted++;
        $montoTotalInsertado += $subtotalFila;
        $rowIdx++;
      }

      foreach ($ids_occ_detalle as $occIdRel) {
        $qInsertRel->execute([
          $id_certificado_maestro_post,
          $aperturadoFinal,
          $loteGenerado,
          (int) $occIdRel,
          $modo_generacion,
        ]);
      }
    } else {
      foreach ($ids_occ_detalle as $occId) {
        $baseIndividual = (float) ($occSubtotales[$occId] ?? 0);
        $aperturadoBaseSep = ($id_aperturado_edicion !== '' && preg_match('/\-SEP-\d+$/', $id_aperturado_edicion))
          ? preg_replace('/\-SEP-\d+$/', '', $id_aperturado_edicion)
          : $aperturadoIdentificador;
        $aperturadoFinal = $id_aperturado_edicion !== ''
          ? $aperturadoBaseSep . '-SEP-' . $occId
          : $aperturadoIdentificador . '-SEP-' . $occId;
        foreach ($aperturadoRows as $apRow) {
          $subtotalFila = round($baseIndividual * ($apRow['incidencia'] / 100), 6);
          $precioUnitarioFila = $apRow['cantidad'] > 0 ? round($subtotalFila / $apRow['cantidad'], 6) : 0;

          $commonValues = [
            $id_certificado_maestro_post,
            $occId,
            $id_proyecto,
            $id_tipo_item,
            $apRow['descripcion'],
            $apRow['cantidad'],
            $apRow['id_unidad_medida'],
            $precioUnitarioFila,
            $subtotalFila,
            $apRow['incidencia'],
            $baseIndividual,
            $aperturadoFinal,
            $loteGenerado,
            $modo_generacion,
          ];

          if ($id_aperturado_edicion !== '' && $rowIdx < count($oldDetalleIds)) {
            $qUpdate->execute(array_merge($commonValues, [$oldDetalleIds[$rowIdx]]));
          } else {
            $qInsert->execute($commonValues);
          }

          $rowsInserted++;
          $montoTotalInsertado += $subtotalFila;
          $rowIdx++;
        }

        $qInsertRel->execute([
          $id_certificado_maestro_post,
          $aperturadoFinal,
          $loteGenerado,
          (int) $occId,
          $modo_generacion,
        ]);
      }
    }

    // Eliminar filas viejas que ya no tienen correspondencia en las nuevas
    // (solo si no tienen avances registrados)
    if ($id_aperturado_edicion !== '' && $rowIdx < count($oldDetalleIds)) {
      $excessIds = array_slice($oldDetalleIds, $rowIdx);
      foreach ($excessIds as $excessId) {
        $checkAv = $pdo->prepare("SELECT COUNT(*) FROM certificados_avances_detalle WHERE id_certificado_maestro_detalle = ?");
        $checkAv->execute([$excessId]);
        if ((int) $checkAv->fetchColumn() === 0) {
          $pdo->prepare("DELETE FROM certificados_maestros_detalles WHERE id = ?")->execute([$excessId]);
        }
      }
    }

    $sql = "UPDATE certificados_maestros SET monto_acumulado_avances = monto_acumulado_avances + ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([round($montoTotalInsertado, 6), $id_certificado_maestro_post]);

    $detalleLog = $id_aperturado_edicion !== ''
      ? ("Edicion de lote de detalle(s) de Certificado Maestro. Aperturado: $id_aperturado_edicion. Lote: $loteGenerado. Modo: $modo_generacion. Items OCC: " . count($ids_occ_detalle) . ". Filas creadas: $rowsInserted")
      : ("Nuevo lote de detalle(s) de Certificado Maestro. Modo: $modo_generacion. Items OCC: " . count($ids_occ_detalle) . ". Filas creadas: $rowsInserted");

    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,?,'Certificado Maestro','')";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id'], $detalleLog]);

    $pdo->commit();
    Database::disconnect();

    if ($id_aperturado_edicion !== '' || isset($_POST['btn_crear_otro_aperturado'])) {
      header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=" . $id_certificado_maestro_post);
    } else {
      header("Location: listarCertificadosMaestros.php");
    }
    exit;
  } catch (Exception $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    Database::disconnect();
    die("Error al guardar detalle de certificado: " . $e->getMessage());
  }
}

$id_certificado_maestro = (int) $_GET['id_certificado_maestro'];
$id_occ = 0;
$id_cliente_occ = 0;
$numero_occ = '';
$moneda_occ = '';
$occ_detalles = [];
$unidades_medida = [];
$certificado_header = [];
$lotes_editables = [];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cm.id, DATE_FORMAT(cm.fecha_emision, '%d/%m/%Y') AS fecha_emision_cm, cm.porcentaje_anticipo, cm.id_occ, occ.id_cuenta_cliente AS id_cliente_occ, occ.numero, DATE_FORMAT(occ.fecha_emision, '%d/%m/%Y') AS fecha_emision_occ, occ.monto, occ.monto_total_certificados, occ.monto_total_facturados, cu.nombre AS cliente_occ, m.moneda FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ = occ.id INNER JOIN cuentas cu ON cu.id = occ.id_cuenta_cliente INNER JOIN monedas m ON occ.id_moneda = m.id WHERE cm.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_maestro]);
$data_occ = $q->fetch(PDO::FETCH_ASSOC);
if (!empty($data_occ)) {
  $certificado_header = $data_occ;
  $id_occ = (int) $data_occ['id_occ'];
  $id_cliente_occ = (int) $data_occ['id_cliente_occ'];
  $numero_occ = $data_occ['numero'];
  $moneda_occ = $data_occ['moneda'];
}

if ($id_occ > 0) {
  $sql = "SELECT id, descripcion, cantidad, precio_unitario, descuento, subtotal FROM occ_detalles WHERE id_occ = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_occ]);
  $occ_detalles = $q->fetchAll(PDO::FETCH_ASSOC);
}

$sql = "SELECT id, unidad_medida FROM unidades_medida ORDER BY unidad_medida";
$q = $pdo->prepare($sql);
$q->execute();
$unidades_medida = $q->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT aperturado, lote, modo_generacion, id_proyecto, COALESCE(MAX(monto_base_occ),0) AS monto_base_occ, COALESCE(SUM(subtotal),0) AS subtotal_lote, COUNT(*) AS cantidad_filas FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado IS NOT NULL AND aperturado <> '' AND modo_generacion IN ('agrupar','separar') GROUP BY aperturado, lote, modo_generacion, id_proyecto ORDER BY MAX(id) DESC";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_maestro]);
$lotes_base = $q->fetchAll(PDO::FETCH_ASSOC);

foreach ($lotes_base as $lote_row) {
  $lote_id = $lote_row['aperturado'];
  $lote_nro = $lote_row['lote'] ?? '';

  $sql = "SELECT id_occ_detalle FROM certificados_maestros_lotes_occ_detalle WHERE id_certificado_maestro = ? AND aperturado = ? ORDER BY id_occ_detalle";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_maestro, $lote_id]);
  $occ_rows_rel = $q->fetchAll(PDO::FETCH_COLUMN, 0);

  $sql = "SELECT id_occ_detalle, descripcion, id_unidad_medida, cantidad, incidencia_porcentaje FROM certificados_maestros_detalles WHERE id_certificado_maestro = ? AND aperturado = ? ORDER BY id";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_maestro, $lote_id]);
  $rows_lote = $q->fetchAll(PDO::FETCH_ASSOC);

  $occ_ids = [];
  foreach ($occ_rows_rel as $occ_rel) {
    $tmp_rel = (int) $occ_rel;
    if ($tmp_rel > 0) {
      $occ_ids[$tmp_rel] = $tmp_rel;
    }
  }

  $ap_rows = [];
  foreach ($rows_lote as $r) {
    if (empty($occ_ids) && !empty($r['id_occ_detalle'])) {
      $occ_ids[(int) $r['id_occ_detalle']] = (int) $r['id_occ_detalle'];
    }
    $ap_rows[] = [
      'descripcion' => (string) ($r['descripcion'] ?? ''),
      'id_unidad_medida' => (int) ($r['id_unidad_medida'] ?? 0),
      'cantidad' => (float) ($r['cantidad'] ?? 0),
      'incidencia' => (float) ($r['incidencia_porcentaje'] ?? 0),
    ];
  }

  $lotes_editables[] = [
    'aperturado' => $lote_id,
    'lote' => $lote_nro,
    'modo_generacion' => (string) $lote_row['modo_generacion'],
    'id_proyecto' => (int) ($lote_row['id_proyecto'] ?? 0),
    'monto_base_occ' => (float) ($lote_row['monto_base_occ'] ?? 0),
    'subtotal_lote' => (float) ($lote_row['subtotal_lote'] ?? 0),
    'cantidad_filas' => (int) ($lote_row['cantidad_filas'] ?? 0),
    'occ_ids' => array_values($occ_ids),
    'aplica_global' => empty($occ_ids),
    'aperturado_rows' => $ap_rows,
  ];
}

// Agrupar occ_ids por lote para editar por lote completo (marca todos los items del lote)
$loteOccGroup = [];
foreach ($lotes_editables as $le) {
  $loteKey = $le['lote'];
  if (!isset($loteOccGroup[$loteKey])) {
    $loteOccGroup[$loteKey] = [];
  }
  foreach ($le['occ_ids'] as $oid) {
    $loteOccGroup[$loteKey][$oid] = $oid;
  }
}
foreach ($lotes_editables as &$le) {
  $le['todos_occ_ids'] = array_values($loteOccGroup[$le['lote']] ?? []);
}
unset($le);

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include('head_forms.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  <style>
    #tabla_occ_detalles tbody tr.occ-grouped-member td {
      background-color: #eef8ff;
    }

    #tabla_occ_detalles tbody tr.occ-grouped-start td,
    #tabla_occ_detalles tbody tr.occ-grouped-middle td,
    #tabla_occ_detalles tbody tr.occ-grouped-end td,
    #tabla_occ_detalles tbody tr.occ-grouped-single td {
      border-left: 3px solid #2b8dbf;
    }

    #tabla_occ_detalles tbody tr.occ-grouped-start td {
      border-top: 2px solid #2b8dbf;
    }

    #tabla_occ_detalles tbody tr.occ-grouped-end td {
      border-bottom: 2px solid #2b8dbf;
    }

    #tabla_occ_detalles tbody tr.occ-grouped-single td {
      border-top: 2px solid #2b8dbf;
      border-bottom: 2px solid #2b8dbf;
    }

    .occ-group-aperturado-wrap {
      border-left: 4px solid #2b8dbf;
      background: #f7fcff;
      border-radius: 4px;
      padding: 8px 10px;
    }
  </style>
</head>

<body>
  <!-- Loader ends-->
  <!-- page-wrapper Start-->
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <!-- Page Header Start-->
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <!-- Page Sidebar Start-->
      <!-- Right sidebar Ends-->
      <div class="page-body"><?php
                              $ubicacion = "Nuevo Conjunto";
                              include_once("head_page.php") ?>
        <!-- Container-fluid starts-->
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5>Detalle del Certificado Maestro #<?= $id_certificado_maestro ?></h5>
                </div>
                <form class="form theme-form" role="form" method="post" action="nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=<?= $id_certificado_maestro ?>">
                  <input type="hidden" name="ids_occ_detalle_seleccionados" id="ids_occ_detalle_seleccionados" value="">
                  <input type="hidden" name="cant_items_occ_seleccionados" id="cant_items_occ_seleccionados_input" value="0">
                  <input type="hidden" name="base_total_occ_seleccionada" id="base_total_occ_seleccionada_input" value="0">
                  <input type="hidden" name="id_aperturado_edicion" id="id_aperturado_edicion" value="">
                  <input type="hidden" name="solo_editar_aperturado" id="solo_editar_aperturado" value="">
                  <div class="card-body">
                    <div class="row">
                      <div class="col">
                        <div class="alert alert-warning mb-3" id="estado_edicion_lote" style="display:none;"></div>
                        <h6 class="mb-3 font-weight-bold">Datos del Certificado</h6>
                        <div class="row">
                          <div class="col-lg-6">
                            <div class="form-group row mb-2">
                              <label class="col-sm-5 col-form-label font-weight-bold">Orden de Compra Cliente</label>
                              <div class="col-sm-7"><span class="form-control-plaintext"><?= htmlspecialchars($numero_occ, ENT_QUOTES) . htmlspecialchars(" - " . $certificado_header['cliente_occ'] ?? '', ENT_QUOTES) ?></span></div>
                            </div>
                            <div class="form-group row mb-2">
                              <label class="col-sm-5 col-form-label font-weight-bold">Monto OCC</label>
                              <div class="col-sm-7"><span class="form-control-plaintext"><?= $moneda_occ ?> <?= number_format((float)($certificado_header['monto'] ?? 0), 2, ',', '.') ?></span></div>
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="form-group row mb-0">
                              <label class="col-sm-5 col-form-label font-weight-bold">Fecha emisión certificado</label>
                              <div class="col-sm-7"><span class="form-control-plaintext"><?= htmlspecialchars($certificado_header['fecha_emision_cm'] ?? '', ENT_QUOTES) ?></span></div>
                            </div>
                            <div class="form-group row mb-0">
                              <label class="col-sm-5 col-form-label font-weight-bold">% Anticipo</label>
                              <div class="col-sm-7"><span class="form-control-plaintext"><?= htmlspecialchars($certificado_header['porcentaje_anticipo'] ?? '0', ENT_QUOTES) ?></span></div>
                            </div>
                          </div>
                        </div>
                        <hr class="mt-4 mb-4">
                        <h6 class="mb-3 font-weight-bold">Items OCC (selección múltiple)</h6>
                        <div class="form-group row">
                          <div class="col-sm-12">
                            <div class="table-responsive">
                              <table class="table table-sm table-bordered display" id="tabla_occ_detalles" style="width:100%">
                                <thead>
                                  <tr>
                                    <th style="width:40px;"><input type="checkbox" id="check_all_occ_items" title="Seleccionar todos"></th>
                                    <th>ID</th>
                                    <th>Descripcion</th>
                                    <th>Cantidad</th>
                                    <th class="text-right">Precio unitario</th>
                                    <th class="text-right">Descuento</th>
                                    <th class="text-right">Subtotal</th>
                                    <th class="text-center occ-desglose-col" style="width:150px;">Acciones</th>
                                  </tr>
                                </thead>
                                <tbody><?php
                                        if (empty($occ_detalles)) { ?>
                                    <tr>
                                      <td colspan="8">La Orden de Compra seleccionada no tiene items.</td>
                                    </tr><?php
                                        } else {
                                          foreach ($occ_detalles as $row) { ?>
                                      <tr class="occ-item-row" data-id="<?= $row['id'] ?>" data-subtotal="<?= $row['subtotal'] ?>">
                                        <td class="text-center" data-order="1"><input type="checkbox" class="occ-item-checkbox" data-id="<?= $row['id'] ?>"></td>
                                        <td><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['descripcion']) ?></td>
                                        <td><?= number_format($row['cantidad'], 2, ',', '.') ?></td>
                                        <td class="text-right"><?= $moneda_occ ?> <?= number_format($row['precio_unitario'], 2, ',', '.') ?></td>
                                        <td class="text-right"><?= $moneda_occ ?> <?= number_format($row['descuento'], 2, ',', '.') ?></td>
                                        <td class="text-right"><?= $moneda_occ ?> <?= number_format($row['subtotal'], 2, ',', '.') ?></td>
                                        <td class="text-center occ-desglose-cell"></td>
                                      </tr><?php
                                          }
                                        } ?>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>

                        <div class="form-group row mt-2" id="occ_breakdown_controls" style="display:none;">
                          <div class="col-sm-12 text-right">
                            <button type="button" id="btn_toggle_todos_desgloses" class="btn btn-secondary btn-sm">Ocultar desglose de todos</button>
                          </div>
                        </div>

                        <hr class="mt-4 mb-4">

                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label font-weight-bold">Cantidad items OCC seleccionados</label>
                          <div class="col-sm-3">
                            <span class="form-control-plaintext" id="cant_items_occ_seleccionados">0</span>
                          </div>
                          <label class="col-sm-3 col-form-label font-weight-bold">Base total OCC seleccionada</label>
                          <div class="col-sm-3">
                            <span class="form-control-plaintext text-right d-block" id="base_total_occ_seleccionada"><?= $moneda_occ ?> 0,00</span>
                          </div>
                        </div>

                        <hr class="mt-4 mb-4">

                        <div class="form-group row">
                          <input type="hidden" name="id_certificado_maestro_detalle">
                          <label class="col-sm-2 col-form-label">Proyecto(*)</label>
                          <div class="col-sm-4">
                            <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required="required">
                              <option value="">Seleccione...</option><?php
                                                                      $pdo = Database::connect();
                                                                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                                                      $sqlZon = "SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0 and p.id_cliente = ?";
                                                                      $q = $pdo->prepare($sqlZon);
                                                                      $q->execute([$id_cliente_occ]);
                                                                      while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                                                        echo "<option value='" . $fila['id'] . "'";
                                                                        echo ">" . $fila['nro_sitio'] . '-' . $fila['nro_subsitio'] . '-' . $fila['nro'] . ': ' . $fila['nombre'] . "</option>";
                                                                      }
                                                                      Database::disconnect(); ?>
                            </select>
                          </div>
                          <label class="col-sm-2 col-form-label">Modo de generacion(*)</label>
                          <div class="col-sm-4">
                            <div class="form-check">
                              <input class="form-check-input" type="radio" name="modo_generacion" id="modo_generacion_agrupar" value="agrupar" required>
                              <label class="form-check-label" for="modo_generacion_agrupar">Agrupar</label>
                            </div>
                            <div class="form-check mt-2">
                              <input class="form-check-input" type="radio" name="modo_generacion" id="modo_generacion_separar" value="separar">
                              <label class="form-check-label" for="modo_generacion_separar">Por cada item OCC</label>
                            </div>
                            <!-- <div class="form-control" style="height:auto; padding:0.45rem 0.75rem;">
                                
                              </div> -->
                          </div>
                        </div>

                        <div class="form-group row">
                          <input type="hidden" name="id_tipo_item" id="id_tipo_item" value="1">
                        </div>
                        <h6 class="mb-3 mt-4 font-weight-bold">Aperturado</h6>
                        <div class="form-group row">
                          <div class="col-12">
                            <div class="table-responsive dynamic-grid-wrap">
                              <table class="table table-sm table-bordered dynamic-grid-table" id="tabla_aperturado" style="width:100%">
                                <!-- <colgroup>
                                    <col style="width:42%;">
                                    <col style="width:11%;">
                                    <col style="width:8%;">
                                    <col style="width:9%;">
                                    <col style="width:11%;">
                                    <col style="width:11%;">
                                    <col style="width:8%;">
                                  </colgroup> -->
                                <thead>
                                  <tr>
                                    <th style="width:35%;">Descripcion</th>
                                    <th style="width:11%;">Unidad</th>
                                    <th style="width:11%;">Cantidad</th>
                                    <th style="width:13%;">Incidencia (%)</th>
                                    <th class="text-right" style="width:11%;">Precio Unitario</th>
                                    <th class="text-right" style="width:11%;">Total</th>
                                    <th style="width:8%;">Accion</th>
                                  </tr>
                                </thead>
                                <tbody id="body_aperturado"></tbody>
                                <tfoot>
                                  <tr>
                                    <td></td>
                                    <td colspan="2" class="text-right font-weight-bold">Suma incidencia</td>
                                    <td>
                                      <span class="font-weight-bold dynamic-grid-summary-total" id="incidencia_total_aperturado">0,00%</span>
                                    </td>
                                    <td>
                                      <small id="estado_incidencia_aperturado" class="text-danger dynamic-grid-summary-state">Debe sumar 100%</small>
                                    </td>
                                    <td colspan="2" class="text-center">
                                      <button type="button" id="btn_agregar_fila_aperturado" class="btn btn-outline-primary btn-sm">Agregar fila</button>
                                    </td>
                                  </tr>
                                </tfoot>
                              </table>
                            </div>
                          </div>
                        </div>
                        <!-- <div class="form-group row">
                            <div class="col-sm-3">
                              
                            </div>
                          </div> -->

                        <!-- Compatibilidad temporal hasta Fase 5: backend actual usa estos campos simples -->
                        <input type="hidden" name="descripcion" id="legacy_descripcion" value="">
                        <input type="hidden" name="cantidad" id="legacy_cantidad" value="0">
                        <input type="hidden" name="id_unidad_medida" id="legacy_id_unidad_medida" value="">
                        <input type="hidden" name="precio_unitario" id="legacy_precio_unitario" value="0">
                      </div>
                    </div>
                  </div>
                  <div class="card-footer">
                    <div class="col-12">

                      <button type="submit" class="btn btn-primary" name="btn_crear_otro_aperturado" value="1" id="btn_guardar_detalle">Crear y agregar otro aperturado</button>
                      <button type="submit" class="btn btn-success" name="btn_ir_certificado" value="1">Crear e ir al listado</button>
                      <button type="button" class="btn btn-secondary" id="btn_cancelar_edicion_lote" style="display:none;">Cancelar edicion de lote</button>
                      <a href='listarCertificadosMaestros.php' class="btn btn-light">Volver</a>

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
  <script src="assets/js/chat-menu.js"></script>
  <script src="assets/js/tooltip-init.js"></script>
  <!-- Plugins JS Ends-->
  <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.buttons.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/jszip.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/buttons.colVis.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/pdfmake.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/vfs_fonts.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.autoFill.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.select.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/buttons.bootstrap4.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/buttons.html5.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/buttons.print.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.responsive.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/responsive.bootstrap4.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.keyTable.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.colReorder.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.fixedHeader.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.rowReorder.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.scroller.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/custom.js"></script>
  <!-- Theme js-->
  <script src="assets/js/script.js"></script>
  <!-- Plugin used-->
  <script src="assets/js/select2/select2.full.min.js"></script>
  <script src="assets/js/select2/select2-custom.js"></script>
  <script>
    $(document).ready(function() {
      const simboloMonedaOcc = <?= json_encode($moneda_occ) ?>;
      const unidadesMedida = <?= json_encode($unidades_medida) ?>;
      const lotesEditablesData = <?= json_encode($lotes_editables) ?>;
      const selectedOccItems = {};
      const hiddenDesglosePorItem = {};
      const desglosePrecargadoPorItem = {};
      const occSubtotalPorId = {};
      let aperturadoRowIndex = 0;
      let ocultarTodosDesgloses = false;
      let occDataTable = null;
      let baseLoteEdicionForzada = 0;

      lotesEditablesData.forEach(function(lote) {
        lote.occ_ids = lote.occ_ids || [];
        lote.aperturado_rows = lote.aperturado_rows || [];
      });

      function preloadSelectedOccItemsFromExistingLots() {
        // No pre-seleccionar checkboxes al editar, solo marcar items para mostrar desglose
        lotesEditablesData.forEach(function(lote) {
          (lote.occ_ids || []).forEach(function(occId) {
            const key = String(occId);
            desglosePrecargadoPorItem[key] = true;
          });
        });
      }

      function getLoteEditableById(loteId) {
        for (let i = 0; i < lotesEditablesData.length; i++) {
          if (String(lotesEditablesData[i].aperturado) === String(loteId)) {
            return lotesEditablesData[i];
          }
        }
        return null;
      }

      function formatNumber(value) {
        return value.toLocaleString('es-AR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function getBaseCalculoActual() {
        return parseFloat($('#base_total_occ_seleccionada_input').val()) || 0;
      }

      function isEditandoLote() {
        return ($('#id_aperturado_edicion').val() || '').trim() !== '';
      }

      function isModoSeparar() {
        return $('input[name="modo_generacion"]:checked').val() === 'separar';
      }

      function getSortPriority(rowId, isSelected) {
        const paddedId = String(rowId).padStart(10, '0');
        const aperturadoId = ($('#id_aperturado_edicion').val() || '').trim();

        let loteIdx = -1;
        for (let i = 0; i < lotesEditablesData.length; i++) {
          const lt = lotesEditablesData[i];
          if (String(lt.modo_generacion || '') !== 'agrupar') continue;
          const ids = (lt.todos_occ_ids || lt.occ_ids || []).map(String);
          if (ids.indexOf(String(rowId)) >= 0) { loteIdx = i; break; }
        }

        const pIdx = String(Math.max(0, loteIdx)).padStart(3, '0');

        if (aperturadoId && isSelected && loteIdx >= 0) {
          if (String(lotesEditablesData[loteIdx].aperturado) === aperturadoId) {
            return '0' + pIdx + paddedId;
          }
        }
        if (loteIdx >= 0) {
          if (!aperturadoId || String(lotesEditablesData[loteIdx].aperturado) !== aperturadoId) {
            return '1' + pIdx + paddedId;
          }
        }
        if (isSelected) return '2' + paddedId;
        return '3' + paddedId;
      }

      function escapeHtml(text) {
        return String(text || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function getProyectoLabelById(idProyecto) {
        const key = String(idProyecto || '');
        if (!key) {
          return '';
        }
        const text = $('#id_proyecto option[value="' + key + '"]').first().text() || '';
        const clean = String(text).trim();
        return clean || key;
      }

      function updateOccBreakdownControls() {
        const hasItems = Object.keys(selectedOccItems).length > 0 || Object.keys(desglosePrecargadoPorItem).length > 0;
        const shouldShow = hasItems;
        $('#occ_breakdown_controls').toggle(shouldShow);

        if (!shouldShow) {
          return;
        }

        let allHidden = ocultarTodosDesgloses;
        if (!allHidden) {
          const ids = Object.keys(selectedOccItems);
          const idsPre = Object.keys(desglosePrecargadoPorItem);
          const allIds = ids.concat(idsPre.filter(function(id) {
            return ids.indexOf(id) < 0;
          }));
          allHidden = allIds.length > 0 && allIds.every(function(id) {
            return hiddenDesglosePorItem[id] === 'hidden';
          });
        }

        $('#btn_toggle_todos_desgloses').text(allHidden ? 'Mostrar desgloses' : 'Ocultar desglose de todos');
      }

      function resetFormCreateMode(keepRows) {
        $('#id_aperturado_edicion').val('');
        $('#solo_editar_aperturado').val('');
        $('#estado_edicion_lote').hide().text('');
        $('#btn_guardar_detalle').text('Crear');
        $('#btn_cancelar_edicion_lote').hide();
        $('button[name="btn_ir_certificado"]').show();
        baseLoteEdicionForzada = 0;

        Object.keys(selectedOccItems).forEach(function(id) {
          delete selectedOccItems[id];
        });
        Object.keys(hiddenDesglosePorItem).forEach(function(id) {
          delete hiddenDesglosePorItem[id];
        });
        Object.keys(desglosePrecargadoPorItem).forEach(function(id) {
          delete desglosePrecargadoPorItem[id];
        });
        ocultarTodosDesgloses = false;

        if (!keepRows) {
          $('#body_aperturado').empty();
          addAperturadoRow(false);
        }

        $('#id_proyecto').val('').trigger('change');
        $('input[name="modo_generacion"]').prop('checked', false);

        updateOccActionsColumnVisibility();
        syncOccRowStyles();
        updateOccSelectionSummary();
        renderOccBreakdowns();
      }

      function cargarLoteParaEdicion(loteId) {
        const lote = getLoteEditableById(loteId);
        if (!lote) {
          alert('No se encontro el lote seleccionado para editar.');
          return;
        }

        $('#id_aperturado_edicion').val(lote.aperturado);
        $('#solo_editar_aperturado').val('');
        baseLoteEdicionForzada = parseFloat(lote.monto_base_occ) || 0;

        $('#estado_edicion_lote')
          .text('Editando lote: ' + (lote.lote || lote.aperturado) + '. Al guardar se reemplazan las filas del lote completo.')
          .show();
        $('#btn_guardar_detalle').text('Guardar cambios de lote');
        $('#btn_cancelar_edicion_lote').show();
        $('button[name="btn_ir_certificado"]').hide();

        $('#id_proyecto').val(String(lote.id_proyecto || '')).trigger('change');
        $('input[name="modo_generacion"]').prop('checked', false);
        if (String(lote.modo_generacion) === 'separar') {
          $('#modo_generacion_separar').prop('checked', true);
        } else {
          $('#modo_generacion_agrupar').prop('checked', true);
        }

        updateOccActionsColumnVisibility();

        Object.keys(selectedOccItems).forEach(function(id) {
          delete selectedOccItems[id];
        });
        Object.keys(hiddenDesglosePorItem).forEach(function(id) {
          delete hiddenDesglosePorItem[id];
        });
        Object.keys(desglosePrecargadoPorItem).forEach(function(id) {
          delete desglosePrecargadoPorItem[id];
        });
        ocultarTodosDesgloses = false;

        (lote.todos_occ_ids || lote.occ_ids || []).forEach(function(occId) {
          const key = String(occId);
          selectedOccItems[key] = parseFloat(occSubtotalPorId[key]) || 0;
        });

        $('#body_aperturado').empty();
        (lote.aperturado_rows || []).forEach(function(row) {
          const nuevaFila = $(buildAperturadoRow());
          $('#body_aperturado').append(nuevaFila);
          nuevaFila.find('.aper-desc').val(row.descripcion || '');
          nuevaFila.find('.aper-unidad').val(String(row.id_unidad_medida || ''));
          nuevaFila.find('.aper-cantidad').val(row.cantidad || 0);
          nuevaFila.find('.aper-incidencia').val(row.incidencia || 0);
        });

        if (!$('#body_aperturado tr').length) {
          addAperturadoRow(false);
        }

        syncOccRowStyles();
        updateOccSelectionSummary();
        renderOccBreakdowns();
        if ($('input[name="modo_generacion"]:checked').val() === 'agrupar') {
          occDataTable.rows().invalidate().order([0, 'asc']).draw(false);
        }

        $('html, body').animate({
          scrollTop: $('#tabla_aperturado').offset().top - 100
        }, 250);
      }

      function cargarAperturadoParaEdicion(loteId) {
        const lote = getLoteEditableById(loteId);
        if (!lote) {
          alert('No se encontro el aperturado seleccionado para editar.');
          return;
        }

        $('#id_aperturado_edicion').val(lote.aperturado);
        $('#solo_editar_aperturado').val('1');
        baseLoteEdicionForzada = parseFloat(lote.monto_base_occ) || 0;

        $('#estado_edicion_lote')
          .text('Editando aperturado: ' + lote.aperturado + ' (lote: ' + (lote.lote || '') + '). Solo se modifican las filas del aperturado, no los items OCC.')
          .show();
        $('#btn_guardar_detalle').text('Guardar cambios de aperturado');
        $('#btn_cancelar_edicion_lote').show();
        $('button[name="btn_ir_certificado"]').hide();

        $('#id_proyecto').val(String(lote.id_proyecto || '')).trigger('change');
        $('input[name="modo_generacion"]').prop('checked', false);
        if (String(lote.modo_generacion) === 'separar') {
          $('#modo_generacion_separar').prop('checked', true);
        } else {
          $('#modo_generacion_agrupar').prop('checked', true);
        }

        updateOccActionsColumnVisibility();

        // Limpiamos selección actual y cargamos solo los items de este lote
        Object.keys(selectedOccItems).forEach(function(id) {
          delete selectedOccItems[id];
        });
        Object.keys(desglosePrecargadoPorItem).forEach(function(id) {
          delete desglosePrecargadoPorItem[id];
        });
        (lote.occ_ids || []).forEach(function(occId) {
          const key = String(occId);
          if (occSubtotalPorId[key] !== undefined) {
            selectedOccItems[key] = parseFloat(occSubtotalPorId[key]) || 0;
          }
        });

        $('#body_aperturado').empty();
        (lote.aperturado_rows || []).forEach(function(row) {
          const nuevaFila = $(buildAperturadoRow());
          $('#body_aperturado').append(nuevaFila);
          nuevaFila.find('.aper-desc').val(row.descripcion || '');
          nuevaFila.find('.aper-unidad').val(String(row.id_unidad_medida || ''));
          nuevaFila.find('.aper-cantidad').val(row.cantidad || 0);
          nuevaFila.find('.aper-incidencia').val(row.incidencia || 0);
        });

        if (!$('#body_aperturado tr').length) {
          addAperturadoRow(false);
        }

        syncOccRowStyles();
        updateOccSelectionSummary();
        renderOccBreakdowns();
        if ($('input[name="modo_generacion"]:checked').val() === 'agrupar') {
          occDataTable.rows().invalidate().order([0, 'asc']).draw(false);
        }

        $('html, body').animate({
          scrollTop: $('#tabla_aperturado').offset().top - 100
        }, 250);
      }

      function updateOccActionsColumnVisibility() {
        if (!occDataTable) {
          return;
        }

        occDataTable.column(7).visible(true, false);
        occDataTable.columns.adjust().draw(false);
      }

      function getSelectedOccIdsInTableOrder() {
        const ids = [];
        $('#tabla_occ_detalles tbody tr.occ-item-row').each(function() {
          const occId = String($(this).data('id') || '');
          if (!occId) {
            return;
          }
          if (selectedOccItems[occId] !== undefined || desglosePrecargadoPorItem[occId] !== undefined) {
            ids.push(occId);
          }
        });
        return ids;
      }

      function buildGroupedRenderContext() {
        const selectedIdsInOrder = getSelectedOccIdsInTableOrder();
        const groupedByOwner = {};
        const groupedMembershipByItem = {};
        const groupedRowClassByItem = {};

        lotesEditablesData
          .filter(function(lote) {
            return String(lote.modo_generacion || '') === 'agrupar';
          })
          .forEach(function(lote) {
            const loteOccIds = Array.isArray(lote.occ_ids) ? lote.occ_ids.map(String) : [];
            const idsDeGrupo = loteOccIds.length ?
              loteOccIds :
              selectedIdsInOrder.slice();

            if (!idsDeGrupo.length) {
              return;
            }

            const ownerOccId = idsDeGrupo[idsDeGrupo.length - 1];
            if (!groupedByOwner[ownerOccId]) {
              groupedByOwner[ownerOccId] = [];
            }
            groupedByOwner[ownerOccId].push({
              lote: lote,
              ids_grupo: idsDeGrupo,
            });

            idsDeGrupo.forEach(function(id, idx) {
              if (!groupedMembershipByItem[id]) {
                groupedMembershipByItem[id] = [];
              }
              groupedMembershipByItem[id].push({
                aperturado: lote.aperturado,
                ids_grupo: idsDeGrupo,
                is_owner: id === ownerOccId,
              });

              if (!groupedRowClassByItem[id]) {
                if (idsDeGrupo.length === 1) {
                  groupedRowClassByItem[id] = 'occ-grouped-single';
                } else if (idx === 0) {
                  groupedRowClassByItem[id] = 'occ-grouped-start';
                } else if (idx === idsDeGrupo.length - 1) {
                  groupedRowClassByItem[id] = 'occ-grouped-end';
                } else {
                  groupedRowClassByItem[id] = 'occ-grouped-middle';
                }
              }
            });
          });

        if (!isEditandoLote() && $('input[name="modo_generacion"]:checked').val() === 'agrupar') {
          const idsEnOrden = getSelectedOccIdsInTableOrder().filter(function(id) {
            return selectedOccItems[id] !== undefined;
          });
          if (idsEnOrden.length > 0) {
            idsEnOrden.forEach(function(id, idx) {
              if (!groupedRowClassByItem[id]) {
                groupedRowClassByItem[id] = idsEnOrden.length === 1
                  ? 'occ-grouped-single'
                  : idx === 0
                    ? 'occ-grouped-start'
                    : idx === idsEnOrden.length - 1
                      ? 'occ-grouped-end'
                      : 'occ-grouped-middle';
              }
            });
          }
        }

        return {
          groupedByOwner: groupedByOwner,
          groupedMembershipByItem: groupedMembershipByItem,
          groupedRowClassByItem: groupedRowClassByItem,
        };
      }

      function buildOccBreakdownHtml(occId, baseIndividual, groupedContext) {
        const groupMemberInfo = (groupedContext && groupedContext.groupedMembershipByItem && groupedContext.groupedMembershipByItem[String(occId)]) || [];
        const groupedLotesForThisItem = (groupedContext && groupedContext.groupedByOwner && groupedContext.groupedByOwner[String(occId)]) || [];
        const hasGroupedOwnership = groupedLotesForThisItem.length > 0;

        const lotesSeparados = lotesEditablesData.filter(function(lote) {
          if (String(lote.modo_generacion || '') === 'agrupar') {
            return false;
          }
          const occIds = Array.isArray(lote.occ_ids) ? lote.occ_ids.map(String) : [];
          return occIds.indexOf(String(occId)) >= 0;
        });

        function renderLoteHtml(lote) {
          const rowsLote = Array.isArray(lote.aperturado_rows) ? lote.aperturado_rows : [];
          const proyectoLabel = getProyectoLabelById(lote.id_proyecto);
          const baseLote = parseFloat(lote.monto_base_occ) || 0;
          const esAgrupado = String(lote.modo_generacion || '') === 'agrupar';
          const filasHtml = rowsLote.length ?
            rowsLote.map(function(row) {
              const cantidad = parseFloat(row.cantidad) || 0;
              const incidencia = parseFloat(row.incidencia) || 0;
              const totalFila = baseLote * (incidencia / 100);
              const precioUnitario = cantidad > 0 ? (totalFila / cantidad) : 0;
              return `
                        <tr>
                          <td>${escapeHtml(row.descripcion)}</td>
                          <td>${escapeHtml((unidadesMedida.find(function (u) { return String(u.id) === String(row.id_unidad_medida); }) || {}).unidad_medida || '')}</td>
                          <td class="text-right">${formatNumber(cantidad)}</td>
                          <td class="text-right">${formatNumber(incidencia)}%</td>
                          <td class="text-right">${simboloMonedaOcc} ${formatNumber(precioUnitario)}</td>
                          <td class="text-right">${simboloMonedaOcc} ${formatNumber(totalFila)}</td>
                        </tr>`;
            }).join('') :
            '<tr><td colspan="6" class="text-muted">Sin filas de aperturado guardadas para este lote.</td></tr>';

          const sumaTotal = rowsLote.reduce(function(sum, row) {
            const incidencia = parseFloat(row.incidencia) || 0;
            return sum + (baseLote * (incidencia / 100));
          }, 0);

          return `
                <div class="border rounded px-2 py-2 mb-2 occ-lote-inline-row">
                  <div class="table-responsive mb-2">
                    <table class="table table-sm table-bordered mb-0 occ-lote-summary-table">
                      <thead>
                        <tr>
                          <th>Aperturado</th>
                          <th>Lote</th>
                          <th>Proyecto</th>
                          <th class="text-right">Monto</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>${escapeHtml(lote.aperturado)}
                          ${!esAgrupado ? `<a href="#" class="btn-editar-aperturado-inline" data-lote="${escapeHtml(lote.aperturado)}" title="Editar solo aperturado (sin cambiar items OCC)" style="color: #17a2b8;">
                            <img src="img/icon_modificar.png" width="20" height="21" border="0" alt="Aperturado" title="Editar solo aperturado (sin cambiar items OCC)">
                          </a>` : ''}</td>
                          <td>${escapeHtml(lote.lote || '')}

                          
                          <a href="#" class="btn-editar-lote-inline mr-2" data-lote="${escapeHtml(lote.aperturado)}" title="Editar lote (items OCC + aperturado)" style="color: midnightblue;">
                            <img src="img/icon_modificar.png" width="20" height="21" border="0" alt="Modificar" title="Editar lote (items OCC + aperturado)">
                          </a></td>
                          <td>${escapeHtml(proyectoLabel)}</td>
                          <td class="text-right">${simboloMonedaOcc} ${formatNumber(parseFloat(lote.subtotal_lote) || 0)}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 occ-breakdown-table">
                      <thead>
                        <tr>
                          <th>Descripcion</th>
                          <th>Unidad</th>
                          <th class="text-right">Cantidad</th>
                          <th class="text-right">Incidencia</th>
                          <th class="text-right">Precio Unitario</th>
                          <th class="text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${filasHtml}
                      </tbody>
                      <tfoot class="bg-light">
                        <tr class="font-weight-bold">
                          <td colspan="5" class="text-right">Total</td>
                          <td class="text-right">${simboloMonedaOcc} ${formatNumber(sumaTotal)}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>`;
        }

        const loteAgrupadoDetalleHtml = groupedLotesForThisItem.length ?
          groupedLotesForThisItem.map(function(entry) {
            const idsGrupo = entry.ids_grupo || [];
            return `
                  <div class="occ-group-aperturado-wrap mb-2">
                    <small class="d-block text-muted mb-2">Aplica al grupo OCC: ${escapeHtml(idsGrupo.join(', '))}</small>
                    ${renderLoteHtml(entry.lote)}
                  </div>`;
          }).join('') :
          '';

        if (!hasGroupedOwnership && groupMemberInfo.length === 0 && lotesSeparados.length === 0) {
          return '';
        }

        if (!hasGroupedOwnership && groupMemberInfo.length > 0 && lotesSeparados.length === 0) {
          return '';
        }

        const lotesSeparadosHtml = lotesSeparados.length ?
          `<small class="d-block font-weight-bold text-primary mb-1">Lotes por item</small>${lotesSeparados.map(renderLoteHtml).join('')}` :
          '';

        const lotesHtml = (groupedLotesForThisItem.length || lotesSeparados.length) ?
          `
              ${loteAgrupadoDetalleHtml}
              ${lotesSeparadosHtml}
            ` :
          '<small class="text-muted d-block mb-2">Sin lotes asociados todavia para este item OCC.</small>';

        return `
            <div class="occ-breakdown-panel">
              <div class="occ-lote-inline-actions mb-2">
                ${lotesHtml}
              </div>
            </div>`;
      }

      // NUEVA FUNCIÓN: Obtener las filas actuales del aperturado (vista previa)
      function getCurrentAperturadoRows() {
        const rows = [];
        $('#body_aperturado tr').each(function() {
          const desc = ($(this).find('.aper-desc').val() || '').trim();
          const unidad = $(this).find('.aper-unidad').val() || '';
          const cantidad = parseFloat($(this).find('.aper-cantidad').val()) || 0;
          const incidencia = parseFloat($(this).find('.aper-incidencia').val()) || 0;

          // Solo incluimos si tiene al menos descripción o unidad
          if (desc || unidad) {
            rows.push({
              descripcion: desc,
              id_unidad_medida: unidad,
              cantidad: cantidad,
              incidencia: incidencia
            });
          }
        });
        return rows;
      }

      // NUEVA FUNCIÓN: Construir HTML de vista previa para un ítem OCC en modo "Por cada item"
      function buildPreviewBreakdownHtml(occId, baseIndividual) {
        const rows = getCurrentAperturadoRows();
        if (!rows.length) {
          return ''; // sin filas no mostramos nada
        }

        const filasHtml = rows.map(function(row) {
          const cantidad = parseFloat(row.cantidad) || 0;
          const incidencia = parseFloat(row.incidencia) || 0;
          const totalFila = baseIndividual * (incidencia / 100);
          const precioUnitario = cantidad > 0 ? (totalFila / cantidad) : 0;
          const unidadTexto = (unidadesMedida.find(function(u) {
            return String(u.id) === String(row.id_unidad_medida);
          }) || {}).unidad_medida || '';

          return `
              <tr>
                <td>${escapeHtml(row.descripcion)}</td>
                <td>${escapeHtml(unidadTexto)}</td>
                <td class="text-right">${formatNumber(cantidad)}</td>
                <td class="text-right">${formatNumber(incidencia)}%</td>
                <td class="text-right">${simboloMonedaOcc} ${formatNumber(precioUnitario)}</td>
                <td class="text-right">${simboloMonedaOcc} ${formatNumber(totalFila)}</td>
              </tr>`;
        }).join('');

        const sumaTotal = rows.reduce(function(sum, row) {
          const incidencia = parseFloat(row.incidencia) || 0;
          return sum + (baseIndividual * (incidencia / 100));
        }, 0);

        return `
            <div class="occ-breakdown-panel">
              <div class="occ-lote-inline-actions mb-2">
                <div class="border rounded px-2 py-2 mb-2">
                  <small class="d-block font-weight-bold text-success mb-2">Vista previa (aún no guardado)</small>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                      <thead>
                        <tr>
                          <th>Descripcion</th>
                          <th>Unidad</th>
                          <th class="text-right">Cantidad</th>
                          <th class="text-right">Incidencia</th>
                          <th class="text-right">Precio Unitario</th>
                          <th class="text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>${filasHtml}</tbody>
                      <tfoot class="bg-light">
                        <tr class="font-weight-bold">
                          <td colspan="5" class="text-right">Total</td>
                          <td class="text-right">${simboloMonedaOcc} ${formatNumber(sumaTotal)}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>`;
      }

      function renderOccBreakdowns() {
        if (!occDataTable) return;
        syncOccRowStyles();

        const separar = isModoSeparar();
        const mostrarEnEdicion = lotesEditablesData.length > 0 || isEditandoLote();
        const selectedIds = Object.keys(selectedOccItems);
        const groupedContext = buildGroupedRenderContext();

        updateOccBreakdownControls();

        occDataTable.rows().every(function() {
          const rowNode = $(this.node());
          const occId = String(rowNode.data('id') || '');
          if (!occId) {
            this.child.hide();
            return;
          }

          const isSelected = selectedOccItems[occId] !== undefined || desglosePrecargadoPorItem[occId] !== undefined;
          const toggleState = hiddenDesglosePorItem[occId];
          const isHidden = ocultarTodosDesgloses || toggleState === 'hidden';
          const isForceShown = toggleState === 'shown';

          const modoSeleccionado = $('input[name="modo_generacion"]:checked').val();
          if ((!modoSeleccionado && !mostrarEnEdicion) || isHidden) {
            this.child.hide();
            return;
          }

          if (!isForceShown && !isSelected) {
            const tieneLotesGuardados = (function() {
              for (let i = 0; i < lotesEditablesData.length; i++) {
                const ids = lotesEditablesData[i].occ_ids || [];
                if (ids.indexOf(parseInt(occId)) >= 0 || ids.indexOf(occId) >= 0) return true;
              }
              return false;
            })();
            if (!tieneLotesGuardados) {
              this.child.hide();
              return;
            }
          }

          const baseIndividual = parseFloat(selectedOccItems[occId] || occSubtotalPorId[occId]) || 0;

          if (isEditandoLote()) {
            let baseParaPreview;
            if (separar) {
              baseParaPreview = baseIndividual;
            } else {
              const selectedIdsInOrder = getSelectedOccIdsInTableOrder();
              const virtualOwner = selectedIdsInOrder.length > 0 ? selectedIdsInOrder[selectedIdsInOrder.length - 1] : null;
              if (occId !== virtualOwner) {
                this.child.hide();
                return;
              }
              baseParaPreview = getBaseCalculoActual();
            }
            const previewHtml = buildPreviewBreakdownHtml(occId, baseParaPreview);
            if (previewHtml) {
              this.child(previewHtml).show();
            } else {
              this.child.hide();
            }
            return;
          }

          // Comportamiento normal (creación o visualización de lotes guardados)
          const breakdownHtml = buildOccBreakdownHtml(occId, baseIndividual, groupedContext);
          if (!breakdownHtml && !isEditandoLote() && separar) {
            const previewHtml = buildPreviewBreakdownHtml(occId, baseIndividual);
            if (previewHtml) {
              this.child(previewHtml).show();
            } else {
              this.child.hide();
            }
            return;
          }

          // Vista previa en modo agrupar durante creación (una sola para el grupo)
          if (!breakdownHtml && !isEditandoLote() && !separar) {
            const selectedIdsInOrder = getSelectedOccIdsInTableOrder();
            const idsSinGrupo = selectedIdsInOrder.filter(function(id) {
              return (!groupedContext.groupedMembershipByItem || !groupedContext.groupedMembershipByItem[id]) && (selectedOccItems[id] !== undefined || occSubtotalPorId[id] !== undefined);
            });
            const virtualOwner = idsSinGrupo.length > 0 ? idsSinGrupo[idsSinGrupo.length - 1] : null;
            if (occId !== virtualOwner) {
              this.child.hide();
              return;
            }
            const baseTotal = getBaseCalculoActual();
            const previewHtml = buildPreviewBreakdownHtml(occId, baseTotal);
            if (previewHtml) {
              this.child(previewHtml).show();
            } else {
              this.child.hide();
            }
            return;
          }

          if (!breakdownHtml) {
            this.child.hide();
            return;
          }
          this.child(breakdownHtml).show();
        });
      }

      function renderUnidadOptions(selectedValue) {
        let options = '<option value="">Seleccione...</option>';
        unidadesMedida.forEach(function(u) {
          const selected = String(selectedValue || '') === String(u.id) ? ' selected' : '';
          options += '<option value="' + u.id + '"' + selected + '>' + u.unidad_medida + '</option>';
        });
        return options;
      }

      function buildAperturadoRow() {
        const rowId = aperturadoRowIndex++;
        return `
          <tr data-row-id="${rowId}">
            <td>
              <input type="text" class="form-control form-control-sm aper-desc" name="aperturado_descripcion[]" maxlength="199" required>
            </td>
            <td>
              <select class="form-control form-control-sm aper-unidad" name="aperturado_id_unidad_medida[]" required>${renderUnidadOptions('')}</select>
            </td>
            <td>
              <input type="number" class="form-control form-control-sm aper-cantidad" name="aperturado_cantidad[]" step="0.01" min="0" required>
            </td>
            <td>
              <input type="number" class="form-control form-control-sm aper-incidencia" name="aperturado_incidencia[]" step="0.01" min="0" max="100" required>
            </td>
            <td>
              <span class="form-control-plaintext aper-precio-unitario text-right d-block">${simboloMonedaOcc} 0,00</span><input type="hidden" class="aper-precio-unitario-hidden" name="aperturado_precio_unitario[]" value="0">
            </td>
            <td>
              <span class="form-control-plaintext aper-total text-right d-block">${simboloMonedaOcc} 0,00</span>
              <input type="hidden" class="aper-total-hidden" name="aperturado_total[]" value="0">
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-danger btn-eliminar-fila-aperturado">X</button>
            </td>
          </tr>`;
      }

      function addAperturadoRow(shouldFocus) {
        const nuevaFila = $(buildAperturadoRow());
        $('#body_aperturado').append(nuevaFila);
        if (shouldFocus) {
          nuevaFila.find('.aper-desc').trigger('focus');
        }
        return nuevaFila;
      }

      function syncLegacyFieldsFromFirstRow() {
        const first = $('#body_aperturado tr:first');
        if (!first.length) {
          $('#legacy_descripcion').val('');
          $('#legacy_cantidad').val('0');
          $('#legacy_id_unidad_medida').val('');
          $('#legacy_precio_unitario').val('0');
          return;
        }

        $('#legacy_descripcion').val(first.find('.aper-desc').val() || '');
        $('#legacy_cantidad').val(first.find('.aper-cantidad').val() || '0');
        $('#legacy_id_unidad_medida').val(first.find('.aper-unidad').val() || '');
        $('#legacy_precio_unitario').val(first.find('.aper-precio-unitario-hidden').val() || '0');
      }

      function recalcularAperturado() {
        const base = getBaseCalculoActual();
        let incidenciaTotal = 0;

        $('#body_aperturado tr').each(function() {
          const cantidad = parseFloat($(this).find('.aper-cantidad').val()) || 0;
          const incidencia = parseFloat($(this).find('.aper-incidencia').val()) || 0;
          const totalFila = base * (incidencia / 100);
          const precioUnitario = cantidad > 0 ? (totalFila / cantidad) : 0;

          incidenciaTotal += incidencia;

          $(this).find('.aper-total').text(simboloMonedaOcc + ' ' + formatNumber(totalFila));
          $(this).find('.aper-total-hidden').val(totalFila.toFixed(6));
          $(this).find('.aper-precio-unitario').text(simboloMonedaOcc + ' ' + formatNumber(precioUnitario));
          $(this).find('.aper-precio-unitario-hidden').val(precioUnitario.toFixed(6));
        });

        $('#incidencia_total_aperturado').text(formatNumber(incidenciaTotal) + '%');
        if (Math.abs(incidenciaTotal - 100) < 0.001) {
          $('#estado_incidencia_aperturado').text('OK (100%)').removeClass('text-danger').addClass('text-success');
        } else {
          $('#estado_incidencia_aperturado').text('Debe sumar 100%').removeClass('text-success').addClass('text-danger');
        }

        syncLegacyFieldsFromFirstRow();
        renderOccBreakdowns();
      }

      function updateOccSelectionSummary() {
        let ids = Object.keys(selectedOccItems);
        let total = 0;
        ids.forEach(function(id) {
          total += parseFloat(selectedOccItems[id]) || 0;
        });

        if (ids.length === 0 && isEditandoLote() && $('input[name="modo_generacion"]:checked').val() === 'agrupar' && baseLoteEdicionForzada > 0) {
          total = baseLoteEdicionForzada;
        }

        $('#ids_occ_detalle_seleccionados').val(ids.join(','));
        $('#cant_items_occ_seleccionados_input').val(ids.length);
        $('#base_total_occ_seleccionada_input').val(total);
        $('#cant_items_occ_seleccionados').text(ids.length);
        $('#base_total_occ_seleccionada').text(simboloMonedaOcc + ' ' + formatNumber(total));

        recalcularAperturado();
      }

      function syncOccRowStyles() {
        const groupedContext = buildGroupedRenderContext();

        $('#tabla_occ_detalles tbody tr.occ-item-row').each(function() {
          const rowId = String($(this).data('id') || '');
          const isSelected = !!selectedOccItems[rowId];
          const isPrecargado = !isSelected && !!desglosePrecargadoPorItem[rowId];
          const toggleState = hiddenDesglosePorItem[rowId];
          const isHidden = ocultarTodosDesgloses || toggleState === 'hidden';
          const actionCell = $(this).find('td.occ-desglose-cell');
          const checkbox = $(this).find('.occ-item-checkbox');

          $(this)
            .removeClass('selected')
            .removeClass('occ-grouped-member occ-grouped-start occ-grouped-middle occ-grouped-end occ-grouped-single');
          actionCell.find('.btn-toggle-desglose-item').remove();

          if (checkbox.length && checkbox.is(':checked') !== isSelected) {
            checkbox.prop('checked', isSelected);
          }

          $(this).find('td:first').attr('data-order', getSortPriority(rowId, isSelected));

          const tieneBreakdownGuardado = buildOccBreakdownHtml(rowId, parseFloat(selectedOccItems[rowId] || occSubtotalPorId[rowId]) || 0, groupedContext);
          const tieneLotesGuardados = (function() {
            for (let i = 0; i < lotesEditablesData.length; i++) {
              const ids = lotesEditablesData[i].occ_ids || [];
              if (ids.indexOf(parseInt(rowId)) >= 0 || ids.indexOf(rowId) >= 0) return true;
            }
            return false;
          })();
          const modoSel = $('input[name="modo_generacion"]:checked').val();
          const tieneFilasPreview = getCurrentAperturadoRows().length > 0;
          let mostrarBoton = false;
          if (tieneBreakdownGuardado || tieneLotesGuardados) {
            if (isModoSeparar()) {
              mostrarBoton = true;
            } else {
              mostrarBoton = !!tieneBreakdownGuardado;
            }
          }
          if (!mostrarBoton && modoSel && tieneFilasPreview) {
            if (isModoSeparar()) {
              mostrarBoton = isSelected;
            } else {
              const selectedIdsInOrder = getSelectedOccIdsInTableOrder();
              const selectedFiltered = selectedIdsInOrder.filter(function(id) {
                return selectedOccItems[id] !== undefined;
              });
              const virtualOwner = selectedFiltered.length > 0 ? selectedFiltered[selectedFiltered.length - 1] : null;
              mostrarBoton = (rowId === virtualOwner);
            }
          }
          if (mostrarBoton) {
            const textBtn = isHidden ? 'Mostrar desglose' : 'Ocultar desglose';
            actionCell.append('<button type="button" class="btn btn-secondary btn-sm btn-toggle-desglose-item" data-occ-id="' + rowId + '">' + textBtn + '</button>');
          }
        });
        $('#tabla_occ_detalles tbody tr.occ-item-row').each(function() {
          const rowId = String($(this).data('id') || '');
          const rowClass = groupedContext.groupedRowClassByItem[rowId] || '';
          if (!rowClass) {
            return;
          }
          $(this).addClass('occ-grouped-member ' + rowClass);
        });
      }

      function getIdsAgruparParaItem(occId) {
        const ids = [];
        lotesEditablesData.forEach(function(lote) {
          if (String(lote.modo_generacion || '') !== 'agrupar') return;
          const loteOccIds = (lote.occ_ids || []).map(String);
          if (loteOccIds.indexOf(String(occId)) >= 0) {
            loteOccIds.forEach(function(id) {
              if (ids.indexOf(id) < 0) ids.push(id);
            });
          }
        });
        return ids;
      }

      $(document).on('change', '.occ-item-checkbox', function() {
        const rowId = String($(this).data('id') || '');
        const subtotal = parseFloat($(this).closest('tr').data('subtotal')) || 0;
        if (!rowId) {
          return;
        }

        const isChecked = $(this).is(':checked');
        const idsGrupo = getIdsAgruparParaItem(rowId);

        if (isChecked) {
          selectedOccItems[rowId] = subtotal;
          delete desglosePrecargadoPorItem[rowId];
        } else {
          delete selectedOccItems[rowId];
          delete desglosePrecargadoPorItem[rowId];
        }

        syncOccRowStyles();
        updateOccSelectionSummary();
        if ($('input[name="modo_generacion"]:checked').val() === 'agrupar') {
          occDataTable.rows().invalidate().order([0, 'asc']).draw();
        }
      });

      $(document).on('change', '#check_all_occ_items', function() {
        const isChecked = $(this).is(':checked');
        $('.occ-item-checkbox').each(function() {
          const rowId = String($(this).data('id') || '');
          if (!rowId) return;
          const subtotal = parseFloat($(this).closest('tr').data('subtotal')) || 0;
          if (isChecked) {
            selectedOccItems[rowId] = subtotal;
            delete desglosePrecargadoPorItem[rowId];
          } else {
            delete selectedOccItems[rowId];
            delete desglosePrecargadoPorItem[rowId];
          }
        });
        syncOccRowStyles();
        updateOccSelectionSummary();
        if ($('input[name="modo_generacion"]:checked').val() === 'agrupar') {
          occDataTable.rows().invalidate().order([0, 'asc']).draw();
        }
      });

      $(document).on('click', '.btn-editar-lote-inline', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const loteId = String($(this).data('lote') || '');
        if (!loteId) {
          return;
        }
        cargarLoteParaEdicion(loteId);
      });

      $(document).on('click', '.btn-editar-aperturado-inline', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const loteId = String($(this).data('lote') || '');
        if (!loteId) {
          return;
        }
        cargarAperturadoParaEdicion(loteId);
      });

      $('#btn_cancelar_edicion_lote').on('click', function() {
        resetFormCreateMode(false);
      });

      $('#btn_agregar_fila_aperturado').on('click', function() {
        addAperturadoRow(true);
      });

      $(document).on('click', '.btn-eliminar-fila-aperturado', function() {
        $(this).closest('tr').remove();
        recalcularAperturado();
      });

      $(document).on('input change', '.aper-desc, .aper-unidad, .aper-cantidad, .aper-incidencia', function() {
        recalcularAperturado();
      });

      $('input[name="modo_generacion"]').on('change', function() {
        updateOccActionsColumnVisibility();
        syncOccRowStyles();
        renderOccBreakdowns();
        updateOccBreakdownControls();
        if ($(this).val() === 'agrupar') {
          occDataTable.rows().invalidate().order([0, 'asc']).draw();
        }
      });

      $(document).on('click', '.btn-toggle-desglose-item', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const occId = String($(this).data('occ-id') || '');
        if (!occId) {
          return;
        }

        const currentState = hiddenDesglosePorItem[occId];
        if (ocultarTodosDesgloses) {
          ocultarTodosDesgloses = false;
          Object.keys(selectedOccItems).forEach(function(id) {
            hiddenDesglosePorItem[id] = 'hidden';
          });
          delete hiddenDesglosePorItem[occId];
        } else if (currentState === 'hidden') {
          delete hiddenDesglosePorItem[occId];
        } else if (currentState === 'shown') {
          hiddenDesglosePorItem[occId] = 'hidden';
        } else {
          const btnText = $(this).text().trim();
          hiddenDesglosePorItem[occId] = btnText.indexOf('Ocultar') >= 0 ? 'hidden' : 'shown';
        }

        syncOccRowStyles();
        renderOccBreakdowns();
      });

      $('#btn_toggle_todos_desgloses').on('click', function() {
        let allHidden = ocultarTodosDesgloses;
        if (!allHidden) {
          const selectedIds = Object.keys(selectedOccItems);
          allHidden = selectedIds.length > 0 && selectedIds.every(function(id) {
            return hiddenDesglosePorItem[id] === 'hidden';
          });
        }

        if (allHidden) {
          ocultarTodosDesgloses = false;
          Object.keys(hiddenDesglosePorItem).forEach(function(id) {
            delete hiddenDesglosePorItem[id];
          });
        } else {
          ocultarTodosDesgloses = true;
        }

        syncOccRowStyles();
        renderOccBreakdowns();
      });

      addAperturadoRow(false);

      occDataTable = $('#tabla_occ_detalles').DataTable({
        stateSave: false,
        responsive: false,
        paging: true,
        pageLength: 10,
        language: {
          "decimal": "",
          "emptyTable": "No hay información",
          "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
          "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
          "infoFiltered": "(Filtrado de _MAX_ total registros)",
          "infoPostFix": "",
          "thousands": ",",
          "lengthMenu": "Mostrar _MENU_ Registros",
          "loadingRecords": "Cargando...",
          "processing": "Procesando...",
          "search": "Buscar:",
          "zeroRecords": "No hay resultados",
          "paginate": {
            "first": "Primero",
            "last": "Ultimo",
            "next": "Siguiente",
            "previous": "Anterior"
          }
        }
      });

      updateOccActionsColumnVisibility();

      if (window.feather) {
        feather.replace();
      }

      $('#tabla_occ_detalles tbody tr.occ-item-row').each(function() {
        const rowId = String($(this).data('id') || '');
        const subtotal = parseFloat($(this).data('subtotal')) || 0;
        if (rowId) {
          occSubtotalPorId[rowId] = subtotal;
        }
      });

      preloadSelectedOccItemsFromExistingLots();
      updateOccActionsColumnVisibility();
      updateOccSelectionSummary();
      syncOccRowStyles();
      renderOccBreakdowns();

      const hayLotesAgrupados = lotesEditablesData.some(function(lt) {
        return String(lt.modo_generacion || '') === 'agrupar' && (lt.occ_ids || []).length > 0;
      });
      if (hayLotesAgrupados || $('input[name="modo_generacion"]:checked').val() === 'agrupar') {
        occDataTable.rows().invalidate().order([0, 'asc']).draw(false);
      }

      $('#tabla_occ_detalles').on('draw.dt', function() {
        syncOccRowStyles();
        renderOccBreakdowns();
      });

      $('form.form.theme-form').on('submit', function(e) {
        const ids = ($('#ids_occ_detalle_seleccionados').val() || '').trim();
        const modoSeleccionado = $('input[name="modo_generacion"]:checked').val() || '';
        const editandoLote = isEditandoLote();

        const idsArray = ids ?
          ids.split(',').map(x => x.trim()).filter(Boolean) : [];

        if (!ids && !(editandoLote && modoSeleccionado === 'agrupar')) {
          e.preventDefault();
          alert('Debe seleccionar al menos un item de la OCC.');
          return;
        }

        if (!$('#body_aperturado tr').length) {
          e.preventDefault();
          alert('Debe agregar al menos una fila en el aperturado.');
          return;
        }

        let incidenciaTotal = 0;
        let hasInvalid = false;
        $('#body_aperturado tr').each(function() {
          const desc = ($(this).find('.aper-desc').val() || '').trim();
          const unidad = ($(this).find('.aper-unidad').val() || '').trim();
          const cantidad = parseFloat($(this).find('.aper-cantidad').val()) || 0;
          const incidencia = parseFloat($(this).find('.aper-incidencia').val()) || 0;

          if (!desc || !unidad || cantidad <= 0) {
            hasInvalid = true;
          }
          incidenciaTotal += incidencia;
        });

        if (hasInvalid) {
          e.preventDefault();
          alert('Complete descripcion, unidad y cantidad mayor a 0 en todas las filas de aperturado.');
          return;
        }

        if (Math.abs(incidenciaTotal - 100) >= 0.001) {
          e.preventDefault();
          alert('La incidencia total del aperturado debe sumar 100%.');
          return;
        }

        // Compatibilidad temporal con backend actual (Fase 5 migrará a persistencia masiva)
        syncLegacyFieldsFromFirstRow();
      });

    });
  </script>
</body>

</html>