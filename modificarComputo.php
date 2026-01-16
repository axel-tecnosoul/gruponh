<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodQuery = $prod ? '?prod=' . $prod : '';
$prodParam = $prod ? '&prod=' . $prod : '';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarComputos.php$prodQuery");
}

if (!empty($_POST)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $idComputo = isset($_POST['idComputo']) ? (int)$_POST['idComputo'] : 0;
  $pedidos   = isset($_POST['cantidad_pedir']) ? $_POST['cantidad_pedir'] : [];
  $userId    = $_SESSION['user']['id'];

  try {
      $pdo->beginTransaction();

      // =======================================================================
      // 1. PROCESAR RESERVAS POR LOTE
      // =======================================================================
      if (!empty($_POST['reservas_lote'])) {
          
          $sqlInfo = "SELECT t.id_proyecto, p.id_sitio, t.id AS id_tarea, c.id_cuenta_solicitante 
                      FROM computos c 
                      INNER JOIN tareas t ON t.id = c.id_tarea 
                      INNER JOIN proyectos p ON p.id = t.id_proyecto 
                      WHERE c.id = ?";
          $qInfo = $pdo->prepare($sqlInfo);
          $qInfo->execute([$idComputo]);
          $infoComputo = $qInfo->fetch(PDO::FETCH_ASSOC);

          if (!$infoComputo) {
              throw new Exception("No se encontró información del cómputo ID: $idComputo");
          }

          // Definir cuenta retira (si no viene en post, usa la del solicitante)
          $idCuentaRetira = !empty($_POST['id_cuenta_recibe']) ? $_POST['id_cuenta_recibe'] : $infoComputo['id_cuenta_solicitante'];

          // Cabecera Egreso
          $sqlEgreso = "INSERT INTO egresos (fecha_hora, id_tipo_egreso, nro, id_cuenta_retira, id_sitio_destino, id_tarea, id_proyecto, observaciones) 
                        VALUES (NOW(), 2, ?, ?, ?, ?, ?, 'Reserva automática desde Cómputo')";
          $stmtCab = $pdo->prepare($sqlEgreso);
          $stmtCab->execute([
              $idComputo, 
              $idCuentaRetira, 
              $infoComputo['id_sitio'], 
              $infoComputo['id_tarea'], 
              $infoComputo['id_proyecto']
          ]);
          $idEgreso = $pdo->lastInsertId();

          // CORRECCION AQUI: Agregamos id_unidad_medida al INSERT y al SELECT
          $stmtInsDet  = $pdo->prepare("INSERT INTO egresos_detalle (id_egreso, id_material, id_detalle_ingreso, cantidad, cantidad_reservada, id_unidad_medida) VALUES (?, ?, ?, ?, ?, ?)");
          $stmtUpdIng  = $pdo->prepare("UPDATE ingresos_detalle SET saldo = saldo - ?, cantidad_egresada = cantidad_egresada + ? WHERE id = ?");
          $stmtUpdComp = $pdo->prepare("UPDATE computos_detalle SET reservado = reservado + ? WHERE id = ?");
          
          // Buscamos material Y unidad de medida del ingreso
          $stmtGetMat  = $pdo->prepare("SELECT id_material, id_unidad_medida FROM ingresos_detalle WHERE id = ?");

          $contadorReservas = 0;

          foreach ($_POST['reservas_lote'] as $idCompDet => $lotes) {
              $totalReservadoItem = 0;
              foreach ($lotes as $idIngDet => $cant) {
                  $cant = (float)$cant;
                  if ($cant > 0) {
                      $stmtGetMat->execute([$idIngDet]);
                      $rowMat = $stmtGetMat->fetch(PDO::FETCH_ASSOC);

                      if ($rowMat) {
                          // Insertamos incluyendo id_unidad_medida
                          $stmtInsDet->execute([
                              $idEgreso, 
                              $rowMat['id_material'], 
                              $idIngDet, 
                              $cant, 
                              $cant, 
                              $rowMat['id_unidad_medida']
                          ]);
                          
                          $stmtUpdIng->execute([$cant, $cant, $idIngDet]);
                          
                          $totalReservadoItem += $cant;
                          $contadorReservas++;
                      }
                  }
              }
              if ($totalReservadoItem > 0) {
                  $stmtUpdComp->execute([$totalReservadoItem, $idCompDet]);
              }
          }

          if ($contadorReservas > 0) {
              $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Nueva reserva de stock', 'Computos', ?)")
                  ->execute([$userId, "verComputo.php?id={$idComputo}$prodParam"]);
          }
      }

      // =======================================================================
      // 2. PROCESAR PEDIDOS
      // =======================================================================
      $tienePedido = false;
      foreach ($pedidos as $amt) { if ((float)$amt > 0) { $tienePedido = true; break; } }

      if ($tienePedido) {
          $id_proyecto = isset($infoComputo) ? $infoComputo['id_proyecto'] : null;
          if (!$id_proyecto) {
              $qP = $pdo->prepare("SELECT t.id_proyecto FROM computos c JOIN tareas t ON t.id=c.id_tarea WHERE c.id=?");
              $qP->execute([$idComputo]);
              $id_proyecto = $qP->fetchColumn();
          }
          
          $idSolicitantePedido = isset($_SESSION['user']['id_perfil']) ? $_SESSION['user']['id_perfil'] : 0;

          $sqlInsPedido = "INSERT INTO pedidos (id_computo, id_proyecto, fecha, lugar_entrega, id_cuenta_recibe, id_cuenta_solicitante, id_estado) VALUES (?, ?, NOW(), ?, ?, ?, 2)";
          $pdo->prepare($sqlInsPedido)->execute([
              $idComputo, 
              $id_proyecto, 
              $_POST['lugar_entrega'], 
              $_POST['id_cuenta_recibe'],
              $idSolicitantePedido
          ]);
          $idPedido = $pdo->lastInsertId();

          $stmtInsDet = $pdo->prepare("INSERT INTO pedidos_detalle (id_pedido, id_computo_detalle, id_material, fecha_necesidad, cantidad, id_unidad_medida, reservado, comprado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
          
          $sqlRows = "SELECT cd.id AS id_computo_detalle, cd.fecha_necesidad, cd.id_material, cd.reservado, cd.comprado, m.id_unidad_medida FROM computos_detalle cd JOIN materiales m on m.id = cd.id_material WHERE cd.cancelado = 0 and cd.id_computo = ?";
          $qRows = $pdo->prepare($sqlRows);
          $qRows->execute([$idComputo]);

          foreach ($qRows->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cantP = isset($pedidos[$r['id_computo_detalle']]) ? (float)$pedidos[$r['id_computo_detalle']] : 0;
            if ($cantP > 0) {
              $stmtInsDet->execute([$idPedido, $r['id_computo_detalle'], $r['id_material'], $r['fecha_necesidad'], $cantP, $r['id_unidad_medida'], $r['reservado'], $r['comprado']]);
            }
          }

          $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Nuevo Pedido', 'Pedidos', ?)")->execute([$userId, "verPedido.php?id={$idPedido}"]);
      }

      // =======================================================================
      // 3. ACTUALIZAR SALDOS Y ESTADO
      // =======================================================================
      if (!empty($_POST['saldo'])) {
        $stmtUpdSaldo = $pdo->prepare("UPDATE computos_detalle SET saldo = ? WHERE id = ?");
        foreach ($_POST['saldo'] as $idDetalle => $saldoActualStr) {
          $saldoActual = (float) $saldoActualStr;
          $cantRes = 0;
          if (isset($_POST['reservas_lote'][$idDetalle])) {
              foreach($_POST['reservas_lote'][$idDetalle] as $c) $cantRes += (float)$c;
          }
          $cantPed = isset($pedidos[$idDetalle]) ? (float)$pedidos[$idDetalle] : 0;
          $nuevoSaldo = max($saldoActual - $cantRes - $cantPed, 0);
          $stmtUpdSaldo->execute([$nuevoSaldo, $idDetalle]);
        }
      }

      // Validacion de estado final
      if (function_exists('marcarComputoGestionandoOTerminado')) {
          marcarComputoGestionandoOTerminado($pdo, $idComputo, 0);
      } else {
          $qSt = $pdo->prepare("SELECT cd.cantidad, cd.reservado, cd.comprado, COALESCE(SUM(pd.cantidad),0) as ped FROM computos_detalle cd LEFT JOIN pedidos p ON p.id_computo=cd.id_computo AND p.anulado=0 LEFT JOIN pedidos_detalle pd ON pd.id_pedido=p.id AND pd.id_material=cd.id_material WHERE cd.id_computo=? AND cd.cancelado=0 GROUP BY cd.id");
          $qSt->execute([$idComputo]);
          $finished = true;
          foreach($qSt->fetchAll() as $r) {
             if (($r['cantidad'] - $r['reservado'] - $r['comprado'] - $r['ped']) > 0.01) { $finished = false; break; }
          }
          $pdo->prepare("UPDATE computos SET id_estado = ? WHERE id = ?")->execute([$finished ? 5 : 2, $idComputo]);
      }

      $pdo->commit();

  } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      echo "Error: " . $e->getMessage();
      die(); 
  } finally {
      Database::disconnect();
  }
  
  header("Location: verComputo.php?id=".$_GET['id'].$prodParam);
} else {
  header("Location: listarComputos.php$prodQuery");
}
?>