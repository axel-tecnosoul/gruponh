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
  header("Location: listarListasCorte.php");
}

if (!empty($_POST)) {

} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT lc.id, lc.id_lista_corte, p.nombre nombre_proyecto, p.nro nro_proyecto, s.nro_sitio, s.nro_subsitio, date_format(lc.fecha,'%d/%m/%Y') fecha, lc.id_usuario, lc.id_estado_lista_corte, lc.nro_revision, lc.anulado, lc.nombre, lc.numero, lc.adjunto, cu1.nombre cuenta_elaboro, cu2.nombre cuenta_verifico, cu3.nombre cuenta_aprobo, cu4.nombre nombre_cliente FROM listas_corte_revisiones lc inner join proyectos p on p.id = lc.id_proyecto inner join sitios s on s.id = p.id_sitio left join cuentas cu1 on cu1.id = lc.id_cuenta_realizo left join cuentas cu2 on cu2.id = lc.id_cuenta_reviso left join cuentas cu3 on cu3.id = lc.id_cuenta_valido inner join cuentas cu4 on cu4.id = p.id_cliente WHERE lc.id = ? ";
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
							<div class="col-sm-6 bordered-div" align="center"><h3><b>LISTA DE CORTE</b></h3></div>
							<div class="col-sm-3 bordered-div"><h6><b><?php echo $data['nombre']; ?><br>Nº <?php echo $data['numero']; ?> - Rev <?php echo $data['nro_revision']; ?><br>Nombre del Plano: <?php echo $data['adjunto']; ?></b></h6></div>
							</div>
							<div class="form-group row">
							
							<div class="col-sm-11 bordered-div">
							<b>Emisión:</b> <?php echo $data['fecha'];?><br>
							<b>Proyecto:</b> <?php echo $data['nombre_proyecto'];?><br>
							<b>Cliente:</b> <?php echo $data['nombre_cliente'];?><br>
							<b>Nro:</b> <?php echo $data['nro_sitio']."_".$data['nro_subsitio']."_".$data['nro_proyecto'];?><br>
							</div>
							</div>
							<div class="row">
							<div class="col-11">
                            <table class="table table-bordered mb-3">
                              <thead>
                                <th>Rev</th>
                                <th>Fecha</th>
                                <th>Modificaciones</th>
                                <th>Elaboró</th>
                                <th>Revisó</th>
                                <th>Aprobó</th>
                              </thead>
                              <tbody><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                //$b=0;
                                $sql = " SELECT nro_revision,date_format(fecha,'%d/%m/%y') AS fecha,descripcion,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_realizo=c.id) AS realizo,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_reviso=c.id) AS reviso,(SELECT c.nombre FROM cuentas c WHERE lcr.id_cuenta_valido=c.id) AS valido FROM listas_corte_revisiones lcr WHERE lcr.id_lista_corte = ".$data['id_lista_corte']." ORDER BY nro_revision ASC";
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
                                Database::disconnect();
                                ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example669" border="1" cellspacing="10" cellpadding="5">
							<thead>
							  <tr>
								  <th>Conjunto</th>
								  <th>Cantidad</th>
								  <th>Posiciones</th>
								  <th>Peso Conjunto Kg</th>
							  </tr>
							</thead>
							<tbody>
							  <?php
								$pesoTotal = 0;
								$pdo = Database::connect();
								$sql = " SELECT lcc.`id`, lcc.`nombre`, lcc.`cantidad`, lcc.`peso`, elcc.estado FROM `listas_corte_conjuntos` lcc inner join estados_lista_corte_conjuntos elcc on elcc.id = lcc.`id_estado_lista_corte_conjuntos` WHERE lcc.id_lista_corte = ".$id;
								foreach ($pdo->query($sql) as $row) {
									$pesoConjunto = 0;
									echo '<tr>';
									echo '<td>'. $row[1] . '</td>';
									echo '<td>'. $row[2] . '</td>';
									echo '<td>';
									?>
									<table class="display" border="1" cellspacing="10" cellpadding="5">
									<thead>
									  <tr>
										  <th>Concepto</th>
										  <th>Posición</th>
										  <th>Cantidad</th>
										  <th>Largo</th>
										  <th>Ancho</th>
										  <th>Marca</th>
										  <th>Peso Posición Kg</th>
										  <th>Colada</th>
										  <th>Diámetro</th>
										  <th>Calidad</th>
										  <th>Procesos</th>
									  </tr>
									</thead>
									<tbody>
									<?php
									$sql2 = "SELECT lcp.`id`, m.concepto, lcp.`posicion`, lcp.`cantidad`, lcp.`largo`, lcp.`ancho`, lcp.`marca`, lcp.`peso`, lcp.`finalizado`, c.nro_colada, lcp.`diametro`, m.`calidad` FROM `lista_corte_posiciones` lcp inner join materiales m on m.id = lcp.`id_material` left join coladas c on c.id = lcp.`id_colada`  WHERE lcp.`id_lista_corte_conjunto` = ".$row[0];
									foreach ($pdo->query($sql2) as $row2) {
										$pesoPosicion = $row2[7];
										if (str_starts_with($row2[1], "Chapa")) {
											if (empty($row2[4]) && empty($row2[5])) {
												$pesoPosicion = $row2[7]*$row2[10]*$row2[10];
											} else {
												$pesoPosicion = $row2[7]*$row2[4]*$row2[5]/1000;	
											}
										}
										if (str_starts_with($row2[1], "Perfil")) {
											$pesoPosicion = $row2[7]*$row2[4]/1000000;
										}
										echo '<tr>';
										echo '<td>'. $row2[1] . '</td>';
										echo '<td>'. $row2[2] . '</td>';
										echo '<td>'. $row2[3] . '</td>';
										echo '<td>'. $row2[4] . '</td>';
										echo '<td>'. $row2[5] . '</td>';
										echo '<td>'. $row2[6] . '</td>';
										echo '<td>'. $pesoPosicion . '</td>';
										echo '<td>'. $row2[9] . '</td>';
										echo '<td>'. $row2[10] . '</td>';
										echo '<td>'. $row2[11] . '</td>';
										echo '<td>';
										$sql3 = "SELECT lcpr.`id`, tp.tipo, ep.`estado`, lcpr.`observaciones` FROM `lista_corte_procesos` lcpr inner join tipos_procesos tp on tp.id = lcpr.`id_tipo_proceso` inner join estados_lista_corte_procesos ep on ep.id = lcpr.id_estado_lista_corte_proceso WHERE lcpr.`id_lista_corte_posicion` = ".$row2[0];
										foreach ($pdo->query($sql3) as $row3) {
											echo $row3[1] . ',';
										}
										echo '</td>';
										echo '</tr>';
										$pesoConjunto += $pesoPosicion;
									}
									?>
									</tbody>
									</table>
									<?php
									echo '</td>';
									echo '<td>'. $pesoConjunto . 'kg</td>';
									echo '</tr>';
									$pesoTotal += $pesoConjunto;
								}
							   Database::disconnect();
							  ?>
							</tbody>
							</table>
							</div>
							
							</div>
							Peso <b>TOTAL</b> de la lista: <?php echo number_format($pesoTotal,2);?>kgs
							<div class="col-sm-3 bordered-div">
							<b>Referencias</b><br>
							<b>C: </b>Cortar<br>
							<b>CP: </b>Corte Pantografo<br>
							<b>P: </b>Punzonar<br>
							<b>R: </b>Recortar<br>
							<b>M: </b>Marcar<br>
							<b>D: </b>Doblar<br>
							<b>A: </b>Armar<br>
							<b>S: </b>Soldar<br>
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