<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
$esOpCA = function_exists('esOperacionesSinEconomico') ? esOperacionesSinEconomico() : false;
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT cm.id,occ.numero AS numero_occ,date_format(cm.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cm.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cm.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cm.cotizacion_dolar,cm.monto_total,cm.monto_acumulado_avances,cm.monto_acumulado_anticipos,cm.monto_acumulado_desacopios,cm.monto_acumulado_descuentos,cm.monto_acumulado_ajustes,cm.observaciones,cm.aprobado_cliente FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ=occ.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cm.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$_GET["id_certificado_maestro"]]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$cmAprobado = (int) ($data['aprobado_cliente'] ?? 0) === 1;
if (!$data || !$cmAprobado) {
  Database::disconnect();
  header("Location: listarCertificadosMaestros.php?error=cm_pendiente");
  exit;
}
?>
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
      #tablaDetalleOCC .avance-periodo-anterior { background-color: #f1f3f5; }
      #tablaDetalleOCC .avance-periodo-actual { background-color: #e9f6fd; }
      #tablaDetalleOCC .avance-periodo-acumulado { background-color: #eef7ee; }
      #tablaDetalleOCC .avance-periodo-saldo { background-color: #fdf3f3; }
      #tablaDetalleOCC .avance-periodo-anterior,
      #tablaDetalleOCC .avance-periodo-actual,
      #tablaDetalleOCC .avance-periodo-acumulado,
      #tablaDetalleOCC .avance-periodo-saldo { border-left: 2px solid #2b8dbf; }
      
      #tabla_occ_listado tbody tr.occ-grouped-member > td {
        background-color: #eef8ff;
      }
      #tabla_occ_listado tbody tr.occ-grouped-member > td + td {
        border-left: 2px solid #2b8dbf;
      }
      #tabla_occ_listado tbody tr.occ-grouped-start > td {
        border-top: 2px solid #2b8dbf;
      }
      #tabla_occ_listado tbody tr.occ-grouped-end > td {
        border-bottom: 2px solid #2b8dbf;
      }
      #tabla_occ_listado tbody tr.occ-grouped-single > td {
        border-top: 2px solid #2b8dbf;
        border-bottom: 2px solid #2b8dbf;
      }
      #tabla_occ_listado tbody tr.occ-grouped-member > td:first-child {
        border-left: 3px solid #2b8dbf;
      }
      #tabla_occ_listado tbody tr.occ-grouped-member > td:last-child {
        border-right: 3px solid #2b8dbf;
      }
      
      .occ-group-aperturado-wrap {
        border: 1px solid #dee2e6;
        border-left: 4px solid #2b8dbf;
        background: #f7fcff;
        border-radius: 4px;
        padding: 0.5rem;
        margin-bottom: 1rem;
      }
      #contenedor_detalle_avance .occ-breakdown-table {
        width: 100%;
      }
      #contenedor_detalle_avance .occ-breakdown-table th,
      #contenedor_detalle_avance .occ-breakdown-table td {
        vertical-align: middle;
        white-space: nowrap;
      }
      #contenedor_detalle_avance .occ-breakdown-table th:first-child,
      #contenedor_detalle_avance .occ-breakdown-table td:first-child {
        white-space: normal;
        min-width: 180px;
      }
      #contenedor_detalle_avance .avance-periodo {
        border-left: 2px solid #2b8dbf;
      }
      #contenedor_detalle_avance .avance-periodo-anterior {
        background-color: #f1f3f5;
      }
      #contenedor_detalle_avance .avance-periodo-actual {
        background-color: #e9f6fd;
      }
      #contenedor_detalle_avance .avance-periodo-acumulado {
        background-color: #eef7ee;
      }
      #contenedor_detalle_avance .avance-periodo-saldo {
        background-color: #fdf3f3;
      }
      #contenedor_detalle_avance .avance-col-inicio {
        border-left: 2px solid #2b8dbf;
      }
      #contenedor_detalle_avance .avance-cantidad-col {
        width: 78px !important;
        min-width: 78px !important;
        max-width: 78px !important;
      }
      #contenedor_detalle_avance .avance-porcentaje-col {
        width: 62px !important;
        min-width: 62px !important;
        max-width: 62px !important;
        padding-right: 2px !important;
        padding-left: 2px !important;
        text-align: center !important;
      }
      .legacy-section {
        border-left: 4px solid #ffc107;
        background: #fffbf0;
        border-radius: 4px;
        padding: 1rem;
        margin-top: 1rem;
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
          $ubicacion="Certificados de Avance ("."CM #".$data['id'].")";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php
                      echo $ubicacion; 
                       if (!empty(tienePermiso(377)) && $cmAprobado) { ?>
                         &nbsp;
                         <a href="nuevoCertificadoAvance.php?id=<?=$_GET["id_certificado_maestro"]?>" title="Nuevo Certificado de Avance"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo"></a>&nbsp;&nbsp;<?php
                       }
                      if (!$esOpCA) echo '<a href="#" id="link_exportar_certificado" style="display:inline-block;margin:0 10px 0 6px;vertical-align:middle;"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;';
                      echo '<a href="#" id="link_imprimir_certificado" target="_blank" title="Imprimir Certificado de Avance"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir"></a>&nbsp;&nbsp;';
                      if (!empty(tienePermiso(378))) {
                        echo '<a href="#" id="link_modificar_ot"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp;';
                        if (!$esOpCA) {
                          echo '<a href="#" id="link_aprobar_avance"><img src="img/aprobar.png" width="24" height="20" border="0" alt="Aprobado Cliente" title="Aprobado Cliente"></a>&nbsp;&nbsp;';
                        }
                      }
					            if (!$esOpCA && !empty(tienePermiso(383))) {
                        echo '<a href="#" id="link_eliminar_avance"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;';
                      }
                      /*echo '<a href="#" id="link_imprimir_pl"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
					            echo '&nbsp;&nbsp;';*/?>

                      <a href="#" id="link_ver_occ" title="Ver Certificado de Avance" style="color: midnightblue;" class="fa fa-lg"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver"></a>&nbsp;
                      <?php if (!$esOpCA) { ?><a href="#" id="link_nuevo_ajuste" title="Nuevo Ajuste" style="color: midnightblue;" class="fa fa-lg"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo Ajuste"></a>&nbsp;<?php } ?>
                    </h5>
                  </div>
                   <div class="card-body">
                     <?php if (!$cmAprobado) { ?>
                       <div class="alert alert-warning">Este Certificado Maestro está pendiente de aprobación. No se pueden cargar nuevos certificados de avance.</div>
                     <?php } ?>
                    <div class="dt-ext table-responsive">
                      <table class="display" id="tablaOCC">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
							<th>N° CA</th>
                            <th>Rev.</th>
                            <th>Fecha emision</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Monto</th>
                            <th>Cotiz. dolar</th>
                            <th class="d-none">Acumulado avances</th>
                            <th class="d-none">Acumulado anticipos</th>
                            <th class="d-none">Acumulado desacopios</th>
                            <th class="d-none">Acumulado descuentos</th>
                            <th class="d-none">Acumulado ajustes</th>
                            <th>Observaciones</th>
							<th>Aprobado Cliente</th>
                          </tr>
                        </thead>
                        <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
							<th>N° CA</th>
                            <th>Rev.</th>
                            <th>Fecha emision</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Monto</th>
                            <th>Cotiz. dolar</th>
                            <th class="d-none">Acumulado avances</th>
                            <th class="d-none">Acumulado anticipos</th>
                            <th class="d-none">Acumulado desacopios</th>
                            <th class="d-none">Acumulado descuentos</th>
                            <th class="d-none">Acumulado ajustes</th>
                            <th>Observaciones</th>
							<th>Aprobado Cliente</th>
                          </tr>
                        </tfoot>
                        <tbody><?php
                          
                          $pdo = Database::connect();
                          $sql = "SELECT cac.id,cac.nro_certificado,cac.nro_revision,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cac.monto_total,cac.cotizacion_dolar,cac.monto_acumulado_avances,cac.monto_acumulado_anticipos,cac.monto_acumulado_desacopios,cac.monto_acumulado_descuentos,cac.monto_acumulado_ajustes,cac.observaciones,cac.aprobado_cliente,NOT EXISTS (SELECT 1 FROM certificados_avances_cabecera x WHERE x.id_certificado_maestro = cac.id_certificado_maestro AND x.nro_certificado = cac.nro_certificado AND x.nro_revision > cac.nro_revision) AS es_ultima FROM certificados_avances_cabecera cac INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cac.id_certificado_maestro = ".(int)$_GET["id_certificado_maestro"]." ORDER BY cac.nro_certificado, cac.nro_revision";
                          foreach ($pdo->query($sql) as $row) {
                            echo '<tr data-id="'.$row["id"].'" data-aprobado="'.($row["aprobado_cliente"] == 1 ? '1' : '0').'" data-es-ultima="'.($row["es_ultima"] ? '1' : '0').'">';
                            echo '<td class="d-none">'.$row["id"].'</td>';
							echo '<td>'.$row["nro_certificado"].'</td>';
                            echo '<td>'.($row["nro_revision"] ?? '-').'</td>';
                            echo '<td>'.$row["fecha_emision"].'</td>';
                            echo '<td>'.$row["fecha_inicio"].'</td>';
                            echo '<td>'.$row["fecha_fin"].'</td>';
                            echo '<td>'.$row["moneda"]." ".number_format($row["monto_total"],2).'</td>';
                            echo '<td>$'.$row["cotizacion_dolar"].'</td>';
                            echo '<td class="d-none">'.$row["moneda"]." ".number_format($row["monto_acumulado_avances"],2)."</td>";
                            echo '<td class="d-none">'.$row["moneda"]." ".number_format($row["monto_acumulado_anticipos"],2)."</td>";
                            echo '<td class="d-none">'.$row["moneda"]." ".number_format($row["monto_acumulado_desacopios"],2)."</td>";
                            echo '<td class="d-none">'.$row["moneda"]." ".number_format($row["monto_acumulado_descuentos"],2)."</td>";
                            echo '<td class="d-none">'.$row["moneda"]." ".number_format($row["monto_acumulado_ajustes"],2)."</td>";
                            echo '<td>'. $row["observaciones"] . '</td>';
                              if ($row["aprobado_cliente"] == 0) {
                                echo '<td>No</td>';	
                              } else {
                                echo '<td>Si</td>';	
                              }
                            echo '</tr>';?>

                          <?php
                          }
                          Database::disconnect();?>
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
                    <h5>Detalle del Certificado de Avance
                      &nbsp;
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <div id="contenedor_detalle_avance">
                        <p class="text-muted">Seleccione un certificado de avance para ver el detalle</p>
                      </div>
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
                    <h5>Ajustes del Certificado de Avance seleccionado
                      &nbsp;
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered" id="tablaAjustesCA">
                        <thead>
                          <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Impacto</th>
                            <th class="text-right <?= $esOpCA ? 'd-none' : '' ?>">Monto</th>
                            <th>Observaciones</th>
                            <th>Usuario</th>
                          </tr>
                        </thead>
                        <tbody id="body_ajustes_ca">
                          <tr><td colspan="<?= $esOpCA ? '5' : '6' ?>" class="text-muted">Seleccione un certificado de avance para ver sus ajustes.</td></tr>
                        </tbody>
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

    <div class="modal fade" id="modalAprobarAvance" tabindex="-1" role="dialog" aria-labelledby="modalAprobarAvanceLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalAprobarAvanceLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea aprobar el certificado de avance?</div>
          <div class="modal-footer">
            <a href="#" class="btn btn-primary" id="btnAprobarAvance">Aprobar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-labelledby="modalEliminarLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEliminarLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea eliminar el certificado de avance?</div>
          <div class="modal-footer">
            <a href="#" class="btn btn-primary" id="btnEliminarAvance">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalRevision" tabindex="-1" role="dialog" aria-labelledby="modalRevisionLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form class="modal-content" id="formRevisionCA" method="post">
          <div class="modal-header">
            <h5 class="modal-title" id="modalRevisionLabel">Nueva Revisión</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body">
            <p>¿Está seguro que desea generar una nueva revisión? La actual quedará como histórico y la nueva comenzará pendiente de aprobación.</p>
            <input type="hidden" name="id" id="revision_id_ca" value="">
            <div class="form-group">
              <label for="motivoRevisionCA">Motivo de la revisión:</label>
              <textarea id="motivoRevisionCA" name="motivoRevision" class="form-control" required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Generar</button>
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          </div>
        </form>
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
      const esOpCAJs = <?php echo $esOpCA ? 'true' : 'false'; ?>;
      $(document).ready(function() {

        // Setup - add a text input to each footer cell
        $('#tablaOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $('#tablaOCC').DataTable({
          stateSave: false,
          responsive: false,
          columnDefs: [
            { targets: [0,8,9,10,11,12], visible: false, searchable: false },
            { targets: [6,7], visible: !esOpCAJs }
          ],
		  dom: 'Bfrtp<"bottom"l>',
			buttons: [
				'excel'
			],
			lengthMenu: [
			[10, 25, 50, 100, 500, 1000],
			[10, 25, 50, 100, 500, 1000]
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
        
        $(document).on("click","#tablaOCC tbody tr td", function(){
          var t=$(this).parent();

          let id_occ=t.attr("data-id");
          let aprobado = t.attr("data-aprobado");
          let esUltima = t.attr("data-es-ultima");

          if (aprobado == "1") {
            $("#link_nuevo_comprobante").data("aprobado","1");  
          } else {
            $("#link_nuevo_comprobante").data("aprobado","0");  
          }
           if(t.hasClass('selected')){
             deselectRow(t);
             get_detalle_certificado_avance(0)
             get_ajustes_certificado_avance(0)

             $("#link_exportar_certificado").attr("href","#");
            $("#link_imprimir_certificado").attr("href","#");
             $("#link_modificar_ot").attr("href","#");
		        $("#link_aprobar_avance").attr("href","#");
            $("#link_ver_occ").attr("href","#");
            $("#link_nuevo_ajuste").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
             selectRow(t);
             get_detalle_certificado_avance(id_occ)
             get_ajustes_certificado_avance(id_occ)

            $("#link_exportar_certificado").attr("href","exportCertificadosAvances.php?id_certificado_avance="+id_occ+"&id_certificado_maestro="+<?= (int) $_GET["id_certificado_maestro"] ?>);
            $("#link_imprimir_certificado").attr("href","imprimirCertificadosAvances.php?id_certificado_avance="+id_occ+"&id_certificado_maestro="+<?= (int) $_GET["id_certificado_maestro"] ?>);
            $("#link_modificar_ot").attr("href", aprobado === "1" ? "#" : (esUltima === "1" ? "modificarCertificadoAvance.php?id="+id_occ : "#"));
            $("#link_aprobar_avance").attr("href", esUltima === "1" ? "aprobarCertificadoAvance.php?id="+id_occ : "#");
            $("#link_ver_occ").attr("href","verCertificadoAvance.php?id="+id_occ);
            $("#link_nuevo_ajuste").attr("href", esUltima === "1" ? "nuevoAjusteCertificadoAvance.php?id_certificado_avance="+id_occ : "#");
          }
        });

         get_detalle_certificado_avance(0)
         get_ajustes_certificado_avance(0)

         $("#link_exportar_certificado").on("click",function(e){
           if (this.getAttribute("href") === "#") {
             e.preventDefault();
             alert("Por favor seleccione un certificado de avance para exportar")
           }
         })

         $("#link_imprimir_certificado").on("click",function(e){
           if (this.getAttribute("href") === "#") {
             e.preventDefault();
             alert("Por favor seleccione un certificado de avance para imprimir");
           }
         })

        $("#link_modificar_ot").on("click",function(e){
          let fila_selected=$(document).find("#tablaOCC tbody tr.selected");
          if(fila_selected.length==0){
            e.preventDefault();
            alert("Por favor seleccione un certificado de avance para modificarlo")
          }else if(fila_selected.attr("data-aprobado") === "1"){
            e.preventDefault();
            if (fila_selected.attr("data-es-ultima") !== "1") {
              alert("Solo la última revisión del certificado permite generar una nueva revisión.");
              return;
            }
            $("#revision_id_ca").val(fila_selected.attr("data-id"));
            $("#motivoRevisionCA").val("");
            $("#formRevisionCA").attr("action","nuevaRevisionCertificadoAvance.php?id="+fila_selected.attr("data-id"));
            $("#modalRevision").modal("show");
          }else if(this.getAttribute("href") === "#"){
            e.preventDefault();
            alert("Solo la ultima revision del certificado puede modificarse")
          }
        })
		
        $("#link_aprobar_avance").on("click",function(e){
          e.preventDefault();
          let fila_selected=$(document).find("#tablaOCC tbody tr.selected");
          if(fila_selected.length==0){
            alert("Por favor seleccione un certificado para aprobar")
          }else if(fila_selected.attr("data-es-ultima") !== "1"){
            alert("Solo la ultima revision del certificado puede aprobarse")
          }else{
            let aprobado=fila_selected.attr("data-aprobado");
            let id=fila_selected.attr("data-id");
            if(aprobado=="1"){
              alert("El certificado ya esta aprobado")
            }else{
              $("#btnAprobarAvance").attr("href","aprobarCertificadoAvance.php?id="+id);
              $("#modalAprobarAvance").modal("show");
            }
          }
        })

        $("#link_eliminar_avance").on("click",function(){
          let l=document.location.href;
          let fila_selected=$(document).find("#tablaOCC tbody tr.selected");

          //if(this.href==l || this.href==l+"#"){
          if(fila_selected.length==0){
            alert("Por favor seleccione un certificado para eliminarlo")
          }else{
            let aprobado=fila_selected.attr("data-aprobado");
            let id=fila_selected.attr("data-id");
            if(aprobado=="1"){
              alert("El certificado ya esta aprobado y no puede ser eliminado")
            }else if(fila_selected.attr("data-es-ultima") !== "1"){
              alert("Solo la ultima revision del certificado puede eliminarse")
            }else{
              $("#btnEliminarAvance").attr("href","eliminarCertificadoAvance.php?id="+id);
              $("#modalEliminar").modal("show");
            }
          }
        })

        $("#link_ver_occ").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un certificado para ver detalle")
          }
        })

        $("#link_nuevo_ajuste").on("click",function(e){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            e.preventDefault();
            alert("Por favor seleccione un certificado para cargar un ajuste")
          }
        })

        $("#link_nuevo_comprobante").on("click",function(){
          let aprobado = $("#link_nuevo_comprobante").data("aprobado");  
          console.log(aprobado);
          if (aprobado == "1") {
            
            let aId=[];
            $("#tablaDetalleOCC tbody tr").each(function(){
              var t=$(this);
              if(t.hasClass('selected')){
                let id_certificado_avance_detalle=t.find("td:first-child").html();
                aId.push(id_certificado_avance_detalle)
              }
            })
            if(aId.length==0){
              alert("Por favor seleccione uno o mas items del certificado de avance para generar un comprobante")
            }else{
              window.open("nuevaFacturaVenta.php?id="+aId.join(","))
            }
          } else {
            alert("El Certificado de Avance aún no ha sido aprobado por el cliente")
          }

        })
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    
      function formatearNumeroAvance(valor) {
        return valor.toLocaleString('es-AR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function obtenerNumeroAvance(valor) {
        valor = String(valor || '').trim().replace(',', '.');
        let numero = parseFloat(valor);
        return isNaN(numero) ? 0 : numero;
      }

      function renderGruposAvanceListado(grupos, moneda) {
        let html = '';
        for (let clave_grupo in grupos) {
          let grupo = grupos[clave_grupo];
          let modo = grupo.modo_generacion || 'legacy';
          let total_anterior_grupo = 0.0;
          let total_actual_grupo = 0.0;
          let total_acumulado_grupo = 0.0;
          let total_saldo_grupo = 0.0;
          let total_cm_grupo = 0.0;
          
          for (let i = 0; i < grupo.filas.length; i++) {
            let fila_total = grupo.filas[i];
            let cantidad_actual_total = obtenerNumeroAvance(fila_total.cantidad_actual || 0);
            let cantidad_acumulada_total = obtenerNumeroAvance(fila_total.acumulado || 0);
            let cantidad_anterior_total = Math.max(0, cantidad_acumulada_total - cantidad_actual_total);
            let precio_unitario_total = obtenerNumeroAvance(fila_total.precio_unitario_cm || 0);
            let subtotal_cm_total = obtenerNumeroAvance(fila_total.subtotal_cm || 0);
            total_anterior_grupo += cantidad_anterior_total * precio_unitario_total;
            total_actual_grupo += cantidad_actual_total * precio_unitario_total;
            total_acumulado_grupo += (cantidad_anterior_total + cantidad_actual_total) * precio_unitario_total;
            total_cm_grupo += subtotal_cm_total;
            total_saldo_grupo += subtotal_cm_total - ((cantidad_anterior_total + cantidad_actual_total) * precio_unitario_total);
          }

          html += '<div class="occ-group-aperturado-wrap border rounded px-2 py-2">';
          html += '<div class="table-responsive">';
          html += '<table class="table table-sm table-bordered mb-0 occ-breakdown-table">';
          html += '<thead>';
          html += '<tr>';
          html += '<th rowspan="2">Descripcion</th>';
          html += '<th rowspan="2">Unidad</th>';
          html += '<th rowspan="2" class="text-right">Cantidad</th>';
          html += '<th rowspan="2" class="text-right">Incidencia</th>';
          if (!esOpCAJs) {
            html += '<th rowspan="2" class="text-right">Precio unitario</th>';
            html += '<th rowspan="2" class="text-right">Total CM</th>';
          }
          html += '<th colspan="' + (esOpCAJs ? 2 : 3) + '" class="text-center avance-periodo avance-periodo-anterior">Anterior</th>';
          html += '<th colspan="' + (esOpCAJs ? 2 : 3) + '" class="text-center avance-periodo avance-periodo-actual">Actual</th>';
          html += '<th colspan="' + (esOpCAJs ? 2 : 3) + '" class="text-center avance-periodo avance-periodo-acumulado">Acumulado</th>';
          html += '<th colspan="' + (esOpCAJs ? 2 : 3) + '" class="text-center avance-periodo avance-periodo-saldo">Saldo</th>';
          html += '</tr>';
          html += '<tr>';
          html += '<th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>';
          html += '<th class="text-center avance-porcentaje-col">%</th>';
          if (!esOpCAJs) html += '<th class="text-right">Monto</th>';
          html += '<th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>';
          html += '<th class="text-center avance-porcentaje-col">%</th>';
          if (!esOpCAJs) html += '<th class="text-right">Monto</th>';
          html += '<th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>';
          html += '<th class="text-center avance-porcentaje-col">%</th>';
          if (!esOpCAJs) html += '<th class="text-right">Monto</th>';
          html += '<th class="text-right avance-col-inicio avance-cantidad-col">Cantidad</th>';
          html += '<th class="text-center avance-porcentaje-col">%</th>';
          if (!esOpCAJs) html += '<th class="text-right">Monto</th>';
          html += '</tr>';
          html += '</thead>';
          html += '<tbody>';

          for (let i = 0; i < grupo.filas.length; i++) {
            let fila = grupo.filas[i];
            let cantidad = obtenerNumeroAvance(fila.cantidad || 0);
            let cantidad_acumulada = obtenerNumeroAvance(fila.acumulado || 0);
            let cantidad_actual = obtenerNumeroAvance(fila.cantidad_actual || 0);
            let cantidad_anterior = Math.max(0, cantidad_acumulada - cantidad_actual);
            cantidad_acumulada = cantidad_anterior + cantidad_actual;
            let maximo_avance = Math.max(0, cantidad - cantidad_anterior);
            let porcentaje_anterior = cantidad > 0 ? (cantidad_anterior / cantidad) * 100 : 0;
            let porcentaje_actual = cantidad > 0 ? (cantidad_actual / cantidad) * 100 : 0;
            let porcentaje_acumulado = cantidad > 0 ? (cantidad_acumulada / cantidad) * 100 : 0;
            let monto_anterior = cantidad_anterior * obtenerNumeroAvance(fila.precio_unitario_cm);
            let monto_actual = cantidad_actual * obtenerNumeroAvance(fila.precio_unitario_cm);
            let monto_acumulado = cantidad_acumulada * obtenerNumeroAvance(fila.precio_unitario_cm);
            let saldo_cantidad = Math.max(0, cantidad - cantidad_acumulada);
            let porcentaje_saldo = cantidad > 0 ? (saldo_cantidad / cantidad) * 100 : 0;
            let subtotal_cm_fila = obtenerNumeroAvance(fila.subtotal_cm || 0);
            let saldo_monto = subtotal_cm_fila - (cantidad_acumulada * obtenerNumeroAvance(fila.precio_unitario_cm));
            let incidencia = fila.incidencia_porcentaje;

            html += '<tr>';
            html += '<td>' + (fila.descripcion || '') + '</td>';
            html += '<td>' + (fila.unidad_medida || '') + '</td>';
            html += '<td class="text-right">' + formatearNumeroAvance(cantidad) + '</td>';
            html += '<td class="text-right">' + (incidencia !== null ? formatearNumeroAvance(parseFloat(incidencia)) + '%' : '-') + '</td>';
            if (!esOpCAJs) {
              html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(obtenerNumeroAvance(fila.precio_unitario_cm)) + '</td>';
              html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(subtotal_cm_fila) + '</td>';
            }
            html += '<td class="text-right avance-col-inicio avance-cantidad-col">' + formatearNumeroAvance(cantidad_anterior) + '</td>';
            html += '<td class="text-center avance-porcentaje-col">' + formatearNumeroAvance(porcentaje_anterior) + '%</td>';
            if (!esOpCAJs) html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(monto_anterior) + '</td>';
            html += '<td class="text-right avance-col-inicio avance-cantidad-col">' + formatearNumeroAvance(cantidad_actual) + '</td>';
            html += '<td class="text-center avance-porcentaje-col">' + formatearNumeroAvance(porcentaje_actual) + '%</td>';
            if (!esOpCAJs) html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(monto_actual) + '</td>';
            html += '<td class="text-right avance-col-inicio avance-cantidad-col">' + formatearNumeroAvance(cantidad_acumulada) + '</td>';
            html += '<td class="text-center avance-porcentaje-col">' + formatearNumeroAvance(porcentaje_acumulado) + '%</td>';
            if (!esOpCAJs) html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(monto_acumulado) + '</td>';
            html += '<td class="text-right avance-col-inicio avance-cantidad-col">' + formatearNumeroAvance(saldo_cantidad) + '</td>';
            html += '<td class="text-center avance-porcentaje-col">' + formatearNumeroAvance(porcentaje_saldo) + '%</td>';
            if (!esOpCAJs) html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(saldo_monto) + '</td>';
            html += '</tr>';
          }

          html += '</tbody>';
          html += '<tfoot class="bg-light">';
          html += '<tr class="font-weight-bold">';
          html += '<td colspan="' + (esOpCAJs ? 4 : 6) + '" class="text-right">Totales del grupo</td>';
          html += '<td class="text-right avance-col-inicio">Anterior</td>';
          html += '<td class="text-center avance-porcentaje-col"></td>';
          if (!esOpCAJs) html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(total_anterior_grupo) + '</td>';
          html += '<td class="text-right avance-col-inicio">Actual</td>';
          html += '<td class="text-center avance-porcentaje-col"></td>';
          if (!esOpCAJs) html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(total_actual_grupo) + '</td>';
          html += '<td class="text-right avance-col-inicio">Acumulado</td>';
          html += '<td class="text-center avance-porcentaje-col"></td>';
          if (!esOpCAJs) html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(total_acumulado_grupo) + '</td>';
          html += '<td class="text-right avance-col-inicio">Saldo</td>';
          html += '<td class="text-center avance-porcentaje-col"></td>';
          if (!esOpCAJs) html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(total_saldo_grupo) + '</td>';
          html += '</tr>';
          html += '</tfoot>';
          html += '</table>';
          html += '</div>';
          html += '</div>';
        }
        return html;
      }

      function get_detalle_certificado_avance(id_certificado_avance){
        let datosUpdate = new FormData();
        datosUpdate.append('id_certificado_avance', id_certificado_avance);
        $.ajax({
          data: datosUpdate,
          url: 'get_detalle_certificado_avance.php',
          method: "post",
          cache: false,
          contentType: false,
          processData: false,
          success: function(data){
            try {
              data = JSON.parse(data);
            } catch (e) {
              console.error('Error al parsear JSON:', e);
              $('#contenedor_detalle_avance').html('<p class="text-danger">Error al cargar los datos</p>');
              return;
            }

            if (!id_certificado_avance || id_certificado_avance == 0) {
              $('#contenedor_detalle_avance').html('<p class="text-muted">Seleccione un certificado de avance para ver el detalle</p>');
              return;
            }

            let html = '';
            let moneda = data.moneda || 'U$S';
            let occ_detalles = data.occ_detalles || [];
            let grupos_por_occ = data.grupos_por_occ || {};
            let grupos_legacy = data.grupos_legacy || {};
            let orden_grupos_agrupados = data.orden_grupos_agrupados || {};

            // Renderizar OCC detalles con sus grupos
            if (occ_detalles.length > 0) {
              html += '<div class="table-responsive">';
              html += '<table class="table table-sm table-bordered" id="tabla_occ_listado" style="width:100%">';
              html += '<thead>';
              html += '<tr>';
              html += '<th>ID</th>';
              html += '<th>Posición</th>';
              html += '<th>Descripcion</th>';
              html += '<th class="text-right">Cantidad</th>';
              if (!esOpCAJs) {
                html += '<th class="text-right">Precio unitario</th>';
                html += '<th class="text-right">Descuento</th>';
                html += '<th class="text-right">Subtotal</th>';
              }
              html += '</tr>';
              html += '</thead>';
              html += '<tbody>';

              for (let i = 0; i < occ_detalles.length; i++) {
                let occ_row = occ_detalles[i];
                let id_occ_fila = parseInt(occ_row.id);
                let grupos_fila = grupos_por_occ[id_occ_fila] || {};
                let meta_agrupado = orden_grupos_agrupados[id_occ_fila] || null;
                let clases_occ = [];

                if (meta_agrupado) {
                  clases_occ.push('occ-grouped-member');
                  if (meta_agrupado.cantidad === 1) {
                    clases_occ.push('occ-grouped-single');
                  } else if (meta_agrupado.posicion === 0) {
                    clases_occ.push('occ-grouped-start');
                  } else if (meta_agrupado.posicion === meta_agrupado.cantidad - 1) {
                    clases_occ.push('occ-grouped-end');
                  } else {
                    clases_occ.push('occ-grouped-middle');
                  }
                }

                html += '<tr class="occ-item-row ' + clases_occ.join(' ') + '">';
                html += '<td>' + id_occ_fila + '</td>';
                html += '<td>' + (occ_row.posicion || '') + '</td>';
                html += '<td>' + (occ_row.descripcion || '') + '</td>';
                html += '<td class="text-right">' + formatearNumeroAvance(obtenerNumeroAvance(occ_row.cantidad)) + '</td>';
                if (!esOpCAJs) {
                  html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(obtenerNumeroAvance(occ_row.precio_unitario)) + '</td>';
                  html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(obtenerNumeroAvance(occ_row.descuento)) + '</td>';
                  html += '<td class="text-right">' + moneda + ' ' + formatearNumeroAvance(obtenerNumeroAvance(occ_row.subtotal)) + '</td>';
                }
                html += '</tr>';

                // Renderizar grupos si los hay
                if (Object.keys(grupos_fila).length > 0) {
                  html += '<tr class="occ-breakdown-row">';
                  html += '<td colspan="' + (esOpCAJs ? 4 : 7) + '">';
                  html += renderGruposAvanceListado(grupos_fila, moneda);
                  html += '</td>';
                  html += '</tr>';
                }
              }

              html += '</tbody>';
              html += '</table>';
              html += '</div>';
            }

            // Renderizar grupos legacy si los hay
            if (Object.keys(grupos_legacy).length > 0) {
              html += '<div class="alert alert-warning mt-4 mb-2">';
              html += 'Estos detalles son registros legacy sin trazabilidad hacia un item OCC. Se mantienen disponibles para cargar el avance.';
              html += '</div>';
              html += '<div class="border rounded p-3 legacy-section">';
              html += '<h6 class="font-weight-bold mb-3">Detalles legacy sin trazabilidad OCC</h6>';
              html += renderGruposAvanceListado(grupos_legacy, moneda);
              html += '</div>';
            }

            if (!html) {
              html = '<p class="text-muted">No hay detalles para este certificado de avance</p>';
            }

            $('#contenedor_detalle_avance').html(html);
          },
          error: function(xhr, status, error) {
            console.error('Error en AJAX:', status, error);
            $('#contenedor_detalle_avance').html('<p class="text-danger">Error al cargar el detalle del certificado</p>');
          }
        });
      }

      function get_ajustes_certificado_avance(id_certificado_avance){
        const body = $("#body_ajustes_ca");
        const colAjustes = esOpCAJs ? 5 : 6;
        const tabla = $('#tablaAjustesCA');
        if ($.fn.DataTable.isDataTable(tabla)) {
          tabla.DataTable().clear().destroy();
        }
        body.html('<tr><td colspan="'+colAjustes+'" class="text-muted">Cargando ajustes...</td></tr>');
        $.ajax({
          url: 'get_ajustes_certificado_avance.php',
          method: 'post',
          data: { id_certificado_avance: id_certificado_avance },
          dataType: 'json'
        }).done(function(ajustes){
          if (!Array.isArray(ajustes)) { ajustes = []; }
          const columnas = esOpCAJs ? [
            { title: 'Fecha' }, { title: 'Tipo' }, { title: 'Impacto' },
            { title: 'Monto', className: 'd-none' }, { title: 'Observaciones' }, { title: 'Usuario' }
          ] : [
            { title: 'Fecha' }, { title: 'Tipo' }, { title: 'Impacto' },
            { title: 'Monto', className: 'text-right' }, { title: 'Observaciones' }, { title: 'Usuario' }
          ];
          tabla.DataTable({
            data: ajustes,
            columns: columnas,
            stateSave: false,
            responsive: false,
            searching: false,
            paging: true,
            pageLength: 10,
            language: {
              emptyTable: 'Sin ajustes registrados para este certificado.',
              info: 'Mostrando _START_ a _END_ de _TOTAL_ ajustes',
              infoEmpty: 'Mostrando 0 ajustes',
              lengthMenu: 'Mostrar _MENU_ ajustes',
              paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
            }
          });
        }).fail(function(xhr, status, error){
          console.error('Error al cargar ajustes:', status, error, xhr && xhr.responseText);
          body.html('<tr><td colspan="'+colAjustes+'" class="text-danger">No se pudieron cargar los ajustes.</td></tr>');
        });
      }
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>