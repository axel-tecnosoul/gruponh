<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';?>
<!DOCTYPE html>
<html lang="en">
  <head><?php
    include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      .nav-tabs .nav-link.active {
        font-weight: bold;
        background-color: #4466f2;
        color: white !important;
        border-color: #4466f2;
      }
      .nav-tabs .nav-link {
        color: #333;
        cursor: pointer;
      }
      .tab-content {
        padding-top: 20px;
      }
      .table-scroll-wrapper {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 65vh;
        width: 100%;
      }
      .table-scroll-wrapper table.dataTable {
        width: 100% !important;
      }
      .table-scroll-wrapper table.dataTable thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f8f9fa;
        padding: 6px 8px;
        font-size: 11px;
        white-space: nowrap;
      }
      table.dataTable tbody td {
        padding: 4px 6px;
        font-size: 11px;
        white-space: nowrap;
      }
      .text-comprado {
        color: green;
        font-weight: bold;
      }
      .text-entregado {
        color: red;
        font-weight: bold;
      }
      .number {
        text-align: right;
      }
      #tablaPendiente tbody tr {
        cursor: pointer;
      }
      #tablaPendiente tbody tr.fila-seleccionada td {
        background-color: #d9e8fb !important;
      }
    </style>
  </head>
  <body>
    <div class="page-wrapper"><?php
      include('header.php');?>
      <div class="page-body-wrapper"><?php
        include('menu.php');?>
        <div class="page-body"><?php
          $ubicacion = "Informe Pendientes Compras";
          include_once("head_page.php");?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
                  <div class="card-body">

                    <ul class="nav nav-tabs" id="informeTabs" role="tablist">
                      <li class="nav-item">
                        <a class="nav-link active" id="comprando-tab" data-toggle="tab" href="#comprando" role="tab">Comprando</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" id="aprobar-tab" data-toggle="tab" href="#aprobar" role="tab">Para aprobar</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" id="pendiente-tab" data-toggle="tab" href="#pendiente" role="tab">Pendiente</a>
                      </li>
                    </ul>

                    <div class="tab-content" id="informeTabsContent">

                      <div class="tab-pane fade show active" id="comprando" role="tabpanel">
                        <div class="table-scroll-wrapper">
                          <table class="display" id="tablaComprando" style="width:100%">
                            <thead>
                              <tr>
                                <th>Nº Pedido</th>
                                <th>Nº OC</th>
                                <th>Estado</th>
                                <th>Proveedor</th>
                                <th>Obra</th>
                                <th>Concepto</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Comprado</th>
                                <th>Entregado</th>
                                <th>Fecha Pedido</th>
                                <th>Fecha Requerido</th>
                                <th>Fecha Pactada</th>
                                <th>Fecha Entrega</th>
                              </tr>
                            </thead>
                            <tbody><?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                              $sql = "SELECT
                                  p.id AS nro_pedido,
                                  c.id AS nro_op,
                                  ec.estado AS estado_compra,
                                  prov.nombre AS proveedor,
                                  CONCAT(si.nro_sitio, '_', si.nro_subsitio, '_', pr.nro) AS obra,
                                  m.concepto,
                                  m.descripcion,
                                  cd.cantidad,
                                  um.unidad_medida AS unidad,
                                  cd.cantidad AS comprado,
                                  COALESCE(cd.entregado, 0) AS entregado,
                                  DATE_FORMAT(p.fecha, '%d/%m/%Y') AS fecha_pedido,
                                  DATE_FORMAT(pd.fecha_necesidad, '%d/%m/%Y') AS fecha_requerido,
                                  DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') AS fecha_pactada,
                                  DATE_FORMAT(cd.fecha_entrega, '%d/%m/%Y') AS fecha_entrega,
                                  COALESCE(cd.precio, 0) AS precio_unitario,
                                  COALESCE(cd.subtotal, 0) AS total,
                                  COALESCE(cd.entregado * cd.precio, 0) AS monto_entregado,
                                  COALESCE((cd.cantidad - COALESCE(cd.entregado, 0)) * cd.precio, 0) AS monto_pendiente
                                FROM compras c
                                  INNER JOIN compras_detalle cd ON cd.id_compra = c.id
                                  INNER JOIN materiales m ON m.id = cd.id_material
                                  INNER JOIN pedidos p ON p.id = c.id_pedido
                                  LEFT JOIN pedidos_detalle pd ON pd.id_pedido = p.id AND pd.id_material = m.id
                                  INNER JOIN proyectos pr ON pr.id = p.id_proyecto
                                  INNER JOIN sitios si ON si.id = pr.id_sitio
                                  LEFT JOIN cuentas prov ON prov.id = c.id_cuenta_proveedor
                                  LEFT JOIN estados_compra ec ON ec.id = c.id_estado_compra
                                  LEFT JOIN unidades_medida um ON um.id = m.id_unidad_medida
                                WHERE c.id_estado_compra IN (3, 6)
                                  AND (cd.cantidad - COALESCE(cd.entregado, 0)) > 0
                                ORDER BY p.id DESC, c.id DESC";

                              try {
                                foreach ($pdo->query($sql) as $row) {?>
                                  <tr>
                                    <td><?=$row['nro_pedido']?></td>
                                    <td><a href="verCompra.php?id=<?=$row['nro_op']?>"><?=$row['nro_op']?></a></td>
                                    <td><?=$row['estado_compra']?></td>
                                    <td><?=$row['proveedor']?></td>
                                    <td><?=$row['obra']?></td>
                                    <td><?=$row['concepto']?></td>
                                    <td><?=$row['descripcion']?></td>
                                    <td class="number"><?=number_format($row['cantidad'], 2, ",", ".")?></td>
                                    <td><?=$row['unidad']?></td>
                                    <td class="number"><?=number_format($row['comprado'], 2, ",", ".")?></td>
                                    <td class="number"><?=number_format($row['entregado'], 2, ",", ".")?></td>
                                    <td><?=$row['fecha_pedido']?></td>
                                    <td><?=$row['fecha_requerido']?></td>
                                    <td><?=$row['fecha_pactada']?></td>
                                    <td><?=$row['fecha_entrega']?></td>
                                  </tr><?php
                                }
                              } catch (PDOException $e) {
                                echo "<tr><td colspan='15'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                              }?>
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <div class="tab-pane fade" id="aprobar" role="tabpanel">
                        <div class="table-scroll-wrapper">
                          <table class="display" id="tablaAprobar" style="width:100%">
                            <thead>
                              <tr>
                                <th>Nº Pedido</th>
                                <th>Estado</th>
                                <th>Obra</th>
                                <th>Concepto</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Fecha Pedido</th>
                                <th>Fecha Requerido</th>
                                <th>Solicitante</th>
                              </tr>
                            </thead>
                            <tbody><?php
                              $sql = "SELECT
                                  p.id AS nro_pedido,
                                  ep.estado AS estado_pedido,
                                  CONCAT(si.nro_sitio, '_', si.nro_subsitio, '_', pr.nro) AS obra,
                                  m.concepto,
                                  m.descripcion,
                                  pd.cantidad,
                                  um.unidad_medida AS unidad,
                                  DATE_FORMAT(p.fecha, '%d/%m/%Y') AS fecha_pedido,
                                  DATE_FORMAT(pd.fecha_necesidad, '%d/%m/%Y') AS fecha_requerido,
                                  cu.nombre AS solicitante
                                FROM pedidos p
                                  INNER JOIN pedidos_detalle pd ON pd.id_pedido = p.id
                                  INNER JOIN materiales m ON m.id = pd.id_material
                                  INNER JOIN proyectos pr ON pr.id = p.id_proyecto
                                  INNER JOIN sitios si ON si.id = pr.id_sitio
                                  INNER JOIN estados_pedidos ep ON ep.id = p.id_estado
                                  LEFT JOIN unidades_medida um ON um.id = m.id_unidad_medida
                                  LEFT JOIN computos co ON co.id = p.id_computo
                                  LEFT JOIN cuentas cu ON cu.id = co.id_cuenta_solicitante
                                WHERE p.id_estado = 2
                                  AND pd.cancelado = 0
                                ORDER BY p.id DESC, m.concepto";

                              try {
                                foreach ($pdo->query($sql) as $row) {?>
                                  <tr>
                                    <td><a href="#" onclick="postPedido(<?=$row['nro_pedido']?>);return false;" style="cursor:pointer;"><?=$row['nro_pedido']?></a></td>
                                    <td><?=$row['estado_pedido']?></td>
                                    <td><?=$row['obra']?></td>
                                    <td><?=$row['concepto']?></td>
                                    <td><?=$row['descripcion']?></td>
                                    <td class="number"><?=number_format($row['cantidad'], 2, ",", ".")?></td>
                                    <td><?=$row['unidad']?></td>
                                    <td><?=$row['fecha_pedido']?></td>
                                    <td><?=$row['fecha_requerido']?></td>
                                    <td><?=$row['solicitante']?></td>
                                  </tr><?php
                                }
                              } catch (PDOException $e) {
                                echo "<tr><td colspan='10'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                              }?>
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <div class="tab-pane fade" id="pendiente" role="tabpanel">
                        <div class="table-scroll-wrapper">
                          <table class="display" id="tablaPendiente" style="width:100%">
                            <thead>
                              <tr>
                                <th>Nº Pedido</th>
                                <th>Obra</th>
                                <th>Estado</th>
                                <th>Concepto</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Fecha Pedido</th>
                                <th>Fecha Requerido</th>
                                <th>Ultimo Proveedor</th>
                                <th>Ultimo Precio</th>
                                <th>Ultima Fecha</th>
                              </tr>
                            </thead>
                            <tbody><?php
                              $sql = "SELECT 
                                  p.id AS nro_pedido,
                                  ep.estado AS estado_pedido,
                                  CONCAT(si.nro_sitio, '_', si.nro_subsitio, '_', pr.nro) AS obra,
                                  m.concepto,
                                  m.descripcion,
                                  (pd.cantidad - COALESCE(pd.comprado, 0)) AS cantidad_pendiente,
                                  um.unidad_medida AS unidad,
                                  DATE_FORMAT(p.fecha, '%d/%m/%Y') AS fecha_pedido,
                                  DATE_FORMAT(pd.fecha_necesidad, '%d/%m/%Y') AS fecha_requerido,
                                  ult.ultimo_proveedor,
                                  ult.ultimo_precio,
                                  ult.ultima_fecha
                                FROM pedidos p
                                  INNER JOIN pedidos_detalle pd ON pd.id_pedido = p.id
                                  INNER JOIN materiales m ON m.id = pd.id_material
                                  INNER JOIN proyectos pr ON pr.id = p.id_proyecto
                                  INNER JOIN sitios si ON si.id = pr.id_sitio
                                  LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
                                  LEFT JOIN unidades_medida um ON um.id = m.id_unidad_medida
                                  LEFT JOIN (
                                    SELECT
                                      cd.id_material,
                                      prov.nombre AS ultimo_proveedor,
                                      cd.precio   AS ultimo_precio,
                                      DATE_FORMAT(c.fecha_emision, '%d/%m/%Y') AS ultima_fecha,
                                      ROW_NUMBER() OVER (PARTITION BY cd.id_material ORDER BY c.fecha_emision DESC) AS rn
                                    FROM compras_detalle cd
                                      INNER JOIN compras c ON c.id = cd.id_compra
                                      LEFT JOIN cuentas prov ON prov.id = c.id_cuenta_proveedor
                                  ) ult ON ult.id_material = m.id AND ult.rn = 1
                                WHERE pd.cancelado = 0
                                  AND (pd.cantidad - COALESCE(pd.comprado, 0)) > 0
                                  AND p.id_estado NOT IN (1, 7)
                                ORDER BY p.id DESC, m.concepto";

                              try {
                                foreach ($pdo->query($sql) as $row) {
                                  //ejemplo: If ultimo_precio != null $val = row[]?>
                                  <tr>
                                    <td><?=$row['nro_pedido']?></td>
                                    <td><?=$row['obra']?></td>
                                    <td><?=$row['estado_pedido']?></td>
                                    <td class='concepto-col'><?=$row['concepto']?></td>
                                    <td class='descripcion-col'><?=$row['descripcion']?></td>
                                    <td class="number"><?=number_format($row['cantidad_pendiente'], 2, ",", ".")?></td>
                                    <td><?=$row['unidad']?></td>
                                    <td><?=$row['fecha_pedido']?></td>
                                    <td><?=$row['fecha_requerido']?></td>
                                    <td><?=$row['ultimo_proveedor']?></td>
                                    <td class="number"><?= $row['ultimo_precio'] !== null ? number_format($row['ultimo_precio'], 2, ",", ".") : '' ?></td>
                                    <td><?=$row['ultima_fecha']?></td>
                                  </tr><?php
                                }
                              } catch (PDOException $e) {
                                echo "<tr><td colspan='12'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                              }

                              Database::disconnect();?>
                            </tbody>
                          </table>
                        </div>
                      </div>

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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/script.js"></script>
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

    <script>
      $(document).ready(function () {

        var dtOptions = {
          dom: 'lfrtip',
          buttons: ['excel'],
          scrollX: false,
          autoWidth: true,
          pageLength: 25,
          order: [],
          language: {
            "emptyTable":   "No hay información",
            "info":         "Mostrando _START_ a _END_ de _TOTAL_ Registros",
            "infoEmpty":    "Mostrando 0 a 0 de 0 Registros",
            "infoFiltered": "(Filtrado de _MAX_ total registros)",
            "lengthMenu":   "Mostrar _MENU_ Registros",
            "search":       "Buscar:",
            "zeroRecords":  "No hay resultados",
            "paginate": {
              "first":    "Primero",
              "last":     "Ultimo",
              "next":     "Siguiente",
              "previous": "Anterior"
            }
          }
        };

        $('#tablaComprando').DataTable(dtOptions);

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          var target = $(e.target).attr("href");

          if (target === '#aprobar' && !$.fn.DataTable.isDataTable('#tablaAprobar')) {
            $('#tablaAprobar').DataTable(dtOptions);
          }

          if (target === '#pendiente' && !$.fn.DataTable.isDataTable('#tablaPendiente')) {
            $('#tablaPendiente').DataTable(dtOptions);

            var $wrapper = $('#tablaPendiente').closest('.dataTables_wrapper');
            var $lengthDiv = $wrapper.find('.dataTables_length');
            var $btnCopiar = $('<button id="btn-copiar-pendiente" class="btn btn-sm" style="margin-left:10px;vertical-align:middle;background-color:#5a6268;color:#fff;border-color:#545b62;">Copiar</button>');
            $lengthDiv.after($btnCopiar);

            $('#tablaPendiente tbody').on('click', 'tr', function () {
              $(this).toggleClass('fila-seleccionada');
            });

            $(document).on('click', '#btn-copiar-pendiente', function () {
              var $filas = $('#tablaPendiente tbody tr.fila-seleccionada');
              if ($filas.length === 0) {
                alert('Seleccione al menos una fila para copiar.');
                return;
              }
              var lineas = [];
              $filas.each(function () {
                var $celdas = $(this).find('td');
                var concepto = $celdas.eq(3).text().trim();
                var cantidad = $celdas.eq(5).text().trim();
                lineas.push(concepto + '\t' + cantidad);
              });
              var texto = lineas.join('\n');

              var $ta = $('<textarea>').val(texto).css({ position: 'fixed', top: 0, left: 0, width: '1px', height: '1px', opacity: 0 }).appendTo('body');
              $ta[0].focus();
              $ta[0].select();
              var ok = false;
              try { ok = document.execCommand('copy'); } catch(e) {}
              $ta.remove();

              if (!ok && navigator.clipboard) {
                navigator.clipboard.writeText(texto).then(function() { ok = true; }).catch(function() {
                  alert('No se pudo copiar. Por favor copie manualmente.');
                });
                ok = true;
              }
              if (ok) {
                var $btn = $('#btn-copiar-pendiente');
                $btn.html('Copiado!');
                setTimeout(function () { $btn.html('Copiar'); }, 1500);
              }
            });
          }

          setTimeout(function () {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
          }, 50);
        });
      });

      function postPedido(id) {
        var f = document.createElement('form');
        f.method = 'post';
        f.action = 'listarPedidos.php';
        f.target = '_blank';
        var campos = { nro_pedido: id, 'id_estado[]': 'todos' };
        for (var k in campos) {
          var i = document.createElement('input');
          i.type = 'hidden';
          i.name = k;
          i.value = campos[k];
          f.appendChild(i);
        }
        document.body.appendChild(f);
        f.submit();
      }

      document.addEventListener("DOMContentLoaded", function () {
        document.querySelector('.page-main-header').classList.add('open');
        document.querySelector('.page-sidebar').classList.add('open');
      });
    </script>
  </body>
</html>
