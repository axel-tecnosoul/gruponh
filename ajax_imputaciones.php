<?php
require("config.php");
if (empty($_SESSION['user'])) { die('[]'); }
require 'database.php';

$idCliente = !empty($_GET['id_cliente']) ? intval($_GET['id_cliente']) : 0;
if (!$idCliente) { echo '[]'; exit; }

// CA vinculados (ej. ?certificados[]=): siempre incluidos aunque sean de otro cliente
$incluir = [];
if (!empty($_GET['incluir'])) {
  foreach (explode(',', (string)$_GET['incluir']) as $v) {
    $v = intval($v);
    if ($v > 0) { $incluir[] = $v; }
  }
  $incluir = array_values(array_unique($incluir));
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cac.id, cm.id AS numero_cm, cac.nro_certificado, cac.nro_revision,
               CONCAT('Cert. ', cac.nro_certificado, '/', cac.nro_revision) AS cert_label,
               date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,
               cm.id_moneda AS id_moneda,
               m.moneda, cac.monto_total
        FROM certificados_avances_cabecera cac
        INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro = cm.id
        LEFT JOIN monedas m ON cm.id_moneda = m.id
        LEFT JOIN occ occ ON occ.id = cm.id_occ
        WHERE cac.aprobado_cliente = 1
          AND (occ.id_cuenta_cliente = ?";
$params = [$idCliente];
if (!empty($incluir)) {
  $sql .= " OR cac.id IN (" . implode(',', array_fill(0, count($incluir), '?')) . ")";
  $params = array_merge($params, $incluir);
}
$sql .= ") ORDER BY cac.id";
$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

echo json_encode($rows);