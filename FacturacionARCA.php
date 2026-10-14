<?php

require_once __DIR__ . '/database.php';

final class FacturacionARCA
{
    private const SDK_DIR = __DIR__ . '/vendor/afip';
    private const ALIQUOT_CODES = [
        [0.0, 3],
        [2.5, 9],
        [5.0, 8],
        [10.5, 4],
        [21.0, 5],
        [27.0, 6],
    ];

    public static function habilitada(): bool
    {
        return getenv('FACTURACION_ARCA_HABILITADA') === '1';
    }

    public static function asegurarCatalogoTiposComprobante(PDO $pdo): void
    {
        $types = [
            [1, 'Factura A'], [2, 'Nota de Débito A'], [3, 'Nota de Crédito A'],
            [4, 'Recibo A'], [5, 'Nota de Venta al Contado A'],
            [6, 'Factura B'], [7, 'Nota de Débito B'], [8, 'Nota de Crédito B'],
            [9, 'Recibo B'], [10, 'Nota de Venta al Contado B'],
            [11, 'Factura C'], [12, 'Nota de Débito C'], [13, 'Nota de Crédito C'],
            [15, 'Recibo C'], [19, 'Factura E'], [20, 'Nota de Débito E'],
            [21, 'Nota de Crédito E'],
            [201, 'Factura de Crédito Electrónica MiPyME A'],
            [202, 'Nota de Débito Electrónica MiPyME A'],
            [203, 'Nota de Crédito Electrónica MiPyME A'],
            [206, 'Factura de Crédito Electrónica MiPyME B'],
            [207, 'Nota de Débito Electrónica MiPyME B'],
            [208, 'Nota de Crédito Electrónica MiPyME B'],
            [211, 'Factura de Crédito Electrónica MiPyME C'],
            [212, 'Nota de Débito Electrónica MiPyME C'],
            [213, 'Nota de Crédito Electrónica MiPyME C'],
        ];
        $insert = $pdo->prepare(
            "INSERT IGNORE INTO arca_tipos_comprobante (codigo, descripcion, fecha_desde, fecha_hasta)
             VALUES (?, ?, '', '')"
        );
        foreach ($types as [$code, $description]) {
            $insert->execute([$code, $description]);
        }
    }

    public static function consultarCatalogosEmpresa(PDO $pdo, int $companyId): array
    {
        if (!self::habilitada()) {
            throw new RuntimeException('La consulta de catálogos ARCA está deshabilitada. Active FACTURACION_ARCA_HABILITADA=1 para habilitarla.');
        }

        $query = $pdo->prepare('SELECT cuit FROM empresas WHERE id = ?');
        $query->execute([$companyId]);
        $cuit = self::digits((string)$query->fetchColumn());
        if (!self::validCuit($cuit)) {
            throw new RuntimeException('La empresa debe tener un CUIT válido para consultar sus catálogos en ARCA.');
        }

        $billing = self::billing([
            'id_empresa' => $companyId,
            'homologacion' => self::homologacion(),
            'datos_emisor' => self::encode(['cuit' => $cuit]),
        ]);
        $salesPoints = [];
        foreach (self::listResponse($billing->GetSalesPoints()) as $point) {
            $number = (int)self::field($point, 'Nro', 0);
            if ($number < 1 || $number > 99998) {
                continue;
            }
            $blocked = in_array(
                strtoupper(trim((string)self::field($point, 'Bloqueado', ''))),
                ['S', 'SI', '1', 'TRUE'],
                true
            );
            $endDate = trim((string)self::field($point, 'FchBaja', ''));
            if (in_array(strtoupper($endDate), ['NULL', '0000-00-00'], true)) {
                $endDate = '';
            }
            $salesPoints[] = [
                'numero' => $number,
                'emision_tipo' => (string)self::field($point, 'EmisionTipo', ''),
                'bloqueado' => $blocked,
                'fecha_baja' => $endDate,
            ];
        }

        $voucherTypes = [];
        foreach (self::listResponse($billing->GetVoucherTypes()) as $type) {
            $code = (int)self::field($type, 'Id', 0);
            $description = trim((string)self::field($type, 'Desc', ''));
            if ($code > 0 && $description !== '') {
                $voucherTypes[] = [
                    'codigo' => $code,
                    'descripcion' => $description,
                    'fecha_desde' => (string)self::field($type, 'FchDesde', ''),
                    'fecha_hasta' => (string)self::field($type, 'FchHasta', ''),
                ];
            }
        }

        return ['puntos_venta' => $salesPoints, 'tipos_comprobante' => $voucherTypes];
    }

    public static function validarFactura(PDO $pdo, int $idFactura, array $input): void
    {
        $invoice = self::loadInvoice($pdo, $idFactura);
        if (!$invoice) {
            throw new RuntimeException('No se encontró la factura de venta.');
        }
        self::prepare($pdo, $invoice, $input);
    }

    public static function emitir(PDO $pdo, int $idFactura, string $claveOperacion, array $input): array
    {
        if (!self::habilitada()) {
            throw new RuntimeException('La emisión ARCA está deshabilitada. Active FACTURACION_ARCA_HABILITADA=1 para habilitarla.');
        }
        if (!preg_match('/^[a-f0-9]{32}$/D', $claveOperacion)) {
            throw new RuntimeException('La clave de operación ARCA no es válida. Actualice la página e intente nuevamente.');
        }

        $invoice = self::loadInvoice($pdo, $idFactura);
        if (!$invoice) {
            throw new RuntimeException('No se encontró la factura de venta.');
        }
        if ((int)$invoice['id_estado'] !== 3) {
            throw new RuntimeException('La factura debe guardarse como definitiva antes de emitirla en ARCA.');
        }

        $existing = self::loadByInvoice($pdo, $idFactura);
        if ($existing) {
            if (!hash_equals((string)$existing['clave_operacion'], $claveOperacion)) {
                throw new RuntimeException('Esta factura ya tiene un intento ARCA. Consulte su estado antes de continuar.');
            }
            return self::procesar($pdo, $existing);
        }

        $model = self::prepare($pdo, $invoice, $input);
        $homologacion = self::homologacion();

        $byKey = $pdo->prepare(
            'SELECT id_factura_venta FROM comprobantes_arca
             WHERE homologacion = ? AND emisor_cuit = ? AND clave_operacion = ?'
        );
        $byKey->execute([$homologacion, $model['emisor']['cuit'], $claveOperacion]);
        $otherInvoice = $byKey->fetchColumn();
        if ($otherInvoice !== false && (int)$otherInvoice !== $idFactura) {
            throw new RuntimeException('La clave de operación ya está asociada a otra factura. No se iniciará un segundo envío.');
        }

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                "INSERT INTO comprobantes_arca
                 (id_factura_venta, clave_operacion, homologacion, emisor_cuit, punto_venta,
                  tipo_comprobante, estado, fecha_comprobante, concepto, receptor_tipo_documento,
                  receptor_numero_documento, receptor_condicion_iva, fecha_servicio_desde,
                  fecha_servicio_hasta, fecha_vencimiento_pago, moneda, cotizacion, importe_total,
                  importe_neto, importe_no_gravado, importe_exento, importe_iva, importe_tributos,
                  datos_emisor, datos_receptor, detalle_comprobante, version_datos)
                 VALUES (?,?,?,?,?,?,'pendiente',
                         ?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?,?, 1)"
            );
            $insert->execute([
                $idFactura,
                $claveOperacion,
                $homologacion,
                $model['emisor']['cuit'],
                $model['punto_venta'],
                $model['tipo_comprobante'],
                $model['fecha_comprobante'],
                $model['concepto'],
                $model['receptor']['tipo_documento'],
                $model['receptor']['numero_documento'],
                $model['receptor']['condicion_iva_arca'],
                $model['fecha_servicio_desde'],
                $model['fecha_servicio_hasta'],
                $model['fecha_vencimiento_pago'],
                $model['moneda'],
                $model['cotizacion'],
                self::money($model['total_centavos']),
                self::money($model['neto_centavos']),
                self::money($model['no_gravado_centavos']),
                self::money($model['exento_centavos']),
                self::money($model['iva_centavos']),
                self::money($model['tributos_centavos']),
                self::encode($model['emisor']),
                self::encode($model['receptor']),
                self::encode($model['detalle']),
            ]);
            $idComprobante = (int)$pdo->lastInsertId();

