<?php
/*session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}*/
include 'config.php';
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
     
      <!-- Page Header Ends-->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <?php include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Compras ";
          include_once("head_page.php")?>
          <div class="container-fluid">
          
          <?php
          if (isset($_SESSION['flash_message'])) {
              $flash_message = $_SESSION['flash_message'];
              $alert_class = ($flash_message['type'] == 'success') ? 'alert-success' : 'alert-danger';
              
              echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
              echo $flash_message['message'];
              echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
              echo '</div>';
              
              unset($_SESSION['flash_message']);
          }
          ?>

          <div class="row">
          <div class="col-md-12">
          <div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarCompras.php">
					  <div class="form-group mb-0">
						N.OC/N.NP:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro_ocnp'])) echo $_POST['nro_ocnp'] ?>" name="nro_ocnp">
					  </div>
					  <div class="form-group mb-0">
						N.Sitio/N.Proy:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro">
					  </div>
					  <div class="form-group mb-0">
						Proveedor:&nbsp;<input class="form-control" size="15" type="text" value="<?php if (isset($_POST['proveedor'])) echo $_POST['proveedor'] ?>" name="proveedor">
					  </div>
					  <div class="form-group mb-0">
						Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fecha'])) echo $_POST['fecha'] ?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fechah'])) echo $_POST['fechah'] ?>" name="fechah">
					  </div>
					  <div class="form-group mb-0">
						Estado:&nbsp;
						<select name="id_estado[]" id="id_estado[]" class="js-example-basic-multiple" multiple="multiple">
							<option value="">Todos</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_compra` WHERE 1 order by estado ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if (isset($_POST['id_estado'])) {
									if (in_array($fila['id'],$_POST['id_estado'])) {
										echo " selected ";
									}
								}
								echo ">".$fila['estado']."</option>";
							}
							Database::disconnect();
							?>
							</select>
					  </div>
					  <div class="form-group mb-0">
						Aprobada:&nbsp;
						<select name="aprobada" id="aprobada" class="form-control">
							<option value="">Seleccione...</option>
							<option value="1" <?php if (isset($_POST['aprobada'])) { if ($_POST['aprobada']==1) { echo " selected "; } } ?> >Si</option>
							<option value="2" <?php if (isset($_POST['aprobada'])) { if ($_POST['aprobada']==2) { echo " selected "; } } ?> >No</option>
							</select>
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarCompras.php'">Buscar</button>
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
                &nbsp;&nbsp;
                <?php 
                echo '<a href="#" id="link_ver_compra"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                echo '&nbsp;&nbsp;';
                echo '<a href="exportCompras.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>';
                echo '&nbsp;&nbsp;';
                if (!empty(tienePermiso(299))) {
                  echo '<a href="#" id="link_modificar_compra"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificación/Revisión O.C" title="Modificación/Revisión O.C"></a>';
                  echo '&nbsp;&nbsp;';
                  echo '<a href="#" id="link_ingresar_compra"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Ingresar Stock" title="Ingresar Stock"></a>';
                  echo '&nbsp;&nbsp;';
                }
                if (!empty(tienePermiso(384))) {
                  echo '<a href="#" id="link_aprobar_compra"><img src="img/aprobar.png" width="24" height="25" border="0" alt="Aprobar" title="Aprobar"></a>';
                  echo '&nbsp;&nbsp;';
                  echo '<a href="#" id="link_rechazar_compra"><img src="img/neg.png" width="24" height="25" border="0" alt="Rechazar" title="Rechazar"></a>';
                  echo '&nbsp;&nbsp;';
                }
                if (!empty(tienePermiso(284))) {
                  echo '<a href="#" id="link_nuevo_suceso"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Agregar Suceso" title="Agregar Suceso"></a>';
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
                        <th class="d-none">ID</th>
                        <th>Nro.OC / Rev</th>
                        <th>Sitio / Sub / Proy</th>
                        <th>Proveedor</th>
                        <th>Estado</th>
                        <th>F.Emisión</th>
                        <th>F.Entrega</th>
                        <th>Aprobado</th>
                        <th style="display: none;">Proy</th>
                      </tr>
                      </thead>
                        <tbody>
                          <?php
                            if (!empty($_POST)) {
                              $pdo = Database::connect();
                              
                              $sql = "SELECT c.id,
                                                cu.nombre,
                                                DATE_FORMAT(c.fecha_emision,'%d/%m/%y') AS fecha_emision,
                                                e.estado,
                                                c.nro_oc,
                                                c.total,
                                                pe.lugar_entrega,
                                                COALESCE(s_comp.nro_sitio, s_dir.nro_sitio) AS nro_sitio,
                                                COALESCE(s_comp.nro_subsitio, s_dir.nro_subsitio) AS nro_subsitio,
                                                COALESCE(p_comp.nro, p_dir.nro) AS nro,
                                                mo.moneda,
                                                c.nro_revision,
                                                DATE_FORMAT(c.fecha_entrega,'%d/%m/%y') AS fecha_entrega,
                                                c.aprobado,
                                                DATE_FORMAT(c.fecha_emision,'%y%m%d') AS fecha_emision_sort,
                                                DATE_FORMAT(c.fecha_entrega,'%y%m%d') AS fecha_entrega_sort,
                                                COALESCE(t.id_proyecto, p_dir.id) AS id_proyecto
                              FROM compras c
                                LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_proveedor
                                LEFT JOIN estados_compra e ON e.id = c.id_estado_compra
                                INNER JOIN pedidos pe ON pe.id = c.id_pedido
                                LEFT JOIN computos co ON co.id = pe.id_computo
                                LEFT JOIN tareas t ON t.id = co.id_tarea
                                LEFT JOIN proyectos p_comp ON p_comp.id = t.id_proyecto
                                LEFT JOIN sitios s_comp ON s_comp.id = p_comp.id_sitio
                                LEFT JOIN proyectos p_dir ON p_dir.id = pe.id_proyecto
                                LEFT JOIN sitios s_dir ON s_dir.id = p_dir.id_sitio
                                LEFT JOIN monedas mo ON mo.id = c.id_moneda
                              WHERE 1 ";

                              // Array para los parámetros de la consulta preparada
                              $params = [];

                              if (!empty($_POST['nro'])) {
                                $sql .= " AND (p_comp.nro = ? OR s_comp.nro_sitio = ? OR p_dir.nro = ? OR s_dir.nro_sitio = ?)";
                                $params[] = $_POST['nro'];
                                $params[] = $_POST['nro'];
                                $params[] = $_POST['nro'];
                                $params[] = $_POST['nro'];
                              }

                              if (!empty($_POST['nro_ocnp'])) {
                                $nro_ocnp = trim($_POST['nro_ocnp']);
                                $sql .= " AND (c.`nro_oc` LIKE ? OR pe.id = ?)";
                                $params[] = '%' . $nro_ocnp . '%';
                                $params[] = $nro_ocnp;
                              }

                              if (!empty($_POST['fecha'])) {
                                $sql .= " AND c.`fecha_emision` >= ?";
                                $params[] = $_POST['fecha'];
                              }
                              if (!empty($_POST['fechah'])) {
                                $sql .= " AND c.`fecha_emision` <= ?";
                                $params[] = $_POST['fechah'];
                              }
                              if (!empty($_POST['aprobada'])) {
                                $sql .= " AND c.aprobado = ?";
                                $params[] = ($_POST['aprobada'] == 1) ? 1 : 0;
                              }
                              if (!empty($_POST['id_estado'][0])) {
                                $placeholders = implode(',', array_fill(0, count($_POST['id_estado']), '?'));
                                $sql .= " AND e.id IN (" . $placeholders . ")";
                                $params = array_merge($params, $_POST['id_estado']);
                              }
                              if (!empty($_POST['proveedor'])) {
                                $sql .= " AND cu.`nombre` LIKE ?";
                                $params[] = '%' . $_POST['proveedor'] . '%';
                              }

                              $q = $pdo->prepare($sql);
                              $q->execute($params);

                              $results = $q->fetchAll(PDO::FETCH_ASSOC);
                              $unique_ids = [];

                              foreach ($results as $row) {
                                  if (in_array($row['id'], $unique_ids)) {
                                      continue;
                                  }
                                  $unique_ids[] = $row['id'];

                                  echo '<tr>';
                                  echo '<td class="d-none">'. $row['id'] . '</td>';
                                  echo '<td>'. $row['nro_oc'] . ' / '. $row['nro_revision']. '</td>';
                                  echo '<td>'. $row['nro_sitio'] .' / '.$row['nro_subsitio'].' / '.$row['nro'].'</td>';
                                  echo '<td>'. $row['nombre'] . '</td>';
                                  echo '<td>'. $row['estado'] . '</td>';
                                  echo '<td><span style="display: none;">'. $row['fecha_emision_sort'] . '</span>'. $row['fecha_emision'] . '</td>';
                                  echo '<td><span style="display: none;">'. $row['fecha_entrega_sort'] . '</span>'. $row['fecha_entrega'] . '</td>';
                                  echo '<td>' . ($row['aprobado'] == 1 ? 'Si' : 'No') . '</td>';
                                  echo '<td style="display: none;">'.$row['id_proyecto'].'</td>';
                                  echo '</tr>';
                                  
                                  ?>
                                  <div class="modal fade" id="aprobarModal_<?php echo $row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                                      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                      </div>
                                      <div class="modal-body">¿Está seguro que desea aprobar la OC?</div>
                                      <div class="modal-footer">
                                      <a href="aprobarCompra.php?id=<?php echo $row['id']; ?>" class="btn btn-primary">Aprobar</a>
                                      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
                                      </div>
                                    </div>
                                    </div>
                                  </div>
                                  <div class="modal fade" id="rechazarModal_<?php echo $row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                                      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                      </div>
                                      <div class="modal-body">¿Está seguro que desea rechazar la OC?</div>
                                      <div class="modal-footer">
                                      <a href="rechazarCompra.php?id=<?php echo $row['id']; ?>" class="btn btn-primary">Rechazar</a>
                                      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
                                      </div>
                                    </div>
                                    </div>
                                  </div>
                                  <?php
                              }
                              Database::disconnect();
                            }
                          ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Zero Configuration  Ends-->
              <!-- Feature Unable /Disable Order Starts-->
            </div>
			<div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Conceptos</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th>Concepto</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                            <th>Peso Total kg</th>
                            <th>$/Kg</th>
                            <th>$/Unitario</th>
                            <th>$/Total</th>
                            <th>Entregado</th>
                            <th>Remitos</th>
                            <th>Facturas</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
						            <tfoot>
                          <tr>
                            <th>Concepto</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                            <th>Peso Total Kg</th>
                            <th>$/Kg</th>
                            <th>$/Unitario</th>
                            <th>$/Total</th>
                            <th>Entregado</th>
                            <th>Remitos</th>
                            <th>Facturas</th>
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
          }
        }
      });
 
      // DataTable
      var table = $('#dataTables-example666').DataTable();
      // Apply the search
      table.columns().every( function () {
        var that = this;
        $( 'input', this.footer() ).on( 'keyup change', function () {
          if ( that.search() !== this.value ) {
            that.search( this.value ).draw();
          }
        });
		  });
		
	    $("#link_ver_compra").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una compra para ver detalle")
        }
      })
	    $("#link_modificar_compra").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una compra para modificar/revisar")
        }
      })
	    $("#link_ingresar_compra").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una compra aprobada para ingresar stock")
        }
      })
	    $("#link_adjuntar_factura").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una compra aprobada para adjuntar factura")
        }
      })
	    $("#link_aprobar_compra").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione una orden de compra para aprobar")
        }
      })
	   $("#link_rechazar_compra").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione una orden de compra para rechazar")
        }
      })
	    $("#link_nuevo_suceso").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una compra para añadir un nuevo suceso")
        }
      })
	  
	    $("#link_nuevo_pago").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione una compra aprobada para añadir pago")
        }
      })
      //$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
      $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
		
        let id_compra=t.find("td:first-child").html();
        let estado = t.find("td:nth-child(8)").html();
        let id_proyecto=t.find("td:nth-child(9)").html();
		
        if(t.hasClass('selected')){
          deselectRow(t);
		      get_conceptos(id_compra)
          $("#link_ver_compra").attr("href","#");
          $("#link_modificar_compra").attr("href","#");
		      $("#link_ingresar_compra").attr("href","#");
          $("#link_adjuntar_factura").attr("href","#");
		      $("#link_nuevo_suceso").attr("href","#");
          $("#link_nuevo_pago").attr("href","#");
          $("#link_aprobar_compra").attr("data-target","#");
          $("#link_rechazar_compra").attr("data-target","#");
        }else{
          //t.parent().find("tr").removeClass("selected");
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_conceptos(id_compra)
          $("#link_ver_compra").attr("href","verCompra.php?id="+id_compra);
          $("#link_modificar_compra").attr("href","modificarCompra.php?id="+id_compra);
          if (estado == 'Si') {
            $("#link_ingresar_compra").attr("href","ingresarCompra.php?id="+id_compra);
            $("#link_adjuntar_factura").attr("href","adjuntarFactura.php?id="+id_compra);
            $("#link_nuevo_pago").attr("href","nuevoPago.php?id="+id_compra);
          } else {
            $("#link_ingresar_compra").attr("href","#");
            $("#link_adjuntar_factura").attr("href","#");
            $("#link_nuevo_pago").attr("href","#");
          }
		      if (estado == 'No') {
            $("#link_aprobar_compra").attr("data-toggle","modal");
            $("#link_aprobar_compra").attr("data-target","#aprobarModal_"+id_compra);
            $("#link_rechazar_compra").attr("data-toggle","modal");
            $("#link_rechazar_compra").attr("data-target","#rechazarModal_"+id_compra);
          } else {
            $("#link_aprobar_compra").attr("href","#");
			      $("#link_rechazar_compra").attr("href","#");
          }
		      $("#link_nuevo_suceso").attr("href","nuevoSucesoCompra.php?id="+id_compra);
        }
      });
	  } );
	
    $(document).ready(function() {
      // Setup - add a text input to each footer cell
      $('#dataTables-example667 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
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
          }
        }
      });
 
      // DataTable
      var table = $('#dataTables-example667').DataTable();
      // Apply the search
      table.columns().every( function () {
        var that = this;
        $( 'input', this.footer() ).on( 'keyup change', function () {
          if ( that.search() !== this.value ) {
            that.search( this.value ).draw();
          }
        });
      });
    });
	
	  function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }

    function get_conceptos(id_compra){
      let datosUpdate = new FormData();
      datosUpdate.append('id_compra', id_compra);
      $.ajax({
        data: datosUpdate,
        url: 'get_conceptos_compra.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          console.log(data);
          data = JSON.parse(data);
          console.log(data);

          $('#dataTables-example667').DataTable().destroy();
          $('#dataTables-example667').DataTable({
            stateSave: false,
            responsive: false,
            data: data,
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
              }
            }
          });
      
          // DataTable
          var table = $('#dataTables-example667').DataTable();
          // Apply the search
          table.columns().every( function () {
            var that = this;
            $( 'input', this.footer() ).on( 'keyup change', function () {
              if ( that.search() !== this.value ) {
                that.search( this.value ).draw();
              }
            });
          });

          
        }
      });
    }
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <!-- Plugin used-->
  </body>
</html>