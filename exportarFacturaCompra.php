<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
require 'database.php';

$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: listarFacturasCompra.php?error=" . urlencode("ID de factura no válido."));
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$q = $pdo->prepare("SELECT id_estado FROM facturas_compra WHERE id = ?");
$q->execute([$id]);
$estado = $q->fetchColumn();

if (!$estado || !in_array((int)$estado, [3, 4, 5])) {
    header("Location: listarFacturasCompra.php?error=" . urlencode("Solo se pueden visualizar facturas terminadas (Definitiva o Pagada)."));
    exit;
}

$pdo->prepare("UPDATE facturas_compra SET exportada = 1, id_estado = 5 WHERE id = ? AND id_estado IN (3,4)")->execute([$id]);

$sql = "SELECT fc.*, tp.tipo AS tipo_comprobante, lc.letra, cu.razon_social AS proveedor,
               cu.direccion AS direccion_proveedor, cu.cuit AS cuit_proveedor,
               e.empresa, fp.forma_pago, m.moneda, u.usuario, ef.estado
        FROM facturas_compra fc
        INNER JOIN tipos_comprobante tp ON tp.id = fc.id_tipo_comprobante
        INNER JOIN letras_comprobante lc ON lc.id = fc.id_letra_comprobante
        INNER JOIN cuentas cu ON cu.id = fc.id_cuenta_origen
        INNER JOIN empresas e ON e.id = fc.id_empresa
        INNER JOIN formas_pago fp ON fp.id = fc.id_condicion_pago
        INNER JOIN monedas m ON m.id = fc.id_moneda
        INNER JOIN usuarios u ON u.id = fc.id_usuario
        INNER JOIN estados_factura ef ON ef.id = fc.id_estado
        WHERE fc.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$fc = $q->fetch(PDO::FETCH_ASSOC);

if (!$fc) {
    die("Factura no encontrada.");
}

$qOC = $pdo->prepare("SELECT c.nro_oc FROM facturas_compra_x_compras fcxc INNER JOIN compras c ON c.id = fcxc.id_compra WHERE fcxc.id_factura_compra = ?");
$qOC->execute([$id]);
$ocs = $qOC->fetchAll(PDO::FETCH_COLUMN);
$ocsStr = !empty($ocs) ? implode(' | ', $ocs) : '';

