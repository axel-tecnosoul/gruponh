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

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT cm.id, cm.porcentaje_anticipo, occ.numero AS numero_occ, occ.monto AS monto_base,
               m.moneda
        FROM certificados_maestros cm
        INNER JOIN occ ON occ.id = cm.id_occ
        INNER JOIN monedas m ON m.id = cm.id_moneda
        WHERE cm.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idCertificadoMaestro]);
$certificado = $q->fetch(PDO::FETCH_ASSOC);

if (!$certificado) {
  Database::disconnect();
  header("Location: listarCertificadosMaestros.php");
  die("Redirecting to listarCertificadosMaestros.php");
}

$sql = "SELECT fecha, monto_base, porcentaje, monto_anticipo, observaciones
        FROM certificados_anticipos
        WHERE id_certificado_maestro = ?
        ORDER BY id DESC LIMIT 1";
$q = $pdo->prepare($sql);
$q->execute([$idCertificadoMaestro]);
$anticipo = $q->fetch(PDO::FETCH_ASSOC);
Database::disconnect();

$fecha = $anticipo['fecha'] ?? date('Y-m-d');
$montoBase = (float) ($certificado['monto_base'] ?? 0);
$porcentaje = (float) ($anticipo['porcentaje'] ?? $certificado['porcentaje_anticipo'] ?? 0);
$montoAnticipo = isset($anticipo['monto_anticipo']) ? (float) $anticipo['monto_anticipo'] : round($montoBase * $porcentaje / 100, 2);
$observaciones = $anticipo['observaciones'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fecha = $_POST['fecha'] ?? '';
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

      $sql = "SELECT id FROM certificados_anticipos
              WHERE id_certificado_maestro = ?
              ORDER BY id DESC LIMIT 1 FOR UPDATE";
      $q = $pdo->prepare($sql);
      $q->execute([$idCertificadoMaestro]);
      $idAnticipo = $q->fetchColumn();

      if ($idAnticipo) {
        $sql = "UPDATE certificados_anticipos
                SET fecha = ?, monto_base = ?, porcentaje = ?, monto_anticipo = ?, observaciones = ?, id_usuario = ?
                WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$fecha, $montoBase, $porcentaje, $montoAnticipo, $observaciones, $_SESSION['user']['id'], $idAnticipo]);
      } else {
        $sql = "INSERT INTO certificados_anticipos
                (id_certificado_maestro, fecha, monto_base, porcentaje, monto_anticipo, observaciones, id_usuario)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
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

function e($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <?php include('head_forms.php'); ?>
  </head>
  <body>
    <div class="page-wrapper">
      <?php include('header.php'); ?>
      <div class="page-body-wrapper">
        <?php include('menu.php'); ?>
        <div class="page-body">
          <?php
            $ubicacion = 'Nuevo Anticipo del Certificado Maestro #' . $idCertificadoMaestro;
            include_once('head_page.php');
          ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header"><h5><?=e($ubicacion)?></h5></div>
                  <form class="form theme-form" method="post" action="nuevoAnticipoCertificadoMaestro.php?id_certificado_maestro=<?=$idCertificadoMaestro?>">
                    <div class="card-body">
                      <?php if ($error !== ''): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">OCC</label>
                        <div class="col-sm-9"><input type="text" class="form-control" value="<?=e($certificado['numero_occ'])?>" readonly></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Monto de la OCC</label>
                        <div class="col-sm-9"><input id="monto_base" type="number" step="0.01" class="form-control" value="<?=e($montoBase)?>" readonly></div>
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
                      <button class="btn btn-primary" type="submit">Guardar anticipo</button>
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
      $('#porcentaje').on('input', function() {
        var montoBase = parseFloat($('#monto_base').val()) || 0;
        var porcentaje = parseFloat($(this).val()) || 0;
        $('#monto_anticipo').val((montoBase * porcentaje / 100).toFixed(2));
      });
    </script>
  </body>
</html>
