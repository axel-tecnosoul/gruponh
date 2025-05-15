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
  
	$sql2 = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute([$_SESSION['user']['id']]);
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);

  $sql = "INSERT INTO despachos(id_cuenta_operario, fecha, nro_remito, transporte, chofer, id_estado_despacho, `id_proyecto`, `detalle_oc`) VALUES (?,?,?,?,?,1,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$data2['id'],$_POST['fecha'],$_POST['nro_remito'],$_POST['transporte'],$_POST['chofer'],$_POST['id_proyecto'],$_POST['detalle_oc']]);
  $id = $pdo->lastInsertId();
  
	$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['id_proyecto']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$descripcionProyecto = $data['nro_sitio']." - ".$data['nro_subsitio']." - ".$data['nro']." - ".$data['nombre'];
	
  
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
	
	$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 9 ";
	foreach ($pdo->query($sql) as $row) {
		
		$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (9,?,now(),0,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$row[0],'ID Despacho: #'.$id,$id]);
		
		$address = $row[1];
		
		$titulo = "ERP Notificaciones - Módulo Ingeniería - Nuevo Despacho (".$descripcionProyecto.")";
		$mensaje="Nuevo despacho dado de alta en el sistema: #".$id;

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
	
	

  if($id>0){
  
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Despacho','Despachos','verDespacho.php?id=$id')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    Database::disconnect();
    header("Location: nuevoBultoDespacho.php?id_despacho=".$id);

  }else{
      
    Database::disconnect();
    header("Location: listarDespachos.php");
  }
}?>
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
          $ubicacion="Nuevo Despacho";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevoDespacho.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
							<select name="id_proyecto" id="id_proyecto" autofocus class="js-example-basic-single col-sm-12" required="required" onChange="jsListarOCs(this.value);">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">OCC(*)</label>
							<div class="col-sm-9">
							<select name="detalle_oc" id="detalle_oc" class="js-example-basic-single col-sm-12" required="required">
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha(*)</label>
							<div class="col-sm-9"><input name="fecha" type="date" autofocus onfocus="this.showPicker()" class="form-control" required="required" value="<?php echo date('Y-m-d');?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nro. Remito(*)</label>
							<div class="col-sm-9"><input name="nro_remito" type="text" maxlength="99" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Transporte(*)</label>
							<div class="col-sm-9"><input name="transporte" type="text" maxlength="99" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Chofer(*)</label>
							<div class="col-sm-9"><input name="chofer" type="text" maxlength="99" class="form-control" required="required"></div>
							</div>
						</div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarDespachos.php" class="btn btn-light">Volver</a>
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
		function jsListarOCs(val) { 
			$.ajax({
				type: "GET",
				url: "ajaxOCC.php",
				data: "id_proyecto="+val,
				success: function(resp){
					$("#detalle_oc").html(resp);
				}
			});
		}
		
	
		$(document).ready(function() {
			$('#dataTables-example666').DataTable({
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
		});
		
		function jsListarProductos(val) { 
			$.ajax({
				type: "POST",
				url: "ajaxProductosListaCorte.php",
				data: "tarea="+val,
				success: function(resp){
					$("#dataTables-example666").html(resp);
				}
			});
		}
		
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
	
	<!-- Page-Level Demo Scripts - Tables - Use for reference -->
   
  </body>
</html>