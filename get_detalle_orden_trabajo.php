<?php
require("config.php");
require 'database.php';

$id_ot = intval($_POST['id_ot']);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT id_lista_corte FROM ordenes_trabajo WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_ot]);
$id_lista_corte = $q->fetchColumn();

if (!$id_lista_corte) {
  echo json_encode([]);
  Database::disconnect();
  exit;
}

$sql = "SELECT id, nombre, cantidad FROM listas_corte_conjuntos WHERE id_lista_corte = ? ORDER BY id";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte]);
$conjuntos = [];

while ($conj = $q->fetch(PDO::FETCH_ASSOC)) {
  $sqlPos = "SELECT lcp.id, lcp.posicion, lcp.cantidad AS cant_pos, m.concepto, GROUP_CONCAT(DISTINCT tp.tipo SEPARATOR ',') AS procesos, COALESCE(otd_total.cant_bajada_total,0) AS cant_bajada_total, otd.id AS id_detalle_ot, otd.cantidad AS cant_pedida, otd.cant_liberadas, otd.cant_reproceso, otd.cant_rechazadas, eotp.estado, eotp.id AS id_estado, date_format(COALESCE(otd.fecha_hora_ultima_modificacion, otd.fecha),'%d/%m/%y') AS fecha, u.usuario
            FROM lista_corte_posiciones lcp
            JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion = lcp.id
            JOIN materiales m ON lcp.id_material = m.id
            JOIN tipos_procesos tp ON lcpr.id_tipo_proceso = tp.id
            LEFT JOIN (
              SELECT otd.id_posicion, SUM(otd.cantidad) AS cant_bajada_total
              FROM ordenes_trabajo_detalle otd
              JOIN ordenes_trabajo ot ON ot.id = otd.id_orden_trabajo
              WHERE ot.id_estado_orden_trabajo IN (1,2,3,4)
              GROUP BY otd.id_posicion
            ) otd_total ON otd_total.id_posicion = lcp.id
            LEFT JOIN ordenes_trabajo_detalle otd ON otd.id_posicion = lcp.id AND otd.id_orden_trabajo = ?
            LEFT JOIN estados_orden_trabajo_posicion eotp ON otd.id_estado_orden_trabajo_posicion = eotp.id
            LEFT JOIN usuarios u ON u.id = otd.id_usuario
            WHERE lcp.id_lista_corte_conjunto = ?
            GROUP BY lcp.id";
  $qPos = $pdo->prepare($sqlPos);
  $qPos->execute([$id_ot, $conj['id']]);

  $posiciones = [];
  $saldo_conj = $conj['cantidad'];

  while ($pos = $qPos->fetch(PDO::FETCH_ASSOC)) {
    $cant_total = $conj['cantidad'] * $pos['cant_pos'];
    $saldo = $cant_total - $pos['cant_bajada_total'];
    $pos['cant_total'] = $cant_total;
    $pos['cant_bajada'] = $pos['cant_bajada_total'];
    $pos['saldo'] = $saldo;
    unset($pos['cant_bajada_total']);
    $posiciones[] = $pos;

    $sets_disp = $pos['cant_pos'] > 0 ? floor($saldo / $pos['cant_pos']) : 0;
    if ($sets_disp < $saldo_conj) {
      $saldo_conj = $sets_disp;
    }
  }

  $conj['posiciones'] = $posiciones;
  $conj['cant_bajada'] = $conj['cantidad'] - $saldo_conj;
  $conj['saldo'] = $saldo_conj;
  $conjuntos[] = $conj;
}

Database::disconnect();
echo json_encode($conjuntos);
?>