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
  if (!$data) {
    Database::disconnect();
    die("El Certificado de Avance no existe.");
  }
  $id_certificado_maestro=$data["id_certificado_maestro"];

  $sqlUlt = "SELECT COUNT(*) FROM certificados_avances_cabecera c
             WHERE c.id_certificado_maestro = ?
               AND c.nro_certificado = (SELECT nro_certificado FROM certificados_avances_cabecera WHERE id = ?)
               AND c.nro_revision > (SELECT nro_revision FROM certificados_avances_cabecera WHERE id = ?)";
  $qUlt = $pdo->prepare($sqlUlt);
  $qUlt->execute([$id_certificado_maestro, $id_certificado_avance, $id_certificado_avance]);
  if ((int) $qUlt->fetchColumn() > 0) {
    Database::disconnect();
    die("Solo la ultima revision del certificado puede modificarse.");
  }

  $sqlAprobPost = "SELECT aprobado_cliente FROM certificados_avances_cabecera WHERE id = ?";
  $qAprobPost = $pdo->prepare($sqlAprobPost);
  $qAprobPost->execute([$id_certificado_avance]);
  if ((int) $qAprobPost->fetchColumn() === 1) {
    Database::disconnect();
    die("El certificado esta aprobado. Genere una nueva revision para modificarlo.");
  }

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
    header("Location: listarCertificadosAvances.php?id_certificado_maestro=".$id_certificado_maestro);

}

$id_certificado_avance=$_GET['id_certificado_avance'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cac.id, cac.id_certificado_maestro, cac.nro_certificado, cac.nro_revision,
               DATE_FORMAT(cac.fecha_emision,'%d/%m/%Y') AS fecha_emision_ca,
               DATE_FORMAT(cac.fecha_inicio,'%d/%m/%Y') AS fecha_inicio_ca,
               DATE_FORMAT(cac.fecha_fin,'%d/%m/%Y') AS fecha_fin_ca,
               cac.cotizacion_dolar, cac.monto_total, cac.monto_acumulado_avances,
               cac.monto_acumulado_anticipos, cac.monto_acumulado_desacopios,
               cac.monto_acumulado_descuentos, cac.monto_acumulado_ajustes,
               cac.observaciones, cac.aprobado_cliente,
               cm.id_occ, occ.numero AS numero_occ, cu.nombre AS cliente_occ, m.moneda
        FROM certificados_avances_cabecera cac
        INNER JOIN certificados_maestros cm ON cm.id = cac.id_certificado_maestro
        INNER JOIN occ occ ON occ.id = cm.id_occ
        INNER JOIN cuentas cu ON cu.id = occ.id_cuenta_cliente
        INNER JOIN monedas m ON m.id = cm.id_moneda
        WHERE cac.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_avance]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (!$data) {
  Database::disconnect();
  header("Location: listarCertificadosMaestros.php");
  exit;
}
$id_certificado_maestro=$data["id_certificado_maestro"];
$id_occ = (int) $data["id_occ"];
$esOpAvance = function_exists('esOperacionesSinEconomico') ? esOperacionesSinEconomico() : false;
$moneda = (string) $data["moneda"];
$cabecera_avance = $data;

$sqlUlt = "SELECT COUNT(*) FROM certificados_avances_cabecera c
           WHERE c.id_certificado_maestro = ?
             AND c.nro_certificado = (SELECT nro_certificado FROM certificados_avances_cabecera WHERE id = ?)
             AND c.nro_revision > (SELECT nro_revision FROM certificados_avances_cabecera WHERE id = ?)";
$qUlt = $pdo->prepare($sqlUlt);
$qUlt->execute([$id_certificado_maestro, $id_certificado_avance, $id_certificado_avance]);
if ((int) $qUlt->fetchColumn() > 0) {
  Database::disconnect();
  die("Solo la ultima revision del certificado puede modificarse.");
}

if ((int) ($cabecera_avance['aprobado_cliente'] ?? 0) === 1) {
  Database::disconnect();
  die("El certificado esta aprobado. Genere una nueva revision para modificarlo.");
}

$sql = "SELECT COUNT(*) FROM certificados_avances_detalle WHERE id_certificado_avance = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_avance]);
$modo = ($q->fetchColumn() > 0) ? 'modificar' : 'crear';

 $sql = "SELECT id, posicion, descripcion, cantidad, precio_unitario, descuento, subtotal
         FROM occ_detalles
         WHERE id_occ = ?
         ORDER BY posicion, id";
