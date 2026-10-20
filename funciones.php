<?php
require_once("PHPMailer/class.phpmailer.php");
require_once("PHPMailer/class.smtp.php");

/**
 * Obtiene la configuraciÃ³n SMTP.
 * Si APP_ENV=development y existe un archivo config.dev.php en la raÃ­z,
 * se cargarÃ¡n los datos desde ese archivo. Caso contrario se consultarÃ¡n
 * desde la base de datos (tabla parametros id 1-5).
 *
 * @param PDO $pdo
 * @return array [host, usuario, clave, from, from_name]
 */
function getSmtpConfig(PDO $pdo): array {
  if (getenv('APP_ENV') === 'development') {
    $configFile = __DIR__ . '/config.dev.php';
    if (file_exists($configFile)) {
      $config = require $configFile;
      return [
        $config['host'] ?? '',
        $config['username'] ?? '',
        $config['password'] ?? '',
        $config['from'] ?? '',
        $config['from_name'] ?? '',
        $config['port'] ?? 587,
        $config['smtpSecure'] ?? 'tls'
      ];
    }
  }
  $stmt = $pdo->query("SELECT valor FROM parametros WHERE id BETWEEN 1 AND 5 ORDER BY id ASC");
  $valores = $stmt->fetchAll(PDO::FETCH_COLUMN);

  $stmtPort = $pdo->query("SELECT valor FROM parametros WHERE id = 6");
  $port = $stmtPort->fetchColumn();

  $stmtSecure = $pdo->query("SELECT valor FROM parametros WHERE id = 7");
  $secure = $stmtSecure->fetchColumn();

  return [
    $valores[0] ?? '',
    $valores[1] ?? '',
    $valores[2] ?? '',
    $valores[3] ?? '',
    $valores[4] ?? '',
    is_numeric($port) ? (int)$port : 587,
    !empty($secure) ? $secure : 'tls',
  ];
}

function debugQuery(PDO $pdo, string $sql, array $params): string {
  foreach ($params as $key => $value) {
    // PDO::quote() aÃ±ade comillas y escapa caracteres especiales
    $quoted = $pdo->quote($value);

    if (is_int($key)) {
      // marcador posicional Â«?Â»
      $sql = preg_replace('/\?/', $quoted, $sql, 1);
    } else {
      // marcador nombrado: quitamos un : adicional si lo trae la clave
      $name    = ltrim($key, ':');
      // \b asegura que no coincida dentro de palabras mÃ¡s largas
      $pattern = '/:' . preg_quote($name, '/') . '\b/';
      $sql     = preg_replace($pattern, $quoted, $sql);
    }
  }
  return $sql;
}

/**
 * Ejecuta una consulta (SELECT, UPDATE, etc.) y, si estÃ¡ en modo debug,
 * imprime el debugQuery y el resultado de la ejecuciÃ³n (para UPDATE/DELETE).
 *
 * @param PDO    $pdo
 * @param string $sql
 * @param array  $params
 * @param bool   $modoDebug
 * @param string $etiqueta  Texto descriptivo que se mostrarÃ¡ en debug
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
        echo "  â†’ Filas afectadas: " . $stmt->rowCount() . "<br><br>";
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
    $stmt = debugExecute($pdo, $sql, [$idComputoDetalle], $modoDebug, "Recuperar pedidos_detalle para cÃ³mputo_detalle $idComputoDetalle");
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
    $sql = "UPDATE pedidos_detalle SET cancelado = 1 WHERE id = ?";
    $stmt=debugExecute($pdo, $sql, [$idPedidoDetalle], $modoDebug, "Cancelar pedido_detalle $idPedidoDetalle");
    $cancelado = $stmt->rowCount() == 1;
    
    // Actualizar estado del pedido_detalle despuÃ©s de cancelar
    if ($cancelado) {
        actualizarEstadoPedidoDetalle($pdo, $idPedidoDetalle);
    }
    
    return $cancelado; // Retorna true si se cancelÃ³ correctamente
}

/**
 * Pasa la reserva de un cÃ³mputo_detalle de la revision anterior a la nueva, ajustando cantidades si es necesario.
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
  $stmt=debugExecute($pdo, $sql, [$nueva_cantidad,$nueva_cantidad, $id_revision_actual], $modoDebug, "Pasamos la reserva a la nueva revisiÃ³n");

  $sql = "UPDATE computos_detalle SET reservado = 0 WHERE id = ?";
  $stmt2=debugExecute($pdo, $sql, [$id_revision_anterior], $modoDebug, "Dejamos en 0 la reserva de la revision anterior");

  return $stmt->rowCount() == 1 && $stmt2->rowCount() == 1; // Retorna true si se actualizÃ³ correctamente
}

/**
 * Reasigna o ajusta un pedido_detalle a otro cÃ³mputo_detalle.
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
 * Comprueba y supera la revisiÃ³n anterior de un cÃ³mputo.
 *
 * @param PDO   $pdo            ConexiÃ³n PDO ya en transacciÃ³n.
 * @param int   $idComputo      ID del cÃ³mputo que acabas de aprobar por completo.
 * @param int   $modoDebug      1 para volcar consultas, 0 para silencioso.
 * @return string Texto que detalle lo sucedido (vacÃ­o si no habÃ­a revisiÃ³n anterior).
 */

