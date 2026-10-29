<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    exit('La sesión expiró. Vuelva a iniciar sesión.');
}
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/FacturaVentaARCAPDF.php';

function responderErrorVistaPrevia(string $message, int $status = 422): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Vista previa</title>'
        . '<body style="font:16px Arial,sans-serif;margin:40px"><h3>No se pudo generar la vista previa</h3><p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p><p>Vuelva a la factura y revise los datos ingresados.</p></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorVistaPrevia('La solicitud no es válida.', 405);
}
$sessionToken = (string)($_SESSION['csrf_factura_arca'] ?? '');
$postedToken = (string)($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
    responderErrorVistaPrevia('La sesión del formulario expiró. Recargue la factura e intente nuevamente.', 403);
}

$pdo = null;
try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $companyId = filter_var($_POST['id_empresa'] ?? null, FILTER_VALIDATE_INT);
    $customerId = filter_var($_POST['id_cuenta_destino'] ?? null, FILTER_VALIDATE_INT);
    $projectId = filter_var($_POST['id_proyecto'] ?? null, FILTER_VALIDATE_INT);
    $activityId = filter_var($_POST['actividad_arca_id'] ?? null, FILTER_VALIDATE_INT);
    $point = filter_var($_POST['punto_venta'] ?? null, FILTER_VALIDATE_INT);
    $typeCode = filter_var($_POST['id_tipo_comprobante'] ?? null, FILTER_VALIDATE_INT);
    if (!$companyId || !$customerId || !$projectId || !$activityId || !$point || !$typeCode) {
        throw new RuntimeException('Complete empresa, cliente, proyecto, punto de venta, tipo de comprobante y actividad ARCA.');
    }

    $activityCheck = $pdo->prepare('SELECT COUNT(*) FROM arca_actividades_empresa WHERE id_empresa = ? AND codigo = ?');
    $activityCheck->execute([$companyId, $activityId]);
    if ((int)$activityCheck->fetchColumn() === 0) {
        throw new RuntimeException('La actividad seleccionada no está configurada para la empresa emisora.');
    }

    $qCompany = $pdo->prepare(
        'SELECT e.empresa, e.cuit, e.direccion, e.email, e.iibb, e.fecha_inicio_actividades, e.cbu_emisor, e.alias_cbu,
                ci.condicion_iva
         FROM empresas e LEFT JOIN condiciones_iva ci ON ci.id = e.id_condicion_iva
         WHERE e.id = ?'
    );
    $qCompany->execute([$companyId]);
    $company = $qCompany->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        throw new RuntimeException('No se encontró la empresa emisora.');
    }

    $qCustomer = $pdo->prepare(
        'SELECT c.nombre, c.razon_social, c.cuit, c.direccion, c.email, ci.condicion_iva,
                c.id_condicion_iva
         FROM cuentas c LEFT JOIN condiciones_iva ci ON ci.id = c.id_condicion_iva
         WHERE c.id = ?'
    );
    $qCustomer->execute([$customerId]);
    $customer = $qCustomer->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        throw new RuntimeException('No se encontró el cliente receptor.');
    }

    $qType = $pdo->prepare('SELECT codigo, descripcion FROM arca_tipos_comprobante WHERE codigo = ?');
    $qType->execute([$typeCode >= 1001 ? $typeCode - 1000 : $typeCode]);
    $voucherType = $qType->fetch(PDO::FETCH_ASSOC);
    if (!$voucherType) {
        throw new RuntimeException('Seleccione un tipo de comprobante válido.');
    }
    $letter = letraComprobanteDesdeTipo($voucherType['descripcion'], (int)$voucherType['codigo']);
    $isFce = in_array((int)$voucherType['codigo'], [201, 206, 211], true);
    $remitoPuntoVenta = trim((string)($_POST['remito_punto_venta'] ?? ''));
    $remitoNumero = trim((string)($_POST['remito_numero'] ?? ''));
    if (($remitoPuntoVenta === '') !== ($remitoNumero === '')) {
        throw new RuntimeException('Para mostrar el remito relacionado, complete punto de venta y número.');
    }
    $remitoText = '';
    if ($remitoPuntoVenta !== '') {
        if (!preg_match('/^\d{1,5}$/D', $remitoPuntoVenta)
            || (int)$remitoPuntoVenta < 1 || (int)$remitoPuntoVenta > 99998
            || !preg_match('/^\d{1,8}$/D', $remitoNumero)
            || (int)$remitoNumero < 1) {
            throw new RuntimeException('El punto de venta o el número del Remito R no son válidos.');
        }
        $remitoText = str_pad((string)(int)$remitoPuntoVenta, 4, '0', STR_PAD_LEFT)
            . '-' . str_pad((string)(int)$remitoNumero, 8, '0', STR_PAD_LEFT);
    }

    $invoiceDate = (string)($_POST['fecha_emitida'] ?? '');
    $date = DateTime::createFromFormat('!Y-m-d', $invoiceDate);
    if (!$date || $date->format('Y-m-d') !== $invoiceDate) {
        throw new RuntimeException('Ingrese una fecha de emisión válida.');
    }
    $concept = filter_var($_POST['concepto_arca'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($concept, [1, 2, 3], true)) {
        throw new RuntimeException('Seleccione un concepto ARCA válido.');
    }
    $fechaServicioDesde = (string)($_POST['fecha_servicio_desde'] ?? '');
    $fechaServicioHasta = (string)($_POST['fecha_servicio_hasta'] ?? '');
    $fechaVencimiento = (string)($_POST['fecha_vencimiento_pago'] ?? '');
    if ((int)$concept !== 1 || $isFce) {
        $serviceDates = (int)$concept !== 1;
        if ($serviceDates) {
        foreach ([$fechaServicioDesde, $fechaServicioHasta, $fechaVencimiento] as $serviceDate) {
            $parsedDate = DateTime::createFromFormat('!Y-m-d', $serviceDate);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $serviceDate) {
                throw new RuntimeException('Complete un período de servicio y vencimiento de pago válidos.');
            }
        }
        if ($fechaServicioDesde > $fechaServicioHasta || $fechaVencimiento < $invoiceDate) {
            throw new RuntimeException('Revise el período del servicio y el vencimiento de pago.');
        }
        } else {
            $parsedDue = DateTime::createFromFormat('!Y-m-d', $fechaVencimiento);
            if (!$parsedDue || $parsedDue->format('Y-m-d') !== $fechaVencimiento || $fechaVencimiento < $invoiceDate) {
                throw new RuntimeException('Ingrese un vencimiento de pago válido para la Factura de Crédito Electrónica.');
            }
        }
    }

    $fceData = null;
    if ($isFce) {
        $fceCbu = preg_replace('/\D+/', '', (string)($_POST['fce_cbu'] ?? $company['cbu_emisor'] ?? ''));
        $fceAlias = trim((string)($_POST['fce_alias_cbu'] ?? $company['alias_cbu'] ?? ''));
        $fceTransfer = strtoupper(trim((string)($_POST['fce_transferencia'] ?? '')));
        if (($fceCbu !== '' && !preg_match('/^\d{22}$/D', $fceCbu))
            || ($fceAlias !== '' && !preg_match('/^[A-Za-z0-9.-]{6,20}$/D', $fceAlias))
            || ($fceCbu === '' && $fceAlias === '')
            || !in_array($fceTransfer, ['SCA', 'ADC'], true)) {
            throw new RuntimeException('Complete un CBU o alias CBU válido y la opción de transferencia de la Factura de Crédito Electrónica.');
        }
        $fceData = [
            'cbu' => $fceCbu,
            'alias_cbu' => $fceAlias,
            'transferencia' => $fceTransfer,
            'leyenda_sca' => $fceTransfer === 'SCA'
                ? 'Luego de su aceptación tácita o expresa, esta Factura de Crédito Electrónica MiPyMEs será transmitida al Sistema de Circulación Abierta para Facturas de Crédito Electrónicas MiPyMEs, para su circulación y negociación, incluso en los Mercados de Valores, en este caso, a través de un Agente de Depósito Colectivo o agentes que cumplan similares funciones.'
                : '',
        ];
    }

    $currencyId = filter_var($_POST['id_moneda'] ?? null, FILTER_VALIDATE_INT);
    $qCurrency = $pdo->prepare('SELECT moneda FROM monedas WHERE id = ?');
    $qCurrency->execute([$currencyId]);
    $currencyText = (string)$qCurrency->fetchColumn();
    if ($currencyText === '') {
        throw new RuntimeException('Seleccione una moneda válida.');
    }
    $isDollar = esMonedaDolar($currencyText);
    $exchangeRate = $isDollar ? (float)($_POST['cotizacion'] ?? 0) : 1.0;
    if ($isDollar && $exchangeRate <= 0) {
        throw new RuntimeException('Ingrese una cotización válida para la moneda extranjera.');
    }

    $conditionIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['condiciones_venta'] ?? [])))));
    if (!$conditionIds) {
        throw new RuntimeException('Seleccione al menos una condición de venta.');
    }
    $conditionMarks = implode(',', array_fill(0, count($conditionIds), '?'));
    $qConditions = $pdo->prepare("SELECT forma_pago FROM formas_pago WHERE id IN ($conditionMarks) ORDER BY forma_pago");
    $qConditions->execute($conditionIds);
    $conditionNames = $qConditions->fetchAll(PDO::FETCH_COLUMN);
    if (count($conditionNames) !== count($conditionIds)) {
        throw new RuntimeException('Una de las condiciones de venta ya no está disponible.');
    }

    $projectCode = '';
    $qProject = $pdo->prepare(
        "SELECT CONCAT_WS('_', s.nro_sitio, s.nro_subsitio, p.nro)
         FROM proyectos p INNER JOIN sitios s ON s.id = p.id_sitio WHERE p.id = ?"
    );
    $qProject->execute([$projectId]);
    $projectCode = (string)$qProject->fetchColumn();

    $details = json_decode((string)($_POST['detalles_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($details) || !$details) {
        throw new RuntimeException('Agregue al menos un detalle antes de descargar el PDF.');
    }
    $certificateIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['certificados'] ?? [])))));
    $inclusionSql = $certificateIds ? ' OR ca.id IN (' . implode(',', array_fill(0, count($certificateIds), '?')) . ')' : '';
    $qCertificate = $pdo->prepare(
        'SELECT ca.id, ca.monto_total
         FROM certificados_avances_cabecera ca
         LEFT JOIN certificados_maestros cm ON cm.id = ca.id_certificado_maestro
         LEFT JOIN occ o ON o.id = cm.id_occ
         WHERE ca.id = ? AND ca.aprobado_cliente = 1 AND (o.id_cuenta_cliente = ?' . $inclusionSql . ')'
    );
    $qConcept = $pdo->prepare(
        'SELECT cc.codigo, cc.descripcion, COALESCE(ti.tasa, 21) AS tasa_iva
         FROM conceptos_contables cc LEFT JOIN tipos_iva ti ON ti.id = cc.id_alicuota_iva WHERE cc.id = ?'
    );

    $items = [];
    $basesPorAlicuota = [];
    $importeExento = 0.0;
    $importeNoGravado = 0.0;
    $receiverExempt = (int)$customer['id_condicion_iva'] === 5;
    foreach ($details as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Uno de los detalles de la factura no es válido.');
        }
        $conceptId = filter_var($item['id_concepto'] ?? null, FILTER_VALIDATE_INT);
        if (!$conceptId || $conceptId < 1) {
            throw new RuntimeException('Seleccione un concepto válido en cada detalle.');
        }
        $qConcept->execute([$conceptId]);
        $conceptRow = $qConcept->fetch(PDO::FETCH_ASSOC);
        if (!$conceptRow) {
            throw new RuntimeException('Uno de los conceptos ya no está disponible.');
        }

        $quantity = 0.0;
        $subtotal = 0.0;
        $unitPrice = (float)($item['precio'] ?? 0);
        $typedById = [];
        foreach ((array)($item['imputaciones_data'] ?? []) as $typed) {
            if (isset($typed['id_cd'])) {
                $typedById[(int)$typed['id_cd']] = $typed;
            }
        }
        $imputationIds = array_values(array_unique(array_filter(array_map('intval', (array)($item['imputaciones'] ?? [])))));
        if ($imputationIds) {
            foreach ($imputationIds as $idCertificate) {
                $params = array_merge([$idCertificate, $customerId], $certificateIds);
                $qCertificate->execute($params);
                $certificate = $qCertificate->fetch(PDO::FETCH_ASSOC);
                if (!$certificate) {
                    throw new RuntimeException('Un certificado imputado ya no está disponible para este cliente.');
                }
                $typed = $typedById[$idCertificate] ?? [];
                $qty = max(0, (float)($typed['cantidad'] ?? 1));
                $price = max(0, (float)($typed['precio'] ?? $certificate['monto_total']));
                $quantity += $qty;
                $subtotal += round($qty * $price, 2);
                $unitPrice = 0;
            }
        } else {
            $quantity = (float)($item['cantidad'] ?? 0);
            $unitPrice = (float)($item['precio'] ?? 0);
            if ($quantity <= 0 || $unitPrice < 0) {
                throw new RuntimeException('La cantidad y el precio de cada detalle deben ser válidos.');
            }
            $subtotal = round($quantity * $unitPrice, 2);
        }
        if ($quantity <= 0) {
            throw new RuntimeException('Cada detalle debe tener cantidad mayor que cero.');
        }
        $discount = (float)($item['porcentaje_descuento'] ?? 0);
        if ($discount < 0 || $discount > 100) {
            throw new RuntimeException('El descuento del detalle debe estar entre 0 y 100%.');
        }
        $subtotal = round($subtotal * (1 - $discount / 100), 2);
        $rate = ($letter === 'C' || $receiverExempt) ? 0.0 : (float)$conceptRow['tasa_iva'];
        if ($rate > 0) {
            $baseKey = number_format($rate, 4, '.', '');
            $basesPorAlicuota[$baseKey] = ($basesPorAlicuota[$baseKey] ?? 0) + (int)round($subtotal * 100);
        } elseif ($receiverExempt) {
            $importeExento += $subtotal;
        } else {
            $importeNoGravado += $subtotal;
        }

        $items[] = [
            'codigo' => (string)$conceptRow['codigo'],
            'ncm' => (string)($item['ncm'] ?? '73.08.20.00'),
            'proyecto' => $projectCode,
            'concepto' => (string)$conceptRow['descripcion'],
            'descripcion' => trim((string)($item['descripcion'] ?? '')) ?: (string)$conceptRow['descripcion'],
            'cantidad' => $quantity,
            'precio_unitario' => $unitPrice,
            'descuento_porcentaje' => $discount,
            'base_imponible' => $subtotal,
            'alicuota_iva' => $rate,
        ];
    }

    $importeNeto = 0.0;
    $ivaRows = [];
    $rateCodes = [[0.0, 3], [2.5, 9], [5.0, 8], [10.5, 4], [21.0, 5], [27.0, 6]];
    $importeIva = 0.0;
    foreach ($basesPorAlicuota as $rateKey => $baseCents) {
        $rate = (float)$rateKey;
        $taxCents = (int)round($baseCents * $rate / 100);
        $importeNeto += $baseCents / 100;
        $importeIva += $taxCents / 100;
        $code = null;
        foreach ($rateCodes as [$allowedRate, $allowedCode]) {
            if (abs((float)$allowedRate - $rate) < 0.0001) {
                $code = $allowedCode;
                break;
            }
        }
        if ($code === null) {
            throw new RuntimeException('La alícuota de un detalle no está soportada para el PDF.');
        }
        $ivaRows[] = ['codigo_arca' => $code, 'importe' => $taxCents / 100];
    }

    $otherTaxes = [];
    $importeTributos = 0.0;
    $retenciones = json_decode((string)($_POST['retenciones_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
    $qRegimen = $pdo->prepare('SELECT regimen, codigo, articulo, signo_vta FROM regimenes_facturacion WHERE id = ?');
    foreach (is_array($retenciones) ? $retenciones : [] as $retention) {
        $regimenId = filter_var($retention['id_regimen'] ?? null, FILTER_VALIDATE_INT);
        if (!$regimenId || $regimenId < 1) continue;
        $qRegimen->execute([$regimenId]);
        $regimen = $qRegimen->fetch(PDO::FETCH_ASSOC);
        if (!$regimen) continue;
        $amount = (float)($retention['monto'] ?? 0);
        $amount = (int)$regimen['signo_vta'] === 2 ? -abs($amount) : abs($amount);
        $importeTributos += $amount;
        $otherTaxes[] = [
            'descripcion' => trim((string)$regimen['regimen'] . ' ' . (string)$regimen['codigo'] . ' ' . (string)$regimen['articulo']),
            'base_imponible' => (float)($retention['base'] ?? 0),
            'alicuota' => (float)($retention['porcentaje'] ?? 0),
            'importe' => $amount,
        ];
    }

    $importeTotal = round($importeNeto + $importeNoGravado + $importeExento + $importeIva + $importeTributos, 2);
    $comprobante = [
        'tipo_comprobante' => (int)$voucherType['codigo'],
        'punto_venta' => (int)$point,
        'numero_comprobante' => 0,
        'fecha_comprobante' => $invoiceDate,
        'concepto' => (int)$concept,
        'fecha_servicio_desde' => $fechaServicioDesde,
        'fecha_servicio_hasta' => $fechaServicioHasta,
        'fecha_vencimiento_pago' => $fechaVencimiento,
        'importe_neto' => round($importeNeto, 2),
        'importe_no_gravado' => round($importeNoGravado, 2),
        'importe_exento' => round($importeExento, 2),
        'importe_iva' => round($importeIva, 2),
        'importe_tributos' => round($importeTributos, 2),
        'importe_total' => $importeTotal,
        'cotizacion' => $exchangeRate,
        'cae' => '',
        'fecha_vencimiento_cae' => '',
    ];
    $emisor = [
        'razon_social' => (string)$company['empresa'],
        'cuit' => (string)$company['cuit'],
        'domicilio' => (string)$company['direccion'],
        'ingresos_brutos' => (string)$company['iibb'],
        'fecha_inicio_actividades' => (string)$company['fecha_inicio_actividades'],
        'condicion_iva' => (string)$company['condicion_iva'],
    ];
    $receptor = [
        'razon_social' => trim((string)($customer['razon_social'] ?? '')) ?: (string)$customer['nombre'],
        'cuit' => (string)$customer['cuit'],
        'numero_documento' => (string)$customer['cuit'],
        'domicilio' => (string)$customer['direccion'],
        'condicion_iva' => (string)$customer['condicion_iva'],
    ];
    $detail = [
        'proyecto' => $projectCode,
        'observaciones' => trim((string)($_POST['observaciones'] ?? '')),
        'leyenda_impositiva' => trim((string)($_POST['leyenda_impositiva'] ?? '')),
        'condicion_venta' => implode(', ', $conditionNames),
        'condiciones_venta' => array_values($conditionNames),
        'remito' => $remitoText,
        'items' => $items,
        'fce' => $fceData,
    ];
    $pdf = new FacturaVentaARCAPDF([
        'comprobante' => $comprobante,
        'emisor' => $emisor,
        'receptor' => $receptor,
        'detalle' => $detail,
        'alicuotas' => $ivaRows,
        'tributos' => $otherTaxes,
        'letra' => $letter,
        'titulo' => strtoupper((string)$voucherType['descripcion']),
        'moneda' => $isDollar ? 'USD' : 'ARS',
        'dolares' => $isDollar,
        'local_only' => true,
        'homologacion' => false,
        'vista_previa' => true,
        'mostrar_iva_item' => $letter === 'A',
        'copias_multiples' => in_array($letter, ['A', 'B', 'C'], true),
        'pie' => 'VISTA PREVIA - SIN VALIDEZ FISCAL',
        'qr_file' => null,
    ]);
    $pdfContent = $pdf->generar();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Vista-Previa-Factura-Venta.pdf"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
} catch (Throwable $exception) {
    if ($pdo instanceof PDO) {
        Database::disconnect();
    }
    responderErrorVistaPrevia($exception->getMessage());
}
