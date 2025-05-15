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
	<style>
	.truncate {
	  max-width:50px;
	  white-space: nowrap;
	  overflow: hidden;
	  text-overflow: ellipsis;
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
          $ubicacion="Polizas ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarPolizas.php">
					  <div class="form-group mb-0">
						N.Póliza:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro">
					  </div>
					  <div class="form-group mb-0">
						Nro.OCC:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['occ'])) echo $_POST['occ'] ?>" name="occ">
					  </div>
					  <div class="form-group mb-0">
						Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fecha'])) echo $_POST['fecha'] ?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?php if (isset($_POST['fechah'])) echo $_POST['fechah'] ?>" name="fechah">
					  </div>
					  <div class="form-group mb-0">
						Aseguradora:&nbsp;<input class="form-control" size="20" type="text" value="<?php if (isset($_POST['aseguradora'])) echo $_POST['aseguradora'] ?>" name="aseguradora">
					  </div>
					  <div class="form-group mb-0">
						Beneficiario:&nbsp;<input class="form-control" size="20" type="text" value="<?php if (isset($_POST['beneficiario'])) echo $_POST['beneficiario'] ?>" name="beneficiario">
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarPolizas.php'">Buscar</button>
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
                      if (!empty(tienePermiso(380))) { ?>
                        &nbsp;
                        <a href="nuevaPoliza.php" title="Nueva Poliza"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo"></a>&nbsp;&nbsp;<?php
                      }
                      echo '<a href="exportPoliza.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;';
                      if (!empty(tienePermiso(381))) {
                        echo '<a href="#" id="link_modificar_ot"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
                        echo '&nbsp;';
                      }
                      /*if (!empty(tienePermiso(317))) {
                        echo '<a href="#" id="link_cancelar_lc"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
                        echo '&nbsp;&nbsp;';
                      }*/
                      /*echo '<a href="#" id="link_imprimir_pl"><img src="img/print.png" width="25" height="20" border="0" alt="Imprimir" title="Imprimir"></a>';
					            echo '&nbsp;&nbsp;';*/?>

                      <a href="#" id="link_ver_occ" title="Ver Poliza"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver"></a>&nbsp;
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="tablaOCC">
                        <thead>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>OCC</th>
                            <th>Empresa</th>
                            <th>Fecha solicitud</th>
                            <th>Fecha renovación</th>
                            <th>N° Poliza</th>
                            <th>Aseguradora</th>
                            <th>Beneficiario</th>
                            <th>Tipo de cobertura</th>
                            <th>Vigencia desde</th>
                            <th>Vigencia hasta</th>
                            <th>Monto garantía</th>
                            <th>Moneda</th>
                            <th>Objeto del seguro</th>
                            <th>Activa</th>
                          </tr>
                        </thead>
                        <tfoot>
                          <tr>
                            <th class="d-none">ID</th>
                            <th>OCC</th>
                            <th>Empresa</th>
                            <th>Fecha solicitud</th>
                            <th>Fecha renovación</th>
                            <th>N° Poliza</th>
                            <th>Aseguradora</th>
                            <th>Beneficiario</th>
                            <th>Tipo de cobertura</th>
                            <th>Vigencia desde</th>
                            <th>Vigencia hasta</th>
                            <th>Monto garantía</th>
                            <th>Moneda</th>
                            <th>Objeto del seguro</th>
                            <th>Estado</th>
                          </tr>
                        </tfoot>
                        <tbody><?php 
                          if (!empty($_POST)) {
							  
                          $pdo = Database::connect();
                          $sql = "SELECT p.id AS id_poliza,occ.numero AS numero_occ, date_format(p.fecha_solicitud,'%d/%m/%y') AS fecha_solicitud,c1.nombre AS usuario_solicitante,p.numero,c2.nombre AS proveedor,c3.nombre as beneficiario,tcp.tipo,date_format(p.vigencia_desde,'%d/%m/%y') AS vigencia_desde,date_format(p.vigencia_hasta,'%d/%m/%y') AS vigencia_hasta,p.monto_garantia,m.moneda,p.descripcion_objetivo,IF(p.activa=1,'Activa','Eliminada') AS activa, e.empresa, date_format(p.fecha_renovacion,'%d/%m/%y') AS fecha_renovacion,p.vigencia_hasta AS vigencia_hasta_inv,p.fecha_renovacion AS fecha_renovacion_inv FROM polizas p INNER JOIN occ ON p.id_occ=occ.id left JOIN cuentas c1 ON p.id_cuenta_solicitante=c1.id INNER JOIN cuentas c2 ON p.id_cuenta_proveedor_aseguradora=c2.id INNER JOIN cuentas c3 ON p.id_cuenta_cliente_beneficiario=c3.id INNER JOIN tipos_cobertura_polizas tcp ON p.id_tipo_cobertura=tcp.id INNER JOIN monedas m ON p.id_moneda=m.id left join empresas e on e.id = p.id_empresa WHERE 1";
                          
							if (!empty($_POST['nro'])) {
								$sql .= " AND p.numero = '".$_POST['nro']."' ";
							}
							if (!empty($_POST['occ'])) {
								$sql .= " AND occ.numero = '".$_POST['occ']."' ";
							}
							if (!empty($_POST['fecha'])) {
								$sql .= " AND p.fecha_solicitud >= '".$_POST['fecha']."' ";
							}
							if (!empty($_POST['fechah'])) {
								$sql .= " AND p.fecha_solicitud <= '".$_POST['fechah']."' ";
							}
							if (!empty($_POST['aseguradora'])) {
								$sql .= " AND c2.nombre = '".$_POST['aseguradora']."' ";
							}
							if (!empty($_POST['beneficiario'])) {
								$sql .= " AND c3.nombre = '".$_POST['beneficiario']."' ";
							}
						  
						  foreach ($pdo->query($sql) as $row) {
                            echo '<tr>';
                            echo '<td class="d-none">'.$row["id_poliza"].'</td>';
                            echo '<td>'.$row["numero_occ"].'</td>';
                            echo '<td>'.$row["empresa"].'</td>';
                            echo '<td>'.$row["fecha_solicitud"].'</td>';
							if (!empty($row["fecha_renovacion_inv"])) {
								$fechaRen = new DateTime($row["fecha_renovacion_inv"]);
								$fechaHoy = new DateTime();
								$diferencia = $fechaHoy->diff($fechaRen);
								$diferencia = $diferencia->days * ($fechaHoy > $fechaRen ? -1 : 1);
								$color = "#000000";
								if ($diferencia < 7) {
									$color = "#28a745";
								} 
								if ($diferencia < 0) {
									$color = "#ff0000";
								}
								echo '<td style="color:'.$color.';">'.$row["fecha_renovacion"].'</td>';
							} else {
								echo '<td>'.$row["fecha_renovacion"].'</td>';
							}
							echo '<td>'.$row["numero"].'</td>';
                            echo '<td>'.$row["proveedor"].'</td>';
                            echo '<td>'.$row["beneficiario"].'</td>';
                            echo '<td>'.$row["tipo"].'</td>';
                            echo '<td>'.$row["vigencia_desde"].'</td>';
							if (!empty($row["vigencia_hasta_inv"])) {
								$fechaVig = new DateTime($row["vigencia_hasta_inv"]);
								$fechaHoy = new DateTime();
								$diferencia = $fechaHoy->diff($fechaVig);
								$diferencia = $diferencia->days * ($fechaHoy > $fechaVig ? -1 : 1);
								$color = "#000000";
								if ($diferencia < 7) {
									$color = "#28a745";
								} 
								if ($diferencia < 0) {
									$color = "#ff0000";
								}
								echo '<td style="color:'.$color.';">'.$row["vigencia_hasta"].'</td>';
							} else {
								echo '<td>'.$row["vigencia_hasta"]."</td>";
							}
                            echo '<td>$'.number_format($row["monto_garantia"],2)."</td>";
                            echo '<td>'.$row["moneda"]."</td>";
                            echo '<td>'.$row["descripcion_objetivo"]."</td>";
                            echo '<td>'.$row["activa"]."</td>";
                            echo '</tr>';
							}
							Database::disconnect();
						  }
						  ?>
                        </tbody>
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

          let id_occ=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);

            $("#link_modificar_ot").attr("href","#");
            $("#link_nuevo_consumo").attr("href","#");
            $("#link_ver_occ").attr("href","#");
          }else{
            //t.parent().find("tr").removeClass("selected");
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            
            $("#link_modificar_ot").attr("href","modificarPoliza.php?id="+id_occ);
            //$("#link_nuevo_consumo").attr("href","listarCertificadosAvances.php?id_certificado_maestro="+id_occ);
            $("#link_ver_occ").attr("href","verPoliza.php?id="+id_occ);
          }
        });

        $('#tablaDetalleOCC tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        });

        $("#link_modificar_ot").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una poliza para modificarla")
          }
        })

        $("#link_cancelar_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una poliza para eliminarla")
          }
        })

        $("#link_ver_occ").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione una poliza para ver detalle")
          }
        })
      
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
	  
	  $(document).ready(function() {
		var table = $('#tablaOCC').DataTable();
		table.search('Activa').draw();  // Presetear búsqueda después de inicializar
	});
    
    </script>
	
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>