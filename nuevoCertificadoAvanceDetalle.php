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

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_GET);
    var_dump($_POST);
  }

  $column_names = [
    1 => "monto_acumulado_avances",
    2 => "monto_acumulado_anticipos",
    3 => "monto_acumulado_desacopios",
    4 => "monto_acumulado_descuentos",
    5 => "monto_acumulado_ajustes",
  ];

  $id_certificado_avance=$_GET['id_certificado_avance'];

  $sql = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_avance]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_certificado_maestro=$data["id_certificado_maestro"];

  $sql = "SELECT COUNT(*) FROM certificados_avances_detalle WHERE id_certificado_avance = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_certificado_avance]);
  $modo_post = ($q->fetchColumn() > 0) ? 'modificar' : 'crear';

    foreach ($_POST["id_certificado_maestro_detalle"] as $key => $id_certificado_maestro_detalle) {
      
      $avance=$_POST['avance'][$key];
      $id_certificado_avance_detalle = isset($_POST['id_certificado_avance_detalle'][$key]) ? $_POST['id_certificado_avance_detalle'][$key] : 0;

      if($avance>0){

        $precio_unitario=$_POST['precio_unitario'][$key];
        $id_tipo_item=$_POST['id_tipo_item'][$key];

        if($id_certificado_avance_detalle>0){

          $sql = "SELECT cantidad_actual, cantidad_acumulado, subtotal FROM certificados_avances_detalle WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$id_certificado_avance_detalle]);
          $data = $q->fetch(PDO::FETCH_ASSOC);

          $cantidad_actual_anterior = ($data and !is_null($data["cantidad_actual"])) ? $data["cantidad_actual"] : 0;
          $cantidad_acumulado_anterior = ($data and !is_null($data["cantidad_acumulado"])) ? $data["cantidad_acumulado"] : 0;
          $subtotal_viejo = ($data and !is_null($data["subtotal"])) ? $data["subtotal"] : 0;

          $total_acumulado=$cantidad_acumulado_anterior-$cantidad_actual_anterior+$avance;
          $subtotal=$avance*$precio_unitario;

          $sql = "UPDATE certificados_avances_detalle SET cantidad_actual = ?, cantidad_acumulado = ?, precio_unitario = ?, subtotal = ? WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$avance,$total_acumulado,$precio_unitario,$subtotal,$id_certificado_avance_detalle]);

          $column_name = $column_names[$id_tipo_item];
          //restamos el subtotal viejo y sumamos el nuevo subtotal en la columna segun el tipo de detalle
          $sql = "UPDATE certificados_avances_cabecera SET $column_name = $column_name - ? WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$subtotal_viejo,$id_certificado_avance]);

          $sql = "UPDATE certificados_avances_cabecera SET $column_name = $column_name + ? WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$subtotal,$id_certificado_avance]);

        } else {

          $sql = "SELECT COALESCE(SUM(cantidad_actual),0) FROM certificados_avances_detalle WHERE id_certificado_maestro_detalle = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$id_certificado_maestro_detalle]);
          $cantidad_acumulado_anterior = $q->fetchColumn();

          $total_acumulado=$cantidad_acumulado_anterior+$avance;
          $subtotal=$avance*$precio_unitario;

          $sql = "INSERT INTO certificados_avances_detalle (id_certificado_avance, id_certificado_maestro_detalle, cantidad_anterior, cantidad_actual, cantidad_acumulado, precio_unitario, subtotal) VALUES (?,?,?,?,?,?,?)";
          $q = $pdo->prepare($sql);
          $q->execute([$id_certificado_avance,$id_certificado_maestro_detalle, $cantidad_acumulado_anterior, $avance, $total_acumulado, $precio_unitario,$subtotal]);

          $column_name = $column_names[$id_tipo_item];
          //sumamos el nuevo subtotal en la columna segun el nuevo tipo de detalle
          $sql = "UPDATE certificados_avances_cabecera SET $column_name = $column_name + ? WHERE id = ?";
          $q = $pdo->prepare($sql);
          $q->execute([$subtotal,$id_certificado_avance]);

        }

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: " . $q->rowCount();
          echo "<br><br>";
        }
        //$id_certificados_maestros_detalles = $pdo->lastInsertId();
      }

    }

    $sql = "SELECT COALESCE(SUM(subtotal),0) FROM certificados_avances_detalle WHERE id_certificado_avance = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_certificado_avance]);
    $monto_total = $q->fetchColumn();

    $sql = "UPDATE certificados_avances_cabecera SET monto_total = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$monto_total,$id_certificado_avance]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: " . $q->rowCount();
      echo "<br><br>";
    }
    
    $accion_log = ($modo_post == 'modificar') ? 'Modificacion Detalle Certificado de Avance #' : 'Nuevo Detalle Certificado de Avance #';
    $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'".$accion_log.$id_certificado_avance."','Certificado de Avance','verCertificadoAvance.php?id=$id_certificado_avance')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: " . $q->rowCount();
      echo "<br><br>";
    }

    if ($modoDebug==1) {
      $pdo->rollBack();
      die();
    }

    Database::disconnect();
    //header("Location: listarCertificadosMaestro.php?id_certificado_avance=".$_GET["id_certificado_avance"]);
    header("Location: listarCertificadosAvances.php?id_certificado_maestro=".$id_certificado_maestro);

}

