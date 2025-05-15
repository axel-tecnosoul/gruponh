<?php
    require("config.php");
	require("PHPMailer/class.phpmailer.php");
	require("PHPMailer/class.smtp.php");

    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$sql = "INSERT INTO `compras`(`id_pedido`, `id_cuenta_proveedor`, `fecha_emision`, `fecha_entrega`, `id_forma_pago`, `id_estado_compra`, `nro_oc`, `total`, `comentarios`, `id_moneda`, `tipo_cambio_dia`,comentarios_revision, `descuento`) VALUES (?,?,?,?,?,1,?,0,?,?,?,'Revisión Original',?)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_GET['idPedido'],$_POST['id_cuenta_proveedor'],$_POST['fecha_emision'],$_POST['fecha_entrega'],$_POST['id_forma_pago'],'',$_POST['comentarios'],$_POST['id_moneda'],$_POST['tipo_cambio_dia'],$_POST['descuento']]);
        
		$id = $pdo->lastInsertId();
		
		$nroOC = $_GET['idPedido'] .'/'. $id;
		$sql = "update `compras` set `nro_oc` = ? where id = ?";
		$q = $pdo->prepare($sql);		   
		$q->execute([$nroOC,$id]);
        
		
		$sql = " SELECT d.`id`, d.`id_material`, m.`concepto`, d.`cantidad`, d.`id_unidad_medida`,m.peso_metro FROM `pedidos_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = m.id_unidad_medida WHERE d.id in (".$_GET['conceptos'].")";
		
		$total = 0;
		foreach ($pdo->query($sql) as $row) {
			
			if ($_POST['preciokg_'.$row[0]] != 0) {
				$_POST['precio_'.$row[0]] = $_POST['preciokg_'.$row[0]] * $row[5];
			}
			
			$sql = "INSERT INTO `compras_detalle`(`id_compra`, `id_material`, `cantidad`, `id_unidad_medida`, `precio`, `precio_kg`) VALUES (?,?,?,?,?,?)";
			$q = $pdo->prepare($sql);		   
			$q->execute([$id,$row[1],$_POST['cantidad_'.$row[0]],$row[4],$_POST['precio_'.$row[0]],$_POST['preciokg_'.$row[0]]]);
			$subtotal = $_POST['cantidad_'.$row[0]]*$_POST['precio_'.$row[0]];
			$total += $subtotal;
			
			$comprando = $_POST['cantidad_'.$row[0]];
			
			$sql = "UPDATE `pedidos_detalle` SET `comprado`=? WHERE `id_pedido`=? AND `id_material`=?";
			$q = $pdo->prepare($sql);
			$q->execute([$comprando,$_GET['idPedido'],$row[1]]);
			
			$sql3 = "SELECT cd.id id from computos_detalle cd inner join computos c on c.id = cd.id_computo inner join pedidos p on p.id_computo = c.id where p.id = ? and cd.cancelado = 0 and cd.id_material = ? ";
			$q3 = $pdo->prepare($sql3);
			$q3->execute([$_GET['idPedido'],$row[1]]);
			$data3 = $q3->fetch(PDO::FETCH_ASSOC);
			
			$sql = "UPDATE `computos_detalle` set `comprado` = ? WHERE id = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$comprando,$data3['id']]);
			
		}
		
		$iva = $total*0.21;
		
		$sql = "update `compras` set total = ?, iva = ? where id = ?";
		$q = $pdo->prepare($sql);		   
		$q->execute([$total,$iva,$id]);
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva orden de compra','Compras','verCompra.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

		$sql = "SELECT valor FROM `parametros` WHERE id = 1 ";
		$q = $pdo->prepare($sql);
		$q->execute();
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$smtpHost = $data['valor'];  
		
		$sql = "SELECT valor FROM `parametros` WHERE id = 2 ";
		$q = $pdo->prepare($sql);
		$q->execute();
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$smtpUsuario = $data['valor'];  
		
		$sql = "SELECT valor FROM `parametros` WHERE id = 3 ";
		$q = $pdo->prepare($sql);
		$q->execute();
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$smtpClave = $data['valor'];  
		
		$sql = "SELECT valor FROM `parametros` WHERE id = 4 ";
		$q = $pdo->prepare($sql);
		$q->execute();
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$smtpFrom = $data['valor'];  
		
		$sql = "SELECT valor FROM `parametros` WHERE id = 5 ";
		$q = $pdo->prepare($sql);
		$q->execute();
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$smtpFromName = $data['valor'];  
		
		$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 4 ";
		foreach ($pdo->query($sql) as $row) {
			
			$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (4,?,now(),0,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$row[0],'ID Orden de Compra: #'.$id,$id]);
			
			$address = $row[1];
			
			$titulo = "ERP Notificaciones - Módulo Compras - Nueva Compra";
			$mensaje="Nueva compra dada de alta en el sistema: #".$id;
			
			$mail = new PHPMailer();
			$mail->IsSMTP();
			$mail->SMTPAuth = true;
			$mail->Port = 25; 
			$mail->SMTPSecure = 'ssl';
			$mail->SMTPAutoTLS = false;
			$mail->SMTPSecure = false;
			$mail->IsHTML(true); 
			$mail->CharSet = "utf-8";
			$mail->From = $smtpFrom;
			$mail->FromName = $_SESSION['user']['usuario'];
			$mail->Host = $smtpHost; 
			$mail->Username = $smtpUsuario; 
			$mail->Password = $smtpClave;
			$mail->AddAddress($address);
			$mail->Subject = $titulo; 
			$mensajeHtml = nl2br($mensaje);
			$mail->Body = "{$mensajeHtml} <br /><br />"; 
			$mail->AltBody = "{$mensaje} \n\n"; 
			$mail->Send();
		
		}
		
		
		
		Database::disconnect();
        header("Location: listarCompras.php");
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
          $ubicacion="Nueva Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevaCompra.php?idPedido=<?php echo $_GET['id'];?>&conceptos=<?php echo $_GET['conceptos'];?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proveedor(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_proveedor" id="id_cuenta_proveedor" class="js-example-basic-single col-sm-12" autofocus required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (5) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Emisión(*)</label>
							<div class="col-sm-9"><input name="fecha_emision" type="date" onfocus="this.showPicker()" value="<?php echo date('Y-m-d');?>" class="form-control" required="required"></div>
							</div>
							<?php
							$fechaSolicitada = "";
							$sql = "SELECT fecha FROM `pedidos` WHERE id = ".$_GET['id'];
							$q = $pdo->prepare($sql);
							$q->execute();
							$data = $q->fetch(PDO::FETCH_ASSOC);
							$fechaSolicitada = $data['fecha'];
							?>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Entrega Estimada</label>
							<div class="col-sm-9"><input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?php echo $fechaSolicitada; ?>" class="form-control"></div>
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
								echo ">".$fila['moneda']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo de Cambio</label>
							<div class="col-sm-9"><input name="tipo_cambio_dia" type="number" step="0.01" class="form-control" ></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descuento</label>
							<div class="col-sm-9"><input name="descuento" type="number" step="0.01" class="form-control" ></div>
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
								echo ">".$fila['forma_pago']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Comentarios</label>
							<div class="col-sm-9"><textarea name="comentarios" class="form-control"></textarea></div>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Concepto</th>
									  <th>Cantidad Solicitada</th>
									  <th>Cantidad a Pedir</th>
									  <th>Precio Unitario</th>
									  <th>Precio x Kg</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT d.`id`, d.`id_material`, m.`concepto`, d.`cantidad`-d.`reservado`-d.`comprado`, d.`id_unidad_medida` FROM `pedidos_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = m.id_unidad_medida WHERE d.id in (".$_GET['conceptos'].")";
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[2] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										echo '<td><input name="cantidad_'.$row[0].'" type="number" step="0.01" min="0.01" max="'.$row[3].'" class="form-control" required="required" value="0"></td>';
										echo '<td><input name="precio_'.$row[0].'" type="number" step="0.01" class="form-control" required="required" value="0"></td>';
										echo '<td><input name="preciokg_'.$row[0].'" type="number" step="0.01" class="form-control" required="required" value="0"></td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
							  </table>
							  <i>NOTA: Si ingresa Precio x KG <> 0, el precio se sobreescribirá multiplicando el Precio x KG * Peso del Concepto</i>
							</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarCompras.php" class="btn btn-light">Volver</a>
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
				"paging": false,     // Deshabilitar la paginación
		
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
		});
		
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
	
	<!-- Page-Level Demo Scripts - Tables - Use for reference -->
   
  </body>
</html>