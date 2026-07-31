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

    function parsearDimensiones($concepto, $idCategoria, $largoExcel) {
        $espesor = null;
        $ancho   = null;
        $largo   = $largoExcel ? (float)$largoExcel : null;

        $conceptoNorm = str_replace(',', '.', $concepto);
        preg_match_all('/\d+[\.\,]?\d*/', $conceptoNorm, $matches);
        $nums = array_map(function($v) { return floatval($v); }, $matches[0]);

        $conceptoUpper = strtoupper($conceptoNorm);

        // Chapas (CAT 6): espesor x ancho x largo
        if ($idCategoria == 6) {
            if (count($nums) >= 3) {
                $espesor = $nums[0];
                $ancho   = $nums[1];
                $largo   = $nums[2];
            } elseif (count($nums) >= 2) {
                $espesor = $nums[0];
                $ancho   = $nums[1];
            } elseif (count($nums) >= 1) {
                $espesor = $nums[0];
            }
            return [$espesor, $ancho, $largo];
        }

        // Perfiles: el número anterior a la "x" es el ancho, largo de Excel (obligatorio)
        if (strpos($conceptoUpper, 'PERFIL') !== false) {
            if (preg_match('/(\d+[\.\,]?\d*)\s*x/i', $conceptoNorm, $m)) {
                $ancho = floatval($m[1]);
            }
            // En Perfiles el largo DEBE venir del Excel
            return [$espesor, $ancho, $largo];
        }

        // Planchuelas (CAT 10): primer número es ancho
        if ($idCategoria == 10) {
            if (count($nums) >= 1) {
                $ancho = $nums[0];
            }
            return [$espesor, $ancho, $largo];
        }

        // Varillas / Bulones (CAT 3): número >= 1000 es largo, ignorar el previo a la x
        if ($idCategoria == 3) {
            foreach ($nums as $n) {
                if ($n >= 1000) {
                    $largo = $n;
                    break;
                }
            }
            return [$espesor, $ancho, $largo];
        }

        // Resto de categorías: usar largo del Excel si existe
        return [$espesor, $ancho, $largo];
    }

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

                $qCategoria = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
                $qUnidad = $pdo->prepare("SELECT id FROM unidades_medida WHERE id = ?");
                $qExiste = $pdo->prepare("SELECT id FROM materiales WHERE codigo = ?");
                $qInsert = $pdo->prepare("INSERT INTO materiales (codigo, concepto, descripcion, largo, peso_metro, id_categoria, id_unidad_medida, stock_minimo, anulado, calidad, perimetro, espesor, ancho) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $qUpdate = $pdo->prepare("UPDATE materiales SET concepto = ?, descripcion = ?, largo = ?, peso_metro = ?, id_categoria = ?, id_unidad_medida = ?, stock_minimo = ?, anulado = ?, calidad = ?, perimetro = ?, espesor = ?, ancho = ? WHERE codigo = ?");

                foreach ($rows as $i => $row) {
                    $id_col = trim($row[0] ?? '');
                    $codigo = trim($row[1] ?? '');
                    $concepto = trim($row[2] ?? '');
                    $descripcion = trim($row[3] ?? '');
                    $largo = str_replace(',', '.', trim($row[4] ?? ''));
                    $peso_metro = str_replace(',', '.', trim($row[5] ?? ''));
                    $id_categoria = str_replace(',', '.', trim($row[6] ?? ''));
                    $id_unidad_medida = str_replace(',', '.', trim($row[7] ?? ''));
                    $stock_minimo = str_replace(',', '.', trim($row[8] ?? ''));
                    $anulado = trim($row[9] ?? '');

                    $esEncabezado = (
                        stripos($id_col, 'Base de datos') !== false ||
                        stripos($id_col, 'Propósito')      !== false ||
                        stripos($id_col, 'erpdb')          !== false ||
                        stripos($codigo, 'codigo')         !== false ||
                        stripos($codigo, 'letras')         !== false ||
                        stripos($codigo, 'números')        !== false ||
                        stripos($concepto, 'concepto')     !== false ||
                        stripos($concepto, 'Permite')      !== false
                    );
                    if ($esEncabezado) {
                        continue;
                    }

                    if (empty($codigo) || empty($concepto)) {
                        if (!empty($codigo) || !empty($concepto)) {
                            $errores[] = "Fila " . ($i + 1) . ": Falta código o concepto.";
                        }
                        continue;
                    }

                    if (strlen($codigo) > 99) {
                        $errores[] = "Fila " . ($i + 1) . ": Código excede 99 caracteres.";
                        continue;
                    }
                    if (strlen($concepto) > 99) {
                        $errores[] = "Fila " . ($i + 1) . ": Concepto excede 99 caracteres.";
                        continue;
                    }

                    if (empty($id_categoria)) {
                        $errores[] = "Fila " . ($i + 1) . ": Falta id_categoria.";
                        continue;
                    }
                    $id_categoria = (int)$id_categoria;

                    $qCategoria->execute([$id_categoria]);
                    if (!$qCategoria->fetch(PDO::FETCH_ASSOC)) {
                        $errores[] = "Fila " . ($i + 1) . " (código: " . htmlspecialchars($codigo) . "): Categoría ID '$id_categoria' no encontrada.";
                        continue;
                    }

                    $id_unidad_medida = $id_unidad_medida !== '' ? (int)$id_unidad_medida : null;
                    if ($id_unidad_medida !== null) {
                        $qUnidad->execute([$id_unidad_medida]);
                        if (!$qUnidad->fetch(PDO::FETCH_ASSOC)) {
                            $errores[] = "Fila " . ($i + 1) . ": Unidad de medida ID '$id_unidad_medida' no encontrada.";
                            continue;
                        }
                    }

                    $largo = $largo !== '' ? (float)$largo : 0;
                    $peso_metro = $peso_metro !== '' ? (float)$peso_metro : 0;
                    $stock_minimo = $stock_minimo !== '' ? (float)$stock_minimo : 0;
                    $anulado = $anulado !== '' ? (int)$anulado : 0;
                    $descripcion = $descripcion !== '' ? $descripcion : '';

                    // Parsear dimensiones desde el concepto
                    list($espesor, $ancho, $largoParsed) = parsearDimensiones($concepto, $id_categoria, $largo);
                    if ($largoParsed !== null) {
                        $largo = $largoParsed;
                    }
                    $espesor = $espesor !== null ? $espesor : 0;
                    $ancho   = $ancho   !== null ? $ancho   : 0;

                    // Validar: Perfiles requieren largo del Excel
                    $conceptoUpper = strtoupper($concepto);
                    if (strpos($conceptoUpper, 'PERFIL') !== false && $largo <= 0) {
                        $errores[] = "Fila " . ($i + 1) . " (código: " . htmlspecialchars($codigo) . "): Perfil sin largo en columna E.";
                        continue;
                    }

                    $qExiste->execute([$codigo]);
                    $existente = $qExiste->fetch(PDO::FETCH_ASSOC);

                    if ($existente) {
                        $qUpdate->execute([$concepto, $descripcion, $largo, $peso_metro, $id_categoria, $id_unidad_medida, $stock_minimo, $anulado, '', 0, $espesor, $ancho, $codigo]);
                        $actualizados++;
                    } else {
                        $qInsert->execute([$codigo, $concepto, $descripcion, $largo, $peso_metro, $id_categoria, $id_unidad_medida, $stock_minimo, $anulado, '', 0, $espesor, $ancho]);
                        $insertados++;
                    }
                }

                $pdo->commit();
                $resultado = "Importación completada: $insertados nuevos, $actualizados actualizados.";

                $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,'Importación Excel Conceptos','Conceptos','')";
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
          $ubicacion = "Importar Conceptos";
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
                      <strong>A:</strong> ID (se ignora) &nbsp;|&nbsp;
                      <strong>B:</strong> Código(*) &nbsp;|&nbsp;
                      <strong>C:</strong> Concepto(*) &nbsp;|&nbsp;
                      <strong>D:</strong> Descripción &nbsp;|&nbsp;
                      <strong>E:</strong> Largo (mm) &nbsp;|&nbsp;
                      <strong>F:</strong> Peso x Metro (kg) &nbsp;|&nbsp;
                      <strong>G:</strong> ID Categoría(*) &nbsp;|&nbsp;
                      <strong>H:</strong> ID Unidad Medida &nbsp;|&nbsp;
                      <strong>I:</strong> Stock Mínimo &nbsp;|&nbsp;
                      <strong>J:</strong> Anulado<br>
                      <small>Las dimensiones (espesor, ancho) se extraen automáticamente del concepto según la categoría. (*) obligatorio.</small>
                    </div>

                    <form class="form theme-form" role="form" method="post" enctype="multipart/form-data" action="importMateriales.php">
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
