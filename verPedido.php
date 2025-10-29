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
  } else {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "SELECT pe.`id`, pe.`id_computo`, c.id_tarea, c.id_cuenta_solicitante, pe.`fecha`, pe.`lugar_entrega`, pe.`id_cuenta_recibe`,pe.aprobado FROM `pedidos` pe inner join computos c on c.id = pe.`id_computo` WHERE pe.id = ? ";
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    
    Database::disconnect();
  }
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	<link rel="stylesheet" type="text/css" href="assets/css/select2.css">
	<link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  <style>
    .form-control:disabled, 
    .form-control[readonly] {
      background-color: #e9ecef;
      opacity: 1;
    }
    
    .form-group {
      margin-bottom: 1rem;
    }
    
    .card-body {
      padding: 1.5rem;
    }
    
    /* Forzar alineación entre thead y tbody */
    #dataTables-example667 {
      width: 100% !important;
      font-size: 0.75rem;
      table-layout: fixed !important;
      border-collapse: collapse !important;
    }
    
    #dataTables-example667 th,
    #dataTables-example667 td {
      padding: 5px 4px !important;
      vertical-align: middle;
      font-size: 0.75rem;
      overflow: hidden;
      text-overflow: ellipsis;
      box-sizing: border-box !important;
    }
    
    /* Headers sin wrap */
    #dataTables-example667 thead th {
      white-space: nowrap !important;
      padding: 6px 4px !important;
      font-size: 0.7rem;
      font-weight: 600;
      line-height: 1.2;
      background-color: #f8f9fa;
    }
    
    /* Anchos EXACTOS para cada columna */
    #dataTables-example667 th:nth-child(1),
    #dataTables-example667 td:nth-child(1) {
      width: 180px !important;
      min-width: 180px !important;
      max-width: 180px !important;
      white-space: normal;
      word-wrap: break-word;
    }
    
    #dataTables-example667 th:nth-child(2),
    #dataTables-example667 td:nth-child(2) {
      width: 85px !important;
      min-width: 85px !important;
      max-width: 85px !important;
    }
    
    #dataTables-example667 th:nth-child(3),
    #dataTables-example667 td:nth-child(3) {
      width: 85px !important;
      min-width: 85px !important;
      max-width: 85px !important;
    }
    
    #dataTables-example667 th:nth-child(4),
    #dataTables-example667 td:nth-child(4) {
      width: 90px !important;
      min-width: 90px !important;
      max-width: 90px !important;
    }
    
    #dataTables-example667 th:nth-child(5),
    #dataTables-example667 td:nth-child(5) {
      width: 90px !important;
      min-width: 90px !important;
      max-width: 90px !important;
    }
    
    #dataTables-example667 th:nth-child(6),
    #dataTables-example667 td:nth-child(6) {
      width: 60px !important;
      min-width: 60px !important;
      max-width: 60px !important;
      text-align: center;
    }
    
    #dataTables-example667 th:nth-child(7),
    #dataTables-example667 td:nth-child(7) {
      width: 65px !important;
      min-width: 65px !important;
      max-width: 65px !important;
      text-align: center;
    }
    
    #dataTables-example667 th:nth-child(8),
    #dataTables-example667 td:nth-child(8) {
      width: 80px !important;
      min-width: 80px !important;
      max-width: 80px !important;
      text-align: center;
    }
    
    #dataTables-example667 th:nth-child(9),
    #dataTables-example667 td:nth-child(9) {
      width: 75px !important;
      min-width: 75px !important;
      max-width: 75px !important;
      text-align: center;
    }
    
    #dataTables-example667 th:nth-child(10),
    #dataTables-example667 td:nth-child(10) {
      width: 95px !important;
      min-width: 95px !important;
      max-width: 95px !important;
    }
    
    #dataTables-example667 th:nth-child(11),
    #dataTables-example667 td:nth-child(11) {
      width: 80px !important;
      min-width: 80px !important;
      max-width: 80px !important;
    }
    
    #dataTables-example667 th:nth-child(12),
    #dataTables-example667 td:nth-child(12) {
      width: 80px !important;
      min-width: 80px !important;
      max-width: 80px !important;
    }
    
    /* Resto de celdas sin wrap */
    #dataTables-example667 tbody td {
      white-space: nowrap;
    }
    
    /* Excepto Concepto */
    #dataTables-example667 tbody td:nth-child(1) {
      white-space: normal;
    }
    
    /* Inputs compactos */
    #dataTables-example667 input.form-control {
      font-size: 0.75rem;
      padding: 0.25rem 0.35rem;
      height: 28px;
      width: 100% !important;
      max-width: 100% !important;
      box-sizing: border-box !important;
    }
    
    /* Importante: Eliminar scrolls de DataTables */
    .dataTables_wrapper .dataTables_scrollHead,
    .dataTables_wrapper .dataTables_scrollBody {
      overflow: visible !important;
    }
    
    .dataTables_wrapper {
      overflow-x: auto;
    }
    
    .dataTables_scrollBody {
      overflow: visible !important;
    }
    
    .dataTables_scrollHead table,
    .dataTables_scrollBody table {
      width: 100% !important;
    }
    
    /* Controles de DataTable más compactos */
    .dataTables_length select,
    .dataTables_filter input {
      font-size: 0.8rem;
      padding: 0.25rem 0.5rem;
    }
    
    .dataTables_info,
    .dataTables_length,
    .dataTables_filter {
      font-size: 0.8rem;
    }
    
    h6 {
      font-weight: 600;
      margin-bottom: 1rem;
    }
    
    /* Reducir espacio en paginación */
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      padding: 0.25rem 0.5rem;
      font-size: 0.8rem;
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
          $ubicacion="Gestión de Pedido y Nueva Orden de Compra";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Información del Pedido</h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#" id="form-unificado" onsubmit="return validarFormularioCompra();">
                    <div class="card-body"><?php
                      if (isset($error)){?>
                        <div class="alert alert-danger"><?=$error;?></div><?php
                      }?>
                      <div class="row">
                        <div class="col-md-6">
                          <h6 class="mb-3">Datos del Pedido</h6>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Fecha Pedido</label>
                            <div class="col-sm-8"><input name="fecha" type="date" onfocus="this.showPicker()" value="<?=$data['fecha'];?>" class="form-control" disabled></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Proyecto</label>
                            <div class="col-sm-8">
                              <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" disabled="disabled">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from computos c inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio where c.id = ".$data['id_computo'];
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                  <option value='<?=$fila['id']?>' selected><?=$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Solicitante</label>
                            <div class="col-sm-8">
                              <select name="id_cuenta_solicitante" id="id_cuenta_solicitante" class="js-example-basic-single col-sm-12" disabled>
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
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
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Lugar de Entrega</label>
                            <div class="col-sm-8"><input name="lugar_entrega" type="text" maxlength="199" class="form-control" value="<?=$data['lugar_entrega'];?>" disabled></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Recibe</label>
                            <div class="col-sm-8">
                              <select name="id_cuenta_recibe" id="id_cuenta_recibe" class="js-example-basic-single col-sm-12">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  if ($fila['id'] == $data['id_cuenta_recibe']) {
                                      echo " selected ";
                                    }
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                        </div>
                        
                        <?php if ($data['aprobado']==1 && tienePermiso(298)): ?>
                        <div class="col-md-6">
                          <h6 class="mb-3">Datos de la Orden de Compra</h6>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Proveedor(*)</label>
                            <div class="col-sm-8">
                              <select name="id_cuenta_proveedor" id="id_cuenta_proveedor" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option>
                                <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (5) and activo = 1 and anulado = 0";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='".$fila['id']."'";
                                    echo ">".$fila['nombre']."</option>";
                                  }
                                  Database::disconnect();
                                ?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Fecha Emisión(*)</label>
                            <div class="col-sm-8"><input name="fecha_emision" type="date" onfocus="this.showPicker()" value="<?=date('Y-m-d');?>" class="form-control" required="required"></div>
                          </div>
                          <?php
                            $pdo = Database::connect();
                            $fechaSolicitada = "";
                            $sql = "SELECT fecha FROM `pedidos` WHERE id = ".$_GET['id'];
                            $q = $pdo->prepare($sql);
                            $q->execute();
                            $dataFecha = $q->fetch(PDO::FETCH_ASSOC);
                            $fechaSolicitada = $dataFecha['fecha'];
                            Database::disconnect();
                          ?>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Fecha Entrega</label>
                            <div class="col-sm-8"><input name="fecha_entrega" type="date" onfocus="this.showPicker()" value="<?=$fechaSolicitada; ?>" class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Moneda(*)</label>
                            <div class="col-sm-8">
                              <select name="id_moneda" id="id_moneda" class="js-example-basic-single col-sm-12" require>
                                <option value="">Seleccione...</option>
                                <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT `id`, `moneda` FROM `monedas` WHERE 1";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='".$fila['id']."'";
                                    echo ">".$fila['moneda']."</option>";
                                  }
                                  Database::disconnect();
                                ?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Tipo de Cambio</label>
                            <div class="col-sm-8"><input name="tipo_cambio_dia" type="number" step="0.01" class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Descuento</label>
                            <div class="col-sm-8"><input name="descuento" type="number" step="0.01" class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Forma de Pago(*)</label>
                            <div class="col-sm-8">
                              <select name="id_forma_pago" id="id_forma_pago" class="js-example-basic-single col-sm-12" required>
                                <option value="">Seleccione...</option>
                                <?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT `id`, `forma_pago` FROM `formas_pago` WHERE 1";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='".$fila['id']."'";
                                    echo ">".$fila['forma_pago']."</option>";
                                  }
                                  Database::disconnect();
                                ?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Comentarios</label>
                            <div class="col-sm-8"><textarea name="comentarios" class="form-control" rows="2"></textarea></div>
                          </div>
                        </div>
                        <?php endif; ?>
                      </div>
                      
                      <hr class="mt-4 mb-4">
                      
                      <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3">Detalle de Conceptos</h6>
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
                                <?php if ($data['aprobado']==1 && tienePermiso(298)): ?>
                                <th>Cant. Solic.</th>
                                <th>Cant. Pedir</th>
                                <th>P. Unit.</th>
                                <th>P. x Kg</th>
                                <?php endif; ?>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                              $pdo = Database::connect();
                              $sql = " SELECT pd.id, m.concepto, pd.cantidad, date_format(pd.fecha_necesidad,'%d/%m/%y'), u.unidad_medida,pd.id_material,pd.reservado,pd.comprado FROM pedidos_detalle pd inner join materiales m on m.id = pd.id_material inner join unidades_medida u on u.id = pd.id_unidad_medida WHERE pd.id_pedido = ".$_GET['id'];
                              
                              foreach ($pdo->query($sql) as $row) {
                                $sql2 = "SELECT d.precio,date_format(c.fecha_emision,'%d/%m/%y') fecha_emision FROM compras_detalle d inner join compras c on c.id = d.id_compra WHERE d.id_material = ".$row[5]." order by c.id desc limit 0,1 ";
                                $q2 = $pdo->prepare($sql2);
                                $q2->execute();
                                $data2 = $q2->fetch(PDO::FETCH_ASSOC);
                                
                                $cantidadDisponible = $row[2] - $row[6] - $row[7];
                                
                                echo '<tr>';
                                echo '<td>'. $row[1] . '</td>';
                                echo '<td>'. $row[3] . '</td>';
                                if (!empty($data2['fecha_emision'])) {
                                  echo '<td>'. $data2['fecha_emision'] . '</td>';	
                                } else {
                                  echo '<td>&nbsp;</td>';	
                                }
                                if (!empty($data2['precio'])) {
                                  echo '<td>$'. number_format($data2['precio'],2) . '</td>';	
                                } else {
                                  echo '<td>&nbsp;</td>';	
                                }
                                echo '<td>'. $row[2] .' '.$row[4]. '</td>';		
                                
                                $sql = "SELECT SUM(id.saldo) AS disponible FROM ingresos_detalle id WHERE id_material = ? ";
                                $q = $pdo->prepare($sql);
                                $q->execute([$row[5]]);
                                $data3 = $q->fetch(PDO::FETCH_ASSOC);
                                
                                if (empty($data3['disponible'])) {
                                  echo '<td>0</td>';	
                                } else {
                                  echo '<td>'.$data3['disponible'].'</td>';	
                                }
                                
                                echo '<td>'. $row[6] . '</td>';
                                echo '<td>'. $row[7] . '</td>';
                                
                                if ($data['aprobado']==1 && tienePermiso(298)) {
                                  echo '<td>'. $cantidadDisponible . '</td>';
                                  echo '<td><input name="cantidad_'.$row[0].'" type="number" step="0.01" min="0" max="'.$cantidadDisponible.'" class="form-control cantidad-input" value="'.$cantidadDisponible.'" data-id="'.$row[0].'"></td>';
                                  echo '<td><input name="precio_'.$row[0].'" type="number" step="0.01" class="form-control precio-input" value="0" data-id="'.$row[0].'"></td>';
                                  echo '<td><input name="preciokg_'.$row[0].'" type="number" step="0.01" class="form-control preciokg-input" value="0" data-id="'.$row[0].'"></td>';
                                }
                                
                                echo '</tr>';
                              }
                              Database::disconnect();
                              ?>
                            </tbody>
                          </table>
                          </div>
                          <?php if ($data['aprobado']==1 && tienePermiso(298)): ?>
                          <div class="mt-3">
                            <i><strong>NOTA:</strong> Si ingresa Precio x KG <> 0, el precio se sobreescribirá multiplicando el Precio x KG * Peso del Concepto.</i><br/>
                            <i>Para guardar una compra, debe ingresar al menos un concepto con cantidad mayor a 0 y al menos uno de los dos precios (Unitario o x Kg).</i>
                          </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-12 text-center">
                        <a class="btn btn-primary" target="_blank" href="imprimirPedido.php?id=<?=$data['id']; ?>">Imprimir Pedido</a>
                        <?php if ($data['aprobado']==1 && tienePermiso(298)): ?>
                        <button class="btn btn-success" id="submit-btn-compra" type="submit" data-original-text="Crear Orden de Compra">Crear Orden de Compra</button>
                        <?php endif; ?>
                        <a href="#" onclick="document.location.href='listarPedidos.php'" class="btn btn-light">Volver</a>
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
          { width: "75px", targets: 8, orderable: true, className: "text-center" },
          { width: "95px", targets: 9, orderable: false },
          { width: "80px", targets: 10, orderable: false },
          { width: "80px", targets: 11, orderable: false }
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
    });

    function validarFormularioCompra() {
      var hayConceptoValido = false;
      
      $('.cantidad-input').each(function() {
        var id = $(this).data('id');
        var cantidad = parseFloat($(this).val()) || 0;
        var precioUnitario = parseFloat($('input[name="precio_' + id + '"]').val()) || 0;
        var precioKg = parseFloat($('input[name="preciokg_' + id + '"]').val()) || 0;
        
        if (cantidad > 0 && (precioUnitario > 0 || precioKg > 0)) {
          hayConceptoValido = true;
          return false;
        }
      });
      
      if (!hayConceptoValido) {
        alert('Debe ingresar al menos un concepto con cantidad mayor a 0 y al menos uno de los dos precios (Precio Unitario o Precio x Kg)');
        return false;
      }
      
      return true;
    }
  </script>
  <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  <!-- Plugin used-->

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('form-unificado');
        const submitButton = document.getElementById('submit-btn-compra');

        if (form && submitButton) {
            form.addEventListener('submit', function(event) {
                
                // Primero, ejecuta tu validación existente.
                // Si la validación falla, detenemos todo aquí.
                if (!validarFormularioCompra()) {
                    event.preventDefault(); // Detiene el envío del formulario
                    return; // No deshabilita el botón si la validación falla
                }

                // Si la validación es exitosa, deshabilita el botón
                submitButton.disabled = true;
                submitButton.innerHTML = 'Procesando... <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                // Hay un pequeño "truco" para re-habilitar el botón si el usuario
                // usa el botón "Atrás" del navegador y la página se carga desde el caché.
                window.addEventListener('pageshow', function(event) {
                    if (event.persisted) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = submitButton.getAttribute('data-original-text');
                    }
                });

                // En un escenario ideal, si el envío AJAX fallara, se re-habilitaría el botón.
                // Como este es un envío de formulario tradicional, no necesitamos un bloque 'catch'
                // ya que la página recargará o redirigirá.
            });
        }
    });
    </script>
  </body>
</html>