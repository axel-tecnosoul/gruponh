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

$id = !empty($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
  header("Location: listarComputos.php$prodQuery");
  exit;
}

$idOrigen   = !empty($_REQUEST['idOrigen']) ? (int)$_REQUEST['idOrigen'] : $id;
$modoActual = $_REQUEST['modo'] ?? 'nuevo';
$idRetorno  = !empty($_GET['idRetorno']) ? (int)$_GET['idRetorno'] : null;

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmtEstPadre = $pdo->prepare("SELECT id_estado FROM computos WHERE id = ?");
$stmtEstPadre->execute([$idOrigen]);
$estadoPadre = (int)$stmtEstPadre->fetchColumn();

$esModoRestringido = ($modoActual === 'update' && $estadoPadre === ID_ESTADO_TERMINADO_COMPUTO);

if ($esModoRestringido) {
  $stmtProy = $pdo->prepare("
        SELECT p.id_estado_proyecto
        FROM computos c
        INNER JOIN tareas t    ON t.id = c.id_tarea
        INNER JOIN proyectos p ON p.id = t.id_proyecto
        WHERE c.id = ?
    ");
  $stmtProy->execute([$idOrigen]);
  $estadoProyecto = (int)$stmtProy->fetchColumn();

  if ($estadoProyecto === ID_ESTADO_TERMINADO_PROYECTO) {
    Database::disconnect();
    header("Location: listarComputos.php$prodQuery");
    exit;
  }
}

if (!empty($_POST)) {
  $nro_revision = $_POST['nro_revision'] ?? 0;
  $cantidad     = (float)$_POST['cantidad'];
  $modoPost     = $_POST['modo'] ?? $modoActual;

  if ($modoPost === 'update') {
    $stmtMatActual = $pdo->prepare("SELECT id_material, cantidad FROM computos_detalle WHERE id = ?");
    $stmtMatActual->execute([$id]);
    $detalleActual = $stmtMatActual->fetch(PDO::FETCH_ASSOC);

    $stmtCantOrig = $pdo->prepare("
            SELECT cantidad FROM computos_detalle 
            WHERE id_computo = ? AND id_material = ? AND cancelado = 0
            LIMIT 1
        ");
    $stmtCantOrig->execute([$idOrigen, $detalleActual['id_material']]);
    $cantidadOriginal = (float)$stmtCantOrig->fetchColumn();

    if ($cantidad > $cantidadOriginal) {
    Database::disconnect();
    header("Location: modificarItemComputo.php?id=$id&idRetorno=" . ($idRetorno ?? $id)
      . "&modo=$modoPost&revision=$nro_revision&idOrigen=$idOrigen&error=cantidad_aumentada$prodParam");
    exit;
  }
}

  $sql = "UPDATE computos_detalle 
            SET id_material = ?, cantidad = ?, saldo = ?, fecha_necesidad = ?, aprobado = 0, comentarios = ? 
            WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([
    $_POST['id_material'],
    $cantidad,
    $cantidad,
    $_POST['fecha_necesidad'],
    $_POST['comentarios'],
    $id
  ]);

  $idComputoRetorno = $idRetorno ?? (int)$_POST['idRetorno'] ?? null;
  if (!$idComputoRetorno) {
    $stmtComp = $pdo->prepare("SELECT id_computo FROM computos_detalle WHERE id = ?");
    $stmtComp->execute([$id]);
    $idComputoRetorno = (int)$stmtComp->fetchColumn();
  }

  Database::disconnect();
  header("Location: itemsComputo.php?id=" . $idComputoRetorno
    . "&modo=$modoPost&revision=$nro_revision&idOrigen=$idOrigen$prodParam");
  exit;
} else {
  $sql = "SELECT `id`, `id_computo`, `id_material`, `cantidad`, `fecha_necesidad`,
                   `aprobado`, `reservado`, `comprado`, `cancelado`, comentarios
            FROM `computos_detalle` WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $cantidadOriginal = $data['cantidad']; // valor por defecto
  if ($modoActual === 'update' && !empty($data['id_material'])) {
    $stmtCantOrig = $pdo->prepare("
      SELECT cantidad FROM computos_detalle 
      WHERE id_computo = ? AND id_material = ? AND cancelado = 0
      LIMIT 1
    ");
    $stmtCantOrig->execute([$idOrigen, $data['id_material']]);
    $fetched = $stmtCantOrig->fetchColumn();
    if ($fetched !== false) {
      $cantidadOriginal = (float)$fetched;
    }
  }

  Database::disconnect();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include('head_forms.php'); ?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
</head>

<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body"><?php
                              $ubicacion = "Modificar Item Cómputo";
                              include_once("head_page.php"); ?>
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5>
                    <?= $ubicacion ?>
                  </h5>
                </div>
                <form class="form theme-form" role="form" method="post"
                  action="modificarItemComputo.php?id=<?= $id ?>&idRetorno=<?= $idRetorno ?? $data['id_computo'] ?>&modo=<?= htmlspecialchars($modoActual) ?>&revision=<?= htmlspecialchars($_GET['revision'] ?? '0') ?>&idOrigen=<?= $idOrigen ?><?= $prodParam ?>">
                  <div class="card-body">
                    <div class="row">
                      <div class="col">

                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Concepto(*)</label>
                          <div class="col-sm-9">
                            <select name="id_material" id="id_material" autofocus
                              class="js-example-basic-single col-sm-12"
                              required="required"
                              <?= $esModoRestringido ? 'disabled' : '' ?>>
                              <option value="">Seleccione...</option><?php
                                                                      $pdo = Database::connect();
                                                                      $q   = $pdo->prepare("SELECT `id`, `concepto`, `codigo` FROM `materiales` WHERE anulado = 0");
                                                                      $q->execute();
                                                                      while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                                                        echo "<option value='" . $fila['id'] . "'";
                                                                        if ($data['id_material'] == $fila['id']) echo " selected";
                                                                        echo ">" . $fila['concepto'] . " (" . $fila['codigo'] . ")</option>";
                                                                      }
                                                                      Database::disconnect(); ?>
                            </select>
                            <?php if ($esModoRestringido): ?>
                              <input type="hidden" name="id_material" value="<?= (int)$data['id_material'] ?>">
                              <small class="text-muted">El concepto no puede modificarse en una revisión.</small>
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                          <div class="col-sm-9">
                            <?php
                            $maxAttr = ($modoActual === 'update')
                              ? 'max="' . $cantidadOriginal . '"'
                              : '';
                            ?>
                            <input name="cantidad" step="0.01" min="0.01"
                              <?= $maxAttr ?>
                              type="number" class="form-control"
                              required="required" value="<?= $data['cantidad'] ?>">
                            <?php if ($modoActual === 'update'): ?>
                              <small class="text-muted">
                                Cantidad máxima: <?= $cantidadOriginal ?> — no se puede aumentar en revisión.
                              </small>
                            <?php endif; ?>
                            <?php if (isset($_GET['error']) && $_GET['error'] === 'cantidad_aumentada'): ?>
                              <b>
                                <font color="red">No se puede aumentar la cantidad en una revisión.</font>
                              </b>
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Fecha Necesidad(*)</label>
                          <div class="col-sm-9"><?php $fecha_actual = date('Y-m-d'); ?>
                            <input name="fecha_necesidad" type="date" min="<?= $fecha_actual ?>"
                              onfocus="this.showPicker()" class="form-control"
                              required="required" value="<?= $data['fecha_necesidad'] ?>">
                          </div>
                        </div>

                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Comentarios</label>
                          <div class="col-sm-9">
                            <textarea name="comentarios" class="form-control"><?= $data['comentarios'] ?></textarea>
                          </div>
                          <input type="hidden" name="nro_revision" value="<?= $_GET['revision'] ?? '0' ?>">
                          <input type="hidden" name="modo" value="<?= htmlspecialchars($modoActual) ?>">
                          <input type="hidden" name="prod" value="<?= $_REQUEST['prod'] ?? '' ?>">
                          <input type="hidden" name="idOrigen" value="<?= $idOrigen ?>">
                        </div>

                      </div>
                    </div>
                  </div>
                  <div class="card-footer">
                    <div class="col-sm-9 offset-sm-3">
                      <button class="btn btn-primary" type="submit">Modificar</button>
                      <a href="itemsComputo.php?id=<?= $idRetorno ?? $data['id_computo'] ?>&modo=<?= $modoActual ?>&revision=<?= $_GET['revision'] ?? '0' ?>&idOrigen=<?= $idOrigen ?><?= $prodParam ?>"
                        class="btn btn-light">Volver</a>
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
  <script src="assets/js/jquery-3.2.1.min.js"></script>
  <script src="assets/js/bootstrap/popper.min.js"></script>
  <script src="assets/js/bootstrap/bootstrap.js"></script>
  <script src="assets/js/icons/feather-icon/feather.min.js"></script>
  <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
  <script src="assets/js/sidebar-menu.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/typeahead/handlebars.js"></script>
  <script src="assets/js/typeahead/typeahead.bundle.js"></script>
  <script src="assets/js/typeahead/typeahead.custom.js"></script>
  <script src="assets/js/chat-menu.js"></script>
  <script src="assets/js/tooltip-init.js"></script>
  <script src="assets/js/typeahead-search/handlebars.js"></script>
  <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
  <script src="assets/js/script.js"></script>
  <script src="assets/js/select2/select2.full.min.js"></script>
  <script src="assets/js/select2/select2-custom.js"></script>
</body>

</html>