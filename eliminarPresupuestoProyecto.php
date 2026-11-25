<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $idProyecto = filter_input(INPUT_GET, 'idProyecto', FILTER_VALIDATE_INT);
    if (!$id || $id < 1 || !$idProyecto || $idProyecto < 1) {
        header("Location: listarProyectos.php");
        exit;
    }

    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "delete from proyectos_presupuestos where id = ? AND id_proyecto = ? ";
    $q = $pdo->prepare($sql);
    $q->execute([$id, $idProyecto]);
        
    Database::disconnect();
        
    header("Location: listarProyectos.php");
