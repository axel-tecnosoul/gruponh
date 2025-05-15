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
        header("Location: listarListasCorte.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "INSERT INTO `listas_corte_detalle_posiciones`(`id_lista_corte`, `id_lista_corte_detalle`, `posicion`, `largo`, `ancho`, `id_estado_lista_corte_detalle_posiciones`) VALUES (?,?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$id,$_POST['id_lista_corte_detalle'],$_POST['posicion'],$_POST['largo'],$_POST['ancho'],$_POST['id_estado_lista_corte_detalle_posiciones']]);
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva posición de lista de corte','Listas de Corte','imprimirListaCorte.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        
        Database::disconnect();
        if (!empty($_POST['btn2'])) {
			header("Location: listarListasCorte.php");	
		} else {
			header("Location: conjuntosListaCorte.php?id=".$id);	
		}
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
        <div class="page-body"><?php
          $ubicacion="Conjuntos de Lista de Corte";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="conjuntosListaCorte.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Concepto</th>
									  <th>Posición</th>
									  <th>Largo</th>
									  <th>Ancho</th>
									  <th>Estado</th>
									  <th>Opciones</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT p.`id`, p.`id_lista_corte`, p.`id_lista_corte_detalle`, m.concepto, p.`posicion`, p.`largo`, p.`ancho`, e.`estado` FROM `listas_corte_detalle_posiciones` p inner join listas_corte_detalle d on d.id = p.`id_lista_corte_detalle` inner join materiales m on m.id = d.id_material inner join estados_detalle_lista_corte_posiciones e on e.id = p.`id_estado_lista_corte_detalle_posiciones` WHERE p.id_lista_corte = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[3] . '</td>';
										echo '<td>'. $row[4] . '</td>';
										echo '<td>'. $row[5] . '</td>';
										echo '<td>'. $row[6] . '</td>';
										echo '<td>'. $row[7] . '</td>';
										echo '<td>';
										echo '<a href="modificarConjuntoListaCorte.php?id='.$row[0].'&idLC='.$row[1].'"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Actualizar" title="Actualizar"></a>';
										echo '&nbsp;&nbsp;';
										echo '<a href="procesosConjuntosListaCorte.php?id='.$row[0].'"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Procesos" title="Procesos"></a>';
										echo '&nbsp;&nbsp;';
										echo '</td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
								<tfoot>
								  <tr>
									  <th>Concepto</th>
									  <th>Posición</th>
									  <th>Largo</th>
									  <th>Ancho</th>
									  <th>Estado</th>
									  <th>Opciones</th>
								  </tr>
								</tfoot>
							  </table>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Concepto(*)</label>
							<div class="col-sm-9">
							<select name="id_lista_corte_detalle" id="id_lista_corte_detalle" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT d.id, m.concepto FROM `listas_corte_detalle` d inner join materiales m on m.id = d.`id_material` WHERE d.`id_lista_corte` = ".$_GET['id'];
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['concepto']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Posición(*)</label>
							<div class="col-sm-9"><input name="posicion" type="text" maxlength="99" class="form-control" required="required" value=""></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Largo(*)</label>
							<div class="col-sm-9"><input name="largo" type="number" step="0.01" class="form-control" required="required" value=""></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Ancho(*)</label>
							<div class="col-sm-9"><input name="ancho" type="number" step="0.01" class="form-control" required="required" value=""></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado(*)</label>
							<div class="col-sm-9">
							<select name="id_estado_lista_corte_detalle_posiciones" id="id_estado_lista_corte_detalle_posiciones" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_detalle_lista_corte_posiciones` WHERE 1 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['estado']."</option>";
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
                        <button class="btn btn-success" type="submit" value="1" name="btn1">Confirmar y Agregar Otro</button>
						<button class="btn btn-primary" type="submit" value="2" name="btn2">Confirmar y Volver al Listado</button>
						<a onclick="document.location.href='listarListasCorte.php'" class="btn btn-danger">Volver al Listado</a>
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
    $sql = " SELECT pd.`id`, pd.`detalle`, pd.`cantidad`, um.unidad_medida, pd.`costo`, pd.`precio`,pd.`id_presupuesto` FROM `presupuestos_detalle` pd inner join unidades_medida um on um.id = pd.`id_unidad_medida` WHERE pd.`id_presupuesto` = ".$_GET['id'];
	foreach ($pdo->query($sql) as $row) {
    ?>
	  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
		  <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
		  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		  </div>
		  <div class="modal-body">¿Está seguro que desea eliminar el ítem del presupuesto?</div>
		  <div class="modal-footer">
		  <a href="eliminarItemPresupuesto.php?id=<?php echo $row[0]; ?>&idPresupuesto=<?php echo $row[6]; ?>" class="btn btn-primary">Eliminar</a>
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