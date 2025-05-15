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
        header("Location: listarComputos.php");
    }
    
    if (!empty($_POST)) {
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `nro_revision`, `id_tarea`, `fecha`, `id_cuenta_solicitante`, `id_estado` FROM `computos` WHERE id = ? ";
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
          $ubicacion="Gestión de Cómputo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
          <form class="form theme-form" role="form" method="post" name="form1" id="form1" action="modificarComputo.php?id=<?php echo $data['id']; ?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha(*)</label>
							<div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha'];?>" class="form-control" readonly="readonly"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tarea(*)</label>
							<div class="col-sm-9">
							<select name="id_tarea" id="id_tarea" class="js-example-basic-single col-sm-12" disabled="disabled">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT t.id,tt.tipo,t.observaciones FROM `tareas` t inner join tipos_tarea tt on tt.id = t.id_tipo_tarea WHERE t.`anulado` = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (!empty($_GET['id'])) {
									if ($fila['id'] == $data['id_tarea']) {
										echo " selected ";
									}									
								}
								echo ">".$fila['tipo']." / ".$fila['observaciones']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Realizó</label>
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
								echo "<option value='".$fila['id']."'";
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
							<label class="col-sm-3 col-form-label">Estado</label>
							<div class="col-sm-9">
							<select name="id_estado" id="id_estado" class="js-example-basic-single col-sm-12" disabled="disabled">
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_computos` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_estado']) {
									echo " selected ";
								}
								echo ">".$fila['estado']."</option>";
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
									  <th>Concepto</th>
									  <th>Necesidad</th>
									  <th>Solicitado</th>
									  <th>En Stock</th>
									  <th>Reservado</th>
									  <th>Pedido</th>
									  <th>Saldo</th>
									  <th>Aprobado</th>
									  <th>Solicitar</th>
									  <th>Opciones</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT d.id, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), d.aprobado, d.id_material, d.reservado, d.comprado,m.id FROM computos_detalle d inner join materiales m on m.id = d.id_material WHERE d.cancelado = 0 and d.id_computo = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										
										echo '<td>'. $row[1] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										
										echo '<td>'. $row[2] . '</td>';
										
										$sql3 = "SELECT SUM(saldo) disponible FROM ingresos_detalle WHERE id_material = ".$row[8];
										$q3 = $pdo->prepare($sql3);
										$q3->execute();
										$data3 = $q3->fetch(PDO::FETCH_ASSOC);
										$enStock = 0;
										if (!empty($data3['disponible'])) {
											$enStock = $data3['disponible'];
										}
										echo '<td>'.$enStock.'</td>';	
										
										$saldo = $row[2]-$row[6]-$row[7];
										echo '<td>'.$row[6].'</td>';	
										echo '<td>'.$row[7].'</td>';	
										echo '<td>'.$saldo.'</td>';	
										
										
										if ($row[4]==1) {
											echo '<td>Si</td>';	
											echo '<td><input name="cantidad_'.$row[0].'" type="number" step="0" min="0" max="'.$saldo.'" value="'.$saldo.'" onkeyup="validateMax(this)" class="form-control" required="required"></td>';
										} else {
											echo '<td>No</td>';	
											echo '<td><input name="cantidad_'.$row[0].'" type="number" step="0.01" min="0" max="'.$saldo.'" class="form-control" disabled="disabled"></td>';
										}
										echo '<td>';
										if (!empty(tienePermiso(294))) {
											if ($row[4]==0) {
												echo '<a href="#" data-toggle="modal" data-target="#aprobarModal_'.$row[0].'"><img src="img/aprobar.png" width="24" height="25" border="0" alt="Aprobar" title="Aprobar"></a>';
												echo '&nbsp;&nbsp;';
											}
										}
										if ($row[6] > 0) {
											if (!empty(tienePermiso(311))) {
												echo '<a href="cancelarStockPedido.php?id='.$row[0].'&idComputo='.$_GET['id'].'"><img src="img/neg.png" width="24" height="25" border="0" alt="Cancelar Reserva" title="Cancelar Reserva"></a>';
												echo '&nbsp;&nbsp;';	
											}
										} 
										echo '</td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
								<input type="hidden" name="idComputo" value="<?php echo $_GET['id']; ?>" />
							  </table>
							</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
					   <?php if(tienePermiso(290)){?> <button class="btn btn-success" type="submit">Modificar</button><?php }?>
					   <?php if(tienePermiso(295)){?> <a class="btn btn-warning" id="pedido-masivo" onclick="pedir();">Hacer Pedido</a><?php }?>
					   <?php if(tienePermiso(310)){?> <a class="btn btn-danger" id="reserva-masivo" onclick="reservar();">Hacer Reserva</a><?php }?>
						<a class="btn btn-primary" target="_blank" href="imprimirComputo.php?id=<?php echo $data['id']; ?>">Imprimir</a>
                        <a href="#" onclick="document.location.href='listarComputos.php'" class="btn btn-light">Volver</a>
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
	<?php
    $pdo = Database::connect();
    $sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, date_format(d.`fecha_necesidad`,'%d/%m/%y'), d.`aprobado`,d.id_computo FROM `computos_detalle` d inner join materiales m on m.id = d.id_material WHERE d.id_computo = ".$_GET['id'];
    foreach ($pdo->query($sql) as $row) {
        ?>

  <div class="modal fade" id="aprobarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea aprobar el ítem del cómputo?</div>
      <div class="modal-footer">
      <a href="aprobarComputoDetalle.php?id=<?php echo $row[0]; ?>&idComputo=<?php echo $row[5]; ?>" class="btn btn-primary">Aprobar</a>
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
	
	function reservar() {
		document.getElementById('form1').action="reservarStockPedido.php";
		document.getElementById('form1').submit();
	}
	function pedir() {
		document.getElementById('form1').method="get";
		document.getElementById('form1').action="nuevoPedido.php";
		document.getElementById('form1').submit();
	}
	
	
	function validateMax(e) {
		if (parseFloat(e.value) > parseFloat(e.max)) {
			e.value = e.max;
		} 
	}
	
	
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
 
    } );
		
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>