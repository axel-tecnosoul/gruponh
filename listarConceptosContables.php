<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
include 'database.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include('head_tables.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <style>
    .truncate {
      max-width: 50px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  </style>
</head>

<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body"><?php $ubicacion = "Conceptos Contables "; include_once("head_page.php") ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5><?php echo $ubicacion; ?>
                    <a href="importConceptosContables.php" title="Importar Excel"><img src="img/xls.png" width="24" height="25" border="0" alt="Importar Excel" title="Importar Excel"></a>
                  </h5>
                </div>
                <div class="card-body">
                  <div class="dt-ext table-responsive">
                    <table class="display truncate" id="dataTables-example666">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Código</th>
                          <th>Descripción</th>
                          <th>Alícuota IVA</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $pdo = Database::connect();
                        $sql = " SELECT cc.`id`, cc.`codigo`, cc.`descripcion`, COALESCE(ti.`tasa`, '') 
                                 FROM `conceptos_contables` cc 
                                 LEFT JOIN `tipos_iva` ti ON ti.id = cc.`id_alicuota_iva` 
                                 WHERE cc.`anulado` = 0 
                                 ORDER BY cc.`codigo` ";

                        foreach ($pdo->query($sql) as $row) {
                          echo '<tr>';
                          echo '<td>' . $row[0] . '</td>';
                          echo '<td>' . htmlspecialchars($row[1]) . '</td>';
                          echo '<td>' . htmlspecialchars($row[2]) . '</td>';
                          echo '<td>' . ($row[3] !== '' ? $row[3] . '%' : '—') . '</td>';
                          echo '</tr>';
                        }
                        Database::disconnect();
                        ?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th>ID</th>
                          <th>Código</th>
                          <th>Descripción</th>
                          <th>Alícuota IVA</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php include("footer.php"); ?>
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
  <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function() {
      $('#dataTables-example666 tfoot th').each(function() {
        var title = $(this).text();
        $(this).html('<input type="text" size="' + title.length + '" placeholder="' + title + '" />');
      });
      $('#dataTables-example666').DataTable({
        stateSave: false,
        responsive: false,
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

      var table = $('#dataTables-example666').DataTable();

      table.columns().every(function() {
        var that = this;
        $('input', this.footer()).on('keyup change', function() {
          if (that.search() !== this.value) {
            that.search(this.value).draw();
          }
        });
    });
    });
  </script>
  <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
</body>

</html>
