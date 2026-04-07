<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();
$modoDebug = 0;

$id = $_GET['id'];
$idComputo = $_GET['idComputo'];

try {

  $sql = "SELECT cd.id_computo, cd.id, cd.id_material, m.concepto, cd.cantidad, cd.reservado, cd.comprado, m.id_unidad_medida
          FROM computos_detalle cd 
          INNER JOIN materiales m ON m.id = cd.id_material 
          WHERE cd.id = ?";
  $q = $pdo->prepare($sql);
  if (!$q->execute([$id])) {
    throw new Exception("Error al consultar computos_detalle: " . implode(" | ", $q->errorInfo()));
  }
  $data = $q->fetch(PDO::FETCH_ASSOC);

  if (!$data) {
    throw new Exception("No se encontró el registro en computos_detalle con id = $id");
  }

  if ($modoDebug === 1) {
    echo "<h3>1) Datos del computo_detalle:</h3>";
    var_dump($data);
    echo "<br>";
  }

  $reservado = $data['reservado'];
  $id_computo = $data['id_computo'];
  $id_material = $data['id_material'];
  $id_unidad_medida = $data['id_unidad_medida'];

  if ($reservado <= 0) {
    $pdo->rollBack();
    Database::disconnect();
    header("Location: verComputo.php?id=" . $idComputo);
    die();
  }

  $sqlEgreso = "SELECT e.id AS id_egreso, ed.id AS id_egreso_detalle, ed.id_detalle_ingreso, 
                       ed.cantidad, ed.cantidad_reservada
                FROM egresos e
                INNER JOIN egresos_detalle ed ON ed.id_egreso = e.id
                WHERE e.nro = ? 
                  AND e.id_tipo_egreso = 2 
                  AND ed.id_material = ?
                  AND ed.cantidad_reservada > 0";
  $qEgreso = $pdo->prepare($sqlEgreso);
  if (!$qEgreso->execute([$id_computo, $id_material])) {
    throw new Exception("Error al consultar egresos_detalle: " . implode(" | ", $qEgreso->errorInfo()));
  }
  $egresosDetalle = $qEgreso->fetchAll(PDO::FETCH_ASSOC);

  if ($modoDebug === 1) {
    echo "<h3>2) Egresos detalle encontrados:</h3>";
    var_dump($egresosDetalle);
    echo "<br>Cantidad de registros: " . count($egresosDetalle) . "<br>";
  }

  $sqlCuenta = "SELECT id FROM cuentas WHERE id_usuario = ? AND activo = 1 AND anulado = 0 LIMIT 1";
  $qCuenta = $pdo->prepare($sqlCuenta);
  if (!$qCuenta->execute([$_SESSION['user']['id']])) {
    throw new Exception("Error al consultar cuentas: " . implode(" | ", $qCuenta->errorInfo()));
  }
  $dC = $qCuenta->fetch(PDO::FETCH_ASSOC);
  $id_cuenta_recibe = $dC ? $dC['id'] : 1;

  if ($modoDebug === 1) {
    echo "<h3>3) Cuenta recibe: $id_cuenta_recibe</h3><br>";
  }

  $sqlIngreso = "INSERT INTO ingresos (fecha_hora, id_tipo_ingreso, nro, id_cuenta_recibe, observaciones, nro_remito, fecha_remito) 
                 VALUES (NOW(), 2, ?, ?, 'Cancelación de reserva de stock', '', NULL)";
  $qIngreso = $pdo->prepare($sqlIngreso);
  if (!$qIngreso->execute([$id_computo, $id_cuenta_recibe])) {
    throw new Exception("Error al insertar en ingresos: " . implode(" | ", $qIngreso->errorInfo()));
  }
  $idIngreso = $pdo->lastInsertId();

  if ($modoDebug === 1) {
    echo "<h3>4) Ingreso creado con ID: $idIngreso</h3><br>";
  }

  if (!empty($egresosDetalle)) {
    if ($modoDebug === 1) {
      echo "<h3>5) CAMINO A: Restaurando lotes originales (sin crear duplicados)</h3><br>";
    }

    $sqlRestaurarIng = "UPDATE ingresos_detalle SET saldo = saldo + ?, cantidad_egresada = cantidad_egresada - ? WHERE id = ?";
    $qRestaurarIng = $pdo->prepare($sqlRestaurarIng);

    $sqlUpdEgDet = "UPDATE egresos_detalle SET cantidad_reservada = 0 WHERE id = ?";
    $qUpdEgDet = $pdo->prepare($sqlUpdEgDet);

    $sqlInsIngDetTraza = "INSERT INTO ingresos_detalle (id_ingreso, id_material, id_unidad_medida, cantidad, cantidad_egresada, saldo) 
                          VALUES (?, ?, ?, ?, ?, 0)";
    $qInsIngDetTraza = $pdo->prepare($sqlInsIngDetTraza);

    foreach ($egresosDetalle as $ed) {
      $cantDevolver = $ed['cantidad_reservada'];

      if ($cantDevolver > 0) {
        if (!$qRestaurarIng->execute([$cantDevolver, $cantDevolver, $ed['id_detalle_ingreso']])) {
          throw new Exception("Error al restaurar saldo en ingresos_detalle id={$ed['id_detalle_ingreso']}: " . implode(" | ", $qRestaurarIng->errorInfo()));
        }

        if ($modoDebug === 1) {
          echo "- Restaurado saldo en ingresos_detalle id={$ed['id_detalle_ingreso']}, cantidad=$cantDevolver<br>";
        }

        if (!$qUpdEgDet->execute([$ed['id_egreso_detalle']])) {
          throw new Exception("Error al actualizar egresos_detalle id={$ed['id_egreso_detalle']}: " . implode(" | ", $qUpdEgDet->errorInfo()));
        }

        if ($modoDebug === 1) {
          echo "- Egreso detalle id={$ed['id_egreso_detalle']} marcado como devuelto<br>";
        }

        if (!$qInsIngDetTraza->execute([$idIngreso, $id_material, $id_unidad_medida, $cantDevolver, $cantDevolver])) {
          throw new Exception("Error al insertar trazabilidad en ingresos_detalle: " . implode(" | ", $qInsIngDetTraza->errorInfo()));
        }

        if ($modoDebug === 1) {
          echo "- Registro de trazabilidad creado (saldo=0): material=$id_material, cantidad=$cantDevolver<br>";
        }
      }
    }
  } else {
    if ($modoDebug === 1) {
      echo "<h3>5) CAMINO B: No se encontraron egresos detalle. Creando ingreso detalle con saldo.</h3><br>";
    }

    $sqlInsIngDet = "INSERT INTO ingresos_detalle (id_ingreso, id_material, id_unidad_medida, cantidad, cantidad_egresada, saldo) 
                     VALUES (?, ?, ?, ?, 0, ?)";
    $qInsIngDet = $pdo->prepare($sqlInsIngDet);
    if (!$qInsIngDet->execute([$idIngreso, $id_material, $id_unidad_medida, $reservado, $reservado])) {
      throw new Exception("Error al insertar ingreso_detalle (camino B): " . implode(" | ", $qInsIngDet->errorInfo()));
    }

    if ($modoDebug === 1) {
      echo "- Ingreso detalle creado (con saldo): material=$id_material, cantidad=$reservado, saldo=$reservado<br>";
    }
  }

  $sql = "UPDATE computos_detalle SET saldo = saldo + ?, reservado = 0 WHERE id = ?";
  $q = $pdo->prepare($sql);
  if (!$q->execute([$reservado, $id])) {
    throw new Exception("Error al actualizar computos_detalle: " . implode(" | ", $q->errorInfo()));
  }

  if ($modoDebug === 1) {
    echo "<h3>6) Computo detalle actualizado: saldo += $reservado, reservado = 0</h3><br>";
  }

  $ok = marcarComputoGestionandoOTerminado($pdo, $id_computo);

  if ($modoDebug === 1) {
    echo "<h3>7) Estado del cómputo verificado</h3><br>";
  }

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) 
          VALUES (NOW(), ?, 'Cancelación de reserva de stock', 'Cómputos', ?)";
  $q = $pdo->prepare($sql);
  $link = "verComputo.php?id=$id_computo";
  if (!$q->execute([$_SESSION['user']['id'], $link])) {
    throw new Exception("Error al insertar log: " . implode(" | ", $q->errorInfo()));
  }

  if ($modoDebug === 1) {
    echo "<h3>8) Log creado</h3><br>";
    echo "<br><strong>MODO DEBUG: Se hizo rollback. Nada se guardó.</strong>";
    $pdo->rollBack();
    die();
  } else {
    $pdo->commit();
  }

  Database::disconnect();

  header("Location: verComputo.php?id=" . $idComputo);
} catch (Exception $e) {
  $pdo->rollBack();
  Database::disconnect();
  echo "<h3 style='color:red;'>⚠ Error durante la cancelación de reserva:</h3>";
  echo "<p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
  echo "<br><a href='verComputo.php?id=" . htmlspecialchars($idComputo) . "'>Volver al cómputo</a>";
  die();
}
