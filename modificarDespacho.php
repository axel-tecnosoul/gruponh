<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';

    $esUsuarioDesarrollador = (int)($_SESSION['user']['id'] ?? 0) === 1;

    $id = null;
    if (!empty($_GET['id'])) {
        $id = $_REQUEST['id'];
    }
    
    if (null==$id) {
        header("Location: listarDespachos.php");
    }
    
    if (!empty($_POST)) {
		
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $idProyecto = filter_var($_POST['id_proyecto'] ?? null, FILTER_VALIDATE_INT);
        $idsOcc = array_values(array_unique(array_filter(
            array_map('intval', (array)($_POST['id_occ'] ?? [])),
            static function ($idOcc) { return $idOcc > 0; }
        )));
        if (!$idProyecto || $idProyecto < 1 || !$idsOcc) {
            Database::disconnect();
            http_response_code(422);
            exit('Seleccione un proyecto y al menos una OCC válida.');
        }

        $placeholdersOcc = implode(',', array_fill(0, count($idsOcc), '?'));
        $qOcc = $pdo->prepare(
            "SELECT o.id, o.numero
             FROM occ o
             INNER JOIN proyectos p ON p.id_cliente = o.id_cuenta_cliente
             WHERE p.id = ? AND o.id IN ($placeholdersOcc)"
        );
        $qOcc->execute(array_merge([$idProyecto], $idsOcc));
        $occPorId = [];
        foreach ($qOcc->fetchAll(PDO::FETCH_ASSOC) as $occ) {
            $occPorId[(int)$occ['id']] = $occ['numero'];
        }
        if (count($occPorId) !== count($idsOcc)) {
            Database::disconnect();
            http_response_code(422);
            exit('Una o más OCC no corresponden al proyecto seleccionado.');
        }

        $pdo->beginTransaction();
        $qEstadoActual = $pdo->prepare("SELECT id_estado_despacho FROM despachos WHERE id = ? FOR UPDATE");
        $qEstadoActual->execute([$id]);
        if ((int)$qEstadoActual->fetchColumn() !== 1) {
            $pdo->rollBack();
            Database::disconnect();
            header("Location: verDespacho.php?id=" . (int)$id);
            exit;
        }
        $sql = "UPDATE `despachos` set `nro_remito` = ?, `transporte` = ?, `chofer` = ?, `id_proyecto` = ?, `detalle_oc` = ? where id = ? AND id_estado_despacho = 1";
        $q = $pdo->prepare($sql);
        // detalle_oc conserva la primera OCC para compatibilidad con registros legados.
        $q->execute([$_POST['nro_remito'],$_POST['transporte'],$_POST['chofer'],$idProyecto,$occPorId[$idsOcc[0]],$id]);

        $pdo->prepare("DELETE FROM despachos_occ WHERE id_despacho = ?")->execute([$id]);
        $qRelacion = $pdo->prepare("INSERT INTO despachos_occ (id_despacho, id_occ) VALUES (?, ?)");
        foreach ($idsOcc as $idOcc) {
            $qRelacion->execute([$id, $idOcc]);
        }
        $pdo->commit();
		
        $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de despacho','Despachos','verDespacho.php?id=$id')";
        $q = $pdo->prepare($sql);
        $q->execute(array($_SESSION['user']['id']));
        
        Database::disconnect();
		
		    //header("Location: listarDespachos.php");
        header("Location: nuevoBultoDespacho.php?id_despacho=".$id);
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_cuenta_operario`, `fecha`, `nro_remito`, `transporte`, `chofer`, `id_estado_despacho`, `id_proyecto`, `detalle_oc` FROM `despachos` WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        if (!$data || (int)$data['id_estado_despacho'] !== 1) {
            Database::disconnect();
            header("Location: verDespacho.php?id=" . (int)$id);
            exit;
        }

        $qOccSeleccionadas = $pdo->prepare("SELECT id_occ FROM despachos_occ WHERE id_despacho = ? ORDER BY id_occ");
        $qOccSeleccionadas->execute([$id]);
        $occIdsSeleccionadas = array_map('intval', $qOccSeleccionadas->fetchAll(PDO::FETCH_COLUMN));
        if (!$occIdsSeleccionadas && !empty($data['detalle_oc'])) {
            // Compatibilidad mientras los despachos históricos no tengan relación migrada.
            $qOccLegacy = $pdo->prepare(
                "SELECT o.id
                 FROM occ o
                 INNER JOIN proyectos p ON p.id_cliente = o.id_cuenta_cliente
                 WHERE p.id = ? AND o.numero = ?
                 GROUP BY o.numero
                 HAVING COUNT(*) = 1"
            );
            $qOccLegacy->execute([$data['id_proyecto'], $data['detalle_oc']]);
            $occIdsSeleccionadas = array_map('intval', $qOccLegacy->fetchAll(PDO::FETCH_COLUMN));
        }

        $sqlOcc = $esUsuarioDesarrollador
            ? "SELECT o.id, o.numero, o.monto, o.fecha_entrega, o.abierta, m.moneda
               FROM occ o
               INNER JOIN proyectos p ON p.id_cliente = o.id_cuenta_cliente
               INNER JOIN monedas m ON m.id = o.id_moneda
               WHERE p.id = ? AND (o.activa = 1"
            : "SELECT o.id, o.numero
               FROM occ o
               INNER JOIN proyectos p ON p.id_cliente = o.id_cuenta_cliente
               WHERE p.id = ? AND (o.activa = 1";
        $paramsOcc = [$data['id_proyecto']];
        if ($occIdsSeleccionadas) {
            $sqlOcc .= " OR o.id IN (" . implode(',', array_fill(0, count($occIdsSeleccionadas), '?')) . ")";
            array_push($paramsOcc, ...$occIdsSeleccionadas);
        }
        $sqlOcc .= ") ORDER BY o.numero";
        $qOcc = $pdo->prepare($sqlOcc);
        $qOcc->execute($paramsOcc);
        $occDisponibles = $qOcc->fetchAll(PDO::FETCH_ASSOC);
        
        Database::disconnect();
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
          $ubicacion="Modificar Despacho";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" action="modificarDespacho.php?id=<?php echo $id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Proyecto(*)</label>
							<div class="col-sm-9">
                            <select name="id_proyecto" id="id_proyecto" autofocus class="js-example-basic-single col-sm-12" required="required" onChange="jsListarOCs(this.value);">
							<option value="">Seleccione...</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "select p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
							$q = $pdo->prepare($sqlZon);
							$q->execute();
							while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='".$fila['id']."'";
								if ($fila['id'] == $data['id_proyecto']) {
                                        echo " selected ";
								}
								echo ">".$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']."</option>";
							}
							Database::disconnect();
							?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">OCC(*)</label>
							<div class="col-sm-9">
							<select name="id_occ[]" id="detalle_oc" class="js-example-basic-multiple col-sm-12" multiple="multiple" data-placeholder="Seleccione una o más OCC" required="required">
							<?php foreach ($occDisponibles as $occ):
								$etiquetaOcc = (string)$occ['numero'];
								if ($esUsuarioDesarrollador) {
									$fechaEntregaOcc = (string)$occ['fecha_entrega'];
									$fechaEntregaOcc = $fechaEntregaOcc !== '' && $fechaEntregaOcc !== '0000-00-00'
										? date('d/m/Y', strtotime($fechaEntregaOcc))
										: 'sin fecha';
									$estadoOcc = (int)$occ['abierta'] === 1 ? 'Abierta' : 'Cerrada';
									$etiquetaOcc = 'OCC ' . $occ['numero']
										. ' · ' . $occ['moneda'] . ' ' . number_format((float)$occ['monto'], 2, ',', '.')
										. ' · Entrega: ' . $fechaEntregaOcc . ' · ' . $estadoOcc;
								}
				?>
								<option value="<?=(int)$occ['id']?>" <?=in_array((int)$occ['id'], $occIdsSeleccionadas, true) ? 'selected' : ''?>><?=htmlspecialchars($etiquetaOcc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></option>
							<?php endforeach; ?>
							</select>
							</div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Nro. Remito(*)</label>
							<div class="col-sm-9"><input name="nro_remito" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['nro_remito']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Transporte(*)</label>
							<div class="col-sm-9"><input name="transporte" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['transporte']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Chofer(*)</label>
							<div class="col-sm-9"><input name="chofer" type="text" maxlength="99" class="form-control" required="required" value="<?php echo $data['chofer']; ?>"></div>
							</div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Estado</label>
							<div class="col-sm-9"><div class="form-control-plaintext">Pendiente</div></div>
							</div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
					    <button class="btn btn-primary" type="submit">Modificar</button>
                        <a href="#" onclick="document.location.href='listarDespachos.php'" class="btn btn-light">Volver</a>
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
		function jsListarOCs(val) {
			$.ajax({
				type: "GET",
				url: "ajaxOCC.php",
				data: {id_proyecto: val},
				success: function(resp) {
					$("#detalle_oc").html(resp).trigger('change');
				}
			});
		}

		$(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example666 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
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
