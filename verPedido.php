<?php
require 'config.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    die('Redirecting to index.php');
}

require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
    $id = $_REQUEST['id'];
}

if ($id === null) {
    header('Location: listarPedidos.php');
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = 'SELECT pe.id, pe.id_computo, pe.id_proyecto, pe.fecha, pe.lugar_entrega, pe.id_cuenta_recibe, pe.aprobado, pe.id_estado, ep.estado AS estado_pedido, c.id_tarea, c.id_cuenta_solicitante FROM pedidos pe LEFT JOIN computos c ON c.id = pe.id_computo LEFT JOIN estados_pedidos ep ON ep.id = pe.id_estado WHERE pe.id = ?';
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
Database::disconnect();

if (!$data) {
    header('Location: listarPedidos.php');
    exit;
}

$isComputo = !empty($data['id_computo']);
$ubicacion = $isComputo ? 'Gestión de Pedido de Cómputo y Nueva Orden de Compra' : 'Gestión de Pedido Directo';
$pageTitle = $isComputo ? 'Información del Pedido de Cómputo' : 'Información del Pedido Directo';
$sectionTitle = $isComputo ? 'Datos del Pedido de Cómputo' : 'Datos del Pedido Directo';
$printUrl = $isComputo ? 'imprimirPedido.php' : 'imprimirPedidoDirecto.php';
$printLabel = $isComputo ? 'Imprimir Pedido' : 'Imprimir';

$pedidoNumero = $data['id'] ?? null;
$obraLabel = '';
$proyectoLabel = '';
$solicitanteNombre = '';
$recibeNombre = '';

$fechaPedido = '';
if (!empty($data['fecha'])) {
    $fechaPedido = date('d/m/Y', strtotime($data['fecha']));
}

if ($pedidoNumero !== null) {
    $pedidoNumero = (int)$pedidoNumero;
}

if ($isComputo && !empty($data['id_computo'])) {
    $pdoObra = Database::connect();
    $pdoObra->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlObra = 'SELECT s.nro_sitio, s.nro_subsitio, p.nro, p.nombre FROM computos c INNER JOIN tareas t ON t.id = c.id_tarea INNER JOIN proyectos p ON p.id = t.id_proyecto INNER JOIN sitios s ON s.id = p.id_sitio WHERE c.id = ? LIMIT 1';
    $stmtObra = $pdoObra->prepare($sqlObra);
    $stmtObra->execute([$data['id_computo']]);
    $obraData = $stmtObra->fetch(PDO::FETCH_ASSOC) ?: [];
    Database::disconnect();

    if (!empty($obraData)) {
        $obraPartes = array_filter([
            $obraData['nro_sitio'] ?? '',
            $obraData['nro_subsitio'] ?? '',
            $obraData['nro'] ?? '',
        ], 'strlen');
        $obraIdentificador = implode('-', $obraPartes);
        $obraLabel = trim($obraIdentificador . ' ' . ($obraData['nombre'] ?? ''));
        $proyectoLabel = $obraLabel;
    }
} elseif (!$isComputo && !empty($data['id_proyecto'])) {
    $pdoObra = Database::connect();
    $pdoObra->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlObra = 'SELECT s.nro_sitio, s.nro_subsitio, p.nro, p.nombre FROM proyectos p INNER JOIN sitios s ON s.id = p.id_sitio WHERE p.id = ? LIMIT 1';
    $stmtObra = $pdoObra->prepare($sqlObra);
    $stmtObra->execute([$data['id_proyecto']]);
    $obraData = $stmtObra->fetch(PDO::FETCH_ASSOC) ?: [];
    Database::disconnect();

    if (!empty($obraData)) {
        $obraPartes = array_filter([
            $obraData['nro_sitio'] ?? '',
            $obraData['nro_subsitio'] ?? '',
            $obraData['nro'] ?? '',
        ], 'strlen');
        $obraIdentificador = implode('-', $obraPartes);
        $obraLabel = trim($obraIdentificador . ' ' . ($obraData['nombre'] ?? ''));
        $proyectoLabel = $obraLabel;
    }
}

$headerInfoParts = [];

if ($pedidoNumero !== null) {
    $headerInfoParts[] = 'Pedido #' . $pedidoNumero;
}

if ($obraLabel !== '') {
    $headerInfoParts[] = 'Obra: ' . $obraLabel;
}

if ($proyectoLabel === '' && !$isComputo && !empty($data['id_proyecto'])) {
    $pdoProyecto = Database::connect();
    $pdoProyecto->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlProyecto = 'SELECT p.nombre FROM proyectos p WHERE p.id = ? LIMIT 1';
    $stmtProyecto = $pdoProyecto->prepare($sqlProyecto);
    $stmtProyecto->execute([$data['id_proyecto']]);
    $proyectoLabel = (string)($stmtProyecto->fetchColumn() ?: '');
    Database::disconnect();
}

if ($isComputo && !empty($data['id_cuenta_solicitante'])) {
    $pdoSolicitante = Database::connect();
    $pdoSolicitante->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlSolicitante = 'SELECT nombre FROM cuentas WHERE id = ? LIMIT 1';
    $stmtSolicitante = $pdoSolicitante->prepare($sqlSolicitante);
    $stmtSolicitante->execute([$data['id_cuenta_solicitante']]);
    $solicitanteNombre = (string)($stmtSolicitante->fetchColumn() ?: '');
    Database::disconnect();
}

if (!empty($data['id_cuenta_recibe'])) {
    $pdoRecibe = Database::connect();
    $pdoRecibe->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlRecibe = 'SELECT nombre FROM cuentas WHERE id = ? LIMIT 1';
    $stmtRecibe = $pdoRecibe->prepare($sqlRecibe);
    $stmtRecibe->execute([$data['id_cuenta_recibe']]);
    $recibeNombre = (string)($stmtRecibe->fetchColumn() ?: '');
    Database::disconnect();
}

$detalleColumns = [
    ['label' => 'Concepto'],
    ['label' => 'Fec. Necesidad'],
    ['label' => 'Fec. Últ. Compra'],
    ['label' => 'Último Precio'],
    ['label' => 'Requerido'],
    ['label' => 'Stock'],
    ['label' => 'Reserv.'],
    ['label' => 'Comprado'],
];

$columnDefs = [
    ['width' => '180px', 'targets' => 0, 'orderable' => true],
    ['width' => '85px', 'targets' => 1, 'orderable' => true],
    ['width' => '85px', 'targets' => 2, 'orderable' => true],
    ['width' => '90px', 'targets' => 3, 'orderable' => true],
    ['width' => '90px', 'targets' => 4, 'orderable' => true],
    ['width' => '60px', 'targets' => 5, 'orderable' => true, 'className' => 'text-center'],
    ['width' => '65px', 'targets' => 6, 'orderable' => true, 'className' => 'text-center'],
    ['width' => '80px', 'targets' => 7, 'orderable' => true, 'className' => 'text-center'],
];

if ($isComputo) {
    $detalleColumns[] = ['label' => 'Pendiente'];
    $columnDefs[] = ['width' => '80px', 'targets' => 8, 'orderable' => true, 'className' => 'text-center'];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include 'head_forms.php'; ?>
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      .form-control-plaintext {
        padding: 0.375rem 0;
        color: #212529;
      }

      .form-group {
        margin-bottom: 1rem;
      }

      .card-body {
        padding: 1.5rem;
      }

      .pedido-info-grid .info-item {
        margin-bottom: 1.25rem;
      }

      .pedido-info-grid .info-label {
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #6c757d;
        font-weight: 600;
        margin-bottom: 0.35rem;
        letter-spacing: 0.02em;
      }

      .pedido-info-grid .info-value {
        font-size: 0.95rem;
        color: #212529;
        margin-bottom: 0;
      }

      #dataTables-example667 {
        width: 100% !important;
        font-size: 0.75rem;
        table-layout: fixed !important;
        border-collapse: collapse !important;
      }

      #dataTables-example667 th,
      #dataTables-example667 td {
        padding: 5px 4px !important;
        vertical-align: middle;
        font-size: 0.75rem;
        overflow: hidden;
        text-overflow: ellipsis;
        box-sizing: border-box !important;
      }

      #dataTables-example667 thead th {
        white-space: nowrap !important;
        padding: 6px 4px !important;
        font-size: 0.7rem;
        font-weight: 600;
        line-height: 1.2;
        background-color: #f8f9fa;
      }

      #dataTables-example667 tbody td {
        white-space: nowrap;
      }

      #dataTables-example667 tbody td.table-concepto {
        white-space: normal;
      }

      .dataTables_wrapper .dataTables_scrollHead,
      .dataTables_wrapper .dataTables_scrollBody {
        overflow: visible !important;
      }

      .dataTables_wrapper {
        overflow-x: auto;
      }

      .dataTables_length select,
      .dataTables_filter input {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
      }

      .dataTables_info,
      .dataTables_length,
      .dataTables_filter {
        font-size: 0.8rem;
      }

      .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
      }

      h6 {
        font-weight: 600;
        margin-bottom: 1rem;
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include 'header.php'; ?>

      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include 'menu.php'; ?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body">
          <?php include_once 'head_page.php'; ?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between w-100">
                      <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($pageTitle); ?></h5>
                        <?php if (!empty($headerInfoParts)): ?>
                        <p class="mb-0 text-muted small"><?php echo htmlspecialchars(implode(' | ', $headerInfoParts)); ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div id="estado-error" class="alert alert-danger mt-3 d-none"></div>
                  </div>
                  <div class="form theme-form" role="presentation" id="form-unificado">
                    <div class="card-body">
                      <h6 class="mb-4"><?php echo htmlspecialchars($sectionTitle); ?></h6>
                      <div class="row pedido-info-grid">
                        <div class="col-12 col-sm-6 col-lg-3 mb-3 mb-lg-0">
                          <div class="info-item">
                            <span class="info-label">Fecha Pedido</span>
                            <p class="info-value"><?php echo htmlspecialchars($fechaPedido !== '' ? $fechaPedido : '-'); ?></p>
                          </div>
                          <div class="info-item mb-0">
                            <span class="info-label">Estado</span>
                            <p class="info-value"><?php echo htmlspecialchars($data['estado_pedido'] ?? '-'); ?></p>
                          </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 mb-3 mb-lg-0">
                          <div class="info-item mb-0">
                            <span class="info-label">Proyecto</span>
                            <p class="info-value"><?php echo htmlspecialchars($proyectoLabel !== '' ? $proyectoLabel : '-'); ?></p>
                          </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 mb-3 mb-lg-0">
                          <?php if ($isComputo): ?>
                          <div class="info-item">
                            <span class="info-label">Solicitante</span>
                            <p class="info-value"><?php echo htmlspecialchars($solicitanteNombre !== '' ? $solicitanteNombre : '-'); ?></p>
                          </div>
                          <?php endif; ?>
                          <div class="info-item mb-0">
                            <span class="info-label">Lugar de Entrega</span>
                            <p class="info-value"><?php echo htmlspecialchars(!empty($data['lugar_entrega']) ? $data['lugar_entrega'] : '-'); ?></p>
                          </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 mb-3 mb-lg-0">
                          <div class="info-item mb-0">
                            <span class="info-label">Recibe</span>
                            <p class="info-value"><?php echo htmlspecialchars($recibeNombre !== '' ? $recibeNombre : '-'); ?></p>
                          </div>
                        </div>
                      </div>

                      <hr class="mt-4 mb-4">

                      <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3">Detalle de Conceptos</h6>
                          <div class="table-responsive">
                            <table class="display" id="dataTables-example667" style="width:100%">
                              <thead>
                                <tr>
                                  <?php foreach ($detalleColumns as $column): ?>
                                    <th><?php echo htmlspecialchars($column['label']); ?></th>
                                  <?php endforeach; ?>
                                </tr>
                              </thead>
                              <tbody>
                                <?php
                                $pdoDetalle = Database::connect();
                                $pdoDetalle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlDetalle = 'SELECT pd.id, m.concepto, pd.cantidad, DATE_FORMAT(pd.fecha_necesidad,\'%d/%m/%y\') AS fecha_necesidad, u.unidad_medida, pd.id_material, pd.reservado, pd.comprado FROM pedidos_detalle pd INNER JOIN materiales m ON m.id = pd.id_material INNER JOIN unidades_medida u ON u.id = pd.id_unidad_medida WHERE pd.id_pedido = ?';
                                $stmtDetalle = $pdoDetalle->prepare($sqlDetalle);
                                $stmtDetalle->execute([$id]);
                                while ($row = $stmtDetalle->fetch(PDO::FETCH_ASSOC)) {
                                    $sqlUltimaCompra = 'SELECT d.precio, DATE_FORMAT(c.fecha_emision,\'%d/%m/%y\') AS fecha_emision FROM compras_detalle d INNER JOIN compras c ON c.id = d.id_compra WHERE d.id_material = ? ORDER BY c.id DESC LIMIT 1';
                                    $stmtUltimaCompra = $pdoDetalle->prepare($sqlUltimaCompra);
                                    $stmtUltimaCompra->execute([$row['id_material']]);
                                    $dataUltimaCompra = $stmtUltimaCompra->fetch(PDO::FETCH_ASSOC) ?: [];

                                    $sqlStock = 'SELECT SUM(id.saldo) AS disponible FROM ingresos_detalle id WHERE id_material = ?';
                                    $stmtStock = $pdoDetalle->prepare($sqlStock);
                                    $stmtStock->execute([$row['id_material']]);
                                    $dataStock = $stmtStock->fetch(PDO::FETCH_ASSOC) ?: [];

                                    $cantidadDisponible = (float)$row['cantidad'] - (float)$row['reservado'] - (float)$row['comprado'];

                                    echo '<tr>';
                                    echo '<td class="table-concepto">' . htmlspecialchars($row['concepto']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['fecha_necesidad']) . '</td>';
                                    if (!empty($dataUltimaCompra['fecha_emision'])) {
                                        echo '<td>' . htmlspecialchars($dataUltimaCompra['fecha_emision']) . '</td>';
                                    } else {
                                        echo '<td>&nbsp;</td>';
                                    }
                                    if (!empty($dataUltimaCompra['precio'])) {
                                        echo '<td>$' . number_format((float)$dataUltimaCompra['precio'], 2) . '</td>';
                                    } else {
                                        echo '<td>&nbsp;</td>';
                                    }
                                    echo '<td>' . htmlspecialchars($row['cantidad']) . ' ' . htmlspecialchars($row['unidad_medida']) . '</td>';
                                    $stockDisponible = !empty($dataStock['disponible']) ? (float)$dataStock['disponible'] : 0;
                                    echo '<td>' . htmlspecialchars($stockDisponible) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['reservado']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['comprado']) . '</td>';
                                    if ($isComputo) {
                                        echo '<td>' . htmlspecialchars($cantidadDisponible) . '</td>';
                                    }
                                    echo '</tr>';
                                }
                                Database::disconnect();
                                ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-12 text-center">
                        <a class="btn btn-primary" target="_blank" href="<?php echo $printUrl . '?id=' . urlencode($data['id']); ?>"><?php echo htmlspecialchars($printLabel); ?></a>
                        <?php if ((int)$data['id_estado'] === 1 && tienePermiso(298)): ?>
                        <button type="button" class="btn btn-primary" id="btnEnviarAprobacion">Enviar a aprobación</button>
                        <?php endif; ?>
                        <a href="#" onclick="document.location.href='listarPedidos.php'" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        <?php include 'footer.php'; ?>
      </div>
    </div>
    <!-- Modal Enviar a aprobación -->
    <div class="modal fade" id="modalEnviarAprobacion" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirmar envío a aprobación</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>¿Desea enviar este pedido a aprobación?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="confirmEnviarAprobacion">Confirmar</button>
          </div>
        </div>
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
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script>
      $(document).ready(function() {
        $('#dataTables-example667').DataTable({
          stateSave: false,
          responsive: false,
          scrollX: false,
          scrollCollapse: false,
          autoWidth: false,
          paging: true,
          pageLength: 10,
          columnDefs: <?php echo json_encode($columnDefs, JSON_UNESCAPED_SLASHES); ?>,
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

        var pedidoId = <?php echo (int)$data['id']; ?>;

        $('#btnEnviarAprobacion').on('click', function () {
          $('#estado-error').addClass('d-none');
          $('#modalEnviarAprobacion').modal('show');
        });

        $('#modalEnviarAprobacion').on('hidden.bs.modal', function () {
          $('#confirmEnviarAprobacion').prop('disabled', false);
        });

        $('#confirmEnviarAprobacion').on('click', function () {
          var $button = $(this);
          $button.prop('disabled', true);
          $.ajax({
            type: 'POST',
            url: 'modificarEstadoPedido.php',
            data: { idEstado: 2, idPosicion: pedidoId },
            success: function (response) {
              var trimmed = $.trim(response || '');
              var pattern = new RegExp('^2\\s*-\\s*' + pedidoId + '$');
              if (pattern.test(trimmed)) {
                window.location.href = 'listarPedidos.php';
              } else {
                mostrarErrorEstado('No se pudo actualizar el estado. Respuesta inesperada del servidor.');
              }
            },
            error: function () {
              mostrarErrorEstado('No se pudo actualizar el estado. Intente nuevamente.');
            },
            complete: function () {
              $button.prop('disabled', false);
            }
          });
        });

        function mostrarErrorEstado(mensaje) {
          $('#modalEnviarAprobacion').modal('hide');
          var $error = $('#estado-error');
          $error.text(mensaje).removeClass('d-none');
        }
      });
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>
