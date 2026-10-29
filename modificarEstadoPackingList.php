<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

if (empty($_POST['idPosicion']) || !isset($_POST['idEstado']) || !in_array((int)$_POST['idEstado'], [1, 2, 3, 4, 5, 6, 7, 8], true)) {
	http_response_code(400);
	echo 'Estado o Packing List inválido.';
	exit;
}

$idPosicion = (int)$_POST['idPosicion'];
$idEstado = (int)$_POST['idEstado'];

// Notifica a los suscriptores del tipo y a quien elaboró (si no está suscripto, para evitar duplicados)
function notificarSuscriptoresYRealizo(PDO $pdo, int $idTipo, int $idPosicion, string $detalle, string $asuntoEmail, string $cuerpoEmail): void {
	crearNotificacion($pdo, $idTipo, $idPosicion, $detalle, $asuntoEmail, $cuerpoEmail);

	$sql = "SELECT id_usuario FROM packing_lists_revisiones WHERE id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$idPosicion]);
	$dataRevision = $q->fetch(PDO::FETCH_ASSOC);
	if (empty($dataRevision)) {
		return;
	}
	$idUsuarioRealizo = (int)$dataRevision['id_usuario'];

	$sql = "SELECT count(*) cant FROM usuarios_tipos_notificacion WHERE id_tipo_notificacion = ? AND id_usuario = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$idTipo, $idUsuarioRealizo]);
	if ($q->fetch(PDO::FETCH_ASSOC)['cant'] == 0) {
		notificarUsuarioDirecto($pdo, $idTipo, $idPosicion, $idUsuarioRealizo, $detalle, $asuntoEmail, $cuerpoEmail);
	}
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$idCuentaReviso = null;
$idCuentaValido = null;

	$sql = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_SESSION['user']['id']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	if (!empty($data)) {
		$idCuentaReviso = $data['id'];
		$idCuentaValido = $data['id'];
	}

$sql = "SELECT plr.id, plr.id_estado_packing_list, plr.nro_revision, pl.ultimo_nro_revision, pl.id_tarea FROM packing_lists_revisiones plr INNER JOIN packing_lists pl ON pl.id = plr.id_packing_list WHERE plr.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idPosicion]);
$revisionActual = $q->fetch(PDO::FETCH_ASSOC);
if (empty($revisionActual)) {
	http_response_code(404);
	echo 'No se encontró la revisión del Packing List.';
	Database::disconnect();
	exit;
}

if ($idEstado === 2 && ((int)$revisionActual['id_estado_packing_list'] !== 1 || (int)$revisionActual['nro_revision'] !== (int)$revisionActual['ultimo_nro_revision'])) {
	http_response_code(409);
	echo 'Solo se puede enviar a aprobación la última revisión que está en Elaboración.';
	Database::disconnect();
	exit;
}

// No se puede enviar una revisión incompleta a aprobación.
if ($idEstado === 2) {
	$sql = "SELECT COUNT(*) AS secciones, SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM packing_lists_componentes plc WHERE plc.id_packing_list_seccion = pls.id) THEN 1 ELSE 0 END) AS secciones_sin_componentes FROM packing_lists_secciones pls WHERE pls.id_packing_list_revision = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$idPosicion]);
	$completitud = $q->fetch(PDO::FETCH_ASSOC);
	if (empty($completitud) || (int)$completitud['secciones'] === 0 || (int)$completitud['secciones_sin_componentes'] > 0) {
		http_response_code(422);
		echo 'Agregue al menos una sección y un componente en cada sección antes de enviar a aprobación.';
		Database::disconnect();
		exit;
	}
}

$sql = "UPDATE packing_lists_revisiones SET id_estado_packing_list = ?,`id_cuenta_reviso`=?, `id_cuenta_valido`=? WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$idEstado,$idCuentaReviso,$idCuentaValido,$idPosicion]);

// La tarea asociada queda presentada al enviar el PK a aprobación.
if ($idEstado === 2 && !empty($revisionActual['id_tarea'])) {
	$sql = "UPDATE estados_tareas SET presentado = 1 WHERE id_tarea = ? AND id_tipo_tarea = 24";
	$q = $pdo->prepare($sql);
	$q->execute([$revisionActual['id_tarea']]);
}

// Descripción del proyecto para los asuntos de los avisos
$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from packing_lists_revisiones plr inner join proyectos p on p.id = plr.id_proyecto inner join sitios s on s.id = p.id_sitio where plr.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$idPosicion]);
$dataProyecto = $q->fetch(PDO::FETCH_ASSOC);
$descripcionProyecto = !empty($dataProyecto) ? $dataProyecto['nro_sitio']." - ".$dataProyecto['nro_subsitio']." - ".$dataProyecto['nro']." - ".$dataProyecto['nombre'] : '';

// Aviso de envío a aprobación (estado 2 = Enviado)
if ($idEstado === 2) {

	notificarSuscriptoresYRealizo($pdo, 7, $idPosicion,
		'ID PackingList: #'.$idPosicion,
		"Módulo Ingeniería - Packing List enviada a aprobación (".$descripcionProyecto.")",
		"La packing list #".$idPosicion." fue enviada a aprobación. Proyecto: ".$descripcionProyecto);

}

// Aviso de aprobación (estado 3)
else if ($idEstado === 3) {

	notificarSuscriptoresYRealizo($pdo, 23, $idPosicion,
		'ID PackingList: #'.$idPosicion,
		"Módulo Ingeniería - Packing List aprobada (".$descripcionProyecto.")",
		"La packing list #".$idPosicion." fue aprobada. Proyecto: ".$descripcionProyecto);

}

// Aviso de cancelación (estado 6)
else if ($idEstado === 6) {

	notificarSuscriptoresYRealizo($pdo, 24, $idPosicion,
		'ID PackingList: #'.$idPosicion,
		"Módulo Ingeniería - Packing List cancelada (".$descripcionProyecto.")",
		"La packing list #".$idPosicion." fue cancelada. Proyecto: ".$descripcionProyecto);

}

// Establecemos el estado de los componentes según el estado del PK
$sql = " SELECT id FROM packing_lists_secciones where id_packing_list_revision = ".$idPosicion;
foreach ($pdo->query($sql) as $row) {

	if ($idEstado === 5) {
		// Terminado: los componentes quedan terminados y cerramos la tarea
		$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 2 WHERE id_packing_list_seccion = ".$row[0];
		$q = $pdo->prepare($sql);
		$q->execute();

	} else {
		$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 1 WHERE id_packing_list_seccion = ".$row[0];
		$q = $pdo->prepare($sql);
		$q->execute();
	}
}

// Finalizamos la tarea asociada solo cuando el Packing List termina.
if ($idEstado === 5 && !empty($revisionActual['id_tarea'])) {
	$sql = "UPDATE tareas SET fecha_fin_real = COALESCE(fecha_fin_real, NOW()) WHERE id = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$revisionActual['id_tarea']]);
}


$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,?,'Packing List')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id'], 'Modificacion de Estado de Packing List ( Estado #'.$idEstado.' )'));

Database::disconnect();

?>
