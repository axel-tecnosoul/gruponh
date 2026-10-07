<?php
require("config.php");
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
  echo json_encode([
    'ok' => false,
    'message' => 'Usuario no autenticado'
  ]);
  exit;
}

require_once 'database.php';
require_once 'vendor/afip/Afip.php';
require_once 'config_facturacion_electronica.php';

$CUIT_REPRESENTADA_PADRON = '20351290340';

function normalizarCuit($value)
{
  return preg_replace('/\D+/', '', trim((string)$value));
}

function respuesta($ok, $message, $data = null)
{
  echo json_encode([
    'ok' => $ok,
    'message' => $message,
    'data' => $data
  ]);
  exit;
}

$cuitCliente = normalizarCuit($_POST['cuit'] ?? '');
if ($cuitCliente === '' || strlen($cuitCliente) !== 11) {
  respuesta(false, 'CUIT inválido');
}

try {
  // Siempre consultar padron con esta CUIT representada
  $aInitializeAFIP['CUIT'] = $CUIT_REPRESENTADA_PADRON;

  $afip = new Afip($aInitializeAFIP);
  $persona = $afip->RegisterScopeThirteen->GetTaxpayerDetails((int)$cuitCliente);

  if (!$persona) {
    respuesta(false, 'No se encontraron datos en padrón A13');
  }

  $razonSocial = trim((string)($persona->razonSocial ?? ''));
  $nombre = trim((string)($persona->nombre ?? ''));
  $apellido = trim((string)($persona->apellido ?? ''));
  $tipoPersona = trim((string)($persona->tipoPersona ?? ''));
  $descripcionActividadPrincipal = trim((string)($persona->descripcionActividadPrincipal ?? ''));

  $domicilio = '';
  if (!empty($persona->domicilio)) {
    if (is_array($persona->domicilio) && !empty($persona->domicilio[0])) {
      $domicilio = trim((string)($persona->domicilio[0]->direccion ?? ''));
    } elseif (is_object($persona->domicilio)) {
      $domicilio = trim((string)($persona->domicilio->direccion ?? ''));
    }
  }

  if ($razonSocial === '') {
    $razonSocial = trim($apellido . ' ' . $nombre);
  }

  $nombreSugerido = trim($nombre . ' ' . $apellido);
  if ($nombreSugerido === '') {
    $nombreSugerido = $razonSocial;
  }

  if ($razonSocial === '') {
    respuesta(false, 'Padrón A13 respondió sin razón social/nombre');
  }

  $condFiscalDesc = '';
  $condFiscalId = '';
  if (strtoupper($tipoPersona) === 'JURIDICA') {
    $condFiscalDesc = 'IVA Responsable Inscripto';
    $condFiscalId = 1;
  } elseif (strtoupper($tipoPersona) === 'FISICA') {
    $condFiscalDesc = 'Consumidor Final';
    $condFiscalId = 5;
  }

  respuesta(true, 'Datos obtenidos correctamente', [
    'cuit' => $cuitCliente,
    'razon_social' => $razonSocial,
    'nombre_sugerido' => $nombreSugerido,
    'tipo_persona' => $tipoPersona,
    'cond_fiscal_sugerida' => $condFiscalDesc,
    'id_cond_fiscal_sugerida' => $condFiscalId,
    'direccion_sugerida' => $domicilio,
    'actividad_principal' => $descripcionActividadPrincipal,
    'raw' => [
      'nombre' => $nombre,
      'apellido' => $apellido,
      'razonSocial' => (string)($persona->razonSocial ?? '')
    ]
  ]);
} catch (Exception $e) {
  respuesta(false, $e->getMessage());
}