            $insertIva = $pdo->prepare(
                'INSERT INTO comprobantes_arca_iva
                 (id_comprobante_arca, codigo_arca, base_imponible, importe) VALUES (?,?,?,?)'
            );
            foreach ($model['iva_por_codigo'] as $codigo => $montos) {
                $insertIva->execute([
                    $idComprobante,
                    $codigo,
                    self::money($montos['base']),
                    self::money($montos['importe']),
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
                $existing = self::loadByInvoice($pdo, $idFactura);
                if ($existing && hash_equals((string)$existing['clave_operacion'], $claveOperacion)) {
                    return self::procesar($pdo, $existing);
                }
                throw new RuntimeException(
                    $existing
                        ? 'Esta factura ya tiene un intento ARCA asociado. Actualice la página y consulte su estado.'
                        : 'La clave de operación ya está asociada a otro comprobante. Actualice la página antes de continuar.',
                    0,
                    $e
                );
            }
            throw $e;
        }

        return self::procesar($pdo, self::loadById($pdo, $idComprobante));
    }

    public static function conciliar(PDO $pdo, int $idFactura, ?int $idUsuario = null): array
    {
        if (!self::habilitada()) {
            throw new RuntimeException('La comunicación con ARCA está deshabilitada. Active FACTURACION_ARCA_HABILITADA=1 para habilitarla.');
        }
        $record = self::loadByInvoice($pdo, $idFactura);
        if (!$record) {
            throw new RuntimeException('La factura todavía no tiene un número reservado en ARCA.');
        }
        if ($record['estado'] === 'autorizado') {
            return ['estado' => 'autorizado', 'mensaje' => 'La factura ya está autorizada.', 'id' => (int)$record['id']];
        }
        if (!in_array($record['estado'], ['incierto', 'enviando'], true) || empty($record['numero_comprobante'])) {
            throw new RuntimeException('Este comprobante no tiene un envío incierto para conciliar.');
        }

        $lock = self::lock($pdo, $record);
        try {
            $record = self::loadById($pdo, (int)$record['id']);
            if ($record['estado'] === 'autorizado') {
                return ['estado' => 'autorizado', 'mensaje' => 'La factura ya está autorizada en ARCA.', 'id' => (int)$record['id']];
            }
            return self::conciliarBloqueado($pdo, $record, $idUsuario);
        } finally {
            self::unlock($pdo, $lock);
        }
    }

