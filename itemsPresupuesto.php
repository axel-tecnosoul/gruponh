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
        header("Location: listarPresupuestos.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		//agregar validacion antes de insertar para validar si el total por los items no supera el monto total del presupuesto
		$sql = "SELECT sum(d.precio) total, p.monto FROM `presupuestos_detalle` d inner join presupuestos p on p.id = d.`id_presupuesto` WHERE d.`id_presupuesto` = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$id]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$msg = "";
		$total = 0;
		if (!empty($data['total'])) {
			$total = $data['total'];
		} else {
			$sql = "SELECT p.monto FROM presupuestos p WHERE p.`id` = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$id]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
		}
		if ($total + $_POST['precio'] > $data['monto']) {
			$msg = "El item ingresado sobrepasa el monto total del presupuesto";
		} else {
			$sql = "INSERT INTO `presupuestos_detalle`(`id_presupuesto`, `detalle`, `cantidad`, `id_unidad_medida`, `costo`, `precio`) VALUES (?,?,?,?,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$id,$_POST['detalle'],$_POST['cantidad'],$_POST['id_unidad_medida'],$_POST['costo'],$_POST['precio']]);
			
			$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Se ha modificado un presupuesto','Presupuestos','verPresupuesto.php?id=$id')";
			$q = $pdo->prepare($sql);
			$q->execute(array($_SESSION['user']['id']));
			
			Database::disconnect();
			if ($_POST['btn']==2) {
				header("Location: listarPresupuestos.php");	
			} else {
				header("Location: itemsPresupuesto.php?id=".$id);	
			}
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
          $ubicacion="Ver/Añadir Items Presupuesto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" id="form1" role="form" method="post" action="itemsPresupuesto.php?id=<?php echo $id?>" onsubmit="validarFormulario(event)">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Item</th>
									  <th>Cantidad</th>
									  <th>Unidad Medida</th>
									  <th>Costo</th>
									  <th>Precio</th>
									  <th>Opciones</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT pd.`id`, pd.`detalle`, pd.`cantidad`, um.unidad_medida, pd.`costo`, pd.`precio` FROM `presupuestos_detalle` pd inner join unidades_medida um on um.id = pd.`id_unidad_medida` WHERE pd.`id_presupuesto` = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td>'. $row[2] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										echo '<td>$'. number_format($row[4],2) . '</td>';
										echo '<td>$'. number_format($row[5],2) . '</td>';
										echo '<td>';
										if (!empty(tienePermiso(269))) {
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
									  <th>Item</th>
									  <th>Cantidad</th>
									  <th>Unidad Medida</th>
									  <th>Costo</th>
									  <th>Precio</th>
									  <th>Opciones</th>
								  </tr>
								</tfoot>
							  </table>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Item(*)</label>
							<div class="col-sm-9"><input name="detalle" type="text" maxlength="99" class="form-control" required="required" value="">
							<?php
							if (!empty($msg)) {
								echo "<i>".$msg."</i>";
							}
							?>
							</div>
							</div>
							
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required" value=""></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Unidad de Medida(*)</label>
							<div class="col-sm-9">
							<select name="id_unidad_medida" id="id_unidad_medida" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `unidad_medida` FROM `unidades_medida` WHERE 1 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['unidad_medida']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Costo(*)</label>
							<div class="col-sm-9"><input name="costo" id="costo" type="number" class="form-control" step="0.1" required="required" value=""></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Precio(*)</label>
							<div class="col-sm-9"><input name="precio" id="precio" type="number" class="form-control" step="0.1" required="required" value=""></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-success" type="submit" value="1" id="btn1">Crear y Agregar Otro</button>
						<button class="btn btn-primary" type="submit" value="2" id="btn2">Crear y Volver al Listado</button>
						<input type="hidden" name="btn" id="btn" value="0">
						<a onclick="document.location.href='listarPresupuestos.php'" class="btn btn-danger">Volver al Listado</a>
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
	<script>
        function validarFormulario(event) {
            // Prevenir el envío del formulario
            event.preventDefault();
            
            // Obtener los valores de los inputs
            const precioCosto = parseFloat(document.getElementById('costo').value);
            const precioVenta = parseFloat(document.getElementById('precio').value);
			const botonPulsado = event.submitter.id;
            
            // Validar que el precio de costo sea menor que el precio de venta
            if (precioCosto > precioVenta) {
                alert('El precio de costo debe ser menor o igual que el precio de venta.');
            } else {
                if (botonPulsado == 'btn1') {
                     document.getElementById('btn').value = 1;
                } else if (botonPulsado == 'btn2') {
                    document.getElementById('btn').value = 2;
                }
                document.getElementById('form1').submit();
            }
        }
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>