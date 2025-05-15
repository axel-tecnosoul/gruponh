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
        header("Location: listarPedidos.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "delete from  `pedidos_detalle` WHERE id_pedido = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
	
	$sql = "delete from `pedidos` WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);

	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Rechazo de pedido','Pedidos','')";
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
	
	$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 14 ";
	foreach ($pdo->query($sql) as $row) {
		
		$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (14,?,now(),0,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$row[0],'ID Pedido: #'.$id,$id]);
		
		$address = $row[1];
		
		$titulo = "ERP Notificaciones - Módulo Compras - Rechazo de Pedido";
		$mensaje="El pedido #".$id." ha sido rechazado y eliminado del sistema";
		
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
        
    header("Location: listarPedidos.php");
