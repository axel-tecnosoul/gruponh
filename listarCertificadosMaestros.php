<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
require_once 'manejarFiltros.php';

$filters = gestionarFiltros('listarCertificadosMaestros');

$occ = $filters['occ'] ?? '';
$fecha = $filters['fecha'] ?? '';
$fechah = $filters['fechah'] ?? '';
$esOpCM = esOperacionesSinEconomico();?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_tables.php');?>
    <style>
      .faClass{
        width: 24px;
        height: 20px;
        color: midnightblue;
      }
      .editable {
        text-decoration: underline;
        cursor: default;
      }
      #tablaDetalleOCC {
        background-color: #ffffff;
        width: 100%;
      }
      #tablaDetalleOCC tbody tr.occ-grouped-member td {
        background-color: #eef8ff;
      }
      #tablaDetalleOCC tbody tr.occ-grouped-start td,
      #tablaDetalleOCC tbody tr.occ-grouped-middle td,
      #tablaDetalleOCC tbody tr.occ-grouped-end td,
      #tablaDetalleOCC tbody tr.occ-grouped-single td {
        border-left: 3px solid #2b8dbf;
      }
      #tablaDetalleOCC tbody tr.occ-grouped-start td {
        border-top: 2px solid #2b8dbf;
      }
      #tablaDetalleOCC tbody tr.occ-grouped-end td {
        border-bottom: 2px solid #2b8dbf;
      }
      #tablaDetalleOCC tbody tr.occ-grouped-single td {
        border-top: 2px solid #2b8dbf;
        border-bottom: 2px solid #2b8dbf;
      }
      #tablaDetalleOCC tbody tr.occ-breakdown-row > td {
        background-color: #f7fcff;
        padding: 0.25rem !important;
      }
      #tablaDetalleOCC .occ-breakdown-table th,
      #tablaDetalleOCC .occ-breakdown-table td {
        vertical-align: middle;
        white-space: nowrap;
      }
      #tablaDetalleOCC .occ-breakdown-table th:first-child,
      #tablaDetalleOCC .occ-breakdown-table td:first-child {
        white-space: normal;
        min-width: 180px;
      }
      #tablaOCC th.cliente-col, #tablaOCC td.cliente-col {
        min-width: 460px;
        max-width: 520px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      #tablaOCC {
        min-width: 2100px !important;
      }
    </style>
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <!-- Page Header Start-->
      <?php include('header.php');?>
     
      <!-- Page Header Ends                              -->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Certificados Maestros ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-body">
                    <form class="form-inline theme-form mt-3" name="form1" method="post" action="listarCertificadosMaestros.php">
                      <div class="form-group mb-0">
                        Nro OCC:&nbsp;<input class="form-control" size="3" type="text" value="<?=htmlspecialchars($occ, ENT_QUOTES, 'UTF-8')?>" name="occ">
                      </div>
                      <div class="form-group mb-0">
                        Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?=htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8')?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?=htmlspecialchars($fechah, ENT_QUOTES, 'UTF-8')?>" name="fechah">
                      </div>
                      <div class="form-group mb-0">
                        <button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarCertificadosMaestros.php'">Buscar</button>
                        <a href="listarCertificadosMaestros.php?clear_filters=1" class="btn btn-secondary ml-2">Limpiar</a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php
                      echo $ubicacion; 
                      if (!$esOpCM && !empty(tienePermiso(374))) { ?>
                        <a href="nuevoCertificadoMaestro.php" title="Nuevo Certificado Maestro" style="color: midnightblue;" class="fa fa-lg"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo">CM</a>&nbsp;&nbsp;<?php
                      }
                      // Operaciones no ve exportación ni montos
                      if (!$esOpCM) { ?>
                      <a href="#" id="link_exportar_certificado"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;<?php } ?>
                      <a href="#" target="_blank" id="link_imprimir_certificado"><img src="img/print.png" width="24" height="20" border="0" alt="Imprimir" title="Imprimir CM"></a>&nbsp;&nbsp;
                      <?php if (!$esOpCM && !empty(tienePermiso(375))) {?>
                        <a href="#" id="link_modificar_ot"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp;<?php
                        echo '<a href="#" id="link_aprobar_maestro"><img src="img/aprobar.png" width="24" height="20" border="0" alt="Aprobar CM" title="Aprobar CM"></a>&nbsp;&nbsp;';
                      }
                      if (!$esOpCM && !empty(tienePermiso(382))) {?>
                        <a href="#" id="link_eliminar_maestro"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      /*echo '<a href="#" id="link_imprimir_pl"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
					            echo '&nbsp;&nbsp;';*/?>

                      <a href="#" id="link_ver_occ" title="Ver Certificado Maestro" style="color: midnightblue;" class="fa fa-lg"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver">CM</a>&nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(376))) { ?>
                        <a href="#" id="link_nuevo_consumo" title="Ver Certificados de Avance"><i style="width: 72px; height: 20px;color: midnightblue;" class='fa fa-lg fa-certificate'>CA</i></a><?php
                      }?>
                      <?php if (!$esOpCM) { ?><a href="#" id="link_nuevo_anticipo" title="Nuevo Anticipo" style="color: midnightblue;" class="fa fa-lg"><img src="img/dolar.png" width="24" height="25" border="0" alt="Nuevo Anticipo"></a><?php } ?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display" id="tablaOCC">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>N° CM</th>
                            <th>N° OCC</th>
                            <th class="cliente-col">Cliente</th>
                            <th>Fecha emision</th>
                            <th>Anticipo %</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Monto</th>
                            <th>Estado CM</th>
                            <th>Monto avances</th>
                            <th>Monto anticipos</th>
                            <th>Monto desacopios</th>
                            <th>Monto descuentos</th>
                            <th>Monto redeterminacion</th>
                            <th>Saldo Pendiente</th>
                            <th>Observaciones</th>
                            <th class="d-none">Cant CA</th>
                          </tr>
                        </thead>
                        <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>N° CM</th>
                            <th>N° OCC</th>
                            <th class="cliente-col">Cliente</th>
                            <th>Fecha emision</th>
                            <th>Anticipo %</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Monto</th>
                            <th>Estado CM</th>
                            <th>Monto avances</th>
                            <th>Monto anticipos</th>
                            <th>Monto desacopios</th>
                            <th>Monto descuentos</th>
                            <th>Monto redeterminacion</th>
                            <th>Saldo Pendiente</th>
                            <th>Observaciones</th>
                            <th class="d-none">Cant CA</th>
                          </tr>
                        </tfoot>
                        <tbody><?php 
                          if (!empty($filters)) {
							  
                            $pdo = Database::connect();
                            $sql = "SELECT cm.id AS id_cm, occ.numero AS numero_occ, c.nombre AS cliente, date_format(cm.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cm.fecha_inicio,'%d/%m/%y') AS fecha_inicio,CASE WHEN cm.fecha_fin = '0000-00-00' THEN '-' ELSE date_format(cm.fecha_fin,'%d/%m/%y') END AS fecha_fin, cm.porcentaje_anticipo, m.moneda,cm.monto_total,cm.monto_acumulado_avances,cm.monto_acumulado_anticipos,cm.monto_acumulado_desacopios,cm.monto_acumulado_descuentos,cm.monto_acumulado_ajustes,cm.observaciones,cm.aprobado_cliente,(SELECT ca2.monto_anticipo FROM certificados_anticipos ca2 WHERE ca2.id_certificado_maestro=cm.id ORDER BY ca2.id DESC LIMIT 1) AS monto_anticipo_registrado,(SELECT COUNT(ca.id) FROM certificados_avances_cabecera ca WHERE ca.id_certificado_maestro=cm.id) AS cant_ca FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ=occ.id INNER JOIN cuentas c ON c.id=occ.id_cuenta_cliente INNER JOIN monedas m ON cm.id_moneda=m.id WHERE 1";
                            if (!empty($occ)) {
                              $sql .= " AND occ.numero = '".$occ."' ";
                            }
                            if (!empty($fecha)) {
                              $sql .= " AND cm.fecha_emision >= '".$fecha."' ";
                            }
                            if (!empty($fechah)) {
                              $sql .= " AND cm.fecha_emision <= '".$fechah."' ";
                            }

                            foreach ($pdo->query($sql) as $row) {
                              $sql2 = "SELECT COALESCE(sum(`monto_total`),0) monto_total, COALESCE(sum(`monto_acumulado_avances`),0) monto_acumulado_avances, COALESCE(sum(`monto_acumulado_anticipos`),0) monto_acumulado_anticipos, COALESCE(sum(`monto_acumulado_desacopios`),0) monto_acumulado_desacopios, COALESCE(sum(`monto_acumulado_descuentos`),0) monto_acumulado_descuentos, COALESCE(sum(`monto_acumulado_ajustes`),0) monto_acumulado_ajustes FROM `certificados_avances_cabecera` WHERE `id_certificado_maestro` = ? ";
                              $q2 = $pdo->prepare($sql2);
                              $q2->execute([$row["id_cm"]]);
                              $data2 = $q2->fetch(PDO::FETCH_ASSOC);
                              
                              $montoTotalAvances = (float) ($data2["monto_acumulado_avances"] ?? 0);
                              $montoTotalAnticipos = (float) ($data2["monto_acumulado_anticipos"] ?? 0);
                              if ($row["monto_anticipo_registrado"] !== null) {
                                $montoTotalAnticipos = (float) $row["monto_anticipo_registrado"];
                              }
                              $montoTotalDesacopios = (float) ($data2["monto_acumulado_desacopios"] ?? 0);
                              $montoTotalDescuentos = (float) ($data2["monto_acumulado_descuentos"] ?? 0);
                              $montoTotalAjustes = (float) ($data2["monto_acumulado_ajustes"] ?? 0);
                              $montoTotalCertificado = (float) ($row["monto_total"] ?? 0);
                              
                              $saldoPendiente=$montoTotalCertificado-$montoTotalAvances-$montoTotalAnticipos-$montoTotalDesacopios-$montoTotalDescuentos-$montoTotalAjustes?>

                              <tr data-id="<?=$row["id_cm"]?>" data-aprobado="<?=$row["aprobado_cliente"] ? '1' : '0'?>" data-cant-ca="<?=$row["cant_ca"]?>" data-tiene-anticipo="<?=$row["monto_anticipo_registrado"] !== null ? '1' : '0'?>">
                                <td class="d-none"><?=$row["id_cm"]?></td>
                                <td><?=$row["id_cm"]?></td>
                                <td><?=$row["numero_occ"]?></td>
                                <td class="cliente-col" title="<?=htmlspecialchars($row["cliente"], ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($row["cliente"], ENT_QUOTES, 'UTF-8')?></td>
                                <td><?=$row["fecha_emision"]?></td>
                                <td><?=$row["porcentaje_anticipo"]?>%</td>
                                <td><?=$row["fecha_inicio"]?></td>
                                <td><?=$row["fecha_fin"]?></td>
                                <td><?=$row["moneda"]." ".number_format($row["monto_total"],2)?></td>
                                <td><span class="badge <?=$row["aprobado_cliente"] ? 'badge-success' : 'badge-warning'?>"><?=$row["aprobado_cliente"] ? 'Aprobado' : 'Pendiente'?></span></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalAvances,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalAnticipos,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalDesacopios,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalDescuentos,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalAjustes,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($saldoPendiente,2)?></td>
                                <td><?=htmlspecialchars($row["observaciones"], ENT_QUOTES, 'UTF-8')?></td>
                                <td class="d-none"><?= $row["cant_ca"]?></td>
                              </tr><?php
                            }
                            Database::disconnect();
                          }?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Zero Configuration  Ends-->
              <!-- Feature Unable /Disable Order Starts-->
            </div>
			      <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Detalle del Certificado Maestro
                      <!-- &nbsp;&nbsp;
                      <span id="btnAbrirModalModificarCantidades" title="Modificar Cantidades" style="cursor: pointer;"><i class='faClass fa fa-lg fa-cogs'></i></span>&nbsp;&nbsp; -->
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="table table-sm table-bordered mb-0" id="tablaDetalleOCC">
                        <thead>
                          <tr>
                             <th>Posición</th>
                             <th>Descripcion</th>
                            <th class="text-right">Cantidad</th>
                            <?php if (!$esOpCM) { ?><th class="text-right">Precio Unitario</th>
                            <th class="text-right">Descuento</th>
                            <th class="text-right">Subtotal</th><?php } ?>
                            <th class="text-center occ-desglose-col" style="width:130px;">Acciones</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                         <tfoot>
                           <tr>
                             <th>Posición</th>
                             <th>Descripcion</th>
                            <th class="text-right">Cantidad</th>
                            <?php if (!$esOpCM) { ?><th class="text-right">Precio Unitario</th>
                            <th class="text-right">Descuento</th>
                            <th class="text-right">Subtotal</th><?php } ?>
                            <th class="text-center">Acciones</th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Zero Configuration  Ends-->
              <!-- Feature Unable /Disable Order Starts-->
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>

    <div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-labelledby="modalEliminarLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEliminarLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea eliminar el certificado maestro?</div>
          <div class="modal-footer">
            <a href="#" class="btn btn-primary" id="btnEliminarMaestro">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalAprobarMaestro" tabindex="-1" role="dialog" aria-labelledby="modalAprobarMaestroLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalAprobarMaestroLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea aprobar el Certificado Maestro? Luego no podrá modificarse.</div>
          <div class="modal-footer">
            <a href="#" class="btn btn-primary" id="btnAprobarMaestro">Aprobar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal">Volver</button>
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <script>
      const esOpCM = <?php echo $esOpCM ? 'true' : 'false'; ?>;
      $(document).ready(function() {

        // Setup - add a text input to each footer cell
        $('#tablaOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $('#tablaOCC').DataTable({
          stateSave: false,
		      searching: false,
          responsive: false,
          autoWidth: false,
          scrollX: false,
          columnDefs: [
            { targets: [0,17], visible: false, searchable: false },
            { targets: [8,9,10,11,12,13,14], visible: !esOpCM },
            { targets: 0, width: "1px" },
            { targets: 1, width: "80px" },
            { targets: 2, width: "90px" },
            { targets: 3, width: "460px" },
            { targets: 4, width: "120px" },
            { targets: 5, width: "95px" },
            { targets: [6,7], width: "120px" },
            { targets: [8,9,10,11,12,13,14], width: "150px" },
            { targets: 15, width: "260px" },
            { targets: 16, width: "120px" },
            { targets: 17, width: "1px" }
          ],
          dom: 'Bfrtp<"bottom"l>',
          buttons: [
            'excel'
          ],
          lengthMenu: [
            [10, 25, 50, 100, 500, 1000], // Cantidades de registros disponibles
            [10, 25, 50, 100, 500, 1000]  // Texto mostrado en el menú desplegable
          ],
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
        var table = $('#tablaOCC').DataTable();
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });
        
        //$('#tablaOCC').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaOCC tbody tr td", function(){
          var t=$(this).parent();

          let id_cm=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            get_detalle_certificado_maestro(0)

            $("#link_exportar_certificado").attr("href","#");
            $("#link_imprimir_certificado").attr("href","#");
            $("#link_modificar_ot").attr("href","#");
            $("#link_aprobar_maestro").attr("href","#");
            $("#link_nuevo_consumo").attr("href","#");
            $("#link_nuevo_anticipo").attr("href","#");
            $("#link_ver_occ").attr("href","#");
          }else{
            //t.parent().find("tr").removeClass("selected");
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_detalle_certificado_maestro(id_cm)
            
            $("#link_exportar_certificado").attr("href","exportCertificadoMaestro.php?id="+id_cm);
            $("#link_imprimir_certificado").attr("href","imprimirCertificadoMaestroPlantilla.php?id="+id_cm);
            const aprobado = t.attr("data-aprobado") === "1";
            $("#link_modificar_ot").attr("href", aprobado ? "#" : "modificarCertificadoMaestro.php?id="+id_cm);
            $("#link_aprobar_maestro").attr("href", aprobado ? "#" : "aprobarCertificadoMaestro.php?id="+id_cm);
            $("#link_nuevo_consumo").attr("href", aprobado ? "listarCertificadosAvances.php?id_certificado_maestro="+id_cm : "#");
            $("#link_nuevo_anticipo").attr("href", aprobado ? "#" : "nuevoAnticipoCertificadoMaestro.php?id_certificado_maestro="+id_cm);
            $("#link_ver_occ").attr("href","verCertificadosMaestro.php?id="+id_cm);
          }
        });

        get_detalle_certificado_maestro(0)

        $("#link_exportar_certificado").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un certificado maestro para exportar")
          }
        })

        $("#link_imprimir_certificado").on("click",function(e){
          if (this.getAttribute("href") === "#") {
            e.preventDefault();
            alert("Por favor seleccione un certificado maestro para imprimir");
          }
        })

        $("#link_modificar_ot").on("click",function(e){
          const filaSeleccionada = $("#tablaOCC tbody tr.selected");
          if (filaSeleccionada.length === 0) {
            e.preventDefault();
            alert("Por favor seleccione un certificado maestro para modificarla")
          } else if (filaSeleccionada.attr("data-aprobado") === "1") {
            e.preventDefault();
            alert("El Certificado Maestro está aprobado y no puede modificarse.");
          }
        })

        $("#link_aprobar_maestro").on("click", function(e) {
          e.preventDefault();
          const filaSeleccionada = $("#tablaOCC tbody tr.selected");

          if (filaSeleccionada.length === 0) {
            alert("Por favor seleccione un certificado maestro para aprobar");
            return;
          }

          if (filaSeleccionada.attr("data-aprobado") === "1") {
            alert("El Certificado Maestro ya está aprobado.");
            return;
          }

          const id = filaSeleccionada.attr("data-id");
          $("#btnAprobarMaestro").attr("href", "aprobarCertificadoMaestro.php?id=" + id);
          $("#modalAprobarMaestro").modal("show");
        });

        $("#link_eliminar_maestro").on("click",function(){
          let fila_selected=$(document).find("#tablaOCC tbody tr.selected");
          console.log(fila_selected.length);

          //if(this.href==l || this.href==l+"#"){
          if(fila_selected.length==0){
            alert("Por favor seleccione un certificado para eliminarlo")
          }else{
            let cant_ca=fila_selected.attr("data-cant-ca");
            let id=fila_selected.attr("data-id");
            if (fila_selected.attr("data-aprobado") === "1") {
              alert("El Certificado Maestro está aprobado y no puede eliminarse.");
              return;
            }
            if(cant_ca=="0"){
              $("#btnEliminarMaestro").attr("href","eliminarCertificadoMaestro.php?id="+id);
              $("#modalEliminar").modal("show");
            }else{
              alert("El certificado no puede ser eliminado debido a que posee avances")
            }
          }
        })

        $("#link_nuevo_consumo").on("click",function(e){
          const filaSeleccionada = $("#tablaOCC tbody tr.selected");
          if (filaSeleccionada.length === 0) {
            e.preventDefault();
            alert("Por favor seleccione un certificado maestro para ver sus certificados de avance");
          } else if (filaSeleccionada.attr("data-aprobado") !== "1") {
            e.preventDefault();
            alert("El Certificado Maestro debe estar aprobado para ver sus certificados de avance.");
          }
        })

        $("#link_nuevo_anticipo").on("click", function(e) {
          let filaSeleccionada = $("#tablaOCC tbody tr.selected");

          if (filaSeleccionada.length === 0) {
            e.preventDefault();
            alert("Por favor seleccione un certificado maestro para cargar un anticipo");
            return;
          }

          if (filaSeleccionada.attr("data-aprobado") === "1") {
            e.preventDefault();
            alert("El Certificado Maestro está aprobado y no puede modificarse.");
            return;
          }

          let textoPorcentaje = filaSeleccionada.find("td:nth-child(6)").text().trim().replace("%", "").replace(",", ".");

          let porcentajeAnticipo = parseFloat(textoPorcentaje);
          let tieneAnticipo = filaSeleccionada.attr("data-tiene-anticipo") === "1";
          let advertencias = [];

          if (tieneAnticipo) {
            advertencias.push("El Certificado Maestro ya tiene un anticipo registrado. Si continua, editara ese anticipo.");
          }

          if (porcentajeAnticipo === 0) {
            advertencias.push("El Certificado Maestro tiene un porcentaje de anticipo del 0%.");
          }

          if (advertencias.length > 0) {
            let continuar = confirm(advertencias.join("\n\n") + "\n\n¿Esta seguro de que desea continuar?");

            if (!continuar) {
              e.preventDefault();
            }
          }
        });

        $("#link_ver_occ").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un certificado maestro para ver detalle")
          }
        })
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    
      function escapeDetalleHtml(text) {
        return String(text || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function fmtDetalleNum(v) {
        return Number(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }

      function ocultarDetalleCM() {
         const col = esOpCM ? 4 : 7;
         $('#tablaDetalleOCC tbody').html('<tr><td colspan="'+col+'" class="text-muted">Seleccione un certificado maestro para ver el detalle.</td></tr>');
      }

      function renderDetalleCM(data) {
        const $body = $('#tablaDetalleOCC tbody');
        $body.empty();

        if (!data || !Array.isArray(data.items) || !data.items.length) {
           const col2 = esOpCM ? 4 : 7;
           $body.html('<tr><td colspan="'+col2+'" class="text-muted">La Orden de Compra seleccionada no tiene items.</td></tr>');
          return;
        }

        const moneda = data.moneda || '';

        const gruposPorOwner = {};
        const gruposPorItem = {};
        (data.grupos || []).forEach(function(g) {
          const ids = (g.occ_ids || []).map(String);
          if (!ids.length) return;
          const owner = ids[ids.length - 1];
          (gruposPorOwner[owner] = gruposPorOwner[owner] || []).push({ g: g, ids: ids });
          ids.forEach(function(id) {
            (gruposPorItem[id] = gruposPorItem[id] || []).push(g);
          });
        });

        const clasePorItem = {};
        (data.grupos || []).forEach(function(g) {
          const ids = (g.occ_ids || []).map(String);
          ids.forEach(function(id, idx) {
            if (clasePorItem[id]) return;
            if (ids.length === 1) clasePorItem[id] = 'occ-grouped-single';
            else if (idx === 0) clasePorItem[id] = 'occ-grouped-start';
            else if (idx === ids.length - 1) clasePorItem[id] = 'occ-grouped-end';
            else clasePorItem[id] = 'occ-grouped-middle';
          });
        });

        const vistos = {};
        const orden = [];
        (data.grupos || []).forEach(function(g) {
          (g.occ_ids || []).forEach(function(id) {
            id = String(id);
            if (!vistos[id]) { vistos[id] = 1; orden.push(id); }
          });
        });
        const itemsById = {};
        data.items.forEach(function(it) { itemsById[String(it.id)] = it; });
        data.items.forEach(function(it) {
          const id = String(it.id);
          if (!vistos[id]) orden.push(id);
        });

        orden.forEach(function(id) {
          const it = itemsById[id];
          if (!it) return;
          const propietario = gruposPorOwner[id];
          const clases = ['occ-item-row'];
          if (clasePorItem[id]) clases.push('occ-grouped-member', clasePorItem[id]);

          let acciones = '<span class="text-muted">Sin desglose</span>';
          if (propietario && propietario.length) {
            acciones = '<button type="button" class="btn btn-secondary btn-sm btn-toggle-desglose" data-target="#desglose-cm-' + escapeDetalleHtml(id) + '">Ocultar</button>';
          } else if (gruposPorItem[id] && gruposPorItem[id].length) {
            acciones = '<span class="text-muted">Incluido en desglose grupal</span>';
          }

          let filaHtml = '<tr class="' + clases.join(' ') + '">' +
             '<td>' + escapeDetalleHtml(it.posicion) + '</td>' +
             '<td>' + escapeDetalleHtml(it.descripcion) + '</td>' +
            '<td class="text-right">' + fmtDetalleNum(it.cantidad) + '</td>';
          if (!esOpCM) {
            filaHtml += '<td class="text-right">' + escapeDetalleHtml(moneda) + ' ' + fmtDetalleNum(it.precio_unitario) + '</td>' +
            '<td class="text-right">' + escapeDetalleHtml(moneda) + ' ' + fmtDetalleNum(it.descuento) + '</td>' +
            '<td class="text-right">' + escapeDetalleHtml(moneda) + ' ' + fmtDetalleNum(it.subtotal) + '</td>';
          }
          filaHtml += '<td class="text-center">' + acciones + '</td></tr>';
          $body.append(filaHtml);

          if (propietario && propietario.length) {
            let paneles = '';
            propietario.forEach(function(entry) {
              const base = parseFloat(entry.g.monto_base_occ) || 0;
              let filas = '';
              let sumaTotal = 0;
              (entry.g.filas || []).forEach(function(f) {
                const cant = parseFloat(f.cantidad) || 0;
                const inc = parseFloat(f.incidencia) || 0;
                const total = base * (inc / 100);
                const pu = cant > 0 ? (total / cant) : 0;
                sumaTotal += total;
                const posicion = f.posicion_aperturado || '';
                if (esOpCM) {
                  filas += '<tr><td>' + escapeDetalleHtml(posicion) + '</td><td>' + escapeDetalleHtml(f.descripcion) + '</td><td>' + escapeDetalleHtml(f.unidad) + '</td><td class="text-right">' + fmtDetalleNum(cant) + '</td><td class="text-right">' + fmtDetalleNum(inc) + '%</td></tr>';
                } else {
                  filas += '<tr><td>' + escapeDetalleHtml(posicion) + '</td><td>' + escapeDetalleHtml(f.descripcion) + '</td><td>' + escapeDetalleHtml(f.unidad) + '</td><td class="text-right">' + fmtDetalleNum(cant) + '</td><td class="text-right">' + fmtDetalleNum(inc) + '%</td><td class="text-right">' + escapeDetalleHtml(moneda) + ' ' + fmtDetalleNum(pu) + '</td><td class="text-right">' + escapeDetalleHtml(moneda) + ' ' + fmtDetalleNum(total) + '</td></tr>';
                }
              });
              if (!(entry.g.filas || []).length) {
                const colEmpty = esOpCM ? 5 : 7;
                filas = '<tr><td colspan="'+colEmpty+'" class="text-muted">Sin filas de detalle guardadas.</td></tr>';
              }
              const theadBreak = esOpCM ? '<thead><tr><th>Posición</th><th>Descripcion</th><th>Unidad</th><th class="text-right">Cantidad</th><th class="text-right">Incidencia</th></tr></thead>' : '<thead><tr><th>Posición</th><th>Descripcion</th><th>Unidad</th><th class="text-right">Cantidad</th><th class="text-right">Incidencia</th><th class="text-right">Precio Unitario</th><th class="text-right">Total</th></tr></thead>';
              const tfootBreak = esOpCM ? '' : '<tfoot class="bg-light"><tr class="font-weight-bold"><td colspan="6" class="text-right">Total</td><td class="text-right">' + escapeDetalleHtml(moneda) + ' ' + fmtDetalleNum(sumaTotal) + '</td></tr></tfoot>';
              paneles += '<div class="border rounded px-2 py-2 mb-2 occ-lote-inline-row"><div class="table-responsive"><table class="table table-sm table-bordered mb-0 occ-breakdown-table">' + theadBreak + '<tbody>' + filas + '</tbody>' + tfootBreak + '</table></div></div>';
            });
             const colBreak = esOpCM ? 4 : 7;
             $body.append('<tr class="occ-breakdown-row" id="desglose-cm-' + escapeDetalleHtml(id) + '"><td colspan="'+colBreak+'">' + paneles + '</td></tr>');
          }
        });
      }

      $(document).on('click', '#tablaDetalleOCC .btn-toggle-desglose', function() {
        const target = $($(this).data('target'));
        target.toggle();
        $(this).text(target.is(':visible') ? 'Ocultar' : 'Mostrar');
      });

      function get_detalle_certificado_maestro(id_certificado_maestro){
        if (!id_cm_valido(id_certificado_maestro)) {
          ocultarDetalleCM();
          return;
        }
        $.ajax({
          url: 'get_detalle_certificado_maestro.php',
          method: 'post',
          data: { id_certificado_maestro: id_certificado_maestro },
          dataType: 'json'
        }).done(function(data){
          renderDetalleCM(data && !Array.isArray(data) ? data : null);
        }).fail(function(xhr, status, error){
          console.error('Error al cargar detalle CM:', status, error, xhr && xhr.responseText);
           const colErr = esOpCM ? 4 : 7;
           $('#tablaDetalleOCC tbody').html('<tr><td colspan="'+colErr+'" class="text-danger">No se pudo cargar el detalle.</td></tr>');
        });
      }

      function id_cm_valido(v){
        return String(v || '').trim() !== '' && String(v) !== '0' && !isNaN(parseInt(v, 10));
      }
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>
