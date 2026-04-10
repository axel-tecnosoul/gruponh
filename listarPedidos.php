<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
require_once 'manejarFiltros.php';

$filters = gestionarFiltros('listarPedidos');

$nro = $filters['nro'] ?? "";
$nro_pedido = $filters['nro_pedido'] ?? "";
$fecha = $filters['fecha'] ?? "";
$fechah = $filters['fechah'] ?? "";
$id_estado = $filters['id_estado'] ?? [];

if (empty($filters)) {
  $id_estado = [1, 2, 3, 4];
}

if (in_array("todos", $id_estado)) {
  $id_estado = ["todos"];
  $filtrarPorEstado = false;
} else {
  $id_estado = array_filter($id_estado, function($v) {
    return $v !== "" && $v !== null;
  });
  $filtrarPorEstado = !empty($id_estado);
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_tables.php');?>
    <style>
      .truncate {
        max-width:50px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .truncate-project {
        width: 35%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .truncate-solicitante {
        width: 7%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      #dataTables-example666 {
        table-layout: fixed;
        width: 100% !important;
      }
      #dataTables-example666 td.truncate-project {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      #dataTables-example666 td.truncate-solicitante {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      
      /* Tabla de conceptos */
      #dataTables-example667 {
        table-layout: fixed;
        width: 100% !important;
      }
      .truncate-concepto {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      #dataTables-example667 td.truncate-concepto {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      #dataTables-example667 th {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .faClass{
        width: 24px;
        height: 20px;
        color: midnightblue;
      }
      .editable {
        text-decoration: underline;
        cursor: default;
      }
      .proyecto-truncado {
        cursor: help;
        border-bottom: 1px dotted #999;
      }
    </style>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <!-- Page Header Start-->
      <?php include('header.php');?>
      <!-- Page Header Ends-->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Pedidos ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-body">
                    <form class="form-inline theme-form mt-3" name="form1" method="post" action="listarPedidos.php">
                      <div class="form-group mb-0">
                        N. Pedido:&nbsp;<input class="form-control" size="5" type="text" value="<?=$nro_pedido?>" name="nro_pedido">
                      </div>
                      <div class="form-group mb-0">
                        N.Sitio/N.Proy:&nbsp;<input class="form-control" size="3" type="text" value="<?=$nro?>" name="nro">
                      </div>
                      <div class="form-group mb-0">
                        Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?=$fecha?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?=$fechah?>" name="fechah">
                      </div>
                      <div class="form-group mb-0">
                        Estado:&nbsp;
                        <select name="id_estado[]" id="id_estado" class="js-example-basic-multiple" multiple="multiple">
                          <option value="todos" <?= in_array("todos", $id_estado) ? 'selected' : '' ?>>Todos</option>
                          <?php
                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $sqlZon = "SELECT id, estado FROM estados_pedidos WHERE 1 ORDER BY id ASC";
                          $q = $pdo->prepare($sqlZon);
                          $q->execute();
                          while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                            $selected = "";
                            if (!in_array("todos", $id_estado) && in_array($fila['id'], $id_estado)) {
                              $selected = " selected ";
                            }?>
                            <option value='<?=$fila['id']?>' <?=$selected?>><?=$fila['estado']?></option><?php
                          }
                          Database::disconnect();?>
                        </select>
                      </div>
                      <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <a href="listarPedidos.php?clear_filters=1" class="btn btn-secondary ml-2">Limpiar</a>
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
                    <h5><?php echo $ubicacion;
                      if (!empty(tienePermiso(295))) { ?>
                        <a href="nuevoPedidoDirecto.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo Pedido Directo" title="Nuevo Pedido Directo"></a>&nbsp;<?php
                      }
                      if (!empty(tienePermiso(295))) {?>
                        <a href="#" id="link_modificar_pedido"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp;<?php
                      }?>
                      <a href="#" id="link_ver_pedido"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Pedido" title="Ver Pedido"></a>
                      &nbsp;&nbsp;
                      <a href="#" id="link_gestionar_pedido"><img src="img/medalla-dorada.png" width="24" height="15" border="0" alt="Gestionar Pedido" title="Gestionar Pedido"></a>
                      &nbsp;&nbsp;
                      <a href="exportPedidos.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(303))) {?>
                        <a href="#" id="link_aprobar_pedido"><img src="img/aprobar.png" width="24" height="25" border="0" alt="Aprobar" title="Aprobar"></a>&nbsp;&nbsp;
                        <a href="#" id="link_rechazar_pedido"><img src="img/neg.png" width="24" height="25" border="0" alt="Rechazar/Eliminar" title="Rechazar/Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(284))) {?>
                        <a href="#" id="link_nuevo_suceso"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Agregar Suceso" title="Agregar Suceso"></a>&nbsp;&nbsp;<?php
                      }?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th style="width: 40px;">Nro.</th>
                            <th style="width: 100px;">Proyecto</th>
                            <th class="truncate-project">Nombre Proyecto</th>
                            <th style="width: 85px;">F. de Carga</th>
                            <th style="width: 85px;">F. Pedida</th>
                            <th style="width: 85px;">F. Entrega</th>
                            <th style="width: 80px;">Estado</th>
                            <th class="truncate-solicitante">Solicitante</th>
                            <th style="width: 60px;">Tipo</th>
                            <th style="display: none;">Proy</th>
                            <th style="display: none;">Estado ID</th>
                          </tr>
                        </thead>
                        <tbody><?php

                          $filtroNroPedido = "";
                          if ($nro_pedido != "") {
                            $filtroNroPedido = " AND pe.id = ".intval($nro_pedido)." ";
                          }

                          $filtroNro="";
                          if ($nro!="") {
                            $ex=explode("/", $nro);
                            if(count($ex)>1){
                              $sitio = $ex[0];
                              $proyecto = $ex[1];
                              $filtroNro = " AND (p.nro = ".intval($proyecto)." AND s.nro_sitio = ".intval($sitio).") ";
                            }else{
                              $filtroNro = " AND (p.nro = ".intval($nro)." OR s.nro_sitio = ".intval($nro).") ";
                            }
                          }
                          $filtroFecha="";
                          if ($fecha!="") {
                            $filtroFecha .= " AND pe.fecha >= '".$fecha."' ";
                          }
                          $filtroFechah="";
                          if ($fechah!="") {
                            $filtroFechah .= " AND pe.fecha <= '".$fechah."' ";
                          }

                          $filtroEstado = "";
                          if ($filtrarPorEstado) {
                            $filtroEstado = " AND ep.id IN (".implode(', ', array_map('intval', $id_estado)).") ";
                          }

                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                          $sql1 = "SELECT pe.id, s.nro_sitio, s.nro_subsitio, p.nro, pe.fecha, p.fecha_entrega, (SELECT MIN(c.fecha_emision) FROM compras c WHERE c.id_pedido = pe.id) AS fecha_pactada_prov, ep.estado, ep.id AS id_estado, cu.nombre AS solicitante, pe.aprobado, p.id AS id_proyecto, p.nombre AS nombre_proyecto, pe.id_computo, e.empresa
                          FROM pedidos pe 
                            INNER JOIN computos c ON c.id = pe.id_computo 
                            INNER JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante
                            INNER JOIN tareas t ON t.id = c.id_tarea 
                            INNER JOIN proyectos p ON p.id = t.id_proyecto 
                            LEFT JOIN sitios s ON s.id = p.id_sitio 
                            LEFT JOIN empresas e ON e.id = s.id_empresa
                            INNER JOIN estados_pedidos ep ON ep.id = pe.id_estado 
                          WHERE 1 ".$filtroNroPedido.$filtroNro.$filtroFecha.$filtroFechah.$filtroEstado;

                          foreach ($pdo->query($sql1) as $row) {
                            $empresa_corta = !empty($row['empresa']) ? ' ('.substr($row['empresa'], 0, 4).')' : '';
                            $obra = htmlspecialchars($row['nro_sitio']).'_'.htmlspecialchars($row['nro_subsitio']).'_'.htmlspecialchars($row['nro']).$empresa_corta;
                            $fecha_entrega_valida = ($row['fecha_entrega'] && $row['fecha_entrega'] != '0000-00-00');
                            $fecha_pactada_valida = ($row['fecha_pactada_prov'] && $row['fecha_pactada_prov'] != '0000-00-00');

                            $nombre_proyecto = htmlspecialchars($row['nombre_proyecto']);
                            ?>
                            <tr>
                              <td><?=htmlspecialchars($row['id'])?></td>
                              <td><?=$obra?></td>
                              <td class="truncate-project"><?=$nombre_proyecto?></td>
                              <td>
                                <span style="display: none;"><?=date('Ymd', strtotime($row['fecha']))?></span>
                                <?=date('d/m/Y', strtotime($row['fecha']))?></td>
                              <td>
                                <span style="display: none;"><?=($fecha_entrega_valida ? date('Ymd', strtotime($row['fecha_entrega'])) : 0)?></span>
                                <?=($fecha_entrega_valida ? date('d/m/Y', strtotime($row['fecha_entrega'])) : 'N/A') ?>
                              </td>
                              <td>
                                <span style="display: none;"><?=($fecha_pactada_valida ? date('Ymd', strtotime($row['fecha_pactada_prov'])) : 0)?></span>
                                <?=($fecha_pactada_valida ? date('d/m/Y', strtotime($row['fecha_pactada_prov'])) : 'N/A') ?>
                              </td>
                              <td><?=htmlspecialchars($row['estado']) ?></td>
                              <td class="truncate-solicitante"><?=htmlspecialchars($row['solicitante'] ?? '') ?></td>
                              <td><?php
                                if($row['id_computo']>0){ ?>
                                  <a href="imprimirComputo.php?id=<?=$row['id_computo']?>" target="_blank" title="Ver Computo">
                                    <i class="fa fa-file-text-o" style="margin-right: 5px;"></i>Computo <?=$row['id_computo']?>
                                  </a><?php
                                } else {
                                  echo "Computo";
                                } ?>
                              </td>
                              <td style="display: none;"><?=htmlspecialchars($row['id_proyecto']) ?></td>
                              <td style="display: none;"><?=htmlspecialchars($row['id_estado']) ?></td>
                            </tr><?php
                          }

                          $sql2 = "SELECT pe.id, s.nro_sitio, s.nro_subsitio, p.nro, pe.fecha, p.fecha_entrega, 
                          (SELECT MIN(c.fecha_emision) FROM compras c WHERE c.id_pedido = pe.id) AS fecha_pactada_prov, 
                          ep.estado, ep.id AS id_estado, 
                          cu.nombre AS solicitante, 
                          pe.aprobado, pe.id_proyecto, p.nombre AS nombre_proyecto, e.empresa
                          FROM pedidos pe 
                            INNER JOIN proyectos p ON p.id = pe.id_proyecto 
                            LEFT JOIN sitios s ON s.id = p.id_sitio 
                            LEFT JOIN empresas e ON e.id = s.id_empresa
                            INNER JOIN estados_pedidos ep ON ep.id = pe.id_estado 
                            LEFT JOIN cuentas cu ON cu.id = pe.id_cuenta_solicitante 
                          WHERE pe.id_computo IS NULL ".$filtroNroPedido.$filtroNro.$filtroFecha.$filtroFechah.$filtroEstado;
                            
                          foreach ($pdo->query($sql2) as $row) {
                            $empresa_corta = !empty($row['empresa']) ? ' ('.substr($row['empresa'], 0, 4).')' : '';
                            $obra=htmlspecialchars($row['nro_sitio']).'_'.htmlspecialchars($row['nro_subsitio']).'_'.htmlspecialchars($row['nro']).$empresa_corta;
                            $fecha_entrega_valida = ($row['fecha_entrega'] && $row['fecha_entrega'] != '0000-00-00');
                            $fecha_pactada_valida = ($row['fecha_pactada_prov'] && $row['fecha_pactada_prov'] != '0000-00-00');
                            
                            $nombre_proyecto = htmlspecialchars($row['nombre_proyecto']);
                            ?>

                            <tr>
                            <td><?=htmlspecialchars($row['id'])?></td>
                            <td><?=$obra?></td>
                            <td class="truncate-project"><?=$nombre_proyecto?></td>
                            <td>
                              <span style="display: none;"><?=date('Ymd', strtotime($row['fecha']))?></span>
                              <?=date('d/m/Y', strtotime($row['fecha'])) ?>
                            </td>
                            <td>
                              <span style="display: none;"><?=($fecha_entrega_valida ? date('Ymd', strtotime($row['fecha_entrega'])) : 0) ?></span>
                              <?=($fecha_entrega_valida ? date('d/m/Y', strtotime($row['fecha_entrega'])) : 'N/A') ?>
                            </td>
                            <td>
                              <span style="display: none;"><?=($fecha_pactada_valida ? date('Ymd', strtotime($row['fecha_pactada_prov'])) : 0) ?></span>
                              <?=($fecha_pactada_valida ? date('d/m/Y', strtotime($row['fecha_pactada_prov'])) : 'N/A') ?>
                            </td>
                            <td><?=htmlspecialchars($row['estado']) ?></td>
                            <td class="truncate-solicitante"><?=htmlspecialchars($row['solicitante'] ?? '') ?></td>
                            <td>Directo</td>
                            <td style="display: none;"><?=htmlspecialchars($row['id_proyecto']) ?></td>
                            <td style="display: none;"><?=htmlspecialchars($row['id_estado']) ?></td>
                            </tr><?php
                          }
                          Database::disconnect();?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <th style="width: 40px;">Nro.</th>
                            <th style="width: 100px;">Proyecto</th>
                            <th class="truncate-project">Nombre Proyecto</th>
                            <th style="width: 85px;">F. de Carga</th>
                            <th style="width: 85px;">F. Pedida</th>
                            <th style="width: 85px;">F. Entrega</th>
                            <th style="width: 80px;">Estado</th>
                            <th class="truncate-solicitante">Solicitante</th>
                            <th style="width: 60px;">Tipo</th>
                            <th style="display: none;">Proy</th>
                            <th style="display: none;">Estado ID</th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Conceptos</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th class="truncate-concepto">Concepto</th>
                            <th style="width: 70px;">Requerido</th>
                            <th style="width: 70px;">Comprado</th>
                            <th style="width: 70px;">Entregado</th>
                            <th style="width: 70px;">Reservado</th>
                            <th style="width: 85px;">F. Necesidad</th>
                            <th style="width: 100px;">F. Última Compra</th>
                            <th style="width: 90px;">Costo Último Precio</th>
                            <th style="width: 90px;">Estado</th>
                            <th style="width: 50px;">Acciones</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                          <tr>
                            <th class="truncate-concepto">Concepto</th>
                            <th style="width: 70px;">Requerido</th>
                            <th style="width: 70px;">Comprado</th>
                            <th style="width: 70px;">Entregado</th>
                            <th style="width: 70px;">Reservado</th>
                            <th style="width: 85px;">F. Necesidad</th>
                            <th style="width: 100px;">F. Última Compra</th>
                            <th style="width: 90px;">Costo Último Precio</th>
                            <th style="width: 90px;">Estado</th>
                            <th style="width: 50px;">Acciones</th>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
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
  
    <!-- Modales únicos -->
    <div class="modal fade" id="aprobarModal" tabindex="-1" role="dialog" aria-labelledby="aprobarModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="aprobarModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea aprobar el pedido?</div>
          <div class="modal-footer">
            <a href="#" id="btnAprobarPedido" class="btn btn-primary">Aprobar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="rechazarModal" tabindex="-1" role="dialog" aria-labelledby="rechazarModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="rechazarModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea rechazar el pedido?</div>
          <div class="modal-footer">
            <a href="#" id="btnRechazarPedido" class="btn btn-primary">Rechazar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
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
      get_conceptos(0);

      // Setup - add a text input to each footer cell
      $('#dataTables-example666 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      } );

      $('#id_estado').on('change', function() {
        var currentValues = $(this).val() || [];
        
        if (currentValues.includes('todos') && currentValues.length > 1) {
          $(this).val(['todos']).trigger('change.select2');
          return;
        }
        
      });

      $('#dataTables-example666').DataTable({
        stateSave: false,
        responsive: false,
        autoWidth: false,
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
        },
        initComplete: function(){
          $('[title]').tooltip();
          addTitleToTruncated();
        }
      });
  
      // DataTable
      var table = $('#dataTables-example666').DataTable();
  
      // Apply the search
      table.columns().every( function () {
        var that = this;
        $( 'input', this.footer() ).on( 'keyup change', function () {
          if ( that.search() !== this.value ) {
            that.search( this.value ).draw();
          }
        });
      });
        
      $("#link_ver_pedido").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un pedido para verlo")
        }
      });
      let selectedPedidoInfo = null;

      function getSelectedPedidoEstadoId() {
        return selectedPedidoInfo ? selectedPedidoInfo.estadoId : null;
      }

      $("#link_gestionar_pedido").on("click",function(e){
        if(!selectedPedidoInfo){
          e.preventDefault();
          alert("Por favor seleccione un pedido para gestionarlo");
          return false;
        }

        const estadoId = $(this).data("estadoId");
        if(!['3','4'].includes(String(estadoId || ''))){
          e.preventDefault();
          alert("El pedido debe estar aprobado para gestionarlo");
          return false;
        }
      });
      $("#link_modificar_pedido").on("click",function(e){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          e.preventDefault();
          alert("Por favor seleccione un pedido directo para modificar");
          return false;
        }
        
        if(selectedPedidoInfo && !['1','2'].includes(String(selectedPedidoInfo.estadoId))){
          e.preventDefault();
          alert('Solo se pueden modificar pedidos en estado "Pendiente" o "Para aprobar"');
          return false;
        }
      });
      $("#link_aprobar_pedido").on("click",function(e){
        if(!selectedPedidoInfo){
          e.preventDefault();
          alert("Por favor seleccione un pedido para aprobar");
          return false;
        }
        if(selectedPedidoInfo.estadoId != 2){
          e.preventDefault();
          alert('Solo se pueden aprobar pedidos en estado "Para aprobar"');
          return false;
        }
      });
      $("#link_rechazar_pedido").on("click",function(e){
        if(!selectedPedidoInfo){
          e.preventDefault();
          alert("Por favor seleccione un pedido para rechazar");
          return false;
        }
        if(selectedPedidoInfo.estadoId != 2){
          e.preventDefault();
          alert('Solo se pueden rechazar pedidos en estado "Para aprobar"');
          return false;
        }
      });
      $("#link_nuevo_suceso").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un pedido para añadir un nuevo suceso")
        }
      });
      
      $(document).on("click", "#dataTables-example666 tbody tr", function() {
        var t = $(this);
        var table = $('#dataTables-example666').DataTable();

        let id_pedido = t.find("td:nth-child(1)").html()?.trim() || '';
        let estado = t.find("td:nth-child(7)").html()?.trim() || ''; 
        let tipo = t.find("td:nth-child(9)").html()?.trim() || ''; 
        let id_proyecto = t.find("td:nth-child(10)").html()?.trim() || ''; 
        let estadoId = t.find("td:nth-child(11)").html()?.trim() || ''; 

        if (t.hasClass('selected')) {
          t.removeClass('selected');
          $('#dataTables-example667').DataTable().clear().draw();
          $("#link_ver_pedido").attr("href", "#");
          $("#link_gestionar_pedido").attr("href", "#");
          $("#link_modificar_pedido").attr("href", "#");
          $("#link_nuevo_suceso").attr("href", "#");
          $("#link_aprobar_pedido").removeAttr("data-toggle").removeAttr("data-target").attr("href", "#");
          $("#link_rechazar_pedido").removeAttr("data-toggle").removeAttr("data-target").attr("href", "#");
          
          $("#btnAprobarPedido").attr("href", "#");
          $("#btnRechazarPedido").attr("href", "#");
          selectedPedidoInfo = null;
          $("#link_gestionar_pedido").removeData("estadoId").removeData("tipo").removeData("pedidoId");
        } else {
          table.$('tr.selected').removeClass('selected');
          t.addClass('selected');
          get_conceptos(id_pedido);

          if (tipo === 'Directo' && ['1','2'].includes(String(estadoId))) {
            $("#link_modificar_pedido").attr("href", "itemsPedidoDirecto.php?id=" + id_pedido);
            $("#link_ver_pedido").attr("href", "verPedido.php?id=" + id_pedido);
          } else {
            $("#link_modificar_pedido").attr("href", "#");
            $("#link_ver_pedido").attr("href", "verPedido.php?id=" + id_pedido);
          }

          if (estadoId == 2) { 
            $("#link_aprobar_pedido").attr("data-toggle", "modal").attr("data-target", "#aprobarModal");
            $("#link_rechazar_pedido").attr("data-toggle", "modal").attr("data-target", "#rechazarModal");
            
            $("#btnAprobarPedido").attr("href", "aprobarPedido.php?id=" + id_pedido);
            $("#btnRechazarPedido").attr("href", "rechazarPedido.php?id=" + id_pedido);
          } else {
            $("#link_aprobar_pedido").removeAttr("data-toggle").removeAttr("data-target").attr("href", "#");
            $("#link_rechazar_pedido").removeAttr("data-toggle").removeAttr("data-target").attr("href", "#");
          }

          selectedPedidoInfo = {
            id: id_pedido,
            estado: estado,
            estadoId: estadoId,
            tipo: tipo
          };

          $("#link_gestionar_pedido").data("estadoId", estadoId).data("tipo", tipo).data("pedidoId", id_pedido);

          if (estado === 'Aprobado' || estado === 'Gestionando' || ['3','4'].includes(estadoId)) {
            $("#link_gestionar_pedido").attr("href", "modificarCompra.php?id_pedido=" + id_pedido);
          } else {
            $("#link_gestionar_pedido").attr("href", "#");
          }

          $("#link_nuevo_suceso").attr("href", "nuevoSuceso.php?entidad_tipo=pedidos&entidad_id=" + selectedPedidoInfo.id);
        }
      });
        
    });
  
    function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }

    function get_conceptos(id_pedido){
      let datosUpdate = new FormData();
      datosUpdate.append('id_pedido', id_pedido);
      $.ajax({
        data: datosUpdate,
        url: 'get_conceptos_pedido.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(data){
          
          $('#dataTables-example667').DataTable().destroy();
          $('#dataTables-example667').DataTable({
            stateSave: false,
            responsive: false,
            autoWidth: false,
            data: data,
            createdRow: function(row, data, dataIndex) {
              $(row).find('td:eq(0)').addClass('truncate-concepto');
            },
            drawCallback: function() {
              setTimeout(initializeAllTooltips, 100);
              if (typeof feather !== 'undefined') {
                feather.replace();
              }
            },
            columnDefs: [
              { targets: 0, className: 'truncate-concepto', width: '30%' },
              { targets: 1, width: '12%', className: 'text-right' },
              { targets: 2, width: '10%', className: 'text-right' },
              { targets: 3, width: '10%', className: 'text-right' },
              { targets: 4, width: '10%', className: 'text-right' },
              { targets: 5, width: '12%' },
              { targets: 6, width: '10%' },
              { targets: 7, width: '8%', className: 'text-right' },
              { targets: 8, width: '12%' },
              { targets: 9, width: '8%', className: 'text-center' } 
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
      
          var table = $('#dataTables-example667').DataTable();
          table.columns().every( function () {
            var that = this;
            $( 'input', this.footer() ).on( 'keyup change', function () {
              if ( that.search() !== this.value ) {
                that.search( this.value ).draw();
              }
            });
          });

          setTimeout(addTitleToTruncated, 100);
        },
        error: function(xhr, status, error) {
            console.error("Error cargando conceptos:", error);
            console.log("Respuesta del servidor:", xhr.responseText);
        }
      });
    }

    function addTitleToTruncated() {
      $('.truncate-project, .truncate-solicitante, .truncate-concepto, #dataTables-example667 th').tooltip('dispose');
      
      $('.truncate-project, .truncate-solicitante, .truncate-concepto, #dataTables-example667 th').each(function() {
        var element = $(this);
        if (!element.hasClass('badge') && !element.find('.badge').length) {
          element.removeAttr('title').removeAttr('data-original-title').removeAttr('aria-describedby');
        }
        if (this.scrollWidth > this.offsetWidth) {
          element.attr('title', element.text().trim());
        }
      });
      
      $('.truncate-project[title], .truncate-solicitante[title], .truncate-concepto[title], #dataTables-example667 th[title]').not('.badge').tooltip({
        placement: 'top',
        trigger: 'hover',
        delay: { show: 300, hide: 100 },
        boundary: 'viewport',
        fallbackPlacement: ['top', 'bottom'],
        flip: false
      });
    }

    $(window).on('resize', function() {
      setTimeout(addTitleToTruncated, 100);
    });
    
    function initializeAllTooltips() {
      $('.badge[data-toggle="tooltip"]').tooltip({
        container: 'body',
        boundary: 'window',
        trigger: 'hover',
        html: false
      });
      addTitleToTruncated();
    }

    $('#dataTables-example666, #dataTables-example667').on('draw.dt', function() {
      setTimeout(initializeAllTooltips, 50);
    });
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <!-- Plugin used-->
  </body>
</html>
