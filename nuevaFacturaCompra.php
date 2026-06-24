<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$idEmpresa = 0;
$idProveedor = 0;
$idFormaPago = 0;
$idMoneda = 0;
$ocPreseleccionada = !empty($_GET['oc']);
$disabledAttr = $ocPreseleccionada ? 'disabled' : '';

if (!empty($_POST)) {
   
	// insert data
	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->beginTransaction();

	try {
		$sql = "INSERT INTO `facturas_compra`(`descripcion`, `id_tipo_comprobante`, `id_letra_comprobante`, `id_orden_compra`, `numero`, `id_cuenta_origen`, `id_empresa`, `fecha_emitida`, `fecha_recibida`, `id_condicion_pago`, `id_moneda`, `cotizacion`, `observaciones`, `id_usuario`, `id_estado`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['descripcion'],$_POST['id_tipo_comprobante'],$_POST['id_letra_comprobante'],$_POST['id_orden_compra'],$_POST['numero'],$_POST['id_cuenta_origen'],$_POST['id_empresa'],$_POST['fecha_emitida'],$_POST['fecha_recibida'],$_POST['id_condicion_pago'],$_POST['id_moneda'],$_POST['cotizacion'],$_POST['observaciones'],$_SESSION['user']['id'],$_POST['id_estado']]);
		$idFactura = $pdo->lastInsertId();
		
		$sql = "SELECT `id_pedido` FROM `compras` WHERE id = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['id_orden_compra']]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$sql = "update pedidos set id_estado = 4 where id_estado = 3 and id  = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$data['id_pedido']]);
		$sql = "update compras set id_estado_compra = 9 where id  = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['id_orden_compra']]);

		// Actualizar estados de todos los pedidos_detalle afectados por el cambio de estado de compra
		$sqlAllItems = "SELECT DISTINCT pd.id FROM pedidos_detalle pd 
					   INNER JOIN compras_detalle cd ON pd.id_pedido = (SELECT id_pedido FROM compras WHERE id = ?) AND pd.id_material = cd.id_material 
					   INNER JOIN compras c ON c.id = cd.id_compra WHERE cd.id_compra = ?";
		$qAllItems = $pdo->prepare($sqlAllItems);
		$qAllItems->execute([$_POST['id_orden_compra'], $_POST['id_orden_compra']]);
		require_once('funciones.php');
		while ($item = $qAllItems->fetch(PDO::FETCH_ASSOC)) {
			actualizarEstadoPedidoDetalle($pdo, $item['id']);
		}

		// Procesar detalles_json
		$detallesProcesados = false;
		$detalles = !empty($_POST['detalles_json']) ? json_decode($_POST['detalles_json'], true) : [];
		if (is_array($detalles) && count($detalles) > 0) {
			$detallesProcesados = true;
			$qDet = $pdo->prepare("INSERT INTO facturas_compra_detalle (id_factura_compra, id_concepto_contable, cantidad, precio, subtotal) VALUES (?,?,?,?,?)");
			$qImp = $pdo->prepare("INSERT INTO facturas_compra_detalle_x_compras_detalle (id_factura_compra_detalle, id_compra_detalle) VALUES (?,?)");

			$total = 0;
			$noGravado = 0;
			$iva = 0;
			$gravado = 0;

			foreach ($detalles as $det) {
				$cant = floatval($det['cantidad']);
				$precio = floatval($det['precio']);
				$subtotal = $cant * $precio;
				$qDet->execute([$idFactura, intval($det['id_concepto']), $cant, $precio, $subtotal]);
				$idDetalle = $pdo->lastInsertId();

				if (!empty($det['imputaciones']) && is_array($det['imputaciones'])) {
					foreach ($det['imputaciones'] as $idImp) {
						$qImp->execute([$idDetalle, intval($idImp)]);
					}
				}

				$total += $subtotal;
				$noGravadoParcial = $precio * $cant;
				$noGravado += $noGravadoParcial;
				$iva += $noGravado * 0.21;
				$gravado += $noGravado + $iva;
			}
		}

		// Procesar retenciones_json
		$totalOtros = 0;
		$retenciones = !empty($_POST['retenciones_json']) ? json_decode($_POST['retenciones_json'], true) : [];
		if (is_array($retenciones) && count($retenciones) > 0) {
			$qRet = $pdo->prepare("INSERT INTO facturas_compra_retenciones (id_factura_compra, id_regimen_facturacion, monto) VALUES (?,?,?)");
			foreach ($retenciones as $ret) {
				$monto = floatval($ret['monto']);
				$qRet->execute([$idFactura, intval($ret['id_regimen']), $monto]);
				$totalOtros += $monto;
			}
		}

		// Actualizar totales si se procesaron detalles o retenciones
		if ($detallesProcesados || $totalOtros > 0) {
			$qu = $pdo->prepare("UPDATE facturas_compra SET subtotal_gravado=?, subtotal_no_gravado=?, otros=?, iva=?, total=? WHERE id=?");
			$qu->execute([$gravado, $noGravado, $totalOtros, $iva, $total + $totalOtros, $idFactura]);
		}

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva Factura de Compra ID #$idFactura','Facturas de Compra','')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

		$pdo->commit();
		Database::disconnect();

		if ($detallesProcesados) {
			header("Location: listarFacturasCompra.php");
		} else {
			header("Location: nuevoDetalleFacturaCompra.php?id=".$idFactura);
		}
		exit;
	} catch (Exception $e) {
		$pdo->rollBack();
		Database::disconnect();
		$errorMsg = "Error al guardar: " . $e->getMessage();
	}
} else {
	if (!empty($_GET['oc'])) {
		$pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT c.`id_cuenta_proveedor`, c.`id_forma_pago`, c.`id_moneda`, p.id_computo, p.id_proyecto FROM `compras` c inner join pedidos p on p.id = c.id_pedido WHERE c.`id` = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_GET['oc']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		
		if (!empty($data['id_cuenta_proveedor'])) {
			$idProveedor = $data['id_cuenta_proveedor'];
		}
		if (!empty($data['id_forma_pago'])) {
			$idFormaPago = $data['id_forma_pago'];
		}
		if (!empty($data['id_moneda'])) {
			$idMoneda = $data['id_moneda'];
		}
		
		$idComputo = $data['id_computo'];
		$idProyecto = $data['id_proyecto'];
		if (!empty($idComputo)) {
			$sql = " SELECT s.id_empresa FROM computos c inner join tareas t on t.id = c.id_tarea inner join proyectos pr on pr.id = t.id_proyecto inner join sitios s on s.id = pr.id_sitio WHERE c.id = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$idComputo]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$idEmpresa = $data['id_empresa'];	
		} else {
			$sql = " SELECT s.id_empresa FROM proyectos pr inner join sitios s on s.id = pr.id_sitio WHERE pr.id = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$idProyecto]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$idEmpresa = $data['id_empresa'];
		}
		
		
        Database::disconnect();
	}
	
}

// Variables que necesita la vista unificada
$preseleccionado = false;
$proyectoDatos = [];
$certIds = [];
$errorMsg = '';

// Pre-cargar imputaciones de la OC (compras_detalle)
$imputacionesOC = [];
if (!empty($_GET['oc'])) {
	$pdo = Database::connect();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$sqlImp = "SELECT d.id, m.concepto, d.cantidad FROM compras_detalle d INNER JOIN materiales m ON m.id = d.id_material WHERE d.id_compra = ?";
	$qImp = $pdo->prepare($sqlImp);
	$qImp->execute([$_GET['oc']]);
	$imputacionesOC = $qImp->fetchAll(PDO::FETCH_ASSOC);
	Database::disconnect();
}

// Labels para campos preseleccionados
$ocLabel = '';
$empresaLabel = '';
$proveedorLabel = '';
$formaPagoLabel = '';
$monedaLabel = '';
if ($ocPreseleccionada && !empty($_GET['oc'])) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $q = $pdo->prepare("SELECT nro_oc FROM compras WHERE id = ?");
    $q->execute([$_GET['oc']]);
    $ocLabel = $q->fetchColumn() ?: '';

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
    }

    Database::disconnect();
}

$vista = 'compra';
include 'nuevaFactura.php';