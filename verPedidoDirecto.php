<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';

    $id = null;
    if (!empty($_GET['id'])) {
        $id = $_REQUEST['id'];
    }
    
    if (null==$id) {
        header("Location: listarPedidos.php");
    }
    
    if (!empty($_POST)) {
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT pe.`id`, pe.`fecha`, pe.`lugar_entrega`, pe.`id_cuenta_recibe`, pe.`aprobado`, pe.`id_estado`, ep.`estado` AS estado_pedido, p.id idproyecto FROM `pedidos` pe INNER JOIN proyectos p ON p.id = pe.`id_proyecto` INNER JOIN estados_pedidos ep ON ep.id = pe.id_estado WHERE pe.id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        
        Database::disconnect();
    }
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
    <?php include('header.php');?>
    
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
    <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Gestión de Pedido Directo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between w-100">
                      <div>
                        <h5 class="mb-1"><?=$ubicacion?></h5>
                        <span class="badge badge-secondary">Estado: <?=htmlspecialchars($data['estado_pedido']);?></span>
                      </div>
                      <?php if ($data['id_estado'] == 1 && tienePermiso(298)): ?>
                        <button type="button" class="btn btn-primary mt-2 mt-sm-0" id="btnEnviarAprobacion">Enviar a aprobación</button>
                      <?php endif; ?>
                    </div>
                    <div id="estado-error" class="alert alert-danger mt-3 d-none"></div>
                  </div>
					<form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <h6 class="mb-3">Datos del Pedido Directo</h6>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Pedido(*)</label>
							<div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha'];?>" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
							<select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" disabled="disabled">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0 and p.id_estado_proyecto = 2";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['idproyecto']) {
									echo " selected ";
								}	
								echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Lugar de Entrega(*)</label>
							<div class="col-sm-9"><input name="lugar_entrega" type="text" maxlength="199" class="form-control" required="required" value="<?php echo $data['lugar_entrega'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Recibe(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_recibe" id="id_cuenta_recibe" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_cuenta_recibe']) {
										echo " selected ";
									}
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th><input type="checkbox" data-orderable="false" class="no-sort toggle-checkboxes" /></th>
									  <th>Concepto</th>
									  <th>Fecha Necesidad</th>
									  <th>Fecha Última Compra</th>
									  <th>Costo Última Precio</th>
									  <th>Requerido</th>
									  <th>Stock</th>
									  <th>Reservado</th>
									  <th>Comprado</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, date_format(d.`fecha_necesidad`,'%d/%m/%y'), u.`unidad_medida`,d.id_material,d.reservado,d.comprado FROM `pedidos_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_pedido = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										$sql2 = "SELECT d.`precio`,date_format(c.`fecha_emision`,'%d/%m/%y') fecha_emision FROM `compras_detalle` d inner join compras c on c.id = d.id_compra WHERE d.id_material = ".$row[5]." order by c.id desc limit 0,1 ";
										$q2 = $pdo->prepare($sql2);
										$q2->execute();
										$data2 = $q2->fetch(PDO::FETCH_ASSOC);
										
										echo '<tr>';
										if ($row[2]-$row[6]-$row[7] > 0) {
											echo '<td><input type="checkbox" class="no-sort customer-selector" value="'.$row[0].'" /> </td>';	
										} else {
											echo '<td>&nbsp;</td>';	
										}
										echo '<td>'. $row[1] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										if (!empty($data2['fecha_emision'])) {
											echo '<td>'. $data2['fecha_emision'] . '</td>';	
										} else {
											echo '<td>&nbsp;</td>';	
										}
										if (!empty($data2['precio'])) {
											echo '<td>$'. number_format($data2['precio'],2) . '</td>';	
										} else {
											echo '<td>&nbsp;</td>';	
										}
										echo '<td>'. $row[2] .' '.$row[4]. '</td>';		
										
										/*$sql = "SELECT `disponible` FROM `stock` WHERE `id_material` = ? ";
										$q = $pdo->prepare($sql);
										$q->execute([$row[5]]);
										$data3 = $q->fetch(PDO::FETCH_ASSOC);*/

										$sql = "SELECT SUM(id.saldo) AS disponible FROM ingresos_detalle id WHERE id_material = ? ";
										$q = $pdo->prepare($sql);
										$q->execute([$row[5]]);
										$data3 = $q->fetch(PDO::FETCH_ASSOC);
										
										if (empty($data3)) {
											echo '<td>0</td>';	
										} else {
											echo '<td>'.$data3['disponible'].'</td>';	
										}
										
										echo '<td>'. $row[6] . '</td>';
										echo '<td>'. $row[7] . '</td>';
										
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
							  </table>
							</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<?php 
						if ($data['aprobado']==1) {
						?>
						<?php if(tienePermiso(298)){?><a class="btn btn-warning" id="compra-masivo">Nueva Orden de Compra</a><?php }?>
						<?php 
						} 
						?>
						<a class="btn btn-primary" target="_blank" href="imprimirPedidoDirecto.php?id=<?php echo $data['id']; ?>">Imprimir</a>
                        <a href="#" onclick="document.location.href='listarPedidos.php'" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
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
    <!-- Modal Enviar a aprobación -->
    <div class="modal fade" id="modalEnviarAprobacion" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirmar envío a aprobación</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>¿Desea enviar este pedido a aprobación?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="confirmEnviarAprobacion">Confirmar</button>
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
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
   <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	<script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
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
	<script>
                $(document).ready(function() {

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

        var pedidoId = <?=intval($data['id']);?>;

        $('#btnEnviarAprobacion').on('click', function () {
            $('#estado-error').addClass('d-none');
            $('#modalEnviarAprobacion').modal('show');
        });

        $('#modalEnviarAprobacion').on('hidden.bs.modal', function () {
            $('#confirmEnviarAprobacion').prop('disabled', false);
        });

        $('#confirmEnviarAprobacion').on('click', function () {
            var $button = $(this);
            $button.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: 'modificarEstadoPedido.php',
                data: { idEstado: 2, idPosicion: pedidoId },
                success: function (response) {
                    var trimmed = $.trim(response || '');
                    var pattern = new RegExp('^2\\s*-\\s*' + pedidoId + '$');
                    if (pattern.test(trimmed)) {
                        window.location.href = 'listarPedidos.php';
                    } else {
                        mostrarErrorEstado('No se pudo actualizar el estado. Respuesta inesperada del servidor.');
                    }
                },
                error: function () {
                    mostrarErrorEstado('No se pudo actualizar el estado. Intente nuevamente.');
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        });

        function mostrarErrorEstado(mensaje) {
            $('#modalEnviarAprobacion').modal('hide');
            var $error = $('#estado-error');
            $error.text(mensaje).removeClass('d-none');
        }

        } );

                </script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
	
	<!-- Page-Level Demo Scripts - Tables - Use for reference -->
    <script>
    
    jQuery('.customer-selector').on('click', function () {
        jQuery('.toggle-checkboxes').prop('checked', false);
    });

    jQuery('#compra-masivo').on('click', function (e) {
        e.preventDefault();
        if (jQuery('.customer-selector:checked').length < 1) {
            alert("Debe seleccionar al menos un concepto");
        } else {
            var arr = [];
            jQuery('.customer-selector:checked').each(function (i,o) { arr.push(jQuery(o).val()); });
            window.location.href= window.location.href.replace("verPedidoDirecto.php?id=<?php echo $id;?>", "nuevaCompra.php?id=<?php echo $id;?>&conceptos=" + arr.join(",") );
        }

    });
	
	var toggle = true;
    jQuery('.toggle-checkboxes').on('click', function (e) {
        e.preventDefault();
        jQuery('.customer-selector').prop('checked', toggle);
        toggle = !toggle;

    })
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>