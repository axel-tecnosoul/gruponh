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

  if(empty($_POST["cotizacion_dolar"])) $_POST["cotizacion_dolar"]=0;
  
  $id_cuenta_cliente_beneficiario = 0;
  $sql = "SELECT id_cuenta_cliente from occ where id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST["id_occ"]]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_cuenta_cliente_beneficiario = $data['id_cuenta_cliente'];

  $sql = "INSERT INTO polizas (id_occ, numero, fecha_solicitud, id_cuenta_proveedor_aseguradora, id_cuenta_cliente_beneficiario, id_tipo_cobertura, vigencia_desde, vigencia_hasta, monto_garantia, id_moneda, descripcion_objetivo, activa, fecha_renovacion, id_empresa) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST["id_occ"], $_POST["numero"], $_POST["fecha_solicitud"], $_POST["id_cuenta_proveedor_aseguradora"], $id_cuenta_cliente_beneficiario, $_POST["id_tipo_cobertura"], $_POST["vigencia_desde"], $_POST["vigencia_hasta"], $_POST["monto_garantia"], $_POST["id_moneda"], $_POST["descripcion_objetivo"], $_POST["fecha_renovacion"], $_POST["id_empresa"]]);

  $id_poliza = $pdo->lastInsertId();
  /*
  if (!empty($_FILES['adjunto']['name'])) {
	  $filename = $_FILES['adjunto']['name'];
	  move_uploaded_file($_FILES['adjunto']['tmp_name'],'adjuntos_polizas/'.$id_poliza.'_'.$filename);
		
	  $sql = "update `polizas` set adjunto = ? where id = ?";
	  $q = $pdo->prepare($sql);
	  $q->execute(array($id_poliza.'_'.$filename,$id_poliza));
	}	
	*/
	 if (!empty($_POST['adjunto'])) {
	  $sql = "update `polizas` set adjunto = ? where id = ?";
	  $q = $pdo->prepare($sql);
	  $q->execute(array($_POST['adjunto'],$id_poliza));
	}	

  $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva Poliza','Polizas','verPoliza.php?id=$id_poliza')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  Database::disconnect();
  header("Location: listarPolizas.php");
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
          $ubicacion="Nueva Póliza";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				          <form class="form theme-form" role="form" method="post" action="nuevaPoliza.php" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Orden de Compra Cliente(*)</label>
                            <div class="col-sm-9">
                              <select name="id_occ" id="id_occ" class="js-example-basic-single col-sm-12" required="required" autofocus>
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT o.id, o.numero,c.nombre FROM occ o inner join cuentas c on c.id = o.id_cuenta_cliente WHERE o.activa = 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['numero'].' - '.$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
						  <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Empresa(*)</label>
                            <div class="col-sm-9">
                              <select name="id_empresa" id="id_empresa" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, empresa FROM empresas WHERE 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['empresa']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Número(*)</label>
                            <div class="col-sm-9"><input name="numero" type="text" maxlength="99" class="form-control" required="required" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Solicitud(*)</label>
                            <div class="col-sm-9"><input name="fecha_solicitud" type="date" onfocus="this.showPicker()" class="form-control" required="required" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Renovación</label>
                            <div class="col-sm-9"><input name="fecha_renovacion" type="date" onfocus="this.showPicker()" class="form-control" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Aseguradora(*)</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta_proveedor_aseguradora" id="id_cuenta_proveedor_aseguradora" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (5) and activo = 1 and anulado = 0";
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
                            <label class="col-sm-3 col-form-label">Tipo de Cobertura(*)</label>
                            <div class="col-sm-9">
                              <select name="id_tipo_cobertura" id="id_tipo_cobertura" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, tipo FROM tipos_cobertura_polizas";//
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
                            <label class="col-sm-3 col-form-label">Vigencia desde</label>
                            <div class="col-sm-9"><input name="vigencia_desde" id="vigencia_desde" type="date" onfocus="this.showPicker()" class="form-control"  value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Vigencia hasta</label>
                            <div class="col-sm-9"><input name="vigencia_hasta" id="vigencia_hasta" type="date" onfocus="this.showPicker()" class="form-control"  value=""></div>
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
                            <label class="col-sm-3 col-form-label">Monto de la Garantía(*)</label>
                            <div class="col-sm-9"><input name="monto_garantia" type="number" step="0.01" min="0" class="form-control" required="required" value=""></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Objeto del seguro</label>
                            <div class="col-sm-9"><textarea name="descripcion_objetivo" class="form-control"></textarea></div>
                          </div>
						  <!--
						  <div class="form-group row">
								<label class="col-sm-3 col-form-label">Adjunto</label>
								<div class="col-sm-9"><input name="adjunto" type="file" value="" class="form-control"></div>
							</div>
							-->
							
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Adjunto</label>
								<div class="col-sm-9"><input name="adjunto" type="text" value="" class="form-control"></div>
							</div>
                        </div>
						

                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-success" type="submit">Crear</button>
						            <a href="listarPolizas.php" class="btn btn-light">Volver</a>
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
	  <script>
	$("#vigencia_hasta").change(function () {
		var startDate = document.getElementById("vigencia_desde").value;
		var endDate = document.getElementById("vigencia_hasta").value;

		if ((Date.parse(startDate) > Date.parse(endDate))) {
			alert("La fecha de vigencia hasta debe ser mayor a la fecha de vigencia desde");
			document.getElementById("vigencia_hasta").value = "";
		}
	});
	</script>
      <!-- Plugin used-->
	
	    <!-- Page-Level Demo Scripts - Tables - Use for reference -->
   
  </body>
</html>