<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['user'])) {
    responderJson(401, ['error' => 'La sesión expiró. Inicie sesión nuevamente.']);
}

$fecha = $_GET['fecha'] ?? '';
if (!is_string($fecha) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $fecha, $partes)
    || !checkdate((int)$partes[2], (int)$partes[3], (int)$partes[1])) {
    responderJson(400, ['error' => 'La fecha solicitada no es válida.']);
}

$url = 'https://api.bcra.gob.ar/estadisticascambiarias/v1.0/Cotizaciones/USD'
    . '?fechaDesde=' . rawurlencode($fecha)
    . '&fechaHasta=' . rawurlencode($fecha);

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$body = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($body === false || $httpStatus < 200 || $httpStatus >= 300) {
    error_log('Error consultando cotización divisa BCRA: ' . ($curlError ?: 'HTTP ' . $httpStatus));
    responderJson(502, ['error' => 'No se pudo consultar la cotización del BCRA.']);
}

$respuesta = json_decode($body, true);
if (!is_array($respuesta) || !isset($respuesta['results']) || !is_array($respuesta['results'])) {
    error_log('Respuesta inválida de la API de cotizaciones del BCRA.');
    responderJson(502, ['error' => 'El BCRA devolvió una respuesta inválida.']);
}

foreach ($respuesta['results'] as $resultado) {
    if (($resultado['fecha'] ?? null) !== $fecha || !isset($resultado['detalle']) || !is_array($resultado['detalle'])) {
        continue;
    }
    foreach ($resultado['detalle'] as $detalle) {
        $valor = $detalle['tipoCotizacion'] ?? null;
        if (($detalle['codigoMoneda'] ?? null) === 'USD' && is_numeric($valor) && (float)$valor > 0) {
            responderJson(200, [
                'fecha' => $fecha,
                'cotizacion' => (float)$valor,
            ]);
        }
    }
}

responderJson(404, ['error' => 'No hay cotización del dólar divisa para esa fecha.']);
