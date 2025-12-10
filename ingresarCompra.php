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

// Validar que la compra esté en un estado que permita ingreso
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        
  // Construir información del proyecto (igual que verCompra.php)
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
  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      /* Estilos para items cancelados */
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
      
      /* Estilos para las celdas combinadas */
      .cancelado-badge {
        display: inline-block;
        min-width: 120px;
      }
      
      /* Asegurar que las celdas ocultas no interfieran */
      td[style*="display: none"], td.hidden-cell {
        display: none !important;
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
                                <div class="col-sm-9">$<?=number_format($data['total'], 2);?> <?=$data['moneda'] ?: '';?></div>
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
                                <div class="col-sm-9">
                                  <input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?=$data['fecha_entrega'];?>" class="form-control">
                                </div>
                              </div>
                              
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Moneda</label>
                                <div class="col-sm-9">
                                  <select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12">
                                    <option value="">Seleccione...</option>
                                    <?php
                                    $pdo = Database::connect();
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $sqlZon = "SELECT id, moneda FROM monedas WHERE 1";
                                    $q = $pdo->prepare($sqlZon);
                                    $q->execute();
                                    while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                      echo "<option value='".$fila['id']."'";
                                      if ($fila['id']==$data['id_moneda']) {
                                        echo " selected ";
                                      }
                                      echo ">".$fila['moneda']."</option>";
                                    }
                                    Database::disconnect();
                                    ?>
                                  </select>
                                </div>
                              </div>
                              
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Tipo de Cambio</label>
                                <div class="col-sm-9">
                                  <input name="tipo_cambio_dia" type="number" step="0.01" class="form-control" value="<?=$data['tipo_cambio_dia'];?>">
                                </div>
                              </div>
                              
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Forma de Pago</label>
                                <div class="col-sm-9">
                                  <select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single col-sm-12">
                                    <option value="">Seleccione...</option><?php
                                    $pdo = Database::connect();
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $sqlZon = "SELECT id, forma_pago FROM formas_pago WHERE 1";
                                    $q = $pdo->prepare($sqlZon);
                                    $q->execute();
                                    while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                      $selected = "";
                                      if ($fila['id']==$data['id_forma_pago']) {
                                        $selected = " selected ";
                                      }?>
                                      <option value='<?=$fila['id']?>' <?=$selected?>><?=$fila['forma_pago']?></option><?php
                                    }
                                    Database::disconnect();?>
                                  </select>
                                </div>
                              </div>
                              
                              <div class="form-group row mt-1">
                                <label class="col-sm-3 font-weight-bold">Comentarios</label>
                                <div class="col-sm-9">
                                  <textarea name="comentarios" class="form-control" rows="3"><?=$data['comentarios'];?></textarea>
                                </div>
                              </div>
                            </div>
                          </div>
                          
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-sm-12">
                          <table class="display" id="dataTables-example667">
                            <thead>
                              <tr>
                                  <!--<th><input type="checkbox" data-orderable="false" class="no-sort toggle-checkboxes" /></th>-->
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
                                  <td><?php
                                    // Siempre agregar hidden con el ID para mantener consistencia?>
                                    <input type="hidden" name="id_compra_detalle[]" value="<?=$row["id"]?>">
                                    <input type="hidden" name="id_material[]" value="<?=$row["id_material"]?>"><?php

                                    // Verificar si el item está cancelado
                                    if ($row["cancelado"] == 1) {?>
                                      <input type="hidden" name="cantidadIngresar[]" value="0">
                                      <span class="badge badge-danger cancelado-badge">Concepto cancelado</span><?php
                                    } elseif ($cantidad > $entregado) {
                                      // Mostrar input solo si resta cantidad por ingresar y no está cancelado
                                      if (!empty(tienePermiso(305))) {?>
                                        <input name="cantidadIngresar[]" type="number" size="2" value="0" max="<?=$cantidad-$entregado?>" class="form-control">
                                        <i><span style="color:red;">Resta: <?=$cantidad-$entregado?></span></i><?php
                                      } else {
                                        // Si no tiene permiso, agregar hidden con 0?>
                                        <input type="hidden" name="cantidadIngresar[]" value="0">
                                        Sin permisos para ingresar<?php
                                      }
                                    } else {
                                      // Si no resta nada, agregar hidden con 0?>
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
                          <input name="nro_remito" type="text" maxlength="99" class="form-control" value="" required>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3"><?php
                        if(tienePermiso(305)){?>
                          <button type="button" class="btn btn-warning" id="reservado-masivo" onclick="procesarIngreso(1)">Marcar Reservados</button>&nbsp;
                          <button type="button" class="btn btn-danger" id="disponible-masivo" onclick="procesarIngreso(0)">Marcar Disponibles</button><?php
                        }?>
                        <!--<button class="btn btn-primary" type="submit">Modificar</button>-->
                        <a href="#" onclick="document.location.href='listarCompras.php'" class="btn btn-light">Volver</a>
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

      // Función para procesar el ingreso con validación
      function procesarIngreso(tipoReservado) {
        const form = document.form1;
        
        // Verificar si hay al menos una cantidad mayor a 0
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
        
        // Validar formulario usando HTML5 validation
        if (!form.checkValidity()) {
          // Si el formulario no es válido, mostrar los mensajes de validación nativos
          form.reportValidity();
          return false;
        }
        
        // Si todo está válido, cambiar action y enviar
        const originalAction = form.action;
        form.action = 'marcarItemsEntregadoCompra.php?id=<?=$id?>&reservado=' + tipoReservado;
        
        try {
          form.submit();
        } catch (error) {
          // Si hay error, restaurar action original
          form.action = originalAction;
          console.error('Error al enviar formulario:', error);
          alert('Error al procesar el formulario. Por favor, intente nuevamente.');
        }
      }
		
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>