<?php
require("config.php");
if (empty($_SESSION['user'])) { die('[]'); }
require 'database.php';

$idCliente = !empty($_GET['id_cliente']) ? intval($_GET['id_cliente']) : 0;
if (!$idCliente) { echo '[]'; exit; }

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cac.id, cm.numero AS numero_cm,
               date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,
               date_format(cac.fecha_inicio,'%d/%m/%y')  AS fecha_inicio,
               date_format(cac.fecha_fin,'%d/%m/%y')     AS fecha_fin,
               m.moneda, cac.monto_total
        FROM certificados_avances_cabecera cac
        INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro = cm.id
        INNER JOIN monedas m ON cm.id_moneda = m.id
        INNER JOIN occ occ ON occ.id = cm.id_occ
        WHERE cac.aprobado_cliente = 1
          AND occ.id_cuenta_cliente = ?";
$q = $pdo->prepare($sql);
$q->execute([$idCliente]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

echo json_encode($rows);