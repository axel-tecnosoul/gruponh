<?php
// tests/quicktests.php

require '../config.php';
require '../database.php';
//require '../funciones.php'; // aquí está superarRevisionAnterior()

/**
 * Ejecuta un exec(), imprime la consulta y el rowCount().
 */
function execLog(PDO $pdo, string $sql, $showLog = true): int {
    if($showLog) echo "<pre style='color:purple'>EXEC: $sql</pre>";
    $count = $pdo->exec($sql);
    if($showLog) echo "<pre style='color:purple'>  → Filas afectadas: $count</pre>";
    return $count;
}

/**
 * Prepara y ejecuta un statement, imprime la consulta y rowCount().
 * Devuelve el PDOStatement para que puedas fetch() normalmente.
 */
function stmtLog(PDO $pdo, string $sql, array $params = []): PDOStatement {
    echo "<pre style='color:blue'>STMT: $sql</pre>";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->rowCount();
    echo "<pre style='color:blue'>  → Filas afectadas: $count</pre>";
    return $stmt;
}

function deleteTables($pdo) {
  $showLog=false;
  // Elimina los datos de las tablas relevantes
  execLog($pdo, "DELETE FROM packing_lists_componentes;",$showLog);
  execLog($pdo, "DELETE FROM pedidos_detalle;",$showLog);
  execLog($pdo, "DELETE FROM computos_detalle;",$showLog);
  execLog($pdo, "DELETE FROM facturas_compra_detalle_x_compras_detalle;",$showLog);
  execLog($pdo, "DELETE FROM compras_detalle;",$showLog);
  execLog($pdo, "DELETE FROM compras_revisiones;",$showLog);
  execLog($pdo, "DELETE FROM compras_sucesos;",$showLog);
  execLog($pdo, "DELETE FROM facturas_compra_detalle;",$showLog);
  execLog($pdo, "DELETE FROM facturas_compra_retenciones;",$showLog);
  execLog($pdo, "DELETE FROM facturas_compra;",$showLog);
  execLog($pdo, "DELETE FROM ingresos_detalle;",$showLog);
  execLog($pdo, "DELETE FROM coladas;",$showLog);
  execLog($pdo, "DELETE FROM compras;",$showLog);
  execLog($pdo, "DELETE FROM pedidos;",$showLog);
  execLog($pdo, "DELETE FROM computos",$showLog);
}


$pdo = Database::connect();