function superarRevisionAnterior(PDO $pdo, int $idComputo, int $modoDebug): string {
  $texto = "";

  // 1) Recuperar datos del cÃ³mputo actual
  $sql = "SELECT c.nro_revision, c.nro AS nro_computo, c.id_tarea FROM computos c WHERE c.id = ?";
  //$stmt = $pdo->prepare($sql);
  $params = [$idComputo];
  //$stmt->execute($params);
  $stmt=debugExecute($pdo,$sql,$params,$modoDebug,"Recuperar datos del cÃ³mputo actual ($idComputo)");

  $info = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug) {
    //echo "-Recuperar datos del cÃ³mputo actual ($idComputo):<br>" . debugQuery($pdo, $sql, $params) . "<br>";
    var_dump($info);
  }

  if (!$info || $info['nro_revision'] < 1) {
    // No hay revisiÃ³n anterior

    //notificar que se ha aprobado un computo
    $sql = "SELECT s.nro_sitio AS sitio, s.nro_subsitio AS subsitio, p.nro AS nro_proyecto, p.nombre AS proyecto, c.nro AS nro_computo, nro_revision FROM computos c LEFT JOIN tareas t ON c.id_tarea=t.id LEFT JOIN tipos_tarea tt on tt.id = t.id_tipo_tarea LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante LEFT JOIN estados_computos ec ON ec.id = c.id_estado INNER JOIN proyectos p on p.id = t.id_proyecto INNER JOIN sitios s on s.id = p.id_sitio WHERE c.id = ? ";
    $q = $pdo->prepare($sql);
    $q->execute([$idComputo]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    //$descProyecto = " NÂ° ".$data["nro_computo"]." Rev. NÂ° ".$data["nro_revision"]." (".$data["sitio"]."_".$data["subsitio"]."_".$data["nro_proyecto"].")";
    $descProyecto = " NÂ° ".$data["nro_computo"]." (".$data["sitio"]."_".$data["subsitio"]."_".$data["nro_proyecto"].")";

    $idTipoNotificacion=15;
    $idEntidad=$idComputo;
    $detalleNotificacion="ID Computo: #".$idComputo;
    $asuntoEmail="ProducciÃ³n - AprobaciÃ³n de CÃ³mputo ({$descProyecto})";
    $cuerpoEmail="El cÃ³mputo #{$descProyecto} ha sido aprobado.";
    crearNotificacion($pdo,$idTipoNotificacion,$idEntidad,$detalleNotificacion,$asuntoEmail,$cuerpoEmail);

    return $texto;
  }

  $revisionAnterior = $info['nro_revision'] - 1;

  // 2) Obtener estado de la revisiÃ³n anterior
  $sql = "SELECT c.id, c.id_estado, ec.estado FROM computos c JOIN estados_computos ec ON c.id_estado=ec.id WHERE c.id_tarea = ? AND c.nro = ? AND c.nro_revision = ?";
  $stmt = $pdo->prepare($sql);
  $params = [$info['id_tarea'], $info['nro_computo'], $revisionAnterior];
  $stmt->execute($params);
  $prev = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug) {
    echo "-Recuperar datos de la revisiÃ³n anterior ($revisionAnterior):<br>" . debugQuery($pdo, $sql, $params) . "<br>";
    var_dump($prev);
  }

  if (!$prev) {
    // No existe esa revisiÃ³n
    if ($modoDebug) {
      echo "-No existe revisiÃ³n anterior NÂ° {$revisionAnterior} para el cÃ³mputo NÂ° {$info['nro_computo']}<br>";
    }
    return $texto;
  }

  $detalleRealizadoCuerpoEmail="";
  if ($prev['id_estado'] != 4) {
    // ============================================================
    // A) Si la revisiÃ³n anterior NO estaba en â€œGestionandoâ€ (4)
    //    â†’ se pasa directamente a â€œSuperadoâ€ sin mÃ¡s lÃ³gica B
    // ============================================================

    if ($modoDebug) {
      echo "-La revision anterior del computo estÃ¡ en un estado diferente a 4 (Gestionando), por lo que se pasa directamente a Superado sin mayor lÃ³gica<br><br>";
    }
    
    $texto = "La revisiÃ³n anterior NÂ° {$revisionAnterior} no se esta Gestionando, por lo que ha sido superada sin mas.";
    $detalleRealizadoCuerpoEmail="<br>".$texto;
    $texto = ". ".$texto;

  }else{
    $detalleRealizadoCuerpoEmail="<br>La revisiÃ³n anterior NÂ° {$revisionAnterior} se estaba gestionando, por lo que fue superada luego de revisar sus conceptos.<br>";

    if ($modoDebug) {
      echo "-La revision anterior del computo estÃ¡ en estado Gestionando, por lo que debemos comaprar linea por linea los conceptos de ambas revisiones y actuar segun cada caso<br><br>";
    }
    // ============================================================
    // B) RevisiÃ³n anterior en â€œGestionandoâ€ (4)
    //    â†’ comparaciones lÃ­nea a lÃ­nea segÃºn B.A y B.B
    // ============================================================

    // B) 2) Traer lÃ­neas NO canceladas de ambas revisiones
    $sql = "SELECT
              cd_previo.id        AS id_previo,
              cd_previo.id_material,
              m.concepto,
              cd_previo.cantidad  AS cantidad_previo,
              cd_previo.reservado AS reservado_previo,
              cd_actual.id        AS id_actual,
              cd_actual.cantidad  AS cantidad_actual
            FROM computos_detalle cd_previo
            LEFT JOIN computos_detalle cd_actual
              ON cd_previo.id_material = cd_actual.id_material
             AND cd_actual.id_computo = :actual
             AND cd_actual.cancelado = 0
            LEFT JOIN materiales m ON cd_previo.id_material=m.id
            WHERE cd_previo.id_computo = :previo
              AND cd_previo.cancelado = 0";

    $stmt = $pdo->prepare($sql);
    $params = [':previo' => $prev['id'], ':actual' => $idComputo];
    $stmt->execute($params);
    $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($modoDebug) {
      echo "-Comparamos las lÃ­neas de cada revisiÃ³n:<br>" . debugQuery($pdo, $sql, $params) . "<br>";
      var_dump($lineas);
    }

    foreach ($lineas as $ln) {
      // -----------------------------------
      // B.A) Concepto Eliminado
      // -----------------------------------
      if (is_null($ln['id_actual'])) {
        $detalleRealizadoCuerpoEmail.="-Se ha eliminado el concepto ".$ln['concepto'];
        if ($modoDebug) {
          echo "-Se ha eliminado el concepto con ID ".$ln['id_previo']."<br><br>";
        }
        // B.A.1) si tiene reserva â†’ la borramos
        if ($ln['reservado_previo'] > 0) {
          $detalleRealizadoCuerpoEmail.=", como tenÃ­a una reserva previa de ".$ln['reservado_previo']." unidades, la hemos eliminado.<br>";
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
          $detalleRealizadoCuerpoEmail.=" que no tenÃ­a reserva previa.<br>";
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
          $detalleRealizadoCuerpoEmail.=" Tiene un pedido asociado (id:".$pd["id"].", cantidad:".$pd["pedido_cantidad"].")";
          if ($modoDebug) {
            echo "-Tiene pedidos asociados (id:".$pd["id"].", cantidad:".$pd["pedido_cantidad"]."):<br><br>";
            //var_dump($pd);
          }
          if ($pd['comprado'] == 0) {
            $detalleRealizadoCuerpoEmail.=" y aun no se estÃ¡ comprando por lo que lo cancelamos.<br>";
            if ($modoDebug) {
              echo "-Aun no se estÃ¡ comprando (".$pd['comprado'].")<br><br>";
            }
            // B.A.2) pedido no comprando â†’ cancelar parcialmente

            $ok=cancelPedidoDetalle($pdo,$id_pedido_detalle=$pd['id'],$modoDebug);

            if ($modoDebug) {
              //echo "B.A.2) cancelar pedido_detalle:<br>". debugQuery($pdo, $sql, $params) . "<br>";
              var_dump($ok);
            }
          } else {
            $detalleRealizadoCuerpoEmail.=" que estÃ¡ en proceso de compra (".$pd['comprado']."), por lo que no se pudo cancelar.<br>";
            // B.A.3) pedido en compra â†’ solo notificar
            if ($modoDebug) {
              echo "-El pedido ya estÃ¡ en compra, solo notificar<br><br>";
            }
          }
        }else{
          $detalleRealizadoCuerpoEmail.="No tenÃ­a pedidos asociados.<br>";
          if ($modoDebug) {
            echo "-No tenia pedido (".$ln['id_previo'].")<br><br>";
          }
        }

        continue; // pasamos a la siguiente lÃ­nea
      }

      // -----------------------------------
      // B.B) Cantidad Cambiada
      // -----------------------------------
      if ($ln['cantidad_previo'] != $ln['cantidad_actual']) {
        
        $diff = $ln['cantidad_actual'] - $ln['cantidad_previo'];
        $cantidadActualEsMenorQueCantidadPrevio = $ln['cantidad_actual'] < $ln['cantidad_previo'];

        $detalleRealizadoCuerpoEmail.= "-Se ha modificado la cantidad del concepto ".$ln['concepto']." de ".$ln['cantidad_previo']." a ".$ln['cantidad_actual'];
        
        if ($modoDebug) {
          echo "-Hubo un cambio en la cantidad:<br>&nbsp;Revision anterior: ".$ln['cantidad_previo']."<br>&nbsp;Revision actual: ".$ln['cantidad_actual']."<br>&nbsp;Diferencia:".$diff."<br><br>";
        }

        // B.B.1) gestionar reserva
        if ($ln['reservado_previo'] > 0) {
          if ($modoDebug) {
            echo "-La reserva en la revision anterior es mayor a 0 (".$ln['reservado_previo'].")<br><br>";
          }
          if ($cantidadActualEsMenorQueCantidadPrevio and $ln['cantidad_actual'] < $ln['reservado_previo']) {
            // B.B.1.2) nueva cantidad < reserva â†’ ajustar y heredar

            $detalleRealizadoCuerpoEmail.= ", como tenÃ­a una reserva previa de ".$ln['reservado_previo'].", la hemos ajustado a la nueva cantidad de ".$ln['cantidad_actual']."<br>";

            if ($modoDebug) {
              echo "-La nueva cantidad (".$ln['cantidad_actual'].") es inferior a lo reservado en la revision anterior (".$ln['reservado_previo']."), pasamos la reserva a la nueva revision ajustando la cantidad y notificamos!<br><br>";
            }

            $ok=pasarReservaDeRevisionAnterior($pdo,$id_revision_anterior=$ln['id_previo'],$id_revision_actual=$ln['id_actual'],$nueva_cantidad=$ln['cantidad_actual'],$modoDebug);

          } else {

            $detalleRealizadoCuerpoEmail.= ", como tenÃ­a una reserva previa de ".$ln['reservado_previo'].", la pasamos a esta revision<br>";

            // B.B.1.1) nueva cantidad > reserva â†’ no tocar reserva, notificar
            if ($modoDebug) {
              echo "-La nueva cantidad (".$ln['cantidad_actual'].") es mayor o igual a la cantidad reservada en la revision anterior (".$ln['reservado_previo']."), pasamos la reserva a la nueva revision y notificamos!<br><br>";
            }

            $ok=pasarReservaDeRevisionAnterior($pdo,$id_revision_anterior=$ln['id_previo'],$id_revision_actual=$ln['id_actual'],$nueva_cantidad=$ln['reservado_previo'],$modoDebug);

          }
        }else{
          $detalleRealizadoCuerpoEmail.= " que no tenÃ­a reserva previa, por lo que no se ha ajustado nada.<br>";
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
          $detalleRealizadoCuerpoEmail.= " Tiene un pedido asociado (id:".$pd["id"].", cantidad:".$pd["pedido_cantidad"].")";

          if ($pd['comprado'] == 0) {

            $detalleRealizadoCuerpoEmail.= " que aun no se estÃ¡ comprando";

            if ($modoDebug) {
              echo "-Aun no se estÃ¡ comprando<br><br>";
            }

            if ($cantidadActualEsMenorQueCantidadPrevio) {
              // B.B.2.2) nueva cantidad < anterior â†’ calcular cantidad a pedir
              $cantPedir = $ln['cantidad_actual'] - max(0, $ln['reservado_previo']);

              if ($modoDebug) {
                echo "-La nueva cantidad a pedir es $cantPedir<br><br>";
              }
              if ($cantPedir <= 0) {
                // B.B.2.2.1) cancelar pedido_detalle
                $detalleRealizadoCuerpoEmail.= " y como ya no es necesario pedir, cancelamos el pedido.<br>";

                $ok=cancelPedidoDetalle($pdo,$id_pedido_detalle=$pd['id'],$modoDebug);

                if ($modoDebug) {
                  //echo "-Cancelamos el pedido_detalle:<br>". debugQuery($pdo, $sql, $params) . "<br>";
                  var_dump($ok);
                }
              } else {
                $detalleRealizadoCuerpoEmail.= " y como la nueva cantidad a pedir (".$cantPedir.") es mayor a 0 y menor a la cantidad anterior, lo actualizamos y reasignamos al computo_detalle de la nueva revision.<br>";

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
              $detalleRealizadoCuerpoEmail.= " y como la nueva cantidad es mayor o igual a la del pedido ".$pd['id']." (".$pd["pedido_cantidad"].") no hemos realizado nada.<br>";

              // B.B.2.1) nueva cantidad > anterior â†’ solo notificar
              if ($modoDebug) {
                echo "-La nueva cantidad (".$ln["cantidad_actual"].") es mayor o igual a la del pedido ".$pd['id']." (".$pd["pedido_cantidad"]."), notificamos!<br><br>";
              }
            }
          } else {
            $detalleRealizadoCuerpoEmail.= " que estÃ¡ en proceso de compra (".$pd['comprado']."), por lo que no se pudo realizar nada.";
            // B.B.3) pedido en compra â†’ solo notificar
            if ($modoDebug) {
              echo "-El pedido estÃ¡ en proceso, ya se estÃ¡ comprando, solo notificamos!<br><br>";
            }
          }
        }else{
          $detalleRealizadoCuerpoEmail.= "No tenÃ­a pedidos asociados.<br>";
          if ($modoDebug) {
            echo "-No tenia pedido (".$ln['id_previo'].")<br><br>";
          }
        }
      }
    }

    $texto = ". La RevisiÃ³n anterior NÂ° {$revisionAnterior} se estaba gestionando, por lo que fue superada luego de revisar sus conceptos.";
  }

  // 3) Finalmente, marcar revisiÃ³n anterior como â€œSuperadoâ€
  $sql = "UPDATE computos SET id_estado = 7 WHERE id = ?";
  $params = [$prev['id']];
  $ok=$pdo->prepare($sql)->execute($params);
  if ($modoDebug) {
    echo "-Superamos la revision anterior:<br>". debugQuery($pdo, $sql, $params) . "<br>";
    var_dump($ok);
    
  }

  //notificar que se ha aprobado una revision de un computo
  $sql = "SELECT s.nro_sitio AS sitio, s.nro_subsitio AS subsitio, p.nro AS nro_proyecto, p.nombre AS proyecto, c.nro AS nro_computo, nro_revision FROM computos c LEFT JOIN tareas t ON c.id_tarea=t.id LEFT JOIN tipos_tarea tt on tt.id = t.id_tipo_tarea LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante LEFT JOIN estados_computos ec ON ec.id = c.id_estado INNER JOIN proyectos p on p.id = t.id_proyecto INNER JOIN sitios s on s.id = p.id_sitio WHERE c.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$idComputo]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $descProyecto = " NÂ° ".$data["nro_computo"]." Rev. NÂ° ".$data["nro_revision"]." (".$data["sitio"]."_".$data["subsitio"]."_".$data["nro_proyecto"].")";

  $idTipoNotificacion=16;
  $idEntidad=$idComputo;
  //$detalleNotificacion="verComputo.php?id=";
  $detalleNotificacion="ID Computo: #".$idComputo;
  $asuntoEmail="ProducciÃ³n - AprobaciÃ³n de revisiÃ³n de CÃ³mputo ".$descProyecto;
  $cuerpoEmail="El cÃ³mputo ".$descProyecto." ha sido aprobado.".$detalleRealizadoCuerpoEmail;
  crearNotificacion($pdo,$idTipoNotificacion,$idEntidad,$detalleNotificacion,$asuntoEmail,$cuerpoEmail);

  return $texto;
}

