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
        header("Location: listarIngresos.php");
    }
    
    if (!empty($_POST)) {
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, date_format(`fecha_hora`,'%d/%m/%y %H:%i') fecha_hora, `id_tipo_ingreso`, `nro`, `id_cuenta_recibe`, `lugar_entrega`, `observaciones`, date_format(`fecha_remito`,'%d/%m/%Y') fecha_remito, `nro_remito` FROM `ingresos` WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
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
        <div class="page-body"><?php
          $ubicacion="Ver Ingreso de Stock";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form- row">
							<label class="col-sm-3 col-form-label">Fecha/Hora</label>
							<div class="col-sm-9"><?php echo $data['fecha_hora'];?>hs</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo de Ingreso</label>
							<div class="col-sm-9">
							<select name="id_tipo_ingreso" id="id_tipo_ingreso" class="js-example-basic-single col-sm-12" disabled="disabled">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_ingreso` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_tipo_ingreso']) {
									echo " selected ";
								}
								echo ">".$fila['tipo']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nro.</label>
							<div class="col-sm-9"><input name="nro" type="text" value="<?php echo $data['nro'];?>" class="form-control" readonly="readonly"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Recibe</label>
							<div class="col-sm-9">
							<select name="id_cuenta_recibe" id="id_cuenta_recibe" class="js-example-basic-single col-sm-12" disabled="disabled">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$data['id']."'";
								if ($fila['id'] == $data['id_cuenta_recibe']) {
										echo " selected ";
									}	
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Lugar de Entrega</label>
							<div class="col-sm-9"><input name="lugar_entrega" type="text" maxlength="199" class="form-control" readonly="readonly" value="<?php echo $data['lugar_entrega'];?>"></div>
							</div>
							<div class="form- row">
							<label class="col-sm-3 col-form-label">Fecha Remito</label>
							<div class="col-sm-9"><?php echo $data['fecha_remito'];?></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nro. Remito</label>
							<div class="col-sm-9"><input name="nro_remito" type="text" value="<?php echo $data['nro_remito'];?>" class="form-control" readonly="readonly"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control" disabled="disabled"><?php echo $data['observaciones'];?></textarea></div>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Código</th>
									  <th>Concepto</th>
									  <th>Categoría</th>
									  <th>Unidad Medida</th>
									  <th>Cantidad</th>
                    <th>Cantidad egresada</th>
                    <th>Saldo</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT m.codigo, m.concepto, cat.categoria, um.unidad_medida, id.cantidad, id.cantidad_egresada, id.saldo FROM ingresos_detalle id inner join unidades_medida um on um.id = id.id_unidad_medida inner join ingresos i on i.id = id.id_ingreso inner join tipos_ingreso ti on ti.id = i.id_tipo_ingreso inner join cuentas c on c.id = i.id_cuenta_recibe inner join materiales m on m.id = id.id_material inner join categorias cat on cat.id = m.id_categoria WHERE id.id_ingreso = ".$_GET['id'];
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[0] . '</td>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td>'. $row[2] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										echo '<td>'. $row[4] . '</td>';
                    echo '<td>'. $row[5] . '</td>';
                    echo '<td>'. $row[6] . '</td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
							  </table>
							</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<a class="btn btn-primary" target="_blank" href="imprimirIngreso.php?id=<?php echo $data['id']; ?>">Imprimir</a>
                        <a href="#" onclick="document.location.href='listarIngresos.php'" class="btn btn-light">Volver</a>
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
    <!-- Plugin used-->

  </body>
</html>