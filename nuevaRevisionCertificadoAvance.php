<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$idOrigen = (int) ($_REQUEST['id'] ?? 0);
if ($idOrigen <= 0) {
  header("Location: listarCertificadosMaestros.php");
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT cac.*,
               (SELECT MAX(c2.nro_revision)
                  FROM certificados_avances_cabecera c2
                 WHERE c2.id_certificado_maestro = cac.id_certificado_maestro
                   AND c2.nro_certificado = cac.nro_certificado) AS max_revision,
               cm.id AS id_cm
        FROM certificados_avances_cabecera cac
        INNER JOIN certificados_maestros cm ON cm.id = cac.id_certificado_maestro
        WHERE cac.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idOrigen]);
$origen = $q->fetch(PDO::FETCH_ASSOC);

if (!$origen) {
  Database::disconnect();
  header("Location: listarCertificadosMaestros.php");
  exit;
}

if ((int) ($origen['aprobado_cliente'] ?? 0) !== 1) {
  Database::disconnect();
  die("El Certificado de Avance debe estar aprobado para generar una nueva revision.");
}

if ((int) $origen['nro_revision'] < (int) $origen['max_revision']) {
  Database::disconnect();
  die("Solo se puede generar una revision desde la ultima version del certificado.");
}

if (!empty($_POST)) {
  $motivo = trim((string) ($_POST['motivoRevision'] ?? ''));
  if ($motivo === '') {
    Database::disconnect();
    die("Debe indicar el motivo de la revision.");
  }

  try {
    $pdo->beginTransaction();

    $sql = "SELECT * FROM certificados_avances_cabecera WHERE id = ? FOR UPDATE";
    $q = $pdo->prepare($sql);
    $q->execute([$idOrigen]);
    $origen = $q->fetch(PDO::FETCH_ASSOC);

    if (!$origen) {
      throw new Exception("El Certificado de Avance no existe.");
    }

    $sql = "SELECT MAX(nro_revision) FROM certificados_avances_cabecera
            WHERE id_certificado_maestro = ? AND nro_certificado = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$origen['id_certificado_maestro'], $origen['nro_certificado']]);
    $maxRevision = (int) $q->fetchColumn();

    if ((int) $origen['nro_revision'] < $maxRevision) {
      throw new Exception("Ya existe una revision mas reciente para este certificado.");
    }

    $nuevaRevision = $maxRevision + 1;

    $sql = "INSERT INTO certificados_avances_cabecera
              (id_certificado_maestro, nro_certificado, nro_revision, fecha_emision, fecha_inicio, fecha_fin,
               cotizacion_dolar, monto_total, monto_acumulado_avances, monto_acumulado_anticipos,
               monto_acumulado_desacopios, monto_acumulado_descuentos, monto_acumulado_ajustes,
               observaciones, aprobado_cliente, id_certificado_avance_origen, motivo_revision,
               fecha_hora_revision, id_usuario_revision)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,NOW(),?)";
    $q = $pdo->prepare($sql);
    $q->execute([
      $origen['id_certificado_maestro'],
      $origen['nro_certificado'],
      $nuevaRevision,
      $origen['fecha_emision'],
      $origen['fecha_inicio'],
      $origen['fecha_fin'],
      $origen['cotizacion_dolar'],
      $origen['monto_total'],
      $origen['monto_acumulado_avances'],
      $origen['monto_acumulado_anticipos'],
      $origen['monto_acumulado_desacopios'],
      $origen['monto_acumulado_descuentos'],
      $origen['monto_acumulado_ajustes'],
      $origen['observaciones'],
      $idOrigen,
      $motivo,
      $_SESSION['user']['id'],
    ]);

    $idNuevo = (int) $pdo->lastInsertId();

    $sql = "INSERT INTO certificados_avances_detalle
              (id_certificado_avance, id_certificado_maestro_detalle, cantidad_anterior,
               cantidad_actual, cantidad_acumulado, precio_unitario, subtotal)
            SELECT ?, id_certificado_maestro_detalle, cantidad_anterior, cantidad_actual,
                   cantidad_acumulado, precio_unitario, subtotal
            FROM certificados_avances_detalle
            WHERE id_certificado_avance = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$idNuevo, $idOrigen]);

    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
            VALUES (NOW(), ?, ?, 'Certificado de Avance', ?)";
    $q = $pdo->prepare($sql);
    $q->execute([
      $_SESSION['user']['id'],
      "Nueva revision {$nuevaRevision} del CA N° {$origen['nro_certificado']} (CM #{$origen['id_certificado_maestro']}) - Motivo: " . $motivo,
      "verCertificadoAvance.php?id=$idNuevo",
    ]);

    $pdo->commit();
  } catch (Exception $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    Database::disconnect();
    die("Error al generar la revision: " . $e->getMessage());
  }

  Database::disconnect();
  header("Location: nuevoCertificadoAvanceDetalle.php?id_certificado_avance=" . $idNuevo);
  exit;
}

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php');?>

      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Nueva Revision de Certificado de Avance";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?> - CA N° <?=htmlspecialchars((string)$origen['nro_certificado'])?> Rev. <?=htmlspecialchars((string)$origen['nro_revision'])?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="nuevaRevisionCertificadoAvance.php?id=<?=$idOrigen?>">
                    <input type="hidden" name="id" value="<?=$idOrigen?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <p>Se generara una nueva revision del certificado conservando cabecera y detalle actuales. La revision anterior quedara como historico y esta comenzara pendiente de aprobacion.</p>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Motivo de la revision(*)</label>
                            <div class="col-sm-9"><textarea name="motivoRevision" class="form-control" required="required" autofocus></textarea></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button type="submit" class="btn btn-primary">Generar Revision</button>
                        <a href="listarCertificadosAvances.php?id_certificado_maestro=<?=htmlspecialchars((string)$origen['id_certificado_maestro'])?>" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
    <!-- latest jquery-->
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
