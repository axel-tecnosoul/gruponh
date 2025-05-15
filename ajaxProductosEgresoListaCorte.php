<?php
	include 'database.php';
	if(!empty($_POST["lista_corte"])) {
?>
		<table class="display" id="dataTables-example666">
		<thead>
		  <tr>
		  <th>Código</th>
		  <th>Concepto</th>
		  <th>Cantidad Solicitada</th>
		  <th>Cantidad Utilizada</th>
		  </tr>
		</thead>
		<tbody>
		<?php
		$pdo = Database::connect();
		$sql = " select m.codigo, m.concepto, cd.cantidad, cd.comprado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id where lc.id = ".$_POST["lista_corte"];
		
		foreach ($pdo->query($sql) as $row) {
			echo '<tr>';
			echo '<td>'. $row[0] . '</td>';
			echo '<td>'. $row[1] . '</td>';
			echo '<td>'. $row[2] . '</td>';
			echo '<td>'. $row[3] . '</td>';
			echo '</tr>';
		}
	    Database::disconnect();
	    ?>
		</tbody>
	  </table>
<?php } ?>