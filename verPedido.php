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
        $sql = "SELECT pe.`id`, pe.`id_computo`, c.id_tarea, c.id_cuenta_solicitante, pe.`fecha`, pe.`lugar_entrega`, pe.`id_cuenta_recibe`,pe.aprobado FROM `pedidos` pe inner join computos c on c.id = pe.`id_computo` WHERE pe.id = ? ";
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
          $ubicacion="Gestión de  Pedido";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
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
							$sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from computos c inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio where c.id = ".$data['id_computo'];
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo " selected>".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Solicitante</label>
							<div class="col-sm-9">
							<select name="id_cuenta_solicitante" id="id_cuenta_solicitante" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$data['id']."'";
								if ($fila['id'] == $data['id_cuenta_solicitante']) {
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
									$sql = " SELECT pd.id, m.concepto, pd.cantidad, date_format(pd.fecha_necesidad,'%d/%m/%y'), u.unidad_medida,pd.id_material,pd.reservado,pd.comprado FROM pedidos_detalle pd inner join materiales m on m.id = pd.id_material inner join unidades_medida u on u.id = pd.id_unidad_medida WHERE pd.id_pedido = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										$sql2 = "SELECT d.precio,date_format(c.fecha_emision,'%d/%m/%y') fecha_emision FROM compras_detalle d inner join compras c on c.id = d.id_compra WHERE d.id_material = ".$row[5]." order by c.id desc limit 0,1 ";
										$q2 = $pdo->prepare($sql2);
										$q2->execute();
										$data2 = $q2->fetch(PDO::FETCH_ASSOC);
										
										echo '<tr>';
										if ($row["cantidad"]-$row["reservado"]-$row["comprado"] > 0) {
											echo '<td><input type="checkbox" class="no-sort customer-selector" value="'.$row[0].'" /> </td>';	
										} else {
											echo '<td>&nbsp;</td>';
                      //echo '<td>'.$row["cantidad"]." - ".$row["reservado"]." - ".$row["comprado"].'</td>';
                      //echo '<td><input type="checkbox" class="no-sort customer-selector" value="'.$row[0].'" /> </td>';
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
						<a class="btn btn-primary" target="_blank" href="imprimirPedido.php?id=<?php echo $data['id']; ?>">Imprimir</a>
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
            window.location.href= window.location.href.replace("verPedido.php?id=<?php echo $id;?>", "nuevaCompra.php?id=<?php echo $id;?>&conceptos=" + arr.join(",") );
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