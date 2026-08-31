<?php 
if (!function_exists('tienePermiso')) {
function tienePermiso($p) {
	
	$idPermiso = in_array($p,$_SESSION['user']['permisos'] ?? []);
	return $idPermiso;	
}
}

if (!function_exists('esOperacionesSinEconomico')) {
function esOperacionesSinEconomico() {
	// Opción A: detectado por acción Ver_Certificados_Sin_Montos (id dinámico) + fallback por nombre de perfil
	static $cache = null;
	if ($cache !== null) return $cache;
	// 1) Si la sesión ya trae el flag
	if (!empty($_SESSION['user']['es_operaciones_sin_eco'])) return $cache = true;
	// 2) Check por id_perfil -> nombre en DB (robusto aunque el id sea dinámico)
	if (!empty($_SESSION['user']['id_perfil'])) {
		try {
			if (class_exists('Database')) {
				$pdo = Database::connect();
				$q = $pdo->prepare("SELECT perfil FROM perfiles WHERE id = ?");
				$q->execute([$_SESSION['user']['id_perfil']]);
				$nombre = $q->fetchColumn();
				if ($nombre === 'Operaciones') return $cache = true;
			}
		} catch (Exception $e) {}
	}
	// 3) Check por acción dinámica
	try {
		if (class_exists('Database')) {
			$pdo = Database::connect();
			$q = $pdo->prepare("SELECT id FROM acciones WHERE accion='Ver_Certificados_Sin_Montos' LIMIT 1");
			$q->execute();
			$idAcc = $q->fetchColumn();
			if ($idAcc && tienePermiso((int)$idAcc)) return $cache = true;
		}
	} catch (Exception $e) {}
	return $cache = false;
}
}

if (!function_exists('puedeVerEconomicoCertificados')) {
function puedeVerEconomicoCertificados() {
	return !esOperacionesSinEconomico();
}
}
?>