<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
include 'database.php';
require_once 'manejarFiltros.php';
$filters = gestionarFiltros('listarFacturasCompra');
$nro = $filters['nro'] ?? "";
$fecha = $filters['fecha'] ?? "";
$fechah = $filters['fechah'] ?? "";
$proveedor = $filters['proveedor'] ?? "";
$tipo_letra = $filters['tipo_letra'] ?? "";
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
  </style>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <!-- Page Header Start-->
      <?php include('header.php');?>
     
      <!-- Page Header Ends                              -->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Facturas Compra ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarFacturasCompra.php">
					  <div class="form-group mb-0">
						Nro:&nbsp;<input class="form-control" size="3" type="text" value="<?= htmlspecialchars($nro) ?>" name="nro">
					  </div>
					  <div class="form-group mb-0">
						Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?= htmlspecialchars($fecha) ?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?= htmlspecialchars($fechah) ?>" name="fechah">
					  </div>
					  <div class="form-group mb-0">
						Proveedor:&nbsp;<input class="form-control" size="20" type="text" value="<?= htmlspecialchars($proveedor) ?>" name="proveedor">
					  </div>
					  <div class="form-group mb-0">
						Tipo:&nbsp;
						<select name="tipo_letra" class="form-control">
							<option value="">Todos</option>
							<?php
							$pdoTL = Database::connect();
							$qTL = $pdoTL->prepare("SELECT DISTINCT CONCAT(tc.tipo, ' ', lc.letra) as tipo_letra FROM facturas_compra fc INNER JOIN tipos_comprobante tc ON tc.id = fc.id_tipo_comprobante INNER JOIN letras_comprobante lc ON lc.id = fc.id_letra_comprobante WHERE 1 ORDER BY tipo_letra");
							$qTL->execute();
							while ($filaTL = $qTL->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$filaTL['tipo_letra']."'";
								if ($tipo_letra === $filaTL['tipo_letra']) echo " selected";
								echo ">".$filaTL['tipo_letra']."</option>";
							}
							Database::disconnect();
							?>
						</select>
					  </div>
					  <div class="form-group mb-0">
						Estado:&nbsp;
						<select name="id_estado[]" id="id_estado[]" class="js-example-basic-multiple" multiple="multiple">
							<option value="">Todos</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_factura` WHERE 1 ORDER BY id ASC";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (in_array($fila['id'], $id_estado)) {
									echo " selected ";
								}
								echo ">".$fila['estado']."</option>";
							}
							Database::disconnect();
							?>
							</select>
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarFacturasCompra.php'">Buscar</button>
						<a href="listarFacturasCompra.php?clear_filters=1" class="btn btn-secondary ml-2">Limpiar</a>
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
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(336))) { ?><a href="#" id="btn_nueva_factura_compra" title="Nueva Factura"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva"></a><?php } ?>
					&nbsp;&nbsp;
				<?php 
				if (!empty(tienePermiso(338))) {
					echo '<a href="#" id="link_modificar_fc"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar/Anular" title="Modificar/Anular"></a>';
					echo '&nbsp;&nbsp;';
				}
				if (!empty(tienePermiso(338))) {
					echo '<a href="#" id="link_pagar_fc"><img src="img/tratoHecho.png" width="24" height="25" border="0" alt="Marcar Pagada" title="Marcar Pagada"></a>';
					echo '&nbsp;&nbsp;';
				}
				if (!empty(tienePermiso(336))) {
						/* echo '<a href="#" id="link_nuevo_detalle_fc"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Añadir ítem Detalle" title="Añadir ítem Detalle"></a>';
						echo '&nbsp;&nbsp;';
						echo '<a href="#" id="link_nuevo_retencion_fc"><img src="img/edit3.png" width="24" height="25" border="0" alt="Añadir Retenciones" title="Añadir Retenciones"></a>';
						echo '&nbsp;&nbsp;'; */
					}
					if (!empty(tienePermiso(342))) {
						echo '<a href="#" id="link_exportar_fc"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>';
						echo '&nbsp;&nbsp;';									
						echo '<a href="#" id="link_exportar_bejerman_fc"><img src="img/import.png" width="24" height="25" border="0" alt="Bejerman TXT" title="Bejerman TXT"></a>';
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
                              <th style="width:1%; white-space:nowrap"><input type="checkbox" id="select-all-fc"></th>
							  <th>ID</th>
							  <th>Descripción</th>
							  <th>Tipo</th>
							  <th>Número</th>
							  <th>Proveedor</th>
							  <th>Fecha</th>
							  <th>Condición</th>
							  <th>Total</th>
							  <th>Estado</th>
							  <th>Exportada</th>
							  <th>Pagada</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                           $hasSearched = isset($_SESSION['filtros_listarFacturasCompra']);
                           if ($hasSearched):
                            $pdo = Database::connect();
                             $sql = " SELECT fc.`id`, fc.`descripcion`, tc.`tipo`, lc.`letra`, fc.`numero`, c.razon_social, date_format(fc.`fecha_emitida`,'%d/%m/%y'), fp.forma_pago, fc.`total`, m.`moneda`, ef.estado, date_format(fc.`fecha_emitida`,'%y%m%d'), ef.id, fc.pagada, fc.exportada FROM `facturas_compra` fc inner join tipos_comprobante tc on tc.id = fc.`id_tipo_comprobante` inner join letras_comprobante lc on lc.id = fc.`id_letra_comprobante` inner join cuentas c on c.id = fc.`id_cuenta_origen` inner join formas_pago fp on fp.id = fc.`id_condicion_pago` inner join monedas m on m.id = fc.`id_moneda` inner join estados_factura ef on ef.id = fc.`id_estado` WHERE 1 ";
                            $params = [];

                            if (!empty($nro)) {
                                $sql .= " AND fc.numero = ? ";
                                $params[] = $nro;
                            }
                            if (!empty($fecha)) {
                                $sql .= " AND fc.fecha_emitida >= ? ";
                                $params[] = $fecha;
                            }
                            if (!empty($fechah)) {
                                $sql .= " AND fc.fecha_emitida <= ? ";
                                $params[] = $fechah;
                            }
                            if (!empty($proveedor)) {
                                $sql .= " AND c.razon_social LIKE ? ";
                                $params[] = '%' . $proveedor . '%';
                            }
                            if (!empty($tipo_letra)) {
                                $sql .= " AND CONCAT(tc.tipo, ' ', lc.letra) = ? ";
                                $params[] = $tipo_letra;
                            }
                            if (!empty($id_estado[0])) {
                                $placeholders = implode(',', array_fill(0, count($id_estado), '?'));
                                $sql .= " AND ef.id IN ($placeholders) ";
                                $params = array_merge($params, $id_estado);
                            }

                            $q = $pdo->prepare($sql);
                            $q->execute($params);
                            foreach ($q as $row) {
                                echo '<tr data-id-estado="'. $row[12] .'" data-pagada="'. (int)$row[13] .'" data-exportada="'. (int)$row[14] .'">';
                                echo '<td class="text-center"><input type="checkbox" class="chk-factura" value="'. $row[0] . '"></td>';
								echo '<td>'. $row[0] . '</td>';
                                echo '<td>'. $row[1] . '</td>';
                                echo '<td>'. $row[2] . ' ' . $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
                                echo '<td><span style="display: none;">'. $row[11] . '</span>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
                                echo '<td class="text-right">'. $row[9] . ' ' . number_format($row[8] ?? 0,2) . '</td>';
                                echo '<td>'. $row[10] . '</td>';
                                echo '<td class="text-center">' . ($row[14] ? 'Sí' : 'No') . '</td>';
                                echo '<td class="text-center">' . ((int)$row[13] ? 'Sí' : 'No') . '</td>';
                                echo '</tr>';
                            }
							Database::disconnect();
                          endif;
                          ?>
                        </tbody>
						<tfoot>
                          <tr>
                              <th style="width:1%"></th>
							  <th>ID</th>
							  <th>Descripción</th>
							  <th>Tipo</th>
							  <th>Número</th>
							  <th>Proveedor</th>
							  <th>Fecha</th>
							  <th>Condición</th>
							  <th>Total</th>
							  <th>Estado</th>
							  <th>Exportada</th>
							  <th>Pagada</th>
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
                    <h5>Detalles de Factura
					&nbsp;&nbsp;
					<?php 
					if (!empty(tienePermiso(338))) {
						echo '<a href="#" id="link_modificar_detalle_fc"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
						echo '<a href="#" id="link_eliminar_detalle_fc"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
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
							  <th>Descripción</th>
							  <th>Precio</th>
							  <th>Cantidad</th>
							  <th>Subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
						<tfoot>
                          <tr>
							  <th>ID</th>
							  <th>Descripción</th>
							  <th>Precio</th>
							  <th>Cantidad</th>
							  <th>Subtotal</th>
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
  <div class="modal fade" id="modalExportarFC" tabindex="-1" role="dialog" aria-labelledby="modalExportarFCLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalExportarFCLabel">Exportar facturas de compra</h5>
          <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
        <div class="modal-body">
          <p>Hay <strong id="cantSeleccionadosFC">0</strong> facturas seleccionadas.</p>
          <p>¿Qué desea exportar?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="btnExportarSeleccionadosFC">Exportar seleccionados</button>
          <button type="button" class="btn btn-secondary" id="btnExportarTodosFC">Exportar todos</button>
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        </div>
      </div>
    </div>
  </div>
  <?php
    $pdo = Database::connect();
    $sql = " SELECT `id`, `id_factura_compra`, `cantidad`, `precio`, `subtotal` FROM `facturas_compra_detalle` WHERE 1 ";
    foreach ($pdo->query($sql) as $row) {
    ?>
  <div class="modal fade" id="eliminarModalDetalle_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelDetalle" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabelDetalle">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea eliminar el Ítem de Detalle de la Factura de Compra?</div>
      <div class="modal-footer">
      <a href="eliminarDetalleFacturaCompra.php?id=<?php echo $row[0]; ?>&fc=<?php echo $row[1]; ?>" class="btn btn-primary">Eliminar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <?php
    }
    Database::disconnect();
    ?>

<!-- Modal Elegir Proveedor y OC para Nueva Factura de Compra -->
<div class="modal fade" id="modalElegirProveedorOC" tabindex="-1" role="dialog" aria-labelledby="modalElegirProveedorOCLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalElegirProveedorOCLabel">Seleccionar Proveedor y Orden de Compra</h5>
        <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">

        <!-- PASO 1: Selección de Proveedor -->
        <div id="pasoProveedor">
          <div class="form-group">
            <input type="text" id="buscarProveedor" class="form-control" placeholder="Filtrar proveedores...">
          </div>
          <div id="listaProveedores" style="max-height: 400px; overflow-y: auto;">
            <div class="text-center py-3"><i>Cargando proveedores...</i></div>
          </div>
        </div>

        <!-- PASO 2: Selección de OC (oculto inicialmente) -->
        <div id="pasoOC" style="display:none;">
          <div class="d-flex align-items-center mb-3">
            <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="btnVolverProveedores">
              &larr; Volver
            </button>
            <div>
              <strong id="labelProveedorSeleccionado"></strong><br>
              <small class="text-muted">Seleccione una o más órdenes de compra para crear la factura</small>
              <span id="infoMonedaSel" class="text-info ml-2" style="font-size:0.85em;display:none;"></span>
            </div>
          </div>
          <div id="listaOC" style="max-height: 400px; overflow-y: auto;">
            <div class="text-center py-3"><i>Cargando órdenes de compra...</i></div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnContinuarConOC" style="display:none;">
          Continuar con OC seleccionadas
        </button>
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function() {
    // Mostrar errores pasados por URL
    var urlParams = new URLSearchParams(window.location.search);
    var errorMsg = urlParams.get('error');
    if (errorMsg) {
      alert(decodeURIComponent(errorMsg));
    }

    // Setup - add a text input to each footer cell
    $('#dataTables-example666 tfoot th').each( function (i) {
        if (i === 0) return;
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example666').DataTable({
        stateSave: false,
		searching: false,
		
        responsive: false,
		dom: 'Bfrtp<"bottom"l>',
        buttons: [
            'excel'
        ],
		lengthMenu: [
        [10, 25, 50, 100, 500, 1000], // Cantidades de registros disponibles
        [10, 25, 50, 100, 500, 1000]  // Texto mostrado en el menú desplegable
		],
        columnDefs: [{
            targets: 0,
            width: '1%',
            className: 'dt-center'
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
        }}
      });
 
    // DataTable
    var table = $('#dataTables-example666').DataTable();
 
    // Apply the search
    table.columns().every( function () {
        var that = this;
 
        $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
                that
                    .search( this.value )
                    .draw();
            }
        } );
		} );
		
	
	  $("#link_modificar_fc").on("click",function(e){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          if ($('#dataTables-example666 tbody tr.selected').length === 0) {
            alert("Por favor seleccione una Factura de Compra para modificar");
          } else {
            var est = $('#dataTables-example666 tbody tr.selected').data('id-estado');
            var exp = $('#dataTables-example666 tbody tr.selected').data('exportada');
            var pag = $('#dataTables-example666 tbody tr.selected').data('pagada');
            var motivo = exp ? 'exportada' : (pag ? 'pagada' : 'definitiva');
            alert("Esta factura ya fue " + motivo + " y no puede editarse.");
          }
          e.preventDefault();
        }
      })
	  $("#link_nuevo_detalle_fc").on("click",function(e){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          if ($('#dataTables-example666 tbody tr.selected').length === 0) {
            alert("Por favor seleccione una Factura de Compra para añadir ítem de detalle");
          } else {
            alert("Esta factura ya fue exportada y no puede editarse.");
          }
          e.preventDefault();
        }
      })
	  $("#link_nuevo_retencion_fc").on("click",function(e){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          if ($('#dataTables-example666 tbody tr.selected').length === 0) {
            alert("Por favor seleccione una Factura de Compra para añadir ítem de retención");
          } else {
            alert("Esta factura ya fue exportada y no puede editarse.");
          }
          e.preventDefault();
        }
      })
	  $("#link_pagar_fc").on("click",function(e){
        e.preventDefault();
        var id = $(this).data('id-fc');
        if (!id) {
          alert("Seleccione una factura definitiva no pagada.");
          return;
        }
        if (confirm("¿Está seguro que desea marcar esta factura como pagada?")) {
          window.location.href = 'pagarFacturaCompra.php?id=' + id;
        }
      })
	   
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();

        let id_fc=t.find("td:nth-child(2)").html();
        let id_estado = parseInt(t.data('id-estado'));
        let pagada = parseInt(t.data('pagada'));
        let exportada = parseInt(t.data('exportada'));

        if(t.hasClass('selected')){
          deselectRow(t);
           get_detalles(id_fc)
          $("#link_modificar_fc").attr("href","#");
		      $("#link_nuevo_detalle_fc").attr("href","#");
			  $("#link_nuevo_retencion_fc").attr("href","#");
			  $("#link_pagar_fc").attr("href","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_detalles(id_fc)
          // Modificar / agregar items: solo si no es definitiva, pagada ni exportada
          if (id_estado !== 3 && pagada !== 1 && exportada !== 1) {
            $("#link_modificar_fc").attr("href","nuevaFacturaCompra.php?id="+id_fc);
            $("#link_nuevo_detalle_fc").attr("href","nuevoDetalleFacturaCompra.php?id="+id_fc);
            $("#link_nuevo_retencion_fc").attr("href","nuevaRetencionFacturaCompra.php?id="+id_fc);
          } else {
            $("#link_modificar_fc").attr("href","#");
            $("#link_nuevo_detalle_fc").attr("href","#");
            $("#link_nuevo_retencion_fc").attr("href","#");
          }
          // Marcar pagada: solo definitivas no pagadas
          if (id_estado === 3 && pagada !== 1) {
            $("#link_pagar_fc").attr("href","#").data('id-fc', id_fc).data('pagada', 0);
          } else {
            $("#link_pagar_fc").attr("href","#").data('id-fc', '').data('pagada', 1);
          }
        }
      });
    
	} );
	
	$('#select-all-fc').on('change', function() {
		var checked = this.checked;
		$('.chk-factura').each(function() {
			if (!$(this).prop('disabled')) $(this).prop('checked', checked);
		});
	});

	$('#link_exportar_fc').on('click', function(e) {
		e.preventDefault();
		var ids = $('.chk-factura:checked').map(function() { return $(this).val(); }).get();
		if (ids.length === 0) {
			window.open('exportFacturasCompra.php', '_blank');
			return;
		}
		$('#modalExportarFCLabel').text('Exportar facturas de compra (Excel)');
		$('#cantSeleccionadosFC').text(ids.length);
		$('#modalExportarFC').modal('show');
		$('#btnExportarSeleccionadosFC').off('click').on('click', function() {
			window.open('exportFacturasCompra.php?ids=' + ids.join(','), '_blank');
			$('#modalExportarFC').modal('hide');
		});
		$('#btnExportarTodosFC').off('click').on('click', function() {
			window.open('exportFacturasCompra.php', '_blank');
			$('#modalExportarFC').modal('hide');
		});
	});

	$('#link_exportar_bejerman_fc').on('click', function(e) {
		e.preventDefault();
		var ids = $('.chk-factura:checked').map(function() { return $(this).val(); }).get();
		if (ids.length === 0) {
			window.open('exportFacturasCompraBejerman.php', '_blank');
			return;
		}
		$('#modalExportarFCLabel').text('Exportar facturas de compra (Bejerman)');
		$('#cantSeleccionadosFC').text(ids.length);
		$('#modalExportarFC').modal('show');
		$('#btnExportarSeleccionadosFC').off('click').on('click', function() {
			window.open('exportFacturasCompraBejerman.php?ids=' + ids.join(','), '_blank');
			$('#modalExportarFC').modal('hide');
		});
		$('#btnExportarTodosFC').off('click').on('click', function() {
			window.open('exportFacturasCompraBejerman.php', '_blank');
			$('#modalExportarFC').modal('hide');
		});
	});

	$(document).on('click', '#dataTables-example666 tbody .chk-factura', function(e) {
		e.stopPropagation();
	});
	
	function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }
    
    </script>
	<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example667 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
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
        }}
      });
 
    // DataTable
    var table = $('#dataTables-example667').DataTable();
 
    // Apply the search
    table.columns().every( function () {
        var that = this;
 
        $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
                that
                    .search( this.value )
                    .draw();
            }
        } );
		} );
	} );
	
    function get_detalles(id_fc){
      let datosUpdate = new FormData();
      datosUpdate.append('id_fc', id_fc);
      $.ajax({
        data: datosUpdate,
        url: 'get_detalles_factura_compra.php',
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
            data: data,
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
          var table = $('#dataTables-example667').DataTable();
          // Apply the search
          table.columns().every( function () {
            var that = this;
            $( 'input', this.footer() ).on( 'keyup change', function () {
              if ( that.search() !== this.value ) {
                that
                  .search( this.value )
                  .draw();
              }
            });
          });
		  
		  $("#link_modificar_detalle_fc").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un detalle para modificar")
			}
		  })
		  
		  $("#link_eliminar_detalle_fc").on("click",function(){
			/*let l=document.location.href;
			if(this.href==l || this.href==l+"#"){*/
      let target=this.dataset.target;
      if(target==undefined || target=="#"){
			  alert("Por favor seleccione un detalle para eliminar")
			}
		  })
		  
          //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
      $(document).on("click","#dataTables-example667 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_detalle=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_modificar_detalle_fc").attr("href","#");
          $("#link_eliminar_detalle_fc").attr("data-target","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_modificar_detalle_fc").attr("href","modificarDetalleFacturaCompra.php?id="+id_detalle);
          $("#link_eliminar_detalle_fc").attr("data-toggle","modal");
          $("#link_eliminar_detalle_fc").attr("data-target","#eliminarModalDetalle_"+id_detalle);
        }
		  });
          
        }
      });
    }
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
	<script src="assets/js/select2/select2.full.min.js"></script>
<script src="assets/js/select2/select2-custom.js"></script>

<script>
// --- Modal elegir proveedor y OC para nueva factura de compra ---
function htmlEsc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

var idsOCSeleccionadas = [];

$('#btn_nueva_factura_compra').on('click', function(e) {
  e.preventDefault();
  idsOCSeleccionadas = [];
  $('#pasoProveedor').show();
  $('#pasoOC').hide();
  $('#btnContinuarConOC').hide();
  $('#buscarProveedor').val('');
  $('#listaProveedores').html('<div class="text-center py-3"><i>Cargando proveedores...</i></div>');
  $('#modalElegirProveedorOC').modal('show');

  // Cargar proveedores con OC pendientes
  $.ajax({
    url: 'get_oc_pendientes_proveedor.php',
    method: 'POST',
    dataType: 'json',
    success: function(proveedores) {
      if (!proveedores || proveedores.length === 0) {
        $('#listaProveedores').html('<div class="alert alert-info mb-0">No hay proveedores con órdenes de compra pendientes de facturar.</div>');
        return;
      }
      var html = '<table class="table table-hover table-sm" id="tablaProveedores"><thead class="thead-light"><tr>';
      html += '<th>Proveedor</th><th>CUIT</th><th class="text-center">OC Pendientes</th>';
      html += '</tr></thead><tbody>';
      $.each(proveedores, function(i, prov) {
        html += '<tr class="fila-proveedor" style="cursor:pointer;" data-id="' + prov.id + '" data-nombre="' + htmlEsc(prov.razon_social) + '">';
        html += '<td>' + htmlEsc(prov.razon_social) + '</td>';
        html += '<td>' + htmlEsc(prov.cuit) + '</td>';
        html += '<td class="text-center"><span class="badge badge-primary">' + prov.cant_oc + '</span></td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
      $('#listaProveedores').html(html);
    },
    error: function() {
      $('#listaProveedores').html('<div class="alert alert-danger">Error al cargar proveedores.</div>');
    }
  });
});

// Filtro de búsqueda de proveedores
$('#buscarProveedor').on('keyup', function() {
  var val = $(this).val().toLowerCase();
  $('.fila-proveedor').each(function() {
    $(this).toggle($(this).text().toLowerCase().indexOf(val) !== -1);
  });
});

// Click en un proveedor: cargar sus OC pendientes
$(document).on('click', '.fila-proveedor', function() {
  var idProveedor = $(this).data('id');
  var nombreProveedor = $(this).data('nombre');
  idsOCSeleccionadas = [];

  $('#labelProveedorSeleccionado').text(nombreProveedor);
  $('#listaOC').html('<div class="text-center py-3"><i>Cargando órdenes de compra...</i></div>');
  $('#pasoProveedor').hide();
  $('#pasoOC').show();
  $('#btnContinuarConOC').show().prop('disabled', true);

  $.ajax({
    url: 'get_oc_pendientes_proveedor.php',
    method: 'POST',
    data: { id_proveedor: idProveedor },
    dataType: 'json',
    success: function(ordenes) {
      if (!ordenes || ordenes.length === 0) {
        $('#listaOC').html('<div class="alert alert-info mb-0">Este proveedor no tiene órdenes de compra pendientes de facturar.</div>');
        $('#btnContinuarConOC').hide();
        $('#infoMonedaSel').hide();
        return;
      }

      $('#infoMonedaSel').hide();

      var html = '<div class="list-group">';
      $.each(ordenes, function(i, oc) {
        var ocMoneda = oc.moneda || 'N/A';
        var ocId = 'oc_' + oc.id;
        html += '<div class="list-group-item p-0 border-0 mb-1 item-oc" data-moneda="' + htmlEsc(ocMoneda) + '">';

        // Cabecera con checkbox y datos principales
        html += '<div class="d-flex align-items-center p-2 bg-light rounded" style="cursor:pointer;" data-toggle="collapse" data-target="#collapse_oc_' + oc.id + '">';
        html += '<input type="checkbox" class="checkbox-oc mr-2" id="' + ocId + '" value="' + oc.id + '" onclick="event.stopPropagation()">';
        html += '<label for="' + ocId + '" class="mb-0 mr-2 font-weight-bold" style="color:#000;" onclick="event.stopPropagation()">OC #' + htmlEsc(oc.nro_oc) + '</label>';
        html += '<span class="badge badge-secondary mr-2">' + htmlEsc(oc.estado) + '</span>';
        html += '<small class="text-dark mr-auto">' + htmlEsc(oc.fecha_emision);
        if (oc.fecha_entrega) {
          html += ' &rarr; ' + htmlEsc(oc.fecha_entrega);
        }
        html += '</small>';
        if (oc.total) {
          html += '<small class="text-dark ml-2"><strong>' + htmlEsc(oc.moneda) + ' ' + parseFloat(oc.total).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</strong></small>';
        }
        html += '<i class="feather icon-chevron-down ml-2"></i>';
        html += '</div>';

        // Panel colapsable con detalle de items
        html += '<div id="collapse_oc_' + oc.id + '" class="collapse">';
        html += '<div class="p-2 border rounded mt-1 bg-white">';
        if (oc.detalles && oc.detalles.length > 0) {
          html += '<table class="table table-sm table-bordered mb-0">';
          html += '<thead class="thead-light"><tr>';
          html += '<th>Material</th>';
          html += '<th class="text-right">Cantidad</th>';
          html += '<th class="text-right">Entregado</th>';
          html += '<th>Unidad</th>';
          html += '<th class="text-right">Precio</th>';
          html += '<th class="text-right">Subtotal</th>';
          html += '</tr></thead><tbody>';
          $.each(oc.detalles, function(j, det) {
            html += '<tr>';
            html += '<td>' + htmlEsc(det.descripcion) + '</td>';
            html += '<td class="text-right">' + parseFloat(det.cantidad).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
            html += '<td class="text-right">' + parseFloat(det.entregado).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
            html += '<td>' + htmlEsc(det.unidad_medida) + '</td>';
            html += '<td class="text-right">$ ' + parseFloat(det.precio).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
            html += '<td class="text-right">$ ' + parseFloat(det.subtotal).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
            html += '</tr>';
          });
          html += '</tbody></table>';
        } else {
          html += '<small class="text-muted">Sin detalles disponibles.</small>';
        }
        if (oc.comentarios) {
          html += '<div class="mt-1"><small><strong>Comentarios:</strong> ' + htmlEsc(oc.comentarios) + '</small></div>';
        }
        html += '</div></div>';
        html += '</div>';
      });
      html += '</div>';
      $('#listaOC').html(html);
    },
    error: function() {
      $('#listaOC').html('<div class="alert alert-danger">Error al cargar órdenes de compra.</div>');
    }
  });
});

// Al seleccionar/deseleccionar OC: grisar las de moneda distinta
$(document).on('change', '.checkbox-oc', function() {
  var checked = $('.checkbox-oc:checked');

  if (checked.length === 0) {
    $('.item-oc').css('opacity', '1').find('.checkbox-oc').prop('disabled', false);
    $('#infoMonedaSel').hide();
  } else {
    var monedaSel = checked.first().closest('.item-oc').data('moneda');
    $('.item-oc').each(function() {
      var itemMoneda = $(this).data('moneda');
      if (itemMoneda === monedaSel) {
        $(this).css('opacity', '1').find('.checkbox-oc').prop('disabled', false);
      } else {
        $(this).css('opacity', '0.4').find('.checkbox-oc').prop('disabled', true).prop('checked', false);
      }
    });
    $('#infoMonedaSel').text('OC en ' + monedaSel + ' seleccionada — OCs en otras monedas deshabilitadas').show();
  }

  idsOCSeleccionadas = $('.checkbox-oc:checked:not(:disabled)').map(function() { return $(this).val(); }).get();
  $('#btnContinuarConOC').prop('disabled', idsOCSeleccionadas.length === 0);
});

// Volver a la lista de proveedores
$('#btnVolverProveedores').on('click', function() {
  $('#pasoOC').hide();
  $('#btnContinuarConOC').hide();
  $('#infoMonedaSel').hide();
  $('.item-oc').css('opacity', '1').find('.checkbox-oc').prop('disabled', false);
  $('#pasoProveedor').show();
  idsOCSeleccionadas = [];
});

// Continuar: ir al formulario de nueva factura con las OC seleccionadas
$('#btnContinuarConOC').on('click', function() {
  idsOCSeleccionadas = $('.checkbox-oc:checked:not(:disabled)').map(function() { return $(this).val(); }).get();
  if (idsOCSeleccionadas.length === 0) {
    alert('Por favor seleccione al menos una orden de compra.');
    return;
  }
  $('#modalElegirProveedorOC').modal('hide');
  window.location.href = 'nuevaFacturaCompra.php?oc=' + idsOCSeleccionadas.join(',');
});
</script>

    <!-- Plugin used-->
  </body>
</html>