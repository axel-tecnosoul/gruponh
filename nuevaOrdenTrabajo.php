<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
if (!empty($_POST)) {
    
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $redirect="listarOrdenesTrabajo.php";

  $id_estado_orden_trabajo=1;
  $nro_revision=0;
  $anulado=0;
  $numero="";//insertamos vacío y una vez que obtenemos el ID lo modificamos
  $descripcion="Emision original";

  $sql = "INSERT INTO ordenes_trabajo (id_orden_trabajo,id_lista_corte, fecha, id_usuario, id_estado_orden_trabajo, nro_revision, anulado, titulo, numero, descripcion, notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
  $q = $pdo->prepare($sql);
  $q->execute([null,$_POST["id_lista_corte"],$_POST['fecha'],$_SESSION["user"]["id"],$id_estado_orden_trabajo,$nro_revision,$anulado,$_POST['titulo'],$numero,$descripcion,$_POST['notas']]);
  $id_orden_trabajo_revision = $pdo->lastInsertId();

  $sql = "update ordenes_trabajo set id_orden_trabajo=? where id =?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_orden_trabajo_revision,$id_orden_trabajo_revision]);


  if ($id_orden_trabajo_revision>0) {
    $numero="LC".$_POST["id_lista_corte"]."-OT".$id_orden_trabajo_revision;
      
    $sql = "UPDATE ordenes_trabajo set numero = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute(array($numero,$id_orden_trabajo_revision));

  }

  foreach ($_POST["cantidad_bajar"] as $key => $cantidad) {
    if($cantidad!="" and $cantidad>0){
      $id_estado_orden_trabajo_posicion=1;//Elaboración, Pendiente, Proceso, Terminada, Liberada, Reproceso, Rechazada, Cancelada

      $sql = "INSERT INTO ordenes_trabajo_detalle (id_orden_trabajo, id_posicion, cantidad, id_estado_orden_trabajo_posicion) VALUES (?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_orden_trabajo_revision,$_POST['id_posicion'][$key],$cantidad,$id_estado_orden_trabajo_posicion]);

    }
  }

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nueva Orden de Trabajo','Orden de Trabajo')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  Database::disconnect();
  header("Location: ".$redirect);
  
}

if(isset($_GET['id_lista_corte'])){
  //nueva revision
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $sql = "SELECT id AS id_lista_corte_revision, nombre, numero, id_estado_lista_corte, descripcion, nro_revision, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido FROM listas_corte_revisiones WHERE id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id_lista_corte']]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

}

