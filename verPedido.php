<?php
  require("config.php");
  require_once("PHPMailer/class.phpmailer.php");
  require_once("PHPMailer/class.smtp.php");

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
    header("Location: listarPedidos.php");
  }
  
  $proyectoDisplay = '';
  $codigoObra = '';
  $data = [];

  if (!empty($_POST)) {
    // insert data
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "INSERT INTO `compras`(`id_pedido`, `id_cuenta_proveedor`, `fecha_emision`, `fecha_entrega`, `id_forma_pago`, `id_estado_compra`, `nro_oc`, `total`, `comentarios`, `id_moneda`, `tipo_cambio_dia`,comentarios_revision, `descuento`) VALUES (?,?,?,?,?,1,?,0,?,?,?,'Revisión Original',?)";
    $q = $pdo->prepare($sql);
    $q->execute([$id,$_POST['id_cuenta_proveedor'],$_POST['fecha_emision'],$_POST['fecha_entrega'],$_POST['id_forma_pago'],'',$_POST['comentarios'],$_POST['id_moneda'],$_POST['tipo_cambio_dia'],$_POST['descuento']]);
    
    $idCompra = $pdo->lastInsertId();
    
    $nroOC = $id .'/'. $idCompra;
    $sql = "update `compras` set `nro_oc` = ? where id = ?";
    $q = $pdo->prepare($sql);           
    $q->execute([$nroOC,$idCompra]);
    
    $sql = " SELECT d.`id`, d.`id_material`, m.`concepto`, d.`cantidad`, d.`id_unidad_medida`,m.peso_metro FROM `pedidos_detalle` d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_pedido = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    
    $total = 0;
    $hasValidItem = false;
    
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
      $cantidadPedir = $_POST['cantidad_'.$row['id']] ?? 0;
      $precioUnitario = $_POST['precio_'.$row['id']] ?? 0;
      $precioKg = $_POST['preciokg_'.$row['id']] ?? 0;
      
      if ($cantidadPedir > 0 && ($precioUnitario > 0 || $precioKg > 0)) {
        $hasValidItem = true;
        
        if ($precioKg != 0) {
            $precioUnitario = $precioKg * $row['peso_metro'];
        }
        
        $sql2 = "INSERT INTO `compras_detalle`(`id_compra`, `id_material`, `cantidad`, `id_unidad_medida`, `precio`, `precio_kg`) VALUES (?,?,?,?,?,?)";
        $q2 = $pdo->prepare($sql2);           
        $q2->execute([$idCompra,$row['id_material'],$cantidadPedir,$row['id_unidad_medida'],$precioUnitario,$precioKg]);
        $subtotal = $cantidadPedir*$precioUnitario;
        $total += $subtotal;
        
        $sql3 = "UPDATE `pedidos_detalle` SET `comprado`= ? WHERE `id_pedido`=? AND `id_material`=?";
        $q3 = $pdo->prepare($sql3);
        $q3->execute([$cantidadPedir,$id,$row['id_material']]);
        
        $sql4 = "SELECT cd.id id from computos_detalle cd inner join computos c on c.id = cd.id_computo inner join pedidos p on p.id_computo = c.id where p.id = ? and cd.cancelado = 0 and cd.id_material = ? ";
        $q4 = $pdo->prepare($sql4);
        $q4->execute([$id,$row['id_material']]);
        $data4 = $q4->fetch(PDO::FETCH_ASSOC);
        
        if ($data4) {
          $sql5 = "UPDATE `computos_detalle` set `comprado` = ? WHERE id = ?";
          $q5 = $pdo->prepare($sql5);
          $q5->execute([$cantidadPedir,$data4['id']]);
        }
      }
    }
    
    if ($hasValidItem) {
      $iva = $total*0.21;
      
      $sql = "update `compras` set total = ?, iva = ? where id = ?";
      $q = $pdo->prepare($sql);           
      $q->execute([$total,$iva,$idCompra]);
      
      $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva orden de compra','Compras','verCompra.php?id=$idCompra')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));

      // Enviar notificaciones por email
      $sql = "SELECT valor FROM `parametros` WHERE id = 1 ";
      $q = $pdo->prepare($sql);
      $q->execute();
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $smtpHost = $data['valor'];  
      
      $sql = "SELECT valor FROM `parametros` WHERE id = 2 ";
      $q = $pdo->prepare($sql);
      $q->execute();
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $smtpUsuario = $data['valor'];  
      
      $sql = "SELECT valor FROM `parametros` WHERE id = 3 ";
      $q = $pdo->prepare($sql);
      $q->execute();
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $smtpClave = $data['valor'];  
      
      $sql = "SELECT valor FROM `parametros` WHERE id = 4 ";
      $q = $pdo->prepare($sql);
      $q->execute();
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $smtpFrom = $data['valor'];  
      
      $sql = "SELECT valor FROM `parametros` WHERE id = 5 ";
      $q = $pdo->prepare($sql);
      $q->execute();
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $smtpFromName = $data['valor'];  
      
      $sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 4 ";
      foreach ($pdo->query($sql) as $row) {
        
        $sql2 = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (4,?,now(),0,?,?)";
        $q2 = $pdo->prepare($sql2);
        $q2->execute([$row[0],'ID Orden de Compra: #'.$idCompra,$idCompra]);
        
        $address = $row[1];
        
        $titulo = "ERP Notificaciones - Módulo Compras - Nueva Compra";
        $mensaje="Nueva compra dada de alta en el sistema: #".$idCompra;
        
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->SMTPAuth = true;
        $mail->Port = 25; 
        $mail->SMTPSecure = 'ssl';
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = false;
        $mail->IsHTML(true); 
        $mail->CharSet = "utf-8";
        $mail->From = $smtpFrom;
        $mail->FromName = $_SESSION['user']['usuario'];
        $mail->Host = $smtpHost; 
        $mail->Username = $smtpUsuario; 
        $mail->Password = $smtpClave;
        $mail->AddAddress($address);
        $mail->Subject = $titulo; 
        $mensajeHtml = nl2br($mensaje);
        $mail->Body = "{$mensajeHtml} <br /><br />"; 
        $mail->AltBody = "{$mensaje} \n\n"; 
        $mail->Send();
      }
      
      Database::disconnect();
      header("Location: listarCompras.php");
    } else {
      Database::disconnect();
      $error = "Debe ingresar al menos un concepto con cantidad mayor a 0 y precio.";
    }
  }

  if (empty($data)) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "SELECT pe.id, pe.id_computo, pe.id_proyecto, DATE_FORMAT(pe.fecha, '%d/%m/%Y') AS fecha, pe.lugar_entrega, pe.id_cuenta_recibe, pe.aprobado, c.id_tarea, c.id_cuenta_solicitante, c.nro_revision AS computo_revision, c.nro AS computo_numero, COALESCE(pc.id, pd.id) AS proyecto_id, COALESCE(pc.nombre, pd.nombre) AS proyecto_nombre, COALESCE(pc.nro, pd.nro) AS proyecto_nro, COALESCE(sc.nro_sitio, sd.nro_sitio) AS nro_sitio, COALESCE(sc.nro_subsitio, sd.nro_subsitio) AS nro_subsitio, cu.nombre AS cuenta_solicitante, cu2.nombre AS cuenta_recibe, pe.id_estado, ep.estado AS estado_pedido FROM pedidos pe LEFT JOIN computos c ON c.id = pe.id_computo LEFT JOIN tareas t ON t.id = c.id_tarea LEFT JOIN proyectos pc ON pc.id = t.id_proyecto LEFT JOIN sitios sc ON sc.id = pc.id_sitio LEFT JOIN proyectos pd ON pd.id = pe.id_proyecto LEFT JOIN sitios sd ON sd.id = pd.id_sitio LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante LEFT JOIN cuentas cu2 ON cu2.id = pe.id_cuenta_recibe LEFT JOIN estados_pedidos ep ON ep.id = pe.id_estado WHERE pe.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if ($data) {
      $codigoObraPartes = array_filter([
        $data['nro_sitio'] ?? null,
        $data['nro_subsitio'] ?? null,
        $data['proyecto_nro'] ?? null
      ], function ($valor) {
        return $valor !== null && $valor !== '';
      });
      $codigoObra = !empty($codigoObraPartes) ? implode('-', $codigoObraPartes) : '';

      $tieneComputo = !empty($data['id_computo']);
      $tipoPedido = "Pedido Directo";
      if($tieneComputo){
        $tipoPedido = 'Pedido de Cómputo';
      }

      $proyectoDisplay = '';
      if (!empty($data['proyecto_id'])) {
        if (!empty($codigoObra) && !empty($data['proyecto_nombre'])) {
          $proyectoDisplay = $codigoObra . ': ' . $data['proyecto_nombre'];
        } elseif (!empty($codigoObra)) {
          $proyectoDisplay = $codigoObra;
        } elseif (!empty($data['proyecto_nombre'])) {
          $proyectoDisplay = $data['proyecto_nombre'];
        }
      }
    } else {
      $data = [];
    }

    Database::disconnect();
  }
    
