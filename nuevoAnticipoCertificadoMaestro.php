<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$idCertificadoMaestro = filter_input(INPUT_GET, 'id_certificado_maestro', FILTER_VALIDATE_INT);
if (!$idCertificadoMaestro) {
  header("Location: listarCertificadosMaestros.php");
  die("Redirecting to listarCertificadosMaestros.php");
}

$error = '';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT aprobado_cliente FROM certificados_maestros WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idCertificadoMaestro]);
$estadoCertificado = $q->fetch(PDO::FETCH_ASSOC);
Database::disconnect();

if (empty($estadoCertificado)) {
  header("Location: listarCertificadosMaestros.php");
  die("Redirecting to listarCertificadosMaestros.php");
}

if ((int) ($estadoCertificado['aprobado_cliente'] ?? 0) === 1) {
  die("El Certificado Maestro está aprobado y no puede modificarse.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fecha = $_POST['fecha'] ?? '';
  $montoBase = isset($_POST['monto_base']) && $_POST['monto_base'] !== '' ? (float) $_POST['monto_base'] : 0;
  $porcentaje = isset($_POST['porcentaje']) && $_POST['porcentaje'] !== '' ? (float) $_POST['porcentaje'] : 0;
  $montoAnticipo = isset($_POST['monto_anticipo']) && $_POST['monto_anticipo'] !== '' ? (float) $_POST['monto_anticipo'] : 0;
  $observaciones = trim($_POST['observaciones'] ?? '');

  if ($fecha === '' || $porcentaje < 0 || $montoAnticipo < 0) {
    $error = 'Complete los datos del anticipo con valores validos.';
  } else {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
      $pdo->beginTransaction();

      $sql = "SELECT id FROM certificados_anticipos WHERE id_certificado_maestro = ? ORDER BY id DESC LIMIT 1 FOR UPDATE";
      $q = $pdo->prepare($sql);
      $q->execute([$idCertificadoMaestro]);
      $idAnticipo = $q->fetchColumn();

      if ($idAnticipo) {
        $sql = "UPDATE certificados_anticipos SET fecha = ?, monto_base = ?, porcentaje = ?, monto_anticipo = ?, observaciones = ?, id_usuario = ? WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$fecha, $montoBase, $porcentaje, $montoAnticipo, $observaciones, $_SESSION['user']['id'], $idAnticipo]);
      } else {
        $sql = "INSERT INTO certificados_anticipos (id_certificado_maestro, fecha, monto_base, porcentaje, monto_anticipo, observaciones, id_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $q = $pdo->prepare($sql);
        $q->execute([$idCertificadoMaestro, $fecha, $montoBase, $porcentaje, $montoAnticipo, $observaciones, $_SESSION['user']['id']]);
      }

      $sql = "UPDATE certificados_maestros SET porcentaje_anticipo = ?, monto_acumulado_anticipos = ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$porcentaje, $montoAnticipo, $idCertificadoMaestro]);

      $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
              VALUES (NOW(), ?, ?, 'Certificado Maestro', ?)";
      $q = $pdo->prepare($sql);
      $q->execute([
        $_SESSION['user']['id'],
        'Actualización de anticipo del Certificado Maestro #' . $idCertificadoMaestro,
        'listarCertificadosMaestros.php'
      ]);

      $pdo->commit();
      Database::disconnect();
      header("Location: listarCertificadosMaestros.php");
      die();
    } catch (Throwable $exception) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      Database::disconnect();
      $error = 'No se pudo guardar el anticipo.';
    }
  }
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT cm.id, cm.porcentaje_anticipo, occ.numero AS numero_occ, occ.monto AS monto_base, m.moneda FROM certificados_maestros cm INNER JOIN occ ON occ.id = cm.id_occ INNER JOIN monedas m ON m.id = cm.id_moneda WHERE cm.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idCertificadoMaestro]);
$certificado = $q->fetch(PDO::FETCH_ASSOC);

if (!$certificado) {
  Database::disconnect();
  header("Location: listarCertificadosMaestros.php");
  die("Redirecting to listarCertificadosMaestros.php");
}

$sql = "SELECT fecha, monto_base, porcentaje, monto_anticipo, observaciones FROM certificados_anticipos WHERE id_certificado_maestro = ? ORDER BY id DESC LIMIT 1";
$q = $pdo->prepare($sql);
$q->execute([$idCertificadoMaestro]);
$anticipo = $q->fetch(PDO::FETCH_ASSOC);
Database::disconnect();

