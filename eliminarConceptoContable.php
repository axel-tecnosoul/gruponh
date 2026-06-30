<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';

    $id = null;
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
    }

    if (null == $id) {
        header("Location: listarConceptosContables.php");
        exit;
    }

    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Eliminacion Concepto Contable','Conceptos Contables','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    $pdo->prepare("DELETE FROM conceptos_contables WHERE id = ?")->execute([$id]);

    Database::disconnect();
    header("Location: listarConceptosContables.php");
