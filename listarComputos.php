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
          $ubicacion="Cómputos ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarComputos.php">
					  <div class="form-group mb-0">
						N.Sitio/N.Proy:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro" id="nro">
					  </div>
					  <div class="form-group mb-0">
						Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fecha'])) echo $_POST['fecha'] ?>" name="fecha" id="fecha">-<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fechah'])) echo $_POST['fechah'] ?>" name="fechah" id="fechah">
					  </div>
					  <div class="form-group mb-0">
						Estado:&nbsp;
						<select name="id_estado[]" id="id_estado" class="js-example-basic-multiple" multiple="multiple">
							<option value="">Todos</option>
							<?php
							$pdo = Database::connect();
							$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon = "SELECT `id`, `estado` FROM `estados_computos` WHERE 1 order by estado ";
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
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarComputos.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(290))) { ?><!--<a href="nuevoComputo.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a>--><?php } ?>
					<a href="#" onclick="jsExportar();"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
					&nbsp;&nbsp;<?php 
					echo '<a href="#" id="link_ver_computo"><img src="img/medalla-dorada.png" width="24" height="15" border="0" alt="Gestionar" title="Gestionar"></a>';
					echo '&nbsp;&nbsp;';
					echo '<a href="#" id="link_imprimir_computo"><img src="img/print.png" width="20" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(291))) {
						echo '<a href="#" id="link_items_computo"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Editar / Revisión" title="Editar / Revisión"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(292))) {
						echo '<a href="#" id="link_eliminar_computo"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
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
							  <th>Sitio</th>
							  <th>Sub</th>
							  <th>Proy</th>
							  <th>Nombre Proyecto</th>
							  <th class="d-none">Nro Computo</th>
							  <th>Nro Computo</th>
							  <th>Revisión</th>
							  <th>Fecha</th>
							  <th>Realizó</th>
							  <th>Estado</th>
							  <th>Observaciones</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                            if (!empty($_POST)) {
								$pdo = Database::connect();
								$sql = " SELECT s.nro_sitio, s.nro_subsitio, p.nro, p.nombre, c.`id`, c.`nro_revision`, date_format(c.`fecha`,'%d/%m/%y'), cu.`nombre`, ec.`estado`,c.nro,c.comentarios_revision, date_format(c.`fecha`,'%y%m%d') FROM computos c left join estados_computos ec on ec.id = c.id_estado left join cuentas cu on cu.id = c.id_cuenta_solicitante inner join tareas t on t.id = c.id_tarea inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE 1 ";
									
								if (!empty($_POST['nro'])) {
									$sql .= " and (p.nro = ".$_POST['nro']." or s.nro_sitio = ".$_POST['nro'].") ";
								}
								if (!empty($_POST['fecha'])) {
									$sql .= " AND c.fecha >= '".$_POST['fecha']."' ";
								}
								if (!empty($_POST['fechah'])) {
									$sql .= " AND c.fecha <= '".$_POST['fechah']."' ";
								}
								
								if (!empty($_POST['id_estado'][0])) {
									$sql .= " AND ec.id in (".implode(', ',$_POST['id_estado']).") ";
								}
								foreach ($pdo->query($sql) as $row) {
									echo '<tr>';
									echo '<td>'. $row[0] . '</td>';
									echo '<td>'. $row[1] . '</td>';
									echo '<td>'. $row[2] . '</td>';
									echo '<td>'. $row[3] . '</td>';
									echo '<td class="d-none">'. $row[4] . '</td>';
									echo '<td>'. $row[9] . '</td>';
									echo '<td>'. $row[5] . '</td>';
									echo '<td><span style="display: none;">'. $row[11] . '</span>'. $row[6] . '</td>';
									echo '<td>'. $row[7] . '</td>';
									echo '<td>'. $row[8] . '</td>';
									echo '<td>'. $row[10] . '</td>';
									echo '</tr>';
								}
								Database::disconnect();
							} else {
								$pdo = Database::connect();
								$sql = " SELECT s.nro_sitio, s.nro_subsitio, p.nro, p.nombre, c.`id`, c.`nro_revision`, date_format(c.`fecha`,'%d/%m/%y'), cu.`nombre`, ec.`estado`,c.nro,c.comentarios_revision, date_format(c.`fecha`,'%y%m%d') FROM computos c left join estados_computos ec on ec.id = c.id_estado left join cuentas cu on cu.id = c.id_cuenta_solicitante inner join tareas t on t.id = c.id_tarea inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE ec.`id` in (1,2,3,4) ";
									
								foreach ($pdo->query($sql) as $row) {
									echo '<tr>';
									echo '<td>'. $row[0] . '</td>';
									echo '<td>'. $row[1] . '</td>';
									echo '<td>'. $row[2] . '</td>';
									echo '<td>'. $row[3] . '</td>';
									echo '<td class="d-none">'. $row[4] . '</td>';
									echo '<td>'. $row[9] . '</td>';
									echo '<td>'. $row[5] . '</td>';
									echo '<td><span style="display: none;">'. $row[11] . '</span>'. $row[6] . '</td>';
									echo '<td>'. $row[7] . '</td>';
									echo '<td>'. $row[8] . '</td>';
									echo '<td>'. $row[10] . '</td>';
									echo '</tr>';
								}
								Database::disconnect();
							}
                          ?>
                        </tbody>
						<tfoot>
                          <tr>
							  <th>Sitio</th>
							  <th>Sub</th>
							  <th>Proy</th>
							  <th>Nombre Proyecto</th>
							  <th class="d-none">Nro Computo</th>
							  <th>Nro Computo</th>
							  <th>Revisión</th>
							  <th>Fecha</th>
							  <th>Realizó</th>
							  <th>Estado</th>
							  <th>Observaciones</th>
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
                    <h5>Conceptos</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
							  <th>Concepto</th>
							  <th>Solicitado</th>
							  <th>Fecha de Necesidad</th>
							  <th>Aprobado</th>
							  <th>Stock</th>
							  <th>Reservado</th>
							  <th>Comprando</th>
							  <th>Pendiente</th>
							  <th>Comentarios</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
						<tfoot>
                          <tr>
							  <th>Concepto</th>
							  <th>Solicitado</th>
							  <th>Fecha de Necesidad</th>
							  <th>Aprobado</th>
							  <th>Stock</th>
							  <th>Reservado</th>
							  <th>Comprando</th>
							  <th>Pendiente</th>
							  <th>Comentarios</th>
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
      <select id="select_estado_base"><?php
        $pdo = Database::connect();
        $sql = "SELECT id,estado FROM estados_computos";
        foreach ($pdo->query($sql) as $row) {
          echo '<option value="'.$row["id"].'">'.$row["estado"].'</option>';
        }
        Database::disconnect();?>
      </select>
    </div>
	
  <?php
    $pdo = Database::connect();
    $sql = " SELECT c.id,s.nro_sitio, s.nro_subsitio, p.id, p.nombre, c.`id`, c.`nro_revision`, date_format(c.`fecha`,'%d/%m/%y'), cu.`nombre`, ec.`estado` FROM computos c inner join estados_computos ec on ec.id = c.id_estado inner join cuentas cu on cu.id = c.id_cuenta_solicitante inner join tareas t on t.id = c.id_tarea inner join tipos_tarea tt on tt.id = t.`id_tipo_tarea` inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE 1 ";
    foreach ($pdo->query($sql) as $row) {
        ?>
  <div class="modal fade" id="eliminarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea eliminar el cómputo?</div>
      <div class="modal-footer">
      <a href="eliminarComputo.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <div class="modal fade" id="aprobarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea aprobar todos los items del cómputo?</div>
      <div class="modal-footer">
      <a href="aprobarComputo.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Aprobar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <div class="modal fade" id="enviarAprobarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea enviar a aprobación el cómputo?</div>
      <div class="modal-footer">
      <a href="aprobarComputo.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Aprobar</a>
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
        }},
		"fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                $('td:eq(9)', nRow).addClass("editable").attr('data-id-posicion', aData[4]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
              },
              initComplete: function(){
                $('[title]').tooltip();
              }
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
	
	$("#link_ver_computo").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un cómputo para ver detalle")
        }
      })
	$("#link_imprimir_computo").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un cómputo para ver imprimir")
        }
      })
	  $("#link_items_computo").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un cómputo para ver/añadir/modificar ítems")
        }
      })
	  $("#link_aprobar_computo").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un cómputo para aprobar")
        }
      })
	   $("#link_enviar_aprobar_computo").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un cómputo para enviar a aprobación")
        }
      })
	  $("#link_eliminar_computo").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un cómputo no aprobado para eliminar")
        }
      })
		
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
		
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_computo=t.find("td:nth-child(5)").html();
		let nro_revision = t.find("td:nth-child(7)").html();
        let estado = t.find("td:nth-child(10)").html();
		
        if(t.hasClass('selected')){
          deselectRow(t);
		      get_conceptos(id_computo)
          $("#link_ver_computo").attr("href","#");
		  $("#link_imprimir_computo").attr("href","#");
          $("#link_items_computo").attr("href","#");
		  $("#link_enviar_aprobar_computo").attr("data-target","#");
          $("#link_aprobar_computo").attr("data-target","#");
          $("#link_eliminar_computo").attr("data-target","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_conceptos(id_computo)
          $("#link_ver_computo").attr("href","verComputo.php?id="+id_computo);
		  $("#link_imprimir_computo").attr("target","_blank");
		  $("#link_imprimir_computo").attr("href","imprimirComputo.php?id="+id_computo);
		  if ((estado == 'Para Aprobar') || (estado == 'Elaboración')) {
		    $("#link_items_computo").attr("href","itemsComputo.php?id="+id_computo+"&modo=nuevo&revision="+nro_revision);
		  } else {
			$("#link_items_computo").attr("href","itemsComputo.php?id="+id_computo+"&modo=update&revision="+nro_revision);  
		  }
          if (estado == 'Para Aprobar') {
            $("#link_aprobar_computo").attr("data-toggle","modal");
            $("#link_aprobar_computo").attr("data-target","#aprobarModal_"+id_computo);
          } else {
            $("#link_aprobar_computo").attr("href","#");
          }
		  if (estado == 'Elaboración') {
            $("#link_enviar_aprobar_computo").attr("data-toggle","modal");
            $("#link_enviar_aprobar_computo").attr("data-target","#enviarAprobarModal_"+id_computo);
          } else {
            $("#link_enviar_aprobar_computo").attr("href","#");
          }
		  
		  if ((estado == 'Elaboración') || (estado == 'Para Aprobar')) {
            $("#link_eliminar_computo").attr("data-toggle","modal");
            $("#link_eliminar_computo").attr("data-target","#eliminarModal_"+id_computo);
          } else {
            $("#link_eliminar_computo").attr("data-target","#");
          }
		  
          
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
              url: "modificarEstadoComputo.php",
              data: "idPosicion="+idPosicion+"&idEstado="+nuevaEstado,
              success: function(data) {
                console.log(data)
                if(data==1){
                  t.css({padding: old_padding});
                  t.html(textoSeleccionado)
                }
              }
            });
          });
        });
	} );
	
    </script>
	
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
	
	function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }

    function get_conceptos(id_computo){
      let datosUpdate = new FormData();
      datosUpdate.append('id_computo', id_computo);
      $.ajax({
        data: datosUpdate,
        url: 'get_conceptos_computo.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          data = JSON.parse(data);

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
                that
                  .search( this.value )
                  .draw();
              }
            });
          });

          
        }
      });
    }
    function jsExportar() {
		document.location.href="exportComputos.php?nro="+document.getElementById('nro').value+"&fecha="+document.getElementById('fecha').value+"&fechah="+document.getElementById('fechah').value+"&estado="+document.getElementById('id_estado').value;
	}	
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
	<script src="assets/js/select2/select2.full.min.js"></script>
<script src="assets/js/select2/select2-custom.js"></script>

    <!-- Plugin used-->
  </body>
</html>