<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarComputos.php");
}

if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT c.id AS id_computo, c.nro_revision, c.id_tarea, tt.tipo, t.observaciones, date_format(c.fecha,'%d/%m/%y') AS fecha_computo, c.id_cuenta_solicitante, cu.nombre AS cuenta_realizo, c.id_estado, ec.estado, s.nro_sitio AS sitio, s.nro_subsitio AS subsitio, p.nro AS nro_proyecto, p.nombre AS proyecto, c.nro AS nro_computo FROM computos c LEFT JOIN tareas t ON c.id_tarea=t.id LEFT JOIN tipos_tarea tt on tt.id = t.id_tipo_tarea LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante LEFT JOIN estados_computos ec ON ec.id = c.id_estado INNER JOIN proyectos p on p.id = t.id_proyecto INNER JOIN sitios s on s.id = p.id_sitio WHERE c.id = ? ";

  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head><?php
    include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      /* Estilos para indicar validación */
      .valid {
        border: 2px solid green !important;
      }
      .invalid {
        border: 2px solid red !important;
      }
      /* Asegura que el contenedor de Select2 ocupe todo el ancho dentro del modal */
      #pedidoModal .select2-container {
        width: 100% !important;
      }
      /* Opcional: aumenta el z-index del dropdown dentro del modal */
      #pedidoModal .select2-dropdown {
        z-index: 2100 !important;
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper"><?php
      include('header.php');?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper"><?php
        include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Gestión de Cómputo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion." N° ".$data["nro_computo"]." (".$data["sitio"]."_".$data["subsitio"]."_".$data["nro_proyecto"].")"?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" name="form1" id="form1" action="modificarComputo.php?id=<?=$data['id_computo']; ?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-sm-5">
                              <label class="col-form-label font-weight-bold">Fecha:</label>
                              <label class="col-form-label"><?=$data['fecha_computo'];?></label>
                            </div>
                            <div class="col-sm-7">
                              <label class="col-form-label font-weight-bold">Tarea:</label>
                              <label class="col-form-label"><?=$data['tipo']." / ".$data['observaciones']?></label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <div class="col-sm-5">
                              <label class="col-form-label font-weight-bold">Estado:</label>
                              <label class="col-form-label"><?=$data['estado']?></label>
                            </div>
                            <div class="col-sm-7">
                              <label class="col-form-label font-weight-bold">Realizó:</label>
                              <label class="col-form-label"><?=$data['cuenta_realizo']?></label>
                            </div>
                          </div>
                          <!-- <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Realizó</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta_solicitante" id="id_cuenta_solicitante" class="js-example-basic-single col-sm-12">
                                <option value="">Seleccione...</option><?php
                                /*$pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_cuenta_solicitante']) {
                                      echo " selected ";
                                    }	
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();*/?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Estado</label>
                            <div class="col-sm-9">
                              <select name="id_estado" id="id_estado" class="js-example-basic-single col-sm-12" disabled="disabled"><?php
                                /*$pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `estado` FROM `estados_computos` WHERE 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id']==$data['id_estado']) {
                                    echo " selected ";
                                  }
                                  echo ">".$fila['estado']."</option>";
                                }
                                Database::disconnect();*/?>
                              </select>
                            </div>
                          </div> -->
							
                          <?php
                          $tienePermisoParaReservar = false;
                          if(tienePermiso(310)){
                            $tienePermisoParaReservar = true;
                          }
                          $tienePermisoParaPedir = false;
                          if(tienePermiso(295)){
                            $tienePermisoParaPedir = true;
                          }?>

                          <div class="form-group row">
                            <div class="col-sm-12">
                              <input type="hidden" name="idComputo" value="<?=$_GET['id']; ?>" />
                              <div class="dt-ext table-responsive">
                                <table class="display" id="dataTables-example667">
                                  <thead>
                                    <tr>
                                      <th>Concepto</th>
                                      <th>Solicitado</th>
                                      <th>Necesidad</th>
                                      <th>Aprobado</th>
                                      <th>En Stock</th>
                                      <th>Reservado</th>
                                      <th>Pedido</th>
                                      <th>Comprando</th>
                                      <th>Saldo</th>
                                      <!-- <th>Solicitar</th> -->
                                      <?php if ($tienePermisoParaReservar) { ?><th>Reservar</th><?php }?>
                                      <?php if ($tienePermisoParaPedir) { ?><th>Pedir</th><?php }?>
                                      <th>Opciones</th>
                                    </tr>
                                  </thead>
                                  <tbody><?php
                                    $pdo = Database::connect();
                                    //$sql = " SELECT cd.id AS id_computo_detalle, m.concepto, cd.cantidad, date_format(cd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad, cd.aprobado, cd.id_material, cd.reservado, cd.comprado, m.id AS id_material FROM computos_detalle cd inner join materiales m on m.id = cd.id_material WHERE cd.cancelado = 0 and cd.id_computo = ".$_GET['id'];
                                    $sql = "SELECT cd.id AS id_computo_detalle, m.concepto, cd.cantidad AS cantidad_solicitada, date_format(cd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad, cd.aprobado, cd.id_material, cd.reservado, pd.cantidad AS cantidad_pedida, cd.comprado, m.id AS id_material FROM computos_detalle cd inner join materiales m on m.id = cd.id_material LEFT JOIN pedidos p ON cd.id_computo=p.id_computo LEFT JOIN pedidos_detalle pd ON pd.id_pedido=p.id AND pd.id_material=m.id WHERE cd.cancelado = 0 and cd.id_computo = ".$_GET['id'];
                                    foreach ($pdo->query($sql) as $row) {
                                      /*$id_computo_detalle=$row["id_computo_detalle"];
                                      $cantidad_solicitada=$row["cantidad"];
                                      $aprobado=$row["aprobado"];
                                      $reservado=$row["reservado"];
                                      $comprado=$row["comprado"];

                                      $sql3 = "SELECT SUM(saldo) disponible FROM ingresos_detalle WHERE id_material = ".$row["id_material"];
                                      $q3 = $pdo->prepare($sql3);
                                      $q3->execute();
                                      $data3 = $q3->fetch(PDO::FETCH_ASSOC);
                                      $enStock = 0;
                                      if (!empty($data3['disponible'])) {
                                        $enStock = $data3['disponible'];
                                      }
                                      
                                      $saldo = $cantidad_solicitada-$reservado-$comprado;
                                      
                                      $lblAprobado="No";
                                      $inputReservar="";
                                      $inputPedir="";
                                      if ($aprobado==1) {
                                        $lblAprobado="Si";
                                        
                                        $inputReservar="<input type='number' class='form-control' name='cantidad_reservar_".$id_computo_detalle."' min='0' max='".$saldo."' step='0' value='".$saldo."' onkeyup='validateMax(this)' required='required'>";

                                        $inputPedir="<input type='number' class='form-control' name='cantidad_pedir_".$id_computo_detalle."' min='0' max='".$saldo."' step='0' value='".$saldo."' onkeyup='validateMax(this)' required='required'>";
                                      }*/
                                      
                                      $id_computo_detalle  = $row["id_computo_detalle"];
                                      $cantidad_solicitada = $row["cantidad_solicitada"];
                                      $aprobado           = $row["aprobado"];
                                      $reservado          = $row["reservado"];
                                      $cantidad_pedida    = $row["cantidad_pedida"];
                                      $comprado           = $row["comprado"];

                                      if($cantidad_pedida<1){
                                        $cantidad_pedida = 0;
                                      }

                                      // 1) Calcular stock disponible
                                      $sql3  = "SELECT SUM(saldo) AS disponible FROM ingresos_detalle WHERE id_material = " . $row["id_material"];
                                      $q3    = $pdo->prepare($sql3);
                                      $q3->execute();
                                      $data3 = $q3->fetch(PDO::FETCH_ASSOC);

                                      $enStock = !empty($data3['disponible']) ? $data3['disponible'] : 0;

                                      // 2) Calcular saldo pendiente de atención
                                      //$saldo = $cantidad_solicitada - $reservado - $comprado;
                                      $saldo = $cantidad_solicitada - $reservado - $cantidad_pedida;
                                      if ($saldo < 0) {
                                        $saldo = 0; // no permitir saldo negativo
                                      }

                                      // 4) Preparar etiquetas e inputs
                                      $lblAprobado   = $aprobado ? "Si" : "No";
                                      $inputReservar = "";
                                      $inputPedir    = "";

                                      if ($aprobado == 1) {

                                        // suponemos que ya tienes:
                                        //$saldo        = max($cantidad_solicitada - $reservado - $comprado, 0);
                                        $saldo        = max($cantidad_solicitada - $reservado - $cantidad_pedida, 0);
                                        $maxReservar  = min($saldo, $enStock);

                                        if ($tienePermisoParaReservar){
                                          // atributos para reservar
                                          $valorReservar   = $maxReservar;
                                          $maxAttrReservar = $maxReservar;
                                          $disabledReservar= $maxReservar > 0 ? '' : 'disabled';
                                          $requiredReservar= $maxReservar > 0 ? 'required' : '';

                                          // arma un solo input de reservar
                                          $inputReservar = sprintf(
                                            '<input type="number" class="form-control" name="cantidad_reservar[%d]" min="0" max="%d" step="1" value="%d" onkeyup="validateMax(this)" %s %s>',
                                            $id_computo_detalle,
                                            $maxAttrReservar,
                                            $valorReservar,
                                            $disabledReservar,
                                            $requiredReservar
                                          );
                                        }

                                        if ($tienePermisoParaPedir){
                                          // cantidad sugerida a pedir = lo que queda tras reservar todo lo posible
                                          $sugeridoPedir = max($saldo - $maxReservar, 0);

                                          // atributos para pedir
                                          $maxPedir   = $saldo;              // límite lógico: nunca pedir más del saldo
                                          $valorPedir = $sugeridoPedir;      // sugerencia por defecto

                                          $inputPedir = sprintf(
                                            '<input type="number" class="form-control" name="cantidad_pedir[%d]" min="0" max="%d" step="1" value="%d" onkeyup="validateMax(this)" required>',
                                            $id_computo_detalle,
                                            $maxPedir,
                                            $valorPedir
                                          );
                                        }

                                      }?>
                                      <tr>
                                        <td><?=$row["concepto"]?></td>
                                        <td><?=$cantidad_solicitada?></td>
                                        <td><?=$row["fecha_necesidad"]?></td>
                                        <td><?=$lblAprobado?></td>
                                        <td><?=$enStock?></td>
                                        <td><?=$reservado?></td>
                                        <td><?=$cantidad_pedida?></td>
                                        <td><?=$comprado?></td>
                                        <td class="saldo"><?=$saldo?></td>
                                        <?php if ($tienePermisoParaReservar) { ?><td><?=$inputReservar?></td><?php }?>
                                        <?php if ($tienePermisoParaPedir) { ?><td><?=$inputPedir?></td><?php }
                                          /*if ($aprobado==1) {?>
                                            <td>Si</td>
                                            <td><input type="number" class="form-control" name="cantidad_<?=$id_computo_detalle?>" min="0" max="<?=$saldo?>" step="0" value="<?=$saldo?>" onkeyup="validateMax(this)" required="required"></td><?php
                                          } else {?>
                                            <td>No</td>	
                                            <td><input type="number" class="form-control" name="cantidad_<?=$id_computo_detalle?>" min="0" max="<?=$saldo?>" step="0.01" disabled="disabled"></td><?php
                                          }*/?>
                                        <td><?php
                                          if (!empty(tienePermiso(294))) {
                                            /*if ($aprobado==0) {?>
                                              <a href="#" data-toggle="modal" data-target="#aprobarModal_<?=$id_computo_detalle?>"><img src="img/aprobar.png" width="24" height="25" border="0" alt="Aprobar" title="Aprobar"></a>
                                              &nbsp;&nbsp;<?php
                                            }*/
                                          }
                                          if (!empty(tienePermiso(311))) {
                                            if ($reservado > 0) {?>
                                              <a href="cancelarStockPedido.php?id=<?=$id_computo_detalle?>&idComputo=<?=$_GET['id']?>"><img src="img/neg.png" width="24" height="25" border="0" alt="Cancelar Reserva" title="Cancelar Reserva"></a>
                                              &nbsp;&nbsp;<?php
                                            }
                                          }?>
                                        </td>
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
  
                    <input type="hidden" name="fecha" id="hiddenFecha">
                    <input type="hidden" name="lugar_entrega" id="hiddenLugar">
                    <input type="hidden" name="id_cuenta_recibe" id="hiddenRecibe">
                    
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <?php if(tienePermiso(290)){?> <button class="btn btn-success" type="submit">Ejecutar</button><?php }?>
                        <?php /*if(tienePermiso(295)){?> <a class="btn btn-warning" id="pedido-masivo" onclick="pedir();">Hacer Pedido</a><?php }*/?>
                        <?php /*if(tienePermiso(310)){?> <a class="btn btn-danger" id="reserva-masivo" onclick="reservar();">Hacer Reserva</a><?php }*/?>
                        <a class="btn btn-primary" target="_blank" href="imprimirComputo.php?id=<?=$data['id_computo']; ?>">Imprimir</a>
                        <a href="listarComputos.php" class="btn btn-light">Volver</a>
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
    
    <!-- 2) Modal Bootstrap para solicitar datos de pedido -->
    <div class="modal fade" id="pedidoModal" tabindex="-1" role="dialog" aria-labelledby="pedidoModalLabel">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <form id="pedidoForm">
            <div class="modal-header">
              <h5 class="modal-title" id="pedidoModalLabel">Datos del Pedido</h5>
              <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">

              <div class="form-group row">
                <label for="inputFecha" class="col-sm-3 col-form-label">Fecha Pedido(*)</label>
                <div class="col-sm-9">
                  <input name="fecha" id="inputFecha" type="date" autofocus onfocus="this.showPicker()" value="<?php echo date('Y-m-d');?>" class="form-control" required="required">
                </div>
              </div>
              <div class="form-group row">
                <label for="inputLugar" class="col-sm-3 col-form-label">Lugar de Entrega(*)</label>
                <div class="col-sm-9"><?php
                  $pdo = Database::connect();
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                  $sql = "SELECT valor FROM parametros WHERE id = 8 ";
                  $q = $pdo->prepare($sql);
                  $q->execute();
                  $data = $q->fetch(PDO::FETCH_ASSOC);
                  $direccion = $data['valor'];?>
                  <input name="lugar_entrega" id="inputLugar" type="text" maxlength="199" class="form-control" required="required" value="<?=$direccion;?>">
                </div>
              </div>
              <div class="form-group row">
                <label for="inputRecibe" class="col-sm-3 col-form-label">Recibe(*)</label>
                <div class="col-sm-9">
                  <select name="id_cuenta_recibe" id="inputRecibe" class="js-example-basic-single col-sm-12" required="required">
                    <option value="">Seleccione...</option><?php
                    $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                    $q = $pdo->prepare($sqlZon);
                    $q->execute();
                    while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                      <option value='<?=$fila['id']?>'><?=$fila['nombre']?></option><?php
                    }
                    Database::disconnect();?>
                  </select>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Confirmar Pedido</button>
            </div>
          </form>
        </div>
      </div>
    </div><?php
    $pdo = Database::connect();
    $sql = " SELECT d.id AS id_computo_detalle, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), d.aprobado,d.id_computo FROM computos_detalle d inner join materiales m on m.id = d.id_material WHERE d.id_computo = ".$_GET['id'];
    foreach ($pdo->query($sql) as $row) {?>
      <div class="modal fade" id="aprobarModal_<?=$row["id_computo_detalle"]?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
              <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">¿Está seguro que desea aprobar el ítem del cómputo?</div>
            <div class="modal-footer">
              <a href="aprobarComputoDetalle.php?id=<?=$row["id_computo_detalle"]?>&idComputo=<?=$row["id_computo"]?>" class="btn btn-primary">Aprobar</a>
              <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
            </div>
          </div>
        </div>
      </div><?php
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
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	  <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
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
	  <script>
		  $(document).ready(function() {

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

        /*
        // 3) **Al cargar la página**, validar todas las filas
        $('#dataTables-example667 tbody tr').each(function() {
          validarFila($(this));
        });

        // Detectar cambios en cualquiera de los inputs de reservar/pedir
        $('#dataTables-example667 tbody').on('input', 'input[name^="cantidad_reservar"], input[name^="cantidad_pedir"]', function() {
          const $tr = $(this).closest('tr');
          validarFila($tr);
        });

        // Al enviar el formulario, revisar todas las filas
        $("form").on("submit", function(e) {
          let todoValido = true;
          $('#dataTables-example667 tbody tr').each(function() {
            const valido = validarFila($(this));
            if (!valido) {
              todoValido = false;
            }
          });

          if (!todoValido) {
            e.preventDefault();
            alert('Hay al menos una fila donde reservar + pedir supera el saldo. Por favor, corrige antes de enviar.');
          } else {
            // opcional: mostrar feedback o dejar que el formulario continúe
            console.log('Formulario válido, enviando…');
          }
        });*/
  
      });

      //$(function() {
        // Función que verifica un row concreto
        /*function validarFila($tr) {
          // Leer saldo de la fila
          const saldo = parseInt($tr.find('.saldo').text(), 10) || 0;
          // Leer valores de reservar y pedir
          const reservar = parseInt($tr.find('input[name^="cantidad_reservar"]').val(), 10) || 0;
          const pedir    = parseInt($tr.find('input[name^="cantidad_pedir"]').val(), 10)    || 0;
          const suma     = reservar + pedir;

          // Seleccionar ambos inputs
          const $inputs = $tr.find('input[name^="cantidad_reservar"], input[name^="cantidad_pedir"]');

          if (suma > saldo) {
            // invalidar
            $inputs.removeClass('valid').addClass('invalid');
            return false;
          } else {
            // marcar como válido
            $inputs.removeClass('invalid').addClass('valid');
            return true;
          }
        }*/

        
      //});

      function validateMax(e) {
        if (parseFloat(e.value) > parseFloat(e.max)) {
          e.value = e.max;
        } 
      }

      function reservar() {
        document.getElementById('form1').action="reservarStockPedido.php";
        document.getElementById('form1').submit();
      }
      function pedir() {
        document.getElementById('form1').method="get";
        document.getElementById('form1').action="nuevoPedido.php";
        document.getElementById('form1').submit();
      }
		</script>
    <script>
      $(function() {
        let datosPedidoListos = false;

        function validarFila($tr) {
          const saldo    = parseInt($tr.find('.saldo').text(), 10) || 0;
          const reservar = parseInt($tr.find('input[name^="cantidad_reservar"]').val(), 10) || 0;
          const pedir    = parseInt($tr.find('input[name^="cantidad_pedir"]').val(), 10) || 0;
          const suma     = reservar + pedir;
          const $inputs  = $tr.find('input[name^="cantidad_reservar"], input[name^="cantidad_pedir"]');

          if (suma > saldo) {
            $inputs.addClass('invalid').removeClass('valid');
            return false;
          } else {
            $inputs.addClass('valid').removeClass('invalid');
            return true;
          }
        }

        function hayPedido() {
          let tiene = false;
          $('input[name^="cantidad_pedir"]').each(function() {
            if (parseInt(this.value, 10) > 0) {
              tiene = true;
              return false;
            }
          });
          return tiene;
        }

        // Cuando se abre el modal, inicializa (o reinicializa) Select2
        $('#pedidoModal').on('shown.bs.modal', function() {
          $('#inputRecibe').select2({
            dropdownParent: $('#pedidoModal'),
            width: '100%',
            placeholder: 'Seleccione...',
            allowClear: true
          });
        });

        // validación en tiempo real
        $('#dataTables-example667 tbody').on('input','input[name^="cantidad_reservar"], input[name^="cantidad_pedir"]',function() {
          validarFila($(this).closest('tr'));
        });

        // handler de submit
        $('#form1').on('submit', function(e) {
          e.preventDefault(); // frenamos siempre la primera vez

          // 1) validar sumas
          let todoValido = true;
          $('#dataTables-example667 tbody tr').each(function() {
            if (!validarFila($(this))) {
              todoValido = false;
            }
          });
          if (!todoValido) {
            return alert('Hay al menos una fila donde reservar + pedir supera el saldo.');
          }

          // 2) si ya estamos retomando tras el modal, enviamos
          if (datosPedidoListos) {
            // quitamos el handler para no bloquear el envío
            $(this).off('submit');
            return this.submit();
          }

          // 3) si no hay pedido, enviamos directamente
          if (!hayPedido()) {
            $(this).off('submit');
            return this.submit();
          }

          // 4) hay pedido y aún no tenemos datos -> abrimos modal
          $('#pedidoModal').modal('show');
        });

        // al confirmar modal
        $('#pedidoForm').on('submit', function(e) {
          e.preventDefault();
          const fecha  = $('#inputFecha').val();
          const lugar  = $('#inputLugar').val().trim();
          const recibe = $('#inputRecibe').val();

          if (!fecha || !lugar || !recibe) {
            return alert('Completa todos los campos del pedido.');
          }

          // llenamos los hidden del form principal
          $('#hiddenFecha').val(fecha);
          $('#hiddenLugar').val(lugar);
          $('#hiddenRecibe').val(recibe);

          datosPedidoListos = true;
          $('#pedidoModal').modal('hide');
          // disparamos de nuevo el submit para recargar el flujo
          $('#form1').submit();
        });

        // al cargar página, marcar filas existentes
        $('#dataTables-example667 tbody tr').each(function() {
          validarFila($(this));
        });
      });

    </script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>