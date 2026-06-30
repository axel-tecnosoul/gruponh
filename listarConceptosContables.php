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
                    <a href="nuevaConceptoContable.php" title="Nuevo Concepto"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo"></a>
                    &nbsp;&nbsp;
                    <a href="#" id="link_modificar_concepto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>
                    &nbsp;&nbsp;
                    <a href="#" id="link_eliminar_concepto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>
                    &nbsp;&nbsp;
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

  <?php
  $pdo = Database::connect();
  $sql = " SELECT cc.`id`, cc.`codigo`, cc.`descripcion` FROM `conceptos_contables` cc ORDER BY cc.`codigo` ";
  foreach ($pdo->query($sql) as $row) {
  ?>
  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
          <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body">¿Está seguro que desea eliminar el concepto <strong><?= htmlspecialchars($row[1] . ' - ' . $row[2]) ?></strong>?</div>
        <div class="modal-footer">
          <a href="eliminarConceptoContable.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
          <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
        </div>
      </div>
    </div>
  </div>
  <?php
  }
  Database::disconnect();
  ?>

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

      $("#link_modificar_concepto").on("click", function() {
        let l = document.location.href;
        if (this.href == l || this.href == l + "#") {
          alert("Por favor seleccione un concepto contable para modificar")
        }
      })

      $("#link_eliminar_concepto").on("click", function() {
        let target = this.dataset.target;
        if (target == undefined || target == "#") {
          alert("Por favor seleccione un concepto contable para eliminar")
        }
      })

      $(document).on("click", "#dataTables-example666 tbody tr td", function() {
        var t = $(this).parent();
        let id_concepto = t.find("td:first-child").html();
        if (t.hasClass('selected')) {
          deselectRow(t);
          $("#link_modificar_concepto").attr("href", "#");
          $("#link_eliminar_concepto").attr("data-target", "#");
        } else {
          table.rows().nodes().each(function(rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_modificar_concepto").attr("href", "nuevaConceptoContable.php?id=" + id_concepto);
          $("#link_eliminar_concepto").attr("data-toggle", "modal");
          $("#link_eliminar_concepto").attr("data-target", "#eliminarModal_" + id_concepto);
        }
      });

    });

    function selectRow(t) { t.addClass('selected'); }
    function deselectRow(t) { t.removeClass('selected'); }
  </script>
  <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
</body>

</html>
