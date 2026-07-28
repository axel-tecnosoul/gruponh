<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }

    require 'database.php';
    require 'vendor/autoload.php';
    use PhpOffice\PhpSpreadsheet\IOFactory;

    $resultado = null;
    $errores = [];

    if (!empty($_FILES['excel'])) {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $file_mime_type = mime_content_type($_FILES['excel']['tmp_name']);
        $tiposValidos = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        if (in_array($file_mime_type, $tiposValidos)) {
            try {
                $file = $_FILES['excel']['tmp_name'];
                $spreadsheet = IOFactory::load($file);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                $insertados = 0;
                $actualizados = 0;
                $pdo->beginTransaction();

                $qExiste = $pdo->prepare("SELECT id FROM unidades_medida WHERE id = ?");
                $qInsert = $pdo->prepare("INSERT INTO unidades_medida (id, unidad_medida) VALUES (?, ?)");
                $qUpdate = $pdo->prepare("UPDATE unidades_medida SET unidad_medida = ? WHERE id = ?");

                foreach ($rows as $i => $row) {
                    $id = trim($row[0] ?? '');
                    $unidad = trim($row[1] ?? '');

                    $esEncabezado = (
                        stripos($id, 'id')          !== false ||
                        stripos($id, 'letras')      !== false ||
                        stripos($id, 'números')     !== false ||
                        stripos($unidad, 'unidad')  !== false
                    );
                    if ($esEncabezado) {
                        continue;
                    }

                    if (empty($id) || empty($unidad)) {
                        continue;
                    }

                    $id = (int)$id;

                    $qExiste->execute([$id]);
                    if ($qExiste->fetch(PDO::FETCH_ASSOC)) {
                        $qUpdate->execute([$unidad, $id]);
                        $actualizados++;
                    } else {
                        $qInsert->execute([$id, $unidad]);
                        $insertados++;
                    }
                }

                $pdo->commit();
                $resultado = "Importación completada: $insertados nuevos, $actualizados actualizados.";

                $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,'Importación Excel Unidades de Medida','Unidades de Medida','')";
                $pdo->prepare($sql)->execute([$_SESSION['user']['id']]);

            } catch (Exception $e) {
                $pdo->rollBack();
                $errores[] = "Error al procesar el archivo: " . $e->getMessage();
            }
        } else {
            $errores[] = "Formato de archivo no válido. Use .xlsx o .xls.";
        }

        Database::disconnect();
    }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  </head>
  <body>
    <div class="page-wrapper">
    <?php include('header.php');?>
      <div class="page-body-wrapper">
    <?php include('menu.php');?>
        <div class="page-body"><?php
          $ubicacion = "Importar Unidades de Medida";
          include_once("head_page.php")?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
                  <div class="card-body">

                    <?php if ($resultado): ?>
                      <div class="alert alert-success"><?= htmlspecialchars($resultado) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errores)): ?>
                      <div class="alert alert-warning">
                        <?php foreach ($errores as $err): ?>
                          <?= htmlspecialchars($err) ?><br>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>

                    <div class="alert alert-info">
                      <strong>Formato esperado del Excel (columnas):</strong><br>
                      <strong>A:</strong> ID (*) &nbsp;|&nbsp;
                      <strong>B:</strong> Unidad de Medida (*)<br>
                      <small>Si el ID ya existe, se actualiza el nombre. Si no, se crea nuevo. (*) obligatorio.</small>
                    </div>

                    <form class="form theme-form" role="form" method="post" enctype="multipart/form-data" action="importUnidadesMedida.php">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Archivo Excel(*)</label>
                            <div class="col-sm-9">
                              <input name="excel" type="file" accept=".xlsx,.xls" class="form-control" required="required">
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="card-footer">
                        <div class="col-sm-9 offset-sm-3">
                          <button class="btn btn-primary" type="submit">Importar</button>
                          <a href="listarMateriales.php" class="btn btn-light">Volver al Listado</a>
                        </div>
                      </div>
                    </form>
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
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
  </body>
</html>
