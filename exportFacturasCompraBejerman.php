<?php
require("config.php");
if (empty($_SESSION['user'])) {
	header("Location: index.php");
	die("Redirecting to index.php");
}

require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
	$sql = "SELECT d.`id`, c.`descripcion`, tc.tipo, lc.letra, GROUP_CONCAT(DISTINCT oc.nro_oc SEPARATOR ' | '), c.`numero`, cu1.razon_social, e.empresa, c.`fecha_emitida`, c.`fecha_recibida`, fp.forma_pago, c.`subtotal_gravado`, c.`subtotal_no_gravado`, c.`otros`, c.`iva`, c.`total`, m.moneda, c.`cotizacion`, c.`observaciones`, u.usuario, ef.estado, d.`descripcion`, d.`cantidad`, d.`precio`, d.`porc_descuento`, CAST(NULL AS DECIMAL(10,2)) as importe_otros, d.`subtotal`, c.id
		FROM `facturas_compra_detalle` d
		INNER JOIN facturas_compra c ON c.id = d.id_factura_compra
		INNER JOIN tipos_comprobante tc ON tc.id = c.`id_tipo_comprobante`
		INNER JOIN letras_comprobante lc ON lc.id = c.`id_letra_comprobante`
		LEFT JOIN facturas_compra_x_compras fcxc ON fcxc.id_factura_compra = c.id
		LEFT JOIN compras oc ON oc.id = fcxc.id_compra
		INNER JOIN cuentas cu1 ON cu1.id = c.`id_cuenta_origen`
		INNER JOIN empresas e ON e.id = c.`id_empresa`
		INNER JOIN formas_pago fp ON fp.id = c.`id_condicion_pago`
		INNER JOIN monedas m ON m.id = c.`id_moneda`
		INNER JOIN usuarios u ON u.id = c.`id_usuario`
		INNER JOIN estados_factura ef ON ef.id = c.`id_estado`
		WHERE c.`id_estado` IN (3,4) AND c.exportada = 0
		GROUP BY d.id
		ORDER BY c.id, d.id";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_NUM);

	if (empty($rows)) {
		die("No hay facturas de compra pendientes de exportación (estado Definitiva o Pagada, no exportadas).");
	}

	$filePath  = 'CCabecer.txt';
	$filePath2 = 'CItems.txt';
	$filePath3 = 'CRegEsp.txt';

	$file  = fopen($filePath, 'w');
	$file2 = fopen($filePath2, 'w');
	$file3 = fopen($filePath3, 'w');

	$idsActualizados = [];

	foreach ($rows as $row) {
		$idFactura = $row[27];
		$line = implode('', array_slice($row, 0, 27)) . PHP_EOL;

		fwrite($file, $line);
		fwrite($file2, $line);
		fwrite($file3, $line);

		if (!in_array($idFactura, $idsActualizados, true)) {
			$idsActualizados[] = $idFactura;
		}
	}

	fclose($file);
	fclose($file2);
	fclose($file3);

	foreach ($idsActualizados as $idFactura) {
		$upd = $pdo->prepare("UPDATE `facturas_compra` SET `exportada` = 1, `id_estado` = 5 WHERE id = ?");
		$upd->execute([$idFactura]);
	}

	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="facturas_compra_exportadas.zip"');
	header('Pragma: public');

	$zip = new ZipArchive();
	$zipPath = 'facturas_compra_exportadas.zip';
	if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
		$zip->addFile($filePath);
		$zip->addFile($filePath2);
		$zip->addFile($filePath3);
		$zip->close();
	}

	readfile($zipPath);

	unlink($filePath);
	unlink($filePath2);
	unlink($filePath3);
	unlink($zipPath);

} catch (Exception $e) {
	die("Error al exportar: " . $e->getMessage());
}

exit;
