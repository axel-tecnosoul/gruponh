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

		$sql = "SELECT valor FROM `parametros` WHERE id = 7 ";
		$q = $pdo->prepare($sql);
		$q->execute();
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$address = $data['valor'];  

		$titulo = "ERP Notificaciones - Módulo Comercial - Adjudicación de Presupuesto";
		$mensaje="El presupuesto #".$id." ha sido adjudicado en el sistema y se requiere alta de proyecto";

		$mail = new PHPMailer();
		$mail->IsSMTP();
		$mail->SMTPAuth = true;
		$mail->Port = 587; 
		$mail->SMTPSecure = 'ssl';
		$mail->IsHTML(true); 
		$mail->CharSet = "utf-8";
		$mail->From = $smtpFrom;
		$mail->FromName = $smtpFromName;
		$mail->Host = $smtpHost; 
		$mail->Username = $smtpUsuario; 
		$mail->Password = $smtpClave;
		$mail->AddAddress($address);
		$mail->Subject = $titulo; 
		$mensajeHtml = nl2br($mensaje);
		$mail->Body = "{$mensajeHtml} <br /><br />"; 
		$mail->AltBody = "{$mensaje} \n\n"; 
		$mail->Send();

	Database::disconnect();
    header("Location: listarPresupuestos.php");
  

?>