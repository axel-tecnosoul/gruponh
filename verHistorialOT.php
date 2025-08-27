<?php
require("config.php");
require 'database.php';

$id = null;
if (!empty($_GET['id_detalle_ot'])) {
  $id = $_REQUEST['id_detalle_ot'];
}

if ($id === null) {
  header("Location: listarOrdenesTrabajo.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlInfo = "SELECT otd.cantidad, otd.cant_liberadas, otd.cant_reproceso, otd.cant_rechazadas,
                  lcp.posicion, m.concepto, ot.nro_orden_trabajo, lc.numero AS numero_lc,
                  lc.nro_revision, lc.id_proyecto
            FROM ordenes_trabajo_detalle otd
            JOIN lista_corte_posiciones lcp ON otd.id_posicion = lcp.id
            JOIN materiales m ON lcp.id_material = m.id
            JOIN ordenes_trabajo ot ON otd.id_orden_trabajo = ot.id
            JOIN listas_corte lc ON ot.id_lista_corte = lc.id
            WHERE otd.id = ?";
$qInfo = $pdo->prepare($sqlInfo);
$qInfo->execute([$id]);
$dataPos = $qInfo->fetch(PDO::FETCH_ASSOC);
$descProyecto = getDescripcionProyecto($pdo, $dataPos['id_proyecto']);
$infoOT = 'OT N° ' . $dataPos['nro_orden_trabajo'] . ' - LC N° ' . $dataPos['numero_lc'] .
          ' - Rev ' . $dataPos['nro_revision'] . ' ' . htmlspecialchars($descProyecto);

if (!empty($_POST)) {

}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <link rel="stylesheet" type="text/css" href="assets/css/mapsjs-ui.css">
    <style>
      /* Define el tamaño del contenedor del mapa */
      #mapContainer {
          width: 100%;
          height: 500px;
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
            $ubicacion="Ver Historial";
            include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion.' '.$infoOT?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-sm-12">
                              <div class="row mb-3">
                                <div class="col-md-4"><strong>Posición:</strong> <?=$dataPos['posicion']?></div>
                                <div class="col-md-4"><strong>Material:</strong> <?=$dataPos['concepto']?></div>
                                <div class="col-md-4"><strong>Cantidad:</strong> <?=$dataPos['cantidad']?></div>
                              </div>
                              <div class="row mb-3">
                                <div class="col-md-4"><strong>Liberadas:</strong> <?=$dataPos['cant_liberadas']?></div>
                                <div class="col-md-4"><strong>A Reprocesar:</strong> <?=$dataPos['cant_reproceso']?></div>
                                <div class="col-md-4"><strong>Rechazadas:</strong> <?=$dataPos['cant_rechazadas']?></div>
                              </div>
                              <table class="display" id="dataTables-example668">
                                <thead>
                                  <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Liberadas</th>
                                    <th>A Reprocesar</th>
                                    <th>Rechazadas</th>
                                    <th>Motivo</th>
                                    <th>Fecha y hora de alta</th>
                                  </tr>
                                </thead>
                                <tbody><?php
                                  $sql = "SELECT d.cantidad_liberada, d.cantidad_reproceso, d.cantidad_rechazada, d.motivo,
                                                  DATE_FORMAT(d.fecha,'%d/%m/%y') AS fecha, u.usuario, DATE_FORMAT(d.fecha_hora_alta,'%d/%m/%y %H:%i') AS fecha_hora_alta
                                          FROM ordenes_trabajo_detalle_log d
                                          INNER JOIN usuarios u ON u.id = d.id_usuario
                                          WHERE d.id_ordenes_trabajo_detalle = ? ORDER BY d.id DESC";
                                  $q = $pdo->prepare($sql);
                                  $q->execute([$id]);
                                  foreach ($q as $row) {
                                    echo '<tr>';
                                    echo '<td>'. $row['fecha'] . '</td>';
                                    echo '<td>'. $row['usuario'] . '</td>';
                                    echo '<td>'. $row['cantidad_liberada'] . '</td>';
                                    echo '<td>'. $row['cantidad_reproceso'] . '</td>';
                                    echo '<td>'. $row['cantidad_rechazada'] . '</td>';
                                    echo '<td>'. $row['motivo'] . '</td>';
                                    echo '<td>'. $row['fecha_hora_alta'] . '</td>';
                                    echo '</tr>';
                                  }
                                  Database::disconnect();?>
                                </tbody>
                                <tfoot>
                                  <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Liberadas</th>
                                    <th>A Reprocesar</th>
                                    <th>Rechazadas</th>
                                    <th>Motivo</th>
                                  </tr>
                                </tfoot>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						            <a href='listarOrdenesTrabajo.php' class="btn btn-light">Volver</a>
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
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
	  <script src="https://js.api.here.com/v3/3.1/mapsjs-core.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-service.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-ui.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js"></script>
    
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	  <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
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
	  <script>
      $(document).ready(function() {
	
        // Setup - add a text input to each footer cell
        $('#dataTables-example668 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $('#dataTables-example668').DataTable({
          stateSave: false,
          responsive: false,
          "dom": 'rtip',
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
        var table = $('#dataTables-example668').DataTable();
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });
      });
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>