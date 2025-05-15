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
  
  if (!empty($_POST['nuevo_cliente'])) {
		$sql = "INSERT INTO `cuentas`(`id_tipo_cuenta`, `nombre`, `razon_social`, `cuit`, `contacto`, `email`, `telefono`, `id_puesto`, `codigo_postal`, `id_pais`, `id_provincia`, `id_localidad`, `observaciones`, `activo`, `es_recurso`, `anulado`, `cuenta_gestion`, `codigo_externo`, `id_condicion_iva`, `direccion`, `id_usuario`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,0,?,?,?,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([1,$_POST['nuevo_cliente'],$_POST['nuevo_cliente'],'11-11111111-1',$_POST['nuevo_cliente'],'','',null,'',12,61,99999,'',0,null,null,null,null,null]);
		
		$_POST['id_cuenta'] = $pdo->lastInsertId();
  }

  $sql = "INSERT INTO presupuestos (`nro`, `id_empresa`, `nro_revision`, `fecha`, `id_cuenta`, `solicitante`, `referencia`, `descripcion`, `id_moneda`, `monto`, `adjudicado`, `observaciones`, `id_usuario`, `anulado`, `es_marco`, `id_linea_negocio`, `fecha_hora_alta`) VALUES (0,?,0,?,?,?,?,?,?,?,?,?,?,0,?,?,now())";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST['id_empresa'],$_POST['fecha'],$_POST['id_cuenta'],$_POST['solicitante'],$_POST['referencia'],$_POST['descripcion'],$_POST['id_moneda'],$_POST['monto'],$_POST['adjudicado'],$_POST['observaciones'],$_SESSION['user']['id'],$_POST['es_marco'],$_POST['id_linea_negocio']]);

  $id = $pdo->lastInsertId();
  
  $sql = "update presupuestos set `nro` = ? where id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id,$id]);
  
  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Presupuesto','Presupuestos','verPresupuesto.php?id=$id')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));
  
	if ($_POST['adjudicado'] == 1) {
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

		$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 1 ";
		foreach ($pdo->query($sql) as $row) {
			
			$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (1,?,now(),0,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$row[0],'ID Presupuesto: #'.$id,$id]);
			
			$address = $row[1];
			
			$titulo = "ERP Notificaciones - Módulo Comercial - Adjudicación de Presupuesto";
			$mensaje="El presupuesto #".$id." ha sido adjudicado en el sistema y se requiere alta de proyecto";

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

	}
  
	

  Database::disconnect();
  header("Location: itemsPresupuesto.php?id=".$id);
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <style>
        .input-group {
            display: flex;
            align-items: center;
        }
        .input-group input {
            width: 100px;
        }
        .input-group button {
            margin-left: 10px;
        }
  </style>
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
          $ubicacion="Nuevo Presupuesto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" name="form1" id="form1" role="form" method="post" action="nuevoPresupuesto.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Empresa(*)</label>
							<div class="col-sm-9">
							<select name="id_empresa" id="id_empresa" autofocus class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `empresa` FROM `empresas` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['empresa']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha(*)</label>
							<div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" maxlength="99" value="<?php echo date("Y-m-d"); ?>" class="form-control" required="required"></div>
							</div>
							<hr>
							
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cliente(*)</label>
							<div class="col-sm-9">
								<div class="input-group">
								<select name="id_cuenta" id="id_cuenta" class="js-example-basic-single">
								<option value="">Buscar Cuenta Existente...</option>
								<?php
								$pdo = Database::connect();
								$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
								$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 1 and activo = 1 and anulado = 0 order by nombre";
								$q = $pdo->prepare($sqlZon);
								$q->execute();
								while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
									echo "<option value='".$fila['id']."'";
									echo ">".$fila['nombre']."</option>";
								}
								Database::disconnect();
								?>
								</select>
								&nbsp;o&nbsp;
								<input name="nuevo_cliente" type="text" maxlength="199" class="form-control" placeholder="Crear Nueva Cuenta...">
								</div>
							</div>
							</div>
							
							<hr>
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
							<label class="col-sm-3 col-form-label">Solicitante(*)</label>
							<div class="col-sm-9"><input name="solicitante" type="text" maxlength="99" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Referencia(*)</label>
							<div class="col-sm-9"><input name="referencia" type="text" maxlength="199" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Es Marco?(*)</label>
							<div class="col-sm-9">
							<select name="es_marco" id="es_marco" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<option value="0" selected>No</option>
							<option value="1">Si</option>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción(*)</label>
							<div class="col-sm-9"><textarea name="descripcion" class="form-control" required="required"></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Moneda(*)</label>
							<div class="col-sm-9">
							<select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12" required="required">
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
							<label class="col-sm-3 col-form-label">Monto(*)</label>
							<div class="col-sm-9"><input name="monto" type="number" step="0.01" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Adjudicado?(*)</label>
							<div class="col-sm-9">
							<select name="adjudicado" id="adjudicado" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<option value="0" selected>No</option>
							<option value="1">Si</option>
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
                        <button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarPresupuestos.php" class="btn btn-light">Volver</a>
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
	$(document).ready(function() {
		$("#form1").submit(function(event) {
			var clienteExistente = $("#id_cuenta").val(); // Valor del select
			var clienteNuevo = $("input[name='nuevo_cliente']").val().trim(); // Valor del input

			if (clienteExistente === "" && clienteNuevo === "") {
				alert("Debe seleccionar una cuenta existente o ingresar un nuevo cliente.");
				event.preventDefault(); // Evita que se envíe el formulario
			}
		});
	});
	</script>
	
  </body>
</html>