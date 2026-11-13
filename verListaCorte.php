<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

require 'database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$prod = !empty($_GET['prod']) ? (int)$_GET['prod'] : 0;
$urlListado = 'listarListasCorte.php' . ($prod ? '?prod=' . $prod : '');
if (!$id || $id < 1) {
  header('Location: ' . $urlListado);
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqlCabecera = "SELECT lc.id, lc.id_proyecto, lc.id_tarea, lc.fecha,
                       DATE_FORMAT(lc.fecha, '%d/%m/%Y') AS fecha_formateada,
                       lc.id_usuario, lc.id_estado_lista_corte, e.estado,
                       lc.nro_revision, lc.anulado, lc.nombre, lc.numero, lc.adjunto, lc.descripcion,
                       p.nro AS nro_proyecto, p.nombre AS proyecto, s.nro_sitio, s.nro_subsitio,
                       cliente.nombre AS cliente, u.usuario AS usuario_alta,
                       realizo.nombre AS realizo, reviso.nombre AS reviso, valido.nombre AS valido,
                       t.observaciones AS tarea_observaciones
                FROM listas_corte lc
                INNER JOIN proyectos p ON p.id = lc.id_proyecto
                INNER JOIN sitios s ON s.id = p.id_sitio
                INNER JOIN estados_lista_corte e ON e.id = lc.id_estado_lista_corte
                LEFT JOIN cuentas cliente ON cliente.id = p.id_cliente
                LEFT JOIN usuarios u ON u.id = lc.id_usuario
                LEFT JOIN cuentas realizo ON realizo.id = lc.id_cuenta_realizo
                LEFT JOIN cuentas reviso ON reviso.id = lc.id_cuenta_reviso
                LEFT JOIN cuentas valido ON valido.id = lc.id_cuenta_valido
                LEFT JOIN tareas t ON t.id = lc.id_tarea
                WHERE lc.id = ?";
$q = $pdo->prepare($sqlCabecera);
$q->execute([$id]);
$listaCorte = $q->fetch(PDO::FETCH_ASSOC);

if (!$listaCorte) {
  Database::disconnect();
  header('Location: ' . $urlListado);
  exit;
}

$sqlHistorial = "SELECT lc.id, lc.nro_revision, DATE_FORMAT(lc.fecha, '%d/%m/%Y') AS fecha,
                        lc.descripcion, e.estado,
                        realizo.nombre AS realizo, reviso.nombre AS reviso, valido.nombre AS valido
                 FROM listas_corte lc
                 LEFT JOIN estados_lista_corte e ON e.id = lc.id_estado_lista_corte
                 LEFT JOIN cuentas realizo ON realizo.id = lc.id_cuenta_realizo
                 LEFT JOIN cuentas reviso ON reviso.id = lc.id_cuenta_reviso
                 LEFT JOIN cuentas valido ON valido.id = lc.id_cuenta_valido
                 WHERE lc.id_proyecto = ? AND lc.numero = ? AND lc.anulado = 0
                 ORDER BY lc.nro_revision, lc.id";
$qHistorial = $pdo->prepare($sqlHistorial);
$qHistorial->execute([$listaCorte['id_proyecto'], $listaCorte['numero']]);
$historial = $qHistorial->fetchAll(PDO::FETCH_ASSOC);

$sqlConjuntos = "SELECT lcc.id, lcc.nombre, lcc.cantidad, lcc.peso, elcc.estado
                 FROM listas_corte_conjuntos lcc
                 LEFT JOIN estados_lista_corte_conjuntos elcc ON elcc.id = lcc.id_estado_lista_corte_conjuntos
                 WHERE lcc.id_lista_corte = ?
                 ORDER BY lcc.id";
$qConjuntos = $pdo->prepare($sqlConjuntos);
$qConjuntos->execute([$id]);
$conjuntos = $qConjuntos->fetchAll(PDO::FETCH_ASSOC);

$sqlPosiciones = "SELECT lcc.id AS id_conjunto, lcc.nombre AS conjunto, lcc.cantidad AS cantidad_conjuntos,
                         lcp.id AS id_posicion, lcp.posicion, lcp.cantidad,
                         lcp.largo, lcp.ancho, lcp.marca, lcp.peso, lcp.peso_calculado_posicion,
                         lcp.finalizado, lcp.diametro, lcp.calidad, lcp.observaciones,
                         m.codigo, m.concepto, cat.categoria, col.nro_colada,
                         COALESCE(otd.cant_ot, 0) AS cant_ot,
                         COALESCE(otd.cant_liberadas, 0) AS cant_liberadas,
                         COALESCE(otd.cant_rechazadas, 0) AS cant_rechazadas,
                         COALESCE(otd.cant_reproceso, 0) AS cant_reproceso,
                         COALESCE((
                           SELECT GROUP_CONCAT(DISTINCT CONCAT(tp.tipo, ' — ', ep.estado) ORDER BY tp.tipo SEPARATOR ', ')
                           FROM lista_corte_procesos lcpr
                           INNER JOIN tipos_procesos tp ON tp.id = lcpr.id_tipo_proceso
                           INNER JOIN estados_lista_corte_procesos ep ON ep.id = lcpr.id_estado_lista_corte_proceso
                           WHERE lcpr.id_lista_corte_posicion = lcp.id
                         ), '') AS procesos
                  FROM listas_corte_conjuntos lcc
                  INNER JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto = lcc.id
                  LEFT JOIN materiales m ON m.id = lcp.id_material
                  LEFT JOIN categorias cat ON cat.id = m.id_categoria
                  LEFT JOIN coladas col ON col.id = lcp.id_colada
                  LEFT JOIN (
                    SELECT otd.id_posicion,
                           SUM(CASE WHEN ot.id_estado_orden_trabajo IN (3, 4) THEN otd.cantidad ELSE 0 END) AS cant_ot,
                           SUM(otd.cant_liberadas) AS cant_liberadas,
                           SUM(otd.cant_rechazadas) AS cant_rechazadas,
                           SUM(otd.cant_reproceso) AS cant_reproceso
                    FROM ordenes_trabajo_detalle otd
                    INNER JOIN ordenes_trabajo ot ON ot.id = otd.id_orden_trabajo
                    WHERE ot.anulado = 0
                    GROUP BY otd.id_posicion
                  ) otd ON otd.id_posicion = lcp.id
                  WHERE lcc.id_lista_corte = ?
                  ORDER BY lcc.id, lcp.posicion";
$qPosiciones = $pdo->prepare($sqlPosiciones);
$qPosiciones->execute([$id]);
$posiciones = $qPosiciones->fetchAll(PDO::FETCH_ASSOC);

$sqlOrdenes = "SELECT ot.id, ot.numero, ot.nro_orden_trabajo, ot.nro_revision,
                      DATE_FORMAT(ot.fecha, '%d/%m/%Y') AS fecha,
                      ot.titulo, ot.descripcion, ot.notas, e.estado, u.usuario
               FROM ordenes_trabajo ot
               LEFT JOIN estados_orden_trabajo e ON e.id = ot.id_estado_orden_trabajo
               LEFT JOIN usuarios u ON u.id = ot.id_usuario
               WHERE ot.id_lista_corte = ? AND ot.anulado = 0
               ORDER BY ot.numero, ot.nro_revision, ot.id";
$qOrdenes = $pdo->prepare($sqlOrdenes);
$qOrdenes->execute([$id]);
$ordenesTrabajo = $qOrdenes->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

$escape = static function ($value): string {
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$formatNumber = static function ($value, int $decimales = 2): string {
  return number_format((float)($value ?? 0), $decimales, ',', '.');
};

$posicionesPorConjunto = [];
$pesoPorConjunto = [];
foreach ($posiciones as $posicion) {
  $idConjunto = (int)$posicion['id_conjunto'];
  $posicion['peso_total'] = calcularPesoPosicion($posicion, (string)$posicion['concepto']);
  $posicionesPorConjunto[$idConjunto][] = $posicion;
  $pesoPorConjunto[$idConjunto] = ($pesoPorConjunto[$idConjunto] ?? 0) + $posicion['peso_total'];
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <?php include 'head_tables.php'; ?>
    <style>
      .dato-label { font-weight: 600; color: #495057; }
      .dato-valor { overflow-wrap: anywhere; }
      .detalle-tabla th, .detalle-tabla td { vertical-align: middle; }
      .tabla-posiciones td { white-space: normal; }
      .badge-estado { font-size: .9rem; }
    </style>
  </head>
  <body>
    <div class="page-wrapper">
      <?php include 'header.php'; ?>
      <div class="page-body-wrapper">
        <?php include 'menu.php'; ?>
        <div class="page-body">
          <?php $ubicacion = 'Detalle de Lista de Corte'; include_once 'head_page.php'; ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$escape($ubicacion)?> Nº <?=$escape($listaCorte['numero'])?> — Revisión <?=$escape($listaCorte['nro_revision'])?></h5>
                  </div>
                  <div class="card-body">
                    <h5 class="mb-3">Información general</h5>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group row"><div class="col-sm-4 dato-label">Nombre</div><div class="col-sm-8 dato-valor"><?=$escape($listaCorte['nombre'])?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Estado</div><div class="col-sm-8 dato-valor"><span class="badge badge-primary badge-estado"><?=$escape($listaCorte['estado'])?></span></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Fecha de emisión</div><div class="col-sm-8 dato-valor"><?=$escape($listaCorte['fecha_formateada'])?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Plano adjunto</div><div class="col-sm-8 dato-valor"><?php if (!empty($listaCorte['adjunto'])): ?><a href="<?=$escape($listaCorte['adjunto'])?>" target="_blank" rel="noopener" title="Abrir plano"><i class="fa fa-file-o mr-1"></i><?=$escape(basename($listaCorte['adjunto']))?></a><?php else: ?>—<?php endif; ?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Descripción de revisión</div><div class="col-sm-8 dato-valor"><?=$escape($listaCorte['descripcion'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Usuario de alta</div><div class="col-sm-8 dato-valor"><?=$escape($listaCorte['usuario_alta'] ?: '—')?></div></div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group row"><div class="col-sm-4 dato-label">Proyecto</div><div class="col-sm-8 dato-valor"><a href="verProyecto.php?id=<?=(int)$listaCorte['id_proyecto']?>" target="_blank" rel="noopener"><?=$escape($listaCorte['nro_sitio'])?>-<?=$escape($listaCorte['nro_subsitio'])?>-<?=$escape($listaCorte['nro_proyecto'])?>: <?=$escape($listaCorte['proyecto'])?></a></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Cliente</div><div class="col-sm-8 dato-valor"><?=$escape($listaCorte['cliente'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Tarea</div><div class="col-sm-8 dato-valor"><?php if (!empty($listaCorte['id_tarea'])): ?><a href="verTarea.php?id=<?=(int)$listaCorte['id_tarea']?>" target="_blank" rel="noopener"><?=$escape($listaCorte['id_tarea'])?></a><?php if (!empty($listaCorte['tarea_observaciones'])): ?> — <?=$escape($listaCorte['tarea_observaciones'])?><?php endif; ?><?php else: ?>—<?php endif; ?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Elaboró</div><div class="col-sm-8 dato-valor"><?=$escape($listaCorte['realizo'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Revisó</div><div class="col-sm-8 dato-valor"><?=$escape($listaCorte['reviso'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Aprobó</div><div class="col-sm-8 dato-valor"><?=$escape($listaCorte['valido'] ?: '—')?></div></div>
                      </div>
                    </div>

                    <h5 class="mt-4 mb-3">Historial de revisiones</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaHistorialLC">
                        <thead class="thead-light"><tr><th>Revisión</th><th>Fecha</th><th>Modificaciones</th><th>Estado</th><th>Elaboró</th><th>Revisó</th><th>Aprobó</th></tr></thead>
                        <tbody><?php foreach ($historial as $revision): ?>
                          <tr>
                            <td><?=$escape($revision['nro_revision'])?></td>
                            <td><?=$escape($revision['fecha'])?></td>
                            <td><?=$escape($revision['descripcion'] ?: '—')?></td>
                            <td><?=$escape($revision['estado'] ?: '—')?></td>
                            <td><?=$escape($revision['realizo'] ?: '—')?></td>
                            <td><?=$escape($revision['reviso'] ?: '—')?></td>
                            <td><?=$escape($revision['valido'] ?: '—')?></td>
                          </tr>
                        <?php endforeach; ?></tbody>
                      </table>
                    </div>

                    <h5 class="mt-4 mb-3">Conjuntos</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaConjuntosLC">
                        <thead class="thead-light"><tr><th>Conjunto</th><th>Cantidad</th><th>Estado</th><th>Posiciones</th><th>Peso total (kg)</th></tr></thead>
                        <tbody><?php foreach ($conjuntos as $conjunto):
                          $idConjunto = (int)$conjunto['id'];
                          $pesoConjunto = round(($pesoPorConjunto[$idConjunto] ?? 0) * numeroListaCorte($conjunto['cantidad']), 2);
                          ?>
                          <tr>
                            <td><?=$escape($conjunto['nombre'])?></td>
                            <td class="text-right"><?=$formatNumber($conjunto['cantidad'], 0)?></td>
                            <td><?=$escape($conjunto['estado'] ?: '—')?></td>
                            <td class="text-right"><?=count($posicionesPorConjunto[$idConjunto] ?? [])?></td>
                            <td class="text-right"><?=$formatNumber($pesoConjunto)?></td>
                          </tr>
                        <?php endforeach; ?></tbody>
                      </table>
                    </div>

                    <h5 class="mt-4 mb-3">Posiciones y avance de fabricación</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla tabla-posiciones" id="tablaPosicionesLC">
                        <thead class="thead-light">
                          <tr><th>Conjunto</th><th>Posición</th><th>Categoría</th><th>Código</th><th>Concepto</th><th>Cant./conj.</th><th>Cant. total</th><th>Largo</th><th>Ancho</th><th>Marca</th><th>Colada</th><th>Diámetro</th><th>Calidad</th><th>Peso total (kg)</th><th>Finalizada</th><th>Procesos / estado</th><th>Cant. OT</th><th>Liberadas</th><th>Rechazadas</th><th>Reproceso</th><th>Observaciones</th></tr>
                        </thead>
                        <tbody><?php foreach ($posiciones as $posicion): ?>
                          <tr>
                            <td><?=$escape($posicion['conjunto'])?></td>
                            <td><?=$escape($posicion['posicion'])?></td>
                            <td><?=$escape($posicion['categoria'] ?: '—')?></td>
                            <td><?=$escape($posicion['codigo'] ?: '—')?></td>
                            <td><?=$escape($posicion['concepto'] ?: '—')?></td>
                            <td class="text-right"><?=$formatNumber($posicion['cantidad'])?></td>
                            <td class="text-right"><?=$formatNumber((float)$posicion['cantidad'] * (float)$posicion['cantidad_conjuntos'])?></td>
                            <td class="text-right"><?=$formatNumber($posicion['largo'])?></td>
                            <td class="text-right"><?=$formatNumber($posicion['ancho'])?></td>
                            <td><?=$escape($posicion['marca'] ?: '—')?></td>
                            <td><?=$escape($posicion['nro_colada'] ?: '—')?></td>
                            <td class="text-right"><?=$formatNumber($posicion['diametro'])?></td>
                            <td><?=$escape($posicion['calidad'] ?: '—')?></td>
                            <td class="text-right"><?=$formatNumber(calcularPesoPosicion($posicion, (string)$posicion['concepto']) * numeroListaCorte($posicion['cantidad_conjuntos']))?></td>
                            <td><?=$posicion['finalizado'] ? 'Sí' : 'No'?></td>
                            <td><?=$escape($posicion['procesos'] ?: '—')?></td>
                            <td class="text-right"><?=$formatNumber($posicion['cant_ot'], 0)?></td>
                            <td class="text-right"><?=$formatNumber($posicion['cant_liberadas'], 0)?></td>
                            <td class="text-right"><?=$formatNumber($posicion['cant_rechazadas'], 0)?></td>
                            <td class="text-right"><?=$formatNumber($posicion['cant_reproceso'], 0)?></td>
                            <td><?=$escape($posicion['observaciones'] ?: '—')?></td>
                          </tr>
                        <?php endforeach; ?></tbody>
                      </table>
                    </div>

                    <h5 class="mt-4 mb-3">Órdenes de Trabajo asociadas</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaOTLC">
                        <thead class="thead-light"><tr><th>Número</th><th>Revisión</th><th>Título</th><th>Fecha</th><th>Estado</th><th>Usuario</th><th>Descripción</th><th>Notas</th></tr></thead>
                        <tbody><?php foreach ($ordenesTrabajo as $orden): ?>
                          <tr>
                            <td><a href="verOrdenTrabajo.php?id=<?=(int)$orden['id']?>" target="_blank" rel="noopener" title="Ver Orden de Trabajo"><i class="fa fa-file-text-o mr-1"></i><?=$escape($orden['numero'])?></a></td>
                            <td><?=$escape($orden['nro_revision'])?></td>
                            <td><?=$escape($orden['titulo'])?></td>
                            <td><?=$escape($orden['fecha'])?></td>
                            <td><?=$escape($orden['estado'] ?: '—')?></td>
                            <td><?=$escape($orden['usuario'] ?: '—')?></td>
                            <td><?=$escape($orden['descripcion'])?></td>
                            <td><?=$escape($orden['notas'])?></td>
                          </tr>
                        <?php endforeach; ?></tbody>
                      </table>
                    </div>
                  </div>
                  <div class="card-footer">
                    <a href="imprimirListaCorte.php?id=<?=(int)$listaCorte['id']?>" class="btn btn-primary" target="_blank" rel="noopener">Imprimir</a>
                    <a href="<?=$escape($urlListado)?>" class="btn btn-light">Volver al listado</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include 'footer.php'; ?>
      </div>
    </div>
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
      $(function() {
        const idioma = {
          decimal: "",
          emptyTable: "No hay información",
          info: "Mostrando _START_ a _END_ de _TOTAL_ Registros",
          infoEmpty: "Mostrando 0 a 0 de 0 Registros",
          infoFiltered: "(Filtrado de _MAX_ total registros)",
          lengthMenu: "Mostrar _MENU_ Registros",
          loadingRecords: "Cargando...",
          processing: "Procesando...",
          search: "Buscar:",
          zeroRecords: "No hay resultados",
          paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
        };
        ['#tablaHistorialLC', '#tablaConjuntosLC', '#tablaPosicionesLC', '#tablaOTLC'].forEach(function(selector) {
          if ($(selector).length) {
            $(selector).DataTable({
              stateSave: false,
              responsive: false,
              pageLength: 25,
              lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
              language: idioma
            });
          }
        });
      });
    </script>
  </body>
</html>
