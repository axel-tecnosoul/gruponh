<?php
require("config.php");
require("PHPMailer/class.phpmailer.php");
require("PHPMailer/class.smtp.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}

require 'database.php';

if (!empty($_POST)) {
    
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  $idTarea = null;
  if (!empty($_POST['id_tarea'])) {
	$idTarea = $_POST['id_tarea'];	  
  }

  $sql = "INSERT INTO listas_corte (ultimo_nro_revision,id_tarea) VALUES (0,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$idTarea]);
  $id_lista_corte = $pdo->lastInsertId();
  
  $sql = "update tareas set `fecha_inicio_real`=now(), `fecha_fin_real`=now() where id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$idTarea]);
  
  
	$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST['id_proyecto']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$descripcionProyecto = $data['nro_sitio']." - ".$data['nro_subsitio']." - ".$data['nro']." - ".$data['nombre'];
  
  $sql = "SELECT count(id) cant FROM listas_corte_revisiones where descripcion = 'Emisión original' and id_proyecto = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST['id_proyecto']]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $_POST['numero'] = $data['cant']+1;
  
	$idCuentaRealizo = null;
	$idCuentaReviso = $_POST['id_cuenta_reviso'];
	$idCuentaValido = $_POST['id_cuenta_valido'];

	$sql = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$_SESSION['user']['id']]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	if (!empty($data)) {
		$idCuentaRealizo = $data['id'];
	}

  $sql = "INSERT INTO listas_corte_revisiones (id_lista_corte, id_proyecto, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero, descripcion,`id_cuenta_realizo`, `id_cuenta_reviso`, `id_cuenta_valido`) VALUES (?,?,?,?,1,0,0,?,?,?,?,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte,$_POST['id_proyecto'],$_POST['fecha'],$_SESSION['user']['id'],$_POST['nombre'],$_POST['numero'],"Emisión original",$idCuentaRealizo,$idCuentaReviso,$idCuentaValido]);

  $id_lista_corte_revision = $pdo->lastInsertId();
  
  if (!empty($_POST['adjunto'])) {
    $sql = "UPDATE listas_corte_revisiones set adjunto = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute(array($_POST['adjunto'],$id_lista_corte_revision));
  }
  
  
  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva Lista de Corte','Listas de Corte','imprimirListaCorte.php?id=$id_lista_corte_revision')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));
  
	$sql = "SELECT valor FROM `parametros` WHERE id = 1 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpHost = $data['valor'];  
	
	$sql = "SELECT valor FROM `parametros` WHERE id = 2 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpUsuario = $data['valor'];  
	
	$sql = "SELECT valor FROM `parametros` WHERE id = 3 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpClave = $data['valor'];  
	
	$sql = "SELECT valor FROM `parametros` WHERE id = 4 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpFrom = $data['valor'];  
	
	$sql = "SELECT valor FROM `parametros` WHERE id = 5 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpFromName = $data['valor'];  
	
	$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 5 ";
	foreach ($pdo->query($sql) as $row) {
		
		$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (5,?,now(),0,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$row[0],'ID Lista de Corte: #'.$id_lista_corte_revision,$id_lista_corte_revision]);
		
		$address = $row[1];
		
		$titulo = "ERP Notificaciones - Módulo Ingeniería - Nueva Lista de Corte (".$descripcionProyecto.")";
		$mensaje="Nueva lista de corte dada de alta en el sistema: #".$id_lista_corte_revision;
		
		$mail = new PHPMailer();
		$mail->IsSMTP();
		$mail->SMTPAuth = true;
		$mail->Port = 25; 
		$mail->SMTPSecure = 'ssl';
		$mail->SMTPAutoTLS = false;
		$mail->SMTPSecure = false;
		$mail->IsHTML(true); 
		$mail->CharSet = "utf-8";
		$mail->From = $smtpFrom;
		$mail->FromName = $_SESSION['user']['usuario'];
		$mail->Host = $smtpHost; 
		$mail->Username = $smtpUsuario; 
		$mail->Password = $smtpClave;
		$mail->AddAddress($address);
		$mail->Subject = $titulo; 
		$mensajeHtml = nl2br($mensaje);
		$mail->Body = "{$mensajeHtml} <br /><br />"; 
		$mail->AltBody = "{$mensaje} \n\n"; 
		$mail->Send();
	
	}
	
	

  Database::disconnect();
  //header("Location: listarListasCorte.php");
  header("Location: nuevaListaCorteConjuntos.php?id_lista_corte_revision=".$id_lista_corte_revision);
}?>
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
          $ubicacion="Nueva Lista de Corte";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				          <form class="form theme-form" role="form" method="post" action="nuevaListaCorte.php" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha(*)</label>
                            <div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" autofocus class="form-control" required="required" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nombre de la LC(*)</label>
                            <div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control" required="required" value=""></div>
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
									$_GET['idProyecto'] = $data['id_proyecto'];
								}
								
                                $sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
								  if ((isset($_GET['idProyecto'])) && ($_GET['idProyecto'] == $fila['id'])) {
									  echo " selected ";
								  }
                                  echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Revisó(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_reviso" id="id_cuenta_reviso" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Aprobó(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_valido" id="id_cuenta_valido" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Adjuntar Plano</label>
                            <div class="col-sm-9"><input name="adjunto" type="text" value="" class="form-control"></div>
                            <input type="hidden" name="hId" value="1" />
                          </div>
						  <input type="hidden" name="id_tarea" value="<?php if (!empty($_GET['idTarea'])) echo $_GET['idTarea'];?>" />
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear y agregar Conjuntos</button>
						            <a href="listarListasCorte.php" class="btn btn-light">Volver</a>
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