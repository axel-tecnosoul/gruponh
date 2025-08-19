<?php
/*session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}*/
include 'config.php';
include 'database.php';?>
<!DOCTYPE html>
<html lang="en">
  <head><?php
    include('head_tables.php');?>
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
      <!-- Page Header Start--><?php
      include('header.php');?>
      <!-- Page Header Ends                              -->
      <!-- Page Body Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start--><?php
        include('menu.php');?>
        <!-- Page Sidebar Ends-->
        <!-- Right sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Listas de Corte ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <?php if(isset($_GET['error']) && $_GET['error']=='lc_no_aprobada'){?>
                <div class="alert alert-danger">La lista de corte debe estar aprobada para generar una orden de trabajo.</div>
                <?php } else if(isset($_GET['error']) && $_GET['error']=='lc_revision_mas_reciente'){?>
                <div class="alert alert-danger">Hay revisiones más recientes generadas para la lista de corte.</div>
                <?php } else if(isset($_GET['error']) && $_GET['error']=='lc_estado_revision'){?>
                <div class="alert alert-danger">La lista de corte seleccionada no permite generar una nueva revisión.</div>
                <?php }?>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-body">
                    <form class="form-inline theme-form mt-3" name="form1" method="post" action="listarListasCorte.php">
                      <div class="form-group mb-0">
                        N.Sitio/N.Proy:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro" id="nro">
                      </div>
                      <div class="form-group mb-0">
                        Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fecha'])) echo $_POST['fecha'] ?>" name="fecha" id="fecha">-<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fechah'])) echo $_POST['fechah'] ?>" name="fechah" id="fechah">
                      </div>
                      <div class="form-group mb-0">
                        Estado:&nbsp;
                        <select name="id_estado[]" id="id_estado" class="js-example-basic-multiple" multiple="multiple">
                          <option value="">Todos</option><?php
                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $sqlZon = "SELECT `id`, `estado` FROM `estados_lista_corte` WHERE 1 order by estado ";
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
                          Database::disconnect();?>
                        </select>
                      </div>
                      <div class="form-group mb-0">
                        <button class="btn btn-primary" id="btnFiltrar" onclick="document.form1.target='_self';document.form1.action='listarListasCorte.php'">Buscar</button>
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
                        &nbsp;<?php
                      }?>
                      <a href="#" onclick="jsExportar();"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
                      &nbsp;&nbsp;<?php 
                      if (!empty(tienePermiso(316))) {?>
                        <a href="#" id="link_modificar_lc"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>
                        &nbsp;&nbsp;<?php
                      }?>
                      <a href="#" id="link_imprimir_lc"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>
                      &nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(317))) {?>
                        <a href="#" id="link_eliminar_lc"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>
                        &nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(315))) { ?>
                        <a href="#" id="link_clonar_lc"><img src="img/icon_ejecutar.png" width="24" height="25" border="0" alt="Clonar" title="Clonar"></a>
                        &nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(293))) {?>
                        <a href="#" id="link_aprobar_lc" data-accion="aprobar" title="Aprobar LC"><img src="img/estrella.png" width="24" height="25"></a>
                        &nbsp;&nbsp;<?php
                      }?>
                      <a href="#" id="link_ot_lc" title="Nueva OT"><i style="vertical-align: middle;color: midnightblue;" class='fa fa-lg fa-briefcase'></i></a>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Sitio</th>
                            <th>Sub</th>
                            <th>Proy</th>
                            <th>Proyecto</th>
                            <th>LC</th>
                            <th>Revisión</th>
                            <th>Fecha</th>
                            <th>Plano</th>
                            <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody><?php 
                          $pdo = Database::connect();
                          //$sql = "SELECT lc.id AS id_lista_corte, lcr.numero, lcr.nro_revision, lcr.nombre, p.nro AS nro_proyecto, date_format(lcr.fecha,'%d/%m/%y') AS fecha_lc, e.estado, lcr.adjunto, s.nro_sitio, s.nro_subsitio, lcr.id AS id_lista_corte_revision, date_format(lcr.fecha,'%y%m%d') AS fecha_lc_numero, p.nombre AS nombre_proyecto FROM listas_corte lc INNER JOIN listas_corte_revisiones lcr ON lcr.id_lista_corte=lc.id inner join estados_lista_corte e on e.id = lcr.id_estado_lista_corte inner join proyectos p on p.id = lcr.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE lc.anulado = 0 "; 
                          $sql = "SELECT lc.id AS id_lista_corte, lc.numero AS numero_lc, lc.nro_revision, lc.nombre, p.nro AS nro_proyecto, date_format(lc.fecha,'%d/%m/%y') AS fecha_lc, e.estado, lc.adjunto, s.nro_sitio, s.nro_subsitio, date_format(lc.fecha,'%y%m%d') AS fecha_lc_numero, p.nombre AS nombre_proyecto, lc.id_estado_lista_corte FROM listas_corte lc inner join estados_lista_corte e on e.id = lc.id_estado_lista_corte inner join proyectos p on p.id = lc.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE lc.anulado = 0 "; 
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
                            $sql .= " AND lc.fecha >= '".$_POST['fecha']."' ";
                          }
                          if (!empty($_POST['fechah'])) {
                            $sql .= " AND lc.fecha <= '".$_POST['fechah']."' ";
                          }
                          if (!empty($_POST['id_estado'][0])) {
                            $sql .= " AND e.id in (".implode(', ',$_POST['id_estado']).") ";
                          }else{
                            $sql .= " AND e.id in (1,2,3,4) ";
                          }
                          //echo $sql;
                          foreach ($pdo->query($sql) as $row) {?>
                            <tr data-estado-id="<?=$row["id_estado_lista_corte"]?>" data-estado-nombre="<?=$row["estado"]?>" data-id-lista-corte="<?=$row["id_lista_corte"]?>" data-numero-lc="<?=$row["numero_lc"]?>" data-nro-revision="<?=$row["nro_revision"]?>" data-proyecto="<?=$row["nombre_proyecto"]?>" data-sitio="<?=$row["nro_sitio"]?>" data-subsitio="<?=$row["nro_subsitio"]?>" data-proy="<?=$row["nro_proyecto"]?>">
                              <td class="d-none"><?=$row["id_lista_corte"]?></td>
                              <td><?=$row["nro_sitio"]?></td>
                              <td><?=$row["nro_subsitio"]?></td>
                              <td><?=$row["nro_proyecto"]?></td>
                              <td><?=$row["nombre_proyecto"]?></td>
                              <td><?=$row["numero_lc"]?></td>
                              <td><?=$row["nro_revision"]?></td>
                              <td><span style="display: none;"><?=$row["fecha_lc_numero"]?></span><?=$row["fecha_lc"]?></td>
                              <td><?php
                                if (!empty($row["adjunto"])) {?>
                                  <a target="_blank" href="<?=$row["adjunto"]?>"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Plano" title="Ver Plano"></a><?php
                                }?>
                              </td>
                              <td><?=$row["estado"]?></td>
                            </tr><?php
                          }
                          Database::disconnect();?>
                        </tbody>
						            <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Sitio</th>
                            <th>Subsitio</th>
                            <th>Nro Proy</th>
							              <th>Proyecto</th>
                            <th>LC</th>
                            <th>Revisión</th>
                            <th>Fecha</th>
                            <th>Plano</th>
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
                    <h5>Detalle de la Lista de Corte
                      &nbsp;&nbsp;<?php 
                     ?>
                    </h5><!--Columnas a mostrar en planilla Detalle de Listas de Corte: conjunto, cantidad, posición, cantidad, material, ancho, alto, diametro, marca, procesos...., fabricando, liberados, pendientes -->
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th>Conjunto</th>
                            <th>Cantidad Conjuntos</th>
                            <th>Posicion</th>
                            <th>Concepto</th>
                            <th>Cantidad Posiciones</th>
                            <th>Procesos</th>
                            <th>Estado</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th>Conjunto</th>
                            <th>Cantidad Conjuntos</th>
                            <th>Posicion</th>
                            <th>Concepto</th>
                            <th>Cantidad Posiciones</th>
                            <th>Procesos</th>
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

    <div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="eliminarModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="eliminarModalLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea cancelar la Lista de Corte?</div>
          <div class="modal-footer">
            <a href="#" id="btnEliminarListaCorte" class="btn btn-primary">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalConfirmarAprobacion" tabindex="-1" role="dialog" aria-labelledby="modalConfirmarAprobacionLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalConfirmarAprobacionLabel">Confirmación</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p id="textoConfirmacion">¿Está seguro?</p>
          </div>
          <div class="modal-footer">
            <button id="btnConfirmarAprobacion" type="button" class="btn btn-primary">Confirmar</button>
            <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalClonar" tabindex="-1" role="dialog" aria-labelledby="modalClonarLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalClonarLabel">Confirmación</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p id="textoClonar">¿Está seguro?</p>
            <select id="select_tarea_clonar" class="col-sm-12" style="width:100%; margin-top:10px;">
              <option value="">Seleccione una tarea...</option><?php
              $pdo = Database::connect();
              $sqlT = "SELECT t.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre, tt.tipo, t.observaciones FROM tareas t INNER JOIN proyectos p ON p.id = t.id_proyecto INNER JOIN sitios s ON s.id = p.id_sitio INNER JOIN tipos_tarea tt ON tt.id = t.id_tipo_tarea WHERE t.anulado = 0 AND p.anulado = 0 ORDER BY s.nro_sitio, s.nro_subsitio, p.nro, t.id";
              foreach ($pdo->query($sqlT) as $filaT) {
                $desc = $filaT['nro_sitio'].'-'.$filaT['nro_subsitio'].'-'.$filaT['nro'].': '.$filaT['nombre'].' / '.$filaT['tipo'].' - '.$filaT['observaciones'];
                echo "<option value='{$filaT['id']}'>".$desc."</option>";
              }
              Database::disconnect();?>
            </select>
          </div>
          <div class="modal-footer">
            <a id="btnConfirmarClonar" class="btn btn-primary" href="#">Confirmar</a>
            <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalRevision" tabindex="-1" role="dialog" aria-labelledby="modalRevisionLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form id="formRevision" method="post">
            <div class="modal-header">
              <h5 class="modal-title" id="modalRevisionLabel">Nueva Revisión</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <p>¿Está seguro que desea generar una nueva revisión?</p>
              <div class="form-group">
                <label for="motivoRevision">Motivo de la revisión:</label>
                <textarea id="motivoRevision" name="motivoRevision" class="form-control" required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">Confirmar</button>
              <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
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
      let accionPendiente = null;
      let id_lista_corte = null;
      var selectTarea;
      $(document).ready(function() {

        selectTarea = $('#select_tarea_clonar');
        selectTarea.select2({
          dropdownParent: $('#modalClonar') // ¡esto es clave!
        });

        // Setup - add a text input to each footer cell
        $('#dataTables-example666 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        var table = $('#dataTables-example666').DataTable({
          stateSave: false,
		      //searching: false,//debemos quitar esta linea para que funcione el buscador
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
          },
		      "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
            //$('td:eq(10)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
          },
          initComplete: function(){
            $('[title]').tooltip();
          }
        });
    
        // DataTable
        //var table = $('#dataTables-example666').DataTable();
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });

        let formRevision = $("#formRevision");
        let hrefToRedirect = null;

        formRevision.on("submit", function(e){
          e.preventDefault();
          //alert("La funcionalidad de nueva revisión está deshabilitada temporalmente.");
          const motivo = $("#motivoRevision").val().trim();
          if(motivo === ""){
            alert("Por favor complete el motivo de la revisión.");
            return;
          }
          const filaActiva = $("#dataTables-example666 tbody tr.selected");
          const id_lista_corte = filaActiva.data("id-lista-corte");
          formRevision.attr("action", `nuevaRevisionListaCorte.php?id_lista_corte=${id_lista_corte}`);
          this.submit();
        });

        bindAccionListaCorte("#link_ot_lc", generarOrdenTrabajoLC, "generar una Orden de trabajo", [3,4]);
        bindAccionListaCorte("#link_imprimir_lc", imprimirListaCorte, "imprimir");
        bindAccionListaCorte("#link_modificar_lc", modificarListaCorte, "modificar/revisar", [1,2,3,4]);
        bindAccionListaCorte("#link_eliminar_lc", cancelarLC, "cancelar", [1,2]);
        bindAccionListaCorte("#link_clonar_lc", clonarLC, "clonar");
        bindAccionListaCorte("#link_aprobar_lc", aprobarLC, "aprobar", [2]);

        $("#btnConfirmarAprobacion").on("click", function() {

          if (!id_lista_corte) {
            alert("No hay lista de corte seleccionada.");
            return;
          }

          const data = {ajax: true, accion: "aprobar", id_lista_corte: id_lista_corte};
          $.ajax({
            url: "aprobarListaCorte.php",
            method: "POST",
            data: data,
            success: function (resp) {
              if (resp.trim() === "ok") {
                alert("Lista de Corte aprobada correctamente.");
                $("#btnFiltrar").click(); // Refiltrar la lista
                //location.reload();
                //console.log("funcionó");
              } else {
                alert("Error: " + resp);
              }
            },
            error: function (xhr) {
              alert("Error del servidor: " + xhr.responseText);
            },
            complete: function () {
              $("#modalConfirmarAprobacion").modal("hide");
              id_lista_corte = null;
            }
          });
        });
        
        //$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#dataTables-example666 tbody tr td", function(){
          var t=$(this).parent();

          let id_lc=t.find("td:first-child").html();
          /*let id_lc_revision=t.find("td:nth-child(2)").html();
          let nro_revision = t.find("td:nth-child(8)").html();*/
		      let estado = t.find("td:nth-child(11)").html();
		  
          if(t.hasClass('selected')){
            deselectRow(t);
            get_detalle_lista_corte(0)
            $("#link_ot_lc").attr("href","#");
            $("#link_imprimir_lc").attr("href","#");
            $("#link_modificar_lc").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            //t.parent().find("tr").removeClass("selected");
            selectRow(t);
            get_detalle_lista_corte(id_lc)
            $("#link_imprimir_lc").attr("target","_blank").attr("href","imprimirListaCorte.php?id="+id_lc);
            if (estado != "Cancelada") {
              //$("#link_modificar_lc").attr("href","modificarListaCorte.php?id_lista_corte_revision="+id_lc);//old version
              $("#link_modificar_lc").attr("href","nuevaListaCorte.php?modo=update&id_lista_corte="+id_lc);
              $("#link_ot_lc").attr("href","nuevaOrdenTrabajo.php?id_lista_corte="+id_lc);
            } else {
              $("#link_modificar_lc").attr("href","#");
              $("#link_ot_lc").attr("href","#");
            }
          }
        });

        // Setup - add a text input to each footer cell
        get_detalle_lista_corte(0)

        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });
      
      });

      function validarAccionListaCorte(estadosPermitidos, nombreAccion) {
        const estado = getEstadoListaCorteSeleccionada();
        if (estado === null) {
          alert("Por favor seleccione una lista de corte para " + nombreAccion);
          return false;
        }
        if (estadosPermitidos.length && !estadosPermitidos.includes(estado.id)) {
          alert("La acción no puede ejecutarse para la lista de corte en el estado actual \"" + estado.nombre + "\"");
          return false;
        }
        return true;
      }

      function bindAccionListaCorte(selector, accion, nombreAccion, estadosPermitidos = [1,2,3,4,5,6]) {
        $(selector).on("click", function(e){
          e.preventDefault();
          if(validarAccionListaCorte(estadosPermitidos, nombreAccion)){
            accion.call(this, e);
          }
        });
      }

      function generarOrdenTrabajoLC(){ window.location.href = this.href; }
      function imprimirListaCorte(){ window.open(this.href, '_blank'); }
      function modificarListaCorte(e){
        const filaActiva = $("#dataTables-example666 tbody tr.selected");
        const numeroLc = filaActiva.data("numero-lc");
        const revision = parseInt(filaActiva.data("nro-revision"),10);
        let maxRevision = revision;
        table.rows().every(function(){
          const tr = $(this.node());
          if(tr.data("numero-lc") == numeroLc){
            const rev = parseInt(tr.data("nro-revision"),10);
            if(rev > maxRevision){
              maxRevision = rev;
            }
          }
        });
        if(revision < maxRevision){
          alert("Hay revisiones más recientes generadas para la lista de corte.");
          return;
        }
        const estado = getEstadoListaCorteSeleccionada();
        if(estado && (estado.id === 3 || estado.id === 4)){
          hrefToRedirect = this.href;
          $("#motivoRevision").val('');
          $("#modalRevision").modal("show");
        }else{
          window.location.href = this.href;
        }
      }

      function cancelarLC(){
        const filaActiva = $("#dataTables-example666 tbody tr.selected");
        const id_lista_corte = filaActiva.data("id-lista-corte");
        $("#btnEliminarListaCorte").attr("href","eliminarListaCorte.php?id="+id_lista_corte);
        $("#eliminarModal").modal("show");
      }

      function clonarLC(){
        const filaActiva = $("#dataTables-example666 tbody tr.selected");
        const id = filaActiva.data("id-lista-corte");
        const numero_lc = filaActiva.data("numero-lc");
        const revision = filaActiva.data("nro-revision");
        const proyecto = filaActiva.data("proyecto");
        const sitio = filaActiva.data("sitio");
        const subsitio = filaActiva.data("subsitio");
        const proy = filaActiva.data("proy");
        const mensaje = `¿Desea clonar la Lista de Corte #${numero_lc} Rev. ${revision} del proyecto ${proyecto} (${sitio}/${subsitio}/${proy})?`;
        $("#textoClonar").text(mensaje);
        $("#btnConfirmarClonar").off('click').on('click', function(ev){
          ev.preventDefault();
          const idTarea = selectTarea.val();
          if(!idTarea){
            alert('Debe seleccionar una tarea');
            return;
          }
          window.location.href = `clonarListaCorte.php?id_lista_corte=${id}&revision=${revision}&id_tarea=${idTarea}`;
        });
        $("#modalClonar").modal("show");
      }

      function aprobarLC() {
        const filaActiva = $("#dataTables-example666 tbody tr.selected");
        /*e.preventDefault();

        if (filaActiva.length === 0) {
          alert("Debe seleccionar una lista de corte de la tabla primero.");
          return;
        }

        let quiereAprobar=true;
        if(quiereAprobar){
          let estado=getEstadoListaCorteSeleccionada();
          if (estado.id != 2) {
            alert("No se puede aprobar la lista de corte en este estado.");
            return;
          }
        }*/
        let mensaje = "¿Desea aprobar la lista de corte seleccionada?";
        id_lista_corte = filaActiva.data("id-lista-corte");
        console.log(id_lista_corte);

        // Mostrar modal de confirmación
        $("#textoConfirmacion").html(mensaje);
        $("#modalConfirmarAprobacion").modal("show");
        //$("#btnConfirmarAprobacion").attr("href","aprobarListaCorte.php?id_lista_corte="+id_lista_corte)
      };

      function getEstadoListaCorteSeleccionada() {
        let fila_seleccionada=$("#dataTables-example666 tbody tr.selected");
        if(fila_seleccionada.length===0){
          return null;
        }
        return {
          id: parseInt(fila_seleccionada.data("estado-id"),10),
          nombre: fila_seleccionada.data("estado-nombre")
        };
      }

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    
      function get_detalle_lista_corte(id_lc){
        let datosUpdate = new FormData();
        datosUpdate.append('id_lc', id_lc);
        $.ajax({
          data: datosUpdate,
          url: 'get_detalle_lista_corte.php',
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
                  that.search( this.value ).draw();
                }
              });
            });

          }
        });
      }
	  
      function jsExportar() {
        document.location.href="exportListasCorte.php?nro="+document.getElementById('nro').value+"&fecha="+document.getElementById('fecha').value+"&fechah="+document.getElementById('fechah').value+"&estado="+document.getElementById('id_estado').value;
      }

    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
	  <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <!-- Plugin used-->
  </body>
</html>