$montoBase = (float) ($certificado['monto_base'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $fecha = $anticipo['fecha'] ?? date('Y-m-d');
  $porcentaje = (float) ($anticipo['porcentaje'] ?? $certificado['porcentaje_anticipo'] ?? 0);
  $montoAnticipo = isset($anticipo['monto_anticipo']) ? (float) $anticipo['monto_anticipo'] : round($montoBase * $porcentaje / 100, 2);
  $observaciones = $anticipo['observaciones'] ?? '';
}

function e($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
  <head><?php
    include('head_forms.php'); ?>
  </head>
  <body>
    <div class="page-wrapper"><?php
      include('header.php'); ?>
      <div class="page-body-wrapper"><?php
        include('menu.php'); ?>
        <div class="page-body"><?php
          $ubicacion = 'Nuevo Anticipo del Certificado Maestro #' . $idCertificadoMaestro;
          include_once('head_page.php');?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header"><h5><?=e($ubicacion)?></h5></div>
                  <form class="form theme-form" method="post" action="nuevoAnticipoCertificadoMaestro.php?id_certificado_maestro=<?=$idCertificadoMaestro?>">
                    <div class="card-body"><?php
                      if ($error !== ''){?>
                        <div class="alert alert-danger"><?=e($error)?></div><?php
                      }
                      if ($anticipo){?>
                        <div class="alert alert-warning">
                          <strong>Este Certificado Maestro ya tiene un anticipo registrado.</strong>
                          <div>
                            Al guardar el formulario se actualizará el registro existente.
                          </div>

                          <div class="mt-2">
                            <strong>Fecha:</strong>
                            <?=e(date('d/m/Y', strtotime($anticipo['fecha'])))?>
                          </div>

                          <div>
                            <strong>Monto base:</strong>
                            <?=e($certificado['moneda'])?>
                            <?=number_format((float) $anticipo['monto_base'], 2, ',', '.')?>
                          </div>

                          <div>
                            <strong>Porcentaje:</strong>
                            <?=number_format((float) $anticipo['porcentaje'], 2, ',', '.')?>%
                          </div>

                          <div>
                            <strong>Monto del anticipo:</strong>
                            <?=e($certificado['moneda'])?>
                            <?=number_format((float) $anticipo['monto_anticipo'], 2, ',', '.')?>
                          </div>

                          <div>
                            <strong>Observaciones:</strong>
                            <?=trim((string) $anticipo['observaciones']) !== ''
                              ? nl2br(e($anticipo['observaciones']))
                              : '-'?>
                          </div>
                        </div><?php
                      } ?>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">OCC</label>
                        <div class="col-sm-9"><?=e($certificado['numero_occ'])?></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Monto de la OCC</label>
                         <div class="col-sm-9">
                          <input id="monto_base" name="monto_base" type="hidden" value="<?=e($montoBase)?>">
                          <?=e($certificado['moneda'])?>
                          <?=number_format($montoBase,2,",",".")?>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Porcentaje de anticipo</label>
                        <div class="col-sm-9"><input id="porcentaje" name="porcentaje" type="number" step="0.01" min="0" class="form-control" value="<?=e($porcentaje)?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Monto anticipo(*)</label>
                        <div class="col-sm-9"><input id="monto_anticipo" name="monto_anticipo" type="number" step="0.01" min="0" class="form-control" required value="<?=e($montoAnticipo)?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha(*)</label>
                        <div class="col-sm-9"><input name="fecha" type="date" class="form-control" required value="<?=e($fecha)?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Observaciones</label>
                        <div class="col-sm-9"><textarea name="observaciones" class="form-control"><?=e($observaciones)?></textarea></div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <button class="btn btn-primary" type="submit">
                        <?=$anticipo ? 'Actualizar anticipo' : 'Guardar anticipo'?>
                      </button>
                      <a href="listarCertificadosMaestros.php" class="btn btn-light">Volver</a>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include('footer.php'); ?>
      </div>
    </div>
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script>
      function obtenerMontoBase() {
        return parseFloat($('#monto_base').val()) || 0;
      }

      $('#porcentaje').on('input', function() {
        var montoBase = obtenerMontoBase();
        var porcentaje = parseFloat($(this).val());

        if (montoBase <= 0 || isNaN(porcentaje)) {
          $('#monto_anticipo').val('');
          return;
        }

        var montoAnticipo = montoBase * porcentaje / 100;
        $('#monto_anticipo').val(montoAnticipo.toFixed(2));
      });

      $('#monto_anticipo').on('input', function() {
        var montoBase = obtenerMontoBase();
        var montoAnticipo = parseFloat($(this).val());

        if (montoBase <= 0 || isNaN(montoAnticipo)) {
          $('#porcentaje').val('');
          return;
        }

        var porcentaje = montoAnticipo * 100 / montoBase;
        $('#porcentaje').val(porcentaje.toFixed(2));
      });
    </script>
  </body>
</html>
