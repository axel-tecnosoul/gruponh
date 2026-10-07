<?php
ini_set("display_errors",1);
ini_set("display_startup_errors",1);
error_reporting(E_ALL);
require("config.php");
if(empty($_SESSION['user'])){
  header("Location: index.php");
  die("Redirecting to index.php"); 
}
require 'database.php';
include 'vendor/afip/Afip.php';
include 'config_facturacion_electronica.php';//poner $homologacion=1 para facturar en modo homologacion. Retorna $aInitializeAFIP.

if ( !empty($_POST)) {
  $facturacion=$_POST["facturacion"];
  
  if($facturacion!="Sin factura"){
    $afip = new Afip($aInitializeAFIP);
    $server_status = (array) $afip->ElectronicBilling->GetServerStatus();
    //var_dump($server_status);
    //$server_status["AppServer"]="Error";
    //$server_status["DbServer"]="Error2";
    //$server_status["AuthServer"]="Error3";
    $afip_ok=0;
    foreach($server_status as $key => $value){
      if($key!="DbServer"){//este parametro no lo contemplamos porque casi siempre está en NO y funciona igual
        if($value!="OK"){
          if($afip_ok==0){
            echo "<br>AFIP informa los siguientes errores:<br>";
          }
          $afip_ok++;
          echo "<b>".$key.":</b> ".$value."<br>";
        }
      }
    }
    if($afip_ok>0){
      die("Actualiza la página para volver a intentarlo o vuelve mas tarde.");
    }
  }
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;
  $aDebug=[];

  $pdo->beginTransaction();
  
  if ($modoDebug==1) {
    var_dump($_POST);
  }

  //$id_cliente=($_POST['id_cliente']) ?: NULL;
  
  // Calcular el total solo con productos que tienen cantidad > 0
  $total = 0;
  foreach ($_POST['id_producto'] as $key => $id_producto) {
    $cantidad = $_POST['cantidad'][$key];
    if($cantidad > 0){
      $subtotal = $_POST['subtotal'][$key];
      $total += $subtotal;
    }
  }
  
  $sql = "INSERT INTO ventas (fecha_venta, id_cliente, facturacion, total, modalidad_pago, observaciones, id_usuario, anulada) VALUES (?,?,?,?,?,?,?,0)";
  $q = $pdo->prepare($sql);
  $params=array($_POST['fecha'],$_POST['id_cliente'],$facturacion,$total,$_POST["modalidad_pago"],$_POST['observaciones'],$_SESSION['user']['id']);
  $q->execute($params);
  $idVenta = $pdo->lastInsertId();

  $aDebug[]=[
    "consulta"=>$sql,
    "params"=>$params,
    "afe"=>$q->rowCount(),
  ];

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }
  
  //$cantPrendas = count($_POST["id_producto"]);

  $aProductos=[];

  $cantProdOK=0;
  foreach ($_POST['id_producto'] as $key => $id_producto) {
    $cantidad = $_POST['cantidad'][$key];

    if($cantidad>0){
      
      $subtotal = $_POST['subtotal'][$key];
      $precio = $_POST['precio'][$key];
      $iva = $_POST['iva'][$key];

      $aProductos[]=[
        "id_producto"=>$id_producto,
        "cantidad"=>$cantidad,
        "precio"=>$precio,
        "subtotal"=>$subtotal,
        "iva"=>$iva,
      ];

      if($subtotal==$cantidad*$precio){
        //echo "el subtotal coicide<br><br>";
      }

      $sql = "INSERT INTO ventas_detalle (id_venta, id_producto, cantidad, precio, iva, subtotal) VALUES (?,?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      //$q->execute(array($idVenta,$id_producto,$cantidad,$precio,$subtotal,$modalidad,$pagado));
      $params=array($idVenta,$id_producto,$cantidad,$precio,$iva,$subtotal);
      $q->execute($params);
      $afe=$q->rowCount();

      if($afe==1){
        $cantProdOK++;
      }

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      $aDebug[]=[
        "consulta"=>$sql,
        "params"=>$params,
        "afe"=>$q->rowCount(),
      ];

      $sql = "UPDATE productos SET precio = ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $params=array($precio,$id_producto);
      $q->execute($params);

      $aDebug[]=[
        "consulta"=>$sql,
        "params"=>$params,
        "afe"=>$q->rowCount(),
      ];

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }
    
  }


  if ($modoDebug==1) {
    var_dump($aProductos);
    echo "cantProdOK!=count(aProductos)<br>";
    echo $cantProdOK."!=".count($aProductos)."<br>";
    var_dump($cantProdOK!=count($aProductos));
    echo "<br><br>";
  }

  if($cantProdOK!=count($aProductos)){
    $pdo->rollback();
    var_dump($aDebug);
    die("Ha ocurrido un error al cargar los productos de la venta");
  }
  
  if($facturacion!="Sin factura"){

    $afip = new Afip($aInitializeAFIP);
    $server_status = $afip->ElectronicBilling->GetServerStatus();
    /*echo 'Este es el estado del servidor:';
    var_dump($server_status);*/

    // Arreglo auxiliar para ir almacenando las sumas de los elementos con el mismo valor de iva
    $sumasPorIva = array();
    // Usamos array_reduce() para iterar sobre los elementos del arreglo original y agruparlos por el valor de iva
    $agrupadosPorIva = array_reduce($aProductos, function($acumulador, $elemento) use(&$sumasPorIva) {
        $iva = $elemento['iva'];
        if (!isset($acumulador[$iva])) {
            $acumulador[$iva] = array();
            $sumasPorIva[$iva] = 0;
        }
        $acumulador[$iva][] = $elemento;
        $sumasPorIva[$iva] += $elemento['subtotal'];
        return $acumulador;
    }, array());
    /*echo "Arreglo agrupado por iva:\n";
    var_dump($agrupadosPorIva);*/

    if ($modoDebug==1) {
      var_dump($sumasPorIva);
    }
    
    $sql = "SELECT valor FROM parametros WHERE id = 6 ";
    $q = $pdo->prepare($sql);
    $q->execute();
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $monto_maximo_factura_consumidor_final=$data["valor"];

    $aFacturas = array();

    $punto_venta=2;
    $tipo_comprobante=1;//1 -> Factura A
    $tipo_comprobante_bbdd="A";
    $DocTipo=80;
    $DocNro=$_POST["cuit"];

    $id_cond_fiscal="";
    $cond_fiscal="";
    if(isset($_POST["id_cond_fiscal"])){
      $id_cond_fiscal=$_POST["id_cond_fiscal"];
    }
    if(isset($_POST["cond_fiscal"])){
      $cond_fiscal=$_POST["cond_fiscal"];
    }

    if($facturacion=="Consumidor Final"){

      $tipo_comprobante=6;//6 -> Factura B
      $tipo_comprobante_bbdd="B";
      $DocTipo=99;
      $DocNro=0;

      if ($modoDebug==1) {
        echo "total>monto_maximo_factura_consumidor_final<br>";
        echo $total.">".$monto_maximo_factura_consumidor_final."<br>";
        var_dump($total>$monto_maximo_factura_consumidor_final);
        echo "<br><br>";
      }

      if($total>$monto_maximo_factura_consumidor_final){

        foreach ($sumasPorIva as $iva => $monto) {
          $cant_facturas=intval($monto/$monto_maximo_factura_consumidor_final);
          $monto_ultima_factura=$monto-($cant_facturas*$monto_maximo_factura_consumidor_final);
          
          /*echo "generar $cant_facturas facturas al $iva% de $monto_maximo_factura_consumidor_final y 1 factura de $monto_ultima_factura<br>";
          echo $cant_facturas."*".$monto_maximo_factura_consumidor_final."+".$monto_ultima_factura."=".(($cant_facturas*$monto_maximo_factura_consumidor_final)+$monto_ultima_factura)."<br>";
          echo "monto=".$monto."<hr>";*/
  
          //completamos el array de facturas para generar $cant_facturas por el valor $monto_maximo_factura_consumidor_final
          for ($i=0; $i < $cant_facturas; $i++) { 
            $aFacturas[]=[
              $iva=>$monto_maximo_factura_consumidor_final,
            ];
          }
          //si queda un saldo según el total del pedido lo agregamos al final del array de facturas
          if($monto_ultima_factura>0){
            $aFacturas[]=[
              $iva=>$monto_ultima_factura,
            ];
          }
        }
      }else{
        $aFacturas[]=$sumasPorIva;
      }
    }else{
      //para la factura A el array de facturas tiene 1 solo valor y está discriminado por IVA
      $aFacturas[]=$sumasPorIva;
    }

    if ($modoDebug==1) {
      var_dump($aFacturas);
    }

    $cantFacturasOK=0;
    $porcentaje_ingresos_brutos=0;
    $porcentaje_percepcion_iva=0;
    if($tipo_comprobante_bbdd=="A"){
      $porcentaje_ingresos_brutos=3.31;
      //$porcentaje_percepcion_iva=3;
    }

    $monto_tributos=0;
    $aIngresosBrutos=[];
    foreach ($aFacturas as $factura) {

      $ImpTotal=array_sum($factura);
      
      $aIva=[];
      $sumaIVA=$sumaNuevoIVA=0;
      $ImpNeto=$ImpTotal;
      $ImpNeto=0;
      
      //declaramos los tipos de IVA de la factura
      $sumaBaseImp=0;
      foreach ($factura as $iva => $monto) {
        $IdIVA=4;
        if($iva==21){
          $IdIVA=5;
        }

        $total_impuestos=$iva+$porcentaje_ingresos_brutos+$porcentaje_percepcion_iva;

        //echo $monto."<br>";
        //echo $total_impuestos."<br>";
        //$baseImponible=round($monto/(($total_impuestos/100)+1),2);//1.21
        $baseImponible=round($monto/(($iva/100)+1),2);//1.21
        //var_dump($baseImponible);
        $sumaBaseImp+=$baseImponible;

        $ImpIVA=round(($baseImponible * $iva) / 100, 2);
        $nuevoImpIVA=round(($baseImponible * $iva) / 100, 2);
        /*var_dump($ImpIVA);
        var_dump($nuevoImpIVA);*/

        $sumaIVA+=$ImpIVA;
        $sumaNuevoIVA+=$nuevoImpIVA;

        $aIva[]=array(
          'Id'=> $IdIVA, // Id del tipo de IVA (5 para 21%)(ver tipos disponibles) 
          'BaseImp'=> number_format($baseImponible,2,".",""),//100, // Base imponible -> ES IGUAL A ImpNeto?
          'Importe'=> number_format($ImpIVA,2,".",""),//21 // Importe -> ES IGUAL A ImpIVA?
        );

        $monto_percepcion_iva=$monto_ingresos_brutos=0;
        if($tipo_comprobante_bbdd=="A"){

          //$baseImponible = $baseImponible / ((100 + $porcentaje_ingresos_brutos + $porcentaje_percepcion_iva) / 100);
          $baseImponible=$sumaBaseImp;
          $monto_ingresos_brutos = round($baseImponible * $porcentaje_ingresos_brutos / 100, 2);
          //$monto_percepcion_iva = round($baseImponible * $porcentaje_percepcion_iva / 100, 2);

          $monto_tributos=$monto_ingresos_brutos+$monto_percepcion_iva;
          
          $aIngresosBrutos=[//array( // (Opcional) Tributos asociados al comprobante
            [
              'Id' => 2, // Id del tipo de tributo (ver tipos disponibles) 
              'Desc' => 'Ingresos Brutos', // (Opcional) Descripcion
              'BaseImp' => $baseImponible, // Base imponible para el tributo
              'Alic' => $porcentaje_ingresos_brutos, // Alícuota
              'Importe' => $monto_ingresos_brutos // Importe del tributo
            ]/*,[
              'Id' => 1, // Id del tipo de tributo (ver tipos disponibles) 
              'Desc' => 'Percepcion del IVA', // (Opcional) Descripcion
              'BaseImp' => $baseImponible, // Base imponible para el tributo
              'Alic' => $porcentaje_percepcion_iva, // Alícuota
              'Importe' => $monto_percepcion_iva // Importe del tributo
            ]*/
          ];
        }
      }

      //echo $ImpTotal."<br>";
      //echo $monto_tributos."<br>";
      $ImpTotal+=$monto_tributos;
      //echo $ImpTotal."<br>";

      /*var_dump($sumaIVA);
      var_dump($sumaNuevoIVA);*/

      $ImpNeto=number_format($ImpNeto,2,".","");
      $sumaIVA=number_format($sumaIVA,2,".","");
      $monto_tributos=number_format($monto_tributos,2,".","");

      if($tipo_comprobante_bbdd=="A"){
        $monto_ingresos_brutos=number_format($monto_ingresos_brutos,2,".","");
        $monto_percepcion_iva=number_format($monto_percepcion_iva,2,".","");
        //$aIngresosBrutos=[];
        //$aIngresosBrutos['Tributos']=[
      }

      //$fecha=date('Y-m-d');
      $fecha=$_POST["fecha"];

      //$ImpNeto=$ImpTotal-$sumaIVA-$monto_tributos;
      $ImpNeto=$sumaBaseImp;
      $data = array(
        'CantReg' 	=> 1,  // Cantidad de comprobantes a registrar
        'PtoVta' 	=> $punto_venta,  // Punto de venta
        'CbteTipo' 	=> $tipo_comprobante,  // Tipo de comprobante (ver tipos disponibles) 
        'Concepto' 	=> 1,  // Concepto del Comprobante: (1)Productos, (2)Servicios, (3)Productos y Servicios
        'DocTipo' 	=> $DocTipo, // Tipo de documento del comprador (99 consumidor final, ver tipos disponibles). Para comprobantes clase A y M el campo DocTipo debe ser igual a 80 (CUIT)
        'DocNro' 	=> $DocNro,  // Número de documento del comprador (0 consumidor final)
        'CbteDesde' 	=> 2,  // Número de comprobante o numero del primer comprobante en caso de ser mas de uno
        'CbteHasta' 	=> 2,  // Número de comprobante o numero del último comprobante en caso de ser mas de uno
        'CbteFch' 	=> intval(date('Ymd',strtotime($fecha))), // (Opcional) Fecha del comprobante (yyyymmdd) o fecha actual si es nulo
        'ImpTotal' 	=> number_format($ImpTotal,2,".",""),//121, // Importe total del comprobante
        'ImpTotConc' 	=> 0,   // Importe neto no gravado
        'ImpNeto' 	=> $ImpNeto,//100, // Importe neto gravado
        'ImpOpEx' 	=> 0,   // Importe exento de IVA
        'ImpIVA' 	=> $sumaIVA,//21,  //Importe total de IVA
        //'ImpTrib' 	=> 0,   //Importe total de tributos
        'ImpTrib' 	=> $monto_tributos,   //Importe total de tributos
        'MonId' 	=> 'PES', //Tipo de moneda usada en el comprobante (ver tipos disponibles)('PES' para pesos argentinos) 
        'MonCotiz' 	=> 1,     // Cotización de la moneda usada (1 para pesos argentinos)  
        'Iva'=>$aIva, // (Opcional) Alícuotas asociadas al comprobante
        "CondicionIVAReceptorId"=>$id_cond_fiscal // ID 6 = Responsable Monotributo 
      );

      if($tipo_comprobante_bbdd=="A"){
        $data['Tributos']=$aIngresosBrutos;
      }

      if ($modoDebug==1) {
        var_dump($data);
      }
      
      try {
        $datos_enviados_arca = json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($datos_enviados_arca === false) {
          throw new Exception('No se pudieron serializar los datos para ARCA: ' . json_last_error_msg());
        }

        $res = $afip->ElectronicBilling->CreateNextVoucher($data);
      }catch(Exception $e){
        $pdo->rollBack();
        if($e->getCode()==10243){
          die("Ha ocurrido un error con la condicion fiscal informada. Por favor seleccione una diferente para el cliente.<br><input type='button' value='Volver' onclick='history.back()'>");
        }else{
          die("Ha ocurrido un error con la facturación electrónica:<br><br>".$e->getMessage()."<br><input type='button' value='Volver' onclick='history.back()'>");
        }
      }

      $estado="E";
      $CAE=NULL;
      $CAEFchVto=NULL;
      $voucher_number=NULL;
      if(isset($res['CAE'])){
        $estado="A";
        $CAE=$res['CAE'];//CAE asignado el comprobante
        $CAEFchVto=$res['CAEFchVto'];//Fecha de vencimiento del CAE (yyyy-mm-dd)
        $voucher_number=$res['voucher_number'];//Número asignado al comprobante
        //var_dump($res);
      }
      
      if ($modoDebug==1) {
        var_dump($res);
        var_dump($CAE);
        var_dump($CAEFchVto);
        var_dump($voucher_number);
      }

      /*$sql = "INSERT INTO facturas (id_venta,fecha_cbte,tipo_doc,dni,tipo_comprobante,total_bruto,total_neto,id_iva,total_iva,alicuota_ingresos_brutos,total_ingresos_brutos,punto_venta,numero_cbte,estado,cae,fecha_vto_cae,id_cbte_relacionado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)";
      $q = $pdo->prepare($sql);
      $params=array($idVenta,$fecha,$DocTipo,$DocNro,$tipo_comprobante_bbdd,$ImpTotal,$ImpNeto,$IdIVA,$sumaIVA,$porcentaje_ingresos_brutos,$monto_ingresos_brutos,$punto_venta,$voucher_number,$estado,$CAE,$CAEFchVto);*/
      $sql = "INSERT INTO facturas (id_venta,fecha_cbte,tipo_doc,dni,condicion_iva_receptor_id,condicion_iva_receptor,tipo_comprobante,total_bruto,total_neto,id_iva,total_iva,alicuota_ingresos_brutos,total_ingresos_brutos,alicuota_percepcion_iva,total_percepcion_iva,punto_venta,numero_cbte,estado,cae,fecha_vto_cae,id_cbte_relacionado,datos_enviados_arca) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,?)";
      $q = $pdo->prepare($sql);
      $params=array($idVenta,$fecha,$DocTipo,$DocNro,$id_cond_fiscal,$cond_fiscal,$tipo_comprobante_bbdd,$ImpTotal,$ImpNeto,$IdIVA,$sumaIVA,$porcentaje_ingresos_brutos,$monto_ingresos_brutos,$porcentaje_percepcion_iva,$monto_percepcion_iva,$punto_venta,$voucher_number,$estado,$CAE,$CAEFchVto,$datos_enviados_arca);
      $q->execute($params);
      $afe=$q->rowCount();

      if($afe==1){
        $cantFacturasOK++;
      }

      $aDebug[]=[
        "consulta"=>$sql,
        "params"=>$params,
        "afe"=>$q->rowCount(),
      ];

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }

    if($cantFacturasOK!=count($aFacturas)){
      $pdo->rollBack();
      var_dump($aDebug);
      die("Ha ocurrido un error con las factuas");
    }

  }

  if ($modoDebug==1) {
    $pdo->rollBack();
    die();
  }

  $pdo->commit();
  Database::disconnect();
  
  header("Location: listarVentas.php");
}
$id_perfil=$_SESSION["user"]["id_perfil"];?>
<!DOCTYPE html>
<html lang="en">
  <head><?php
    include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <style>
      .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0;}
    </style>
  </head>
  <body class="light-only">
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper"><?php
      include('header.php');?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper"><?php
        include('menu.php');?>
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
                      <li class="breadcrumb-item">Nueva Venta</li>
                    </ol>
                  </div>
                </div>
                <!-- Bookmark Start-->
                <div class="col-2">
                  <div class="bookmark pull-right">
                    <ul>
                      <li><a target="_blank" data-container="body" data-toggle="popover" data-placement="top" title="" data-original-title="<?php echo date('d-m-Y');?>"><i data-feather="calendar"></i></a></li>
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
                    <h5>Nueva Venta</h5>
                    <h6><?php
                      $disabledFacturaElectronica="";
                      if (extension_loaded('soap')) {
                        try{
                          $afip = new Afip($aInitializeAFIP);
                          $server_status = (array) $afip->ElectronicBilling->GetServerStatus();
                          //var_dump($server_status);
                          //$server_status["AppServer"]="Error";
                          //$server_status["DbServer"]="Error2";
                          //$server_status["AuthServer"]="Error3";
                          $ok=0;
                          foreach($server_status as $key => $value){
                            if($key!="DbServer"){//este parametro no lo contemplamos porque casi siempre está en NO y funciona igual
                              if($value!="OK"){
                                $disabledFacturaElectronica="disabled";
                                if($ok==0){
                                  echo "<br>AFIP informa los siguientes errores:<br>";
                                }
                                $ok++;
                                echo "<b>".$key.":</b> ".$value."<br>";
                              }
                            }
                          }
                        } catch (SoapFault $e) {
                          // Captura específicamente una excepción SOAP Fault
                          // Muestra un mensaje personalizado en lugar de mostrar el error real
                          echo "<br>Se produjo un error al comunicarse con el servidor de AFIP. Por favor, inténtelo de nuevo más tarde.";
                          $disabledFacturaElectronica="disabled";
                        } catch (Exception $e) {
                          // Captura otras excepciones que puedan ocurrir durante la ejecución del código
                          echo "<br>Se produjo un error inesperado. Por favor, póngase en contacto con el soporte técnico.";
                          $disabledFacturaElectronica="disabled";
                        }
                      } else {
                        echo "<br>SOAP no está habilitado en tu servidor. Aun no podemos generar Facturas Electronicas y te pedimos disculas por ello. Este mensaje desaparecerá automaticamente cuando SOAP haya sido habilitado";
                        $disabledFacturaElectronica="disabled";
                      }
                      $checkdFacturaConsumidorFinal="checked";
                      $checkdSinFactura="";
                      if($disabledFacturaElectronica=="disabled"){
                        $checkdFacturaConsumidorFinal="";
                        $checkdSinFactura="checked";
                      }?>
                    </h6>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="nuevaVenta.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row"><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT MAX(fecha_cbte) AS fecha_ult_cbte FROM facturas";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            $fila = $q->fetch(PDO::FETCH_ASSOC);

                            $hace_cinco_dias=date("Y-m-d",strtotime(date("Y-m-d")." -5 days"));
                            
                            $ultima_fecha_disponible_para_facturas = max($fila["fecha_ult_cbte"], $hace_cinco_dias);

                            Database::disconnect();
                            ?>
                            <label class="col-sm-3 col-form-label">Fecha</label>
                            <div class="col-sm-9"><input type="date" name="fecha" id="fecha" class="form-control form-control-sm" value="<?=date("Y-m-d")?>" min="<?=$ultima_fecha_disponible_para_facturas?>" required></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cliente</label>
                            <div class="col-sm-9">
                              <select name="id_cliente" id="id_cliente" class="js-example-basic-single col-sm-12" required>
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, razon_social, cuit, cond_fiscal, id_cond_fiscal FROM clientes WHERE activo = 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  $cuit="";
                                  if($fila["cuit"]){
                                    $cuit=" (".$fila["cuit"].")";
                                  }?>
                                  <option value='<?=$fila['id']?>' data-cuit='<?=$fila['cuit']?>' data-cond_fiscal='<?=$fila['cond_fiscal']?>' data-id_cond_fiscal='<?=$fila['id_cond_fiscal']?>'><?=$fila['razon_social']?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>

                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Condicion frente al IVA</label>
                            <div class="col-sm-9">
                              <input type="hidden" name="id_cond_fiscal" id="id_cond_fiscal" value="5">
                              <input type="text" name="cond_fiscal" class="form-control form-control-sm" disabled id="lbl_cond_fiscal" value="Consumidor Final">
                            </div>
                          </div>

                          <!-- <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipo de comprobante</label>
                            <div class="col-sm-9">
                            <select name="tipo_comprobante" id="tipo_comprobante" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option>
                                <option value="B" selected>Factura B</option>
                              </select>
                            </div>
                          </div> -->

                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Facturar como:</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="edo-ani">
                                <input class="radio_animated" value="Consumidor Final" <?=$checkdFacturaConsumidorFinal?> required id="edo-ani" type="radio" name="facturacion" <?=$disabledFacturaElectronica?>>
                                <label for="edo-ani">Consumidor Final</label>
                              </label>
                              <label class="d-block" for="edo-ani1">
                                <input class="radio_animated" value="Cliente" required id="edo-ani1" type="radio" name="facturacion" <?=$disabledFacturaElectronica?>>
                                <label for="edo-ani1">Cliente <span id="lbl_cuit_cliente"></span></label>
                              </label>
                              <input type="hidden" name="cuit" id="hidden_cuit_cliente">
                              <label class="d-block" for="edo-ani2">
                                <input class="radio_animated" value="Sin factura" <?=$checkdSinFactura?> required id="edo-ani2" type="radio" name="facturacion">
                                <label for="edo-ani2">Sin factura <span id="lbl_cuit_cliente"></span></label>
                              </label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Productos</label>
                          </div>
                          <div class="form-group row">
                            <div class="col-sm-12" id="tabla_productos">
                              <table class="table table-sm display">
                                <thead>
                                  <tr>
                                    <th>Descripcion</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                  </tr>
                                </thead>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT id, descripcion,precio,iva FROM productos p WHERE activo = 1 ORDER BY (SELECT COUNT(*) FROM ventas_detalle vd WHERE vd.id_producto=p.id) DESC";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                    <tr>
                                      <td><?=$fila["descripcion"]?></td>
                                      <td>
                                        <input type="number" name="precio[]" class="form-control form-control-sm precio" value="<?=$fila["precio"]?>" min="0">
                                        <input type="hidden" name="id_producto[]" value="<?=$fila["id"]?>">
                                        <input type="hidden" name="iva[]" value="<?=$fila["iva"]?>">
                                      </td>
                                      <td><input type="number" name="cantidad[]" class="form-control form-control-sm cantidad" min="0" step="1"></td>
                                      <td><input type="number" name="subtotal[]" class="form-control form-control-sm subtotal" readonly tabindex="-1"></td>
                                    </tr><?php
                                  }
                                  Database::disconnect();?>
                                </tbody>
                              </table>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Total</label>
                            <div class="col-sm-9"><label id="total_compra">$ 0</label></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Modalidad de pago</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="modalidad_cta_cte">
                                <input class="radio_animated" value="Cuenta Corriente" checked required id="modalidad_cta_cte" type="radio" name="modalidad_pago"><label for="modalidad_cta_cte">Cuenta Corriente</label>
                              </label>
                              <label class="d-block" for="modalidad_contado">
                                <input class="radio_animated" value="Contado" required id="modalidad_contado" type="radio" name="modalidad_pago"><label for="modalidad_contado">Contado</label>
                              </label>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones</label>
                            <div class="col-sm-9"><textarea name="observaciones" id="observaciones" class="form-control"></textarea></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit" id="btnSubmit">Crear</button>
                        <a href="listarVentas.php" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start--><?php 
        include("footer.php"); ?>
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
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
	  <script>
      $(document).ready(function() {

        // Prevenir cambios con la rueda del mouse en inputs number
        $(document).on('wheel', 'input[type=number]', function(e) {
          $(this).blur();
        });
        
        // Prevenir cambios con la rueda del mouse cuando el input está enfocado
        $(document).on('focus', 'input[type=number]', function() {
          $(this).on('wheel.number', function(e) {
            e.preventDefault();
          });
        });
        
        $(document).on('blur', 'input[type=number]', function() {
          $(this).off('wheel.number');
        });

        $('#dataTables-example666').DataTable({
          stateSave: true,
          responsive: true,
          processing: true,
          scrollY: false,
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
        })

        $("#id_cliente").on("change",function(){
          let lbl_cuit=""
          let cuit=0;
          console.log(this.value);
          if(this.value!=""){
            cuit=$("#id_cliente option[value='"+this.value+"']").data("cuit")
            lbl_cuit="("+cuit+")";
          }
          console.log(cuit);
          $("#lbl_cuit_cliente").html(lbl_cuit)
          $("#hidden_cuit_cliente").val(cuit)

          cond_fiscal=$("#id_cliente option[value='"+this.value+"']").data("cond_fiscal")
          id_cond_fiscal=$("#id_cliente option[value='"+this.value+"']").data("id_cond_fiscal")
          console.log(cond_fiscal,id_cond_fiscal)

          $("#id_cond_fiscal").val(id_cond_fiscal)
          $("#lbl_cond_fiscal").val(cond_fiscal)

        })

        $("form").on("submit",function(e){
          e.preventDefault();
          if($('#tabla_productos tbody tr').length){
            //console.log("submit");
            let sin_cantidad=1;
            //console.log($("input[type='number'][name='cantidad[]'"));
            $("input[type='number'][name='cantidad[]'").each(function(){
              if(this.value>0){
                sin_cantidad=0;
              }
            });
            if(sin_cantidad==1){
              alert("Ingrese la cantidad de al menos 1 producto")
            }else{
              $("#btnSubmit").addClass("disabled")
              this.submit();
              //console.log("submit")
            }
          }else{
            alert("Añada algún producto")
          }
        });

        $(document).on("keyup change",".cantidad, .precio",function(){
          let fila=$(this).closest("tr");
          let cantidad=parseInt(fila.find(".cantidad").val()) || 0;
          let precio=parseInt(fila.find(".precio").val()) || 0;
          
          // Prevenir valores negativos
          if(cantidad < 0) {
            cantidad = 0;
            fila.find(".cantidad").val(0);
          }
          if(precio < 0) {
            precio = 0;
            fila.find(".precio").val(0);
          }
          
          fila.find(".subtotal").val(precio * cantidad);
          actualizarMontoTotal()
        })

      });

      function actualizarMontoTotal(){
        var total=0;
        // Solo sumar productos con cantidad > 0
        $(".cantidad").each(function(index){
          let cantidad = parseInt($(this).val()) || 0;
          if(cantidad > 0) {
            let subtotal = parseInt($(".subtotal").eq(index).val()) || 0;
            total += subtotal;
          }
        })
        $("#total_compra").html(new Intl.NumberFormat('es-AR', {currency: 'ARS', style: 'currency'}).format(total))
      }

		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
		
  </body>
</html>