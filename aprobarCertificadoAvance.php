<?php
    require("config.php");
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
        header("Location: listarCertificadosAvances.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
    $sql = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$id]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$id_certificado_maestro=$data["id_certificado_maestro"];
        
    $sql = "UPDATE `certificados_avances_cabecera`  SET 	aprobado_cliente = 1 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);

	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Aprobación de Cliente por Certificado Avance #$id','Certificado Avance','')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));
        
    Database::disconnect();
        
    header("Location: listarCertificadosAvances.php?id_certificado_maestro=".$id_certificado_maestro);
