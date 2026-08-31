<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$idCertificadoAvance = filter_input(INPUT_GET, 'id_certificado_avance', FILTER_VALIDATE_INT);
if (!$idCertificadoAvance) {
  header("Location: listarCertificadosMaestros.php");
  die("Redirecting to listarCertificadosMaestros.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqlUlt = "SELECT COUNT(*) FROM certificados_avances_cabecera c
           WHERE c.id_certificado_maestro = (SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?)
             AND c.nro_certificado = (SELECT nro_certificado FROM certificados_avances_cabecera WHERE id = ?)
             AND c.nro_revision > (SELECT nro_revision FROM certificados_avances_cabecera WHERE id = ?)";
$q = $pdo->prepare($sqlUlt);
$q->execute([$idCertificadoAvance, $idCertificadoAvance, $idCertificadoAvance]);
if ((int) $q->fetchColumn() > 0) {
  Database::disconnect();
  die("Solo la ultima revision del certificado puede recibir ajustes.");
}

$sql = "SELECT id, id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idCertificadoAvance]);
$certificado = $q->fetch(PDO::FETCH_ASSOC);
Database::disconnect();

if (!$certificado) {
  header("Location: listarCertificadosMaestros.php");
  die("Redirecting to listarCertificadosMaestros.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fecha = $_POST['fecha'] ?? '';
  $tipoAjuste = $_POST['tipo_ajuste'] ?? '';
  $observaciones = trim($_POST['observaciones'] ?? '');
  $monto = $_POST['monto'] ?? '';
  $impacto = ($tipoAjuste === 'Redeterminación' && ($_POST['impacto'] ?? '') === 'suma') ? 1 : -1;

  if ($fecha === '' || $tipoAjuste === '' || $monto === '' || !is_numeric($monto) || (float)$monto <= 0) {
    $error = 'Complete fecha, tipo y un monto mayor a cero.';
  } else {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $montoFinal = abs((float) $monto);
    try {
      $sql = "INSERT INTO certificados_ajustes (id_certificado_avance, fecha, tipo_ajuste, observaciones, monto, impacto, id_usuario)
              VALUES (?, ?, ?, ?, ?, ?, ?)";
      $q = $pdo->prepare($sql);
      $q->execute([$idCertificadoAvance, $fecha, $tipoAjuste, $observaciones, $montoFinal, $impacto, $_SESSION['user']['id']]);
    } catch (PDOException $e) {
      // Compatibilidad si la columna impacto aun no existe.
      $sql = "INSERT INTO certificados_ajustes (id_certificado_avance, fecha, tipo_ajuste, observaciones, monto, id_usuario)
              VALUES (?, ?, ?, ?, ?, ?)";
      $q = $pdo->prepare($sql);
      $q->execute([$idCertificadoAvance, $fecha, $tipoAjuste, $observaciones, $montoFinal, $_SESSION['user']['id']]);
    }

    $idAjuste = $pdo->lastInsertId();
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
            VALUES (NOW(), ?, ?, 'Certificado de Avance', ?)";
    $q = $pdo->prepare($sql);
    $q->execute([
      $_SESSION['user']['id'],
      'Nuevo ajuste de Certificado de Avance #' . $idCertificadoAvance,
      'listarAjustesCertificadoAvance.php?id_certificado_avance=' . $idCertificadoAvance
    ]);
    Database::disconnect();
    header("Location: listarCertificadosAvances.php?id_certificado_maestro=" . $certificado['id_certificado_maestro']);
    die();
  }
}

$hoy = date('Y-m-d');
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
            $ubicacion = "Nuevo Ajuste del Certificado de Avance #" . $idCertificadoAvance;
            include_once("head_page.php");
          ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header"><h5><?=htmlspecialchars($ubicacion, ENT_QUOTES, 'UTF-8')?></h5></div>
                  <form class="form theme-form" method="post" action="nuevoAjusteCertificadoAvance.php?id_certificado_avance=<?=$idCertificadoAvance?>">
                    <div class="card-body">
                      <?php if (!empty($error)): ?><div class="alert alert-danger"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha(*)</label>
                        <div class="col-sm-9"><input name="fecha" type="date" class="form-control" required value="<?=htmlspecialchars($_POST['fecha'] ?? $hoy, ENT_QUOTES, 'UTF-8')?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Tipo de ajuste(*)</label>
                        <div class="col-sm-9">
                          <select name="tipo_ajuste" id="tipo_ajuste" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <?php foreach (['Desacopio', 'Descuento', 'Redeterminación'] as $tipo): ?>
                              <option value="<?=$tipo?>" <?=($_POST['tipo_ajuste'] ?? '') === $tipo ? 'selected' : ''?>><?=$tipo?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group row" id="row_impacto">
                        <label class="col-sm-3 col-form-label">Impacto</label>
                        <div class="col-sm-9">
                          <label class="radio-inline mr-3"><input type="radio" name="impacto" value="resta" checked> Resta</label>
                          <label class="radio-inline"><input type="radio" name="impacto" value="suma"> Suma</label>
                          <small class="d-block text-muted">Desacopio y Descuento restan siempre. La Redeterminación puede sumar o restar.</small>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Observaciones</label>
                        <div class="col-sm-9"><textarea name="observaciones" class="form-control"><?=htmlspecialchars($_POST['observaciones'] ?? '', ENT_QUOTES, 'UTF-8')?></textarea></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Monto(*)</label>
                        <div class="col-sm-9"><input name="monto" type="number" step="0.01" min="0.01" class="form-control" required value="<?=htmlspecialchars($_POST['monto'] ?? '', ENT_QUOTES, 'UTF-8')?>"></div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <button class="btn btn-primary" type="submit">Guardar ajuste</button>
                      <a href="listarCertificadosAvances.php?id_certificado_maestro=<?=$certificado['id_certificado_maestro']?>" class="btn btn-light">Volver</a>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include("footer.php"); ?>
      </div>
    </div>
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script>
      $(document).ready(function() {
        function actualizarImpacto() {
          const tipo = $('#tipo_ajuste').val();
          const fila = $('#row_impacto');
          if (tipo === 'Redeterminación') {
            fila.show();
          } else {
            fila.hide();
            fila.find('input[value="resta"]').prop('checked', true);
          }
        }

        $('#tipo_ajuste').on('change', actualizarImpacto);
        actualizarImpacto();
      });
    </script>
    <script src="assets/js/script.js"></script>
  </body>
</html>
