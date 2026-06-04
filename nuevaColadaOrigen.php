<?php
require('config.php');
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    die('Redirecting to index.php');
}
require 'database.php';

if (!empty($_POST)) {
    $id_material = !empty($_POST['id_material']) ? $_POST['id_material'] : null;
    $fecha = !empty($_POST['fecha']) ? $_POST['fecha'] : null;
    $cod_fabricante = trim($_POST['cod_fabricante'] ?? '');
    $nro_colada = trim($_POST['nro_colada'] ?? '');
    $adjunto = trim($_POST['adjunto'] ?? '');
    $internalIds = !empty($_POST['id_coladas_internas']) && is_array($_POST['id_coladas_internas']) ? $_POST['id_coladas_internas'] : [];

    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "INSERT INTO `coladas` (`id_material`, `id_proveedor`, `id_compra`, `cod_fabricante`, `nro_colada`, `adjunto`, `fecha`, `es_origen`) VALUES (?, NULL, NULL, ?, ?, ?, ?, 1)";
    $q = $pdo->prepare($sql);
    $q->execute([$id_material, $cod_fabricante, $nro_colada, $adjunto, $fecha]);
    $idColada = $pdo->lastInsertId();

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

    $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva colada de origen ID #$idColada creada','Coladas','verColada.php?id=$idColada')";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id']]);

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
  </head>
  <body>
    <div class="page-wrapper">
      <?php include('header.php');?>
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <div class="page-body">
          <?php $ubicacion="Nueva Colada de Origen"; include_once('head_page.php'); ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="nuevaColadaOrigen.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha</label>
                            <div class="col-sm-9"><input name="fecha" type="date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Concepto</label>
                            <div class="col-sm-9">
                              <select name="id_material" id="id_material" class="form-control select2" required>
                                <option value="">Seleccione un concepto</option>
                                <?php foreach ($materials as $material): ?>
                                  <option value="<?=$material['id']?>"><?=htmlspecialchars($material['concepto'])?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nombre del fabricante</label>
                            <div class="col-sm-9"><input name="cod_fabricante" type="text" maxlength="99" class="form-control" required></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nro. Colada</label>
                            <div class="col-sm-9"><input name="nro_colada" type="text" maxlength="99" class="form-control" required></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Link certificado <small class="text-muted">(opcional)</small></label>
                            <div class="col-sm-9"><input name="adjunto" type="text" maxlength="500" class="form-control" placeholder="Ruta o URL del certificado"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Asociar a coladas internas</label>
                            <div class="col-sm-9">
                              <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="chk_asociar_internas" name="asociar_internas">
                                <label class="custom-control-label" for="chk_asociar_internas">Sí, quiero seleccionar coladas internas</label>
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
                                        <th>Saldo</th>
                                      </tr>
                                    </thead>
                                    <tbody><tr><td colspan="6">Seleccione un concepto y marque la casilla para ver las coladas internas disponibles.</td></tr></tbody>
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
                        <button class="btn btn-primary" type="submit">Crear</button>
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
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script>
      $(document).ready(function () {
        $('#id_material').select2({
          placeholder: 'Seleccione un concepto',
          allowClear: true,
          width: '100%'
        });
      });

      function loadInternalColadas() {
        var materialId = $('#id_material').val();
        if (!$('#chk_asociar_internas').is(':checked') || !materialId) {
          $('#internalColadasSection').hide();
          return;
        }
        $.ajax({
          url: 'get_coladas_internas_disponibles.php',
          method: 'get',
          dataType: 'json',
          data: { id_material: materialId },
          success: function(response) {
            if (!response.success) {
              $('#internalColadasTable tbody').html('<tr><td colspan="6">'+(response.message || 'No hay coladas internas disponibles.')+'</td></tr>');
              $('#internalColadasSection').show();
              return;
            }
            if (!response.data || response.data.length === 0) {
              $('#internalColadasTable tbody').html('<tr><td colspan="6">No se encontraron coladas internas disponibles para el concepto seleccionado.</td></tr>');
              $('#internalColadasSection').show();
              return;
            }
            var html = '';
            response.data.forEach(function(row) {
              html += '<tr>';
              html += '<td><input type="checkbox" name="id_coladas_internas[]" value="'+row.id+'"></td>';
              html += '<td>'+row.id+'</td>';
              html += '<td>'+row.concepto+'</td>';
              html += '<td>'+row.nro_colada_interna+'</td>';
              html += '<td>'+row.cantidad+'</td>';
              html += '<td>'+row.saldo+'</td>';
              html += '</tr>';
            });
            $('#internalColadasTable tbody').html(html);
            $('#internalColadasSection').show();
          },
          error: function() {
            $('#internalColadasTable tbody').html('<tr><td colspan="6">Error al cargar coladas internas.</td></tr>');
            $('#internalColadasSection').show();
          }
        });
      }

      $(document).ready(function() {
        $('#chk_asociar_internas').on('change', function() {
          loadInternalColadas();
        });
        $('#id_material').on('change', function() {
          loadInternalColadas();
        });
      });
    </script>
  </body>
</html>
