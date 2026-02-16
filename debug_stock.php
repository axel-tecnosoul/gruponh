<?php
  require 'config.php';
  require 'database.php';

  $idMaterial = $_GET['id'] ?? null;

  if (!$idMaterial) {
      die("Debes indicar el ID del material en la URL. Ejemplo: debug_stock.php?id=123");
  }

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $pdo->prepare("SELECT concepto FROM materiales WHERE id = ?");
  $stmt->execute([$idMaterial]);
  $material = $stmt->fetch(PDO::FETCH_ASSOC);
  $nombreMaterial = $material ? $material['concepto'] : "Desconocido";

  echo "<h1>Auditoría de Material: $nombreMaterial (ID: $idMaterial)</h1>";
  echo "<p>Si el total es negativo, significa que hay más salidas registradas que entradas.</p>";

  echo "<h3>🟢 INGRESOS (Entradas sumadas)</h3>";
  $sqlIn = "SELECT 
              ingresos_detalle.id AS id_detalle, 
              ingresos.nro, 
              ingresos_detalle.cantidad, 
              date_format(ingresos.fecha_hora,'%d/%m/%Y') as fecha 
            FROM ingresos_detalle 
            JOIN ingresos ON ingresos.id = ingresos_detalle.id_ingreso 
            WHERE ingresos_detalle.id_material = $idMaterial";

  $qIn = $pdo->query($sqlIn);
  $totalIn = 0;

  echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>
          <tr style='background:#e0ffe0'>
              <th>ID Detalle</th>
              <th>Nro Ingreso</th>
              <th>Fecha</th>
              <th>Cantidad</th>
          </tr>";

  foreach($qIn as $row){
      echo "<tr>
              <td>{$row['id_detalle']}</td>
              <td>{$row['nro']}</td>
              <td>{$row['fecha']}</td>
              <td>{$row['cantidad']}</td>
            </tr>";
      $totalIn += $row['cantidad'];
  }
  echo "<tr><td colspan='3' align='right'><b>TOTAL ENTRADAS:</b></td><td><b>$totalIn</b></td></tr>";
  echo "</table>";

  echo "<h3>🔴 EGRESOS (Salidas sumadas)</h3>";
  $sqlOut = "SELECT 
              egresos_detalle.id AS id_detalle, 
              egresos.nro, 
              egresos_detalle.cantidad, 
              date_format(egresos.fecha_hora,'%d/%m/%Y') as fecha 
            FROM egresos_detalle 
            JOIN egresos ON egresos.id = egresos_detalle.id_egreso 
            WHERE egresos_detalle.id_material = $idMaterial";

  $qOut = $pdo->query($sqlOut);
  $totalOut = 0;

  echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>
          <tr style='background:#ffe0e0'>
              <th>ID Detalle</th>
              <th>Nro Egreso</th>
              <th>Fecha</th>
              <th>Cantidad</th>
          </tr>";

  foreach($qOut as $row){
      echo "<tr>
              <td>{$row['id_detalle']}</td>
              <td>{$row['nro']}</td>
              <td>{$row['fecha']}</td>
              <td>{$row['cantidad']}</td>
            </tr>";
      $totalOut += $row['cantidad'];
  }
  echo "<tr><td colspan='3' align='right'><b>TOTAL SALIDAS:</b></td><td><b>$totalOut</b></td></tr>";
  echo "</table>";

  $diferencia = $totalIn - $totalOut;
  $color = $diferencia < 0 ? "red" : "green";

  echo "<h2 style='color:$color'>
          STOCK FINAL CALCULADO: $totalIn (Entradas) - $totalOut (Salidas) = $diferencia
        </h2>";

  Database::disconnect();
?>