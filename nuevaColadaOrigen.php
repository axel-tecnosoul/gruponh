<?php
require('config.php');
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    die('Redirecting to index.php');
}
require 'database.php';

// Detectar modo edición
$editId   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$editMode = $editId > 0;
$colada   = null;

if ($editMode) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "SELECT c.*, GROUP_CONCAT(id2.id) AS ids_internas
            FROM coladas c
            LEFT JOIN ingresos_detalle id2 ON id2.id_colada_origen = c.id
            WHERE c.id = ? AND c.es_origen = 1
            GROUP BY c.id";
    $q = $pdo->prepare($sql);
    $q->execute([$editId]);
    $colada = $q->fetch(PDO::FETCH_ASSOC);
    Database::disconnect();

    if (!$colada) {
        header('Location: listarColadas.php');
        exit;
    }
}

if (!empty($_POST)) {
    $edit_id        = !empty($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $id_material    = !empty($_POST['id_material']) ? intval($_POST['id_material']) : null;
    $fecha          = !empty($_POST['fecha']) ? $_POST['fecha'] : null;
    $cod_fabricante = trim($_POST['cod_fabricante'] ?? '');
    $nro_colada     = trim($_POST['nro_colada'] ?? '');
    $adjunto        = trim($_POST['adjunto'] ?? '');
    $internalIds    = !empty($_POST['id_coladas_internas']) && is_array($_POST['id_coladas_internas'])
                        ? $_POST['id_coladas_internas'] : [];

    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($edit_id > 0) {
        // UPDATE
        $sql = "UPDATE coladas SET id_material=?, cod_fabricante=?, nro_colada=?, adjunto=?, fecha=?
                WHERE id=? AND es_origen=1";
        $q = $pdo->prepare($sql);
        $q->execute([$id_material, $cod_fabricante, $nro_colada, $adjunto, $fecha, $edit_id]);
        $idColada = $edit_id;

        // Desasociar todas las internas previas de esta colada y reasignar las nuevas
        $pdo->prepare("UPDATE ingresos_detalle SET id_colada_origen = NULL WHERE id_colada_origen = ?")
            ->execute([$idColada]);

        $logDetalle = "Colada de origen ID #$idColada modificada";
    } else {
        // INSERT
        $sql = "INSERT INTO coladas (id_material, id_proveedor, id_compra, cod_fabricante, nro_colada, adjunto, fecha, es_origen)
                VALUES (?, NULL, NULL, ?, ?, ?, ?, 1)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_material, $cod_fabricante, $nro_colada, $adjunto, $fecha]);
        $idColada = $pdo->lastInsertId();

        $logDetalle = "Nueva colada de origen ID #$idColada creada";
    }

    // Asociar coladas internas seleccionadas
    if (!empty($internalIds)) {
        $sql = "UPDATE ingresos_detalle SET id_colada_origen = ? WHERE id = ?";
        $q = $pdo->prepare($sql);
        foreach ($internalIds as $detalleId) {
            $detalleId = intval($detalleId);
            if ($detalleId > 0) {
                $q->execute([$idColada, $detalleId]);
            }
        }
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link)
            VALUES (now(), ?, ?, 'Coladas', 'verColada.php?id=$idColada')";
    $pdo->prepare($sql)->execute([$_SESSION['user']['id'], $logDetalle]);

    Database::disconnect();
    header('Location: listarColadas.php');
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT id, concepto FROM materiales ORDER BY concepto";
$materials = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      #internalColadasTable th,
      #internalColadasTable td {
        vertical-align: middle;
      }
    </style>
  </head>
  <body>
    <div class="page-wrapper">
      <?php include('header.php');?>
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <div class="page-body">
          <?php
            $ubicacion = $editMode
              ? "Editar Colada de Origen #$editId"
              : "Nueva Colada de Origen";
            include_once('head_page.php');
          ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?= $ubicacion ?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="nuevaColadaOrigen.php">

                    <?php if ($editMode): ?>
                      <input type="hidden" name="edit_id" value="<?= $editId ?>">
                    <?php endif; ?>

                    <div class="card-body">
                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label">Fecha</label>
                        <div class="col-sm-4">
                          <input
                            name="fecha"
                            type="date"
                            class="form-control"
                            value="<?= $editMode ? htmlspecialchars($colada['fecha']) : date('Y-m-d') ?>"
                            required>
                        </div>
                        <label class="col-sm-2 col-form-label">Concepto</label>
                        <div class="col-sm-4">
                          <select name="id_material" id="id_material" class="form-control select2" required>
                            <option value="">Seleccione un concepto</option>
                            <?php foreach ($materials as $material): ?>
                              <option
                                value="<?= $material['id'] ?>"
                                <?= ($editMode && $colada['id_material'] == $material['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($material['concepto']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label">Nombre del fabricante</label>
                        <div class="col-sm-4">
                          <input
                            name="cod_fabricante"
                            type="text"
                            maxlength="99"
                            class="form-control"
                            value="<?= $editMode ? htmlspecialchars($colada['cod_fabricante']) : '' ?>"
                            required>
                        </div>
                        <label class="col-sm-2 col-form-label">Nro. Colada</label>
                        <div class="col-sm-4">
                          <input
                            name="nro_colada"
                            type="text"
                            maxlength="99"
                            class="form-control"
                            value="<?= $editMode ? htmlspecialchars($colada['nro_colada']) : '' ?>"
                            required>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label">Link certificado <small class="text-muted">(opcional)</small></label>
                        <div class="col-sm-10">
                          <input
                            name="adjunto"
                            type="text"
                            maxlength="500"
                            class="form-control"
                            placeholder="Ruta o URL del certificado"
                            value="<?= $editMode ? htmlspecialchars($colada['adjunto']) : '' ?>">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col">
                          <div class="form-group row mt-2">
                            <label class="col-sm-3 col-form-label">Asociar a coladas internas</label>
                            <div class="col-sm-9">
                              <div class="custom-control custom-checkbox">
                                <input
                                  type="checkbox"
                                  class="custom-control-input"
                                  id="chk_asociar_internas"
                                  name="asociar_internas"
                                  <?= ($editMode && !empty($colada['ids_internas'])) ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="chk_asociar_internas">
                                  Sí, quiero seleccionar coladas internas
                                </label>
                              </div>
                            </div>
                          </div>
                          <div id="internalColadasSection" style="display:none;">
                            <div class="form-group row">
                              <div class="col-sm-12">
                                <div class="table-responsive">
                                  <table class="table table-bordered table-sm" id="internalColadasTable">
                                    <thead>
                                      <tr>
                                        <th></th>
                                        <th>ID</th>
                                        <th>Concepto</th>
                                        <th>Nro. Colada Interna</th>
                                        <th>Cantidad</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <tr>
                                        <td colspan="5">Seleccione un concepto y marque la casilla para ver las coladas internas disponibles.</td>
                                      </tr>
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">
                          <?= $editMode ? 'Guardar cambios' : 'Crear' ?>
                        </button>
                        <a href="listarColadas.php" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include('footer.php'); ?>
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
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <script>
      var internalColadasDataTable = null;

      // IDs preseleccionados en modo edición (inyectados desde PHP)
      var preselectedIds = <?php
        if ($editMode && !empty($colada['ids_internas'])) {
            $ids = array_map('intval', explode(',', $colada['ids_internas']));
            echo json_encode($ids);
        } else {
            echo '[]';
        }
      ?>;

      function getSpanishDataTableLanguage(emptyMessage) {
        return {
          decimal: '',
          emptyTable: emptyMessage || 'No hay coladas internas disponibles.',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ Registros',
          infoEmpty: 'Mostrando 0 a 0 de 0 Registros',
          infoFiltered: '(Filtrado de _MAX_ total registros)',
          infoPostFix: '',
          thousands: ',',
          lengthMenu: 'Mostrar _MENU_ Registros',
          loadingRecords: 'Cargando...',
          processing: 'Procesando...',
          search: 'Buscar:',
          zeroRecords: 'No hay resultados',
          paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior'
          }
        };
      }

      function ensureInternalColadasDataTable(emptyMessage) {
        if (internalColadasDataTable) {
          return internalColadasDataTable;
        }

        internalColadasDataTable = $('#internalColadasTable').DataTable({
          stateSave: false,
          responsive: false,
          data: [],
          columns: [
            {
              data: 'id',
              orderable: false,
              searchable: false,
              className: 'text-center',
              render: function(data) {
                return '<input type="checkbox" name="id_coladas_internas[]" value="' + data + '">';
              }
            },
            { data: 'id' },
            { data: 'concepto', defaultContent: '' },
            { data: 'nro_colada_interna', defaultContent: '' },
            { data: 'cantidad', defaultContent: '' }
          ],
          order: [[1, 'desc']],
          language: getSpanishDataTableLanguage(emptyMessage)
        });

        return internalColadasDataTable;
      }

      function updateInternalColadasTable(rows, emptyMessage, idsToCheck) {
        idsToCheck = idsToCheck || [];
        var table = ensureInternalColadasDataTable(emptyMessage);
        var settings = table.settings()[0];
        settings.oLanguage.sEmptyTable = emptyMessage || 'No hay coladas internas disponibles.';
        table.clear();
        if (rows && rows.length > 0) {
          table.rows.add(rows);
        }
        table.draw(false);

        // Marcar los checkboxes que correspondan
        if (idsToCheck.length > 0) {
          $('#internalColadasTable tbody input[type="checkbox"]').each(function() {
            if (idsToCheck.indexOf(parseInt($(this).val())) !== -1) {
              $(this).prop('checked', true);
            }
          });
        }
      }

      function loadInternalColadas(idsToCheck) {
        idsToCheck = idsToCheck || [];
        var materialId = $('#id_material').val();

        if (!$('#chk_asociar_internas').is(':checked') || !materialId) {
          if (internalColadasDataTable) {
            updateInternalColadasTable([], 'No hay coladas internas disponibles.');
          }
          $('#internalColadasSection').hide();
          return;
        }

        ensureInternalColadasDataTable('Cargando coladas internas...');
        $('#internalColadasSection').show();

        // En modo edición pasamos el id de la colada para incluir las ya asociadas
        var ajaxData = { id_material: materialId };
        <?php if ($editMode): ?>
        ajaxData.edit_id = <?= $editId ?>;
        <?php endif; ?>

        $.ajax({
          url: 'get_coladas_internas_disponibles.php',
          method: 'get',
          dataType: 'json',
          data: ajaxData,
          success: function(response) {
            if (!response.success) {
              updateInternalColadasTable([], response.message || 'No hay coladas internas disponibles.');
              return;
            }
            if (!response.data || response.data.length === 0) {
              updateInternalColadasTable([], 'No se encontraron coladas internas disponibles para el concepto seleccionado.');
              return;
            }
            updateInternalColadasTable(response.data, 'No hay coladas internas disponibles.', idsToCheck);
          },
          error: function() {
            updateInternalColadasTable([], 'Error al cargar coladas internas.');
          }
        });
      }

      $(document).ready(function () {
        $('#id_material').select2({
          placeholder: 'Seleccione un concepto',
          allowClear: true,
          width: '100%'
        });

        $('#chk_asociar_internas').on('change', function() {
          loadInternalColadas();
        });

        $('#id_material').on('change', function() {
          loadInternalColadas();
        });

        // Si estamos en modo edición y hay IDs preseleccionados, cargar la tabla automáticamente
        if (preselectedIds.length > 0) {
          loadInternalColadas(preselectedIds);
        }
      });
    </script>
  </body>
</html>