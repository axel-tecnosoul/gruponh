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

    $_POST['id_condicion_pago'] = empty($_POST['id_condicion_pago']) ? 0 : $_POST['id_condicion_pago'];
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
    $idLetraComprobante = derivarIdLetraComprobante($pdo, $idTipoComprobante);

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

    if (empty($_POST['id_condicion_pago'])) {
      $qFallback = $pdo->query("SELECT id FROM formas_pago LIMIT 1");
      $_POST['id_condicion_pago'] = (int)$qFallback->fetchColumn();
    }

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

      $idCondicionPago = !empty($_POST['id_condicion_pago']) ? intval($_POST['id_condicion_pago']) : null;
      $idProyecto      = !empty($_POST['id_proyecto'])       ? intval($_POST['id_proyecto'])       : null;
      $idEmpresa       = !empty($_POST['id_empresa'])        ? intval($_POST['id_empresa'])        : null;
      $idCuentaDest    = !empty($_POST['id_cuenta_destino']) ? intval($_POST['id_cuenta_destino']) : null;

      $q = $pdo->prepare("UPDATE facturas_venta SET id_tipo_comprobante=?, id_letra_comprobante=?, id_proyecto=?, numero=?, id_cuenta_destino=?, id_empresa=?, fecha_emitida=?, fecha_enviada=?, id_condicion_pago=?, id_moneda=?, cotizacion=?, observaciones=?, id_estado=? WHERE id=?");
      $q->execute([
        $idTipoComprobante,
        $idLetraComprobante,
        $idProyecto,
        $_POST['numero'],
        $idCuentaDest,
        $idEmpresa,
        $_POST['fecha_emitida'],
        $_POST['fecha_enviada'],
        $idCondicionPago,
        intval($_POST['id_moneda']),
        $_POST['cotizacion'],
        $_POST['observaciones'],
        $idEstado,
        $idFactura
      ]);

      $pdo->prepare("DELETE FROM facturas_venta_detalle_x_certificados_avance WHERE id_factura_venta_detalle IN (SELECT id FROM facturas_venta_detalle WHERE id_factura_venta = ?)")->execute([$idFactura]);
      $pdo->prepare("DELETE FROM facturas_venta_detalle WHERE id_factura_venta = ?")->execute([$idFactura]);
      $pdo->prepare("DELETE FROM facturas_venta_retenciones WHERE id_factura_venta = ?")->execute([$idFactura]);
      $pdo->prepare("DELETE FROM facturas_venta_otros WHERE id_factura_venta = ?")->execute([$idFactura]);
      $pdo->prepare("UPDATE certificados_avances_detalle SET id_comprobante = NULL WHERE id_comprobante = ?")->execute([$idFactura]);

    } else {
      $idCondicionPago = !empty($_POST['id_condicion_pago']) ? intval($_POST['id_condicion_pago']) : null;
      $idProyecto      = !empty($_POST['id_proyecto'])       ? intval($_POST['id_proyecto'])       : null;
      $idEmpresa       = !empty($_POST['id_empresa'])        ? intval($_POST['id_empresa'])        : null;
      $idCuentaDest    = !empty($_POST['id_cuenta_destino']) ? intval($_POST['id_cuenta_destino']) : null;

      $q = $pdo->prepare("INSERT INTO facturas_venta
                    (id_tipo_comprobante, id_letra_comprobante, id_proyecto,
                     numero, id_cuenta_destino, id_empresa, fecha_emitida, fecha_enviada,
                     id_condicion_pago, id_moneda, cotizacion, observaciones, id_usuario, id_estado)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $q->execute([
        $idTipoComprobante,
        $idLetraComprobante,
        $idProyecto,
        $_POST['numero'],
        $idCuentaDest,
        $idEmpresa,
        $_POST['fecha_emitida'],
        $_POST['fecha_enviada'],
        $idCondicionPago,
        intval($_POST['id_moneda']),
        $_POST['cotizacion'],
        $_POST['observaciones'],
        $_SESSION['user']['id'],
        $idEstado
      ]);
      $idFactura = $pdo->lastInsertId();
    }

    // Detalles
    $gravado = 0;
    $noGravado = 0;
    $iva = 0;
    $totalFv = 0;
    $basesPorAlicuota = [];
    $detalles = !empty($_POST['detalles_json']) ? json_decode($_POST['detalles_json'], true) : [];

    if (is_array($detalles)) {
      $qDet = $pdo->prepare("INSERT INTO facturas_venta_detalle
                                     (id_factura_venta, id_concepto_contable, descripcion, cantidad, precio, subtotal, porcentaje_descuento)
                                     VALUES (?,?,?,?,?,?,?)");
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

          $qDet->execute([$idFactura, intval($det['id_concepto']), $det['descripcion'] ?? '', $cant, $precio, $subtotal, $porcDescuento]);
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

          $qDet->execute([$idFactura, intval($det['id_concepto']), $det['descripcion'] ?? '', $cant, $precio, $subtotal, $porcDescuento]);
          $idDetalle = $pdo->lastInsertId();
        }

        $qTasa->execute([intval($det['id_concepto'])]);
        $tasaIva = (float)$qTasa->fetchColumn();
        $noGravado += $subtotal;
        $claveTasa = number_format($tasaIva, 4, '.', '');
        $basesPorAlicuota[$claveTasa] = ($basesPorAlicuota[$claveTasa] ?? 0) + (int)round($subtotal * 100);
      }
    }

    foreach ($basesPorAlicuota as $tasa => $baseCentavos) {
      $t = (float)$tasa;
      if ($t > 0) {
        $iva += round($baseCentavos * $t / (100 + $t)) / 100;
      }
    }
    $brutoTotal = round($noGravado, 2);
    $iva = round($iva, 2);
    $noGravado = round($brutoTotal - $iva, 2);
    $gravado = $brutoTotal;
    $totalFv = $brutoTotal;

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
      header("Location: imprimirFacturaVentaARCA.php?id=" . (int)$idFactura);
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

    $proyectoDatos = [
      'id_empresa' => $facturaData['id_empresa'],
      'id_cliente' => $facturaData['id_cuenta_destino'],
    ];
    $_GET['id_proyecto'] = $facturaData['id_proyecto'];

    $preseleccionado = true;
  }

  Database::disconnect();

} elseif (!empty($_GET['id_proyecto'])) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $q = $pdo->prepare("SELECT p.id, p.nombre, p.id_cliente, p.solicitante,
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
$vista = 'venta';
include 'nuevaFactura.php';
