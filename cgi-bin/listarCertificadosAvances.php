<?php
session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT cm.id,cm.numero AS numero_cm,occ.numero AS numero_occ,date_format(cm.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cm.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cm.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cm.cotizacion_dolar,cm.monto_total,cm.monto_acumulado_avances,cm.monto_acumulado_anticipos,cm.monto_acumulado_desacopios,cm.monto_acumulado_descuentos,cm.monto_acumulado_ajustes,cm.observaciones FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ=occ.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cm.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$_GET["id_certificado_maestro"]]);
$data = $q->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_tables.php');?>
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
          $ubicacion="Certificados de Avance (CM #".$data['numero_cm'].")";
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
                      if (!empty(tienePermiso(377))) { ?>
                        &nbsp;
                        <a href="nuevoCertificadoAvance.php?id=<?=$_GET["id_certificado_maestro"]?>" title="Nuevo Certificado de Avance"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo"></a>&nbsp;&nbsp;<?php
                      }
                      echo '<a href="exportCertificadosAvances.php?id_certificado_maestro='.$_GET["id_certificado_maestro"].'"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;';
                      if (!empty(tienePermiso(378))) {
                        echo '<a href="#" id="link_modificar_ot"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;&nbsp;';
                        echo '<a href="#" id="link_aprobar_avance"><img src="img/tratoHecho.png" width="24" height="20" border="0" alt="Aprobado Cliente" title="Aprobado Cliente"></a>';
                        echo '&nbsp;&nbsp;';
                      }
					            if (!empty(tienePermiso(383))) {
                        echo '<a href="#" id="link_eliminar_avance"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }
                      /*echo '<a href="#" id="link_imprimir_pl"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
					            echo '&nbsp;&nbsp;';*/?>

                      <a href="#" id="link_ver_occ" title="Ver Certificado de Avance" style="color: midnightblue;" class="fa fa-lg"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver"></a>&nbsp;
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display" id="tablaOCC">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
							<th>ID</th>
                            <th>N° CM</th>
                            <th>Fecha emision</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Monto</th>
                            <th>Acumulado avances</th>
                            <th>Acumulado anticipos</th>
                            <th>Acumulado desacopios</th>
                            <th>Acumulado descuentos</th>
                            <th>Acumulado ajustes</th>
                            <th>Observaciones</th>
							<th>Aprobado Cliente</th>
                          </tr>
                        </thead>
                        <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
							<th>ID</th>
                            <th>N° CM</th>
                            <th>Fecha emision</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Monto</th>
                            <th>Acumulado avances</th>
                            <th>Acumulado anticipos</th>
                            <th>Acumulado desacopios</th>
                            <th>Acumulado descuentos</th>
                            <th>Acumulado ajustes</th>
                            <th>Observaciones</th>
							<th>Aprobado Cliente</th>
                          </tr>
                        </tfoot>
                        <tbody><?php
                          
                          $pdo = Database::connect();
                          $sql = "SELECT cac.id,cm.numero AS numero_cm,date_format(cac.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cac.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cac.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cac.monto_total,cac.monto_acumulado_avances,cac.monto_acumulado_anticipos,cac.monto_acumulado_desacopios,cac.monto_acumulado_descuentos,cac.monto_acumulado_ajustes,cac.observaciones,cac.aprobado_cliente FROM certificados_avances_cabecera cac INNER JOIN certificados_maestros cm ON cac.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cac.id_certificado_maestro = ".$_GET["id_certificado_maestro"];
                          //echo $sql;
						  $count = 1;
                          foreach ($pdo->query($sql) as $row) {
                            echo '<tr>';
                            echo '<td class="d-none">'.$row["id"].'</td>';
							echo '<td>'.$count.'</td>';
                            echo '<td>'.$row["numero_cm"].'</td>';
                            echo '<td>'.$row["fecha_emision"].'</td>';
                            echo '<td>'.$row["fecha_inicio"].'</td>';
                            echo '<td>'.$row["fecha_fin"].'</td>';
                            echo '<td>'.$row["moneda"]." ".number_format($row["monto_total"],2).'</td>';
                            echo '<td>$'.number_format($row["monto_acumulado_avances"],2)."</td>";
                            echo '<td>$'.number_format($row["monto_acumulado_anticipos"],2)."</td>";
                            echo '<td>$'.number_format($row["monto_acumulado_desacopios"],2)."</td>";
                            echo '<td>$'.number_format($row["monto_acumulado_descuentos"],2)."</td>";
                            echo '<td>$'.number_format($row["monto_acumulado_ajustes"],2)."</td>";
                            echo '<td>'. $row["observaciones"] . '</td>';
                              if ($row["aprobado_cliente"] == 0) {
                                echo '<td>No</td>';	
                              } else {
                                echo '<td>Si</td>';	
                              }
							  $count++;
                            echo '</tr>';?>

                            <!-- <div class="modal fade" id="eliminarModal_<?=$row["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
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
                            </div> --><?php
                          }
                          Database::disconnect();?>
                        </tbody>
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
                    <h5>Detalle del Certificado de Avance
                      &nbsp;
                      <a href="#" id="link_nuevo_comprobante" title="Nuevo Comprobante" data-aprobado="0"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo"></a>
                      <!-- &nbsp;&nbsp;
                      <span id="btnAbrirModalModificarCantidades" title="Modificar Cantidades" style="cursor: pointer;"><i class='faClass fa fa-lg fa-cogs'></i></span>&nbsp;&nbsp; -->
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display" id="tablaDetalleOCC">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Descripcion</th>
                            <th>Cantidad</th>
                            <th>Unidad de Medida</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                            <th>ID Cbte.</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Descripcion</th>
                            <th>Cantidad</th>
                            <th>Unidad de Medida</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                            <th>ID Cbte.</th>
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
        $('#tablaOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $('#tablaOCC').DataTable({
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
        var table = $('#tablaOCC').DataTable();
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });
        
        $(document).on("click","#tablaOCC tbody tr td", function(){
          var t=$(this).parent();

          let id_occ=t.find("td:first-child").html();
          let aprobado = t.find("td:nth-child(14)").html();
          
          if (aprobado == "Si") {
            $("#link_nuevo_comprobante").data("aprobado","1");  
          } else {
            $("#link_nuevo_comprobante").data("aprobado","0");  
          }
          if(t.hasClass('selected')){
            deselectRow(t);
            get_detalle_certificado_avance(0)

			
            $("#link_modificar_ot").attr("href","#");
			      $("#link_aprobar_avance").attr("href","#");
            $("#link_ver_occ").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_detalle_certificado_avance(id_occ)
            
            $("#link_modificar_ot").attr("href","modificarCertificadoAvance.php?id="+id_occ);
            $("#link_aprobar_avance").attr("href","aprobarCertificadoAvance.php?id="+id_occ);
            $("#link_ver_occ").attr("href","verCertificadoAvance.php?id="+id_occ);
          }
        });

        get_detalle_certificado_avance(0)

        $('#tablaDetalleOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $("#link_modificar_ot").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de compra cliente para modificarla")
          }
        })
		
        $("#link_aprobar_avance").on("click",function(){
          let l=document.location.href;
            if(this.href==l || this.href==l+"#"){
              alert("Por favor seleccione un certificado para aprobar")
            }
        })

        $("#link_eliminar_avance").on("click",function(){
          let l=document.location.href;
          let fila_selected=$(document).find("#tablaOCC tbody tr.selected");
          console.log(fila_selected.length);

          //if(this.href==l || this.href==l+"#"){
          if(fila_selected.length==0){
            alert("Por favor seleccione un certificado para eliminarlo")
          }else{
            let aprobado=fila_selected.find("td:nth-child(14)").html();
            let id=fila_selected.find("td:nth-child(1)").html();
            console.log(id);
            if(aprobado=="Si"){
              alert("El certificado ya esta aprobado y no puede ser eliminado")
            }else{
              document.location.href="eliminarCertificadoAvance.php?id="+id
            }
          }
        })

        $("#link_ver_occ").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una orden de compra cliente para ver detalle")
          }
        })

        $("#link_nuevo_comprobante").on("click",function(){
          let aprobado = $("#link_nuevo_comprobante").data("aprobado");  
          console.log(aprobado);
          if (aprobado == "1") {
            
            let aId=[];
            $("#tablaDetalleOCC tbody tr").each(function(){
              var t=$(this);
              if(t.hasClass('selected')){
                let id_certificado_avance_detalle=t.find("td:first-child").html();
                aId.push(id_certificado_avance_detalle)
              }
            })
            if(aId.length==0){
              alert("Por favor seleccione uno o mas items del certificado de avance para generar un comprobante")
            }else{
              window.open("nuevaFacturaVenta.php?id="+aId.join(","))
            }
          } else {
            alert("El Certificado de Avance aún no ha sido aprobado por el cliente")
          }

        })
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    
      function get_detalle_certificado_avance(id_certificado_avance){
        let datosUpdate = new FormData();
        datosUpdate.append('id_certificado_avance', id_certificado_avance);
        $.ajax({
          data: datosUpdate,
          url: 'get_detalle_certificado_avance.php',
          method: "post",
          cache: false,
          contentType: false,
          processData: false,
          success: function(data){
            data = JSON.parse(data);
            $('#tablaDetalleOCC').DataTable().destroy();
            $('#tablaDetalleOCC').DataTable({
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
            var table = $('#tablaDetalleOCC').DataTable();
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

        $(document).on("click","#tablaDetalleOCC tbody tr td", function(){
          var t=$(this).parent();
          if(t.hasClass('selected')){
            deselectRow(t);
          }else{
            selectRow(t);
          }
        });
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>