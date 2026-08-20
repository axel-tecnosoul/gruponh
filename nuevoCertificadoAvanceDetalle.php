<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
if (!empty($_POST)) {
    
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_GET);
    var_dump($_POST);
  }

  $column_names = [
    1 => "monto_acumulado_avances",
    2 => "monto_acumulado_anticipos",
    3 => "monto_acumulado_desacopios",
    4 => "monto_acumulado_descuentos",
    5 => "monto_acumulado_ajustes",
  ];

  $id_certificado_avance=$_GET['id_certificado_avance'];

  $sql = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_avance]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_certificado_maestro=$data["id_certificado_maestro"];

  $sql = "SELECT COUNT(*) FROM certificados_avances_detalle WHERE id_certificado_avance = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_avance]);
  $modo_post = ($q->fetchColumn() > 0) ? 'modificar' : 'crear';

    foreach ($_POST["id_certificado_maestro_detalle"] as $key => $id_certificado_maestro_detalle) {
      
      $avance=$_POST['avance'][$key];
      $id_certificado_avance_detalle = isset($_POST['id_certificado_avance_detalle'][$key]) ? $_POST['id_certificado_avance_detalle'][$key] : 0;

      if($avance>0){

        $precio_unitario=$_POST['precio_unitario'][$key];
        $id_tipo_item=$_POST['id_tipo_item'][$key];

        if($id_certificado_avance_detalle>0){

          $sql = "SELECT cantidad_actual, cantidad_acumulado, subtotal FROM certificados_avances_detalle WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$id_certificado_avance_detalle]);
          $data = $q->fetch(PDO::FETCH_ASSOC);

          $cantidad_actual_anterior = ($data and !is_null($data["cantidad_actual"])) ? $data["cantidad_actual"] : 0;
          $cantidad_acumulado_anterior = ($data and !is_null($data["cantidad_acumulado"])) ? $data["cantidad_acumulado"] : 0;
          $subtotal_viejo = ($data and !is_null($data["subtotal"])) ? $data["subtotal"] : 0;

          $total_acumulado=$cantidad_acumulado_anterior-$cantidad_actual_anterior+$avance;
          $subtotal=$avance*$precio_unitario;

          $sql = "UPDATE certificados_avances_detalle SET cantidad_actual = ?, cantidad_acumulado = ?, precio_unitario = ?, subtotal = ? WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$avance,$total_acumulado,$precio_unitario,$subtotal,$id_certificado_avance_detalle]);

          $column_name = $column_names[$id_tipo_item];
          //restamos el subtotal viejo y sumamos el nuevo subtotal en la columna segun el tipo de detalle
          $sql = "UPDATE certificados_avances_cabecera SET $column_name = $column_name - ? WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$subtotal_viejo,$id_certificado_avance]);

          $sql = "UPDATE certificados_avances_cabecera SET $column_name = $column_name + ? WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$subtotal,$id_certificado_avance]);

        } else {

          $sql = "SELECT COALESCE(SUM(cantidad_actual),0) FROM certificados_avances_detalle WHERE id_certificado_maestro_detalle = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$id_certificado_maestro_detalle]);
          $cantidad_acumulado_anterior = $q->fetchColumn();

          $total_acumulado=$cantidad_acumulado_anterior+$avance;
          $subtotal=$avance*$precio_unitario;

          $sql = "INSERT INTO certificados_avances_detalle (id_certificado_avance, id_certificado_maestro_detalle, cantidad_anterior, cantidad_actual, cantidad_acumulado, precio_unitario, subtotal) VALUES (?,?,?,?,?,?,?)";
          $q = $pdo->prepare($sql);
          $q->execute([$id_certificado_avance,$id_certificado_maestro_detalle, $cantidad_acumulado_anterior, $avance, $total_acumulado, $precio_unitario,$subtotal]);

          $column_name = $column_names[$id_tipo_item];
          //sumamos el nuevo subtotal en la columna segun el nuevo tipo de detalle
          $sql = "UPDATE certificados_avances_cabecera SET $column_name = $column_name + ? WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$subtotal,$id_certificado_avance]);

        }

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: " . $q->rowCount();
          echo "<br><br>";
        }
        //$id_certificados_maestros_detalles = $pdo->lastInsertId();
      }

    }

    $sql = "SELECT COALESCE(SUM(subtotal),0) FROM certificados_avances_detalle WHERE id_certificado_avance = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_certificado_avance]);
    $monto_total = $q->fetchColumn();

    $sql = "UPDATE certificados_avances_cabecera SET monto_total = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$monto_total,$id_certificado_avance]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: " . $q->rowCount();
      echo "<br><br>";
    }
    
    $accion_log = ($modo_post == 'modificar') ? 'Modificacion Detalle Certificado de Avance #' : 'Nuevo Detalle Certificado de Avance #';
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'".$accion_log.$id_certificado_avance."','Certificado de Avance','verCertificadoAvance.php?id=$id_certificado_avance')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: " . $q->rowCount();
      echo "<br><br>";
    }

    if ($modoDebug==1) {
      $pdo->rollBack();
      die();
    }

    Database::disconnect();
    //header("Location: listarCertificadosMaestro.php?id_certificado_avance=".$_GET["id_certificado_avance"]);
    header("Location: listarCertificadosAvances.php?id_certificado_maestro=".$id_certificado_maestro);

}

