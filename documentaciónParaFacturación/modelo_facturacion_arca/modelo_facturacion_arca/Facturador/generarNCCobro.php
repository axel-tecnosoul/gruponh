<?php
// La reserva persistente impide volver a emitir ante errores de red o doble envio.
function generarNCCobro($pdo, $id, $motivo, $usuario, $billingFactory = null) {
    $motivo = trim($motivo);
    if ($motivo === '' || mb_strlen($motivo) > 1000) {
        throw new RuntimeException('Debe indicar un motivo de hasta 1000 caracteres.');
    }
    $q = $pdo->prepare('SELECT f.* FROM cobros co INNER JOIN facturas f ON f.id = co.id_factura WHERE co.id = ?');
    $q->execute([$id]);
    $f = $q->fetch(PDO::FETCH_ASSOC);
    if (!$f || $f['estado'] !== 'A' || empty($f['cae']) || empty($f['numero_comprobante'])) {
        throw new RuntimeException('La factura debe estar autorizada por ARCA para generar una NC.');
    }
    $tipos = [1 => 3, 6 => 8, 11 => 13];
    if (!isset($tipos[$f['tipo_comprobante']])) {
        throw new RuntimeException('Tipo de factura no compatible con esta anulacion.');
    }
    $q = $pdo->prepare('SELECT estado FROM facturas WHERE id_factura_asociada = ?');
    $q->execute([$f['id']]);
    if ($q->fetch()) {
        throw new RuntimeException('Esta factura ya tiene una NC emitida o pendiente de conciliacion.');
    }
    if ($billingFactory) {
        $billing = $billingFactory($f);
    } else {
        require_once __DIR__.'/vendor/afip/Afip.php';
        require __DIR__.'/config_facturacion_electronica.php';
        if ($f['homologacion'] === null || (int)$f['homologacion'] !== (int)$homologacion) {
            throw new RuntimeException('El ambiente ARCA no coincide con el de la factura original.');
        }
        $aInitializeAFIP['CUIT'] = $f['emisor_cuit'];
        $afip = new Afip($aInitializeAFIP);
        $billing = $afip->ElectronicBilling;
    }
    $original = $billing->GetVoucherInfo($f['numero_comprobante'], $f['punto_venta'], $f['tipo_comprobante']);
    if (!$original || (string)$original->CodAutorizacion !== (string)$f['cae'] || abs((float)$original->ImpTotal - (float)$f['imp_total']) > 0.001) {
        throw new RuntimeException('No se pudo verificar la factura original en ARCA.');
    }
    $payload = [];
    foreach (['Concepto','DocTipo','DocNro','ImpTotal','ImpTotConc','ImpNeto','ImpOpEx','ImpIVA','ImpTrib','MonId','MonCotiz'] as $campo) {
        $payload[$campo] = $original->$campo;
    }
    $payload['CondicionIVAReceptorId'] = $original->CondicionIVAReceptorId ?? $f['condicion_iva_receptor'];
    foreach (['Iva' => 'AlicIva', 'Tributos' => 'Tributo'] as $campo => $elemento) {
        if (!empty($original->$campo->$elemento)) {
            $valores = $original->$campo->$elemento;
            $payload[$campo] = json_decode(json_encode(is_array($valores) ? $valores : [$valores]), true);
        }
    }
    $payload['PtoVta'] = (int)$f['punto_venta'];
    $payload['CbteTipo'] = $tipos[$f['tipo_comprobante']];
    $payload['CbteFch'] = date('Ymd');
    if ((int)$payload['Concepto'] !== 1) {
        foreach (['FchServDesde','FchServHasta','FchVtoPago'] as $campo) {
            $payload[$campo] = !empty($original->$campo) ? $original->$campo : date('Ymd');
        }
    }
    $payload['CbtesAsoc'] = ['CbteAsoc' => [[
        'Tipo' => (int)$f['tipo_comprobante'], 'PtoVta' => (int)$f['punto_venta'],
        'Nro' => (int)$f['numero_comprobante'], 'Cuit' => $f['emisor_cuit'], 'CbteFch' => $original->CbteFch
    ]]];
    $numero = $billing->GetLastVoucher($payload['PtoVta'], $payload['CbteTipo']) + 1;
    $payload['CbteDesde'] = $payload['CbteHasta'] = $numero;
    $payload['CantReg'] = 1;
    // Reservar el comprobante y ambas relaciones antes de contactar ARCA.
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT id_nota_credito FROM facturas WHERE id = ? FOR UPDATE');
        $q->execute([$f['id']]);
        if ($q->fetchColumn()) throw new RuntimeException('Esta factura ya tiene una NC emitida o pendiente.');
        $q = $pdo->prepare("INSERT INTO facturas
            (id_cliente,emisor_cuit,fecha_cbte,doc_tipo,doc_nro,condicion_iva_receptor,
             tipo_comprobante,punto_venta,numero_comprobante,imp_total,imp_neto,imp_iva,
             concepto,estado,homologacion,id_factura_asociada,motivo_nc,solicitud_arca,intentos_arca)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'P',?,?,?,?,1)");
        $q->execute([$f['id_cliente'],$f['emisor_cuit'],date('Y-m-d'),$payload['DocTipo'],
            $payload['DocNro'],$payload['CondicionIVAReceptorId'],$payload['CbteTipo'],
            $payload['PtoVta'],$numero,$payload['ImpTotal'],$payload['ImpNeto'],$payload['ImpIVA'],
            $payload['Concepto'],$f['homologacion'],$f['id'],$motivo,
            json_encode(['id_usuario'=>$usuario,'comprobante'=>$payload], JSON_THROW_ON_ERROR)]);
        $idNC = $pdo->lastInsertId();
        $q = $pdo->prepare('SELECT descripcion,precio,cantidad FROM factura_detalle WHERE id_factura = ?');
        $q->execute([$f['id']]);
        $detalles = $q->fetchAll(PDO::FETCH_ASSOC);
        $q = $pdo->prepare('INSERT INTO factura_detalle (id_factura,descripcion,precio,cantidad) VALUES (?,?,?,?)');
        foreach ($detalles as $detalle) {
            $q->execute([$idNC,$detalle['descripcion'],$detalle['precio'],$detalle['cantidad']]);
        }
        $pdo->prepare('UPDATE facturas SET id_nota_credito = ? WHERE id = ?')->execute([$idNC,$f['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    try {
        $res = $billing->CreateVoucher($payload);
        if (empty($res['CAE'])) {
            throw new RuntimeException('ARCA no devolvio un CAE.');
        }
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE facturas SET estado = 'A', cae = ?, fecha_vencimiento_cae = ?, respuesta_arca = ?, error_arca = NULL WHERE id = ?")
            ->execute([$res['CAE'],$res['CAEFchVto'],json_encode($res, JSON_THROW_ON_ERROR),$idNC]);
        // La NC autorizada determina la anulacion; se conservan los importes del cobro.
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('UPDATE facturas SET error_arca = ? WHERE id = ?')->execute([$e->getMessage(),$idNC]);
        throw new RuntimeException('NC pendiente de conciliacion: '.$e->getMessage().'. Verifique el comprobante en ARCA antes de volver a emitir.');
    }
}
