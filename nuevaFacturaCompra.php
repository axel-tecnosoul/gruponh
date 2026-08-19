<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
    require 'database.php';
    require_once("PHPMailer/class.phpmailer.php");
    require_once("PHPMailer/class.smtp.php");

$editMode = false;
$editId = null;
if (!empty($_GET['id'])) {
  $editId = (int)$_GET['id'];
  $editMode = true;
}

function getOcIdsFromRequest() {
  if (!empty($_GET['oc'])) {
    $parts = explode(',', $_GET['oc']);
    $ids = array_map('intval', $parts);
    return array_filter($ids, function($id) { return $id > 0; });
  }
  return [];
}

$ocIds = getOcIdsFromRequest();
$ocPreseleccionada = !empty($ocIds) || $editMode;
$disabledAttr = $ocPreseleccionada ? 'disabled' : '';

$idEmpresa = 0;
$idProveedor = 0;
$idFormaPago = 0;
$idMoneda = 0;
$idComputo = 0;
$idProyecto = 0;

// Edit mode data
$facturaData = null;
$detallesExistentes = [];
$retencionesExistentes = [];
$ocVinculadas = [];

if (!empty($_POST)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->beginTransaction();

  try {
    $idEstado = !empty($_POST['btn_definitivo']) ? 3 : 2;

    $_POST['id_condicion_pago'] = empty($_POST['id_condicion_pago']) ? 0 : $_POST['id_condicion_pago'];

    // Concatenar punto_venta + nro_comprobante en numero
    if (isset($_POST['punto_venta']) || isset($_POST['nro_comprobante'])) {
      $_POST['numero'] = str_pad($_POST['punto_venta'] ?? '', 4, '0', STR_PAD_LEFT)
        . '-' . str_pad($_POST['nro_comprobante'] ?? '', 8, '0', STR_PAD_LEFT);
    }

    $ocPostIds = [];
    if (!empty($_POST['id_orden_compra'])) {
      $ocPostIds = is_array($_POST['id_orden_compra'])
        ? array_map('intval', $_POST['id_orden_compra'])
        : [intval($_POST['id_orden_compra'])];
    }

    $isEditing = !empty($_POST['id_factura']);
    require_once('funciones.php');

    if (empty($_POST['id_condicion_pago'])) {
      $qFallback = $pdo->query("SELECT id FROM formas_pago LIMIT 1");
      $_POST['id_condicion_pago'] = (int)$qFallback->fetchColumn();
    }

    if ($isEditing) {
      $idFactura = (int)$_POST['id_factura'];

      // Verificar que la factura siga siendo editable
      $qCheckEstado = $pdo->prepare("SELECT id_estado, exportada FROM facturas_compra WHERE id = ?");
      $qCheckEstado->execute([$idFactura]);
      $estadoRow = $qCheckEstado->fetch(PDO::FETCH_ASSOC);
      if (!$estadoRow) throw new Exception("Factura no encontrada.");
      if ($estadoRow['id_estado'] == 3 || $estadoRow['exportada'] == 1) {
        $motivo = $estadoRow['exportada'] == 1 ? 'exportada' : 'definitiva';
        throw new Exception("Esta factura ya fue " . $motivo . " y no puede editarse.");
      }

      // UPDATE header
      $q = $pdo->prepare("UPDATE facturas_compra SET id_tipo_comprobante=?, id_letra_comprobante=?, numero=?, id_cuenta_origen=?, id_empresa=?, fecha_emitida=?, fecha_recibida=?, id_condicion_pago=?, id_moneda=?, cotizacion=?, observaciones=?, id_estado=? WHERE id=?");
      $q->execute([$_POST['id_tipo_comprobante'],$_POST['id_letra_comprobante'],$_POST['numero'],$_POST['id_cuenta_origen'],$_POST['id_empresa'],$_POST['fecha_emitida'],$_POST['fecha_recibida'],$_POST['id_condicion_pago'],$_POST['id_moneda'],$_POST['cotizacion'],$_POST['observaciones'],$idEstado,$idFactura]);

      // OC diff logic
      $qActuales = $pdo->prepare("SELECT id, id_compra, estado_anterior FROM facturas_compra_x_compras WHERE id_factura_compra = ?");
      $qActuales->execute([$idFactura]);
      $actuales = [];
      while ($row = $qActuales->fetch(PDO::FETCH_ASSOC)) {
        $actuales[$row['id_compra']] = $row;
      }
      $idsActuales = array_keys($actuales);

      $aAgregar = array_diff($ocPostIds, $idsActuales);
      $aQuitar = array_diff($idsActuales, $ocPostIds);

      $qInsertPivote = $pdo->prepare("INSERT INTO facturas_compra_x_compras (id_factura_compra, id_compra, estado_anterior) VALUES (?,?,?)");
      $qDeletePivote = $pdo->prepare("DELETE FROM facturas_compra_x_compras WHERE id_factura_compra = ? AND id_compra = ?");
      $qSelEstado = $pdo->prepare("SELECT id_estado_compra FROM compras WHERE id = ?");
      $qUpdCompra = $pdo->prepare("UPDATE compras SET id_estado_compra = ? WHERE id = ?");
      $qUpdPedido = $pdo->prepare("UPDATE pedidos SET id_estado = 4 WHERE id_estado = 3 AND id = ?");
      $qSelPedido = $pdo->prepare("SELECT id_pedido FROM compras WHERE id = ?");

      foreach ($aAgregar as $ocId) {
        $qSelEstado->execute([$ocId]);
        $estadoAnterior = $qSelEstado->fetchColumn();
        $estadoAnterior = $estadoAnterior !== false ? (int)$estadoAnterior : null;
        $qInsertPivote->execute([$idFactura, $ocId, $estadoAnterior]);
        $qSelPedido->execute([$ocId]);
        $pedidoData = $qSelPedido->fetch(PDO::FETCH_ASSOC);
        if (!empty($pedidoData['id_pedido'])) {
          $qUpdPedido->execute([$pedidoData['id_pedido']]);
        }
        $sqlAllItems = "SELECT DISTINCT pd.id FROM pedidos_detalle pd INNER JOIN compras_detalle cd ON pd.id_pedido = (SELECT id_pedido FROM compras WHERE id = ?) AND pd.id_material = cd.id_material INNER JOIN compras c ON c.id = cd.id_compra WHERE cd.id_compra = ?";
        $qAllItems = $pdo->prepare($sqlAllItems);
        $qAllItems->execute([$ocId, $ocId]);
        while ($item = $qAllItems->fetch(PDO::FETCH_ASSOC)) {
          actualizarEstadoPedidoDetalle($pdo, $item['id']);
        }
      }

      foreach ($aQuitar as $ocId) {
        if (isset($actuales[$ocId])) {
          $estadoAnterior = $actuales[$ocId]['estado_anterior'];
          if ($estadoAnterior !== null) {
            $qUpdCompra->execute([$estadoAnterior, $ocId]);
          }
          $qDeletePivote->execute([$idFactura, $ocId]);
        }
      }

      // Delete existing details + retenciones before re-insert
      $pdo->prepare("DELETE FROM facturas_compra_detalle_x_compras_detalle WHERE id_factura_compra_detalle IN (SELECT id FROM facturas_compra_detalle WHERE id_factura_compra = ?)")->execute([$idFactura]);
      $pdo->prepare("DELETE FROM facturas_compra_detalle WHERE id_factura_compra = ?")->execute([$idFactura]);
      $pdo->prepare("DELETE FROM facturas_compra_retenciones WHERE id_factura_compra = ?")->execute([$idFactura]);

    } else {
      // CREATE mode
      $q = $pdo->prepare("INSERT INTO facturas_compra(id_tipo_comprobante, id_letra_comprobante, numero, id_cuenta_origen, id_empresa, fecha_emitida, fecha_recibida, id_condicion_pago, id_moneda, cotizacion, observaciones, id_usuario, id_estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $q->execute([$_POST['id_tipo_comprobante'],$_POST['id_letra_comprobante'],$_POST['numero'],$_POST['id_cuenta_origen'],$_POST['id_empresa'],$_POST['fecha_emitida'],$_POST['fecha_recibida'],$_POST['id_condicion_pago'],$_POST['id_moneda'],$_POST['cotizacion'],$_POST['observaciones'],$_SESSION['user']['id'],$idEstado]);
      $idFactura = $pdo->lastInsertId();

      // Asociar cada OC a la factura
      $qPivote = $pdo->prepare("INSERT INTO facturas_compra_x_compras (id_factura_compra, id_compra, estado_anterior) VALUES (?,?,?)");
      $qSelEstado = $pdo->prepare("SELECT id_estado_compra FROM compras WHERE id = ?");
      $qUpdCompra = $pdo->prepare("UPDATE compras SET id_estado_compra = ? WHERE id = ?");
      $qUpdPedido = $pdo->prepare("UPDATE pedidos SET id_estado = 4 WHERE id_estado = 3 AND id = ?");
      $qSelPedido = $pdo->prepare("SELECT id_pedido FROM compras WHERE id = ?");

      foreach ($ocPostIds as $ocId) {
        $qSelEstado->execute([$ocId]);
        $estadoAnterior = $qSelEstado->fetchColumn();
        $estadoAnterior = $estadoAnterior !== false ? (int)$estadoAnterior : null;
        $qPivote->execute([$idFactura, $ocId, $estadoAnterior]);
        $qSelPedido->execute([$ocId]);
        $pedidoData = $qSelPedido->fetch(PDO::FETCH_ASSOC);
        if (!empty($pedidoData['id_pedido'])) {
          $qUpdPedido->execute([$pedidoData['id_pedido']]);
        }
        $sqlAllItems = "SELECT DISTINCT pd.id FROM pedidos_detalle pd INNER JOIN compras_detalle cd ON pd.id_pedido = (SELECT id_pedido FROM compras WHERE id = ?) AND pd.id_material = cd.id_material INNER JOIN compras c ON c.id = cd.id_compra WHERE cd.id_compra = ?";
        $qAllItems = $pdo->prepare($sqlAllItems);
        $qAllItems->execute([$ocId, $ocId]);
        while ($item = $qAllItems->fetch(PDO::FETCH_ASSOC)) {
          actualizarEstadoPedidoDetalle($pdo, $item['id']);
        }
      }
    }

    // Procesar detalles_json (común)
    $detallesProcesados = false;
    $detalles = !empty($_POST['detalles_json']) ? json_decode($_POST['detalles_json'], true) : [];
    $total = 0;
    $noGravado = 0;
    $iva = 0;
    $gravado = 0;
    $excesosEmail = [];

    if (is_array($detalles) && count($detalles) > 0) {
      $detallesProcesados = true;
      $qDet = $pdo->prepare("INSERT INTO facturas_compra_detalle (id_factura_compra, id_concepto_contable, descripcion, porc_descuento, cantidad, precio, subtotal) VALUES (?,?,?,?,?,?,?)");
      $qImp = $pdo->prepare("INSERT INTO facturas_compra_detalle_x_compras_detalle (id_factura_compra_detalle, id_compra_detalle, cantidad, precio) VALUES (?,?,?,?)");

      // Validate: collect all compra_detalle IDs from all details to check duplicates
      $allImpIds = [];
      foreach ($detalles as $det) {
        $imps = !empty($det['imputaciones_data']) ? $det['imputaciones_data'] : [];
        foreach ($imps as $imp) {
          $cid = (int)$imp['id_cd'];
          if (in_array($cid, $allImpIds)) {
            // Duplicate found — error
            echo '<script>alert("No se puede usar la misma imputacion 2 veces en la misma factura."); window.history.back();</script>';
            exit;
          }
          $allImpIds[] = $cid;
        }
      }

      foreach ($detalles as $det) {
        $porcDesc = !empty($det['porc_descuento']) ? floatval($det['porc_descuento']) : 0;
        $descripcion = !empty($det['descripcion']) ? $det['descripcion'] : '';

        $imps = !empty($det['imputaciones_data']) ? $det['imputaciones_data'] : [];
        $totalCant = 0;
        $totalSub = 0;

        if (!empty($imps)) {
          foreach ($imps as $imp) {
            $cantImp = floatval($imp['cantidad'] ?? 0);
            $precImp = floatval($imp['precio'] ?? 0);
            $totalCant += $cantImp;
            $totalSub += $cantImp * $precImp;
          }
        } else {
          $totalCant = floatval($det['cantidad'] ?? 0);
          $precio = floatval($det['precio'] ?? 0);
          $totalSub = $totalCant * $precio;
        }

        $subtotal = $totalSub * (1 - $porcDesc / 100);

        // Validate quantities against remaining (for compras)
        if (!empty($imps)) {
          foreach ($imps as $imp) {
            $cid = (int)$imp['id_cd'];
            $cantImp = floatval($imp['cantidad'] ?? 0);
            $sqlRest = "SELECT GREATEST(0, cd.cantidad - COALESCE(
              (SELECT SUM(fcdx2.cantidad) FROM facturas_compra_detalle_x_compras_detalle fcdx2
               INNER JOIN facturas_compra_detalle fcd2 ON fcd2.id = fcdx2.id_factura_compra_detalle
               WHERE fcdx2.id_compra_detalle = cd.id), 0)) as restante
              FROM compras_detalle cd WHERE cd.id = ?";
            $qRest = $pdo->prepare($sqlRest);
            $qRest->execute([$cid]);
            $rest = (float)$qRest->fetchColumn();
            if ($cantImp > $rest && $rest >= 0) {
              $excesosEmail[] = [
                'id_cd' => $cid,
                'cant_solicitada' => $cantImp,
                'restante' => $rest
              ];
            }
          }
        }

        // IVA from concepto contable
        $idCC = intval($det['id_concepto'] ?? 0);
        $tasaIva = 0.21;
        if ($idCC) {
          $qIVA = $pdo->prepare("SELECT COALESCE(ti.tasa, 21) / 100 FROM conceptos_contables cc LEFT JOIN tipos_iva ti ON ti.id = cc.id_alicuota_iva WHERE cc.id = ?");
          $qIVA->execute([$idCC]);
          $tasaIva = (float)($qIVA->fetchColumn() ?: 0.21);
        }

        $qDet->execute([$idFactura, $idCC, $descripcion, $porcDesc, $totalCant, 0, $subtotal]);
        $idDetalle = $pdo->lastInsertId();

        if (!empty($imps)) {
          foreach ($imps as $imp) {
            $cid = (int)$imp['id_cd'];
            $cantImp = floatval($imp['cantidad'] ?? 0);
            $precImp = floatval($imp['precio'] ?? 0);
            if ($cantImp > 0) {
              $qImp->execute([$idDetalle, $cid, $cantImp, $precImp]);
            }
          }
        }

        $total += $subtotal;
        $noGravadoParcial = $subtotal;
        $noGravado += $noGravadoParcial;
        $iva += $noGravadoParcial * $tasaIva;
        $gravado += $noGravadoParcial + ($noGravadoParcial * $tasaIva);
      }
    }

    // Send email for excess
    if (!empty($excesosEmail)) {
      $qNum = $pdo->prepare("SELECT fc.numero, cu.razon_social FROM facturas_compra fc INNER JOIN cuentas cu ON cu.id = fc.id_cuenta_origen WHERE fc.id = ?");
      $qNum->execute([$idFactura]);
      $fc = $qNum->fetch(PDO::FETCH_ASSOC);

      $detalleExceso = '';
      foreach ($excesosEmail as $ex) {
        $qD = $pdo->prepare("SELECT m.concepto, cd.cantidad FROM compras_detalle cd INNER JOIN materiales m ON m.id = cd.id_material WHERE cd.id = ?");
        $qD->execute([$ex['id_cd']]);
        $cd = $qD->fetch(PDO::FETCH_ASSOC);
        $detalleExceso .= "- {$cd['concepto']}: solicitado {$ex['cant_solicitada']}, pendiente {$ex['restante']} de {$cd['cantidad']}\n";
      }

      $asunto = "Alerta: Exceso en Factura #" . ($fc['numero'] ?? $idFactura);
      $cuerpo = "Exceso de cantidad al imputar en Factura de Compra.\n\n"
        . "Factura: #" . ($fc['numero'] ?? $idFactura) . "\n"
        . "Proveedor: " . ($fc['razon_social'] ?? 'N/A') . "\n"
        . "Usuario: " . ($_SESSION['user']['usuario'] ?? 'N/A') . "\n\n"
        . "Excesos:\n" . $detalleExceso;

      $detalleNotif = "Factura #" . ($fc['numero'] ?? $idFactura) . " - " . ($fc['razon_social'] ?? 'N/A');
      crearNotificacion($pdo, 22, $idFactura, $detalleNotif, $asunto, $cuerpo);
    }

    // Procesar retenciones_json (común)
    $totalOtros = 0;
    $retenciones = !empty($_POST['retenciones_json']) ? json_decode($_POST['retenciones_json'], true) : [];
    if (is_array($retenciones) && count($retenciones) > 0) {
      $qRegimenData = $pdo->prepare("SELECT regimen, codigo, articulo, signo_cpr FROM regimenes_facturacion WHERE id = ?");
      $qRet = $pdo->prepare("INSERT INTO facturas_compra_retenciones (id_factura_compra, regimen_text, codigo, articulo, monto, porcentaje, base_imponible) VALUES (?,?,?,?,?,?,?)");
      foreach ($retenciones as $ret) {
        $idRegimen = intval($ret['id_regimen']);
        $qRegimenData->execute([$idRegimen]);
        $regData = $qRegimenData->fetch(PDO::FETCH_ASSOC);
        $monto = floatval($ret['monto']);
        $porcentaje = floatval($ret['porcentaje'] ?? 0);
        $base = floatval($ret['base'] ?? 0);
        if (isset($regData['signo_cpr']) && (int)$regData['signo_cpr'] === 2) {
          $monto = -abs($monto);
        } else {
          $monto = abs($monto);
        }
        $qRet->execute([$idFactura, $regData['regimen'] ?? null, $regData['codigo'] ?? null, $regData['articulo'] ?? null, $monto, $porcentaje, $base]);
        $totalOtros += $monto;
      }
    }

    // Verificar si cada OC quedó completamente facturada
    foreach ($ocPostIds as $ocId) {
        $sqlOc = "SELECT COALESCE(SUM(cd.cantidad), 0) as total_oc,
            COALESCE((SELECT SUM(fcdx.cantidad)
             FROM facturas_compra_detalle_x_compras_detalle fcdx
             INNER JOIN compras_detalle cd2 ON cd2.id = fcdx.id_compra_detalle
             WHERE cd2.id_compra = ?), 0) as total_fact
            FROM compras_detalle cd WHERE cd.id_compra = ?";
        $qOc = $pdo->prepare($sqlOc);
        $qOc->execute([$ocId, $ocId]);
        $rowOc = $qOc->fetch(PDO::FETCH_ASSOC);
        if ($rowOc && (float)$rowOc['total_oc'] <= (float)$rowOc['total_fact']) {
            $qUpdCompra->execute([9, $ocId]);
        }
    }

    // Actualizar totales
    if ($detallesProcesados || $totalOtros != 0) {
      $qu = $pdo->prepare("UPDATE facturas_compra SET subtotal_gravado=?, subtotal_no_gravado=?, otros=?, iva=?, total=? WHERE id=?");
      $qu->execute([$gravado, $noGravado, $totalOtros, $iva, $total + $totalOtros, $idFactura]);
    }

    $logMsg = $isEditing ? "Modificación de Factura de Compra ID #$idFactura" : "Nueva Factura de Compra ID #$idFactura";
    $q = $pdo->prepare("INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,'$logMsg','Facturas de Compra','')");
    $q->execute(array($_SESSION['user']['id']));

    $pdo->commit();
    Database::disconnect();

    header("Location: listarFacturasCompra.php");
    exit;
  } catch (Exception $e) {
    $pdo->rollBack();
    Database::disconnect();
    $errorMsg = "Error al guardar: " . $e->getMessage();
  }
} else {
  if ($editMode && $editId) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $q = $pdo->prepare("SELECT * FROM facturas_compra WHERE id = ?");
    $q->execute([$editId]);
    $facturaData = $q->fetch(PDO::FETCH_ASSOC);

    if (!empty($facturaData['id_estado']) && ($facturaData['id_estado'] == 3 || $facturaData['exportada'] == 1)) {
      $msg = $facturaData['exportada'] == 1 ? 'exportada' : 'definitiva';
      echo '<script>alert("Esta factura ya fue ' . $msg . ' y no puede editarse");</script>';
      echo '<div style="text-align:center;padding:40px;color:red;font-size:18px;">Esta factura ya fue ' . $msg . ' y no puede editarse.<br><a href="listarFacturasCompra.php">Volver al listado</a></div>';
      exit;
    }

    if ($facturaData) {
      $qOC = $pdo->prepare("SELECT fcxc.id_compra, c.nro_oc FROM facturas_compra_x_compras fcxc INNER JOIN compras c ON c.id = fcxc.id_compra WHERE fcxc.id_factura_compra = ?");
      $qOC->execute([$editId]);
      $ocVinculadas = $qOC->fetchAll(PDO::FETCH_ASSOC);
      $ocVinculadasIds = array_column($ocVinculadas, 'id_compra');

      $ocIds = $ocVinculadasIds;
      $ocPreseleccionada = true;

      $ocTotal = 0;
      $qOcTotal = $pdo->prepare("SELECT total FROM compras WHERE id = ?");
      foreach ($ocIds as $ocId) {
        $qOcTotal->execute([$ocId]);
        $ocTotal += (float)($qOcTotal->fetchColumn() ?: 0);
      }

      $idEmpresa = (int)$facturaData['id_empresa'];
      $idProveedor = (int)$facturaData['id_cuenta_origen'];
      $idFormaPago = (int)$facturaData['id_condicion_pago'];
      $idMoneda = (int)$facturaData['id_moneda'];

      $imputacionesOC = [];
      foreach ($ocIds as $ocId) {
        $sqlImp = "SELECT d.id, d.id_compra, m.concepto, d.cantidad as oc_cantidad, d.precio as oc_precio,
          GREATEST(0, d.cantidad - COALESCE(
            (SELECT SUM(fcdx2.cantidad) FROM facturas_compra_detalle_x_compras_detalle fcdx2
             INNER JOIN facturas_compra_detalle fcd2 ON fcd2.id = fcdx2.id_factura_compra_detalle
             WHERE fcdx2.id_compra_detalle = d.id), 0
          )) as restante
          FROM compras_detalle d INNER JOIN materiales m ON m.id = d.id_material WHERE d.id_compra = ?";
        $qImp = $pdo->prepare($sqlImp);
        $qImp->execute([$ocId]);
        $imputacionesOC = array_merge($imputacionesOC, $qImp->fetchAll(PDO::FETCH_ASSOC));
      }

      $restanteOC = [];
      foreach ($imputacionesOC as $ioc) {
        $restanteOC[(int)$ioc['id']] = (float)$ioc['restante'];
      }

      $qDet = $pdo->prepare("SELECT d.*, cc.descripcion AS concepto_text FROM facturas_compra_detalle d INNER JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable WHERE d.id_factura_compra = ?");
      $qDet->execute([$editId]);
      while ($row = $qDet->fetch(PDO::FETCH_ASSOC)) {
        $qImp = $pdo->prepare("SELECT fcdx.id_compra_detalle, fcdx.cantidad, fcdx.precio, m.concepto, c.nro_oc
          FROM facturas_compra_detalle_x_compras_detalle fcdx
          INNER JOIN compras_detalle cd ON cd.id = fcdx.id_compra_detalle
          INNER JOIN materiales m ON m.id = cd.id_material
          INNER JOIN compras c ON c.id = cd.id_compra
          WHERE fcdx.id_factura_compra_detalle = ?");
        $qImp->execute([$row['id']]);
        $impRows = $qImp->fetchAll(PDO::FETCH_ASSOC);
        $imputacionesIds = array_column($impRows, 'id_compra_detalle');
        $imputacionesData = [];
        foreach ($impRows as $ir) {
          $imputacionesData[] = [
            'id_cd' => (int)$ir['id_compra_detalle'],
            'concepto_text' => 'OC #' . $ir['nro_oc'] . ' - ' . ($ir['concepto'] ?? ''),
            'cantidad' => (float)($ir['cantidad'] ?? $row['cantidad']),
            'precio' => (float)($ir['precio'] ?? $row['precio']),
            'restante' => $restanteOC[(int)$ir['id_compra_detalle']] ?? 0
          ];
        }

        $detallesExistentes[] = [
          'id' => $row['id'],
          'id_concepto' => $row['id_concepto_contable'],
          'concepto_text' => $row['concepto_text'],
          'descripcion' => $row['descripcion'] ?? '',
          'cantidad' => (float)$row['cantidad'],
          'precio' => (float)$row['precio'],
          'porc_descuento' => (float)($row['porc_descuento'] ?? 0),
          'imputaciones' => $imputacionesIds,
          'imputaciones_data' => $imputacionesData,
          'subtotal' => (float)$row['subtotal']
        ];
      }

      $qRet = $pdo->prepare("SELECT r.*,
                             (SELECT rf.id FROM regimenes_facturacion rf WHERE rf.regimen = r.regimen_text AND rf.anulado = 0 ORDER BY rf.id LIMIT 1) AS id_regimen
                             FROM facturas_compra_retenciones r
                             WHERE r.id_factura_compra = ?");
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
    }

    Database::disconnect();
  } elseif (!empty($ocIds)) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $idProveedor = null;
    $idFormaPago = null;
    $idMoneda = null;
    $ocTotal = 0;
    $imputacionesOC = [];

    foreach ($ocIds as $ocId) {
      $sql = "SELECT c.id_cuenta_proveedor, c.id_forma_pago, c.id_moneda, c.total, p.id_computo, p.id_proyecto FROM compras c INNER JOIN pedidos p ON p.id = c.id_pedido WHERE c.id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$ocId]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      if (empty($data)) continue;

      if ($idProveedor === null) {
        $idProveedor = $data['id_cuenta_proveedor'];
        $idFormaPago = $data['id_forma_pago'];
        $idMoneda = $data['id_moneda'];
        $idComputo = $data['id_computo'];
        $idProyecto = $data['id_proyecto'];
      } elseif ($data['id_cuenta_proveedor'] != $idProveedor) {
        continue;
      }

      $ocTotal += !empty($data['total']) ? (float)$data['total'] : 0;

      $sqlImp = "SELECT d.id, d.id_compra, m.concepto, d.cantidad as oc_cantidad, d.precio as oc_precio,
        GREATEST(0, d.cantidad - COALESCE(
          (SELECT SUM(fcdx2.cantidad) FROM facturas_compra_detalle_x_compras_detalle fcdx2
           INNER JOIN facturas_compra_detalle fcd2 ON fcd2.id = fcdx2.id_factura_compra_detalle
           WHERE fcdx2.id_compra_detalle = d.id), 0
        )) as restante
        FROM compras_detalle d INNER JOIN materiales m ON m.id = d.id_material WHERE d.id_compra = ?";
      $qImp = $pdo->prepare($sqlImp);
      $qImp->execute([$ocId]);
      $imputacionesOC = array_merge($imputacionesOC, $qImp->fetchAll(PDO::FETCH_ASSOC));
    }

    if (!empty($idComputo)) {
      $q = $pdo->prepare("SELECT s.id_empresa FROM computos c INNER JOIN tareas t ON t.id = c.id_tarea INNER JOIN proyectos pr ON pr.id = t.id_proyecto INNER JOIN sitios s ON s.id = pr.id_sitio WHERE c.id = ?");
      $q->execute([$idComputo]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $idEmpresa = (int)$data['id_empresa'];
    } elseif (!empty($idProyecto)) {
      $q = $pdo->prepare("SELECT s.id_empresa FROM proyectos pr INNER JOIN sitios s ON s.id = pr.id_sitio WHERE pr.id = ?");
      $q->execute([$idProyecto]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $idEmpresa = (int)$data['id_empresa'];
    }

    Database::disconnect();
  }
}

// Variables que necesita la vista unificada
$preseleccionado = false;
$proyectoDatos = [];
$certIds = [];
$regimenesSeleccionados = [];
$errorMsg = $errorMsg ?? '';

$ocLabel = '';
$ocLabels = [];
$empresaLabel = '';
$proveedorLabel = '';
$formaPagoLabel = '';
$monedaLabel = '';
$monedaEsDolar = false;
$ocTotalFormatted = '';

if ($ocPreseleccionada && !empty($ocIds)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $placeholders = implode(',', array_fill(0, count($ocIds), '?'));
  $q = $pdo->prepare("SELECT id, nro_oc FROM compras WHERE id IN ($placeholders)");
  $q->execute(array_values($ocIds));
  $ocLabels = $q->fetchAll(PDO::FETCH_ASSOC);

  if ($idEmpresa) {
    $q = $pdo->prepare("SELECT empresa FROM empresas WHERE id = ?");
    $q->execute([$idEmpresa]);
    $empresaLabel = $q->fetchColumn() ?: '';
  }
  if ($idProveedor) {
    $q = $pdo->prepare("SELECT razon_social FROM cuentas WHERE id = ?");
    $q->execute([$idProveedor]);
    $proveedorLabel = $q->fetchColumn() ?: '';
  }
  if ($idFormaPago) {
    $q = $pdo->prepare("SELECT forma_pago FROM formas_pago WHERE id = ?");
    $q->execute([$idFormaPago]);
    $formaPagoLabel = $q->fetchColumn() ?: '';
  }
  if ($idMoneda) {
    $q = $pdo->prepare("SELECT moneda FROM monedas WHERE id = ?");
    $q->execute([$idMoneda]);
    $monedaLabel = $q->fetchColumn() ?: '';
    $monedaEsDolar = (stripos($monedaLabel, 'dolar') !== false || stripos($monedaLabel, 'dólar') !== false || stripos($monedaLabel, 'usd') !== false || stripos($monedaLabel, 'u$d') !== false || stripos($monedaLabel, 'u$s') !== false);
  }
  if (!empty($ocTotal) && $monedaLabel) {
    $ocTotalFormatted = $monedaLabel . ' ' . number_format($ocTotal, 2, ',', '.');
  }

  Database::disconnect();
}


$vista = 'compra';
include 'nuevaFactura.php';
