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
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE `computos` set `id_cuenta_solicitante` = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['id_cuenta_solicitante'],$_GET['id']]);
		
        $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de computo','Computos','verComputo.php?id=$id')";
        $q = $pdo->prepare($sql);
        $q->execute(array($_SESSION['user']['id']));
        
        Database::disconnect();
        
        header("Location: verComputo.php?id=".$_GET['id']);
    } else {
        header("Location: listarComputos.php");
    }
    
?>