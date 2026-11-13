<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

require 'database.php';

$idRevision = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$prod = !empty($_GET['prod']) ? (int)$_GET['prod'] : 0;
$urlListado = 'listarPackingList.php' . ($prod ? '?prod=' . $prod : '');
if (!$idRevision || $idRevision < 1) {
  header('Location: ' . $urlListado);
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqlPackingList = "SELECT plr.id, plr.id_packing_list, plr.id_proyecto, plr.nombre, plr.numero,
                          plr.fecha, DATE_FORMAT(plr.fecha, '%d/%m/%Y') AS fecha_formateada,
                          plr.id_usuario, plr.id_estado_packing_list, e.estado,
                          plr.nro_revision, plr.descripcion, plr.anulado,
                          pl.ultimo_nro_revision, pl.anulado AS packing_anulado, pl.id_tarea,
                          p.nro AS nro_proyecto, p.nombre AS proyecto, p.id_cliente,
                          s.nro_sitio, s.nro_subsitio, cliente.nombre AS cliente,
                          u.usuario AS usuario_alta,
                          realizo.nombre AS realizo, reviso.nombre AS reviso, valido.nombre AS valido,
                          t.observaciones AS tarea_observaciones, tt.tipo AS tipo_tarea
                   FROM packing_lists_revisiones plr
                   INNER JOIN packing_lists pl ON pl.id = plr.id_packing_list
                   INNER JOIN proyectos p ON p.id = plr.id_proyecto
                   INNER JOIN sitios s ON s.id = p.id_sitio
                   LEFT JOIN cuentas cliente ON cliente.id = p.id_cliente
                   LEFT JOIN usuarios u ON u.id = plr.id_usuario
                   LEFT JOIN estados_packing_list e ON e.id = plr.id_estado_packing_list
                   LEFT JOIN cuentas realizo ON realizo.id = plr.id_cuenta_realizo
                   LEFT JOIN cuentas reviso ON reviso.id = plr.id_cuenta_reviso
                   LEFT JOIN cuentas valido ON valido.id = plr.id_cuenta_valido
                   LEFT JOIN tareas t ON t.id = pl.id_tarea
                   LEFT JOIN tipos_tarea tt ON tt.id = t.id_tipo_tarea
                   WHERE plr.id = ?";
$q = $pdo->prepare($sqlPackingList);
$q->execute([$idRevision]);
$packingList = $q->fetch(PDO::FETCH_ASSOC);

if (!$packingList) {
  Database::disconnect();
  header('Location: ' . $urlListado);
  exit;
}

$qHistorial = $pdo->prepare(
  "SELECT plr.id, plr.nro_revision, DATE_FORMAT(plr.fecha, '%d/%m/%Y') AS fecha,
          plr.descripcion, e.estado, plr.anulado,
          realizo.nombre AS realizo, reviso.nombre AS reviso, valido.nombre AS valido
   FROM packing_lists_revisiones plr
   LEFT JOIN estados_packing_list e ON e.id = plr.id_estado_packing_list
   LEFT JOIN cuentas realizo ON realizo.id = plr.id_cuenta_realizo
   LEFT JOIN cuentas reviso ON reviso.id = plr.id_cuenta_reviso
   LEFT JOIN cuentas valido ON valido.id = plr.id_cuenta_valido
   WHERE plr.id_packing_list = ?
   ORDER BY plr.nro_revision, plr.id"
);
$qHistorial->execute([$packingList['id_packing_list']]);
$historial = $qHistorial->fetchAll(PDO::FETCH_ASSOC);

$qSecciones = $pdo->prepare(
  "SELECT id, cantidad, observaciones
   FROM packing_lists_secciones
   WHERE id_packing_list_revision = ?
   ORDER BY id"
);
$qSecciones->execute([$idRevision]);
$secciones = $qSecciones->fetchAll(PDO::FETCH_ASSOC);

$qComponentes = $pdo->prepare(
  "SELECT plc.id, plc.id_packing_list_seccion, plc.id_conjunto_lista_corte,
          plc.id_concepto, plc.id_computo_detalle, plc.cantidad, plc.cantidad_despachada,
          plc.observaciones, ecp.estado AS estado_componente,
          m.codigo, m.concepto, m.largo, m.peso_metro,
          lcc.nombre AS nombre_conjunto, lc.numero AS numero_lista_corte,
          lcc.id_lista_corte, lc.nro_revision AS revision_lista_corte,
          c.id AS id_computo, c.nro AS numero_computo, c.nro_revision AS revision_computo,
          ec.estado AS estado_computo,
          pls.observaciones AS nombre_seccion, pls.cantidad AS cantidad_seccion
   FROM packing_lists_secciones pls
   LEFT JOIN packing_lists_componentes plc ON plc.id_packing_list_seccion = pls.id
   LEFT JOIN estados_componentes_packing_list ecp ON ecp.id = plc.id_estado_componente_packing_list
   LEFT JOIN materiales m ON m.id = plc.id_concepto
   LEFT JOIN listas_corte_conjuntos lcc ON lcc.id = plc.id_conjunto_lista_corte
   LEFT JOIN listas_corte lc ON lc.id = lcc.id_lista_corte
   LEFT JOIN computos_detalle cd ON cd.id = plc.id_computo_detalle
   LEFT JOIN computos c ON c.id = cd.id_computo
   LEFT JOIN estados_computos ec ON ec.id = c.id_estado
   WHERE pls.id_packing_list_revision = ?
   ORDER BY pls.id, plc.id"
);
$qComponentes->execute([$idRevision]);
$filasComponentes = $qComponentes->fetchAll(PDO::FETCH_ASSOC);

$componentes = [];
$componentesPorSeccion = [];
$pesosPorSeccion = [];
$pesoTotal = 0.0;
$idsConjuntosLC = [];
foreach ($filasComponentes as $componente) {
  if (empty($componente['id'])) {
    continue;
  }

  $peso = 0.0;
  if (!empty($componente['id_conjunto_lista_corte'])) {
    $peso = calcularPesoConjunto($pdo, (int)$componente['id_conjunto_lista_corte']);
    $idsConjuntosLC[] = (int)$componente['id_conjunto_lista_corte'];
  } elseif ($componente['largo'] !== null && $componente['peso_metro'] !== null) {
    $peso = (float)$componente['largo'] * (float)$componente['peso_metro'] / 1000;
  }

  $componente['peso_estimado'] = $peso;
  $idSeccion = (int)$componente['id_packing_list_seccion'];
  $componentes[] = $componente;
  $componentesPorSeccion[$idSeccion][] = $componente;
  $pesosPorSeccion[$idSeccion] = ($pesosPorSeccion[$idSeccion] ?? 0) + $peso;
  $pesoTotal += $peso;
}

$idsConjuntosLC = array_values(array_unique(array_filter($idsConjuntosLC)));
$posicionesConjuntos = [];
if ($idsConjuntosLC) {
  $marcadores = implode(',', array_fill(0, count($idsConjuntosLC), '?'));
  $sqlPosiciones = "SELECT lcc.id AS id_conjunto, lcc.nombre AS conjunto,
                           lc.id AS id_lista_corte, lc.numero AS numero_lista_corte,
                           lc.nro_revision AS revision_lista_corte,
                           lcp.id AS id_posicion, lcp.posicion, lcp.cantidad,
                           lcp.largo, lcp.ancho, lcp.marca, lcp.peso,
                           lcp.peso_calculado_posicion, lcp.finalizado,
                           lcp.diametro, lcp.calidad, lcp.observaciones,
                           m.codigo, m.concepto, col.nro_colada,
                           COALESCE((
                             SELECT GROUP_CONCAT(DISTINCT CONCAT(tp.tipo, ' — ', ep.estado) ORDER BY tp.tipo SEPARATOR ', ')
                             FROM lista_corte_procesos lcpr
                             INNER JOIN tipos_procesos tp ON tp.id = lcpr.id_tipo_proceso
                             INNER JOIN estados_lista_corte_procesos ep ON ep.id = lcpr.id_estado_lista_corte_proceso
                             WHERE lcpr.id_lista_corte_posicion = lcp.id
                           ), '') AS procesos
                    FROM listas_corte_conjuntos lcc
                    INNER JOIN listas_corte lc ON lc.id = lcc.id_lista_corte
                    INNER JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto = lcc.id
                    LEFT JOIN materiales m ON m.id = lcp.id_material
                    LEFT JOIN coladas col ON col.id = lcp.id_colada
                    WHERE lcc.id IN ($marcadores)
                    ORDER BY lcc.id, lcp.posicion";
  $qPosiciones = $pdo->prepare($sqlPosiciones);
  $qPosiciones->execute($idsConjuntosLC);
  $posicionesConjuntos = $qPosiciones->fetchAll(PDO::FETCH_ASSOC);
}

Database::disconnect();

$escape = static function ($value): string {
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$formatNumber = static function ($value, int $decimales = 2): string {
  return number_format((float)($value ?? 0), $decimales, ',', '.');
};

$estadoActual = (int)$packingList['nro_revision'] === (int)$packingList['ultimo_nro_revision'];
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <?php include 'head_tables.php'; ?>
    <style>
      .dato-label { font-weight: 600; color: #495057; }
      .dato-valor { overflow-wrap: anywhere; }
      .detalle-tabla th, .detalle-tabla td { vertical-align: middle; }
      .badge-estado { font-size: .9rem; }
    </style>
  </head>
  <body>
    <div class="page-wrapper">
      <?php include 'header.php'; ?>
      <div class="page-body-wrapper">
        <?php include 'menu.php'; ?>
        <div class="page-body">
          <?php $ubicacion = 'Ver Packing List'; include_once 'head_page.php'; ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$escape($ubicacion)?> Nº <?=$escape($packingList['numero'])?> — Revisión <?=$escape($packingList['nro_revision'])?></h5>
                  </div>
                  <div class="card-body">
                    <h5 class="mb-3">Información general</h5>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group row"><div class="col-sm-4 dato-label">Nombre</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['nombre'])?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Estado</div><div class="col-sm-8 dato-valor"><span class="badge badge-primary badge-estado"><?=$escape($packingList['estado'] ?: '—')?></span><?php if (!$estadoActual): ?> <span class="badge badge-secondary">Revisión anterior</span><?php endif; ?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Fecha de emisión</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['fecha_formateada'])?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Descripción / motivo</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['descripcion'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Usuario de alta</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['usuario_alta'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Revisión vigente</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['ultimo_nro_revision'])?></div></div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group row"><div class="col-sm-4 dato-label">Proyecto</div><div class="col-sm-8 dato-valor"><a href="verProyecto.php?id=<?=(int)$packingList['id_proyecto']?>" target="_blank" rel="noopener"><?=$escape($packingList['nro_sitio'])?>-<?=$escape($packingList['nro_subsitio'])?>-<?=$escape($packingList['nro_proyecto'])?>: <?=$escape($packingList['proyecto'])?></a></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Cliente</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['cliente'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Tarea asociada</div><div class="col-sm-8 dato-valor"><?php if (!empty($packingList['id_tarea'])): ?><a href="verTarea.php?id=<?=(int)$packingList['id_tarea']?>" target="_blank" rel="noopener"><?=$escape($packingList['tipo_tarea'] ?: 'Tarea')?> #<?=(int)$packingList['id_tarea']?></a><?php if (!empty($packingList['tarea_observaciones'])): ?> — <?=$escape($packingList['tarea_observaciones'])?><?php endif; ?><?php else: ?>—<?php endif; ?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Elaboró</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['realizo'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Revisó</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['reviso'] ?: '—')?></div></div>
                        <div class="form-group row"><div class="col-sm-4 dato-label">Aprobó</div><div class="col-sm-8 dato-valor"><?=$escape($packingList['valido'] ?: '—')?></div></div>
                      </div>
                    </div>

                    <h5 class="mt-4 mb-3">Historial de revisiones</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaHistorialPL">
                        <thead class="thead-light"><tr><th>Rev.</th><th>Fecha</th><th>Modificaciones</th><th>Estado</th><th>Elaboró</th><th>Revisó</th><th>Aprobó</th><th>Registro</th></tr></thead>
                        <tbody><?php foreach ($historial as $revision): ?>
                          <tr>
                            <td><?=$escape($revision['nro_revision'])?></td>
                            <td><?=$escape($revision['fecha'])?></td>
                            <td><?=$escape($revision['descripcion'] ?: '—')?></td>
                            <td><?=$escape($revision['estado'] ?: '—')?></td>
                            <td><?=$escape($revision['realizo'] ?: '—')?></td>
                            <td><?=$escape($revision['reviso'] ?: '—')?></td>
                            <td><?=$escape($revision['valido'] ?: '—')?></td>
                            <td><?=$revision['anulado'] ? 'Anulada' : ((int)$revision['id'] === (int)$idRevision ? 'Seleccionada' : '—')?></td>
                          </tr>
                        <?php endforeach; ?></tbody>
                      </table>
                    </div>

                    <h5 class="mt-4 mb-3">Secciones</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaSeccionesPL">
                        <thead class="thead-light"><tr><th>Sección</th><th>Cantidad</th><th>Componentes</th><th>Peso estimado (kg)</th></tr></thead>
                        <tbody><?php foreach ($secciones as $seccion):
                          $idSeccion = (int)$seccion['id'];
                          ?>
                          <tr>
                            <td><?=$escape($seccion['observaciones'])?></td>
                            <td class="text-right"><?=$formatNumber($seccion['cantidad'], 0)?></td>
                            <td class="text-right"><?=count($componentesPorSeccion[$idSeccion] ?? [])?></td>
                            <td class="text-right"><?=$formatNumber($pesosPorSeccion[$idSeccion] ?? 0)?></td>
                          </tr>
                        <?php endforeach; ?></tbody>
                      </table>
                    </div>

                    <h5 class="mt-4 mb-3">Componentes</h5>
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered display detalle-tabla" id="tablaComponentesPL">
                        <thead class="thead-light"><tr><th>Sección</th><th>Tipo / origen</th><th>Componente</th><th>Cantidad</th><th>Despachada</th><th>Estado</th><th>Largo ref.</th><th>Peso estimado (kg)</th><th>Observaciones</th></tr></thead>
                        <tbody><?php foreach ($componentes as $componente): ?>
                          <?php
                            if (!empty($componente['id_conjunto_lista_corte'])) {
                              $origen = 'Conjunto de LC';
                              $nombreComponente = $componente['nombre_conjunto'] ?: 'Conjunto';
                              $urlOrigen = 'verListaCorte.php?id=' . (int)$componente['id_lista_corte'];
                              $textoOrigen = trim((string)$componente['numero_lista_corte'] . ' Rev. ' . (string)$componente['revision_lista_corte']);
                            } elseif (!empty($componente['id_computo_detalle'])) {
                              $origen = 'Cómputo';
                              $nombreComponente = trim((string)$componente['codigo'] . ' — ' . (string)$componente['concepto']);
                              $urlOrigen = !empty($componente['id_computo']) ? 'verDetalleComputo.php?id=' . (int)$componente['id_computo'] : '';
                              $textoOrigen = !empty($componente['numero_computo']) ? 'Nº ' . $componente['numero_computo'] . ' Rev. ' . $componente['revision_computo'] : 'Detalle de material';
                            } else {
                              $origen = 'Material';
                              $nombreComponente = trim((string)$componente['codigo'] . ' — ' . (string)$componente['concepto']);
                              $urlOrigen = '';
                              $textoOrigen = '';
                            }
                          ?>
                          <tr>
                            <td><?=$escape($componente['nombre_seccion'])?></td>
                            <td><?=$escape($origen)?><?php if ($textoOrigen !== ''): ?><br><?php if ($urlOrigen !== ''): ?><a href="<?=$escape($urlOrigen)?>" target="_blank" rel="noopener"><?=$escape($textoOrigen)?></a><?php else: ?><?=$escape($textoOrigen)?><?php endif; ?><?php endif; ?></td>
                            <td><?=$escape($nombreComponente)?></td>
                            <td class="text-right"><?=$formatNumber($componente['cantidad'], 0)?></td>
                            <td class="text-right"><?=$formatNumber($componente['cantidad_despachada'], 0)?></td>
                            <td><?=$escape($componente['estado_componente'] ?: '—')?></td>
                            <td class="text-right"><?=$componente['largo'] !== null ? $formatNumber($componente['largo']) : '—'?></td>
                            <td class="text-right"><?=$formatNumber($componente['peso_estimado'])?></td>
                            <td><?=$escape($componente['observaciones'] ?: '—')?></td>
                          </tr>
                        <?php endforeach; ?></tbody>
                      </table>
                    </div>

                    <?php if ($posicionesConjuntos): ?>
                      <h5 class="mt-4 mb-3">Posiciones incluidas por conjuntos de LC</h5>
                      <div class="dt-ext table-responsive">
                        <table class="table table-sm table-bordered display detalle-tabla" id="tablaPosicionesPL">
                          <thead class="thead-light"><tr><th>LC origen</th><th>Conjunto</th><th>Posición</th><th>Código</th><th>Concepto</th><th>Cantidad</th><th>Largo</th><th>Ancho</th><th>Marca</th><th>Colada</th><th>Diámetro</th><th>Calidad</th><th>Finalizada</th><th>Procesos / estado</th><th>Observaciones</th></tr></thead>
                          <tbody><?php foreach ($posicionesConjuntos as $posicion): ?>
                            <tr>
                              <td><a href="verListaCorte.php?id=<?=(int)$posicion['id_lista_corte']?>" target="_blank" rel="noopener"><?=$escape($posicion['numero_lista_corte'])?> Rev. <?=$escape($posicion['revision_lista_corte'])?></a></td>
                              <td><?=$escape($posicion['conjunto'])?></td>
                              <td><?=$escape($posicion['posicion'])?></td>
                              <td><?=$escape($posicion['codigo'] ?: '—')?></td>
                              <td><?=$escape($posicion['concepto'] ?: '—')?></td>
                              <td class="text-right"><?=$formatNumber($posicion['cantidad'])?></td>
                              <td class="text-right"><?=$formatNumber($posicion['largo'])?></td>
                              <td class="text-right"><?=$formatNumber($posicion['ancho'])?></td>
                              <td><?=$escape($posicion['marca'] ?: '—')?></td>
                              <td><?=$escape($posicion['nro_colada'] ?: '—')?></td>
                              <td class="text-right"><?=$formatNumber($posicion['diametro'])?></td>
                              <td><?=$escape($posicion['calidad'] ?: '—')?></td>
                              <td><?=$posicion['finalizado'] ? 'Sí' : 'No'?></td>
                              <td><?=$escape($posicion['procesos'] ?: '—')?></td>
                              <td><?=$escape($posicion['observaciones'] ?: '—')?></td>
                            </tr>
                          <?php endforeach; ?></tbody>
                        </table>
                      </div>
                    <?php endif; ?>

                    <div class="text-right mt-3"><strong>Peso estimado total:</strong> <?=$formatNumber($pesoTotal)?> kg</div>
                  </div>
                  <div class="card-footer">
                    <a href="imprimirPackingList.php?peso=1&id=<?=(int)$idRevision?><?= $prod ? '&amp;prod=' . $prod : '' ?>" class="btn btn-primary" target="_blank" rel="noopener">Imprimir con peso</a>
                    <a href="imprimirPackingList.php?peso=0&id=<?=(int)$idRevision?><?= $prod ? '&amp;prod=' . $prod : '' ?>" class="btn btn-outline-primary" target="_blank" rel="noopener">Imprimir sin peso</a>
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
        ['#tablaHistorialPL', '#tablaSeccionesPL', '#tablaComponentesPL', '#tablaPosicionesPL'].forEach(function(selector) {
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