$id_certificado_avance=$_GET['id_certificado_avance'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cac.id_certificado_maestro, cm.id_occ, m.moneda
        FROM certificados_avances_cabecera cac
        INNER JOIN certificados_maestros cm ON cm.id = cac.id_certificado_maestro
        INNER JOIN monedas m ON m.id = cm.id_moneda
        WHERE cac.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_avance]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$id_certificado_maestro=$data["id_certificado_maestro"];
$id_occ = (int) $data["id_occ"];
$moneda = (string) $data["moneda"];

$sql = "SELECT COUNT(*) FROM certificados_avances_detalle WHERE id_certificado_avance = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_avance]);
$modo = ($q->fetchColumn() > 0) ? 'modificar' : 'crear';

$sql = "SELECT id, descripcion, cantidad, precio_unitario, descuento, subtotal
        FROM occ_detalles
        WHERE id_occ = ?
        ORDER BY id";
$q = $pdo->prepare($sql);
$q->execute([$id_occ]);
$occ_detalles = $q->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT cmd.id AS id_certificado_maestro_detalle,
               cmd.id_occ_detalle,
               cmd.id_tipo_item_certificado,
               tic.tipo,
               cmd.descripcion,
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
               cad.subtotal AS subtotal_ca,
               COALESCE(acumulados.acumulado, 0) AS acumulado
        FROM certificados_maestros_detalles cmd
        INNER JOIN tipos_item_certificado tic ON tic.id = cmd.id_tipo_item_certificado
        INNER JOIN unidades_medida um ON um.id = cmd.id_unidad_medida
        LEFT JOIN proyectos p ON p.id = cmd.id_proyecto
        LEFT JOIN sitios s ON s.id = p.id_sitio
        LEFT JOIN certificados_avances_detalle cad
          ON cad.id_certificado_maestro_detalle = cmd.id
         AND cad.id_certificado_avance = ?
        LEFT JOIN (
          SELECT id_certificado_maestro_detalle, SUM(cantidad_actual) AS acumulado
          FROM certificados_avances_detalle
          GROUP BY id_certificado_maestro_detalle
        ) acumulados ON acumulados.id_certificado_maestro_detalle = cmd.id
        WHERE cmd.id_certificado_maestro = ?
        ORDER BY cmd.lote, cmd.aperturado, cmd.id";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_avance, $id_certificado_maestro]);
$filas_detalle = $q->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT aperturado, id_occ_detalle
        FROM certificados_maestros_lotes_occ_detalle
        WHERE id_certificado_maestro = ?
        ORDER BY id";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_maestro]);
$relaciones_occ = $q->fetchAll(PDO::FETCH_ASSOC);

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

  // En la pantalla de detalle del CM el desglose agrupado se muestra
  // despues del ultimo item del grupo, no despues del primero.
  $id_occ_propietario = (int) end($grupo_aperturado['occ_ids']);
  $grupos_por_occ[$id_occ_propietario][$clave_grupo] = $grupo_aperturado;

  if ($grupo_aperturado['modo_generacion'] === 'agrupar') {
    foreach ($grupo_aperturado['occ_ids'] as $posicion => $id_occ_grupo) {
      $orden_grupos_agrupados[(int) $id_occ_grupo] = [
        'grupo' => $clave_grupo,
        'posicion' => $posicion,
        'cantidad' => count($grupo_aperturado['occ_ids']),
        'propietario' => $id_occ_propietario,
      ];
    }
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
  return $id_a <=> $id_b;
});

