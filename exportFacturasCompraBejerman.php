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
    $idFilter = " AND fc.id IN ($placeholders) ";
    $params = $ids;
    $extraFilter = " AND fc.id_estado = 3 ";
} else {
    $extraFilter = " AND fc.id_estado = 3 AND fc.exportada = 0 ";
}

try {
  // Cabeceras de facturas compra
  $qCab = $pdo->prepare("SELECT fc.id, fc.descripcion, tc.tipo, lc.letra, fc.numero,
                              cu.razon_social, cu.cuit, e.empresa,
                              DATE_FORMAT(fc.fecha_emitida,'%Y%m%d') as fe,
                              fc.total
                       FROM facturas_compra fc
                       INNER JOIN tipos_comprobante tc ON tc.id = fc.id_tipo_comprobante
                       INNER JOIN letras_comprobante lc ON lc.id = fc.id_letra_comprobante
                       INNER JOIN cuentas cu ON cu.id = fc.id_cuenta_origen
                       INNER JOIN empresas e ON e.id = fc.id_empresa
                       WHERE 1 $extraFilter $idFilter
                       ORDER BY fc.id");
  $qCab->execute($params);
  $facturas = $qCab->fetchAll(PDO::FETCH_ASSOC);

  if (empty($facturas)) {
    die("No hay facturas de compra pendientes de exportación.");
  }

  // Todos los items
  $qDet = $pdo->prepare("SELECT fc.id as factura_id, d.cantidad, d.precio,
                              COALESCE(d.descripcion, d.texto_impreso, cc.descripcion, '') as descripcion_det
                       FROM facturas_compra fc
                       INNER JOIN facturas_compra_detalle d ON d.id_factura_compra = fc.id
                       LEFT JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
                       WHERE 1 $extraFilter $idFilter
                       ORDER BY fc.id, d.id");
  $qDet->execute($params);
  $itemsRaw = $qDet->fetchAll(PDO::FETCH_ASSOC);

  // Todas las retenciones
  $qRet = $pdo->prepare("SELECT fc.id as factura_id, r.monto, COALESCE(r.regimen_text, r.codigo, r.articulo, '') AS regimen
                       FROM facturas_compra fc
                       INNER JOIN facturas_compra_retenciones r ON r.id_factura_compra = fc.id
                       WHERE 1 $extraFilter $idFilter
                       ORDER BY fc.id");
  $qRet->execute($params);
  $retsRaw = $qRet->fetchAll(PDO::FETCH_ASSOC);

  // Agrupar por factura_id
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
    $razonSocial = str_pad(mb_substr($f['razon_social'] ?? '', 0, 40), 40);
    $cuit = str_pad(preg_replace('/[^0-9]/', '', $f['cuit'] ?? ''), 15, '0', STR_PAD_LEFT);
    $total = str_pad(number_format($f['total'] ?? 0, 2, '.', ''), 18, ' ', STR_PAD_LEFT);
    $cabLine = $prefix . $razonSocial . $cuit . str_repeat(' ', 60) . $fecha . str_repeat(' ', 6) . $total . str_repeat(' ', 260) . $total;
    $hCab[] = str_pad($cabLine, 610) . PHP_EOL;

    // Items
    $items = $itemsPorFactura[$idFactura] ?? [];
    foreach ($items as $det) {
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
      $regimen = str_pad(substr(preg_replace('/[^A-Za-z0-9]/', '', $ret['regimen'] ?? ''), 0, 8), 8);
      $montoReg = str_pad(number_format($ret['monto'] ?? 0, 2, '.', ''), 14, ' ', STR_PAD_LEFT);
      $regLine = $prefix . $regimen . $montoReg . str_repeat(' ', 14) . '0.00';
      $hReg[] = str_pad($regLine, 78) . PHP_EOL;
    }
  }

  // Escribir archivos
  file_put_contents(__DIR__ . '/CCabecer.txt', implode('', $hCab));
  file_put_contents(__DIR__ . '/CItems.txt', implode('', $hItems));
  file_put_contents(__DIR__ . '/CRegEsp.txt', implode('', $hReg));

  // Marcar como exportadas
  $upd = $pdo->prepare("UPDATE facturas_compra SET exportada = 1 WHERE id = ?");
  foreach ($facturas as $f) {
    $upd->execute([$f['id']]);
  }

  ob_clean();

  $zip = new ZipArchive();
  $zipPath = __DIR__ . '/facturas_compra_bejerman.zip';
  $zip->open($zipPath, ZipArchive::CREATE);
  $zip->addFile(__DIR__ . '/CCabecer.txt', 'CCabecer.txt');
  $zip->addFile(__DIR__ . '/CItems.txt', 'CItems.txt');
  $zip->addFile(__DIR__ . '/CRegEsp.txt', 'CRegEsp.txt');
  $zip->close();

  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="facturas_compra_bejerman.zip"');
  header('Pragma: public');

  readfile($zipPath);

  unlink(__DIR__ . '/CCabecer.txt');
  unlink(__DIR__ . '/CItems.txt');
  unlink(__DIR__ . '/CRegEsp.txt');
  unlink($zipPath);

} catch (Exception $e) {
  die("Error al exportar: " . $e->getMessage());
}

exit;
