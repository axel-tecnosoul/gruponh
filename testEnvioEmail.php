<?php
//include_once 'config.php';
include_once 'database.php';
require_once("funciones.php");

$pdo = Database::connect();

$smtp = [];
for ($i = 1; $i <= 5; $i++) {
  $stmt = $pdo->prepare("SELECT valor FROM parametros WHERE id = ?");
  $stmt->execute([$i]);
  $smtp[$i] = $stmt->fetchColumn();
}
list($smtpHost, $smtpUsuario, $smtpClave, $smtpFrom, $smtpFromName) = [$smtp[1], $smtp[2], $smtp[3], $smtp[4], $smtp[5]];

$idComputo=90;
$descProyecto="Proyecto de Prueba";

$idTipoNotificacion=15;
$idEntidad=$idComputo;
$detalleNotificacion="ID Computo: #".$idComputo;
$asuntoEmail="Producción - Aprobación de Cómputo ({$descProyecto})";
$cuerpoEmail="El cómputo #{$descProyecto} ha sido aprobado.";
//crearNotificacion($pdo,$idTipoNotificacion,$idEntidad,$detalleNotificacion,$asuntoEmail,$cuerpoEmail);

$destEmail="axelbritzius@gmail.com";

// Armo y envío mail
//$titulo  = "ERP Notificaciones - Producción - Revisión Cómputo ({$descProyecto})";
$titulo  = "ERP Notificaciones - ".$asuntoEmail; // Usar el asunto del email pasado como parámetro
//$mensaje = "La revisión de cómputo #{$descProyecto} está lista para aprobación.";
$mensaje = $cuerpoEmail; // Usar el cuerpo del email pasado como parámetro

$mail = new PHPMailer();
$mail->SMTPDebug = 3;//Habilitamos solo para debugguear
$mail->IsSMTP();
$mail->Host       = $smtpHost;
//$mail->SMTPAuth   = true;
$mail->Username   = $smtpUsuario;
$mail->Password   = $smtpClave;

/*$mail->Port = 465;
$mail->SMTPSecure = 'ssl';*/
/*$mail->Port       = 25;
$mail->SMTPSecure = false;*/
//$mail->Port = 587;
//$mail->SMTPSecure = 'tls';

$mail->SMTPAuth = true;
$mail->Port = 25; 
$mail->SMTPSecure = 'ssl';
$mail->SMTPAutoTLS = false;
$mail->SMTPSecure = false;

$mail->From       = $smtpFrom;
$mail->FromName   = "Testing ERP Notificaciones";
$mail->CharSet    = "utf-8";
$mail->IsHTML(true);
$mail->clearAddresses(); // <- MUY IMPORTANTE LIMPIAR destinatarios anteriores
$mail->AddAddress($destEmail);
$mail->Subject    = $titulo;
$mail->Body       = nl2br($mensaje) . "<br><br>";
$mail->AltBody    = $mensaje;
//$mail->Send();
$envio = $mail->Send();