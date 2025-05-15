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

  $redirect="listarOrdenesTrabajo.php";

  if(isset($_POST["btn1"])){
    //echo "modificar";
    $sql = "UPDATE ordenes_trabajo set fecha = ?, titulo = ?, notas = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute(array($_POST["fecha"],$_POST["titulo"],$_POST["notas"],$_GET["id"]));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Modificacion de Orden de Trabajo','Orden de Trabajo')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

  }else{
    //echo "revisar";
    $id_estado_orden_trabajo=1;
    $nro_revision=0;
    $anulado=0;
    $numero="";//insertamos vacío y una vez que obtenemos el ID lo modificamos
    $descripcion="Emision original";

    $id_orden_trabajo=$_POST["id_orden_trabajo"];
    $nro_revision=$_POST["nro_revision"];
    $nuevo_nro_revision=$nro_revision+1;

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "SELECT otr.id, otr.id_orden_trabajo, otr.id_lista_corte, otr.fecha, otr.id_usuario, otr.id_estado_orden_trabajo, otr.nro_revision, otr.anulado, otr.titulo, otr.numero, otr.descripcion, otr.notas FROM ordenes_trabajo otr WHERE otr.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_GET["id"]]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "INSERT INTO ordenes_trabajo (id_orden_trabajo, id_lista_corte, fecha, id_usuario, id_estado_orden_trabajo, nro_revision, anulado, titulo, numero, descripcion, notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$data["id_orden_trabajo"],$data["id_lista_corte"],$_POST['fecha'],$_SESSION["user"]["id"],$data["id_estado_orden_trabajo"],$nuevo_nro_revision,$data["anulado"],$data['titulo'],$data["numero"],$_POST["descripcion"],$_POST['notas']]);
    $id_orden_trabajo_revision = $pdo->lastInsertId();

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    foreach ($_POST["cantidad_bajar"] as $key => $cantidad) {
      if($cantidad!="" and $cantidad>0){
        $id_posicion=$_POST['id_posicion'][$key];
        
        $cant_liberadas=0;
        $cant_proceso=0;
        $cant_rechazadas=0;
        $id_estado_orden_trabajo_posicion=1;//Elaboración, Pendiente, Proceso, Terminada, Liberada, Reproceso, Rechazada, Cancelada

        $sql = "SELECT otd.id_orden_trabajo,otd.id_posicion,otd.cantidad,otd.cant_liberadas,otd.cant_proceso,otd.cant_rechazadas,otd.id_estado_orden_trabajo_posicion FROM ordenes_trabajo_detalle otd WHERE otd.id_posicion = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$id_posicion]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        
        if($data){
          $cant_liberadas=$data["cant_liberadas"];
          $cant_proceso=$data["cant_proceso"];
          $cant_rechazadas=$data["cant_rechazadas"];
          $id_estado_orden_trabajo_posicion=$data["id_estado_orden_trabajo_posicion"];
        }

        $sql = "INSERT INTO ordenes_trabajo_detalle (id_orden_trabajo, id_posicion, cantidad, cant_liberadas, cant_proceso, cant_rechazadas, id_estado_orden_trabajo_posicion) VALUES (?,?,?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_orden_trabajo_revision,$id_posicion,$cantidad,$cant_liberadas,$cant_proceso,$cant_rechazadas,$id_estado_orden_trabajo_posicion]);

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: ".$q->rowCount();
          echo "<br><br>";
        }
      }
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nueva Revision de Orden de Trabajo','Orden de Trabajo')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));
  }

  if ($modoDebug==1) {
    echo "redirect: ".$redirect;
    $pdo->rollBack();
    die();
  } else {
    Database::disconnect();
    header("Location: ".$redirect);
  }

}