    public static function reintentarHomologacion(PDO $pdo, int $idFactura): array
    {
        if (!self::habilitada() || self::homologacion() !== 1) {
            throw new RuntimeException('El reintento explícito solo está disponible con ARCA habilitada en homologación.');
        }
        $record = self::loadByInvoice($pdo, $idFactura);
        if (!$record || (int)$record['homologacion'] !== 1) {
            throw new RuntimeException('No se encontró un intento de homologación para esta factura.');
        }
        $correctableReceiverCondition = $record['estado'] === 'rechazado'
            && strpos((string)$record['ultimo_error'], '(10242)') !== false;
        if ((!in_array($record['estado'], ['incierto', 'enviando'], true) && !$correctableReceiverCondition)
            || empty($record['numero_comprobante'])) {
            throw new RuntimeException('Solo se puede reintentar un envío incierto o un rechazo 10242 corregible en homologación.');
        }

        $lock = self::lock($pdo, $record);
        try {
            $record = self::loadById($pdo, (int)$record['id']);
            $correctableReceiverCondition = $record['estado'] === 'rechazado'
                && strpos((string)$record['ultimo_error'], '(10242)') !== false;
            if ((!in_array($record['estado'], ['incierto', 'enviando'], true) && !$correctableReceiverCondition)
                || empty($record['numero_comprobante'])) {
                throw new RuntimeException('El intento cambió de estado. Consulte nuevamente la factura antes de continuar.');
            }

            $number = (int)$record['numero_comprobante'];
            $request = json_decode((string)$record['solicitud_arca'], true);
            if ($correctableReceiverCondition) {
                $receiverSnapshot = json_decode((string)$record['datos_receptor'], true);
                $correctReceiverCondition = (int)($receiverSnapshot['condicion_iva_arca'] ?? 0);
                if (!is_array($request) || $correctReceiverCondition < 1) {
                    throw new RuntimeException('No se puede reconstruir la condición IVA del receptor desde el snapshot original.');
                }
                $originalRequest = $request;
                $request['CondicionIVAReceptorId'] = $correctReceiverCondition;
                $previousResponses = json_decode((string)$record['respuesta_arca'], true);
                if (!is_array($previousResponses)) {
                    $previousResponses = [];
                }
                $previousResponses['rechazo_10242'] = [
                    'error' => (string)$record['ultimo_error'],
                    'solicitud' => $originalRequest,
                ];
                $pdo->beginTransaction();
                try {
                    $q = $pdo->prepare(
                        "UPDATE comprobantes_arca
                         SET solicitud_arca = ?, respuesta_arca = ?, estado = 'incierto',
                             ultimo_error = 'Reintento homologación: condición IVA ARCA recuperada del snapshot del receptor.'
                         WHERE id = ? AND estado = 'rechazado' AND numero_comprobante = ?"
                    );
                    $q->execute([
                        self::encode($request),
                        self::encode($previousResponses),
                        (int)$record['id'],
                        $number,
                    ]);
                    if ($q->rowCount() !== 1) {
                        throw new RuntimeException('El intento cambió de estado antes de preparar el reintento corregido.');
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                $record = self::loadById($pdo, (int)$record['id']);
            }

            if (!is_array($request)
                || (int)($request['PtoVta'] ?? 0) !== (int)$record['punto_venta']
                || (int)($request['CbteTipo'] ?? 0) !== (int)$record['tipo_comprobante']
                || (int)($request['CbteDesde'] ?? 0) !== $number
                || (int)($request['CbteHasta'] ?? 0) !== $number) {
                throw new RuntimeException('La solicitud guardada no coincide con el número reservado. No se reenviará.');
            }

            $billing = self::billing($record);
            try {
                $voucher = $billing->GetVoucherInfo(
                    $number,
                    (int)$record['punto_venta'],
                    (int)$record['tipo_comprobante']
                );
                if ($voucher) {
                    return self::conciliarBloqueado($pdo, $record);
                }
            } catch (Throwable $e) {
                self::saveError(
                    $pdo,
                    (int)$record['id'],
                    'No se pudo verificar el número en ARCA antes del reintento. Sigue incierto. Detalle técnico: '
                        . mb_substr(trim($e->getMessage()) ?: get_class($e), 0, 1000)
                );
                return [
                    'estado' => 'incierto',
                    'mensaje' => 'No se pudo verificar la numeración en ARCA. No se reintentó el envío.',
                    'id' => (int)$record['id'],
                ];
            }

            try {
                $lastVoucher = (int)$billing->GetLastVoucher(
                    (int)$record['punto_venta'],
                    (int)$record['tipo_comprobante']
                );
            } catch (Throwable $e) {
                self::saveError(
                    $pdo,
                    (int)$record['id'],
                    'No se pudo consultar el último número autorizado antes del reintento. Sigue incierto. Detalle técnico: '
                        . mb_substr(trim($e->getMessage()) ?: get_class($e), 0, 1000)
                );
                return [
                    'estado' => 'incierto',
                    'mensaje' => 'No se pudo consultar la numeración actual en ARCA. No se reintentó el envío.',
                    'id' => (int)$record['id'],
                ];
            }
            if ($lastVoucher < 0 || $lastVoucher >= 99999999 || $lastVoucher + 1 !== $number) {
                $message = 'El último número autorizado en ARCA es ' . $lastVoucher
                    . ' y el comprobante reservado es ' . $number
                    . '. No se reintentó para evitar un conflicto de numeración; requiere conciliación.';
                self::saveError($pdo, (int)$record['id'], $message);
                return [
                    'estado' => 'incierto',
                    'mensaje' => $message,
                    'id' => (int)$record['id'],
                ];
            }

            $q = $pdo->prepare(
                "UPDATE comprobantes_arca
                 SET estado = 'enviando', ultimo_error = NULL, fecha_ultimo_envio = NOW()
                 WHERE id = ? AND estado = 'incierto' AND numero_comprobante = ?"
            );
            $q->execute([(int)$record['id'], $number]);
            if ($q->rowCount() !== 1) {
                throw new RuntimeException('El intento cambió de estado antes del reenvío. No se envió a ARCA.');
            }

            try {
                $response = $billing->CreateVoucher($request, true);
            } catch (Throwable $e) {
                $diagnostic = trim($e->getMessage()) ?: get_class($e);
                $rejected = self::isExplicitArcaRejection($e);
                self::setState(
                    $pdo,
                    (int)$record['id'],
                    $rejected ? 'rechazado' : 'incierto',
                    ($rejected
                        ? 'ARCA rechazó el comprobante.'
                        : 'El resultado del reintento no se pudo confirmar. Consulte ARCA antes de continuar.')
                        . ' Detalle técnico: ' . mb_substr($diagnostic, 0, 1000)
                );
                return [
                    'estado' => $rejected ? 'rechazado' : 'incierto',
                    'mensaje' => $rejected
                        ? 'ARCA rechazó el comprobante: ' . $diagnostic
                        : 'El reintento quedó incierto. Se conservó el mismo número reservado; consulte ARCA.',
                    'id' => (int)$record['id'],
                ];
            }

            return self::handleVoucherResponse(
                $pdo,
                self::loadById($pdo, (int)$record['id']),
                $response
            );
        } finally {
            self::unlock($pdo, $lock);
        }
    }

    private static function conciliarBloqueado(PDO $pdo, array $record, ?int $idUsuario = null): array
    {
        $billing = self::billing($record);
        try {
            $voucher = $billing->GetVoucherInfo(
                (int)$record['numero_comprobante'],
                (int)$record['punto_venta'],
                (int)$record['tipo_comprobante']
            );
        } catch (Throwable $e) {
            self::saveError($pdo, (int)$record['id'], 'Falló la consulta de conciliación ARCA. El estado sigue incierto.');
            throw new RuntimeException('No se pudo consultar ARCA. El comprobante sigue incierto y no se volverá a enviar.', 0, $e);
        }

        if (!$voucher) {
            self::saveError($pdo, (int)$record['id'], 'ARCA no informó el número reservado. Se requiere una nueva verificación antes de cualquier reenvío.');
            return [
                'estado' => 'incierto',
                'mensaje' => 'ARCA no informa el número reservado. El intento sigue incierto; no se generó otro envío.',
                'id' => (int)$record['id'],
            ];
        }

        $request = json_decode((string)$record['solicitud_arca'], true);
        if (!is_array($request) || !self::coincide($voucher, $request)) {
            self::saveError($pdo, (int)$record['id'], 'Existe un comprobante ARCA con datos distintos. No se asociará su CAE; requiere revisión manual.');
            return [
                'estado' => 'incierto',
                'mensaje' => 'El número existe en ARCA pero sus datos no coinciden. No se asociará el CAE.',
                'id' => (int)$record['id'],
            ];
        }

        $cae = (string)self::field($voucher, 'CodAutorizacion', self::field($voucher, 'CAE', ''));
        if ($cae === '') {
            self::saveError($pdo, (int)$record['id'], 'ARCA devolvió el comprobante sin CAE. Se mantiene incierto.');
            return [
                'estado' => 'incierto',
                'mensaje' => 'ARCA devolvió el comprobante sin CAE. Se mantiene pendiente de conciliación.',
                'id' => (int)$record['id'],
            ];
        }

        self::authorize(
            $pdo,
            $record,
            $cae,
            self::dateValue(self::field($voucher, 'FchVto', self::field($voucher, 'CAEFchVto', ''))),
            ['conciliado' => true],
            $idUsuario
        );
        return ['estado' => 'autorizado', 'mensaje' => 'Se recuperó y concilió el CAE informado por ARCA.', 'id' => (int)$record['id']];
    }

    private static function procesar(PDO $pdo, array $record): array
    {
        if (!$record) {
            throw new RuntimeException('No se pudo cargar el intento ARCA guardado.');
        }
        $lock = self::lock($pdo, $record);
        try {
            return self::procesarBloqueado($pdo, $record);
        } finally {
            self::unlock($pdo, $lock);
        }
    }

    private static function procesarBloqueado(PDO $pdo, array $record): array
    {
        if ($record['estado'] === 'autorizado') {
            return ['estado' => 'autorizado', 'mensaje' => 'La factura ya está autorizada en ARCA.', 'id' => (int)$record['id']];
        }
        if (in_array($record['estado'], ['enviando', 'incierto'], true)) {
            return self::conciliarBloqueado($pdo, $record);
        }
        if ($record['estado'] === 'rechazado') {
            $localNumberConflict = $record['numero_comprobante'] === null
                && strpos((string)$record['ultimo_error'], 'Conflicto de numeración local para el comprobante propuesto ') === 0;
            if (!$localNumberConflict) {
                return ['estado' => 'rechazado', 'mensaje' => 'ARCA rechazó el comprobante. No se reenviará con los mismos datos.', 'id' => (int)$record['id']];
            }
            $q = $pdo->prepare(
                "UPDATE comprobantes_arca
                 SET estado = 'pendiente', ultimo_error = NULL
                 WHERE id = ? AND estado = 'rechazado' AND numero_comprobante IS NULL"
            );
            $q->execute([(int)$record['id']]);
            if ($q->rowCount() !== 1) {
                throw new RuntimeException('No se pudo reabrir el intento que nunca se envió a ARCA.');
            }
            $record = self::loadById($pdo, (int)$record['id']);
        }

        try {
            $billing = self::billing($record);
            $lastVoucher = (int)$billing->GetLastVoucher((int)$record['punto_venta'], (int)$record['tipo_comprobante']);
        } catch (Throwable $e) {
            self::saveError($pdo, (int)$record['id'], $e->getMessage());
            return [
                'estado' => 'pendiente',
                'mensaje' => 'No se pudo preparar el envío ARCA: ' . $e->getMessage(),
                'id' => (int)$record['id'],
            ];
        }

        if ($lastVoucher < 0 || $lastVoucher >= 99999999) {
            $message = 'El punto de venta ya alcanzó el máximo de numeración admitido para este tipo de comprobante.';
            self::saveError($pdo, (int)$record['id'], $message);
            return ['estado' => 'pendiente', 'mensaje' => $message, 'id' => (int)$record['id']];
        }

        $number = $lastVoucher + 1;
        $payload = self::payload($record, $number);
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare(
                "UPDATE comprobantes_arca
                 SET numero_comprobante = ?, solicitud_arca = ?, estado = 'enviando',
                     ultimo_error = NULL, fecha_ultimo_envio = NOW()
                 WHERE id = ? AND estado = 'pendiente' AND numero_comprobante IS NULL"
            );
            $q->execute([$number, self::encode($payload), $record['id']]);
            if ($q->rowCount() !== 1) {
                throw new RuntimeException('El intento ARCA cambió de estado antes de reservar su número.');
            }
            $q = $pdo->prepare('UPDATE facturas_venta SET numero = ? WHERE id = ?');
            $q->execute([
                str_pad((string)$record['punto_venta'], 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$number, 8, '0', STR_PAD_LEFT),
                $record['id_factura_venta'],
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
                $message = 'Conflicto de numeración local para el comprobante propuesto '
                    . (int)$record['punto_venta'] . '-' . $number . '. No se envió a ARCA; requiere revisión.';
                $q = $pdo->prepare(
                    "UPDATE comprobantes_arca
                     SET estado = 'rechazado', solicitud_arca = ?, ultimo_error = ?
                     WHERE id = ? AND estado = 'pendiente' AND numero_comprobante IS NULL"
                );
                $q->execute([self::encode($payload), $message, $record['id']]);
                return [
                    'estado' => 'rechazado',
                    'mensaje' => $message,
                    'id' => (int)$record['id'],
                ];
            }
            throw $e;
        }

        try {
            $response = $billing->CreateVoucher($payload, true);
        } catch (Throwable $e) {
            $diagnostic = trim($e->getMessage());
            if ($diagnostic === '') {
                $diagnostic = get_class($e);
            }
            $rejected = self::isExplicitArcaRejection($e);
            self::setState(
                $pdo,
                (int)$record['id'],
                $rejected ? 'rechazado' : 'incierto',
                ($rejected
                    ? 'ARCA rechazó el comprobante.'
                    : 'El resultado del envío no se pudo confirmar. Consulte ARCA antes de reintentar.')
                    . ' Detalle técnico: ' . mb_substr($diagnostic, 0, 1000)
            );
            return [
                'estado' => $rejected ? 'rechazado' : 'incierto',
                'mensaje' => $rejected
                    ? 'ARCA rechazó el comprobante: ' . $diagnostic
                    : 'No se pudo confirmar la respuesta del envío. El número quedó reservado; consulte ARCA antes de reintentar.',
                'id' => (int)$record['id'],
            ];
        }

        return self::handleVoucherResponse($pdo, $record, $response);
    }

    private static function handleVoucherResponse(PDO $pdo, array $record, $response): array
    {
        $detail = self::field(self::field($response, 'FeDetResp', null), 'FECAEDetResponse', null);
        $cae = (string)self::field($detail, 'CAE', '');
        $observations = self::field($detail, 'Observaciones', null);
        $errors = self::field($response, 'Errors', null);
        $summary = [
            'resultado' => self::field($detail, 'Resultado', ''),
            'observaciones' => $observations,
            'errores' => $errors,
        ];

        if ($cae !== '') {
            self::authorize(
                $pdo,
                $record,
                $cae,
                self::dateValue(self::field($detail, 'CAEFchVto', '')),
                $summary
            );
            return ['estado' => 'autorizado', 'mensaje' => 'La factura fue autorizada por ARCA.', 'id' => (int)$record['id']];
        }

        $message = self::responseMessage($summary);
        $state = ($errors !== null || self::field($detail, 'Resultado', '') === 'R') ? 'rechazado' : 'incierto';
        self::setState($pdo, (int)$record['id'], $state, $message, $summary);
        return [
            'estado' => $state,
            'mensaje' => $state === 'rechazado'
                ? 'ARCA rechazó el comprobante: ' . $message
                : 'ARCA no confirmó la autorización. El intento sigue incierto y no se reenviará.',
            'id' => (int)$record['id'],
        ];
    }

    private static function prepare(PDO $pdo, array $invoice, array $input): array
    {
        $letter = strtoupper(trim((string)$invoice['letra']));
        $typeName = self::normalize((string)$invoice['tipo_comprobante']);
        $unsupportedType = strpos($typeName, 'credito') !== false
            || strpos($typeName, 'mipyme') !== false
            || strpos($typeName, 'export') !== false;
        if (strpos($typeName, 'factura') !== 0 || $unsupportedType || !isset(['A' => 1, 'B' => 6, 'C' => 11][$letter])) {
            throw new RuntimeException('Solo se emiten facturas locales A, B o C comunes. Exportación, crédito electrónico y notas requieren su circuito fiscal propio.');
        }

        $issuerConditionId = (int)$invoice['emisor_condicion_iva_id'];
        if (($issuerConditionId === 1 && $letter === 'C') || ($issuerConditionId === 6 && $letter !== 'C')) {
            throw new RuntimeException('La letra del comprobante no coincide con la condición fiscal de la empresa emisora.');
        }
        if (!in_array($issuerConditionId, [1, 6], true)) {
            throw new RuntimeException('La condición fiscal de la empresa emisora no está configurada para emitir por ARCA.');
        }

        $cuitEmisor = self::digits((string)$invoice['emisor_cuit']);
        if (!self::validCuit($cuitEmisor)) {
            throw new RuntimeException('Complete un CUIT válido en la ficha de la empresa emisora antes de emitir.');
        }
        if (trim((string)$invoice['emisor_razon_social']) === ''
            || trim((string)$invoice['emisor_direccion']) === ''
            || trim((string)($invoice['emisor_iibb'] ?? '')) === ''
            || empty($invoice['emisor_fecha_inicio_actividades'])) {
            throw new RuntimeException('Complete razón social, domicilio, Ingresos Brutos y fecha de inicio de actividades de la empresa emisora.');
        }
        self::validDate((string)$invoice['emisor_fecha_inicio_actividades'], 'fecha de inicio de actividades del emisor');
        $pointOfSale = self::parsePointOfSale((string)$invoice['numero']);
        if ($pointOfSale < 1 || $pointOfSale > 99998) {
            throw new RuntimeException('El punto de venta de la factura debe estar entre 1 y 99998.');
        }
        $voucherTypeCode = ['A' => 1, 'B' => 6, 'C' => 11][$letter];
        $configuredType = $pdo->prepare(
            'SELECT COUNT(*)
             FROM arca_puntos_venta p
             INNER JOIN arca_punto_venta_tipos t ON t.id_punto_venta = p.id
             WHERE p.id_empresa = ? AND p.numero = ? AND p.bloqueado = 0
               AND p.fecha_baja IS NULL AND t.codigo_tipo_comprobante = ?'
        );
        $configuredType->execute([(int)$invoice['id_empresa'], $pointOfSale, $voucherTypeCode]);
        if ((int)$configuredType->fetchColumn() === 0) {
            throw new RuntimeException('El punto de venta y el tipo de comprobante deben estar configurados para esta empresa antes de emitir por ARCA.');
        }

        $date = self::validDate((string)$invoice['fecha_emitida'], 'fecha de emisión');
        if (strtotime($date) > strtotime(date('Y-m-d'))) {
            throw new RuntimeException('La fecha de emisión no puede ser futura.');
        }

        $concept = filter_var($input['concepto_arca'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($concept, [1, 2, 3], true)) {
            throw new RuntimeException('Seleccione si la factura corresponde a productos, servicios o ambos.');
        }
        $serviceFrom = $serviceTo = $paymentDue = null;
        if ($concept !== 1) {
            $serviceFrom = self::validDate((string)($input['fecha_servicio_desde'] ?? ''), 'inicio del período de servicio');
            $serviceTo = self::validDate((string)($input['fecha_servicio_hasta'] ?? ''), 'fin del período de servicio');
            $paymentDue = self::validDate((string)($input['fecha_vencimiento_pago'] ?? ''), 'vencimiento de pago');
            if ($serviceFrom > $serviceTo) {
                throw new RuntimeException('El inicio del período de servicio no puede ser posterior al fin.');
            }
            if ($paymentDue < $date) {
                throw new RuntimeException('El vencimiento de pago no puede ser anterior a la fecha de emisión.');
            }
        }

        $conditionId = (int)$invoice['receptor_condicion_iva_id'];
        $receiverVat = [1 => 1, 3 => 5, 5 => 4, 6 => 6, 7 => 7][$conditionId] ?? null;
        if ($receiverVat === null) {
            throw new RuntimeException('La condición IVA del cliente no tiene una correspondencia ARCA confirmada. Actualice su ficha antes de emitir.');
        }
        if (trim((string)$invoice['receptor_razon_social']) === ''
            || trim((string)($invoice['receptor_direccion'] ?? '')) === '') {
            throw new RuntimeException('Complete razón social y domicilio del cliente antes de emitir.');
        }
        $receiverDocument = self::digits((string)$invoice['receptor_cuit']);
        if ($receiverDocument !== '' && preg_match('/^0+$/D', $receiverDocument)) {
            $receiverDocument = '';
        }
        if (strlen($receiverDocument) === 11 && self::validCuit($receiverDocument)) {
            $receiverDocumentType = 80;
        } elseif (in_array(strlen($receiverDocument), [7, 8], true) && $conditionId === 3) {
            $receiverDocumentType = 96;
        } elseif ($receiverDocument === '' && $conditionId === 3) {
            $receiverDocument = '0';
            $receiverDocumentType = 99;
        } else {
            throw new RuntimeException('El documento del cliente debe ser un CUIT válido o, para consumidor final, un DNI válido.');
        }
        if ($letter === 'A' && ($receiverVat !== 1 || $receiverDocumentType !== 80)) {
            throw new RuntimeException('Una factura A requiere un receptor Responsable Inscripto.');
        }

        $currency = self::currency((string)$invoice['moneda']);
        $exchangeRate = (float)$invoice['cotizacion'];
        if ($currency === 'PES') {
            $exchangeRate = 1;
        } elseif ($exchangeRate <= 0) {
            throw new RuntimeException('La cotización de la moneda debe ser mayor que cero.');
        }

        $q = $pdo->prepare(
            "SELECT d.id, d.descripcion, d.cantidad, d.precio, d.subtotal,
                    d.porcentaje_descuento, cc.codigo AS concepto_codigo,
                    cc.descripcion AS concepto_text, COALESCE(ti.tasa, 21) AS tasa_iva
             FROM facturas_venta_detalle d
             INNER JOIN conceptos_contables cc ON cc.id = d.id_concepto_contable
             LEFT JOIN tipos_iva ti ON ti.id = cc.id_alicuota_iva
             WHERE d.id_factura_venta = ? ORDER BY d.id"
        );
        $q->execute([(int)$invoice['id']]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            throw new RuntimeException('Agregue al menos un renglón antes de emitir la factura.');
        }

        $unmappedRegimes = $pdo->prepare(
            'SELECT COUNT(*)
             FROM facturas_venta_otros o
             WHERE o.id_factura_venta = ?
               AND NOT EXISTS (
                   SELECT 1
                   FROM facturas_venta_retenciones r
                   WHERE r.id_factura_venta = o.id_factura_venta
                     AND r.regimen_text <=> o.regimen_text
                     AND r.codigo <=> o.codigo
                     AND r.articulo <=> o.articulo
               )'
        );
        $unmappedRegimes->execute([(int)$invoice['id']]);
        if ((int)$unmappedRegimes->fetchColumn() > 0) {
            throw new RuntimeException('La factura tiene regímenes fiscales sin correspondencia ARCA configurada. No se omitieron ni se enviaron como tributos incorrectos.');
        }

        $ivaByCode = [];
        $items = [];
        $grossCents = 0;
        foreach ($rows as $row) {
            $quantity = (float)$row['cantidad'];
            $discount = (float)($row['porcentaje_descuento'] ?? 0);
            $subtotalCents = (int)round((float)$row['subtotal'] * 100);
            if ($quantity <= 0 || $subtotalCents < 0 || $discount < 0 || $discount > 100) {
                throw new RuntimeException('Hay un renglón con cantidad, importe o descuento inválido.');
            }
            $rate = $letter === 'C' ? 0.0 : (float)$row['tasa_iva'];
            if ($letter !== 'C' && abs($rate) < 0.0001) {
                throw new RuntimeException('No se puede emitir un renglón al 0% sin distinguir si es gravado, exento o no gravado. Configure su clasificación fiscal antes de emitir.');
            }
            $code = self::aliquotCode($rate);
            $grossCents += $subtotalCents;
            if (!isset($ivaByCode[$code])) {
                $ivaByCode[$code] = ['base' => 0, 'importe' => 0, 'tasa' => $rate];
            }
            $ivaByCode[$code]['base'] += $subtotalCents;

            $factor = ($rate > 0) ? (1 + $rate / 100) : 1;
            $netPrice = ((float)$row['precio'] > 0) ? round((float)$row['precio'] / $factor, 2) : 0;
            $netBase = round((float)$row['subtotal'] / $factor, 2);

            $projectCode = (string)($invoice['proyecto_codigo'] ?? '');
            $items[] = [
                'codigo' => (string)$row['concepto_codigo'],
                'ncm' => '73.08.20.00',
                'proyecto' => $projectCode,
                'concepto' => (string)$row['concepto_text'],
                'descripcion' => (string)($row['descripcion'] ?: $row['concepto_text']),
                'cantidad' => $quantity,
                'precio_unitario' => $netPrice,
                'descuento_porcentaje' => $discount,
                'base_imponible' => $netBase,
                'codigo_iva_arca' => $code,
                'alicuota_iva' => $rate,
            ];
        }

        $ivaCents = 0;
        $netCents = 0;
        foreach ($ivaByCode as $code => &$amounts) {
            $t = (float)$amounts['tasa'];
            $grossBaseCents = (int)$amounts['base'];
            $ivaPartCents = ($t > 0) ? (int)round($grossBaseCents * $t / (100 + $t)) : 0;
            $netBaseCents = $grossBaseCents - $ivaPartCents;
            $amounts['base'] = $netBaseCents;
            $amounts['importe'] = $ivaPartCents;
            $ivaCents += $ivaPartCents;
            $netCents += $netBaseCents;
        }
        unset($amounts);

        $number = $receiverDocument;
        $companyCond = (string)$invoice['emisor_condicion_iva'];
        $receiverCond = (string)$invoice['receptor_condicion_iva'];

        return [
            'emisor' => [
                'razon_social' => (string)$invoice['emisor_razon_social'],
                'cuit' => $cuitEmisor,
                'domicilio' => (string)$invoice['emisor_direccion'],
                'email' => (string)($invoice['emisor_email'] ?? ''),
                'telefono' => '',
                'ingresos_brutos' => (string)($invoice['emisor_iibb'] ?? ''),
                'fecha_inicio_actividades' => (string)($invoice['emisor_fecha_inicio_actividades'] ?? ''),
                'condicion_iva' => $companyCond,
            ],
            'receptor' => [
                'razon_social' => (string)$invoice['receptor_razon_social'],
                'cuit' => $receiverDocumentType === 80 ? $number : '',
                'domicilio' => (string)($invoice['receptor_direccion'] ?? ''),
                'email' => (string)($invoice['receptor_email'] ?? ''),
                'condicion_iva' => $receiverCond,
                'condicion_iva_arca' => $receiverVat,
                'tipo_documento' => $receiverDocumentType,
                'numero_documento' => $number,
            ],
            'punto_venta' => $pointOfSale,
            'tipo_comprobante' => $voucherTypeCode,
            'letra' => $letter,
            'fecha_comprobante' => $date,
            'concepto' => $concept,
            'fecha_servicio_desde' => $serviceFrom,
            'fecha_servicio_hasta' => $serviceTo,
            'fecha_vencimiento_pago' => $paymentDue,
            'moneda' => $currency,
            'moneda_texto' => (string)$invoice['moneda'],
            'cotizacion' => $exchangeRate,
            'total_centavos' => $netCents + $ivaCents,
            'neto_centavos' => $netCents,
            'iva_centavos' => $ivaCents,
            'no_gravado_centavos' => 0,
            'exento_centavos' => 0,
            'tributos_centavos' => 0,
            'iva_por_codigo' => $ivaByCode,
            'detalle' => [
                'version' => 1,
                'letra' => $letter,
                'tipo_comprobante_nombre' => (string)$invoice['tipo_comprobante'],
                'ncm' => '73.08.20.00',
                'proyecto' => (string)($invoice['proyecto_codigo'] ?? ''),
                'observaciones' => (string)($invoice['observaciones'] ?? ''),
                'moneda_texto' => (string)$invoice['moneda'],
                'condicion_venta' => (string)($invoice['condicion_venta'] ?? ''),
                'items' => $items,
            ],
        ];
    }

    private static function payload(array $record, int $number): array
    {
        $issuer = json_decode((string)$record['datos_emisor'], true);
        $receiver = json_decode((string)$record['datos_receptor'], true);
        $detail = json_decode((string)$record['detalle_comprobante'], true);
        $request = [
            'CantReg' => 1,
            'PtoVta' => (int)$record['punto_venta'],
            'CbteTipo' => (int)$record['tipo_comprobante'],
            'Concepto' => (int)$record['concepto'],
            'DocTipo' => (int)$record['receptor_tipo_documento'],
            'DocNro' => (string)$record['receptor_numero_documento'],
            'CbteDesde' => $number,
            'CbteHasta' => $number,
            'CbteFch' => date('Ymd', strtotime((string)$record['fecha_comprobante'])),
            'ImpTotal' => (float)$record['importe_total'],
            'ImpTotConc' => (float)$record['importe_no_gravado'],
            'ImpNeto' => (float)$record['importe_neto'],
            'ImpOpEx' => (float)$record['importe_exento'],
            'ImpIVA' => (float)$record['importe_iva'],
            'ImpTrib' => (float)$record['importe_tributos'],
            'MonId' => (string)$record['moneda'],
            'MonCotiz' => (float)$record['cotizacion'],
            'CondicionIVAReceptorId' => (int)$record['receptor_condicion_iva'],
        ];
        if ((int)$record['concepto'] !== 1) {
            $request['FchServDesde'] = date('Ymd', strtotime((string)$record['fecha_servicio_desde']));
            $request['FchServHasta'] = date('Ymd', strtotime((string)$record['fecha_servicio_hasta']));
            $request['FchVtoPago'] = date('Ymd', strtotime((string)$record['fecha_vencimiento_pago']));
        }

        $ivaRows = self::ivaRowsFromSnapshot($detail);
        if ($ivaRows && (int)$record['tipo_comprobante'] !== 11) {
            $request['Iva'] = $ivaRows;
        }
        if (isset($issuer['cuit'], $receiver['numero_documento'])) {
            return $request;
        }
        throw new RuntimeException('El comprobante guardado no contiene sus datos fiscales históricos.');
    }

    private static function ivaRowsFromSnapshot(array $detail): array
    {
        $groups = [];
        foreach (($detail['items'] ?? []) as $item) {
            $code = (int)$item['codigo_iva_arca'];
            if (!isset($groups[$code])) {
                $groups[$code] = ['base' => 0, 'rate' => (float)$item['alicuota_iva']];
            }
            $groups[$code]['base'] += (int)round((float)$item['base_imponible'] * 100);
        }
        $rows = [];
        foreach ($groups as $code => $group) {
            $rows[] = [
                'Id' => (int)$code,
                'BaseImp' => self::money($group['base']),
                'Importe' => self::money((int)round($group['base'] * $group['rate'] / 100)),
            ];
        }
        return $rows;
    }

    private static function billing(array $record)
    {
        if (!class_exists('SoapClient')) {
            throw new RuntimeException('El módulo PHP SOAP no está habilitado en el servidor.');
        }
        $issuer = json_decode((string)$record['datos_emisor'], true);
        $companyId = (int)$record['id_empresa'];
        $mode = (int)$record['homologacion'] === 1 ? 'HOMOLOGACION' : 'PRODUCCION';
        $cert = getenv('ARCA_CERT_EMPRESA_' . $companyId . '_' . $mode);
        $key = getenv('ARCA_KEY_EMPRESA_' . $companyId . '_' . $mode);
        if (!$cert || !$key || !is_readable($cert) || !is_readable($key)) {
            throw new RuntimeException('Faltan el certificado o la clave privada ARCA de la empresa emisora en la configuración del servidor.');
        }
        if (realpath(dirname($cert)) !== realpath(dirname($key))) {
            throw new RuntimeException('El certificado y la clave privada ARCA deben estar en el mismo directorio de recursos.');
        }
        if (!is_file(self::SDK_DIR . '/Afip.php')) {
            throw new RuntimeException('No se encontró el SDK ARCA indicado en la documentación del proyecto.');
        }

        $resourceDir = rtrim(dirname($cert), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach (['wsaa.wsdl', 'wsfe.wsdl', 'wsfe-production.wsdl'] as $wsdl) {
            $destination = $resourceDir . $wsdl;
            $source = self::SDK_DIR . '/Afip_res/' . $wsdl;
            if (!is_file($destination) && !copy($source, $destination)) {
                throw new RuntimeException('No se pudo preparar el WSDL ARCA requerido: ' . $wsdl);
            }
        }

        $taDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gruponh_arca_ta'
            . DIRECTORY_SEPARATOR . $mode . DIRECTORY_SEPARATOR . $issuer['cuit'] . DIRECTORY_SEPARATOR;
        if (!is_dir($taDirectory) && !mkdir($taDirectory, 0700, true) && !is_dir($taDirectory)) {
            throw new RuntimeException('No se pudo crear el directorio privado temporal de tickets ARCA.');
        }

        require_once self::SDK_DIR . '/Afip.php';
        $options = [
            'CUIT' => $issuer['cuit'],
            'production' => $mode === 'PRODUCCION',
            'exceptions' => true,
            'res_folder' => $resourceDir,
            'ta_folder' => $taDirectory,
            'cert' => basename($cert),
            'key' => basename($key),
        ];
        $afip = new Afip($options);
        return $afip->ElectronicBilling;
    }

    private static function loadInvoice(PDO $pdo, int $id): ?array
    {
        $q = $pdo->prepare(
            "SELECT fv.*, tc.tipo AS tipo_comprobante, lc.letra, m.moneda,
                    CASE WHEN p.id IS NULL THEN '' ELSE CONCAT(
                        COALESCE(s.nro_sitio, ''), '_',
                        COALESCE(s.nro_subsitio, ''), '_',
                        COALESCE(p.nro, '')
                    ) END AS proyecto_codigo,
                    e.id AS id_empresa, e.empresa AS emisor_razon_social, e.cuit AS emisor_cuit,
                    e.direccion AS emisor_direccion, e.email AS emisor_email, e.iibb AS emisor_iibb,
                    e.fecha_inicio_actividades AS emisor_fecha_inicio_actividades,
                    e.id_condicion_iva AS emisor_condicion_iva_id, ec.condicion_iva AS emisor_condicion_iva,
                    fp.forma_pago AS condicion_venta,
                    c.razon_social AS receptor_razon_social, c.cuit AS receptor_cuit,
                    c.direccion AS receptor_direccion, c.email AS receptor_email,
                    c.id_condicion_iva AS receptor_condicion_iva_id, rc.condicion_iva AS receptor_condicion_iva
             FROM facturas_venta fv
             INNER JOIN tipos_comprobante tc ON tc.id = fv.id_tipo_comprobante
             INNER JOIN letras_comprobante lc ON lc.id = fv.id_letra_comprobante
             INNER JOIN monedas m ON m.id = fv.id_moneda
             INNER JOIN empresas e ON e.id = fv.id_empresa
             LEFT JOIN proyectos p ON p.id = fv.id_proyecto
             LEFT JOIN sitios s ON s.id = p.id_sitio
             LEFT JOIN condiciones_iva ec ON ec.id = e.id_condicion_iva
             LEFT JOIN formas_pago fp ON fp.id = fv.id_condicion_pago
             INNER JOIN cuentas c ON c.id = fv.id_cuenta_destino
             LEFT JOIN condiciones_iva rc ON rc.id = c.id_condicion_iva
             WHERE fv.id = ?"
        );
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function loadByInvoice(PDO $pdo, int $idFactura): ?array
    {
        $q = $pdo->prepare(
            'SELECT ca.*, fv.id_empresa FROM comprobantes_arca ca
             INNER JOIN facturas_venta fv ON fv.id = ca.id_factura_venta
             WHERE ca.id_factura_venta = ? ORDER BY ca.id DESC LIMIT 1'
        );
        $q->execute([$idFactura]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function loadById(PDO $pdo, int $id): ?array
    {
        $q = $pdo->prepare(
            'SELECT ca.*, fv.id_empresa FROM comprobantes_arca ca
             INNER JOIN facturas_venta fv ON fv.id = ca.id_factura_venta WHERE ca.id = ?'
        );
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function authorize(PDO $pdo, array $record, string $cae, string $expires, array $summary, ?int $idUsuario = null): void
    {
        if (!preg_match('/^\d{14}$/D', $cae)) {
            throw new RuntimeException('ARCA devolvió un CAE con formato inválido; el comprobante requiere conciliación.');
        }
        $expiry = self::validDate($expires, 'vencimiento del CAE');
        $previousResponse = json_decode((string)($record['respuesta_arca'] ?? ''), true);
        if (is_array($previousResponse)) {
            $summary = array_merge($previousResponse, $summary);
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare(
                "UPDATE comprobantes_arca
                 SET estado = 'autorizado', cae = ?, fecha_vencimiento_cae = ?,
                     respuesta_arca = ?, ultimo_error = NULL, fecha_autorizacion = NOW(),
                     fecha_conciliacion = IF(? IS NULL, fecha_conciliacion, NOW()),
                     usuario_conciliacion = IF(? IS NULL, usuario_conciliacion, ?)
                 WHERE id = ? AND estado IN ('enviando', 'incierto')"
            );
            $q->execute([
                $cae,
                $expiry,
                self::encode($summary),
                $idUsuario,
                $idUsuario,
                $idUsuario,
                $record['id'],
            ]);
            if ($q->rowCount() !== 1 && $record['estado'] !== 'autorizado') {
                throw new RuntimeException('El CAE recibido no pudo persistirse en la base local. Concilie el número reservado antes de continuar.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function setState(PDO $pdo, int $id, string $state, string $message, ?array $summary = null): void
    {
        $q = $pdo->prepare(
            'UPDATE comprobantes_arca SET estado = ?, ultimo_error = ?, respuesta_arca = COALESCE(?, respuesta_arca)
             WHERE id = ?'
        );
        $q->execute([$state, mb_substr($message, 0, 4000), $summary ? self::encode($summary) : null, $id]);
    }

    private static function saveError(PDO $pdo, int $id, string $message): void
    {
        $q = $pdo->prepare('UPDATE comprobantes_arca SET ultimo_error = ? WHERE id = ?');
        $q->execute([mb_substr($message, 0, 4000), $id]);
    }

    private static function isExplicitArcaRejection(Throwable $exception): bool
    {
        return $exception->getCode() > 0
            && preg_match('/^\(\d+\)\s+/D', trim($exception->getMessage())) === 1;
    }

    private static function coincide($voucher, array $request): bool
    {
        foreach ([
            'CbteTipo' => 'CbteTipo',
            'PtoVta' => 'PtoVta',
            'CbteDesde' => 'CbteNro',
            'DocTipo' => 'DocTipo',
            'DocNro' => 'DocNro',
            'CbteFch' => 'CbteFch',
            'MonId' => 'MonId',
            'CondicionIVAReceptorId' => 'CondicionIVAReceptorId',
        ] as $requestKey => $voucherKey) {
            if (!array_key_exists($requestKey, $request)
                || (string)self::field($voucher, $voucherKey, '') !== (string)$request[$requestKey]) {
                return false;
            }
        }
        foreach (['FchServDesde', 'FchServHasta', 'FchVtoPago'] as $key) {
            if (isset($request[$key]) && (string)self::field($voucher, $key, '') !== (string)$request[$key]) {
                return false;
            }
        }
        foreach (['ImpTotal', 'ImpTotConc', 'ImpNeto', 'ImpOpEx', 'ImpIVA', 'ImpTrib', 'MonCotiz'] as $key) {
            if (abs((float)self::field($voucher, $key, -999999) - (float)$request[$key]) > 0.01) {
                return false;
            }
        }
        return self::ivaCoincide(self::field($voucher, 'Iva', null), $request['Iva'] ?? []);
    }

    private static function ivaCoincide($voucherIva, array $requestIva): bool
    {
        $voucherRows = self::field($voucherIva, 'AlicIva', []);
        if (!is_array($voucherRows)) {
            $voucherRows = $voucherRows ? [$voucherRows] : [];
        }
        $normalize = static function (array $rows): array {
            $normalized = [];
            foreach ($rows as $row) {
                $code = (int)self::field($row, 'Id', -1);
                $normalized[$code] = [
                    (float)self::field($row, 'BaseImp', -1),
                    (float)self::field($row, 'Importe', -1),
                ];
            }
            ksort($normalized);
            return $normalized;
        };
        $expected = $normalize($requestIva);
        $actual = $normalize($voucherRows);
        if (array_keys($expected) !== array_keys($actual)) {
            return false;
        }
        foreach ($expected as $code => $amounts) {
            if (abs($amounts[0] - $actual[$code][0]) > 0.01 || abs($amounts[1] - $actual[$code][1]) > 0.01) {
                return false;
            }
        }
        return true;
    }

    private static function responseMessage(array $summary): string
    {
        $messages = [];
        foreach ([$summary['errores'], $summary['observaciones']] as $group) {
            $entries = self::field($group, 'Err', self::field($group, 'Obs', $group));
            foreach (is_array($entries) ? $entries : [$entries] as $entry) {
                $message = self::field($entry, 'Msg', '');
                if ($message !== '') {
                    $messages[] = (string)$message;
                }
            }
        }
        return $messages ? implode(' | ', $messages) : 'ARCA no devolvió CAE ni detalle de rechazo.';
    }

    private static function field($value, string $key, $default = null)
    {
        if (is_array($value)) {
            return $value[$key] ?? $default;
        }
        if (is_object($value)) {
            return $value->{$key} ?? $default;
        }
        return $default;
    }

    private static function listResponse($value): array
    {
        if (is_array($value)) {
            if (array_key_exists('Nro', $value) || array_key_exists('Id', $value)) {
                return [$value];
            }
            return array_values($value);
        }
        return is_object($value) ? [$value] : [];
    }

    private static function aliquotCode(float $rate): int
    {
        foreach (self::ALIQUOT_CODES as [$allowedRate, $code]) {
            if (abs($rate - $allowedRate) < 0.0001) {
                return $code;
            }
        }
        throw new RuntimeException('La alícuota IVA de un concepto no tiene código ARCA soportado: ' . $rate . '%.');
    }

    private static function currency(string $currency): string
    {
        $normalized = self::normalize($currency);
        if (in_array($normalized, ['$', 'peso', 'pesos', 'peso argentino', 'pesos argentinos', 'ars'], true)) {
            return 'PES';
        }
        if (in_array($normalized, ['dolar', 'dolares', 'dolar estadounidense', 'dolares estadounidenses', 'usd', 'u$s', 'u$d'], true)) {
            return 'DOL';
        }
        throw new RuntimeException('La moneda seleccionada todavía no está habilitada para emisión ARCA.');
    }

    private static function parsePointOfSale(string $number): int
    {
        if (!preg_match('/^\s*(\d{1,5})\s*-\s*\d+\s*$/D', $number, $matches)) {
            throw new RuntimeException('La factura debe tener un punto de venta numérico válido.');
        }
        return (int)$matches[1];
    }

    private static function validCuit(string $cuit): bool
    {
        if (!preg_match('/^\d{11}$/D', $cuit)) {
            return false;
        }
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += (int)$cuit[$index] * $weight;
        }
        $digit = 11 - ($sum % 11);
        if ($digit === 11) {
            $digit = 0;
        } elseif ($digit === 10) {
            $digit = 9;
        }
        return $digit === (int)$cuit[10];
    }

    private static function validDate(string $date, string $label): string
    {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException('Indique una ' . $label . ' válida.');
        }
        return $date;
    }

    private static function dateValue($value): string
    {
        $value = (string)$value;
        if (preg_match('/^\d{8}$/D', $value)) {
            $date = DateTime::createFromFormat('!Ymd', $value);
            return $date ? $date->format('Y-m-d') : '';
        }
        return $value;
    }

    private static function lock(PDO $pdo, array $record): string
    {
        $resource = 'arca_' . substr(hash(
            'sha256',
            (int)$record['homologacion'] . '|' . $record['emisor_cuit'] . '|'
            . (int)$record['punto_venta'] . '|' . (int)$record['tipo_comprobante']
        ), 0, 48);
        $q = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $q->execute([$resource]);
        if ((int)$q->fetchColumn() !== 1) {
            throw new RuntimeException('Otra operación está utilizando esta numeración ARCA. Consulte e intente nuevamente.');
        }
        return $resource;
    }

    private static function unlock(PDO $pdo, string $resource): void
    {
        $q = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $q->execute([$resource]);
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return $ascii === false ? $value : $ascii;
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }

    private static function money(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    private static function encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function homologacion(): int
    {
        return getenv('ARCA_HOMOLOGACION') === '0' ? 0 : 1;
    }
}
