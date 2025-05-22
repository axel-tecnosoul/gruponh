<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarComputos.php");
}

if (!empty($_POST)) {

  // insert data
  /*$pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=1;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    //var_dump($_POST);
    //var_dump($_GET);
    //var_dump($_FILES);
  }
  
  $sql = "UPDATE computos set id_cuenta_solicitante = ? where id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST['id_cuenta_solicitante'],$_GET['id']]);

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de computo','Computos','verComputo.php?id=$id')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  $pdo->rollBack();
  
  Database::disconnect();*/



  /*$pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug = 1;
  $id        = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  $modoDebug=1;

  if ($modoDebug==1) {
    var_dump($_POST);
    var_dump($_GET);
    var_dump($_FILES);
    //var_dump($_SESSION);
  }

  try {
    if ($modoDebug === 1) {
      $pdo->beginTransaction();
    }

    $id_cuenta_solicitante=$_SESSION['user']['id'];

    // 1) UPDATE computo
    $sql = "UPDATE computos SET id_cuenta_solicitante = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $ok = $q->execute([$id_cuenta_solicitante,$id]);
    
    if (!$ok) {
      throw new Exception("Error al actualizar computo (ID: $id).");
    }

    // 2) INSERT log
    $link = "verComputo.php?id={$id}";
    $sql  = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (NOW(), ?, 'Modificación de computo', 'Computos', ?)";
    $q = $pdo->prepare($sql);
    $ok = $q->execute([$_SESSION['user']['id'],$link]);
    
    if (!$ok) {
      throw new Exception("Error al insertar log para computo (ID: $id).");
    }

    // 3) Si todo salió bien, confirmamos
    if ($modoDebug === 1) {
      $pdo->rollBack();
    }else{
      $pdo->commit();
    }
    echo "Operación realizada con éxito.";

  } catch (Exception $e) {
    // Si hubo cualquier fallo, deshacemos
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    // Aquí puedes registrar $e->getMessage() en un log de errores
    echo "Ocurrió un error. La operación no fue completada. Detalle: " . htmlspecialchars($e->getMessage());
  } finally {
    Database::disconnect();
  }*/


  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;

  if ($modoDebug==1) {
    var_dump($_POST);
    var_dump($_GET);
    var_dump($_FILES);
    //var_dump($_SESSION);
  }

  $idComputo   = isset($_POST['idComputo']) ? (int) $_POST['idComputo'] : 0;
  $reservas    = isset($_POST['cantidad_reservar']) ? $_POST['cantidad_reservar'] : [];
  $pedidos     = isset($_POST['cantidad_pedir'])   ? $_POST['cantidad_pedir']   : [];
  $userId      = $_SESSION['user']['id'];

  try {
      $pdo->beginTransaction();

      // 1) Registrar todas las reservas de stock
      $sqlUpdReserva = "UPDATE computos_detalle SET reservado = ? WHERE id = ?";
      $stmtUpd = $pdo->prepare($sqlUpdReserva);

      foreach ($reservas as $idDetalle => $cantRes) {
        $cantRes = (int) $cantRes;
        if ($cantRes > 0) {

          $params = [$cantRes, $idDetalle];
          
          if ($modoDebug == 1) {
            // Generar y mostrar la consulta “real”
            $fullSql = debugQuery($pdo, $sqlUpdReserva, $params);
            echo $fullSql . "<br><br>";
          }
          
          $stmtUpd->execute($params);
          if ($stmtUpd->rowCount() === 0) {
            throw new Exception("No se pudo actualizar reserva para detalle $idDetalle.");
          }
        }
      }

      // 2) Si hay al menos un pedido, creamos cabecera de pedido
      $tienePedido = false;
      foreach ($pedidos as $amt) {
        if ((int)$amt > 0) {
          $tienePedido = true;
          break;
        }
      }

      if ($tienePedido) {
          // 2.a) Insertar cabecera
          $sqlInsPedido = "INSERT INTO pedidos (id_computo, fecha, lugar_entrega, id_cuenta_recibe, id_estado) VALUES (?, NOW(), ?, ?, 1)";
          $params = [$idComputo,$_POST['lugar_entrega'],$_POST['id_cuenta_recibe']];

          if ($modoDebug == 1) {
            // Generar y mostrar la consulta “real”
            $fullSql = debugQuery($pdo, $sqlInsPedido, $params);
            echo $fullSql . "<br><br>";
          }

          $stmtInsPedido = $pdo->prepare($sqlInsPedido);
          // Asumo que recibes fecha y lugar_entrega en $_POST:
          $stmtInsPedido->execute($params);
          $idPedido = $pdo->lastInsertId();

          // 2.b) Insertar detalle de pedido por cada material pedido
          $sqlInsDetalle = "INSERT INTO pedidos_detalle (id_pedido, id_material, fecha_necesidad, cantidad, id_unidad_medida, reservado, comprado) VALUES (?, ?, ?, ?, ?, ?, ?)";
          $stmtInsDet = $pdo->prepare($sqlInsDetalle);

          // Para obtener datos de computos_detalle (id_material, fecha_necesidad, unidad, reservado, comprado)
          $sqlFetch = "SELECT d.id, m.concepto, d.cantidad, d.fecha_necesidad, d.aprobado, d.id_material, d.reservado, d.comprado,SUM(id.saldo) AS disponible,m.id_unidad_medida FROM computos_detalle d inner join materiales m on m.id = d.id_material left join ingresos_detalle id on id.id_material = d.id_material WHERE d.cancelado = 0 and d.id_computo = ? GROUP BY d.id_material";
          /*$sqlFetch = "SELECT d.id, d.id_material, d.fecha_necesidad,
                              m.id_unidad_medida, d.reservado, d.comprado
                      FROM computos_detalle d inner join materiales m on m.id = d.id_material
                      WHERE d.id_computo = ? AND d.cancelado = 0";*/
          $datos = $pdo->prepare($sqlFetch);
          $datos->execute([$idComputo]);
          $rows = $datos->fetchAll(PDO::FETCH_ASSOC);

          foreach ($rows as $r) {
            $idDet = $r['id'];
            $cantP = isset($pedidos[$idDet]) ? (int)$pedidos[$idDet] : 0;
            if ($cantP > 0) {

              $params=[$idPedido,$r['id_material'],$r['fecha_necesidad'],$cantP,$r['id_unidad_medida'],$r['reservado'],$r['comprado']];

              if ($modoDebug == 1) {
                // Generar y mostrar la consulta “real”
                $fullSql = debugQuery($pdo, $sqlInsDetalle, $params);
                echo $fullSql . "<br><br>";
              }

              $stmtInsDet->execute($params);
              if ($stmtInsDet->rowCount() === 0) {
                throw new Exception("No se pudo insertar detalle de pedido para detalle $idDet.");
              }
            }
          }

          // 2.c) Log de pedido
          $sqlLogP = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Nuevo Pedido', 'Pedidos', ?)";
          $stmtLogP = $pdo->prepare($sqlLogP);

          $params = [$userId, "verPedido.php?id={$idPedido}"];

          if ($modoDebug == 1) {
            // Generar y mostrar la consulta “real”
            $fullSql = debugQuery($pdo, $sqlLogP, $params);
            echo $fullSql . "<br><br>";
          }

          $stmtLogP->execute($params);
      }

      // 3) Actualizar estado de cómputo a “4” (reservado/pedido completo)
      $sqlUpdComp = "UPDATE computos SET id_estado = 4 WHERE id = ?";
      $stmtComp = $pdo->prepare($sqlUpdComp);

      $params = [$idComputo];

      if ($modoDebug == 1) {
        // Generar y mostrar la consulta “real”
        $fullSql = debugQuery($pdo, $sqlUpdComp, $params);
        echo $fullSql . "<br><br>";
      }

      /*$stmtComp->execute($params);
      if ($stmtComp->rowCount() === 0) {
        throw new Exception("No se pudo actualizar estado de cómputo {$idComputo}.");
      }*/

      $ok = $stmtComp->execute($params);
      if ($ok === false) {
          throw new Exception("Falló la ejecución de la consulta de estado de cómputo.");
      }

      // 4) Log de reserva
      $sqlLogR = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Nueva reserva de stock', 'Pedidos', ?)";
      $stmtLogR = $pdo->prepare($sqlLogR);

      $params = [$userId, "verPedido.php?id={$idComputo}"];

      if ($modoDebug == 1) {
        // Generar y mostrar la consulta “real”
        $fullSql = debugQuery($pdo, $sqlLogR, $params);
        echo $fullSql . "<br><br>";
      }

      $stmtLogR->execute($params);

      // 5) Commit
      if ($modoDebug === 1) {
        $pdo->rollBack();
        echo "Operación realizada con éxito.";
      }else{
        $pdo->commit();
      }

  } catch (Exception $e) {
      // Rollback y muestra mensaje de error
      if ($pdo->inTransaction()) {
          $pdo->rollBack();
      }
      echo "Error en la operación: " . htmlspecialchars($e->getMessage());
  } finally {
      Database::disconnect();
  }

  
  header("Location: verComputo.php?id=".$_GET['id']);
} else {
  header("Location: listarComputos.php");
}