<?php
ob_start();
require("config.php");
require 'database.php';

if (empty($_POST['id_fv'])) {
    ob_end_clean();
    echo json_encode([]);
    exit;
}

$id_fv = intval($_POST['id_fv']);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT d.id, COALESCE(d.descripcion, cc.descripcion, ''), d.precio, d.cantidad, d.subtotal
        FROM facturas_venta_detalle d
        INNER JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
        WHERE d.id_factura_venta = ?";

$q = $pdo->prepare($sql);
$q->execute([$id_fv]);

$aDetalles = [];
while ($row = $q->fetch(PDO::FETCH_NUM)) {
    $aDetalles[] = [
        0 => $row[0],
        1 => $row[1],
        2 => number_format($row[2], 2),
        3 => $row[3],
        4 => number_format($row[4], 2),
    ];
}

Database::disconnect();

ob_end_clean();
echo json_encode($aDetalles);
