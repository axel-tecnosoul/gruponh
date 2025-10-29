<?php
session_start();
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
include 'database.php';
$nro="";
if (isset($_POST['nro'])){
  $nro=$_POST['nro'];
}
$fecha="";
if (isset($_POST['fecha'])){
  $fecha=$_POST['fecha'];
}
$fechah="";
if (isset($_POST['fechah'])){
  $fechah=$_POST['fechah'];
}
$id_estado=array();
if (isset($_POST['id_estado'])) {
  $id_estado=$_POST['id_estado'];
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
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
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
          $ubicacion="Pedidos ";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-body">
                    <form class="form-inline theme-form mt-3" name="form1" method="post" action="listarPedidos.php">
                      <div class="form-group mb-0">
						            N.Sitio/N.Proy:&nbsp;<input class="form-control" size="3" type="text" value="<?=$nro?>" name="nro">
					            </div>
					            <div class="form-group mb-0">
						            Rango:&nbsp;<input class="form-control" size="20" type="date" value="<?=$fecha?>" name="fecha">-<input class="form-control" size="20" type="date" value="<?=$fechah?>" name="fechah">
					            </div>
                      <div class="form-group mb-0">
                        Estado:&nbsp;
                        <select name="id_estado[]" id="id_estado" class="js-example-basic-multiple" multiple="multiple">
                          <option value="">Todos</option><?php
                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $sqlZon = "SELECT id, estado FROM estados_pedidos WHERE 1 order by estado ";
                          $q = $pdo->prepare($sqlZon);
                          $q->execute();
                          while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                            $selected="";
                            if (in_array($fila['id'],$id_estado)) {
                              $selected=" selected ";
                            }?>
                            <option value='<?=$fila['id']?>' <?=$selected?>><?=$fila['estado']?></option><?php
                          }
                          Database::disconnect();?>
                        </select>
                      </div>
                      <!-- <div class="form-group mb-0">
                        Aprobado:&nbsp;
                        <select name="aprobado" id="aprobado" class="form-control">
                          <option value="">Seleccione...</option>
                          <option value="1" <?php /*  if (isset($_POST['aprobado'])) { if ($_POST['aprobado']==1) { echo " selected "; } }  */?> >Si</option>
                          <option value="2" <?php /*  if (isset($_POST['aprobado'])) { if ($_POST['aprobado']==2) { echo " selected "; } }  */?> >No</option>
                        </select>
					            </div> -->
                      <div class="form-group mb-0">
                        <button class="btn btn-primary" onclick="document.form1.target='_self';document.form1.action='listarPedidos.php'">Buscar</button>
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
                    <h5><?php echo $ubicacion;
                      if (!empty(tienePermiso(295))) { ?>
                        <a href="nuevoPedidoDirecto.php"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo Pedido Directo" title="Nuevo Pedido Directo"></a>&nbsp;<?php
                      }
                      if (!empty(tienePermiso(295))) {?>
                        <a href="#" id="link_modificar_pedido"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp;<?php
                      }?>
                      <a href="#" id="link_ver_pedido"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver Pedido" title="Ver Pedido"></a>
                      &nbsp;&nbsp;
                      <a href="#" id="link_gestionar_pedido"><img src="img/medalla-dorada.png" width="24" height="15" border="0" alt="Gestionar Pedido" title="Gestionar Pedido"></a>
                      &nbsp;&nbsp;
                      <a href="exportPedidos.php"><img src="img/xls.png" width="24" height="25" border="0" alt="Exportar" title="Exportar"></a>&nbsp;&nbsp;<?php
                      if (!empty(tienePermiso(303))) {?>
                        <a href="#" id="link_aprobar_pedido"><img src="img/aprobar.png" width="24" height="25" border="0" alt="Aprobar" title="Aprobar"></a>&nbsp;&nbsp;
                        <a href="#" id="link_rechazar_pedido"><img src="img/neg.png" width="24" height="25" border="0" alt="Rechazar/Eliminar" title="Rechazar/Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(284))) {?>
                        <a href="#" id="link_nuevo_suceso"><img src="img/venc.jpg" width="24" height="25" border="0" alt="Agregar Suceso" title="Agregar Suceso"></a>&nbsp;&nbsp;<?php
                      }?>
					          </h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example666">
                        <thead>
                          <tr>
                            <th>Nro.</th>
                            <th>Obra</th>
                            <th>Fecha de Carga</th>
                            <th>Fecha Entrega Pedida</th>
                            <th>Fecha Pactada Prov.</th>
                            <th>Estado</th>
                            <th>Solicitante</th>
                            <!-- <th>Aprobado</th> -->
                            <th>Tipo</th>
                            <th style="display: none;">Proy</th>
                          </tr>
                        </thead>
                        <tbody><?php
                          if (!empty($_POST)) {

                            $filtroNro="";
                            if ($nro!="") {
                              $ex=explode("/", $nro);
                              if(count($ex)>1){
                                $sitio = $ex[0];
                                $proyecto = $ex[1];
                                $filtroNro = " AND (p.nro = ".intval($proyecto)." AND s.nro_sitio = ".intval($sitio).") ";
                              }else{
                                $filtroNro = " AND (p.nro = ".intval($nro)." OR s.nro_sitio = ".intval($nro).") ";
                              }
                            }
                            $filtroFecha="";
                            if ($fecha!="") {
                              $filtroFecha .= " AND pe.fecha >= '".$fecha."' ";
                            }
                            $filtroFechah="";
                            if ($fechah!="") {
                              $filtroFechah .= " AND pe.fecha <= '".$fechah."' ";
                            }
                            /*if (isset($_POST['aprobado']) && in_array($_POST['aprobado'], [1, 2])) {
                              $sql1 .= " AND pe.aprobado = " . ($_POST['aprobado'] == 1 ? 1 : 0);
                            }*/
                            $filtroEstado="";
                            if (!empty($id_estado) && !empty($id_estado[0])) {
                              $filtroEstado .= " AND ep.id IN (".implode(', ', array_map('intval', $id_estado)).") ";
                            }

                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                            $sql1 = "SELECT pe.id, s.nro_sitio, s.nro_subsitio, p.nro, pe.fecha, p.fecha_entrega, (SELECT MIN(c.fecha_emision) FROM compras c WHERE c.id_pedido = pe.id) AS fecha_pactada_prov, ep.estado, p.solicitante, pe.aprobado, p.id AS id_proyecto
                            FROM pedidos pe 
                              INNER JOIN computos c ON c.id = pe.id_computo 
                              INNER JOIN tareas t ON t.id = c.id_tarea 
                              INNER JOIN proyectos p ON p.id = t.id_proyecto 
                              LEFT JOIN sitios s ON s.id = p.id_sitio 
                              INNER JOIN estados_pedidos ep ON ep.id = pe.id_estado 
                            WHERE 1 ".$filtroNro.$filtroFecha.$filtroFechah.$filtroEstado;

                            foreach ($pdo->query($sql1) as $row) {
                              $obra=htmlspecialchars($row['nro_sitio']).'/'.htmlspecialchars($row['nro_subsitio']).'/'.htmlspecialchars($row['nro']);
                              $fecha_entrega_valida = ($row['fecha_entrega'] && $row['fecha_entrega'] != '0000-00-00');
                              $fecha_pactada_valida = ($row['fecha_pactada_prov'] && $row['fecha_pactada_prov'] != '0000-00-00');?>
                              <tr>
                                <td><?=htmlspecialchars($row['id'])?></td>
                                <td><?=$obra?></td>
                                <td>
                                  <span style="display: none;"><?=date('Ymd', strtotime($row['fecha']))?></span>
                                  <?=date('d/m/Y', strtotime($row['fecha']))?></td>
                                <td>
                                  <span style="display: none;"><?=($fecha_entrega_valida ? date('Ymd', strtotime($row['fecha_entrega'])) : 0)?></span>
                                  <?=($fecha_entrega_valida ? date('d/m/Y', strtotime($row['fecha_entrega'])) : 'N/A') ?></td>
                                <td>
                                  <span style="display: none;"><?=($fecha_pactada_valida ? date('Ymd', strtotime($row['fecha_pactada_prov'])) : 0)?></span>
                                  <?=($fecha_pactada_valida ? date('d/m/Y', strtotime($row['fecha_pactada_prov'])) : 'N/A') ?></td>
                                <td><?=htmlspecialchars($row['estado']) ?></td>
                                <td><?=htmlspecialchars($row['solicitante']) ?></td>
                                <!-- <td><?=($row['aprobado'] == 1 ? 'Si' : 'No') ?></td> -->
                                <td>Computo</td>
                                <td style="display: none;"><?=htmlspecialchars($row['id_proyecto']) ?></td>
                              </tr><?php
                            }

                            $sql2 = "SELECT pe.id, s.nro_sitio, s.nro_subsitio, p.nro, pe.fecha, p.fecha_entrega, (SELECT MIN(c.fecha_emision) FROM compras c WHERE c.id_pedido = pe.id) AS fecha_pactada_prov, ep.estado, p.solicitante, pe.aprobado, pe.id_proyecto
                            FROM pedidos pe 
                              INNER JOIN proyectos p ON p.id = pe.id_proyecto 
                              LEFT JOIN sitios s ON s.id = p.id_sitio 
                              INNER JOIN estados_pedidos ep ON ep.id = pe.id_estado 
                            WHERE pe.id_computo IS NULL ".$filtroNro.$filtroFecha.$filtroFechah.$filtroEstado;
                              
                            /*if (!empty($_POST['nro'])) { $sql2 .= " AND (p.nro = '".intval($_POST['nro'])."' OR s.nro_sitio = '".intval($_POST['nro'])."') "; }
                            if (!empty($_POST['fecha'])) { $sql2 .= " AND pe.fecha >= '".$_POST['fecha']."' "; }
                            if (!empty($_POST['fechah'])) { $sql2 .= " AND pe.fecha <= '".$_POST['fechah']."' "; }
                            if (isset($_POST['aprobado']) && in_array($_POST['aprobado'], [1, 2])) { $sql2 .= " AND pe.aprobado = " . ($_POST['aprobado'] == 1 ? 1 : 0); }
                            if (!empty($_POST['id_estado']) && !empty($_POST['id_estado'][0])) { $sql2 .= " AND ep.id IN (".implode(', ', array_map('intval', $_POST['id_estado'])).") "; }*/

                            foreach ($pdo->query($sql2) as $row) {
                              $obra=htmlspecialchars($row['nro_sitio']).'/'.htmlspecialchars($row['nro_subsitio']).'/'.htmlspecialchars($row['nro']);
                              $fecha_entrega_valida = ($row['fecha_entrega'] && $row['fecha_entrega'] != '0000-00-00');
                              $fecha_pactada_valida = ($row['fecha_pactada_prov'] && $row['fecha_pactada_prov'] != '0000-00-00');?>

                              <tr>
                              <td><?=htmlspecialchars($row['id'])?></td>
                              <td><?=$obra?></td>
                              <td>
                                <span style="display: none;"><?=date('Ymd', strtotime($row['fecha']))?></span>
                                <?=date('d/m/Y', strtotime($row['fecha'])) ?>
                              </td>
                              <td>
                                <span style="display: none;"><?=($fecha_entrega_valida ? date('Ymd', strtotime($row['fecha_entrega'])) : 0) ?></span>
                                <?=($fecha_entrega_valida ? date('d/m/Y', strtotime($row['fecha_entrega'])) : 'N/A') ?>
                              </td>
                              <td>
                                <span style="display: none;"><?=($fecha_pactada_valida ? date('Ymd', strtotime($row['fecha_pactada_prov'])) : 0) ?></span>
                                <?=($fecha_pactada_valida ? date('d/m/Y', strtotime($row['fecha_pactada_prov'])) : 'N/A') ?>
                              </td>
                              <td><?=htmlspecialchars($row['estado']) ?></td>
                              <td><?=htmlspecialchars($row['solicitante']) ?></td>
                              <!-- <td><?=($row['aprobado'] == 1 ? 'Si' : 'No') ?></td> -->
                              <td>Directo</td>
                              <td style="display: none;"><?=htmlspecialchars($row['id_proyecto']) ?></td>
                              </tr><?php
                            }
                            Database::disconnect();
                          }?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <th>Nro.</th>
                            <th>Obra</th>
                            <th>Fecha de Carga</th>
                            <th>Fecha Entrega Pedida</th>
                            <th>Fecha Pactada Prov.</th>
                            <th>Estado</th>
                            <th>Solicitante</th>
                            <!-- <th>Aprobado</th> -->
                            <th>Tipo</th>
                            <th style="display: none;">Proy</th>
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
                    <h5>Conceptos</h5>
                  </div>
                  <div class="card-body">
                    <div class="dt-ext table-responsive">
                      <table class="display truncate" id="dataTables-example667">
                        <thead>
                          <tr>
                            <th>Concepto</th>
                            <th>Requerido</th>
                            <th>Stock</th>
                            <th>Reservado</th>
                            <th>Comprado</th>
                            <th>Fecha Necesidad</th>
                            <th>Fecha Última Compra</th>
                            <th>Costo Último Precio</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
						            <tfoot>
                          <tr>
                            <th>Concepto</th>
                            <th>Requerido</th>
                            <th>Stock</th>
                            <th>Reservado</th>
                            <th>Comprado</th>
                            <th>Fecha Necesidad</th>
                            <th>Fecha Última Compra</th>
                            <th>Costo Último Precio</th>
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
	
	  <!-- <div style="width: 0;height: 0;display: none;">
      <select id="select_estado_base"><?php
        $pdo = Database::connect();
        $sql = "SELECT id,estado FROM estados_pedidos";
        foreach ($pdo->query($sql) as $row) {
          echo '<option value="'.$row["id"].'">'.$row["estado"].'</option>';
        }
        Database::disconnect();?>
      </select>
    </div> -->
  <?php
    $pdo = Database::connect();
    $sql = " SELECT pe.`id`, s.nombre, p.descripcion, t.`estructura`, date_format(pe.`fecha`,'%d/%m/%y'), cu.`nombre`, pe.`lugar_entrega`, pe.`aprobado` FROM pedidos pe inner join `computos` c on c.id = pe.id_computo inner join cuentas cu on cu.id = pe.id_cuenta_recibe inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto left join sitios s on s.id = p.id_sitio WHERE 1 ";
	foreach ($pdo->query($sql) as $row) {
        ?>
  <div class="modal fade" id="aprobarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea aprobar el pedido?</div>
      <div class="modal-footer">
      <a href="aprobarPedido.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Aprobar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <div class="modal fade" id="rechazarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea rechazar el pedido?</div>
      <div class="modal-footer">
      <a href="rechazarPedido.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Rechazar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <?php
    }
	$sql = " SELECT pe.`id`, s.nombre, p.descripcion, p.descripcion, date_format(pe.`fecha`,'%d/%m/%y'), cu.`nombre`, pe.`lugar_entrega`, pe.`aprobado` FROM pedidos pe inner join `proyectos` p on p.id = pe.id_proyecto inner join cuentas cu on cu.id = pe.id_cuenta_recibe left join sitios s on s.id = p.id_sitio WHERE 1 ";
	foreach ($pdo->query($sql) as $row) {
        ?>
  <div class="modal fade" id="aprobarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea aprobar el pedido?</div>
      <div class="modal-footer">
      <a href="aprobarPedido.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Aprobar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <div class="modal fade" id="rechazarModal_<?php echo $row[0]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
      <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
      <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
      </div>
      <div class="modal-body">¿Está seguro que desea rechazar el pedido?</div>
      <div class="modal-footer">
      <a href="rechazarPedido.php?id=<?php echo $row[0]; ?>" class="btn btn-primary">Rechazar</a>
      <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
      </div>
    </div>
    </div>
  </div>
  <?php
    }
    Database::disconnect();
    ?>
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
      get_conceptos(0);

      // Setup - add a text input to each footer cell
      $('#dataTables-example666 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      } );

      $('#dataTables-example666').DataTable({
        stateSave: false,
        searching: false,
        responsive: false,
        dom: 'Bfrtp<"bottom"l>',
        buttons: [
          'excel'
        ],
        lengthMenu: [
          [10, 25, 50, 100, 500, 1000],
          [10, 25, 50, 100, 500, 1000]
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
        
      $("#link_ver_pedido").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un pedido para verlo")
        }
      });
      $("#link_gestionar_pedido").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un pedido para gestionarlo")
        }
      });
      $("#link_modificar_pedido").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un pedido directo para modificar")
        }
      });
      $("#link_aprobar_pedido").on("click",function(){
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un pedido para aprobar")
        }
      });
      $("#link_rechazar_pedido").on("click",function(){
        let target=this.dataset.target;
        if(target==undefined || target=="#"){
          alert("Por favor seleccione un pedido para rechazar")
        }
      });
      $("#link_nuevo_suceso").on("click",function(){
        let l=document.location.href;
        if(this.href==l || this.href==l+"#"){
          alert("Por favor seleccione un pedido para añadir un nuevo suceso")
        }
      });
      
      $(document).on("click", "#dataTables-example666 tbody tr", function() {
        var t = $(this);
        var table = $('#dataTables-example666').DataTable();

        let id_pedido = t.find("td:nth-child(1)").html()?.trim() || '';
        let estado = t.find("td:nth-child(6)").html()?.trim() || '';
        let tipo = t.find("td:nth-child(8)").html()?.trim() || '';
        let id_proyecto = t.find("td:nth-child(9)").html()?.trim() || '';

        if (t.hasClass('selected')) {
          t.removeClass('selected');
          $('#dataTables-example667').DataTable().clear().draw();
          $("#link_ver_pedido").attr("href", "#");
          $("#link_gestionar_pedido").attr("href", "#");
          $("#link_modificar_pedido").attr("href", "#");
          $("#link_nuevo_suceso").attr("href", "#");
          $("#link_aprobar_pedido").attr("data-target", "#").removeAttr("data-toggle");
          $("#link_rechazar_pedido").attr("data-target", "#").removeAttr("data-toggle");
        } else {
          table.$('tr.selected').removeClass('selected');
          t.addClass('selected');
          get_conceptos(id_pedido);
          
          if (tipo === 'Directo') {
            $("#link_modificar_pedido").attr("href", "itemsPedidoDirecto.php?id=" + id_pedido);
            //if (estado === 'Aprobado') {
              $("#link_ver_pedido").attr("href", "verPedidoDirecto.php?id=" + id_pedido);
            /*} else {
              $("#link_ver_pedido").attr("href", "#");
            }*/
          } else {
            $("#link_modificar_pedido").attr("href", "#");
            //if (estado === 'Aprobado') {
              $("#link_ver_pedido").attr("href", "verPedido.php?id=" + id_pedido);
            /*} else {
              $("#link_ver_pedido").attr("href", "#");
            }*/
          }

          if (estado === 'Pendiente' || estado === 'Generado' || estado === 'A Evaluar') {
            $("#link_aprobar_pedido").attr("data-toggle", "modal").attr("data-target", "#aprobarModal_" + id_pedido);
            $("#link_rechazar_pedido").attr("data-toggle", "modal").attr("data-target", "#rechazarModal_" + id_pedido);
          } else {
            $("#link_aprobar_pedido").attr("data-target", "#").removeAttr("data-toggle");
            $("#link_rechazar_pedido").attr("data-target", "#").removeAttr("data-toggle");
          }

          $("#link_nuevo_suceso").attr("href", "nuevoSuceso.php?desdePedidos=1&id=" + id_proyecto);
        }
      });
        
    });
  
    /*$(document).ready(function() {
      // Setup - add a text input to each footer cell
      $('#dataTables-example667 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
      } );
	    
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
		  });
	  
    } );*/
	
	  function selectRow(t){
      t.addClass('selected');
    }
    function deselectRow(t){
      t.removeClass('selected');
    }

    function get_conceptos(id_pedido){
      let datosUpdate = new FormData();
      datosUpdate.append('id_pedido', id_pedido);
      $.ajax({
        data: datosUpdate,
        url: 'get_conceptos_pedido.php',
        method: "post",
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
          data = JSON.parse(data);

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

        }
      });
    }
    
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <!-- Plugin used-->
  </body>
</html>