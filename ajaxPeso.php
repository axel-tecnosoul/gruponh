<?php
	include 'database.php';
	if(!empty($_POST["id_concepto"])) {
		$pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `peso_metro` FROM `materiales` WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST["id_concepto"]]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		$peso = $data['peso_metro'];
		echo '<input name="peso" type="number" step="0.01" maxlength="99" class="form-control peso" value="'.$peso.'">';
	} 
?>