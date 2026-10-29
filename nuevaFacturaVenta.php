<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
require_once __DIR__ . '/FacturacionARCA.php';

$csrfToken = $_SESSION['csrf_factura_arca'] ?? '';
if ($csrfToken === '') {
  $csrfToken = bin2hex(random_bytes(32));
  $_SESSION['csrf_factura_arca'] = $csrfToken;
}

$editMode = false;
$editId = null;
$claveOperacion = $_POST['clave_operacion'] ?? bin2hex(random_bytes(16));
if (!empty($_GET['id'])) {
  $editId = (int)$_GET['id'];
  $editMode = true;
}

if (!empty($_POST)) {
  if (!empty($_POST['btn_definitivo'])
      && !hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('La sesión de emisión expiró. Vuelva a abrir la factura e intente nuevamente.');
  }

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->beginTransaction();

  try {
    $idEstado = !empty($_POST['btn_definitivo']) ? 3 : 2;

    $qMoneda = $pdo->prepare('SELECT moneda FROM monedas WHERE id = ?');
    $qMoneda->execute([(int)($_POST['id_moneda'] ?? 0)]);
    $monedaFactura = $qMoneda->fetchColumn();
    if ($monedaFactura === false) {
      throw new RuntimeException('Seleccione una moneda válida para la factura.');
    }
    if (!esMonedaDolar($monedaFactura)) {
      $_POST['cotizacion'] = 1;
    } else {
      $cotizVal = floatval($_POST['cotizacion'] ?? 0);
      if ($cotizVal <= 0) {
        throw new RuntimeException('Ingrese una cotización mayor que cero para facturas en dólares.');
      }
      $_POST['cotizacion'] = $cotizVal;
    }

    $isEditing = !empty($_POST['id_factura']);

    // Punto de venta: en el alta llega solo el punto (el número lo asigna ARCA).
    $puntoVenta = filter_var($_POST['punto_venta'] ?? null, FILTER_VALIDATE_INT);
    if ($puntoVenta === false || $puntoVenta < 1 || $puntoVenta > 99998) {
      throw new RuntimeException('Seleccione un punto de venta válido.');
    }

    $remitoPuntoVenta = trim((string)($_POST['remito_punto_venta'] ?? ''));
    $remitoNumero = trim((string)($_POST['remito_numero'] ?? ''));
    if (($remitoPuntoVenta === '') !== ($remitoNumero === '')) {
      throw new RuntimeException('Para asociar un remito, complete tanto el punto de venta como el número de comprobante.');
    }
    if ($remitoPuntoVenta !== '') {
      $remitoPuntoVenta = filter_var($remitoPuntoVenta, FILTER_VALIDATE_INT);
      $remitoNumero = filter_var($remitoNumero, FILTER_VALIDATE_INT);
      if ($remitoPuntoVenta === false || $remitoPuntoVenta < 1 || $remitoPuntoVenta > 99998
          || $remitoNumero === false || $remitoNumero < 1 || $remitoNumero > 99999999) {
        throw new RuntimeException('El punto de venta o el número del Remito R no son válidos.');
      }
    } else {
      $remitoPuntoVenta = $remitoNumero = null;
    }
    $leyendaImpositiva = trim((string)($_POST['leyenda_impositiva'] ?? ''));
    if (mb_strlen($leyendaImpositiva) > 1000) {
      throw new RuntimeException('La leyenda impositiva no puede superar los 1000 caracteres.');
    }

    // Tipo de comprobante: codigo del padrón ARCA (con mapeo dual para
    // históricos que guardaban el id de la vieja tabla tipos_comprobante).
    $idTipoComprobante = (int)($_POST['id_tipo_comprobante'] ?? 0);
    // Invariante: se guarda el codigo ARCA directo (FK a arca_tipos_comprobante.codigo).
    // Si algo postea el formato intermedio 1000+codigo, se normaliza.
    if ($idTipoComprobante >= 1001) { $idTipoComprobante -= 1000; }
    $qTipo = $pdo->prepare("SELECT codigo, descripcion FROM arca_tipos_comprobante WHERE codigo = ?");
    $qTipo->execute([codigoArcaDesdeTipo($idTipoComprobante)]);
    $tipoRow = $qTipo->fetch(PDO::FETCH_ASSOC);
    if (!$tipoRow) {
      throw new RuntimeException('Seleccione un tipo de comprobante válido.');
    }
    $codigoTipoArca = (int)$tipoRow['codigo'];
    $esFacturaCreditoElectronica = in_array($codigoTipoArca, [201, 206, 211], true);
    $idLetraComprobante = derivarIdLetraComprobante($pdo, $idTipoComprobante);

    $fceFechaVencimiento = null;
    $fceCbu = null;
    $fceAliasCbu = null;
    $fceTransferencia = null;
    if ($esFacturaCreditoElectronica) {
      $fceDateRaw = trim((string)($_POST['fecha_vencimiento_pago'] ?? ''));
      $fceDate = DateTime::createFromFormat('!Y-m-d', $fceDateRaw);
      if ($fceDate && $fceDate->format('Y-m-d') === $fceDateRaw) $fceFechaVencimiento = $fceDateRaw;
      $fceTransferencia = strtoupper(trim((string)($_POST['fce_transferencia'] ?? ''))) ?: null;
      $emisorFacturaId = (int)($_POST['id_empresa'] ?? 0);
      $qFceCompany = $pdo->prepare('SELECT cbu_emisor, alias_cbu FROM empresas WHERE id = ?');
      $qFceCompany->execute([$emisorFacturaId]);
      $fceCompany = $qFceCompany->fetch(PDO::FETCH_ASSOC) ?: [];
      $fceCbu = preg_replace('/\D+/', '', (string)($fceCompany['cbu_emisor'] ?? '')) ?: null;
      $fceAliasCbu = trim((string)($fceCompany['alias_cbu'] ?? '')) ?: null;
      if ($isEditing) {
        $qFceExisting = $pdo->prepare('SELECT id_empresa, fce_cbu, fce_alias_cbu FROM facturas_venta WHERE id = ?');
        $qFceExisting->execute([(int)$_POST['id_factura']]);
        $existingFce = $qFceExisting->fetch(PDO::FETCH_ASSOC) ?: [];
        if ((int)($existingFce['id_empresa'] ?? 0) === $emisorFacturaId) {
          if (!empty($existingFce['fce_cbu'])) $fceCbu = (string)$existingFce['fce_cbu'];
          if (!empty($existingFce['fce_alias_cbu'])) $fceAliasCbu = (string)$existingFce['fce_alias_cbu'];
        }
      }
    }

    // El tipo debe estar habilitado para el punto de venta de la empresa.
    $idEmpresaVal = !empty($_POST['id_empresa']) ? (int)$_POST['id_empresa'] : 0;
    if ($idEmpresaVal > 0) {
      $qHab = $pdo->prepare(
        'SELECT COUNT(*) FROM arca_puntos_venta p
         INNER JOIN arca_punto_venta_tipos t ON t.id_punto_venta = p.id
         WHERE p.id_empresa = ? AND p.numero = ? AND p.bloqueado = 0
           AND p.fecha_baja IS NULL AND t.codigo_tipo_comprobante = ?'
      );
      $qHab->execute([$idEmpresaVal, $puntoVenta, (int)$tipoRow['codigo']]);
      if ((int)$qHab->fetchColumn() === 0) {
        throw new RuntimeException('El tipo de comprobante no está habilitado para el punto de venta elegido.');
      }
    }

    // Número: provisorio en el alta; en edición se conserva salvo que siga provisorio.
    if ($isEditing) {
      $qNum = $pdo->prepare("SELECT numero FROM facturas_venta WHERE id = ?");
      $qNum->execute([(int)$_POST['id_factura']]);
      $numeroExistente = (string)$qNum->fetchColumn();
      $partesNum = explode('-', $numeroExistente);
      if (count($partesNum) === 2 && preg_match('/^0+$/D', $partesNum[1])) {
        $_POST['numero'] = str_pad($puntoVenta, 4, '0', STR_PAD_LEFT) . '-00000000';
      } else {
        $_POST['numero'] = $numeroExistente;
      }
    } else {
      $_POST['numero'] = str_pad($puntoVenta, 4, '0', STR_PAD_LEFT) . '-00000000';
    }

    $rawConditions = $_POST['condiciones_venta'] ?? [];
    if (!is_array($rawConditions)) {
      throw new RuntimeException('Seleccione una o más condiciones de venta válidas.');
    }
    $conditionIds = [];
    foreach ($rawConditions as $rawConditionId) {
      $conditionId = filter_var($rawConditionId, FILTER_VALIDATE_INT);
      if ($conditionId === false || $conditionId < 1) {
        throw new RuntimeException('Una de las condiciones de venta seleccionadas no es válida.');
      }
      $conditionIds[(int)$conditionId] = (int)$conditionId;
    }
    // Compatibilidad con formularios antiguos que todavía envían una sola forma de pago.
    if (!$conditionIds && !empty($_POST['id_condicion_pago'])) {
      $legacyConditionId = filter_var($_POST['id_condicion_pago'], FILTER_VALIDATE_INT);
      if ($legacyConditionId !== false && $legacyConditionId > 0) {
        $conditionIds[(int)$legacyConditionId] = (int)$legacyConditionId;
      }
    }
    if (!$conditionIds) {
      throw new RuntimeException('Seleccione al menos una condición de venta.');
    }
    $conditionIds = array_values($conditionIds);
    $conditionPlaceholders = implode(',', array_fill(0, count($conditionIds), '?'));
    $qValidConditions = $pdo->prepare("SELECT COUNT(*) FROM formas_pago WHERE id IN ($conditionPlaceholders)");
    $qValidConditions->execute($conditionIds);
    if ((int)$qValidConditions->fetchColumn() !== count($conditionIds)) {
      throw new RuntimeException('Una de las condiciones de venta ya no está disponible.');
    }
    $_POST['id_condicion_pago'] = $conditionIds[0];

    $idEmpresaActividad = filter_var($_POST['id_empresa'] ?? null, FILTER_VALIDATE_INT);
    $actividadArcaId = filter_var($_POST['actividad_arca_id'] ?? null, FILTER_VALIDATE_INT);
    if ($idEmpresaActividad === false || $idEmpresaActividad < 1 || $actividadArcaId === false || $actividadArcaId < 1) {
      throw new RuntimeException('Seleccione una actividad ARCA válida para la empresa emisora.');
    }
    $qActividad = $pdo->prepare('SELECT COUNT(*) FROM arca_actividades_empresa WHERE id_empresa = ? AND codigo = ?');
    $qActividad->execute([$idEmpresaActividad, $actividadArcaId]);
    if ((int)$qActividad->fetchColumn() === 0) {
      throw new RuntimeException('La actividad seleccionada no está configurada para la empresa emisora. Sincronice las actividades desde Administración de Empresas.');
    }
    $_POST['actividad_arca_id'] = (int)$actividadArcaId;

    if ($isEditing) {
      $idFactura = (int)$_POST['id_factura'];

      $qCheckEstado = $pdo->prepare("SELECT id_estado, pagada, exportada FROM facturas_venta WHERE id = ?");
      $qCheckEstado->execute([$idFactura]);
      $estadoRow = $qCheckEstado->fetch(PDO::FETCH_ASSOC);
      if (!$estadoRow) throw new Exception("Factura no encontrada.");
      if ($estadoRow['id_estado'] == 3 || $estadoRow['pagada'] == 1 || $estadoRow['exportada'] == 1) {
        $motivo = $estadoRow['pagada'] == 1 ? 'pagada' : ($estadoRow['exportada'] == 1 ? 'exportada' : 'definitiva');
        throw new Exception("Esta factura ya fue " . $motivo . " y no puede editarse.");
      }

      $idCondicionPago = (int)$_POST['id_condicion_pago'];
      $idProyecto      = !empty($_POST['id_proyecto'])       ? intval($_POST['id_proyecto'])       : null;
      $idEmpresa       = !empty($_POST['id_empresa'])        ? intval($_POST['id_empresa'])        : null;
      $idCuentaDest    = !empty($_POST['id_cuenta_destino']) ? intval($_POST['id_cuenta_destino']) : null;

      $q = $pdo->prepare("UPDATE facturas_venta SET id_tipo_comprobante=?, id_letra_comprobante=?, id_proyecto=?, numero=?, id_cuenta_destino=?, id_empresa=?, actividad_arca_id=?, fecha_emitida=?, id_condicion_pago=?, id_moneda=?, cotizacion=?, observaciones=?, leyenda_impositiva=?, remito_punto_venta=?, remito_numero=?, fce_fecha_vencimiento=?, fce_cbu=?, fce_alias_cbu=?, fce_transferencia=?, id_estado=? WHERE id=?");
      $q->execute([
        $idTipoComprobante,
        $idLetraComprobante,
        $idProyecto,
        $_POST['numero'],
        $idCuentaDest,
        $idEmpresa,
        $_POST['actividad_arca_id'],
        $_POST['fecha_emitida'],
        $idCondicionPago,
        intval($_POST['id_moneda']),
        $_POST['cotizacion'],
        $_POST['observaciones'],
        $leyendaImpositiva,
        $remitoPuntoVenta,
        $remitoNumero,
        $fceFechaVencimiento,
        $fceCbu,
        $fceAliasCbu,
        $fceTransferencia,
        $idEstado,
        $idFactura
      ]);

      $pdo->prepare("DELETE FROM facturas_venta_detalle_x_certificados_avance WHERE id_factura_venta_detalle IN (SELECT id FROM facturas_venta_detalle WHERE id_factura_venta = ?)")->execute([$idFactura]);
      $pdo->prepare("DELETE FROM facturas_venta_detalle WHERE id_factura_venta = ?")->execute([$idFactura]);
      $pdo->prepare("DELETE FROM facturas_venta_retenciones WHERE id_factura_venta = ?")->execute([$idFactura]);
      $pdo->prepare("DELETE FROM facturas_venta_otros WHERE id_factura_venta = ?")->execute([$idFactura]);
      $pdo->prepare("UPDATE certificados_avances_detalle SET id_comprobante = NULL WHERE id_comprobante = ?")->execute([$idFactura]);

    } else {
      $idCondicionPago = (int)$_POST['id_condicion_pago'];
      $idProyecto      = !empty($_POST['id_proyecto'])       ? intval($_POST['id_proyecto'])       : null;
      $idEmpresa       = !empty($_POST['id_empresa'])        ? intval($_POST['id_empresa'])        : null;
      $idCuentaDest    = !empty($_POST['id_cuenta_destino']) ? intval($_POST['id_cuenta_destino']) : null;

      $q = $pdo->prepare("INSERT INTO facturas_venta
                     (id_tipo_comprobante, id_letra_comprobante, id_proyecto,
                      numero, id_cuenta_destino, id_empresa, actividad_arca_id, fecha_emitida, fecha_enviada,
                       id_condicion_pago, id_moneda, cotizacion, observaciones, leyenda_impositiva,
                       remito_punto_venta, remito_numero, fce_fecha_vencimiento, fce_cbu, fce_alias_cbu,
                       fce_transferencia, id_usuario, id_estado)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $q->execute([
        $idTipoComprobante,
        $idLetraComprobante,
        $idProyecto,
        $_POST['numero'],
        $idCuentaDest,
        $idEmpresa,
        $_POST['actividad_arca_id'],
        $_POST['fecha_emitida'],
        $_POST['fecha_emitida'], // fecha_enviada: la columna sigue siendo NOT NULL; se iguala a la fecha emitida
        $idCondicionPago,
        intval($_POST['id_moneda']),
        $_POST['cotizacion'],
        $_POST['observaciones'],
        $leyendaImpositiva,
        $remitoPuntoVenta,
        $remitoNumero,
        $fceFechaVencimiento,
        $fceCbu,
        $fceAliasCbu,
        $fceTransferencia,
        $_SESSION['user']['id'],
        $idEstado
      ]);
      $idFactura = $pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM facturas_venta_condiciones_pago WHERE id_factura_venta = ?')->execute([$idFactura]);
    $qCondicionFactura = $pdo->prepare('INSERT INTO facturas_venta_condiciones_pago (id_factura_venta, id_forma_pago) VALUES (?, ?)');
    foreach ($conditionIds as $conditionId) {
      $qCondicionFactura->execute([$idFactura, $conditionId]);
    }

    // Detalles
    $gravado = 0;
    $noGravado = 0;
    $iva = 0;
    $totalFv = 0;
    $basesPorAlicuota = [];
    $qCondicionReceptor = $pdo->prepare('SELECT id_condicion_iva FROM cuentas WHERE id = ?');
    $qCondicionReceptor->execute([$idCuentaDest]);
    $condicionReceptor = $qCondicionReceptor->fetchColumn();
    if ($condicionReceptor === false) {
      throw new RuntimeException('Seleccione un cliente válido para la factura.');
    }
    $receptorExento = (int)$condicionReceptor === 5;
    $esFacturaC = strtoupper(letraComprobanteDesdeTipo((string)$tipoRow['descripcion'])) === 'C';
    $detalles = !empty($_POST['detalles_json']) ? json_decode($_POST['detalles_json'], true) : [];

    if (is_array($detalles)) {
      $qDet = $pdo->prepare("INSERT INTO facturas_venta_detalle
                                     (id_factura_venta, id_concepto_contable, descripcion, ncm, cantidad, precio, subtotal, porcentaje_descuento)
                                     VALUES (?,?,?,?,?,?,?,?)");
      $hasMontoCols = false;
      try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM facturas_venta_detalle_x_certificados_avance LIKE 'cantidad'")->fetch();
        $hasMontoCols = (bool)$colCheck;
      } catch (Exception $e) {
        $hasMontoCols = false;
      }
      if ($hasMontoCols) {
        $qImp = $pdo->prepare("INSERT INTO facturas_venta_detalle_x_certificados_avance
                                     (id_factura_venta_detalle, id_certificado_avance, cantidad, precio) VALUES (?,?,?,?)");
      } else {
        $qImp = $pdo->prepare("INSERT INTO facturas_venta_detalle_x_certificados_avance
                                     (id_factura_venta_detalle, id_certificado_avance) VALUES (?,?)");
      }
      $qTasa = $pdo->prepare("SELECT COALESCE(ti.tasa, 21)
                                FROM conceptos_contables cc
                                LEFT JOIN tipos_iva ti ON ti.id = cc.id_alicuota_iva
                                WHERE cc.id = ?");

      foreach ($detalles as $det) {
        $ncmDetalle = isset($det['ncm']) && trim((string)$det['ncm']) !== '' ? trim((string)$det['ncm']) : null;
        $porcDescuento = isset($det['porcentaje_descuento']) ? (float)$det['porcentaje_descuento'] : 0;
        if ($porcDescuento < 0 || $porcDescuento > 100) {
          throw new RuntimeException("El descuento del detalle debe estar entre 0 y 100%.");
        }
        $impIds = (!empty($det['imputaciones']) && is_array($det['imputaciones']))
          ? array_values(array_unique(array_map('intval', $det['imputaciones'])))
          : [];

        if (!empty($impIds)) {
          // Ítem con CA imputados: montos tipeados (imputaciones_data); fallback al total del CA.
          $qMonto = $pdo->prepare("SELECT id, monto_total FROM certificados_avances_cabecera WHERE id = ?");
          $typed = [];
          if (!empty($det['imputaciones_data']) && is_array($det['imputaciones_data'])) {
            foreach ($det['imputaciones_data'] as $t) {
              if (isset($t['id_cd'])) { $typed[(int)$t['id_cd']] = $t; }
            }
          }
          $subtotal = 0;
          $cant = 0;
          $validos = [];
          foreach ($impIds as $idImp) {
            if ($idImp <= 0) { continue; }
            $qMonto->execute([$idImp]);
            $m = $qMonto->fetch(PDO::FETCH_ASSOC);
            if (!$m) { continue; }
            $tc = isset($typed[$idImp]['cantidad']) ? floatval($typed[$idImp]['cantidad']) : 1;
            $tp = isset($typed[$idImp]['precio']) ? floatval($typed[$idImp]['precio']) : (float)$m['monto_total'];
            if ($tc < 0) { $tc = 0; }
            if ($tp < 0) { $tp = 0; }
            $subtotal += round($tc * $tp, 2);
            $cant += $tc;
            $validos[] = ['id' => (int)$m['id'], 'cantidad' => $tc, 'precio' => $tp];
          }
          $subtotal = round($subtotal * (1 - $porcDescuento / 100), 2);
          $precio = 0;

          $qDet->execute([$idFactura, intval($det['id_concepto']), $det['descripcion'] ?? '', $ncmDetalle, $cant, $precio, $subtotal, $porcDescuento]);
          $idDetalle = $pdo->lastInsertId();

          foreach ($validos as $vImp) {
            if ($hasMontoCols) {
              $qImp->execute([$idDetalle, $vImp['id'], $vImp['cantidad'], $vImp['precio']]);
            } else {
              $qImp->execute([$idDetalle, $vImp['id']]);
            }
          }
        } else {
          // Ítem manual sin imputaciones.
          $cant     = floatval($det['cantidad']);
          $precio   = floatval($det['precio']);
          $subtotal = round($cant * $precio * (1 - $porcDescuento / 100), 2);

          $qDet->execute([$idFactura, intval($det['id_concepto']), $det['descripcion'] ?? '', $ncmDetalle, $cant, $precio, $subtotal, $porcDescuento]);
          $idDetalle = $pdo->lastInsertId();
        }

        $qTasa->execute([intval($det['id_concepto'])]);
        $tasaIva = (float)$qTasa->fetchColumn();
        if ($receptorExento || $esFacturaC) {
          $tasaIva = 0;
        }
        if ($tasaIva > 0) {
          $gravado += $subtotal;
          $claveTasa = number_format($tasaIva, 4, '.', '');
          $basesPorAlicuota[$claveTasa] = ($basesPorAlicuota[$claveTasa] ?? 0) + (int)round($subtotal * 100);
        } else {
          $noGravado += $subtotal;
        }
      }
    }

    foreach ($basesPorAlicuota as $tasa => $baseCentavos) {
      $t = (float)$tasa;
      if ($t > 0) {
        $iva += round($baseCentavos * $t / 100) / 100;
      }
    }
    $gravado = round($gravado, 2);
    $noGravado = round($noGravado, 2);
    $iva = round($iva, 2);
    $totalFv = round($gravado + $noGravado + $iva, 2);

    // Retenciones
    $totalOtros = 0;
    $retenciones = !empty($_POST['retenciones_json']) ? json_decode($_POST['retenciones_json'], true) : [];

    if (is_array($retenciones)) {
      $qRegimenData = $pdo->prepare("SELECT regimen, codigo, articulo, signo_vta FROM regimenes_facturacion WHERE id = ?");
      $qRet = $pdo->prepare("INSERT INTO facturas_venta_retenciones
                                     (id_factura_venta, regimen_text, codigo, articulo, monto, porcentaje, base_imponible) VALUES (?,?,?,?,?,?,?)");
      foreach ($retenciones as $ret) {
        $idRegimen = intval($ret['id_regimen']);
        $qRegimenData->execute([$idRegimen]);
        $regData = $qRegimenData->fetch(PDO::FETCH_ASSOC);
        $monto = floatval($ret['monto']);
        $porcentaje = floatval($ret['porcentaje'] ?? 0);
        $base = floatval($ret['base'] ?? 0);
        if (isset($regData['signo_vta']) && (int)$regData['signo_vta'] === 2) {
          $monto = -abs($monto);
        } else {
          $monto = abs($monto);
        }
        $qRet->execute([$idFactura, $regData['regimen'] ?? null, $regData['codigo'] ?? null, $regData['articulo'] ?? null, $monto, $porcentaje, $base]);
        $totalOtros += $monto;

        // Actualiza el % en el régimen para que la próxima factura ya traiga el último valor
        if ($idRegimen > 0 && $porcentaje > 0) {
          $qUpdPorc = $pdo->prepare("UPDATE regimenes_facturacion SET porcentaje = ? WHERE id = ?");
          $qUpdPorc->execute([$porcentaje, $idRegimen]);
        }
      }
    }

    // Totales
    $qu = $pdo->prepare("UPDATE facturas_venta
                              SET subtotal_gravado=?, subtotal_no_gravado=?, iva=?, otros=?, total=?
                              WHERE id=?");
    $qu->execute([$gravado, $noGravado, $iva, $totalOtros, $totalFv + $totalOtros, $idFactura]);

    if ($leyendaImpositiva === '') {
      $cotizacionLeyenda = (float)$_POST['cotizacion'];
      $qMonedaLeyenda = $pdo->prepare('SELECT moneda FROM monedas WHERE id = ?');
      $qMonedaLeyenda->execute([(int)$_POST['id_moneda']]);
      if (!esMonedaDolar((string)$qMonedaLeyenda->fetchColumn())) $cotizacionLeyenda = 1;
      $dineroLeyenda = static fn(float $monto): string => number_format($monto, 2, ',', '.');
      $leyendaImpositiva = 'A los fines impositivos Importe Neto $ '
        . $dineroLeyenda(($gravado + $noGravado) * $cotizacionLeyenda)
        . ', IVA $ ' . $dineroLeyenda($iva * $cotizacionLeyenda)
        . ' y el Total $ ' . $dineroLeyenda(($gravado + $noGravado + $iva) * $cotizacionLeyenda) . '.';
    }
    $pdo->prepare('UPDATE facturas_venta SET leyenda_impositiva = ? WHERE id = ?')->execute([$leyendaImpositiva, $idFactura]);

    // Regímenes
    if (!empty($_POST['regimenes'])) {
      $qp = $pdo->prepare("SELECT regimen, codigo, articulo, porcentaje FROM regimenes_facturacion WHERE id = ?");
      $qi = $pdo->prepare("INSERT INTO facturas_venta_otros (id_factura_venta, id_regimen, regimen_text, codigo, articulo, porcentaje) VALUES (?,?,?,?,?,?)");
      foreach ($_POST['regimenes'] as $idRegimen) {
        $qp->execute([$idRegimen]);
        $reg = $qp->fetch(PDO::FETCH_ASSOC);
        $qi->execute([$idFactura, $idRegimen, $reg['regimen'] ?? null, $reg['codigo'] ?? null, $reg['articulo'] ?? null, $reg['porcentaje'] ?? 0]);
      }
    }

    // Certificados
    if (!empty($_POST['certificados'])) {
      foreach ($_POST['certificados'] as $idCert) {
        $idCert = intval($idCert);
        if ($idCert > 0) {
          $qc = $pdo->prepare("UPDATE certificados_avances_detalle SET id_comprobante = ? WHERE id_certificado_avance = ?");
          $qc->execute([$idFactura, $idCert]);
        }
      }
    }

    $arcaInput = [
      'concepto_arca' => $_POST['concepto_arca'] ?? null,
      'fecha_servicio_desde' => $_POST['fecha_servicio_desde'] ?? '',
      'fecha_servicio_hasta' => $_POST['fecha_servicio_hasta'] ?? '',
      'fecha_vencimiento_pago' => $_POST['fecha_vencimiento_pago'] ?? '',
    ];
    $emitirEnARCA = $idEstado === 3 && FacturacionARCA::habilitada();
    if ($emitirEnARCA) {
      FacturacionARCA::validarFactura($pdo, (int)$idFactura, $arcaInput);
    }

    $logMsg = $isEditing ? "Modificación de Factura de Venta ID #$idFactura" : "Nueva Factura de Venta ID #$idFactura";
    $ql = $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
                               VALUES (now(), ?, ?, 'Facturas de Venta', '')");
    $ql->execute([$_SESSION['user']['id'], $logMsg]);

    $pdo->commit();
    Database::disconnect();

    if ($emitirEnARCA) {
      try {
        $arcaPdo = Database::connect();
        $resultadoARCA = FacturacionARCA::emitir($arcaPdo, (int)$idFactura, $claveOperacion, $arcaInput);
        $_SESSION['factura_arca_mensaje'] = [
          'tipo' => $resultadoARCA['estado'] === 'autorizado' ? 'success' : 'warning',
          'texto' => $resultadoARCA['mensaje'],
        ];
      } catch (Throwable $e) {
        $_SESSION['factura_arca_mensaje'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
      } finally {
        Database::disconnect();
      }
      header("Location: verFacturaVenta.php?id=" . (int)$idFactura);
    } elseif ($idEstado === 3) {
      header("Location: verFacturaVenta.php?id=" . (int)$idFactura);
    } else {
      header("Location: listarFacturasVenta.php");
    }
    exit;
  } catch (Exception $e) {
    $pdo->rollBack();
    Database::disconnect();
    $errorMsg = "Error al guardar: " . $e->getMessage();
  }
}

// Datos pre-cargados
$proyectoDatos = [];
$preseleccionado = false;
$empresaLabel = '';
$clienteLabel = '';
$proyectoLabel = '';
$facturaData = null;
$detallesExistentes = [];
$retencionesExistentes = [];
$certIds = [];
$regimenesSeleccionados = [];
$condicionesVentaSeleccionadas = array_values(array_unique(array_map('intval', (array)($_POST['condiciones_venta'] ?? []))));
$monedaLabel = '';
$idMoneda = 0;

if ($editMode && $editId) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $q = $pdo->prepare("SELECT * FROM facturas_venta WHERE id = ?");
  $q->execute([$editId]);
  $facturaData = $q->fetch(PDO::FETCH_ASSOC);

  if (!empty($facturaData['id_estado']) && ($facturaData['id_estado'] == 3 || $facturaData['pagada'] == 1 || $facturaData['exportada'] == 1)) {
    $msg = $facturaData['pagada'] == 1 ? 'pagada' : ($facturaData['exportada'] == 1 ? 'exportada' : 'definitiva');
    echo '<script>alert("Esta factura ya fue ' . $msg . ' y no puede editarse");</script>';
    echo '<div style="text-align:center;padding:40px;color:red;font-size:18px;">Esta factura ya fue ' . $msg . ' y no puede editarse.<br><a href="listarFacturasVenta.php">Volver al listado</a></div>';
    exit;
  }

  if ($facturaData) {
    $qCondicionesVenta = $pdo->prepare('SELECT id_forma_pago FROM facturas_venta_condiciones_pago WHERE id_factura_venta = ? ORDER BY id_forma_pago');
    $qCondicionesVenta->execute([$editId]);
    $condicionesGuardadas = array_map('intval', $qCondicionesVenta->fetchAll(PDO::FETCH_COLUMN));
    if (empty($_POST)) {
      if ($condicionesGuardadas) {
        $condicionesVentaSeleccionadas = $condicionesGuardadas;
      } elseif (!empty($facturaData['id_condicion_pago'])) {
        $condicionesVentaSeleccionadas = [(int)$facturaData['id_condicion_pago']];
      }
    }

    $idEmpresaVal = (int)$facturaData['id_empresa'];
    $idClienteVal = (int)$facturaData['id_cuenta_destino'];
    $idProyectoVal = (int)$facturaData['id_proyecto'];

    if ($idEmpresaVal) {
      $q = $pdo->prepare("SELECT empresa FROM empresas WHERE id = ?");
      $q->execute([$idEmpresaVal]);
      $empresaLabel = $q->fetchColumn() ?: '';
    }
    if (!empty($facturaData['id_moneda'])) {
      $q = $pdo->prepare("SELECT moneda FROM monedas WHERE id = ?");
      $q->execute([$facturaData['id_moneda']]);
      $monedaLabel = $q->fetchColumn() ?: '';
      $idMoneda = (int)$facturaData['id_moneda'];
    }
    if ($idClienteVal) {
      $q = $pdo->prepare("SELECT nombre FROM cuentas WHERE id = ?");
      $q->execute([$idClienteVal]);
      $clienteLabel = $q->fetchColumn() ?: '';
    }
    if ($idProyectoVal) {
      $q = $pdo->prepare("SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre
                           FROM proyectos p
                           INNER JOIN sitios s ON s.id = p.id_sitio
                           WHERE p.id = ?");
      $q->execute([$idProyectoVal]);
      $proj = $q->fetch(PDO::FETCH_ASSOC);
      if ($proj) {
        $proyectoLabel = $proj['nro_sitio'] . '-' . $proj['nro_subsitio'] . '-' . $proj['nro'] . ': ' . htmlspecialchars($proj['nombre']);
      }
    }

    $qReg = $pdo->prepare("SELECT id_regimen FROM facturas_venta_otros WHERE id_factura_venta = ?");
    $qReg->execute([$editId]);
    $regimenesSeleccionados = $qReg->fetchAll(PDO::FETCH_COLUMN);

    $qCert = $pdo->prepare("SELECT DISTINCT id_certificado_avance FROM certificados_avances_detalle WHERE id_comprobante = ?");
    $qCert->execute([$editId]);
    $certIds = $qCert->fetchAll(PDO::FETCH_COLUMN);

    $qDet = $pdo->prepare("SELECT d.*, cc.descripcion AS concepto_text
                             FROM facturas_venta_detalle d
                             INNER JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
                             WHERE d.id_factura_venta = ?");
    $qDet->execute([$editId]);
    $hasMontoColsEdit = false;
    try {
      $colCheckEdit = $pdo->query("SHOW COLUMNS FROM facturas_venta_detalle_x_certificados_avance LIKE 'cantidad'")->fetch();
      $hasMontoColsEdit = (bool)$colCheckEdit;
    } catch (Exception $e) {
      $hasMontoColsEdit = false;
    }
    while ($row = $qDet->fetch(PDO::FETCH_ASSOC)) {
      if ($hasMontoColsEdit) {
        $qImp = $pdo->prepare("SELECT id_certificado_avance, cantidad, precio FROM facturas_venta_detalle_x_certificados_avance WHERE id_factura_venta_detalle = ?");
      } else {
        $qImp = $pdo->prepare("SELECT id_certificado_avance FROM facturas_venta_detalle_x_certificados_avance WHERE id_factura_venta_detalle = ?");
      }
      $qImp->execute([$row['id']]);
      $impRows = $qImp->fetchAll(PDO::FETCH_ASSOC);
      $certIdsDet = array_map(function ($r) { return (int)$r['id_certificado_avance']; }, $impRows);
      $montoGuardado = [];
      foreach ($impRows as $rImp) {
        $montoGuardado[(int)$rImp['id_certificado_avance']] = $rImp;
      }

      $imputacionesData = [];
      $imputacionesText = [];
      foreach ($certIdsDet as $cid) {
        $qC = $pdo->prepare("SELECT ca.id, ca.nro_certificado, ca.nro_revision, ca.monto_total
                               FROM certificados_avances_cabecera ca
                               INNER JOIN certificados_maestros cm ON cm.id = ca.id_certificado_maestro
                               WHERE ca.id = ?");
        $qC->execute([$cid]);
        $c = $qC->fetch(PDO::FETCH_ASSOC);
        if ($c) {
          $concepto = fmtCertificadoAvance($c['nro_certificado'], $c['nro_revision']);
          $monto = (float)$c['monto_total'];
          $cantG = isset($montoGuardado[$cid]['cantidad']) ? (float)$montoGuardado[$cid]['cantidad'] : 1;
          $precG = isset($montoGuardado[$cid]['precio']) && (float)$montoGuardado[$cid]['precio'] > 0 ? (float)$montoGuardado[$cid]['precio'] : $monto;
          if ($cantG <= 0) { $cantG = 1; }
          $imputacionesData[] = ['id_cd' => $c['id'], 'concepto_text' => $concepto, 'cantidad' => $cantG, 'precio' => $precG, 'restante' => 1, 'oc_precio' => $monto];
          $imputacionesText[] = $concepto . ' (' . $cantG . ' x $' . number_format($precG, 2) . ')';
        }
      }

      $detallesExistentes[] = [
        'id' => $row['id'],
        'id_concepto' => $row['id_concepto_contable'],
        'concepto_text' => $row['concepto_text'],
        'descripcion' => $row['descripcion'] ?? '',
        'ncm' => $row['ncm'] ?? '',
        'cantidad' => (float)$row['cantidad'],
        'precio' => (float)$row['precio'],
        'subtotal' => (float)$row['subtotal'],
        'porcentaje_descuento' => (float)($row['porcentaje_descuento'] ?? 0),
        'imputaciones' => $certIdsDet,
        'imputaciones_data' => $imputacionesData,
        'imputaciones_text' => $imputacionesText
      ];
    }

    $qRet = $pdo->prepare("SELECT r.*,
                             (SELECT rf.id FROM regimenes_facturacion rf WHERE rf.regimen = r.regimen_text AND rf.anulado = 0 ORDER BY rf.id LIMIT 1) AS id_regimen
                             FROM facturas_venta_retenciones r
                             WHERE r.id_factura_venta = ?");
    $qRet->execute([$editId]);
    while ($row = $qRet->fetch(PDO::FETCH_ASSOC)) {
      $porc = (float)$row['porcentaje'];
      $retencionesExistentes[] = [
        'id_regimen' => $row['id_regimen'] ?? $row['id'],
        'regimen_text' => $row['regimen_text'],
        'monto' => (float)$row['monto'],
        'porcentaje' => $porc,
        'base' => $porc > 0 ? round((float)$row['monto'] / ($porc / 100), 2) : (float)$row['monto']
      ];
    }

    $qCondicionReceptor = $pdo->prepare('SELECT id_condicion_iva FROM cuentas WHERE id = ?');
    $qCondicionReceptor->execute([$facturaData['id_cuenta_destino']]);
    $proyectoDatos = [
      'id_empresa' => $facturaData['id_empresa'],
      'id_cliente' => $facturaData['id_cuenta_destino'],
      'id_condicion_iva' => $qCondicionReceptor->fetchColumn(),
    ];
    $_GET['id_proyecto'] = $facturaData['id_proyecto'];

    $preseleccionado = true;
  }

  Database::disconnect();

} elseif (!empty($_GET['id_proyecto'])) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $q = $pdo->prepare("SELECT p.id, p.nombre, p.id_cliente, p.solicitante, cu.id_condicion_iva,
                               s.id_empresa,
                               COALESCE(cu.nombre, p.solicitante) AS nombre_cliente
                        FROM proyectos p
                        INNER JOIN sitios s ON s.id = p.id_sitio
                        LEFT JOIN cuentas cu ON cu.id = p.id_cliente
                        WHERE p.id = ?");
  $q->execute([$_GET['id_proyecto']]);
  $proyectoDatos = $q->fetch(PDO::FETCH_ASSOC) ?: [];
  Database::disconnect();
  $preseleccionado = !empty($proyectoDatos);

  if ($preseleccionado) {
    $clienteLabel = $proyectoDatos['nombre_cliente'] ?? '';
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (!empty($proyectoDatos['id_empresa'])) {
      $q = $pdo->prepare("SELECT empresa FROM empresas WHERE id = ?");
      $q->execute([$proyectoDatos['id_empresa']]);
      $empresaLabel = $q->fetchColumn() ?: '';
    }
    if (!empty($_GET['id_proyecto'])) {
      $q = $pdo->prepare("SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre
                            FROM proyectos p
                            INNER JOIN sitios s ON s.id = p.id_sitio
                            WHERE p.id = ?");
      $q->execute([intval($_GET['id_proyecto'])]);
      $proj = $q->fetch(PDO::FETCH_ASSOC);
      if ($proj) {
        $proyectoLabel = $proj['nro_sitio'] . '-' . $proj['nro_subsitio'] . '-' . $proj['nro'] . ': ' . htmlspecialchars($proj['nombre']);
      }
    }
    Database::disconnect();
  }

  if (!empty($_GET['certificados'])) {
    $certIds = array_filter(array_map('intval', (array)$_GET['certificados']));
  }

  // Precarga la moneda del primer certificado vinculado (el select sigue siendo editable)
  if (!empty($certIds)) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $qMon = $pdo->prepare("SELECT cm.id_moneda, m.moneda
                             FROM certificados_avances_cabecera ca
                             INNER JOIN certificados_maestros cm ON cm.id = ca.id_certificado_maestro
                             LEFT JOIN monedas m ON m.id = cm.id_moneda
                             WHERE ca.id = ?
                             ORDER BY ca.id
                             LIMIT 1");
    $qMon->execute([reset($certIds)]);
    if ($filaMon = $qMon->fetch(PDO::FETCH_ASSOC)) {
      $idMoneda = (int)($filaMon['id_moneda'] ?? 0);
      $monedaLabel = $filaMon['moneda'] ?: '';
    }
    Database::disconnect();
  }
}

$claveOperacion = preg_match('/^[a-f0-9]{32}$/D', (string)$claveOperacion)
  ? $claveOperacion
  : bin2hex(random_bytes(16));
$errorMsg = $errorMsg ?? '';
$ncmDefault = '73.08.20.00';
$pdoNcm = Database::connect();
$qNcm = $pdoNcm->query("SELECT valor FROM parametros WHERE id = 13");
if ($qNcm && ($valNcm = $qNcm->fetchColumn()) !== false && trim((string)$valNcm) !== '') {
  $ncmDefault = trim((string)$valNcm);
}
Database::disconnect();

$vista = 'venta';
include 'nuevaFactura.php';
