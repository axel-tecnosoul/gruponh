<?php
	include 'database.php';
	$idProyecto = filter_input(INPUT_GET, 'id_proyecto', FILTER_VALIDATE_INT);
	if ($idProyecto && $idProyecto > 0) {
?>
		<?php
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sqlZon = "SELECT o.`id`, o.`numero`, o.`monto`, o.`fecha_entrega`, o.`abierta`, m.`moneda`
		           FROM `occ` o
		           INNER JOIN proyectos p ON p.id_cliente = o.id_cuenta_cliente
		           INNER JOIN monedas m ON m.id = o.id_moneda
		           WHERE o.`activa` = 1 AND p.id = ?
		           ORDER BY o.`numero`";
		$q = $pdo->prepare($sqlZon);
		$q->execute([$idProyecto]);
		while ($fila = $q->fetch(PDO::FETCH_ASSOC)):
			$fechaEntrega = (string)$fila['fecha_entrega'];
			$fechaEntrega = $fechaEntrega !== '' && $fechaEntrega !== '0000-00-00'
				? date('d/m/Y', strtotime($fechaEntrega))
				: 'sin fecha';
			$estadoOcc = (int)$fila['abierta'] === 1 ? 'Abierta' : 'Cerrada';
			$etiquetaOcc = 'OCC ' . $fila['numero']
				. ' · ' . $fila['moneda'] . ' ' . number_format((float)$fila['monto'], 2, ',', '.')
				. ' · Entrega: ' . $fechaEntrega . ' · ' . $estadoOcc;
		?>
			<option value="<?=(int)$fila['id']?>"><?=htmlspecialchars($etiquetaOcc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></option>
		<?php endwhile;
		Database::disconnect();
		}
		?>
