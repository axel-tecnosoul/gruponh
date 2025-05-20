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
		
		if (empty($_POST['id_recurso'])) {
			$_POST['id_recurso'] = null;
		}
		if (empty($_POST['id_coordinador'])) {
			$_POST['id_coordinador'] = null;
		}

		$sql = "INSERT INTO `tareas`(`id_proyecto`, `estructura`, `id_sector`, `id_tipo_tarea`, `id_recurso`, `observaciones`, `id_coordinador`, `fecha_inicio_estimada`, `fecha_fin_estimada`, `fecha_inicio_real`, `fecha_fin_real`, `anulado`) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_POST['id_proyecto'],$_POST['estructura'],$_POST['id_sector'],$_POST['id_tipo_tarea'],$_POST['id_recurso'],$_POST['observaciones'],$_POST['id_coordinador'],$_POST['fecha_inicio_estimada'],$_POST['fecha_fin_estimada'],$_POST['fecha_inicio_real'],$_POST['fecha_fin_real']]);

    $id = $pdo->lastInsertId();
        
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva tarea de proyecto','Tareas','verTarea.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
		if (!empty($_POST['btn2'])) {
			header("Location: listarTareas.php");
		} else {
			header("Location: nuevaTarea.php?id=".$_POST['id_proyecto']);
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
          $ubicacion="Nueva Tarea";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevaTarea.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th class="d-none">ID</th>
									  <th>Estructura</th>
									  <th>Sector</th>
									  <th>Tarea</th>
									  <th>Recurso</th>
									  <th>Coordinador</th>
									  <th>Observaciones</th>
									  <th>FIP</th>
									  <th>FFP</th>
									  <th>FIR</th>
									  <th>FFR</th>
									  <th>Completada</th>
									  <th>Cómputo</th>
									  <th>Cómputo ID</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									if (!empty($_GET['id'])) {
										$sql = " SELECT t.`id`, p.`nombre`, s.nombre, t.`estructura`, sec.`sector`, tt.`tipo`, c.`nombre`, date_format(t.`fecha_inicio_estimada`,'%d/%m/%y'), date_format(t.`fecha_fin_estimada`,'%d/%m/%y'), date_format(t.`fecha_inicio_real`,'%d/%m/%y'), date_format(t.`fecha_fin_real`,'%d/%m/%y'), c2.`nombre`,t.observaciones FROM `tareas` t inner join proyectos p on p.id = t.`id_proyecto` inner join sitios s on s.id = p.id_sitio inner join sectores sec on sec.id = t.`id_sector` inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` left join cuentas c on c.id = t.`id_coordinador` left join cuentas c2 on c2.id = t.`id_recurso` WHERE t.`anulado` = 0 and p.anulado = 0 and id_proyecto = ".$_GET['id'];

										foreach ($pdo->query($sql) as $row) {
									
											$tieneComputo = 0;
											$sql2 = "SELECT `id` from computos where id_tarea = ? and id_estado <> 6 ";
											$q2 = $pdo->prepare($sql2);
											$q2->execute([$row[0]]);
											$data2 = $q2->fetch(PDO::FETCH_ASSOC);
											$idComputo = 0;
											if (!empty($data2)) {
												$tieneComputo = 1;	
												$idComputo = $data2['id'];
											}
											
											echo '<tr>';
											echo '<td class="d-none">'. $row[0] . '</td>';
											echo '<td>'. $row[3] . '</td>';
											echo '<td>'. $row[4] . '</td>';
											echo '<td>'. $row[5] . '</td>';
											echo '<td>'. $row[11] . '</td>';
											echo '<td>'. $row[6] . '</td>';
											echo '<td>'. $row[12] . '</td>';
											echo '<td>'. $row[7] . '</td>';
											echo '<td>'. $row[8] . '</td>';
											echo '<td>'. $row[9] . '</td>';
											echo '<td>'. $row[10] . '</td>';
											if ($row[10] != '00/00/00') {
												echo '<td>Si</td>';	
											} else {
												echo '<td>No</td>';	
											}
											if ($tieneComputo == 1) {
												echo '<td>Si</td>';	
												echo '<td>'.$idComputo.'</td>';	
											} else {
												echo '<td>No</td>';	
												echo '<td>&nbsp;</td>';	
											}
											echo '</tr>';
										}
									}
									
								   Database::disconnect();
								  ?>
								</tbody>
								<tfoot>
								  <tr>
									  <th class="d-none">ID</th>
									  <th>Estructura</th>
									  <th>Sector</th>
									  <th>Tarea</th>
									  <th>Recurso</th>
									  <th>Coordinador</th>
									  <th>Observaciones</th>
									  <th>FIP</th>
									  <th>FFP</th>
									  <th>FIR</th>
									  <th>FFR</th>
									  <th>Completada</th>
									  <th>Cómputo</th>
									  <th>Cómputo ID</th>
								  </tr>
								</tfoot>
							  </table>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
							<select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required="required" autofocus>
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (!empty($_GET['id'])) {
									if ($fila['id'] == $_GET['id']) {
										echo " selected ";
									}	
								}								
								echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estructura(*)</label>
							<div class="col-sm-9"><input name="estructura" type="text" maxlength="99" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Sector(*)</label>
							<div class="col-sm-9">
							<select name="id_sector" id="id_sector" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `sector` FROM `sectores` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['sector']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo(*)</label>
							<div class="col-sm-9">
							<select name="id_tipo_tarea" id="id_tipo_tarea" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_tarea` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['tipo']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Recurso</label>
							<div class="col-sm-9">
							<select name="id_recurso" id="id_recurso" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (2,4) and activo = 1 and anulado = 0";
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
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Coordinador</label>
							<div class="col-sm-9">
							<select name="id_coordinador" id="id_coordinador" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 4 and activo = 1 and anulado = 0";
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
							<label class="col-sm-3 col-form-label">Fecha Inicio Estimada</label>
							<div class="col-sm-9"><input name="fecha_inicio_estimada" id="fecha_inicio_estimada" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Fin Estimada</label>
							<div class="col-sm-9"><input name="fecha_fin_estimada" id="fecha_fin_estimada" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Inicio Real</label>
							<div class="col-sm-9"><input name="fecha_inicio_real" id="fecha_inicio_real" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Fin Real</label>
							<div class="col-sm-9"><input name="fecha_fin_real" id="fecha_fin_real" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control"></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-success" value="1" name="btn1" type="submit">Crear y Agregar Nueva</button>
						<button class="btn btn-primary" value="2" name="btn2" type="submit">Crear y Volver</button>
						<a href="listarProyectos.php" class="btn btn-light">Volver</a>
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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	<script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
	<script>
	$("#fecha_fin_estimada").change(function () {
		var startDate = document.getElementById("fecha_inicio_estimada").value;
		var endDate = document.getElementById("fecha_fin_estimada").value;

		if ((Date.parse(startDate) > Date.parse(endDate))) {
			alert("La fecha de fin estimada debe ser mayor a la fecha de inicio estimada");
			document.getElementById("fecha_fin_estimada").value = "";
		}
	});
	$("#fecha_fin_real").change(function () {
		var startDate2 = document.getElementById("fecha_inicio_real").value;
		var endDate2 = document.getElementById("fecha_fin_real").value;

		if ((Date.parse(startDate2) > Date.parse(endDate2))) {
			alert("La fecha de fin real debe ser mayor a la fecha de inicio real");
			document.getElementById("fecha_fin_real").value = "";
		}
	});
	</script>
	
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