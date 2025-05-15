<?php
  require("config.php");
  if (empty($_SESSION['user'])) {
      header("Location: index.php");
      die("Redirecting to index.php");
  }
  
  require 'database.php';
    
	$pdo = Database::connect();
	//$sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, date_format(d.`fecha_necesidad`,'%d/%m/%y'), d.`aprobado`, d.id_material, d.`reservado`, d.`comprado`,s.disponible FROM `computos_detalle` d inner join materiales m on m.id = d.id_material left join stock s on s.id_material = d.id_material WHERE d.id_computo = ".$_POST['idComputo'];
  $sql = " SELECT d.id, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), d.aprobado, d.id_material, d.reservado, d.comprado,SUM(id.saldo) AS disponible FROM computos_detalle d inner join materiales m on m.id = d.id_material left join ingresos_detalle id on id.id_material = d.id_material WHERE d.cancelado = 0 and d.id_computo = ".$_POST['idComputo']." GROUP BY d.id_material";

	foreach ($pdo->query($sql) as $row) {
		
		$cantidad = $_POST['cantidad_'.$row[0]];
		if ($cantidad > 0) {
			/*$sql = "SELECT id, `disponible`, `reservado`, `comprando` FROM `stock` WHERE `id_material` = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$row[5]]);
			$data3 = $q->fetch(PDO::FETCH_ASSOC);
			if (!empty($data3)) {*/
				/*$sql = "update `stock` set `reservado` = `reservado` + ?, disponible = disponible - ? where id = ?";
				$q = $pdo->prepare($sql);
				$q->execute([$cantidad,$cantidad,$data3['id']]);*/
				
				$sql = "UPDATE `computos_detalle` SET `reservado`=? WHERE `id`=?";
				$q = $pdo->prepare($sql);
				$q->execute([$cantidad,$row[0]]);
			//}
		}
	}
	
	$sql = "UPDATE `computos` SET id_estado = 4 WHERE id = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['idComputo']]);
	
	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva reserva de stock','Pedidos','verPedido.php?id=$id')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));

	
	Database::disconnect();
	header("Location: verComputo.php?id=".$_POST['idComputo']);
    
?>