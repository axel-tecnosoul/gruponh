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
        header("Location: listarSitios.php");
    }
	
	try {
		
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$sql = "delete from sitios where id_sitio_superior = ? ";
		$q = $pdo->prepare($sql);
		$q->execute(array($id));
		
		$sql = "delete from sitios where id = ? ";
		$q = $pdo->prepare($sql);
		$q->execute(array($id));
			
		Database::disconnect();
	} catch (PDOException $e) {
		
	}
        
    header("Location: listarSitios.php");
