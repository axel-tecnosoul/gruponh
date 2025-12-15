<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
require_once 'manejarFiltros.php';

$filters = gestionarFiltros('listarCompras');

$nro_ocnp = $filters['nro_ocnp'] ?? "";
$nro_pedido = $filters['nro_pedido'] ?? "";
$nro = $filters['nro'] ?? "";
$proveedor = $filters['proveedor'] ?? "";
$fecha = $filters['fecha'] ?? "";
$fechah = $filters['fechah'] ?? "";
$id_estado = $filters['id_estado'] ?? [];
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
        width: 30%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .truncate-provider {
        width: 25%;
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
      #dataTables-example666 td.truncate-provider {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      
      /* Tabla de conceptos de compras */
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
      .truncate-header {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
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
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Compras ";
          include_once("head_page.php")?>
          <div class="container-fluid"><?php
          
            if (isset($_SESSION['flash_message'])) {
              $flash_message = $_SESSION['flash_message'];
              $alert_class = ($flash_message['type'] == 'success') ? 'alert-success' : 'alert-danger';?>
              <div class="alert <?= $alert_class ?> alert-dismissible fade show" role="alert">
                <?= $flash_message['message'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              </div><?php
              unset($_SESSION['flash_message']);
            }?>

            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-body">
                    <form class="form-inline theme-form mt-3" name="form1" method="post" action="listarCompras.php" >
                      <div class='form-group mb-12' style='width: 100%;'>
                        <div class="form-group mb-0">
                          N.OC/Rev:&nbsp;
                          <input class="form-control" size="3" type="text" value="<?=htmlspecialchars($nro_ocnp)?>" name="nro_ocnp" placeholder="ej: 123, 123/A" title="Buscar por ID de OC o ID/Revisión">
                        </div>
                        <div class="form-group mb-0">
                          N.Ped.:&nbsp;
                          <input class="form-control" size="3" type="text" value="<?=htmlspecialchars($nro_pedido)?>" name="nro_pedido" placeholder="ej: 456" title="Buscar por Número de Pedido">
                        </div>
                        <div class="form-group mb-0">
                          N.Sitio/N.Proy:&nbsp;
                          <input class="form-control" size="3" type="text" value="<?=htmlspecialchars($nro)?>" name="nro">
                        </div>
                        <div class="form-group mb-0">
                          Proveedor:&nbsp;
                          <input class="form-control" size="15" type="text" value="<?=htmlspecialchars($proveedor)?>" name="proveedor">
                        </div>
                        <div class="form-group mb-0">
                          Rango:&nbsp;
                          <input class="form-control" size="20" type="date" value="<?=htmlspecialchars($fecha)?>" name="fecha">-
                          <input class="form-control" size="20" type="date" value="<?=htmlspecialchars($fechah)?>" name="fechah">
                        </div>
                        <div class="form-group mb-0">
                          Estado:&nbsp;
                          <select name="id_estado[]" id="id_estado[]" class="js-example-basic-multiple" multiple="multiple">
                            <option value="">Todos</option><?php
                            $pdo = Database::connect();
                            $sqlZon = "SELECT id, estado FROM estados_compra WHERE 1 ORDER BY id ASC";
                            foreach ($pdo->query($sqlZon) as $fila) {
                              $selected = in_array($fila['id'], $id_estado) ? " selected" : "";?>
                              <option value='<?= $fila['id'] ?>'<?= $selected ?>><?= $fila['estado'] ?></option><?php
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                        <div class="form-group mb-0">
                          <button type="submit" class="btn btn-primary">Buscar</button>
                          <a href="listarCompras.php?clear_filters=1" class="btn btn-secondary ml-2">Limpiar</a>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <div class="row" style='min-width: 100%;'>
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo $ubicacion; ?>&nbsp;&nbsp;
                      <a href="#" id="link_ver_compra"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp;
                      <!-- Botón Imprimir Agregado -->
                      <a href="#" id="link_imprimir_compra"><img src="img/print.png" width="24" height="25" border="0" alt="Imprimir OC" title="Imprimir OC"></a>&nbsp;&nbsp;
                      <a href="exportCompras.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(299))) {?>
                        <a href="#" id="link_modificar_compra"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificación/Revisión O.C" title="Modificación/Revisión O.C"></a>&nbsp;&nbsp;
                        <a href="#" id="link_ingresar_compra"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Ingresar Stock" title="Ingresar Stock"></a>&nbsp;&nbsp;<?
                      }
                      if (!empty(tienePermiso(384))) {?>
                        <a href="#" id="link_aprobar_compra"><img src="img/aprobar.png" width="24" height="25" border="0" alt="Aprobar" title="Aprobar"></a>&nbsp;&nbsp;
                        <a href="#" id="link_rechazar_compra"><img src="img/neg.png" width="24" height="25" border="0" alt="Rechazar" title="Rechazar"></a>&nbsp;&nbsp;<?php
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
                            <th class="d-none">ID</th>
                            <th>Nro.OC / Rev</th>
                            <th>Proyecto</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>F.Emisión</th>
                            <th>F.Entrega</th>
                            <!-- Columna Total Agregada -->
                            <th>Total</th>
                            <th style="display: none;">Proy</th>
                            <th style="display: none;">Estado ID</th>
                          </tr>
                        </thead>
                        <tbody><?php
                          $pdo = Database::connect();
                          // Aseguramos que mo.moneda y c.total estén en la consulta
                          $sql = "SELECT c.id, cu.nombre, DATE_FORMAT(c.fecha_emision,'%d/%m/%y') AS fecha_emision_formatted, e.estado, c.nro_oc, c.total, pe.lugar_entrega, s.nro_sitio, p.nro, p.nombre AS nombre_proyecto, mo.moneda, c.nro_revision, DATE_FORMAT(c.fecha_entrega,'%d/%m/%y') AS fecha_entrega_formatted, DATE_FORMAT(c.fecha_emision,'%y%m%d') AS fecha_emision, DATE_FORMAT(c.fecha_entrega,'%y%m%d') AS fecha_entrega, t.id_proyecto, s.nro_subsitio, c.id_estado_compra FROM compras c LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_proveedor LEFT JOIN estados_compra e ON e.id = c.id_estado_compra INNER JOIN pedidos pe ON pe.id = c.id_pedido INNER JOIN computos co ON co.id = pe.id_computo INNER JOIN tareas t ON t.id = co.id_tarea INNER JOIN proyectos p ON p.id = t.id_proyecto INNER JOIN sitios s ON s.id = p.id_sitio LEFT JOIN monedas mo ON mo.id = c.id_moneda WHERE 1 ";
                          $params = [];
                          if (!empty($nro)) {
                            $sql .= " AND (p.nro = ? OR s.nro_sitio = ?)";
                            $params[] = $nro;
                            $params[] = $nro;
                          }
                          if (!empty($nro_ocnp)) {
                            $nro_ocnp_trimmed = trim($nro_ocnp);
                            $ex=explode("/", $nro_ocnp_trimmed);
                            if(count($ex)>1){
                              $id_oc = $ex[0];
                              $nro_revision = $ex[1];
                              $sql .= " AND (c.nro_revision = ? AND c.id = ? ) ";
                              $params[] = $nro_revision;
                              $params[] = $id_oc;
                              
                            }else{
                              $sql .= " AND c.id = ? ";
                              $params[] = intval($nro_ocnp_trimmed);
                            }
                          }
                          if (!empty($nro_pedido)) {
                            $nro_pedido_trimmed = trim($nro_pedido);
                            $sql .= " AND pe.id = ?";
                            $params[] = $nro_pedido_trimmed;
                          }
                          if (!empty($fecha)) {
                            $sql .= " AND c.fecha_emision >= ?";
                            $params[] = $fecha;
                          }
                          if (!empty($fechah)) {
                            $sql .= " AND c.fecha_emision <= ?";
                            $params[] = $fechah;
                          }

                          if (!empty($id_estado) && !empty($id_estado[0])) {
                            $placeholders = implode(',', array_fill(0, count($id_estado), '?'));
                            $sql .= " AND e.id IN (" . $placeholders . ")";
                            $params = array_merge($params, $id_estado);
                          }
                          if (!empty($proveedor)) {
                            $sql .= " AND cu.nombre LIKE ?";
                            $params[] = '%' . $proveedor . '%';
                          }
                          $q = $pdo->prepare($sql);
                          $q->execute($params);

                          $results = $q->fetchAll(PDO::FETCH_ASSOC);
                          $unique_ids = [];
                          foreach ($results as $row) {
                            if (in_array($row['id'], $unique_ids)) {
                              continue;
                            }
                            $unique_ids[] = $row['id'];?>

                            <tr>
                              <td class="d-none"><?=$row['id']?></td>
                              <td><?=$row['nro_oc']?> / <?=$row['nro_revision']?></td>
                              <td><?=$row['nombre_proyecto']?></td>
                              <td><?=$row['nombre']?></td>
                              <td><?=$row['estado']?></td>
                              <td><span style="display: none;"><?=$row["fecha_emision"]?></span><?=$row["fecha_emision_formatted"]?></td>
                              <td><span style="display: none;"><?=$row["fecha_entrega"]?></span><?=$row["fecha_entrega_formatted"]?></td>
                              <!-- Valor Total Agregado -->
                              <td><?=$row['moneda'] . ' ' . number_format($row['total'], 2, ',', '.')?></td>
                              <td style="display: none;"><?=$row['id_proyecto']?></td>
                              <td style="display: none;"><?=$row['id_estado_compra']?></td>
                            </tr><?php
                          }
                          Database::disconnect();?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Nro.OC / Rev</th>
                            <th>Proyecto</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>F.Emisión</th>
                            <th>F.Entrega</th>
                            <th>Total</th>
                            <th style="display: none;">Proy</th>
                            <th style="display: none;">Estado ID</th>
                          </tr>
                        </tfoot>
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
                    <h5>Conceptos</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th class="truncate-concepto">Concepto</th>
                            <th style="width: 90px;">Cantidad</th>
                            <th style="width: 80px;">F. Entrega</th>
                            <th style="width: 70px;">Kg Totales</th>
                            <th style="width: 60px;">$/Kg</th>
                            <th style="width: 70px;">$/Unitario</th>
                            <th style="width: 80px;">Subtotal</th>
                            <th style="width: 60px;">% Desc.</th>
                            <th style="width: 80px;">Total c/Desc</th>
                            <th style="width: 70px;">Entregado</th>
                            <th style="width: 70px;">Remitos</th>
                            <th style="width: 70px;">Facturas</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th class="truncate-concepto">Concepto</th>
                            <th style="width: 90px;">Cantidad</th>
                            <th style="width: 80px;">F. Entrega</th>
                            <th style="width: 70px;">Kg Totales</th>
                            <th style="width: 60px;">$/Kg</th>
                            <th style="width: 70px;">$/Unitario</th>
                            <th style="width: 80px;">Subtotal</th>
                            <th style="width: 60px;">% Desc.</th>
                            <th style="width: 80px;">Total c/Desc</th>
                            <th style="width: 70px;">Entregado</th>
                            <th style="width: 70px;">Remitos</th>
                            <th style="width: 70px;">Facturas</th>
                          </tr>
                        </tfoot>
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

    <!-- Modales únicos -->
    <div class="modal fade" id="aprobarModal" tabindex="-1" role="dialog" aria-labelledby="aprobarModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="aprobarModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea aprobar la OC?</div>
          <div class="modal-footer">
            <a href="#" id="btnAprobarCompra" class="btn btn-primary">Aprobar</a>
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
          <div class="modal-body">¿Está seguro que desea rechazar la OC?</div>
          <div class="modal-footer">
            <a href="#" id="btnRechazarCompra" class="btn btn-primary">Rechazar</a>
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
    <script>
      // Configuración global para tooltips
      if (typeof $ !== 'undefined' && $.fn.tooltip && $.fn.tooltip.Constructor) {
        $.fn.tooltip.Constructor.Default = $.extend({}, $.fn.tooltip.Constructor.Default, {
          placement: 'top',
          trigger: 'hover',
          delay: { show: 300, hide: 100 },
          boundary: 'viewport',
          fallbackPlacement: ['top', 'bottom'],
          flip: false
        });
      }
    </script>
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
    $(document).ready(function() {
      get_conceptos(0);

      let selectedCompraInfo = null;
      
      // Setup - add a text input to each footer cell
      $('#dataTables-example666 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      } );
	    
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
        }
      });
 
      var table = $('#dataTables-example666').DataTable();
      
      setTimeout(addTitleToTruncated, 100);

      table.columns().every( function () {
        var that = this;
        $( 'input', this.footer() ).on( 'keyup change', function () {
          if ( that.search() !== this.value ) {
            that.search( this.value ).draw();
          }
        });
		  });
		
	    $("#link_ver_compra").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una compra para ver detalle")
        }
      });

      // Acción Imprimir
	    $("#link_imprimir_compra").on("click",function(e){
        e.preventDefault();
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una compra para imprimir");
          return false;
        }
        window.open(this.href, '_blank');
      });

      // Acción Modificar (Revisada)
	    $("#link_modificar_compra").on("click",function(e){
        let l=document.location.href;
        // Validación de selección
        if(this.href==l || this.href==l+"#" || !selectedCompraInfo){
          e.preventDefault();
          alert("Por favor seleccione una compra para modificar/revisar");
          return false;
        }
        // Aquí podrías agregar validaciones de estado extra si es necesario
      });

      // Acción Ingresar Stock (Revisada)
	    $("#link_ingresar_compra").on("click",function(e){
        let l=document.location.href;
        // Validación combinada: selección + estado
        if(this.href==l || this.href==l+"#" || !selectedCompraInfo){
          e.preventDefault();
          alert("Por favor seleccione una compra para ingresar stock");
          return false;
        } 
        
        // Verificación estricta del estado
        const estadoActual = parseInt(selectedCompraInfo.id_estado);
        const estadosValidos = [3, 5, 6]; // 3: Enviada/Aprobada, 5: Pendiente, 6: Pendiente Parcial

        if(!estadosValidos.includes(estadoActual)){
          e.preventDefault();
          alert("Solo se puede ingresar stock en compras con estados válidos (Enviada, Pendiente, Pendiente Parcial)");
          return false;
        }
      });

	    $("#link_adjuntar_factura").on("click",function(e){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          if(!selectedCompraInfo){
            alert("Por favor seleccione una compra para adjuntar factura");
          } else if(![3, 5, 6, 7, 8, 9].includes(parseInt(selectedCompraInfo.id_estado))){
            e.preventDefault();
            alert("Solo se pueden adjuntar facturas en compras con estados válidos");
            return false;
          }
        }
      });

      // Manejo de clicks en filas
      $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
		
        // Recuperar datos de la fila con los índices actualizados (Total agregado en col 8)
        let id_compra=t.find("td:first-child").html();
        let estado = t.find("td:nth-child(5)").html(); 
        // Indices actualizados debido a la nueva columna Total
        let id_proyecto=t.find("td:nth-child(9)").html(); // Antes 10 (con total es 9 si total es col 8, espera...)
        // Estructura actual:
        // 1: ID (none)
        // 2: Nro OC
        // 3: Proyecto
        // 4: Proveedor
        // 5: Estado
        // 6: F Emision
        // 7: F Entrega
        // 8: Total (NUEVO)
        // 9: Proy ID (none)
        // 10: Estado ID (none)

        id_proyecto=t.find("td:nth-child(9)").html(); 
        let id_estado_compra=t.find("td:nth-child(10)").html(); 
		
        if(t.hasClass('selected')){
          deselectRow(t);
          $('#dataTables-example667').DataTable().clear().draw();
          $("#link_ver_compra").attr("href","#");
          $("#link_imprimir_compra").attr("href","#"); // Reset Imprimir
          $("#link_modificar_compra").attr("href","#");
		      $("#link_ingresar_compra").attr("href","#");
          $("#link_adjuntar_factura").attr("href","#");
		      $("#link_nuevo_suceso").attr("href","#");
          $("#link_nuevo_pago").attr("href","#");
          $("#link_aprobar_compra").removeAttr("data-toggle").removeAttr("data-target").attr("href","#");
          $("#link_rechazar_compra").removeAttr("data-toggle").removeAttr("data-target").attr("href","#");
          
          selectedCompraInfo = null;
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_conceptos(id_compra)
          $("#link_ver_compra").attr("href","verCompra.php?id="+id_compra);
          $("#link_imprimir_compra").attr("href","imprimirCompra.php?id="+id_compra); // Set Imprimir
          $("#link_modificar_compra").attr("href","modificarCompra.php?id="+id_compra);
          
          // Lógica visual para Ingresar Stock
          if ([3, 5, 6].includes(parseInt(id_estado_compra))) {
            $("#link_ingresar_compra").attr("href","ingresarCompra.php?id="+id_compra);
          } else {
            $("#link_ingresar_compra").attr("href","#"); // Deshabilitar visualmente el link
          }
          
          if ([3, 5, 6, 7, 8, 9].includes(parseInt(id_estado_compra))) {
            $("#link_adjuntar_factura").attr("href","adjuntarFactura.php?id="+id_compra);
            $("#link_nuevo_pago").attr("href","nuevoPago.php?id="+id_compra);
          } else {
            $("#link_adjuntar_factura").attr("href","#");
            $("#link_nuevo_pago").attr("href","#");
          }
        
        if (id_estado_compra == 2) {
            $("#link_aprobar_compra").attr("data-toggle","modal").attr("data-target","#aprobarModal");
            $("#btnAprobarCompra").attr("href", "aprobarCompra.php?id=" + id_compra);
        } else {
            $("#link_aprobar_compra").removeAttr("data-toggle").removeAttr("data-target").attr("href","#");
        }
        
        if (id_estado_compra == 2) {
            $("#link_rechazar_compra").attr("data-toggle","modal").attr("data-target","#rechazarModal");
            $("#btnRechazarCompra").attr("href", "rechazarCompra.php?id=" + id_compra);
        } else {
            $("#link_rechazar_compra").removeAttr("data-toggle").removeAttr("data-target").attr("href","#");
        }
            
        selectedCompraInfo = {
          id: id_compra,
          estado: estado,
          id_proyecto: id_proyecto,
          id_estado: id_estado_compra
        };

		      $("#link_nuevo_suceso").attr("href","nuevoSuceso.php?entidad_tipo=compras&entidad_id="+selectedCompraInfo.id);
        }
      });
	  } );
	
	  function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }

    function get_conceptos(id_compra){
      let datosUpdate = new FormData();
      datosUpdate.append('id_compra', id_compra);
      $.ajax({
        data: datosUpdate,
        url: 'get_conceptos_compra.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          console.log(data);
          data = JSON.parse(data);
          console.log(data);

          $('#dataTables-example667').DataTable().destroy();
          $('#dataTables-example667').DataTable({
            stateSave: false,
            responsive: false,
            autoWidth: false,
            data: data,
            columnDefs: [
              { "width": "auto", "targets": 0, "className": "truncate-concepto" },
              { "width": "90px", "targets": 1 },
              { "width": "80px", "targets": 2 },
              { "width": "70px", "targets": 3 , "className": "text-right"},
              { "width": "60px", "targets": 4 , "className": "text-right"},
              { "width": "70px", "targets": 5 , "className": "text-right"},
              { "width": "80px", "targets": 6 , "className": "text-right"},
              { "width": "60px", "targets": 7 , "className": "text-right"},
              { "width": "80px", "targets": 8 , "className": "text-right"},
              { "width": "70px", "targets": 9 },
              { "width": "70px", "targets": 10 },
              { "width": "70px", "targets": 11 }
            ],
            drawCallback: function() {
              setTimeout(function() {
                addTitleToTruncated();
              }, 100);
            },
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

          setTimeout(function() {
            addTitleToTruncated();
          }, 200);
          
        }
      });
    }

    function addTitleToTruncated() {
      $('.truncate-project, .truncate-provider, .truncate-concepto, #dataTables-example667 th').tooltip('dispose');
      $('.truncate-project, .truncate-provider, .truncate-concepto, #dataTables-example667 th').each(function() {
        var element = $(this);
        element.removeAttr('title').removeAttr('data-original-title').removeAttr('aria-describedby');
        if (this.scrollWidth > this.offsetWidth) {
          element.attr('title', element.text().trim());
        }
      });
      $('.truncate-project[title], .truncate-provider[title], .truncate-concepto[title], #dataTables-example667 th[title]').tooltip({
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
    
    $('#dataTables-example666, #dataTables-example667').on('draw.dt', function() {
      setTimeout(function() {
        addTitleToTruncated();
      }, 50);
    });
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
  </body>
</html>