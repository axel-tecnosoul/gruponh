<?php
	include 'database.php';
	if(!empty($_POST["computo"])) {
?>
		<table class="display" id="dataTables-example666">
		<thead>
		  <tr>
		  <th>Código</th>
		  <th>Concepto</th>
		  <th>Categoría</th>
		  <th>Cantidad Requerida</th>
		  <th>Cantidad Reservada</th>
		  <th>Opciones</th>
		  </tr>
		</thead>
		<tbody>
		<?php
		$pdo = Database::connect();
		$sql = " SELECT d.id, m.codigo, m.concepto, ca.categoria, d.cantidad, u.unidad_medida, d.id_material, d.reservado FROM computos_detalle d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = m.id_unidad_medida inner join categorias ca on ca.id = m.id_categoria WHERE d.cancelado = 0 and d.aprobado = 1 and d.id_computo = ".$_POST["computo"];
		
		foreach ($pdo->query($sql) as $row) {
			echo '<tr>';
			echo '<td>'. $row[1] . '</td>';
			echo '<td>'. $row[2] . '</td>';
			echo '<td>'. $row[3] . '</td>';
			echo '<td>'. $row[4] ." ".$row[5] . '</td>';
			/*$sql = "SELECT `id`, `reservado` FROM `stock` WHERE `id_material` = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$row[6]]);
			$data2 = $q->fetch(PDO::FETCH_ASSOC);
			if (!empty($data2)) {
				echo '<td>'.$data2['reservado'].'</td>';	
			} else {
				echo '<td>0</td>';
			}*/
      echo '<td>'.$row['reservado'].'</td>';	
			$sql = "SELECT c.id from compras_detalle cd inner join compras c on c.id = cd.id_compra inner join pedidos p on p.id = c.id_pedido inner join computos co on co.id = p.id_computo where cd.id_material = ? and p.id_computo = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$row[6],$_POST["computo"]]);
			$data2 = $q->fetch(PDO::FETCH_ASSOC);
			if (!empty($data2)) {
				echo '<td>';
				echo '<a target="_blank" href="verCompra.php?id='.$data2['id'].'"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Compra" title="Ver Compra"></a>';
				echo '&nbsp;&nbsp;';
				echo '</td>';
			} else {
				echo '<td></td>';
			}
			echo '</tr>';
		}
	    Database::disconnect();
	    ?>
		</tbody>
	  </table>
<?php } ?>