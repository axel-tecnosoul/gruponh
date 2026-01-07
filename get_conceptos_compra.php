<?php
require("config.php");
require 'database.php';

$id_compra = $_POST['id_compra'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Obtener símbolo de moneda de la OC
$sqlMoneda = "SELECT mo.moneda FROM compras c LEFT JOIN monedas mo ON mo.id = c.id_moneda WHERE c.id = ?";
$qMoneda = $pdo->prepare($sqlMoneda);
$qMoneda->execute([$id_compra]);
$simboloMoneda = $qMoneda->fetchColumn() ?: '$';

$sql = " SELECT cd.id AS id_compra_detalle, m.concepto, cd.cantidad, u.unidad_medida, cd.id_material, cd.precio, cd.entregado, cd.precio_kg, cd.subtotal, cd.descuento, cd.fecha_entrega, m.peso_metro, m.largo FROM compras_detalle cd inner join materiales m on m.id = cd.id_material inner join unidades_medida u on u.id = cd.id_unidad_medida WHERE cd.id_compra = ".$id_compra;
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {
  $cantidad = (float) $row["cantidad"];
  $precioUnitario = (float) $row["precio"];
  $precioKgRaw = (float) $row["precio_kg"];
  $porcentajeDescuento = (float) $row["descuento"];
  $fechaEntrega = $row["fecha_entrega"];
  $pesoMetro = (float) $row["peso_metro"];
  $largo = (float) $row["largo"];
  $id_material = $row["id_material"];

  $precio = number_format($precioUnitario,2,",",".");
  $preciokg = number_format($precioKgRaw,2,",",".");
  
  // Formatear fecha de entrega
  $fechaEntregaFormateada = $fechaEntrega ? date('d/m/Y', strtotime($fechaEntrega)) : '';

  //$pesoMetroKg = $pesoMetro / 1000;
  $pesoMetroKg = $pesoMetro;
  $largoMetros = $largo / 1000;

  if ($largoMetros > 0) {
      $pesoUnitario = $pesoMetroKg * $largoMetros;
  } else {
      $pesoUnitario = $pesoMetroKg;
  }

  $pesoTotalRaw = $pesoUnitario * (float) $cantidad;
  $peso = number_format($pesoTotalRaw,2,",",".");
  
  // Usar subtotal guardado si existe, sino calcularlo (compatibilidad con registros antiguos)
  $subtotalGuardado = isset($row["subtotal"]) ? (float) $row["subtotal"] : 0;
  
  if ($subtotalGuardado > 0) {
    $subtotalSinDescuento = $subtotalGuardado;
  } else {
    // Fallback: calcular como antes para registros antiguos
    if ($precioUnitario == 0) {
      $subtotalSinDescuento = $precioKgRaw * $pesoTotalRaw;
    } else {
      $subtotalSinDescuento = $precioUnitario * (float) $cantidad;
    }
  }
  
  // Calcular % de descuento
  $descuento = 0;
  if ($subtotalSinDescuento > 0 && $porcentajeDescuento > 0) {
    $descuento = ($porcentajeDescuento * $subtotalSinDescuento) / 100;
    //$descuento = ($subtotalSinDescuento * 100) / $porcentajeDescuento;
  }
  
  // Aplicar descuento al subtotal
  $subtotalConDescuento = $subtotalSinDescuento - $descuento;

  $subtotalConDescuento = $simboloMoneda.number_format($subtotalConDescuento,2,',','.');
  $subtotalSinDescuento = $simboloMoneda.number_format($subtotalSinDescuento,2,',','.');
  $porcentajeDescuento = number_format($porcentajeDescuento,1,",",".") . '%';

  //$porcentajeDescuentoFormateado = $porcentajeDescuento > 0 ? number_format($porcentajeDescuento,1,",",".") . '%' : '-';
	
	$arrRemitos = [];
	$sql2 = " SELECT i.id, i.nro_remito, DATE_FORMAT(i.fecha_remito, '%d/%m/%Y') as fecha_fmt FROM ingresos_detalle id inner join ingresos i on i.id = id.id_ingreso WHERE id.id_material = $id_material and id.id_compra = ".$id_compra;
	
	foreach ($pdo->query($sql2) as $row2) {
		$arrRemitos[] = $row2["nro_remito"] . "#" . $row2["fecha_fmt"] . "#" . $row2["id"];
	}
	$remitos = implode(", ", $arrRemitos);
	
	$facturas = "";
	$sql2 = " SELECT f.numero FROM facturas_compra_detalle_x_compras_detalle fd inner join facturas_compra_detalle d on d.id = fd.id_factura_compra_detalle inner join facturas_compra f on f.id = d.id_factura_compra inner join compras_detalle cd on cd.id = fd.id_compra_detalle WHERE cd.id_material = $id_material and f.id_orden_compra = ".$id_compra;
	foreach ($pdo->query($sql2) as $row2) {
		$facturas .= $row2["numero"]." | ";
	}
	
	$aConceptos[]=[
    0 => $row["concepto"],
    1 => $cantidad . ' ' . $row["unidad_medida"], // Cantidad + Unidad unificada
    2 => $fechaEntregaFormateada, // Fecha de entrega
    3 => $peso,
    4 => $preciokg,
    5 => $precio,
    6 => $subtotalSinDescuento, // Subtotal sin descuento
    //7 => $porcentajeDescuento. " (".$descuento.")", // % Descuento
    7 => $porcentajeDescuento, // % Descuento
    8 => $subtotalConDescuento, // Total con descuento
    9 => $row["entregado"],
    10 => $remitos,
    11 => $facturas
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>