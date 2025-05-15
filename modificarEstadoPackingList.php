<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

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

$sql = "UPDATE packing_lists_revisiones SET id_estado_packing_list = ?,`id_cuenta_reviso`=?, `id_cuenta_valido`=? WHERE id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$_POST["idEstado"],$idCuentaReviso,$idCuentaValido,$_POST["idPosicion"]]);

$sql = " SELECT id FROM packing_lists_secciones where id_packing_list_revision = ".$_POST["idPosicion"];
foreach ($pdo->query($sql) as $row) {
	
	if ($_POST["idEstado"] == 1) {
		$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 1 WHERE id_packing_list_seccion = ".$row[0];
		$q = $pdo->prepare($sql);
		$q->execute();	
	} else if ($_POST["idEstado"] == 2) {
		$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 1 WHERE id_packing_list_seccion = ".$row[0];
		$q = $pdo->prepare($sql);
		$q->execute();	
	} else if ($_POST["idEstado"] == 3) {
		$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 1 WHERE id_packing_list_seccion = ".$row[0];
		$q = $pdo->prepare($sql);
		$q->execute();	
	} else if ($_POST["idEstado"] == 4) {
		$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 1 WHERE id_packing_list_seccion = ".$row[0];
		$q = $pdo->prepare($sql);
		$q->execute();	
	} else if ($_POST["idEstado"] == 5) {
		$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 2 WHERE id_packing_list_seccion = ".$row[0];
		$q = $pdo->prepare($sql);
		$q->execute();	
		
		$sql = "UPDATE tareas SET fecha_fin_real = now() WHERE id = (select pl.id_tarea from packing_lists_revisiones plr inner join packing_lists pl on pl.id = plr.id_packing_list where plr.id = ?)";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST["idPosicion"]]);
		
	} else if ($_POST["idEstado"] == 6) {
		$sql = "UPDATE packing_lists_componentes SET id_estado_componente_packing_list = 1 WHERE id_packing_list_seccion = ".$row[0];
		$q = $pdo->prepare($sql);
		$q->execute();	
	}
}

	
$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Estado de Packing List','Packing List')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();

?>