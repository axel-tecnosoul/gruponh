<?php
require("config.php");
require 'database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id_certificado_maestro = filter_input(INPUT_POST, 'id_certificado_maestro', FILTER_VALIDATE_INT);
if (!$id_certificado_maestro) {
  echo json_encode([]);
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cm.id_occ, m.moneda
        FROM certificados_maestros cm
        INNER JOIN occ ON occ.id = cm.id_occ
        INNER JOIN monedas m ON m.id = cm.id_moneda
        WHERE cm.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_maestro]);
$cm = $q->fetch(PDO::FETCH_ASSOC);
if (!$cm) {
  echo json_encode([]);
  exit;
}
$id_occ = (int) $cm['id_occ'];
$moneda = (string) $cm['moneda'];

 $sql = "SELECT id, posicion, descripcion, cantidad, precio_unitario, descuento, subtotal
         FROM occ_detalles
         WHERE id_occ = ?
         ORDER BY posicion, id";
$q = $pdo->prepare($sql);
$q->execute([$id_occ]);
$items = [];
foreach ($q as $row) {
  $items[] = [
      'id' => (int) $row['id'],
      'posicion' => (string) $row['posicion'],
      'descripcion' => (string) $row['descripcion'],
    'cantidad' => (float) $row['cantidad'],
    'precio_unitario' => (float) $row['precio_unitario'],
    'descuento' => (float) $row['descuento'],
    'subtotal' => (float) $row['subtotal'],
  ];
}

$unidadesMap = [];
$sql = "SELECT id, unidad_medida FROM unidades_medida";
foreach ($pdo->query($sql) as $row) {
  $unidadesMap[(int) $row['id']] = (string) $row['unidad_medida'];
}

$sqlLotesBase = "SELECT aperturado, lote, modo_generacion,
                        COALESCE(MAX(monto_base_occ),0) AS monto_base_occ,
                        COALESCE(SUM(subtotal),0) AS subtotal_lote,
                        COUNT(*) AS cantidad_filas
                 FROM certificados_maestros_detalles
                 WHERE id_certificado_maestro = ?
                   AND aperturado IS NOT NULL AND aperturado <> ''
                   AND modo_generacion IN ('agrupar','separar')
                 GROUP BY aperturado, lote, modo_generacion
                 ORDER BY MAX(id) DESC";
$q = $pdo->prepare($sqlLotesBase);
$q->execute([$id_certificado_maestro]);
$lotesBase = $q->fetchAll(PDO::FETCH_ASSOC);

$grupos = [];
foreach ($lotesBase as $loteRow) {
  $aperturadoId = (string) $loteRow['aperturado'];

  $sql = "SELECT id_occ_detalle FROM certificados_maestros_lotes_occ_detalle
          WHERE id_certificado_maestro = ? AND aperturado = ?
          ORDER BY id_occ_detalle";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_maestro, $aperturadoId]);
  $occIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN, 0));

  $sql = "SELECT descripcion, id_unidad_medida, cantidad, incidencia_porcentaje, id_occ_detalle, posicion_aperturado
          FROM certificados_maestros_detalles
          WHERE id_certificado_maestro = ? AND aperturado = ?
          ORDER BY id";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_maestro, $aperturadoId]);

  $filas = [];
  foreach ($q as $r) {
    if (empty($occIds) && !empty($r['id_occ_detalle'])) {
      $occIds[] = (int) $r['id_occ_detalle'];
    }
    $filas[] = [
      'descripcion' => (string) $r['descripcion'],
      'unidad' => (string) ($unidadesMap[(int) $r['id_unidad_medida']] ?? ''),
      'cantidad' => (float) $r['cantidad'],
      'incidencia' => (float) $r['incidencia_porcentaje'],
      'posicion_aperturado' => (string) ($r['posicion_aperturado'] ?? ''),
    ];
  }

  $occIds = array_values(array_unique($occIds));
  if (empty($occIds)) {
    continue;
  }

  $grupos[] = [
    'occ_ids' => $occIds,
    'monto_base_occ' => (float) $loteRow['monto_base_occ'],
    'filas' => $filas,
  ];
}

Database::disconnect();

echo json_encode([
  'moneda' => $moneda,
  'items' => $items,
  'grupos' => $grupos,
]);
