<?php
	include 'database.php';
	if(!empty($_POST["tarea"])) {
?>
		<table class="display" id="dataTables-example666">
		<thead>
		  <tr>
		  <th>Código</th>
		  <th>Concepto</th>
		  <th>Categoría</th>
		  <th>Cantidad</th>
		  </tr>
		</thead>
		<tbody>
		<?php
		$pdo = Database::connect();
		$sql = " SELECT d.`id`, m.codigo, m.`concepto`, ca.categoria, d.`cantidad`, u.unidad_medida, d.id_material FROM `computos_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = m.id_unidad_medida inner join categorias ca on ca.id = m.id_categoria inner join computos co on co.id = d.id_computo WHERE d.`cancelado` = 0 and d.`aprobado` = 1 and co.id_tarea = ".$_POST["tarea"];
		
		foreach ($pdo->query($sql) as $row) {
			echo '<tr>';
			echo '<td>'. $row[1] . '</td>';
			echo '<td>'. $row[2] . '</td>';
			echo '<td>'. $row[3] . '</td>';
			echo '<td>'. $row[4] ." ".$row[5] . '</td>';
			echo '</tr>';
		}
	    Database::disconnect();
	    ?>
		</tbody>
	  </table>
<?php } ?>