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
        header("Location: listarRegimenes.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "UPDATE `regimenes_facturacion` SET anulado = 1 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);

	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Eliminación de regimen ID #$id','Regimenes','')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));

    Database::disconnect();
        
    header("Location: listarRegimenes.php");
