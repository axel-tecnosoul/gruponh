<?php
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

    $modoDebug=1;

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
      $sql = "SELECT c.nro_revision, c.nro AS nro_computo,c.id_tarea FROM computos c WHERE c.id = $id_computo";
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

        //inicio logica de revision anterior gestionando
        // ... tras el bloque de aprobación parcial/completa y superación de revisión anterior:

        // 1) Obtener estado de la revisión anterior
        $sql = "SELECT id, id_estado FROM computos WHERE id_tarea = ? AND nro = ? AND nro_revision = ?";
        $stmt = $pdo->prepare($sql);
        $params = [$data['id_tarea'], $data['nro_computo'], $revision_anterior];
        $stmt->execute($params);
        $prevComp = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($modoDebug == 1) {
          // Generar y mostrar la consulta “real”
          echo "Obtenemos el estado de la revisión anterior<br><br>";
          $fullSql = debugQuery($pdo, $sql, $params);
          echo $fullSql . "<br><br>";
        }

        if ($prevComp && $prevComp['id_estado'] == 4) { // 4 = 'Gestionando'
          // 2) Traer líneas de ambas revisiones que NO estén canceladas
          $sql = "SELECT cd_previo.id AS id_previo, cd_previo.id_material,
                  cd_previo.cantidad AS cantidad_previo, cd_previo.reservado AS reservado_previo,
                  cd_actual.id AS id_actual, cd_actual.cantidad AS cantidad_actual
            FROM computos_detalle cd_previo
            JOIN computos_detalle cd_actual 
              ON cd_previo.id_material = cd_actual.id_material
            WHERE cd_previo.id_computo = :previo
              AND cd_actual.id_computo = :actual
              AND cd_previo.cancelado = 0
              AND cd_actual.cancelado = 0";
          $q = $pdo->prepare($sql);
          $params=[
            ':previo' => $prevComp['id'],
            ':actual' => $id_computo
          ];
          $q->execute($params);
          $lineas = $q->fetchAll(PDO::FETCH_ASSOC);

          if ($modoDebug == 1) {
            // Generar y mostrar la consulta “real”
            echo "Traemos líneas de ambas revisiones que NO estén canceladas<br><br>";
            $fullSql = debugQuery($pdo, $sql, $params);
            echo $fullSql . "<br><br>";
          }

          foreach ($lineas as $linea) {
            // A) Concepto Eliminado: existe en previo, no en actual
            if (is_null($linea['id_actual'])) {
              // 1) si $linea['reservado_previo'] > 0 → eliminar reserva
              if ($linea['reservado_previo'] > 0) {
                $sql = "UPDATE computos_detalle SET reservado = 0 WHERE id = ?";
                $params=[$linea['id_previo']];
                $pdo->prepare($sql)->execute($params);

                if ($modoDebug == 1) {
                  // Generar y mostrar la consulta “real”
                  echo "Eliminamos reserva del concepto eliminado en la revisión anterior<br><br>";
                  $fullSql = debugQuery($pdo, $sql, $params);
                  echo $fullSql . "<br><br>";
                }
                // TODO: anotar para notificar al gestor
              }
              // 2) y 3) manejar pedidos vía pedidos_detalle.id_computo_detalle
              //    – si pedido existe y comprando=0 → cancelar pedido parcial
              //    – si comprando=1 → solo notificar
            }
            // B) Cantidad cambiada:
            else if ($linea['cantidad_previo'] != $linea['cantidad_actual']) {
              $diff = $linea['cantidad_actual'] - $linea['cantidad_previo'];
              // 1) si tiene reserva:
              if ($linea['reservado_previo'] > 0) {
                if ($diff > 0) {
                  // > no tocar reserva, solo notificar
                  if ($modoDebug == 1) {
                    echo "Concepto con reserva, cantidad aumentada. No se toca reserva, solo se notifica.<br><br>";
                  }
                } else {
                  // < ajustar reserva: nuevo = reservado + diff
                  $nuevaReserva = $linea['reservado_previo'] + $diff;
                  $sql="UPDATE computos_detalle SET reservado = GREATEST(0, ?) WHERE id = ?";
                  $params=[$nuevaReserva, $linea['id_previo']];
                  $pdo->prepare($sql)->execute($params);

                  if ($modoDebug == 1) {
                    // Generar y mostrar la consulta “real”
                    echo "Ajustamos reserva del concepto con cantidad cambiada en la revisión anterior<br><br>";
                    $fullSql = debugQuery($pdo, $sql, $params);
                    echo $fullSql . "<br><br>";
                  }
                  // TODO: anotar para notificar al gestor
                }
              }
              // 2) si pedido existe y comprando=0 → comparar diff y actualizar pedido
              // 3) si comprando=1 → solo notificar
            }
            // si coincide cantidad y no cancelado, no hacemos nada
          }

          // 3) Finalmente, marcar esa revisión anterior y sus líneas como “Superado”
          //$pdo->prepare("UPDATE computos SET id_estado = 7 WHERE id = ?")->execute([$prevComp['id']]);
          //$pdo->prepare("UPDATE computos_detalle SET id_estado = 7 WHERE id_computo = ?")->execute([$prevComp['id']]);
          //$sql = "UPDATE computos SET id_estado = 7 WHERE id_tarea = ? AND nro = ? AND nro_revision = ?";
          $sql = "UPDATE computos SET id_estado = 7 WHERE id = ?";
          $q = $pdo->prepare($sql);
          $params = [$prevComp['id']];
          $q->execute($params);
          $textoComputo.=". Revisión anterior N° ".$revision_anterior." superada.";

          if ($modoDebug == 1) {
            // Generar y mostrar la consulta “real”
            echo "Marcamos la revisión anterior como superada<br><br>";
            $fullSql = debugQuery($pdo, $sql, $params);
            echo $fullSql . "<br><br>";
          }
        }

        //fin logica de revision anterior gestionando
      }
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
    http_response_code(500);
    echo "Error: " . $e->getMessage();
  }

    Database::disconnect();
    exit;
}
