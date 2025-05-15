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
  header("Location: listarPackingList.php");
}

if (!empty($_POST)) {

} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT pl.id, plr.id_packing_list, plr.nombre, plr.id_proyecto, p.nombre nombre_proyecto, p.nro nro_proyecto, s.nro_sitio, s.nro_subsitio, date_format(plr.fecha,'%d/%m/%Y') fecha, plr.id_usuario, plr.id_estado_packing_list, plr.nro_revision, plr.anulado, cu1.nombre cuenta_elaboro, cu2.nombre cuenta_verifico, cu3.nombre cuenta_aprobo, plr.numero FROM packing_lists pl INNER JOIN packing_lists_revisiones plr ON plr.id_packing_list=pl.id AND plr.nro_revision=pl.ultimo_nro_revision inner join proyectos p on p.id = plr.id_proyecto inner join sitios s on s.id = p.id_sitio left join cuentas cu1 on cu1.id = plr.id_cuenta_realizo left join cuentas cu2 on cu2.id = plr.id_cuenta_reviso left join cuentas cu3 on cu3.id = plr.id_cuenta_valido WHERE plr.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
	<style>
	@page {
		size: landscape;
	}
	.bordered-div {
            border: 2px solid black; /* 2px width, solid style, black color */
            padding: 10px; /* Optional: add some padding inside the div */
			margin: 2px;
        }
		.bordered-div-thin {
            border: 1px solid black; /* 2px width, solid style, black color */
            padding: 10px; /* Optional: add some padding inside the div */
			margin: 2px;
        }
	</style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
    
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
	  
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body">
          <!-- Container-fluid starts-->
		  <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<div class="col-sm-2 bordered-div"><img src="assets/images/logo.jpg"></div>
							<div class="col-sm-6 bordered-div" align="center"><h3><b>PACKING LIST</b></h3></div>
							<div class="col-sm-3 bordered-div"><h6><b><?php echo $data['nombre']; ?><br>Nº <?php echo $data['numero']; ?> - Rev <?php echo $data['nro_revision']; ?></b></h6></div>
							</div>
							<div class="form-group row">
							
							<div class="col-sm-11 bordered-div">
							<b>Emisión:</b> <?php echo $data['fecha'];?><br>
							<b>Proyecto:</b> <?php echo $data['nombre_proyecto'];?><br>
							<b>Nro:</b> <?php echo $data['nro_sitio']."_".$data['nro_subsitio']."_".$data['nro_proyecto'];?><br>
							</div>
							</div>
							<div class="row">
                          <div class="col-12">
                            <table class="table table-bordered mb-3">
                              <thead>
                                <th>Rev</th>
                                <th>Fecha</th>
                                <th>Modificaciones</th>
                                <th>Elaboro</th>
                                <th>Reviso</th>
                                <th>Aprobo</th>
                              </thead>
                              <tbody><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                $sql = " SELECT nro_revision,date_format(fecha,'%d/%m/%y') AS fecha,descripcion, cu1.nombre realizo, cu2.nombre reviso, cu3.nombre valido FROM packing_lists_revisiones plr left join cuentas cu1 on cu1.id = plr.id_cuenta_realizo left join cuentas cu2 on cu2.id = plr.id_cuenta_reviso left join cuentas cu3 on cu3.id = plr.id_cuenta_valido WHERE plr.id_packing_list = ".$data['id_packing_list']." ORDER BY nro_revision ASC";//,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_realizo=c.id) AS realizo,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_reviso=c.id) AS reviso,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_valido=c.id) AS valido
                                foreach ($pdo->query($sql) as $row) {
                                  //$b=1;
                                  echo '<tr>';
                                  echo '<td>'. $row["nro_revision"] . '</td>';
                                  echo '<td>'. $row["fecha"] . '</td>';
                                  echo '<td>'. $row["descripcion"] . '</td>';
                                  echo '<td>'. $row["realizo"] . '</td>';
                                  echo '<td>'. $row["reviso"] . '</td>';
                                  echo '<td>'. $row["valido"] . '</td>';
                                  echo '</tr>';
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                        </div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example669" border="1" cellspacing="10" cellpadding="5">
							<thead>
							  <tr>
								  <th>Sección</th>
								  <th>Cantidad</th>
								  <th>Observaciones</th>
								  <th>Componentes</th>
							  </tr>
							</thead>
							<tbody>
							  <?php
								$pesoTotal = 0;
								$pdo = Database::connect();
								$sql = " SELECT id, cantidad, observaciones FROM packing_lists_secciones WHERE id_packing_list_revision = ".$id;
								foreach ($pdo->query($sql) as $row) {
									echo '<tr>';
									echo '<td>'. $row[0] . '</td>';
									echo '<td>'. $row[1] . '</td>';
									echo '<td>'. $row[2] . '</td>';
									echo '<td>';
									?>
									<table class="display" border="1" cellspacing="10" cellpadding="5">
									<thead>
									  <tr>
										  <th>Conjunto / Concepto</th>
										  <th>Cantidad</th>
										  <th>Largo</th>
										  <?php
										  if ($_GET['peso']==1) {
												echo "<th>Peso Kg</th>";
										  }
										  ?>
										  <th>Observaciones</th>
										  <th>Posiciones</th>
									  </tr>
									</thead>
									<tbody>
									<?php
									$sql2 = "SELECT plc.`id`, lcc.nombre, m.concepto, plc.`cantidad`, plc.`observaciones`, e.estado, m.largo, m.peso_metro, lcc.peso, lcc.id FROM `packing_lists_componentes` plc LEFT JOIN listas_corte_conjuntos lcc on lcc.id = plc.`id_conjunto_lista_corte` LEFT JOIN materiales m on m.id = plc.`id_concepto` INNER JOIN estados_componentes_packing_list e on e.id = plc.`id_estado_componente_packing_list` WHERE plc.`id_packing_list_seccion` = ".$row[0];
									foreach ($pdo->query($sql2) as $row2) {
										echo '<tr>';
										echo '<td>'. $row2[1] .' '. $row2[2] .  '</td>';
										echo '<td>'. $row2[3] . '</td>';
										if (!empty($row2[2])) {
											echo '<td>'. $row2[6] . '</td>';
										} else {
											echo '<td>&nbsp;</td>';	
										}
										if ($_GET['peso']==1) {
											if (!empty($row2[2])) {
												echo '<td>'. $row2[6]*$row2[7]/1000 . '</td>';
												$pesoTotal += $row2[6]*$row2[7]/1000;
											} else {
												echo '<td>'. $row2[8] . '</td>';
												$pesoTotal += $row2[8];
											}
										}
										
										echo '<td>'. $row2[4] . '</td>';
										if (!empty($row2[2])) {
											echo '<td></td>';
										} else {
											echo '<td>';
											?>
											<table class="display" border="1" cellspacing="10" cellpadding="5">
											<thead>
											  <tr>
												  <th>P</th>
												  <th>F</th>
												  <th>S</th>
												  <th>R</th>
												  <th>Concepto</th>
												  <th>Posición</th>
												  <th>Cantidad</th>
												  <th>Largo</th>
												  <th>Ancho</th>
												  <th>Marca</th>
												  <?php
												  if ($_GET['peso']==1) {
														echo "<th>Peso Kg</th>";
												  }
												  ?>
												  <th>Colada</th>
												  <th>Diámetro</th>
												  <th>Calidad</th>
												  <th>Procesos</th>
											  </tr>
											</thead>
											<tbody>
											<?php
											$sql22 = "SELECT lcp.`id`, m.concepto, lcp.`posicion`, lcp.`cantidad`, lcp.`largo`, lcp.`ancho`, lcp.`marca`, lcp.`peso`, lcp.`finalizado`, c.nro_colada, lcp.`diametro`, lcp.`calidad` FROM `lista_corte_posiciones` lcp inner join materiales m on m.id = lcp.`id_material` left join coladas c on c.id = lcp.`id_colada`  WHERE lcp.`id_lista_corte_conjunto` = ".$row2[9];
											foreach ($pdo->query($sql22) as $row22) {
												echo '<tr>';
												echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
												echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
												echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
												echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
												echo '<td>'. $row22[1] . '</td>';
												echo '<td>'. $row22[2] . '</td>';
												echo '<td>'. $row22[3] . '</td>';
												echo '<td>'. $row22[4] . '</td>';
												echo '<td>'. $row22[5] . '</td>';
												if ($_GET['peso']==1) {
													echo '<td>'. $row22[6] . '</td>';
												}
												echo '<td>'. $row22[7] . '</td>';
												echo '<td>'. $row22[9] . '</td>';
												echo '<td>'. $row22[10] . '</td>';
												echo '<td>'. $row22[11] . '</td>';
												echo '<td>';
												?>
												<?php
												$sql33 = "SELECT lcpr.`id`, tp.tipo, ep.`estado`, lcpr.`observaciones` FROM `lista_corte_procesos` lcpr inner join tipos_procesos tp on tp.id = lcpr.`id_tipo_proceso` inner join estados_lista_corte_procesos ep on ep.id = lcpr.id_estado_lista_corte_proceso WHERE lcpr.`id_lista_corte_posicion` = ".$row22[0];
												foreach ($pdo->query($sql33) as $row33) {
													echo $row33[1] . ',';
												}
												?>
												
												<?php
												echo '</td>';
												echo '</tr>';
											}
											?>
											</tbody>
											</table>
											<?php
											echo '</td>';
										}
										echo '</tr>';
									}
									?>
									</tbody>
									</table>
									<?php
									echo '</td>';
									echo '</tr>';
								}
							   Database::disconnect();
							  ?>
							</tbody>
							</table>
							</div>
							</div>
							<?php
							if ($_GET['peso']==1) {
							?>
								Peso <b>TOTAL</b> de la lista: <?php echo number_format($pesoTotal,2);?>kgs
							<?php
							}
							?>
							</div>
							
                        </div>
                      </div>
                    </div>
                    
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
		
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
    $('#dataTables-example666 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example666').DataTable({
        stateSave: false,
		searching: false,
		
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
    var table = $('#dataTables-example666').DataTable();
 
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
<script>window.print();</script>