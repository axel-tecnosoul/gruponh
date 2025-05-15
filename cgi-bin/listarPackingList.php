<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}?>
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
          $ubicacion="Packing List ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(348))) { ?><a href="nuevaPackingList.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva"></a>&nbsp;&nbsp;<?php } ?><a href="exportPackingList.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_pl"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Detalle" title="Ver Detalle"></a>';
					echo '&nbsp;&nbsp;';
					echo '<a href="#" id="link_imprimir_pl"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(350))) {
						echo '<a href="#" id="link_eliminar_pl"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(349))) {
						echo '<a href="#" id="link_modificar_pl"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
					}
					/*if (!empty(tienePermiso(351))) {
						echo '<a href="#" id="link_nueva_seccion"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nueva Sección" title="Nueva Sección"></a>';
						echo '&nbsp;&nbsp;';
					}*/
					if (!empty(tienePermiso(351))) {
						echo '<a href="#" id="link_nueva_despacho" class="d-none"><i style="width: 24px; height: 20px;color: midnightblue;" class="fa fa-lg fa-truck" aria-hidden="true"></i></a>';
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
                            <th class="d-none"></th>
							<th>Sitio/Sub</th>
							<th>Proy</th>
							<th>Proyecto</th>
							<th>Packing</th>
							<th>Nombre</th>
                            <th>Rev</th>
                            <th>Fecha</th>
                            <th>Emisor</th>
							<th>Estado</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php 
                            include 'database.php';
                            $pdo = Database::connect();
                            $sql = "SELECT pl.id, plr.nro_revision, p.nombre, date_format(plr.fecha,'%d/%m/%y'), e.estado, plr.id, plr.nombre,p.id, s.nro_sitio, s.nro_subsitio,u.usuario,plr.numero FROM packing_lists pl INNER JOIN packing_lists_revisiones plr ON pl.id=plr.id_packing_list AND pl.ultimo_nro_revision=plr.nro_revision inner join proyectos p on p.id = plr.id_proyecto inner join sitios s on s.id = p.id_sitio inner join estados_packing_list e on e.id = plr.id_estado_packing_list inner join usuarios u on u.id = plr.id_usuario WHERE pl.anulado = 0 "; 
									 
                            foreach ($pdo->query($sql) as $row) {
                                echo '<tr>';
                                echo '<td class="d-none">'. $row[5] . '</td>';
                                echo '<td>'. $row[8] .' / '.$row[9] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
                                echo '<td>'. $row[2] . '</td>';
                                echo '<td>'. $row[11] . '</td>';
                                echo '<td>'. $row[6] . '</td>';
								echo '<td>'. $row[1] . '</td>';
								echo '<td>'. $row[3] . '</td>';
                                echo '<td>'. $row[10] . '</td>';
                                echo '<td>'. $row[4] . '</td>';
                                echo '</tr>';?>

                                <div class="modal fade" id="eliminarModal_<?=$row[0]?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                  <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                                        <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                      </div>
                                      <div class="modal-body">¿Está seguro que desea cancelar el Packing List?</div>
                                      <div class="modal-footer">
                                        <a href="eliminarPackingList.php?id=<?=$row[0]?>" class="btn btn-primary">Eliminar</a>
                                        <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
                                      </div>
                                    </div>
                                  </div>
                                </div><?php

                            }
                           Database::disconnect();
                          ?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <th class="d-none"></th>
                            <th>Sitio/Sub</th>
							<th>Proy</th>
							<th>Proyecto</th>
							<th>Packing</th>
							<th>Nombre</th>
                            <th>Rev</th>
                            <th>Fecha</th>
                            <th>Emisor</th>
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
                    <h5>Secciones y componentes&nbsp;&nbsp;<?php 
                      /*echo '<a href="#" id="link_ver_seccion_pl"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                      echo '&nbsp;&nbsp;';
                      if (!empty(tienePermiso(352))) {
                        echo '<a href="#" id="link_modificar_seccion"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(353))) {
                        echo '<a href="#" id="link_eliminar_seccion"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(354))) {
                        echo '<a href="#" id="link_nuevo_componente"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Componente" title="Nuevo Componente"></a>';
                        echo '&nbsp;&nbsp;';
                      }*/?>
					          </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th>Sección</th>
                            <th>Cantidad</th>
                            <th>Peso</th>
                            <th>Componente</th>
                            <th>Cantidad</th>
							<th>Peso</th>
                            <th>Observaciones</th>
                            <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th>Sección</th>
                            <th>Cantidad</th>
                            <th>Peso</th>
                            <th>Componente</th>
                            <th>Cantidad</th>
                            <th>Peso</th>
                            <th>Observaciones</th>
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
			<div class="row d-none">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Secciones
                      &nbsp;&nbsp;
                      <?php 
                      echo '<a href="#" id="link_ver_seccion_pl"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                      echo '&nbsp;&nbsp;';
                      if (!empty(tienePermiso(352))) {
                        echo '<a href="#" id="link_modificar_seccion"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(353))) {
                        echo '<a href="#" id="link_eliminar_seccion"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(354))) {
                        echo '<a href="#" id="link_nuevo_componente"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Componente" title="Nuevo Componente"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      ?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example6672">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Cantidad</th>
                            <th>Observaciones</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
						            <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Cantidad</th>
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
			<div class="row d-none">
         <!-- Zero Configuration  Starts-->
			  <div class="col-sm-12">
				<div class="card">
				  <div class="card-header">
					<h5>Componentes
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_componente_pl"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(355))) {
						echo '<a href="#" id="link_modificar_componente"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Actualizar" title="Actualizar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(356))) {
						echo '<a href="#" id="link_eliminar_componente"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
						echo '&nbsp;&nbsp;';
					}
					?>
					</h5>
				  </div>
				  <div class="card-body">
					<div class="dt-ext table-responsive">
					  <table class="display truncate" id="dataTables-example668">
						<thead>
						  <tr>
							  <th>ID</th>
							  <th>Conjunto</th>
							  <th>Concepto</th>
							  <th>Cantidad</th>
							  <th>Observaciones</th>
							  <th>Estado</th>
						  </tr>
						</thead>
						<tbody>
						</tbody>
						<tfoot>
						  <tr>
							  <th>ID</th>
							  <th>Conjunto</th>
							  <th>Concepto</th>
							  <th>Cantidad</th>
							  <th>Observaciones</th>
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
        $sql = "SELECT id,estado FROM estados_packing_list";
        foreach ($pdo->query($sql) as $row) {
          echo '<option value="'.$row["id"].'">'.$row["estado"].'</option>';
        }
        Database::disconnect();?>
      </select>
    </div>
	
		<?php
		$pdo = Database::connect();
		$sql = "SELECT `id`, `id_packing_list_seccion`, `id_conjunto_lista_corte`, `id_concepto`, `cantidad`, `observaciones`, `id_estado_componente_packing_list` FROM `packing_lists_componentes` WHERE 1 "; 
		foreach ($pdo->query($sql) as $row) {
			?>
		  <div class="modal fade" id="eliminarModalComponente_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalComponenteLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
			<div class="modal-content">
			  <div class="modal-header">
			  <h5 class="modal-title" id="exampleModalComponenteLabel">Confirmación</h5>
			  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
			  </div>
			  <div class="modal-body">¿Está seguro que desea eliminar el Componente?</div>
			  <div class="modal-footer">
			  <a href="eliminarComponentePackingList.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
			  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
			  </div>
			</div>
			</div>
		  </div>
		  <?php
			}
			Database::disconnect();
			?>
			<?php
			$pdo = Database::connect();
			$sql = "SELECT id, id_packing_list_revision, cantidad, observaciones FROM packing_lists_secciones WHERE 1 "; 
			foreach ($pdo->query($sql) as $row) {
				$sql2 = "select count(*) cant from packing_lists_componentes where id_packing_list_seccion = ".$row[0];
				$q2 = $pdo->prepare($sql2);
				$q2->execute();
				$data2 = $q2->fetch(PDO::FETCH_ASSOC);
				if (empty($data2['cant'])) {
				?>
				<div class="modal fade" id="eliminarModalSeccion_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalSeccionLabel" aria-hidden="true">
				<div class="modal-dialog" role="document">
				<div class="modal-content">
				  <div class="modal-header">
				  <h5 class="modal-title" id="exampleModalSeccionLabel">Confirmación</h5>
				  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				  </div>
				  <div class="modal-body">¿Está seguro que desea eliminar la Sección?</div>
				  <div class="modal-footer">
				  <a href="eliminarSeccionPackingList.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
				  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
				  </div>
				</div>
				</div>
			  </div>
			  <?php
				} else {
			?>
				<div class="modal fade" id="eliminarModalSeccion_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalSeccionLabel" aria-hidden="true">
				<div class="modal-dialog" role="document">
				<div class="modal-content">
				  <div class="modal-header">
				  <h5 class="modal-title" id="exampleModalSeccionLabel">Alerta</h5>
				  <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				  </div>
				  <div class="modal-body">La Sección no puede ser eliminada debido a que tiene Componentes sin cancelar.</div>
				  <div class="modal-footer">
				  <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
				  </div>
				</div>
				</div>
			  </div>
			  <?php
				}
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
        }},
		"fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                $('td:eq(9)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
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
		$("#link_ver_pl").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un packing list para ver detalle")
			}
    })
    $("#link_imprimir_pl").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un packing list para imprimir")
			}
		})
		
		$("#link_eliminar_pl").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un packing list para cancelar")
        }
      })
		$("#link_modificar_pl").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un packing list para modificar/revisar")
			}
		})
		$("#link_nueva_seccion").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un packing list para crear sección")
			}
		})
    $("#link_nueva_despacho").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un packing list para realizar un despacho")
			}
    })
		
		
		//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_pl_revision=t.find("td:first-child").html();
        let id_pl = t.find("td:nth-child(5)").html();
		    let nro_revision = t.find("td:nth-child(3)").html();
        if(t.hasClass('selected')){
          deselectRow(t);
		      get_secciones(id_pl_revision)
          $("#link_ver_pl").attr("href","#");
          $("#link_imprimir_pl").attr("href","#");
		  $("#link_eliminar_pl").attr("data-target","#");
          $("#link_modificar_pl").attr("href","#");
          $("#link_nueva_seccion").attr("href","#");
          $("#link_nueva_despacho").attr("href","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
		      get_secciones(id_pl_revision)
          $("#link_ver_pl").attr("href","verPackingList.php?id="+id_pl_revision);
          $("#link_imprimir_pl").attr("href","imprimirPackingList.php?id="+id_pl_revision);
          $("#link_eliminar_pl").attr("data-toggle","modal");
          $("#link_eliminar_pl").attr("data-target","#eliminarModal_"+id_pl);
		  
		  
          $("#link_nueva_seccion").attr("href","nuevaSeccionPackingList.php?id="+id_pl_revision);
          //$("#link_modificar_pl").attr("href","modificarPackingList.php?id="+id_pl_revision+"&revision="+nro_revision);
          $("#link_modificar_pl").attr("href","modificarPackingList.php?id_packing_list_revision="+id_pl_revision);
          $("#link_nueva_despacho").attr("href","nuevoDespacho.php?id_packing_list="+id_pl_revision);
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
              url: "modificarEstadoPackingList.php",
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
    function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }
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
	
	function get_secciones(id_pl){
      let datosUpdate = new FormData();
      datosUpdate.append('id_pl', id_pl);
      $.ajax({
        data: datosUpdate,
        url: 'get_secciones.php',
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
                that
                  .search( this.value )
                  .draw();
              }
            });
          });
		  
		  $("#link_ver_seccion_pl").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione una sección para ver detalle")
			}
		  })
		  $("#link_modificar_seccion").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione una sección para modificar")
				}
			})
			$("#link_eliminar_seccion").on("click",function(){
				/*let l=document.location.href;
				if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
				  alert("Por favor seleccione una sección para eliminar")
				}
			})
			$("#link_nuevo_componente").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione una sección para agregar componente")
				}
			})
			
      //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
      $(document).on("click","#dataTables-example667 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_sec=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          get_componentes(id_sec)
          $("#link_ver_seccion_pl").attr("href","#");
          $("#link_modificar_seccion").attr("href","#");
          $("#link_eliminar_seccion").attr("data-target","#");
          $("#link_nuevo_componente").attr("href","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          get_componentes(id_sec)
          $("#link_ver_seccion_pl").attr("href","verSeccionPackingList.php?id="+id_sec);
          $("#link_modificar_seccion").attr("href","modificarSeccionPackingList.php?id="+id_sec);
          $("#link_nuevo_componente").attr("href","nuevoComponentePackingList.php?id="+id_sec);
          $("#link_eliminar_seccion").attr("data-toggle","modal");
          $("#link_eliminar_seccion").attr("data-target","#eliminarModalSeccion_"+id_sec);
        }
		  });
          
        }
      });
    }
    
    </script>
	
	<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell
    $('#dataTables-example668 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
    } );
	$('#dataTables-example668').DataTable({
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
    var table = $('#dataTables-example668').DataTable();
 
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
	
	function get_componentes(id_sec){
      let datosUpdate = new FormData();
      datosUpdate.append('id_sec', id_sec);
      $.ajax({
        data: datosUpdate,
        url: 'get_componentes.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          console.log(data);
          data = JSON.parse(data);
          console.log(data);

          $('#dataTables-example668').DataTable().destroy();
          $('#dataTables-example668').DataTable({
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
          var table = $('#dataTables-example668').DataTable();
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
		  
		  $("#link_ver_componente_pl").on("click",function(){
			let l=document.location.href;
			if(this.href==l || this.href==l+"#"){
			  alert("Por favor seleccione un componente para ver detalle")
			}
		  })
		  $("#link_modificar_componente").on("click",function(){
				let l=document.location.href;
				if(this.href==l || this.href==l+"#"){
				  alert("Por favor seleccione un componente para actualizar")
				}
			})
			$("#link_eliminar_componente").on("click",function(){
				/*let l=document.location.href;
				if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
				  alert("Por favor seleccione un componente para eliminar")
				}
			})
			
          //$('#dataTables-example668').find("tbody tr td").not(":last-child").on( 'click', function () {
      $(document).on("click","#dataTables-example668 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_componente=t.find("td:first-child").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_ver_componente_pl").attr("href","#");
          $("#link_modificar_componente").attr("href","#");
          $("#link_eliminar_componente").attr("data-target","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_ver_componente_pl").attr("href","verComponenteSeccionPackingList.php?id="+id_componente);
          $("#link_modificar_componente").attr("href","modificarComponenteSeccionPackingList.php?id="+id_componente);
          $("#link_eliminar_componente").attr("data-toggle","modal");
          $("#link_eliminar_componente").attr("data-target","#eliminarModalComponente_"+id_componente);
        }
		  });
          
        }
      });
    }
    
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>