/**
 * Comprueba y supera la revisiÃ³n anterior de un cÃ³mputo.
 *
 * @param PDO   $pdo            ConexiÃ³n PDO ya en transacciÃ³n.
 * @param int   $idComputo      ID del cÃ³mputo que acabas de aprobar por completo.
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
  $textoLog="Marcamos el computo como gestionando porque aÃºn quedan saldos pendientes.";
  if ($data['suma_saldos'] == 0) {
    $id_estado=5;//terminado
    $textoLog="Marcamos el computo como terminado porque no quedan saldos pendientes.";
  }

  // 3) Actualizar estado de cÃ³mputo a â€œ4â€ (reservado/pedido completo)
  $sqlUpdComp = "UPDATE computos SET id_estado = $id_estado WHERE id = ?";
  $stmtComp = $pdo->prepare($sqlUpdComp);

  $params = [$idComputo];

  if ($modoDebug == 1) {
    // Generar y mostrar la consulta â€œrealâ€
    $fullSql = debugQuery($pdo, $sqlUpdComp, $params);
    echo $fullSql . "<br><br>";
  }

  $ok = 0;
  if ($stmtComp->execute($params)) {
    // 3) Insertar log
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, '$textoLog', 'CÃ³mputos', 'verComputo.php?id={$idComputo}')";
    $q = $pdo->prepare($sql);

    $params= [$_SESSION['user']['id']];
    
    if($q->execute($params)){
      $ok=1;
    }

    if ($modoDebug == 1) {
      // Generar y mostrar la consulta â€œrealâ€
      $fullSql = debugQuery($pdo, $sql, $params);
      echo $fullSql . "<br><br>";
    }
  }

  return $ok;

}


function crearNotificacion(PDO $pdo, int $idTipoNotificacion, int $idEntidad, string $detalleNotificacion, string $asuntoEmail, string $cuerpoEmail): void {
  // Se asume que $pdo es una conexiÃ³n vÃ¡lida y con la configuraciÃ³n adecuada
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  $smtp = getSmtpConfig($pdo);
  list($smtpHost, $smtpUsuario, $smtpClave, $smtpFrom, $smtpFromName, $smtpPort, $smtpSecure) = $smtp;

  error_log("=== DEBUG crearNotificacion ===");
  error_log("Tipo notificaciÃ³n: $idTipoNotificacion");
  error_log("SMTP Host: $smtpHost");
  error_log("SMTP Port: $smtpPort");
  error_log("SMTP Secure: " . var_export($smtpSecure, true));
  error_log("SMTP Usuario: $smtpUsuario");

  $whereDebug = "";
  
  $sql = "SELECT t.id_usuario, u.email FROM usuarios_tipos_notificacion t JOIN usuarios u ON u.id = t.id_usuario WHERE t.id_tipo_notificacion = $idTipoNotificacion" . $whereDebug;
  
  $countRows = 0;
  
  foreach ($pdo->query($sql) as $row) {
    $countRows++;
    list($destUsuario, $destEmail) = $row;
    
    error_log("Destinatario #$countRows: usuario=$destUsuario, email=$destEmail");

    $stmt = $pdo->prepare("INSERT INTO notificaciones (id_tipo_notificacion, id_usuario, fecha_hora, leida, detalle, id_entidad) VALUES ($idTipoNotificacion, ?, NOW(), 0, ?, ?)");
    $detalle = $detalleNotificacion;
    $stmt->execute([$destUsuario, $detalle, $idEntidad]);

    $titulo  = "ERP Notificaciones - " . $asuntoEmail;
    $mensaje = $cuerpoEmail;

    $mail = new PHPMailer();
    $mail->SMTPDebug = 0;
    $mail->IsSMTP();
    $mail->Host       = $smtpHost;
    $mail->Username   = $smtpUsuario;
    $mail->Password   = $smtpClave;
    
    /*$mail->Port = 465;
    $mail->SMTPSecure = 'ssl';*/

    //$mail->SMTPAuth   = true;
    //EN LOCAL
    //$mail->Port = 587;
    //$mail->SMTPSecure = 'tls';

    //EN PRODUCCION
    /*$mail->Port = 25; 
    //$mail->SMTPSecure = 'ssl';
    //$mail->SMTPAutoTLS = false;
    $mail->SMTPSecure = false;*/
    $mail->Port       = $smtpPort;
    $mail->SMTPSecure = $smtpSecure;
    $mail->From       = $smtpFrom;
    $mail->FromName   = $_SESSION['user']['usuario'];
    $mail->CharSet    = "utf-8";
    $mail->IsHTML(true);
    $mail->clearAddresses();
    $mail->AddAddress($destEmail);
    $mail->Subject    = $titulo;
    $mail->Body       = nl2br($mensaje) . "<br><br>";
    $mail->AltBody    = $mensaje;
    
    $envio = $mail->Send();

    if (!$envio) {
        error_log("ERROR PHPMailer: " . $mail->ErrorInfo);
    } else {
        error_log("Email enviado OK a: $destEmail");
    }
  }
  
  error_log("Total destinatarios encontrados para tipo $idTipoNotificacion: $countRows");
  error_log("=== FIN DEBUG ===");
}