$id_certificado_avance=$_GET['id_certificado_avance'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT id_certificado_maestro FROM certificados_avances_cabecera WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_avance]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$id_certificado_maestro=$data["id_certificado_maestro"];

$sql = "SELECT COUNT(*) FROM certificados_avances_detalle WHERE id_certificado_avance = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_certificado_avance]);
$modo = ($q->fetchColumn() > 0) ? 'modificar' : 'crear';

Database::disconnect();

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      #dataTables-example667 {
        border: none;
      }
      #dataTables-example667 tbody {
        border: 1px solid #dee2e6;
      }
      #dataTables-example667 tbody td {
        border-top: 1px solid #dee2e6;
      }
      #dataTables-example667 tbody tr:first-child td {
        border-top: none;
      }
      #dataTables-example667 tbody tr.fila-aperturada td {
        background-color: #eef8ff;
      }
      #dataTables-example667 tbody tr.fila-aperturado-start td {
        border-top: 2px solid #2b8dbf;
      }
      #dataTables-example667 tbody tr.fila-aperturado-end td {
        border-bottom: 2px solid #2b8dbf;
      }
      #dataTables-example667 tbody tr.fila-aperturado-start td:nth-child(3),
      #dataTables-example667 tbody tr.fila-aperturado-middle td:nth-child(3),
      #dataTables-example667 tbody tr.fila-aperturado-end td:nth-child(3) {
        border-left: 3px solid #2b8dbf;
      }
      #dataTables-example667 tbody tr.fila-aperturado-start td:last-child,
      #dataTables-example667 tbody tr.fila-aperturado-middle td:last-child,
      #dataTables-example667 tbody tr.fila-aperturado-end td:last-child {
        border-right: 3px solid #2b8dbf;
      }
    </style>
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
          $ubicacion="Certificado de avance";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?= $modo == 'modificar' ? 'Modificar' : 'Nuevo' ?> Detalle del Certificado de Avance #<?=$id_certificado_avance?>
                      &nbsp;&nbsp;
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevoCertificadoAvanceDetalle.php?id_certificado_avance=<?=$id_certificado_avance?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-12">
                              <div class="dt-ext table-responsive">
                                
                                <table class="table table-sm display" id="dataTables-example667" style="width:100%">
                                  <thead>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
									  <th>Acumulado</th>
									  <th>Saldo</th>
                                      <th>Unidad</th>
                                      <th>Precio U.</th>
                                      <th>Avance Actual</th>
                                      <th>Subtotal</th>
                                      <th>Aperturado</th>
                                      <th>Lote</th>
                                    </tr>
                                  </thead>
                                  <tfoot>
                                    <tr>
                                      <th class="d-none">ID</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
									  <th>Acumulado</th>
									  <th>Saldo</th>
                                      <th>Unidad</th>
                                      <th>Precio U.</th>
                                      <th>Avance Actual</th>
                                      <th>Subtotal</th>
                                      <th>Aperturado</th>
                                      <th>Lote</th>
                                    </tr>
                                  </tfoot>
                                  <tbody><?php
                                    $pdo = Database::connect();
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                    $sql = " SELECT cmd.id AS id_certificado_maestro_detalle,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario AS precio_unitario_cm,cmd.subtotal AS subtotal_cm,m.moneda,cad.id AS id_certificado_avance_detalle,cad.cantidad_actual,cad.subtotal AS subtotal_ca,cmd.aperturado,cmd.lote FROM certificados_maestros_detalles cmd INNER JOIN certificados_maestros cm ON cmd.id_certificado_maestro=cm.id INNER JOIN monedas m ON cm.id_moneda=m.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id LEFT JOIN certificados_avances_detalle cad ON cad.id_certificado_maestro_detalle=cmd.id AND cad.id_certificado_avance=$id_certificado_avance WHERE cmd.id_certificado_maestro=$id_certificado_maestro ORDER BY cmd.aperturado, cmd.id";

                                    $rows_tabla = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                                    $total_filas = count($rows_tabla);
                                    foreach ($rows_tabla as $i => $row) {
										$acumulado = 0;
										$sql2 = "select sum(cantidad_actual) acumulado from certificados_avances_detalle where id_certificado_maestro_detalle = ? ";
										$q2 = $pdo->prepare($sql2);
										$q2->execute([$row["id_certificado_maestro_detalle"]]);
										$data2 = $q2->fetch(PDO::FETCH_ASSOC);
										if (!empty($data2['acumulado'])) {
											$acumulado = $data2['acumulado'];
										}

										$ap_actual = (string) ($row["aperturado"] ?? '');
										$ap_prev = ($i > 0) ? (string) ($rows_tabla[$i - 1]["aperturado"] ?? '') : '';
										$ap_next = ($i < $total_filas - 1) ? (string) ($rows_tabla[$i + 1]["aperturado"] ?? '') : '';
										$clase_aperturado = '';
										if ($ap_actual !== '') {
											if ($ap_actual !== $ap_prev && $ap_actual !== $ap_next) {
												$clase_aperturado = 'fila-aperturada';
											} elseif ($ap_actual !== $ap_prev) {
												$clase_aperturado = 'fila-aperturada fila-aperturado-start';
											} elseif ($ap_actual !== $ap_next) {
												$clase_aperturado = 'fila-aperturada fila-aperturado-end';
											} else {
												$clase_aperturado = 'fila-aperturada fila-aperturado-middle';
											}
										}
									   ?>
                                      <tr class="<?=$clase_aperturado?>">
                                        <td class="d-none"><?=$row["id_certificado_maestro_detalle"]?>
                                          <input type="hidden" name="id_certificado_avance_detalle[]" value="<?=($row["id_certificado_avance_detalle"]!==null)?$row["id_certificado_avance_detalle"]:'';?>">
                                        </td>
                                        <td class="d-none" data-id="<?=$row["id_tipo_item_certificado"]?>">
                                          <input type="hidden" name="id_tipo_item[]" value="<?=$row["id_tipo_item_certificado"]?>">
                                          <?=$row["tipo"]?>
                                        </td>
                                        <td><?=$row["descripcion"]?></td>
                                        <td style="text-align:right"><?=$row["cantidad"]?></td>
										<td style="text-align:right"><?=$acumulado?></td>
										<td style="text-align:right"><?=$row["cantidad"]-$acumulado;?></td>
                                        <td data-id="<?=$row["id_unidad_medida"]?>"><?=$row["unidad_medida"]?></td>
                                        <td style="text-align:right">
                                          <?=$row["moneda"]." ".number_format($row["precio_unitario_cm"],2)?>
                                          <input type="hidden" name="precio_unitario[]" value="<?=$row["precio_unitario_cm"]?>">
                                        </td>
                                        <td>
                                          <input type="hidden" name="id_certificado_maestro_detalle[]" value="<?=$row["id_certificado_maestro_detalle"]?>">
                                          <input type="number" step="0.01" class="form-control" name="avance[]" placeholder="Avance" min="0" max="<?=$row["cantidad"]-$acumulado+(($row["cantidad_actual"]!==null)?$row["cantidad_actual"]:0);?>" value="<?=($row["cantidad_actual"]!==null)?$row["cantidad_actual"]:'';?>" oninput="calcularSubtotalAvance(this)" onchange="calcularSubtotalAvance(this)">
                                        </td>
                                        <td style="text-align:right">
                                          <?=$row["moneda"]?> <label class='subtotal_formatted'><?=($row["subtotal_ca"]!==null)?number_format($row["subtotal_ca"],2):'0.00'?></label>
                                          <input type="hidden" name="subtotal[]" value="<?=($row["subtotal_ca"]!==null)?$row["subtotal_ca"]:'';?>">
                                        </td>
                                        <td><?=$row["aperturado"]?></td>
                                        <td><?=$row["lote"]?></td>
                                      </tr><?php
                                    }
                                    Database::disconnect();?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">

                        <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion"><?= $modo == 'modificar' ? 'Modificar' : 'Crear' ?> Certificado de Avance</button>
                        <!-- <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Crear e ir a Certificados</button> -->
                        <a href='listarCertificadosAvances.php?id_certificado_maestro=<?=$id_certificado_maestro?>' class="btn btn-light">Volver</a>

                      </div>
                    </div>
                  </form>
                </div>
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
      function calcularSubtotalAvance(input) {
        var fila = input.closest('tr');
        var precioInput = fila.querySelector("input[name='precio_unitario[]']");
        var subtotalInput = fila.querySelector("input[name='subtotal[]']");
        var subtotalLabel = fila.querySelector('.subtotal_formatted');
        var avance = parseFloat(String(input.value || '').replace(',', '.')) || 0;
        var precioUnitario = parseFloat(String(precioInput.value || '').replace(',', '.')) || 0;
        var subtotal = avance * precioUnitario;

        subtotalInput.value = subtotal.toFixed(2);
        subtotalLabel.textContent = subtotal.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      $(document).ready(function () {

        // Setup - add a text input to each footer cell
        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );

	      $('#dataTables-example667').DataTable({
          stateSave: false,
          responsive: false,
          order: [],
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
        } );

        $("form").on("submit",function(e){
          e.preventDefault();
          let ok=0;
          $("#dataTables-example667 tbody tr").each(function(){
            actualizarSubtotal($(this));
            let avance=$(this).find("input[name='avance[]']").val()
            //let precio_unitario=$(this).find("input[name='precio_unitario[]']").val()
            if(avance.length>0){// && precio_unitario.length>0
              ok=1;
            }
          })
          if(ok==0){
            alert("Debe completar el avance de al menos una fila");
          }else{
            this.submit();
            //console.log("submit");
          }
        })

        function obtenerNumero(valor) {
          valor = String(valor || '').trim().replace(',', '.');
          let numero = parseFloat(valor);
          return isNaN(numero) ? 0 : numero;
        }

        function actualizarSubtotal(fila) {
          let avance = obtenerNumero(fila.find("input[name='avance[]']").val());
          let precioUnitario = obtenerNumero(fila.find("input[name='precio_unitario[]']").val());
          let subtotal = avance * precioUnitario;
          let subtotalFormateado = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

          fila.find("input[name='subtotal[]']").val(subtotal.toFixed(2));
          fila.find(".subtotal_formatted").html(subtotalFormateado);
        }

        $("#dataTables-example667 tbody tr").each(function() {
          calcularSubtotalAvance($(this).find("input[name='avance[]']")[0]);
        });

        $(document).on("input change keyup", "input[name='avance[]']", function() {
          actualizarSubtotal($(this).closest("tr"));
        });

      });
    </script>
  </body>
</html>