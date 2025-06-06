<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
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
          $ubicacion="Listas de Corte ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <!-- Zero Configuration  Starts-->
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php
                      echo $ubicacion; 
                      if (!empty(tienePermiso(315))) { ?>
                        &nbsp;
                        <!--<a href="nuevaListaCorte.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva" title="Nueva"></a>&nbsp;&nbsp;--><?php
                      } ?>
                      <a href="exportListasCorte.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>
                      &nbsp;&nbsp;<?php 
                      //ACCIONES nueva lista, editar lista, ordenes de trabajo, consumos, exportar, imprimir
                      if (!empty(tienePermiso(316))) {
                        echo '<a href="#" id="link_modificar_lc"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      /*echo '<a href="#" id="link_ver_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Detalle" title="Ver Detalle"></a>';
                      echo '&nbsp;&nbsp;';*/
                      echo '<a href="#" id="link_imprimir_lc"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
                      echo '&nbsp;&nbsp;';
                      if (!empty(tienePermiso(317))) {
                        echo '<a href="#" id="link_eliminar_lc"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(315))) { ?>
                        <a href="#" id="link_clonar_lc"><img src="img/icon_ejecutar.png" width="24" height="25" border="0" alt="Clonar" title="Clonar"></a>&nbsp;&nbsp;<?php
                      }?>
                      <a href="#" id="link_ot_lc" title="Nueva OT"><i style="width: 24px; height: 20px;color: midnightblue;" class='fa fa-lg fa-briefcase'></i></a><?php
                      /*if (!empty(tienePermiso(328))) {
                        echo '<a href="#" id="link_nuevo_conjunto"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Conjunto" title="Nuevo Conjunto"></a>';
                        echo '&nbsp;&nbsp;';
                      }*/?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th class="d-none"></th>
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
                        </thead>
                        <tbody><?php 
                          include 'database.php';
                          $pdo = Database::connect();
                          $sql = "SELECT lc.id, lcr.numero, lcr.nro_revision, lcr.nombre,p.nro, date_format(lcr.fecha,'%d/%m/%y'), e.estado,lcr.adjunto,s.nro_sitio,s.nro_subsitio,lcr.id AS id_lista_corte_revision,date_format(lcr.fecha,'%y%m%d'),p.nombre FROM listas_corte lc INNER JOIN listas_corte_revisiones lcr ON lcr.id_lista_corte=lc.id inner join estados_lista_corte e on e.id = lcr.id_estado_lista_corte inner join proyectos p on p.id = lcr.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE lc.anulado = 0 "; 
                          //echo $sql;
                          foreach ($pdo->query($sql) as $row) {
                            echo '<tr>';
                            echo '<td class="d-none">'. $row["id_lista_corte_revision"] . '</td>';
                            echo '<td class="d-none">'. $row[0] . '</td>';
                            if (empty($row[9])) {
                              echo '<td>'.$row[8].'</td>';
                              echo '<td>0</td>';
                            } else {
                              echo '<td>'.$row[9].'</td>';
                              echo '<td>'.$row[8].'</td>';
                            }
                            echo '<td>'. $row[4] . '</td>';
							echo '<td>'. $row[12] . '</td>';
                            echo '<td>'. $row[1] . '</td>';
                            echo '<td>'. $row[2] . '</td>';
                            
                            echo '<td><span style="display: none;">'. $row[11] . '</span>'. $row[5] . '</td>';
                            if (empty($row[7])) {
                              echo '<td>&nbsp;</td>';	
                            } else {
                              echo '<td><a target="_blank" href="adjuntos_lc/'.$row[7].'"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Plano" title="Ver Plano"></a></td>';
                            }
                            echo '<td>'. $row[6] . '</td>';
                            echo '</tr>';?>

                            <div class="modal fade" id="eliminarModal_<?=$row["id_lista_corte_revision"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                              <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
                                    <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                  </div>
                                  <div class="modal-body">¿Está seguro que desea cancelar la Lista de Corte?</div>
                                  <div class="modal-footer">
                                    <a href="eliminarListaCorte.php?id=<?=$row["id"]; ?>" class="btn btn-primary">Eliminar</a>
                                    <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
                                  </div>
                                </div>
                              </div>
                            </div><?php
                          }
                          Database::disconnect();?>
                        </tbody>
						            <tfoot>
                          <tr>
                            <th class="d-none"></th>
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
                      /*echo '<a href="#" id="link_ver_conjunto_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
                      echo '&nbsp;&nbsp;';
                      if (!empty(tienePermiso(329))) {
                        echo '<a href="#" id="link_modificar_conjunto"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(330))) {
                        echo '<a href="#" id="link_eliminar_conjunto"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      if (!empty(tienePermiso(331))) {
                        echo '<a href="#" id="link_nueva_posicion"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nueva Posición" title="Nueva Posición"></a>';
                        echo '&nbsp;&nbsp;';
                      }*/?>
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
	
	<div style="width: 0;height: 0;display: none;">
      <select id="select_estado_base"><?php
        $pdo = Database::connect();
        $sql = "SELECT id,estado FROM estados_lista_corte";
        foreach ($pdo->query($sql) as $row) {
          echo '<option value="'.$row["id"].'">'.$row["estado"].'</option>';
        }
        Database::disconnect();?>
      </select>
    </div>
	
	<?php
    $pdo = Database::connect();
    $sql = "SELECT `id`, `id_lista_corte`, `nombre`, `cantidad`, `peso`, `id_estado_lista_corte_conjuntos` FROM `listas_corte_conjuntos` WHERE 1 "; 
    foreach ($pdo->query($sql) as $row) {
      $sql2 = "select count(*) cant from lista_corte_posiciones where id_lista_corte_conjunto = ".$row[0];
      $q2 = $pdo->prepare($sql2);
      $q2->execute();
      $data2 = $q2->fetch(PDO::FETCH_ASSOC);
      if (empty($data2['cant'])) {?>
        <div class="modal fade" id="eliminarModalConjunto_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el Detalle?</div>
              <div class="modal-footer">
                <a href="eliminarConjuntoListaCorte.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
              </div>
            </div>
          </div>
        </div><?php
		  } else {?>
        <div class="modal fade" id="eliminarModalConjunto_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Alerta</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">El Conjunto no puede ser eliminado debido a que tiene Posiciones sin cancelar.</div>
              <div class="modal-footer">
                <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
              </div>
            </div>
          </div>
        </div><?php
      }
    }
	  Database::disconnect();?>
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

        /*$(".selectOneFromTableLC").on("click",function(e){
          var nodes = $('#dataTables-example666').DataTable().rows().nodes();
          var selectedRows = $(nodes).filter('.selected');
          console.log(selectedRows);
          if(selectedRows.length!=1){
            e.preventDefault();
          }
          if(selectedRows.length==0){
            alert("Seleccione una fila")
          }
          if(selectedRows.length>1){
            alert("Seleccione solamente una fila")
          }
        })

        $(".selectOneFromTableLCD").on("click",function(e){
          var nodes = $('#dataTables-example667').DataTable().rows().nodes();
          var selectedRows = $(nodes).filter('.selected');
          console.log(selectedRows);
          if(selectedRows.length!=1){
            e.preventDefault();
          }
          if(selectedRows.length==0){
            alert("Seleccione una fila")
          }
          if(selectedRows.length>1){
            alert("Seleccione solamente una fila")
          }
        })*/

        // Setup - add a text input to each footer cell
        $('#dataTables-example666 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

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
            }
          },
		  "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                $('td:eq(10)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
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
              that.search( this.value ).draw();
            }
          });
        });

        $("#link_ot_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una lista de corte para generar una Orden de trabajo")
          }
        })
        $("#link_ver_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una lista de corte para ver detalle")
          }
        })
        $("#link_imprimir_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una lista de corte para imprimir")
          }
        })
        $("#link_eliminar_lc").on("click",function(){
          let target=this.dataset.target;
          if(target==undefined || target=="#"){
            alert("Por favor seleccione una lista de corte para cancelar")
          }
        })
        $("#link_modificar_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una lista de corte para modificar/revisar")
          }
        })
        $("#link_clonar_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una lista de corte para clonar")
          }
        })
        $("#link_nuevo_conjunto").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una lista de corte para crear un conjunto")
          }
        })
        
        //$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#dataTables-example666 tbody tr td", function(){
          var t=$(this).parent();

          let id_lc=t.find("td:first-child").html();
          let id_lc_revision=t.find("td:nth-child(2)").html();
          let nro_revision = t.find("td:nth-child(8)").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            get_detalle_lista_corte(0)
            $("#link_ver_lc").attr("href","#");
            $("#link_ot_lc").attr("href","#");
            $("#link_imprimir_lc").attr("href","#");
            //$("#link_eliminar_lc").attr("href","#");
            $("#link_eliminar_lc").attr("data-target","#");
            $("#link_modificar_lc").attr("href","#");
            $("#link_clonar_lc").attr("href","#");
            $("#link_nuevo_conjunto").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            //t.parent().find("tr").removeClass("selected");
            selectRow(t);
            get_detalle_lista_corte(id_lc)
            $("#link_ver_lc").attr("href","verListaCorte.php?id="+id_lc);
            $("#link_ot_lc").attr("href","nuevaOrdenTrabajo.php?id_lista_corte="+id_lc);
            $("#link_imprimir_lc").attr("href","imprimirListaCorte.php?id="+id_lc);
            $("#link_nuevo_conjunto").attr("href","nuevoConjuntoListaCorte.php?id="+id_lc);
            //$("#link_modificar_lc").attr("href","modificarListaCorte.php?id_lista_corte="+id_lc_revision+"&revision="+nro_revision);
            $("#link_modificar_lc").attr("href","modificarListaCorte.php?id_lista_corte_revision="+id_lc);
            $("#link_clonar_lc").attr("href","clonarListaCorte.php?id_lista_corte_revision="+id_lc);
            //$("#link_clonar_lc").attr("href","clonarListaCorte.php?id_lista_corte="+id_lc_revision+"&revision="+nro_revision);
            /*$("#link_eliminar_lc").attr("data-toggle","modal");*/
            $("#link_eliminar_lc").attr("data-target","#eliminarModal_"+id_lc);
            $("#link_eliminar_lc").on("click",function(){
              $("#eliminarModal_"+id_lc).modal("show")
            })
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
              url: "modificarEstadoListaCorte.php",
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
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
      
      $(document).ready(function() {
        // Setup - add a text input to each footer cell
        /*$('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });
      
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
              that.search( this.value ).draw();
            }
          });
        });*/

        get_detalle_lista_corte(0)
        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $("#link_ver_conjunto_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un conjunto para ver detalle")
          }
        })
        $("#link_modificar_conjunto").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un conjunto para modificar")
          }
        })
        $("#link_eliminar_conjunto").on("click",function(){
          /*let l=document.location.href;
          if(this.href==l || this.href==l+"#"){*/
          let target=this.dataset.target;
          if(target==undefined || target=="#"){
            alert("Por favor seleccione un conjunto para eliminar")
          }
        })
        $("#link_nueva_posicion").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un conjunto para agregar conceptos y posiciones")
          }
        })

      });
    
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
          //rowsGroup:[0,1],
          //order: [[2, 'asc']],
          /*rowGroup: {
              dataSrc: 0
          },*/
          success: function(data){
            //console.log(data);
            data = JSON.parse(data);
            //console.log(data);

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
        
            //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
            $(document).on("click","#dataTables-example667 tbody tr td", function(){
              var t=$(this).parent();
              //t.parent().find("tr").removeClass("selected");

              let id_con=t.find("td:first-child").html();
              if(t.hasClass('selected')){
                deselectRow(t);
                //get_posiciones(id_con)
                $("#link_ver_conjunto_lc").attr("href","#");
                $("#link_modificar_conjunto").attr("href","#");
                $("#link_eliminar_conjunto").attr("data-target","#");
                $("#link_nueva_posicion").attr("href","#");
              }else{
                table.rows().nodes().each( function (rowNode, index) {
                  $(rowNode).removeClass("selected");
                });
                selectRow(t);
                //get_posiciones(id_con)
                $("#link_ver_conjunto_lc").attr("href","verConjuntoListaCorte.php?id="+id_con);
                $("#link_modificar_conjunto").attr("href","modificarConjuntoListaCorte.php?id="+id_con);
                $("#link_nueva_posicion").attr("href","nuevaPosicionListaCorte.php?id="+id_con);
                $("#link_eliminar_conjunto").attr("data-toggle","modal");
                $("#link_eliminar_conjunto").attr("data-target","#eliminarModalConjunto_"+id_con);
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