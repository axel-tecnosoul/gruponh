<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';

    $id = null;
    if (!empty($_GET['id'])) {
        $id = $_REQUEST['id'];
    }
    
    if (null==$id) {
        header("Location: listarEventos.php");
    }
    
    if (!empty($_POST)) {
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `fecha`, `titulo`, `resumen`, `descripcion`, `id_relevancia`, `muestra_calendario`, `adjunto` FROM `anuncios_dashboard` WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        
        Database::disconnect();
    }
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<script src="https://cdn.tiny.cloud/1/bn4vd1dl0xp5581pwxv1oxb4clgeslj5gei7b7uf570gsj32/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
	<script>
    tinymce.init({
      selector: '#mytextarea'
    });
  </script>
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
          $ubicacion="Ver Datos de Evento de Calendario";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha(*)</label>
							<div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha']; ?>" readonly="readonly"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Título(*)</label>
							<div class="col-sm-9"><input name="titulo" type="text" maxlength="99" class="form-control" value="<?php echo $data['titulo']; ?>" readonly="readonly"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Resumen(*)</label>
							<div class="col-sm-9"><textarea name="resumen" maxlength="199" class="form-control" readonly="readonly"><?php echo $data['resumen']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción Larga</label>
							<div class="col-sm-9"><textarea id="mytextarea" name="descripcion" class="form-control" disabled="disabled"><?php echo $data['descripcion']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Relevancia(*)</label>
							<div class="col-sm-9">
							<select name="id_relevancia" id="id_relevancia" class="js-example-basic-single col-sm-12" disabled="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `relevancia` FROM `relevancias` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_relevancia']) {
                                        echo " selected ";
								}
								echo ">".$fila['relevancia']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">¿Muestra en Calendario?(*)</label>
							<div class="col-sm-9">
							<select name="muestra_calendario" id="muestra_calendario" class="js-example-basic-single col-sm-12" disabled="disabled">
							<option value="">Seleccione...</option>
							<option value="1" <?php if ($data['muestra_calendario']==1) {
                                    echo " selected ";
                                }?>>Si</option>
								<option value="0" <?php if ($data['muestra_calendario']==0) {
                                    echo " selected ";
                                }?>>No</option>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Destinatarios</label>
							<div class="col-sm-9">
							<select name="id_cuenta_destino[]" id="id_cuenta_destino[]" multiple="multiple" class="js-example-basic-single col-sm-12" disabled="disabled">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
									$sql2 = " SELECT id from anuncios_dashboard_cuentas where id_anuncio = ? and id_cuenta_destino = ? ";
									$q2 = $pdo->prepare($sql2);
									$q2->execute([$id, $fila['id']]);
									$data22 = $q2->fetch(PDO::FETCH_ASSOC);
									if (!empty($data22)) {
										echo " selected ";
									}
                                  echo ">".$fila['nombre']."</option>";
                                }
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Adjunto</label>
								<div class="col-sm-9"><a target="_blank" href="<?php echo $data['adjunto']; ?>">Ver adjunto</a></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<a onclick="document.location.href='listarEventos.php'" class="btn btn-light">Volver</a>
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