$q = $pdo->prepare($sql);
$q->execute([$id_occ]);
$occ_detalles = $q->fetchAll(PDO::FETCH_ASSOC);

// CONSULTA CORREGIDA: ahora acumulado es el total acumulado (anterior + actual)
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
               -- Si existe registro en cad, usamos su cantidad_acumulado (total acumulado); si no, usamos la suma de CAs anteriores (que en creación es el total, ya que actual=0)
               COALESCE(cad.cantidad_acumulado, acumulados.acumulado) AS acumulado
        FROM certificados_maestros_detalles cmd
        INNER JOIN tipos_item_certificado tic ON tic.id = cmd.id_tipo_item_certificado
        INNER JOIN unidades_medida um ON um.id = cmd.id_unidad_medida
        LEFT JOIN proyectos p ON p.id = cmd.id_proyecto
        LEFT JOIN sitios s ON s.id = p.id_sitio
        LEFT JOIN certificados_avances_detalle cad
          ON cad.id_certificado_maestro_detalle = cmd.id
         AND cad.id_certificado_avance = ?
        LEFT JOIN (
          SELECT cad2.id_certificado_maestro_detalle,
                 SUM(cad2.cantidad_actual) AS acumulado
          FROM certificados_avances_detalle cad2
          INNER JOIN certificados_avances_cabecera cac2 ON cac2.id = cad2.id_certificado_avance
          WHERE cac2.id_certificado_maestro = ?
            AND cac2.nro_certificado < ?
            AND NOT EXISTS (
              SELECT 1 FROM certificados_avances_cabecera y
              WHERE y.id_certificado_maestro = cac2.id_certificado_maestro
                AND y.nro_certificado = cac2.nro_certificado
                AND y.nro_revision > cac2.nro_revision
          )
          GROUP BY cad2.id_certificado_maestro_detalle
        ) acumulados ON acumulados.id_certificado_maestro_detalle = cmd.id
        WHERE cmd.id_certificado_maestro = ?
        ORDER BY cmd.lote, cmd.aperturado, cmd.id";
$q = $pdo->prepare($sql);
// OJO: parámetros: id_certificado_avance, id_certificado_maestro, nro_certificado, id_certificado_maestro
$q->execute([$id_certificado_avance, $id_certificado_maestro, $cabecera_avance['nro_certificado'], $id_certificado_maestro]);
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

  $id_occ_propietario = (int) end($grupo_aperturado['occ_ids']);
  $grupos_por_occ[$id_occ_propietario][$clave_grupo] = $grupo_aperturado;

  // Asignar posiciones a TODOS los occ_ids del grupo
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

