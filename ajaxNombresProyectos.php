<?php
	// Devuelve nombres de proyectos existentes (no anulados) para el filtro
	// de búsqueda del listado de proyectos.
	//
	// GET q     : texto a filtrar (LIKE '%q%')
	header('Content-Type: application/json; charset=utf-8');
	require 'database.php';

	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

	if ($q === '') {
		// Sin texto: primeros 15 nombres alfabéticamente
		$sql = "SELECT DISTINCT nombre FROM proyectos WHERE anulado = 0";
		$params = [];
	} else {
		$sql = "SELECT DISTINCT nombre FROM proyectos WHERE anulado = 0 AND LOWER(TRIM(nombre)) LIKE LOWER(TRIM(?))";
		$params = ['%' . $q . '%'];
	}

	$sql .= " ORDER BY nombre LIMIT 15";

	$qStmt = $pdo->prepare($sql);
	$qStmt->execute($params);
	$rows = [];
	while ($fila = $qStmt->fetch(PDO::FETCH_ASSOC)) {
		$rows[] = $fila['nombre'];
	}

	Database::disconnect();
	echo json_encode($rows);
	exit;
