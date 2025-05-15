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
        header("Location: listarProyectos.php");
    }
    
    if (!empty($_POST)) {
        
       
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT p.`id`, p.`id_sitio`, s.latitud, s.longitud, p.`descripcion`, p.`id_tipo_proyecto`, p.`observaciones`, p.`solicitante`, p.`fecha_pedido`, p.`fecha_entrega`, p.`id_estado_proyecto`, p.`id_usuario`, p.`informacion_entrada`, p.`facturado`, p.`id_gerente`, p.`id_linea_negocio`, p.`anulado`, p.`id_cliente`, p.`nombre`, p.`tags` FROM `proyectos` p inner join sitios s on s.id = p.id_sitio WHERE p.id = ? ";
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
	<link rel="stylesheet" type="text/css" href="assets/css/mapsjs-ui.css">
	<style>
        /* Define el tamaño del contenedor del mapa */
        #mapContainer {
            width: 100%;
            height: 500px;
        }
    </style>
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
          $ubicacion="Ver Proyecto";
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
							<label class="col-sm-3 col-form-label">Sitio</label>
							<div class="col-sm-9">
							<select name="id_sitio" id="id_sitio" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre`,nro_sitio,nro_subsitio FROM `sitios` WHERE 1 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_sitio']) {
                                        echo " selected ";
								}
								echo ">".$fila['nombre'].' ('.$fila['nro_sitio'].' / '.$fila['nro_subsitio'].")</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nombre</label>
							<div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['nombre']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Descripción</label>
							<div class="col-sm-9"><input name="descripcion" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['descripcion']; ?>"></div>
							</div>
							
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cliente</label>
							<div class="col-sm-9">
							<select name="id_cliente" id="id_cliente" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 1 and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_cliente']) {
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
							<label class="col-sm-3 col-form-label">Solicitante</label>
							<div class="col-sm-9"><input name="solicitante" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['solicitante']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Información_de Entrada</label>
							<div class="col-sm-9"><textarea name="informacion_entrada" class="form-control"><?php echo $data['informacion_entrada']; ?></textarea></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Pedido</label>
							<div class="col-sm-9"><input name="fecha_pedido" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" required="required" value="<?php echo $data['fecha_pedido']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Entrega</label>
							<div class="col-sm-9"><input name="fecha_entrega" type="date" onfocus="this.showPicker()" maxlength="99" class="form-control" value="<?php echo $data['fecha_entrega']; ?>"></div>
							</div>
							
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Línea de Negocio</label>
							<div class="col-sm-9">
							<select name="id_linea_negocio" id="id_linea_negocio" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `linea_negocio` FROM `lineas_negocio` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_linea_negocio']) {
                                        echo " selected ";
								}
								echo ">".$fila['linea_negocio']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tags de Búsqueda</label>
							<div class="col-sm-9"><input name="tags" type="text" maxlength="299" class="form-control" required="required" value="<?php echo $data['tags']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Tipo</label>
							<div class="col-sm-9">
							<select name="id_tipo_proyecto" id="id_tipo_proyecto" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `tipo` FROM `tipos_proyecto` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_tipo_proyecto']) {
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
							<label class="col-sm-3 col-form-label">Gerente</label>
							<div class="col-sm-9">
							<select name="id_gerente" id="id_gerente" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta = 4 and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_gerente']) {
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
							<label class="col-sm-3 col-form-label">Estado</label>
							<div class="col-sm-9">
							<select name="id_estado_proyecto" id="id_estado_proyecto" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_proyecto` WHERE 1";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_estado_proyecto']) {
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
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"><?php echo $data['observaciones']; ?></textarea></div>
							</div>
							<hr>
							<h5>Sucesos</h5>
							<div class="form-group row">
								<div class="col-sm-9">
								<div class="timeline-small">
								  <?php 
									$pdo = Database::connect();
									$sql = " SELECT s.`id`, date_format(s.`fecha_hora`,'%d/%m/%y %H:%i'), s.`suceso`, s.`titulo`, t.tipo FROM `sucesos_proyecto` s inner join tipos_suceso t on t.id = s.id_tipo_suceso WHERE s.`id_proyecto` = ".$_GET['id'].' order by s.id desc';
									
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
							<h5>Adjuntos</h5>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Descripción</th>
									  <th>Adjunto</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT `id`, `descripcion`, `archivo` FROM `adjuntos_proyecto` WHERE `anulado` = 0 and `id_proyecto` = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td><a target="_blank" href="'.$row[2].'">Descargar</a></td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
								<tfoot>
								  <tr>
									  <th>Descripción</th>
									  <th>Adjunto</th>
								  </tr>
								</tfoot>
							  </table>
							</div>
							</div>
							<hr>
							<h5>Presupuestos</h5>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example668">
								<thead>
								  <tr>
									  <th>Nro</th>
									  <th>Fecha</th>
									  <th>Cliente</th>
									  <th>Solicitante</th>
									  <th>Referencia</th>
									  <th>Descripción</th>
									  <th>Monto</th>
									  <th>Adjudicado</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
                  					$sql = " SELECT p.nro, date_format(p.fecha,'%d/%m/%y'), p.nro_revision, c.nombre, p.solicitante, p.referencia, p.descripcion, m.moneda, p.monto, p.adjudicado,date_format(p.fecha,'%Y'),pp.id FROM proyectos_presupuestos pp inner join presupuestos p on p.id = pp.id_presupuesto inner join cuentas c on c.id = p.id_cuenta inner join monedas m on m.id = p.id_moneda WHERE p.anulado = 0 and pp.id_proyecto = ".$_GET['id'];
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[0] . ' / '.$row[2].'</td>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										echo '<td>'. $row[4] . '</td>';
										echo '<td>'. $row[5] . '</td>';
										echo '<td>'. $row[6] . '</td>';
										echo '<td>'. $row[7] . ' ' . number_format($row[8],2). '</td>';
										if ($row[9] == 1) {
											echo '<td>Si</td>';
										} else {
											echo '<td>No</td>';
										}
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
								<tfoot>
								  <tr>
									  <th>Nro</th>
									  <th>Fecha</th>
									  <th>Cliente</th>
									  <th>Solicitante</th>
									  <th>Referencia</th>
									  <th>Descripción</th>
									  <th>Monto</th>
									  <th>Adjudicado</th>
								  </tr>
								</tfoot>
							  </table>
							</div>
							</div>
							<hr>
							<h5>Mapa</h5>
							<div class="form-group row">
							<div class="col-sm-12">
								<div id="mapContainer"></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<a onclick="document.location.href='listarProyectos.php'" class="btn btn-light">Volver</a>
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
	<script src="https://js.api.here.com/v3/3.1/mapsjs-core.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-service.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-ui.js"></script>
    <script src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js"></script>
    
	<script>
    // Inicializa la plataforma con tu API Key de HERE
    var platform = new H.service.Platform({
        apikey: '9fZ937HZp9Pg9l8A36-K8U-HYO6g_yRNJIk7YoRNic0' // Reemplaza con tu API Key
    });

    // Obtiene las capas del mapa predeterminadas (ej. vista de mapa normal, satélite, etc.)
    var defaultLayers = platform.createDefaultLayers();

	// map12
	function addMarkersToMap(map) {
	  var parisMarker = new H.map.Marker({lat:<?php echo $data['latitud']; ?>, lng:<?php echo $data['longitud']; ?>});
	  map.addObject(parisMarker);
	}

    // Inicializa el mapa en el contenedor con las capas predeterminadas
    var map = new H.Map(
        document.getElementById('mapContainer'),  // ID del contenedor HTML
        defaultLayers.vector.normal.map,  // Tipo de mapa (mapa base normal en vector)
        {
            zoom: 11,  // Nivel de zoom inicial
            center: {lat:<?php echo $data['latitud']; ?>, lng:<?php echo $data['longitud']; ?>} // Coordenadas iniciales (Berlín en este caso)
        }
    );

    // Habilita interacciones de usuario como el zoom y el arrastre
    var behavior = new H.mapevents.Behavior(new H.mapevents.MapEvents(map));

    // Agrega controles de UI como el zoom y la brújula
    var ui = H.ui.UI.createDefault(map, defaultLayers);
	
	addMarkersToMap(map);
</script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	<script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
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
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>