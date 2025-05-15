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
		
		$sql = "SELECT lc.`id_proyecto` idproyecto, p.`id_sitio` idsitio from listas_corte lc inner join proyectos p on p.id = lc.id_proyecto where lc.id = ? ";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST["id_lista_corte"]]);
		$data2 = $q->fetch(PDO::FETCH_ASSOC);
		
		$sql = "INSERT INTO `egresos`(`fecha_hora`, `id_tipo_egreso`, `nro`, `id_cuenta_retira`, `id_sitio_destino`, `id_proyecto`, `observaciones`) VALUES (now(),1,?,?,?,?,?)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_POST["id_lista_corte"],$_POST['id_cuenta_retira'],$data2['idsitio'],$data2['idproyecto'],$_POST['observaciones']]);
		$idEgreso = $pdo->lastInsertId();
		
		
		$sql = " select cd.id, m.id, cd.comprado, cd.id_computo, m.id_unidad_medida from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id where cd.cancelado = 0 and lc.id = ".$_POST["id_lista_corte"];
		
		foreach ($pdo->query($sql) as $row) {
			/*$sql = "update `stock` set `reservado` = `reservado` - ? where id_material = ?";	
			$q = $pdo->prepare($sql);
			$q->execute([$row[2],$row[1]]);*/
			
			$sql = "select cd.precio,cd.precio*cd.cantidad subtotal from compras_detalle cd inner join compras c on c.id = cd.id_compra inner join pedidos p on p.id = c.id_pedido inner join computos co on co.id = p.id_computo where cd.id_material = ? and p.id_computo = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$row[1],$row[3]]);
			$data2 = $q->fetch(PDO::FETCH_ASSOC);
			
			if (!empty($data2)) {
				$precio = $data2['precio'];
				$subtotal = $precio*$row[2]; 
			} else {
				$precio = 0;
				$subtotal = 0; 
			}
			
			$sql = "INSERT INTO `egresos_detalle`(`id_egreso`, `id_material`, `id_unidad_medida`, `cantidad`, `precio`, `subtotal`) VALUES (?,?,?,?,?,?)";
			$q = $pdo->prepare($sql);		   
			$q->execute([$idEgreso,$row[1],$row[4],$row[2],$precio,$subtotal]);
			
		}
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo egreso de stock de lista de corte','Egresos','verEgreso.php?id=$id')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
		
		Database::disconnect();
        header("Location: listarEgresos.php");
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
          $ubicacion="Egreso de Cómputo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevoEgresoListaCorte.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Lista de Corte(*)</label>
							<div class="col-sm-9">
							<select name="id_lista_corte" id="id_lista_corte" autofocus class="js-example-basic-single col-sm-12" required="required" onChange="jsListarProductos(this.value);">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT lc.id, lcr.numero, lcr.nombre, lcr.nro_revision FROM listas_corte lc INNER JOIN listas_corte_revisiones lcr ON lcr.id_lista_corte=lc.id AND lcr.nro_revision=lc.ultimo_nro_revision WHERE lc.anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['numero']."-".$fila['nro_revision']." / ".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Detalle</label>
								<div class="col-sm-9">
								  <table class="display" id="dataTables-example666">
									<thead>
									  <tr>
									  <th>Código</th>
									  <th>Concepto</th>
									  <th>Categoría</th>
									  <th>Cantidad Solicitada</th>
									  <th>Cantidad Utilizada</th>
									  </tr>
									</thead>
									<tbody>
									</tbody>
								  </table>
								</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Retira(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_retira" id="id_cuenta_retira" class="js-example-basic-single col-sm-12" required="required">
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
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"></textarea></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Registrar Egreso</button>
						<a href="listarEgresos.php" class="btn btn-light">Volver</a>
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
			$('#dataTables-example666').DataTable({
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
		});
		
		function jsListarProductos(val) { 
			$.ajax({
				type: "POST",
				url: "ajaxProductosEgresoListaCorte.php",
				data: "lista_corte="+val,
				success: function(resp){
					$("#dataTables-example666").html(resp);
				}
			});
		}
		
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
	
	<!-- Page-Level Demo Scripts - Tables - Use for reference -->
   
  </body>
</html>