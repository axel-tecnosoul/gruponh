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
  header("Location: listarPackingList.php");
  exit;
}

if (!empty($_POST)) {

} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT plr.id, plr.id_packing_list, plr.id_proyecto, plr.fecha, plr.id_usuario, plr.id_estado_packing_list, plr.nro_revision, plr.anulado FROM packing_lists_revisiones plr WHERE plr.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  if (empty($data)) {
    Database::disconnect();
    header("Location: listarPackingList.php");
    exit;
  }
  
  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
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
          $ubicacion="Ver Packing List";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" action="">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado(*)</label>
							<div class="col-sm-9">
							<select name="id_estado_packing_list" id="id_estado_packing_list" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_packing_list` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_estado_packing_list']) {
									echo " selected ";
								}
								echo ">".$fila['estado']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Revisiones</label>
								<div class="col-sm-9">
								<div class="timeline-small">
								  <?php 
									$pdo = Database::connect();
									$sql = " SELECT nro_revision, descripcion, date_format(fecha,'%d/%m/%y %H:%i') FROM packing_lists_revisiones WHERE id_packing_list = ".(int)$data['id_packing_list']." ORDER BY nro_revision ASC ";
									
									$revisiones = [];
									foreach ($pdo->query($sql) as $row) {
										$revisiones[] = $row;
									}
									
									if (count($revisiones) == 0) {
										echo '<div class="media">';
										echo '<div class="timeline-round m-r-30 timeline-line-1 bg-primary"><i data-feather="message-circle"></i></div>';
										echo '<div class="media-body">';
										echo '<h6>Revisión: -</h6>';
										echo '<p>Sin revisiones registradas</p>';
										echo '</div></div>';
									} else {
										foreach ($revisiones as $row) {
											$fechaRev = !empty($row[3]) ? $row[3].'hs' : '';
											echo '<div class="media">';
											echo '<div class="timeline-round m-r-30 timeline-line-1 bg-primary"><i data-feather="message-circle"></i></div>';
											echo '<div class="media-body">';
											echo '<h6>Revisión: '.$row[1].($fechaRev != '' ? ' <span class="pull-right f-14">'.$fechaRev.'</span>' : '').'</h6>';
											echo '<p>'.(isset($row[2]) ? htmlspecialchars($row[2]) : '').'</p>';
											echo '</div></div>';
										}
									}
									
									Database::disconnect();
								  ?>
								</div>
								</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <a href="#" onclick="document.location.href='listarPackingList.php'" class="btn btn-light">Volver</a>
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

    <!-- Plugin used-->

  </body>
</html>