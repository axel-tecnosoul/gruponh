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
		header("Location: listarProyectos.php");
	}
	
	if ( !empty($_POST)) {
		
		// insert data
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$sql = "INSERT INTO `proyectos_presupuestos`(`id_proyecto`, `id_presupuesto`) VALUES (?,?)";
		$q = $pdo->prepare($sql);
		$q->execute(array($id,$_POST['id_presupuesto']));
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Se ha añadido un presupuesto al proyecto','Proyectos','verProyecto.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
		
		Database::disconnect();
		header("Location: agregarPresupuestoProyecto.php?id=".$id);	
		
	} else {
		
		$pdo = Database::connect();
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = "SELECT `id`, `id_sitio`, `descripcion`, `id_tipo_proyecto`, `observaciones`, `solicitante`, `fecha_pedido`, `fecha_entrega`, `id_estado_proyecto`, `id_usuario`, `informacion_entrada`, `facturado`, `id_gerente`, `id_linea_negocio`, `anulado`, `id_cliente` FROM `proyectos` WHERE id = ? ";
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
                    <h5>Añadir Presupuesto/s a Proyecto</h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="agregarPresupuestoProyecto.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Nro</th>
									  <th>Fecha</th>
									  <th>Cliente</th>
									  <th>Solicitante</th>
									  <th>Referencia</th>
									  <th>Descripción</th>
									  <th>Monto</th>
									  <th>Adjudicado</th>
									  <th>Opciones</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT p.nro, p.nro_revision, date_format(p.fecha,'%d/%m/%y'), c.nombre, p.solicitante, p.referencia, p.descripcion, m.moneda, p.monto, p.adjudicado,pp.id FROM proyectos_presupuestos pp inner join presupuestos p on p.id = pp.id_presupuesto inner join cuentas c on c.id = p.id_cuenta inner join monedas m on m.id = p.id_moneda WHERE p.anulado = 0 ";
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[0] . ' / '.$row[1].'</td>';
										echo '<td>'. $row[2] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										echo '<td>'. $row[4] . '</td>';
										echo '<td>'. $row[5] . '</td>';
										echo '<td>'. $row[6] . '</td>';
										echo '<td>'. $row[7] . ' ' . number_format($row[8],2). '</td>';
										if ($row[9] == 1) {
											echo '<td>Si</td>';
										} else {
											echo '<td>No</td>';
										}
										echo '<td>';
										if (!empty(tienePermiso(306))) {
											echo '<a href="#" data-toggle="modal" data-target="#eliminarModal_'.$row[10].'"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Quitar Presupuesto" title="Quitar Presupuesto"></a>';
											echo '&nbsp;&nbsp;';
										}
										echo '</td>';
										echo '</tr>';?>

                    <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                          </div>
                          <div class="modal-body">¿Está seguro que desea quitar el presupuesto del proyecto?</div>
                          <div class="modal-footer">
                            <a href="eliminarPresupuestoProyecto.php?id=<?php echo $row[10]; ?>" class="btn btn-primary">Eliminar</a>
                            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
                          </div>
                        </div>
                      </div>
                    </div><?php
									}
								   Database::disconnect();
								  ?>
								</tbody>
								<tfoot>
								  <tr>
									  <th>Nro</th>
									  <th>Fecha</th>
									  <th>Cliente</th>
									  <th>Solicitante</th>
									  <th>Referencia</th>
									  <th>Descripción</th>
									  <th>Monto</th>
									  <th>Adjudicado</th>
									  <th>Opciones</th>
								  </tr>
								</tfoot>
							  </table>
							</div>
							</div>
							
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Presupuesto(*)</label>
							<div class="col-sm-9">
							<select name="id_presupuesto" id="id_presupuesto" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT p.nro, p.nro_revision, date_format(p.fecha,'%Y') anio,c.nombre,p.descripcion,m.moneda,p.monto,p.id FROM presupuestos p inner join cuentas c on c.id = p.id_cuenta inner join monedas m on m.id = p.id_moneda WHERE p.anulado = 0 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nro'].' / '.$fila['nro_revision'].' - '.$fila['nombre'].' - '.$fila['descripcion'].' - '.$fila['moneda'].number_format($fila['monto'],2)."</option>";
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
                        <button class="btn btn-primary" type="submit">Añadir</button>
						<a onclick="document.location.href='listarProyectos.php'" class="btn btn-light">Volver</a>
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
		"createdRow": function(row, data, dataIndex) {
            // Limitar el texto de la columna 0 (Name)
            var maxLength = 20; // Número máximo de caracteres
            var cell = $('td', row).eq(3); // Obtener la celda de la primera columna
            var cellText = cell.text();

            if (cellText.length > maxLength) {
                var truncatedText = cellText.substring(0, maxLength) + '...';
                cell.html('<span title="' + cellText + '">' + truncatedText + '</span>');
            }
			var cell = $('td', row).eq(4); // Obtener la celda de la primera columna
            var cellText = cell.text();

            if (cellText.length > maxLength) {
                var truncatedText = cellText.substring(0, maxLength) + '...';
                cell.html('<span title="' + cellText + '">' + truncatedText + '</span>');
            }
			var cell = $('td', row).eq(5); // Obtener la celda de la primera columna
            var cellText = cell.text();

            if (cellText.length > maxLength) {
                var truncatedText = cellText.substring(0, maxLength) + '...';
                cell.html('<span title="' + cellText + '">' + truncatedText + '</span>');
            }
        },
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