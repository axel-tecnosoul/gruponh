<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';

    $editMode = !empty($_GET['id']);
    $editId = $editMode ? (int)$_GET['id'] : null;
    $editData = null;

    if (!empty($_POST)) {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $idAlicuota = !empty($_POST['id_alicuota_iva']) ? (int)$_POST['id_alicuota_iva'] : null;

        if (!empty($_POST['edit_id'])) {
            $sql = "UPDATE `conceptos_contables` SET `codigo` = ?, `descripcion` = ?, `id_alicuota_iva` = ? WHERE id = ?";
            $q = $pdo->prepare($sql);
            $q->execute([$_POST['codigo'], $_POST['descripcion'], $idAlicuota, $_POST['edit_id']]);

            $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificacion Concepto Contable','Conceptos Contables','')";
            $q = $pdo->prepare($sql);
            $q->execute(array($_SESSION['user']['id']));
        } else {
            $sql = "INSERT INTO `conceptos_contables`(`codigo`, `descripcion`, `id_alicuota_iva`) VALUES (?,?,?)";
            $q = $pdo->prepare($sql);
            $q->execute([$_POST['codigo'], $_POST['descripcion'], $idAlicuota]);

            $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Concepto Contable','Conceptos Contables','')";
            $q = $pdo->prepare($sql);
            $q->execute(array($_SESSION['user']['id']));
        }

        Database::disconnect();
        header("Location: listarConceptosContables.php");
        exit;
    }

    if ($editMode) {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $q = $pdo->prepare("SELECT * FROM conceptos_contables WHERE id = ?");
        $q->execute([$editId]);
        $editData = $q->fetch(PDO::FETCH_ASSOC);
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
          $ubicacion = $editMode ? "Modificar Concepto Contable" : "Nuevo Concepto Contable";
          include_once("head_page.php")?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
          <form class="form theme-form" role="form" method="post" action="nuevaConceptoContable.php<?= $editMode ? '?id='.$editId : '' ?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Código(*)</label>
                            <div class="col-sm-9"><input name="codigo" type="text" maxlength="99" class="form-control" required="required" value="<?= $editData ? htmlspecialchars($editData['codigo']) : '' ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Descripción(*)</label>
                            <div class="col-sm-9"><input name="descripcion" type="text" maxlength="99" class="form-control" required="required" value="<?= $editData ? htmlspecialchars($editData['descripcion']) : '' ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Alícuota IVA</label>
                            <div class="col-sm-9">
                            <select name="id_alicuota_iva" id="id_alicuota_iva" class="js-example-basic-single col-sm-12">
                            <option value="">Sin IVA asignado</option>
                            <?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT `id`, `tasa` FROM `tipos_iva` ORDER BY tasa";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='".$fila['id']."'";
                                if ($editData && $editData['id_alicuota_iva'] == $fila['id']) {
                                    echo " selected ";
                                }
                                echo ">".$fila['tasa']."%</option>";
                            }
                            Database::disconnect();
                            ?>
                            </select>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <?php if ($editMode): ?>
                          <input type="hidden" name="edit_id" value="<?= $editId ?>">
                          <button class="btn btn-primary" type="submit">Modificar</button>
                        <?php else: ?>
                          <button class="btn btn-primary" type="submit">Crear</button>
                        <?php endif; ?>
                        <a href="listarConceptosContables.php" class="btn btn-light">Volver</a>
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
