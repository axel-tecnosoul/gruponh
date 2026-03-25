<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();

require("config.php");
require 'database.php';

$prod      = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodQuery = $prod ? '?prod=' . $prod : '';
$prodParam = $prod ? '&prod=' . $prod : '';

define('ID_ESTADO_TERMINADO_COMPUTO',  5);
define('ID_ESTADO_TERMINADO_PROYECTO', 4);

$modo     = $_REQUEST['modo']     ?? 'nuevo';
$idOrigen = $_REQUEST['id']       ?? null;
$nroRev   = $_REQUEST['revision'] ?? 0;

$idComputoPadre = !empty($_REQUEST['idOrigen'])
  ? (int)$_REQUEST['idOrigen']
  : (int)$idOrigen;

if (!$idOrigen) {
  header("Location: listarComputos.php$prodQuery");
  exit;
}

$id       = (int)$idOrigen;
$revision = $nroRev;

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function validarProyectoNoTerminado(PDO $pdo, int $idComputo, string $prodQuery): void
{
  $stmt = $pdo->prepare("
        SELECT p.id_estado_proyecto
        FROM computos c
        INNER JOIN tareas t    ON t.id = c.id_tarea
        INNER JOIN proyectos p ON p.id = t.id_proyecto
        WHERE c.id = ?
    ");
  $stmt->execute([$idComputo]);
  $estadoProyecto = (int)$stmt->fetchColumn();

  if ($estadoProyecto === ID_ESTADO_TERMINADO_PROYECTO) {
    header("Location: listarComputos.php$prodQuery");
    exit;
  }
}

$stmtEstadoPadre = $pdo->prepare("
  SELECT c.id_estado,
         (SELECT MAX(c2.nro_revision) FROM computos c2 WHERE c2.nro_computo = c.nro_computo) AS max_revision,
         c.nro_revision
  FROM computos c WHERE c.id = ?
");
$stmtEstadoPadre->execute([$idComputoPadre]);
$rowPadre    = $stmtEstadoPadre->fetch(PDO::FETCH_ASSOC);
$estadoPadre = (int)($rowPadre['id_estado'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

  if (!in_array($estadoPadre, [1, 2, ID_ESTADO_TERMINADO_COMPUTO])) {
    header("Location: listarComputos.php$prodQuery");
    exit;
  }

  if ($estadoPadre === ID_ESTADO_TERMINADO_COMPUTO) {
    $tokenOk = isset($_SESSION['revision_autorizada']) && $_SESSION['revision_autorizada'] === $id;
    if (!$tokenOk) {
      header("Location: listarComputos.php$prodQuery");
      exit;
    }
    unset($_SESSION['revision_autorizada']);
  }
}

$esModoRestringido = ($modo === 'update' && $estadoPadre === ID_ESTADO_TERMINADO_COMPUTO);

if ($esModoRestringido) {
  validarProyectoNoTerminado($pdo, $idComputoPadre, $prodQuery);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $userId = $_SESSION['user']['id'] ?? 0;

  if (isset($_POST['btn2_confirm'])) {
    $id_computo = (int)$_POST['id'];
    $revision   = $_POST['revision'];

    $stmt = $pdo->prepare("UPDATE computos SET id_estado = 2 WHERE id = ?");
    $stmt->execute([$id_computo]);

    $sql = "SELECT c.nro AS nro_computo, c.nro_revision, t.id_proyecto 
                FROM computos c LEFT JOIN tareas t ON c.id_tarea = t.id 
                WHERE c.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_computo]);
    $dataC = $q->fetch(PDO::FETCH_ASSOC);

    $descProyecto        = getDescripcionProyecto($pdo, $dataC["id_proyecto"]);
    $descripcion_computo = " N° " . $dataC["nro_computo"] . " Rev. N° " . $dataC["nro_revision"] . $descProyecto;

    crearNotificacion(
      $pdo,
      8,
      $id_computo,
      "ID Computo: #$id_computo",
      "Producción - Aprobación de Cómputo $descripcion_computo",
      "La revisión de cómputo $descripcion_computo está lista para aprobación."
    );

    header("Location: listarComputos.php$prodQuery");
    exit;
  }

  $esRevision = ($modo === 'update' && !empty(trim($_POST['motivoRevision'] ?? '')));

  if ($esRevision) {
    $motivo = trim($_POST['motivoRevision']);

    if ($motivo === '') {
      header("Location: listarComputos.php$prodQuery");
      exit;
    }

    $idPadrePost = !empty($_POST['idOrigen']) ? (int)$_POST['idOrigen'] : (int)$idOrigen;

    if ($estadoPadre === ID_ESTADO_TERMINADO_COMPUTO) {
        validarProyectoNoTerminado($pdo, $idPadrePost, $prodQuery);
    }

    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("
                SELECT nro_revision, id_tarea, fecha, id_cuenta_solicitante, nro, nro_computo 
                FROM computos WHERE id = ?
            ");
      $stmt->execute([$idOrigen]);
      $orig = $stmt->fetch(PDO::FETCH_ASSOC);

      $nuevoNroRev = $orig['nro_revision'] + 1;

      $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE id_usuario = ?");
      $stmt->execute([$userId]);
      $rev = $stmt->fetchColumn();

      $stmt = $pdo->prepare("
                INSERT INTO computos 
                    (nro_revision, id_tarea, fecha, id_cuenta_solicitante, id_estado, 
                     nro_computo, comentarios_revision, fecha_hora_revision, nro, 
                     id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido)
                VALUES (?, ?, ?, ?, 1, ?, ?, NOW(), ?, ?, ?, ?)
            ");
      $stmt->execute([
        $nuevoNroRev,
        $orig['id_tarea'],
        $orig['fecha'],
        $orig['id_cuenta_solicitante'],
        $orig['nro_computo'],
        $motivo,
        $orig['nro'],
        $rev,
        $rev,
        $rev
      ]);
      $idRevision = (int)$pdo->lastInsertId();

      $stmt = $pdo->prepare("
                INSERT INTO computos_detalle 
                    (id_computo, id_material, cantidad, fecha_necesidad, 
                     aprobado, reservado, saldo, comprado, cancelado, comentarios)
                SELECT ?, id_material, cantidad, fecha_necesidad, 
                       0, 0, 0, 0, 0, comentarios
                FROM computos_detalle 
                WHERE id_computo = ?
            ");
      $stmt->execute([$idRevision, $idOrigen]);

      $pdo->commit();
    } catch (Exception $e) {
      $pdo->rollBack();
      die("Error al generar revisión: " . $e->getMessage());
    }

    $_SESSION['revision_autorizada'] = $idRevision;

    header("Location: itemsComputo.php?modo=$modo&id=$idRevision&revision=$nuevoNroRev&idOrigen={$idPadrePost}$prodParam");
    exit;
  }

  if (!empty($_POST['id_material'])) {
    if ($esModoRestringido) {
      header("Location: itemsComputo.php?modo=$modo&id=$id&revision=$nroRev&idOrigen=$idComputoPadre&error=2$prodParam");
      exit;
    }

    $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM computos_detalle 
            WHERE cancelado = 0 AND id_computo = ? AND id_material = ?
        ");
    $stmt->execute([$id, $_POST['id_material']]);
    if ((int)$stmt->fetchColumn() > 0) {
      header("Location: itemsComputo.php?modo=$modo&id=$id&revision=$nroRev&idOrigen=$idComputoPadre&error=1$prodParam");
      exit;
    }

    $cantidad = (float)$_POST['cantidad'];

    $stmt = $pdo->prepare("
            INSERT INTO computos_detalle 
                (id_computo, id_material, cantidad, saldo, fecha_necesidad, aprobado, reservado, comprado, cancelado, comentarios) 
            VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?)
        ");
    $stmt->execute([$id, $_POST['id_material'], $cantidad, $cantidad, $_POST['fecha_necesidad'], $_POST['comentarios']]);

    $stmt = $pdo->prepare("
            INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) 
            VALUES (NOW(), ?, 'Se ha modificado un item de un cómputo', 'Cómputos', ?)
        ");
    $stmt->execute([$userId, "verComputo.php?id=$id$prodParam"]);
  }

  if (isset($_POST['btn2'])) {
    header("Location: listarComputos.php$prodQuery");
  } else {
    header("Location: itemsComputo.php?modo=$modo&id=$id&revision=$nroRev&idOrigen=$idComputoPadre$prodParam");
  }
  exit;
}

$sql = "
    SELECT s.nro_sitio AS sitio, s.nro_subsitio AS subsitio,
           p.nro AS nro_proyecto, p.nombre AS proyecto,
           c.nro AS nro_computo, c.nro_revision, c.id_estado
    FROM computos c
    LEFT JOIN tareas t            ON c.id_tarea = t.id
    LEFT JOIN cuentas cu          ON cu.id = c.id_cuenta_solicitante
    LEFT JOIN estados_computos ec ON ec.id = c.id_estado
    INNER JOIN proyectos p        ON p.id = t.id_proyecto
    INNER JOIN sitios s           ON s.id = p.id_sitio
    WHERE c.id = ?
";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include('head_forms.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const btn1 = document.getElementById("btn1");
      const observaciones = document.getElementById("observaciones");
      const id_cuenta_valido = document.getElementById("id_cuenta_valido");
      const id_cuenta_reviso = document.getElementById("id_cuenta_reviso");
      if (btn1) {
        btn1.addEventListener("click", function() {
          if (observaciones) observaciones.removeAttribute("required");
          if (id_cuenta_valido) id_cuenta_valido.removeAttribute("required");
          if (id_cuenta_reviso) id_cuenta_reviso.removeAttribute("required");
        });
      }
    });
  </script>
  <style>
    .input-group {
      display: flex;
      align-items: center;
    }

    .input-group input {
      width: 100px;
    }

    .input-group button {
      margin-left: 10px;
    }
  </style>
</head>

<body>
  <div class="page-wrapper"><?php include('header.php'); ?>
    <div class="page-body-wrapper"><?php include('menu.php'); ?>
      <div class="page-body"><?php
                              $ubicacion = "Ver/Añadir Items Cómputo";
                              include_once("head_page.php"); ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5>
                    <?= $ubicacion . " N° " . $data["nro_computo"]
                      . " Rev. N° " . $data["nro_revision"]
                      . " (" . $data["sitio"] . "_" . $data["subsitio"] . "_" . $data["nro_proyecto"] . ")" ?>
                  </h5>
                </div>
                <form class="form theme-form" role="form" method="post" id="miFormulario"
                  action="itemsComputo.php?modo=<?= $modo ?>&id=<?= $id ?>&idOrigen=<?= $idComputoPadre ?><?= $prodParam ?>">
                  <input type="hidden" name="modo" value="<?= htmlspecialchars($modo) ?>">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                  <input type="hidden" name="revision" value="<?= htmlspecialchars($revision) ?>">
                  <input type="hidden" name="prod" value="<?= $_REQUEST['prod'] ?? '' ?>">
                  <input type="hidden" name="idOrigen" value="<?= $idComputoPadre ?>">
                  <div class="card-body">
                    <div class="row">
                      <div class="col">
                        <div class="form-group row">
                          <div class="col-sm-12">
                            <table class="display" id="dataTables-example667">
                              <thead>
                                <tr>
                                  <th>Concepto</th>
                                  <th>Cantidad</th>
                                  <th>Fecha Necesidad</th>
                                  <th>Aprobado</th>
                                  <th>Comentarios</th>
                                  <th>Opciones</th>
                                </tr>
                              </thead>
                              <tbody><?php
                                      $pdo = Database::connect();
                                      $sql = "
                                  SELECT cd.id AS id_computo_detalle, m.concepto, cd.cantidad,
                                         date_format(cd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad_formatted,
                                         cd.aprobado, cd.comentarios,
                                         date_format(cd.fecha_necesidad,'%y%m%d') AS fecha_necesidad
                                  FROM computos_detalle cd
                                  INNER JOIN materiales m ON m.id = cd.id_material
                                  WHERE cancelado = 0 AND cd.id_computo = " . $id;
                                      $b = 0;
                                      foreach ($pdo->query($sql) as $row) {
                                        $b = 1;
                                        $aprobado = ($row["aprobado"] == 1) ? "Si" : "No"; ?>
                                  <tr>
                                    <td><?= $row["concepto"] ?></td>
                                    <td><?= $row["cantidad"] ?></td>
                                    <td>
                                      <span style="display:none;"><?= $row["fecha_necesidad"] ?></span>
                                      <?= $row["fecha_necesidad_formatted"] ?>
                                    </td>
                                    <td><?= $aprobado ?></td>
                                    <td><?= $row["comentarios"] ?></td>
                                    <td><?php if (!empty(tienePermiso(291))) { ?>
                                        <a href="modificarItemComputo.php?id=<?= $row["id_computo_detalle"] ?>&idRetorno=<?= $id ?>&modo=<?= $modo ?>&revision=<?= $revision ?>&idOrigen=<?= $idComputoPadre ?><?= $prodParam ?>">
                                          <img src="img/icon_modificar.png" width="24" height="25" alt="Modificar" title="Modificar">
                                        </a>
                                        &nbsp;&nbsp;
                                        <a href="#" data-toggle="modal" data-target="#eliminarModal_<?= $row["id_computo_detalle"] ?>">
                                          <img src="img/icon_baja.png" width="24" height="25" alt="Eliminar" title="Eliminar">
                                        </a>
                                        &nbsp;&nbsp;
                                      <?php } ?>
                                    </td>
                                  </tr><?php
                                      }
                                      Database::disconnect(); ?>
                              </tbody>
                              <tfoot>
                                <tr>
                                  <th>Concepto</th>
                                  <th>Cantidad</th>
                                  <th>Fecha Necesidad</th>
                                  <th>Aprobado</th>
                                  <th>Comentarios</th>
                                  <th>Opciones</th>
                                </tr>
                              </tfoot>
                            </table>
                          </div>
                        </div>

                        <?php if ($esModoRestringido): ?>
                          <div class="alert alert-info mt-3" role="alert">
                            <strong>Modo revisión:</strong> Este cómputo ya fue terminado/aprobado.
                            No es posible agregar nuevos conceptos.
                          </div>
                        <?php else: ?>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Concepto(*)</label>
                            <div class="col-sm-9">
                              <select name="id_material" id="id_material"
                                class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                                                        $pdo = Database::connect();
                                                                        $q   = $pdo->prepare("SELECT `id`, `concepto`, `codigo` FROM `materiales` WHERE anulado = 0");
                                                                        $q->execute();
                                                                        while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                                                          echo "<option value='" . $fila['id'] . "'>"
                                                                            . $fila['concepto'] . " (" . $fila['codigo'] . ")</option>";
                                                                        }
                                                                        Database::disconnect(); ?>
                              </select>
                              <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
                                <b>
                                  <font color="red">No se puede agregar un concepto repetido!</font>
                                </b>
                              <?php endif; ?>
                              <?php if (isset($_GET['error']) && $_GET['error'] == 2): ?>
                                <b>
                                  <font color="red">No se pueden agregar conceptos en una revisión.</font>
                                </b>
                              <?php endif; ?>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9">
                              <input name="cantidad" id="cantidad" step="0.01" min="0.01"
                                type="number" class="form-control" required="required" value="">
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Necesidad(*)</label>
                            <div class="col-sm-9"><?php $fecha_actual = date('Y-m-d'); ?>
                              <input name="fecha_necesidad" min="<?= $fecha_actual ?>" id="fecha_necesidad"
                                type="date" onfocus="this.showPicker()" class="form-control"
                                required="required" value="">
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Comentarios</label>
                            <div class="col-sm-9">
                              <textarea name="comentarios" class="form-control"></textarea>
                            </div>
                          </div>
                          <hr>
                        <?php endif; ?>

                      </div>
                    </div>
                  </div>
                  <div class="card-footer">
                    <div class="col-sm-9 offset-sm-3">
                      <?php if (!$esModoRestringido): ?>
                        <button class="btn btn-success" type="submit" value="1" name="btn1" id="btn1">
                          Crear y Agregar Otro
                        </button>
                      <?php endif; ?>
                      <?php if ($b == 1): ?>
                        <button class="btn btn-primary" type="button" id="btnEnviarAprobacion">
                          Enviar a aprobación
                        </button>
                      <?php endif; ?>
                      <button class="btn btn-danger" type="button" id="guardarYVolver">
                        Guardar y volver al Listado
                      </button>
                    </div>
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

  <div class="modal fade" id="modalEnviarAprobacion" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form id="formEnviarAprobacion" method="post" action="itemsComputo.php<?= $prodQuery ?>">
          <input type="hidden" name="modo" value="<?= htmlspecialchars($modo) ?>">
          <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
          <input type="hidden" name="revision" value="<?= htmlspecialchars($revision) ?>">
          <input type="hidden" name="prod" value="<?= $_REQUEST['prod'] ?? '' ?>">
          <input type="hidden" name="idOrigen" value="<?= $idComputoPadre ?>">
          <input type="hidden" name="btn2_confirm" value="1">
          <div class="modal-header">
            <h5 class="modal-title">Confirmar envío a aprobación</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <p>¿Estás seguro de que quieres enviar esta revisión a aprobación?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Confirmar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php
  $pdo = Database::connect();
  $sql = "SELECT d.id, m.concepto, d.cantidad, d.id_computo
        FROM computos_detalle d
        INNER JOIN materiales m ON m.id = d.id_material
        WHERE d.id_computo = " . $id;
  foreach ($pdo->query($sql) as $row) { ?>
    <div class="modal fade" id="eliminarModal_<?= $row["id"] ?>" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea eliminar el ítem del cómputo?</div>
          <div class="modal-footer">
            <a href="eliminarItemComputo.php?id=<?= $row["id"] ?>&idComputo=<?= $row["id_computo"] ?>&revision=<?= $revision ?>&idOrigen=<?= $idComputoPadre ?><?= $prodParam ?>"
              class="btn btn-primary">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal">Volver</button>
          </div>
        </div>
      </div>
    </div>
  <?php }
  Database::disconnect(); ?>

  <!-- Scripts -->
  <script src="assets/js/jquery-3.2.1.min.js"></script>
  <script src="assets/js/bootstrap/popper.min.js"></script>
  <script src="assets/js/bootstrap/bootstrap.js"></script>
  <script src="assets/js/icons/feather-icon/feather.min.js"></script>
  <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
  <script src="assets/js/sidebar-menu.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/chat-menu.js"></script>
  <script src="assets/js/tooltip-init.js"></script>
  <script src="assets/js/script.js"></script>
  <script src="assets/js/select2/select2.full.min.js"></script>
  <script src="assets/js/select2/select2-custom.js"></script>
  <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.buttons.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/jszip.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/buttons.colVis.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/pdfmake.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/vfs_fonts.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.autoFill.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.select.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/buttons.bootstrap4.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/buttons.html5.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/buttons.print.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.responsive.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/responsive.bootstrap4.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.keyTable.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.colReorder.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.fixedHeader.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.rowReorder.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/dataTables.scroller.min.js"></script>
  <script src="assets/js/datatable/datatable-extension/custom.js"></script>

  <script>
    const prod = <?= $prod ?? 0 ?>;
    const prodQuery = prod ? '?prod=' + prod : '';
    const prodParam = prod ? '&prod=' + prod : '';
    const idOrigen = <?= $idComputoPadre ?>;

    $(document).ready(function() {

      $('#btnEnviarAprobacion').on('click', function() {
        const $id_material    = $("#id_material");
        const $cantidad       = $("#cantidad");
        const $fecha_necesidad = $("#fecha_necesidad");
        if ($id_material.length && $cantidad.length && $fecha_necesidad.length) {
          if ($id_material.val() !== "" || $cantidad.val() !== "" || $fecha_necesidad.val() !== "") {
            alert("No se puede enviar a aprobación si hay ítems sin guardar.");
            return false;
          }
        }
        $('#modalEnviarAprobacion').modal('show');
      });

      $("#guardarYVolver").on("click", function() {
        const $id_material    = $("#id_material");
        const $cantidad       = $("#cantidad");
        const $fecha_necesidad = $("#fecha_necesidad");
        if ($id_material.length && $cantidad.length && $fecha_necesidad.length) {
          if ($id_material.val() !== "" || $cantidad.val() !== "" || $fecha_necesidad.val() !== "") {
            alert("No se puede guardar y volver si hay ítems sin cargar.");
            return false;
          }
        }
        window.location.href = "listarComputos.php" + prodQuery;
        return false;
      });

      $('#dataTables-example667 tfoot th').each(function() {
        var title = $(this).text();
        $(this).html('<input type="text" size="' + title.length + '" placeholder="' + title + '" />');
      });

      var table667 = $('#dataTables-example667').DataTable({
        stateSave: false,
        responsive: false,
        language: {
          "decimal": "",
          "emptyTable": "No hay información",
          "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
          "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
          "infoFiltered": "(Filtrado de _MAX_ total registros)",
          "infoPostFix": "",
          "thousands": ",",
          "lengthMenu": "Mostrar _MENU_ Registros",
          "loadingRecords": "Cargando...",
          "processing": "Procesando...",
          "search": "Buscar:",
          "zeroRecords": "No hay resultados",
          "paginate": {
            "first": "Primero",
            "last": "Ultimo",
            "next": "Siguiente",
            "previous": "Anterior"
          }
        }
      });

      table667.columns().every(function() {
        var that = this;
        $('input', this.footer()).on('keyup change', function() {
          if (that.search() !== this.value) {
            that.search(this.value).draw();
          }
        });
      });
    });
  </script>
  <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
</body>

</html>