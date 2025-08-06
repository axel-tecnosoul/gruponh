<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$id_lista_corte = null;
if (!empty($_GET['id_lista_corte'])) {
  $id_lista_corte = $_REQUEST['id_lista_corte'];
}

if (null==$id_lista_corte) {
  header("Location: listarListasCorte.php");
}

if (!empty($_POST)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Datos de la revisión actual
  $sql = "SELECT id_proyecto, id_tarea, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero, adjunto, descripcion, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido FROM listas_corte WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  // Crear nueva revisión duplicando los datos anteriores
  $nuevoNro = $data['nro_revision'] + 1;
  $motivo = trim($_POST['motivoRevision']);
  $id_estado_lista_corte = 1;

  $sql = "INSERT INTO listas_corte (id_proyecto, id_tarea, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero, adjunto, descripcion, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $params = [$data['id_proyecto'], $data['id_tarea'], $data['fecha'], $data['id_usuario'], $id_estado_lista_corte, $nuevoNro, $data['anulado'], $data['nombre'], $data['numero'], $data['adjunto'], $motivo, $data['id_cuenta_realizo'], $data['id_cuenta_reviso'], $data['id_cuenta_valido']];
  $q = $pdo->prepare($sql);
  $q->execute($params);
  $id_nueva_lista_corte = $pdo->lastInsertId();

  // Copiar conjuntos y posiciones
  duplicarListaCorteRevision($pdo, $id_lista_corte, $id_nueva_lista_corte);

  // Registrar en logs
  $detalle = 'Nueva revisión de lista de corte';
  if (!empty($_POST['comentarios'])) {
      $detalle .= ' - ' . $_POST['comentarios'];
  }
  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,?,'Listas de Corte','imprimirListaCorte.php?id=$id_nueva_lista_corte')";
  $q = $pdo->prepare($sql);
  $q->execute([$_SESSION['user']['id'], $detalle]);

  Database::disconnect();

  header("Location: nuevaListaCorte.php?modo=update&id_lista_corte=".$id_nueva_lista_corte);
}