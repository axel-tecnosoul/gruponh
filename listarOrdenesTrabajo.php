<?php
/*session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
include 'database.php';*/
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
      .faClass{
        width: 24px;
        height: 20px;
        color: midnightblue;
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
                          <option value="">Todos</option><?php
                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $sqlZon = "SELECT `id`, `estado` FROM `estados_orden_trabajo` WHERE 1 order by estado ";
                          $q = $pdo->prepare($sqlZon);
                          $q->execute();
                          while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                            $selected = '';
                            if (isset($_POST['id_estado'])) {
                              if (in_array($fila['id'],$_POST['id_estado'])) {
                                $selected = " selected ";
                              }
                            }?>
                            <option value='<?=$fila['id']?>'<?=$selected?>><?=$fila['estado']?></option><?php
                          }
							            Database::disconnect();?>
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
                      if (!empty(tienePermiso(316))) {?>
                        <a href="#" id="link_modificar_ot"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>
                        &nbsp;&nbsp;<?php
                      }?>
                      <a href="#" id="link_ver_ot"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>
                      &nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(318))) {?>
                        <a href="#" id="link_enviar_produccion_ot"><img src="img/estrella.png" width="24" height="25" border="0" alt="Enviar a Producción" title="Enviar a Producción"></a>
                        &nbsp;&nbsp;<?php
                      }?>
                      <?php
                      if (!empty(tienePermiso(317))) {?>
                        <a href="#" id="link_cancelar_ot"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>
                        &nbsp;&nbsp;<?php
                      }?>
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
                            <!-- <th>N° OT / Revisión</th> -->
                             <th>N° OT</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody><?php 
                          if (!empty($_POST)) {
                            $pdo = Database::connect();
                            //$sql = "SELECT otr.id, otr.numero, otr.nro_revision, otr.titulo, lcr.id_lista_corte, lcr.nombre ,p.nombre AS proyecto, date_format(otr.fecha,'%d/%m/%y') AS fecha, e.estado,s.nro_sitio AS sitio,s.nro_subsitio AS subsitio,u.usuario,p.nro nro FROM ordenes_trabajo otr INNER JOIN ordenes_trabajo ot ON ot.id=otr.id_orden_trabajo INNER JOIN listas_corte_revisiones lcr ON otr.id_lista_corte=lcr.id inner join estados_orden_trabajo e on e.id = otr.id_estado_orden_trabajo inner join proyectos p on p.id = lcr.id_proyecto inner join sitios s on s.id = p.id_sitio INNER JOIN usuarios u ON otr.id_usuario=u.id WHERE ot.anulado = 0";
                            $sql = "SELECT ot.id, ot.numero, ot.nro_orden_trabajo, ot.nro_revision, ot.titulo, lc.id AS id_lista_corte, lc.nombre, lc.numero AS nro_lc ,p.nombre AS proyecto, date_format(ot.fecha,'%d/%m/%y') AS fecha, e.estado,s.nro_sitio, s.nro_subsitio, u.usuario,p.nro nro FROM ordenes_trabajo ot INNER JOIN listas_corte lc ON ot.id_lista_corte=lc.id inner join estados_orden_trabajo e on e.id = ot.id_estado_orden_trabajo inner join proyectos p on p.id = lc.id_proyecto inner join sitios s on s.id = p.id_sitio INNER JOIN usuarios u ON ot.id_usuario=u.id WHERE ot.anulado = 0";
                            if (!empty($_POST['nro'])) {
                              $nro=$_POST['nro'];
                              $ex=explode("/", $nro);
                              if(count($ex)>1){
                                $sitio = $ex[0];
                                $proyecto = $ex[1];
                                $sql .= " AND (p.nro = ".$proyecto." AND s.nro_sitio = ".$sitio.") ";
                              }else{
                                $sql .= " AND (p.nro = ".$nro." OR s.nro_sitio = ".$nro.") ";
                              }
                            }
                            if (!empty($_POST['fecha'])) {
                              $sql .= " AND otr.fecha >= '".$_POST['fecha']."' ";
                            }
                            if (!empty($_POST['fechah'])) {
                              $sql .= " AND otr.fecha <= '".$_POST['fechah']."' ";
                            }
                            if (!empty($_POST['id_estado'][0])) {
                              $sql .= " AND e.id in (".implode(', ',$_POST['id_estado']).") ";
                            }else{
                              $sql .= " AND e.id in (1,2,3,4) ";
                            }
                            foreach ($pdo->query($sql) as $row) {?>
                              <tr>
                                <td class="d-none"><?=$row["id"]?></td>
                                <td><?=$row["nro_sitio"]?></td>
                                <td><?=$row["nro_subsitio"]?></td>
                                <td><?=$row["nro"]?></td>
                                <td><?=$row["nro_lc"]?></td>
                                <td><?=$row["nombre"]?></td>
                                <!-- <td><?=$row["nro_orden_trabajo"].' / '.$row["nro_revision"]?></td> -->
                                 <td><?=$row["nro_orden_trabajo"]?></td>
                                <td><?=$row["fecha"]?></td>
                                <td><?=$row["usuario"]?></td>
                                <td><?= $row["estado"]?></td>
                              </tr><?php
                            }
                            Database::disconnect();
                          }?>
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
                                                    <a id="btnDetalle" title="Ver Historial" style="cursor: pointer;" href="#"><i class='faClass fa fa-lg fa-eye'></i></a>&nbsp;&nbsp;
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="tablaDetalleOT">
                        <thead>
                          <tr>
                            <th class="d-none">ID Posición</th>
                            <th>Conjunto</th>
                            <th>Cant. Conjuntos</th>
                            <th>Posicion</th>
                            <th>Cant. Pedida</th>
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
                            <th class="d-none">ID Posición</th>
                            <th>Conjunto</th>
                            <th>Cant. Conjuntos</th>
                            <th>Posicion</th>
                            <th>Cant. Pedida</th>
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

      <div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea cancelar la Orden de Trabajo?</div>
          <div class="modal-footer">
            <a href="#" class="btn btn-primary" id="btnEliminarOT">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
      </div>
      <div class="modal fade" id="enviarProduccionModal" tabindex="-1" role="dialog" aria-labelledby="modalEnviarProduccionLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalEnviarProduccionLabel">Confirmación</h5>
              <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">¿Está seguro que desea enviar la Orden de Trabajo a producción?</div>
            <div class="modal-footer">
              <a href="#" class="btn btn-primary" id="btnEnviarProduccionOT">Enviar</a>
              <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal fade" id="modificarCantidades" tabindex="-1" role="dialog" aria-labelledby="exampleModalModificarCantidades" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form id="formModificarCantidades" action="modificarCantidadesPosicionesOT.php" method="post">
            <div class="modal-header">
              <h5 class="modal-title" id="exampleModalModificarCantidades">Ingrese las cantidades para <span id="info_pos_titulo"></span> (Max. <span id="cantMaxima"></span>)</h5>
              <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Posición</label>
                <div class="col-sm-9"><p class="form-control-plaintext" id="info_posicion"></p></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Cant. Pedida</label>
                <div class="col-sm-9"><p class="form-control-plaintext" id="info_cant_pedida"></p></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Cantidades actuales</label>
                <div class="col-sm-9"><p class="form-control-plaintext" id="info_cant_actuales"></p></div>
              </div>
              <div class="form-group row">
                <input type="hidden" name="id_posicion_ot" id="id_posicion_ot">
                <input type="hidden" name="id_orden_trabajo" id="id_orden_trabajo">
                <input type="hidden" id="liberadas_actual" value="0">
                <input type="hidden" id="rechazadas_actual" value="0">
                <input type="hidden" id="reproceso_actual" value="0">
                <label class="col-sm-3 col-form-label">Fecha</label>
                <div class="col-sm-9"><input name="fecha" type="datetime-local" class="form-control"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Liberados</label>
                <div class="col-sm-9"><input name="liberadas" type="number" class="form-control"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Reproceso</label>
                <div class="col-sm-9"><input name="reproceso" type="number" class="form-control"></div>
              </div>
              <div class="form-group row">
                <label class="col-sm-3 col-form-label">Rechazados</label>
                <div class="col-sm-9"><input name="rechazadas" type="number" class="form-control"></div>
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

        let estadoOT = '';
        $("#btnAbrirModalModificarCantidades").data("id","").data("estado","");

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
		      /*"fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
            $('td:eq(9)', nRow).addClass("editable1").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[4]).attr("title","Doble click para editar");
          },*/
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
            $("#link_enviar_produccion_ot").attr("href","#");
            $("#link_nuevo_consumo").attr("href","#");
            $("#link_cancelar_ot").attr("data-target","#");
            $("#btnEliminarOT").attr("href","#");
            $("#id_orden_trabajo").val('');
            estadoOT='';
            $("#btnAbrirModalModificarCantidades").data("id","").data("estado","");
            $("#btnDetalle").attr("href","#");
          }else{
            //t.parent().find("tr").removeClass("selected");
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_detalle_orden_trabajo(id_ot)
            $("#btnAbrirModalModificarCantidades").data("id","").data("estado","");
            $("#btnDetalle").attr("href","#");
            $("#id_orden_trabajo").val(id_ot);
            estadoOT = estado;
            if (estado == "Elaboracion" || estado == "Para Aprobar") {
              $("#link_modificar_ot").attr("href","nuevaOrdenTrabajo.php?id="+id_ot);
            } else {
              $("#link_modificar_ot").attr("href","#");
            }
            if (estado == "Para Aprobar") {
              $("#link_enviar_produccion_ot").attr("href","enviarProduccionOT.php?id="+id_ot);
            } else {
              $("#link_enviar_produccion_ot").attr("href","#");
            }
            if (estado == "En Produccion") {
              $("#link_nuevo_consumo").attr("href","nuevoConsumo.php?id_orden_trabajo="+id_ot);
            } else {
              $("#link_nuevo_consumo").attr("href","#");
            }

            $("#link_ver_ot").attr("href","verOrdenTrabajo.php?id="+id_ot);
            if ((estado == "Elaboracion") || (estado == "Para Aprobar")) {
              $("#link_cancelar_ot").attr("data-toggle","modal");
              $("#link_cancelar_ot").attr("data-target","#eliminarModal");
              $("#btnEliminarOT").attr("href","eliminarOrdenTrabajo.php?id="+id_ot);
            } else {
              $("#link_cancelar_ot").attr("data-target","#");
              $("#btnEliminarOT").attr("href","#");
            }
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
        $("#link_cancelar_ot").on("click",function(){
          let target=this.dataset.target;
          if(target==undefined || target=="#"){
            alert("Por favor seleccione una orden de trabajo en estado 'Elaboracion' o 'Para Aprobar' para eliminarla")
          }
        })

        $("#link_enviar_produccion_ot").on("click",function(e){
          e.preventDefault();
          if(estadoOT != "Para Aprobar"){
            alert("Por favor seleccione una orden de trabajo en estado 'Para Aprobar' para enviarla a producción");
            return;
          }
          $("#btnEnviarProduccionOT").attr("href", this.href);
          $("#enviarProduccionModal").modal("show");
        })

        $("#link_nuevo_consumo").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de trabajo para agregar consumos")
          }
        })

        $("#btnAbrirModalModificarCantidades").on("click",function(){
          let id_posicion=$(this).data("id");
          let estadoPos=$(this).data("estado");
          if(id_posicion=="" || id_posicion<=0){
            alert("Por favor seleccione una posicion para modificar las cantidades");
            return;
          }
          if(estadoPos!="En Produccion"){
            alert("No se pueden modificar las cantidades si la posicion no está en estado 'En Produccion'");
            return;
          }
          let modal=$("#modificarCantidades");
          modal.modal("show");
        });

        $("#modificarCantidades").on('shown.bs.modal',function(){
          let now=new Date();
          let local=new Date(now.getTime() - now.getTimezoneOffset()*60000).toISOString().slice(0,16);
          $(this).find("input[name='fecha']").val(local);
          $(this).find("input[name='reproceso'],input[name='rechazadas'],input[name='liberadas'],input[name='motivo']").val("");
          $(this).find("input[name='motivo']").prop('required',false);
          let max=parseInt($("#cantMaxima").html())||0;
          $(this).find("input[name='liberadas'],input[name='rechazadas']").attr('max',max);
        });

        $("input[name='rechazadas']").on('input',function(){
          let motivo=$("input[name='motivo']");
          if(parseInt($(this).val())>0){
            motivo.prop('required',true);
          }else{
            motivo.prop('required',false);
          }
        });
		
        $("#btnDetalle").on("click",function(e){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            e.preventDefault();
            alert("Por favor seleccione una posicion para ver el historial");
          }
        });
		
		
        $("#btnModificarCantidades").on("click",function(e){
          e.preventDefault();
          let form=$("#formModificarCantidades");
          let reproceso=parseInt(form.find("input[name='reproceso']").val())||0;
          let rechazadas=parseInt(form.find("input[name='rechazadas']").val())||0;
          let liberadas=parseInt(form.find("input[name='liberadas']").val())||0;
          let cantMaxima=parseInt($("#cantMaxima").html())||0;
          let motivo=form.find("input[name='motivo']").val();

          if(rechazadas>0 && motivo.trim()==""){
            alert("Debe ingresar el motivo del rechazo");
          }else if((liberadas+rechazadas)>cantMaxima){
            alert("La suma de liberadas y rechazadas no puede superar la cantidad disponible ("+cantMaxima+")");
          }else{
            form.submit();
          }
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
              order: [[0, 'asc']],
              columns: [
                { data: 14 },
                { data: 1 },
                { data: 2 },
                { data: 3 },
                { data: 4 },
                { data: 5 },
                { data: 6 },
                { data: 7 },
                { data: 8 },
                { data: 9 },
                { data: 10 },
                { data: 11 },
                { data: 12 },
              ],
              columnDefs: [
                { targets: [0], className: 'd-none'},
                { targets: [2], visible: false },
                //{ targets: [13], className: 'd-none', visible: false }
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
              /*"fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                console.log(nRow);
                console.log(aData);
                $('td:eq(7)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[10]).attr("title","Doble click para editar");
              },*/
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
        
            $('#tablaDetalleOT').find("tbody tr td").not(":last-child").not(":nth-last-child(2)").on( 'click', function () {
            //$(document).on("click","#tablaDetalleOT tbody tr td", function(){
              var t=$(this).parent();
              //t.parent().find("tr").removeClass("selected");

              let rowData = table.row(t).data();
              let id_detalle_ot = rowData[0];
              let id_pos_ot = rowData[14];
              let cantPedida = rowData[4];
              let cantLibAct = rowData[8];
              let cantRepAct = rowData[9];
              let cantRechAct = rowData[10];
              let estadoPos = rowData[7];
              let posicion = rowData[3];
              let material = rowData[5];
              let cantDisponible = cantPedida - cantLibAct - cantRechAct;
              if(t.hasClass('selected')){
                deselectRow(t);
                $("#btnAbrirModalModificarCantidades").data("id","").data("estado","");
                $("#btnDetalle").attr("href","#");
                $("#cantMaxima").html("");
                $("#id_posicion_ot").val("");
                $("#liberadas_actual").val(0);
                $("#rechazadas_actual").val(0);
                $("#reproceso_actual").val(0);
                $("#info_pos_titulo").html("");
                $("#info_posicion").html("");
                $("#info_cant_pedida").html("");
                $("#info_cant_actuales").html("");
              }else{
                table.rows().nodes().each( function (rowNode, index) {
                  $(rowNode).removeClass("selected");
                });
                selectRow(t);
                $("#btnAbrirModalModificarCantidades").data("id",id_pos_ot).data("estado",estadoPos);
                $("#btnDetalle").attr("href","verHistorialOT.php?id_detalle_ot="+id_detalle_ot);
                $("#cantMaxima").html(cantDisponible);
                $("#id_posicion_ot").val(id_pos_ot);
                $("#liberadas_actual").val(cantLibAct);
                $("#rechazadas_actual").val(cantRechAct);
                $("#reproceso_actual").val(cantRepAct);
                let infoPos = posicion + ' - ' + material;
                $("#info_pos_titulo").text(infoPos);
                $("#info_posicion").text(infoPos);
                $("#info_cant_pedida").text(cantPedida);
                $("#info_cant_actuales").text('Liberadas: '+cantLibAct+' | Reproceso: '+cantRepAct+' | Rechazadas: '+cantRechAct+' | Disponibles: '+cantDisponible);
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