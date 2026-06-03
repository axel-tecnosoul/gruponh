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

  /*var_dump($_POST);
  var_dump($_GET);
  die();*/

  $column_names = [
    1 => "monto_acumulado_avances",
    2 => "monto_acumulado_anticipos",
    3 => "monto_acumulado_desacopios",
    4 => "monto_acumulado_descuentos",
    5 => "monto_acumulado_ajustes",
  ];

  //$id_tipo_item=$_POST["id_tipo_item"];
  // Fase 1: tipo de item fijo interno en Avance (id=1)
  $id_tipo_item=1;
  $subtotal=$_POST['cantidad']*$_POST['precio_unitario'];

  //btn2 y btn3 son parar modificar
  if (isset($_POST['btn3'])) {

    //obtenemos la informacion del detalle del certificado antes de editarlo
    $sql = "SELECT id_tipo_item_certificado,subtotal FROM certificados_maestros_detalles WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['id_certificado_maestro_detalle']]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $id_tipo_item_old=$data["id_tipo_item_certificado"];
    $subtotal_old=$data["subtotal"];

    //obtenemos el nombre de la columna del tipo de detalle en la tabla certificado_maestro para restar el subtotal
    $column_name_old = $column_names[$id_tipo_item_old];
    //restamos el viejo subtotal en la columna segun el viejo tipo de detalle
    $sql = "UPDATE certificados_maestros SET $column_name_old = $column_name_old - ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal_old,$_GET['id_certificado_maestro']]);

    //obtenemos el nombre de la columna en la tabla certificado_maestro para sumar el subtotal
    $column_name = $column_names[$id_tipo_item];
    //sumamos el nuevo subtotal en la columna segun el nuevo tipo de detalle
    $sql = "UPDATE certificados_maestros SET $column_name = $column_name + ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal,$_GET['id_certificado_maestro']]);

    $sql = "UPDATE certificados_maestros_detalles SET id_proyecto=?, id_tipo_item_certificado=?, descripcion=?, cantidad=?, id_unidad_medida=?, precio_unitario=?, subtotal=? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST["id_proyecto"], $id_tipo_item, $_POST["descripcion"], $_POST["cantidad"], $_POST["id_unidad_medida"], $_POST["precio_unitario"],$subtotal,$_POST['id_certificado_maestro_detalle']]);
    
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificacion de Detalle ID #".$_POST['id_certificado_maestro_detalle']." de Certificado Maestro','Certificado Maestro','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=".$_GET['id_certificado_maestro']);

  }else{

    $column_name = $column_names[$id_tipo_item];

    $sql = "UPDATE certificados_maestros SET $column_name = $column_name + ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$subtotal,$_GET['id_certificado_maestro']]);

    $sql = "INSERT INTO certificados_maestros_detalles (id_certificado_maestro, id_proyecto, id_tipo_item_certificado, descripcion, cantidad, id_unidad_medida, precio_unitario, subtotal) VALUES (?,?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$_GET['id_certificado_maestro'],$_POST["id_proyecto"], $id_tipo_item, $_POST["descripcion"], $_POST["cantidad"], $_POST["id_unidad_medida"], $_POST["precio_unitario"],$subtotal]);
    $id_certificados_maestros_detalles = $pdo->lastInsertId();
    
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Detalle #$id_certificados_maestros_detalles de Certificado Maestro','Certificado Maestro','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    Database::disconnect();
    if (isset($_POST['btn1'])) {
      header("Location: nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=".$_GET["id_certificado_maestro"]);
    } else {
      header("Location: listarCertificadosMaestros.php");
    }
  }

}

$id_certificado_maestro = (int) $_GET['id_certificado_maestro'];
$id_occ = 0;
$id_cliente_occ = 0;
$numero_occ = '';
$moneda_occ = '';
$occ_detalles = [];
$unidades_medida = [];
$certificado_header = [];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cm.id, DATE_FORMAT(cm.fecha_emision, '%d/%m/%Y') AS fecha_emision_cm, cm.porcentaje_anticipo, cm.id_occ, occ.id_cuenta_cliente AS id_cliente_occ, occ.numero, DATE_FORMAT(occ.fecha_emision, '%d/%m/%Y') AS fecha_emision_occ, occ.monto, occ.monto_total_certificados, occ.monto_total_facturados, cu.nombre AS cliente_occ, m.moneda FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ = occ.id INNER JOIN cuentas cu ON cu.id = occ.id_cuenta_cliente INNER JOIN monedas m ON occ.id_moneda = m.id WHERE cm.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_maestro]);
$data_occ = $q->fetch(PDO::FETCH_ASSOC);
if (!empty($data_occ)) {
  $certificado_header = $data_occ;
  $id_occ = (int) $data_occ['id_occ'];
  $id_cliente_occ = (int) $data_occ['id_cliente_occ'];
  $numero_occ = $data_occ['numero'];
  $moneda_occ = $data_occ['moneda'];
}

