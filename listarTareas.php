<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';

$nro           = isset($_POST['nro'])           ? $_POST['nro']           : "";
$id_tipo_tarea = isset($_POST['id_tipo_tarea']) ? $_POST['id_tipo_tarea'] : "";
$completada    = isset($_POST['completada'])    ? $_POST['completada']    : "";
$orden         = isset($_POST['orden'])         ? $_POST['orden']         : "t.id asc";
$submitted     = isset($_POST['buscar'])        ? "1"                     : "0";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include('head_tables.php'); ?>
  <link rel="stylesheet" href="assets/css/colResize.css">
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
  <!-- page-wrapper Start-->
  <div class="page-wrapper">
    <!-- Page Header Start-->
    <?php include('header.php'); ?>
    <!-- Page Header Ends-->
    <!-- Page Body Start-->
    <div class="page-body-wrapper">
      <!-- Page Sidebar Start-->
      <?php include('menu.php'); ?>
      <!-- Page Sidebar Ends-->
      <div class="page-body"><?php
                              $ubicacion = "Tareas ";
                              include_once("head_page.php") ?>
        <!-- Container-fluid starts-->
        <div class="container-fluid">
          <div class="row">
            <div class="col-md-12">
              <div class="card">
                <div class="card-body">
                  <form class="form-inline theme-form mt-3" name="form1" method="post" action="listarTareas.php">
                    <div class="form-group mb-0">
                      N.Sitio-N.Sub-N.Proy:&nbsp;
                      <input class="form-control" size="8" type="text"
                        value="<?php echo htmlspecialchars($nro); ?>"
                        name="nro"
                        placeholder="Ej: 10 ó 10-5 ó 10-2-5">
                    </div>
                    <div class="form-group mb-0">
                      Tipo:&nbsp;
                      <select name="id_tipo_tarea" id="id_tipo_tarea" class="form-control">
                        <option value="">Seleccione...</option>
                        <?php
                        $pdo = Database::connect();
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $sqlZon = "SELECT `id`, `tipo` FROM `tipos_tarea` WHERE 1 ORDER BY tipo";
                        $q = $pdo->prepare($sqlZon);
                        $q->execute();
                        while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                          echo "<option value='" . $fila['id'] . "'";
                          if ($fila['id'] == $id_tipo_tarea) {
                            echo " selected";
                          }
                          echo ">" . htmlspecialchars($fila['tipo']) . "</option>";
                        }
                        Database::disconnect();
                        ?>
                      </select>
                    </div>
                    <div class="form-group mb-0">
                      Completada:&nbsp;
                      <select name="completada" id="completada" class="form-control">
                        <option value="">Seleccione...</option>
                        <option value="1" <?php if ($completada === '1') echo "selected"; ?>>Si</option>
                        <option value="0" <?php if ($completada === '0') echo "selected"; ?>>No</option>
                      </select>
                    </div>
                    <div class="form-group mb-0">
                      Orden:&nbsp;
                      <select name="orden" id="orden" class="form-control">
                        <option value="t.id asc" <?php if ($orden == "t.id asc")    echo "selected"; ?>>Fecha Creación Asc</option>
                        <option value="t.id desc" <?php if ($orden == "t.id desc")   echo "selected"; ?>>Fecha Creación Desc</option>
                        <option value="tt.tipo asc" <?php if ($orden == "tt.tipo asc") echo "selected"; ?>>Tipo Tarea Asc</option>
                        <option value="tt.tipo desc" <?php if ($orden == "tt.tipo desc") echo "selected"; ?>>Tipo Tarea Desc</option>
                      </select>
                    </div>
                    <div class="form-group mb-0">
                      <button type="submit" class="btn btn-primary">Buscar</button>
                      <input type="hidden" name="buscar" value="1">
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
                      if (!empty(tienePermiso(281))) { ?>
                      <a href="nuevaTareaTarea.php">
                        <img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva">
                      </a><?php
                        } ?>
                    &nbsp;
                    <a href="exportTareas.php">
                      <img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar">
                    </a>
                    &nbsp;&nbsp;
                    <?php
                    echo '<a href="#" id="link_ver_tarea"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                    echo '&nbsp;&nbsp;';
                    if (!empty(tienePermiso(290))) {
                      echo '<a href="#" id="link_nuevo_computo"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo Cómputo / LC / Packing" title="Nuevo Cómputo / LC / Packing"></a>';
                      echo '&nbsp;&nbsp;';
                    }
                    if (!empty(tienePermiso(282))) {
                      echo '<a href="#" id="link_modificar_tarea"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                      echo '&nbsp;&nbsp;';
                    }
                    if (!empty(tienePermiso(283))) {
                      echo '<a href="#" id="link_eliminar_tarea"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                      echo '&nbsp;&nbsp;';
                    }
                    ?>
                  </h5>
                </div>
                <div class="card-body">
                  <div class="dt-ext table-responsive">
                    <table class="display truncate" id="dataTables-example666">
                      <thead>
                        <tr>
                          <th class="d-none">ID</th>
                          <th>Sitio</th>
                          <th>Subsitio</th>
                          <th>Nro.Proy</th>
                          <th>Proyecto</th>
                          <th>Estructura</th>
                          <th>Sector</th>
                          <th>Tarea</th>
                          <th>Recurso</th>
                          <th>Coordinador</th>
                          <th>Observaciones</th>
                          <th>FIP</th>
                          <th>FFP</th>
                          <th>FIR</th>
                          <th>FFR</th>
                          <th>Completada</th>
                          <th>Cómputo</th>
                          <th>Cómputo ID</th>
                          <th>LC</th>
                          <th>PL</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                      <tfoot>
                        <tr>
                          <th class="d-none">ID</th>
                          <th>Sitio</th>
                          <th>Subsitio</th>
                          <th>Nro.Proy</th>
                          <th>Proyecto</th>
                          <th>Estructura</th>
                          <th>Sector</th>
                          <th>Tarea</th>
                          <th>Recurso</th>
                          <th>Coordinador</th>
                          <th>Observaciones</th>
                          <th>FIP</th>
                          <th>FFP</th>
                          <th>FIR</th>
                          <th>FFR</th>
                          <th>Completada</th>
                          <th>Cómputo</th>
                          <th>Cómputo ID</th>
                          <th>LC</th>
                          <th>PL</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <!-- Zero Configuration  Ends-->
          </div>
        </div>
        <!-- Container-fluid Ends-->
      </div>
      <!-- footer start-->
      <?php include("footer.php"); ?>
    </div>
  </div>

  <?php
  $pdo = Database::connect();
  $sqlModal = "SELECT t.`id`
                 FROM `tareas` t
                 INNER JOIN proyectos p ON p.id = t.`id_proyecto`
                 WHERE t.`anulado` = 0 AND p.anulado = 0";
  foreach ($pdo->query($sqlModal) as $row) { ?>
    <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog"
      aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">×</span>
            </button>
          </div>
          <div class="modal-body">¿Está seguro que desea eliminar la tarea?</div>
          <div class="modal-footer">
            <a href="eliminarTarea.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
    </div>
  <?php }
  Database::disconnect();
  ?>

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

  <!-- ↓↓↓ TU SCRIPT ANTES DE custom.js ↓↓↓ -->
  <script src="assets/js/colResize.js"></script>
  <script>
    $(document).ready(function() {

      $('#dataTables-example666 tfoot th').each(function() {
        if (!$(this).hasClass('d-none')) {
          var title = $(this).text();
          $(this).html('<input type="text" size="' + title.length + '" placeholder="' + title + '" />');
        }
      });

      var table = $('#dataTables-example666').DataTable({
        ordering:   true,
        stateSave:  false,
        responsive: false,
        serverSide: true,
        processing: true,
        searching:  true,
        ajax: {
          url:  'listarTareasAjax.php',
          type: 'GET',
          data: function(d) {
            d.nro           = '<?php echo addslashes($nro); ?>';
            d.id_tipo_tarea = '<?php echo addslashes($id_tipo_tarea); ?>';
            d.completada    = '<?php echo addslashes($completada); ?>';
            d.orden         = '<?php echo addslashes($orden); ?>';
            d.submitted     = '<?php echo $submitted; ?>';
            return d;
          }
        },
        "colResize": {
          isEnabled: true,
          saveState: true,
          hoverClass: 'dt-colresizable-hover',
          hasBoundCheck: true,
          minBoundClass: 'dt-colresizable-bound-min',
          maxBoundClass: 'dt-colresizable-bound-max',
          isResizable: function(column) {
            return true;
          },
          onResizeStart: function(column, columns) {},
          onResize: function(column) {},
          onResizeEnd: function(column, columns) {},
          getMinWidthOf: function($thNode) {},
          stateSaveCallback: function(settings, data) {},
          stateLoadCallback: function(settings) {}
        },
        columnDefs: [{
          targets:    0,
          visible:    false,
          searchable: false,
          orderable:  false
        }],
        dom: 'Bfrtp<"bottom"l>',
        buttons: ['excel'],
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

      table.columns().every(function() {
        var that = this;
        $('input', this.footer()).on('keyup change', function() {
          if (that.search() !== this.value) {
            that.search(this.value).draw();
          }
        });
      });

      $("#link_ver_tarea").on("click", function() {
        if ($(this).attr("href") === "#") {
          alert("Por favor seleccione una tarea para ver detalle");
        }
      });
      $("#link_modificar_tarea").on("click", function() {
        if ($(this).attr("href") === "#") {
          alert("Por favor seleccione una tarea para modificar");
        }
      });
      $("#link_nuevo_computo").on("click", function() {
        if ($(this).attr("href") === "#") {
          alert("Ya tiene uno asociado");
        }
      });
      $("#link_eliminar_tarea").on("click", function() {
        var target = this.dataset.target;
        if (target === undefined || target === "#") {
          alert("Por favor seleccione una tarea para eliminar");
        }
      });

      $(document).on("click", "#dataTables-example666 tbody tr td", function() {
        var fila = $(this).closest("tr");

        var rowData = table.row(fila).data();
        var id_tarea = rowData ? rowData[0] : fila.find("td:first-child").html();
        var tt = fila.find("td:eq(6)").html()?.trim();
        var tiene_computo = fila.find("td:eq(15)").html()?.trim();
        var tiene_lc = fila.find("td:eq(17)").html()?.trim();
        var tiene_packing = fila.find("td:eq(18)").html()?.trim();

        if (fila.hasClass('selected')) {
          deselectRow(fila);
          $("#link_ver_tarea").attr("href", "#");
          $("#link_modificar_tarea").attr("href", "#");
          $("#link_nuevo_computo").attr("href", "#");
          $("#link_eliminar_tarea")
            .removeAttr("data-toggle")
            .attr("data-target", "#");
        } else {
          table.rows().nodes().each(function(rowNode) {
            $(rowNode).removeClass("selected");
          });
          selectRow(fila);

          $("#link_ver_tarea").attr("href", "verTarea.php?id=" + id_tarea);
          $("#link_modificar_tarea").attr("href", "modificarTarea.php?id=" + id_tarea);

          if (tt === 'Computos') {
            $("#link_nuevo_computo").attr("href",
              tiene_computo === 'No' ? "nuevoComputo.php?id=" + id_tarea : "#");
          } else if (tt === 'Planos y LC') {
            $("#link_nuevo_computo").attr("href",
              tiene_lc === 'No' ? "nuevaListaCorte.php?idTarea=" + id_tarea : "#");
          } else if (tt === 'Packing List') {
            $("#link_nuevo_computo").attr("href",
              tiene_packing === 'No' ? "nuevaPackingList.php?idTarea=" + id_tarea : "#");
          } else {
            $("#link_nuevo_computo").attr("href", "#");
          }

          $("#link_eliminar_tarea")
            .attr("data-toggle", "modal")
            .attr("data-target", "#eliminarModal_" + id_tarea);
        }
      });

    });

    function selectRow(t) {
      t.addClass('selected');
    }

    function deselectRow(t) {
      t.removeClass('selected');
    }
  </script>
  <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  <!-- Plugin used-->
</body>

</html>