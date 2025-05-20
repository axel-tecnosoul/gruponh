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

$id=$_GET['id'];

$sql = " SELECT d.id, d.id_material, m.concepto, d.cantidad, d.reservado, d.comprado FROM computos_detalle d inner join materiales m on m.id = d.id_material WHERE d.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

$reservado = $data['reservado'];

$sql = "UPDATE computos_detalle SET reservado=0 WHERE id=?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

/*$sql = "SELECT id, disponible, reservado, comprando FROM stock WHERE id_material = ? ";
$q = $pdo->prepare($sql);
$q->execute([$data['id_material']]);
$data2 = $q->fetch(PDO::FETCH_ASSOC);

$sql = "update stock set reservado = reservado - ?, disponible = disponible + ? where id = ?";
$q = $pdo->prepare($sql);
$q->execute([$reservado,$reservado,$data2['id']]);*/

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Cancelación de reserva de stock','Cómputos','verComputo.php?id=$id')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();
header("Location: verComputo.php?id=".$_GET['idComputo']);
?>