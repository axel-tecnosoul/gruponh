<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = intval($_GET['id']);
}
if (null == $id) {
  header("Location: listarPedidos.php");
  exit();
}

// Procesar el formulario POST
if (!empty($_POST)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $sql = "UPDATE pedidos 
          SET id_proyecto = ?, fecha = ?, id_cuenta_solicitante = ? 
          WHERE id = ? AND id_computo IS NULL";
  $q = $pdo->prepare($sql);
  $q->execute([
    $_POST['id_proyecto'],
    $_POST['fecha'],
    $_POST['id_cuenta_solicitante'],
    $id
  ]);

  $sql_log = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
              VALUES (now(), ?, 'Se modificó la cabecera del pedido directo ID: $id', 'Pedidos', 'itemsPedidoDirecto.php?id=$id')";
  $q_log = $pdo->prepare($sql_log);
  $q_log->execute([$_SESSION['user']['id']]);

  Database::disconnect();

  $_SESSION['flash_message'] = [
    'type'    => 'success',
    'message' => 'Cabecera del pedido modificada correctamente.'
  ];

  // Botón "Guardar y Editar Ítems" o "Guardar y Volver"
  if (isset($_POST['btn_items'])) {
    header("Location: itemsPedidoDirecto.php?id=" . $id);
  } else {
    header("Location: listarPedidos.php");
  }
  exit();
}

// Cargar datos actuales del pedido directo
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT pe.id, pe.id_proyecto, pe.fecha, pe.id_cuenta_solicitante,
               p.nombre AS nombre_proyecto, ep.estado, ep.id AS id_estado
        FROM pedidos pe
        INNER JOIN proyectos p ON p.id = pe.id_proyecto
        INNER JOIN estados_pedidos ep ON ep.id = pe.id_estado
        WHERE pe.id = ? AND pe.id_computo IS NULL";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$pedido = $q->fetch(PDO::FETCH_ASSOC);
Database::disconnect();

if (!$pedido) {
  header("Location: listarPedidos.php");
  exit();
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
        <div class="page-body">
          <?php
            $ubicacion = "Modificar Cabecera – Pedido Directo N° " . $id;
            include_once("head_page.php");
          ?>
          <div class="container-fluid">

            <!-- Flash message -->
            <?php if (isset($_SESSION['flash_message'])):
              $flash   = $_SESSION['flash_message'];
              $alertCl = ($flash['type'] === 'success') ? 'alert-success' : 'alert-danger'; ?>
              <div class="alert <?= $alertCl ?> alert-dismissible fade show" role="alert">
                <?= $flash['message'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            <?php
              unset($_SESSION['flash_message']);
            endif; ?>

            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?= $ubicacion ?></h5>
                  </div>

                  <form class="form theme-form" method="post"
                        action="modificarPedidoDirecto.php?id=<?= $id ?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">

                          <!-- Proyecto -->
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Proyecto (*)</label>
                            <div class="col-sm-9">
                              <select name="id_proyecto" id="id_proyecto"
                                      class="js-example-basic-single col-sm-12"
                                      required="required">
                                <option value="">Seleccione...</option>
                                <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlProy = "SELECT p.id, p.nombre, p.nro, s.nro_sitio
                                              FROM proyectos p
                                              LEFT JOIN sitios s ON s.id = p.id_sitio
                                              WHERE p.anulado = 0
                                              ORDER BY p.nro ASC";
                                  $qProy = $pdo->prepare($sqlProy);
                                  $qProy->execute();
                                  while ($fila = $qProy->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($fila['id'] == $pedido['id_proyecto']) ? 'selected' : '';
                                    $label    = htmlspecialchars($fila['nro_sitio'] . '_' . $fila['nro'] . ' – ' . $fila['nombre']);
                                    echo "<option value='{$fila['id']}' $selected>$label</option>";
                                  }
                                  Database::disconnect();
                                ?>
                              </select>
                            </div>
                          </div>

                          <!-- Fecha de carga -->
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha de Carga (*)</label>
                            <div class="col-sm-9">
                              <input name="fecha" type="date"
                                     onfocus="this.showPicker()"
                                     class="form-control"
                                     required="required"
                                     value="<?= htmlspecialchars($pedido['fecha']) ?>">
                            </div>
                          </div>

                          <!-- Solicitante -->
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Solicitante (*)</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta_solicitante" id="id_cuenta_solicitante"
                                      class="js-example-basic-single col-sm-12"
                                      required="required">
                                <option value="">Seleccione...</option>
                                <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlCta = "SELECT id, nombre FROM cuentas WHERE anulado = 0 ORDER BY nombre ASC";
                                  $qCta   = $pdo->prepare($sqlCta);
                                  $qCta->execute();
                                  while ($fila = $qCta->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($fila['id'] == $pedido['id_cuenta_solicitante']) ? 'selected' : '';
                                    echo "<option value='{$fila['id']}' $selected>"
                                        . htmlspecialchars($fila['nombre'])
                                        . "</option>";
                                  }
                                  Database::disconnect();
                                ?>
                              </select>
                            </div>
                          </div>

                          <!-- Info de solo lectura -->
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Estado actual</label>
                            <div class="col-sm-9">
                              <input type="text" class="form-control"
                                     value="<?= htmlspecialchars($pedido['estado']) ?>"
                                     readonly>
                            </div>
                          </div>

                        </div><!-- /col -->
                      </div><!-- /row -->
                    </div><!-- /card-body -->

                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <!-- Guarda cabecera y va a editar ítems -->
                        <button class="btn btn-primary" type="submit" name="btn_items">
                          Guardar y Editar Ítems
                        </button>
                        <!-- Guarda cabecera y vuelve al listado -->
                        <button class="btn btn-success" type="submit" name="btn_volver">
                          Guardar y Volver al Listado
                        </button>
                        <!-- Cancela sin guardar -->
                        <a href="listarPedidos.php" class="btn btn-danger">Cancelar</a>
                      </div>
                    </div>
                  </form>

                </div><!-- /card -->
              </div>
            </div>

          </div><!-- /container-fluid -->
        </div><!-- /page-body -->
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
    <script src="assets/js/script.js"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
  </body>
</html>