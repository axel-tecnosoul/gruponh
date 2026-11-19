<?php
require("config.php");
require_once("PHPMailer/class.phpmailer.php");
require_once("PHPMailer/class.smtp.php");

if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}

require 'database.php';

$prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodQuery = $prod ? '?prod=' . $prod : '';
$prodParam = $prod ? '&prod=' . $prod : '';

$idPackingListRevision = isset($_GET['id_packing_list_revision']) ? (int)$_GET['id_packing_list_revision'] : null;
$packingListEdicion = null;
if ($idPackingListRevision) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT plr.id, plr.id_proyecto, plr.fecha, plr.nombre, plr.id_estado_packing_list, plr.nro_revision, pl.ultimo_nro_revision FROM packing_lists_revisiones plr INNER JOIN packing_lists pl ON pl.id = plr.id_packing_list WHERE plr.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$idPackingListRevision]);
  $packingListEdicion = $q->fetch(PDO::FETCH_ASSOC);
  Database::disconnect();

  if (empty($packingListEdicion) || (int)$packingListEdicion['nro_revision'] !== (int)$packingListEdicion['ultimo_nro_revision']) {
    header("Location: listarPackingList.php" . $prodQuery);
    exit;
  }

  // Los cambios de revisión se inician desde el listado, que valida estado y motivo.
  if ((int)$packingListEdicion['id_estado_packing_list'] !== 1) {
    header("Location: listarPackingList.php" . $prodQuery);
    exit;
  }
}

if (!empty($_POST)) {
    
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if ($idPackingListRevision) {
    $sql = "UPDATE packing_lists_revisiones SET fecha = ?, id_proyecto = ?, nombre = ? WHERE id = ? AND id_estado_packing_list = 1";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['fecha'], $_POST['id_proyecto'], $_POST['nombre'], $idPackingListRevision]);

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificación de Packing List','Packing List')";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id']]);

    Database::disconnect();
    header("Location: nuevaPackingListSecciones.php?id_packing_list_revision=" . $idPackingListRevision . $prodParam);
    exit;
  }
  
  $idTarea = null;
  if (!empty($_POST['id_tarea'])) {
	$idTarea = $_POST['id_tarea'];	  
  }


  $nro_revision=0;
  $descripcion="Emision original";

  $sql = "INSERT INTO packing_lists (ultimo_nro_revision,id_tarea) VALUES ($nro_revision,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$idTarea]);
  
  $id_packing_list = $pdo->lastInsertId();
  
	$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['id_proyecto']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$descripcionProyecto = $data['nro_sitio']." - ".$data['nro_subsitio']." - ".$data['nro']." - ".$data['nombre'];
  
  $sql = "SELECT count(id) cant FROM packing_lists_revisiones where descripcion = 'Emision original' and id_proyecto = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST['id_proyecto']]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $nroPL = $data['cant']+1;
  
	$idCuentaRealizo = null;
	$idCuentaReviso = null;
	$idCuentaValido = null;

	$sql = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_SESSION['user']['id']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	if (!empty($data)) {
		$idCuentaRealizo = $data['id'];
	}

  $sql = "INSERT INTO packing_lists_revisiones (id_packing_list, id_proyecto, nombre, fecha, id_usuario, id_estado_packing_list, nro_revision, descripcion, anulado, numero,`id_cuenta_realizo`, `id_cuenta_reviso`, `id_cuenta_valido`) VALUES (?,?,?,?,?,1,$nro_revision,?,0,?,?,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$id_packing_list,$_POST['id_proyecto'],$_POST['nombre'],$_POST['fecha'],$_SESSION['user']['id'],$descripcion,$nroPL,$idCuentaRealizo,$idCuentaReviso,$idCuentaValido]);


  $id_packing_list_revision = $pdo->lastInsertId();

  // Iniciar la tarea una vez creado su entregable (la revisión original del Packing List).
  if ($idTarea !== null) {
    $sql = "UPDATE tareas SET fecha_inicio_real = IF(fecha_inicio_real IS NULL OR fecha_inicio_real = '0000-00-00', NOW(), fecha_inicio_real) WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$idTarea]);
  }

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva Packing List','Packing List','verPackingList.php?id=".$id_packing_list_revision.$prodParam."')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));
  
  Database::disconnect();
  //header("Location: listarPackingList.php");
  header("Location: nuevaPackingListSecciones.php?id_packing_list_revision=".$id_packing_list_revision.$prodParam);
}

