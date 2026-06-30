<?php
// AJAX endpoint para modal de nueva factura de compra.
// - Sin id_proveedor: devuelve proveedores con OC pendientes de facturar.
// - Con id_proveedor: devuelve OC pendientes de ese proveedor con detalle.
require("config.php");
if (empty($_SESSION['user'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
require 'database.php';

header('Content-Type: application/json');

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$idProveedor = isset($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : 0;

if ($idProveedor === 0) {
    // Modo 1: Devolver lista de proveedores con OC pendientes de facturar
    $sql = "SELECT cu.id, cu.razon_social, cu.cuit, COUNT(c.id) AS cant_oc
            FROM cuentas cu
            INNER JOIN compras c ON c.id_cuenta_proveedor = cu.id
            WHERE c.id_estado_compra NOT IN (4)
              AND (
                SELECT COALESCE(SUM(cd.cantidad), 0) FROM compras_detalle cd WHERE cd.id_compra = c.id
              ) > (
                SELECT COALESCE(SUM(fcdx.cantidad), 0)
                FROM facturas_compra_detalle_x_compras_detalle fcdx
                INNER JOIN compras_detalle cd2 ON cd2.id = fcdx.id_compra_detalle
                WHERE cd2.id_compra = c.id
              )
            GROUP BY cu.id, cu.razon_social, cu.cuit
            ORDER BY cu.razon_social ASC";
    $q = $pdo->prepare($sql);
    $q->execute();
    $proveedores = $q->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($proveedores);
} else {
    // Modo 2: Devolver OC pendientes de un proveedor con detalles
    $sql = "SELECT c.id, c.nro_oc, DATE_FORMAT(c.fecha_emision, '%d/%m/%Y') AS fecha_emision,
                   DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') AS fecha_entrega,
                   c.total, m.moneda, ec.estado,
                   c.comentarios
            FROM compras c
            INNER JOIN monedas m ON m.id = c.id_moneda
            INNER JOIN estados_compra ec ON ec.id = c.id_estado_compra
            WHERE c.id_estado_compra NOT IN (4)
              AND c.id_cuenta_proveedor = ?
              AND (
                SELECT COALESCE(SUM(cd.cantidad), 0) FROM compras_detalle cd WHERE cd.id_compra = c.id
              ) > (
                SELECT COALESCE(SUM(fcdx.cantidad), 0)
                FROM facturas_compra_detalle_x_compras_detalle fcdx
                INNER JOIN compras_detalle cd2 ON cd2.id = fcdx.id_compra_detalle
                WHERE cd2.id_compra = c.id
              )
            ORDER BY c.fecha_emision DESC";
    $q = $pdo->prepare($sql);
    $q->execute([$idProveedor]);
    $ordenes = $q->fetchAll(PDO::FETCH_ASSOC);

    // Para cada OC, traer sus items de detalle
    foreach ($ordenes as &$oc) {
        $sqlDet = "SELECT cd.id, mat.concepto AS descripcion, cd.cantidad, cd.entregado,
                          um.unidad_medida, cd.precio, cd.subtotal
                   FROM compras_detalle cd
                   INNER JOIN materiales mat ON mat.id = cd.id_material
                   INNER JOIN unidades_medida um ON um.id = cd.id_unidad_medida
                   WHERE cd.id_compra = ?
                   ORDER BY cd.id ASC";
        $qd = $pdo->prepare($sqlDet);
        $qd->execute([$oc['id']]);
        $oc['detalles'] = $qd->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($ordenes);
}

Database::disconnect();
