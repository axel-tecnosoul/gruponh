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
  header("Location: listarCompras.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Validar Estado de la Compra
$sqlEstado = "SELECT id_estado_compra, ec.estado FROM compras c LEFT JOIN estados_compra ec ON ec.id = c.id_estado_compra WHERE c.id = ?";
$qEstado = $pdo->prepare($sqlEstado);
$qEstado->execute([$id]);
$estadoData = $qEstado->fetch(PDO::FETCH_ASSOC);

if (!$estadoData) {
  Database::disconnect();
  header("Location: listarCompras.php");
  exit();
}

// Estados permitidos para ingreso: 3 (Enviada), 6 (Entrega parcial)
$estadosPermitidos = [3, 6];
if (!in_array((int)$estadoData['id_estado_compra'], $estadosPermitidos)) {
  Database::disconnect();
  $_SESSION['flash_message'] = [
    'type' => 'error', 
    'message' => 'No se puede ingresar material para esta orden de compra. Estado actual: ' . $estadoData['estado'] . '. Estados permitidos: Enviada, Entrega parcial.'
  ];
  header("Location: listarCompras.php");
  exit();
}
Database::disconnect();

if (!empty($_POST)) {
  // insert data
  /*$pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  $sql = "UPDATE compras set fecha_entrega = ?, id_forma_pago = ?, id_estado_compra = ?, comentarios = ?, id_moneda = ?, tipo_cambio_dia = ? where id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$_POST['fecha_entrega'],$_POST['id_forma_pago'],$_POST['id_estado_compra'],$_POST['comentarios'],$_POST['id_moneda'],$_POST['tipo_cambio_dia'],$_GET['id']]);

  $sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de orden de compra','Compras','verCompra.php?id=$id')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));
        
  Database::disconnect();*/
		
  header("Location: listarCompras.php");
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  // Consulta igual a verCompra.php para consistencia
  $sql = "SELECT c.id, c.id_pedido, c.id_cuenta_proveedor, DATE_FORMAT(c.fecha_emision, '%d/%m/%Y') AS fecha_emision_formatted, DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') AS fecha_entrega_formatted, c.fecha_emision, c.fecha_entrega, c.id_forma_pago, c.id_estado_compra, c.nro_revision, c.total, c.comentarios, pe.lugar_entrega, c.adjunto_factura, c.id_moneda, c.tipo_cambio_dia, c.iva, c.descuento, prov.nombre AS proveedor_nombre, fp.forma_pago, ec.estado AS estado_compra, m.moneda, COALESCE(pc.id, pd.id) AS proyecto_id, COALESCE(pc.nombre, pd.nombre) AS proyecto_nombre, COALESCE(pc.nro, pd.nro) AS proyecto_nro, COALESCE(sc.nro_sitio, sd.nro_sitio) AS nro_sitio, COALESCE(sc.nro_subsitio, sd.nro_subsitio) AS nro_subsitio
  FROM compras c 
    INNER JOIN pedidos pe ON pe.id = c.id_pedido 
    LEFT JOIN cuentas prov ON prov.id = c.id_cuenta_proveedor 
    LEFT JOIN formas_pago fp ON fp.id = c.id_forma_pago 
    LEFT JOIN estados_compra ec ON ec.id = c.id_estado_compra 
    LEFT JOIN monedas m ON m.id = c.id_moneda
    LEFT JOIN computos co ON co.id = pe.id_computo 
    LEFT JOIN tareas t ON t.id = co.id_tarea 
    LEFT JOIN proyectos pc ON pc.id = t.id_proyecto 
    LEFT JOIN sitios sc ON sc.id = pc.id_sitio 
    LEFT JOIN proyectos pd ON pd.id = pe.id_proyecto 
    LEFT JOIN sitios sd ON sd.id = pd.id_sitio 
  WHERE c.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $proyectoDisplay = '';
  $codigoObra = '';

  if ($data) {
    $codigoObraPartes = array_filter([
      $data['nro_sitio'] ?? null,
      $data['nro_subsitio'] ?? null,
      $data['proyecto_nro'] ?? null
    ], function ($valor) {
      return $valor !== null && $valor !== '';
    });
    $codigoObra = !empty($codigoObraPartes) ? implode('-', $codigoObraPartes) : '';
    
    if (!empty($data['proyecto_id'])) {
      if (!empty($codigoObra) && !empty($data['proyecto_nombre'])) {
        $proyectoDisplay = $codigoObra . ': ' . $data['proyecto_nombre'];
      } elseif (!empty($codigoObra)) {
        $proyectoDisplay = $codigoObra;
      } elseif (!empty($data['proyecto_nombre'])) {
        $proyectoDisplay = $data['proyecto_nombre'];
      }
    }
  }

  $remitosList = [];
  $sql_rem = "SELECT DISTINCT i.id, i.nro_remito, DATE_FORMAT(i.fecha_remito, '%d/%m/%Y') as fecha_fmt 
              FROM ingresos i 
              INNER JOIN ingresos_detalle id ON id.id_ingreso = i.id 
              WHERE id.id_compra = ? 
              ORDER BY i.fecha_remito DESC";
  $q_rem = $pdo->prepare($sql_rem);
  $q_rem->execute([$id]);
  $remitosList = $q_rem->fetchAll(PDO::FETCH_ASSOC);

  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      .table-secondary {
        background-color: #f8f9fa !important;
        opacity: 0.8;
      }
      .table-secondary td {
        text-decoration: line-through;
        color: #6c757d;
      }
      .table-secondary .badge-danger {
        text-decoration: none;
        font-size: 0.7rem;
        padding: 0.5rem 1rem;
      }
      .text-muted {
        color: #6c757d !important;
      }
      .cancelado-badge {
        display: inline-block;
        min-width: 120px;
      }
      td[style*="display: none"], td.hidden-cell {
        display: none !important;
      }
    </style>
  </head>
  <body>
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php');?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <div class="page-body"><?php
          $ubicacion="Ingresar Stock Orden de Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header compra-summary">
                    <h5>
                      <?=$ubicacion." N° ".$data["id"]."/".$data["nro_revision"]?> - Pedido N° <?=$data["id_pedido"]?>
                      <!-- <a href="imprimirCompra.php?id=<?=$data['id'];?>" target="_blank"><img src="img/print.png" width="20" height="20" border="0" alt="Imprimir O.C." title="Imprimir O.C."></a> -->
                    </h5>
                  </div>
                  <form class="form theme-form" role="form" name="form1" method="post" action="ingresarCompra.php?id=<?=$id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col-md-12">
                          <h6 class="mb-3 font-weight-bold">Ingresar Stock - Orden de Compra</h6>
                          <div class="row">
                            <!-- Columna Izquierda - Datos de solo lectura -->
                            <div class="col-md-6">
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Nro O.C.</label>
                                <div class="col-sm-9"><?=$data['id']."/".$data['nro_revision'];?></div>
                              </div>
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Proveedor</label>
                                <div class="col-sm-9"><?=$data['proveedor_nombre'];?></div>
                              </div>
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Fecha Emisión</label>
                                <div class="col-sm-9"><?=$data['fecha_emision_formatted'];?></div>
                              </div>
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Proyecto</label>
                                <div class="col-sm-9"><?=$proyectoDisplay;?></div>
                              </div>
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Estado</label>
                                <div class="col-sm-9"><?=$data['estado_compra'];?></div>
                              </div>
                              <div class="form-group row">
                                <label class="col-sm-3 font-weight-bold">Pedido N°</label>
                                <div class="col-sm-9">
                                  <a href="verPedido.php?id=<?=$data['id_pedido']?>" target="_blank">
                                    <i class="fa fa-file-text-o" style="margin-right: 5px;"></i><?=$data['id_pedido']?>
                                  </a>
                                </div>
                              </div>
                              <div class="form-group row">
                                <label class="col-sm-3 font-weight-bold">Total</label>
                                <div class="col-sm-9"><?=$data['moneda'] ?: '$'?><?=number_format($data['total'], 2);?></div>
                              </div><?php
                              if (!empty($data['lugar_entrega'])) { ?>
                                <div class="form-group row mt-2">
                                  <label class="col-sm-3 font-weight-bold">Lugar de Entrega</label>
                                  <div class="col-sm-9"><?=$data['lugar_entrega'];?></div>
                                </div><?php
                              } ?>
                            </div>
                            
                            <!-- Columna Derecha - Campos editables -->
                            <div class="col-md-6">
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Fecha Entrega Est.</label>
                                <div class="col-sm-9"><?=$data['fecha_entrega_formatted'];?></div>
                              </div>
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Moneda</label>
                                <div class="col-sm-9"><?=$data['moneda'];?></div>
                              </div>
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Tipo de Cambio</label>
                                <div class="col-sm-9"><?=$data['tipo_cambio_dia'];?></div>
                              </div>
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Forma de Pago</label>
                                <div class="col-sm-9"><?=$data['forma_pago'];?></div>
                              </div>
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Comentarios</label>
                                <div class="col-sm-9"><?=$data['comentarios'];?></div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Tabla de items -->
                      <div class="row">
                        <div class="col-sm-12">
                          <table class="display" id="dataTables-example667">
                            <thead>
                              <tr>
                                <th>Concepto</th>
                                <th>Nro. Colada Interna</th>
                                <th>Precio</th>
                                <th>Precio Kg</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Entregado</th>
                                <th>En Stock</th>
                                <th width="5%">Ingresar</th>
                              </tr>
                            </thead>
                            <tbody><?php
                              $pdo = Database::connect();
                              $sql = " SELECT d.id, m.concepto, d.cantidad, u.unidad_medida, d.id_material, d.precio, d.entregado, d.precio_kg, pd.cancelado 
                                       FROM compras_detalle d 
                                       INNER JOIN materiales m ON m.id = d.id_material 
                                       INNER JOIN unidades_medida u ON u.id = d.id_unidad_medida 
                                       INNER JOIN compras c ON c.id = d.id_compra
                                       INNER JOIN pedidos_detalle pd ON pd.id_pedido = c.id_pedido AND pd.id_material = d.id_material
                                       WHERE d.id_compra = ".$_GET['id'];
                              $count = 1;
                              foreach ($pdo->query($sql) as $row) {
                                $precio=$row["precio"];
                                $cantidad=$row["cantidad"];
                                $entregado=$row["entregado"];

                                $stock = 0;
                                $sql = "SELECT sum(cantidad)-sum(cantidad_egresada) as stock FROM ingresos_detalle WHERE id_material = ? ";
                                $q = $pdo->prepare($sql);
                                $q->execute([$row[0]]);
                                $data = $q->fetch(PDO::FETCH_ASSOC);
                                if (!empty($data['stock'])) {
                                  $stock = $data['stock'];
                                }
                                
                                $sql2 = "SELECT s.nro_sitio,s.nro_subsitio,p.nro FROM compras_detalle cd inner join compras c on c.id = cd.id_compra inner join pedidos pe on pe.id = c.id_pedido inner join proyectos p on p.id = pe.id_proyecto inner join sitios s on s.id = p.id_sitio WHERE cd.id_compra = ? ";
                                $q2 = $pdo->prepare($sql2);
                                $q2->execute([$_GET['id']]);
                                $data3 = $q2->fetch(PDO::FETCH_ASSOC);

                                if ($data3) {
                                  $colada = $data3['nro_sitio']."/".$data3['nro_subsitio']."/".$data3['nro']."-".$count;
                                } else {
                                  $colada = "S/D-" . $count;
                                }?>

                                <tr data-id="<?=$row["id"]?>" <?=($row["cancelado"] == 1) ? 'class="table-secondary"' : ''?>>
                                  <td><?=$row["concepto"]?></td>
                                  <td><?=$colada?></td>
                                  <td>$<?=number_format($precio,2)?></td>
                                  <td>$<?=number_format($row["precio_kg"],2)?></td>
                                  <td><?=$cantidad?></td>
                                  <td>$<?=number_format($precio*$cantidad,2)?></td>
                                  <td><?=$entregado?></td>
                                  <td><?=$stock?></td>
                                  <td>
                                    <input type="hidden" name="id_compra_detalle[]" value="<?=$row["id"]?>">
                                    <input type="hidden" name="id_material[]" value="<?=$row["id_material"]?>"><?php

                                    if ($row["cancelado"] == 1) {?>
                                      <input type="hidden" name="cantidadIngresar[]" value="0">
                                      <span class="badge badge-danger cancelado-badge">Concepto cancelado</span><?php
                                    } elseif ($cantidad > $entregado) {
                                      if (!empty(tienePermiso(305))) {?>
                                        <input name="cantidadIngresar[]" type="number" size="2" value="0" max="<?=$cantidad-$entregado?>" class="form-control">
                                        <i><span style="color:red;">Resta: <?=$cantidad-$entregado?></span></i><?php
                                      } else {?>
                                        <input type="hidden" name="cantidadIngresar[]" value="0">
                                        Sin permisos para ingresar<?php
                                      }
                                    } else {?>
                                      <input type="hidden" name="cantidadIngresar[]" value="0">
                                      Completamente entregado<?php
                                    }?>
                                  </td>
                                </tr><?php
                                $count++;
                              }
								              Database::disconnect();?>
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <div class="form-group row mt-3">
                        <label class="col-sm-2">Fecha Remito (*)</label>
                        <div class="col-sm-4">
                          <input name="fecha_remito" type="date" onfocus="this.showPicker()" value="<?=date('Y-m-d');?>" required class="form-control">
                        </div>
                        <label class="col-sm-2">Nro Remito (*)</label>
                        <div class="col-sm-4">
                          <div class="input-group">
                            <input name="nro_remito" type="text" maxlength="13" class="form-control" value="" required pattern="\d{4}-\d{8}" placeholder="0001-12345678" title="Formato requerido: 4 números, un guión, 8 números (Ej: 0001-12345678)" id="nro_remito_input">
                            <div class="input-group-append">
                              <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#modalRemitos" title="Ver remitos asociados">
                                <i class="fa fa-eye"></i>
                              </button>
                            </div>
                          </div>
                          <small class="form-text text-muted">Formato: 0001-12345678</small>
                        </div>
                      </div>

                      <div class="form-group row mt-3">
                        <label class="col-sm-2">Ruta Documento</label>
                        <div class="col-sm-10">
                          <div class="input-group">
                            <div class="input-group-prepend">
                              <span class="input-group-text"><i class="fa fa-folder-open"></i></span>
                            </div>
                            <input name="ruta_documento" type="text" class="form-control" value="" placeholder="Ej: \\servidor\remitos\0001-12345678.pdf" id="ruta_documento_input">
                            <div class="input-group-append">
                              <button type="button" class="btn btn-outline-secondary" onclick="abrirExploradorRed()" title="Abrir ubicación de red">
                                <i class="fa fa-external-link"></i>
                              </button>
                            </div>
                          </div>
                          <small class="form-text text-muted">
                            Copie la ruta desde el explorador de Windows. 
                            <a href="#" onclick="mostrarAyudaRuta(); return false;">¿Cómo obtener la ruta?</a>
                          </small>
                        </div>
                      </div>

                      <script>
                        document.getElementById('nro_remito_input').addEventListener('input', function (e) {
                          var target = e.target;
                          var input = target.value.replace(/\D/g, '').substring(0, 12);
                          var zip = input.substring(0, 4);
                          var middle = input.substring(4, 12);

                          if (input.length > 4) { target.value = zip + "-" + middle; }
                          else { target.value = input; }
                        });
                      </script>
                    </div>
                    <div class="card-footer">
                      <div class="card-footer">
                        <div class="col-sm-12 text-center">
                          <?php if(tienePermiso(305)){ ?>
                            <div class="form-group mb-3">
                              <label class="font-weight-bold d-block mb-3">Destino de los materiales:</label>
                              <input type="hidden" name="destino_seleccionado" id="destino_seleccionado" value="">
                              
                              <button type="button" class="btn btn-primary btn-lg mr-3 py-4 border" onclick="procesarIngreso(0)">
                                <i class="fa fa-cubes mr-1"></i> Poner en Stock
                              </button>

                              <button type="button" class="btn btn-warning btn-lg mr-3 py-4 border" onclick="procesarIngreso(1)">
                                <i class="fa fa-lock mr-1"></i> Reservar
                              </button>

                              <button type="button" class="btn btn-success btn-lg py-4 border" onclick="procesarIngreso(2)">
                                <i class="fa fa-building mr-1"></i> Ingresar en Obra
                              </button>
                            </div>

                          <?php } ?>
                          &nbsp;
                          <a href="#" onclick="document.location.href='listarCompras.php'" class="btn btn-light">Volver</a>
                        </div>
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
    
    <!-- Modal Listado de Remitos -->
    <div class="modal fade" id="modalRemitos" tabindex="-1" role="dialog" aria-labelledby="modalRemitosLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalRemitosLabel">Remitos registrados para esta OC</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <table class="table table-striped table-bordered table-sm">
                <thead>
                  <tr>
                    <th>Fecha Remito</th>
                    <th>Nro Remito</th>
                    <th>Ver</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  // Usamos la lista que cargamos al principio del PHP
                  if (count($remitosList) > 0) {
                    foreach ($remitosList as $rem) { ?>
                      <tr>
                        <td><?=$rem['fecha_fmt']?></td>
                        <td><?=$rem['nro_remito']?></td>
                        <td class="text-center">
                          <a href="verIngreso.php?id=<?=$rem['id']?>" target="_blank" class="btn btn-xs btn-primary" title="Ver Detalle">
                            <i class="fa fa-external-link"></i>
                          </a>
                        </td>
                      </tr>
                    <?php }
                  } else {
                    echo "<tr><td colspan='3' class='text-center'>No se encontraron remitos previos.</td></tr>";
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
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
        // DataTable
        var table = $('#dataTables-example667').DataTable();
      } );

      function procesarIngreso(idDestino) {
        const form = document.form1;
        
        // Guardar el destino seleccionado en el campo oculto
        document.getElementById('destino_seleccionado').value = idDestino;

        const inputFecha = form.querySelector('input[name="fecha_remito"]');
        const inputNro = form.querySelector('input[name="nro_remito"]');

        if (idDestino == 1) {
            inputFecha.removeAttribute('required');
            inputNro.setAttribute('required', '');
        } else {
            inputFecha.setAttribute('required', '');
            inputNro.setAttribute('required', '');
        }
        
        const cantidadInputs = document.querySelectorAll('input[name="cantidadIngresar[]"]:not([type="hidden"])');
        let hayItemsParaIngresar = false;
        
        cantidadInputs.forEach(function(input) {
          if (parseFloat(input.value) > 0) {
            hayItemsParaIngresar = true;
          }
        });
        
        if (!hayItemsParaIngresar) {
          alert('Debe ingresar al menos una cantidad mayor a 0 para procesar.');
          return false;
        }
        
        if (!form.checkValidity()) {
          form.reportValidity();
          return false;
        }
        
        const originalAction = form.action;
        form.action = 'marcarItemsEntregadoCompra.php?id=<?=$id?>&destino=' + idDestino;
        
        try {
          form.submit();
        } catch (error) {
          form.action = originalAction;
          console.error('Error al enviar formulario:', error);
          alert('Error al procesar el formulario. Por favor, intente nuevamente.');
        }
      }
		
		</script>

    <script>
      function abrirExploradorRed() {
        var rutaBase = '\\\\servidor\\remitos';
        
        window.open('file:///' + rutaBase.replace(/\\/g, '/'), '_blank');
        
        alert('Si no se abrió el explorador automáticamente:\n\n' +
              '1. Abra el Explorador de Windows\n' +
              '2. Navegue a: ' + rutaBase + '\n' +
              '3. Seleccione el archivo\n' +
              '4. Presione Shift + Click derecho sobre el archivo\n' +
              '5. Seleccione "Copiar como ruta de acceso"\n' +
              '6. Pegue la ruta en el campo');
      }
      
      function mostrarAyudaRuta() {
        alert('Para obtener la ruta del archivo:\n\n' +
              '1. Abra el Explorador de Windows\n' +
              '2. Navegue hasta el archivo del remito\n' +
              '3. Mantenga presionada la tecla SHIFT\n' +
              '4. Haga click derecho sobre el archivo\n' +
              '5. Seleccione "Copiar como ruta de acceso"\n' +
              '6. Pegue aquí con Ctrl+V');
      }
    </script>

		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>
