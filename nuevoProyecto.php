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

		$idSitioPadre = null;
		if (!empty($_POST['id_sitio_superior'])) {
			$idSitioPadre = $_POST['id_sitio_superior'];
		}
		
		$sql = "SELECT max(nro) max_nro FROM `proyectos` WHERE id_sitio = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['id_sitio']]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$nroProyecto = $data['max_nro']+1;

		$sql = "INSERT INTO `proyectos`(`id_sitio`, `descripcion`, `id_tipo_proyecto`, `observaciones`, `solicitante`, `fecha_pedido`, `fecha_entrega`, `id_estado_proyecto`, `id_usuario`, `informacion_entrada`, `id_gerente`, `id_linea_negocio`, `anulado`, `id_cliente`, `nombre`, `tags`, `nro`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_POST['id_sitio'],$_POST['descripcion'],$_POST['id_tipo_proyecto'],$_POST['observaciones'],$_POST['solicitante'],$_POST['fecha_pedido'],$_POST['fecha_entrega'],$_POST['id_estado_proyecto'],$_SESSION['user']['id'],$_POST['informacion_entrada'],$_POST['id_gerente'],$_POST['id_linea_negocio'],$_POST['id_cliente'],$_POST['nombre'],$_POST['tags'],$nroProyecto]);
		$id = $pdo->lastInsertId();
		
		$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.id = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$id]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$descripcionProyecto = $data['nro_sitio']." - ".$data['nro_subsitio']." - ".$data['nro']." - ".$data['nombre'];

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Proyecto','Proyectos','verProyecto.php?id=$id')";
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
		
		$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 11 ";
		foreach ($pdo->query($sql) as $row) {
			
			$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (11,?,now(),0,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$row[0],'ID Proyecto: #'.$id,$id]);
			
			$address = $row[1];
			
			$titulo = "ERP Notificaciones - Módulo Proyectos - Nuevo Proyecto (".$descripcionProyecto.")";
			$mensaje="Nuevo proyecto dado de alta en el sistema: #".$descripcionProyecto;
			
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
		if (!empty($_POST['btn2'])) {
			header("Location: listarProyectos.php");
		} else {
			header("Location: nuevaTarea.php?id=".$id);
		}
        
    }
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
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
          $ubicacion="Nuevo Proyecto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevoProyecto.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Sitio(*)</label>
							<div class="col-sm-9">
							<select name="id_sitio" id="id_sitio" autofocus class="js-example-basic-single col-sm-12" required="required" >
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre`,nro_sitio,nro_subsitio FROM `sitios` WHERE 1 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (!empty($_GET['idSitio'])) {
									if ($_GET['idSitio'] == $fila['id']) {
										echo " selected ";
									}
								}
								echo ">".$fila['nombre'].' ('.$fila['nro_sitio'].' / '.$fila['nro_subsitio'].")</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nombre de Proyecto(*)</label>
							<div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control" required="required" ></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción(*)</label>
							<div class="col-sm-9"><input name="descripcion" type="text" maxlength="499" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cliente(*)&nbsp;<?php if (!empty(tienePermiso(260))) { ?><a href="nuevaCuenta.php?nuevoProyecto=true"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva Cuenta" title="Nueva Cuenta"></a><?php } ?></label>
							<div class="col-sm-9">
							<select name="id_cliente" id="id_cliente" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 1 and activo = 1 and anulado = 0";
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
							<label class="col-sm-3 col-form-label">Solicitante(*)</label>
							<div class="col-sm-9"><input name="solicitante" type="text" maxlength="99" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Información_de Entrada</label>
							<div class="col-sm-9"><textarea name="informacion_entrada" class="form-control"></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Pedido(*)</label>
							<div class="col-sm-9"><input name="fecha_pedido" id="fecha_pedido" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Entrega</label>
							<div class="col-sm-9"><input name="fecha_entrega" id="fecha_entrega" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Línea de Negocio(*)</label>
							<div class="col-sm-9">
							<select name="id_linea_negocio" id="id_linea_negocio" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `linea_negocio` FROM `lineas_negocio` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['linea_negocio']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tags de Búsqueda(*)</label>
							<div class="col-sm-9"><input name="tags" type="text" maxlength="299" class="form-control" required="required"></div>
							</div>
							
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo(*)</label>
							<div class="col-sm-9">
							<select name="id_tipo_proyecto" id="id_tipo_proyecto" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_proyecto` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['tipo']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Gerente(*)</label>
							<div class="col-sm-9">
							<select name="id_gerente" id="id_gerente" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 4 and activo = 1 and anulado = 0";
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
							<label class="col-sm-3 col-form-label">Estado(*)</label>
							<div class="col-sm-9">
							<select name="id_estado_proyecto" id="id_estado_proyecto" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_proyecto` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == 1) {
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
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"></textarea></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-success" value="1" name="btn1" type="submit">Crear y Agregar Tareas</button>
						<button class="btn btn-primary" value="2" name="btn2" type="submit">Crear y Volver</button>
						<a href="listarProyectos.php" class="btn btn-light">Volver</a>
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
	<script>
		$("#fecha_entrega").change(function () {
			var startDate = document.getElementById("fecha_pedido").value;
			var endDate = document.getElementById("fecha_entrega").value;

			if ((Date.parse(startDate) > Date.parse(endDate))) {
				alert("La fecha de fin debe ser mayor a la fecha de inicio");
				document.getElementById("fecha_entrega").value = "";
			}
		});
		</script>
  </body>
</html>