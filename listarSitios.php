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
          $ubicacion="Sitios ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
		    <div class="row">
			<div class="col-md-12">
				<div class="card">
				  <div class="card-body">
					<form class="form-inline theme-form mt-3" name="form1" method="post" action="listarSitios.php">
					  <div class="form-group mb-0">
						Nro.Sitio:&nbsp;<input class="form-control" size="3" type="text" value="<?php if (isset($_POST['nro'])) echo $_POST['nro'] ?>" name="nro" id="nro">
					  </div>
					  <div class="form-group mb-0">
						Nombre Sitio:&nbsp;<input class="form-control" size="20" type="text" value="<?php if (isset($_POST['nombre'])) echo $_POST['nombre'] ?>" name="nombre" id="nombre">
					  </div>
					  <div class="form-group mb-0">
						Cliente:&nbsp;<input class="form-control" size="20" type="text" value="<?php if (isset($_POST['cliente'])) echo $_POST['cliente'] ?>" name="cliente" id="cliente">
					  </div>
					  <div class="form-group mb-0">
						<button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarSitios.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion; if (!empty(tienePermiso(273))) { ?><a href="nuevoSitio.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo" title="Nuevo"></a><?php } ?>&nbsp;<a href="#" onclick="jsExportar();"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;<a href="#" onclick="jsMapa();"><img src="img/copa.png" width="24" height="25" border="0" alt="Mapa Sitios" title="Mapa Sitios"></a>
					&nbsp;&nbsp;
					<?php 
					echo '<a href="#" id="link_ver_sitio"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>';
					echo '&nbsp;&nbsp;';
					if (!empty(tienePermiso(274))) {
						echo '<a href="#" id="link_modificar_sitio"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>';
						echo '&nbsp;&nbsp;';
						echo '<a href="#" id="link_adjuntar_sitio"><img src="img/import.png" width="24" height="25" border="0" alt="Adjuntar" title="Adjuntar"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(277))) {
						echo '<a href="#" id="link_nuevo_proyecto"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo Proyecto" title="Nuevo Proyecto"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(273))) {
						echo '<a href="#" id="link_nuevo_sitio"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Subsitio" title="Nuevo Subsitio"></a>';
						echo '&nbsp;&nbsp;';
					}
					if (!empty(tienePermiso(275))) {
						echo '<a href="#" id="link_eliminar_sitio"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>';
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
							  <th class="d-none">ID</th>
							  <th>Sitio</th>
							  <th>Subsitio</th>
							  <th>Nombre</th>
							  <th>Dueño</th>
							  <th>País</th>
							  <th>Provincia</th>
							  <th>Localidad</th>
							  <th>Dirección</th>
                          </tr>
                        </thead>
                        <tbody>
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
    
	$('#dataTables-example666').DataTable({
        stateSave: false,
		<?php
		if (!empty($_POST)) {
		?>
		'ajax': 'listarSitiosAjax.php?nro=<?php echo $_POST['nro'];?>&nombre=<?php echo $_POST['nombre'];?>&cliente=<?php echo $_POST['cliente'];?>',	
		<?php
		} else {
		?>
		'ajax': 'listarSitiosAjax.php?nro=225252525&nombre=225252525&cliente=225252525',		
		<?php
		}
		?>
		
        responsive: true,
		serverSide: true,
		searching: false,
		
        processing: true,
		rowCallback: function(row, data, index) {
			$('td:eq(0)', row).addClass('d-none');
		},
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
        }}
      });
 
    // DataTable
    var table = $('#dataTables-example666').DataTable();
 
	$("#link_ver_sitio").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un sitio para ver detalle")
        }
      })
	  $("#link_modificar_sitio").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un sitio para modificar")
        }
      })
	  $("#link_adjuntar_sitio").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un sitio para adjuntar documentación")
        }
      })
	  $("#link_nuevo_proyecto").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un sitio para añadir proyecto")
        }
      })
		$("#link_nuevo_sitio").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un sitio para crearle un subsitio")
        }
      })
	  $("#link_eliminar_sitio").on("click",function(){
        /*let l=document.location.href;
        if(this.href==l || this.href==l+"#"){*/
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un sitio para eliminar")
        }
      })	  
	//$('#dataTables-example666').find("tbody tr td").not(":last-child").on( 'click', function () {
    $(document).on("click","#dataTables-example666 tbody tr td", function(){
        var t=$(this).parent();
        //t.parent().find("tr").removeClass("selected");

        let id_sitio=t.find("td:first-child").html();
		    let nro_subsitio = t.find("td:nth-child(3)").html();
        if(t.hasClass('selected')){
          deselectRow(t);
          $("#link_ver_sitio").attr("href","#");
          $("#link_modificar_sitio").attr("href","#");
          $("#link_adjuntar_sitio").attr("href","#");
          $("#link_nuevo_proyecto").attr("href","#");
          $("#link_nuevo_sitio").attr("href","#");
		  $("#link_eliminar_sitio").attr("href","#");
        }else{
          table.rows().nodes().each( function (rowNode, index) {
            $(rowNode).removeClass("selected");
          });
          selectRow(t);
          $("#link_ver_sitio").attr("href","verSitio.php?id="+id_sitio);
          $("#link_modificar_sitio").attr("href","modificarSitio.php?id="+id_sitio);
          $("#link_adjuntar_sitio").attr("href","adjuntarSitio.php?id="+id_sitio);
          $("#link_nuevo_proyecto").attr("href","nuevoProyecto.php?idSitio="+id_sitio);
          $("#link_nuevo_sitio").attr("href","nuevoSitio.php?id="+id_sitio);  
		  $("#link_eliminar_sitio").attr("href","eliminarSitio.php?id="+id_sitio);  
        }
      });
    
	} );
	
	function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }
	
	function jsExportar() {
		document.location.href="exportSitios.php?nro="+document.getElementById('nro').value+"&nombre="+document.getElementById('nombre').value+"&cliente="+document.getElementById('cliente').value;
	}
	function jsMapa() {
		document.location.href="mapaSitios.php?nro="+document.getElementById('nro').value+"&nombre="+document.getElementById('nombre').value+"&cliente="+document.getElementById('cliente').value;
	}
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>