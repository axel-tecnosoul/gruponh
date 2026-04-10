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
    
$moneda="$";
if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT c.id, c.nro_oc, c.id_pedido, c.id_cuenta_proveedor, 
          DATE_FORMAT(c.fecha_emision, '%d/%m/%Y') AS fecha_emision_formatted, 
          DATE_FORMAT(c.fecha_entrega, '%d/%m/%Y') AS fecha_entrega_formatted, 
          c.fecha_emision, c.fecha_entrega, c.id_forma_pago, c.id_estado_compra, 
          c.nro_revision, c.total, c.comentarios, pe.lugar_entrega, c.adjunto_factura,
          c.id_moneda, c.tipo_cambio_dia, c.id_tipo_iva, c.iva, c.descuento, prov.nombre AS proveedor_nombre,
          fp.forma_pago, ec.estado AS estado_compra, m.moneda, COALESCE(pc.id, pd.id) AS proyecto_id, COALESCE(pc.nombre, pd.nombre) AS proyecto_nombre,
          COALESCE(pc.nro, pd.nro) AS proyecto_nro, COALESCE(sc.nro_sitio, sd.nro_sitio) AS nro_sitio, COALESCE(sc.nro_subsitio, sd.nro_subsitio), COALESCE(emc.empresa, emd.empresa) AS empresa, pe.id_computo
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
          LEFT JOIN empresas emc ON emc.id = sc.id_empresa 
          LEFT JOIN proyectos pd ON pd.id = pe.id_proyecto 
          LEFT JOIN sitios sd ON sd.id = pd.id_sitio 
          LEFT JOIN empresas emd ON emd.id = sd.id_empresa 
          WHERE c.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
        
  $proyectoDisplay = '';
  $codigoObra = '';
        
  if ($data) {

    $moneda=$data['moneda'];

    $porcentaje_iva = "0%";
    switch ($data['id_tipo_iva']) {
        case 2:
            $porcentaje_iva = "10.5%";
            break;
        case 3:
            $porcentaje_iva = "21%";
            break;
        default:
            $porcentaje_iva = "0%";
            break;
    }

    $codigoObraPartes = array_filter([
      $data['nro_sitio'] ?? null,
      $data['nro_subsitio'] ?? null,
      $data['proyecto_nro'] ?? null
    ], function ($valor) {
      return $valor !== null && $valor !== '';
    });
    $codigoObra = !empty($codigoObraPartes) ? implode('_', $codigoObraPartes) : '';
    
    if (!empty($data['proyecto_id'])) {
      if (!empty($codigoObra) && !empty($data['proyecto_nombre'])) {
        $proyectoDisplay = $codigoObra . ': ' . $data['proyecto_nombre'];
      } elseif (!empty($codigoObra)) {
        $proyectoDisplay = $codigoObra;
      } elseif (!empty($data['proyecto_nombre'])) {
        $proyectoDisplay = $data['proyecto_nombre'];
      }
    }
    if (!empty($data['empresa'])) {
      $proyectoDisplay .= ' (' . substr($data['empresa'], 0, 4) . ')';
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
      /* Estilos para la tabla de detalle de compra */
      #dataTables-example667 {
        width: 100% !important;
      }
      
      #dataTables-example667 th,
      #dataTables-example667 td {
        padding: 8px 10px !important;
        vertical-align: middle !important;
        font-size: 0.85rem;
      }
      
      /* Alineación de columnas numéricas */
      #dataTables-example667 th:nth-child(4),
      #dataTables-example667 th:nth-child(5),
      #dataTables-example667 th:nth-child(6),
      #dataTables-example667 th:nth-child(7),
      #dataTables-example667 th:nth-child(8),
      #dataTables-example667 th:nth-child(9) {
        text-align: right !important;
      }
      
      #dataTables-example667 td:nth-child(4),
      #dataTables-example667 td:nth-child(5),
      #dataTables-example667 td:nth-child(6),
      #dataTables-example667 td:nth-child(7),
      #dataTables-example667 td:nth-child(8),
      #dataTables-example667 td:nth-child(9) {
        text-align: right !important;
      }
      
      /* Primera columna (Concepto) más ancha */
      #dataTables-example667 th:nth-child(1),
      #dataTables-example667 td:nth-child(1) {
        min-width: 200px;
        text-align: left !important;
      }
      
      /* Columnas de cantidad y fecha */
      #dataTables-example667 th:nth-child(2),
      #dataTables-example667 td:nth-child(2),
      #dataTables-example667 th:nth-child(3),
      #dataTables-example667 td:nth-child(3),
      #dataTables-example667 th:nth-child(10),
      #dataTables-example667 td:nth-child(10) {
        text-align: center !important;
      }
      
      /* Header de la tabla */
      #dataTables-example667 thead th {
        background-color: #f8f9fa;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
      }
      
      /* Resumen financiero */
      .resumen-financiero .font-weight-bold {
        font-size: 0.9rem;
      }
      
      .resumen-financiero .text-success {
        font-size: 1.1rem;
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
          $ubicacion="Ver Orden de Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header compra-summary">
                    <h5>
                      <?=$ubicacion." N° ".$data["nro_oc"]."/".$data["nro_revision"]?> - Pedido N° <?=$data["id_pedido"]?>
                      <a href="imprimirCompra.php?id=<?=$data['id'];?>" target="_blank"><img src="img/print.png" width="20" height="20" border="0" alt="Imprimir O.C." title="Imprimir O.C."></a>
                    </h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#" id="form-unificado">
                    <div class="card-body">
                      <div class="row">
                        <div class="col-md-12">
                          <h6 class="mb-3 font-weight-bold">Datos de la Orden de Compra</h6>
                          <div class="form-group row mt-1">
                            <label class="col-sm-2 font-weight-bold">Nro O.C.</label>
                            <div class="col-sm-4"><?=$data['nro_oc']."/".$data['nro_revision'];?></div>
                            <label class="col-sm-2 font-weight-bold">Proveedor</label>
                            <div class="col-sm-4"><?=$data['proveedor_nombre'];?></div>
                          </div>
                          <div class="form-group row mt-1">
                            <label class="col-sm-2 font-weight-bold">Fecha Emisión</label>
                            <div class="col-sm-4"><?=$data['fecha_emision_formatted'];?></div>
                            <label class="col-sm-2 font-weight-bold">Fecha Entrega</label>
                            <div class="col-sm-4"><?=$data['fecha_entrega_formatted'];?></div>
                          </div>
                          <div class="form-group row mt-1">
                            <label class="col-sm-2 font-weight-bold">Proyecto</label>
                            <div class="col-sm-4"><?=$proyectoDisplay;?></div>
                            <label class="col-sm-2 font-weight-bold">Pedido N°</label>
                            <div class="col-sm-4">
                              <a href="verPedido.php?id=<?=$data['id_pedido']?>" target="_blank">
                                <i class="fa fa-file-text-o" style="margin-right: 5px;"></i><?=$data['id_pedido']?>
                              </a>
                                <?php if (!empty($data['id_computo'])) { ?>
                                <br>
                                <a href="imprimirComputo.php?id=<?=$data['id_computo']?>" target="_blank" class="text-info">
                                  <i class="fa fa-print"></i> Imprimir Cómputo
                                </a>
                              <?php } ?>
                            </div>
                          </div>
                          <div class="form-group row mt-1">
                            <label class="col-sm-2 font-weight-bold">Estado</label>
                            <div class="col-sm-4"><?=$data['estado_compra'];?></div>
                            <label class="col-sm-2 font-weight-bold">Forma de Pago</label>
                            <div class="col-sm-4"><?=$data['forma_pago'];?></div>
                          </div>
                          <div class="form-group row mt-1">
                            <label class="col-sm-2 font-weight-bold">Lugar de Entrega</label>
                            <div class="col-sm-4"><?=$data['lugar_entrega'];?></div>
                            <label class="col-sm-2 font-weight-bold">Moneda</label>
                            <div class="col-sm-4"><?=$moneda;?><?= ($data['tipo_cambio_dia'] && $moneda !== '$') ? ' (TC: '.$data['tipo_cambio_dia'].')' : '' ?></div>
                          </div>
                          <div class="form-group row mt-1">
                            <label class="col-sm-2 font-weight-bold">Tipo de IVA</label>
                            <div class="col-sm-4"><?=$porcentaje_iva;?></div>
                          </div><?php
                          if (!empty($data['comentarios'])) { ?>
                            <div class="form-group row mt-1">
                              <label class="col-sm-2 font-weight-bold">Comentarios</label>
                              <div class="col-sm-10"><?=$data['comentarios'];?></div>
                            </div><?php
                          } ?>
                        </div>
                      </div>
                      <hr class="mt-4 mb-4">
                      <div class="form-group row mt-1">
                        <div class="col-sm-12">
                          <table class="display" id="dataTables-example667">
                            <thead>
                              <tr>
                                <th class="text-left">Concepto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-center">F. Entrega</th>
                                <th class="text-right">Peso Total (Kg)</th>
                                <th class="text-right">P/Unitario</th>
                                <th class="text-right">P/Kilo</th>
                                <th class="text-right">Subtotal</th>
                                <th class="text-right">% Desc.</th>
                                <th class="text-right">Total c/Desc</th>
                                <th class="text-center">Entregado</th>
                              </tr>
                            </thead>
                            <tbody><?php
                              $sumaSubtotal = 0;
                              $sumaDescuento = 0;
                              $pdo = Database::connect();
                              $sql = " SELECT d.id, m.concepto, d.cantidad, u.unidad_medida,d.id_material,d.precio,d.entregado,d.precio_kg,d.subtotal,d.total,d.descuento,d.fecha_entrega,m.peso_metro,m.largo FROM compras_detalle d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_compra = ".$_GET['id'];
                              foreach ($pdo->query($sql) as $row) {
                                $cantidad = (float) $row["cantidad"];
                                $precio_unitario = (float) $row["precio"];
                                $precio_kg = (float) $row["precio_kg"];
                                $porcentajeDescuento = (float) $row["descuento"];
                                $fechaEntrega = $row["fecha_entrega"];
                                $subtotalGuardado = isset($row["subtotal"]) ? (float) $row["subtotal"] : 0;
                                $totalGuardado = isset($row["total"]) ? (float) $row["total"] : 0;
                                
                                $peso_por_unidad = $row["peso_metro"] * ($row["largo"] / 1000);
                                $peso_total_linea = $peso_por_unidad * $cantidad;
                                
                                if ($subtotalGuardado > 0) {
                                  $subtotalSinDescuento = $subtotalGuardado;
                                } else {
                                  if ($precio_kg > 0) {
                                    $subtotalSinDescuento = $peso_total_linea * $precio_kg;
                                  } else {
                                    $subtotalSinDescuento = $precio_unitario * $cantidad;
                                  }
                                }

                                if ($totalGuardado > 0) {
                                  $subtotalConDescuento = $totalGuardado;
                                } else {
                                  $descuento = 0;
                                  if ($subtotalSinDescuento > 0 && $porcentajeDescuento > 0) {
                                    $descuento = ($porcentajeDescuento * $subtotalSinDescuento) / 100;
                                  }
                                  $subtotalConDescuento = $subtotalSinDescuento - $descuento;
                                }
                                
                                $descuento = $subtotalSinDescuento - $subtotalConDescuento;

                                $sumaSubtotal += $subtotalSinDescuento;
                                $sumaDescuento += $descuento;

                                $subtotalConDescuentoMostrar = $moneda.number_format($subtotalConDescuento,2,',','.');
                                $porcentajeDescuentoMostrar = number_format($porcentajeDescuento,1,",",".") . '%';
                                
                                $fechaEntregaFormateada = $fechaEntrega ? date('d/m/Y', strtotime($fechaEntrega)) : '';

                                $precio_unitario_mostrar = number_format($precio_unitario, 2,",",".");
                                $precio_kg_mostrar = number_format($precio_kg, 2,",",".");
                                $peso_total_mostrar = number_format($peso_total_linea, 2,",",".");
                                $subtotalSinDescuentoMostrar = $moneda.number_format($subtotalSinDescuento,2,',','.');?>
                                <tr>
                                  <td class="text-left"><?=$row["concepto"]?></td>
                                  <td class="text-center"><?=$cantidad . ' ' . $row["unidad_medida"]?></td>
                                  <td class="text-center"><?=$fechaEntregaFormateada?></td>
                                  <td class="text-right"><?=$peso_total_mostrar?></td>
                                  <td class="text-right"><?=$precio_unitario_mostrar?></td>
                                  <td class="text-right"><?=$precio_kg_mostrar?></td>
                                  <td class="text-right"><?=$subtotalSinDescuentoMostrar?></td>
                                  <td class="text-right"><?=$porcentajeDescuentoMostrar?></td>
                                  <td class="text-right"><?=$subtotalConDescuentoMostrar?></td>
                                  <td class="text-center"><?=$row["entregado"]?></td>
                                </tr><?php
                              }
                              $totalFinal = $sumaSubtotal + $data['iva'] - $sumaDescuento;
                              Database::disconnect();?>
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <hr class="mt-4 mb-4">
                      <div class="row">
                        <div class="col-md-8">
                          <h6 class="mb-3 font-weight-bold">Resumen Financiero</h6>
                          <div class="form-group row mt-1">
                            <label class="col-sm-3 font-weight-bold">Subtotal</label>
                            <div class="col-sm-3"><?=$moneda.number_format($sumaSubtotal, 2,",",".");?></div>
                            <label class="col-sm-3 font-weight-bold">IVA (<?=$porcentaje_iva?>)</label>
                            <div class="col-sm-3"><?=$moneda.number_format($data['iva'], 2,",",".");?></div>
                          </div>
                          <div class="form-group row mt-1">
                            <label class="col-sm-3 font-weight-bold">Descuento</label>
                            <div class="col-sm-3"><?=$moneda.number_format($sumaDescuento, 2,",",".");?></div>
                            <label class="col-sm-3 font-weight-bold text-success">TOTAL</label>
                            <div class="col-sm-3 text-success font-weight-bold"><?=$moneda.number_format($totalFinal, 2,",",".");?></div>
                          </div>
                        </div>
                      </div>

<!--                       <hr class="mt-4 mb-4">
                      <div class="form-group row mt-1">
                        <label class="col-sm-12">
                          <h6 class="mb-3 font-weight-bold">Conceptos Cómputo</h6>
                        </label>
                      </div>
                      <div class="form-group row mt-1">
                        <div class="col-sm-12">
                          <table class="display" id="dataTables-example668">
                            <thead>
                              <tr>
                                <th>Concepto</th>
                                <th>Cantidad</th>
                              </tr>
                            </thead>
                            <tbody><?php
                              $pdo = Database::connect();
                              $sql = "SELECT m.concepto,cd.cantidad from computos_detalle cd inner join computos co on co.id = cd.id_computo inner join pedidos p on p.id_computo = co.id inner join compras c on c.id_pedido = p.id inner join materiales m on m.id = cd.id_material where c.id = ".$_GET['id'];
                              foreach ($pdo->query($sql) as $row) {?>
                                <tr>
                                  <td><?= $row["concepto"] ?></td>
                                  <td><?= $row["cantidad"] ?></td>
                                </tr><?php
                              }
                              Database::disconnect();?>
                            </tbody>
                          </table>
                        </div>
                      </div> -->
                      
                      <hr class="mt-4 mb-4">
                      <h5 class='mb-3 font-weight-bold'>Sucesos de la Compra</h5>
                      <div class="form-group row mt-1">
                        <div class="col-sm-12">
                          <div class="timeline-small"><?php 
                            $pdo = Database::connect();
                            $sql_sucesos = "SELECT s.id, DATE_FORMAT(s.fecha_hora,'%d/%m/%y %H:%i') AS fecha_formateada, s.suceso, s.titulo, ts.tipo, u.usuario AS nombre_usuario FROM sucesos s INNER JOIN tipos_suceso ts ON ts.id = s.id_tipo_suceso LEFT JOIN usuarios u ON u.id = s.id_usuario WHERE s.entidad_tipo = 'compras' AND s.entidad_id = ? ORDER BY s.fecha_hora DESC, s.id DESC";
                            
                            $q_sucesos = $pdo->prepare($sql_sucesos);
                            $q_sucesos->execute([$id]);

                            if ($q_sucesos->rowCount() > 0) {
                              foreach ($q_sucesos as $row_suceso) {
                                $usuario_suceso = !empty($row_suceso['nombre_usuario']) ? ' por ' . htmlspecialchars($row_suceso['nombre_usuario']) : '';?>
                                <div class="media">
                                  <div class="timeline-round m-r-30 timeline-line-1 bg-primary">
                                    <i data-feather="message-circle"></i>
                                  </div>
                                  <div class="media-body">
                                    <h6>
                                      <?=htmlspecialchars($row_suceso['titulo'])?> <span class="pull-right f-14"><?=$row_suceso['fecha_formateada']?>hs</span>
                                    </h6>
                                    <p>
                                      <strong><?=htmlspecialchars($row_suceso['tipo'])?>:</strong> 
                                      <?=htmlspecialchars($row_suceso['suceso'])?> 
                                      <small class="text-muted"><?=$usuario_suceso?></small>
                                    </p>
                                  </div>
                                </div><?php
                              }
                            } else {
                              echo '<p>No hay sucesos registrados para esta compra.</p>';
                            }
                            Database::disconnect();?>
                          </div>
                        </div>
                      </div>

                      <?php if (!empty($data['adjunto_factura'])) { ?>
                        <hr class="mt-4 mb-4">
                        <div class="row">
                          <div class="col-sm-12">
                            <h6 class="mb-3 font-weight-bold">Comprobante</h6>
                            <div class="form-group row mt-1">
                              <div class="col-sm-12">
                                <a target="_blank" href="<?php echo $data['adjunto_factura'];?>" class="btn btn-outline-primary">
                                  <i class="fa fa-download"></i> Descargar Comprobante
                                </a>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php } ?>
                      <hr class="mt-4 mb-4">
<!--                       <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3 font-weight-bold">Remitos Ingresados</h6>
                          <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm">
                              <thead>
                                <tr>
                                  <th>Nro Remito</th>
                                  <th>Fecha</th>
                                  <th>Usuario</th>
                                  <th>Ver</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php /* 
                                $pdo = Database::connect();
                                
                                $sql_remitos = "SELECT DISTINCT i.id, i.nro_remito, date_format(i.fecha_hora,'%d/%m/%Y') as fecha_formatted, u.usuario 
                                                FROM ingresos i 
                                                INNER JOIN ingresos_detalle id ON id.id_ingreso = i.id 
                                                INNER JOIN compras_detalle cd ON cd.id = id.id_detalle_compra
                                                LEFT JOIN usuarios u ON u.id = i.id_usuario 
                                                WHERE cd.id_compra = ? 
                                                ORDER BY i.fecha_hora DESC";
                                
                                $q_remitos = $pdo->prepare($sql_remitos);
                                $q_remitos->execute([$id]);
                                $remitos_encontrados = false;
                                
                                while ($row_rem = $q_remitos->fetch(PDO::FETCH_ASSOC)) { 
                                  $remitos_encontrados = true;  */?>
                                  <tr>
                                    <td><?= $row_rem['nro_remito']; ?></td>
                                    <td><?= $row_rem['fecha_formatted']; ?></td>
                                    <td><?= $row_rem['usuario']; ?></td>
                                    <td>
                                      <a href="verIngreso.php?id=<?=$row_rem['id']?>" target="_blank" class="btn btn-xs btn-primary">
                                        <i class="fa fa-eye"></i>
                                      </a>
                                    </td>
                                  </tr>
                                <?php /*  } 
                                
                                if (!$remitos_encontrados) {
                                  echo "<tr><td colspan='4' class='text-center'>No hay remitos registrados para esta compra.</td></tr>";
                                }
                                Database::disconnect(); */
                                ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div> -->
                      <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3 font-weight-bold">Pagos / Comentarios Realizados</h6>
                          <div class="timeline-small"><?php 
                            $pdo = Database::connect();
                            $sql = " SELECT cp.id, date_format(cp.fecha,'%d/%m/%y'), cp.monto, cp.comentarios, u.usuario FROM compras_pagos cp left join usuarios u on u.id = cp.id_usuario WHERE cp.id_compra = ".$_GET['id'];
                            
                            $hasPagos = false;
                            foreach ($pdo->query($sql) as $row) {
                              $hasPagos = true;?>
                              <div class="media">
                                <div class="timeline-round m-r-30 timeline-line-1 bg-primary">
                                  <i data-feather="message-circle"></i>
                                </div>
                                <div class="media-body">
                                  <h6>
                                    Monto: $<?=number_format($row["monto"],2,',','.')?> <span class="pull-right f-14"><?=$row[1]?></span>
                                  </h6>
                                  <p>Usuario: <?=$row["usuario"]?></p>
                                  <p>Observaciones: <?=$row["comentarios"]?></p>
                                </div>
                              </div><?php
                            }
                            if (!$hasPagos) {
                              echo '<p>No hay pagos registrados para esta compra.</p>';
                            }
                            Database::disconnect();?>
                          </div>
                        </div>
                      </div>
                    </div>
                      <div class="card-footer">
                        <div class="col-sm-12 text-center">
                          <?php
                          if ($data['id_estado_compra'] == 1 && function_exists('tienePermiso') && tienePermiso(298)){?>
                            <button type="button" class="btn btn-primary mt-2 mt-sm-0" data-toggle="modal" data-target="#approvalModal">Enviar a aprobación</button>
                          <?php } ?>

                          <?php 
                          if ($data['id_estado_compra'] == 2 && function_exists('tienePermiso') && tienePermiso(384)){ ?>
                            <a href="aprobarCompra.php?id=<?=$data['id']?>" class="btn btn-success mt-2 mt-sm-0" onclick="return confirm('¿Está seguro que desea APROBAR esta compra?');">Aprobar Compra</a>
                          <?php } ?>

                          <a href="listarCompras.php" class="btn btn-light">Volver</a>
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

    <div class="modal fade" id="approvalModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirmar envío a aprobación</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>¿Desea enviar esta compra a aprobación?</p>
            <div id="estado-error" class="alert alert-danger d-none" role="alert"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="confirmApproval">Confirmar</button>
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="resultModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="resultModalTitle">Resultado</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div id="resultModalIcon" class="text-center mb-3">
            </div>
            <p id="resultModalMessage" class="text-center"></p>
          </div>
          <div class="modal-footer">
            <a class="btn btn-primary" href="listarCompras.php">Ir a Lista de Compras</a>
            <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
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
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
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
      // Setup - add a text input to each footer cell
      $('#dataTables-example667 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      });

	    $('#dataTables-example667').DataTable({
        stateSave: false,
        responsive: false,
		    "dom": 'rtip',
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

    });
	
    $(document).ready(function() {
      // Setup - add a text input to each footer cell
      $('#dataTables-example668 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      });
	    
      $('#dataTables-example668').DataTable({
        stateSave: false,
        responsive: false,
		    "dom": 'rtip',
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
      var table = $('#dataTables-example668').DataTable();
      // Apply the search
      table.columns().every( function () {
        var that = this;
        $( 'input', this.footer() ).on( 'keyup change', function () {
          if ( that.search() !== this.value ) {
            that.search( this.value ).draw();
          }
        });
      });

    });
	
    $(document).ready(function() {
      // Setup - add a text input to each footer cell
      $('#dataTables-example669 tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
      });

	    $('#dataTables-example669').DataTable({
        stateSave: false,
        responsive: false,
		    "dom": 'rtip',
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
      var table = $('#dataTables-example669').DataTable();
      // Apply the search
      table.columns().every( function () {
        var that = this;
        $('input', this.footer() ).on( 'keyup change', function () {
          if ( that.search() !== this.value ) {
            that.search( this.value ).draw();
          }
        });
      });

      $('#confirmApproval').on('click', function() {
        var compraId = <?= $id ?>;
        
        $.ajax({
          url: 'modificarEstadoCompra.php',
          type: 'POST',
          data: {
            id_compra: compraId,
            nuevo_estado: 2
          },
          dataType: 'json',
          success: function(response) {
            $('#approvalModal').modal('hide');
            
            if(response.success) {
              $('button[data-target="#approvalModal"]').hide();
              
              showResultModal(
                '¡Éxito!', 
                'La compra ha sido enviada para aprobación correctamente.',
                'success'
              );
            } else {
              showResultModal(
                'Error', 
                'Error al enviar la compra para aprobación: ' + (response.message || 'Error desconocido'),
                'error'
              );
            }
          },
          error: function(xhr, status, error) {
            $('#approvalModal').modal('hide');
            console.error('Error AJAX:', error);
            showResultModal(
              'Error de Conexión', 
              'No se pudo conectar con el servidor. Intente nuevamente.',
              'error'
            );
          }
        });
      });



    function showResultModal(title, message, type) {
      var iconHtml = '';
      var borderColor = '';

      if (type === 'success') {
        iconHtml = '<i class="fa fa-check-circle fa-3x" style="color: #28a745;"></i>';
        borderColor = '#28a745';
      } else if (type === 'error') {
        iconHtml = '<i class="fa fa-times-circle fa-3x" style="color: #dc3545;"></i>';
        borderColor = '#dc3545';
      }

      $('#resultModalTitle').text(title);
      $('#resultModalMessage').text(message);
      $('#resultModalIcon').html(iconHtml);
      $('#resultModal .modal-header').css('border-left', '4px solid ' + borderColor);

      $('#resultModal').modal('show');
    }

    $('#approvalModal').on('hidden.bs.modal', function () {
      $('#approvalLoading').hide();
      $('#confirmApproval').prop('disabled', false);
      $('#btnCancelApproval').prop('disabled', false);
      $('#approvalModal .close').show();
      $('#estado-error').addClass('d-none').text('');
    });

    $('#resultModal').on('hidden.bs.modal', function () {
      $('#resultModal .modal-header').css('border-left', 'none');
    });

    });
		
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>