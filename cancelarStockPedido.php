<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

// insert data
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();
$modoDebug=0;

$id=$_GET['id'];

$sql = " SELECT cd.id_computo, cd.id, cd.id_material, m.concepto, cd.cantidad, cd.reservado, cd.comprado FROM computos_detalle cd inner join materiales m on m.id = cd.id_material WHERE cd.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

if ($modoDebug === 1) {
  var_dump($data);
}

$reservado = $data['reservado'];
$id_computo = $data['id_computo'];

//$sql = "UPDATE computos_detalle SET reservado=0 WHERE id=?";
//AL CANCELAR LA RESERVA AUMENTAMOS EL SALDO
$sql = "UPDATE computos_detalle SET saldo = saldo + $reservado, reservado=0 WHERE id=?";
$q = $pdo->prepare($sql);
$params = [$id];
$q->execute($params);

if ($modoDebug === 1) {
  // Generar y mostrar la consulta “real”
  $fullSql = debugQuery($pdo, $sql, $params);
  echo $fullSql . "<br><br>";
}

$ok=marcarComputoGestionandoOTerminado($pdo, $id_computo);

/*$sql = "SELECT id, disponible, reservado, comprando FROM stock WHERE id_material = ? ";
$q = $pdo->prepare($sql);
$q->execute([$data['id_material']]);
$data2 = $q->fetch(PDO::FETCH_ASSOC);

$sql = "update stock set reservado = reservado - ?, disponible = disponible + ? where id = ?";
$q = $pdo->prepare($sql);
$q->execute([$reservado,$reservado,$data2['id']]);*/

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Cancelación de reserva de stock','Cómputos','verComputo.php?id=$id')";
$q = $pdo->prepare($sql);
$params = [$_SESSION['user']['id']];
$q->execute($params);

if ($modoDebug === 1) {
  // Generar y mostrar la consulta “real”
  $fullSql = debugQuery($pdo, $sql, $params);
  echo $fullSql . "<br><br>";
}

if ($modoDebug === 1) {
  $pdo->rollBack();
}else {
  $pdo->commit();
}

Database::disconnect();

//die();
header("Location: verComputo.php?id=".$_GET['idComputo']);
?>