<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';
    $prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
    $prodQuery = $prod ? '?prod=' . $prod : '';
    $prodParam = $prod ? '&prod=' . $prod : '';

    $id = null;
    if (!empty($_GET['id'])) {
        $id = $_REQUEST['id'];
    }
    
    if (null==$id) {
        header("Location: listarComputos.php$prodQuery");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "UPDATE `computos` SET id_estado = 6 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);

    $sql = "delete from `computos_detalle` WHERE id_computo = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);

	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Eliminación de cómputo ID #$id','Cómputos','')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));

    Database::disconnect();
        
    header("Location: listarComputos.php$prodQuery");
