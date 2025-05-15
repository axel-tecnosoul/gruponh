<?php
	include 'database.php';
	if(!empty($_POST["id_provincia"])) {
?>
		<select name="id_localidad" id="id_localidad" class="js-example-basic-single col-sm-12" required="required">
		  <option value="">Seleccione...</option>
			<?php 
			$pdo = Database::connect();
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$sqlZon = "SELECT `id`, `localidad` FROM `localidades` WHERE `id_provincia` = ".$_POST["id_provincia"];
			$q = $pdo->prepare($sqlZon);
			$q->execute();
			while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
				echo "<option value='".$fila['id']."'";
				echo ">".$fila['localidad']."</option>";
			}
			Database::disconnect();
			?>
		  </select>
<?php } ?>