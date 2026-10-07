<?php
require("config.php");
if(empty($_SESSION['user'])){
  header("Location: index.php");
  die("Redirecting to index.php"); 
}
require 'database.php';
require_once 'validarClienteFacturacion.php';
require_once 'generarNCCobro.php';
require_once 'informarFacturaARCA.php';
$id = null;
if (empty($_SESSION['csrf_nc'])) $_SESSION['csrf_nc'] = bin2hex(random_bytes(32));
$mensajeNC = $_SESSION['mensaje_nc'] ?? '';
unset($_SESSION['mensaje_nc']);
if (!empty($_GET['id'])) {
  $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
}
if ( null==$id ) {
  header("Location: listarCobros.php");
  exit;
}

$items=array();
$totalFactura=0;
$bloquearEdicionFactura=false;
$attrDisabled='';
$bloqueoFacturaJs='false';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_cobro') {
  try {
    if (!is_string($_POST['csrf_nc'] ?? null) || !hash_equals($_SESSION['csrf_nc'], $_POST['csrf_nc'])) {
      throw new RuntimeException('La sesion del formulario vencio. Recargue la pagina.');
    }
    require_once 'eliminarCobroSinFactura.php';
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    eliminarCobroSinFactura($pdo, $id);
    header('Location: listarCobros.php', true, 303);
    exit;
  } catch (Throwable $e) {
    $_SESSION['mensaje_nc'] = $e->getMessage();
  }
  header('Location: modificarCobro.php?id='.(int)$id, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'quitar_factura') {
  try {
    if (!is_string($_POST['csrf_nc'] ?? null) || !hash_equals($_SESSION['csrf_nc'], $_POST['csrf_nc'])) {
      throw new RuntimeException('La sesion del formulario vencio. Recargue la pagina.');
    }
    require_once 'quitarFacturaCobro.php';
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    quitarFacturaCobro($pdo, $id);
    $_SESSION['mensaje_nc'] = 'La factura fallida se elimino. El cobro y sus items se conservaron sin factura.';
  } catch (Throwable $e) {
    $_SESSION['mensaje_nc'] = $e->getMessage();
  }
  header('Location: modificarCobro.php?id='.(int)$id, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reintentar_factura') {
  try {
    if (!is_string($_POST['csrf_nc'] ?? null) || !hash_equals($_SESSION['csrf_nc'], $_POST['csrf_nc'])) {
      throw new RuntimeException('La sesion del formulario vencio. Recargue la pagina.');
    }
    $pdo = Database::connect();
    $q = $pdo->prepare('SELECT f.* FROM cobros co INNER JOIN facturas f ON f.id=co.id_factura WHERE co.id=?');
    $q->execute(array($id));
    $factura = $q->fetch(PDO::FETCH_ASSOC);
    if (!puedeReintentarFacturaARCA($factura)) {
      throw new RuntimeException('La factura no admite un reintento automatico. Consulte su estado antes de volver a emitir.');
    }
    $_SESSION['mensaje_nc'] = informarFacturaARCA($factura['id']);
  } catch (Throwable $e) {
    $_SESSION['mensaje_nc'] = $e->getMessage();
  }
  header('Location: modificarCobro.php?id='.(int)$id, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'generar_nc') {
  try {
    if (!is_string($_POST['csrf_nc'] ?? null) || !hash_equals($_SESSION['csrf_nc'], $_POST['csrf_nc'])) {
      throw new RuntimeException('La sesion del formulario vencio. Recargue la pagina.');
    }
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    generarNCCobro($pdo, $id, is_string($_POST['motivo_nc'] ?? null) ? $_POST['motivo_nc'] : '', $_SESSION['user']['id']);
    header('Location: verCobro.php?id='.(int)$id);
    exit;
  } catch (Throwable $e) {
    $_SESSION['mensaje_nc'] = $e->getMessage();
  }
  header('Location: modificarCobro.php?id='.(int)$id);
  exit;
}

if ( !empty($_POST)) {

  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  try{
    $qNC = $pdo->prepare('SELECT f.id_nota_credito FROM cobros co INNER JOIN facturas f ON f.id = co.id_factura WHERE co.id = ? AND f.id_nota_credito IS NOT NULL');
    $qNC->execute([$id]);
    if ($qNC->fetch()) throw new RuntimeException('El cobro tiene una NC emitida o pendiente y no admite cambios.');
    $pdo->beginTransaction();
    // obtener estado actual del cobro/factura
    $sqlAct = "SELECT id_factura, id_cliente, id_emisor, fecha_factura, monto_pesos, detalle FROM cobros WHERE id = ? FOR UPDATE";
    $qAct = $pdo->prepare($sqlAct);
    $qAct->execute(array($id));
    $rowAct = $qAct->fetch(PDO::FETCH_ASSOC);
    if (!$rowAct) throw new RuntimeException('El cobro ya no existe.');
    $idFacturaActual = $rowAct['id_factura'];

    $cotizacion_usd=filter_var($_POST['cotizacion_usd'],FILTER_VALIDATE_FLOAT);
    $id_cliente=filter_var($_POST['id_cliente'] ?? null,FILTER_VALIDATE_INT);
    $fecha_cobro=$_POST['fecha_cobro'];
    if($fecha_cobro==""){
      $fecha_cobro=null;
    }
    $generarFactura = isset($_POST['generar_factura']);
    $id_emisor = !empty($_POST['id_emisor']) ? filter_var($_POST['id_emisor'],FILTER_VALIDATE_INT) : null;

    $idFactura=null;

    // Si el cobro ya tiene factura, no se permite modificar datos de factura ni del cobro ligado a facturacion.
    // Solo se permite actualizar fecha de cobro y cotizacion USD.
    if($idFacturaActual){
      $qEstado = $pdo->prepare('SELECT estado,cae FROM facturas WHERE id=? FOR UPDATE');
      $qEstado->execute(array($idFacturaActual));
      $estadoActual = $qEstado->fetch(PDO::FETCH_ASSOC);
      if (!$estadoActual || $estadoActual['estado'] !== 'A' || empty($estadoActual['cae'])) {
        throw new RuntimeException('La factura esta pendiente de autorizacion. Reintente el envio o deje el cobro sin factura cuando corresponda.');
      }
      $montoPesosActual = isset($rowAct['monto_pesos']) ? (float)$rowAct['monto_pesos'] : 0;
      $monto_dolares=0;
      if($cotizacion_usd>0){
        $monto_dolares=$montoPesosActual/$cotizacion_usd;
      }

      $sql = "UPDATE cobros set fecha_cobro = ?, cotizacion_usd = ?, monto_dolares = ? where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute(array($fecha_cobro,$cotizacion_usd,$monto_dolares,$id));

      $pdo->commit();
      Database::disconnect();
      header("Location: listarCobros.php");
      exit;
    }

    // Procesar ítems siempre
    $totalFactura=0;
    $itemsSan=array();
    if(isset($_POST['items']) && is_array($_POST['items'])){
      foreach($_POST['items'] as $item){
        if(!isset($item['precio'], $item['cantidad'])){
          $pdo->rollBack();
          die('Datos de ítems incompletos');
        }
        $desc=trim($item['descripcion']);
        $precio=filter_var($item['precio'],FILTER_VALIDATE_FLOAT);
        $cantidad=filter_var($item['cantidad'],FILTER_VALIDATE_FLOAT);
        if($desc==='' || $precio===false || $cantidad===false || $cantidad<0.1){
          $pdo->rollBack();
          die('Datos de ítems inválidos');
        }
        $desc=htmlspecialchars($desc,ENT_QUOTES,'UTF-8');
        $itemsSan[]=['descripcion'=>$desc,'precio'=>$precio,'cantidad'=>$cantidad];
        $totalFactura+=floatval($precio)*floatval($cantidad);
      }
    }

    if(empty($itemsSan)){
      $pdo->rollBack();
      die('Debe agregar al menos un ítem');
    }

    if($generarFactura){
      validarClienteParaFactura($pdo, $id_cliente);
      if($id_emisor===false || $id_emisor===null){
        $pdo->rollBack();
        die('Debe seleccionar un emisor para generar factura');
      }
      if(empty($_POST['fecha_factura'])){
        $pdo->rollBack();
        die('Debe indicar la fecha de factura');
      }
      $postedTotal=filter_var($_POST['total_factura'],FILTER_VALIDATE_FLOAT);
      if($postedTotal===false || abs($totalFactura-$postedTotal)>0.01){
        $pdo->rollBack();
        die('Total de factura inconsistente');
      }
      if($idFacturaActual){
        $sqlF="UPDATE facturas SET id_cliente = ?, fecha_cbte = ?, imp_total = ? WHERE id = ?";
        $qF=$pdo->prepare($sqlF);
        $qF->execute(array($id_cliente,$_POST['fecha_factura'],$totalFactura,$idFacturaActual));
        $idFactura=$idFacturaActual;
        $pdo->prepare("DELETE FROM factura_detalle WHERE id_factura = ?")->execute(array($idFactura));
      }else{
        $sqlF="INSERT INTO facturas (id_cliente,fecha_cbte,imp_total) VALUES (?,?,?)";
        $qF=$pdo->prepare($sqlF);
        $qF->execute(array($id_cliente,$_POST['fecha_factura'],$totalFactura));
        $idFactura=$pdo->lastInsertId();
      }
      if(!empty($itemsSan)){
        $sqlD="INSERT INTO factura_detalle (id_factura,descripcion,precio,cantidad) VALUES (?,?,?,?)";
        $qD=$pdo->prepare($sqlD);
        foreach($itemsSan as $it){
          $qD->execute(array($idFactura,$it['descripcion'],$it['precio'],$it['cantidad']));
        }
      }
    }else{
      if($idFacturaActual){
        $pdo->prepare("DELETE FROM factura_detalle WHERE id_factura = ?")->execute(array($idFacturaActual));
        $pdo->prepare("DELETE FROM facturas WHERE id = ?")->execute(array($idFacturaActual));
      }
    }

    $monto_pesos=$totalFactura;
    $monto_dolares=0;
    if($cotizacion_usd>0 && $monto_pesos!==null && $monto_pesos!==false){
      $monto_dolares=$monto_pesos/$cotizacion_usd;
    }

    $detalle = isset($itemsSan[0]['descripcion']) ? $itemsSan[0]['descripcion'] : '';
    $sql = "UPDATE cobros set id_cliente = ?, id_emisor = ?, fecha_factura = ?, fecha_cobro = ?, monto_pesos = ?, cotizacion_usd = ?, monto_dolares = ?, detalle = ?, id_factura = ? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute(array($id_cliente,$id_emisor,$_POST['fecha_factura'],$fecha_cobro,$monto_pesos,$cotizacion_usd,$monto_dolares,$detalle,$idFactura,$id));

    $pdo->prepare('DELETE FROM cobro_detalle WHERE id_cobro=?')->execute(array($id));
    if (!$idFactura) {
      $qDetalle = $pdo->prepare('INSERT INTO cobro_detalle (id_cobro,descripcion,precio,cantidad) VALUES (?,?,?,?)');
      foreach ($itemsSan as $item) {
        $qDetalle->execute(array($id,$item['descripcion'],$item['precio'],$item['cantidad']));
      }
    }

    $pdo->commit();

    Database::disconnect();

    if($idFactura){
      require_once 'informarFacturaARCA.php';
      $resultadoArca = informarFacturaARCA($idFactura);
      if(stripos($resultadoArca,'Error') !== false){
        $_SESSION['mensaje_nc'] = 'El cobro se guardo, pero la factura no se autorizo. '.$resultadoArca;
        header('Location: modificarCobro.php?id='.(int)$id, true, 303);
        exit;
      }
    }

    header("Location: listarCobros.php");
    exit;
  }catch(Exception $e){
    if($pdo->inTransaction()){
      $pdo->rollBack();
    }
    $_SESSION['mensaje_nc'] = 'Error al procesar el cobro: '.$e->getMessage();
    header('Location: modificarCobro.php?id='.(int)$id, true, 303);
    exit;
  }

} else {

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $sql = "SELECT co.id AS id_cobro,co.fecha_factura,co.fecha_cobro,co.detalle,co.monto_pesos,co.cotizacion_usd,co.id_cliente,co.id_emisor,co.id_factura FROM cobros co WHERE co.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute(array($id));
  $data = $q->fetch(PDO::FETCH_ASSOC);
  if (!$data) { header('Location: listarCobros.php'); exit; }
  $qNC = $pdo->prepare('SELECT * FROM facturas WHERE id_factura_asociada = ?');
  $qNC->execute([$data['id_factura']]);
  $notaCredito = $qNC->fetch(PDO::FETCH_ASSOC);
  $facturaNC = null;
  if (!empty($data['id_factura'])) {
    $qNC = $pdo->prepare('SELECT * FROM facturas WHERE id = ?');
    $qNC->execute([$data['id_factura']]);
    $facturaNC = $qNC->fetch(PDO::FETCH_ASSOC);
  }

  $items=array();
  $totalFactura=0;
  if($data && $data['id_factura']){
    $sqlDet="SELECT descripcion,precio,cantidad FROM factura_detalle WHERE id_factura = ?";
    $qDet=$pdo->prepare($sqlDet);
    $qDet->execute(array($data['id_factura']));
    while($rowDet=$qDet->fetch(PDO::FETCH_ASSOC)){
      $items[]=$rowDet;
      $totalFactura+=floatval($rowDet['precio'])*floatval($rowDet['cantidad']);
    }
  }elseif($data){
    $descBase=trim((string)$data['detalle']);
    if($descBase===''){
      $descBase='Cobro';
    }
    $precioBase=floatval($data['monto_pesos']);
    if($precioBase<0){
      $precioBase=0;
    }
    $items[] = array('descripcion'=>$descBase,'precio'=>$precioBase,'cantidad'=>1);
    $totalFactura=$precioBase;
  }

  if (empty($data['id_factura'])) {
    $qDet = $pdo->prepare('SELECT descripcion,precio,cantidad FROM cobro_detalle WHERE id_cobro=? ORDER BY id');
    $qDet->execute(array($id));
    $detalleCobro = $qDet->fetchAll(PDO::FETCH_ASSOC);
    if ($detalleCobro) {
      $items = $detalleCobro;
      $totalFactura = 0;
      foreach ($items as $it) $totalFactura += (float)$it['precio'] * (float)$it['cantidad'];
    }
  }
  $facturaPendiente = !empty($data['id_factura']) && (!$facturaNC || $facturaNC['estado'] !== 'A' || empty($facturaNC['cae']));
  $bloquearDatosCobro = $facturaPendiente || !empty($notaCredito);

  $tienePagosColaboradores = false;
  if (empty($data['id_factura'])) {
    $qPagos = $pdo->prepare('SELECT id FROM pagos_colaboradores WHERE id_cobro=? LIMIT 1');
    $qPagos->execute(array($id));
    $tienePagosColaboradores = (bool)$qPagos->fetchColumn();
  }

  $bloquearEdicionFactura = ($data && !empty($data['id_factura']));
  if($bloquearEdicionFactura){
    $attrDisabled='disabled';
    $bloqueoFacturaJs='true';
  }

  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
	  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="vendor/bootstrap-select-1.13.14/dist/css/bootstrap-select.min.css">
    <style>
      .multiselect{
        color:#212529 !important;
        background-color:#fff;
        border-color:#ccc;
      }
      .cobros-table{
        table-layout:fixed;
      }
      .cobros-table thead th{
        text-align:center;
        vertical-align:middle;
      }
      .cobros-table th:nth-child(5),
      .cobros-table td:nth-child(5),
      .cobros-table .acciones-cell{
        text-align:center;
        vertical-align:middle;
      }
      .cobros-table col.desc-col{ width:40%; }
      .cobros-table col.precio-col{ width:18%; }
      .cobros-table col.cantidad-col{ width:10%; }
      .cobros-table col.subtotal-col{ width:18%; }
      .cobros-table col.acciones-col{ width:14%; }
      .subtotal-input{
        min-height: calc(1.5em + 0.75rem + 2px);
      }
      .monto-factura-label{
        font-weight: 700;
        margin-bottom: 0;
      }
      .monto-factura-preview{
        min-height: calc(1.5em + 0.95rem + 2px);
        font-size: 1rem;
      }
      .item-trash-icon{
        width: 1rem;
        height: 1rem;
      }
    </style>
  </head>
  <body class="light-only">
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
	    <?php include('header.php');?>
	  
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
		    <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body">
          <div class="container-fluid">
            <div class="page-header">
              <div class="row">
                <div class="col-10">
                  <div class="page-header-left">
                    <h3><?php include("title.php"); ?></h3>
                    <ol class="breadcrumb">
                      <li class="breadcrumb-item"><a href="#"><i data-feather="home"></i></a></li>
                      <li class="breadcrumb-item">Modificar Cobro</li>
                    </ol>
                  </div>
                </div>
                <!-- Bookmark Start-->
                <div class="col-2">
                  <div class="bookmark pull-right">
                    <ul>
                      <li><a  target="_blank" data-container="body" data-toggle="popover" data-placement="top" title="" data-original-title="<?=date('d-m-Y');?>"><i data-feather="calendar"></i></a></li>
                    </ul>
                  </div>
                </div>
                <!-- Bookmark Ends-->
              </div>
            </div>
          </div>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Modificar Cobro</h5>
                  </div><?php
                  if (!empty($facturaNC['homologacion'])) { ?>
                    <div class="alert alert-warning m-3" style="border:3px solid #b45309;font-size:18px;" role="alert"><strong>HOMOLOGACI&Oacute;N - REGISTRO DE PRUEBA SIN VALIDEZ FISCAL</strong></div><?php
                  }
                  if ($mensajeNC !== '') { ?>
                    <div class="alert alert-info m-3" role="alert"><?=htmlspecialchars($mensajeNC, ENT_QUOTES, 'UTF-8')?></div><?php
                  }
                  if ($notaCredito) { ?>
                    <div class="alert alert-warning m-3">
                      <strong><?=$notaCredito['estado'] === 'A' ? 'Cobro anulado por NC' : 'NC pendiente de conciliacion con ARCA'?></strong><br>
                      Motivo: <?=htmlspecialchars($notaCredito['motivo_nc'], ENT_QUOTES, 'UTF-8')?><br>
                      Comprobante: <span class="text-nowrap"><?=!empty($notaCredito['numero_comprobante']) ? sprintf('%04d-%08d', $notaCredito['punto_venta'], $notaCredito['numero_comprobante']) : 'Pendiente'?></span>
                      <?php if ($notaCredito['cae']) { ?> | CAE: <?=htmlspecialchars($notaCredito['cae'], ENT_QUOTES, 'UTF-8')?><?php } ?>
                    </div><?php
                  }
                  if (!empty($facturaNC['error_arca'])) { ?>
                    <div class="alert alert-warning m-3" role="alert">
                      <?=htmlspecialchars($facturaNC['error_arca'], ENT_QUOTES, 'UTF-8')?><?php
                      if (!$notaCredito && puedeReintentarFacturaARCA($facturaNC)) { ?>
                        <p class="mb-0 mt-2">Corrija la causa del error y reintente el envio sobre esta misma factura.</p><?php
                      } ?>
                    </div><?php
                  }
                  if(!empty($bloquearEdicionFactura)){ ?>
                    <div class="alert alert-warning m-3 mb-0"><?php
                      if ($facturaPendiente) { ?>
                        Factura pendiente de autorizaci&oacute;n. No se pueden guardar cambios en el cobro hasta resolver la facturaci&oacute;n.<?php
                      } elseif ($notaCredito) { ?>
                        El cobro tiene una nota de cr&eacute;dito y no admite modificaciones.<?php
                      } else { ?>
                        Factura autorizada por ARCA. Solo se puede modificar la fecha de cobro y la cotizaci&oacute;n USD.<?php
                      } ?>
                    </div><?php
                  }?>
				          <form class="form theme-form" role="form" method="post" action="modificarCobro.php?id=<?=$id?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Emisor</label>
                            <div class="col-sm-3">
                              <select name="id_emisor" id="id_emisor" class="form-control form-control-sm filtraTabla selectpicker multiselect w-100" data-live-search="true" <?=$attrDisabled; ?>>
                                <option value="">Sin emisor</option><?php
                                $pdo = Database::connect();
                                $sql = "SELECT id, razon_social, cuit FROM emisores WHERE activo = 1 ORDER BY razon_social";
                                foreach ($pdo->query($sql) as $row) {
                                  $selected = "";
                                  if($data["id_emisor"] == $row["id"]){
                                    $selected = " selected";
                                  }?>
                                  <option value="<?=$row["id"]?>"<?=$selected?>><?=$row["razon_social"]?> (<?=$row["cuit"]?>)</option><?php
                                }
                                Database::disconnect();?>
                              </select>
                              <small id="emisor_help" class="text-danger" style="display:none;">Debe seleccionar un emisor para generar factura.</small>
                            </div>
                            <label class="col-sm-3 col-form-label">Fecha factura</label>
                            <div class="col-sm-3"><input name="fecha_factura" type="date" class="form-control" value="<?=$data["fecha_factura"]?>" <?=$attrDisabled; ?>></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cliente</label>
                            <div class="col-sm-3">
                              <select name="id_cliente" class="form-control form-control-sm filtraTabla selectpicker multiselect w-100" data-live-search="true" required <?=$attrDisabled; ?>>
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $sql = "SELECT c.id AS id_cliente,c.nombre,c.pide_factura,c.cuit,c.id_cond_fiscal,c.cond_fiscal FROM clientes c WHERE c.activo = 1";
                                foreach ($pdo->query($sql) as $row) {
                                  $selected="";
                                  if($data["id_cliente"]==$row["id_cliente"]){
                                    $selected=" selected";
                                  }?>
                                  <option value="<?=$row["id_cliente"]?>" data-pide-factura="<?=$row["pide_factura"]?>" data-fiscal="<?=htmlspecialchars(json_encode(analizarClienteFacturacion($row)), ENT_QUOTES, 'UTF-8')?>" <?=$selected?>><?=$row["nombre"]?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                            <label class="col-sm-3 col-form-label" for="generar_factura">Generar factura</label>
                            <div class="col-sm-3"><input type="checkbox" name="generar_factura" id="generar_factura" <?php if($data['id_factura']) echo 'checked'; ?> <?=$attrDisabled; ?>></div>
                          </div>
                          <div id="cliente_facturacion_aviso" class="alert" role="status" aria-live="polite" style="display:none;"></div>
                          <div id="factura_section">
                            <table class="table cobros-table">
                              <colgroup>
                                <col class="desc-col">
                                <col class="precio-col">
                                <col class="cantidad-col">
                                <col class="subtotal-col">
                                <col class="acciones-col">
                              </colgroup>
                              <thead>
                                <tr>
                                  <th>Descripción</th>
                                  <th>Precio</th>
                                  <th>Cantidad</th>
                                  <th>Subtotal</th>
                                  <th>Acciones</th>
                                </tr>
                              </thead>
                              <tbody id="detalles"><?php
                                foreach($items as $i => $it){ ?>
                                  <tr data-index="<?= $i ?>">
                                    <td><input type="text" name="items[<?= $i ?>][descripcion]" class="form-control" value="<?= $it['descripcion'] ?>" required <?=$attrDisabled; ?>></td>
                                    <td><input type="number" step="100" name="items[<?= $i ?>][precio]" class="form-control precio" value="<?= $it['precio'] ?>" required <?=$attrDisabled; ?>></td>
                                    <td><input type="number" name="items[<?= $i ?>][cantidad]" class="form-control cantidad" step="0.1" value="<?= $it['cantidad'] ?>" required <?=$attrDisabled; ?>></td>
                                    <td><input type="text" class="form-control text-right subtotal subtotal-input" value="0" readonly tabindex="-1"></td>
                                    <td class="acciones-cell"><button type="button" class="btn btn-danger btn-sm remove" title="Eliminar fila" <?=$attrDisabled; ?>><i data-feather="trash-2" class="item-trash-icon"></i></button></td>
                                  </tr><?php
                                } ?>
                              </tbody>
                              <tfoot>
                                <tr id="monto_factura_preview_row">
                                  <td colspan="3" class="text-right align-middle">
                                    <label class="monto-factura-label">Monto factura</label>
                                    <input type="hidden" name="total_factura" id="total_factura" value="<?=$totalFactura?>">
                                  </td>
                                  <td><input id="monto_factura_preview" type="text" class="form-control monto-factura-preview text-right" value="" readonly tabindex="-1"></td>
                                  <td class="acciones-cell"><button type="button" class="btn btn-secondary btn-sm" id="add_item" <?=$attrDisabled; ?>>Agregar ítem</button></td>
                                </tr>
                              </tfoot>
                            </table>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha de cobro</label>
                            <div class="col-sm-3"><input name="fecha_cobro" type="date" class="form-control" value="<?=$data["fecha_cobro"]?>" <?=$bloquearDatosCobro ? 'disabled' : ''?>></div>
                            <label class="col-sm-3 col-form-label">Cotizacion USD</label>
                            <div class="col-sm-3"><input name="cotizacion_usd" type="number" maxlength="99" class="form-control" value="<?=$data["cotizacion_usd"]?>" <?=$bloquearDatosCobro ? 'disabled' : ''?>></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3"><?php
                        if (!$notaCredito && puedeReintentarFacturaARCA($facturaNC)) { ?>
                          <button class="btn btn-warning" type="submit" form="formReintentarFactura">Reintentar factura en ARCA</button>
                          <button class="btn btn-outline-danger" type="submit" form="formQuitarFactura">Dejar como cobro sin factura</button><?php
                        } ?>
                        <button id="guardar_cobro" class="btn btn-primary" type="submit" <?=$bloquearDatosCobro ? 'disabled' : ''?>>Guardar datos del cobro</button><?php
                        if (empty($data['id_factura'])) { ?>
                          <button class="btn btn-danger" type="submit" form="formEliminarCobro" <?=$tienePagosColaboradores ? 'disabled' : ''?>>Eliminar cobro</button><?php
                          if ($tienePagosColaboradores) { ?>
                            <small class="text-muted d-block mt-2">No se puede eliminar porque tiene pagos a colaboradores.</small><?php
                          }
                        }
                        if ($facturaNC && $facturaNC['estado'] === 'A' && !empty($facturaNC['cae']) && !$notaCredito) { ?>
                          <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#modalNC">Anular con NC</button><?php
                        } ?>
						            <a href='listarCobros.php' class="btn btn-light">Volver</a>
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
    </div><?php
    if (empty($data['id_factura']) && !$tienePagosColaboradores) { ?>
      <form id="formEliminarCobro" method="post" action="modificarCobro.php?id=<?=(int)$id?>" onsubmit="return confirm('Se eliminara definitivamente este cobro y sus items. No quedara en Anulados. ¿Continuar?');">
        <input type="hidden" name="accion" value="eliminar_cobro">
        <input type="hidden" name="csrf_nc" value="<?=htmlspecialchars($_SESSION['csrf_nc'], ENT_QUOTES, 'UTF-8')?>">
      </form><?php
    }
    if (!$notaCredito && puedeReintentarFacturaARCA($facturaNC)) { ?>
      <form id="formQuitarFactura" method="post" action="modificarCobro.php?id=<?=(int)$id?>" onsubmit="return confirm('Se eliminara la factura fallida y se conservaran el cobro, sus items e importes. ¿Continuar?');">
        <input type="hidden" name="accion" value="quitar_factura">
        <input type="hidden" name="csrf_nc" value="<?=htmlspecialchars($_SESSION['csrf_nc'], ENT_QUOTES, 'UTF-8')?>">
      </form>
      <form id="formReintentarFactura" method="post" action="modificarCobro.php?id=<?=(int)$id?>" onsubmit="document.querySelector('[form=\'formReintentarFactura\']').disabled=true;">
        <input type="hidden" name="accion" value="reintentar_factura">
        <input type="hidden" name="csrf_nc" value="<?=htmlspecialchars($_SESSION['csrf_nc'], ENT_QUOTES, 'UTF-8')?>">
      </form><?php
    }
    if ($facturaNC && $facturaNC['estado'] === 'A' && !empty($facturaNC['cae']) && !$notaCredito) { ?>
      <div class="modal fade" id="modalNC" tabindex="-1" role="dialog" aria-labelledby="tituloNC" aria-hidden="true">
        <div class="modal-dialog" role="document"><div class="modal-content">
          <form method="post" action="modificarCobro.php?id=<?=(int)$id?>" id="formNC">
            <input type="hidden" name="accion" value="generar_nc">
            <input type="hidden" name="csrf_nc" value="<?=htmlspecialchars($_SESSION['csrf_nc'], ENT_QUOTES, 'UTF-8')?>">
            <div class="modal-header">
              <h5 class="modal-title" id="tituloNC">Anular cobro con nota de cr&eacute;dito</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
              <p>Se generar&aacute; una NC por el 100% de la factura: <strong>$ <?=number_format((float)$facturaNC['imp_total'], 2, ',', '.')?></strong>.</p>
              <p>Al autorizarse en ARCA, el cobro pasar&aacute; a Anulados y conservar&aacute; su importe original. La factura original y los pagos a colaboradores se conservar&aacute;n.</p>
              <label for="motivo_nc">Motivo de anulaci&oacute;n</label>
              <textarea class="form-control" id="motivo_nc" name="motivo_nc" rows="4" maxlength="1000" required></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-danger">Generar NC y anular</button>
            </div>
          </form>
        </div></div>
      </div><?php
    } ?>
    <!-- latest jquery-->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/cliente-facturacion.js"></script>
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
    <script src="vendor/bootstrap-select-1.13.14/dist/js/bootstrap-select.js"></script>
    <script src="vendor/bootstrap-select-1.13.14/js/i18n/defaults-es_ES.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script>
      $(function(){
        $('#modalNC').on('shown.bs.modal', function(){ $('#motivo_nc').trigger('focus'); });
        $('#formNC').on('submit', function(e){
          var motivo = $('#motivo_nc')[0];
          motivo.setCustomValidity(motivo.value.trim() ? '' : 'Ingrese el motivo de anulacion.');
          if (!this.reportValidity()) { e.preventDefault(); return; }
          $(this).find('button[type="submit"]').prop('disabled', true).text('Generando NC...');
        });
        $('#motivo_nc').on('input', function(){ this.setCustomValidity(''); });
        const bloqueoFactura = <?=$bloqueoFacturaJs; ?>;
        let itemIndex = $('#detalles tr').length;
        function syncFacturaRules(){
          if(bloqueoFactura){
            return;
          }
          var facturaActiva = $('#generar_factura').is(':checked');
          $('#guardar_cobro').text(facturaActiva ? 'Guardar y generar factura' : 'Guardar datos del cobro');
          $('#id_emisor').prop('required',facturaActiva);
          $('input[name="fecha_factura"]').prop('required',facturaActiva);
          $('#emisor_help').toggle(facturaActiva && !$('#id_emisor').val());
          $('input[name*="[descripcion]"], .precio').prop('required',true);
          $('#add_item').prop('disabled',!facturaActiva);
          $('input[name*="[cantidad]"]').prop('readonly',!facturaActiva);

          $('.selectpicker').selectpicker('refresh');
        }
        function formatNumber(num){
          return num.toLocaleString('es-AR',{minimumFractionDigits:0,maximumFractionDigits:0});
        }
        function addRow(){
          var idx = itemIndex++;
          var facturaActiva = $('#generar_factura').is(':checked');
          var cantidadReadonly = facturaActiva ? '' : ' readonly';
          var row='<tr data-index="'+idx+'">'+
            '<td><input type="text" name="items['+idx+'][descripcion]" class="form-control" required></td>'+
            '<td><input type="number" step="0.01" name="items['+idx+'][precio]" class="form-control precio" required></td>'+
            '<td><input type="number" name="items['+idx+'][cantidad]" class="form-control cantidad" step="0.1" value="1" required'+cantidadReadonly+'></td>'+
            '<td><input type="text" class="form-control text-right subtotal subtotal-input" value="0" readonly tabindex="-1"></td>'+
            '<td class="acciones-cell"><button type="button" class="btn btn-danger btn-sm remove" title="Eliminar fila"><i data-feather="trash-2" class="item-trash-icon"></i></button></td>'+
            '</tr>';
          $('#detalles').append(row);
          if(typeof feather !== 'undefined'){
            feather.replace();
          }
          calcTotal();
        }
        function calcTotal(){
          var total=0;
          $('#detalles tr').each(function(){
            var p=parseFloat($(this).find('.precio').val())||0;
            var c=parseFloat($(this).find('.cantidad').val())||0;
            var st=p*c;
            total+=st;
            $(this).find('.subtotal-input').val(formatNumber(st));
          });
          $('#total_factura').val(total.toFixed(2));
          $('#monto_factura_preview').val(formatNumber(total));
        }
        $('#generar_factura').on('change',function(){
          if(bloqueoFactura){
            return;
          }
          if($(this).is(':checked')){
            if($('#detalles tr').length===0){addRow();}
            calcTotal();
            $('#detalles tr').find('.cantidad').each(function(){
              $(this).prop('readonly',false);
            });
          }else{
            $('#detalles tr').find('.cantidad').each(function(){
              $(this).prop('readonly',true);
            });
          }
          syncFacturaRules();
        });
        $('#id_emisor').on('changed.bs.select change',function(){
          syncFacturaRules();
        });
        $('#add_item').on('click',function(){
          if(bloqueoFactura){
            return;
          }
          addRow();
        });
        $('#detalles').on('click','.remove',function(){
          if(bloqueoFactura){
            return;
          }
          $(this).closest('tr').remove();
          calcTotal();
        });
        $('#detalles').on('input','.precio, .cantidad',function(){
          if(bloqueoFactura){
            return;
          }
          calcTotal();
        });
        $('select[name="id_cliente"]').on('changed.bs.select', function(){
          if(bloqueoFactura){
            return;
          }
          var pideFactura = $('option:selected', this).data('pide-factura');
          $('#generar_factura').prop('checked', pideFactura==1).trigger('change');
        });
        $('form.theme-form').on('submit',function(){
          if(!bloqueoFactura){
            var facturaActiva = $('#generar_factura').is(':checked');
            if(facturaActiva && !$('#id_emisor').val()){
              $('#emisor_help').show();
              alert('Debe seleccionar un emisor para generar factura');
              return false;
            }
            if(facturaActiva && $('#detalles tr').length===0){
              alert('Debe agregar al menos un ítem para generar factura');
              return false;
            }
            if($('#detalles tr').length===0){
              alert('Debe agregar al menos un ítem');
              return false;
            }
          }
          $('#detalles tr').each(function(){
            var idx=$(this).data('index');
            var cantidadInput=$(this).find('input[name="items['+idx+'][cantidad]"]');
            if(cantidadInput.length>0){
              cantidadInput.prop('readonly',false);
            }else{
              $('<input>').attr({type:'hidden',name:'items['+idx+'][cantidad]',value:'1'}).appendTo($(this));
            }
            if($(this).find('input[name="items['+idx+'][precio]"]').length===0){
              $('<input>').attr({type:'hidden',name:'items['+idx+'][precio]',value:'0'}).appendTo($(this));
            }
          });
        });
        syncFacturaRules();
        $('#generar_factura').trigger('change');
        calcTotal();
      });
    </script>
  </body>
</html>
