<?php
	// Devuelve nombres de sitios principales existentes para el autocompletado
	// del campo "Nombre Sitio" y la validación anti-duplicados.
	//
	// GET q          : texto a filtrar (LIKE '%q%')
	// GET exclude_id : id de sitio a excluir (para no contarse a sí mismo al modificar)
	// GET exact      : si = 1, compara el nombre completo y exacto (ignora mayúsculas)
	//                  y devuelve solo los que coinciden (0 o 1) -> para validar duplicado.
	header('Content-Type: application/json; charset=utf-8');
	require 'database.php';

	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$excludeId = !empty($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
	$exact = !empty($_GET['exact']) ? 1 : 0;
	$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

	if ($exact) {
		// Coincidencia exacta (ignora mayúsculas y espacios) -> para validar duplicado
		$sql = "SELECT DISTINCT nombre FROM sitios WHERE nro_subsitio = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?))";
		$params = [$q];
	} elseif ($q === '') {
		// Sin texto: primeros 15 nombres alfabéticamente
		$sql = "SELECT DISTINCT nombre FROM sitios WHERE nro_subsitio = 0";
		$params = [];
	} else {
		// Coincidencia parcial para el autocompletado
		$sql = "SELECT DISTINCT nombre FROM sitios WHERE nro_subsitio = 0 AND LOWER(TRIM(nombre)) LIKE LOWER(TRIM(?))";
		$params = ['%' . $q . '%'];
	}

	if ($excludeId !== null && $excludeId > 0) {
		$sql .= " AND id <> ? ";
		$params[] = $excludeId;
	}

	if ($exact) {
		$sql .= " LIMIT 1";
	} else {
		$sql .= " ORDER BY nombre LIMIT 15";
	}

	$qStmt = $pdo->prepare($sql);
	$qStmt->execute($params ?: []);
	$rows = [];
	while ($fila = $qStmt->fetch(PDO::FETCH_ASSOC)) {
		$rows[] = $fila['nombre'];
	}

	Database::disconnect();
	echo json_encode($rows);
	exit;
