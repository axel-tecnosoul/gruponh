<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "INSERT INTO `devoluciones`(`fecha_hora`, `id_usuario`, `nro_obra`, `observaciones`) VALUES (now(),?,?,?)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_POST['id_usuario'],$_POST['nro_obra'],$_POST['observaciones']]);
		$idDev = $pdo->lastInsertId();
		
		$sql = "INSERT INTO `ingresos`(`fecha_hora`, `id_tipo_ingreso`, `nro`, `id_cuenta_recibe`, `lugar_entrega`, `observaciones`) VALUES (now(),2,?,?,'',?)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_POST['nro_obra'],$_POST['id_usuario'],$_POST['observaciones']]);
		$idIn = $pdo->lastInsertId();
		
		$sql = " SELECT m.`id`, m.`codigo`, m.`concepto`, c.categoria, m.id_unidad_medida FROM `materiales` m inner join categorias c on c.id = m.`id_categoria` WHERE m.`activo` = 1 and m.`anulado` = 0";				
		foreach ($pdo->query($sql) as $row) {
			if (!empty($_POST['cantidad_'.$row[0]])) {
				$sql = "INSERT INTO `devoluciones_detalle`(`id_devolucion`, `id_material`, `cantidad`) VALUES (?,?,?)";
				$q = $pdo->prepare($sql);		   
				$q->execute([$idDev,$row[0],$_POST['cantidad_'.$row[0]]]);
				
				$sql = "INSERT INTO ingresos_detalle(id_ingreso, id_material, id_unidad_medida, cantidad, cantidad_egresada, saldo) VALUES (?,?,?,?,?,?)";
				$q = $pdo->prepare($sql);		   
				$q->execute([$idIn,$row[0],$row[4],$_POST['cantidad_'.$row[0]],0,$_POST['cantidad_'.$row[0]]]);
				
				$ingresando = $_POST['cantidad_'.$row[0]];
				
			}
		}
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo ingreso por devolución','Ingresos','verIngreso.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

		
		Database::disconnect();
        header("Location: listarIngresos.php");
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
          $ubicacion="Nueva Devolución";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevaDevolucion.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Responsable(*)</label>
							<div class="col-sm-9">
							<select name="id_usuario" id="id_usuario" class="js-example-basic-single col-sm-12" autofocus required="required">
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
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
							<select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required="required" onchange="jsMaterialesAjax();">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"></textarea></div>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Código</th>
									  <th>Concepto</th>
									  <th>Categoría</th>
									  <th>En Stock</th>
									  <th>Cantidad a Ingresar</th>
								  </tr>
								</thead>
								<tbody>
								</tbody>
							  </table>
							</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Ingresar</button>
						<a href="listarIngresos.php" class="btn btn-light">Volver</a>
                      </div>
                    </div>
					<input type="hidden" name="nro_obra" id="nro_obra" value="" />
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
			$('#dataTables-example667').DataTable({
				stateSave: false,
				responsive: false,
				paging: false,
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
		});
		
		function jsMaterialesAjax() {
			var select = document.getElementById('id_proyecto');
			var selectedText = select.options[select.selectedIndex].text;
			document.getElementById('nro_obra').value = selectedText;
			get_conceptos(document.getElementById('id_proyecto').value);
		}
		
		function get_conceptos(id_proyecto){
			
		  let datosUpdate = new FormData();
		  datosUpdate.append('id_proyecto', id_proyecto);
		  $.ajax({
			data: datosUpdate,
			url: 'get_conceptos_devolucion.php',
			method: "post",
			cache: false,
			contentType: false,
			processData: false,
			success: function(data){
			  console.log(data);
			  data = JSON.parse(data);
			  console.log(data);

			  $('#dataTables-example667').DataTable().destroy();
			  $('#dataTables-example667').DataTable({
				stateSave: false,
				responsive: false,
				paging: false,
				data: data,
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

			  
			}
		  });
		}
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>