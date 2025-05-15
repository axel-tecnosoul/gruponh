<?php
require("config.php");
require("PHPMailer/class.phpmailer.php");
require("PHPMailer/class.smtp.php");
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
  header("Location: listarPresupuestos.php");
}

if (!empty($_POST)) {

  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;

  if($modoDebug==1){
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_GET);
  }

  if(isset($_POST["btn1"])){
    //modificamos el presupuesto
	
	$sql = "select nro, adjudicado,fecha_hora_alta FROM `presupuestos` where id = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$id]);
	$dataC = $q->fetch(PDO::FETCH_ASSOC);
	
	$nro = $dataC['nro'];
	$adjudicado = $dataC['adjudicado'];
	$fecha_hora_alta = $dataC['fecha_hora_alta'];
  
    $sql = "UPDATE presupuestos set id_empresa = ?, fecha = ?, id_cuenta = ?, solicitante = ?, referencia = ?, descripcion = ?, id_moneda = ?, monto = ?, adjudicado = ?, observaciones = ?, id_usuario = ?, es_marco = ?, id_linea_negocio = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['id_empresa'],$_POST['fecha'],$_POST['id_cuenta'],$_POST['solicitante'],$_POST['referencia'],$_POST['descripcion'],$_POST['id_moneda'],$_POST['monto'],$_POST['adjudicado'],$_POST['observaciones'],$_SESSION['user']['id'],$_POST['es_marco'],$_POST['id_linea_negocio'],$_GET['id']]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
    
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificacion de presupuesto','Presupuestos','verPresupuesto.php?id=$id')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

  }else{
    //creamos una revision
	$nro_revision=$_POST["revision"];
    $nuevo_nro_revision=$nro_revision+1;
	
	$sql = "select nro, adjudicado,fecha_hora_alta FROM `presupuestos` where id = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$id]);
	$dataC = $q->fetch(PDO::FETCH_ASSOC);
	
	$nro = $dataC['nro'];
	$adjudicado = $dataC['adjudicado'];
	$fecha_hora_alta = $dataC['fecha_hora_alta'];

	$sql = "insert into `presupuestos` (`nro`, `id_empresa`, `nro_revision`, `fecha`, `id_cuenta`, `solicitante`, `referencia`, `descripcion`, `id_moneda`, `monto`, `adjudicado`, `observaciones`, `id_usuario`, `anulado`, `es_marco`, `id_linea_negocio`, `fecha_hora_alta`, `comentarios_revision`, `fecha_hora_revision`) values (?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,now())";
	$q = $pdo->prepare($sql);
	$q->execute([$nro,$_POST['id_empresa'],$nuevo_nro_revision,$_POST['fecha'],$_POST['id_cuenta'],$_POST['solicitante'],$_POST['referencia'],$_POST['descripcion'],$_POST['id_moneda'],$_POST['monto'],$_POST['adjudicado'],$_POST['observaciones'],$_SESSION['user']['id'],$_POST['es_marco'],$_POST['id_linea_negocio'],$fecha_hora_alta,$_POST['comentarios_revision']]);
	
	$idNuevoPresupuesto = $pdo->lastInsertId();
	
	$sql = "SELECT pd.detalle,pd.cantidad,pd.id_unidad_medida,pd.costo,pd.precio FROM presupuestos_detalle pd WHERE pd.id_presupuesto = ".$id;
    foreach ($pdo->query($sql) as $row) {
      $sql = "INSERT INTO presupuestos_detalle (id_presupuesto, detalle, cantidad, id_unidad_medida, costo, precio) VALUES (?,?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$idNuevoPresupuesto,$row["detalle"],$row["cantidad"],$row["id_unidad_medida"],$row["costo"],$row["precio"]]);
    }


    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Revisión de presupuesto','Presupuestos','verPresupuesto.php?id=$idNuevoPresupuesto')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    $id=$idNuevoPresupuesto;
  }
  
  if ($adjudicado == 0 && $_POST['adjudicado'] == 1) {
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
  
  if ($modoDebug==1) {
    $pdo->rollBack();
    Database::disconnect();
    die();
  } else {
    Database::disconnect();
    header("Location: itemsPresupuesto.php?id=".$id);
  }

} else {

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT id, nro, id_empresa, nro_revision, fecha, id_cuenta, solicitante, referencia, descripcion, id_moneda, monto, adjudicado, observaciones, id_usuario, anulado, es_marco, id_linea_negocio FROM presupuestos WHERE id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  Database::disconnect();
}?>
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
          $ubicacion="Modificar Presupuesto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				          <form class="form theme-form" role="form" method="post" action="modificarPresupuesto.php?id=<?=$id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <input type="hidden" name="revision" value="<?=$_GET["revision"]?>">
                            <label class="col-sm-3 col-form-label">Empresa(*)</label>
                            <div class="col-sm-9">
                              <select name="id_empresa" id="id_empresa" class="js-example-basic-single col-sm-12" autofocus required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, empresa FROM empresas WHERE 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_empresa']) {
                                    echo " selected ";
                                  }
                                  echo ">".$fila['empresa']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha(*)</label>
                            <div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" required="required" value="<?php echo $data['fecha']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cliente(*)</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta" id="id_cuenta" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta = 1 and activo = 1 and anulado = 0 order by nombre";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_cuenta']) {
                                    echo " selected ";
                                  }
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Línea de Negocio(*)</label>
                            <div class="col-sm-9">
                              <select name="id_linea_negocio" id="id_linea_negocio" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, linea_negocio FROM lineas_negocio WHERE 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_linea_negocio']) {
                                    echo " selected ";
                                  }
                                  echo ">".$fila['linea_negocio']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Solicitante(*)</label>
                            <div class="col-sm-9"><input name="solicitante" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['solicitante']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Referencia(*)</label>
                            <div class="col-sm-9"><input name="referencia" type="text" maxlength="199" class="form-control" required="required" value="<?php echo $data['referencia']; ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Es Marco?(*)</label>
                            <div class="col-sm-9">
                              <select name="es_marco" id="es_marco" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option>
                                <option value="1" <?php if ($data['es_marco']==1) {
                                  echo " selected ";
                                }?>>Si</option>
                                <option value="0" <?php if ($data['es_marco']==0) {
                                  echo " selected ";
                                }?>>No</option>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Descripción(*)</label>
                            <div class="col-sm-9"><textarea name="descripcion" class="form-control" required="required"><?php echo $data['descripcion']; ?></textarea></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Moneda(*)</label>
                            <div class="col-sm-9">
                              <select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, moneda FROM monedas WHERE 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_moneda']) {
                                    echo " selected ";
                                  }
                                  echo ">".$fila['moneda']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Monto(*)</label>
                            <div class="col-sm-9"><input name="monto" type="number" step="0.01" class="form-control" required="required" value="<?php echo $data['monto']; ?>"></div>
                          </div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Adjudicado?(*)</label>
                            <div class="col-sm-9">
                              <select name="adjudicado" id="adjudicado" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option>
                                <option value="1" <?php if ($data['adjudicado']==1) {
                                  echo " selected ";
                                }?>>Si</option>
                                <option value="0" <?php if ($data['adjudicado']==0) {
                                  echo " selected ";
                                }?>>No</option>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                            <div class="col-sm-9"><textarea name="observaciones" class="form-control"><?php echo $data['observaciones']; ?></textarea></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<?php
						$ultimaRevision = 0;
						$sql2 = "SELECT max(nro_revision) ultimaRevision FROM presupuestos WHERE nro = ? ";
						$q2 = $pdo->prepare($sql2);
						$q2->execute([$data['nro']]);
						$data2 = $q2->fetch(PDO::FETCH_ASSOC);
						$ultimaRevision = $data2['ultimaRevision'];
						if ($data['nro_revision'] == $ultimaRevision) {
						?>
                        <button type="submit" value="1" name="btn1" class="btn btn-primary">Modificar</button>
                        <?php } ?>
						<a href='listarPresupuestos.php' class="btn btn-light">Volver</a>
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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	  <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
  </body>
</html>