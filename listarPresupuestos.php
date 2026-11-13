<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
require_once __DIR__ . '/funciones.php';

$filtrosPresupuesto = [
  'nro' => '',
  'fecha' => '',
  'fechah' => '',
  'cliente' => '',
  'id_empresa' => '',
  'solicitante' => '',
  'referencia' => '',
];
$mensajeErrorFiltros = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($filtrosPresupuesto as $campo => $valor) {
    if (isset($_POST[$campo])) {
      if (is_string($_POST[$campo])) {
        $filtrosPresupuesto[$campo] = trim($_POST[$campo]);
      } else {
        $mensajeErrorFiltros = 'Los filtros deben contener valores de texto válidos.';
      }
    }
  }
} else {
  $hoy = new DateTimeImmutable('today', new DateTimeZone('America/Argentina/Buenos_Aires'));
  $filtrosPresupuesto['fecha'] = $hoy->modify('-7 days')->format('Y-m-d');
  $filtrosPresupuesto['fechah'] = $hoy->format('Y-m-d');
}

foreach (['fecha', 'fechah'] as $campoFecha) {
  if ($filtrosPresupuesto[$campoFecha] !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $filtrosPresupuesto[$campoFecha])) {
      $mensajeErrorFiltros = 'Ingrese un rango de fechas válido.';
      continue;
    }
    $fechaValidada = DateTimeImmutable::createFromFormat('!Y-m-d', $filtrosPresupuesto[$campoFecha]);
    if (!$fechaValidada || $fechaValidada->format('Y-m-d') !== $filtrosPresupuesto[$campoFecha]) {
      $mensajeErrorFiltros = 'Ingrese un rango de fechas válido.';
    }
  }
}
if ($filtrosPresupuesto['fecha'] !== '' && $filtrosPresupuesto['fechah'] !== '' && $filtrosPresupuesto['fecha'] > $filtrosPresupuesto['fechah']) {
  $mensajeErrorFiltros = 'La fecha desde no puede ser posterior a la fecha hasta.';
}

$sql = " SELECT p.id, p.nro, p.nro_revision, e.empresa, date_format(p.fecha,'%d/%m/%y') AS fecha, c.nombre, p.solicitante, p.referencia, p.descripcion, m.moneda, p.monto, p.adjudicado, date_format(p.fecha,'%Y%m%d') FROM presupuestos p inner join cuentas c on c.id = p.id_cuenta inner join monedas m on m.id = p.id_moneda inner join empresas e on e.id = p.id_empresa WHERE p.anulado = 0 ";
$params = [];
if ($filtrosPresupuesto['nro'] !== '') {
  $sql .= ' AND p.nro = ?';
  $params[] = $filtrosPresupuesto['nro'];
}
if ($filtrosPresupuesto['fecha'] !== '') {
  $sql .= ' AND p.fecha >= ?';
  $params[] = $filtrosPresupuesto['fecha'];
}
if ($filtrosPresupuesto['fechah'] !== '') {
  $sql .= ' AND p.fecha <= ?';
  $params[] = $filtrosPresupuesto['fechah'];
}
if ($filtrosPresupuesto['cliente'] !== '') {
  $sql .= ' AND c.nombre LIKE ?';
  $params[] = '%' . $filtrosPresupuesto['cliente'] . '%';
}
if ($filtrosPresupuesto['id_empresa'] !== '') {
  $sql .= ' AND p.id_empresa = ?';
  $params[] = $filtrosPresupuesto['id_empresa'];
}
if ($filtrosPresupuesto['solicitante'] !== '') {
  $sql .= ' AND p.solicitante LIKE ?';
  $params[] = '%' . $filtrosPresupuesto['solicitante'] . '%';
}
if ($filtrosPresupuesto['referencia'] !== '') {
  $sql .= ' AND (p.referencia LIKE ? OR p.descripcion LIKE ?)';
  $params[] = '%' . $filtrosPresupuesto['referencia'] . '%';
  $params[] = '%' . $filtrosPresupuesto['referencia'] . '%';
}