// Verifica si la funciÃ³n str_starts_with ya existe (PHP 8.0+)
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    return $needle !== '' && strpos($haystack, $needle) === 0;
  }
}

/**
 * Obtene una descripciÃ³n del proyecto asociado a un ID de proyecto.
 */
function getDescripcionProyecto($pdo, $id_proyecto) {
  $sql = "SELECT s.nro_sitio AS sitio, s.nro_subsitio AS subsitio, p.nro AS nro_proyecto FROM proyectos p INNER JOIN sitios s on s.id = p.id_sitio WHERE p.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id_proyecto]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  return " (".$data["sitio"]."_".$data["subsitio"]."_".$data["nro_proyecto"].")";
}

/**
 * Duplica conjuntos, posiciones y procesos de una revisiÃ³n de lista de corte en otra.
 */
function duplicarListaCorteRevision(PDO $pdo, int $idOrigen, int $idDestino, bool $modoDebug = false): void {
  $sqlCon = "SELECT id, nombre, cantidad, peso, id_estado_lista_corte_conjuntos FROM listas_corte_conjuntos WHERE id_lista_corte = ?";
  $stmtCon = $pdo->prepare($sqlCon);
  $stmtCon->execute([$idOrigen]);
  while ($conj = $stmtCon->fetch(PDO::FETCH_ASSOC)) {
    $sql = 'INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES(?,?,?,?,?)';
    $q = $pdo->prepare($sql);
    $params = [$idDestino, $conj['nombre'], $conj['cantidad'], $conj['peso'], $conj['id_estado_lista_corte_conjuntos']];
    if ($modoDebug) {
      echo debugQuery($pdo, $sql, $params) . "<br>";
    }
    $q->execute($params);
    if ($modoDebug) {
      echo "Afe: " . $q->rowCount() . "<br><br>";
    }
    $idConjNuevo = (int)$pdo->lastInsertId();

    $sql = 'SELECT id, id_material, posicion, cantidad, largo, ancho, marca, peso, peso_calculado_posicion, finalizado, id_colada, diametro, calidad FROM lista_corte_posiciones WHERE id_lista_corte_conjunto = ?';
    $stmtPos = $pdo->prepare($sql);
    $params = [$conj['id']];
    if ($modoDebug) {
      echo debugQuery($pdo, $sql, $params) . "<br>";
    }
    $stmtPos->execute($params);
    if ($modoDebug) {
      echo "Afe: " . $stmtPos->rowCount() . "<br><br>";
    }
    while ($pos = $stmtPos->fetch(PDO::FETCH_ASSOC)) {
      $sql = 'INSERT INTO lista_corte_posiciones (id_lista_corte_conjunto, id_material, posicion, cantidad, largo, ancho, marca, peso, peso_calculado_posicion, finalizado, id_colada, diametro, calidad) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
      $q = $pdo->prepare($sql);
      $params = [
        $idConjNuevo,
        $pos['id_material'],
        $pos['posicion'],
        $pos['cantidad'],
        $pos['largo'],
        $pos['ancho'],
        $pos['marca'],
        $pos['peso'],
        $pos['peso_calculado_posicion'],
        $pos['finalizado'],
        $pos['id_colada'],
        $pos['diametro'],
        $pos['calidad']
      ];
      if ($modoDebug) {
        echo debugQuery($pdo, $sql, $params) . "<br>";
      }
      $q->execute($params);
      if ($modoDebug) {
        echo "Afe: " . $q->rowCount() . "<br><br>";
      }
      $idPosNuevo = (int)$pdo->lastInsertId();

      $sql = 'SELECT id_tipo_proceso, observaciones, id_estado_lista_corte_proceso FROM lista_corte_procesos WHERE id_lista_corte_posicion = ?';
      $stmtProc = $pdo->prepare($sql);
      $params = [$pos['id']];
      if ($modoDebug) {
        echo debugQuery($pdo, $sql, $params) . "<br>";
      }
      $stmtProc->execute($params);
      if ($modoDebug) {
        echo "Afe: " . $stmtProc->rowCount() . "<br><br>";
      }
      while ($proc = $stmtProc->fetch(PDO::FETCH_ASSOC)) {
        $sql = 'INSERT INTO lista_corte_procesos (id_lista_corte_posicion, id_tipo_proceso, observaciones, id_estado_lista_corte_proceso) VALUES (?,?,?,?)';
        $q = $pdo->prepare($sql);
        $params = [
          $idPosNuevo,
          $proc['id_tipo_proceso'],
          $proc['observaciones'],
          $proc['id_estado_lista_corte_proceso']
        ];
        if ($modoDebug) {
          echo debugQuery($pdo, $sql, $params) . "<br>";
        }
        $q->execute($params);
        if ($modoDebug) {
          echo "Afe: " . $q->rowCount() . "<br><br>";
        }
      }
    }
  }
}

