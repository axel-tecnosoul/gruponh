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

$sql = "SELECT cac.id, cac.id_certificado_maestro, DATE_FORMAT(cac.fecha_emision, '%d/%m/%Y') AS fecha_emision, cm.numero_x, occ.numero AS numero_occ
        FROM certificados_avances_cabecera cac
        INNER JOIN certificados_maestros cm ON cm.id = cac.id_certificado_maestro
        INNER JOIN occ ON occ.id = cm.id_occ
        WHERE cac.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$idCertificadoAvance]);
$certificado = $q->fetch(PDO::FETCH_ASSOC);

if (!$certificado) {
  Database::disconnect();
  header("Location: listarCertificadosMaestros.php");
  die("Redirecting to listarCertificadosMaestros.php");
}

$sql = "SELECT ca.id, ca.fecha, ca.tipo_ajuste, ca.observaciones, ca.monto, u.usuario AS nombre_usuario
  FROM certificados_ajustes ca
  LEFT JOIN usuarios u ON u.id = ca.id_usuario
  WHERE ca.id_certificado_avance = ?
  ORDER BY ca.fecha, ca.id";
$q = $pdo->prepare($sql);
$q->execute([$idCertificadoAvance]);
$ajustes = $q->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

function e($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <?php include('head_tables.php'); ?>
  </head>
  <body>
    <div class="page-wrapper">
      <?php include('header.php'); ?>
      <div class="page-body-wrapper">
        <?php include('menu.php'); ?>
        <div class="page-body">
          <?php
            $ubicacion = "Ajustes del Certificado de Avance #" . $certificado['id'];
            include_once("head_page.php");
          ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>
                      <?=e($ubicacion)?>
                      <a href="nuevoAjusteCertificadoAvance.php?id_certificado_avance=<?=$certificado['id']?>" title="Cargar nuevo ajuste">
                        <img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo ajuste">
                      </a>
                    </h5>
                  </div>
                  <div class="card-body">
                    <p>CM #<?=e($certificado['id_certificado_maestro'])?> | OCC #<?=e($certificado['numero_occ'])?> | Fecha emisión: <?=e($certificado['fecha_emision'])?></p>
                    <div class="table-responsive">
                      <table class="display" id="tablaAjustes">
                        <thead>
                          <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Observaciones</th>
                            <th>Monto</th>
                            <th>Usuario</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($ajustes as $ajuste): ?>
                            <tr>
                              <td><?=e(date('d/m/Y', strtotime($ajuste['fecha'])))?></td>
                              <td><?=e($ajuste['tipo_ajuste'])?></td>
                              <td><?=e($ajuste['observaciones'])?></td>
                              <td><?=number_format((float) $ajuste['monto'], 2, ',', '.')?></td>
                              <td><?=e($ajuste['nombre_usuario'] ?? '')?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <a href="listarCertificadosAvances.php?id_certificado_maestro=<?=$certificado['id_certificado_maestro']?>" class="btn btn-light mt-3">Volver a certificados de avance</a>
                  </div>
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
    <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script>
      $(document).ready(function() {
        $('#tablaAjustes').DataTable({
          language: {
            emptyTable: 'No hay ajustes registrados',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ Registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ Registros',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
          }
        });
      });
    </script>
    <script src="assets/js/script.js"></script>
  </body>
</html>
