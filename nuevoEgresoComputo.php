<?php   
    $modoDebug = 0;

    require("config.php");
    if (empty($_SESSION['user'])) {
        if ($modoDebug == 0) {
            header("Location: index.php");
            die("Redirecting to index.php");
        }
    }

    require 'database.php';

    if (!empty($_POST)) {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->beginTransaction();

            $idCuentaRetira = isset($_POST['id_cuenta_retira']) ? (int)$_POST['id_cuenta_retira'] : 0;
            
            if ($idCuentaRetira <= 0) {
                throw new Exception("Debe seleccionar quién retira el material.");
            }
            
            $stmtVerifyCuenta = $pdo->prepare("SELECT id FROM cuentas WHERE id = ? AND activo = 1");
            $stmtVerifyCuenta->execute([$idCuentaRetira]);
            if (!$stmtVerifyCuenta->fetchColumn()) {
                throw new Exception("La cuenta seleccionada no es válida o no está activa.");
            }

            $sql = "SELECT c.`id`, c.`nro_revision`, t.`estructura`, s.nombre, t.id idtarea, s.id idsitio, p.id AS id_proyecto 
                    FROM `computos` c 
                    INNER JOIN tareas t on t.id = c.`id_tarea` 
                    INNER JOIN proyectos p on p.id = t.id_proyecto 
                    INNER JOIN sitios s on s.id = p.id_sitio 
                    WHERE c.`id_estado` <> 6 and c.`id` = ? ";
            $q = $pdo->prepare($sql);
            $q->execute([$_POST["id_computo"]]);
            $data2 = $q->fetch(PDO::FETCH_ASSOC);

            if (!$data2) throw new Exception("No se encontró el cómputo.");

            $sql = "INSERT INTO `egresos`(`fecha_hora`, `id_tipo_egreso`, `nro`, `id_cuenta_retira`, `id_sitio_destino`, `id_tarea`, `id_proyecto`, `observaciones`) 
                    VALUES (now(), 2, ?, ?, ?, ?, ?, ?)";
            $q = $pdo->prepare($sql);		   
            $q->execute([
                $_POST["id_computo"],
                $idCuentaRetira,  // CORRECCIÓN: usar variable verificada
                $data2['idsitio'],
                $data2['idtarea'],
                $data2['id_proyecto'],
                $_POST['observaciones']
            ]);
            $idEgreso = $pdo->lastInsertId();

            $sqlDet = "SELECT d.`id`, m.codigo, m.`concepto`, d.`cantidad`, u.unidad_medida, d.id_material, u.id as id_unidad_medida 
                    FROM `computos_detalle` d 
                    INNER JOIN materiales m on m.id = d.id_material 
                    INNER JOIN unidades_medida u on u.id = m.id_unidad_medida 
                    WHERE d.`cancelado` = 0 and d.`aprobado` = 1 and d.id_computo = ?";
            $qDet = $pdo->prepare($sqlDet);

            $sqlStock = "SELECT id, saldo, id_unidad_medida, saldo 
                        FROM ingresos_detalle 
                        WHERE id_material = ? AND saldo > 0 
                        ORDER BY id ASC";
            $qStock = $pdo->prepare($sqlStock);

            $sqlUpdIngreso = "UPDATE ingresos_detalle 
                            SET saldo = saldo - ? 
                            WHERE id = ?";
            $qUpdIngreso = $pdo->prepare($sqlUpdIngreso);

            $sqlInsEgresoDet = "INSERT INTO egresos_detalle (id_egreso, id_material, id_detalle_ingreso, id_unidad_medida, cantidad, cantidad_reservada, cantidad_efectivizada, precio, subtotal) 
                                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)";
            $qInsEgresoDet = $pdo->prepare($sqlInsEgresoDet);

            $sqlUpdComputo = "UPDATE computos_detalle SET reservado = reservado + ? WHERE id = ?";
            $qUpdComputo = $pdo->prepare($sqlUpdComputo);

            $sqlPrecio = "SELECT cd.precio FROM compras_detalle cd 
                        INNER JOIN compras c on c.id = cd.id_compra 
                        INNER JOIN pedidos p on p.id = c.id_pedido 
                        WHERE cd.id_material = ? AND p.id_computo = ? LIMIT 1"; 
            $qPrecio = $pdo->prepare($sqlPrecio);

            $qDet->execute([$_POST["id_computo"]]);

            while ($row = $qDet->fetch(PDO::FETCH_ASSOC)) {
                $idMaterial = $row['id_material'];
                $cantRequerida = (double)$row['cantidad']; 
                $idComputoDetalle = $row['id'];
                $nombreMaterial = $row['concepto'];
                
                $qUpdComputo->execute([$cantRequerida, $idComputoDetalle]);

                $qPrecio->execute([$idMaterial, $_POST["id_computo"]]);
                $dPrecio = $qPrecio->fetch(PDO::FETCH_ASSOC);
                $precioUnitario = $dPrecio ? $dPrecio['precio'] : 0;

                $qStock->execute([$idMaterial]);
                
                $cantPendiente = $cantRequerida;

                while ($cantPendiente > 0.00001 && $lote = $qStock->fetch(PDO::FETCH_ASSOC)) {
                    $idIngresoDetalle = $lote['id'];
                    $saldoLote = (double)$lote['saldo'];
                    $unidadMedidaLote = $lote['id_unidad_medida'];

                    $tomar = min($cantPendiente, $saldoLote);
                    $subtotal = $tomar * $precioUnitario;

                    $qInsEgresoDet->execute([
                        $idEgreso,
                        $idMaterial,
                        $idIngresoDetalle,
                        $unidadMedidaLote,
                        $tomar,
                        $tomar,
                        $precioUnitario,
                        $subtotal
                    ]);

                    $qUpdIngreso->execute([$tomar, $idIngresoDetalle]);

                    $cantPendiente -= $tomar;
                }

                if ($cantPendiente > 0.00001) {
                    throw new Exception("Stock Insuficiente para el material: '{$nombreMaterial}'. Se requieren {$cantRequerida}, pero solo hay disponible " . ($cantRequerida - $cantPendiente) . ".");
                }
            }
            
            $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nuevo egreso de stock de cómputo','Egresos','verEgreso.php?id=$idEgreso')";
            $q = $pdo->prepare($sql);
            $q->execute(array($_SESSION['user']['id']));
            
            $pdo->commit();
            Database::disconnect();
            header("Location: listarEgresos.php");

        } catch (Exception $e) {
            $pdo->rollBack();
            Database::disconnect();
            $error = $e->getMessage();
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
  <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
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
          $ubicacion="Egreso de Cómputo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><?=$ubicacion?></h5>
                    </div>
                    <?php if (isset($error)) { ?>
                        <div class="card-body" style="padding-bottom: 0px;">
                            <div class="alert alert-danger inverse alert-dismissible fade show" role="alert">
                                <p><b>No se pudo realizar el egreso:</b></p>
                                <p><?php echo $error; ?></p>
                                <button class="close" type="button" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                        </div>
                    <?php } 
                ?>
				<form class="form theme-form" role="form" method="post" action="nuevoEgresoComputo.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cómputo(*)</label>
							<div class="col-sm-9">
							<select name="id_computo" id="id_computo" autofocus class="js-example-basic-single col-sm-12" required="required" onChange="jsListarProductos(this.value);">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT c.`id`, c.`nro_revision`, t.`observaciones`  FROM `computos` c inner join tareas t on t.id = c.`id_tarea` inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE c.`id_estado` in (3,4)";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['id']."-".$fila['nro_revision']."/".$fila['observaciones']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							
							<div class="form-group row">
								<label class="col-sm-3 col-form-label">Detalle</label>
								<div class="col-sm-9">
								  <table class="display" id="dataTables-example666">
									<thead>
									  <tr>
									  <th>Código</th>
									  <th>Concepto</th>
									  <th>Categoría</th>
									  <th>Cantidad Requerida</th>
									  <th>Cantidad Reservada</th>
									  <th>Opciones</th>
									  </tr>
									</thead>
									<tbody>
									</tbody>
								  </table>
								</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Retira(*)</label>
							<div class="col-sm-9">
							<select name="id_cuenta_retira" id="id_cuenta_retira" class="js-example-basic-single col-sm-12" required="required">
							<option value="">Seleccione...</option>
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
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Observaciones</label>
							<div class="col-sm-9"><textarea name="observaciones" class="form-control"></textarea></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Registrar Egreso</button>
						<a href="listarEgresos.php" class="btn btn-light">Volver</a>
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
			$('#dataTables-example666').DataTable({
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
		});
		
		function jsListarProductos(val) { 
		
			$.ajax({
				type: "POST",
				url: "ajaxProductosEgresoComputo.php",
				data: "computo="+val,
				success: function(resp){
					$("#dataTables-example666").html(resp);
				}
			});
		}
		
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
	
	<!-- Page-Level Demo Scripts - Tables - Use for reference -->
   
  </body>
</html>
