<?php
    require("config.php");
    require_once("PHPMailer/class.phpmailer.php");
    require_once("PHPMailer/class.smtp.php");

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
        exit();
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    try {
        $sql = "UPDATE `pedidos` SET aprobado = 1, id_estado = 3 WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);

        $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Aprobación de pedido','Pedidos','verPedido.php?id=$id')";
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
        
        $sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 3 ";
        
        foreach ($pdo->query($sql) as $row) {
            $idUsuarioDestino = $row[0];
            $address = trim($row[1]);

            if (empty($address)) {
                continue; 
            }
            
            $sqlNoti = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (3,?,now(),0,?,?)";
            $q = $pdo->prepare($sqlNoti);
            $q->execute([$idUsuarioDestino, 'ID Pedido: #'.$id, $id]);
            
            $titulo = "ERP Notificaciones - Módulo Compras - Aprobación de Pedido";
            $mensaje = "El pedido #".$id." ha sido aprobado en el sistema";
            
            $mail = new PHPMailer();
            $mail->IsSMTP();
            $mail->SMTPAuth = true;
            
            $mail->Port = 587;
            $mail->SMTPSecure = 'tls';

            $mail->CharSet = "utf-8";
            $mail->Host = $smtpHost; 
            $mail->Username = $smtpUsuario; 
            $mail->Password = $smtpClave;
            
            $mail->From = $smtpFrom;
            $mail->FromName = $_SESSION['user']['usuario'];
            
            $mail->IsHTML(true); 
            $mail->AddAddress($address);
            $mail->Subject = $titulo; 
            
            $mensajeHtml = nl2br($mensaje);
            $mail->Body = "{$mensajeHtml} <br /><br />"; 
            $mail->AltBody = "{$mensaje} \n\n"; 
            
            $mail->Send();
            $mail->ClearAddresses();
        }

    } catch (Exception $e) {
    }

    Database::disconnect();
    
    header("Location: listarPedidos.php");
?>