?>
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
          $ubicacion="Ver Pedido ".$tipoPedido;
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header pedido-summary">
                    <!-- <div class="pedido-summary-text"><?=!empty($headerText) ? $headerText : 'INFORMACIÓN DEL PEDIDO';?></div> -->
                    <h5>
                      <?=$ubicacion." N° ".$data["id"]?>
                      <a href="imprimirPedido.php?id=<?=$data['id'];?>" target="_blank"><img src="img/print.png" width="20" height="20" border="0" alt="Imprimir Pedido" title="Imprimir Pedido"></a>
                    </h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#" id="form-unificado">
                    <div class="card-body"><?php
                      if (isset($error)){?>
                        <div class="alert alert-danger"><?=$error;?></div><?php
                      }?>
                      <div class="row">
                        <div class="col-md-12">
                          <h6 class="mb-3 font-weight-bold">Datos del Pedido</h6>
                          <div class="form-group row">
                            <label class="col-sm-2 col-form-label font-weight-bold">Fecha Pedido</label>
                            <div class="col-sm-4"><?=$data['fecha'];?></div>
                            <label class="col-sm-2 col-form-label font-weight-bold">Proyecto</label>
                            <div class="col-sm-4"><?=$proyectoDisplay;?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-2 col-form-label font-weight-bold">Lugar de Entrega</label>
                            <div class="col-sm-4"><?=$data['lugar_entrega'];?></div>
                            <label class="col-sm-2 col-form-label font-weight-bold">Recibe</label>
                            <div class="col-sm-4"><?=$data['cuenta_recibe']?></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-2 col-form-label font-weight-bold">Estado</label>
                            <div class="col-sm-4"><?=$data['estado_pedido'];?></div>
                            <label class="col-sm-2 col-form-label font-weight-bold">Solicitante</label>
                            <div class="col-sm-4"><?=$data['cuenta_solicitante']?></div>
                          </div>
                        </div>
                      </div>
                      
                      <hr class="mt-4 mb-4">
                      
                      <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3 font-weight-bold">Detalle de Conceptos</h6>
                          <div class="table-responsive">
                          <table class="display" id="dataTables-example667" style="width:100%">
                            <thead>
                              <tr>
                                <th>Concepto</th>
                                <th>Fec. Necesidad</th>
                                <th>Fec. Últ. Compra</th>
                                <th>Último Precio</th>
                                <th>Requerido</th>
                                <th>Stock</th>
                                <th>Reserv.</th>
                                <th>Comprado</th>
                              </tr>
                            </thead>
                            <tbody><?php
                              $pdo = Database::connect();
                              $sql = " SELECT pd.id, m.concepto, pd.cantidad, date_format(pd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad, u.unidad_medida,pd.id_material,pd.reservado,pd.comprado FROM pedidos_detalle pd inner join materiales m on m.id = pd.id_material inner join unidades_medida u on u.id = pd.id_unidad_medida WHERE pd.id_pedido = ".$_GET['id'];
                              
                              foreach ($pdo->query($sql) as $row) {
                                $cantidadDisponible = $row["cantidad"] - $row["reservado"] - $row["comprado"];

                                $sql2 = "SELECT d.precio, date_format(c.fecha_emision,'%d/%m/%y') AS fecha_emision FROM compras_detalle d inner join compras c on c.id = d.id_compra WHERE d.id_material = ".$row[5]." order by c.id desc limit 0,1 ";
                                $q2 = $pdo->prepare($sql2);
                                $q2->execute();
                                $data2 = $q2->fetch(PDO::FETCH_ASSOC);

                                $fecha_emision="";
                                if (!empty($data2['fecha_emision'])) {
                                  $fecha_emision = $data2['fecha_emision'];
                                }
                                
                                $precio = "";
                                if (!empty($data2['precio'])) {
                                  $precio = "$".number_format($data2['precio'],2);
                                }
                                
                                $sql = "SELECT SUM(id.saldo) AS disponible FROM ingresos_detalle id WHERE id_material = ? ";
                                $q = $pdo->prepare($sql);
                                $q->execute([$row[5]]);
                                $data3 = $q->fetch(PDO::FETCH_ASSOC);
                                
                                $disponible=0;
                                if (empty($data3['disponible'])) {
                                }?>

                                <tr>
                                  <td><?=$row["concepto"]?></td>
                                  <td><?=$row["fecha_necesidad"]?></td>
                                  <td><?=$fecha_emision?></td>
                                  <td><?=$precio?></td>
                                  <td><?=$row["cantidad"] .' '.$row["unidad_medida"]?></td>
                                  <td><?=$disponible?></td>
                                  <td><?=$row["reservado"]?></td>
                                  <td><?=$row["comprado"]?></td>
                                </tr><?php
                              }
                              Database::disconnect();?>
                            </tbody>
                          </table>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-12 text-center"><?php
                        if ($data['id_estado'] == 1 && tienePermiso(298)){?>
                          <button type="button" class="btn btn-primary mt-2 mt-sm-0" id="btnEnviarAprobacion">Enviar a aprobación</button><?php
                        }?>
                        <a href='listarPedidos.php' class="btn btn-light">Volver</a>
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

    <!-- Modal Enviar a aprobación -->
    <div class="modal fade" id="modalEnviarAprobacion" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirmar envío a aprobación</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>¿Desea enviar este pedido a aprobación?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="confirmEnviarAprobacion">Confirmar</button>
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
          scrollX: false,
          scrollCollapse: false,
          autoWidth: false,
          paging: true,
          pageLength: 10,
          columnDefs: [
            { width: "180px", targets: 0, orderable: true },
            { width: "85px", targets: 1, orderable: true },
            { width: "85px", targets: 2, orderable: true },
            { width: "90px", targets: 3, orderable: true },
            { width: "90px", targets: 4, orderable: true },
            { width: "60px", targets: 5, orderable: true, className: "text-center" },
            { width: "65px", targets: 6, orderable: true, className: "text-center" },
            { width: "80px", targets: 7, orderable: true, className: "text-center" },
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
          }
        });

        var pedidoId = <?=intval($data['id']);?>;

        $('#btnEnviarAprobacion').on('click', function () {
          $('#estado-error').addClass('d-none');
          $('#modalEnviarAprobacion').modal('show');
        });

        $('#modalEnviarAprobacion').on('hidden.bs.modal', function () {
          $('#confirmEnviarAprobacion').prop('disabled', false);
        });

        $('#confirmEnviarAprobacion').on('click', function () {
          var $button = $(this);
          $button.prop('disabled', true);
          $.ajax({
            type: 'POST',
            url: 'modificarEstadoPedido.php',
            data: { idEstado: 2, idPosicion: pedidoId },
            success: function (response) {
              var trimmed = $.trim(response || '');
              var pattern = new RegExp('^2\\s*-\\s*' + pedidoId + '$');
              if (pattern.test(trimmed)) {
                window.location.href = 'listarPedidos.php';
              } else {
                mostrarErrorEstado('No se pudo actualizar el estado. Respuesta inesperada del servidor.');
              }
            },
            error: function () {
              mostrarErrorEstado('No se pudo actualizar el estado. Intente nuevamente.');
            },
            complete: function () {
              $button.prop('disabled', false);
            }
          });
        });

        function mostrarErrorEstado(mensaje) {
          $('#modalEnviarAprobacion').modal('hide');
          var $error = $('#estado-error');
          $error.text(mensaje).removeClass('d-none');
        }
      });

    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
  </body>
</html>