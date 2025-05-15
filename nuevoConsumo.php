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

  $modoDebug=0;

  if($modoDebug==1){
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_GET);

  }

  $redirect="listarConsumos.php";

  $nro_revision=0;
  $descripcion="Emision original";

  $sql = "INSERT INTO consumos (fecha,id_orden_trabajo_revision,nro_revision,descripcion,id_usuario,anulado) VALUES (NOW(),?,?,?,?,0)";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST["id_orden_trabajo_revision"],$nro_revision,$descripcion,$_SESSION["user"]["id"]]);
  $id_consumo = $pdo->lastInsertId();

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }

  $aEgresos=[];
  $aIngresos=[];
  foreach ($_POST["id_material"] as $key => $id_material) {

    $situacion=$_POST['situacion'][$key];

    $sql = "INSERT INTO consumos_detalle (id_consumo, id_material, id_colada, situacion, cantidad, id_unidad_medida, observacion) VALUES (?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$id_consumo,$id_material,$_POST['id_colada'][$key],$situacion,$_POST['cantidad'][$key],$_POST['id_unidad_medida'][$key],$_POST['observacion'][$key]]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $aux=[
      "id_material"=>$id_material,
      "id_unidad_medida"=>$_POST['id_unidad_medida'][$key],
      "cantidad"=>$_POST['cantidad'][$key],
      "id_colada"=>$_POST['id_colada'][$key],
      "observacion"=>$_POST['observacion'][$key]
    ];
    if($situacion=="Consumo"){
      //cargamos los datos para registrar un egreso
      $aEgresos[]=$aux;
    }else{//Sobrante
      //cargamos los datos para registrar un ingreso
      $aIngresos[]=$aux;
    }
  }

  if(count($aEgresos)>0){
    //REGISTRAMOS UN EGRESO
    $id_tipo_egreso=1;//1 -> Lista de Corte
    $nro=1;
    $id_cuenta_retira=1;

    $sql = "SELECT p.id_sitio FROM ordenes_trabajo otr INNER JOIN listas_corte_revisiones lcr ON otr.id_lista_corte=lcr.id INNER JOIN proyectos p ON lcr.id_proyecto=p.id WHERE otr.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST["id_orden_trabajo_revision"]]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    $id_sitio=$data["id_sitio"];
    $id_tarea=null;
    $observaciones="";

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "INSERT INTO egresos (fecha_hora, id_tipo_egreso, nro, id_cuenta_retira, id_sitio_destino, id_tarea, observaciones) VALUES (now(),$id_tipo_egreso,?,?,?,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$nro,$id_cuenta_retira,$id_sitio,$id_tarea,$observaciones]);
		$idEgreso = $pdo->lastInsertId();

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
		
		foreach ($aEgresos as $key => $value) {
			
			$sql = "SELECT id.id,cd.precio FROM ingresos_detalle id INNER JOIN compras_detalle cd ON id.id_compra=cd.id_compra AND id.id_material=cd.id_material WHERE id.id_material = ? AND id_colada = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$value["id_material"],$value["id_colada"]]);
			$data2 = $q->fetch(PDO::FETCH_ASSOC);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
			
			if (!empty($data2)) {
				$precio = $data2['precio'];
				$subtotal = $data2['precio']*$value["cantidad"];
			} else {
				$precio = 0;
				$subtotal = 0; 
			}
			
			$sql = "INSERT INTO egresos_detalle (id_egreso, id_detalle_ingreso, id_material, id_unidad_medida, cantidad, precio, subtotal) VALUES (?,?,?,?,?,?,?)";
			$q = $pdo->prepare($sql);
			$q->execute([$idEgreso,$value["id_material"],$data2['id'],$value["id_unidad_medida"],$value["cantidad"],$precio,$subtotal]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
			
		}

		$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo egreso de stock de Lista de corte','Egresos','verEgreso.php?id=$idEgreso')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

  }

  if(count($aIngresos)>0){
    //REGISTRAMOS UN INGRESO
    $id_tipo_ingreso=2;//2 -> Devolucion
    $nro=1;
    $lugar_entrega="";
    $id_cuenta_recibe=1;
    $observaciones="";

    $sql = "INSERT INTO ingresos (fecha_hora, id_tipo_ingreso, nro, id_cuenta_recibe, lugar_entrega, observaciones) VALUES (now(),$id_tipo_ingreso,?,?,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$nro,$id_cuenta_recibe,$lugar_entrega,$observaciones]);
		$idIngreso = $pdo->lastInsertId();

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
		
		foreach ($aIngresos as $key => $value) {
			
      $sql = "INSERT INTO ingresos_detalle (id_ingreso, id_material, id_unidad_medida, cantidad, cantidad_egresada, saldo) VALUES (?,?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$idIngreso,$value["id_material"],$value["id_unidad_medida"],$value['cantidad'],0,$value['cantidad']]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
				
		}
		
		$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo ingreso por devolución','Ingresos','verIngreso.php?id=$idIngreso')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
  }

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva Consumo','Consumos','verConsumo.php?id=$id_consumo')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  if ($modoDebug==1) {
    echo "redirect: ".$redirect;
    $pdo->rollBack();
    die();
  } else {
    Database::disconnect();
    header("Location: ".$redirect);
  }

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
          $ubicacion="Nuevo Consumo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card mb-0">
                  <div class="card-header">
                    <h5>Resumen Orden de Trabajo N° <?=$_GET['id_orden_trabajo']?></h5>
                  </div>
                  <div class="card-body">
                    <div class="form-group row">
                      <div class="dt-ext table-responsive">
                        <table class="display" id="tablaMaterialesOT">
                          <thead>
                            <tr>
                              <th>Material</th>
                              <th>Largo (mm)</th>
                              <th>M2</th>
                              <th>Barras/Chapas</th>
                              <th>Area pintable</th>
                            </tr>
                          </thead>
                          <tfoot>
                            <tr>
                              <th>Material</th>
                              <th>Largo (mm)</th>
                              <th>M2</th>
                              <th>Barras/Chapas</th>
                              <th>Area pintable</th>
                            </tr>
                          </tfoot>
                          <tbody><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            
                            $sql = "SELECT m.concepto,SUM(lcp.largo) AS largo FROM ordenes_trabajo_detalle otd INNER JOIN lista_corte_posiciones lcp ON otd.id_posicion=lcp.id INNER JOIN materiales m ON lcp.id_material=m.id WHERE id_orden_trabajo = ".$_GET['id_orden_trabajo']." GROUP BY lcp.id_material ";
                            foreach ($pdo->query($sql) as $row) {
                              echo '<tr>';
                              echo '<td>'.$row["concepto"].'</td>';
                              echo '<td>'.$row["largo"].'</td>';
                              echo '<td>0'.'</td>';
                              echo '<td>0'.'</td>';
                              echo '<td>0'.'</td>';
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
                    <h5><?=$ubicacion?></h5>
                  </div>
                  <form id="formInput">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Material(*)</label>
                            <div class="col-sm-9">
                              <select name="id_material" id="id_material" class="js-example-basic-single col-sm-12" autofocus required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT lcp.id_material,m.concepto,lcp.id_colada,c.nro_colada,otd.cantidad,um.unidad_medida FROM ordenes_trabajo_detalle otd INNER JOIN lista_corte_posiciones lcp ON otd.id_posicion=lcp.id INNER JOIN materiales m ON lcp.id_material=m.id INNER JOIN coladas c ON lcp.id_colada=c.id INNER JOIN unidades_medida um ON m.id_unidad_medida=um.id WHERE id_orden_trabajo = ".$_GET['id_orden_trabajo'];
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                  <option value='<?=$fila['id_material']?>' data-concepto='<?=$fila['concepto']?>' data-id-colada='<?=$fila['id_colada']?>' data-nro-colada='<?=$fila['nro_colada']?>'><?=$fila['concepto']." | ".$fila['nro_colada']." | ".$fila['cantidad']." | ".$fila['unidad_medida']?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Situacion(*)</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="edo-ani">
                                <input class="radio_animated" value="Consumo" required id="edo-ani" type="radio" name="situacion"><label for="edo-ani">Consumo</label>
                              </label>
                              <label class="d-block" for="edo-ani1">
                                <input class="radio_animated" value="Sobrante" required id="edo-ani1" type="radio" name="situacion"><label for="edo-ani1">Sobrante</label>
                              </label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9"><input type="number" step="0.01" name="cantidad" required class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Medida(*)</label>
                            <div class="col-sm-9">
                              <select name="id_unidad_medida" id="id_unidad_medida" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id,unidad_medida FROM unidades_medida";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['unidad_medida']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observacion</label>
                            <div class="col-sm-9"><input type="text" name="observacion" class="form-control"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" id="btnAgregarConsumo" class="btn btn-primary">Agregar</button>
                        <!-- <button type="submit" id="btnEditarConsumo" class="btn btn-primary">Agregar</button> -->
                      </div>
                    </div>
                  </form>
                </div>

                <div class="card mb-0">
                  <div class="card-header">
                    <h5>
                      Detalle de Consumos y Sobrantes
                      <img src="img/icon_baja.png" id="link_eliminar_consumo" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">&nbsp;&nbsp;
                      <!-- <img src="img/icon_modificar.png" id="link_modificar_consumo" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Modificar" title="Modificar">&nbsp;&nbsp; -->
                    </h5>
                  </div>
                  <form action="nuevoConsumo.php" method="post">
                    <input type="hidden" name="id_orden_trabajo_revision" id="id_orden_trabajo_revision" value="<?=$_GET['id_orden_trabajo']?>">
                    <div class="card-body">
                      <div class="form-group row">
                        <div class="dt-ext table-responsive">
                          <table class="display" id="tablaConsumos">
                          <thead>
                              <tr>
                                <th>Material</th>
                                <th>Colada</th>
                                <th>Situacion</th>
                                <th>Cantidad</th>
                                <th>Medida</th>
                                <th>Observacion</th>
                              </tr>
                            </thead>
                            <tfoot>
                              <tr>
                                <th>Material</th>
                                <th>Colada</th>
                                <th>Situacion</th>
                                <th>Cantidad</th>
                                <th>Medida</th>
                                <th>Observacion</th>
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
                  </form>
                </div>

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
        var tablaMaterialesOT = $('#tablaMaterialesOT');
        var tablaConsumos = $('#tablaConsumos');

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
        tablaMaterialesOT.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaMaterialesOT.DataTable(datatableDefault);
 
        // Apply the search
        tablaMaterialesOT.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        //tablaMaterialesOT.find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaMaterialesOT tbody tr td", function(){
          var t=$(this).parent();

          let id_conjunto=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
          }else{
            tablaMaterialesOT.DataTable().rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
          }
        });

        //$(document).find(tablaConsumos).find(" tbody tr td").not(":last-child").on( 'click', function () {
        //tablaConsumos.on('click',"tbody tr td", function () {
        $(document).on("click","#tablaConsumos tbody tr td", function(){
          var t=$(this).parent();
          
          /*let celdaClickeado=$(this)[0];
          let celdaConInput=t.find("td:nth-child(5)")[0];
          if(celdaConInput!=celdaClickeado){
            if(t.hasClass('selected')){
              deselectRow(t);
            }else{
              selectRow(t);
            }
          }*/
          if(t.hasClass('selected')){
            deselectRow(t);
          }else{
            tablaConsumos.DataTable().rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
          }
        });

        // Setup - add a text input to each footer cell
        tablaConsumos.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaConsumos.DataTable(datatableDefault);
 
        // Apply the search
        tablaConsumos.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        $("#link_eliminar_consumo").on("click",function(){
          var selectedRowsOT = tablaConsumos.DataTable().rows('.selected');
          if(selectedRowsOT[0].length>0){

            $(selectedRowsOT.nodes()).find("input[name='id_posicion[]']").each(function() {
              tablaMaterialesOT.find("#"+$(this).val()).show()
            });
            //$(selectedRowsOT.nodes()).remove().draw();
            selectedRowsOT.remove().draw();
  
          }else{
            alert("Por favor seleccione una posicion para eliminar")
          }
        });

        $("#link_modificar_consumo").on("click",function(){
          console.log(this);
          var selectedRowsConsumo = tablaConsumos.DataTable().rows('.selected');
          if(selectedRowsConsumo[0].length>0){
            console.log(selectedRowsConsumo);
            let nodes=selectedRowsConsumo.nodes()[0]
            console.log(nodes);
            /*let data=selectedRowsConsumo.data()[0]
            console.log(data);*/
            $(selectedRowsConsumo.nodes()).find("input[name='id_posicion[]']").each(function() {
              tablaMaterialesOT.find("#"+$(this).val()).show()
            });

            console.log(nodes)
            //console.log(data)
            
            let id_material=$(nodes).find(".id_material").val()
            console.log(id_material);
            let situacion=$(nodes).find(".situacion").val()
            let cantidad=$(nodes).find(".cantidad").val()
            let id_unidad_medida=$(nodes).find(".id_unidad_medida").val()
            let observacion=$(nodes).find(".observacion").val()
            editarFormInput(id_material,situacion,cantidad,id_unidad_medida,observacion)
            //$(selectedRowsConsumo.nodes()).remove().draw();
            //selectedRowsConsumo.remove().draw();
  
          }else{
            alert("Por favor seleccione una posicion para modificar")
          }
        });

        //$("#btnAgregarConsumo").on("click",function(){
        $("#formInput").on("submit",function(e){
          e.preventDefault();
          let select_id_material=$("select[name='id_material']")
          let id_material=select_id_material.val()
          let selected_option_id_material=select_id_material.find("option[value='"+id_material+"']")
            
          let concepto=selected_option_id_material.data("concepto")
          let id_colada=selected_option_id_material.data("idColada")
          let nro_colada=selected_option_id_material.data("nroColada")
          let situacion=$("input[name='situacion']:checked").val()
          let cantidad=$("input[name='cantidad']").val()
          let select_id_unidad_medida=$("select[name='id_unidad_medida']")
          let id_unidad_medida=select_id_unidad_medida.val()
          let selected_option_id_unidad_medida=select_id_unidad_medida.find("option[value='"+id_unidad_medida+"']").text()

          let observacion=$("input[name='observacion']").val()

          let newConsumo=[
            `<input type="hidden" name="id_material[]" class="id_material" value="${id_material}">`+concepto,
            `<input type="hidden" name="id_colada[]" class="id_colada" value="${id_colada}">`+nro_colada,
            `<input type="hidden" name="situacion[]" class="situacion" value="${situacion}">`+situacion,
            `<input type="hidden" name="cantidad[]" class="cantidad" value="${cantidad}">`+cantidad,
            `<input type="hidden" name="id_unidad_medida[]" class="id_unidad_medida" value="${id_unidad_medida}">`+selected_option_id_unidad_medida,
            `<input type="hidden" name="observacion[]" class="observacion" value="${observacion}">`+observacion
          ]
          console.log(newConsumo);

          tablaConsumos.DataTable().row.add(newConsumo).draw();

          limpiarFormInput()
        });
    
      });

      function editarFormInput(id_material,situacion,cantidad,id_unidad_medida,observacion){
        $("select[name='id_material']").val(id_material).trigger('change')
        $("input[name='situacion']").prop("checked",false)
        $("input[name='situacion'][value='"+situacion+"']").prop("checked",true)
        $("input[name='cantidad']").val(cantidad)
        $("select[name='id_unidad_medida']").val(id_unidad_medida).trigger('change')
        $("input[name='observacion']").val(observacion)
      }

      function limpiarFormInput(){
        editarFormInput(id_material="",situacion="",cantidad="",id_unidad_medida="",observacion="")
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