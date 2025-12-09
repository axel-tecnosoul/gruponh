<?php
require("config.php");
require 'database.php';

$id_compra = $_POST['id_compra'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = " SELECT cd.id AS id_compra_detalle, m.concepto, cd.cantidad, u.unidad_medida, cd.id_material, cd.precio, cd.entregado, cd.precio_kg, cd.subtotal, m.peso_metro, m.largo FROM compras_detalle cd inner join materiales m on m.id = cd.id_material inner join unidades_medida u on u.id = cd.id_unidad_medida WHERE cd.id_compra = ".$id_compra;
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
  $cantidad = (float) $row["cantidad"];
  $precioUnitario = (float) $row["precio"];
  $precioKgRaw = (float) $row["precio_kg"];
  $pesoMetro = (float) $row["peso_metro"];
  $largo = (float) $row["largo"];
  $id_material = $row["id_material"];

  $precio = number_format($precioUnitario,2);
  $preciokg = number_format($precioKgRaw,2);

  $pesoMetroKg = $pesoMetro / 1000;
  $largoMetros = $largo / 1000;

  if ($largoMetros > 0) {
      $pesoUnitario = $pesoMetroKg * $largoMetros;
  } else {
      $pesoUnitario = $pesoMetroKg;
  }

  $pesoTotalRaw = $pesoUnitario * (float) $cantidad;
  $peso = number_format($pesoTotalRaw,2);
  
  // Usar subtotal guardado si existe, sino calcularlo (compatibilidad con registros antiguos)
  $subtotalGuardado = isset($row["subtotal"]) ? (float) $row["subtotal"] : 0;
  
  if ($subtotalGuardado > 0) {
    $subtotalValue = $subtotalGuardado;
  } else {
    // Fallback: calcular como antes para registros antiguos
    if ($precioUnitario == 0) {
      $subtotalValue = $precioKgRaw * $pesoTotalRaw;
    } else {
      $subtotalValue = $precioUnitario * (float) $cantidad;
    }
  }
  $subtotal = number_format($subtotalValue,2);
	
	$remitos = "";
	$sql2 = " SELECT i.nro_remito FROM ingresos_detalle id inner join ingresos i on i.id = id.id_ingreso WHERE id.id_material = $id_material and id.id_compra = ".$id_compra;
	foreach ($pdo->query($sql2) as $row2) {
		$remitos = $row2["nro_remito"]." | ";
	}
	
	$facturas = "";
	$sql2 = " SELECT f.numero FROM facturas_compra_detalle_x_compras_detalle fd inner join facturas_compra_detalle d on d.id = fd.id_factura_compra_detalle inner join facturas_compra f on f.id = d.id_factura_compra inner join compras_detalle cd on cd.id = fd.id_compra_detalle WHERE cd.id_material = $id_material and f.id_orden_compra = ".$id_compra;
	foreach ($pdo->query($sql2) as $row2) {
		$facturas = $row2["numero"]." | ";
	}
	
	$aConceptos[]=[
    0 => $row["concepto"],
    1 => $cantidad,
	  2 => $row["unidad_medida"],
    3 => $peso,
    4 => $preciokg,
    5 => $precio,
    6 => $subtotal,
    7 => $row["entregado"],
    8 => $remitos,
    9 => $facturas
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>