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
    
	$sql = "SELECT cd.`id`, cd.`id_compra`, cd.`id_material`, cd.`cantidad`, cd.`id_unidad_medida`, cd.`precio`, cd.`entregado`,p.lugar_entrega,p.id_cuenta_recibe,c.comentarios,c.nro_oc,c.id_cuenta_proveedor, p.id idPedido FROM `compras_detalle` cd inner join compras c on c.id = cd.id_compra inner join pedidos p on p.id = c.id_pedido WHERE cd.id_compra = ".$_GET['id'];
	$count = 1;
	$idIngreso = 0;
	foreach ($pdo->query($sql) as $row) {
		
		$idPedido = $row[12];
		$cantidadIngresar = $_POST['cantidadIngresar_'.$row[0]];
		if ($cantidadIngresar > 0) {
			$sql = "UPDATE `compras_detalle` SET entregado = entregado + ? WHERE id = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$cantidadIngresar,$row[0]]);
			
			$sql = "update pedidos set id_estado = 2 where id  = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$idPedido]);
			
			$sql = "update compras set id_estado_compra = 2 where id  = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$_GET['id']]);
			
			if ($_GET['reservado'] == 0) {
				$sql = "UPDATE `pedidos_detalle` SET `comprado`=`comprado` - ? WHERE `id_pedido`=? AND `id_material`=?";
				$q = $pdo->prepare($sql);
				$q->execute([$cantidadIngresar,$row[12],$row[2]]);
				
				$sql3 = "select cd.id id from computos_detalle cd inner join computos c on c.id = cd.id_computo inner join pedidos p on p.id_computo = c.id where p.id = ? and cd.cancelado = 0 and cd.id_material = ? ";
				$q3 = $pdo->prepare($sql3);
				$q3->execute([$row[12],$row[2]]);
				$data3 = $q3->fetch(PDO::FETCH_ASSOC);
				
				$sql = "update `computos_detalle` set `comprado` = `comprado` - ? WHERE id = ?";
				$q = $pdo->prepare($sql);
				$q->execute([$cantidadIngresar,$data3['id']]);
			} else {
				$sql = "UPDATE `pedidos_detalle` SET reservado = reservado + ?, `comprado`=`comprado` - ? WHERE `id_pedido`=? AND `id_material`=?";
				$q = $pdo->prepare($sql);
				$q->execute([$cantidadIngresar,$cantidadIngresar,$row[12],$row[2]]);
				
				$sql3 = "select cd.id id from computos_detalle cd inner join computos c on c.id = cd.id_computo inner join pedidos p on p.id_computo = c.id where p.id = ? and cd.cancelado = 0 and cd.id_material = ? ";
				$q3 = $pdo->prepare($sql3);
				$q3->execute([$row[12],$row[2]]);
				$data3 = $q3->fetch(PDO::FETCH_ASSOC);
				
				$sql = "update `computos_detalle` set reservado = reservado + ?, `comprado` = `comprado` - ? WHERE id = ?";
				$q = $pdo->prepare($sql);
				$q->execute([$cantidadIngresar,$cantidadIngresar,$data3['id']]);
			}
			
			if ($count == 1) {
				$sql = "insert into `ingresos` (`fecha_hora`, `id_tipo_ingreso`, `nro`, `id_cuenta_recibe`, `lugar_entrega`, `observaciones`, `fecha_remito`, `nro_remito`) values (now(),1,?,?,?,?,?,?)";
				$q = $pdo->prepare($sql);
				$q->execute([$row[10],$row[8],$row[7],$row[9],$_POST['fecha_remito'],$_POST['nro_remito']]);
				$idIngreso = $pdo->lastInsertId();				
			}
			
			$sql3 = "select s.nro_sitio,s.nro_subsitio,p.nro from pedidos pe inner join proyectos p on p.id = pe.id_proyecto inner join sitios s on s.id = p.id_sitio where pe.id = ? ";
			$q3 = $pdo->prepare($sql3);
			$q3->execute([$row[12]]);
			$data3 = $q3->fetch(PDO::FETCH_ASSOC);
			
			$colada = $data3['nro_sitio']."/".$data3['nro_subsitio']."/".$data3['nro']."-".$count;
			
			$sql = "INSERT into ingresos_detalle (id_ingreso, id_material, id_unidad_medida, cantidad, saldo, id_compra, id_proveedor,nro_colada_interna) values (?,?,?,?,?,?,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$idIngreso,$row[2],$row[4],$cantidadIngresar,$cantidadIngresar,$row[1],$row[11],$colada]);
			
			$count++;
		}
	}
	
	$sql = "SELECT count(*) cant FROM `compras_detalle` where id_compra = ? and entregado < cantidad ";
	$q = $pdo->prepare($sql);
	$q->execute([$_GET['id']]);
	$data2 = $q->fetch(PDO::FETCH_ASSOC);
	if ($data2['cant'] == 0) {
		$sql = "update compras set id_estado_compra = 3 where id  = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_GET['id']]);
		
		$sql = "update pedidos set id_estado = 3 where id  = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$idPedido]);
	}
	
	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Recepción de items de orden de compra','Compras','verCompra.php?id=$id')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));
	
    Database::disconnect();
        
    header("Location: listarCompras.php");
