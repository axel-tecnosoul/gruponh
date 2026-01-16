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
		  <th>Cant. Entregada</th>
		  <th>Opciones</th>
		  </tr>
		</thead>
		<tbody>
		<?php
		$pdo = Database::connect();
		$sql = " SELECT d.id, 
					m.codigo, 
					m.concepto, 
					ca.categoria, 
					d.cantidad, 
					u.unidad_medida, 
					d.id_material, 
					d.reservado, 
					(
						SELECT COALESCE(SUM(ed.cantidad_efectivizada), 0)  
						FROM egresos_detalle ed  
						INNER JOIN egresos e ON e.id = ed.id_egreso
						WHERE e.id_tipo_egreso = 2 
						AND e.nro = d.id_computo
						AND ed.id_material = d.id_material
					) as entregado 
				FROM computos_detalle d 
				INNER JOIN materiales m ON m.id = d.id_material 
				INNER JOIN unidades_medida u ON u.id = m.id_unidad_medida 
				INNER JOIN categorias ca ON ca.id = m.id_categoria 
				WHERE d.cancelado = 0 AND d.aprobado = 1 AND d.id_computo = ?";
		
		$q = $pdo->prepare($sql);
		$q->execute([$_POST["computo"]]);
		
		// IMPORTANTE: Quitamos PDO::FETCH_ASSOC para que funcionen los índices numéricos ($row[1])
		$rows = $q->fetchAll(); 
		
		foreach ($rows as $row) {
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
	  		echo '<td>'. number_format($row[7], 2) . '</td>';
			
			echo '<td>'. number_format($row[8], 2) . '</td>';

			$sql2 = "SELECT c.id 
					 FROM compras_detalle cd 
					 INNER JOIN compras c ON c.id = cd.id_compra 
					 INNER JOIN pedidos p ON p.id = c.id_pedido 
					 WHERE cd.id_material = ? AND p.id_computo = ? LIMIT 1";
            
			$q2 = $pdo->prepare($sql2);
            $q2->execute([$row[6], $_POST["computo"]]);
			$data2 = $q2->fetch(PDO::FETCH_ASSOC);
			
			echo '<td>';
            if (!empty($data2)) {
				echo '<a target="_blank" href="verCompra.php?id='.$data2['id'].'"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Compra" title="Ver Compra"></a>';
            }
			echo '</td>';
			echo '</tr>';
		}
	    Database::disconnect();
	    ?>
		</tbody>
	  </table>
<?php } ?>