/**
 * Calcula el estado de un Ã­tem de pedido (pedidos_detalle) sin actualizar la BD.
 * 
 * @param PDO $db ConexiÃ³n a la base de datos
 * @param int $idPedidoDetalle ID del registro en pedidos_detalle
 * @return int|null ID del estado correspondiente en estados_pedidos_detalle o null si hay error
 */
function calcularEstadoPedidoDetalle(PDO $db, int $idPedidoDetalle): ?int {
  try {
    // 1) Leer pedidos_detalle por id
    $sqlPedido = "SELECT cantidad, cancelado, id_pedido, id_material FROM pedidos_detalle WHERE id = ?";
    $stmtPedido = $db->prepare($sqlPedido);
    $stmtPedido->execute([$idPedidoDetalle]);
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
      return null; // No existe el registro
    }
    
    // 2) Si cancelado = 1, devolver el id del estado "Cancelado"
    if ($pedido['cancelado'] == 1) {
      return 8; // ID del estado "Cancelado"
    }
    
    $cantidadPedida = (float)$pedido['cantidad'];
    $idPedido = (int)$pedido['id_pedido'];
    $idMaterial = (int)$pedido['id_material'];
    
    // Si cantidadPedida <= 0, devolver "Pendiente" como fallback seguro
    if ($cantidadPedida <= 0) {
      return 1; // ID del estado "Pendiente"
    }
    
    // 3) Calcular cantidadComprada (OC enviadas y superiores: estados 3, 4, 5, 6, 7, 8)
    $sqlComprada = "SELECT COALESCE(SUM(cd.cantidad), 0) as cantidad_comprada FROM compras_detalle cd INNER JOIN compras c ON c.id = cd.id_compra WHERE c.id_pedido = ? AND cd.id_material = ? AND c.id_estado_compra >= 3";
    $stmtComprada = $db->prepare($sqlComprada);
    $stmtComprada->execute([$idPedido, $idMaterial]);
    $cantidadComprada = (float)$stmtComprada->fetchColumn();
    
    // 4) Calcular cantidadEntregada (sin filtrar por estado de compra)
    $sqlEntregada = "SELECT COALESCE(SUM(cd.entregado), 0) as cantidad_entregada FROM compras_detalle cd INNER JOIN compras c ON c.id = cd.id_compra WHERE c.id_pedido = ? AND cd.id_material = ?";
    $stmtEntregada = $db->prepare($sqlEntregada);
    $stmtEntregada->execute([$idPedido, $idMaterial]);
    $cantidadEntregada = (float)$stmtEntregada->fetchColumn();
    
    // 5) Verificar si tiene OC en elaboraciÃ³n (estados 1 = ElaboraciÃ³n, 2 = Para aprobar)
    $sqlElaboracion = "SELECT COUNT(*) as tiene_elaboracion FROM compras_detalle cd INNER JOIN compras c ON c.id = cd.id_compra WHERE c.id_pedido = ? AND cd.id_material = ? AND c.id_estado_compra IN (1, 2)";
    $stmtElaboracion = $db->prepare($sqlElaboracion);
    $stmtElaboracion->execute([$idPedido, $idMaterial]);
    $tieneOcEnElaboracion = (int)$stmtElaboracion->fetchColumn() > 0;
    
    //echo "Cantidad Pedida: $cantidadPedida, Comprada: $cantidadComprada, Entregada: $cantidadEntregada, Tiene OC en elaboraciÃ³n: " . ($tieneOcEnElaboracion ? 'SÃ­' : 'No') . "\n";
    // 6) Aplicar lÃ³gica de estados
    
    // Si cantidadEntregada >= cantidadPedida â†’ Entregado
    if ($cantidadEntregada >= $cantidadPedida) {
      return 7; // ID del estado "Entregado"
    }
    
    // Si cantidadComprada = 0 y cantidadEntregada = 0
    if ($cantidadComprada == 0 && $cantidadEntregada == 0) {
      if ($tieneOcEnElaboracion) {
        return 2; // ID del estado "Comprando"
      } else {
        return 1; // ID del estado "Pendiente"
      }
    }
    
    // Si cantidadComprada > 0 y cantidadComprada < cantidadPedida y cantidadEntregada = 0
    if ($cantidadComprada > 0 && $cantidadComprada < $cantidadPedida && $cantidadEntregada == 0) {
      return 3; // ID del estado "Comprando Parcial"
    }
    
    // Si cantidadComprada < cantidadPedida y cantidadEntregada > 0
    if ($cantidadComprada < $cantidadPedida && $cantidadEntregada > 0) {
      return 4; // ID del estado "Comprando y Entregando"
    }
    
    // Si cantidadComprada >= cantidadPedida y cantidadEntregada = 0
    if ($cantidadComprada >= $cantidadPedida && $cantidadEntregada == 0) {
      return 5; // ID del estado "Comprando Total"
    }
    
    // Si cantidadComprada >= cantidadPedida y cantidadEntregada > 0 y cantidadEntregada < cantidadPedida
    if ($cantidadComprada >= $cantidadPedida && $cantidadEntregada > 0 && $cantidadEntregada < $cantidadPedida) {
      return 6; // ID del estado "Entregado Parcial"
    }
    
    // Fallback por defecto (no deberÃ­a llegar aquÃ­ en condiciones normales)
    return 1; // ID del estado "Pendiente"
    
  } catch (Exception $e) {
    // En caso de error, devolver null
    error_log("Error en calcularEstadoPedidoDetalle: " . $e->getMessage());
    return null;
  }
}

