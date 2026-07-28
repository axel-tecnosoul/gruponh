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
                $existentes = [];
                $pdo->beginTransaction();

                $qExiste = $pdo->prepare("SELECT id FROM regimenes_facturacion WHERE codigo = ? AND COALESCE(articulo,'') = COALESCE(?,'')");
                $qInsert = $pdo->prepare("INSERT INTO regimenes_facturacion (codigo, articulo, regimen, porcentaje, monto, anulado) VALUES (?, ?, ?, ?, ?, 0)");
                $qUpdate = $pdo->prepare("UPDATE regimenes_facturacion SET regimen = ?, porcentaje = ?, monto = ?, anulado = 0 WHERE codigo = ? AND COALESCE(articulo,'') = COALESCE(?,'')");

                foreach ($rows as $i => $row) {
                    $codigo     = trim((string)($row[0] ?? ''));
                    $articulo   = trim((string)($row[1] ?? ''));
                    $regimen    = trim((string)($row[2] ?? ''));
                    $monto      = str_replace(',', '.', trim((string)($row[8] ?? '')));
                    $porcentaje = str_replace(',', '.', trim((string)($row[9] ?? '')));

                    $esEncabezado = (
                        stripos($codigo, 'res_cod')  !== false ||
                        stripos($codigo, 'codigo')   !== false ||
                        stripos($codigo, 'código')   !== false ||
                        stripos($codigo, 'letras')   !== false ||
                        stripos($codigo, 'números')  !== false
                    );
                    if ($esEncabezado) {
                        continue;
                    }

                    if (empty($codigo)) {
                        continue;
                    }

                    $porcentaje = $porcentaje !== '' ? (float)$porcentaje : 0;
                    $monto      = $monto      !== '' ? (float)$monto      : 0;

                    $qExiste->execute([$codigo, $articulo]);
                    $existente = $qExiste->fetch(PDO::FETCH_ASSOC);

                    if ($existente) {
                        $qUpdate->execute([$regimen, $porcentaje, $monto, $codigo, $articulo]);
                        $actualizados++;
                    } else {
                        $qInsert->execute([$codigo, $articulo, $regimen, $porcentaje, $monto]);
                        $insertados++;
                    }

                    $existentes[] = $codigo . '|' . $articulo;
                }

                // Soft-delete: marcar anulado=1 los que no están en el Excel
                if (!empty($existentes)) {
                    $sqlTodos = "SELECT id, codigo, articulo FROM regimenes_facturacion WHERE anulado = 0";
                    $qTodos = $pdo->query($sqlTodos);
                    while ($reg = $qTodos->fetch(PDO::FETCH_ASSOC)) {
                        $key = $reg['codigo'] . '|' . ($reg['articulo'] ?? '');
                        if (!in_array($key, $existentes)) {
                            $pdo->prepare("UPDATE regimenes_facturacion SET anulado = 1 WHERE id = ?")->execute([$reg['id']]);
                        }
                    }
                }

                $pdo->commit();
                $resultado = "Importación completada: $insertados nuevos, $actualizados actualizados.";

                $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,'Importación Excel Regimenes','Regimenes','')";
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
          $ubicacion = "Importar Regímenes";
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
                      <strong>Formato esperado del Excel (Bejerman - tabla_ret_perc):</strong><br>
                      <strong>A:</strong> Código (res_Cod) &nbsp;|&nbsp;
                      <strong>B:</strong> Artículo (res_Art) &nbsp;|&nbsp;
                      <strong>C:</strong> Descripción (res_Desc) &nbsp;|&nbsp;
                      <strong>I:</strong> Porcentaje (res_Porcentaje) &nbsp;|&nbsp;
                      <strong>J:</strong> Monto (res_Monto)<br>
                      <small>Si el código + artículo ya existe, se actualiza régimen, porcentaje y monto. Registros no presentes en el Excel se marcan como anulados.</small>
                    </div>

                    <form class="form theme-form" role="form" method="post" enctype="multipart/form-data" action="importRegimenes.php">
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
                          <a href="listarRegimenes.php" class="btn btn-light">Volver al Listado</a>
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
