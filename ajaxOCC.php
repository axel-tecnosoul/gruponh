<?php
	include 'database.php';
	if(!empty($_GET["id_proyecto"])) {
?>
		<option value="">Seleccione...</option>
		<?php
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sqlZon = "SELECT o.`id`, o.`numero` FROM `occ` o inner join cuentas c on c.id = o.id_cuenta_cliente inner join proyectos p on p.id_cliente = c.id WHERE o.`activa` = 1 and p.id = ".$_GET["id_proyecto"];
		$q = $pdo->prepare($sqlZon);
		$q->execute();
		while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
			echo "<option value='".$fila['numero']."'";
			echo ">".$fila['numero']."</option>";
		}
		Database::disconnect();
		}
		?>
	