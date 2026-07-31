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
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body">
        <?php
        $ubicacion = "Conceptos ";
        include_once("head_page.php") ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5><?php echo $ubicacion;
                      if (!empty(tienePermiso(286))) { ?><a href="nuevoMaterial.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a><?php } ?>
                    &nbsp;&nbsp;
                    <a href="importMateriales.php" title="Importar Excel Conceptos"><img src="img/xls.png" width="24" height="25" border="0" alt="Importar Conceptos"></a>
                    &nbsp;
<!--                     <a href="importUnidadesMedida.php" title="Importar Unidades de Medida"><img src="img/xls.png" width="24" height="25" border="0" alt="Importar Unidades" title="Importar Unidades de Medida"></a>
 -->                    <?php
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
                        </tr>
                      </thead>
                      <tbody>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th>ID</th>
                          <th>Código</th>
                          <th>Concepto</th>
                          <th>Categoría</th>
                          <th>Stock</th>
                          <th>Reservado</th>
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

  <div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="eliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="eliminarModalLabel">Confirmación</h5>
          <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body">¿Está seguro que desea eliminar el concepto?</div>
        <div class="modal-footer">
          <a href="#" id="btnConfirmarEliminar" class="btn btn-primary">Eliminar</a>
          <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
        </div>
      </div>
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
        if (title === 'Stock' || title === 'Reservado') {
          $(this).html(
            '<select class="numeric-operator">' +
            '<option value=""></option>' +
            '<option value="<">&lt;</option>' +
            '<option value="<=">&lt;=</option>' +
            '<option value=">">&gt;</option>' +
            '<option value=">=">&gt;=</option>' +
            '<option value="==">==</option>' +
            '<option value="!=">!=</option>' +
            '</select>' +
            '<input type="text" class="numeric-value" size="' + title.length + '" placeholder="' + title + '" />'
          );
        } else {
          $(this).html('<input type="text" size="' + title.length + '" placeholder="' + title + '" />');
        }
      });

      var table = $('#dataTables-example666').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
          url: 'get_materiales_datatable.php',
          data: function(d) {
            d.stock_op = $('#dataTables-example666 tfoot th').eq(4).find('select.numeric-operator').val() || '';
            d.stock_val = $('#dataTables-example666 tfoot th').eq(4).find('input.numeric-value').val() || '';
            d.reservado_op = $('#dataTables-example666 tfoot th').eq(5).find('select.numeric-operator').val() || '';
            d.reservado_val = $('#dataTables-example666 tfoot th').eq(5).find('input.numeric-value').val() || '';
          }
        },
        columns: [
          { data: 'id' },
          { data: 'codigo' },
          { data: 'concepto' },
          { data: 'categoria' },
          { data: 'stock' },
          { data: 'reservado' }
        ],
        lengthMenu: [[10, 25, 50, 100, 500, 1000], [10, 25, 50, 100, 500, 1000]],
        dom: 'Bfrtp<"bottom"l>',
        buttons: ['excel'],
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
        var columnIndex = that.index();

        $('input, select', this.footer()).on('keyup change', function() {
          var isNumericColumn = (columnIndex === 4 || columnIndex === 5);

          if (isNumericColumn) {
            var op = $(that.footer()).find('select.numeric-operator').val() || '';
            var num = $(that.footer()).find('input.numeric-value').val() || '';

            if (op && num) {
              that.search('');
            } else {
              var val = $(that.footer()).find('input').val() || '';
              if (that.search() !== val) {
                that.search(val);
              }
            }
          } else {
            var val = $(that.footer()).find('input').val() || '';
            if (that.search() !== val) {
              that.search(val);
            }
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
          $("#link_eliminar_material").attr("data-target", "#eliminarModal");
          $("#btnConfirmarEliminar").attr("href", "eliminarMaterial.php?id=" + id_material);
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
</body>
</html>
