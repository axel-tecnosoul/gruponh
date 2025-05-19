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
        // var_dump($_POST);
		// die;
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$sql = "SELECT id_proyecto FROM `tareas` WHERE id = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['id_tarea']]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$idProyecto = $data['id_proyecto'];
		
		$sql = "update tareas set `fecha_inicio_real`=now(), `fecha_fin_real`=now() where id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['id_tarea']]);
		
		$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.id = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$idProyecto]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$descripcionProyecto = $data['nro_sitio']." - ".$data['nro_subsitio']." - ".$data['nro']." - ".$data['nombre'];
		
		$sql = "SELECT max(c.nro) cant FROM computos c inner join `tareas` t on t.id = c.id_tarea where t.id_tipo_tarea = 5 and t.id_proyecto = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$idProyecto]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$nroComputo = $data['cant']+1;
		
		$idCuentaRealizo = null;
		$idCuentaValido = null;
		$idCuentaReviso = null;
		if(isset($_POST['id_cuenta_realizo'])){
			$idCuentaRealizo = $_POST['id_cuenta_realizo'];
		}

		if(isset($_POST['id_cuenta_reviso'])){
			$idCuentaReviso = $_POST['id_cuenta_reviso'];
		}

		if(isset($_POST['id_cuenta_valido'])){
			$idCuentaValido = $_POST['id_cuenta_valido'];
		}

		$sql = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$_SESSION['user']['id']]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		if (!empty($data)) {
			$idCuentaRealizo = $data['id'];
		}

		$sql = "INSERT INTO `computos`(`nro_revision`, `id_tarea`, `fecha`, `id_cuenta_solicitante`, `id_estado`, `nro`,`id_cuenta_realizo`, `id_cuenta_reviso`, `id_cuenta_valido`) VALUES (0,?,?,?,?,?,?,?,?)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_POST['id_tarea'],$_POST['fecha'],$idCuentaRealizo,$_POST['id_estado'],$nroComputo,$idCuentaRealizo,$idCuentaReviso,$idCuentaValido]);
        
		$id = $pdo->lastInsertId();
		
		$sql = "update `computos` set nro_computo = ? where id = ?";
		$q = $pdo->prepare($sql);		   
		$q->execute([$id,$id]);

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Cómputo','Cómputos','verComputo.php?id=$id')";
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
		
		
		if ($_POST['id_estado']==2) {
			
			$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 8 ";
			foreach ($pdo->query($sql) as $row) {
				
				$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (8,?,now(),0,?,?)";
				$q = $pdo->prepare($sql);
				$q->execute([$row[0],'ID Computo: #'.$id,$id]);
				
				$address = $row[1];
				
				$titulo = "ERP Notificaciones - Módulo Producción - Nuevo Cómputo (".$descripcionProyecto.")";
				$mensaje="Nuevo cómputo dado de alta en el sistema para aprobar: #".$id;
				
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
        header("Location: itemsComputo.php?id=".$id."&revision=0&modo=nuevo");
    } else {
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from tareas t inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio where t.id = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$_GET['id']]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$descProyecto = $data['nro_sitio'].'-'.$data['nro_subsitio'].'-'.$data['nro'].': '.$data['nombre'];
		
		$sql2 = "select id from cuentas where id_usuario = ? ";
		$q2 = $pdo->prepare($sql2);
		$q2->execute([$_SESSION['user']['id']]);
		
		$data2 = $q2->fetch(PDO::FETCH_ASSOC);
		
		$idCuenta = 0;
		if (!empty($data2['id'])) {
			$idCuenta = $data2['id'];	
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
          $ubicacion="Nuevo Cómputo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" id="miFormulario" role="form" method="post" action="nuevoComputo.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha(*)</label>
							<div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" autofocus value="<?php echo date('Y-m-d');?>" class="form-control" required="required"></div>
							</div>
							<div class="form-group row mt-3">
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
							<label class="form-control"><?php echo $descProyecto; ?></label>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tarea(*)</label>
							<div class="col-sm-9">
							<select name="id_tarea_2" id="id_tarea_2" class="js-example-basic-single col-sm-12" disabled="disabled">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT t.id,tt.tipo,t.observaciones FROM `tareas` t inner join tipos_tarea tt on tt.id = t.id_tipo_tarea WHERE t.`anulado` = 0 and t.id_tipo_tarea = 5 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								
								$tieneComputo = 0;
								$sql2 = "SELECT `id` from computos where id_tarea = ? and id_estado <> 6 ";
								$q2 = $pdo->prepare($sql2);
								$q2->execute([$fila['id']]);
								$data2 = $q2->fetch(PDO::FETCH_ASSOC);
								if (!empty($data2)) {
									$tieneComputo = 1;	
								}
								
								if ($tieneComputo == 0) {
									echo "<option value='".$fila['id']."'";
									if (!empty($_GET['id'])) {
										if ($fila['id'] == $_GET['id']) {
											echo " selected ";
										}									
									}
									echo ">".$fila['tipo']." / ".$fila['observaciones']."</option>";
								}
							}
							Database::disconnect();
							?>
							</select>
							<input type="hidden" name="id_tarea" value="<?php echo $_GET['id'];?>">
							</div>
							<!-- </div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Revisó(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_reviso" id="id_cuenta_reviso" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
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
							</div> -->
							<!-- <div class="form-group row">
							<label class="col-sm-3 col-form-label">Aprobó(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_valido" id="id_cuenta_valido" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
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
							</div> -->
							<!-- <div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado</label>
							<div class="col-sm-9">
							<select name="id_estado" id="id_estado" class="js-example-basic-single col-sm-12" required="required">
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_computos` WHERE id in (1,2)";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['estado']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div> -->
							<input type="hidden" name="id_estado" id="id_estado" value="1">
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarComputos.php" class="btn btn-light">Volver</a>
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
        document.getElementById('miFormulario').addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    </script>
  </body>
</html>