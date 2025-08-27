<?php
require("config.php");
require_once("funciones.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = $_GET['id'] ?? null;
if ($id) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Cambia estado a En Producción solo si está Pendiente
  $sql = "UPDATE ordenes_trabajo SET id_estado_orden_trabajo = 3 WHERE id = ? AND id_estado_orden_trabajo = 2";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);

  if ($q->rowCount() > 0) {
    // Actualizar estado de las posiciones
    $sqlDet = "UPDATE ordenes_trabajo_detalle SET id_estado_orden_trabajo_posicion = 3 WHERE id_orden_trabajo = ?";
    $qDet = $pdo->prepare($sqlDet);
    $qDet->execute([$id]);

    // Registrar log
    $sqlLog = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(), ?, 'Envio a producción OT', 'Orden de Trabajo', 'verOrdenTrabajo.php?id=$id')";
    $qLog = $pdo->prepare($sqlLog);
    $qLog->execute([$_SESSION['user']['id']]);

    // Obtener información para el correo/notificación
    $sqlInfo = "SELECT lc.id_proyecto, ot.nro_orden_trabajo FROM ordenes_trabajo ot INNER JOIN listas_corte lc ON ot.id_lista_corte = lc.id WHERE ot.id = ?";
    $qInfo = $pdo->prepare($sqlInfo);
    $qInfo->execute([$id]);
    $info = $qInfo->fetch(PDO::FETCH_ASSOC);

    if ($info) {
      $descProyecto = getDescripcionProyecto($pdo, $info['id_proyecto']);
      $descripcionOT = 'OT '.$info['nro_orden_trabajo'].$descProyecto;

      // Enviar notificación y correo
      $idTipoNotificacion = 8; // Producción
      $detalleNotificacion = 'ID OT: #'.$id;
      $asuntoEmail = 'Módulo Producción - Envío a Producción '.$descripcionOT;
      $cuerpoEmail = 'La '.$descripcionOT.' ha sido enviada a producción.';
      crearNotificacion($pdo, $idTipoNotificacion, $id, $detalleNotificacion, $asuntoEmail, $cuerpoEmail);
    }
  }

  Database::disconnect();
}

header("Location: listarOrdenesTrabajo.php");
