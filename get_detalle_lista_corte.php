<?php
require("config.php");
require 'database.php';

$id_lc = intval($_POST['id_lc']);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT id, nombre, cantidad FROM listas_corte_conjuntos WHERE id_lista_corte = ? ORDER BY id";
$q = $pdo->prepare($sql);
$q->execute([$id_lc]);

$conjuntos = [];
while ($conj = $q->fetch(PDO::FETCH_ASSOC)) {
  $sqlPos = "SELECT 
      lcp.posicion,
      m.concepto,
      lcp.cantidad AS cant_pos,
      (lcc.cantidad * lcp.cantidad) AS cant_total,
      COALESCE(otd.cant_ot,0) AS cant_ot,
      COALESCE(otd.cant_liberadas,0) AS cant_liberadas,
      COALESCE(otd.cant_rechazadas,0) AS cant_rechazadas,
      COALESCE(otd.cant_reproceso,0) AS cant_reproceso,
      GROUP_CONCAT(DISTINCT tp.tipo SEPARATOR ',') AS procesos
    FROM lista_corte_posiciones lcp
    INNER JOIN listas_corte_conjuntos lcc ON lcc.id = lcp.id_lista_corte_conjunto
    LEFT JOIN materiales m ON lcp.id_material = m.id
    LEFT JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion = lcp.id
    LEFT JOIN tipos_procesos tp ON lcpr.id_tipo_proceso = tp.id
    LEFT JOIN (
      SELECT otd.id_posicion,
             SUM(otd.cant_liberadas) AS cant_liberadas,
             SUM(otd.cant_rechazadas) AS cant_rechazadas,
             SUM(otd.cant_reproceso) AS cant_reproceso,
             SUM(CASE WHEN ot.id_estado_orden_trabajo IN (3,4) THEN otd.cantidad ELSE 0 END) AS cant_ot
      FROM ordenes_trabajo_detalle otd
      INNER JOIN ordenes_trabajo ot ON ot.id = otd.id_orden_trabajo
      GROUP BY otd.id_posicion
    ) otd ON otd.id_posicion = lcp.id
    WHERE lcp.id_lista_corte_conjunto = ?
    GROUP BY lcp.id
    ORDER BY lcp.posicion";

  $qPos = $pdo->prepare($sqlPos);
  $qPos->execute([$conj['id']]);

  $posiciones = [];
  $totalCantOt = 0;
  $totalCantLib = 0;
  $saldoConj = $conj['cantidad'];

  while ($pos = $qPos->fetch(PDO::FETCH_ASSOC)) {
    $setsDisp = $pos['cant_pos'] > 0 ? floor(($pos['cant_total'] - $pos['cant_ot'] - $pos['cant_liberadas'] - $pos['cant_rechazadas'] - $pos['cant_reproceso']) / $pos['cant_pos']) : 0;
    if ($setsDisp < $saldoConj) {
      $saldoConj = $setsDisp;
    }
    $totalCantOt += $pos['cant_ot'];
    $totalCantLib += $pos['cant_liberadas'];
    $posiciones[] = $pos;
  }

  $conj['posiciones'] = $posiciones;
  $conj['cant_ot'] = $totalCantOt;
  $conj['cant_lib'] = $totalCantLib;
  $conj['saldo'] = max(0, $saldoConj);
  $conjuntos[] = $conj;
}

Database::disconnect();
echo json_encode($conjuntos);
?>
