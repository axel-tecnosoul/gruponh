<?php
require("config.php");
if (empty($_SESSION['user'])) {
	header('Content-Type: application/json; charset=utf-8', true, 401);
	echo json_encode(['ok' => false, 'afecta_lc' => false, 'error' => 'Sesión expirada']);
	exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once 'database.php';
require_once 'funciones.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($id <= 0) {
	echo json_encode(['ok' => false, 'afecta_lc' => false, 'error' => 'ID de concepto inválido']);
	exit;
}

$nuevosDatos = [
	'codigo'     => $_POST['codigo'] ?? null,
	'concepto'   => $_POST['concepto'] ?? null,
	'calidad'    => $_POST['calidad'] ?? null,
	'peso_metro' => $_POST['peso_metro'] ?? null,
];

try {
	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$impacto = obtenerImpactoModificacionConceptoEnListasCorte($pdo, $id, $nuevosDatos);
	Database::disconnect();

	echo json_encode(array_merge(['ok' => true], $impacto));
} catch (Throwable $e) {
	Database::disconnect();
	echo json_encode(['ok' => false, 'afecta_lc' => false, 'error' => $e->getMessage()]);
}
exit;
