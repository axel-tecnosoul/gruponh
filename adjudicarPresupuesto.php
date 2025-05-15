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


  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
	$sql = "SELECT adjudicado FROM `presupuestos` WHERE id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_GET['id']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	if ($data['adjudicado']==1) {
		$sql = "UPDATE presupuestos set adjudicado = 0 where id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_GET['id']]);
	} else {
		$sql = "UPDATE presupuestos set adjudicado = 1 where id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_GET['id']]);
		
		$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Adjudicación de presupuesto','Presupuestos','verPresupuesto.php?id=$id')";
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
		
		$sql = "INSERT INTO `anuncios_dashboard`(`fecha`, `titulo`, `resumen`, `descripcion`, `id_relevancia`, `muestra_calendario`) VALUES (now(),'Módulo Comercial - Adjudicación de Presupuesto Nro $id de NHNET','','',1,1)";
		$q = $pdo->prepare($sql);		   
		$q->execute();
		$idAnuncio = $pdo->lastInsertId();

		$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion=1";
		foreach ($pdo->query($sql) as $row) {
			
			$sql = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$row[0]]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			if (!empty($data)) {
				$idCuenta = $data['id'];
				$sql = "INSERT INTO `anuncios_dashboard_cuentas`(`id_cuenta_destino`,`id_anuncio`) VALUES (?,?)";
				$q = $pdo->prepare($sql);
				$q->execute([$idCuenta,$idAnuncio]);
			}
			
			$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (1,?,now(),0,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$row[0],'ID Presupuesto: #'.$id,$id]);
			
			$address = $row[1];
			
			$titulo = "Módulo Comercial - Adjudicación de Presupuesto Nro $id de NHNET";
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
    header("Location: listarPresupuestos.php");
  

?>