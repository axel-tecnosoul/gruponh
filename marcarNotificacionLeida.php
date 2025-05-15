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
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
    $sql = "UPDATE `notificaciones`  SET leida = 1 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    
	Database::disconnect();
        
    header("Location: ".$_GET['returnURL']);
