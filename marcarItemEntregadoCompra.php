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
        header("Location: listarCompras.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "UPDATE `compras_detalle` SET entregado = 1 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
	
	$sql = "SELECT cd.`id`, cd.`id_compra`, cd.`id_material`, cd.`cantidad`, cd.`id_unidad_medida`, cd.`precio`, cd.`entregado`,p.lugar_entrega,p.id_cuenta_recibe,c.comentarios,c.nro_oc,c.id_cuenta_proveedor FROM `compras_detalle` cd inner join compras c on c.id = cd.id_compra inner join pedidos p on p.id = c.id_pedido WHERE cd.id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$id]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	
	/*$sql = "SELECT `id`, `disponible`, `reservado`, `comprando` FROM `stock` WHERE `id_material` = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$data['id_material']]);
	$data2 = $q->fetch(PDO::FETCH_ASSOC);
	
	if (empty($data2)) {
		$disponible = 0;
		$reservado = 0;
		if ($_GET['reservado'] == 0) {
			$disponible = $data['cantidad'];	
		} else {
			$reservado = $data['cantidad'];	
		}
		$sql = "insert into `stock` (`id_material`, `disponible`, `reservado`, `comprando`) values (?,?,?,0)";
		$q = $pdo->prepare($sql);
		$q->execute([$data['id_material'],$disponible,$reservado]);
	} else {
		if ($_GET['reservado'] == 0) {
			$sql = "update `stock` set `disponible` = `disponible` + ? where id = ?";
		} else {
			$sql = "update `stock` set `reservado` = `reservado` + ? where id = ?";
		}
		
		$q = $pdo->prepare($sql);
		$q->execute([$data['cantidad'],$data2['id']]);
	}*/
	
	$sql = "insert into `ingresos` (`fecha_hora`, `id_tipo_ingreso`, `nro`, `id_cuenta_recibe`, `lugar_entrega`, `observaciones`) values (now(),1,?,?,?,?)";
	$q = $pdo->prepare($sql);
	$q->execute([$data['nro_oc'],$data['id_cuenta_recibe'],$data['lugar_entrega'],$data['comentarios']]);
	$idIngreso = $pdo->lastInsertId();
	
	$sql = "INSERT into ingresos_detalle (id_ingreso, id_material, id_unidad_medida, cantidad, cantidad_egresada, salda) values (?,?,?,?,?,?)";
	$q = $pdo->prepare($sql);
	$q->execute([$idIngreso,$data['id_material'],$data['id_unidad_medida'],$data['cantidad'],0,$data['cantidad']]);
	
	$sql = "insert into `coladas` (`id_material`, `id_proveedor`, `id_compra`) values (?,?,?)";
	$q = $pdo->prepare($sql);
	$q->execute([$data['id_material'],$data['id_cuenta_proveedor'],$_GET['idCompra']]);
	
	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Recepción de items de orden de compra','Compras','verCompra.php?id=$id')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));
	
    Database::disconnect();
        
    header("Location: modificarCompra.php?id=".$_GET['idCompra']);
