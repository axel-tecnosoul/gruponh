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
        header("Location: listarCompras.php");
    }
    
    if (!empty($_POST)) {
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT c.`id`, c.`id_pedido`, c.`id_cuenta_proveedor`, c.`fecha_emision`, c.`fecha_entrega`, c.`id_forma_pago`, c.`id_estado_compra`, c.`nro_oc`, c.`total`, c.`comentarios`, pe.lugar_entrega, c.adjunto_factura, c.id_moneda, c.tipo_cambio_dia, c.`iva`, c.`descuento` FROM `compras` c inner join pedidos pe on pe.id = c.id_pedido WHERE c.id = ? ";
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
          $ubicacion="Ver Orden de Compra";
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
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nro OC(*)</label>
							<div class="col-sm-9"><input name="nro_oc" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['nro_oc'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proveedor(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_proveedor" id="id_cuenta_proveedor" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (5) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_cuenta_proveedor']) {
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
							<label class="col-sm-3 col-form-label">Fecha Emisión(*)</label>
							<div class="col-sm-9"><input name="fecha_emision" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha_emision'];?>" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Entrega Estimada</label>
							<div class="col-sm-9"><input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?php echo $data['fecha_entrega'];?>" class="form-control"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Moneda</label>
							<div class="col-sm-9">
							<select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `moneda` FROM `monedas` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_moneda']) {
									echo " selected ";
								}
								echo ">".$fila['moneda']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo de Cambio</label>
							<div class="col-sm-9"><input name="tipo_cambio_dia" type="number" step="0.01" class="form-control" value="<?php echo $data['tipo_cambio_dia'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Forma de Pago</label>
							<div class="col-sm-9">
							<select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `forma_pago` FROM `formas_pago` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_forma_pago']) {
									echo " selected ";
								}
								echo ">".$fila['forma_pago']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado</label>
							<div class="col-sm-9">
							<select name="id_estado_compra" id="id_estado_compra" class="js-example-basic-single col-sm-12">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_compra` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id']==$data['id_estado_compra']) {
									echo " selected ";
								}
								echo ">".$fila['estado']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Lugar de Entrega</label>
							<div class="col-sm-9"><input name="lugar_entrega" type="text" maxlength="299" class="form-control" required="required" value="<?php echo $data['lugar_entrega'];?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Subtotal</label>
							<div class="col-sm-9"><input name="total" type="number" step="0.01" class="form-control" value="<?php echo $data['total'];?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">IVA</label>
							<div class="col-sm-9"><input name="iva" type="number" step="0.01" class="form-control" value="<?php echo $data['iva'];?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descuento</label>
							<div class="col-sm-9"><input name="descuento" type="number" step="0.01" class="form-control" value="<?php echo $data['descuento'];?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Total</label>
							<div class="col-sm-9"><input name="total_totales" type="number" step="0.01" class="form-control" value="<?php echo $data['total']+$data['iva']-$data['descuento'];?>" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Comentarios</label>
							<div class="col-sm-9"><textarea name="comentarios" class="form-control"><?php echo $data['comentarios'];?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-12 col-form-label">Conceptos O.C</label>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Concepto</th>
									  <th>Cantidad</th>
									  <th>Unidad</th>
									  <th>Peso Total</th>
									  <th>P/Unitario</th>
									  <th>P/Total</th>
									  <th>Entregado</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, u.`unidad_medida`,d.id_material,d.precio,d.entregado,d.precio_kg,m.peso_metro,m.largo FROM `compras_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_compra = ".$_GET['id'];
									foreach ($pdo->query($sql) as $row) {
										
										$precio = number_format($row[5],2);
										$preciokg = number_format($row[7],2);
										$subtotal = number_format($row[5]*$row[2],2);
										
										$peso = $row[8]*($row[9]/1000);
										echo '<tr>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td>'. $row[2] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										echo '<td>'. number_format($peso,2) . '</td>';
										echo '<td>'. $precio . '</td>';
										echo '<td>'. $subtotal . '</td>';
										echo '<td>'. $row[6] . '</td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
							  </table>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-12 col-form-label">Conceptos Cómputo</label>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example668">
								<thead>
								  <tr>
									  <th>Concepto</th>
									  <th>Cantidad</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " select m.concepto,cd.cantidad from computos_detalle cd inner join computos co on co.id = cd.id_computo inner join pedidos p on p.id_computo = co.id inner join compras c on c.id_pedido = p.id inner join materiales m on m.id = cd.id_material where c.id = ".$_GET['id'];
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[0] . '</td>';
										echo '<td>'. $row[1] . '</td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
							  </table>
							</div>
							</div>
							<hr>
							<h5>Sucesos</h5>
							<div class="form-group row">
								<div class="col-sm-9">
								<div class="timeline-small">
								  <?php 
									$pdo = Database::connect();
									$sql = " SELECT s.`id`, date_format(s.`fecha_hora`,'%d/%m/%y %H:%i'), s.`suceso`, s.`titulo`, t.tipo FROM `compras_sucesos` s inner join tipos_suceso t on t.id = s.id_tipo_suceso WHERE s.`id_compra` = ".$_GET['id'].' order by s.id desc';
									
									foreach ($pdo->query($sql) as $row) {
										echo '<div class="media">';
										echo '<div class="timeline-round m-r-30 timeline-line-1 bg-primary"><i data-feather="message-circle"></i></div>';
										echo '<div class="media-body">';
										echo '<h6>'.$row[3].' <span class="pull-right f-14">'.$row[1].'hs</span></h6>';
										echo '<p>'.$row[4].': '.$row[2].'</p>';
										echo '</div></div>';
								   }
								   Database::disconnect();
								  ?>
								</div>
								</div>
							</div>
							<hr>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Comprobante</label>
							<div class="col-sm-9"><a target="_blank" href="<?php echo $data['adjunto_factura'];?>"><i>Descargar</i></a></div>
							</div>
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Pagos / Comentarios Realizados</label>
								<div class="col-sm-9">
								<div class="timeline-small">
								  <?php 
									$pdo = Database::connect();
									$sql = " SELECT cp.`id`, date_format(cp.`fecha`,'%d/%m/%y'), cp.`monto`, cp.`comentarios`, u.usuario FROM `compras_pagos` cp left join usuarios u on u.id = cp.`id_usuario` WHERE cp.`id_compra` = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										echo '<div class="media">';
										echo '<div class="timeline-round m-r-30 timeline-line-1 bg-primary"><i data-feather="message-circle"></i></div>';
										echo '<div class="media-body">';
										echo '<h6>Monto: $'.number_format($row[2],2).' <span class="pull-right f-14">'.$row[1].'hs</span></h6>';
										echo '<h6><span class="pull-right f-14">Usuario: '.$row[4].'</span></h6>';
										echo '<p>Observaciones: '.$row[3].'</p>';
										echo '</div></div>';
								   }
								   Database::disconnect();
								  ?>
								</div>
								</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<a class="btn btn-primary" target="_blank" href="imprimirCompra.php?id=<?php echo $data['id']; ?>">Imprimir</a>
                        <a href="#" onclick="document.location.href='listarCompras.php'" class="btn btn-light">Volver</a>
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
		"dom": 'rtip',
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
	
	$(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example668 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example668').DataTable({
        stateSave: false,
        responsive: false,
		"dom": 'rtip',
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
    var table = $('#dataTables-example668').DataTable();
 
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
	
	$(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example669 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example669').DataTable({
        stateSave: false,
        responsive: false,
		"dom": 'rtip',
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
    var table = $('#dataTables-example669').DataTable();
 
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