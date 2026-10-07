<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }

    require 'database.php';

    // Control de acceso: misma acción que el listado (Menu_Empresas)
    try {
        $pdoGuard = Database::connect();
        $qGuard = $pdoGuard->prepare("SELECT id FROM acciones WHERE accion='Menu_Empresas' LIMIT 1");
        $qGuard->execute();
        $idAccEmpresas = $qGuard->fetchColumn();
        if ($idAccEmpresas && empty(tienePermiso((int)$idAccEmpresas))) {
            header("Location: dashboard.php");
            die("Redirecting to dashboard.php");
        }
    } catch (Exception $e) {}

    $id = null;
    if (!empty($_GET['id'])) {
        $id = $_REQUEST['id'];
    }

    if (null==$id) {
        header("Location: listarEmpresas.php");
        die();
    }

    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT `id`, `empresa`, `cuit`, `direccion`, `email`, `id_condicion_iva`, `iibb`, `fecha_inicio_actividades` FROM `empresas` WHERE id = ? ";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if (empty($data)) {
        Database::disconnect();
        header("Location: listarEmpresas.php");
        die();
    }

    // El CUIT solo se puede cargar una vez: mientras este vacio (0 / 00000000000) queda editable
    $cuitActual = trim((string)($data['cuit'] ?? ''));
    $cuitEditable = ($cuitActual === '' || preg_match('/^0+$/', str_replace('-', '', $cuitActual)));

    if (!empty($_POST)) {

        // update data
        $fechaInicio = !empty($_POST['fecha_inicio_actividades']) ? $_POST['fecha_inicio_actividades'] : null;
        $idCondicionIva = !empty($_POST['id_condicion_iva']) ? $_POST['id_condicion_iva'] : null;

        // Si el CUIT ya fue cargado se conserva el valor original
        $cuit = $cuitEditable ? trim($_POST['cuit'] ?? '') : $cuitActual;

        $sql = "UPDATE `empresas` set `cuit` = ?, `direccion` = ?, `email` = ?, `id_condicion_iva` = ?, `iibb` = ?, `fecha_inicio_actividades` = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$cuit, $_POST['direccion'] ?? '', $_POST['email'] ?? '', $idCondicionIva, $_POST['iibb'] ?? '', $fechaInicio, $id]);

		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de empresa','Empresas','')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

        Database::disconnect();

        header("Location: listarEmpresas.php");
        die();
    }

    Database::disconnect();

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
	  <?php include('header.php');?>

      <!-- Page Header Start-->
      <div class="page-body-wrapper">
		<?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Modificar Empresa ".$data['id']." - ".$data['empresa'];
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarEmpresa.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <!-- COLUMNA IZQUIERDA -->
                        <div class="col-md-6">
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Empresa</label>
                              <div class="col-sm-8"><input name="empresa" type="text" maxlength="99" class="form-control" readonly value="<?php echo $data['empresa']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">CUIT(*)</label>
                              <div class="col-sm-8"><input name="cuit" type="text" maxlength="99" class="form-control" <?php echo $cuitEditable ? 'required="required"' : 'readonly'; ?> value="<?php echo $cuitActual; ?>">
                              <?php if (!$cuitEditable) { ?><small class="form-text text-muted">El CUIT solo se puede cargar una vez.</small><?php } ?>
                              </div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Condición frente al IVA(*)</label>
                              <div class="col-sm-8">
                                <select name="id_condicion_iva" id="id_condicion_iva" class="js-example-basic-single col-sm-12" required="required">
                                  <option value="">Seleccione...</option>
                                  <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT `id`, `condicion_iva` FROM `condiciones_iva` WHERE 1";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='".$fila['id']."'";
                                    if ($fila['id'] == $data['id_condicion_iva']) {
                                      echo " selected ";
                                    }
                                    echo ">".$fila['condicion_iva']."</option>";
                                  }
                                  Database::disconnect();
                                  ?>
                                </select>
                              </div>
                            </div>
                        </div>
                        <!-- COLUMNA DERECHA -->
                        <div class="col-md-6">
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Dirección(*)</label>
                              <div class="col-sm-8"><input name="direccion" type="text" maxlength="199" class="form-control" required="required" value="<?php echo $data['direccion']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Email(*)</label>
                              <div class="col-sm-8"><input name="email" type="email" maxlength="99" class="form-control" required="required" value="<?php echo $data['email']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Ingresos Brutos(*)</label>
                              <div class="col-sm-8"><input name="iibb" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['iibb']; ?>"></div>
                            </div>
                            <div class="form-group row">
                              <label class="col-sm-4 col-form-label">Fecha inicio de actividades(*)</label>
                              <div class="col-sm-8"><input name="fecha_inicio_actividades" type="date" class="form-control" required="required" value="<?php echo $data['fecha_inicio_actividades']; ?>"></div>
                            </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12 text-right">
                        <button class="btn btn-primary" type="submit">Modificar</button>
						<a onclick="document.location.href='listarEmpresas.php'" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
		<?php include("footer.php"); ?>
      </div>
    </div>
    <!-- latest jquery-->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap js-->
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <!-- feather icon js-->
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <!-- Sidebar jquery-->
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <!-- Plugins JS start-->
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	<script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
  </body>
</html>
