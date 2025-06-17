<?php
function debugQuery(PDO $pdo, string $sql, array $params): string {
  foreach ($params as $key => $value) {
    // PDO::quote() añade comillas y escapa caracteres especiales
    $quoted = $pdo->quote($value);

    if (is_int($key)) {
      // marcador posicional «?»
      $sql = preg_replace('/\?/', $quoted, $sql, 1);
    } else {
      // marcador nombrado: quitamos un : adicional si lo trae la clave
      $name    = ltrim($key, ':');
      // \b asegura que no coincida dentro de palabras más largas
      $pattern = '/:' . preg_quote($name, '/') . '\b/';
      $sql     = preg_replace($pattern, $quoted, $sql);
    }
  }
  return $sql;
}

/**
 * Ejecuta una consulta (SELECT, UPDATE, etc.) y, si está en modo debug,
 * imprime el debugQuery y el resultado de la ejecución (para UPDATE/DELETE).
 *
 * @param PDO    $pdo
 * @param string $sql
 * @param array  $params
 * @param bool   $modoDebug
 * @param string $etiqueta  Texto descriptivo que se mostrará en debug
 * @return PDOStatement     El statement preparado y ejecutado
 */
function debugExecute(PDO $pdo, string $sql, array $params, bool $modoDebug, string $etiqueta = ''): PDOStatement {
    if ($modoDebug) {
        echo "-{$etiqueta}:<br>" . debugQuery($pdo, $sql, $params) . "<br>";
        if(!preg_match('/^\s*(SELECT)/i', $sql)){
          echo "<br>";
        }
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($modoDebug && preg_match('/^\s*(UPDATE|DELETE)/i', $sql)) {
        echo "  → Filas afectadas: " . $stmt->rowCount() . "<br><br>";
    }
    return $stmt;
}

/**
 * Recupera el registro de pedidos_detalle para un id_computo_detalle dado.
 *
 * @param PDO  $pdo
 * @param int  $idComputoDetalle
 * @param bool $modoDebug
 * @return array|null   Array asociativo con ['id','pedido_cantidad','comprado'] o null
 */
function fetchPedidoDetalle(PDO $pdo, int $idComputoDetalle, bool $modoDebug): ?array {
    $sql = "SELECT id, cantidad AS pedido_cantidad, comprado FROM pedidos_detalle WHERE id_computo_detalle = ?";
    $stmt = debugExecute($pdo, $sql, [$idComputoDetalle], $modoDebug, "Recuperar pedidos_detalle para cómputo_detalle $idComputoDetalle");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Cancela un pedido_detalle (marca cancelado=1 y cantidad=0).
 *
 * @param PDO  $pdo
 * @param int  $idPedidoDetalle
 * @param bool $modoDebug
 * @return void
 */
function cancelPedidoDetalle(PDO $pdo, int $idPedidoDetalle, bool $modoDebug): bool {
    $sql = "UPDATE pedidos_detalle SET cancelado = 1, cantidad = 0 WHERE id = ?";
    $stmt=debugExecute($pdo, $sql, [$idPedidoDetalle], $modoDebug, "Cancelar pedido_detalle $idPedidoDetalle");
    return $stmt->rowCount() == 1; // Retorna true si se canceló correctamente
}

/**
 * Pasa la reserva de un cómputo_detalle de la revision anterior a la nueva, ajustando cantidades si es necesario.
 *
 * @param PDO  $pdo
 * @param int  $id_revision_anterior
 * @param int  $id_revision_actual
 * @param int  $nueva_cantidad
 * @param bool $modoDebug
 * @return bool
 */
function pasarReservaDeRevisionAnterior(PDO $pdo, int $id_revision_anterior, int $id_revision_actual, int $nueva_cantidad, bool $modoDebug): bool{
  $sql = "UPDATE computos_detalle SET reservado = ?, saldo = saldo - ? WHERE id = ?";
  $stmt=debugExecute($pdo, $sql, [$nueva_cantidad,$nueva_cantidad, $id_revision_actual], $modoDebug, "Pasamos la reserva a la nueva revisión");

  $sql = "UPDATE computos_detalle SET reservado = 0 WHERE id = ?";
  $stmt2=debugExecute($pdo, $sql, [$id_revision_anterior], $modoDebug, "Dejamos en 0 la reserva de la revision anterior");

  return $stmt->rowCount() == 1 && $stmt2->rowCount() == 1; // Retorna true si se actualizó correctamente
}

/**
 * Reasigna o ajusta un pedido_detalle a otro cómputo_detalle.
 *
 * @param PDO  $pdo
 * @param int  $idPedidoDetalle
 * @param int  $nuevaCantidad
 * @param int  $nuevoComputoDetalle
 * @param bool $modoDebug
 * @return void
 */
function reassignPedidoDetalle(PDO $pdo, int $idPedidoDetalle, int $nuevaCantidad, int $nuevoComputoDetalle, bool $modoDebug): void {
    $sql = "UPDATE pedidos_detalle SET cantidad = ?, id_computo_detalle = ? WHERE id = ?";
    debugExecute($pdo, $sql, [$nuevaCantidad, $nuevoComputoDetalle, $idPedidoDetalle], $modoDebug, "Reasignar pedido_detalle $idPedidoDetalle");
}

/**
 * Comprueba y supera la revisión anterior de un cómputo.
 *
 * @param PDO   $pdo            Conexión PDO ya en transacción.
 * @param int   $idComputo      ID del cómputo que acabas de aprobar por completo.
 * @param int   $modoDebug      1 para volcar consultas, 0 para silencioso.
 * @return string Texto que detalle lo sucedido (vacío si no había revisión anterior).
 */

function superarRevisionAnterior(PDO $pdo, int $idComputo, int $modoDebug): string {
  $texto = "";

  // 1) Recuperar datos del cómputo actual
  $sql = "SELECT c.nro_revision, c.nro AS nro_computo, c.id_tarea FROM computos c WHERE c.id = ?";
  //$stmt = $pdo->prepare($sql);
  $params = [$idComputo];
  //$stmt->execute($params);
  $stmt=debugExecute($pdo,$sql,$params,$modoDebug,"Recuperar datos del cómputo actual ($idComputo)");

  $info = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug) {
    //echo "-Recuperar datos del cómputo actual ($idComputo):<br>" . debugQuery($pdo, $sql, $params) . "<br>";
    var_dump($info);
  }

  if (!$info || $info['nro_revision'] < 1) {
    // No hay revisión anterior
    return $texto;
  }

  $revisionAnterior = $info['nro_revision'] - 1;

  // 2) Obtener estado de la revisión anterior
  $sql = "SELECT c.id, c.id_estado, ec.estado FROM computos c JOIN estados_computos ec ON c.id_estado=ec.id WHERE c.id_tarea = ? AND c.nro = ? AND c.nro_revision = ?";
  $stmt = $pdo->prepare($sql);
  $params = [$info['id_tarea'], $info['nro_computo'], $revisionAnterior];
  $stmt->execute($params);
  $prev = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug) {
    echo "-Recuperar datos de la revisión anterior ($revisionAnterior):<br>" . debugQuery($pdo, $sql, $params) . "<br>";
    var_dump($prev);
  }

  if (!$prev) {
    // No existe esa revisión
    if ($modoDebug) {
      echo "-No existe revisión anterior N° {$revisionAnterior} para el cómputo N° {$info['nro_computo']}<br>";
    }
    return $texto;
  }

  if ($prev['id_estado'] != 4) {
    // ============================================================
    // A) Si la revisión anterior NO estaba en “Gestionando” (4)
    //    → se pasa directamente a “Superado” sin más lógica B
    // ============================================================

    if ($modoDebug) {
      echo "-La revision anterior del computo está en un estado diferente a 4 (Gestionando), por lo que se pasa directamente a Superado sin mayor lógica<br><br>";
    }
    
    $texto = ". La revisión anterior N° {$revisionAnterior} NO se esta Gestionando, por lo que ha sido superada sin mas.";

  }else{
    if ($modoDebug) {
      echo "-La revision anterior del computo está en estado Gestionando, por lo que debemos comaprar linea por linea los conceptos de ambas revisiones y actuar segun cada caso<br><br>";
    }
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
            LEFT JOIN computos_detalle cd_actual
              ON cd_previo.id_material = cd_actual.id_material
             AND cd_actual.id_computo = :actual
             AND cd_actual.cancelado = 0
            WHERE cd_previo.id_computo = :previo
              AND cd_previo.cancelado = 0";

    $stmt = $pdo->prepare($sql);
    $params = [':previo' => $prev['id'], ':actual' => $idComputo];
    $stmt->execute($params);
    $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($modoDebug) {
      echo "-Comparamos las líneas de cada revisión:<br>" . debugQuery($pdo, $sql, $params) . "<br>";
      var_dump($lineas);
    }

    foreach ($lineas as $ln) {
      // -----------------------------------
      // B.A) Concepto Eliminado
      // -----------------------------------
      if (is_null($ln['id_actual'])) {
        if ($modoDebug) {
          echo "-Se ha eliminado el concepto con ID ".$ln['id_previo']."<br><br>";
        }
        // B.A.1) si tiene reserva → la borramos
        if ($ln['reservado_previo'] > 0) {
          if ($modoDebug) {
            echo "-Tenia una reserva (".$ln['reservado_previo'].")<br><br>";
          }
          $sql = "UPDATE computos_detalle SET reservado = 0 WHERE id = ?";
          $params = [$ln['id_previo']];
          $ok=$pdo->prepare($sql)->execute($params);
          if ($modoDebug) {
            echo "-Eliminamos la reserva previa:<br>". debugQuery($pdo, $sql, $params) . "<br>";
            var_dump($ok);
          }
        }else{
          if ($modoDebug) {
            echo "-No tenia reserva (".$ln['reservado_previo'].")<br><br>";
          }
        }

        // B.A.2 y B.A.3) gestionar pedidos en pedidos_detalle

        $pd=fetchPedidoDetalle($pdo,$id_computo_detalle=$ln['id_previo'],$modoDebug);

        if ($modoDebug) {
          //echo "-Recuperamos los pedidos relacionados al computo:<br>" . debugQuery($pdo, $sql, $params) . "<br>";
          var_dump($pd);
        }

        if ($pd) {
          if ($modoDebug) {
            echo "-Tiene pedidos asociados (id:".$pd["id"].", cantidad:".$pd["pedido_cantidad"]."):<br><br>";
            //var_dump($pd);
          }
          if ($pd['comprado'] == 0) {
            if ($modoDebug) {
              echo "-Aun no se está comprando (".$pd['comprado'].")<br><br>";
            }
            // B.A.2) pedido no comprando → cancelar parcialmente

            $ok=cancelPedidoDetalle($pdo,$id_pedido_detalle=$pd['id'],$modoDebug);

            if ($modoDebug) {
              echo "B.A.2) cancelar pedido_detalle:<br>". debugQuery($pdo, $sql, $params) . "<br>";
              var_dump($ok);
            }
          } else {
            // B.A.3) pedido en compra → solo notificar
            if ($modoDebug) {
              echo "-El pedido ya está en compra, solo notificar<br><br>";
            }
          }
        }else{
          if ($modoDebug) {
            echo "-No tenia pedido (".$ln['id_previo'].")<br><br>";
          }
        }

        continue; // pasamos a la siguiente línea
      }

      // -----------------------------------
      // B.B) Cantidad Cambiada
      // -----------------------------------
      if ($ln['cantidad_previo'] != $ln['cantidad_actual']) {
        
        $diff = $ln['cantidad_actual'] - $ln['cantidad_previo'];
        $cantidadActualEsMenorQueCantidadPrevio = $ln['cantidad_actual'] < $ln['cantidad_previo'];
        
        if ($modoDebug) {
          echo "-Hubo un cambio en la cantidad:<br>&nbsp;Revision anterior: ".$ln['cantidad_previo']."<br>&nbsp;Revision actual: ".$ln['cantidad_actual']."<br>&nbsp;Diferencia:".$diff."<br><br>";
        }

        // B.B.1) gestionar reserva
        if ($ln['reservado_previo'] > 0) {
          if ($modoDebug) {
            echo "-La reserva en la revision anterior es mayor a 0 (".$ln['reservado_previo'].")<br><br>";
          }
          if ($cantidadActualEsMenorQueCantidadPrevio and $ln['cantidad_actual'] < $ln['reservado_previo']) {
            // B.B.1.2) nueva cantidad < reserva → ajustar y heredar

            if ($modoDebug) {
              echo "-La nueva cantidad (".$ln['cantidad_actual'].") es inferior a lo reservado en la revision anterior (".$ln['reservado_previo']."), pasamos la reserva a la nueva revision ajustando la cantidad y notificamos!<br><br>";
            }

            $ok=pasarReservaDeRevisionAnterior($pdo,$id_revision_anterior=$ln['id_previo'],$id_revision_actual=$ln['id_actual'],$nueva_cantidad=$ln['cantidad_actual'],$modoDebug);

          } else {
            // B.B.1.1) nueva cantidad > reserva → no tocar reserva, notificar
            if ($modoDebug) {
              echo "-La nueva cantidad (".$ln['cantidad_actual'].") es mayor o igual a la cantidad reservada en la revision anterior (".$ln['reservado_previo']."), pasamos la reserva a la nueva revision y notificamos!<br><br>";
            }

            $ok=pasarReservaDeRevisionAnterior($pdo,$id_revision_anterior=$ln['id_previo'],$id_revision_actual=$ln['id_actual'],$nueva_cantidad=$ln['reservado_previo'],$modoDebug);

          }
        }else{
          if ($modoDebug) {
            echo "-No tenia reserva (".$ln['reservado_previo'].")<br><br>";
          }
        }

        // B.B.2 y B.B.3) gestionar pedidos

        $pd=fetchPedidoDetalle($pdo,$id_computo_detalle=$ln['id_previo'],$modoDebug);

        if ($modoDebug) {
          //echo "B.B) SQL pedidos_detalle:<br>" . debugQuery($pdo, $sql, $params) . "<br>";
          var_dump($pd);
        }

        if ($pd) {
          if ($pd['comprado'] == 0) {

            if ($modoDebug) {
              echo "-Aun no se está comprando<br><br>";
            }

            if ($cantidadActualEsMenorQueCantidadPrevio) {
              // B.B.2.2) nueva cantidad < anterior → calcular cantidad a pedir
              $cantPedir = $ln['cantidad_actual'] - max(0, $ln['reservado_previo']);

              if ($modoDebug) {
                echo "-La nueva cantidad a pedir es $cantPedir<br><br>";
              }
              if ($cantPedir <= 0) {
                // B.B.2.2.1) cancelar pedido_detalle

                $ok=cancelPedidoDetalle($pdo,$id_pedido_detalle=$pd['id'],$modoDebug);

                if ($modoDebug) {
                  echo "-Cancelamos el pedido_detalle:<br>". debugQuery($pdo, $sql, $params) . "<br>";
                  var_dump($ok);
                }
              } else {
                // B.B.2.2.2) actualizar pedido y reasignar
                $sql = "UPDATE pedidos_detalle SET cantidad = ?, id_computo_detalle = ? WHERE id = ?";
                $params = [$cantPedir, $ln['id_actual'], $pd['id']];
                $ok=$pdo->prepare($sql)->execute($params);
                if ($modoDebug) {
                  echo "-Cambiamos la cantidad del pedido_detalle y lo asignamos al computo_detalle de la nueva revision:<br>". debugQuery($pdo, $sql, $params) . "<br>";
                  var_dump($ok);
                }
              }
            } else {
              // B.B.2.1) nueva cantidad > anterior → solo notificar
              if ($modoDebug) {
                echo "-La nueva cantidad (".$ln["cantidad_actual"].") es mayor o igual a la del pedido ".$pd['id']." (".$pd["pedido_cantidad"]."), notificamos!<br><br>";
              }
            }
          } else {
            // B.B.3) pedido en compra → solo notificar
            if ($modoDebug) {
              echo "-El pedido está en proceso, ya se está comprando, solo notificamos!<br><br>";
            }
          }
        }else{
          if ($modoDebug) {
            echo "-No tenia pedido (".$ln['id_previo'].")<br><br>";
          }
        }
      }
    }

    $texto = ". La Revisión anterior N° {$revisionAnterior} se estaba gestionando, por lo que fue superada luego de revisar sus conceptos.";
  }

  // 3) Finalmente, marcar revisión anterior como “Superado”
  $sql = "UPDATE computos SET id_estado = 7 WHERE id = ?";
  $params = [$prev['id']];
  $ok=$pdo->prepare($sql)->execute($params);
  if ($modoDebug) {
    echo "-Superamos la revision anterior:<br>". debugQuery($pdo, $sql, $params) . "<br>";
    var_dump($ok);
    
  }

  return $texto;
}

