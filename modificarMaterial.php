<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null == $id) {
  header("Location: listarMateriales.php");
}

if (!empty($_POST)) {

  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  try {
    $pdo->beginTransaction();

    $qAnterior = $pdo->prepare("SELECT concepto, peso_metro, calidad FROM materiales WHERE id = ?");
    $qAnterior->execute([(int)$id]);
    $materialAnterior = $qAnterior->fetch(PDO::FETCH_ASSOC) ?: [];

    $sql = "UPDATE `materiales` set `codigo` = ?, `concepto` = ?, `calidad` = ?, `descripcion` = ?, `espesor` = ?, `ancho` = ?, `largo` = ?, `peso_metro` = ?, `perimetro` = ?, `id_categoria` = ?, `id_unidad_medida` = ?, `stock_minimo` = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['codigo'], $_POST['concepto'], $_POST['calidad'], $_POST['descripcion'], $_POST['espesor'] ?: null, $_POST['ancho'] ?: null, $_POST['largo'], $_POST['peso_metro'], $_POST['perimetro'] ?: null, $_POST['id_categoria'], $_POST['id_unidad_medida'], $_POST['stock_minimo'], (int)$id]);

    // Solo propagamos si cambió algún dato que la LC copia del concepto.
    $cambioRelevante = empty($materialAnterior)
      || numeroListaCorte($materialAnterior['peso_metro'] ?? 0) !== numeroListaCorte($_POST['peso_metro'])
      || (string)($materialAnterior['concepto'] ?? '') !== (string)$_POST['concepto']
      || (string)($materialAnterior['calidad'] ?? '') !== (string)$_POST['calidad'];

    $sincronizacion = ['posiciones' => 0, 'conjuntos' => 0];
    if ($cambioRelevante) {
      // Propagar peso, calidad y peso calculado a las listas de corte no terminadas.
      $sincronizacion = sincronizarConceptosEnListasCorte($pdo, [(int)$id]);
    }

    $detalleLog = 'Modificación de concepto';
    if ($sincronizacion['posiciones'] > 0) {
      $detalleLog .= ' (' . $sincronizacion['posiciones'] . ' posiciones de Lista de Corte actualizadas)';
    }

    $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,?,'Conceptos',?)";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id'], $detalleLog, "verMaterial.php?id=" . (int)$id]);

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    Database::disconnect();
    die('Error al modificar el concepto: ' . htmlspecialchars($e->getMessage()));
  }

  Database::disconnect();

  header("Location: listarMateriales.php");
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT `id`, `codigo`, `concepto`, `calidad`, `descripcion`, `espesor`, `ancho`, `largo`, `peso_metro`, `perimetro`, `id_categoria`, `id_unidad_medida`, `stock_minimo`, `anulado` FROM `materiales` WHERE id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  Database::disconnect();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include('head_forms.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
</head>

<body>
  <!-- Loader ends-->
  <!-- page-wrapper Start-->
  <div class="page-wrapper">
    <?php include('header.php'); ?>

    <!-- Page Header Start-->
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <!-- Page Sidebar Start-->
      <!-- Right sidebar Ends-->
      <div class="page-body"><?php
                              $ubicacion = "Modificar Concepto";
                              include_once("head_page.php") ?>
        <!-- Container-fluid starts-->
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5><?= $ubicacion ?></h5>
                </div>
                <form class="form theme-form" role="form" method="post" action="modificarMaterial.php?id=<?php echo $id ?>" id="formModificarMaterial">
                  <div class="card-body">
                    <div class="row">
                      <div class="col">
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Categoría(*)</label>
                          <div class="col-sm-9">
                            <select name="id_categoria" id="id_categoria" class="js-example-basic-single col-sm-12" autofocus required="required">
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $sqlZon = "SELECT `id`, `categoria` FROM `categorias` WHERE 1 ";
                              $q = $pdo->prepare($sqlZon);
                              $q->execute();
                              while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . $fila['id'] . "'";
                                if ($fila['id'] == $data['id_categoria']) {
                                  echo " selected ";
                                }
                                echo ">" . $fila['categoria'] . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Código(*)</label>
                          <div class="col-sm-9"><input name="codigo" type="text" maxlength="99" class="form-control" value="<?php echo $data['codigo']; ?>" required="required"></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Concepto(*)</label>
                          <div class="col-sm-9"><input name="concepto" type="text" maxlength="99" class="form-control" value="<?php echo $data['concepto']; ?>" required="required"></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Descripción</label>
                          <div class="col-sm-9"><textarea name="descripcion" class="form-control"><?php echo $data['descripcion']; ?></textarea></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Unidad Medida(*)</label>
                          <div class="col-sm-9">
                            <select name="id_unidad_medida" id="id_unidad_medida" class="js-example-basic-single col-sm-12" required="required">
                              <option value="">Seleccione...</option>
                              <?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $sqlZon = "SELECT `id`, `unidad_medida` FROM `unidades_medida` WHERE 1 ";
                              $q = $pdo->prepare($sqlZon);
                              $q->execute();
                              while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . $fila['id'] . "'";
                                if ($fila['id'] == $data['id_unidad_medida']) {
                                  echo " selected ";
                                }
                                echo ">" . $fila['unidad_medida'] . "</option>";
                              }
                              Database::disconnect();
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Espesor (mm)</label>
                          <div class="col-sm-9">
                            <input name="espesor" type="number" step="0.01" class="form-control" value="<?php echo $data['espesor']; ?>">
                            <small class="form-text text-muted">Ingrese el espesor en <strong>milímetros (mm)</strong></small>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Ancho (mm)</label>
                          <div class="col-sm-9">
                            <input name="ancho" type="number" step="0.01" class="form-control" value="<?php echo $data['ancho']; ?>">
                            <small class="form-text text-muted">Ingrese el ancho en <strong>milímetros (mm)</strong></small>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Largo (mm)(*)</label>
                          <div class="col-sm-9">
                            <input name="largo" type="number" step="0.01" class="form-control" required="required" value="<?php echo $data['largo']; ?>">
                            <small class="form-text text-muted">Ingrese el largo en <strong>milímetros (mm)</strong></small>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Peso x Metro (kg)(*)</label>
                          <div class="col-sm-9">
                            <input name="peso_metro" type="number" step="0.01" class="form-control" required="required" value="<?php echo $data['peso_metro']; ?>">
                            <small class="form-text text-muted">Ingrese el peso por metro en <strong>kilogramos (kg)</strong></small>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Stock Mínimo(*)</label>
                          <div class="col-sm-9"><input name="stock_minimo" type="number" step="0.01" class="form-control" value="<?php echo $data['stock_minimo']; ?>" required="required"></div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Perímetro (mm)</label>
                          <div class="col-sm-9">
                            <input name="perimetro" type="number" step="0.01" class="form-control" value="<?php echo $data['perimetro']; ?>">
                            <small class="form-text text-muted">Ingrese el perímetro en <strong>milímetros (mm)</strong></small>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Calidad</label>
                          <div class="col-sm-9"><input name="calidad" type="text" maxlength="99" class="form-control" value="<?php echo $data['calidad']; ?>"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="card-footer">
                    <div class="col-sm-9 offset-sm-3">
                      <button class="btn btn-primary" type="submit" id="btnModificar">Modificar</button>
                      <a onclick="document.location.href='listarMateriales.php'" class="btn btn-light">Volver</a>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
        <!-- Container-fluid Ends-->
      </div>

      <!-- Modal Confirmar Cambios en Listas de Corte -->
      <div class="modal fade" id="modalConfirmarCambiosLC" tabindex="-1" role="dialog" aria-labelledby="modalConfirmarCambiosLCLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document" style="max-width: 1600px; width: 95%;">
          <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background-color: #1e293b; color: #ffffff; border-bottom: 1px solid #0f172a;">
              <h5 class="modal-title font-weight-bold" id="modalConfirmarCambiosLCLabel" style="color: #ffffff !important;">
                <i class="fa fa-exclamation-triangle text-warning mr-2"></i> Confirmar Modificaciones en Listas de Corte
              </h5>
              <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close" style="color: #ffffff !important; opacity: 0.9;">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body" style="max-height: calc(85vh - 130px); overflow-y: auto; background-color: #f8fafc;">
              <div id="contenidoModalCambiosLC"></div>
            </div>
            <div class="modal-footer bg-light">
              <button class="btn btn-secondary" type="button" data-dismiss="modal">
                <i class="fa fa-times mr-1"></i> Cancelar
              </button>
              <button class="btn btn-primary" type="button" id="btnContinuarModificacionLC">
                <i class="fa fa-check mr-1"></i> Continuar
              </button>
            </div>
          </div>
        </div>
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
  <script src="assets/js/select2/select2.full.min.js"></script>
  <script src="assets/js/select2/select2-custom.js"></script>

  <script>
    $(document).ready(function() {
      var submitConfirmado = false;

      function escaparHtml(texto) {
        if (texto === null || texto === undefined) return '';
        return String(texto)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function formatearNumero(val) {
        var num = parseFloat(val);
        if (isNaN(num)) return '0,00';
        return num.toLocaleString('es-AR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function construirDetalleModal(data) {
        var html = '';

        // Alerta inicial con fondo oscuro y alto contraste
        html += '<div class="alert mb-3" style="background-color: #1e293b; color: #ffffff; border: 1px solid #0f172a; border-left: 6px solid #f59e0b; border-radius: 4px; padding: 14px 18px;">';
        html += '  <h6 class="font-weight-bold mb-1" style="color: #f59e0b; font-size: 15px;"><i class="fa fa-exclamation-triangle mr-1"></i> Se detectaron Listas de Corte afectadas</h6>';
        html += '  <p class="mb-0" style="color: #e2e8f0; font-size: 13.5px;">';
        html += '    Este concepto se encuentra en <strong>' + data.total_lcs + ' Lista(s) de Corte no terminada(s)</strong> ';
        html += '    (' + data.total_posiciones + ' posición/posiciones en ' + data.total_conjuntos + ' conjunto/s). ';
        html += '    Al continuar con la modificación, se sincronizarán y recalcularán automáticamente los siguientes valores:';
        html += '  </p>';
        html += '</div>';

        // Resumen de cambios en el concepto con colores explícitos para evitar blanco sobre blanco
        var cc = data.cambios_concepto || {};
        var cambiosConceptoHtml = '';

        if (cc.concepto && cc.concepto.cambio) {
          cambiosConceptoHtml += '<li class="mb-1" style="color: #1e293b !important;"><strong style="color: #0f172a !important;">Concepto:</strong> <span style="color: #dc3545; text-decoration: line-through;">' + escaparHtml(cc.concepto.anterior) + '</span> &rarr; <span style="color: #16a34a; font-weight: bold;">' + escaparHtml(cc.concepto.nuevo) + '</span></li>';
        }
        if (cc.codigo && cc.codigo.cambio) {
          cambiosConceptoHtml += '<li class="mb-1" style="color: #1e293b !important;"><strong style="color: #0f172a !important;">Código:</strong> <span style="color: #dc3545; text-decoration: line-through;">' + escaparHtml(cc.codigo.anterior) + '</span> &rarr; <span style="color: #16a34a; font-weight: bold;">' + escaparHtml(cc.codigo.nuevo) + '</span></li>';
        }
        if (cc.calidad && cc.calidad.cambio) {
          var calAnt = cc.calidad.anterior ? escaparHtml(cc.calidad.anterior) : '<em style="color: #64748b;">(Vacío)</em>';
          var calNue = cc.calidad.nuevo ? escaparHtml(cc.calidad.nuevo) : '<em style="color: #64748b;">(Vacío)</em>';
          cambiosConceptoHtml += '<li class="mb-1" style="color: #1e293b !important;"><strong style="color: #0f172a !important;">Calidad:</strong> <span style="color: #dc3545; text-decoration: line-through;">' + calAnt + '</span> &rarr; <span style="color: #16a34a; font-weight: bold;">' + calNue + '</span></li>';
        }
        if (cc.peso_metro && cc.peso_metro.cambio) {
          cambiosConceptoHtml += '<li class="mb-1" style="color: #1e293b !important;"><strong style="color: #0f172a !important;">Peso x Metro:</strong> <span style="color: #dc3545; text-decoration: line-through;">' + formatearNumero(cc.peso_metro.anterior) + ' kg</span> &rarr; <span style="color: #16a34a; font-weight: bold;">' + formatearNumero(cc.peso_metro.nuevo) + ' kg</span></li>';
        }

        if (cambiosConceptoHtml !== '') {
          html += '<div class="card mb-3" style="border: 1px solid #cbd5e1; background-color: #ffffff;">';
          html += '  <div class="card-header py-2" style="background-color: #e2e8f0; color: #0f172a !important; border-bottom: 1px solid #cbd5e1;">';
          html += '    <strong style="color: #0f172a !important; font-size: 14px;"><i class="fa fa-edit mr-1 text-primary"></i> Modificaciones realizadas en el Concepto:</strong>';
          html += '  </div>';
          html += '  <div class="card-body py-2 px-3" style="background-color: #ffffff; color: #1e293b !important;">';
          html += '    <ul class="mb-0 pl-3" style="color: #1e293b !important; font-size: 13.5px;">' + cambiosConceptoHtml + '</ul>';
          html += '  </div>';
          html += '</div>';
        }

        // Listado de LCs afectadas
        html += '<h6 class="font-weight-bold mb-2 text-dark"><i class="fa fa-list-alt mr-1"></i> Detalle de Listas de Corte y Posiciones que se actualizarán:</h6>';

        (data.listas_corte || []).forEach(function(lc) {
          html += '<div class="card mb-3 border border-secondary shadow-sm" style="background-color: #ffffff;">';
          html += '  <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center flex-wrap">';
          html += '    <div>';
          html += '      <strong style="font-size: 14.5px;"><i class="fa fa-file-text-o mr-1"></i> LC N° ' + escaparHtml(lc.numero) + ' - ' + escaparHtml(lc.nombre) + ' (Rev. ' + lc.nro_revision + ')</strong>';
          html += '      <span class="ml-2 badge badge-light text-dark font-weight-normal"><i class="fa fa-folder-open-o mr-1"></i> ' + escaparHtml(lc.proyecto) + '</span>';
          html += '    </div>';
          html += '    <div>';
          html += '      <span class="badge badge-warning text-dark font-weight-bold">Estado: ' + escaparHtml(lc.estado) + '</span>';
          html += '    </div>';
          html += '  </div>';

          html += '  <div class="table-responsive">';
          html += '    <table class="table table-bordered table-sm table-hover mb-0" style="font-size: 12.5px; background-color: #ffffff;">';
          html += '      <thead class="thead-light text-center" style="color: #0f172a;">';
          html += '        <tr>';
          html += '          <th style="min-width: 140px;">Conjunto</th>';
          html += '          <th style="min-width: 130px;">Posición</th>';
          html += '          <th style="min-width: 90px;">Calidad</th>';
          html += '          <th style="min-width: 105px;">Peso x Metro</th>';
          html += '          <th style="min-width: 110px;">Peso Unit. Pos.</th>';
          html += '          <th style="min-width: 110px;">Peso Total Pos.</th>';
          html += '          <th style="min-width: 115px;">Peso Conjunto</th>';
          html += '        </tr>';
          html += '      </thead>';
          html += '      <tbody style="color: #1e293b;">';

          (lc.conjuntos || []).forEach(function(conj) {
            var cantPos = (conj.posiciones || []).length;
            (conj.posiciones || []).forEach(function(pos, idx) {
              html += '<tr>';

              // Conjunto (con rowspan para la primera posición del conjunto)
              if (idx === 0) {
                html += '<td rowspan="' + cantPos + '" class="align-middle" style="background-color: #f8fafc; color: #0f172a;">';
                html += '  <strong style="color: #0f172a;">' + escaparHtml(conj.nombre) + '</strong><br>';
                html += '  <small class="text-muted">Cant. Conj.: ' + conj.cantidad + '</small>';
                html += '</td>';
              }

              // Posición y medidas
              html += '<td class="align-middle" style="color: #0f172a;">';
              html += '  <strong style="color: #0f172a;">Pos. ' + escaparHtml(pos.posicion) + '</strong><br>';
              html += '  <small class="text-muted">Cant: ' + pos.cantidad + ' | L: ' + formatearNumero(pos.largo) + ' mm';
              if (pos.ancho > 0) {
                html += ' x An: ' + formatearNumero(pos.ancho) + ' mm';
              }
              html += '  </small>';
              html += '</td>';

              // Calidad
              html += '<td class="align-middle text-center" style="color: #1e293b;">';
              if (pos.cambio_calidad) {
                var calPosAnt = pos.calidad_actual ? escaparHtml(pos.calidad_actual) : '<em style="color: #64748b;">(Vacío)</em>';
                var calPosNue = pos.calidad_nueva ? escaparHtml(pos.calidad_nueva) : '<em style="color: #64748b;">(Vacío)</em>';
                html += '<span style="color: #dc3545; text-decoration: line-through;">' + calPosAnt + '</span><br><strong style="color: #16a34a;"><i class="fa fa-arrow-right"></i> ' + calPosNue + '</strong>';
              } else {
                html += '<span style="color: #475569;">' + (pos.calidad_actual ? escaparHtml(pos.calidad_actual) : '-') + '</span>';
              }
              html += '</td>';

              // Peso x Metro
              html += '<td class="align-middle text-right" style="color: #1e293b;">';
              if (pos.cambio_peso_metro) {
                html += '<span style="color: #dc3545; text-decoration: line-through;">' + formatearNumero(pos.peso_metro_actual) + ' kg</span><br><strong style="color: #16a34a;"><i class="fa fa-arrow-right"></i> ' + formatearNumero(pos.peso_metro_nuevo) + ' kg</strong>';
              } else {
                html += '<span style="color: #475569;">' + formatearNumero(pos.peso_metro_actual) + ' kg</span>';
              }
              html += '</td>';

              // Peso Unitario Posición
              html += '<td class="align-middle text-right" style="color: #1e293b;">';
              if (pos.cambio_peso_unitario) {
                html += '<span style="color: #dc3545; text-decoration: line-through;">' + formatearNumero(pos.peso_unitario_actual) + ' kg</span><br><strong style="color: #16a34a;"><i class="fa fa-arrow-right"></i> ' + formatearNumero(pos.peso_unitario_nuevo) + ' kg</strong>';
              } else {
                html += '<span style="color: #475569;">' + formatearNumero(pos.peso_unitario_actual) + ' kg</span>';
              }
              html += '</td>';

              // Peso Total Posición
              html += '<td class="align-middle text-right" style="color: #1e293b;">';
              if (pos.cambio_peso_total) {
                html += '<span style="color: #dc3545; text-decoration: line-through;">' + formatearNumero(pos.peso_total_actual) + ' kg</span><br><strong style="color: #16a34a;"><i class="fa fa-arrow-right"></i> ' + formatearNumero(pos.peso_total_nuevo) + ' kg</strong>';
              } else {
                html += '<span style="color: #475569;">' + formatearNumero(pos.peso_total_actual) + ' kg</span>';
              }
              html += '</td>';

              // Peso Conjunto (rowspan para la primera fila)
              if (idx === 0) {
                html += '<td rowspan="' + cantPos + '" class="align-middle text-right" style="background-color: #f8fafc; color: #1e293b;">';
                if (conj.cambio_peso) {
                  html += '<span style="color: #dc3545; text-decoration: line-through;">' + formatearNumero(conj.peso_actual) + ' kg</span><br><strong style="color: #16a34a; font-size: 13.5px;"><i class="fa fa-arrow-right"></i> ' + formatearNumero(conj.peso_nuevo) + ' kg</strong>';
                } else {
                  html += '<span style="color: #475569;">' + formatearNumero(conj.peso_actual) + ' kg</span>';
                }
                html += '</td>';
              }

              html += '</tr>';
            });
          });

          html += '      </tbody>';
          html += '    </table>';
          html += '  </div>';
          html += '</div>';
        });

        return html;
      }

      $('#formModificarMaterial').on('submit', function(e) {
        if (submitConfirmado) {
          return true;
        }

        e.preventDefault();

        var form = this;
        if (!form.checkValidity()) {
          form.reportValidity();
          return false;
        }

        var $btn = $('#btnModificar');
        var btnHtmlOriginal = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Verificando LCs...');

        var formData = {
          id: <?php echo (int)$id; ?>,
          codigo: $('input[name="codigo"]').val(),
          concepto: $('input[name="concepto"]').val(),
          calidad: $('input[name="calidad"]').val(),
          peso_metro: $('input[name="peso_metro"]').val()
        };

        $.ajax({
          url: 'ajaxVerificarCambiosConceptoLC.php',
          type: 'POST',
          data: formData,
          dataType: 'json',
          success: function(response) {
            $btn.prop('disabled', false).html(btnHtmlOriginal);
            if (response && response.ok && response.afecta_lc) {
              var cuerpoHtml = construirDetalleModal(response);
              $('#contenidoModalCambiosLC').html(cuerpoHtml);
              $('#modalConfirmarCambiosLC').modal('show');
            } else {
              submitConfirmado = true;
              form.submit();
            }
          },
          error: function(xhr, status, error) {
            $btn.prop('disabled', false).html(btnHtmlOriginal);
            console.error('Error al verificar impacto en LCs:', error);
            if (confirm('No se pudo verificar el impacto en Listas de Corte. ¿Desea continuar con la modificación?')) {
              submitConfirmado = true;
              form.submit();
            }
          }
        });
      });

      $('#btnContinuarModificacionLC').on('click', function() {
        submitConfirmado = true;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Guardando...');
        $('#formModificarMaterial')[0].submit();
      });
    });
  </script>

</body>

</html>
