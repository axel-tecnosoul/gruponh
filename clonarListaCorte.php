<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';
$prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodQuery = $prod ? '?prod=' . $prod : '';
$prodParam = $prod ? '&prod=' . $prod : '';
// funciones.php is included from config.php. Ensure it is available for helper utilities

$id = null;
if (!empty($_GET['id_lista_corte'])) {
  $id = $_REQUEST['id_lista_corte'];
}

if (null==$id) {
  header("Location: listarListasCorte.php$prodQuery");
}

// insert data
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modoDebug=0;

if ($modoDebug==1) {
  $pdo->beginTransaction();
  var_dump($_POST);
  var_dump($_GET);
  var_dump($_FILES);
}

$id_lista_corte_clonar = $_GET['id_lista_corte'];
$id_tarea_nueva = null;
if (!empty($_GET['id_tarea'])) {
  $id_tarea_nueva = (int)$_GET['id_tarea'];
}

$sql = "SELECT id_proyecto, id_tarea, fecha, id_usuario, id_estado_lista_corte, anulado, nombre, descripcion, adjunto, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido FROM listas_corte WHERE id = ?";
$q = $pdo->prepare($sql);
$params = [$id_lista_corte_clonar];
$q->execute($params);
$data = $q->fetch(PDO::FETCH_ASSOC);

if ($modoDebug==1) {
  //$q->debugDumpParams();
  echo debugQuery($pdo,$sql,$params);
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}


$id_proyecto_destino = $data['id_proyecto'];
if ($id_tarea_nueva) {
  $sql = "SELECT id_proyecto FROM tareas WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_tarea_nueva]);
  $tarea = $q->fetch(PDO::FETCH_ASSOC);
  if (!empty($tarea['id_proyecto'])) {
    $id_proyecto_destino = (int)$tarea['id_proyecto'];
  }
}

$sql = "SELECT MAX(numero) AS max_num FROM listas_corte WHERE id_proyecto = ?";
$q = $pdo->prepare($sql);
$params=[$id_proyecto_destino];
$q->execute($params);
$cant = $q->fetch(PDO::FETCH_ASSOC);
$numero_lc = ((int)$cant['max_num']) + 1;

if ($modoDebug==1) {
  //$q->debugDumpParams();
  echo debugQuery($pdo,$sql,$params);
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "INSERT INTO listas_corte (id_proyecto, id_tarea, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero, adjunto, descripcion, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
$q = $pdo->prepare($sql);
$params = [
  $id_proyecto_destino,
  $id_tarea_nueva ? $id_tarea_nueva : $data['id_tarea'],
  $data['fecha'],
  $data['id_usuario'],
  1,
  0,
  0,
  $data['nombre'],
  $numero_lc,
  $data['adjunto'],
  'Emisión original',
  $data['id_cuenta_realizo'],
  $data['id_cuenta_reviso'],
  $data['id_cuenta_valido']
];
$q->execute($params);
$id_lista_corte = $pdo->lastInsertId();

if ($modoDebug==1) {
  //$q->debugDumpParams();
  echo debugQuery($pdo,$sql,$params);
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

// Duplicar conjuntos, posiciones y procesos
duplicarListaCorteRevision($pdo, (int)$id_lista_corte_clonar, $id_lista_corte, $modoDebug);

$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,'Se ha clonado una Lista de Corte','Listas de Corte','imprimirListaCorte.php?id=$id_lista_corte')";
$q = $pdo->prepare($sql);
$params = [$_SESSION['user']['id']];
$q->execute($params);

if ($modoDebug==1) {
  //$q->debugDumpParams();
  echo debugQuery($pdo,$sql,$params);
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

if ($modoDebug==1) {
  $pdo->rollBack();
  die();
} else {
  Database::disconnect();
  //header("Location: nuevaListaCorteConjuntos.php?id_lista_corte=".$id_lista_corte);
  header("Location: nuevaListaCorte.php?modo=update&id_lista_corte=".$id_lista_corte.$prodParam);
  exit();
}
?>
