<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }

    require 'database.php';

    $id = !empty($_GET['id']) ? (int)$_GET['id'] : null;

    if (null == $id) {
        header("Location: listarFacturasCompra.php");
        exit;
    }

    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $q = $pdo->prepare("SELECT id_estado, pagada FROM facturas_compra WHERE id = ?");
    $q->execute([$id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        Database::disconnect();
        header("Location: listarFacturasCompra.php");
        exit;
    }

    if ($data['id_estado'] != 3) {
        Database::disconnect();
        header("Location: listarFacturasCompra.php?error=" . urlencode("Solo se pueden marcar como pagadas facturas definitivas."));
        exit;
    }

    if ($data['pagada'] == 1) {
        Database::disconnect();
        header("Location: listarFacturasCompra.php?error=" . urlencode("Esta factura ya fue pagada."));
        exit;
    }

    $pdo->prepare("UPDATE facturas_compra SET pagada = 1 WHERE id = ?")->execute([$id]);

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,'Factura de Compra marcada como pagada ID #$id','Facturas de Compra','verFacturaCompra.php?id=$id')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    Database::disconnect();
    header("Location: listarFacturasCompra.php");
