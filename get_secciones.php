<?php
require_once 'config.php';
require_once 'database.php';

$idPackingListRevision = filter_var($_POST['id_pl'] ?? null, FILTER_VALIDATE_INT);
if (!$idPackingListRevision || $idPackingListRevision < 1) {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'No se recibió un ID válido de Packing List.']);
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT pls.id AS id_seccion, pls.cantidad AS cantidad_seccion, pls.observaciones AS seccion,
               plc.id AS id_componente, plc.cantidad AS cantidad_componente,
               plc.observaciones AS observaciones_componente, ecpl.estado AS estado_componente,
               m.concepto, m.largo, m.peso_metro,
               lcc.id AS id_conjunto, lcc.nombre AS conjunto
        FROM packing_lists_secciones pls
        LEFT JOIN packing_lists_componentes plc ON plc.id_packing_list_seccion = pls.id
        LEFT JOIN listas_corte_conjuntos lcc ON lcc.id = plc.id_conjunto_lista_corte
        LEFT JOIN materiales m ON m.id = plc.id_concepto
        LEFT JOIN estados_componentes_packing_list ecpl ON ecpl.id = plc.id_estado_componente_packing_list
        WHERE pls.id_packing_list_revision = ?
        ORDER BY pls.id, plc.id";
$q = $pdo->prepare($sql);
$q->execute([$idPackingListRevision]);
$filas = $q->fetchAll(PDO::FETCH_ASSOC);

$pesosSecciones = [];
$pesosComponentes = [];
foreach ($filas as $fila) {
  $idSeccion = (int)$fila['id_seccion'];
  $idComponente = (int)($fila['id_componente'] ?? 0);
  $cantidadComponente = numeroListaCorte($fila['cantidad_componente'] ?? 0);
  $pesoComponente = 0.0;

  if (!empty($fila['id_conjunto'])) {
    $pesoComponente = round(calcularPesoConjunto($pdo, (int)$fila['id_conjunto']) * $cantidadComponente, 2);
  } elseif (!empty($fila['concepto'])) {
    $largo = numeroListaCorte($fila['largo'] ?? 0);
    $pesoMetro = numeroListaCorte($fila['peso_metro'] ?? 0);
    $pesoComponente = round(($largo * $pesoMetro / 1000) * $cantidadComponente, 2);
  }

  if ($idComponente > 0) {
    $pesosComponentes[$idComponente] = $pesoComponente;
  }
  $pesosSecciones[$idSeccion] = ($pesosSecciones[$idSeccion] ?? 0.0) + $pesoComponente;
}

$aSecciones = [];
foreach ($filas as $fila) {
  $idSeccion = (int)$fila['id_seccion'];
  $idComponente = (int)($fila['id_componente'] ?? 0);
  $nombreComponente = trim((string)($fila['concepto'] ?? '') . ' ' . (string)($fila['conjunto'] ?? ''));

  $aSecciones[] = [
    0 => $fila['seccion'],
    1 => $fila['cantidad_seccion'],
    2 => round($pesosSecciones[$idSeccion] ?? 0.0, 2),
    3 => $nombreComponente,
    4 => $fila['cantidad_componente'],
    5 => $pesosComponentes[$idComponente] ?? 0.0,
    6 => $fila['observaciones_componente'],
    7 => $fila['estado_componente'],
  ];
}

Database::disconnect();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($aSecciones, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
