<?php
require("config.php");
require 'database.php';

$id_ingreso = $_POST['id_ingreso'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT m.codigo, m.concepto, cat.categoria, um.unidad_medida, id.cantidad, id.id_colada,id.id, id.cantidad_egresada, id.saldo, id.nro_colada_interna FROM ingresos_detalle id inner join unidades_medida um on um.id = id.id_unidad_medida inner join ingresos i on i.id = id.id_ingreso inner join tipos_ingreso ti on ti.id = i.id_tipo_ingreso inner join cuentas c on c.id = i.id_cuenta_recibe inner join materiales m on m.id = id.id_material inner join categorias cat on cat.id = m.id_categoria WHERE id.id_ingreso = ".$id_ingreso;
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
	
	if (empty($row[5])) {
		$colada = 'No';
	} else {
		$colada = 'Si';
	}
	
	$aConceptos[]=[
    0 => $row[6],
    1 => $row[0],
    2 => $row[1],
    3 => $row[2],
    4 => $row[3],
    5 => $row[4],
	  6 => $row[7],
    7 => $row[8],
	8 => $row[9],
    9 => $colada,
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>
