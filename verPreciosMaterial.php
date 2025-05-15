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
        header("Location: listarMateriales.php");
    }
    
    if (!empty($_POST)) {
        
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `codigo`, `concepto`, `descripcion`, `largo`, `peso_metro`, `id_categoria`, `activo`, `id_unidad_medida`, `stock_minimo`, `anulado` FROM `materiales` WHERE id = ? ";
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
          $ubicacion="Ver Concepto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" action="modificarMaterial.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Categoría</label>
							<div class="col-sm-9">
							<select name="id_categoria" id="id_categoria" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
											$pdo = Database::connect();
											$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
											$sqlZon = "SELECT `id`, `categoria` FROM `categorias` WHERE 1 ";
											$q = $pdo->prepare($sqlZon);
											$q->execute();
											while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
												echo "<option value='".$fila['id']."'";
												if ($fila['id'] == $data['id_categoria']) {
													echo " selected ";
												}
												echo ">".$fila['categoria']."</option>";
											}
											Database::disconnect();
											?>
							</select>
							</div>
						  </div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Código</label>
							<div class="col-sm-9"><input name="codigo" type="text" maxlength="99" class="form-control" value="<?php echo $data['codigo']; ?>" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Concepto</label>
							<div class="col-sm-9"><input name="concepto" type="text" maxlength="99" class="form-control" value="<?php echo $data['concepto']; ?>" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción</label>
							<div class="col-sm-9"><textarea name="descripcion" class="form-control"><?php echo $data['descripcion']; ?></textarea></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Unidad Medida</label>
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
												if ($fila['id'] == $data['id_unidad_medida']) {
													echo " selected ";
												}
												echo ">".$fila['unidad_medida']."</option>";
											}
											Database::disconnect();
											?>
							</select>
							</div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Largo</label>
							<div class="col-sm-9"><input name="largo" type="number" step="0.01" class="form-control" required="required" value="<?php echo $data['largo']; ?>"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Peso x Metro</label>
							<div class="col-sm-9"><input name="peso_metro" type="number" step="0.01" class="form-control" required="required" value="<?php echo $data['peso_metro']; ?>"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Stock Mínimo</label>
							<div class="col-sm-9"><input name="stock_minimo" type="number" step="0.01" class="form-control" value="<?php echo $data['stock_minimo']; ?>" required="required"></div>
						  </div>
						  <div class="form-group row">
								<label class="col-sm-3 col-form-label">Activo</label>
								<div class="col-sm-9">
								<select name="activo" id="activo" class="js-example-basic-single col-sm-12" required="required">
								<option value="">Seleccione...</option>
								<option value="1" <?php if ($data['activo']==1) {
                                    echo " selected ";
                                }?>>Si</option>
								<option value="0" <?php if ($data['activo']==0) {
                                    echo " selected ";
                                }?>>No</option>
								</select>
								</div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Histórico de Compras</label>
								<div class="col-sm-9">
								<table class="display" id="dataTables-example666">
									<thead>
									  <tr>
										  <th>Nro. OC</th>
										  <th>Nro. Proy</th>
										  <th>Fecha</th>
										  <th>Precio</th>
										  <th>Proveedor</th>
									  </tr>
									</thead>
									<tbody>
									  <?php
										$pdo = Database::connect();
										$sql = " SELECT c.nro_oc, date_format(c.fecha_emision,'%d/%m/%y') f, d.`precio`,p.nro, cu.nombre,s.nro_sitio, s.nro_subsitio FROM `compras_detalle` d inner join compras c on c.id = d.id_compra inner join pedidos pe on pe.id = c.id_pedido inner join computos co on co.id = pe.id_computo inner join tareas t on t.id = co.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join cuentas cu on cu.id = c.id_cuenta_proveedor inner join sitios s on s.id = p.id_sitio WHERE d.`id_material` = ".$_GET['id'];
										
										foreach ($pdo->query($sql) as $row) {
											echo '<tr>';
											echo '<td>'. $row[0] . '</td>';
											echo '<td>'. $row[5]."-".$row[6]."-".$row[3] . '</td>';
											echo '<td>'. $row[1] . '</td>';
											echo '<td>$'. number_format($row[2],2) . '</td>';
											echo '<td>'. $row[4] . '</td>';
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
						<a onclick="document.location.href='listarMateriales.php'" class="btn btn-light">Volver</a>
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
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
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>