<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
include 'database.php';
require_once 'manejarFiltros.php';

$filters = gestionarFiltros('listarCertificadosMaestros');

$occ = $filters['occ'] ?? '';
$fecha = $filters['fecha'] ?? '';
$fechah = $filters['fechah'] ?? '';?>

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
          $ubicacion="Certificados Maestros ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-body">
                    <form class="form-inline theme-form mt-3" name="form1" method="post" action="listarCertificadosMaestros.php">
                      <div class="form-group mb-0">
                        Nro OCC:&nbsp;<input class="form-control" size="3" type="text" value="<?=htmlspecialchars($occ, ENT_QUOTES, 'UTF-8')?>" name="occ">
                      </div>
                      <div class="form-group mb-0">
                        Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?=htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8')?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?=htmlspecialchars($fechah, ENT_QUOTES, 'UTF-8')?>" name="fechah">
                      </div>
                      <div class="form-group mb-0">
                        <button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarCertificadosMaestros.php'">Buscar</button>
                        <a href="listarCertificadosMaestros.php?clear_filters=1" class="btn btn-secondary ml-2">Limpiar</a>
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
                      if (!empty(tienePermiso(374))) { ?>
                        <a href="nuevoCertificadoMaestro.php" title="Nuevo Certificado Maestro" style="color: midnightblue;" class="fa fa-lg"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo">CM</a>&nbsp;&nbsp;<?php
                      }
                      //echo '<a href="exportCertificadosMaestro.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;';?>
                      <a href="#" id="link_exportar_certificado"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(375))) {?>
                        <a href="#" id="link_modificar_ot"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(382))) {?>
                        <a href="#" id="link_eliminar_maestro"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      /*echo '<a href="#" id="link_imprimir_pl"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
					            echo '&nbsp;&nbsp;';*/?>

                      <a href="#" id="link_ver_occ" title="Ver Certificado Maestro" style="color: midnightblue;" class="fa fa-lg"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver">CM</a>&nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(376))) { ?>
                        <a href="#" id="link_nuevo_consumo" title="Ver Certificados de Avance"><i style="width: 72px; height: 20px;color: midnightblue;" class='fa fa-lg fa-certificate'>CA</i></a><?php
                      }?>
                      <a href="#" id="link_nuevo_anticipo" title="Nuevo Anticipo" style="color: midnightblue;" class="fa fa-lg"><img src="img/dolar.png" width="24" height="25" border="0" alt="Nuevo Anticipo"></a>
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display" id="tablaOCC">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>N° CM</th>
                            <th>N° OCC</th>
                            <th>Fecha emision</th>
                            <th>% Anticipo</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Monto</th>
                            <th>Cotiz. dolar</th>
                            <th>Monto avances</th>
                            <th>Monto anticipos</th>
                            <th>Monto desacopios</th>
                            <th>Monto descuentos</th>
                            <th>Monto ajustes</th>
                            <th>Saldo Pendiente</th>
                            <th>Observaciones</th>
                            <th class="d-none">Cant CA</th>
                          </tr>
                        </thead>
                        <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>N° CM</th>
                            <th>N° OCC</th>
                            <th>Fecha emision</th>
                            <th>% Anticipo</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Monto</th>
                            <th>Cotiz. dolar</th>
                            <th>Monto avances</th>
                            <th>Monto anticipos</th>
                            <th>Monto desacopios</th>
                            <th>Monto descuentos</th>
                            <th>Monto ajustes</th>
                            <th>Saldo Pendiente</th>
                            <th>Observaciones</th>
                            <th class="d-none">Cant CA</th>
                          </tr>
                        </tfoot>
                        <tbody><?php 
                          if (!empty($filters)) {
							  
                            $pdo = Database::connect();
                            $sql = "SELECT cm.id AS id_cm, occ.numero AS numero_occ,date_format(cm.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cm.fecha_inicio,'%d/%m/%y') AS fecha_inicio,CASE WHEN cm.fecha_fin = '0000-00-00' THEN '-' ELSE date_format(cm.fecha_fin,'%d/%m/%y') END AS fecha_fin, cm.porcentaje_anticipo, m.moneda,cm.cotizacion_dolar,cm.monto_total,cm.monto_acumulado_avances,cm.monto_acumulado_anticipos,cm.monto_acumulado_desacopios,cm.monto_acumulado_descuentos,cm.monto_acumulado_ajustes,cm.observaciones,(SELECT ca2.monto_anticipo FROM certificados_anticipos ca2 WHERE ca2.id_certificado_maestro=cm.id ORDER BY ca2.id DESC LIMIT 1) AS monto_anticipo_registrado,(SELECT COUNT(ca.id) FROM certificados_avances_cabecera ca WHERE ca.id_certificado_maestro=cm.id) AS cant_ca FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ=occ.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE 1";
                            if (!empty($occ)) {
                              $sql .= " AND occ.numero = '".$occ."' ";
                            }
                            if (!empty($fecha)) {
                              $sql .= " AND cm.fecha_emision >= '".$fecha."' ";
                            }
                            if (!empty($fechah)) {
                              $sql .= " AND cm.fecha_emision <= '".$fechah."' ";
                            }

                            foreach ($pdo->query($sql) as $row) {
                              $sql2 = "SELECT COALESCE(sum(`monto_total`),0) monto_total, COALESCE(sum(`monto_acumulado_avances`),0) monto_acumulado_avances, COALESCE(sum(`monto_acumulado_anticipos`),0) monto_acumulado_anticipos, COALESCE(sum(`monto_acumulado_desacopios`),0) monto_acumulado_desacopios, COALESCE(sum(`monto_acumulado_descuentos`),0) monto_acumulado_descuentos, COALESCE(sum(`monto_acumulado_ajustes`),0) monto_acumulado_ajustes FROM `certificados_avances_cabecera` WHERE `id_certificado_maestro` = ? ";
                              $q2 = $pdo->prepare($sql2);
                              $q2->execute([$row["id_cm"]]);
                              $data2 = $q2->fetch(PDO::FETCH_ASSOC);
                              
                              $montoTotalAvances = (float) ($data2["monto_acumulado_avances"] ?? 0);
                              $montoTotalAnticipos = (float) ($data2["monto_acumulado_anticipos"] ?? 0);
                              if ($row["monto_anticipo_registrado"] !== null) {
                                $montoTotalAnticipos = (float) $row["monto_anticipo_registrado"];
                              }
                              $montoTotalDesacopios = (float) ($data2["monto_acumulado_desacopios"] ?? 0);
                              $montoTotalDescuentos = (float) ($data2["monto_acumulado_descuentos"] ?? 0);
                              $montoTotalAjustes = (float) ($data2["monto_acumulado_ajustes"] ?? 0);
                              $montoTotalCertificado = (float) ($row["monto_total"] ?? 0);
                              
                              $saldoPendiente=$montoTotalCertificado-$montoTotalAvances-$montoTotalAnticipos-$montoTotalDesacopios-$montoTotalDescuentos-$montoTotalAjustes?>

                              <tr data-tiene-anticipo="<?=$row["monto_anticipo_registrado"] !== null ? '1' : '0'?>">
                                <td class="d-none"><?=$row["id_cm"]?></td>
                                <td><?=$row["id_cm"]?></td>
                                <td><?=$row["numero_occ"]?></td>
                                <td><?=$row["fecha_emision"]?></td>
                                <td><?=$row["porcentaje_anticipo"]?>%</td>
                                <td><?=$row["fecha_inicio"]?></td>
                                <td><?=$row["fecha_fin"]?></td>
                                <td><?=$row["moneda"]." ".number_format($row["monto_total"],2)?></td>
                                <td>$<?=$row["cotizacion_dolar"]?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalAvances,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalAnticipos,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalDesacopios,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalDescuentos,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($montoTotalAjustes,2)?></td>
                                <td><?=$row["moneda"]." ".number_format($saldoPendiente,2)?></td>
                                <td><?= $row["observaciones"]?></td>
                                <td class="d-none"><?= $row["cant_ca"]?></td>
                              </tr><?php
                            }
                            Database::disconnect();
                          }?>
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
                    <h5>Detalle del Certificado Maestro
                      <!-- &nbsp;&nbsp;
                      <span id="btnAbrirModalModificarCantidades" title="Modificar Cantidades" style="cursor: pointer;"><i class='faClass fa fa-lg fa-cogs'></i></span>&nbsp;&nbsp; -->
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display" id="tablaDetalleOCC">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Proyecto</th>
                            <th>Sitio</th>
                            <th>Subsitio</th>
                            <th>Descripcion</th>
                            <th>Cantidad</th>
                            <th>Unidad de Medida</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                            <th>Aperturado</th>
                            <th>Lote</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>Proyecto</th>
                            <th>Sitio</th>
                            <th>Subsitio</th>
                            <th>Descripcion</th>
                            <th>Cantidad</th>
                            <th>Unidad de Medida</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                            <th>Aperturado</th>
                            <th>Lote</th>
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

    <div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-labelledby="modalEliminarLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEliminarLabel">Confirmación</h5>
            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          </div>
          <div class="modal-body">¿Está seguro que desea eliminar el certificado maestro?</div>
          <div class="modal-footer">
            <a href="#" class="btn btn-primary" id="btnEliminarMaestro">Eliminar</a>
            <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
          </div>
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
        $('#tablaOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $('#tablaOCC').DataTable({
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
        
        //$('#tablaOCC').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaOCC tbody tr td", function(){
          var t=$(this).parent();

          let id_cm=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            get_detalle_certificado_maestro(0)

            $("#link_exportar_certificado").attr("href","#");
            $("#link_modificar_ot").attr("href","#");
            $("#link_nuevo_consumo").attr("href","#");
            $("#link_nuevo_anticipo").attr("href","#");
            $("#link_ver_occ").attr("href","#");
          }else{
            //t.parent().find("tr").removeClass("selected");
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            get_detalle_certificado_maestro(id_cm)
            
            $("#link_exportar_certificado").attr("href","exportCertificadosMaestro.php?id="+id_cm);
            $("#link_modificar_ot").attr("href","modificarCertificadoMaestro.php?id="+id_cm);
            $("#link_nuevo_consumo").attr("href","listarCertificadosAvances.php?id_certificado_maestro="+id_cm);
            $("#link_nuevo_anticipo").attr("href","nuevoAnticipoCertificadoMaestro.php?id_certificado_maestro="+id_cm);
            $("#link_ver_occ").attr("href","verCertificadosMaestro.php?id="+id_cm);
          }
        });

        get_detalle_certificado_maestro(0)

        $('#tablaDetalleOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $("#link_exportar_certificado").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un certificado maestro para exportar")
          }
        })

        $("#link_modificar_ot").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un certificado maestro para modificarla")
          }
        })

        $("#link_eliminar_maestro").on("click",function(){
          let fila_selected=$(document).find("#tablaOCC tbody tr.selected");
          console.log(fila_selected.length);

          //if(this.href==l || this.href==l+"#"){
          if(fila_selected.length==0){
            alert("Por favor seleccione un certificado para eliminarlo")
          }else{
            let cant_ca=fila_selected.find("td:nth-child(16)").html();
            console.log(cant_ca);
            let id=fila_selected.find("td:nth-child(1)").html();
            console.log(id);
            if(cant_ca=="0"){
              $("#btnEliminarMaestro").attr("href","eliminarCertificadoMaestro.php?id="+id);
              $("#modalEliminar").modal("show");
            }else{
              alert("El certificado no puede ser eliminado debido a que posee avances")
            }
          }
        })

        $("#link_nuevo_consumo").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un certificado maestro para ver sus certificados de avance")
          }
        })

        $("#link_nuevo_anticipo").on("click", function(e) {
          let filaSeleccionada = $("#tablaOCC tbody tr.selected");

          if (filaSeleccionada.length === 0) {
            e.preventDefault();
            alert("Por favor seleccione un certificado maestro para cargar un anticipo");
            return;
          }

          let textoPorcentaje = filaSeleccionada.find("td:nth-child(5)").text().trim().replace("%", "").replace(",", ".");

          let porcentajeAnticipo = parseFloat(textoPorcentaje);
          let tieneAnticipo = filaSeleccionada.attr("data-tiene-anticipo") === "1";
          let advertencias = [];

          if (tieneAnticipo) {
            advertencias.push("El Certificado Maestro ya tiene un anticipo registrado. Si continua, editara ese anticipo.");
          }

          if (porcentajeAnticipo === 0) {
            advertencias.push("El Certificado Maestro tiene un porcentaje de anticipo del 0%.");
          }

          if (advertencias.length > 0) {
            let continuar = confirm(advertencias.join("\n\n") + "\n\n¿Esta seguro de que desea continuar?");

            if (!continuar) {
              e.preventDefault();
            }
          }
        });

        $("#link_ver_occ").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un certificado maestro para ver detalle")
          }
        })
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    
      function get_detalle_certificado_maestro(id_certificado_maestro){
        let datosUpdate = new FormData();
        datosUpdate.append('id_certificado_maestro', id_certificado_maestro);
        $.ajax({
          data: datosUpdate,
          url: 'get_detalle_certificado_maestro.php',
          method: "post",
          cache: false,
          contentType: false,
          processData: false,
          success: function(data){
            //console.log(data);
            data = JSON.parse(data);
            //console.log(data);
            $('#tablaDetalleOCC').DataTable().destroy();
            $('#tablaDetalleOCC').DataTable({
              stateSave: false,
              responsive: false,
              "columnDefs": [{
                "targets": [0],
                "className": 'd-none'
              }],
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
                $('td:eq(7)', nRow).addClass("editable").attr('data-id-posicion', aData[0]).attr('data-id-estado', aData[11]).attr("title","Doble click para editar");
              },*/
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
        
            //$('#tablaDetalleOCC').find("tbody tr td").not(":last-child").on( 'click', function () {
            /*$(document).on("click","#tablaDetalleOCC tbody tr td", function(){
              var t=$(this).parent();
              //t.parent().find("tr").removeClass("selected");

              let id_pos_ot=t.find("td:first-child").html();
              let cantMaxima=t.find("td:nth-child(5)").html();
              if(t.hasClass('selected')){
                deselectRow(t);
                $("#btnAbrirModalModificarCantidades").data("id","");
                $("#cantMaxima").html("")
                $("#id_posicion_ot").val("")
              }else{
                table.rows().nodes().each( function (rowNode, index) {
                  $(rowNode).removeClass("selected");
                });
                selectRow(t);
                $("#btnAbrirModalModificarCantidades").data("id",id_pos_ot);
                $("#cantMaxima").html(cantMaxima)
                $("#id_posicion_ot").val(id_pos_ot)
              }
            });*/
          }
        });
      }
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>