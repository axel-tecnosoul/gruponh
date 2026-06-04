<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
require 'database.php';

$materials = [];
$alertMessage = '';
$alertType = '';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'internas') {
    header('Content-Type: application/json; charset=utf-8');
    $id_material = isset($_GET['id_material']) ? intval($_GET['id_material']) : 0;
    if ($id_material <= 0) {
        echo json_encode(['success' => false, 'message' => 'Concepto inválido']);
        exit;
    }
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "SELECT id, nro_colada_interna, cantidad, saldo FROM ingresos_detalle WHERE id_material = ? AND nro_colada_interna IS NOT NULL AND nro_colada_interna <> '' AND id_colada_origen IS NULL";
    $q = $pdo->prepare($sql);
    $q->execute([$id_material]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    Database::disconnect();
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if (!empty($_POST['accion']) && $_POST['accion'] === 'nueva_colada_origen') {
    $id_material = intval($_POST['id_material'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $cod_fabricante = trim($_POST['cod_fabricante'] ?? '');
    $nro_colada = trim($_POST['nro_colada'] ?? '');
    $adjunto = trim($_POST['adjunto'] ?? '');
    $internalIds = !empty($_POST['id_coladas_internas']) && is_array($_POST['id_coladas_internas']) ? $_POST['id_coladas_internas'] : [];

    if ($id_material <= 0 || $fecha === '' || $cod_fabricante === '' || $nro_colada === '' || $adjunto === '') {
        $alertMessage = 'Complete todos los datos obligatorios antes de crear la colada de origen.';
        $alertType = 'danger';
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "INSERT INTO `coladas` (`id_material`, `id_proveedor`, `id_compra`, `cod_fabricante`, `nro_colada`, `adjunto`, `fecha`, `es_origen`) VALUES (?, NULL, NULL, ?, ?, ?, ?, 1)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_material, $cod_fabricante, $nro_colada, $adjunto, $fecha]);
        $idColada = $pdo->lastInsertId();

        if (!empty($internalIds)) {
            $sql = "UPDATE ingresos_detalle SET id_colada_origen = ? WHERE id = ?";
            $q = $pdo->prepare($sql);
            foreach ($internalIds as $detalleId) {
                $detalleId = intval($detalleId);
                if ($detalleId > 0) {
                    $q->execute([$idColada, $detalleId]);
                }
            }
        }

        $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva colada de origen ID #$idColada creada','Coladas','verColada.php?id=$idColada')";
        $q = $pdo->prepare($sql);
        $q->execute([$_SESSION['user']['id']]);

        Database::disconnect();
        header('Location: listarColadas.php?created=1');
        exit;
    }
}

if (!empty($_GET['created'])) {
    $alertMessage = 'Colada de origen creada con éxito.';
    $alertType = 'success';
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT id, concepto FROM materiales ORDER BY concepto";
$materials = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();
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
          $ubicacion="Coladas ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo $ubicacion; ?><a href="exportColadas.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
					&nbsp;&nbsp;
					<?php 
					if (!empty(tienePermiso(325))) {
						echo '<a href="nuevaColadaOrigen.php" id="btnNuevaColadaOrigen"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva Colada de Origen" title="Nueva Colada de Origen"></a>';
						echo '&nbsp;&nbsp;';
						echo '<a href="#" id="link_modificar_colada"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Actualizar Colada" title="Actualizar Colada"></a>';
						echo '&nbsp;&nbsp;';
					}
					?>
					</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
						      <th>ID</th>
							  <th>Código</th>
							  <th>Concepto</th>
							  <th>Categoría</th>
							  <th>Proveedor</th>
							  <th>Nro. OC</th>
							  <th>Fabricante</th>
							  <th>Nro. Colada</th>
							  <th>Colada Interna</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                            $pdo = Database::connect();
                            $sql = " SELECT c.`id`, m.`codigo`, m.`concepto`, ca.categoria, cu.`nombre`, co.`nro_oc`, c.`id_compra`, c.`cod_fabricante`, c.`nro_colada`, id.nro_colada_interna FROM `coladas` c inner join materiales m on m.id = c.`id_material` inner join categorias ca on ca.id = m.`id_categoria` left join cuentas cu on cu.id = c.`id_proveedor` left join compras co on co.id = c.id_compra left join ingresos_detalle id on id.id_colada = c.id WHERE 1 ";
                            
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
								echo '<td>'. $row[0] . '</td>';
                                echo '<td>'. $row[1] . '</td>';
                                echo '<td>'. $row[2] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '<td>'. $row[5] . '</td>';
								echo '<td>'. $row[7] . '</td>';
								echo '<td>'. $row[8] . '</td>'; 
								echo '<td>'. $row[9] . '</td>'; 
                                echo '</tr>';
                            }
                           Database::disconnect();
                          ?>
                        </tbody>
						<tfoot>
                          <tr>
							  <th>ID</th>
							  <th>Código</th>
							  <th>Concepto</th>
							  <th>Categoría</th>
							  <th>Proveedor</th>
							  <th>Nro. OC</th>
							  <th>Fabricante</th>
							  <th>Nro. Colada</th>
							  <th>Colada Interna</th>
                          </tr>
                        </tfoot>
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

	$("#link_modificar_colada").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una colada para modificarla")
        }
      })
		
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();

        let id_colada=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_modificar_colada").attr("href","#");
        }else{
          //t.parent().find("tr").removeClass("selected");
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_modificar_colada").attr("href","modificarColada.php?id="+id_colada);
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
    <!-- Plugin used-->
  </body>
</html>