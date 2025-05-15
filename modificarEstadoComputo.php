<?php
require("config.php");
require("PHPMailer/class.phpmailer.php");
require("PHPMailer/class.smtp.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$idCuentaReviso = null;
$idCuentaValido = null;

$sql = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
$q = $pdo->prepare($sql);
$q->execute([$_SESSION['user']['id']]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (!empty($data)) {
	$idCuentaReviso = $data['id'];
	$idCuentaValido = $data['id'];
}

$sql = "UPDATE computos SET id_estado = ?,`id_cuenta_reviso`=?, `id_cuenta_valido`=? WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$_POST["idEstado"],$idCuentaReviso,$idCuentaValido,$_POST["idPosicion"]]);

if (($_POST["idEstado"] == 1) || ($_POST["idEstado"] == 2) || ($_POST["idEstado"] == 6)) {
	$sql = "UPDATE computos_detalle SET aprobado = 0 WHERE id_computo = ".$_POST["idPosicion"];
	$q = $pdo->prepare($sql);
	$q->execute();
} else if (($_POST["idEstado"] == 3) || ($_POST["idEstado"] == 4) || ($_POST["idEstado"] == 5)) {
	$sql = "UPDATE computos_detalle SET aprobado = 1 WHERE id_computo = ".$_POST["idPosicion"];
	$q = $pdo->prepare($sql);
	$q->execute();	
	
	if ($_POST["idEstado"] == 5) {
		$sql = "UPDATE tareas SET fecha_fin_real = now() WHERE id = (select id_tarea from computos where id = ?)";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST["idPosicion"]]);
	}
}


$afe = $q->rowCount();

echo $afe;
$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Estado de Cómputo','Computos')";
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

$sql = "SELECT id_tarea FROM `computos` WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$_POST["idPosicion"]]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$idTarea = $data['id_tarea'];

$sql = "select id_proyecto from tareas where id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$idTarea]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$idProyecto = $data['id_proyecto'];

$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$idProyecto]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$descripcionProyecto = $data['nro_sitio']." - ".$data['nro_subsitio']." - ".$data['nro']." - ".$data['nombre'];

$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 8 ";
foreach ($pdo->query($sql) as $row) {
	
	$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (8,?,now(),0,?,?)";
	$q = $pdo->prepare($sql);
	$q->execute([$row[0],'ID Computo: #'.$_POST["idPosicion"],$_POST["idPosicion"]]);
	
	$address = $row[1];
	
	$titulo = "ERP Notificaciones - Módulo Producción - Cambio de Estado Cómputo (".$descripcionProyecto.")";
	$mensaje="Cambio de Estado de cómputo en el sistema: #".$descripcionProyecto;
	
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

?>