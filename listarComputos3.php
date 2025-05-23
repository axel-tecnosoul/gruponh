<?php

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
      .abrirModalAprobarItem, .abrirModalCancelarItem, .abrirModalCancelarReservaItem{
        cursor: pointer;
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
							          <option value="">Todos</option><?php
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
                        Database::disconnect();?>
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
                  <h5><?php
                    echo $ubicacion;
                    /*if (!empty(tienePermiso(290))) { ?>
                      <a href="nuevoComputo.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a><?php
                    }*/?>
                    <a href="#" onclick="jsExportar();"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
                    &nbsp;&nbsp;
                    <a href="#" id="link_ver_computo"><img src="img/medalla-dorada.png" width="24" height="15" border="0" alt="Gestionar" title="Gestionar"></a>
                    &nbsp;&nbsp;
                    <a href="#" id="link_imprimir_computo"><img src="img/print.png" width="20" height="20" border="0" alt="Imprimir" title="Imprimir"></a>
                    &nbsp;&nbsp;<?php
                    if (!empty(tienePermiso(291))) {?>
                      <a href="#" id="link_items_computo"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Editar / Revisión" title="Editar / Revisión"></a>
                      &nbsp;&nbsp;<?php
                    }
                    if (!empty(tienePermiso(293))) {?>
                    <a href="#" class="accion-computo" data-accion="aprobar_completo" title="Aprobar Completo">
                      <img src="img/estrella.png" width="24" height="25">
                    </a>
                    &nbsp;&nbsp;<?php
                    }
                    if (!empty(tienePermiso(293))) {?>
                    <a href="#" class="accion-computo" data-accion="aprobar_parcial" title="Aprobar Parcial">
                      <img src="img/medalla-plateada.png" width="24" height="25">
                    </a>
                    &nbsp;&nbsp;<?php
                    }
                    if (!empty(tienePermiso(293))) {?>
                    <a href="#" class="accion-computo" data-accion="cancelar_computo" title="Cancelar Cómputo">
                      <img src="img/neg.png" width="24" height="25">
                    </a>
                    &nbsp;&nbsp;<?php
                    }
                    
                    if (!empty(tienePermiso(292))) {?>
                      <a href="#" id="link_eliminar_computo"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>
                      &nbsp;&nbsp;<?php
                    }?>
                    
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
                      <tbody><?php
                        if (!empty($_POST)) {
                          $pdo = Database::connect();
                          $sql = " SELECT s.nro_sitio, s.nro_subsitio, p.nro AS nro_proyecto, p.nombre AS nombre_proyecto, c.id AS id_computo, c.nro_revision, date_format(c.fecha,'%d/%m/%y') AS fecha_computo, cu.nombre AS nombre_cuenta, ec.estado, ec.id as id_estado,c.nro AS nro_computo,c.comentarios_revision, date_format(c.fecha,'%y%m%d') AS fecha_computo_number FROM computos c left join estados_computos ec on ec.id = c.id_estado left join cuentas cu on cu.id = c.id_cuenta_solicitante inner join tareas t on t.id = c.id_tarea inner join tipos_tarea tt on tt.id = t.id_tipo_tarea inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE 1 ";
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
                          foreach ($pdo->query($sql) as $row) {?>
                            <tr data-estado-id="<?=$row["id_estado"]?>" data-id-computo="<?=$row["id_computo"]?>">
                              <td><?=$row["nro_sitio"]?></td>
                              <td><?=$row["nro_subsitio"]?></td>
                              <td><?=$row["nro_proyecto"]?></td>
                              <td><?=$row["nombre_proyecto"]?></td>
                              <td class="d-none"><?=$row["id_computo"]?></td>
                              <td><?=$row["nro_computo"]?></td>
                              <td><?=$row["nro_revision"]?></td>
                              <td><span style="display: none;"><?=$row["fecha_computo_number"]?></span><?=$row["fecha_computo"]?></td>
                              <td><?=$row["nombre_cuenta"]?></td>
                              <td><?=$row["estado"]?></td>
                              <td><?=$row["comentarios_revision"]?></td>
                            </tr><?php
                          }
                          Database::disconnect();
                        } else {
                          $pdo = Database::connect();
                          $sql = " SELECT s.nro_sitio, s.nro_subsitio, p.nro AS nro_proyecto, p.nombre AS nombre_proyecto, c.id AS id_computo, c.nro_revision, date_format(c.fecha,'%d/%m/%y') AS fecha_computo, cu.nombre AS nombre_cuenta, ec.estado, ec.id as id_estado,c.nro AS nro_computo,c.comentarios_revision, date_format(c.fecha,'%y%m%d') AS fecha_computo_number FROM computos c left join estados_computos ec on ec.id = c.id_estado left join cuentas cu on cu.id = c.id_cuenta_solicitante inner join tareas t on t.id = c.id_tarea inner join tipos_tarea tt on tt.id = t.id_tipo_tarea inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE ec.id in (1,2,3,4) ";
                            
                          foreach ($pdo->query($sql) as $row) {?>
                            <tr data-estado-id="<?=$row["id_estado"]?>" data-id-computo="<?=$row["id_computo"]?>">
                              <td><?=$row["nro_sitio"]?></td>
                              <td><?=$row["nro_subsitio"]?></td>
                              <td><?=$row["nro_proyecto"]?></td>
                              <td><?=$row["nombre_proyecto"]?></td>
                              <td class="d-none"><?=$row["id_computo"]?></td>
                              <td><?=$row["nro_computo"]?></td>
                              <td><?=$row["nro_revision"]?></td>
                              <td><span style="display: none;"><?=$row["fecha_computo_number"]?></span><?=$row["fecha_computo"]?></td>
                              <td><?=$row["nombre_cuenta"]?></td>
                              <td><?=$row["estado"]?></td>
                              <td><?=$row["comentarios_revision"]?></td>
                            </tr><?php
                          }
                          Database::disconnect();
                        }?>
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
                          <th>Acciones</th>
                          <th>Necesidad</th>
                          <th>Aprobado</th>
                          <th>En Stock</th>
                          <th>Reservado</th>
                          <th>Pedido</th>
                          <th>Comprando</th>
                          <th>Saldo</th>
                          <th>Comentarios</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
						          <tfoot>
                        <tr>
                          <th>Concepto</th>
                          <th>Solicitado</th>
                          <th>Acciones</th>
                          <th>Necesidad</th>
                          <th>Aprobado</th>
                          <th>En Stock</th>
                          <th>Reservado</th>
                          <th>Pedido</th>
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
    
        var table = $('#dataTables-example666').DataTable({
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
            }
          },
          // "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
          //   $('td:eq(9)', nRow).addClass("editable").attr('data-id-posicion', aData[4]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
          // },
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
            console.log(table);
            console.log(this.value);
            console.log(that.search());
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });
      
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

          setEstadoIdParaItemsComputo(t);
        });
      
        let accionPendiente = null;
        let idComputoPendiente = null;

        // Marcar fila activa al hacer clic
        $(document).on("click", "#dataTables-example666 tbody tr", function () {
          $("#dataTables-example666 tbody tr").removeClass("fila-activa");
          $(this).addClass("fila-activa");
        });

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

      });
    
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
              },
              initComplete: function(){
                $('[title]').tooltip();
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
      
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <!-- Plugin used-->
  </body>
</html>