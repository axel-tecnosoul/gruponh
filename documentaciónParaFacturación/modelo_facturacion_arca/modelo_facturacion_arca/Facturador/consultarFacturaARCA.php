<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

require_once 'database.php';
header('Cache-Control: no-store');

$id = false;
$hayError = false;
$informe = [];
$salidaConsulta = '';
if (isset($_GET['id']) && is_string($_GET['id'])) {
  $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

try {
  if ($id === false) {
    http_response_code(400);
    throw new RuntimeException('Indique un ID de factura válido en la URL: consultarFacturaARCA.php?id=123');
  }

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $consulta = $pdo->prepare('SELECT id, emisor_cuit, homologacion, punto_venta, tipo_comprobante, numero_comprobante, cae, condicion_iva_receptor, estado FROM facturas WHERE id = ?');
  $consulta->execute([$id]);
  $factura = $consulta->fetch(PDO::FETCH_ASSOC);
  Database::disconnect();

  if (!$factura) {
    http_response_code(404);
    throw new RuntimeException('No se encontró una factura con ese ID.');
  }
  $informe['registro_local'] = $factura;

  // Usar los datos históricos del comprobante, no los datos actuales del emisor.
  http_response_code(422);
  if (!is_scalar($factura['emisor_cuit']) || !preg_match('/^\d{11}$/D', (string)$factura['emisor_cuit'])) {
    throw new RuntimeException('El comprobante no tiene un CUIT emisor válido registrado.');
  }
  if (!in_array($factura['homologacion'], [0, 1, '0', '1'], true)) {
    throw new RuntimeException('El comprobante no tiene un ambiente válido registrado. No se consultará un ambiente supuesto.');
  }
  foreach (['punto_venta', 'tipo_comprobante', 'numero_comprobante'] as $campo) {
    if (!is_scalar($factura[$campo])) {
      throw new RuntimeException('Falta un dato fiscal válido para consultar: '.$campo.'.');
    }
    $valor = filter_var($factura[$campo], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($valor === false) {
      throw new RuntimeException('Falta un dato fiscal válido para consultar: '.$campo.'.');
    }
    $factura[$campo] = $valor;
  }

  require 'config_facturacion_electronica.php';
  if ((int)$factura['homologacion'] !== (int)$homologacion) {
    throw new RuntimeException('El ambiente guardado no coincide con la configuración de facturación. La consulta se detuvo para no utilizar credenciales de otro ambiente.');
  }

  $informe['ambiente_consultado'] = 'Producción';
  if ((int)$factura['homologacion'] === 1) {
    $informe['ambiente_consultado'] = 'Homologación (pruebas)';
  }

  require_once 'vendor/afip/Afip.php';
  $aInitializeAFIP['CUIT'] = (string)$factura['emisor_cuit'];
  $aInitializeAFIP['exceptions'] = true;
  $afip = new Afip($aInitializeAFIP);
  http_response_code(502);
  $comprobante = $afip->ElectronicBilling->GetVoucherInfo(
    $factura['numero_comprobante'],
    $factura['punto_venta'],
    $factura['tipo_comprobante']
  );

  if ($comprobante === null) {
    http_response_code(404);
    throw new RuntimeException('ARCA no encontró ese comprobante para el emisor, ambiente, punto de venta, tipo y número indicados. No se modificó el registro local.');
  }
  if (!is_object($comprobante)) {
    throw new RuntimeException('ARCA devolvió una respuesta con un formato inesperado.');
  }

  $informe['verificacion']['condicion_iva'] = 'ARCA no devolvió CondicionIVAReceptorId. Esto no demuestra por sí solo que no se haya enviado.';
  if (isset($comprobante->CondicionIVAReceptorId) && is_scalar($comprobante->CondicionIVAReceptorId)) {
    $informe['verificacion']['condicion_iva'] = 'ARCA devolvió CondicionIVAReceptorId; el valor figura en la respuesta de abajo.';
    if ($factura['condicion_iva_receptor'] !== null && $factura['condicion_iva_receptor'] !== '') {
      $informe['verificacion']['condicion_iva_coincide_con_registro_local'] = (string)$comprobante->CondicionIVAReceptorId === (string)$factura['condicion_iva_receptor'];
    }
  }
  $informe['verificacion']['cae'] = 'No se puede comparar: falta el CAE local o el código de autorización en la respuesta.';
  if (!empty($factura['cae']) && isset($comprobante->CodAutorizacion) && is_scalar($comprobante->CodAutorizacion)) {
    $informe['verificacion']['cae_coincide_con_registro_local'] = (string)$comprobante->CodAutorizacion === (string)$factura['cae'];
    $informe['verificacion']['cae'] = 'Comparación realizada con el código de autorización devuelto por ARCA.';
  }
  $informe['respuesta_arca'] = $comprobante;
  http_response_code(200);
} catch (Throwable $error) {
  $hayError = true;
  if (http_response_code() === false || http_response_code() === 200) {
    http_response_code(500);
  }
  $informe['error'] = $error->getMessage();
} finally {
  Database::disconnect();
}

try {
  $salidaConsulta = json_encode($informe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
  $hayError = true;
  http_response_code(500);
  $salidaConsulta = 'No se pudo representar la respuesta como JSON: '.$error->getMessage();
} ?>
<!DOCTYPE html>
<html lang="es">
  <head><?php
    include 'head_forms.php'; ?>
  </head>
  <body class="light-only">
    <div class="page-wrapper"><?php
      include 'header.php'; ?>
      <div class="page-body-wrapper"><?php
        include 'menu.php'; ?>
        <div class="page-body">
          <div class="container-fluid">
            <div class="page-header">
              <div class="page-header-left">
                <h3><?php
                  include 'title.php'; ?>
                </h3>
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="listarCobros.php">Cobros</a></li>
                  <li class="breadcrumb-item">Consultar comprobante en ARCA</li>
                </ol>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Consulta del comprobante en ARCA</h5>
                  </div>
                  <div class="card-body">
                    <p>Consulta por ID interno de la tabla facturas. También permite consultar notas de crédito registradas en esa tabla. No emite comprobantes ni actualiza la base de datos.</p><?php
                    if ($hayError) { ?>
                      <div class="alert alert-warning" role="alert">No se pudo completar la consulta. El motivo figura a continuación.</div><?php
                    } else { ?>
                      <div class="alert alert-info" role="alert">Respuesta recibida de ARCA. Revisá las comparaciones y los datos del comprobante; esta consulta no concilia automáticamente el registro local.</div><?php
                    } ?>
                    <pre class="border rounded p-3"><?=htmlspecialchars($salidaConsulta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></pre>
                  </div>
                  <div class="card-footer">
                    <a href="listarCobros.php" class="btn btn-light">Volver a cobros</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div><?php
        include 'footer.php'; ?>
      </div>
    </div>
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/script.js"></script>
  </body>
</html>
