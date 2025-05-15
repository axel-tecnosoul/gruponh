<?php
require("config.php");
if (empty($_SESSION['user'])) {
	header("Location: index.php");
	die("Redirecting to index.php");
}

require 'database.php';

// Conexión a la base de datos usando PDO
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Archivo de salida
$filePath = 'CCabecer.txt';
$file = fopen($filePath, 'w');

// Consulta para obtener los datos de facturas de venta
$sql = "SELECT d.`id`, c.`descripcion`, tc.tipo, lc.letra, oc.nro_oc, c.`numero`, cu1.razon_social, e.empresa, c.`fecha_emitida`, c.`fecha_recibida`, fp.forma_pago, c.`subtotal_gravado`, c.`subtotal_no_gravado`, c.`otros`, c.`iva`, c.`total`, m.moneda, c.`cotizacion`, c.`observaciones`, u.usuario, ef.estado, d.`descripcion`, d.`cantidad`, d.`precio`, d.`porc_descuento`, d.`importe_otros`, d.`observaciones`, d.`subtotal`,c.id FROM `facturas_compra_detalle` d inner join facturas_compra c on c.id = d.id_factura_compra inner join tipos_comprobante tc on tc.id = c.`id_tipo_comprobante` inner join letras_comprobante lc on lc.id = c.`id_letra_comprobante` inner join compras oc on oc.id = c.`id_orden_compra` inner join cuentas cu1 on cu1.id = c.`id_cuenta_origen` inner join empresas e on e.id = c.`id_empresa` inner join formas_pago fp on fp.id = c.`id_condicion_pago` inner join monedas m on m.id = c.`id_moneda` inner join usuarios u on u.id = c.`id_usuario` inner join estados_factura ef on ef.id = c.`id_estado` WHERE c.`id_estado` in (3,4) and c.exportada = 0 ";
$stmt = $pdo->prepare($sql);
$stmt->execute();

// Procesar los datos y escribir en el archivo sin separadores
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
	// Concatena los datos de cada factura en una sola línea sin separadores
	$line = $row[0] . $row[1] . $row[2] . $row[3] . $row[4] . $row[5] . $row[6] . $row[7] . $row[8] . $row[9] . $row[10] . $row[11] . $row[12] . $row[13] . $row[14] . $row[15] . $row[16] . $row[17] . $row[18] . $row[19] . $row[20] . $row[21] . $row[22] . $row[23] . $row[24] . $row[25] . $row[26] . $row[27] . PHP_EOL;
	
	// Escribe la línea en el archivo
	fwrite($file, $line);
	
}

// Cerrar el archivo y la conexión a la base de datos
fclose($file);

// Archivo de salida
$filePath2 = 'CItems.txt';
$file2 = fopen($filePath2, 'w');

// Consulta para obtener los datos de facturas de compra
$sql = "SELECT d.`id`, c.`descripcion`, tc.tipo, lc.letra, oc.nro_oc, c.`numero`, cu1.razon_social, e.empresa, c.`fecha_emitida`, c.`fecha_recibida`, fp.forma_pago, c.`subtotal_gravado`, c.`subtotal_no_gravado`, c.`otros`, c.`iva`, c.`total`, m.moneda, c.`cotizacion`, c.`observaciones`, u.usuario, ef.estado, d.`descripcion`, d.`cantidad`, d.`precio`, d.`porc_descuento`, d.`importe_otros`, d.`observaciones`, d.`subtotal`,c.id FROM `facturas_compra_detalle` d inner join facturas_compra c on c.id = d.id_factura_compra inner join tipos_comprobante tc on tc.id = c.`id_tipo_comprobante` inner join letras_comprobante lc on lc.id = c.`id_letra_comprobante` inner join compras oc on oc.id = c.`id_orden_compra` inner join cuentas cu1 on cu1.id = c.`id_cuenta_origen` inner join empresas e on e.id = c.`id_empresa` inner join formas_pago fp on fp.id = c.`id_condicion_pago` inner join monedas m on m.id = c.`id_moneda` inner join usuarios u on u.id = c.`id_usuario` inner join estados_factura ef on ef.id = c.`id_estado` WHERE c.`id_estado` in (3,4) and c.exportada = 0 ";
$stmt = $pdo->prepare($sql);
$stmt->execute();