$qDet = $pdo->prepare("SELECT d.cantidad, d.precio, d.subtotal, d.descripcion, cc.descripcion AS concepto
                        FROM facturas_compra_detalle d
                        INNER JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
                        WHERE d.id_factura_compra = ?");
$qDet->execute([$id]);
$detalles = $qDet->fetchAll(PDO::FETCH_ASSOC);

$qRet = $pdo->prepare("SELECT r.monto, rf.regimen FROM facturas_compra_retenciones r INNER JOIN regimenes_facturacion rf ON rf.id = r.id_regimen_facturacion WHERE r.id_factura_compra = ?");
$qRet->execute([$id]);
$retenciones = $qRet->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura de Compra #<?= $id ?></title>
    <link rel="stylesheet" type="text/css" href="assets/css/print.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .no-print { text-align: center; margin-bottom: 20px; }
        .no-print .btn-print {
            display: inline-block;
            padding: 10px 24px;
            font-size: 15px;
            background: #1d97e1;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .no-print .btn-print:hover { background: #1479b8; }
        .no-print .btn-back {
            display: inline-block;
            padding: 10px 24px;
            font-size: 15px;
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            margin-left: 8px;
        }
        .no-print .btn-back:hover { background: #5a6268; }

        .invoice-box {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            padding: 30px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1d97e1;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .invoice-header .title h1 {
            margin: 0;
            font-size: 24px;
            color: #1d97e1;
        }
        .invoice-header .title small {
            color: #777;
            font-size: 13px;
        }
        .invoice-header .badge {
            background: #28a745;
            color: #fff;
            padding: 4px 12px;
            border-radius: 3px;
            font-size: 13px;
            font-weight: bold;
        }

        .info-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-grid .col {
            flex: 1;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .info-grid .col strong {
            display: block;
            font-size: 11px;
            color: #777;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .info-grid .col span {
            display: block;
            font-size: 14px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.items thead th {
            background: #1d97e1;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        table.items tbody td.right { text-align: right; }
        table.items tbody tr:nth-child(even) { background: #f8f9fa; }
        table.items tbody tr.total-row td {
            font-weight: bold;
            border-top: 2px solid #333;
        }

        table.totals {
            width: 350px;
            margin-left: auto;
            border-collapse: collapse;
        }
        table.totals td {
            padding: 6px 10px;
        }
        table.totals td.label { text-align: right; color: #777; }
        table.totals td.value { text-align: right; font-weight: bold; }
        table.totals tr.final td {
            border-top: 2px solid #333;
            font-size: 18px;
            color: #1d97e1;
        }

        .footer-note {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            color: #777;
            font-size: 12px;
        }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .invoice-box {
                border: none;
                padding: 20px;
                max-width: 100%;
            }
            .info-grid .col { background: #f8f9fa !important; }
            table.items thead th { background: #1d97e1 !important; color: #fff !important; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <a href="javascript:void(0)" class="btn-print" onclick="window.print()">🖨 Imprimir / PDF</a>
    <a href="listarFacturasCompra.php" class="btn-back">← Volver al Listado</a>
</div>

<div class="invoice-box">

    <div class="invoice-header">
        <div class="title">
            <h1>FACTURA DE COMPRA</h1>
            <small>#<?= $id ?> — <?= htmlspecialchars($fc['empresa']) ?></small>
        </div>
        <div class="badge"><?= htmlspecialchars($fc['estado']) ?></div>
    </div>

    <div class="info-grid">
        <div class="col">
            <strong>Proveedor</strong>
            <span><?= htmlspecialchars($fc['proveedor']) ?></span>
            <?php if (!empty($fc['cuit_proveedor'])): ?>
                <span style="font-size:12px;color:#777;">CUIT: <?= htmlspecialchars($fc['cuit_proveedor']) ?></span>
            <?php endif; ?>
        </div>
        <div class="col">
            <strong>Comprobante</strong>
            <span><?= htmlspecialchars($fc['tipo_comprobante']) ?> — <?= htmlspecialchars($fc['letra']) ?></span>
            <span style="font-size:12px;color:#777;">N° <?= htmlspecialchars($fc['numero']) ?></span>
        </div>
        <div class="col">
            <strong>Fechas</strong>
            <span>Emitida: <?= htmlspecialchars($fc['fecha_emitida']) ?></span>
            <span style="font-size:12px;color:#777;">Recibida: <?= htmlspecialchars($fc['fecha_recibida']) ?></span>
        </div>
    </div>

    <div class="info-grid">
        <div class="col">
            <strong>Condición de Pago</strong>
            <span><?= htmlspecialchars($fc['forma_pago']) ?></span>
        </div>
        <div class="col">
            <strong>Moneda / Cotización</strong>
            <span><?= htmlspecialchars($fc['moneda']) ?> — <?= number_format($fc['cotizacion'], 2) ?></span>
        </div>
        <div class="col">
            <strong>Órdenes de Compra</strong>
            <span><?= htmlspecialchars($ocsStr ?: '—') ?></span>
        </div>
    </div>

    <?php if (!empty($fc['descripcion'])): ?>
    <div class="info-grid">
        <div class="col" style="flex:2;">
            <strong>Descripción</strong>
            <span><?= nl2br(htmlspecialchars($fc['descripcion'])) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <table class="items">
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Descripción</th>
                <th class="right">Cantidad</th>
                <th class="right">Precio Unit.</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php $sumDetalles = 0; ?>
            <?php foreach ($detalles as $det): ?>
            <?php $sumDetalles += (float)$det['subtotal']; ?>
            <tr>
                <td><?= htmlspecialchars($det['concepto']) ?></td>
                <td><?= htmlspecialchars($det['descripcion'] ?? '') ?></td>
                <td class="right"><?= (int)$det['cantidad'] ?></td>
                <td class="right">$ <?= number_format($det['precio'], 2) ?></td>
                <td class="right">$ <?= number_format($det['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal Gravado:</td>
            <td class="value">$ <?= number_format($fc['subtotal_gravado'] ?? 0, 2) ?></td>
        </tr>
        <tr>
            <td class="label">Subtotal No Gravado:</td>
            <td class="value">$ <?= number_format($fc['subtotal_no_gravado'] ?? 0, 2) ?></td>
        </tr>
        <tr>
            <td class="label">IVA:</td>
            <td class="value">$ <?= number_format($fc['iva'] ?? 0, 2) ?></td>
        </tr>
        <?php if (!empty($retenciones)): ?>
        <tr>
            <td class="label">Retenciones:</td>
            <td class="value">$ <?= number_format(array_sum(array_column($retenciones, 'monto')), 2) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($fc['otros'])): ?>
        <tr>
            <td class="label">Otros:</td>
            <td class="value">$ <?= number_format($fc['otros'], 2) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="final">
            <td class="label">TOTAL:</td>
            <td class="value">$ <?= number_format($fc['total'] ?? 0, 2) ?></td>
        </tr>
    </table>

    <?php if (!empty($retenciones)): ?>
    <div class="info-grid" style="margin-top:20px;">
        <div class="col">
            <strong>Detalle de Retenciones</strong>
            <?php foreach ($retenciones as $ret): ?>
                <span><?= htmlspecialchars($ret['regimen']) ?>: $ <?= number_format($ret['monto'], 2) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($fc['observaciones'])): ?>
    <div class="footer-note">
        <strong>Observaciones:</strong><br>
        <?= nl2br(htmlspecialchars($fc['observaciones'])) ?>
    </div>
    <?php endif; ?>

    <div class="footer-note" style="text-align:center;">
        Generado por <?= htmlspecialchars($fc['usuario']) ?> — <?= date('d/m/Y H:i') ?>
    </div>

</div>

<script>
    window.onload = function() {
        setTimeout(function() {
            var p = window.location.search.match(/print=1/);
            if (p) window.print();
        }, 300);
    };
</script>
</body>
</html>