<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ids = isset($_GET['ids']) ? array_map('intval', array_filter(explode(',', $_GET['ids']))) : [];
$idFilter = '';
$params = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $idFilter = " AND fv.id IN ($placeholders) ";
    $params = $ids;
    $extraFilter = " AND fv.id_estado = 3 ";
} else {
    $extraFilter = " AND fv.id_estado = 3 AND fv.exportada = 0 ";
}

try {
  // Cabeceras de facturas
  $qCab = $pdo->prepare("SELECT fv.id, fv.descripcion, tc.tipo, lc.letra, fv.numero,
                              cu.nombre, cu.cuit, e.empresa,
                              DATE_FORMAT(fv.fecha_emitida,'%Y%m%d') as fe,
                              fv.total
                       FROM facturas_venta fv
                       INNER JOIN tipos_comprobante tc ON tc.id = fv.id_tipo_comprobante
                       INNER JOIN letras_comprobante lc ON lc.id = fv.id_letra_comprobante
                       INNER JOIN cuentas cu ON cu.id = fv.id_cuenta_destino
                       INNER JOIN empresas e ON e.id = fv.id_empresa
                       WHERE 1 $extraFilter $idFilter
                       ORDER BY fv.id");
  $qCab->execute($params);
  $facturas = $qCab->fetchAll(PDO::FETCH_ASSOC);

  if (empty($facturas)) {
    die("No hay facturas de venta pendientes de exportación.");
  }

  // Todos los items de todas las facturas
  $qDet = $pdo->prepare("SELECT fv.id as factura_id, d.cantidad, d.precio,
                              COALESCE(d.texto_impreso, cc.descripcion, '') as descripcion_det
                       FROM facturas_venta fv
                       INNER JOIN facturas_venta_detalle d ON d.id_factura_venta = fv.id
                       LEFT JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
                       WHERE 1 $extraFilter $idFilter
                       ORDER BY fv.id, d.id");
  $qDet->execute($params);
  $itemsRaw = $qDet->fetchAll(PDO::FETCH_ASSOC);

  // Todas las retenciones de todas las facturas
  $qRet = $pdo->prepare("SELECT fv.id as factura_id, r.monto, COALESCE(r.regimen_text, r.codigo, r.articulo, '') AS regimen
                       FROM facturas_venta fv
                       INNER JOIN facturas_venta_retenciones r ON r.id_factura_venta = fv.id
                       WHERE 1 $extraFilter $idFilter
                       ORDER BY fv.id");
  $qRet->execute($params);
  $retsRaw = $qRet->fetchAll(PDO::FETCH_ASSOC);

  // Agrupar items y retenciones por factura_id
  $itemsPorFactura = [];
  foreach ($itemsRaw as $r) {
    $itemsPorFactura[$r['factura_id']][] = $r;
  }
  $retsPorFactura = [];
  foreach ($retsRaw as $r) {
    $retsPorFactura[$r['factura_id']][] = $r;
  }

  $hCab = [];
  $hItems = [];
  $hReg = [];

  $totalItems = 0;
  $totalRets = 0;

  foreach ($facturas as $f) {
    $idFactura = $f['id'];
    $tipo = $f['tipo'] ?? 'FC';
    $letra = $f['letra'] ?? ' ';
    $tipoLetra = str_pad(substr($tipo, 0, 2) . $letra, 4);
    $numero = str_pad($f['numero'] ?? '', 12, '0', STR_PAD_LEFT);
    $fecha = $f['fe'] ?? date('Ymd');
    $codEmpresa = str_pad(substr(preg_replace('/[^A-Za-z0-9]/', '', $f['empresa'] ?? ''), 0, 6), 6);
    $prefix = $tipoLetra . $numero . str_repeat(' ', 8) . $fecha . $codEmpresa;

    // Cabecera
    $razonSocial = str_pad(mb_substr($f['nombre'] ?? '', 0, 40), 40);
    $cuit = str_pad(preg_replace('/[^0-9]/', '', $f['cuit'] ?? ''), 15, '0', STR_PAD_LEFT);
    $total = str_pad(number_format($f['total'] ?? 0, 2, '.', ''), 18, ' ', STR_PAD_LEFT);
    $cabLine = $prefix . $razonSocial . $cuit . str_repeat(' ', 60) . $fecha . str_repeat(' ', 6) . $total . str_repeat(' ', 260) . $total;
    $hCab[] = str_pad($cabLine, 610) . PHP_EOL;

    // Items
    $items = $itemsPorFactura[$idFactura] ?? [];
    foreach ($items as $det) {
      $totalItems++;
      $descDet = str_pad(mb_substr($det['descripcion_det'] ?? '', 0, 35), 35);
      $cantidad = str_pad(number_format($det['cantidad'] ?? 0, 2, '.', ''), 12, ' ', STR_PAD_LEFT);
      $precio = str_pad(number_format($det['precio'] ?? 0, 2, '.', ''), 18, ' ', STR_PAD_LEFT);
      $itemSubtotal = ($det['cantidad'] ?? 0) * ($det['precio'] ?? 0);
      $subtotal = str_pad(number_format($itemSubtotal, 2, '.', ''), 18, ' ', STR_PAD_LEFT);
      $ivaDet = str_pad(number_format($itemSubtotal * 0.21, 2, '.', ''), 12, ' ', STR_PAD_LEFT);
      $totalDet = str_pad(number_format($itemSubtotal, 2, '.', ''), 18, ' ', STR_PAD_LEFT);
      $itemLine = $prefix . $descDet . $cantidad . str_repeat(' ', 13) . '0.00' . str_repeat(' ', 56) . $subtotal . '   21.00    0.00     ' . $ivaDet . str_repeat(' ', 12) . '0.00' . str_repeat(' ', 6) . $totalDet . str_repeat(' ', 12) . '0.00' . str_repeat(' ', 12) . '0.00' . str_repeat(' ', 12) . '0.00' . str_repeat(' ', 16) . '0.001' . str_repeat(' ', 14) . '0.00' . str_repeat(' ', 34) . $totalDet;
      $hItems[] = str_pad($itemLine, 548) . PHP_EOL;
    }

    // Retenciones
    $rets = $retsPorFactura[$idFactura] ?? [];
    foreach ($rets as $ret) {
      $totalRets++;
      $regimen = str_pad(substr(preg_replace('/[^A-Za-z0-9]/', '', $ret['regimen'] ?? ''), 0, 8), 8);
      $montoReg = str_pad(number_format($ret['monto'] ?? 0, 2, '.', ''), 14, ' ', STR_PAD_LEFT);
      $regLine = $prefix . $regimen . $montoReg . str_repeat(' ', 14) . '0.00';
      $hReg[] = str_pad($regLine, 78) . PHP_EOL;
    }
  }

  // Escribir archivos
  file_put_contents(__DIR__ . '/VCabecer.txt', implode('', $hCab));
  file_put_contents(__DIR__ . '/VItems.txt', implode('', $hItems));
  file_put_contents(__DIR__ . '/VRegEsp.txt', implode('', $hReg));

  // Marcar como exportadas
  $upd = $pdo->prepare("UPDATE facturas_venta SET exportada = 1 WHERE id = ?");
  foreach ($facturas as $f) {
    $upd->execute([$f['id']]);
  }

  ob_clean();

  $zip = new ZipArchive();
  $zipPath = __DIR__ . '/facturas_venta_bejerman.zip';
  $zip->open($zipPath, ZipArchive::CREATE);
  $zip->addFile(__DIR__ . '/VCabecer.txt', 'VCabecer.txt');
  $zip->addFile(__DIR__ . '/VItems.txt', 'VItems.txt');
  $zip->addFile(__DIR__ . '/VRegEsp.txt', 'VRegEsp.txt');
  $zip->close();

  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="facturas_venta_bejerman.zip"');
  header('Pragma: public');

  readfile($zipPath);

  unlink(__DIR__ . '/VCabecer.txt');
  unlink(__DIR__ . '/VItems.txt');
  unlink(__DIR__ . '/VRegEsp.txt');
  unlink($zipPath);

} catch (Exception $e) {
  die("Error al exportar: " . $e->getMessage());
}

exit;