/**
 * Actualiza el campo id_estado en pedidos_detalle usando calcularEstadoPedidoDetalle.
 * 
 * @param PDO $db ConexiÃ³n a la base de datos
 * @param int $idPedidoDetalle ID del registro en pedidos_detalle a actualizar
 * @return void
 */
function actualizarEstadoPedidoDetalle(PDO $db, int $idPedidoDetalle): void {
  try {
    // 1) Llamar a calcularEstadoPedidoDetalle para obtener el nuevo estado
    $nuevoEstadoId = calcularEstadoPedidoDetalle($db, $idPedidoDetalle);
    
    // 2) Si el resultado es null, no hacer nada y loguear
    if ($nuevoEstadoId === null) {
      error_log("No se pudo calcular el estado para pedidos_detalle ID: $idPedidoDetalle");
      return;
    }
    
    // 3) Actualizar el campo id_estado en la base de datos
    $sql = "UPDATE pedidos_detalle SET id_estado = :id_estado WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([
      ':id_estado' => $nuevoEstadoId,
      ':id' => $idPedidoDetalle
    ]);
    
  } catch (Exception $e) {
    // En caso de error, loguear el problema
    error_log("Error en actualizarEstadoPedidoDetalle: " . $e->getMessage());
  }
}

/**
 * Verifica si un pedido debe cambiar al estado "Terminado" (5) 
 * cuando todas sus OC estÃ¡n terminadas y todos los items estÃ¡n comprados.
 * 
 * @param PDO $pdo ConexiÃ³n a la base de datos
 * @param int $idPedido ID del pedido a verificar
 * @param bool $modoDebug Si estÃ¡ en modo debug para mostrar informaciÃ³n
 * @return bool True si el pedido cambiÃ³ de estado, false si no
 */
