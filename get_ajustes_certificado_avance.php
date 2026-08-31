<?php
require("config.php");
require 'database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id_certificado_avance = filter_input(INPUT_POST, 'id_certificado_avance', FILTER_VALIDATE_INT);
if (!$id_certificado_avance) {
  echo json_encode([]);
  exit;
}

function ajustesSalida(array $rows): void {
  echo json_encode(array_values($rows));
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$aAjustes = [];

$sql = "SELECT DATE_FORMAT(ca.fecha,'%d/%m/%Y') AS fecha,
               ca.tipo_ajuste,
               ca.impacto,
               ca.observaciones,
               ca.monto,
               u.usuario AS nombre_usuario
        FROM certificados_ajustes ca
        LEFT JOIN usuarios u ON u.id = ca.id_usuario
        WHERE ca.id_certificado_avance = ?
        ORDER BY ca.fecha, ca.id";
try {
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_avance]);
  foreach ($q as $row) {
    $monto = (float) $row["monto"];
    $impacto = (int) ($row["impacto"] ?? -1) === 1 ? 'Suma' : 'Resta';
    $montoMostrado = ($impacto === 'Suma' ? '' : '-') . number_format(abs($monto), 2, ',', '.');
    $aAjustes[] = [
      0 => $row["fecha"],
      1 => $row["tipo_ajuste"],
      2 => $impacto,
      3 => '$' . $montoMostrado,
      4 => (string) ($row["observaciones"] ?? ''),
      5 => (string) ($row["nombre_usuario"] ?? ''),
    ];
  }
} catch (PDOException $e) {
  // Compatibilidad si la columna impacto aun no existe.
  try {
    $sqlLegacy = "SELECT DATE_FORMAT(ca.fecha,'%d/%m/%Y') AS fecha,
                         ca.tipo_ajuste,
                         ca.observaciones,
                         ca.monto,
                         u.usuario AS nombre_usuario
                  FROM certificados_ajustes ca
                  LEFT JOIN usuarios u ON u.id = ca.id_usuario
                  WHERE ca.id_certificado_avance = ?
                  ORDER BY ca.fecha, ca.id";
    $q = $pdo->prepare($sqlLegacy);
    $q->execute([$id_certificado_avance]);
    foreach ($q as $row) {
      $aAjustes[] = [
        0 => $row["fecha"],
        1 => $row["tipo_ajuste"],
        2 => 'Resta',
        3 => '-$' . number_format((float) $row["monto"], 2, ',', '.'),
        4 => (string) ($row["observaciones"] ?? ''),
        5 => (string) ($row["nombre_usuario"] ?? ''),
      ];
    }
  } catch (Throwable $e2) {
    error_log('get_ajustes_certificado_avance: ' . $e2->getMessage());
  }
}

Database::disconnect();
ajustesSalida($aAjustes);
