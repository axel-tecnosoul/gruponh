<?php
	include 'database.php';
	if(!empty($_POST["id_conjunto"])) {
		$pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `cantidad` FROM `listas_corte_conjuntos` WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST["id_conjunto"]]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		$cant = $data['cantidad'];
		echo '<input name="cantidad" id="cantidad" type="number" step="0.01" max="'.$cant.'" value="'.$cant.'" class="form-control" required="required">';
	} 
?>