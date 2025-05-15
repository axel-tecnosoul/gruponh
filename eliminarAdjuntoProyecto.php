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
        header("Location: listarProyectos.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "update adjuntos_proyecto set anulado = 1 where id = ? ";
    $q = $pdo->prepare($sql);
    $q->execute(array($id));
	
	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Eliminación de adjunto de proyecto','Proyectos','verProyecto.php?id=$id')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));
	
        
    Database::disconnect();
        
    header("Location: adjuntarProyecto.php?id=".$_GET['idProyecto']);
