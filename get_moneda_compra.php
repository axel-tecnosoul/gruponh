<?php
require("config.php");
require 'database.php';

$id_compra = $_POST['id_compra'] ?? null;

if (!$id_compra) {
    echo '$';
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT m.moneda FROM compras c LEFT JOIN monedas m ON m.id = c.id_moneda WHERE c.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_compra]);

$simboloMoneda = $q->fetchColumn();

Database::disconnect();

echo $simboloMoneda ?: '$';
?>