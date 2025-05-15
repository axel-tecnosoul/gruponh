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
        header("Location: listarFacturasCompra.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	$sql = "delete from facturas_compra_detalle_x_compras_detalle where id_factura_compra_detalle = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$id]);
    
    $sql = "delete from `facturas_compra_detalle` WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
	
	$gravado = 0;
	$noGravado = 0;
	$iva = 0;
	$total = 0;
	
	$sql = " SELECT `cantidad`, `precio`, `subtotal` FROM `facturas_compra_detalle` WHERE `id_factura_compra` = ".$_GET['fc'];    
	foreach ($pdo->query($sql) as $row) {
		$total += $row[2];
		$noGravadoParcial = $row[1]*$row[0];
		$noGravado += $noGravadoParcial;
		$iva += $noGravado *0.21;
		$gravado += $noGravado + $iva;
	}
	
	$sql = "update `facturas_compra` set  `subtotal_gravado` = ?, `subtotal_no_gravado` = ?, `iva` = ?, `total` = ? where id = ?";
	$q = $pdo->prepare($sql);		   
	$q->execute([$gravado, $noGravado, $iva, $total, $_GET['fc']]);
	
	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Eliminación de Ítem de Detalle de Factura de Compra','Facturas de Compra','')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));

        
    Database::disconnect();
        
    header("Location: listarFacturasCompra.php");
