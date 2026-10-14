<?php
require("config.php");
if(empty($_SESSION['user'])){
  header("Location: index.php");
  die("Redirecting to index.php"); 
}
require 'database.php';
require_once 'validarClienteFacturacion.php';
if ( !empty($_POST)) {
  $modoDebug=0;
  if($modoDebug==1){
    var_dump($_POST);
  }

  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  try{
    $cotizacion_usd=filter_var($_POST['cotizacion_usd'],FILTER_VALIDATE_FLOAT);
    $id_cliente=filter_var($_POST['id_cliente'],FILTER_VALIDATE_INT);
    $fecha_cobro=$_POST['fecha_cobro'];
    if($fecha_cobro==""){
      $fecha_cobro=null;
    }
    $generarFactura = isset($_POST['generar_factura']);
    $id_emisor = !empty($_POST['id_emisor']) ? filter_var($_POST['id_emisor'],FILTER_VALIDATE_INT) : null;

    $pdo->beginTransaction();
    $idFactura=null;

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
      $sqlF="INSERT INTO facturas (id_cliente,fecha_cbte,imp_total) VALUES (?,?,?)";
      $q=$pdo->prepare($sqlF);
      $q->execute(array($id_cliente,$_POST['fecha_factura'],$totalFactura));
      $idFactura=$pdo->lastInsertId();

      if(!empty($itemsSan)){
        $sqlD="INSERT INTO factura_detalle (id_factura,descripcion,precio,cantidad) VALUES (?,?,?,?)";
        $q=$pdo->prepare($sqlD);
        foreach($itemsSan as $item){
          $q->execute(array($idFactura,$item['descripcion'],$item['precio'],$item['cantidad']));
        }
      }
    }

    $monto_pesos=$totalFactura;
    $monto_dolares=0;
    if($cotizacion_usd>0 && $monto_pesos!==null && $monto_pesos!==false){
      $monto_dolares=$monto_pesos/$cotizacion_usd;
    }

    $detalle = isset($itemsSan[0]['descripcion']) ? $itemsSan[0]['descripcion'] : '';
    $sql = "INSERT INTO cobros (id_cliente,id_emisor,fecha_factura,fecha_cobro,monto_pesos,cotizacion_usd,monto_dolares,detalle,id_usuario,id_factura) VALUES (?,?,?,?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute(array($id_cliente,$id_emisor,$_POST['fecha_factura'],$fecha_cobro,$monto_pesos,$cotizacion_usd,$monto_dolares,$detalle,$_SESSION["user"]['id'],$idFactura));

    $idCobro = $pdo->lastInsertId();
    if (!$idFactura) {
      $qDetalle = $pdo->prepare('INSERT INTO cobro_detalle (id_cobro,descripcion,precio,cantidad) VALUES (?,?,?,?)');
      foreach ($itemsSan as $item) {
        $qDetalle->execute(array($idCobro,$item['descripcion'],$item['precio'],$item['cantidad']));
      }
    }
    $pdo->commit();

    Database::disconnect();

    if($idFactura){
      require_once 'informarFacturaARCA.php';
      $resultadoArca = informarFacturaARCA($idFactura);
      if(stripos($resultadoArca,'Error') !== false){
        $_SESSION['mensaje_nc'] = 'El cobro se guardo, pero la factura no se autorizo. '.$resultadoArca;
        header('Location: modificarCobro.php?id='.(int)$idCobro, true, 303);
        exit;
      }
    }

    header("Location: listarCobros.php");
  }catch(Exception $e){
    if($pdo->inTransaction()){
      $pdo->rollBack();
    }
    die('Error al procesar el cobro: '.$e->getMessage());
  }
}
// Leer el ambiente para mostrarlo despues de procesar el cobro.
require 'config_facturacion_electronica.php';
$hoy=date("Y-m-d")?>
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
                      <li class="breadcrumb-item">Nuevo Cobro</li>
                    </ol>
                  </div>
                </div>
                <!-- Bookmark Start-->
                <div class="col-2">
                  <div class="bookmark pull-right">
                    <ul>
                      <li><a  target="_blank" data-container="body" data-toggle="popover" data-placement="top" title="" data-original-title="<?php echo date('d-m-Y');?>"><i data-feather="calendar"></i></a></li>
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
                    <h5>Nuevo Cobro</h5>
                  </div>
				          <form class="form theme-form" role="form" method="post" action="nuevoCobro.php">
                    <div class="card-body">
                      <?php if ((int)$homologacion === 1) { ?>
                        <div class="alert alert-warning" role="alert">
                          <strong>Modo homologaci&oacute;n activo</strong><br>
                          Si genera una factura, se emitir&aacute; en el entorno de pruebas de ARCA y no tendr&aacute; validez fiscal.
                        </div>
                      <?php } ?>
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Emisor</label>
                            <div class="col-sm-3">
                              <select name="id_emisor" id="id_emisor" class="form-control form-control-sm filtraTabla selectpicker multiselect w-100" data-live-search="true">
                                <option value="">Sin emisor</option><?php
                                $pdo = Database::connect();
                                $sql = "SELECT id, razon_social, cuit FROM emisores WHERE activo = 1 ORDER BY razon_social";
                                foreach ($pdo->query($sql) as $row) {?>
                                  <option value="<?=$row["id"]?>"><?=$row["razon_social"]?> (<?=$row["cuit"]?>)</option><?php
                                }
                                Database::disconnect();?>
                              </select>
                              <small id="emisor_help" class="text-danger" style="display:none;">Debe seleccionar un emisor para generar factura.</small>
                            </div>
                            <label class="col-sm-3 col-form-label">Fecha factura</label>
                            <div class="col-sm-3"><input name="fecha_factura" type="date" class="form-control" value="<?=$hoy?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cliente</label>
                            <div class="col-sm-3">
                              <select name="id_cliente" class="form-control form-control-sm filtraTabla selectpicker multiselect w-100" data-live-search="true" required>
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $sql = "SELECT c.id AS id_cliente,c.nombre,c.pide_factura,c.cuit,c.id_cond_fiscal,c.cond_fiscal FROM clientes c WHERE c.activo = 1";
                                foreach ($pdo->query($sql) as $row) {?>
                                  <option value="<?=$row["id_cliente"]?>" data-pide-factura="<?=$row["pide_factura"]?>" data-fiscal="<?=htmlspecialchars(json_encode(analizarClienteFacturacion($row)), ENT_QUOTES, 'UTF-8')?>"><?=$row["nombre"]?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                            <label class="col-sm-3 col-form-label" for="generar_factura">Generar factura</label>
                            <div class="col-sm-3"><input type="checkbox" name="generar_factura" id="generar_factura"></div>
                          </div>
                          <div id="cliente_facturacion_aviso" class="alert" role="status" aria-live="polite" style="display:none;"></div>
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
                            <tbody id="detalles"></tbody>
                            <tfoot>
                              <tr id="monto_factura_preview_row">
                                <td colspan="3" class="text-right align-middle">
                                  <label class="monto-factura-label">Monto factura</label>
                                  <input type="hidden" name="total_factura" id="total_factura" value="0">
                                </td>
                                <td>
                                  <input id="monto_factura_preview" type="text" class="form-control monto-factura-preview text-right" value="" readonly tabindex="-1">
                                </td>
                                <td class="acciones-cell">
                                  <button type="button" class="btn btn-secondary btn-sm" id="add_item">Agregar ítem</button>
                                </td>
                              </tr>
                            </tfoot>
                          </table>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha de cobro</label>
                            <div class="col-sm-3"><input name="fecha_cobro" type="date" class="form-control"></div>
                            <label class="col-sm-3 col-form-label">Cotizacion USD</label>
                            <div class="col-sm-3"><input name="cotizacion_usd" type="number" maxlength="99" class="form-control" value=""></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
						            <a href="listarCobros.php" class="btn btn-light">Volver</a>
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
          let itemIndex = $('#detalles tr').length;
          function syncFacturaRules(){
            var facturaActiva = $('#generar_factura').is(':checked');
            if($('#detalles tr').length===0){addRow();}
            $('#id_emisor').prop('required',facturaActiva);
            $('input[name="fecha_factura"]').prop('required',facturaActiva);
            $('#emisor_help').toggle(facturaActiva && !$('#id_emisor').val());
            $('input[name*="[descripcion]"], .precio').prop('required',true);
            $('#add_item').prop('disabled',!facturaActiva);
            $('input[name*="[cantidad]"]').prop('readonly',!facturaActiva);
            if(!facturaActiva){
              $('input[name*="[cantidad]"]').val(1);
            }
            calcTotal();
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
              '<td><input type="number" step="10" name="items['+idx+'][precio]" class="form-control precio" required></td>'+
              '<td><input type="number" name="items['+idx+'][cantidad]" class="form-control cantidad" value="1" step="0.1" required'+cantidadReadonly+'></td>'+
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
            if($(this).is(':checked')){
              if($('#detalles tr').length===0){addRow();}
              calcTotal();
              $('#detalles tr').find('.cantidad').each(function(){
                $(this).prop('readonly',false);
              });
            }else{
              $('#detalles tr').find('.cantidad').each(function(){
                $(this).val(1).prop('readonly',true);
              });
            }
            syncFacturaRules();
          });
          $('#id_emisor').on('changed.bs.select change',function(){
            syncFacturaRules();
          });
          $('#add_item').on('click',function(){
            addRow();
          });
          $('#detalles').on('click','.remove',function(){
            $(this).closest('tr').remove();
            syncFacturaRules();
          });
          $('#detalles').on('input','.precio, .cantidad',function(){
            calcTotal();
          });
          $('select[name="id_cliente"]').on('changed.bs.select', function(){
            var pideFactura = $('option:selected', this).data('pide-factura');
            $('#generar_factura').prop('checked', pideFactura==1).trigger('change');
          });
          $('form.theme-form').on('submit',function(){
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
        });
  </script>
  </body>
  </html>
