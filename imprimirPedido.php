<?php
	include("permisos.php");
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
        header("Location: listarPedidos.php");
    }
    
    if (!empty($_POST)) {
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT pe.`id`, pe.`id_computo`, c.id_tarea, c.id_cuenta_solicitante, pe.`fecha`, pe.`lugar_entrega`, pe.`id_cuenta_recibe`, pro.nombre, cu.nombre cuenta_solicitante, pro.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio,t.observaciones FROM `pedidos` pe inner join computos c on c.id = pe.`id_computo` inner join tareas t on t.id = c.id_tarea inner join proyectos pro on pro.id = t.id_proyecto inner join cuentas cu on cu.id = c.id_cuenta_solicitante inner join sitios s on s.id = pro.id_sitio WHERE pe.id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		
		if (empty($data['id_computo'])) {
			$sql2 = "SELECT pro.nombre, pro.solicitante cuenta_solicitante, pro.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio,pro.observaciones FROM `pedidos` pe inner join proyectos pro on pro.id = pe.id_proyecto inner join sitios s on s.id = pro.id_sitio WHERE pe.id = ? ";
			$q2 = $pdo->prepare($sql2);
			$q2->execute([$data['id']]);
			$data2 = $q2->fetch(PDO::FETCH_ASSOC);
			$nombreProyecto = $data2['nombre'];
			$idSitio = $data2['id_sitio'];
			$nroSitio = $data2['nro_sitio'];
			$nroSubsitio = $data2['nro_subsitio'];
			$nroProyecto = $data2['nro_proyecto'];
			$cuentaSolicitante = $data2['cuenta_solicitante'];
			$observaciones = $data2['observaciones'];
		} else {
			$sql2 = "SELECT pro.nombre, cu.nombre cuenta_solicitante, pro.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio,t.observaciones FROM `pedidos` pe inner join computos c on c.id = pe.`id_computo` inner join tareas t on t.id = c.id_tarea inner join proyectos pro on pro.id = t.id_proyecto inner join cuentas cu on cu.id = c.id_cuenta_solicitante inner join sitios s on s.id = pro.id_sitio WHERE pe.id = ? ";
			$q2 = $pdo->prepare($sql2);
			$q2->execute([$data['id']]);
			$data2 = $q2->fetch(PDO::FETCH_ASSOC);
			$nombreProyecto = $data2['nombre'];
			$idSitio = $data2['id_sitio'];
			$nroSitio = $data2['nro_sitio'];
			$nroSubsitio = $data2['nro_subsitio'];
			$nroProyecto = $data2['nro_proyecto'];
			$cuentaSolicitante = $data2['cuenta_solicitante'];
			$observaciones = $data2['observaciones'];
		}
        
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
        <div class="page-body"><?php
          $ubicacion="Ver Pedido";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
				
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<div class="col-sm-8 bordered-div"><h3><b>NOTA DE PEDIDO</b></h3></div>
							<div class="col-sm-3 bordered-div"><h6><b>Nº <?php echo $data['id']; ?></b></h6></div>
							</div>
							<?php
							$logo = "logo_np.png";
							$sql666 = "SELECT id_empresa from sitios where id = ".$idSitio;
							$q666 = $pdo->prepare($sql666);
							$q666->execute();
							$data666 = $q666->fetch(PDO::FETCH_ASSOC);
							if ($data666['id_empresa'] == 1) {
								$logo = "logo_nc.png";	
							}
							?>
							<div class="form-group row">
							<div class="col-sm-11 bordered-div"><img src="img/<?php echo $logo;?>" width="500px"></div>
							</div>
							<div class="form-group row">
							<div class="col-sm-11 bordered-div">
							<b>Fecha:</b> <?php echo $data['fecha'];?><br>
							<b>Proyecto:</b> <?php echo $data['nombre'];?><br>
							<b>Nro:</b> <?php echo $data['nro_sitio']."_".$data['nro_subsitio']."_".$data['nro_proyecto'];?><br>
							<b>Solicitó:</b> <?php echo $data['cuenta_solicitante'];?><br>
							</div>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Código</th>
									  <th>Unidad</th>
									  <th>Cantidad</th>
									  <th>Descripción</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT m.`codigo`, u.`unidad_medida`, d.`cantidad`, m.`concepto` FROM `pedidos_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_pedido = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[0] . '</td>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td>'. $row[2] . '</td>';
										echo '<td>'. $row[3] . '</td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
							  </table>
							</div>
							</div>
							<div class="form-group row">
							<div class="col-sm-11 bordered-div-thin">
							<?php
							$sql666 = "SELECT valor from parametros where id = 9 ";
							$q666 = $pdo->prepare($sql666);
							$q666->execute();
							$data666 = $q666->fetch(PDO::FETCH_ASSOC);
							echo $data666['valor'];
							?>
							</div>
							</div>
							<div class="form-group row">
							<div class="col-sm-12">
							<b>Comentarios:</b> <?php echo $data['observaciones'];?><br><br>
							<b>Lugar de entrega:</b> <?php echo $data['lugar_entrega'];?><br>
							</div>
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
    $('#dataTables-example667 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example667').DataTable({
        stateSave: false,
        responsive: false,
		
		searching: false,
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
<script>window.print();</script>