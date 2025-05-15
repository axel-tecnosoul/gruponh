<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
include 'database.php';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <?php include('head_tables.php');?>
  <style>
	.truncate {
	  max-width:50px;
	  white-space: nowrap;
	  overflow: hidden;
	  text-overflow: ellipsis;
	}
  </style>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <!-- Page Header Start-->
      <?php include('header.php');?>
     
      <!-- Page Header Ends                              -->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Reporte Centros de Costo ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="reporteCentrosCosto.php">
					  <div class="form-group mb-0">
						Centros de Costo:&nbsp;
						<select name="centros_costo[]" id="centros_costo[]" class="js-example-basic-multiple" multiple="multiple">
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
					  <div class="form-group mb-0">
						Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fecha'])) echo $_POST['fecha'] ?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fechah'])) echo $_POST['fechah'] ?>" name="fechah">
					  </div>
					  <div class="form-group mb-0">
						Costos Adicionales:&nbsp;<input class="form-control" size="10" type="number" value="<?php if (isset($_POST['adicionales'])) echo $_POST['adicionales']; else echo "0"; ?>" name="adicionales">
					  </div>
					  
					  <div class="form-group mb-0">
						<button class="btn btn-warning" onclick="document.form1.target='_self';document.form1.action='reporteCentrosCosto.php'">Ejecutar</button>
					  </div>
					</form>
				</div>
			  </div>
			</div>
			</div>
			<div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo $ubicacion; ?>
					</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
							  <th>Fecha</th>
							  <th>Centro de Costo</th>
							  <th>FC Nro.</th>
							  <th>Concepto</th>
							  <th>Precio</th>
							  <th>Cantidad</th>
							  <th>Subtotal</th>
							  <th>OC Nro.</th>
							  <th>NP Nro.</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                            if (!empty($_POST)) {
                            $pdo = Database::connect();
							$subtotal = 0;
                            $sql = " SELECT date_format(fc.fecha_emitida,'%Y%m%d') fechaOrden, date_format(fc.fecha_emitida,'%d/%m/%Y') fecha, s.nro_sitio, s.nro_subsitio , p.nro, p.nombre, fc.numero fc, cc.descripcion concepto, fcd.`precio`, fcd.`cantidad`, fcd.`subtotal`, c.nro_oc oc, pe.id np, p.id idproyecto FROM `facturas_compra_detalle` fcd inner join conceptos_contables cc on cc.id = fcd.`id_concepto_contable` inner join facturas_compra fc on fc.id = fcd.id_factura_compra inner join compras c on c.id = fc.id_orden_compra inner join pedidos pe on pe.id = c.id_pedido inner join `proyectos` p on p.id = pe.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE 1 ";
                            
							if (!empty($_POST['centros_costo'][0])) {
								$sql .= " AND p.id in (".implode(', ',$_POST['centros_costo']).") ";
							}
							if (!empty($_POST['fecha'])) {
								$sql .= " AND fc.fecha_emitida >= '".$_POST['fecha']."' ";
							}
							if (!empty($_POST['fechah'])) {
								$sql .= " AND fc.fecha_emitida <= '".$_POST['fechah']."' ";
							}
							
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
								echo '<td><span style="display: none;">'. $row[0] . '</span>'. $row[1] . '</td>';
								echo '<td>'. $row[2] .'/'.$row[3].'/'.$row[4]. ': '.$row[5]. '</td>';
								echo '<td>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
								echo '<td>$'. number_format($row[8],2) . '</td>';
								echo '<td>'. $row[9] . '</td>';
								echo '<td>$'. number_format($row[10],2) . '</td>';
								echo '<td>'. $row[11] . '</td>';
								echo '<td>'. $row[12] . '</td>';
                                echo '</tr>';
								$subtotal += $row[10];
                            }
							$sql = " SELECT date_format(fc.fecha_emitida,'%Y%m%d') fechaOrden, date_format(fc.fecha_emitida,'%d/%m/%Y') fecha, s.nro_sitio, s.nro_subsitio , p.nro, p.nombre, fc.numero fc, cc.descripcion concepto, fcd.`precio`, fcd.`cantidad`, fcd.`subtotal`, c.nro_oc oc, pe.id np, p.id idproyecto FROM `facturas_compra_detalle` fcd inner join conceptos_contables cc on cc.id = fcd.`id_concepto_contable` inner join facturas_compra fc on fc.id = fcd.id_factura_compra inner join compras c on c.id = fc.id_orden_compra inner join pedidos pe on pe.id = c.id_pedido inner join `computos` co on co.id = pe.id_computo inner join tareas t on t.id = co.id_tarea inner join `proyectos` p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE 1 ";
                            
							if (!empty($_POST['centros_costo'][0])) {
								$sql .= " AND p.id in (".implode(', ',$_POST['centros_costo']).") ";
							}
							if (!empty($_POST['fecha'])) {
								$sql .= " AND fc.fecha_emitida >= '".$_POST['fecha']."' ";
							}
							if (!empty($_POST['fechah'])) {
								$sql .= " AND fc.fecha_emitida <= '".$_POST['fechah']."' ";
							}
							
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
								echo '<td><span style="display: none;">'. $row[0] . '</span>'. $row[1] . '</td>';
								echo '<td>'. $row[2] .'/'.$row[3].'/'.$row[4]. ': '.$row[5]. '</td>';
								echo '<td>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
								echo '<td>$'. number_format($row[8],2) . '</td>';
								echo '<td>'. $row[9] . '</td>';
								echo '<td>$'. number_format($row[10],2) . '</td>';
								echo '<td>'. $row[11] . '</td>';
								echo '<td>'. $row[12] . '</td>';
                                echo '</tr>';
								$subtotal += $row[10];
                            }
							Database::disconnect();
							}
                          ?>
                        </tbody>
                      </table>
					  <?php 
					  if (!isset($subtotal)) {
						  $subtotal = 0;
					  }
					  ?>
					  <h6 class="f-w-600">Subtotal: $<?php echo number_format($subtotal,2);?></h6>
					  <?php 
					  if (!isset($_POST['adicionales'])) {
						  $_POST['adicionales'] = 0;
					  }
					  ?>
					  <h6 class="f-w-600 txt-danger">Costos Adic.: $<?php echo number_format($_POST['adicionales'],2);?></h6>
					  <h6 class="f-w-600 txt-success">Total Costo: $<?php echo number_format($_POST['adicionales']+$subtotal,2);?></h6>
					  <?php 
					  $presupuestado = 0;
					  if (!empty($_POST['centros_costo'][0])) {
							$sql = "SELECT sum(p.monto) total_presupuesto FROM `proyectos_presupuestos` pp inner join presupuestos p on p.id = pp.id_presupuesto WHERE pp.id_proyecto in (".implode(', ',$_POST['centros_costo']).") and p.adjudicado = 1 and p.anulado = 0";
							$q = $pdo->prepare($sql);
							$q->execute();
							$dataC = $q->fetch(PDO::FETCH_ASSOC);
							if (!empty($dataC['total_presupuesto'])) {
								$presupuestado = $dataC['total_presupuesto'];
							}
					  }
					  ?>
					  
					  <h6 class="f-w-600 txt-warning">Total Presupuestado: $<?php echo number_format($presupuestado,2);?></h6>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Zero Configuration  Ends-->
              <!-- Feature Unable /Disable Order Starts-->
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
    // Setup - add a text input to each footer cell
	$('#dataTables-example666').DataTable({
        stateSave: false,
		searching: false,
        responsive: false,
		dom: 'Bfrtp<"bottom"l>',
        buttons: [
            'excel'
        ],
		lengthMenu: [
        [10, 25, 50, 100, 500, 1000], // Cantidades de registros disponibles
        [10, 25, 50, 100, 500, 1000]  // Texto mostrado en el menú desplegable
		],
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
    var table = $('#dataTables-example666').DataTable();
 } );
   </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
	<script src="assets/js/select2/select2.full.min.js"></script>
<script src="assets/js/select2/select2-custom.js"></script>

    <!-- Plugin used-->
  </body>
</html>