if ($id_occ > 0) {
  $sql = "SELECT id, descripcion, cantidad, precio_unitario, descuento, subtotal FROM occ_detalles WHERE id_occ = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_occ]);
  $occ_detalles = $q->fetchAll(PDO::FETCH_ASSOC);
}

$sql = "SELECT id, unidad_medida FROM unidades_medida ORDER BY unidad_medida";
$q = $pdo->prepare($sql);
$q->execute();
$unidades_medida = $q->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
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
          $ubicacion="Nuevo Conjunto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Detalle del Certificado Maestro #<?=$id_certificado_maestro?></h5>
                  </div>
      		        <form class="form theme-form" role="form" method="post" action="nuevoCertificadoMaestroDetalle.php?id_certificado_maestro=<?=$id_certificado_maestro?>">
                    <input type="hidden" name="ids_occ_detalle_seleccionados" id="ids_occ_detalle_seleccionados" value="">
                    <input type="hidden" name="cant_items_occ_seleccionados" id="cant_items_occ_seleccionados_input" value="0">
                    <input type="hidden" name="base_total_occ_seleccionada" id="base_total_occ_seleccionada_input" value="0">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <h6 class="mb-3 font-weight-bold">Datos del Certificado</h6>
                          <div class="row">
                            <div class="col-lg-6">
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Orden de Compra Cliente</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=htmlspecialchars($numero_occ, ENT_QUOTES).htmlspecialchars(" - ".$certificado_header['cliente_occ'] ?? '', ENT_QUOTES)?></span></div>
                              </div>
                              <div class="form-group row mb-2">
                                <label class="col-sm-5 col-form-label font-weight-bold">Monto OCC</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=$moneda_occ?> <?=number_format((float)($certificado_header['monto'] ?? 0),2,',','.')?></span></div>
                              </div>
                            </div>
                            <div class="col-lg-6">
                              <div class="form-group row mb-0">
                                <label class="col-sm-5 col-form-label font-weight-bold">Fecha emisión certificado</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=htmlspecialchars($certificado_header['fecha_emision_cm'] ?? '', ENT_QUOTES)?></span></div>
                              </div>
                              <div class="form-group row mb-0">
                                <label class="col-sm-5 col-form-label font-weight-bold">% Anticipo</label>
                                <div class="col-sm-7"><span class="form-control-plaintext"><?=htmlspecialchars($certificado_header['porcentaje_anticipo'] ?? '0', ENT_QUOTES)?></span></div>
                              </div>
                            </div>
                          </div>
                          <hr class="mt-4 mb-4">
                          <h6 class="mb-3 font-weight-bold">Items OCC (selección múltiple)</h6>
                          <div class="form-group row">
                            <div class="col-sm-12">
                              <div class="table-responsive">
                                <table class="table table-sm table-bordered display" id="tabla_occ_detalles" style="width:100%">
                                  <thead>
                                    <tr>
                                      <th>ID</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
                                      <th>Precio unitario</th>
                                      <th>Descuento</th>
                                      <th>Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tbody><?php
                                    if (empty($occ_detalles)) {?>
                                      <tr>
                                        <td colspan="6">La Orden de Compra seleccionada no tiene items.</td>
                                      </tr><?php
                                    } else {
                                      foreach ($occ_detalles as $row) {?>
                                        <tr class="occ-item-row" data-id="<?=$row['id']?>" data-subtotal="<?=$row['subtotal']?>" style="cursor:pointer;">
                                          <td><?=$row['id']?></td>
                                          <td><?=htmlspecialchars($row['descripcion'])?></td>
                                          <td><?=number_format($row['cantidad'],2,',','.')?></td>
                                          <td><?=$moneda_occ?> <?=number_format($row['precio_unitario'],2,',','.')?></td>
                                          <td><?=$moneda_occ?> <?=number_format($row['descuento'],2,',','.')?></td>
                                          <td><?=$moneda_occ?> <?=number_format($row['subtotal'],2,',','.')?></td>
                                        </tr><?php
                                      }
                                    }?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>

                          <hr class="mt-4 mb-4">

                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label font-weight-bold">Cantidad items OCC seleccionados</label>
                            <div class="col-sm-3">
                              <span class="form-control-plaintext" id="cant_items_occ_seleccionados">0</span>
                            </div>
                            <label class="col-sm-3 col-form-label font-weight-bold">Base total OCC seleccionada</label>
                            <div class="col-sm-3">
                              <span class="form-control-plaintext" id="base_total_occ_seleccionada"><?=$moneda_occ?> 0,00</span>
                            </div>
                          </div>

                          <hr class="mt-4 mb-4">

                          <div class="form-group row">
                            <input type="hidden" name="id_certificado_maestro_detalle">
                            <label class="col-sm-2 col-form-label">Proyecto(*)</label>
                            <div class="col-sm-4">
                             <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0 and p.id_cliente = ?";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute([$id_cliente_occ]);
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                            <label class="col-sm-2 col-form-label">Modo de generacion(*)</label>
                            <div class="col-sm-4">
                              <div class="form-check">
                                  <input class="form-check-input" type="radio" name="modo_generacion" id="modo_generacion_agrupar" value="agrupar" required>
                                  <label class="form-check-label" for="modo_generacion_agrupar">Agrupar</label>
                                </div>
                                <div class="form-check mt-2">
                                  <input class="form-check-input" type="radio" name="modo_generacion" id="modo_generacion_separar" value="separar">
                                  <label class="form-check-label" for="modo_generacion_separar">Por cada item OCC</label>
                                </div>
                              <!-- <div class="form-control" style="height:auto; padding:0.45rem 0.75rem;">
                                
                              </div> -->
                            </div>
                          </div>

                          <div class="form-group row">
                            <input type="hidden" name="id_tipo_item" id="id_tipo_item" value="1">
                          </div>
                          <h6 class="mb-3 mt-4 font-weight-bold">Aperturado</h6>
                          <div class="form-group row">
                            <div class="col-12">
                              <div class="table-responsive dynamic-grid-wrap">
                                <table class="table table-sm table-bordered dynamic-grid-table" id="tabla_aperturado" style="width:100%">
                                  <!-- <colgroup>
                                    <col style="width:42%;">
                                    <col style="width:11%;">
                                    <col style="width:8%;">
                                    <col style="width:9%;">
                                    <col style="width:11%;">
                                    <col style="width:11%;">
                                    <col style="width:8%;">
                                  </colgroup> -->
                                  <thead>
                                    <tr>
                                      <th style="width:35%;">Descripcion</th>
                                      <th style="width:11%;">Unidad</th>
                                      <th style="width:11%;">Cantidad</th>
                                      <th style="width:13%;">Incidencia (%)</th>
                                      <th style="width:11%;">Precio Unitario</th>
                                      <th style="width:11%;">Total</th>
                                      <th style="width:8%;">Accion</th>
                                    </tr>
                                  </thead>
                                  <tbody id="body_aperturado"></tbody>
                                  <tfoot>
                                    <tr>
                                      <td></td>
                                      <td colspan="2" class="text-right font-weight-bold">Suma incidencia</td>
                                      <td>
                                        <span class="font-weight-bold dynamic-grid-summary-total" id="incidencia_total_aperturado">0,00%</span>
                                      </td>
                                      <td>
                                        <small id="estado_incidencia_aperturado" class="text-danger dynamic-grid-summary-state">Debe sumar 100%</small>
                                      </td>
                                      <td colspan="2" class="text-center">
                                        <button type="button" id="btn_agregar_fila_aperturado" class="btn btn-outline-primary btn-sm">Agregar fila</button>
                                      </td>
                                    </tr>
                                  </tfoot>
                                </table>
                              </div>
                            </div>
                          </div>
                          <!-- <div class="form-group row">
                            <div class="col-sm-3">
                              
                            </div>
                          </div> -->

                          <!-- Compatibilidad temporal hasta Fase 5: backend actual usa estos campos simples -->
                          <input type="hidden" name="descripcion" id="legacy_descripcion" value="">
                          <input type="hidden" name="cantidad" id="legacy_cantidad" value="0">
                          <input type="hidden" name="id_unidad_medida" id="legacy_id_unidad_medida" value="">
                          <input type="hidden" name="precio_unitario" id="legacy_precio_unitario" value="0">
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">

                        <button type="submit" value="1" name="btn1" class="btn btn-success">Crear y Agregar otro Detalle</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary">Crear e ir a Certificados</button>
                        <a href='listarCertificadosMaestros.php' class="btn btn-light">Volver</a>

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
      $(document).ready(function () {
        const simboloMonedaOcc = <?=json_encode($moneda_occ)?>;
        const unidadesMedida = <?=json_encode($unidades_medida)?>;
        const selectedOccItems = {};
        let aperturadoRowIndex = 0;

        function formatNumber(value) {
          return value.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function getBaseCalculoActual() {
          return parseFloat($('#base_total_occ_seleccionada_input').val()) || 0;
        }

        function renderUnidadOptions(selectedValue) {
          let options = '<option value="">Seleccione...</option>';
          unidadesMedida.forEach(function (u) {
            const selected = String(selectedValue || '') === String(u.id) ? ' selected' : '';
            options += '<option value="' + u.id + '"' + selected + '>' + u.unidad_medida + '</option>';
          });
          return options;
        }

        function buildAperturadoRow() {
          const rowId = aperturadoRowIndex++;
          return `
          <tr data-row-id="${rowId}">
            <td>
              <input type="text" class="form-control form-control-sm aper-desc" name="aperturado_descripcion[]" maxlength="199" required>
            </td>
            <td>
              <select class="form-control form-control-sm aper-unidad" name="aperturado_id_unidad_medida[]" required>${renderUnidadOptions('')}</select>
            </td>
            <td>
              <input type="number" class="form-control form-control-sm aper-cantidad" name="aperturado_cantidad[]" step="0.01" min="0.0001" required>
            </td>
            <td>
              <input type="number" class="form-control form-control-sm aper-incidencia" name="aperturado_incidencia[]" step="0.01" min="0" max="100" required>
            </td>
            <td>
              <span class="form-control-plaintext aper-precio-unitario">${simboloMonedaOcc} 0,00</span><input type="hidden" class="aper-precio-unitario-hidden" name="aperturado_precio_unitario[]" value="0">
            </td>
            <td>
              <span class="form-control-plaintext aper-total">${simboloMonedaOcc} 0,00</span>
              <input type="hidden" class="aper-total-hidden" name="aperturado_total[]" value="0">
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-danger btn-eliminar-fila-aperturado">X</button>
            </td>
          </tr>`;
        }

        function syncLegacyFieldsFromFirstRow() {
          const first = $('#body_aperturado tr:first');
          if (!first.length) {
            $('#legacy_descripcion').val('');
            $('#legacy_cantidad').val('0');
            $('#legacy_id_unidad_medida').val('');
            $('#legacy_precio_unitario').val('0');
            return;
          }

          $('#legacy_descripcion').val(first.find('.aper-desc').val() || '');
          $('#legacy_cantidad').val(first.find('.aper-cantidad').val() || '0');
          $('#legacy_id_unidad_medida').val(first.find('.aper-unidad').val() || '');
          $('#legacy_precio_unitario').val(first.find('.aper-precio-unitario-hidden').val() || '0');
        }

        function recalcularAperturado() {
          const base = getBaseCalculoActual();
          let incidenciaTotal = 0;

          $('#body_aperturado tr').each(function () {
            const cantidad = parseFloat($(this).find('.aper-cantidad').val()) || 0;
            const incidencia = parseFloat($(this).find('.aper-incidencia').val()) || 0;
            const totalFila = base * (incidencia / 100);
            const precioUnitario = cantidad > 0 ? (totalFila / cantidad) : 0;

            incidenciaTotal += incidencia;

            $(this).find('.aper-total').text(simboloMonedaOcc + ' ' + formatNumber(totalFila));
            $(this).find('.aper-total-hidden').val(totalFila.toFixed(6));
            $(this).find('.aper-precio-unitario').text(simboloMonedaOcc + ' ' + formatNumber(precioUnitario));
            $(this).find('.aper-precio-unitario-hidden').val(precioUnitario.toFixed(6));
          });

          $('#incidencia_total_aperturado').text(formatNumber(incidenciaTotal) + '%');
          if (Math.abs(incidenciaTotal - 100) < 0.001) {
            $('#estado_incidencia_aperturado').text('OK (100%)').removeClass('text-danger').addClass('text-success');
          } else {
            $('#estado_incidencia_aperturado').text('Debe sumar 100%').removeClass('text-success').addClass('text-danger');
          }

          syncLegacyFieldsFromFirstRow();
        }

        function updateOccSelectionSummary() {
          let ids = Object.keys(selectedOccItems);
          let total = 0;
          ids.forEach(function (id) {
            total += parseFloat(selectedOccItems[id]) || 0;
          });

          $('#ids_occ_detalle_seleccionados').val(ids.join(','));
          $('#cant_items_occ_seleccionados_input').val(ids.length);
          $('#base_total_occ_seleccionada_input').val(total);
          $('#cant_items_occ_seleccionados').text(ids.length);
          $('#base_total_occ_seleccionada').text(simboloMonedaOcc + ' ' + formatNumber(total));
          recalcularAperturado();
        }

        function syncOccRowStyles() {
          $('#tabla_occ_detalles tbody tr.occ-item-row').each(function () {
            const rowId = String($(this).data('id') || '');
            $(this).toggleClass('selected', !!selectedOccItems[rowId]);
          });
        }

        $(document).on('click', '#tabla_occ_detalles tbody tr.occ-item-row', function () {
          const rowId = String($(this).data('id') || '');
          const subtotal = parseFloat($(this).data('subtotal')) || 0;
          if (!rowId) {
            return;
          }

          if (selectedOccItems[rowId] !== undefined) {
            delete selectedOccItems[rowId];
          } else {
            selectedOccItems[rowId] = subtotal;
          }

          syncOccRowStyles();
          updateOccSelectionSummary();
        });

        updateOccSelectionSummary();

        $('#btn_agregar_fila_aperturado').on('click', function () {
          $('#body_aperturado').append(buildAperturadoRow());
        });

        $(document).on('click', '.btn-eliminar-fila-aperturado', function () {
          $(this).closest('tr').remove();
          recalcularAperturado();
        });

        $(document).on('input change', '.aper-desc, .aper-unidad, .aper-cantidad, .aper-incidencia', function () {
          recalcularAperturado();
        });

        $('#btn_agregar_fila_aperturado').trigger('click');

        $('#tabla_occ_detalles').DataTable({
          stateSave: false,
          responsive: false,
          paging: true,
          pageLength: 10,
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

        $('#tabla_occ_detalles').on('draw.dt', function () {
          syncOccRowStyles();
        });

        $('form.form.theme-form').on('submit', function (e) {
          const ids = ($('#ids_occ_detalle_seleccionados').val() || '').trim();
          if (!ids) {
            e.preventDefault();
            alert('Debe seleccionar al menos un item de la OCC.');
            return;
          }

          if (!$('#body_aperturado tr').length) {
            e.preventDefault();
            alert('Debe agregar al menos una fila en el aperturado.');
            return;
          }

          let incidenciaTotal = 0;
          let hasInvalid = false;
          $('#body_aperturado tr').each(function () {
            const desc = ($(this).find('.aper-desc').val() || '').trim();
            const unidad = ($(this).find('.aper-unidad').val() || '').trim();
            const cantidad = parseFloat($(this).find('.aper-cantidad').val()) || 0;
            const incidencia = parseFloat($(this).find('.aper-incidencia').val()) || 0;

            if (!desc || !unidad || cantidad <= 0) {
              hasInvalid = true;
            }
            incidenciaTotal += incidencia;
          });

          if (hasInvalid) {
            e.preventDefault();
            alert('Complete descripcion, unidad y cantidad mayor a 0 en todas las filas de aperturado.');
            return;
          }

          if (Math.abs(incidenciaTotal - 100) >= 0.001) {
            e.preventDefault();
            alert('La incidencia total del aperturado debe sumar 100%.');
            return;
          }

          // Compatibilidad temporal con backend actual (Fase 5 migrará a persistencia masiva)
          syncLegacyFieldsFromFirstRow();
        });

      });
    </script>
  </body>
</html>