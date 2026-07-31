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
                $anulados = 0;
                $codigosImportados = [];
                $pdo->beginTransaction();

                $qTasa = $pdo->prepare("SELECT id FROM tipos_iva WHERE tasa = ?");
                $qExiste = $pdo->prepare("SELECT id FROM conceptos_contables WHERE codigo = ?");
                $qInsert = $pdo->prepare("INSERT INTO conceptos_contables (codigo, descripcion, id_alicuota_iva, anulado) VALUES (?, ?, ?, 0)");
                $qUpdate = $pdo->prepare("UPDATE conceptos_contables SET descripcion = ?, id_alicuota_iva = ?, anulado = 0 WHERE codigo = ?");

                foreach ($rows as $i => $row) {
                    $codigo = trim($row[0] ?? '');
                    $descripcion = trim($row[1] ?? '');
                    $tasaStr = trim($row[2] ?? '');

                    if (empty($codigo) || empty($descripcion)) {
                        if (!empty($codigo) || !empty($descripcion)) {
                            $errores[] = "Fila " . ($i + 1) . ": Falta código o descripción.";
                        }
                        continue;
                    }

                    $codigosImportados[] = $codigo;

                    $idAlicuota = null;
                    if ($tasaStr !== '') {
                        $qTasa->execute([$tasaStr]);
                        $tasaData = $qTasa->fetch(PDO::FETCH_ASSOC);
                        if ($tasaData) {
                            $idAlicuota = (int)$tasaData['id'];
                        } else {
                            $errores[] = "Fila " . ($i + 1) . ": Tasa IVA '" . htmlspecialchars($tasaStr) . "' no encontrada (usar 0.00, 10.50 o 21.00).";
                            continue;
                        }
                    }

                    $qExiste->execute([$codigo]);
                    $existente = $qExiste->fetch(PDO::FETCH_ASSOC);

                    if ($existente) {
                        $qUpdate->execute([$descripcion, $idAlicuota, $codigo]);
                        $actualizados++;
                    } else {
                        $qInsert->execute([$codigo, $descripcion, $idAlicuota]);
                        $insertados++;
                    }
                }

                $pdo->commit();

                if (!empty($codigosImportados)) {
                    $placeholders = implode(',', array_fill(0, count($codigosImportados), '?'));
                    $qAnular = $pdo->prepare("UPDATE conceptos_contables SET anulado = 1 WHERE codigo NOT IN ($placeholders) AND anulado = 0");
                    $qAnular->execute($codigosImportados);
                    $anulados = $qAnular->rowCount();
                }

                $resultado = "Importación completada: $insertados nuevos, $actualizados actualizados, $anulados anulados.";

                $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,'Importación Excel Conceptos Contables','Conceptos Contables','')";
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
          $ubicacion = "Importar Conceptos Contables";
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
                      <strong>A:</strong> Código &nbsp;|&nbsp; <strong>B:</strong> Descripción &nbsp;|&nbsp; <strong>C:</strong> Tasa IVA (0.00, 10.50 o 21.00)<br>
                      <small>Si el código ya existe, se actualiza la descripción y alícuota. Si no, se crea nuevo.</small>
                    </div>

                    <form class="form theme-form" role="form" method="post" enctype="multipart/form-data" action="importConceptosContables.php">
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
                          <a href="listarConceptosContables.php" class="btn btn-light">Volver al Listado</a>
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
