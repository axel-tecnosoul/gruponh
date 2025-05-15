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
        header("Location: listarComputos.php");
    }
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	$sql = "SELECT `id_estado` FROM `computos` WHERE id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$id]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	
    if ($data['id_estado']==1) {
		$sql = "UPDATE `computos` SET id_estado = 2 WHERE id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$id]);
	} else {
		
		$idCuentaReviso = null;
		$idCuentaValido = null;

		$sql2 = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
		$q2 = $pdo->prepare($sql2);
		$q2->execute([$_SESSION['user']['id']]);
		$data2 = $q2->fetch(PDO::FETCH_ASSOC);
		if (!empty($data2)) {
			$idCuentaReviso = $data['id'];
			$idCuentaValido = $data['id'];
		}
		
		$sql = "UPDATE `computos` SET id_estado = 3, id_cuenta_reviso = ?, id_cuenta_valido = ? WHERE id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$idCuentaReviso,$idCuentaValido,$id]);		
		$sql = "UPDATE `computos_detalle` SET aprobado = 1 WHERE id_computo = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$id]);		
	}
    
	$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Aprobación de cómputo','Cómputos','verComputo.php?id=$id')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));


    Database::disconnect();
        
    header("Location: listarComputos.php");
