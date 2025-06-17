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

$b="B) si la revisión anterior del cómputo tiene estado “Gestionando”, comparamos ambos cómputos línea por línea (solo líneas que no tengan estado Cancelado) y si<br>";
$ba=$b."&nbsp;A) tiene un concepto Eliminado:<br>";
$ba1=$ba."&nbsp;&nbsp;1) tiene reserva -> la borramos y se notifica al que gestiona cómputos";
$ba2=$ba."&nbsp;&nbsp;2) tiene pedido y NO se está comprando -> se cancela en el pedido y sigue asociado a la revisión del cómputo anterior. Se notifica al que gestiona cómputos y al comprador";
$ba3=$ba."&nbsp;&nbsp;3) tiene pedido y se está comprando -> solo notifico al que gestiona cómputos y al comprador y sigue asociado a la revisión del cómputo anterior (requerirá intervención humana)";

$bb=$b."&nbsp;B) cambia la cantidad de un concepto (en más o en menos)<br>";

$bb1=$bb."&nbsp;&nbsp;1) tiene reserva:<br>";

$bb11=$bb1."&nbsp;&nbsp;&nbsp;1) y la nueva cantidad es mayor a la reserva, no hago nada y notifico al que gestiona cómputos";
$bb12=$bb1."&nbsp;&nbsp;&nbsp;2) y la nueva cantidad es inferior a la reserva, modifico la reserva y notifico al que gestiona cómputos";

$bb2=$bb."&nbsp;&nbsp;2) si tiene pedido y no se está comprando -> evaluamos si nueva cant es mayor o menor a cantidad anterior y:<br>";

$bb21=$bb2."&nbsp;&nbsp;&nbsp;1) si nueva cant es mayor a cant anterior: -> no hago nada y notifico al que gestiona cómputos y al comprador";

$bb22=$bb2."&nbsp;&nbsp;&nbsp;2) si nueva cant es menor a cant anterior -> calculamos cantidad a pedir (cant a pedir = nueva cant - reservado):<br>";

$bb221=$bb22."&nbsp;&nbsp;&nbsp;&nbsp;1) si cantidad a pedir es 0 -> cancelo en el pedido y sigue asociado a la revisión del cómputo anterior. Se notifica al que gestiona cómputos y al comprador";
$bb222=$bb22."&nbsp;&nbsp;&nbsp;&nbsp;2) si cantidad a pedir es mayor a 0 -> modifico la cantidad en el pedido, hago que el pedido ahora apunte a esta revisión del cómputo y a esta línea de detalle del cómputo, y notifico al que gestiona cómputos y al comprador";
$bb3=$bb."&nbsp;&nbsp;3) si tiene pedido y se está comprando -> notifico al que gestiona cómputos y al comprador. El ítem sigue asociado a la revisión del cómputo anterior.";

