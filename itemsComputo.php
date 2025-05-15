<?php
    require("config.php");
	require("PHPMailer/class.phpmailer.php");
	require("PHPMailer/class.smtp.php");
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
        header("Location: listarComputos.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$nro = $_POST['nro_revision'];
		
		$sql = "select id_material FROM `computos_detalle` where cancelado = 0 and `id_computo` = ? and `id_material` = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$id,$_POST['id_material']]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		if (empty($data)) {
			if ($_GET['modo'] == "nuevo") {
				$sql = "INSERT INTO `computos_detalle`(`id_computo`, `id_material`, `cantidad`, `fecha_necesidad`, `aprobado`,comentarios) VALUES (?,?,?,?,0,?)";
				$q = $pdo->prepare($sql);
				$q->execute([$id,$_POST['id_material'],$_POST['cantidad'],$_POST['fecha_necesidad'],$_POST['comentarios']]);
				
				if (!empty($_POST['btn2'])) {
					$sql = "update `computos` set id_estado = 2 where id = ?";
					$q = $pdo->prepare($sql);
					$q->execute([$id]);
				}
				
			} else if ($_GET['modo'] == "update") {
				
				$sql = "update `computos` set `id_estado` = 7 where id = ?";
				$q = $pdo->prepare($sql);
				$q->execute([$id]);
				
				$sql = "select id,id_tarea,fecha,id_cuenta_solicitante,nro,nro_computo FROM `computos` where id = ?";
				$q = $pdo->prepare($sql);
				$q->execute([$id]);
				$dataC = $q->fetch(PDO::FETCH_ASSOC);
					
				$estadoComputo = 1;
				if (!empty($_POST['btn2'])) {
					
					$nro = $_POST['nro_revision']+1;
					
					$sql = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
					$q = $pdo->prepare($sql);
					$q->execute([$_SESSION['user']['id']]);
					$data = $q->fetch(PDO::FETCH_ASSOC);
					if (!empty($data)) {
						$idCuentaRealizo = $data['id'];
					}

					$estadoComputo = 2;
					$sql = "insert into `computos` (`nro_revision`, `id_tarea`, `fecha`, `id_cuenta_solicitante`, `id_estado`, `nro_computo`, `comentarios_revision`, `fecha_hora_revision`,nro,`id_cuenta_realizo`, `id_cuenta_reviso`, `id_cuenta_valido`) values (?,?,?,?,?,?,?,now(),?,?,?,?)";
					$q = $pdo->prepare($sql);
					$q->execute([$nro,$dataC['id_tarea'],$dataC['fecha'],$dataC['id_cuenta_solicitante'],$estadoComputo,$dataC['nro_computo'],$_POST['observaciones'],$dataC['nro'],$idCuentaRealizo,$_POST['id_cuenta_reviso'],$_POST['id_cuenta_valido']]);
					
					$idNew = $pdo->lastInsertId();
					
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
					
					$sql = "select id_proyecto from tareas where id = ? ";
					$q = $pdo->prepare($sql);
					$q->execute([$dataC['id_tarea']]);
					$data = $q->fetch(PDO::FETCH_ASSOC);
					$idProyecto = $data['id_proyecto'];
					
					$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.id = ? ";
					$q = $pdo->prepare($sql);
					$q->execute([$idProyecto]);
					$data = $q->fetch(PDO::FETCH_ASSOC);
					$descripcionProyecto = $data['nro_sitio']." - ".$data['nro_subsitio']." - ".$data['nro']." - ".$data['nombre'];
					
					$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 8 ";
					foreach ($pdo->query($sql) as $row) {
						
						$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (8,?,now(),0,?,?)";
						$q = $pdo->prepare($sql);
						$q->execute([$row[0],'ID Computo: #'.$idNew,$idNew]);
						
						$address = $row[1];
						
						$titulo = "ERP Notificaciones - Módulo Producción - Revisión Cómputo (".$descripcionProyecto.")";
						$mensaje="Revisión de cómputo en el sistema para aprobar: #".$descripcionProyecto;
						
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
					
					$sqlList = " SELECT `id_material`, `cantidad`, `fecha_necesidad`, `aprobado`, `reservado`, `comprado`, `cancelado`,comentarios FROM `computos_detalle` WHERE cancelado = 0 and `id_computo` = ".$id;
					foreach ($pdo->query($sqlList) as $row) {
						$sql = "INSERT INTO `computos_detalle`(`id_computo`, `id_material`, `cantidad`, `fecha_necesidad`, `aprobado`, `reservado`, `comprado`, `cancelado`, comentarios) VALUES (?,?,?,?,?,?,?,?,?)";
						$q = $pdo->prepare($sql);
						$q->execute([$idNew,$row[0],$row[1],$row[2],$row[3],$row[4],$row[5],$row[6],$row[7]]);
					}
					
					$sql = "INSERT INTO `computos_detalle`(`id_computo`, `id_material`, `cantidad`, `fecha_necesidad`, `aprobado`, `comentarios`) VALUES (?,?,?,?,0,?)";
					$q = $pdo->prepare($sql);
					$q->execute([$idNew,$_POST['id_material'],$_POST['cantidad'],$_POST['fecha_necesidad'],$_POST['comentarios']]);
				} else {

					$sql = "INSERT INTO `computos_detalle`(`id_computo`, `id_material`, `cantidad`, `fecha_necesidad`, `aprobado`, `comentarios`) VALUES (?,?,?,?,0,?)";
					$q = $pdo->prepare($sql);
					$q->execute([$id,$_POST['id_material'],$_POST['cantidad'],$_POST['fecha_necesidad'],$_POST['comentarios']]);
					
				}
			
				
			}
			
			$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Se ha modificado un item de un cómputo','Cómputos','verComputo.php?id=$idNew')";
			$q = $pdo->prepare($sql);
			$q->execute(array($_SESSION['user']['id']));
			
			Database::disconnect();
			if (!empty($_POST['btn2'])) {
				
				header("Location: listarComputos.php");	
			} else {
				
				header("Location: itemsComputo.php?modo=".$_GET['modo']."&id=".$id."&revision=".$nro);	
			}
		} else {
			header("Location: itemsComputo.php?modo=".$_GET['modo']."&id=".$id."&revision=".$nro."&error=1");	
		}
    }
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const form = document.getElementById("miFormulario");
            const btn1 = document.getElementById("btn1");
            const btn2 = document.getElementById("btn2");
            const observaciones = document.getElementById("observaciones");
			const id_cuenta_valido = document.getElementById("id_cuenta_valido");
			const id_cuenta_reviso = document.getElementById("id_cuenta_reviso");

            // Al presionar el botón, ajustamos el atributo "required" dinámicamente
            btn1.addEventListener("click", function () {
                observaciones.removeAttribute("required");
				id_cuenta_valido.removeAttribute("required");
				id_cuenta_reviso.removeAttribute("required");
				
				
            });

            btn2.addEventListener("click", function () {
                observaciones.setAttribute("required", "required");
				id_cuenta_valido.setAttribute("required", "required");
				id_cuenta_reviso.setAttribute("required", "required");
            });

            // Validación general antes de enviar
            form.addEventListener("submit", function (event) {
                if (btn2.getAttribute("clicked") === "true" && !observaciones.value.trim()) {
                    event.preventDefault();
                    alert("Los datos de la revisión son obligatorios");
                }
            });

            // Marcar qué botón fue presionado
            btn1.addEventListener("click", () => btn2.removeAttribute("clicked"));
            btn2.addEventListener("click", () => btn2.setAttribute("clicked", "true"));
        });
    </script>
	<style>
        .input-group {
            display: flex;
            align-items: center;
        }
        .input-group input {
            width: 100px;
        }
        .input-group button {
            margin-left: 10px;
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
          $ubicacion="Ver/Añadir Items Cómputo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				  <form class="form theme-form" role="form" method="post" id="miFormulario" action="itemsComputo.php?modo=<?php echo $_GET['modo']?>&id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							
							<div class="form-group row">
							<div class="col-sm-12">
							<table class="display" id="dataTables-example667">
								<thead>
								  <tr>
									  <th>Concepto</th>
									  <th>Cantidad</th>
									  <th>Fecha Necesidad</th>
									  <th>Aprobado</th>
									  <th>Comentarios</th>
									  <th>Opciones</th>
								  </tr>
								</thead>
								<tbody>
								  <?php
									$pdo = Database::connect();
									$sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, date_format(d.`fecha_necesidad`,'%d/%m/%y'), d.`aprobado`, d.`comentarios`, date_format(d.`fecha_necesidad`,'%y%m%d') FROM `computos_detalle` d inner join materiales m on m.id = d.id_material WHERE cancelado = 0 and d.id_computo = ".$_GET['id'];
									
									foreach ($pdo->query($sql) as $row) {
										echo '<tr>';
										echo '<td>'. $row[1] . '</td>';
										echo '<td>'. $row[2] . '</td>';
										echo '<td><span style="display: none;">'. $row[6] . '</span>'. $row[3] . '</td>';
										if ($row[4] == 1) {
											echo '<td>Si</td>';	
										} else {
											echo '<td>No</td>';	
										}
										echo '<td>'. $row[5] . '</td>';
										echo '<td>';
										if (!empty(tienePermiso(291))) {
											echo '<a href="modificarItemComputo.php?id='.$row[0].'&idRetorno='.$_GET['id'].'&modo='.$_GET['modo'].'&revision='.$_GET['revision'].'"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
											echo '&nbsp;&nbsp;';
											echo '<a href="#" data-toggle="modal" data-target="#eliminarModal_'.$row[0].'"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Cancelar" title="Cancelar"></a>';
											echo '&nbsp;&nbsp;';
										}
										echo '</td>';
										echo '</tr>';
									}
								   Database::disconnect();
								  ?>
								</tbody>
								<tfoot>
								  <tr>
									  <th>Concepto</th>
									  <th>Cantidad</th>
									  <th>Fecha Necesidad</th>
									  <th>Aprobado</th>
									  <th>Comentarios</th>
									  <th>Opciones</th>
								  </tr>
								</tfoot>
							  </table>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Concepto(*)</label>
							<div class="col-sm-9">
							<select name="id_material" id="id_material" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `concepto`, `codigo` FROM `materiales` WHERE anulado = 0 ";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['concepto']." (".$fila['codigo'].")</option>";
							}
							Database::disconnect();
							?>
							</select>
							<?php if (isset($_GET['error'])) { ?>
							<div class="checkbox p-0">
							  <?php print("<b><font color='red'>No se puede agregar un concepto repetido!</font></b>");  ?>
							</div>
							<?php } ?>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" step="0.01" min="0.01" type="number" class="form-control" required="required" value=""></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Fecha Necesidad(*)</label>
							<div class="col-sm-9"><input name="fecha_necesidad" type="date" onfocus="this.showPicker()" class="form-control" required="required" value=""></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Comentarios</label>
							<div class="col-sm-9"><textarea name="comentarios" class="form-control"></textarea></div>
							<input type="hidden" name="nro_revision" value="<?php if (!empty($_GET['revision'])) { echo $_GET['revision']; }else { echo "0"; } ?>">
							<input type="hidden" name="modo" value="<?php echo $_GET['modo']; ?>">
							</div>
							<hr>
							<?php if ($_GET['modo']=="update") { ?>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Responsables de la revisión</label>
							<div class="col-sm-9">
							<div class="input-group">
							<select name="id_cuenta_reviso" id="id_cuenta_reviso" class="form-control col-sm-12" required="required">
							<option value="">Cuenta Revisó...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
								&nbsp;-&nbsp;
							<select name="id_cuenta_valido" id="id_cuenta_valido" class="form-control col-sm-12" required="required">
							<option value="">Cuenta Aprobó...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones de la revisión</label>
							<div class="col-sm-9"><textarea name="observaciones" id="observaciones" class="form-control" required="required"></textarea></div>
							</div>
							<?php } ?>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-success" type="submit" value="1" name="btn1" id="btn1">Crear y Agregar Otro</button>
						<button class="btn btn-primary" type="submit" value="2" name="btn2" id="btn2">Crear y Enviar a Aprobación</button>
						<a onclick="document.location.href='listarComputos.php'" class="btn btn-danger">Volver al Listado</a>
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
	<?php
    $pdo = Database::connect();
    $sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, date_format(d.`fecha_necesidad`,'%d/%m/%y'), d.`aprobado`,d.id_computo FROM `computos_detalle` d inner join materiales m on m.id = d.id_material WHERE d.id_computo = ".$_GET['id'];
	foreach ($pdo->query($sql) as $row) {
    ?>
	  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
		<div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
		  <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
		  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		  </div>
		  <div class="modal-body">¿Está seguro que desea cancelar el ítem del cómputo?</div>
		  <div class="modal-footer">
		  <a href="eliminarItemComputo.php?id=<?php echo $row[0]; ?>&idComputo=<?php echo $row[5]; ?>&revision=<?php if (!empty($_GET['revision'])) { echo $_GET['revision']; }else { echo "0"; } ?>" class="btn btn-primary">Eliminar</a>
		  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
		  </div>
		</div>
		</div>
	  </div>
	<?php
	}
	Database::disconnect();
	?>
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