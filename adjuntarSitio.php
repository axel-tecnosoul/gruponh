<?php
    require("config.php");
    if(empty($_SESSION['user']))
    {
        header("Location: index.php");
        die("Redirecting to index.php"); 
    }
	
	require 'database.php';

	$id = null;
	if ( !empty($_GET['id'])) {
		$id = $_REQUEST['id'];
	}
	
	if ( null==$id ) {
		header("Location: listarSitios.php");
	}
	
	if ( !empty($_POST)) {
		
		// insert data
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		/*
		$filename = $_FILES['adjunto']['name'];
		move_uploaded_file($_FILES['adjunto']['tmp_name'],'adjuntos_sitios/'.$id.'_'.$filename);
			
		$sql = "INSERT INTO `adjuntos_sitio`(`id_sitio`, `archivo`, `descripcion`, `anulado`) VALUES (?,?,?,0)";
		$q = $pdo->prepare($sql);
		$q->execute(array($id,$id.'_'.$filename,$_POST['descripcion']));
		*/
		$sql = "INSERT INTO `adjuntos_sitio`(`id_sitio`, `archivo`, `descripcion`, `anulado`) VALUES (?,?,?,0)";
		$q = $pdo->prepare($sql);
		$q->execute(array($id,$_POST['adjunto'],$_POST['descripcion']));
		
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Se ha agregado un documento adjunto al sitio','Sitios','verSitio.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
		Database::disconnect();
		header("Location: adjuntarSitio.php?id=".$id);	
		
	} else {
		
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = "SELECT `id`, `id_sitio_superior`, `nombre`, `direccion`, `latitud`, `longitud`, `observaciones`, `id_pais`, `id_provincia`, `id_localidad`, `id_tipo_estructura`, `altura`, `ancho_cara`, `peso_estructura`, `id_tipo_montaje`, `paso`, `beta`, `rugosidad`, `id_cuenta_duenio`, `nro_subsitio`, `nro_sitio` FROM `sitios` WHERE id = ? ";
        $q = $pdo->prepare($sql);
		$q->execute(array($id));
		$data = $q->fetch(PDO::FETCH_ASSOC);
		
		Database::disconnect();
	}
	
?>
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
        <div class="page-body">
          <div class="container-fluid">
            <div style="padding-top:10px">
              
            </div>
          </div>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Adjuntar Archivo a Sitio</h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="adjuntarSitio.php?id=<?php echo $id?>" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Descripción</th>
									  <th>Adjunto</th>
									  <th>Opciones</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT `id`, `descripcion`, `archivo` FROM `adjuntos_sitio` WHERE `anulado` = 0 and `id_sitio` = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td><a target="_blank" href="'.$row[2].'">Descargar</a></td>';
										echo '<td>';
										if (!empty(tienePermiso(274))) {
											echo '<a href="#" data-toggle="modal" data-target="#eliminarModal_'.$row[0].'"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
											echo '&nbsp;&nbsp;';
										}
										echo '</td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
								<tfoot>
								  <tr>
									  <th>Descripción</th>
									  <th>Adjunto</th>
									  <th>Opciones</th>
								  </tr>
								</tfoot>
							  </table>
							</div>
							</div>
							
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Descripción del adjunto(*)</label>
								<div class="col-sm-9"><input name="descripcion" type="text" value="" class="form-control" required="required"></div>
							</div>
							<!--
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Archivo(*)</label>
								<div class="col-sm-9"><input name="adjunto" type="file" value="" class="form-control" required="required"></div>
							</div>
							-->
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Archivo(*)</label>
								<div class="col-sm-9"><input name="adjunto" type="text" value="" class="form-control" required="required"></div>
							</div>
                        </div>
                      </div>
                    </div>
					
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Adjuntar</button>
						<a onclick="document.location.href='listarSitios.php'" class="btn btn-light">Volver</a>
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
	<?php
    $pdo = Database::connect();
   $sql = " SELECT `id`, `descripcion`, `archivo`,`id_sitio` FROM `adjuntos_sitio` WHERE `anulado` = 0 and `id_sitio` = ".$_GET['id'];
   foreach ($pdo->query($sql) as $row) {
    ?>
	  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
		  <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
		  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		  </div>
		  <div class="modal-body">¿Está seguro que desea eliminar el adjunto?</div>
		  <div class="modal-footer">
		  <a href="eliminarAdjuntoSitio.php?id=<?php echo $row[0]; ?>&idSitio=<?php echo $row[3]; ?>" class="btn btn-primary">Eliminar</a>
		  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
		  </div>
		</div>
		</div>
	  </div>
	<?php
	}
	Database::disconnect();
	?>
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
	<!-- Plugins JS start-->
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
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
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
        }}
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
        } );
		} );
	} );
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>