$tests = [

  // ------------------------------------------------------------
  // A) Revisión anterior en estado ≠ 4 (p.ej. 3) → superada sin más
  // ------------------------------------------------------------
  'A) si la revisión anterior del cómputo tiene estado diferente a “Gestionando” se pasa dicha revisión y su detalle a “Superado”' => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0 con estado 3 (no gestionando)
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (20,1,0,120,3,4001866)");
      // rev1
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (21,1,1,120,3,4001866)");
    },
    'test' => function(PDO $pdo) {
      $texto = superarRevisionAnterior($pdo, 21, 1);
      // Verifico que estado quedó a 7
      $stmt = stmtLog($pdo, "SELECT c.id_estado,ec.estado FROM computos c JOIN estados_computos ec ON c.id_estado=ec.id WHERE c.id = ?",[20]);
      //$stmt->execute([20]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      echo "El estado de la revisión anterior es: ".$row["estado"]."<br>";
      $supradoOK=strpos($texto, 'superada');
      if($supradoOK !== false) {
        echo "La funcion que supera la revisión anterior se ejectuó correctamente.<br>";
      } else {
        echo "La funcion que supera la revisión anterior NO se ejectuó correctamente.<br>";
      }
      return $row["id_estado"] == 7 && $supradoOK !== false;
    }
  ],

  // ------------------------------------------------------------
  // B.A.1) Concepto eliminado + tenía reserva → borramos reserva
  // ------------------------------------------------------------
  //'B.A.1: eliminar_concepto_reservado_en_revision_anterior' => [
  "$ba1" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (30,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (3,30,70,5,5,4,0,0,0,'2025-01-01','')");
      // no hay pedido para este caso
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (31,1,1,120,3,4001866)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 31, 1);
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = ?",[3]);
      //$stmt->execute([3]);
      return $stmt->fetchColumn() == 0;
    }
  ],

  // ------------------------------------------------------------
  // B.A.2) Concepto eliminado + pedido no “comprando” → cancelar pedido
  // ------------------------------------------------------------
  //'B.A.2: cancelar_pedido' => [
  "$ba2" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (40,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (4,40,80,6,3,0,0,0,0,'2025-01-01','')");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (201,40,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_computo_detalle ,id_material,fecha_necesidad,cantidad,id_unidad_medida,reservado,comprado) VALUES (4,201,4,80,'2025-01-01',3,1,0,0)");
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (41,1,1,120,3,4001866)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 41, 1);
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[4]);
      //$stmt->execute([4]);
      return $stmt->fetchColumn() == 0;
    }
  ],

  // ------------------------------------------------------------
  // B.A.3) Concepto eliminado + pedido “comprando” → no tocar cantidad
  // ------------------------------------------------------------
  //'B.A.3: pedido_comprando' => [
  "$ba3" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (50,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (5,50,90,7,3,0,0,0,4,'2025-01-01','')");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (202,50,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_computo_detalle ,id_material,fecha_necesidad,cantidad,id_unidad_medida,reservado,comprado) VALUES (5,202,5,90,'2025-01-01',4,1,0,1)"); // comprado=1
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (51,1,1,120,3,4001866)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 51, 1);
      $stmt = stmtLog($pdo, "SELECT cantidad,id_computo_detalle FROM pedidos_detalle WHERE id = ?",[5]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $stmt2 = stmtLog($pdo, "SELECT comprado FROM computos_detalle WHERE id = ?",[5]);
      $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
      //$stmt->execute([5]);
      return ($row["cantidad"]==4 and $row2["comprado"]==4 and $row["id_computo_detalle"]==5); // no se toca
    }
  ],

  // ------------------------------------------------------------
  // B.B.1.1) Cantidad aumentada > reserva → no tocar reserva
  // ------------------------------------------------------------
  //'B.B.1.1: no_tocar_reserva' => [
  "$bb11" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (60,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado) VALUES (6,60,100,5,0,5)");
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (61,1,1,120,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado) VALUES (7,61,100,7,7,0)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 61, 1);
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = ?",[6]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $stmt2 = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = ?",[7]);
      $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
      echo "Reserva anterior: ".$row["reservado"]."<br>";
      echo "Reserva actual: ".$row2["reservado"]."<br>";
      return $row["reservado"] == 0 and $row2["reservado"]==5; // reserva intacta
    }
  ],

  // ------------------------------------------------------------
  // B.B.1.2) Cantidad inferior a reserva → ajustar previa e heredar a actual
  // ------------------------------------------------------------
  //'B.B.1.2: ajustar_y_heredar_reserva' => [
  "$bb12" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // Computo rev0
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (70,1,0,120,4,4001866)");
      // Detalle rev0: cantidad=10, reservado=6
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (7,70,110,10,4,6,0,0,0,'2025-01-01','')");
      // Computo rev1
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (71,1,1,120,3,4001866)");
      // Detalle rev1: cantidad_actual=4
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (8,71,110,4,4,0,0,0,0,'2025-01-01','')");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 71, 1);
      // prev.reservado ajustado a 6 + (4-10) = 0
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = 7");
      $r0 = $stmt->fetchColumn();
      // actual.reservado heredado = min(cantidad_actual=4, nuevaReserva=0) = 0
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id = 8");
      $r1 = $stmt->fetchColumn();
      return $r0 == 0 && $r1 == 4;
    }
  ],

  // ------------------------------------------------------------
  // B.B.2.1) Pedido no comprado + diff > 0 → no tocar pedido
  // ------------------------------------------------------------
  "$bb21" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (80,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (9,80,120,3,1,0,0,0,0,'2025-01-01','')");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (203,80,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_computo_detalle ,id_material,fecha_necesidad,cantidad,id_unidad_medida,reservado,comprado) VALUES (9,203,9,120,'2025-01-01',2,1,0,0)");
      // rev1 con cantidad_actual > prev: 5 > 3
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (81,1,1,120,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (10,81,120,5,5,0,0,0,0,'2025-01-01','')");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 81, 1);
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[9]);
      //$q->execute([9]);
      return $stmt->fetchColumn() == 2;
    }
  ],

  // ------------------------------------------------------------
  // B.B.2.2.1) Pedido no comprado + diff < 0 + cantAPedir <=0 → cancelar pedido
  // ------------------------------------------------------------
  //'B.B.2.2.2.1: cancelar_pedido' => [
  "$bb221" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0: cantidad=5, reservado=2, pedido 2 -> saldo 1
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (90,1,0,120,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (11,90,130,4,0,2,0,0,0,'2025-01-01','')");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (204,90,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_computo_detalle ,id_material,fecha_necesidad,cantidad,id_unidad_medida) VALUES (11,204,11,130,'2025-01-01',2,1)");
      // rev1: cantidad_actual=3, reservado=2, cantAPedir = 1, saldo=0
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (91,1,1,120,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,cancelado,aprobado,comprado,fecha_necesidad,comentarios) VALUES (12,91,130,2,2,0,0,0,0,'2025-01-01','')");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 91, 1);
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[11]);
      //$q->execute([11]);
      return $stmt->fetchColumn() == 0;
    }
  ],

  // ------------------------------------------------------------
  // B.B.2.2.2) Pedido no comprado + diff <0 + cantAPedir >0 → modificar y reasignar
  // ------------------------------------------------------------
  //'B.B.2.2.2.2: modificar_pedido' => [
  "$bb222" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0: cantidad=8, reservado=4 → pedido cantidad 4
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (100,1,0,12,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado) VALUES (13,100,140,8,0,4)");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (205,100,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_computo_detalle ,id_material,fecha_necesidad,cantidad,id_unidad_medida) VALUES (13,205,13,140,'2025-01-01',4,1)");
      // rev1: cantidad_actual=6 → cantAPedir = 2 - 2 = 4 >0
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (101,1,1,12,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado) VALUES (14,101,140,6,6,0)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 101, 1);
      $stmt = stmtLog($pdo, "SELECT cantidad,id_computo_detalle FROM pedidos_detalle WHERE id = ?",[13]);
      //$q->execute([13]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      //$row = $q->fetch(PDO::FETCH_ASSOC);
      return $row['cantidad'] == 2 && $row['id_computo_detalle'] == 14;
    }
  ],

  // ------------------------------------------------------------
  // B.B.3) Pedido “comprando” → no tocar ni reserva ni pedido
  // ------------------------------------------------------------
  //'B.B.3: pedido_comprando_ni_tocar' => [
  "$bb3" => [
    'setup' => function(PDO $pdo) {
      deleteTables($pdo);
      // rev0: cantidad=5, any reserved
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (110,1,0,12,4,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,comprado) VALUES (15,110,150,5,1,1,3)");
      execLog($pdo, "INSERT INTO pedidos (id,id_computo,id_cuenta_recibe,id_estado) VALUES (206,110,4000074,1)");
      execLog($pdo, "INSERT INTO pedidos_detalle (id,id_pedido,id_computo_detalle ,id_material,fecha_necesidad,cantidad,comprado,id_unidad_medida) VALUES (15,206,15,150,'2025-01-01',3,3,1)");
      // rev1: cantidad_actual different e.g. 2
      execLog($pdo, "INSERT INTO computos (id,nro,nro_revision,id_tarea,id_estado,id_cuenta_solicitante) VALUES (111,1,1,12,3,4001866)");
      execLog($pdo, "INSERT INTO computos_detalle (id,id_computo,id_material,cantidad,saldo,reservado,comprado) VALUES (16,111,150,2,2,0,0)");
    },
    'test' => function(PDO $pdo) {
      superarRevisionAnterior($pdo, 111, 1);
      $stmt = stmtLog($pdo, "SELECT reservado FROM computos_detalle WHERE id=15");
      $r = $stmt->fetchColumn();
      $stmt = stmtLog($pdo, "SELECT cantidad FROM pedidos_detalle WHERE id = ?",[15]);
      $p = $stmt->fetchColumn();
      // Reserva intacta y pedido intacto
      return $r == 0 && $p == 3;
    }
  ],

];

echo "=== INICIO DE TESTS RÁPIDOS ===<br><br>";

foreach ($tests as $name => $tc) {
  echo "<h3>$name</h3>";
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

