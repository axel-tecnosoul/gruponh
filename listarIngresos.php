<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_tables.php');?>
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
      <?php include('header.php');?>
      <!-- Page Header Ends -->

      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->

        <div class="page-body">
          <?php
          $ubicacion = "Ingresos de Stock ";
          include_once("head_page.php");
          ?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>
                      <?php 
                      echo $ubicacion; 
                      if (!empty(tienePermiso(324))) { 
                      ?>
                        <a href="nuevaDevolucion.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo Ingreso x Devolución" title="Nuevo Ingreso x Devolución"></a>&nbsp;
                      <?php 
                      } 
                      ?>
                      <a href="exportIngresos.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
                      &nbsp;&nbsp;
                      <a href="#" id="link_ver_ingreso"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>
                      &nbsp;&nbsp;
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Tipo</th>
                            <th>Nro.</th>
                            <th>Recibe</th>
                            <th>Lugar</th>
                            <th>Fecha Remito</th>
                            <th>Nro Remito</th>
                            <th>Observaciones</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          include 'database.php';
                          $pdo = Database::connect();
                          
                          $sql = " SELECT 
                                i.`id`, 
                                date_format(i.`fecha_hora`,'%d/%m/%y %H:%i'), 
                                ti.`tipo`, 
                                i.`nro`, 
                                c.`nombre`, 
                                i.`lugar_entrega`, 
                                i.`observaciones`, 
                                date_format(i.`fecha_remito`,'%d/%m/%Y'), 
                                i.`nro_remito`, 
                                date_format(i.`fecha_remito`,'%Y%m%d%H%i'),
                                date_format(i.`fecha_hora`,'%Y%m%d%H%i')
                            FROM `ingresos` i 
                            inner join tipos_ingreso ti on ti.id = i.`id_tipo_ingreso` 
                            inner join cuentas c on c.id = i.`id_cuenta_recibe` 
                            WHERE 1 ";

                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
                                echo '<td>' . $row[0] . '</td>';
                                echo '<td data-order="' . $row[10] . '">' . $row[1] . 'hs</td>';
                                echo '<td>' . $row[2] . '</td>';
                                echo '<td>' . $row[3] . '</td>';
                                echo '<td>' . $row[4] . '</td>';
                                echo '<td>' . $row[5] . '</td>';
                                echo '<td data-order="' . $row[9] . '">' . $row[7] . '</td>';
                                echo '<td>' . $row[8] . '</td>';
                                echo '<td>' . $row[6] . '</td>';
                                echo '</tr>';
                            }

                          Database::disconnect();
                          ?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Tipo</th>
                            <th>Nro.</th>
                            <th>Recibe</th>
                            <th>Lugar</th>
                            <th>Fecha Remito</th>
                            <th>Nro Remito</th>
                            <th>Observaciones</th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Zero Configuration Ends-->
            </div>

            <div class="row">
              <!-- Zero Configuration Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Conceptos
                      &nbsp;&nbsp;
                      <?php
                      if (!empty(tienePermiso(327))) {
                        echo '<a href="#" id="link_nueva_colada"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Vincular Colada" title="Vincular Colada"></a>';
                        echo '&nbsp;&nbsp;';
                        echo '<button type="button" id="btn_asignar_coladas_internas" class="btn btn-primary btn-sm">Asignar Coladas Internas</button>';
                      }
                      ?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Concepto</th>
                            <th>Categoría</th>
                            <th>Unidad Medida</th>
                            <th>Cantidad</th>
                            <th>Efectivizado</th>
                            <th>Cantidad egresada</th>
                            <th>Saldo</th>
                            <th>Colada Interna</th>
                            <th>Vinculación Colada</th>
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
                            <th>Unidad Medida</th>
                            <th>Cantidad</th>
                            <th>Efectivizado</th>
                            <th>Cantidad egresada</th>
                            <th>Saldo</th>
                            <th>Colada Interna</th>
                            <th>Vinculación Colada</th>
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

    <div class="modal fade" id="coladasInternasModal" tabindex="-1" role="dialog" aria-labelledby="coladasInternasModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="coladasInternasModalLabel">Asignar Coladas Internas</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>Se asignarán códigos de colada interna consecutivos a los conceptos seleccionados.</p>
            <div class="table-responsive">
              <table class="table table-bordered table-sm" id="tableColadasInternas">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Código</th>
                    <th>Concepto</th>
                    <th>Cantidad</th>
                    <th>Saldo</th>
                    <th>Colada Interna Asignada</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="button" id="btn_confirmar_coladas_internas" class="btn btn-primary">Confirmar Asignación</button>
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
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>

    <script>
      $(document).ready(function() {
        // Setup - add a text input to each footer cell
        $('#dataTables-example666 tfoot th').each(function() {
          var title = $(this).text();
          $(this).html('<input type="text" size="' + title.length + '" placeholder="' + title + '" />');
        });

        $('#dataTables-example666').DataTable({
            stateSave: false,
            responsive: false,
            dom: 'Bfrtp<"bottom"l>',
            buttons: ['excel'],
            order: [[0, 'desc']],
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
        var table = $('#dataTables-example666').DataTable();

        // Apply the search
        table.columns().every(function() {
          var that = this;
          $('input', this.footer()).on('keyup change', function() {
            if (that.search() !== this.value) {
              that.search(this.value).draw();
            }
          });
        });


        $("#link_ver_ingreso").on("click", function() {
          let l = document.location.href;
          if (this.href == l || this.href == l + "#") {
            alert("Por favor seleccione un ingreso para ver detalle")
          }
        });

        $(document).on("click", "#dataTables-example666 tbody tr td", function() {
          var t = $(this).parent();

          let id_ingreso = t.find("td:first-child").html();

          if (t.hasClass('selected')) {
            deselectRow(t);
            get_conceptos(id_ingreso)
            $("#link_ver_ingreso").attr("href", "#");

          } else {
            table.rows().nodes().each(function(rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_conceptos(id_ingreso)
            $("#link_ver_ingreso").attr("href", "verIngreso.php?id=" + id_ingreso);
          }
        });

      });
    </script>

    <script>
      var conceptTable = null;
      var currentIngresoId = null;
      var pendingInternalAssignments = [];

      function normalizeInternalCode(code) {
        if (!code || typeof code !== 'string') {
          return '';
        }
        var normalized = code.toString().trim().toUpperCase();
        if (/^[A-Z]{3}$/.test(normalized)) {
          return normalized;
        }
        return '';
      }

      function incrementInternalColada(code) {
        var normalized = normalizeInternalCode(code);
        if (!normalized) {
          return 'DAA';
        }
        var chars = normalized.split('').map(function(c) {
          return c.charCodeAt(0) - 65;
        });
        for (var i = chars.length - 1; i >= 0; i--) {
          if (chars[i] < 25) {
            chars[i]++;
            break;
          }
          chars[i] = 0;
        }
        return String.fromCharCode(chars[0] + 65, chars[1] + 65, chars[2] + 65);
      }

      function getNextInternalColadas(startCode, count) {
        var codes = [];
        var normalizedStart = normalizeInternalCode(startCode);
        var code = normalizedStart ? incrementInternalColada(normalizedStart) : 'DAA';
        for (var i = 0; i < count; i++) {
          codes.push(code);
          code = incrementInternalColada(code);
        }
        return codes;
      }

      function updateLinkNuevaColada() {
        var selectedRows = $('#dataTables-example667 tbody tr.selected');
        if (selectedRows.length === 1) {
          var t = selectedRows.first();
          var tiene_colada = t.find('td:nth-child(11)').text().trim();
          var id_ingreso = t.find('td:first-child').text().trim();
          var coladaInterna = t.find('td:nth-child(10)').text().trim();
          if (tiene_colada == 'No') {
            $('#link_nueva_colada').attr('href', 'nuevaColada.php?id=' + id_ingreso + '&interna=' + encodeURIComponent(coladaInterna));
            return;
          }
        }
        $('#link_nueva_colada').attr('href', '#');
      }

      function buildInternalAssignModal(selectedData, startCode) {
        var codes = getNextInternalColadas(startCode, selectedData.length);
        pendingInternalAssignments = [];
        var html = '';
        for (var i = 0; i < selectedData.length; i++) {
          var row = selectedData[i];
          var code = codes[i];
          html += '<tr>';
          html += '<td>' + row[0] + '</td>';
          html += '<td>' + row[1] + '</td>';
          html += '<td>' + row[2] + '</td>';
          html += '<td>' + row[5] + '</td>';
          html += '<td>' + row[8] + '</td>';
          html += '<td>' + code + '</td>';
          html += '</tr>';
          pendingInternalAssignments.push({id: row[0], codigo: code});
        }
        $('#tableColadasInternas tbody').html(html);
        $('#coladasInternasModal').modal('show');
      }

      $(document).ready(function() {
        $('#dataTables-example667 tfoot th').each(function() {
          var title = $(this).text();
          $(this).html('<input type="text" size="' + title.length + '" placeholder="' + title + '" />');
        });

        $('#dataTables-example667').DataTable({
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

        conceptTable = $('#dataTables-example667').DataTable();

        conceptTable.columns().every(function() {
          var that = this;
          $('input', this.footer()).on('keyup change', function() {
            if (that.search() !== this.value) {
              that.search(this.value).draw();
            }
          });
        });

        $('#btn_asignar_coladas_internas').off('click').on('click', function() {
          if (!conceptTable) {
            alert('Por favor seleccione un ingreso primero.');
            return;
          }
          var selected = conceptTable.rows('.selected').data().toArray();
          if (selected.length === 0) {
            alert('Por favor seleccione al menos un concepto para asignar colada interna.');
            return;
          }
          var alreadyAssigned = selected.some(function(row) {
            return row[9] && row[9].toString().trim() !== '';
          });
          if (alreadyAssigned) {
            alert('No se puede asignar colada interna porque al menos uno de los conceptos seleccionados ya tiene una colada interna.');
            return;
          }
          $.ajax({
            url: 'get_ultimo_nro_colada_interna.php',
            method: 'get',
            dataType: 'json',
            success: function(response) {
              if (!response.success) {
                alert(response.message || 'No fue posible obtener el último código.');
                return;
              }
              buildInternalAssignModal(selected, response.ultimo);
            },
            error: function() {
              alert('Error al obtener el último código de colada interna.');
            }
          });
        });

        $('#btn_confirmar_coladas_internas').off('click').on('click', function() {
          if (!pendingInternalAssignments || pendingInternalAssignments.length === 0) {
            alert('No hay asignaciones pendientes.');
            return;
          }
          var datos = new FormData();
          datos.append('assignments', JSON.stringify(pendingInternalAssignments));
          $.ajax({
            url: 'guardar_coladas_internas_ingreso.php',
            method: 'post',
            data: datos,
            dataType: 'json',
            cache: false,
            contentType: false,
            processData: false,
            success: function(response) {
              if (response.success) {
                alert('Coladas internas asignadas correctamente.');
                $('#coladasInternasModal').modal('hide');
                if (currentIngresoId) {
                  get_conceptos(currentIngresoId);
                }
              } else {
                alert('Error: ' + (response.message || 'No fue posible guardar.'));
              }
            },
            error: function(jqXHR) {
              var msg = 'Error al guardar las asignaciones.';
              if (jqXHR.responseText) {
                msg += '\n' + jqXHR.responseText;
              }
              alert(msg);
            }
          });
        });

        $('#link_nueva_colada').off('click').on('click', function() {
          var l = document.location.href;
          if (this.href == l || this.href == l + '#') {
            alert('Por favor seleccione un concepto para vincularle la colada');
          }
        });
      });

      function selectRow(t) {
        t.addClass('selected');
      }

      function deselectRow(t) {
        t.removeClass('selected');
      }

      function get_conceptos(id_ingreso) {
        currentIngresoId = id_ingreso;
        var datosUpdate = new FormData();
        datosUpdate.append('id_ingreso', id_ingreso);
        $.ajax({
          data: datosUpdate,
          url: 'get_conceptos_ingreso.php',
          method: 'post',
          cache: false,
          contentType: false,
          processData: false,
          success: function(data) {
            data = JSON.parse(data);

            $('#dataTables-example667').DataTable().destroy();
            $('#dataTables-example667').DataTable({
              stateSave: false,
              responsive: false,
              data: data,
              columnDefs: [{
                targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                className: 'text-center'
              }],
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

            conceptTable = $('#dataTables-example667').DataTable();

            conceptTable.columns().every(function() {
              var that = this;
              $('input', this.footer()).on('keyup change', function() {
                if (that.search() !== this.value) {
                  that.search(this.value).draw();
                }
              });
            });

            $('#dataTables-example667 tbody').off('click').on('click', 'tr', function() {
              var t = $(this);
              if (t.hasClass('selected')) {
                deselectRow(t);
              } else {
                selectRow(t);
              }
              updateLinkNuevaColada();
            });

            updateLinkNuevaColada();
          }
        });
      }
    </script>

    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>