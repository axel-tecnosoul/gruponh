<?php
    require("config.php");

    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }

    require 'database.php';
    require_once('funciones.php');

    $id = null;
    if (!empty($_GET['id'])) {
        $id = $_REQUEST['id'];
    }

    if (null == $id) {
        header("Location: listarPedidos.php");
        exit();
    }

    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "UPDATE pedidos SET aprobado = 1, id_estado = 3 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);

    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) 
            VALUES (now(), ?, 'Aprobación de pedido', 'Pedidos', 'verPedido.php?id=$id')";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id']]);

    $detalleNotificacion = "ID Pedido: #" . $id;
    $asuntoEmail = "Módulo Compras - Aprobación de Pedido";
    $cuerpoEmail = "El pedido #" . $id . " ha sido aprobado en el sistema";

    crearNotificacion($pdo, 3, $id, $detalleNotificacion, $asuntoEmail, $cuerpoEmail);

    Database::disconnect();
    header("Location: listarPedidos.php");