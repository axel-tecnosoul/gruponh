<?php
require("config.php");
require 'database.php';

$id_certificado_avance = $_POST['id_certificado_avance'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Obtener moneda
$sqlMoneda = "SELECT m.moneda 
              FROM certificados_avances_cabecera ca
              INNER JOIN certificados_maestros cm ON cm.id = ca.id_certificado_maestro
              INNER JOIN monedas m ON m.id = cm.id_moneda
              WHERE ca.id = ?";
$qMoneda = $pdo->prepare($sqlMoneda);
$qMoneda->execute([$id_certificado_avance]);
$moneda = $qMoneda->fetchColumn() ?: 'U$S';

// Obtener ID certificado maestro
$sqlIdCm = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
$qIdCm = $pdo->prepare($sqlIdCm);
$qIdCm->execute([$id_certificado_avance]);
$id_certificado_maestro = $qIdCm->fetchColumn();

// Obtener ID OCC
$sqlIdOcc = "SELECT id_occ FROM certificados_maestros WHERE id = ?";
$qIdOcc = $pdo->prepare($sqlIdOcc);
$qIdOcc->execute([$id_certificado_maestro]);
$id_occ = $qIdOcc->fetchColumn();

// Traer OCC detalles
$sqlOccDetalles = "SELECT id, posicion, descripcion, cantidad, precio_unitario, descuento, subtotal
                   FROM occ_detalles
                   WHERE id_occ = ?
                   ORDER BY posicion, id";
$qOccDetalles = $pdo->prepare($sqlOccDetalles);
$qOccDetalles->execute([$id_occ]);
$occ_detalles = $qOccDetalles->fetchAll(PDO::FETCH_ASSOC);

// Traer CMD con toda la información de agrupación
// NOTA: Ahora usamos cad.cantidad_acumulado como "acumulado" (el valor guardado en el CA actual)
$sqlCmd = "SELECT cmd.id AS id_certificado_maestro_detalle,
                  cmd.id_occ_detalle,
                  cmd.id_tipo_item_certificado,
                  tic.tipo,
                  cmd.descripcion,
                  cmd.posicion_aperturado,
                  cmd.cantidad,
                  cmd.id_unidad_medida,
                  um.unidad_medida,
                  cmd.precio_unitario AS precio_unitario_cm,
                  cmd.subtotal AS subtotal_cm,
                  cmd.incidencia_porcentaje,
                  cmd.monto_base_occ,
                  cmd.aperturado,
                  cmd.lote,
                  cmd.modo_generacion,
                  cmd.id_proyecto,
                  CONCAT_WS(' - ',
                    NULLIF(CONCAT_WS('-', s.nro_sitio, s.nro_subsitio, p.nro), ''),
                    NULLIF(p.nombre, '')
                  ) AS proyecto,
                  cad.id AS id_certificado_avance_detalle,
                  cad.cantidad_actual,
                  cad.cantidad_acumulado AS acumulado,
                  cad.subtotal AS subtotal_ca
           FROM certificados_maestros_detalles cmd
           INNER JOIN tipos_item_certificado tic ON tic.id = cmd.id_tipo_item_certificado
           INNER JOIN unidades_medida um ON um.id = cmd.id_unidad_medida
           LEFT JOIN proyectos p ON p.id = cmd.id_proyecto
           LEFT JOIN sitios s ON s.id = p.id_sitio
           LEFT JOIN certificados_avances_detalle cad
             ON cad.id_certificado_maestro_detalle = cmd.id
            AND cad.id_certificado_avance = ?
           WHERE cmd.id_certificado_maestro = ?
           ORDER BY cmd.lote, cmd.aperturado, cmd.id";

$qCmd = $pdo->prepare($sqlCmd);
$qCmd->execute([$id_certificado_avance, $id_certificado_maestro]);
$filas_detalle = $qCmd->fetchAll(PDO::FETCH_ASSOC);

// Traer relaciones OCC (para saber qué occ_ids pertenecen a cada aperturado)
$sqlRelaciones = "SELECT aperturado, id_occ_detalle
                  FROM certificados_maestros_lotes_occ_detalle
                  WHERE id_certificado_maestro = ?
                  ORDER BY id";
$qRelaciones = $pdo->prepare($sqlRelaciones);
$qRelaciones->execute([$id_certificado_maestro]);
$relaciones_occ = $qRelaciones->fetchAll(PDO::FETCH_ASSOC);

// Armar estructura de grupos por OCC
$occ_ids_por_aperturado = [];
foreach ($relaciones_occ as $relacion) {
  $aperturado_relacion = (string) $relacion['aperturado'];
  $id_occ_detalle_relacion = (int) $relacion['id_occ_detalle'];
  $occ_ids_por_aperturado[$aperturado_relacion][$id_occ_detalle_relacion] = $id_occ_detalle_relacion;
}

$grupos_aperturado = [];
foreach ($filas_detalle as $fila_detalle) {
  $aperturado = trim((string) ($fila_detalle['aperturado'] ?? ''));
  $clave_grupo = $aperturado !== '' ? $aperturado : 'legacy-' . $fila_detalle['id_certificado_maestro_detalle'];

  if (!isset($grupos_aperturado[$clave_grupo])) {
    $grupos_aperturado[$clave_grupo] = [
      'aperturado' => $aperturado,
      'lote' => (string) ($fila_detalle['lote'] ?? ''),
      'modo_generacion' => (string) ($fila_detalle['modo_generacion'] ?? 'legacy'),
      'proyecto' => (string) ($fila_detalle['proyecto'] ?? ''),
      'monto_base_occ' => (float) ($fila_detalle['monto_base_occ'] ?? 0),
      'subtotal_cm' => 0.0,
      'occ_ids' => [],
      'filas' => [],
    ];
  }

  $grupos_aperturado[$clave_grupo]['subtotal_cm'] += (float) ($fila_detalle['subtotal_cm'] ?? 0);
  $grupos_aperturado[$clave_grupo]['filas'][] = $fila_detalle;

  $id_occ_detalle_fila = (int) ($fila_detalle['id_occ_detalle'] ?? 0);
  if ($id_occ_detalle_fila > 0) {
    $grupos_aperturado[$clave_grupo]['occ_ids'][$id_occ_detalle_fila] = $id_occ_detalle_fila;
  }
}

foreach ($grupos_aperturado as $clave_grupo => &$grupo_aperturado) {
  $aperturado = $grupo_aperturado['aperturado'];
  if ($aperturado !== '' && !empty($occ_ids_por_aperturado[$aperturado])) {
    $grupo_aperturado['occ_ids'] = $occ_ids_por_aperturado[$aperturado];
  }
  $grupo_aperturado['occ_ids'] = array_values($grupo_aperturado['occ_ids']);
}
unset($grupo_aperturado);

$grupos_por_occ = [];
$grupos_legacy = [];
$orden_grupos_agrupados = [];

foreach ($grupos_aperturado as $clave_grupo => $grupo_aperturado) {
  if (empty($grupo_aperturado['occ_ids'])) {
    $grupos_legacy[$clave_grupo] = $grupo_aperturado;
    continue;
  }

  $id_occ_propietario = (int) end($grupo_aperturado['occ_ids']);
  $grupos_por_occ[$id_occ_propietario][$clave_grupo] = $grupo_aperturado;

  // Asignar posiciones a TODOS los occ_ids del grupo (para que se pinten con el estilo agrupado)
  $cantidad = count($grupo_aperturado['occ_ids']);
  foreach ($grupo_aperturado['occ_ids'] as $posicion => $id_occ_grupo) {
    $orden_grupos_agrupados[(int) $id_occ_grupo] = [
      'grupo' => $clave_grupo,
      'posicion' => $posicion,
      'cantidad' => $cantidad,
      'propietario' => $id_occ_propietario,
    ];
  }
}

usort($occ_detalles, function ($a, $b) use ($orden_grupos_agrupados) {
  $id_a = (int) $a['id'];
  $id_b = (int) $b['id'];
  $meta_a = $orden_grupos_agrupados[$id_a] ?? null;
  $meta_b = $orden_grupos_agrupados[$id_b] ?? null;

  if ($meta_a && $meta_b && $meta_a['grupo'] === $meta_b['grupo']) {
    return $meta_a['posicion'] <=> $meta_b['posicion'];
  }
  if ($meta_a && !$meta_b) return -1;
  if (!$meta_a && $meta_b) return 1;
  if ($meta_a && $meta_b) return strcmp($meta_a['grupo'], $meta_b['grupo']);
  return ((int) ($a['posicion'] ?? 0) <=> (int) ($b['posicion'] ?? 0)) ?: ($id_a <=> $id_b);
});

Database::disconnect();

// Retornar estructura completa
echo json_encode([
  'moneda' => $moneda,
  'occ_detalles' => $occ_detalles,
  'grupos_por_occ' => $grupos_por_occ,
  'grupos_legacy' => $grupos_legacy,
  'orden_grupos_agrupados' => $orden_grupos_agrupados
]);
?>
