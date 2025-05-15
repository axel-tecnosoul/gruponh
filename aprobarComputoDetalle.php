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
        header("Location: listarComputos.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "UPDATE `computos_detalle` SET aprobado = 1 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
	
	$sql = "SELECT count(*) cant FROM `computos_detalle` WHERE aprobado = 0 and id_computo = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_GET['idComputo']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	if ($data['cant'] == 0) {
		$sql = "UPDATE `computos` SET id_estado = 3 WHERE id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_GET['idComputo']]);		
	}

	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Aprobación de detalle de cómputo','Cómputos','verComputo.php?id=$id')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));

    Database::disconnect();
        
    header("Location: verComputo.php?id=".$_GET['idComputo']);
