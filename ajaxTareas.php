<?php
	include 'database.php';
	if(!empty($_GET["id_proyecto"])) {
?>
		<option value="">Seleccione...</option>
		<?php
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sqlZon = "SELECT t.id,tt.tipo,t.observaciones FROM `tareas` t inner join tipos_tarea tt on tt.id = t.id_tipo_tarea WHERE t.`anulado` = 0 and t.id_proyecto = ".$_GET["id_proyecto"];
		$q = $pdo->prepare($sqlZon);
		$q->execute();
		while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
			echo "<option value='".$fila['id']."'";
			echo ">".$fila['tipo']." / ".$fila['observaciones']."</option>";
		}
		Database::disconnect();
		}
		?>
	