$presupuestos = [];
if ($mensajeErrorFiltros === '') {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $q = $pdo->prepare($sql);
  $q->execute($params);
  $presupuestos = $q->fetchAll(PDO::FETCH_BOTH);
  Database::disconnect();
} ?>
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
          $ubicacion="Presupuestos ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid"><?php
            if ($mensajeErrorFiltros !== '') { ?>
              <div class="alert alert-danger" role="alert"><?=escaparHtml($mensajeErrorFiltros)?></div><?php
            } ?>
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarPresupuestos.php">
					  <div class="form-group mb-0">
						<label for="nro">Nro.</label>
						<input class="form-control" size="3" type="text" value="<?=escaparHtml($filtrosPresupuesto['nro'])?>" name="nro" id="nro">
					  </div>
					  <div class="form-group mb-0">
						<label for="fecha">Desde</label>
						<input class="form-control" size="20" type="date" id="fecha" name="fecha" value="<?=escaparHtml($filtrosPresupuesto['fecha'])?>">
						<label for="fechah">Hasta</label>
						<input class="form-control" size="20" type="date" id="fechah" name="fechah" value="<?=escaparHtml($filtrosPresupuesto['fechah'])?>">
					  </div>
					  <div class="form-group mb-0">
						<label for="cliente">Cliente</label>
						<select name="cliente" id="cliente" class="js-example-basic-single">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE (id_tipo_cuenta = 1 or id in (select id_cuenta from cuentas_x_tipos_cuenta where id_tipo_cuenta = 1)) and activo = 1 and anulado = 0 order by nombre";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".escaparHtml($fila['nombre'])."'";
								if ($fila['nombre'] === $filtrosPresupuesto['cliente']) {
									echo " selected ";
								}
								echo ">".escaparHtml($fila['nombre'])."</option>";
							}
							Database::disconnect();
							?>
							</select>
					  </div>
					  <div class="form-group mb-0">
						<label for="id_empresa">Empresa</label>
						<select name="id_empresa" id="id_empresa" class="js-example-basic-single">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlEmp = "SELECT `id`, `empresa` FROM `empresas` ORDER BY empresa";
							$qEmp = $pdo->prepare($sqlEmp);
							$qEmp->execute();
							while ($filaEmp = $qEmp->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".(int)$filaEmp['id']."'";
								if ((string)$filaEmp['id'] === $filtrosPresupuesto['id_empresa']) {
									echo " selected ";
								}
								echo ">".escaparHtml($filaEmp['empresa'])."</option>";
							}
							Database::disconnect();
							?>
						</select>
					  </div>
					  <div class="form-group mb-0">
						<label for="solicitante">Solicitante</label>
						<input class="form-control" size="20" type="text" value="<?=escaparHtml($filtrosPresupuesto['solicitante'])?>" name="solicitante" id="solicitante">
					  </div>
					  <div class="form-group mb-0">
						<label for="referencia">Referencia</label>
						<input class="form-control" size="20" type="text" value="<?=escaparHtml($filtrosPresupuesto['referencia'])?>" name="referencia" id="referencia">
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarPresupuestos.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion;
                      if(!empty(tienePermiso(268))) {?>
                        <a href="nuevoPresupuesto.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a><?php
                      }?>
                      &nbsp;&nbsp;<?php
                      //echo '<a href="#" id="link_ver_presupuesto"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                      //echo '&nbsp;&nbsp;';
                      if (!empty(tienePermiso(269))) {
                        echo '<a href="#" id="link_modificar_presupuesto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Ver/Modificar" title="Ver/Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                        echo '<a href="#" id="link_revisar_presupuesto"><img src="img/edit3.png" width="24" height="25" border="0" alt="Revisión" title="Revisión"></a>';
                        echo '&nbsp;&nbsp;';
                        echo '<a href="#" id="link_adjudicar_presupuesto"><img src="img/tratohecho.png" width="24" height="20" border="0" alt="Adjudicar" title="Adjudicar"></a>';
                        echo '&nbsp;&nbsp;';						
                      }
                      if (!empty(tienePermiso(268))) {
                        echo '<a href="#" id="link_items_presupuesto"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Ver/Añadir ítems" title="Ver/Añadir ítems"></a>';
                        echo '&nbsp;&nbsp;';
                        echo '<a href="#" id="link_items_presupuesto_masivo"><img src="img/import.png" width="24" height="25" border="0" alt="Importar ítems" title="Importar ítems"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(268))) {
                        echo '<a href="#" id="link_clonar_presupuesto"><img src="img/clone.png" width="24" height="25" border="0" alt="Nuevo Desde" title="Nuevo Desde"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(270))) {
                        echo '<a href="#" id="link_eliminar_presupuesto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th class="d-none">Id</th>
                            <th>Nro.</th>
                            <th>Rev.</th>
                            <th>Empresa</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Solicitante</th>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Adj.</th>
                          </tr>
                        </thead>
                        <tfoot>
                            <tr>
                              <th class="d-none">Id</th>
                              <th>Nro.</th>
                              <th>Rev.</th>
                              <th>Empresa</th>
                              <th>Fecha</th>
                              <th>Cliente</th>
                              <th>Solicitante</th>
                              <th>Referencia</th>
                              <th>Descripción</th>
                              <th>Monto</th>
                              <th>Adj.</th>
                            </tr>
                          </tfoot>
                        <tbody>
						<?php
								foreach ($presupuestos as $row) {
								  echo '<tr>';
								  echo '<td class="d-none">'. $row[0] . '</td>';
								  echo '<td>'. $row[1] . '</td>';
								  echo '<td>'. $row[2] . '</td>';
								  echo '<td>'. $row[3] . '</td>';
								  echo '<td><span style="display: none;">'. $row[12] . '</span>'. $row[4] . '</td>';
								  echo '<td>'. $row[5] . '</td>';
								  echo '<td>'. $row[6] . '</td>';
								  echo '<td>'. $row[7] . '</td>';
								  echo '<td>'. $row[8] . '</td>';
								  echo '<td>'. $row[9] . ' ' . number_format($row[10],2). '</td>';
								  if ($row[11] == 1) {
									  echo '<td>Si</td>';
								  } else {
									  echo '<td>No</td>';
								  }
								  echo '</tr>';?>

								  <div class="modal fade" id="eliminarModal_<?php echo $row["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
									<div class="modal-dialog" role="document">
									  <div class="modal-content">
										<div class="modal-header">
										  <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
										  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
										</div>
										<div class="modal-body">¿Está seguro que desea eliminar el presupuesto?</div>
										<div class="modal-footer">
										  <a href="eliminarPresupuesto.php?id=<?php echo $row["id"]; ?>" class="btn btn-primary">Eliminar</a>
										  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
										</div>
									  </div>
									</div>
								  </div>
								  
								  <div class="modal fade" id="adjudicarModal_<?php echo $row["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
									<div class="modal-dialog" role="document">
									  <div class="modal-content">
										<div class="modal-header">
										  <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
										  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
										</div>
										<div class="modal-body">¿Está seguro que desea adjudicar/desadjudicar el presupuesto?</div>
										<div class="modal-footer">
										  <a href="adjudicarPresupuesto.php?id=<?php echo $row["id"]; ?>" class="btn btn-primary">Ejecutar</a>
										  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
										</div>
									  </div>
									</div>
								  </div>
							
								  <div class="modal fade" id="clonarModal_<?php echo $row["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
									<div class="modal-dialog" role="document">
									  <div class="modal-content">
										<div class="modal-header">
										  <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
										  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
										</div>
										<div class="modal-body">¿Está seguro que desea clonar este presupuesto?</div>
										<div class="modal-footer">
										  <a href="clonarPresupuesto.php?id=<?php echo $row["id"]; ?>" class="btn btn-primary">Clonar</a>
										  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
										</div>
									  </div>
									</div>
								  </div><?php
								} ?>
                          </tbody>
                        </table>
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
        $('#dataTables-example666 tfoot th').each( function () {
            var title = $(this).text();
            $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
        $('#dataTables-example666').DataTable({
          stateSave: false,
          //searching: false,//debemos quitar esta linea para que funcione el buscador
		  dom: 'Bfrtp<"bottom"l>',
          responsive: false,
		  "order": [[0, 'desc']], 
		  "createdRow": function(row, data, dataIndex) {
            // Limitar el texto de la columna 0 (Name)
            var maxLength = 20; // Número máximo de caracteres
            var cell = $('td', row).eq(8); // Obtener la celda de la primera columna
            var cellText = cell.text();

            if (cellText.length > maxLength) {
                var truncatedText = cellText.substring(0, maxLength) + '...';
                cell.html('<span title="' + cellText + '">' + truncatedText + '</span>');
            }
			var cell = $('td', row).eq(7); // Obtener la celda de la primera columna
            var cellText = cell.text();

            if (cellText.length > maxLength) {
                var truncatedText = cellText.substring(0, maxLength) + '...';
                cell.html('<span title="' + cellText + '">' + truncatedText + '</span>');
            }
			var cell = $('td', row).eq(6); // Obtener la celda de la primera columna
            var cellText = cell.text();

            if (cellText.length > maxLength) {
                var truncatedText = cellText.substring(0, maxLength) + '...';
                cell.html('<span title="' + cellText + '">' + truncatedText + '</span>');
            }
			var cell = $('td', row).eq(5); // Obtener la celda de la primera columna
            var cellText = cell.text();

            if (cellText.length > maxLength) {
                var truncatedText = cellText.substring(0, maxLength) + '...';
                cell.html('<span title="' + cellText + '">' + truncatedText + '</span>');
            }
        },
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
    
        $("#link_ver_presupuesto").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un presupuesto para ver detalle")
          }
        })
        $("#link_modificar_presupuesto").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor un presupuesto en su última revisión para modificar")
          }
        })
		$("#link_revisar_presupuesto").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor un presupuesto en su última revisión para revisar")
          }
        })
		
        $("#link_items_presupuesto").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un presupuesto para ver/añadir/modificar ítems")
          }
        })
        $("#link_items_presupuesto_masivo").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un presupuesto para ver/añadir/modificar ítems en forma masiva")
          }
        })
        $("#link_clonar_presupuesto").on("click",function(){
          let target=this.dataset.target;
          if(target==undefined || target=="#"){
            alert("Por favor seleccione un presupuesto para clonar")
          }
        })
        $("#link_eliminar_presupuesto").on("click",function(){
          
          let target=this.dataset.target;
          if(target==undefined || target=="#"){
            alert("Por favor seleccione un presupuesto NO ADJUDICADO para eliminar")
          }
        })	  
		 $("#link_adjudicar_presupuesto").on("click",function(){
         
          let target=this.dataset.target;
          if(target==undefined || target=="#"){
            alert("Por favor un presupuesto NO ADJUDICADO en su última revisión para adjudicar")
          }
        })	  
		
        $(document).on("click","#dataTables-example666 tbody tr td", function(){
          var t=$(this).parent();

          let id_presupuesto=t.find("td:first-child").html();
          let nro_revision = t.find("td:nth-child(3)").html();
		  let adj = t.find("td:nth-child(11)").html();
		  
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#link_ver_presupuesto").attr("href","#");
            $("#link_modificar_presupuesto").attr("href","#");
			$("#link_revisar_presupuesto").attr("href","#");
			$("#link_adjudicar_presupuesto").attr("href","#");
            $("#link_items_presupuesto").attr("href","#");
            $("#link_items_presupuesto_masivo").attr("href","#");
            $("#link_clonar_presupuesto").attr("data-target","#");
            $("#link_eliminar_presupuesto").attr("data-target","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            $("#link_ver_presupuesto").attr("href","verPresupuesto.php?id="+id_presupuesto);
            $("#link_modificar_presupuesto").attr("href","modificarPresupuesto.php?id="+id_presupuesto+"&revision="+nro_revision);
			$("#link_revisar_presupuesto").attr("href","modificarPresupuestoRevision.php?id="+id_presupuesto+"&revision="+nro_revision);
			$("#link_items_presupuesto").attr("href","itemsPresupuesto.php?id="+id_presupuesto);
            $("#link_items_presupuesto_masivo").attr("href","itemsPresupuestoMasivo.php?id="+id_presupuesto);
            $("#link_clonar_presupuesto").attr("data-toggle","modal");
            $("#link_clonar_presupuesto").attr("data-target","#clonarModal_"+id_presupuesto);
			if (adj == "No") {
				$("#link_eliminar_presupuesto").attr("data-toggle","modal");
				$("#link_eliminar_presupuesto").attr("data-target","#eliminarModal_"+id_presupuesto);
				$("#link_adjudicar_presupuesto").attr("data-toggle","modal");
				$("#link_adjudicar_presupuesto").attr("data-target","#adjudicarModal_"+id_presupuesto);
			} else {
				$("#link_eliminar_presupuesto").attr("data-target","#");
				$("#link_adjudicar_presupuesto").attr("data-toggle","modal");
				$("#link_adjudicar_presupuesto").attr("data-target","#adjudicarModal_"+id_presupuesto);
			}
          }
        });
      } );

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
	<script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <!-- Plugin used-->
  </body>
</html>