// Procesar los datos y escribir en el archivo sin separadores
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
	// Concatena los datos de cada factura en una sola línea sin separadores
	$line = $row[0] . $row[1] . $row[2] . $row[3] . $row[4] . $row[5] . $row[6] . $row[7] . $row[8] . $row[9] . $row[10] . $row[11] . $row[12] . $row[13] . $row[14] . $row[15] . $row[16] . $row[17] . $row[18] . $row[19] . $row[20] . $row[21] . $row[22] . $row[23] . $row[24] . $row[25] . $row[26] . $row[27] . PHP_EOL;
	
	// Escribe la línea en el archivo
	fwrite($file2, $line);
	
}

// Cerrar el archivo y la conexión a la base de datos
fclose($file2);

// Archivo de salida
$filePath3 = 'CRegEsp.txt';
$file3 = fopen($filePath3, 'w');

// Consulta para obtener los datos de facturas de compra
$sql = "SELECT d.`id`, c.`descripcion`, tc.tipo, lc.letra, oc.nro_oc, c.`numero`, cu1.razon_social, e.empresa, c.`fecha_emitida`, c.`fecha_recibida`, fp.forma_pago, c.`subtotal_gravado`, c.`subtotal_no_gravado`, c.`otros`, c.`iva`, c.`total`, m.moneda, c.`cotizacion`, c.`observaciones`, u.usuario, ef.estado, d.`descripcion`, d.`cantidad`, d.`precio`, d.`porc_descuento`, d.`importe_otros`, d.`observaciones`, d.`subtotal`,c.id FROM `facturas_compra_detalle` d inner join facturas_compra c on c.id = d.id_factura_compra inner join tipos_comprobante tc on tc.id = c.`id_tipo_comprobante` inner join letras_comprobante lc on lc.id = c.`id_letra_comprobante` inner join compras oc on oc.id = c.`id_orden_compra` inner join cuentas cu1 on cu1.id = c.`id_cuenta_origen` inner join empresas e on e.id = c.`id_empresa` inner join formas_pago fp on fp.id = c.`id_condicion_pago` inner join monedas m on m.id = c.`id_moneda` inner join usuarios u on u.id = c.`id_usuario` inner join estados_factura ef on ef.id = c.`id_estado` WHERE c.`id_estado` in (3,4) and c.exportada = 0 ";
$stmt = $pdo->prepare($sql);
$stmt->execute();

// Procesar los datos y escribir en el archivo sin separadores
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
	// Concatena los datos de cada factura en una sola línea sin separadores
	$line = $row[0] . $row[1] . $row[2] . $row[3] . $row[4] . $row[5] . $row[6] . $row[7] . $row[8] . $row[9] . $row[10] . $row[11] . $row[12] . $row[13] . $row[14] . $row[15] . $row[16] . $row[17] . $row[18] . $row[19] . $row[20] . $row[21] . $row[22] . $row[23] . $row[24] . $row[25] . $row[26] . $row[27] . PHP_EOL;
	
	// Escribe la línea en el archivo
	fwrite($file3, $line);
	
	$sql666 = "UPDATE `facturas_compra` set `exportada` = 1 where id = ?";
	$q666 = $pdo->prepare($sql666);
	$q666->execute([$row[28]]);
}

// Cerrar el archivo y la conexión a la base de datos
fclose($file3);

// Configuración de cabeceras para la descarga de ambos archivos
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="facturas_compra_exportadas.zip"');
header('Pragma: public');

// Crear un archivo ZIP para descargar ambos archivos juntos
$zip = new ZipArchive();
$zipPath = 'facturas_compra_exportadas.zip';
if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
	$zip->addFile($filePath);
	$zip->addFile($filePath2);
	$zip->addFile($filePath3);
	$zip->close();
}

// Leer el archivo ZIP para descargar
readfile($zipPath);

// Eliminar archivos temporales después de la descarga (opcional)
unlink($filePath);
unlink($filePath2);
unlink($filePath3);
unlink($zipPath);

exit;
?>
