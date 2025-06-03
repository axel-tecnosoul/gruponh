<?php
function debugQuery(PDO $pdo, string $sql, array $params): string {
  foreach ($params as $p) {
      // $pdo->quote() añade comillas y escapa el valor
      $quoted = $pdo->quote($p);
      // reemplazamos la primera aparición de '?' por el valor quoteado
      $sql = preg_replace('/\?/', $quoted, $sql, 1);
  }
  return $sql;
}

/**
 * Comprueba y supera la revisión anterior de un cómputo.
 *
 * @param PDO   $pdo            Conexión PDO ya en transacción.
 * @param int   $idComputo      ID del cómputo que acabas de aprobar por completo.
 * @param int   $modoDebug      1 para volcar consultas, 0 para silencioso.
 * @param array $usuarioSesion  $_SESSION['user'], o al menos ['id'].
 * @return string Texto que detalle lo sucedido (vacío si no había revisión anterior).
 */
function superarRevisionAnterior2(PDO $pdo, int $idComputo, int $modoDebug, array $usuarioSesion): string {
  $texto = "";

  // 1) Recuperar datos del cómputo actual
  $sql = "SELECT c.nro_revision, c.nro AS nro_computo, c.id_tarea FROM computos c WHERE c.id = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$idComputo]);
  $info = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$info || $info['nro_revision'] < 1) {
      // No hay revisión anterior
      return $texto;
  }

  $revisionAnterior = $info['nro_revision'] - 1;

  // 2) Obtener estado de la revisión anterior
  $sql = "SELECT id, id_estado FROM computos WHERE id_tarea = ? AND nro = ? AND nro_revision = ?";
  $stmt = $pdo->prepare($sql);
  $params = [$info['id_tarea'], $info['nro_computo'], $revisionAnterior];
  $stmt->execute($params);
  $prev = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug) {
    echo "SQL obtener revisión anterior:<br>" . debugQuery($pdo, $sql, $params) . "<br><br>";
  }

  if (!$prev) {
    return $texto;
  }

  // A) Si NO estaba en “Gestionando” (4) → superarla sin más
  if ($prev['id_estado'] != 4) {
    $sql = "UPDATE computos SET id_estado = 7 WHERE id = ?";
    $pdo->prepare($sql)->execute([$prev['id']]);
    if ($modoDebug) {
      echo "Superada (sin gestionar) revisión anterior:<br>" . debugQuery($pdo, $sql, [$prev['id']]) . "<br><br>";
    }
    return ". Revisión anterior N° {$revisionAnterior} superada.";
  }

  // B) Estaba en “Gestionando” → comparo línea a línea
  // Traer líneas no canceladas de ambas revisiones
  $sql = "SELECT
            cd_previo.id        AS id_previo,
            cd_previo.id_material,
            cd_previo.cantidad  AS cantidad_previo,
            cd_previo.reservado AS reservado_previo,
            cd_actual.id        AS id_actual,
            cd_actual.cantidad  AS cantidad_actual
          FROM computos_detalle cd_previo
          JOIN computos_detalle cd_actual 
            ON cd_previo.id_material = cd_actual.id_material
          WHERE cd_previo.id_computo = :previo
            AND cd_actual.id_computo = :actual
            AND cd_previo.cancelado = 0
            AND cd_actual.cancelado = 0";
  $stmt = $pdo->prepare($sql);
  $params = [':previo' => $prev['id'], ':actual' => $idComputo];
  $stmt->execute($params);
  $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($modoDebug) {
    echo "SQL líneas revisión B:<br>" . debugQuery($pdo, $sql, $params) . "<br><br>";
  }

  foreach ($lineas as $ln) {
    // B.A) Concepto Eliminado
    if (is_null($ln['id_actual'])) {
      // B.A.1) reserva → borrarla
      if ($ln['reservado_previo'] > 0) {
        $sql = "UPDATE computos_detalle SET reservado = 0 WHERE id = ?";
        $pdo->prepare($sql)->execute([$ln['id_previo']]);
        if ($modoDebug) {
          echo "B.A.1) " . debugQuery($pdo, $sql, [$ln['id_previo']]) . "<br><br>";
        }
      }
      // B.A.2/3) pedido_detalle
      $sql = "SELECT id, cantidad AS pedido_cantidad, comprado FROM pedidos_detalle WHERE id_computo_detalle = ?";
      $stmtPd = $pdo->prepare($sql);
      $stmtPd->execute([$ln['id_previo']]);
      $pd = $stmtPd->fetch(PDO::FETCH_ASSOC);

      if ($pd) {
        if ($pd['comprado'] == 0) {
          $sql = "UPDATE pedidos_detalle SET cancelado = 1, cantidad = 0 WHERE id = ?";
          $pdo->prepare($sql)->execute([$pd['id']]);
          if ($modoDebug) {
            echo "B.A.2) " . debugQuery($pdo, $sql, [$pd['id']]) . "<br><br>";
          }
        } else {
          if ($modoDebug) {
            echo "B.A.3) pedido en compra, solo notificar<br><br>";
          }
        }
      }
      continue;
    }

    // B.B) Cantidad Cambiada
    if ($ln['cantidad_previo'] != $ln['cantidad_actual']) {
      $diff = $ln['cantidad_actual'] - $ln['cantidad_previo'];

      // B.B.1) reserva
      if ($ln['reservado_previo'] > 0) {
        if ($diff < 0) {
          // ajustar previa
          $nueva = $ln['reservado_previo'] + $diff;
          $sql = "UPDATE computos_detalle SET reservado = GREATEST(0, ?) WHERE id = ?";
          $pdo->prepare($sql)->execute([$nueva, $ln['id_previo']]);
          if ($modoDebug) {
            echo "B.B.1.2) " . debugQuery($pdo, $sql, [$nueva, $ln['id_previo']]) . "<br><br>";
          }
          // heredar a actual
          $heredada = min($ln['cantidad_actual'], $nueva);
          $sql = "UPDATE computos_detalle SET reservado = ? WHERE id = ?";
          $pdo->prepare($sql)->execute([$heredada, $ln['id_actual']]);
          if ($modoDebug) {
            echo "B.B.1.2 heredada) " . debugQuery($pdo, $sql, [$heredada, $ln['id_actual']]) . "<br><br>";
          }
        } else {
          if ($modoDebug) {
            echo "B.B.1.1) reserva existe y diff>0, solo notificar<br><br>";
          }
        }
      }

      // B.B.2/3) pedido_detalle
      $sql = "SELECT id, cantidad AS pedido_cantidad, comprado FROM pedidos_detalle WHERE id_computo_detalle = ?";
      $stmtPd = $pdo->prepare($sql);
      $stmtPd->execute([$ln['id_previo']]);
      $pd = $stmtPd->fetch(PDO::FETCH_ASSOC);

      if ($pd) {
        if ($pd['comprado'] == 0) {
          if ($diff < 0) {
            $cantPedir = $ln['cantidad_actual'] - max(0, $ln['reservado_previo']);
            if ($cantPedir <= 0) {
              $sql = "UPDATE pedidos_detalle SET cancelado = 1, cantidad = 0 WHERE id = ?";
              $pdo->prepare($sql)->execute([$pd['id']]);
              if ($modoDebug) {
                echo "B.B.2.2.1) " . debugQuery($pdo, $sql, [$pd['id']]) . "<br><br>";
              }
            } else {
              $sql = "UPDATE pedidos_detalle SET cantidad = ?, id_computo_detalle = ? WHERE id = ?";
              $pdo->prepare($sql)->execute([$cantPedir, $ln['id_actual'], $pd['id']]);
              if ($modoDebug) {
                echo "B.B.2.2.2) " . debugQuery($pdo, $sql, [$cantPedir, $ln['id_actual'], $pd['id']]) . "<br><br>";
              }
            }
          } else {
            if ($modoDebug) {
              echo "B.B.2.1) diff>0 en pedido, solo notificar<br><br>";
            }
          }
        } else {
          if ($modoDebug) {
            echo "B.B.3) pedido en compra, solo notificar<br><br>";
          }
        }
      }
    }
  }

  // 3) Marcamos la revisión anterior como “Superado”
  $sql = "UPDATE computos SET id_estado = 7 WHERE id = ?";
  $pdo->prepare($sql)->execute([$prev['id']]);
  if ($modoDebug) {
    echo "Fin B) – rev. anterior superada:<br>" . debugQuery($pdo, $sql, [$prev['id']]) . "<br><br>";
  }
  $texto = ". Revisión anterior N° {$revisionAnterior} superada.";

  return $texto;
}

