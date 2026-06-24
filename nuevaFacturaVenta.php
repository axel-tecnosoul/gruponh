<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

if (!empty($_POST) && ($_POST['_accion'] ?? '') === 'guardar_todo') {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->beginTransaction();

  try {
    $sql = "INSERT INTO facturas_venta
                  (descripcion, id_tipo_comprobante, id_letra_comprobante, id_proyecto,
                   numero, id_cuenta_destino, id_empresa, fecha_emitida, fecha_enviada,
                   id_condicion_pago, id_moneda, cotizacion, observaciones, id_usuario, id_estado)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)";
    $q = $pdo->prepare($sql);

    $idCondicionPago = !empty($_POST['id_condicion_pago']) ? intval($_POST['id_condicion_pago']) : null;
    $idProyecto      = !empty($_POST['id_proyecto'])       ? intval($_POST['id_proyecto'])       : null;
    $idEmpresa       = !empty($_POST['id_empresa'])        ? intval($_POST['id_empresa'])        : null;
    $idCuentaDest    = !empty($_POST['id_cuenta_destino']) ? intval($_POST['id_cuenta_destino']) : null;

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
      $_SESSION['user']['id']
    ]);
    $idNueva = $pdo->lastInsertId();

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

        $qDet->execute([$idNueva, intval($det['id_concepto']), $cant, $precio, $subtotal]);
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
      $qRet = $pdo->prepare("INSERT INTO facturas_venta_retenciones
                                     (id_factura_venta, id_regimen_facturacion, monto) VALUES (?,?,?)");
      foreach ($retenciones as $ret) {
        $monto = floatval($ret['monto']);
        $qRet->execute([$idNueva, intval($ret['id_regimen']), $monto]);
        $totalOtros += $monto;
      }
    }

    // Totales
    $qu = $pdo->prepare("UPDATE facturas_venta
                              SET subtotal_gravado=?, subtotal_no_gravado=?, iva=?, otros=?, total=?
                              WHERE id=?");
    $qu->execute([$gravado, $noGravado, $iva, $totalOtros, $totalFv + $totalOtros, $idNueva]);

    // Regímenes
    if (!empty($_POST['regimenes'])) {
      foreach ($_POST['regimenes'] as $idRegimen) {
        $qp = $pdo->prepare("SELECT porcentaje FROM regimenes_facturacion WHERE id = ?");
        $qp->execute([$idRegimen]);
        $reg = $qp->fetch(PDO::FETCH_ASSOC);
        $porcentaje = $reg ? $reg['porcentaje'] : 0;
        $qi = $pdo->prepare("INSERT INTO facturas_venta_otros (id_factura_venta, id_regimen, porcentaje) VALUES (?,?,?)");
        $qi->execute([$idNueva, $idRegimen, $porcentaje]);
      }
    }

    // Certificados
    if (!empty($_POST['certificados'])) {
      foreach ($_POST['certificados'] as $idCert) {
        $idCert = intval($idCert);
        if ($idCert > 0) {
          $qc = $pdo->prepare("UPDATE certificados_avances_detalle SET id_comprobante = ? WHERE id_certificado_avance = ?");
          $qc->execute([$idNueva, $idCert]);
        }
      }
    }

    $ql = $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
                               VALUES (now(), ?, ?, 'Facturas de Venta', '')");
    $ql->execute([$_SESSION['user']['id'], "Nueva Factura de Venta ID #$idNueva"]);

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

// Datos pre-cargados desde proyecto
$proyectoDatos = [];
if (!empty($_GET['id_proyecto'])) {
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
}
$preseleccionado = !empty($proyectoDatos);

// Labels para campos preseleccionados
$empresaLabel = '';
$clienteLabel = '';
$proyectoLabel = '';
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

$certIds = [];
if (!empty($_GET['certificados'])) {
  $certIds = array_filter(array_map('intval', (array)$_GET['certificados']));
}

$vista = 'venta';
include 'nuevaFactura.php';
