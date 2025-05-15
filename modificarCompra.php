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
        header("Location: listarCompras.php");
    }
    
    if (!empty($_POST)) {
		
		 // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		if (!empty($_POST['btn1'])) {
			
			$sql = "UPDATE `compras` set `fecha_entrega` = ?, `id_forma_pago` = ?, `id_estado_compra` = ?, `comentarios` = ?, `id_moneda` = ?, `tipo_cambio_dia` = ?, `iva` = ?, `descuento` = ?, `total` = ? where id = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$_POST['fecha_entrega'],$_POST['id_forma_pago'],$_POST['id_estado_compra'],$_POST['comentarios'],$_POST['id_moneda'],$_POST['tipo_cambio_dia'],$_POST['iva'],$_POST['descuento'],$_POST['total'],$_GET['id']]);
			
		} else {
			
			$sql = "UPDATE `compras` set aprobado = 1 where id = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$_GET['id']]);
			
			$sql = "SELECT `id_pedido`, `id_cuenta_proveedor`, `fecha_emision`, `fecha_entrega`, `id_forma_pago`, `id_estado_compra`, `nro_oc`, `total`, `comentarios`, `adjunto_factura`, `id_moneda`, `tipo_cambio_dia`, `nro_revision`, `iva`, `descuento` FROM `compras` WHERE `id` = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$_GET['id']]);
			$dataC = $q->fetch(PDO::FETCH_ASSOC);
				
			$nro = $dataC['nro_revision']+1;
			
			$sql = "INSERT INTO `compras`(`id_pedido`, `id_cuenta_proveedor`, `fecha_emision`, `fecha_entrega`, `id_forma_pago`, `id_estado_compra`, `nro_oc`, `total`, `comentarios`, `adjunto_factura`, `id_moneda`, `tipo_cambio_dia`, `nro_revision`, `aprobado`, `comentarios_revision`, `iva`, `descuento`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$dataC['id_pedido'],$dataC['id_cuenta_proveedor'],$dataC['fecha_emision'],$_POST['fecha_entrega'],$_POST['id_forma_pago'],$_POST['id_estado_compra'],$dataC['nro_oc'],$_POST['total'],$dataC['comentarios'],$dataC['adjunto_factura'],$_POST['id_moneda'],$_POST['tipo_cambio_dia'],$nro,0,$_POST['comentarios'],$_POST['iva'],$_POST['descuento']]);
			
			$id = $pdo->lastInsertId();
			
			$sqlList = " SELECT `id_material`, `cantidad`, `id_unidad_medida`, `precio`, `entregado`, `precio_kg` FROM `compras_detalle` WHERE id_compra = ".$_GET['id'];
			foreach ($pdo->query($sqlList) as $row) {
				$sql = "INSERT INTO `compras_detalle`(`id_compra`, `id_material`, `cantidad`, `id_unidad_medida`, `precio`, `entregado`, `precio_kg`) VALUES (?,?,?,?,?,?,?)";
				$q = $pdo->prepare($sql);
				$q->execute([$id,$row[0],$row[1],$row[2],$row[3],$row[4],$row[5]]);
			}
			
		}
        
        $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de orden de compra','Compras','verCompra.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
		
		header("Location: listarCompras.php");
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_pedido`, `id_cuenta_proveedor`, `fecha_emision`, `fecha_entrega`, `id_forma_pago`, `id_estado_compra`, `nro_oc`, `total`, `comentarios`, `id_moneda`, `tipo_cambio_dia`, `iva`, `descuento` FROM `compras` WHERE id = ? ";
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
          $ubicacion="Modificar Orden de Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" name="form1" method="post" action="modificarCompra.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nro OC(*)</label>
							<div class="col-sm-9"><input name="nro_oc" type="text" maxlength="99" autofocus class="form-control" readonly="readonly" value="<?php echo $data['nro_oc'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proveedor(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_proveedor" id="id_cuenta_proveedor" class="js-example-basic-single col-sm-12" disabled="disabled">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (5) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_cuenta_proveedor']) {
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
							<label class="col-sm-3 col-form-label">Fecha Emisión(*)</label>
							<div class="col-sm-9"><input name="fecha_emision" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha_emision'];?>" class="form-control" readonly="readonly"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Entrega Estimada</label>
							<div class="col-sm-9"><input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha_entrega'];?>" class="form-control"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Moneda</label>
							<div class="col-sm-9">
							<select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `moneda` FROM `monedas` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_moneda']) {
									echo " selected ";
								}
								echo ">".$fila['moneda']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo de Cambio</label>
							<div class="col-sm-9"><input name="tipo_cambio_dia" type="number" step="0.01" class="form-control" value="<?php echo $data['tipo_cambio_dia'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Forma de Pago</label>
							<div class="col-sm-9">
							<select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `forma_pago` FROM `formas_pago` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_forma_pago']) {
									echo " selected ";
								}
								echo ">".$fila['forma_pago']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado</label>
							<div class="col-sm-9">
							<select name="id_estado_compra" id="id_estado_compra" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_compra` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_estado_compra']) {
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
							<label class="col-sm-3 col-form-label">Subtotal</label>
							<div class="col-sm-9"><input name="total" type="number" step="0.01" class="form-control" value="<?php echo $data['total'];?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">IVA</label>
							<div class="col-sm-9"><input name="iva" type="number" step="0.01" class="form-control" value="<?php echo $data['iva'];?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descuento</label>
							<div class="col-sm-9"><input name="descuento" type="number" step="0.01" class="form-control" value="<?php echo $data['descuento'];?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Total</label>
							<div class="col-sm-9"><input name="total_total" type="number" step="0.01" class="form-control" value="<?php echo $data['total']+$data['iva']-$data['descuento'];?>" readonly="readonly"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Comentarios</label>
							<div class="col-sm-9"><textarea name="comentarios" class="form-control"><?php echo $data['comentarios'];?></textarea></div>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Concepto</th>
									  <th>Precio</th>
									  <th>Precio Kg</th>
									  <th>Cantidad</th>
									  <th>Subtotal</th>
									  <th>Entregado</th>
									  <th>Opciones</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, u.`unidad_medida`,d.id_material,d.precio,d.entregado,d.precio_kg FROM `compras_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_compra = ".$_GET['id'];
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td>$'. number_format($row[5],2) . '</td>';
										echo '<td>$'. number_format($row[7],2) . '</td>';
										echo '<td>'. $row[2] . '</td>';
										echo '<td>$'. number_format($row[5]*$row[2],2) . '</td>';
										echo '<td>'. $row[6] . '</td>';
										echo '<td>';
										if (($data['id_estado_compra'] == 1) || ($data['id_estado_compra'] == 2)) {
											echo '<a href="modificarConceptoCompra.php?id='.$row[0].'"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
											echo '&nbsp;&nbsp;';
											echo '<a href="eliminarConceptoCompra.php?id='.$row[0].'&id_compra='.$data['id'].'"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
											echo '&nbsp;&nbsp;';
										}
										echo '</td>';
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
					    <button class="btn btn-success" type="submit" value="1" name="btn1">Guardar Modificación</button>
						<button class="btn btn-primary" type="submit" value="2" name="btn2">Crear Revisión</button>
                        <a href="#" onclick="document.location.href='listarCompras.php'" class="btn btn-danger">Volver al Listado</a>
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
  </body>
</html>