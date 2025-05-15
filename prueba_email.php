<?php
    require("config.php");
	require("PHPMailer/class.phpmailer.php");
	require("PHPMailer/class.smtp.php");
    require 'database.php';

	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
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

	$titulo = "ERP Notificaciones - Prueba de Envio";
	$mensaje="Este es un mensaje de prueba en el cuerpo del email";
	
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
	$mail->FromName = $smtpFromName;
	$mail->Host = $smtpHost; 
	$mail->Username = $smtpUsuario; 
	$mail->Password = $smtpClave;
	$mail->AddAddress($address);
	$mail->AddAddress("pruebaerp@gruponh.com.ar");
	$mail->Subject = $titulo; 
	$mensajeHtml = nl2br($mensaje);
	$mail->Body = "{$mensajeHtml} <br /><br />"; 
	$mail->AltBody = "{$mensaje} \n\n"; 
	$mail->Send();
	
	Database::disconnect();
	
	die("Prueba finalizada: chequee su email");

?>