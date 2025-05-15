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
		
		$subtotal = $_POST['precio']*$_POST['cantidad'];
		
		$sql = "INSERT INTO `facturas_venta_detalle`(`id_factura_venta`, `id_concepto_contable`, `cantidad`, `precio`, `subtotal`) VALUES (?,?,?,?,?)";
		$q = $pdo->prepare($sql);		   
		$q->execute([$_GET['id'],$_POST['id_concepto_contable'],$_POST['cantidad'],$_POST['precio'],$subtotal]);
        
		$idDetalle = $pdo->lastInsertId();
		
		$gravado = 0;
		$noGravado = 0;
		$otros = 0;
		$iva = 0;
		$total = 0;
		
		$sql = " SELECT `cantidad`, `precio`, `subtotal` FROM `facturas_venta_detalle` WHERE `id_factura_venta` = ".$_GET['id'];    
		foreach ($pdo->query($sql) as $row) {
			$total += $row[2];
			$noGravadoParcial = $row[1]*$row[0];
			$noGravado += $noGravadoParcial;
			$iva += $noGravado *0.21;
			$gravado += $noGravado + $iva;
		}
		
		$sql = "update `facturas_venta` set  `subtotal_gravado` = ?, `subtotal_no_gravado` = ?, `otros` = ?, `iva` = ?, `total` = ? where id = ?";
		$q = $pdo->prepare($sql);		   
		$q->execute([$gravado, $noGravado, $otros, $iva, $total, $_GET['id']]);
		
		foreach ($_POST['imputaciones'] as $item) {
            $sql = "INSERT INTO `facturas_venta_detalle_x_certificados_avance`(`id_factura_venta_detalle`, `id_certificado_avance`) VALUES (?,?)";
            $q = $pdo->prepare($sql);
            $q->execute([$idDetalle,$item]);
        }
        
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo Ítem Detalle de Factura de Venta','Facturas de Venta','')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
		if (!empty($_POST['btn2'])) {
			header("Location: listarFacturasVenta.php");
		} else if (!empty($_POST['btn1'])) {
			header("Location: nuevoDetalleFacturaVenta.php?id=".$_GET['id']);
		} else {
			header("Location: nuevaRetencionFacturaVenta.php?id=".$_GET['id']);
		}
		
	} else {
		$pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "select id_cuenta_destino FROM `facturas_venta` where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_GET['id']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        $idCuentaCliente = $data['id_cuenta_destino'];
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
          $ubicacion="Nuevo Ítem Detalle de Factura Venta";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				<form class="form theme-form" role="form" method="post" action="nuevoDetalleFacturaVenta.php?id=<?php echo $_GET['id'];?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th class="d-none">ID</th>
									  <th>Descripción</th>
									  <th>Precio</th>
									  <th>Cantidad</th>
									  <th>Subtotal</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									if (!empty($_GET['id'])) {
										$sql = " SELECT d.`id`, cc.descripcion, d.`precio`, d.`cantidad`, d.`subtotal` FROM `facturas_venta_detalle` d inner join conceptos_contables cc on cc.id = d.id_concepto_contable WHERE d.id_factura_venta = ".$_GET['id'];
										$totalImputado = 0;
										foreach ($pdo->query($sql) as $row) {
									
											echo '<tr>';
											echo '<td class="d-none">'. $row[0] . '</td>';
											echo '<td>'. $row[1] . '</td>';
											echo '<td>$'. number_format($row[2],2) . '</td>';
											echo '<td>'. $row[3] . '</td>';
											echo '<td>$'. number_format($row[4],2) . '</td>';
											$totalImputado += $row[4];
											echo '</tr>';
										}
									}
									
								   Database::disconnect();
								  ?>
								</tbody>
							  </table>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Concepto Contable(*)</label>
							<div class="col-sm-9">
							<select name="id_concepto_contable" id="id_concepto_contable" class="js-example-basic-single col-sm-12" required="required" autofocus>
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `codigo`, `descripcion` FROM `conceptos_contables` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['descripcion']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Imputaciones(*)</label>
							<div class="col-sm-9">
							<select class="js-example-basic-multiple col-sm-12" name="imputaciones[]" id="imputaciones[]" multiple="multiple" required="required">
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sql = "SELECT cac.id,cm.numero AS numero_cm,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cac.monto_total FROM certificados_avances_cabecera cac INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id inner join occ occ on occ.id = cm.id_occ WHERE cac.aprobado_cliente = 1 and occ.id_cuenta_cliente = ".$idCuentaCliente;
							$count = 1;
							foreach ($pdo->query($sql) as $row) {
								echo "<option value='".$row['id']."'";
								echo ">".$count." - CM: ".$row['numero_cm']." Emitido: ".$row['fecha_emision']." / (".$row['fecha_inicio']."-".$row['fecha_fin'].") ".$row['moneda'].number_format($row['monto_total'],2)."</option>";
								$count++;
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Precio(*)</label>
							<div class="col-sm-9"><input name="precio" type="number" step="0.01" class="form-control" required="required">
							</div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
					    <button class="btn btn-success" value="1" name="btn1" type="submit">Crear y Agregar Nuevo Concepto</button>
                        <button class="btn btn-primary" value="2" name="btn2" type="submit">Crear y Volver al Listado</button>
						<button class="btn btn-warning" value="3" name="btn3" type="submit">Crear y Agregar Retenciones</button>
						<a href="listarFacturasVenta.php" class="btn btn-light">Volver al Listado</a>
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