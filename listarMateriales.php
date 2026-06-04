<?php
require("config.php");
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

    <!-- Page Header Ends -->
    <!-- Page Body Start-->
    <div class="page-body-wrapper">
      <!-- Page Sidebar Start-->
      <?php include('menu.php'); ?>
      <!-- Page Sidebar Ends-->
      <div class="page-body">
        <?php
        $ubicacion = "Conceptos ";
        include_once("head_page.php") ?>
        <!-- Container-fluid starts-->
        <div class="container-fluid">
          <div class="row">
            <!-- Zero Configuration Starts-->
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5><?php echo $ubicacion;
                      if (!empty(tienePermiso(286))) { ?><a href="nuevoMaterial.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a><?php } ?>
                    &nbsp;&nbsp;
                    <?php
                    echo '<a href="#" id="link_ver_material"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                    echo '&nbsp;&nbsp;';
                    if (!empty(tienePermiso(287))) {
                      echo '<a href="#" id="link_modificar_material"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                      echo '&nbsp;&nbsp;';
                    }
                    if (!empty(tienePermiso(300))) {
                      echo '<a href="#" id="link_ver_precios_material"><img src="img/dolar.png" width="24" height="25" border="0" alt="Histórico de Precios" title="Histórico de Precios"></a>';
                      echo '&nbsp;&nbsp;';
                    }
                    if (!empty(tienePermiso(288))) {
                      echo '<a href="#" id="link_eliminar_material"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
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
                          <th>ID</th>
                          <th>Código</th>
                          <th>Concepto</th>
                          <th>Categoría</th>
                          <th>Stock</th>
                          <th>Reservado</th>
                          <th>Activo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $pdo = Database::connect();
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $sql = "SELECT m.id, m.codigo, m.concepto, c.categoria, m.activo 
                                FROM materiales m 
                                INNER JOIN categorias c ON c.id = m.id_categoria 
                                WHERE m.anulado = 0";

                        foreach ($pdo->query($sql) as $row) {
                          $stockInfo = obtenerStockYReservado($pdo, (int)$row['id']);
                          ?>
                          <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['codigo']) ?></td>
                            <td><?= htmlspecialchars($row['concepto']) ?></td>
                            <td><?= htmlspecialchars($row['categoria']) ?></td>
                            <td><?= $stockInfo['stock'] ?></td>
                            <td><?= $stockInfo['reservado'] ?></td>
                            <td><?= ($row['activo'] == 1) ? 'Si' : 'No' ?></td>
                          </tr>
                        <?php
                        }
                        Database::disconnect();
                        ?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th>ID</th>
                          <th>Código</th>
                          <th>Concepto</th>
                          <th>Categoría</th>
                          <th>Stock</th>
                          <th>Reservado</th>
                          <th>Activo</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <!-- Zero Configuration Ends-->
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
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT m.id, m.codigo, m.concepto, c.categoria, m.activo 
          FROM materiales m 
          INNER JOIN categorias c ON c.id = m.id_categoria 
          WHERE m.anulado = 0";
  foreach ($pdo->query($sql) as $row) {
  ?>
    <div class="modal fade" id="eliminarModal_<?= $row['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea eliminar el concepto?</div>
          <div class="modal-footer">
            <a href="eliminarMaterial.php?id=<?= $row['id'] ?>" class="btn btn-primary">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
    </div>
  <?php
  }
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
  <script src="assets/js/datatable/datatable-extension/custom.js"></script>
  <script src="assets/js/chat-menu.js"></script>
  <script src="assets/js/tooltip-init.js"></script>
  <!-- Plugins JS Ends-->
  <!-- Theme js-->
  <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function() {
      // Setup - add a text input to each footer cell
      $('#dataTables-example666 tfoot th').each(function() {
        var title = $(this).text();
        var placeholder = title;
        if (title === 'Stock' || title === 'Reservado') {
          placeholder = title;
        }
        $(this).html('<input type="text" size="' + title.length + '" placeholder="' + placeholder + '" />');
      });

      var table = $('#dataTables-example666').DataTable({
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

      function parseNumericFilter(value) {
        value = (value || '').trim();
        if (value === '') {
          return null;
        }
        var match = value.match(/^\s*([<>]=?)?\s*(-?\d+(?:[\.,]\d+)?)\s*$/);
        if (match) {
          var op = match[1] || '=';
          var num = parseFloat(match[2].replace(',', '.'));
          return { op: op, num: num };
        }
        return null;
      }

      function numericCompare(cellValue, filter) {
        if (!filter) {
          return true;
        }
        var cellNum = parseFloat((cellValue || '').toString().replace(/,/g, '.'));
        if (isNaN(cellNum)) {
          return false;
        }
        switch (filter.op) {
          case '>': return cellNum > filter.num;
          case '<': return cellNum < filter.num;
          case '>=': return cellNum >= filter.num;
          case '<=': return cellNum <= filter.num;
          case '=': return cellNum === filter.num;
          default: return false;
        }
      }

      $.fn.dataTable.ext.search.push(function(settings, data) {
        if (settings.nTable !== $('#dataTables-example666')[0]) {
          return true;
        }

        var stockFilter = parseNumericFilter($('#dataTables-example666 tfoot th').eq(4).find('input').val());
        var reservadoFilter = parseNumericFilter($('#dataTables-example666 tfoot th').eq(5).find('input').val());

        if (stockFilter && !numericCompare(data[4], stockFilter)) {
          return false;
        }
        if (reservadoFilter && !numericCompare(data[5], reservadoFilter)) {
          return false;
        }
        return true;
      });

      // Apply the search
      table.columns().every(function() {
        var that = this;
        var columnIndex = that.index();

        $('input', this.footer()).on('keyup change', function() {
          var value = this.value || '';
          var isNumericOperator = (columnIndex === 4 || columnIndex === 5) && parseNumericFilter(value);

          if (isNumericOperator) {
            if (that.search() !== '') {
              that.search('');
            }
          } else if (that.search() !== value) {
            that.search(value);
          }

          table.draw();
        });
      });

      $("#link_ver_material").on("click", function() {
        let l = document.location.href;
        if (this.href == l || this.href == l + "#") {
          alert("Por favor seleccione un concepto para ver detalle")
        }
      })
      $("#link_modificar_material").on("click", function() {
        let l = document.location.href;
        if (this.href == l || this.href == l + "#") {
          alert("Por favor seleccione un concepto para modificar")
        }
      })
      $("#link_ver_precios_material").on("click", function() {
        let l = document.location.href;
        if (this.href == l || this.href == l + "#") {
          alert("Por favor seleccione un concepto para ver/actualizar precios de ítems")
        }
      })
      $("#link_eliminar_material").on("click", function() {
        let target = this.dataset.target;
        if (target == undefined || target == "#") {
          alert("Por favor seleccione un concepto para eliminar")
        }
      })

      $(document).on("click", "#dataTables-example666 tbody tr td", function() {
        var t = $(this).parent();

        let id_material = t.find("td:first-child").html();
        if (t.hasClass('selected')) {
          deselectRow(t);
          $("#link_ver_material").attr("href", "#");
          $("#link_modificar_material").attr("href", "#");
          $("#link_ver_precios_material").attr("href", "#");
          $("#link_eliminar_material").attr("data-target", "#");
        } else {
          table.rows().nodes().each(function(rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_ver_material").attr("href", "verMaterial.php?id=" + id_material);
          $("#link_modificar_material").attr("href", "modificarMaterial.php?id=" + id_material);
          $("#link_ver_precios_material").attr("href", "verPreciosMaterial.php?id=" + id_material);
          $("#link_eliminar_material").attr("data-toggle", "modal");
          $("#link_eliminar_material").attr("data-target", "#eliminarModal_" + id_material);
        }
      });

    });
      /*table.on('page.dt', function () {
          //alert('La página ha cambiado');
          // Hacer algo más aquí...
      });

      // Ejecutar una función al cambiar la cantidad de filas mostradas
      table.on('length.dt', function (e, settings, len) {
          //alert('La cantidad de filas mostradas ha cambiado a ' + len);
          // Hacer algo más aquí...
      });*/
    
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