function escaparAvance($valor) {
  return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function renderGruposAvance($grupos, $moneda) {
  foreach ($grupos as $clave_grupo => $grupo) {
    $modo = (string) ($grupo['modo_generacion'] ?? 'legacy');
    $total_anterior_grupo = 0.0;
    $total_actual_grupo = 0.0;
    $total_acumulado_grupo = 0.0;
    foreach ($grupo['filas'] as $fila_total) {
      $cantidad_actual_total = (float) ($fila_total['cantidad_actual'] ?? 0);
      $cantidad_acumulada_total = (float) ($fila_total['acumulado'] ?? 0);
      $cantidad_anterior_total = max(0, $cantidad_acumulada_total - $cantidad_actual_total);
      $precio_unitario_total = (float) ($fila_total['precio_unitario_cm'] ?? 0);
      $total_anterior_grupo += $cantidad_anterior_total * $precio_unitario_total;
      $total_actual_grupo += $cantidad_actual_total * $precio_unitario_total;
      $total_acumulado_grupo += ($cantidad_anterior_total + $cantidad_actual_total) * $precio_unitario_total;
    }
    ?>
    <div class="<?=$modo === 'agrupar' ? 'occ-group-aperturado-wrap ' : ''?>mb-3"><?php
      if ($modo === 'agrupar') { ?>
        <small class="d-block text-muted mb-2">Aplica al grupo OCC: <?=escaparAvance(implode(', ', $grupo['occ_ids']))?></small><?php
      } else { ?>
        <small class="d-block font-weight-bold text-primary mb-1">Lotes por item</small><?php
      } ?>
      <div class="border rounded px-2 py-2 occ-lote-inline-row">
      <div class="table-responsive mb-2">
        <table class="table table-sm table-bordered mb-0 occ-lote-summary-table">
          <thead>
            <tr>
              <th>Aperturado</th>
              <th>Lote</th>
              <th>Proyecto</th>
              <th class="text-right">Monto CM</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?=escaparAvance($grupo['aperturado'] !== '' ? $grupo['aperturado'] : 'Sin identificador')?></td>
              <td><?=escaparAvance($grupo['lote'] !== '' ? $grupo['lote'] : '-')?></td>
              <td><?=escaparAvance($grupo['proyecto'] !== '' ? $grupo['proyecto'] : '-')?></td>
              <td class="text-right"><?=escaparAvance($moneda)?> <?=number_format((float) $grupo['subtotal_cm'], 2, ',', '.')?></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered mb-0 occ-breakdown-table">
          <thead>
            <tr>
              <th rowspan="2">Descripcion</th>
              <th rowspan="2">Unidad</th>
              <th rowspan="2" class="text-right">Cantidad</th>
              <th rowspan="2" class="text-right">Incidencia</th>
              <th rowspan="2" class="text-right">Precio unitario</th>
              <th rowspan="2" class="text-right">Total CM</th>
              <th colspan="3" class="text-center avance-periodo avance-periodo-anterior">Anterior</th>
              <th colspan="3" class="text-center avance-periodo avance-periodo-actual">Actual</th>
              <th colspan="3" class="text-center avance-periodo avance-periodo-acumulado">Acumulado</th>
            </tr>
            <tr>
              <th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>
              <th class="text-center avance-porcentaje-col">%</th>
              <th class="text-right">Monto</th>
              <th class="text-right avance-col-inicio avance-cantidad-col avance-cantidad-actual-col">Cantidad</th>
              <th class="text-center avance-porcentaje-col">%</th>
              <th class="text-right">Monto</th>
              <th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>
              <th class="text-center avance-porcentaje-col">%</th>
              <th class="text-right">Monto</th>
            </tr>
          </thead>
          <tbody><?php
            foreach ($grupo['filas'] as $fila) {
              $cantidad = (float) ($fila['cantidad'] ?? 0);
              $cantidad_acumulada = (float) ($fila['acumulado'] ?? 0);
              $cantidad_actual = (float) ($fila['cantidad_actual'] ?? 0);
              $cantidad_anterior = max(0, $cantidad_acumulada - $cantidad_actual);
              $cantidad_acumulada = $cantidad_anterior + $cantidad_actual;
              $maximo_avance = max(0, $cantidad - $cantidad_anterior);
              $porcentaje_anterior = $cantidad > 0 ? ($cantidad_anterior / $cantidad) * 100 : 0;
              $porcentaje_actual = $cantidad > 0 ? ($cantidad_actual / $cantidad) * 100 : 0;
              $porcentaje_acumulado = $cantidad > 0 ? ($cantidad_acumulada / $cantidad) * 100 : 0;
              $monto_anterior = $cantidad_anterior * (float) $fila['precio_unitario_cm'];
              $monto_actual = $cantidad_actual * (float) $fila['precio_unitario_cm'];
              $monto_acumulado = $cantidad_acumulada * (float) $fila['precio_unitario_cm'];
              $incidencia = $fila['incidencia_porcentaje'];
              ?>
              <tr class="fila-detalle-avance" data-cantidad-total="<?=escaparAvance($cantidad)?>" data-cantidad-anterior="<?=escaparAvance($cantidad_anterior)?>">
                <td>
                  <?=escaparAvance($fila['descripcion'])?>
                  <input type="hidden" name="id_certificado_avance_detalle[]" value="<?=escaparAvance($fila['id_certificado_avance_detalle'] ?? '')?>">
                  <input type="hidden" name="id_tipo_item[]" value="<?=escaparAvance($fila['id_tipo_item_certificado'])?>">
                  <input type="hidden" name="id_certificado_maestro_detalle[]" value="<?=escaparAvance($fila['id_certificado_maestro_detalle'])?>">
                  <input type="hidden" name="precio_unitario[]" value="<?=escaparAvance($fila['precio_unitario_cm'])?>">
                </td>
                <td><?=escaparAvance($fila['unidad_medida'])?></td>
                <td class="text-right"><?=number_format($cantidad, 2, ',', '.')?></td>
                <td class="text-right"><?=$incidencia !== null ? number_format((float) $incidencia, 2, ',', '.') . '%' : '-'?></td>
                <td class="text-right"><?=escaparAvance($moneda)?> <?=number_format((float) $fila['precio_unitario_cm'], 2, ',', '.')?></td>
                <td class="text-right"><?=escaparAvance($moneda)?> <?=number_format((float) $fila['subtotal_cm'], 2, ',', '.')?></td>
                <td class="text-right avance-col-inicio avance-cantidad-col cantidad-anterior"><?=number_format($cantidad_anterior, 2, ',', '.')?></td>
                <td class="text-center avance-porcentaje-col porcentaje-anterior"><?=number_format($porcentaje_anterior, 2, ',', '.')?>%</td>
                <td class="text-right"><?=escaparAvance($moneda)?> <span class="monto-anterior"><?=number_format($monto_anterior, 2, ',', '.')?></span></td>
                <td class="avance-col-inicio avance-cantidad-col avance-cantidad-actual-col">
                  <input type="number" step="0.01" class="form-control form-control-sm" name="avance[]" placeholder="Avance" min="0" max="<?=escaparAvance($maximo_avance)?>" value="<?=$fila['cantidad_actual'] !== null ? escaparAvance($fila['cantidad_actual']) : ''?>">
                </td>
                <td class="text-center avance-porcentaje-col porcentaje-actual"><?=number_format($porcentaje_actual, 2, ',', '.')?>%</td>
                <td class="text-right">
                  <?=escaparAvance($moneda)?> <span class="subtotal_formatted"><?=number_format($monto_actual, 2, ',', '.')?></span>
                  <input type="hidden" name="subtotal[]" value="<?=escaparAvance($fila['subtotal_ca'] ?? '')?>">
                </td>
                <td class="text-right avance-col-inicio avance-cantidad-col cantidad-acumulada"><?=number_format($cantidad_acumulada, 2, ',', '.')?></td>
                <td class="text-center avance-porcentaje-col porcentaje-acumulado"><?=number_format($porcentaje_acumulado, 2, ',', '.')?>%</td>
                <td class="text-right"><?=escaparAvance($moneda)?> <span class="monto-acumulado"><?=number_format($monto_acumulado, 2, ',', '.')?></span></td>
              </tr><?php
            }
          ?></tbody>
          <tfoot class="bg-light">
            <tr class="font-weight-bold">
              <td colspan="6" class="text-right">Totales del grupo</td>
              <td colspan="2" class="text-right avance-col-inicio">Anterior</td>
              <td class="text-right"><?=escaparAvance($moneda)?> <span class="total-grupo-anterior"><?=number_format($total_anterior_grupo, 2, ',', '.')?></span></td>
              <td colspan="2" class="text-right avance-col-inicio">Actual</td>
              <td class="text-right"><?=escaparAvance($moneda)?> <span class="total-grupo-avance"><?=number_format($total_actual_grupo, 2, ',', '.')?></span></td>
              <td colspan="2" class="text-right avance-col-inicio">Acumulado</td>
              <td class="text-right"><?=escaparAvance($moneda)?> <span class="total-grupo-acumulado"><?=number_format($total_acumulado_grupo, 2, ',', '.')?></span></td>
            </tr>
          </tfoot>
        </table>
      </div>
      </div>
    </div><?php
  }
}

Database::disconnect();

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      #tabla_occ_avances tbody tr.occ-grouped-member > td {
        background-color: #eef8ff;
      }
      #tabla_occ_avances tbody tr.occ-grouped-member > td + td {
        border-left: 2px solid #2b8dbf;
      }
      #tabla_occ_avances tbody tr.occ-grouped-start > td {
        border-top: 2px solid #2b8dbf;
      }
      #tabla_occ_avances tbody tr.occ-grouped-end > td {
        border-bottom: 2px solid #2b8dbf;
      }
      #tabla_occ_avances tbody tr.occ-grouped-single > td {
        border-top: 2px solid #2b8dbf;
        border-bottom: 2px solid #2b8dbf;
      }
      #tabla_occ_avances tbody tr.occ-grouped-member > td:first-child {
        border-left: 3px solid #2b8dbf;
      }
      #tabla_occ_avances tbody tr.occ-grouped-member > td:last-child {
        border-right: 3px solid #2b8dbf;
      }
      #tabla_occ_avances.table-sm > tbody > tr.occ-breakdown-row > td {
        background-color: #f7fcff;
        padding: 0.25rem !important;
      }
      .occ-group-aperturado-wrap {
        border-left: 4px solid #2b8dbf;
        background: #f7fcff;
        border-radius: 4px;
        padding: 0.5rem;
      }
      .occ-breakdown-table th,
      .occ-breakdown-table td {
        vertical-align: middle;
        white-space: nowrap;
      }
      .occ-breakdown-table th:first-child,
      .occ-breakdown-table td:first-child {
        white-space: normal;
        min-width: 180px;
      }
      .occ-breakdown-table input[name='avance[]'] {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
        padding: 0.25rem 0.35rem;
      }
      .occ-breakdown-table .avance-periodo {
        border-left: 2px solid #2b8dbf;
      }
      .occ-breakdown-table .avance-periodo-anterior {
        background-color: #f1f3f5;
      }
      .occ-breakdown-table .avance-periodo-actual {
        background-color: #e9f6fd;
      }
      .occ-breakdown-table .avance-periodo-acumulado {
        background-color: #eef7ee;
      }
      .occ-breakdown-table .avance-col-inicio {
        border-left: 2px solid #2b8dbf;
      }
      .legacy-section {
        border-left: 4px solid #ffc107;
      }

      #tabla_occ_avances.table-sm .occ-breakdown-table > thead > tr > th,
      #tabla_occ_avances.table-sm .occ-breakdown-table > tbody > tr > td,
      #tabla_occ_avances.table-sm .occ-breakdown-table > tfoot > tr > td {
        padding: 2px 5px !important;
      }
      .occ-breakdown-table .avance-cantidad-col {
        width: 78px !important;
        min-width: 78px !important;
        max-width: 78px !important;
      }
      .occ-breakdown-table .avance-cantidad-actual-col {
        width: 90px !important;
        min-width: 90px !important;
        max-width: 90px !important;
      }
      .occ-breakdown-table .avance-porcentaje-col {
        width: 62px !important;
        min-width: 62px !important;
        max-width: 62px !important;
        padding-right: 2px !important;
        padding-left: 2px !important;
        text-align: center !important;
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php');?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Certificado de avance";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?= $modo == 'modificar' ? 'Modificar' : 'Nuevo' ?> Detalle del Certificado de Avance #<?=$id_certificado_avance?>
                      &nbsp;&nbsp;
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevoCertificadoAvanceDetalle.php?id_certificado_avance=<?=$id_certificado_avance?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-12">
                              <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 font-weight-bold">Items OCC y aperturados</h6>
                                <button type="button" id="btn_toggle_todos_desgloses" class="btn btn-secondary btn-sm">Ocultar todos los desgloses</button>
                              </div>
                              <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="tabla_occ_avances" style="width:100%">
                                  <thead>
                                    <tr>
                                      <th>ID</th>
                                      <th>Descripcion</th>
                                      <th class="text-right">Cantidad</th>
                                      <th class="text-right">Precio unitario</th>
                                      <th class="text-right">Descuento</th>
                                      <th class="text-right">Subtotal</th>
                                      <th class="text-center" style="width:95px;">Acciones</th>
                                    </tr>
                                  </thead>
                                  <tbody><?php
                                    if (empty($occ_detalles)) { ?>
                                      <tr><td colspan="7">La Orden de Compra seleccionada no tiene items.</td></tr><?php
                                    } else {
                                      foreach ($occ_detalles as $occ_row) {
                                        $id_occ_fila = (int) $occ_row['id'];
                                        $grupos_fila = $grupos_por_occ[$id_occ_fila] ?? [];
                                        $meta_agrupado = $orden_grupos_agrupados[$id_occ_fila] ?? null;
                                        $clases_occ = [];
                                        if ($meta_agrupado) {
                                          $clases_occ[] = 'occ-grouped-member';
                                          if ($meta_agrupado['cantidad'] === 1) {
                                            $clases_occ[] = 'occ-grouped-single';
                                          } elseif ($meta_agrupado['posicion'] === 0) {
                                            $clases_occ[] = 'occ-grouped-start';
                                          } elseif ($meta_agrupado['posicion'] === $meta_agrupado['cantidad'] - 1) {
                                            $clases_occ[] = 'occ-grouped-end';
                                          } else {
                                            $clases_occ[] = 'occ-grouped-middle';
                                          }
                                        }
                                        $id_desglose = 'desglose-occ-' . $id_occ_fila;
                                        ?>
                                        <tr class="occ-item-row <?=escaparAvance(implode(' ', $clases_occ))?>">
                                          <td><?=$id_occ_fila?></td>
                                          <td><?=escaparAvance($occ_row['descripcion'])?></td>
                                          <td class="text-right"><?=number_format((float) $occ_row['cantidad'], 2, ',', '.')?></td>
                                          <td class="text-right"><?=escaparAvance($moneda)?> <?=number_format((float) $occ_row['precio_unitario'], 2, ',', '.')?></td>
                                          <td class="text-right"><?=escaparAvance($moneda)?> <?=number_format((float) $occ_row['descuento'], 2, ',', '.')?></td>
                                          <td class="text-right"><?=escaparAvance($moneda)?> <?=number_format((float) $occ_row['subtotal'], 2, ',', '.')?></td>
                                          <td class="text-center"><?php
                                            if (!empty($grupos_fila)) { ?>
                                              <button type="button" class="btn btn-secondary btn-sm btn-toggle-desglose" data-target="#<?=$id_desglose?>" title="Ocultar desglose" aria-label="Ocultar desglose">Ocultar</button><?php
                                            } elseif ($meta_agrupado && (int) $meta_agrupado['propietario'] !== $id_occ_fila) { ?>
                                              <span class="sr-only">Desglose agrupado en item #<?=escaparAvance($meta_agrupado['propietario'])?></span><?php
                                            } else { ?>
                                              <span class="text-muted">Sin aperturado</span><?php
                                            } ?>
                                          </td>
                                        </tr><?php
                                        if (!empty($grupos_fila)) { ?>
                                          <tr class="occ-breakdown-row" id="<?=$id_desglose?>">
                                            <td colspan="7"><?php renderGruposAvance($grupos_fila, $moneda); ?></td>
                                          </tr><?php
                                        }
                                      }
                                    } ?>
                                  </tbody>
                                </table>
                              </div><?php
                              if (!empty($grupos_legacy)) { ?>
                                <div class="alert alert-warning mt-4 mb-2">
                                  Estos detalles son registros legacy sin trazabilidad hacia un item OCC. Se mantienen disponibles para cargar el avance.
                                </div>
                                <div class="border rounded p-3 legacy-section">
                                  <h6 class="font-weight-bold mb-3">Detalles legacy sin trazabilidad OCC</h6>
                                  <?php renderGruposAvance($grupos_legacy, $moneda); ?>
                                </div><?php
                              } ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">

                        <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion"><?= $modo == 'modificar' ? 'Modificar' : 'Crear' ?> Certificado de Avance</button>
                        <!-- <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Crear e ir a Certificados</button> -->
                        <a href='listarCertificadosAvances.php?id_certificado_maestro=<?=$id_certificado_maestro?>' class="btn btn-light">Volver</a>

                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
          <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
    <!-- latest jquery-->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap js-->
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <!-- feather icon js-->
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <!-- Sidebar jquery-->
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <!-- Plugins JS start-->
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.buttons.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/jszip.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.colVis.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/pdfmake.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/vfs_fonts.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.autoFill.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.select.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.html5.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.print.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.responsive.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/responsive.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.keyTable.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.colReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.fixedHeader.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.rowReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.scroller.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/custom.js"></script>
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script type="text/plain" id="script-tabla-plana-anterior">
      function calcularSubtotalAvance(input) {
        var fila = input.closest('tr');
        var precioInput = fila.querySelector("input[name='precio_unitario[]']");
        var subtotalInput = fila.querySelector("input[name='subtotal[]']");
        var subtotalLabel = fila.querySelector('.subtotal_formatted');
        var avance = parseFloat(String(input.value || '').replace(',', '.')) || 0;
        var precioUnitario = parseFloat(String(precioInput.value || '').replace(',', '.')) || 0;
        var subtotal = avance * precioUnitario;

        subtotalInput.value = subtotal.toFixed(2);
        subtotalLabel.textContent = subtotal.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      $(document).ready(function () {

        if (!document.getElementById('dataTables-example667')) {
          return;
        }

        // Setup - add a text input to each footer cell
        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );

	      $('#dataTables-example667').DataTable({
          stateSave: false,
          responsive: false,
          order: [],
          language: {
            "decimal": "",
            "emptyTable": "No hay información",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
            "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
            "infoFiltered": "(Filtrado de _MAX_ total registros)",
            "infoPostFix": "",
            "thousands": ",",
            "lengthMenu": "Mostrar _MENU_ Registros",
            "loadingRecords": "Cargando...",
            "processing": "Procesando...",
            "search": "Buscar:",
            "zeroRecords": "No hay resultados",
            "paginate": {
                "first": "Primero",
                "last": "Ultimo",
                "next": "Siguiente",
                "previous": "Anterior"
            }
          }
        });
 
        // DataTable
        var table = $('#dataTables-example667').DataTable();
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        $("form").on("submit",function(e){
          e.preventDefault();
          let ok=0;
          $("#dataTables-example667 tbody tr").each(function(){
            actualizarSubtotal($(this));
            let avance=$(this).find("input[name='avance[]']").val()
            //let precio_unitario=$(this).find("input[name='precio_unitario[]']").val()
            if(avance.length>0){// && precio_unitario.length>0
              ok=1;
            }
          })
          if(ok==0){
            alert("Debe completar el avance de al menos una fila");
          }else{
            this.submit();
            //console.log("submit");
          }
        })

        function obtenerNumero(valor) {
          valor = String(valor || '').trim().replace(',', '.');
          let numero = parseFloat(valor);
          return isNaN(numero) ? 0 : numero;
        }

        function actualizarSubtotal(fila) {
          let avance = obtenerNumero(fila.find("input[name='avance[]']").val());
          let precioUnitario = obtenerNumero(fila.find("input[name='precio_unitario[]']").val());
          let subtotal = avance * precioUnitario;
          let subtotalFormateado = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

          fila.find("input[name='subtotal[]']").val(subtotal.toFixed(2));
          fila.find(".subtotal_formatted").html(subtotalFormateado);
        }

        $("#dataTables-example667 tbody tr").each(function() {
          calcularSubtotalAvance($(this).find("input[name='avance[]']")[0]);
        });

        $(document).on("input change keyup", "input[name='avance[]']", function() {
          actualizarSubtotal($(this).closest("tr"));
        });

      });
    </script>
    <script>
      function obtenerNumeroAvance(valor) {
        valor = String(valor || '').trim().replace(',', '.');
        let numero = parseFloat(valor);
        return isNaN(numero) ? 0 : numero;
      }

      function formatearNumeroAvance(valor) {
        return valor.toLocaleString('es-AR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function recalcularTotalGrupoAvance(grupo) {
        if (!grupo || !grupo.length) {
          return;
        }

        let totalActual = 0;
        let totalAcumulado = 0;
        grupo.find('.fila-detalle-avance').each(function() {
          let fila = $(this);
          totalActual += obtenerNumeroAvance(fila.find("input[name='subtotal[]']").val());
          totalAcumulado += obtenerNumeroAvance(fila.attr('data-monto-acumulado'));
        });

        grupo.find('.total-grupo-avance').text(formatearNumeroAvance(totalActual));
        grupo.find('.total-grupo-acumulado').text(formatearNumeroAvance(totalAcumulado));
      }

      function actualizarSubtotalAvance(fila) {
        let avance = obtenerNumeroAvance(fila.find("input[name='avance[]']").val());
        let precioUnitario = obtenerNumeroAvance(fila.find("input[name='precio_unitario[]']").val());
        let cantidadTotal = obtenerNumeroAvance(fila.attr('data-cantidad-total'));
        let cantidadAnterior = obtenerNumeroAvance(fila.attr('data-cantidad-anterior'));
        let cantidadAcumulada = cantidadAnterior + avance;
        let porcentajeActual = cantidadTotal > 0 ? (avance / cantidadTotal) * 100 : 0;
        let porcentajeAcumulado = cantidadTotal > 0 ? (cantidadAcumulada / cantidadTotal) * 100 : 0;
        let subtotal = avance * precioUnitario;
        let montoAcumulado = cantidadAcumulada * precioUnitario;

        fila.find("input[name='subtotal[]']").val(subtotal.toFixed(2));
        fila.find('.porcentaje-actual').text(formatearNumeroAvance(porcentajeActual) + '%');
        fila.find('.subtotal_formatted').text(formatearNumeroAvance(subtotal));
        fila.find('.cantidad-acumulada').text(formatearNumeroAvance(cantidadAcumulada));
        fila.find('.porcentaje-acumulado').text(formatearNumeroAvance(porcentajeAcumulado) + '%');
        fila.find('.monto-acumulado').text(formatearNumeroAvance(montoAcumulado));
        fila.attr('data-monto-acumulado', montoAcumulado.toFixed(2));
        recalcularTotalGrupoAvance(fila.closest('.occ-lote-inline-row'));
      }

      $(document).ready(function() {
        // Esta pantalla necesita todo el ancho disponible al abrirse.
        $('.page-sidebar').addClass('open');
        $('.page-main-header').addClass('open');

        $('.fila-detalle-avance').each(function() {
          actualizarSubtotalAvance($(this));
        });

        $(document).on('input change keyup', "input[name='avance[]']", function() {
          actualizarSubtotalAvance($(this).closest('.fila-detalle-avance'));
        });

        $(document).on('click', '.btn-toggle-desglose', function() {
          let target = $($(this).data('target'));
          target.toggle();
          let accion = target.is(':visible') ? 'Ocultar' : 'Mostrar';
          $(this)
            .text(accion)
            .attr('title', accion + ' desglose')
            .attr('aria-label', accion + ' desglose');
        });

        $('#btn_toggle_todos_desgloses').on('click', function() {
          let filas = $('.occ-breakdown-row');
          let hayVisible = filas.filter(':visible').length > 0;
          filas.toggle(!hayVisible);
          let accionIndividual = hayVisible ? 'Mostrar' : 'Ocultar';
          $('.btn-toggle-desglose')
            .text(accionIndividual)
            .attr('title', accionIndividual + ' desglose')
            .attr('aria-label', accionIndividual + ' desglose');
          $(this).text(hayVisible ? 'Mostrar todos los desgloses' : 'Ocultar todos los desgloses');
        });

        $('form').on('submit', function(e) {
          e.preventDefault();
          let hayAvance = false;

          $('.fila-detalle-avance').each(function() {
            actualizarSubtotalAvance($(this));
            if (obtenerNumeroAvance($(this).find("input[name='avance[]']").val()) > 0) {
              hayAvance = true;
            }
          });

          if (!hayAvance) {
            alert('Debe completar el avance de al menos una fila');
            return;
          }

          this.submit();
        });
      });
    </script>
  </body>
</html>