Database::disconnect();?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php');?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Nueva Orden de Trabajo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <form class="form theme-form" role="form" method="post" action="nuevaOrdenTrabajo.php">
                  <div class="card mb-0">
                    <div class="card-header">
                      <h5><?=$ubicacion?></h5>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <!-- <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Revisión(*)</label>
                            <div class="col-sm-3">
                              <input name="revision" data-original="" type="number" class="form-control">
                            </div>
                            <label class="col-sm-3 col-form-label">N° OT(*)</label>
                            <div class="col-sm-3">
                              <input name="numero" data-original="" type="text" class="form-control">
                            </div>
                          </div> -->
                          <div class="form-group row">
                            <input type="hidden" name="id_lista_corte" id="id_lista_corte" value="<?=$_GET['id_lista_corte']?>">
                            <label class="col-sm-3 col-form-label">Fecha(*)</label>
                            <div class="col-sm-3"><input name="fecha" type="date" autofocus onfocus="this.showPicker()" value="<?php echo date('Y-m-d');?>" class="form-control"></div>
                            <label class="col-sm-3 col-form-label">Titulo(*)</label>
                            <div class="col-sm-3"><input name="titulo" type="text" class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <!-- <label class="col-sm-3 col-form-label">Descripcion del cambio(*)</label>
                            <div class="col-sm-3">
                              <textarea name="descripcion" class="form-control"></textarea>
                            </div> -->
                            <label class="col-sm-3 col-form-label">Notas de la OT(*)</label>
                            <div class="col-sm-9">
                              <textarea name="notas" class="form-control"></textarea>
                            </div>
                          </div>
                          <div class="form-group row">
                            <div class="col-12">
                              
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>Detalle de la LC
                        &nbsp;&nbsp;
                        <img src="img/icon_alta.png" id="link_agregar_posiciones" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Agregar" title="Agregar">
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="form-group row">
                        <div class="dt-ext table-responsive">
                          <table class="display" id="tablaLC">
                            <thead>
                              <tr>
                                <th class="d-none">ID Posicion</th>
                                <th>Conjunto</th>
                                <th>Cantidad</th>
                                <th>Posicion</th>
                                <th>Cantidad Pedida</th>
                                <th>Material</th>
                                <th>Procesos</th>
								<th>Cantidad Bajada</th>
								<th>Saldo</th>
                                
                              </tr>
                            </thead>
                            <tfoot>
                              <tr>
                                <th class="d-none">ID Posicion</th>
                                <th>Conjunto</th>
                                <th>Cantidad</th>
                                <th>Posicion</th>
                                <th>Cantidad Pedida</th>
                                <th>Material</th>
                                <th>Procesos</th>
								<th>Cantidad Bajada</th>
								<th>Saldo</th>
                                
                              </tr>
                            </tfoot>
                            <tbody><?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              
                              $sql = " SELECT lcc.nombre,lcc.cantidad AS cant_conj,lcp.posicion,lcp.cantidad AS cant_pos,m.concepto,GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos, lcp.id AS id_posicion FROM listas_corte_conjuntos lcc INNER JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto=lcc.id INNER JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion=lcp.id INNER JOIN materiales m ON lcp.id_material=m.id INNER JOIN tipos_procesos tp ON lcpr.id_tipo_proceso=tp.id WHERE lcc.id_lista_corte = ".$_GET['id_lista_corte']." GROUP BY lcp.id";
                              foreach ($pdo->query($sql) as $row) {
                                echo '<tr id="'. $row["id_posicion"] . '">';
                                echo '<td class="d-none">'. $row["id_posicion"] . '</td>';
                                echo '<td>'. $row["nombre"] . '</td>';
                                echo '<td>'. $row["cant_conj"] . '</td>';
                                echo '<td>'. $row["posicion"] . '</td>';
                                echo '<td>'. $row["cant_pos"] . '</td>';
                                echo '<td>'. $row["concepto"] . '</td>';
                                echo '<td>'. $row["procesos"] . '</td>';
								echo '<td>'. 0 . '</td>';
								echo '<td>'. $row["cant_pos"] . '</td>';
                                
                                echo '</tr>';
                              }
                              Database::disconnect();?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>
                        Detalle de la OT
                        <img src="img/icon_baja.png" id="link_eliminar_posiciones" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">&nbsp;&nbsp;
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="form-group row">
                        <div class="dt-ext table-responsive">
                          <table class="display" id="tablaOT">
                          <thead>
                              <tr>
                                <th>ID Posicion</th>
                                <th>Conjunto</th>
                                <th>Cantidad</th>
                                <th>Posicion</th>
                                <th>Cantidad Pedida</th>
                                <th>Cantidad a Bajar</th>
                                <!-- <th>Material</th>
                                <th>Procesos</th> -->
                              </tr>
                            </thead>
                            <tfoot>
                              <tr>
                                <th>ID Posicion</th>
                                <th>Conjunto</th>
                                <th>Cantidad</th>
                                <th>Posicion</th>
                                <th>Cantidad Pedida</th>
                                <th>Cantidad a Bajar</th>
                                <!-- <th>Material</th>
                                <th>Procesos</th> -->
                              </tr>
                            </tfoot>
                            <tbody></tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" class="btn btn-primary">Crear</button>
                        <a href='listarOrdenesTrabajo.php' class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </div>

                </form>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- Modal para eliminas conjuntos -->
        <div class="modal fade" id="eliminarConjunto" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el conjunto?</div>
              <div class="modal-footer">
                <a href="#" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal para eliminas posiciones -->
        <div class="modal fade" id="eliminarPosicion" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar la posicion?</div>
              <div class="modal-footer">
                <a href="#" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
              </div>
            </div>
          </div>
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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
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
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script>
      $(document).ready(function () {
        var tablaLC = $('#tablaLC');
        var tablaOT = $('#tablaOT');

        let datatableDefault={
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
        }

        // Setup - add a text input to each footer cell
        tablaLC.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaLC.DataTable(datatableDefault);
 
        // Apply the search
        tablaLC.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        //tablaLC.find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaLC tbody tr td", function(){
          var t=$(this).parent();

          let id_conjunto=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            //$("#link_agregar_posiciones").data("id","");
          }else{
            tablaLC.DataTable().rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
          }
        });

        $("#link_agregar_posiciones").on("click",function(){
          var selectedRowsLC = tablaLC.DataTable().rows('.selected');
          if(selectedRowsLC[0].length>0){
            let newData=selectedRowsLC.data().map(function(elemento){
              elemento[5] = `
                <input type="hidden" name="id_posicion[]" value="${elemento["DT_RowId"]}">
                <input type="number" step="0.01" class="form-control" name="cantidad_bajar[]">
              `;
              return elemento;
            })
            tablaOT.DataTable().rows.add(newData).draw();
            $(selectedRowsLC.nodes()).hide().removeClass("selected")

          }else{
            alert("Por favor seleccione una posicion para agregar a la Orden de trabajo")
          }
        });
		
		// Setup - add a text input to each footer cell
        tablaOT.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaOT.DataTable({
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
		
		   //$(document).find(tablaOT).find(" tbody tr td").not(":last-child").on( 'click', function () {
        //tablaOT.on('click',"tbody tr td", function () {
        $(document).on("click","#tablaOT tbody tr td", function(){
          var t=$(this).parent();
          console.log(t.find("td:nth-child(5)"));
          console.log($(this));
          let celdaClickeado=$(this)[0];
          let celdaConInput=t.find("td:nth-child(5)")[0];
          if(celdaConInput!=celdaClickeado){
            if(t.hasClass('selected')){
              deselectRow(t);
            }else{
              tablaOT.DataTable().rows().nodes().each( function (rowNode, index) {
                $(rowNode).removeClass("selected");
              });
              selectRow(t);
            }
          }
        });

        

        
 
        // Apply the search
        tablaOT.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        $("#link_eliminar_posiciones").on("click",function(){
          var selectedRowsOT = tablaOT.DataTable().rows('.selected');
          if(selectedRowsOT[0].length>0){
            $(selectedRowsOT.nodes()).find("input[name='id_posicion[]']").each(function() {
              tablaLC.find("#"+$(this).val()).show()
            });
            //$(selectedRowsOT.nodes()).remove().draw();
            selectedRowsOT.remove().draw();
  
          }else{
            alert("Por favor seleccione una posicion para eliminar")
          }
        });
    
      });
	  
	  function order(a, b) {
        return b.age - a.age;
      }

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    </script>
  </body>
</html>