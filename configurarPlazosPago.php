<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/permisos.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$guard = $pdo->query("SELECT id FROM acciones WHERE accion='Menu_Empresas' LIMIT 1")->fetchColumn();
if ($guard && !tienePermiso((int)$guard)) {
    Database::disconnect();
    header('Location: dashboard.php');
    exit;
}
if (empty($_SESSION['csrf_plazos_pago'])) {
    $_SESSION['csrf_plazos_pago'] = bin2hex(random_bytes(32));
}
$error = '';
$saved = isset($_GET['guardado']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)$_SESSION['csrf_plazos_pago'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'La sesión del formulario venció. Actualice la página e intente nuevamente.';
    } elseif (!is_array($_POST['dias'] ?? null)) {
        $error = 'Los plazos recibidos no son válidos.';
    } else {
        $rows = $pdo->query('SELECT id FROM formas_pago')->fetchAll(PDO::FETCH_COLUMN);
        $days = [];
        foreach ($rows as $id) {
            $raw = trim((string)($_POST['dias'][$id] ?? ''));
            if ($raw === '') {
                $days[(int)$id] = null;
                continue;
            }
            $value = filter_var($raw, FILTER_VALIDATE_INT);
            if ($value === false || $value < 0 || $value > 3650) {
                $error = 'Ingrese días enteros entre 0 y 3650, o deje vacío si no hay un plazo único.';
                break;
            }
            $days[(int)$id] = (int)$value;
        }
        if ($error === '') {
            try {
                $pdo->beginTransaction();
                $update = $pdo->prepare('UPDATE formas_pago SET dias_vencimiento = ? WHERE id = ?');
                foreach ($days as $id => $value) {
                    $update->execute([$value, $id]);
                }
                $pdo->commit();
                Database::disconnect();
                header('Location: configurarPlazosPago.php?guardado=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'No se pudieron guardar los plazos: ' . $e->getMessage();
            }
        }
    }
}
$paymentTerms = $pdo->query('SELECT id, forma_pago, dias_vencimiento FROM formas_pago ORDER BY forma_pago')->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();
?>
<!DOCTYPE html>
<html lang="es">
<head><?php include('head_forms.php'); ?></head>
<body>
<div class="page-wrapper">
  <?php include('header.php'); ?>
  <div class="page-body-wrapper">
    <?php include('menu.php'); ?>
    <div class="page-body">
      <?php $ubicacion = 'Plazos de vencimiento de pago'; include_once('head_page.php'); ?>
      <div class="container-fluid">
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($saved): ?><div class="alert alert-success">Los plazos se guardaron.</div><?php endif; ?>
        <div class="card">
          <div class="card-header"><h5>Plazos por condición de venta</h5></div>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_plazos_pago'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="card-body">
              <p class="text-muted">Estos días se usan para proponer el vencimiento de una Factura de Crédito Electrónica MiPyME. Configure un plazo solo cuando la condición tenga un vencimiento único; la fecha seguirá siendo editable en la factura.</p>
              <div class="table-responsive">
                <table class="table table-bordered table-sm">
                  <thead><tr><th>Condición de venta</th><th style="width:220px">Días hasta vencimiento</th></tr></thead>
                  <tbody>
                  <?php foreach ($paymentTerms as $term): ?>
                    <tr>
                      <td><?= htmlspecialchars($term['forma_pago'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><input type="number" name="dias[<?= (int)$term['id'] ?>]" min="0" max="3650" step="1" class="form-control" value="<?= $term['dias_vencimiento'] === null ? '' : (int)$term['dias_vencimiento'] ?>" placeholder="Sin plazo configurado"></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer">
              <button type="submit" class="btn btn-primary">Guardar plazos</button>
              <a href="javascript:window.close()" class="btn btn-light">Cerrar</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php include('footer.php'); ?>
  </div>
</div>
<script src="assets/js/jquery-3.2.1.min.js"></script>
<script src="assets/js/bootstrap/popper.min.js"></script>
<script src="assets/js/bootstrap/bootstrap.js"></script>
<script src="assets/js/script.js"></script>
</body>
</html>
