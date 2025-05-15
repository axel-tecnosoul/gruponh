<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarPresupuestos.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modoDebug=0;

if($modoDebug==1){
  $pdo->beginTransaction();
  var_dump($_POST);
  var_dump($_GET);
}

$id_usuario=$_SESSION['user']['id'];

$sql = "INSERT INTO presupuestos (nro, id_empresa, nro_revision, fecha, id_cuenta, solicitante, referencia, descripcion, id_moneda, monto, adjudicado, observaciones, id_usuario, anulado, es_marco, id_linea_negocio) SELECT 0, id_empresa, 0, NOW(), id_cuenta, solicitante, referencia, descripcion, id_moneda, monto, 0, observaciones, $id_usuario, anulado, es_marco, id_linea_negocio FROM presupuestos WHERE id = ?";
echo $sql;
$q = $pdo->prepare($sql);
$q->execute([$id]);
$id_presupuesto_revision = $pdo->lastInsertId();

$sql = "update presupuestos set `nro` = ? where id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_presupuesto_revision,$id_presupuesto_revision]);

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}


$sql = "SELECT pd.detalle,pd.cantidad,pd.id_unidad_medida,pd.costo,pd.precio FROM presupuestos_detalle pd WHERE pd.id_presupuesto = ".$id;
foreach ($pdo->query($sql) as $row) {

  $sql = "INSERT INTO presupuestos_detalle (id_presupuesto, detalle, cantidad, id_unidad_medida, costo, precio) VALUES (?,?,?,?,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$id_presupuesto_revision,$row["detalle"],$row["cantidad"],$row["id_unidad_medida"],$row["costo"],$row["precio"]]);

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }
}

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Se ha clonado un presupuesto','Presupuestos','verPresupuesto.php?id=$id')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

if ($modoDebug==1) {
  //echo "redirect: ".$redirect;
  $pdo->rollBack();
  Database::disconnect();
  die();
} else {
  Database::disconnect();
  //header("Location: ".$redirect);
  header("Location: modificarPresupuesto.php?id=".$id_presupuesto_revision."&revision=0");
}