function superarRevisionAnterior(PDO $pdo, int $idComputo, int $modoDebug, array $usuarioSesion): string {
  $texto = "";

  // 1) Recuperar datos del cómputo actual
  $sql = "SELECT c.nro_revision, c.nro AS nro_computo, c.id_tarea FROM computos c WHERE c.id = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$idComputo]);
  $info = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$info || $info['nro_revision'] < 1) {
    // No hay revisión anterior
    return $texto;
  }

  $revisionAnterior = $info['nro_revision'] - 1;

  // 2) Obtener estado de la revisión anterior
  $sql = "SELECT id, id_estado FROM computos WHERE id_tarea = ? AND nro = ? AND nro_revision = ?";
  $stmt = $pdo->prepare($sql);
  $params = [$info['id_tarea'], $info['nro_computo'], $revisionAnterior];
  $stmt->execute($params);
  $prev = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug) {
    echo "SQL obtener revisión anterior:<br>" . debugQuery($pdo, $sql, $params) . "<br><br>";
  }

  if (!$prev) {
    // No existe esa revisión
    return $texto;
  }

  if ($prev['id_estado'] != 4) {
    // ============================================================
    // A) Si la revisión anterior NO estaba en “Gestionando” (4)
    //    → se pasa directamente a “Superado” sin más lógica B
    // ============================================================
    
    $texto = ". La revisión anterior N° {$revisionAnterior} NO se esta Gestionando, por lo que ha sido superada sin mas.";

  }else{
    // ============================================================
    // B) Revisión anterior en “Gestionando” (4)
    //    → comparaciones línea a línea según B.A y B.B
    // ============================================================

    // B) 2) Traer líneas NO canceladas de ambas revisiones
    $sql = "SELECT
              cd_previo.id        AS id_previo,
              cd_previo.id_material,
              cd_previo.cantidad  AS cantidad_previo,
              cd_previo.reservado AS reservado_previo,
              cd_actual.id        AS id_actual,
              cd_actual.cantidad  AS cantidad_actual
            FROM computos_detalle cd_previo
            JOIN computos_detalle cd_actual 
              ON cd_previo.id_material = cd_actual.id_material
            WHERE cd_previo.id_computo = :previo
              AND cd_actual.id_computo = :actual
              AND cd_previo.cancelado = 0
              AND cd_actual.cancelado = 0";
    $stmt = $pdo->prepare($sql);
    $params = [':previo' => $prev['id'], ':actual' => $idComputo];
    $stmt->execute($params);
    $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($modoDebug) {
      echo "B) SQL líneas revisión B:<br>" . debugQuery($pdo, $sql, $params) . "<br><br>";
    }

    foreach ($lineas as $ln) {
      // -----------------------------------
      // B.A) Concepto Eliminado
      // -----------------------------------
      if (is_null($ln['id_actual'])) {
        // B.A.1) si tiene reserva → la borramos
        if ($ln['reservado_previo'] > 0) {
          $sql = "UPDATE computos_detalle SET reservado = 0 WHERE id = ?";
          $pdo->prepare($sql)->execute([$ln['id_previo']]);
          if ($modoDebug) {
            echo "B.A.1) eliminar reserva previa:<br>". debugQuery($pdo, $sql, [$ln['id_previo']]) . "<br><br>";
          }
        }

        // B.A.2 y B.A.3) gestionar pedidos en pedidos_detalle
        $sql = "SELECT id, cantidad AS pedido_cantidad, comprado FROM pedidos_detalle WHERE id_computo_detalle = ?";
        $stmtPd = $pdo->prepare($sql);
        $stmtPd->execute([$ln['id_previo']]);
        $pd = $stmtPd->fetch(PDO::FETCH_ASSOC);

        if ($pd) {
          if ($pd['comprado'] == 0) {
            // B.A.2) pedido no comprando → cancelar parcialmente
            $sql = "UPDATE pedidos_detalle SET cancelado = 1, cantidad = 0 WHERE id = ?";
            $pdo->prepare($sql)->execute([$pd['id']]);
            if ($modoDebug) {
              echo "B.A.2) cancelar pedido_detalle:<br>". debugQuery($pdo, $sql, [$pd['id']]) . "<br><br>";
            }
          } else {
            // B.A.3) pedido en compra → solo notificar
            if ($modoDebug) {
              echo "B.A.3) pedido ya en compra, solo notificar<br><br>";
            }
          }
        }

        continue; // pasamos a la siguiente línea
      }

      // -----------------------------------
      // B.B) Cantidad Cambiada
      // -----------------------------------
      if ($ln['cantidad_previo'] != $ln['cantidad_actual']) {
        $diff = $ln['cantidad_actual'] - $ln['cantidad_previo'];

        // B.B.1) gestionar reserva
        if ($ln['reservado_previo'] > 0) {
          if ($diff < 0) {
            // B.B.1.2) nueva cantidad < reserva → ajustar y heredar
            $nueva = $ln['reservado_previo'] + $diff;
            $sql = "UPDATE computos_detalle SET reservado = GREATEST(0, ?) WHERE id = ?";
            $pdo->prepare($sql)->execute([$nueva, $ln['id_previo']]);
            if ($modoDebug) {
              echo "B.B.1.2) ajustar reserva previa:<br>". debugQuery($pdo, $sql, [$nueva, $ln['id_previo']]) . "<br><br>";
            }
            // heredar reserva a la revisión actual
            $heredada = min($ln['cantidad_actual'], $nueva);
            $sql = "UPDATE computos_detalle SET reservado = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$heredada, $ln['id_actual']]);
            if ($modoDebug) {
              echo "B.B.1.2 heredada) heredar reserva:<br>". debugQuery($pdo, $sql, [$heredada, $ln['id_actual']]) . "<br><br>";
            }
          } else {
            // B.B.1.1) nueva cantidad > reserva → no tocar reserva, notificar
            if ($modoDebug) {
              echo "B.B.1.1) diff>0 con reserva, solo notificar<br><br>";
            }
          }
        }

        // B.B.2 y B.B.3) gestionar pedidos
        $sql = "SELECT id, cantidad AS pedido_cantidad, comprado FROM pedidos_detalle WHERE id_computo_detalle = ?";
        $stmtPd = $pdo->prepare($sql);
        $stmtPd->execute([$ln['id_previo']]);
        $pd = $stmtPd->fetch(PDO::FETCH_ASSOC);

        if ($pd) {
          if ($pd['comprado'] == 0) {
            if ($diff < 0) {
              // B.B.2.2) nueva cantidad < anterior → calcular cantidad a pedir
              $cantPedir = $ln['cantidad_actual'] - max(0, $ln['reservado_previo']);
              if ($cantPedir <= 0) {
                // B.B.2.2.1) cancelar pedido_detalle
                $sql = "UPDATE pedidos_detalle SET cancelado = 1, cantidad = 0 WHERE id = ?";
                $pdo->prepare($sql)->execute([$pd['id']]);
                if ($modoDebug) {
                  echo "B.B.2.2.1) cancelar pedido_detalle:<br>". debugQuery($pdo, $sql, [$pd['id']]) . "<br><br>";
                }
              } else {
                // B.B.2.2.2) actualizar pedido y reasignar
                $sql = "UPDATE pedidos_detalle SET cantidad = ?, id_computo_detalle = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$cantPedir, $ln['id_actual'], $pd['id']]);
                if ($modoDebug) {
                  echo "B.B.2.2.2) reasignar pedido_detalle:<br>". debugQuery($pdo, $sql, [$cantPedir, $ln['id_actual'], $pd['id']]) . "<br><br>";
                }
              }
            } else {
              // B.B.2.1) nueva cantidad > anterior → solo notificar
              if ($modoDebug) {
                echo "B.B.2.1) diff>0 en pedido, solo notificar<br><br>";
              }
            }
          } else {
            // B.B.3) pedido en compra → solo notificar
            if ($modoDebug) {
              echo "B.B.3) pedido en proceso, solo notificar<br><br>";
            }
          }
        }
      }
    }

    $texto = ". La Revisión anterior N° {$revisionAnterior} se estaba gestionando, por lo que fue superada luego de revisar sus conceptos.";
  }

  // 3) Finalmente, marcar revisión anterior como “Superado”
  $sql = "UPDATE computos SET id_estado = 7 WHERE id = ?";
  $pdo->prepare($sql)->execute([$prev['id']]);
  if ($modoDebug) {
    echo "Fin B) - rev. anterior superada:<br>". debugQuery($pdo, $sql, [$prev['id']]) . "<br><br>";
  }

  return $texto;
}