function verificarYActualizarEstadoPedido(PDO $pdo, int $idPedido, bool $modoDebug = false): bool {
  try {
    if ($modoDebug) {
      echo "<h3>ðŸ” Verificando estado del pedido ID: $idPedido</h3>";
    }
    
    // Verificar si TODAS las OC del pedido estÃ¡n terminadas (estado 7 u 8)
    $sqlVerificarPedido = "SELECT count(*) cant_pendientes FROM compras WHERE id_pedido = ? AND id_estado_compra NOT IN (7, 8)";
    $qVerificarPedido = $pdo->prepare($sqlVerificarPedido);
    $qVerificarPedido->execute([$idPedido]);
    $dataPedido = $qVerificarPedido->fetch(PDO::FETCH_ASSOC);
    
    if ($modoDebug) {
      echo "<b>OC pendientes (no en estado 7 u 8):</b> " . $dataPedido['cant_pendientes'] . "<br>";
      
      // Mostrar todas las OC del pedido
      $sqlDebugOC = "SELECT c.id, c.id_estado_compra, ec.estado FROM compras c LEFT JOIN estados_compra ec ON ec.id = c.id_estado_compra WHERE id_pedido = ?";
      $qDebugOC = $pdo->prepare($sqlDebugOC);
      $qDebugOC->execute([$idPedido]);
      echo "<b>ðŸ” TODAS LAS OC DEL PEDIDO:</b><br>";
      echo "<table border='1'><tr><th>ID OC</th><th>Estado ID</th><th>Estado</th></tr>";
      while ($ocDebug = $qDebugOC->fetch(PDO::FETCH_ASSOC)) {
        $color = in_array($ocDebug['id_estado_compra'], [7, 8]) ? '#ccffcc' : '#ffcccc';
        echo "<tr style='background-color: $color'>";
        echo "<td>" . $ocDebug['id'] . "</td>";
        echo "<td>" . $ocDebug['id_estado_compra'] . "</td>";
        echo "<td>" . $ocDebug['estado'] . "</td>";
        echo "</tr>";
      }
      echo "</table><br>";
    }
    
    // Verificar que no se puedan crear mÃ¡s OC (calculando directamente desde compras_detalle)
    $sqlVerificarComprado = "SELECT COUNT(*) as cant_sin_comprar FROM pedidos_detalle pd 
      INNER JOIN materiales m ON m.id = pd.id_material
    WHERE pd.id_pedido = ? 
      AND pd.cancelado = 0 
      AND pd.cantidad > COALESCE((
        SELECT SUM(cd.cantidad) 
        FROM compras_detalle cd 
        INNER JOIN compras c ON c.id = cd.id_compra 
        WHERE c.id_pedido = pd.id_pedido 
        AND cd.id_material = pd.id_material 
        AND c.id_estado_compra >= 3
      ), 0)";
    $qVerificarComprado = $pdo->prepare($sqlVerificarComprado);
    $qVerificarComprado->execute([$idPedido]);
    $dataComprado = $qVerificarComprado->fetch(PDO::FETCH_ASSOC);
    
    if ($modoDebug) {
      echo "<b>Items sin comprar (cantidad > comprado real desde OC y no cancelados):</b> " . $dataComprado['cant_sin_comprar'] . "<br>";
      
      // Mostrar detalle de items sin comprar con cÃ¡lculo real
      if ($dataComprado['cant_sin_comprar'] > 0) {
        $sqlDebugItems = "SELECT pd.id, pd.id_material, m.concepto, pd.cantidad, pd.comprado as comprado_campo,COALESCE((
            SELECT SUM(cd.cantidad) 
            FROM compras_detalle cd 
            INNER JOIN compras c ON c.id = cd.id_compra 
            WHERE c.id_pedido = pd.id_pedido 
            AND cd.id_material = pd.id_material 
            AND c.id_estado_compra >= 3
          ), 0) as comprado_real,
          pd.cancelado
        FROM pedidos_detalle pd 
          INNER JOIN materiales m ON m.id = pd.id_material
        WHERE pd.id_pedido = ? AND pd.cancelado = 0 
          AND pd.cantidad > COALESCE((
            SELECT SUM(cd.cantidad) 
            FROM compras_detalle cd 
            INNER JOIN compras c ON c.id = cd.id_compra 
            WHERE c.id_pedido = pd.id_pedido 
            AND cd.id_material = pd.id_material 
            AND c.id_estado_compra >= 3
          ), 0)";
        $qDebugItems = $pdo->prepare($sqlDebugItems);
        $qDebugItems->execute([$idPedido]);
        $itemsDebug = $qDebugItems->fetchAll(PDO::FETCH_ASSOC);
        echo "<b>ðŸ” ITEMS SIN COMPRAR COMPLETAMENTE:</b> " . count($itemsDebug) . "<br>";
        echo "<table border='1'><tr><th>ID</th><th>Material</th><th>Concepto</th><th>Cantidad</th><th>Comprado Campo</th><th>Comprado Real</th><th>Faltante</th></tr>";
        foreach ($itemsDebug as $itemDebug) {
          $faltante = $itemDebug['cantidad'] - $itemDebug['comprado_real'];
          echo "<tr>";
          echo "<td>" . $itemDebug['id'] . "</td>";
          echo "<td>" . $itemDebug['id_material'] . "</td>";
          echo "<td>" . htmlspecialchars($itemDebug['concepto']) . "</td>";
          echo "<td>" . $itemDebug['cantidad'] . "</td>";
          echo "<td>" . $itemDebug['comprado_campo'] . "</td>";
          echo "<td>" . $itemDebug['comprado_real'] . "</td>";
          echo "<td>" . $faltante . "</td>";
          echo "</tr>";
        }
        echo "</table><br>";
      }
    }
    
    // Si no hay OC pendientes Y no se pueden crear mÃ¡s OC, el pedido pasa a "Terminado"
    if ($dataPedido['cant_pendientes'] == 0 && $dataComprado['cant_sin_comprar'] == 0) {
      if ($modoDebug) {
        echo "<b>ðŸŽ‰ PEDIDO COMPLETAMENTE TERMINADO - Cambiando a estado 5</b><br>";
      }
      
      $sql = "UPDATE pedidos set id_estado = 5 where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$idPedido]);
      
      if ($modoDebug) {
        echo "<b>Filas afectadas:</b> " . $q->rowCount() . "<br>";
      }
      
      return true;
    } else {
      if ($modoDebug) {
        echo "<b>â³ Pedido AÃšN NO completamente terminado</b><br>";
        echo "<b>- OC pendientes:</b> " . $dataPedido['cant_pendientes'] . "<br>";
        echo "<b>- Items sin comprar:</b> " . $dataComprado['cant_sin_comprar'] . "<br>";
      }
      return false;
    }
    
  } catch (Exception $e) {
    if ($modoDebug) {
      echo "<b>âŒ Error al verificar estado del pedido:</b> " . $e->getMessage() . "<br>";
    }
    error_log("Error en verificarYActualizarEstadoPedido: " . $e->getMessage());
    return false;
  }
}