$ubicacion = $idPackingListRevision ? "Modificar packing list" : "Nueva packing list";
$fechaPackingList = $packingListEdicion['fecha'] ?? date('Y-m-d');
$nombrePackingList = $packingListEdicion['nombre'] ?? '';
$idProyectoSeleccionado = $packingListEdicion['id_proyecto'] ?? ($_GET['idProyecto'] ?? '');
$formParams = [];
if ($idPackingListRevision) $formParams['id_packing_list_revision'] = $idPackingListRevision;
if ($prod) $formParams['prod'] = $prod;
$formAction = 'nuevaPackingList.php' . ($formParams ? '?' . http_build_query($formParams) : '');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      
    </style>
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
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
                                          <form class="form theme-form" role="form" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES) ?>" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha(*)</label>
                            <div class="col-sm-9"><input name="fecha" type="date" autofocus onfocus="this.showPicker()" class="form-control" required="required" value="<?= htmlspecialchars(substr((string)$fechaPackingList, 0, 10), ENT_QUOTES) ?>"></div>
                          </div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nombre PL(*)</label>
                            <div class="col-sm-9"><input name="nombre" type="text" class="form-control" required="required" value="<?= htmlspecialchars((string)$nombrePackingList, ENT_QUOTES) ?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Proyecto(*)</label>
                            <div class="col-sm-9">
                              <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
								
								if (!empty($_GET['idTarea'])) {
									$sql = "SELECT id_proyecto from tareas where id = ?";
									$q = $pdo->prepare($sql);
									$q->execute([$_GET['idTarea']]);
									$data = $q->fetch(PDO::FETCH_ASSOC);
									$idProyectoSeleccionado = $data['id_proyecto'];
								}
								
                                $sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
								  if ($idProyectoSeleccionado == $fila['id']) {
									  echo " selected ";
								  }
                                  echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                                                  <input type="hidden" name="id_tarea" value="<?php if (!empty($_GET['idTarea'])) echo $_GET['idTarea'];?>" />
                                                  <input type="hidden" name="prod" value="<?= $_REQUEST['prod'] ?? '' ?>" />
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit"><?= $idPackingListRevision ? 'Modificar y continuar' : 'Crear y agregar Secciones' ?></button>
                                                            <a href="listarPackingList.php<?= $prodQuery ?>" class="btn btn-light">Volver</a>
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
	  <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.buttons.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/jszip.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.colVis.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/pdfmake.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/vfs_fonts.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.autoFill.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.select.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.html5.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.print.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.responsive.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/responsive.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.keyTable.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.colReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.fixedHeader.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.rowReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.scroller.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <script>
      $(document).ready(function() {
        // Setup - add a text input to each footer cell
        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
        $('#dataTables-example667').DataTable({
          stateSave: false,
          responsive: false,
          language: {
            "decimal": "",
            "emptyTable": "No hay información",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
            "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
            "infoFiltered": "(Filtrado de _MAX_ total registros)",
            "infoPostFix": "",
            "thousands": ",",
            "lengthMenu": "Mostrar _MENU_ Registros",
            "loadingRecords": "Cargando...",
            "processing": "Procesando...",
            "search": "Buscar:",
            "zeroRecords": "No hay resultados",
            "paginate": {
              "first": "Primero",
              "last": "Ultimo",
              "next": "Siguiente",
              "previous": "Anterior"
            }
          }
        });
  
        // DataTable
        var table = $('#dataTables-example667').DataTable();
    
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that
              .search( this.value )
              .draw();
            }
          });
        });
      });
      
      </script>
      <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
      <!-- Plugin used-->
	
	    <!-- Page-Level Demo Scripts - Tables - Use for reference -->
   
  </body>
</html>
