<?php
require("config.php");
require("PHPMailer/class.phpmailer.php");
require("PHPMailer/class.smtp.php");

if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}

require 'database.php';

if (!empty($_POST)) {
    
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if(empty($_POST["otros_importes"])) $_POST["otros_importes"]=0;

	$sql = "INSERT INTO occ (numero, fecha_emision, fecha_recepcion, id_cuenta_cliente, monto, id_moneda, id_condicion_iva, percepcion, otros_importes, id_forma_pago, id_presupuesto, requiere_polizas, abierta, fecha_vencimiento, fecha_entrega, lugar_entrega, observaciones, activa, monto_total_certificados, monto_total_facturados) VALUES (?,?,?,?,0,?,?,?,?,?,?,?,?,?,?,?,?,1,0,0)";
	$q = $pdo->prepare($sql);
	$q->execute([$_POST["numero"], $_POST["fecha_emision"], $_POST["fecha_recepcion"], $_POST["id_cuenta_cliente"], $_POST["id_moneda"], $_POST["id_condicion_iva"], 0, 0, $_POST["id_forma_pago"], $_POST["id_presupuesto"], $_POST["requiere_polizas"], $_POST["tipo_oc"], $_POST["fecha_vencimiento"], $_POST["fecha_entrega"], $_POST["lugar_entrega"], $_POST["observaciones"]]);

	$id_orden_compra_cliente = $pdo->lastInsertId();
	
	foreach ($_POST['id_tipo_cobertura'] as $item) {
		if ($item != 0) {
			$sql = "INSERT INTO `occ_tipos_cobertura`(`id_tipo_cobertura`,`id_occ`) VALUES (?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$item,$id_orden_compra_cliente]);
		}
	}

	$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva Orden Compra Clientes','Orden Compra Clientes','verOrdenCompraCliente.php?id=$id_orden_compra_cliente')";
	$q = $pdo->prepare($sql);
	$q->execute(array($_SESSION['user']['id']));
  
	$sql = "SELECT valor FROM `parametros` WHERE id = 1 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpHost = $data['valor'];  

	$sql = "SELECT valor FROM `parametros` WHERE id = 2 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpUsuario = $data['valor'];  

	$sql = "SELECT valor FROM `parametros` WHERE id = 3 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpClave = $data['valor'];  

	$sql = "SELECT valor FROM `parametros` WHERE id = 4 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpFrom = $data['valor'];  

	$sql = "SELECT valor FROM `parametros` WHERE id = 5 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpFromName = $data['valor'];  

	$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 6 ";
	foreach ($pdo->query($sql) as $row) {
		
		$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (6,?,now(),0,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$row[0],'ID OCC: #'.$id_orden_compra_cliente,$id_orden_compra_cliente]);
		
		$address = $row[1];
		
		$titulo = "ERP Notificaciones - Módulo Administración - Nueva Orden de Compra de Cliente";
		$mensaje="Nueva orden de compra de cliente dada de alta en el sistema: #".$id_orden_compra_cliente;

		$mail = new PHPMailer();
		$mail->IsSMTP();
		$mail->SMTPAuth = true;
		$mail->Port = 25; 
		$mail->SMTPSecure = 'ssl';
		$mail->SMTPAutoTLS = false;
		$mail->SMTPSecure = false;
		$mail->IsHTML(true); 
		$mail->CharSet = "utf-8";
		$mail->From = $smtpFrom;
		$mail->FromName = $_SESSION['user']['usuario'];
		$mail->Host = $smtpHost; 
		$mail->Username = $smtpUsuario; 
		$mail->Password = $smtpClave;
		$mail->AddAddress($address);
		$mail->Subject = $titulo; 
		$mensajeHtml = nl2br($mensaje);
		$mail->Body = "{$mensajeHtml} <br /><br />"; 
		$mail->AltBody = "{$mensaje} \n\n"; 
		$mail->Send();
	
	}

		

  Database::disconnect();
  header("Location: nuevaOrdenCompraClienteDetalle.php?id_orden_compra_cliente=".$id_orden_compra_cliente);
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      
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
          $ubicacion="Nueva Orden de Compra Clientes";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				          <form class="form theme-form" role="form" id="form1" method="post" action="nuevaOrdenCompraCliente.php" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Emisión(*)</label>
                            <div class="col-sm-9"><input name="fecha_emision" id="fecha_emision" autofocus type="date" onfocus="this.showPicker()" class="form-control" required="required" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Recepción(*)</label>
                            <div class="col-sm-9"><input name="fecha_recepcion" id="fecha_recepcion" type="date"  onfocus="this.showPicker()" class="form-control" required="required" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Número(*)</label>
                            <div class="col-sm-9"><input name="numero" type="text" maxlength="99" class="form-control" required="required" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cliente(*)</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta_cliente" id="id_cuenta_cliente" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (1) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Moneda(*)</label>
                            <div class="col-sm-9">
                              <select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, moneda FROM monedas";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['moneda']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Condición IVA(*)</label>
                            <div class="col-sm-9">
							<select name="id_condicion_iva" id="id_condicion_iva" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option>
								<?php
								$pdo = Database::connect();
								$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
								$sqlZon = "SELECT `id`, `condicion_iva` FROM `condiciones_iva` WHERE 1";
								$q = $pdo->prepare($sqlZon);
								$q->execute();
								while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
									echo "<option value='".$fila['id']."'";
									echo ">".$fila['condicion_iva']."</option>";
								}
								Database::disconnect();
								?>
                              </select>
							</div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Forma de pago(*)</label>
                            <div class="col-sm-9">
                              <select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, forma_pago FROM formas_pago";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['forma_pago']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Presupuesto(*)</label>
                            <div class="col-sm-9">
                              <select name="id_presupuesto" id="id_presupuesto" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT p.id, p.nro_revision,c.nombre,p.descripcion,m.moneda,p.monto from presupuestos p inner join cuentas c on c.id = p.id_cuenta inner join monedas m on m.id = p.id_moneda WHERE p.anulado = 0 ";
								$q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['id'].' / '.$fila['nro_revision'].' - '.$fila['nombre'].' - '.$fila['descripcion'].' - '.$fila['moneda'].number_format($fila['monto'],2)."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Requiere Poliza(*)</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="requiere_polizas_si">
                                <input class="radio_animated" value="1" required id="requiere_polizas_si" type="radio" name="requiere_polizas"><label for="requiere_polizas_si">Si</label>
                              </label>
                              <label class="d-block" for="requiere_polizas_no">
                                <input class="radio_animated" value="0" required id="requiere_polizas_no" type="radio" name="requiere_polizas"><label for="requiere_polizas_no">No</label>
                              </label>
                            </div>
                          </div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipos de Cobertura(*)</label>
                            <div class="col-sm-9">
                              <select name="id_tipo_cobertura[]" id="id_tipo_cobertura[]" multiple="multiple" class="js-example-basic-single col-sm-12" required="required">
                                <option value="0">No Requiere</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, tipo FROM tipos_cobertura_polizas  ";
								$q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  
                                  echo ">".$fila['tipo']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipo OC(*)</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="tipo_oc_abierta">
                                <input class="radio_animated" value="1" required id="tipo_oc_abierta" type="radio" name="tipo_oc"><label for="tipo_oc_abierta">Abierta</label>
                              </label>
                              <label class="d-block" for="tipo_oc_cerrada">
                                <input class="radio_animated" value="0" required id="tipo_oc_cerrada" type="radio" name="tipo_oc"><label for="tipo_oc_cerrada">Cerrada</label>
                              </label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Vencimiento</label>
                            <div class="col-sm-9"><input name="fecha_vencimiento" id="fecha_vencimiento" type="date" onfocus="this.showPicker()" class="form-control" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Entrega(*)</label>
                            <div class="col-sm-9"><input name="fecha_entrega" id="fecha_entrega" type="date" onfocus="this.showPicker()" required class="form-control" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Lugar de Entrega(*)</label>
                            <div class="col-sm-9"><input name="lugar_entrega" type="text" required class="form-control" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                            <div class="col-sm-9"><textarea name="observaciones" name="observaciones" class="form-control"></textarea></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear y agregar Detalle</button>
						            <a href="listarOrdenesCompraClientes.php" class="btn btn-light">Volver</a>
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
              that
              .search( this.value )
              .draw();
            }
          });
        });
      });
      
      </script>
      <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
      <!-- Plugin used-->
	
	    <!-- Page-Level Demo Scripts - Tables - Use for reference -->
		<script>
		$("#form1").submit(function () {
			var startDate1 = document.getElementById("fecha_emision").value;
			var endDate1 = document.getElementById("fecha_recepcion").value;
			var startDate2 = document.getElementById("fecha_vencimiento").value;
			var endDate2 = document.getElementById("fecha_entrega").value;
	
			if ((Date.parse(startDate1) > Date.parse(endDate1))) {
				alert("La fecha de recepcion debe ser mayor a la fecha de emision");
				document.getElementById("fecha_recepcion").value = "";
				event.preventDefault(); // Evita que se envíe el formulario
			}
		
			if ((Date.parse(startDate2) > Date.parse(endDate2))) {
				alert("La fecha de entrega debe ser mayor a la fecha de vencimiento");
				document.getElementById("fecha_entrega").value = "";
				event.preventDefault(); // Evita que se envíe el formulario
			}
		});
		</script>
  </body>
</html>