function escaparAvance($valor) {
  return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function renderGruposAvance($grupos, $moneda) {
  global $esOpAvance;
  $esOp = !empty($esOpAvance);
  foreach ($grupos as $clave_grupo => $grupo) {
    $modo = (string) ($grupo['modo_generacion'] ?? 'legacy');
    $total_anterior_grupo = 0.0;
    $total_actual_grupo = 0.0;
    $total_acumulado_grupo = 0.0;
    $total_saldo_grupo = 0.0;
    $total_cm_grupo = 0.0;
    foreach ($grupo['filas'] as $fila_total) {
      $cantidad_actual_total = (float) ($fila_total['cantidad_actual'] ?? 0);
      $cantidad_acumulada_total = (float) ($fila_total['acumulado'] ?? 0);
      $cantidad_anterior_total = max(0, $cantidad_acumulada_total - $cantidad_actual_total);
      $precio_unitario_total = (float) ($fila_total['precio_unitario_cm'] ?? 0);
      $subtotal_cm_total = (float) ($fila_total['subtotal_cm'] ?? 0);
      $total_anterior_grupo += $cantidad_anterior_total * $precio_unitario_total;
      $total_actual_grupo += $cantidad_actual_total * $precio_unitario_total;
      $total_acumulado_grupo += ($cantidad_anterior_total + $cantidad_actual_total) * $precio_unitario_total;
      $total_cm_grupo += $subtotal_cm_total;
      $total_saldo_grupo += $subtotal_cm_total - (($cantidad_anterior_total + $cantidad_actual_total) * $precio_unitario_total);
    }?>
    
    <div class="occ-group-aperturado-wrap border rounded px-2 py-2 occ-lote-inline-row">
      <div class="table-responsive">
        <table class="table table-bordered mb-0 occ-breakdown-table">
          <thead>
            <tr>
              <th rowspan="2">Descripcion</th>
              <th rowspan="2">Unidad</th>
              <th rowspan="2" class="text-right">Cantidad</th>
              <th rowspan="2" class="text-right">Incidencia</th>
              <?php if (!$esOp) { ?><th rowspan="2" class="text-right">Precio unitario</th>
              <th rowspan="2" class="text-right">Total CM</th><?php } ?>
              <th colspan="<?php echo $esOp ? 2 : 3; ?>" class="text-center avance-periodo avance-periodo-anterior">Anterior</th>
              <th colspan="<?php echo $esOp ? 2 : 3; ?>" class="text-center avance-periodo avance-periodo-actual">Actual</th>
              <th colspan="<?php echo $esOp ? 2 : 3; ?>" class="text-center avance-periodo avance-periodo-acumulado">Acumulado</th>
              <th colspan="<?php echo $esOp ? 2 : 3; ?>" class="text-center avance-periodo avance-periodo-saldo">Saldo</th>
            </tr>
            <tr>
              <th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>
              <th class="text-center avance-porcentaje-col">%</th>
              <?php if (!$esOp) { ?><th class="text-right">Monto</th><?php } ?>
              <th class="text-right avance-col-inicio avance-cantidad-col avance-cantidad-actual-col">Cantidad</th>
              <th class="text-center avance-porcentaje-col">%</th>
              <?php if (!$esOp) { ?><th class="text-right">Monto</th><?php } ?>
              <th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>
              <th class="text-center avance-porcentaje-col">%</th>
              <?php if (!$esOp) { ?><th class="text-right">Monto</th><?php } ?>
              <th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>
              <th class="text-center avance-porcentaje-col">%</th>
              <?php if (!$esOp) { ?><th class="text-right">Monto</th><?php } ?>
            </tr>
          </thead>
          <tbody><?php
            foreach ($grupo['filas'] as $fila) {
              $cantidad = (float) ($fila['cantidad'] ?? 0);
              $cantidad_acumulada = (float) ($fila['acumulado'] ?? 0); // ahora es el total acumulado (anterior + actual)
              $cantidad_actual = (float) ($fila['cantidad_actual'] ?? 0);
              $cantidad_anterior = max(0, $cantidad_acumulada - $cantidad_actual);
              $cantidad_acumulada = $cantidad_anterior + $cantidad_actual; // redundante pero queda claro
              $maximo_avance = max(0, $cantidad - $cantidad_anterior);
              $porcentaje_anterior = $cantidad > 0 ? ($cantidad_anterior / $cantidad) * 100 : 0;
              $porcentaje_actual = $cantidad > 0 ? ($cantidad_actual / $cantidad) * 100 : 0;
              $porcentaje_acumulado = $cantidad > 0 ? ($cantidad_acumulada / $cantidad) * 100 : 0;
              $monto_anterior = $cantidad_anterior * (float) $fila['precio_unitario_cm'];
              $monto_actual = $cantidad_actual * (float) $fila['precio_unitario_cm'];
              $monto_acumulado = $cantidad_acumulada * (float) $fila['precio_unitario_cm'];
              $saldo_cantidad = max(0, $cantidad - $cantidad_acumulada);
              $porcentaje_saldo = $cantidad > 0 ? ($saldo_cantidad / $cantidad) * 100 : 0;
              $subtotal_cm_fila = (float) ($fila['subtotal_cm'] ?? 0);
              $saldo_monto = $subtotal_cm_fila - ($cantidad_acumulada * (float) $fila['precio_unitario_cm']);
              $incidencia = $fila['incidencia_porcentaje'];
              ?>
              <tr class="fila-detalle-avance" data-cantidad-total="<?=escaparAvance($cantidad)?>" data-cantidad-anterior="<?=escaparAvance($cantidad_anterior)?>" data-subtotal-cm="<?=escaparAvance($subtotal_cm_fila)?>">
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
                <?php if (!$esOp) { ?><td class="text-right"><?=escaparAvance($moneda)?> <?=number_format((float) $fila['precio_unitario_cm'], 2, ',', '.')?></td>
                <td class="text-right"><?=escaparAvance($moneda)?> <?=number_format((float) $fila['subtotal_cm'], 2, ',', '.')?></td><?php } ?>
                <td class="text-right avance-col-inicio avance-cantidad-col cantidad-anterior"><?=number_format($cantidad_anterior, 2, ',', '.')?></td>
                <td class="text-center avance-porcentaje-col porcentaje-anterior"><?=number_format($porcentaje_anterior, 2, ',', '.')?>%</td>
                <?php if (!$esOp) { ?><td class="text-right"><?=escaparAvance($moneda)?> <span class="monto-anterior"><?=number_format($monto_anterior, 2, ',', '.')?></span></td><?php } ?>
                <td class="avance-col-inicio avance-cantidad-col avance-cantidad-actual-col">
                  <input type="number" step="0.01" class="form-control form-control-sm" name="avance[]" placeholder="Avance" min="0" max="<?=escaparAvance($maximo_avance)?>" value="<?=$fila['cantidad_actual'] !== null ? escaparAvance($fila['cantidad_actual']) : ''?>">
                </td>
                <td class="text-center avance-porcentaje-col porcentaje-actual"><?=number_format($porcentaje_actual, 2, ',', '.')?>%</td>
                <?php if (!$esOp) { ?><td class="text-right">
                  <?=escaparAvance($moneda)?> <span class="subtotal_formatted"><?=number_format($monto_actual, 2, ',', '.')?></span>
                  <input type="hidden" name="subtotal[]" value="<?=escaparAvance($fila['subtotal_ca'] ?? '')?>">
                </td><?php } ?>
                <td class="text-right avance-col-inicio avance-cantidad-col cantidad-acumulada"><?=number_format($cantidad_acumulada, 2, ',', '.')?></td>
                <td class="text-center avance-porcentaje-col porcentaje-acumulado"><?=number_format($porcentaje_acumulado, 2, ',', '.')?>%</td>
                <?php if (!$esOp) { ?><td class="text-right"><?=escaparAvance($moneda)?> <span class="monto-acumulado"><?=number_format($monto_acumulado, 2, ',', '.')?></span></td><?php } ?>
                <td class="text-right avance-col-inicio avance-cantidad-col cantidad-saldo"><?=number_format($saldo_cantidad, 2, ',', '.')?></td>
                <td class="text-center avance-porcentaje-col porcentaje-saldo"><?=number_format($porcentaje_saldo, 2, ',', '.')?>%</td>
                <?php if (!$esOp) { ?><td class="text-right"><?=escaparAvance($moneda)?> <span class="monto-saldo"><?=number_format($saldo_monto, 2, ',', '.')?></span></td><?php } ?>
              </tr><?php
            }
          ?></tbody>
          <tfoot class="bg-light">
            <tr class="font-weight-bold">
              <td colspan="<?php echo $esOp ? 4 : 6; ?>" class="text-right">Totales del grupo</td>
              <td class="text-right avance-col-inicio">Anterior</td>
              <td class="text-center avance-porcentaje-col"></td>
              <?php if (!$esOp) { ?><td class="text-right"><?=escaparAvance($moneda)?> <span class="total-grupo-anterior"><?=number_format($total_anterior_grupo, 2, ',', '.')?></span></td><?php } ?>
              <td class="text-right avance-col-inicio">Actual</td>
              <td class="text-center avance-porcentaje-col"></td>
              <?php if (!$esOp) { ?><td class="text-right"><?=escaparAvance($moneda)?> <span class="total-grupo-avance"><?=number_format($total_actual_grupo, 2, ',', '.')?></span></td><?php } ?>
              <td class="text-right avance-col-inicio">Acumulado</td>
              <td class="text-center avance-porcentaje-col"></td>
              <?php if (!$esOp) { ?><td class="text-right"><?=escaparAvance($moneda)?> <span class="total-grupo-acumulado"><?=number_format($total_acumulado_grupo, 2, ',', '.')?></span></td><?php } ?>
              <td class="text-right avance-col-inicio">Saldo</td>
              <td class="text-center avance-porcentaje-col"></td>
              <?php if (!$esOp) { ?><td class="text-right total-grupo-saldo" data-total-saldo="<?=escaparAvance($total_saldo_grupo)?>" data-moneda="<?=escaparAvance($moneda)?>"><?=escaparAvance($moneda)?> <?=number_format($total_saldo_grupo, 2, ',', '.')?></td><?php } ?>
            </tr>
          </tfoot>
        </table>
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
      .occ-breakdown-table .avance-periodo-saldo {
        background-color: #fdf3f3;
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
                    <h5><?= $modo == 'modificar' ? 'Modificar' : 'Nuevo' ?> Detalle del Certificado de Avance
                      N° <?=escaparAvance($cabecera_avance['nro_certificado'] ?? '-')?> Rev. <?=escaparAvance($cabecera_avance['nro_revision'] ?? '-')?>
                      (CM #<?=escaparAvance($id_certificado_maestro)?>)
                      &nbsp;&nbsp;
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevoCertificadoAvanceDetalle.php?id_certificado_avance=<?=$id_certificado_avance?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <h6 class="mb-3 font-weight-bold">Datos del Certificado</h6>
                          <div class="row mb-2">
                            <div class="col-lg-6">
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Cliente</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($cabecera_avance['cliente_occ'] ?? '')?></span></div>
                              </div>
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Orden de Compra Cliente</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($cabecera_avance['numero_occ'] ?? '')?></span></div>
                              </div>
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Fecha Emisión CA</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($cabecera_avance['fecha_emision_ca'] ?? '')?></span></div>
                              </div>
                            </div>
                            <div class="col-lg-6">
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Periodo (Inicio - Fin)</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($cabecera_avance['fecha_inicio_ca'] ?? '')?> - <?=escaparAvance($cabecera_avance['fecha_fin_ca'] ?? '')?></span></div>
                              </div>
                              <?php if (!$esOpAvance) { ?><div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Moneda / Cotización Dólar</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($moneda)?> / <?=number_format((float)($cabecera_avance['cotizacion_dolar'] ?? 0), 2, ',', '.')?></span></div>
                              </div>
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Monto Total del Certificado</label>
                                <div class="col-sm-7"><span class="form-control-plaintext font-weight-bold"><?=escaparAvance($moneda)?> <?=number_format((float)($cabecera_avance['monto_total'] ?? 0), 2, ',', '.')?></span></div>
                              </div><?php } ?>
                            </div>
                          </div>
                          <?php if (!$esOpAvance) { ?><div class="row">
                            <div class="col-lg-6">
                              <div class="form-group row mb-1">
                                <label class="col-sm-5 col-form-label">Acumulado avances</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($moneda)?> <?=number_format((float)($cabecera_avance['monto_acumulado_avances'] ?? 0), 2, ',', '.')?></span></div>
                              </div>
                              <div class="form-group row mb-1">
                                <label class="col-sm-5 col-form-label">Acumulado anticipos</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($moneda)?> <?=number_format((float)($cabecera_avance['monto_acumulado_anticipos'] ?? 0), 2, ',', '.')?></span></div>
                              </div>
                              <div class="form-group row mb-1">
                                <label class="col-sm-5 col-form-label">Acumulado desacopios</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($moneda)?> <?=number_format((float)($cabecera_avance['monto_acumulado_desacopios'] ?? 0), 2, ',', '.')?></span></div>
                              </div>
                            </div>
                            <div class="col-lg-6">
                              <div class="form-group row mb-1">
                                <label class="col-sm-5 col-form-label">Acumulado descuentos</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($moneda)?> <?=number_format((float)($cabecera_avance['monto_acumulado_descuentos'] ?? 0), 2, ',', '.')?></span></div>
                              </div>
                              <div class="form-group row mb-1">
                                <label class="col-sm-5 col-form-label">Acumulado redeterminaciones</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=escaparAvance($moneda)?> <?=number_format((float)($cabecera_avance['monto_acumulado_ajustes'] ?? 0), 2, ',', '.')?></span></div>
                              </div><?php } ?>
                              <div class="form-group row mb-1">
                                <label class="col-sm-5 col-form-label">Estado de aprobación</label>
                                <div class="col-sm-7"><span class="badge <?=($cabecera_avance['aprobado_cliente'] ?? 0) == 1 ? 'badge-success' : 'badge-warning'?>"><?=($cabecera_avance['aprobado_cliente'] ?? 0) == 1 ? 'Aprobado Cliente' : 'Pendiente'?></span></div>
                              </div>
                            </div>
                          </div>
                          <?php if (!empty(trim((string)($cabecera_avance['observaciones'] ?? '')))) { ?>
                          <div class="form-group row mb-2">
                            <label class="col-sm-2 col-form-label">Observaciones</label>
                            <div class="col-sm-10"><span class="form-control-plaintext"><?=escaparAvance($cabecera_avance['observaciones'])?></span></div>
                          </div>
                          <?php } ?>
                          <hr class="mt-3 mb-3">
                          <div class="form-group row">
                            <div class="col-12">
                              <h6 class="mb-3 font-weight-bold">Items OCC y aperturados</h6>
                              <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="tabla_occ_avances" style="width:100%">
                                  <thead>
                                    <tr>
                                      <th>ID</th>
                                      <th>Posición</th>
                                      <th>Descripcion</th>
                                      <th class="text-right">Cantidad</th>
                                      <th class="text-right <?= $esOpAvance ? 'd-none' : '' ?>">Precio unitario</th>
                                      <th class="text-right <?= $esOpAvance ? 'd-none' : '' ?>">Descuento</th>
                                      <th class="text-right <?= $esOpAvance ? 'd-none' : '' ?>">Subtotal</th>
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
                                          <td><?=escaparAvance($occ_row['posicion'])?></td>
                                          <td><?=escaparAvance($occ_row['descripcion'])?></td>
                                          <td class="text-right"><?=number_format((float) $occ_row['cantidad'], 2, ',', '.')?></td>
                                          <td class="text-right <?= $esOpAvance ? 'd-none' : '' ?>"><?=escaparAvance($moneda)?> <?=number_format((float) $occ_row['precio_unitario'], 2, ',', '.')?></td>
                                          <td class="text-right <?= $esOpAvance ? 'd-none' : '' ?>"><?=escaparAvance($moneda)?> <?=number_format((float) $occ_row['descuento'], 2, ',', '.')?></td>
                                          <td class="text-right <?= $esOpAvance ? 'd-none' : '' ?>"><?=escaparAvance($moneda)?> <?=number_format((float) $occ_row['subtotal'], 2, ',', '.')?></td>
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
        let totalCm = 0;
        grupo.find('.fila-detalle-avance').each(function() {
          let fila = $(this);
          totalActual += obtenerNumeroAvance(fila.find("input[name='subtotal[]']").val());
          totalAcumulado += obtenerNumeroAvance(fila.attr('data-monto-acumulado'));
          totalCm += obtenerNumeroAvance(fila.attr('data-subtotal-cm'));
        });

        const celdaSaldo = grupo.find('.total-grupo-saldo');
        const monedaGrupo = celdaSaldo.attr('data-moneda') || '';
        const totalSaldo = totalCm - totalAcumulado;

        grupo.find('.total-grupo-avance').text(formatearNumeroAvance(totalActual));
        grupo.find('.total-grupo-acumulado').text(formatearNumeroAvance(totalAcumulado));
        celdaSaldo.text(monedaGrupo + ' ' + formatearNumeroAvance(totalSaldo)).attr('data-total-saldo', totalSaldo.toFixed(2));
      }

      function actualizarSubtotalAvance(fila) {
        let avance = obtenerNumeroAvance(fila.find("input[name='avance[]']").val());
        let precioUnitario = obtenerNumeroAvance(fila.find("input[name='precio_unitario[]']").val());
        let cantidadTotal = obtenerNumeroAvance(fila.attr('data-cantidad-total'));
        let cantidadAnterior = obtenerNumeroAvance(fila.attr('data-cantidad-anterior'));
        let subtotalCm = obtenerNumeroAvance(fila.attr('data-subtotal-cm'));
        let cantidadAcumulada = cantidadAnterior + avance;
        let porcentajeActual = cantidadTotal > 0 ? (avance / cantidadTotal) * 100 : 0;
        let porcentajeAcumulado = cantidadTotal > 0 ? (cantidadAcumulada / cantidadTotal) * 100 : 0;
        let saldoCantidad = Math.max(0, cantidadTotal - cantidadAcumulada);
        let porcentajeSaldo = cantidadTotal > 0 ? (saldoCantidad / cantidadTotal) * 100 : 0;
        let subtotal = avance * precioUnitario;
        let montoAcumulado = cantidadAcumulada * precioUnitario;
        let montoSaldo = subtotalCm - montoAcumulado;

        fila.find("input[name='subtotal[]']").val(subtotal.toFixed(2));
        fila.find('.porcentaje-actual').text(formatearNumeroAvance(porcentajeActual) + '%');
        fila.find('.subtotal_formatted').text(formatearNumeroAvance(subtotal));
        fila.find('.cantidad-acumulada').text(formatearNumeroAvance(cantidadAcumulada));
        fila.find('.porcentaje-acumulado').text(formatearNumeroAvance(porcentajeAcumulado) + '%');
        fila.find('.monto-acumulado').text(formatearNumeroAvance(montoAcumulado));
        fila.find('.cantidad-saldo').text(formatearNumeroAvance(saldoCantidad));
        fila.find('.porcentaje-saldo').text(formatearNumeroAvance(porcentajeSaldo) + '%');
        fila.find('.monto-saldo').text(formatearNumeroAvance(montoSaldo));
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