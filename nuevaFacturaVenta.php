<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$editMode = false;
$editId = null;
if (!empty($_GET['id'])) {
  $editId = (int)$_GET['id'];
  $editMode = true;
}

if (!empty($_POST)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->beginTransaction();

  try {
    $idEstado = !empty($_POST['btn_definitivo']) ? 3 : 2;

    $_POST['descripcion'] = '';
    $_POST['id_condicion_pago'] = empty($_POST['id_condicion_pago']) ? 0 : $_POST['id_condicion_pago'];

    if (isset($_POST['punto_venta']) || isset($_POST['nro_comprobante'])) {
      $_POST['numero'] = str_pad($_POST['punto_venta'] ?? '', 4, '0', STR_PAD_LEFT)
        . '-' . str_pad($_POST['nro_comprobante'] ?? '', 8, '0', STR_PAD_LEFT);
    }

    $isEditing = !empty($_POST['id_factura']);

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

      $q = $pdo->prepare("UPDATE facturas_venta SET descripcion=?, id_tipo_comprobante=?, id_letra_comprobante=?, id_proyecto=?, numero=?, id_cuenta_destino=?, id_empresa=?, fecha_emitida=?, fecha_enviada=?, id_condicion_pago=?, id_moneda=?, cotizacion=?, observaciones=?, id_estado=? WHERE id=?");
      $q->execute([
        $_POST['descripcion'],
        intval($_POST['id_tipo_comprobante']),
        intval($_POST['id_letra_comprobante']),
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
                    (descripcion, id_tipo_comprobante, id_letra_comprobante, id_proyecto,
                     numero, id_cuenta_destino, id_empresa, fecha_emitida, fecha_enviada,
                     id_condicion_pago, id_moneda, cotizacion, observaciones, id_usuario, id_estado)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $q->execute([
        $_POST['descripcion'],
        intval($_POST['id_tipo_comprobante']),
        intval($_POST['id_letra_comprobante']),
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
    $detalles = !empty($_POST['detalles_json']) ? json_decode($_POST['detalles_json'], true) : [];

    if (is_array($detalles)) {
      $qDet = $pdo->prepare("INSERT INTO facturas_venta_detalle
                                     (id_factura_venta, id_concepto_contable, cantidad, precio, subtotal)
                                     VALUES (?,?,?,?,?)");
      $qImp = $pdo->prepare("INSERT INTO facturas_venta_detalle_x_certificados_avance
                                     (id_factura_venta_detalle, id_certificado_avance) VALUES (?,?)");

      foreach ($detalles as $det) {
        $cant     = floatval($det['cantidad']);
        $precio   = floatval($det['precio']);
        $subtotal = $cant * $precio;

        $qDet->execute([$idFactura, intval($det['id_concepto']), $cant, $precio, $subtotal]);
        $idDetalle = $pdo->lastInsertId();

        if (!empty($det['imputaciones']) && is_array($det['imputaciones'])) {
          foreach ($det['imputaciones'] as $idImp) {
            $qImp->execute([$idDetalle, intval($idImp)]);
          }
        }

        $totalFv   += $subtotal;
        $parNoGrav  = $precio * $cant;
        $noGravado += $parNoGrav;
        $iva       += $parNoGrav * 0.21;
        $gravado   += $parNoGrav + ($parNoGrav * 0.21);
      }
    }

    // Retenciones
    $totalOtros = 0;
    $retenciones = !empty($_POST['retenciones_json']) ? json_decode($_POST['retenciones_json'], true) : [];

    if (is_array($retenciones)) {
      $qRegimenData = $pdo->prepare("SELECT regimen, codigo, articulo FROM regimenes_facturacion WHERE id = ?");
      $qRet = $pdo->prepare("INSERT INTO facturas_venta_retenciones
                                     (id_factura_venta, id_regimen_facturacion, regimen_text, codigo, articulo, monto, porcentaje, base_imponible) VALUES (?,?,?,?,?,?,?,?)");
      foreach ($retenciones as $ret) {
        $idRegimen = intval($ret['id_regimen']);
        $qRegimenData->execute([$idRegimen]);
        $regData = $qRegimenData->fetch(PDO::FETCH_ASSOC);
        $monto = floatval($ret['monto']);
        $porcentaje = floatval($ret['porcentaje'] ?? 0);
        $base = floatval($ret['base'] ?? 0);
        $qRet->execute([$idFactura, $idRegimen, $regData['regimen'] ?? null, $regData['codigo'] ?? null, $regData['articulo'] ?? null, $monto, $porcentaje, $base]);
        $totalOtros += $monto;
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

    $logMsg = $isEditing ? "Modificación de Factura de Venta ID #$idFactura" : "Nueva Factura de Venta ID #$idFactura";
    $ql = $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
                               VALUES (now(), ?, ?, 'Facturas de Venta', '')");
    $ql->execute([$_SESSION['user']['id'], $logMsg]);

    $pdo->commit();
    Database::disconnect();
    header("Location: listarFacturasVenta.php");
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
    while ($row = $qDet->fetch(PDO::FETCH_ASSOC)) {
      $qImp = $pdo->prepare("SELECT id_certificado_avance FROM facturas_venta_detalle_x_certificados_avance WHERE id_factura_venta_detalle = ?");
      $qImp->execute([$row['id']]);
      $certIdsDet = $qImp->fetchAll(PDO::FETCH_COLUMN);

      $imputacionesData = [];
      $imputacionesText = [];
      foreach ($certIdsDet as $cid) {
        $qC = $pdo->prepare("SELECT ca.id, cm.numero, ca.monto_total
                               FROM certificados_avances_cabecera ca
                               INNER JOIN certificados_maestros cm ON cm.id = ca.id_certificado_maestro
                               WHERE ca.id = ?");
        $qC->execute([$cid]);
        $c = $qC->fetch(PDO::FETCH_ASSOC);
        if ($c) {
          $imputacionesData[] = ['id_cd' => $c['id'], 'concepto_text' => 'Cert. #' . $c['numero']];
          $imputacionesText[] = 'Cert. #' . $c['numero'];
        }
      }

      $detallesExistentes[] = [
        'id' => $row['id'],
        'id_concepto' => $row['id_concepto_contable'],
        'concepto_text' => $row['concepto_text'],
        'descripcion' => '',
        'cantidad' => (float)$row['cantidad'],
        'precio' => (float)$row['precio'],
        'subtotal' => (float)$row['subtotal'],
        'porc_descuento' => 0,
        'imputaciones' => $certIdsDet,
        'imputaciones_data' => $imputacionesData,
        'imputaciones_text' => $imputacionesText
      ];
    }

    $qRet = $pdo->prepare("SELECT r.*, rf.regimen AS regimen_text, rf.porcentaje
                             FROM facturas_venta_retenciones r
                             INNER JOIN regimenes_facturacion rf ON rf.id = r.id_regimen_facturacion
                             WHERE r.id_factura_venta = ?");
    $qRet->execute([$editId]);
    while ($row = $qRet->fetch(PDO::FETCH_ASSOC)) {
      $porc = (float)$row['porcentaje'];
      $retencionesExistentes[] = [
        'id_regimen' => $row['id_regimen_facturacion'],
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
}

$errorMsg = $errorMsg ?? '';
$vista = 'venta';
include 'nuevaFactura.php';