function obtenerStockYReservado(PDO $pdo, int $idMaterial): array
{
  $sqlDisponible = 'SELECT COALESCE(SUM(saldo), 0) 
                    FROM ingresos_detalle 
                    WHERE id_material = ?';
  $stmt = $pdo->prepare($sqlDisponible);
  $stmt->execute([$idMaterial]);
  $stockDisponible = (float)$stmt->fetchColumn();

  $sqlReservado = 'SELECT COALESCE(SUM(cantidad_reservada), 0) 
                    FROM egresos_detalle 
                    WHERE id_material = ?';
  $stmt = $pdo->prepare($sqlReservado);
  $stmt->execute([$idMaterial]);
  $totalReservado = (float)$stmt->fetchColumn();

  $sqlEfectivizado = 'SELECT COALESCE(SUM(cantidad_efectivizada), 0) 
                      FROM egresos_detalle 
                      WHERE id_material = ?';
  $stmt = $pdo->prepare($sqlEfectivizado);
  $stmt->execute([$idMaterial]);
  $totalEfectivizado = (float)$stmt->fetchColumn();

  $sqlIngresado = 'SELECT COALESCE(SUM(cantidad), 0) 
                    FROM ingresos_detalle 
                    WHERE id_material = ?';
  $stmt = $pdo->prepare($sqlIngresado);
  $stmt->execute([$idMaterial]);
  $totalIngresado = (float)$stmt->fetchColumn();

  $sqlDevuelto = 'SELECT COALESCE(SUM(id.cantidad), 0) 
                  FROM ingresos_detalle id 
                  INNER JOIN ingresos i ON i.id = id.id_ingreso 
                  WHERE id.id_material = ? AND i.id_tipo_ingreso = 2';
  $stmt = $pdo->prepare($sqlDevuelto);
  $stmt->execute([$idMaterial]);
  $totalDevuelto = (float)$stmt->fetchColumn();

  $sqlEgresado = 'SELECT COALESCE(SUM(cantidad), 0) 
                  FROM egresos_detalle 
                  WHERE id_material = ?';
  $stmt = $pdo->prepare($sqlEgresado);
  $stmt->execute([$idMaterial]);
  $totalEgresado = (float)$stmt->fetchColumn();

  $stockFisico = $stockDisponible + $totalReservado;

  return [
    'stock'            => $stockDisponible,
    'stock_fisico'     => $stockFisico,
    'reservado'        => $totalReservado,
    'efectivizado'     => $totalEfectivizado,
    'devuelto'         => $totalDevuelto,
    'total_ingresado'  => $totalIngresado,
    'total_egresado'   => $totalEgresado,
    'disponible'       => $stockDisponible
  ];
}

// Formato canÃ³nico de un certificado de avance: "Cert. 1/1" (nro_certificado / nro_revision)
function fmtCertificadoAvance($nroCertificado, $nroRevision): string {
	return 'Cert. ' . intval($nroCertificado) . '/' . intval($nroRevision);
}

/**
 * Â¿La moneda indicada es una moneda dÃ³lar?
 *
 * Se normalizan espacios, puntos y guiones antes de comparar, y se aceptan
 * ambas variantes con y sin tilde ('dolar' / 'dÃ³lar') porque la tilde depende de
 * cÃ³mo se haya cargado cada valor en la tabla.
 *
 * Ojo: un '$' suelto NO se considera dÃ³lar. En la tabla `monedas` el valor '$'
 * es la moneda local, y tomarlo como dÃ³lar harÃ­a que toda factura en pesos pida
 * cotizaciÃ³n.
 */
function esMonedaDolar($moneda): bool {
	$m = trim((string)$moneda);
	if ($m === '') {
		return false;
	}

	$original = $m;
	$m = preg_replace('/[\s._\-]+/u', '', $m);
	if ($m === '') {
		$m = $original;
	}
	if (function_exists('mb_strtolower')) {
		$m = mb_strtolower($m, 'UTF-8');
		$original = mb_strtolower($original, 'UTF-8');
	} else {
		$m = strtolower($m);
		$original = strtolower($original);
	}

	// Se prueba contra la cadena original y contra la normalizada: quitar separadores
	// puede partir una marca (ej. 'U.S.$' -> 'US$'), asÃ­ que hacen falta ambas.
	foreach ([$original, $m] as $candidato) {
		foreach (['dolar', 'dÃ³lar', 'usd', 'u$d', 'u$s'] as $marca) {
			if (strpos($candidato, $marca) !== false) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Letra fiscal (A/B/C/E/X) derivada de la denominaciÃ³n del tipo de comprobante.
 *
 * Se usa porque el alta de facturas ya no pide letra: el tipo elegido
 * (padrÃ³n AFIP con codigo_afip) la determina. Reglas:
 *  - denominaciÃ³n terminada en " A"/" B"/" C" => esa letra;
 *  - cÃ³digos 19-22 o menciÃ³n a exportaciÃ³n/exterior => E;
 *  - resto (M, tiques, remitos, ajustes, etc.) => X (mismo criterio
 *    histÃ³rico: ya hay facturas con letra X en la base).
 */
function letraComprobanteDesdeTipo($denominacion, $codigoAfip = null): string {
	$denominacion = trim((string)$denominacion);
	if (preg_match('/\s([ABC])$/u', $denominacion, $m)) {
		return $m[1];
	}
	$codigo = (int)$codigoAfip;
	if (in_array($codigo, [19, 20, 21, 22], true)
		|| stripos($denominacion, 'EXPORT') !== false
		|| stripos($denominacion, 'EXTERIOR') !== false) {
		return 'E';
	}
	return 'X';
}

/**
 * Codigo ARCA del tipo de comprobante a partir del valor guardado en
 * facturas_venta/facturas_compra.id_tipo_comprobante.
 * Los registros nuevos guardan directamente el codigo de `arca_tipos_comprobante`,
 * pero hay historicos que guardaban el id de la vieja tabla `tipos_comprobante`
 * (id = 1000 + codigo_afip). Esa tabla se elimina (consultas.sql, correcciÃ³n 27).
 */
function codigoArcaDesdeTipo($idTipoComprobante): int {
	$id = (int)$idTipoComprobante;
	return $id >= 1001 ? $id - 1000 : $id;
}

/**
 * Id numerico de letra para facturas_venta/facturas_compra.id_letra_comprobante.
 * La tabla letras_comprobante se elimino (consultas.sql); la letra ahora se
 * deriva del tipo (letraComprobanteDesdeTipo) y la convenciÃ³n de ids es la
 * misma que tenia esa tabla: 1=A, 2=B, 3=C, 4=E, 5=X.
 */
function derivarIdLetraComprobante(PDO $pdo, int $idTipoComprobante): int {
	$codigo = codigoArcaDesdeTipo($idTipoComprobante);
	$q = $pdo->prepare("SELECT codigo, descripcion FROM arca_tipos_comprobante WHERE codigo = ?");
	$q->execute([$codigo]);
	$fila = $q->fetch(PDO::FETCH_ASSOC);
	if (!$fila) {
		throw new RuntimeException('Seleccione un tipo de comprobante vÃ¡lido.');
	}
	$letra = letraComprobanteDesdeTipo($fila['descripcion'], $fila['codigo']);
	$mapaIdLetra = ['A' => 1, 'B' => 2, 'C' => 3, 'E' => 4, 'X' => 5];
	if (!isset($mapaIdLetra[$letra])) {
		throw new RuntimeException('No se pudo determinar la letra del comprobante.');
	}
	return $mapaIdLetra[$letra];
}