if(isset($_GET['id'])){
  //nueva revision
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $sql = "SELECT id_orden_trabajo,fecha,id_lista_corte,nro_revision,titulo,numero,descripcion,notas FROM ordenes_trabajo WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id']]);
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
          $ubicacion="Ver Orden de Trabajo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <form class="form theme-form" role="form" method="post" action="verOrdenTrabajo.php?id=<?=$_GET['id']?>">
                  <div class="card mb-0">
                    <div class="card-header">
                      <h5><?=$ubicacion?></h5>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Revisión(*)</label>
                            <div class="col-sm-3">
                              <input type="hidden" name="id_orden_trabajo" value='<?=$data["id_orden_trabajo"]?>'>
                              <input name="nro_revision" readonly type="number" value="<?=$data["nro_revision"]?>" class="form-control">
                            </div>
                            <label class="col-sm-3 col-form-label">N° OT(*)</label>
                            <div class="col-sm-3">
                              <input name="numero" readonly type="text" autofocus value="<?=$data["numero"]?>" class="form-control">
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha(*)</label>
                            <div class="col-sm-3"><input name="fecha" type="date" onfocus="this.showPicker()" value="<?=$data["fecha"]?>" class="form-control"></div>
                            <label class="col-sm-3 col-form-label">Titulo(*)</label>
                            <div class="col-sm-3"><input name="titulo" type="text" value="<?=$data["titulo"]?>" class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Descripcion del cambio(*)</label>
                            <div class="col-sm-3">
                              <textarea name="descripcion" class="form-control"><?=$data["descripcion"]?></textarea>
                            </div>
                            <label class="col-sm-3 col-form-label">Notas de la OT(*)</label>
                            <div class="col-sm-3">
                              <textarea name="notas" class="form-control"><?=$data["notas"]?></textarea>
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
                              
                              $sql = " SELECT lcc.nombre,lcc.cantidad AS cant_conj,lcp.posicion,lcp.cantidad AS cant_pos,m.concepto,GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos, lcp.id AS id_posicion,otd.cantidad AS cant_bajada FROM listas_corte_conjuntos lcc INNER JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto=lcc.id INNER JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion=lcp.id INNER JOIN materiales m ON lcp.id_material=m.id INNER JOIN tipos_procesos tp ON lcpr.id_tipo_proceso=tp.id LEFT JOIN ordenes_trabajo_detalle otd ON otd.id_posicion=lcp.id WHERE lcc.id_lista_corte = ".$data['id_lista_corte']." GROUP BY lcp.id";

                              $posiciones_agregadas=[];
                              foreach ($pdo->query($sql) as $row) {
                                $style="";
                                if(!empty($row["cant_bajada"])){
                                  $style="display: none";
                                  $posiciones_agregadas[]=$row;
                                }
                                echo '<tr id="'.$row["id_posicion"].'" style="'.$style.'">';
                                echo '<td class="d-none">'.$row["id_posicion"].'</td>';
                                echo '<td>'.$row["nombre"].'</td>';
                                echo '<td>'.$row["cant_conj"].'</td>';
                                echo '<td>'.$row["posicion"].'</td>';
                                echo '<td>'.$row["cant_pos"].'</td>';
                                echo '<td>'.$row["concepto"].'</td>';
                                echo '<td>'.$row["procesos"].'</td>';
								echo '<td>'.$row["cant_bajada"].'</td>';
								echo '<td>'.$row["cant_pos"]-$row["cant_bajada"].'</td>';
                                
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
                            <tbody><?php
                              $aDetalleOT=[];
                              foreach ($posiciones_agregadas as $row) {
                                $aDetalleOT[]=[
                                  "id_posicion"=>$row["id_posicion"],
                                  "cantidad"=>$row["cant_bajada"],
                                ];
                                $cant_max=$row["cant_pos"];
                                //$cant_max=$row["cant_bajada"];
                                
                                echo '<tr id="'.$row["id_posicion"].'">';
                                echo '<td>'.$row["id_posicion"].'</td>';
                                echo '<td>'.$row["nombre"].'</td>';
                                echo '<td>'.$row["cant_conj"].'</td>';
                                echo '<td>'.$row["posicion"].'</td>';
                                echo '<td>'.$row["cant_pos"].'</td>';
                                echo '<td>';
                                echo '<input type="hidden" name="id_posicion[]" value="'.$row["id_posicion"].'">';
                                echo '<input type="number" step="0.01" name="cantidad_bajar[]" value="'.$row["cant_bajada"].'" max="'.$cant_max.'" class="form-control" required>';
                                echo '</td>';
                                echo '</tr>';
                              }?>
                            </tbody>
                          </table>
                          <input type="hidden" id="aDetalleOT" name="aDetalleOT" value='<?=json_encode($aDetalleOT)?>'>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
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

        // Setup - add a text input to each footer cell
        tablaLC.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaLC.DataTable({
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
              let cant_max=parseInt(elemento[4])-parseInt(elemento[5]);
              if(isNaN(cant_max)){
                cant_max=elemento[4]
              }
              elemento[5] = `
                <input type="hidden" name="id_posicion[]" value="${elemento["DT_RowId"]}">
                <input type="number" class="form-control" name="cantidad_bajar[]" step="0.01" max="${cant_max}" required>
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

        /*$("form").on("submit",function(e){
          e.preventDefault();
          let aDetalleOT=JSON.parse($("#aDetalleOT").val())
          let nuevoDetalleOT=[]
          tablaOT.find("tbody tr").each(function(){
            let id_posicion=$(this).find("input[name='id_posicion[]']").val();
            let cantidad_bajar=$(this).find("input[name='cantidad_bajar[]']").val();
            nuevoDetalleOT.push({"id_posicion":id_posicion,"cantidad":cantidad_bajar});
          })

          aDetalleOT=aDetalleOT.sort(order);
          nuevoDetalleOT=nuevoDetalleOT.sort(order);

          if (JSON.stringify(aDetalleOT) === JSON.stringify(nuevoDetalleOT)) {
            console.log('Los arrays son iguales');
          } else {
            console.log('Los arrays son diferentes');
          }
        })*/
    
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