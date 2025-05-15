<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
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
    <style>
      .faClass{
        width: 24px;
        height: 20px;
        color: midnightblue;
      }
      .editable {
        text-decoration: underline;
        cursor: default;
      }
	  .editable1 {
        text-decoration: underline;
        cursor: default;
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
          $ubicacion="Ordenes de trabajo ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
			<div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarOrdenesTrabajo.php">
					  <div class="form-group mb-0">
						N.Sitio/N.Proy:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro">
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
							$sqlZon = "SELECT `id`, `estado` FROM `estados_orden_trabajo` WHERE 1 order by estado ";
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
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarOrdenesTrabajo.php'">Buscar</button>
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
                    <h5><?php
                      echo $ubicacion; 
                      if (!empty(tienePermiso(315))) { ?>
                        &nbsp;
                        <?php
                      }
                      if (!empty(tienePermiso(316))) {
                        echo '<a href="#" id="link_modificar_ot"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
					  echo '<a href="#" id="link_ver_ot"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
						echo '&nbsp;&nbsp;';
					
					  ?>
					  
                      <a href="#" id="link_nuevo_consumo" title="Nuevo Consumo"><i style="width: 24px; height: 20px;color: midnightblue;" class='fa fa-lg fa-shopping-basket'></i></a>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="tablaOT">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Sitio</th>
                            <th>Subsitio</th>
                            <th>Proy</th>
                            <th>LC</th>
                            <th>Nombre LC</th>
                            <th>N° OT / Revisión</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody><?php 
                          if (!empty($_POST)) {
                          $pdo = Database::connect();
                          $sql = "SELECT otr.id, otr.numero, otr.nro_revision, otr.titulo, lcr.id_lista_corte, lcr.nombre ,p.nombre AS proyecto, date_format(otr.fecha,'%d/%m/%y') AS fecha, e.estado,s.nro_sitio AS sitio,s.nro_subsitio AS subsitio,u.usuario,p.nro nro FROM ordenes_trabajo otr INNER JOIN ordenes_trabajo ot ON ot.id=otr.id_orden_trabajo INNER JOIN listas_corte_revisiones lcr ON otr.id_lista_corte=lcr.id inner join estados_orden_trabajo e on e.id = otr.id_estado_orden_trabajo inner join proyectos p on p.id = lcr.id_proyecto inner join sitios s on s.id = p.id_sitio INNER JOIN usuarios u ON otr.id_usuario=u.id WHERE ot.anulado = 0";
                          
						  if (!empty($_POST['nro'])) {
								$sql .= " and (p.nro = ".$_POST['nro']." or s.nro_sitio = ".$_POST['nro'].") ";
							}
							if (!empty($_POST['fecha'])) {
								$sql .= " AND otr.fecha >= '".$_POST['fecha']."' ";
							}
							if (!empty($_POST['fechah'])) {
								$sql .= " AND otr.fecha <= '".$_POST['fechah']."' ";
							}
							if (!empty($_POST['id_estado'][0])) {
								$sql .= " AND e.id in (".implode(', ',$_POST['id_estado']).") ";
							}
						  
                          foreach ($pdo->query($sql) as $row) {
                            echo '<tr>';
                            echo '<td class="d-none">'.$row["id"].'</td>';
                            if (empty($row["subsitio"])) {
                              echo '<td>'.$row["sitio"].'</td>';
                              echo '<td>0</td>';
                            } else {
                              echo '<td>'.$row["subsitio"].'</td>';
                              echo '<td>'.$row["sitio"].'</td>';
                            }
                            echo '<td>'.$row["nro"].'</td>';
                            echo '<td>'.$row["id_lista_corte"].'</td>';
                            echo '<td>'.$row["nombre"].'</td>';
                            echo '<td>'.$row["numero"].' / '.$row["nro_revision"]. '</td>';
                            echo '<td>'.$row["fecha"].'</td>';
                            echo '<td>'.$row["usuario"].'</td>';
                            echo '<td>'. $row["estado"] . '</td>';
                            echo '</tr>';?>

                            <div class="modal fade" id="eliminarModal_<?=$row["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                              <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                                    <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                  </div>
                                  <div class="modal-body">¿Está seguro que desea cancelar la Lista de Corte?</div>
                                  <div class="modal-footer">
                                    <a href="eliminarOrdenTrabajo.php?id=<?=$row["id"]; ?>" class="btn btn-primary">Eliminar</a>
                                    <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
                                  </div>
                                </div>
                              </div>
                            </div><?php
                          }
                          Database::disconnect();
						  }
						  ?>
                        </tbody>
						            <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Sitio</th>
                            <th>Subsitio</th>
                            <th>Proy</th>
                            <th>LC</th>
                            <th>Nombre LC</th>
                            <th>N° OT / Revisión</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Estado</th>
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
			      <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Detalle de Orden de Trabajo
                      &nbsp;&nbsp;
                      <span id="btnAbrirModalModificarCantidades" title="Modificar Cantidades" style="cursor: pointer;"><i class='faClass fa fa-lg fa-cogs'></i></span>&nbsp;&nbsp;
					  <span id="btnDetalle" title="Ver Historial" style="cursor: pointer;"><i class='faClass fa fa-lg fa-eye'></i></span>&nbsp;&nbsp;
                      <?php
                      
                      ?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="tablaDetalleOT">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Conjunto</th>
                            <th>Cantidad Conjuntos</th>
                            <th>Posicion</th>
                            <th>Cantidad Pedida</th>
                            <th>Material</th>
                            <th>Procesos</th>
                            <th>Estado</th>
                            <th>Liberados</th>
                            <th>Reproceso</th>
                            <th>Rechazados</th>
							<th>F.Revisión</th>
							<th>Usuario</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Conjunto</th>
                            <th>Cantidad Conjuntos</th>
                            <th>Posicion</th>
                            <th>Cantidad Pedida</th>
                            <th>Material</th>
                            <th>Procesos</th>
                            <th>Estado</th>
                            <th>Liberados</th>
                            <th>Reproceso</th>
                            <th>Rechazados</th>
							<th>F.Revisión</th>
							<th>Usuario</th>
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
	
	<div style="width: 0;height: 0;display: none;">
      <select id="select_estadoOT_base"><?php
        $pdo = Database::connect();
        $sql = "SELECT id,estado FROM estados_orden_trabajo";
        foreach ($pdo->query($sql) as $row) {
          echo '<option value="'.$row["id"].'">'.$row["estado"].'</option>';
        }
        Database::disconnect();?>
      </select>
    </div>

    <div style="width: 0;height: 0;display: none;">
      <select id="select_estado_base"><?php
        $pdo = Database::connect();
        $sql = "SELECT id,estado FROM estados_orden_trabajo_posicion";
        foreach ($pdo->query($sql) as $row) {
          echo '<option value="'.$row["id"].'">'.$row["estado"].'</option>';
        }
        Database::disconnect();?>
      </select>
    </div>
    
    <div class="modal fade" id="modificarCantidades" tabindex="-1" role="dialog" aria-labelledby="exampleModalModificarCantidades" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form id="formModificarCantidades" action="modificarCantidadesPosicionesOT.php" method="post">
            <div class="modal-header">
              <h5 class="modal-title" id="exampleModalModificarCantidades">Ingrese las cantidad (Max. <span id="cantMaxima"></span>)</h5>
              <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
              <div class="form-group row">
                <input type="hidden" name="id_posicion_ot" id="id_posicion_ot">
                <label class="col-sm-3 col-form-label">Reproceso</label>
                <div class="col-sm-9"><input name="enProceso" type="number" class="form-control"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Rechazados</label>
                <div class="col-sm-9"><input name="rechazadas" type="number" class="form-control"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Liberados</label>
                <div class="col-sm-9"><input name="liberadas" type="number" class="form-control"></div>
              </div>
			  <div class="form-group row">
                <label class="col-sm-3 col-form-label">Motivo</label>
                <div class="col-sm-9"><input name="motivo" type="text" class="form-control"></div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-primary" type="button" id="btnModificarCantidades">Modificar</button>
              <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
            </div>
          </form>
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
        $('#tablaOT tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $('#tablaOT').DataTable({
          stateSave: false,
		  searching: false,
		  
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
          },
		  "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                $('td:eq(9)', nRow).addClass("editable1").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[4]).attr("title","Doble click para editar");
              },
              initComplete: function(){
                $('[title]').tooltip();
              }
        });
    
        // DataTable
        var table = $('#tablaOT').DataTable();
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });
        
        //$('#tablaOT').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaOT tbody tr td", function(){
          var t=$(this).parent();

          let id_ot=t.find("td:first-child").html();
		  let estado = t.find("td:nth-child(10)").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            get_detalle_orden_trabajo(0)

            $("#link_modificar_ot").attr("href","#");
			$("#link_ver_ot").attr("href","#");
            $("#link_nuevo_consumo").attr("href","#");
          }else{
            //t.parent().find("tr").removeClass("selected");
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_detalle_orden_trabajo(id_ot)
            if ((estado == "Elaborando") || (estado == "Pendiente")) {
				$("#link_modificar_ot").attr("href","modificarOrdenTrabajo.php?id="+id_ot);	
			} else {
				$("#link_modificar_ot").attr("href","#");
			}
			if (estado == "En Producción") {
				$("#link_nuevo_consumo").attr("href","nuevoConsumo.php?id_orden_trabajo="+id_ot);
			} else {
				$("#link_nuevo_consumo").attr("href","#");
			}
            
			$("#link_ver_ot").attr("href","verOrdenTrabajo.php?id="+id_ot);
            
          }
        });

        get_detalle_orden_trabajo(0)
        $('#tablaDetalleOT tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $("#link_modificar_ot").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de trabajo para modificarla")
          }
        })
		$("#link_ver_ot").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de trabajo para ver detalle")
          }
        })
        $("#link_cancelar_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de trabajo para eliminarla")
          }
        })

        $("#link_nuevo_consumo").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de trabajo para agregar consumos")
          }
        })

        $("#btnAbrirModalModificarCantidades").on("click",function(){
          let id_conjunto=$(this).data("id")
          if(id_conjunto!="" && id_conjunto>0){
            let modal=$("#modificarCantidades")
            modal.modal("show")
          }else{
            alert("Por favor seleccione una posicion para modificar las cantidades")
          }
        });
		
		$("#btnDetalle").on("click",function(e){
          let id_conjunto=$(this).data("id")
          if(id_conjunto!="" && id_conjunto>0){
			  window.location.href="verHistorialOT.php?id="+id_conjunto
          }else{
            alert("Por favor seleccione una posicion para ver el historial")
          }
        });
		
		
		$("#btnModificarCantidades").on("click",function(e){
          e.preventDefault();
          let form=$("#formModificarCantidades");
          let enProceso=parseInt(form.find("input[name='enProceso']").val());
          let rechazadas=parseInt(form.find("input[name='rechazadas']").val());
          let liberadas=parseInt(form.find("input[name='liberadas']").val());
          let cantMaxima=parseInt($("#cantMaxima").html());

          if((enProceso+rechazadas+liberadas)>cantMaxima){
            alert("La suma de los 3 campos no puede superar la cantida maxima ("+cantMaxima+")")
          }else{
            console.log("submit");
            form.submit();
          }
        });

        $("body").on('dblclick',".editable", function(event) {
          var t=$(this);
          
          let old_padding=t.css("padding");
          t.css({padding: '0'});
          t.find('input[type="hidden"]');
          
          var idPosicion=t.data("idPosicion");
		  console.log(idPosicion);
          var idEstado=t.data("idEstado");
          console.log(idEstado);

          dataString="idPosicion="+idPosicion;

          let nuevo_select_estado=$("#select_estado_base").clone()
          nuevo_select_estado.id="id_estado_nuevo"

          t.html(nuevo_select_estado);
          nuevo_select_estado.val(idEstado)

          nuevo_select_estado.on('blur', function(event) {
            nuevaEstado=nuevo_select_estado.val();
            // Obtener el texto correspondiente al valor seleccionado
            var textoSeleccionado = nuevo_select_estado.find('option[value="'+nuevaEstado+'"]').text();

            $.ajax({
              type: "POST",
              url: "modificarEstadoPosicionOT.php",
              data: "idPosicion="+idPosicion+"&idEstado="+nuevaEstado,
              success: function(data) {
                //console.log(data)
                if(data==1){
                  t.css({padding: old_padding});
                  t.html(textoSeleccionado)
                }
              }
            });
          });
        });
		
		$("body").on('dblclick',".editable1", function(event) {
          var t=$(this);
          
          let old_padding=t.css("padding");
          t.css({padding: '0'});
          t.find('input[type="hidden"]');
          
          var idPosicion=t.data("idPosicion");
		  console.log(idPosicion);
          var idEstado=t.data("idEstado");
          console.log(idEstado);

          dataString="idPosicion="+idPosicion;

          let nuevo_select_estado=$("#select_estadoOT_base").clone()
          nuevo_select_estado.id="id_estado_nuevo"

          t.html(nuevo_select_estado);
          nuevo_select_estado.val(idEstado)

          nuevo_select_estado.on('blur', function(event) {
            nuevaEstado=nuevo_select_estado.val();
            // Obtener el texto correspondiente al valor seleccionado
            var textoSeleccionado = nuevo_select_estado.find('option[value="'+nuevaEstado+'"]').text();

            $.ajax({
              type: "POST",
              url: "modificarEstadoOT.php",
              data: "idPosicion="+idPosicion+"&idEstado="+nuevaEstado,
              success: function(data) {
                //console.log(data)
                if(data==1){
                  t.css({padding: old_padding});
                  t.html(textoSeleccionado)
                }
              }
            });
          });
        });
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    
      function get_detalle_orden_trabajo(id_ot){
        let datosUpdate = new FormData();
        datosUpdate.append('id_ot', id_ot);
        $.ajax({
          data: datosUpdate,
          url: 'get_detalle_orden_trabajo.php',
          method: "post",
          cache: false,
          contentType: false,
          processData: false,
          
          success: function(data){
            data = JSON.parse(data);
          
            $('#tablaDetalleOT').DataTable().destroy();
            $('#tablaDetalleOT').DataTable({
              stateSave: false,
              responsive: false,
			  "columnDefs": [
				{
				  "targets": [0],
				  "className": 'd-none'
				}
			  ],
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
              },
              "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                console.log(nRow);
                console.log(aData);
                $('td:eq(7)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[10]).attr("title","Doble click para editar");
              },
              initComplete: function(){
                $('[title]').tooltip();
              }
            });
        
            // DataTable
            var table = $('#tablaDetalleOT').DataTable();
            // Apply the search
            table.columns().every( function () {
              var that = this;
              $( 'input', this.footer() ).on( 'keyup change', function () {
                if ( that.search() !== this.value ) {
                  that.search( this.value ).draw();
                }
              });
            });
        
            $('#tablaDetalleOT').find("tbody tr td").not(":last-child").on( 'click', function () {
            //$(document).on("click","#tablaDetalleOT tbody tr td", function(){
              var t=$(this).parent();
              //t.parent().find("tr").removeClass("selected");

              let id_pos_ot=t.find("td:first-child").html();
              let cantMaxima=t.find("td:nth-child(5)").html();
              if(t.hasClass('selected')){
                deselectRow(t);
                $("#btnAbrirModalModificarCantidades").data("id","");
				$("#btnDetalle").data("id","");
                $("#cantMaxima").html("")
                $("#id_posicion_ot").val("")
              }else{
                table.rows().nodes().each( function (rowNode, index) {
                  $(rowNode).removeClass("selected");
                });
                selectRow(t);
                $("#btnAbrirModalModificarCantidades").data("id",id_pos_ot);
				$("#btnDetalle").data("id",id_pos_ot);
                $("#cantMaxima").html(cantMaxima)
                $("#id_posicion_ot").val(id_pos_ot)
              }
            });
          }
        });
      }
    </script>
	<script src="assets/js/select2/select2.full.min.js"></script>
<script src="assets/js/select2/select2-custom.js"></script>

    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>