// **Activa el modo excepción para que cualquier exec o query fallida lance un PDOException**
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tests = [

  // ------------------------------------------------------------
  // A) Revisión anterior en estado ≠ 4 (p.ej. 3) → superada sin más
  // ------------------------------------------------------------
  'A_superar_sin_comparar' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0 con estado 3 (no gestionando)
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (20,1,0,120,3,4001866)");
      // rev1
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (21,1,1,120,3,4001866)");
    },
    'test' => function(PDO $pdo) {
      $texto = superarRevisionAnterior($pdo, 21, 0, ['id'=>1]);
      // Verifico que estado quedó a 7
      $stmt = stmtLog($pdo, "SELECT id_estado FROM computos WHERE id = ?",[20]);
      //$stmt->execute([20]);
      return $stmt->fetchColumn() == 7 && strpos($texto, 'superada') !== false;
    }
  ],

  // ------------------------------------------------------------
  // B.A.1) Concepto eliminado + tenía reserva → borramos reserva
  // ------------------------------------------------------------
  'BA1_eliminar_reserva' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (30,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (3,30,70,5,4,0,0,0,'2025-01-01','')");
      // no hay pedido para este caso
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (31,1,1,120,3,4001866)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 31, 0, ['id'=>1]);
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = ?",[3]);
      //$stmt->execute([3]);
      return $stmt->fetchColumn() == 0;
    }
  ],

  // ------------------------------------------------------------
  // B.A.2) Concepto eliminado + pedido no “comprando” → cancelar pedido
  // ------------------------------------------------------------
  'BA2_cancelar_pedido' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (40,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (4,40,80,6,0,0,0,0,'2025-01-01','')");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (201,40,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_material,fecha_necesidad,cantidad,id_unidad_medida,reservado,comprado) VALUES (4,201,80,'2025-01-01',3,1,0,0)");
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (41,1,1,120,3,4001866)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 41, 0, ['id'=>1]);
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[4]);
      //$stmt->execute([4]);
      return $stmt->fetchColumn() == 0;
    }
  ],

  // ------------------------------------------------------------
  // B.A.3) Concepto eliminado + pedido “comprando” → no tocar cantidad
  // ------------------------------------------------------------
  'BA3_pedido_comprando' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (50,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (5,50,90,7,0,0,0,0,'2025-01-01','')");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (202,50,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_material,fecha_necesidad,cantidad,id_unidad_medida,reservado,comprado) VALUES (5,202,90,'2025-01-01',4,1,0,1)"); // comprado=1
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (51,1,1,120,3,4001866)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 51, 0, ['id'=>1]);
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[5]);
      //$stmt->execute([5]);
      return $stmt->fetchColumn() == 4; // no se toca
    }
  ],

  // ------------------------------------------------------------
  // B.B.1.1) Cantidad aumentada > reserva → no tocar reserva
  // ------------------------------------------------------------
  'BB11_no_tocar_reserva' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (60,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado) VALUES (6,60,100,5,3)");
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (61,1,1,120,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad) VALUES (7,61,100,7)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 61, 0, ['id'=>1]);
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = ?",[6]);
      //$stmt->execute([6]);
      return $stmt->fetchColumn() == 3; // reserva intacta
    }
  ],

  // ------------------------------------------------------------
  // B.B.1.2) Cantidad inferior a reserva → ajustar previa e heredar a actual
  // ------------------------------------------------------------
  'BB12_ajustar_y_heredar_reserva' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // Computo rev0
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (70,1,0,120,4,4001866)");
      // Detalle rev0: cantidad=10, reservado=6
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (7,70,110,10,6,0,0,0,'2025-01-01','')");
      // Computo rev1
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (71,1,1,120,3,4001866)");
      // Detalle rev1: cantidad_actual=4
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (8,71,110,4,0,0,0,0,'2025-01-01','')");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 71, 0, ['id'=>1]);
      // prev.reservado ajustado a 6 + (4-10) = 0
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = 7");
      $r0 = $stmt->fetchColumn();
      // actual.reservado heredado = min(cantidad_actual=4, nuevaReserva=0) = 0
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = 8");
      $r1 = $stmt->fetchColumn();
      return $r0 == 0 && $r1 == 0;
    }
  ],

  // ------------------------------------------------------------
  // B.B.2.1) Pedido no comprado + diff > 0 → no tocar pedido
  // ------------------------------------------------------------
  'BB21_no_tocar_pedido_diff_positivo' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (80,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (9,80,120,3,0,0,0,0,'2025-01-01','')");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (203,80,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_material,fecha_necesidad,cantidad,id_unidad_medida,reservado,comprado) VALUES (9,203,120,'2025-01-01',2,1,0,0)");
      // rev1 con cantidad_actual > prev: 5 > 3
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (81,1,1,120,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle(id,id_computo,id_material,cantidad) VALUES (10,81,120,5)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 81, 0, ['id'=>1]);
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[9]);
      //$q->execute([9]);
      return $stmt->fetchColumn() == 2;
    }
  ],

  // ------------------------------------------------------------
  // B.B.2.2.1) Pedido no comprado + diff < 0 + cantAPedir <=0 → cancelar pedido
  // ------------------------------------------------------------
  'BB2221_cancelar_pedido' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0: cantidad=5, reservado=4 → pedido cantidad 2
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (90,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado) VALUES (11,90,130,5,4)");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (204,90,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_material,fecha_necesidad,cantidad,id_unidad_medida) VALUES (11,204,130,'2025-01-01',2,1)");
      // rev1: cantidad_actual=3 → cantAPedir = 3 - 4 = -1 <=0
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (91,1,1,120,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad) VALUES (12,91,130,3)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 91, 0, ['id'=>1]);
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[11]);
      //$q->execute([11]);
      return $stmt->fetchColumn() == 0;
    }
  ],

  // ------------------------------------------------------------
  // B.B.2.2.2) Pedido no comprado + diff <0 + cantAPedir >0 → modificar y reasignar
  // ------------------------------------------------------------
  'BB2222_modificar_pedido' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0: cantidad=8, reservado=2 → pedido cantidad 4
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (100,1,0,12,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado) VALUES (13,100,140,8,2)");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (205,100,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_material,fecha_necesidad,cantidad,id_unidad_medida) VALUES (13,205,140,'2025-01-01',4,1)");
      // rev1: cantidad_actual=6 → cantAPedir = 6 - 2 = 4 >0
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (101,1,1,12,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad) VALUES (14,101,140,6)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 101, 0, ['id'=>1]);
      $stmt = stmtLog($pdo, "SELECT cantidad,id_computo_detalle FROM pedidos_detalle WHERE id = ?",[13]);
      //$q->execute([13]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      //$row = $q->fetch(PDO::FETCH_ASSOC);
      return $row['cantidad'] == 4 && $row['id_computo_detalle'] == 14;
    }
  ],

  // ------------------------------------------------------------
  // B.B.3) Pedido “comprando” → no tocar ni reserva ni pedido
  // ------------------------------------------------------------
  'BB3_pedido_comprando_ni_tocar' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0: cantidad=5, any reserved
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (110,1,0,12,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,reservado) VALUES (15,110,150,5,1)");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (206,110,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_material,fecha_necesidad,cantidad,comprado,id_unidad_medida) VALUES (15,206,150,'2025-01-01',3,1,1)");
      // rev1: cantidad_actual different e.g. 2
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (111,1,1,12,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad) VALUES (16,111,150,2)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 111, 0, ['id'=>1]);
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id=15");
      $r = $stmt->fetchColumn();
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[15]);
      $p = $stmt->fetchColumn();
      // Reserva intacta y pedido intacto
      return $r == 1 && $p == 3;
    }
  ],


];

echo "=== INICIO DE TESTS RÁPIDOS ===<br><br>";

foreach ($tests as $name => $tc) {
  try {
    $pdo->beginTransaction();
    $tc['setup']($pdo);
    $ok = $tc['test']($pdo);
    $pdo->rollBack();
    echo str_pad($name, 45) . ($ok ? "[ OK ]<hr>" : "<span style='color:red'>[ FAIL ]</span><hr>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo str_pad($name, 45) . "<span style='color:red'>[ ERROR: " . $e->getMessage() . " ]</span><hr>";
  }
}

echo "<br>=== FIN DE TESTS ===";

