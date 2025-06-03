<?php
$modoDebug=0;
if($modoDebug == 1) {
  $_SERVER["REQUEST_METHOD"] = "POST";
  $_POST["ajax"] = true; // Simulamos una petición AJAX para pruebas
  $_POST["accion"] = "aprobar_completo"; // Acción de prueba
  $_POST["id_computo"] = 100; // ID de cómputo de prueba
  $_SESSION['user']['id'] = 1; // Simulamos un usuario logueado
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax"])) {
  include_once 'config.php';
  include_once 'database.php';
  $pdo = Database::connect();

  $accion = $_POST["accion"];

  try {
    if (!isset($_POST["id_computo"])) {
      throw new Exception("ID de cómputo no recibido.");
    }

    $pdo->beginTransaction();

    $id_computo = (int)$_POST["id_computo"];

    $aprobando=0;
    switch ($accion) {
      case "aprobar_completo":
        $aprobando=1;
        $detalle_accion = "Aprobación del cómputo y todos sus conceptos";

        // Aprobar el cómputo
        $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id_computo]);

        // Aprobar todos los conceptos relacionados con el cómputo (independientemente del estado cancelado)
        $pdo->prepare("UPDATE computos_detalle SET aprobado = 1, cancelado = 0 WHERE id_computo = ?")->execute([$id_computo]);
        break;

      case "aprobar_parcial":
        $aprobando=1;
        $detalle_accion = "Aprobación del cómputo y los conceptos que no están cancelados";

        // Aprobar los conceptos que NO están cancelados (cancelado = 0)
        $pdo->prepare("UPDATE computos_detalle SET aprobado = 1 WHERE id_computo = ? AND cancelado = 0")->execute([$id_computo]);

        // Cambiar el estado del cómputo a aprobado
        $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id_computo]);
        break;

      case "cancelar_computo":
        $detalle_accion = "Cancelación del cómputo y todos sus conceptos";

        // Cambiar el estado del cómputo a cancelado
        $pdo->prepare("UPDATE computos SET id_estado = 6 WHERE id = ?")->execute([$id_computo]);

        // Cancelar todos los conceptos asociados a ese cómputo
        $pdo->prepare("UPDATE computos_detalle SET cancelado = 1, aprobado = 0 WHERE id_computo = ?")->execute([$id_computo]);
        break;

      default:
        http_response_code(400);
        echo "Acción no válida";
        exit;
    }

    $textoComputo = "";

    if($aprobando==1){

      // 3) Superar la revisión anterior y acumular su texto
      $textoComputo .= superarRevisionAnterior($pdo, $idComputo, $modoDebug, $_SESSION['user']);

      /*$sql = "SELECT c.nro_revision, c.nro AS nro_computo,c.id_tarea FROM computos c WHERE c.id = $id_computo";
      $q = $pdo->prepare($sql);
      $q->execute();
      $data = $q->fetch(PDO::FETCH_ASSOC);

      if ($modoDebug == 1) {
        echo $sql."<br>";
        var_dump($data);
      }

      $nro_revision=$data['nro_revision'];

      if($nro_revision>0){
        
        $revision_anterior=$nro_revision-1;

        // inicio lógica de revisión anterior “Gestionando”
        // ... tras el bloque de aprobación parcial/completa y superación de revisión anterior:

        // 1) Obtener estado de la revisión anterior
        $sql = "SELECT id, id_estado FROM computos WHERE id_tarea = ? AND nro = ? AND nro_revision = ?";
        $stmt = $pdo->prepare($sql);
        $params = [$data['id_tarea'], $data['nro_computo'], $revision_anterior];
        $stmt->execute($params);
        $prevComp = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($modoDebug == 1) {
          echo "Obtenemos el estado de la revisión anterior<br><br>";
          echo debugQuery($pdo, $sql, $params) . "<br><br>";
        }

        // ============================================================
        // A) Si la revisión previa NO estaba en “Gestionando” (id_estado ≠ 4),
        //    se debería haber marcado todo directamente como “Superado”
        //    sin entrar al B) de comparaciones.
        // ============================================================

        if ($prevComp && $prevComp['id_estado'] == 4) { // 4 = 'Gestionando'
          // 2) Traer líneas de ambas revisiones que NO estén canceladas
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
          $q = $pdo->prepare($sql);
          $params = [
            ':previo' => $prevComp['id'],
            ':actual' => $id_computo
          ];
          $q->execute($params);
          $lineas = $q->fetchAll(PDO::FETCH_ASSOC);

          if ($modoDebug == 1) {
            echo "Traemos líneas de ambas revisiones que NO estén canceladas<br><br>";
            echo debugQuery($pdo, $sql, $params) . "<br><br>";
          }

          foreach ($lineas as $linea) {
            // ============================================================
            // B) Revisiones anteriores en estado “Gestionando” → comparar
            //    solo líneas no canceladas paralelo a la descripción B.A y B.B
            // ============================================================

            // ----------------------------
            // B.A) Concepto Eliminado
            // ----------------------------
            if (is_null($linea['id_actual'])) {
              // B.A.1) si tiene reserva → la borramos
              if ($linea['reservado_previo'] > 0) {
                // 1) eliminar reserva en revisión anterior
                $sql = "UPDATE computos_detalle SET reservado = 0 WHERE id = ?";
                $params = [$linea['id_previo']];
                $pdo->prepare($sql)->execute($params);

                if ($modoDebug == 1) {
                  echo "Eliminamos reserva del concepto eliminado<br><br>";
                  echo debugQuery($pdo, $sql, $params) . "<br><br>";
                }
                // TODO: acumular mensaje para notificar al gestor

                // NOTA: la línea ya no existe en la nueva revisión,
                // así que no hay nada que heredar aquí.
              }

              // B.A.2 y B.A.3) si existe pedido ↦ cancelar o notificar según comprando
              // buscamos el pedido_detalle vinculado a esta línea previa
              $sql="SELECT id, cantidad AS pedido_cantidad, comprado FROM pedidos_detalle WHERE id_computo_detalle = ?";
              $params = [$linea['id_previo']];
              $stmtPd = $pdo->prepare($sql);
              $stmtPd->execute($params);
              $pd = $stmtPd->fetch(PDO::FETCH_ASSOC);

              if ($modoDebug == 1) {
                echo "Buscamos el pedido_detalle vinculado a esta linea previa<br><br>";
                echo debugQuery($pdo, $sql, $params) . "<br><br>";
              }

              if ($pd) {
                if ($pd['comprado'] == 0) {
                  // B.A.2) pedido no comprando → cancelar parcialmente
                  $sql = "UPDATE pedidos_detalle SET cancelado = 1, cantidad = 0 WHERE id = ?";
                  $params = [$pd['id']];
                  $pdo->prepare($sql)->execute($params);

                  if ($modoDebug == 1) {
                    echo "Cancelamos pedido parcial para concepto eliminado<br><br>";
                    echo debugQuery($pdo, $sql, $params) . "<br><br>";
                  }
                  // TODO: notificar gestor + comprador
                } else {
                  // B.A.3) pedido ya en compra → solo notificar
                  // TODO: notificar gestor + comprador

                  if ($modoDebug == 1) {
                    echo "Pedido en proceso de compra, solo notificar<br><br>";
                  }
                }
              }
            }
            // ----------------------------
            // B.B) Cantidad Cambiada
            // ----------------------------
            else if ($linea['cantidad_previo'] != $linea['cantidad_actual']) {
              $diff = $linea['cantidad_actual'] - $linea['cantidad_previo'];

              // B.B.1) si tenía reserva en la revision previa
              if ($linea['reservado_previo'] > 0) {
                if ($diff > 0) {
                  // B.B.1.1) nueva cantidad > reserva → no tocar reserva, notificar
                  // TODO: notificar gestor
                  if ($modoDebug == 1) {
                    echo "Reserva existe, cantidad aumentada: no tocar reserva, solo notificar<br><br>";
                  }
                } else {
                  // B.B.1.2) nueva cantidad < reserva → modificar reserva en la revision previa

                  $nuevaReserva = $linea['reservado_previo'] + $diff;
                  $sql = "UPDATE computos_detalle SET reservado = GREATEST(0, ?) WHERE id = ?";
                  $params = [$nuevaReserva, $linea['id_previo']];
                  $pdo->prepare($sql)->execute($params);

                  if ($modoDebug == 1) {
                    echo "Ajustamos reserva tras reducción de cantidad<br><br>";
                    echo debugQuery($pdo, $sql, $params) . "<br><br>";
                  }
                  // TODO: notificar gestor

                  // Heredar reserva a la revisión actual hasta la nueva cantidad_actual
                  $sql = "UPDATE computos_detalle SET reservado = LEAST(?, ?) WHERE id = ?";
                  // LEAST(cantidad_actual, nuevaReserva) en caso de que nuevaReserva > cantidad_actual
                  $params = [$linea['cantidad_actual'],$nuevaReserva,$linea['id_actual']];
                  $pdo->prepare($sql)->execute($params);

                  if ($modoDebug == 1) {
                    echo "Heredamos reserva a la revisión actual<br><br>";
                    echo debugQuery($pdo, $sql, $params) . "<br><br>";
                  }
                  // TODO: notificar gestor que la nueva revi. mantiene reserva de = cantidad_actual
                }
              }

              // B.B.2 y B.B.3) si existe pedido en la revision previa
              $sql="SELECT id, cantidad AS pedido_cantidad, comprado FROM pedidos_detalle WHERE id_computo_detalle = ?";
              $stmtPd = $pdo->prepare($sql);
              $params = [$linea['id_previo']];
              $stmtPd->execute($params);
              $pd = $stmtPd->fetch(PDO::FETCH_ASSOC);

              if ($modoDebug == 1) {
                echo "buscamos el pedido_detalle vinculado a esta línea previa<br><br>";
                echo debugQuery($pdo, $sql, $params) . "<br><br>";
              }

              if ($pd) {
                if ($pd['comprado'] == 0) {
                  if ($diff > 0) {
                    // B.B.2.1) nueva cant > anterior → solo notificar gestor+comprador
                    // TODO: notificar gestor + comprador
                    if ($modoDebug == 1) {
                      echo "Pedido existe y no comprado, cantidad mayor: solo notificar<br><br>";
                    }
                  } else {
                    // B.B.2.2) nueva cant < anterior → calcular cant a pedir
                    $cantAPedir = $linea['cantidad_actual'] - $linea['reservado_previo'];
                    if ($cantAPedir <= 0) {
                      // B.B.2.2.1) cant a pedir = 0 → cancelar pedido_detalle
                      $sql = "UPDATE pedidos_detalle SET cancelado = 1, cantidad = 0 WHERE id = ?";
                      $params = [$pd['id']];
                      $pdo->prepare($sql)->execute($params);
                      
                      if ($modoDebug == 1) {
                        echo "Reducida a cero: cancelamos pedido_detalle<br><br>";
                        echo debugQuery($pdo, $sql, $params) . "<br><br>";
                      }
                      // TODO: notificar gestor + comprador
                    } else {
                      // B.B.2.2.2) cant a pedir > 0 → modificar pedido y reasignar a línea actual
                      $sql = "UPDATE pedidos_detalle SET cantidad = ?, id_computo_detalle = ? WHERE id = ?";
                      $params = [$cantAPedir, $linea['id_actual'], $pd['id']];
                      $pdo->prepare($sql)->execute($params);

                      if ($modoDebug == 1) {
                        echo "Pedido actualizado a nueva cantidad y reasignado al cómputo actual<br><br>";
                        echo debugQuery($pdo, $sql, $params) . "<br><br>";
                      }
                      // TODO: notificar gestor + comprador
                    }
                  }
                } else {
                  // B.B.3) pedido en compra → solo notificar gestor+comprador
                  // TODO: notificar gestor + comprador
                  if ($modoDebug == 1) {
                    echo "Pedido en proceso de compra tras cambio de cantidad: solo notificar<br><br>";
                  }
                }
              }
            }
            // si la cantidad no cambió ni está cancelado, nada que hacer
          }

        }

        // 3) Finalmente, marcar esa revisión anterior y sus líneas como “Superado”
        $sql = "UPDATE computos SET id_estado = 7 WHERE id = ?";
        $params = [$prevComp['id']];
        $pdo->prepare($sql)->execute($params);
        $textoComputo .= ". Revisión anterior N° {$revision_anterior} superada.";

        if ($modoDebug == 1) {
          echo "Marcamos la revisión anterior como superada<br><br>";
          echo debugQuery($pdo, $sql, $params) . "<br><br>";
        }

        // fin lógica de revisión anterior gestionando

      }*/
    }

    // 3) Insertar log
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, '$detalle_accion.$textoComputo', 'Cómputos', 'verComputo.php?id={$id_computo}')";
    $q = $pdo->prepare($sql);
    $q->execute([ $_SESSION['user']['id'] ]);

    if ($modoDebug == 1) {
      $pdo->rollBack();
    }else{
      $pdo->commit();
    }

    echo "ok";
  } catch (Exception $e) {

    // Si hubo un beginTransaction() activo, lo revertimos
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }

    http_response_code(500);
    echo "Error: " . $e->getMessage();
  }finally {
    // Siempre cerramos la conexión
    Database::disconnect();
    exit;
  }

}