/**
 * Comprueba y supera la revisión anterior de un cómputo.
 *
 * @param PDO   $pdo            Conexión PDO ya en transacción.
 * @param int   $idComputo      ID del cómputo que acabas de aprobar por completo.
 * @param int   $modoDebug      1 para volcar consultas, 0 para silencioso.
 * @return int entero que indicar 1 si todo salio bien o 0 si hubo algun error.
 */

function marcarComputoGestionandoOTerminado(PDO $pdo, int $idComputo, int $modoDebug = 0): int {
  //calculamos la suma de los saldos para ver si marcamos el computo como terminado o gestionando
  $sql = "SELECT SUM(saldo) AS suma_saldos FROM computos_detalle WHERE aprobado = 1 AND id_computo = $idComputo";
  if ($modoDebug == 1) {
    echo $sql."<br>";
  }
  $q = $pdo->prepare($sql);
  $q->execute();
  $data = $q->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug == 1) {
    var_dump($data);
  }

  $id_estado=4;//gestionando
  $textoLog="Marcamos el computo como gestionando porque aún quedan saldos pendientes.";
  if ($data['suma_saldos'] == 0) {
    $id_estado=5;//terminado
    $textoLog="Marcamos el computo como terminado porque no quedan saldos pendientes.";
  }

  // 3) Actualizar estado de cómputo a “4” (reservado/pedido completo)
  $sqlUpdComp = "UPDATE computos SET id_estado = $id_estado WHERE id = ?";
  $stmtComp = $pdo->prepare($sqlUpdComp);

  $params = [$idComputo];

  if ($modoDebug == 1) {
    // Generar y mostrar la consulta “real”
    $fullSql = debugQuery($pdo, $sqlUpdComp, $params);
    echo $fullSql . "<br><br>";
  }

  $ok = 0;
  if ($stmtComp->execute($params)) {
    // 3) Insertar log
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, '$textoLog', 'Cómputos', 'verComputo.php?id={$idComputo}')";
    $q = $pdo->prepare($sql);

    $params= [$_SESSION['user']['id']];
    
    if($q->execute($params)){
      $ok=1;
    }

    if ($modoDebug == 1) {
      // Generar y mostrar la consulta “real”
      $fullSql = debugQuery($pdo, $sql, $params);
      echo $fullSql . "<br><br>";
    }
  }

  return $ok;

}