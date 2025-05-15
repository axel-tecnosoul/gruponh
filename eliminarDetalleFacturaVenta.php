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
        header("Location: listarFacturasVenta.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	$sql = "delete from facturas_venta_detalle_x_certificados_avance where id_factura_venta_detalle = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$id]);
    
    $sql = "delete from `facturas_venta_detalle` WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
	
	$gravado = 0;
	$noGravado = 0;
	$iva = 0;
	$total = 0;
	
	$sql = " SELECT `cantidad`, `precio`, `subtotal` FROM `facturas_venta_detalle` WHERE `id_factura_venta` = ".$_GET['fv'];    
	foreach ($pdo->query($sql) as $row) {
		$total += $row[2];
		$noGravadoParcial = $row[1]*$row[0];
		$noGravado += $noGravadoParcial;
		$iva += $noGravado *0.21;
		$gravado += $noGravado + $iva;
	}
	
	$sql = "update `facturas_venta` set  `subtotal_gravado` = ?, `subtotal_no_gravado` = ?, `iva` = ?, `total` = ? where id = ?";
	$q = $pdo->prepare($sql);		   
	$q->execute([$gravado, $noGravado, $iva, $total, $_GET['fc']]);
	
	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Eliminación de Ítem de Detalle de Factura de Venta','Facturas de Venta','')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));

        
    Database::disconnect();
        
    header("Location: listarFacturasVenta.php");
