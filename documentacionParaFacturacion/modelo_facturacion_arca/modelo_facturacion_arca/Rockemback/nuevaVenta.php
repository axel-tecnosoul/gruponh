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
  //$fecha=date('Y-m-d');
  $fecha=$_POST["fecha"];
  
  if($facturacion!="R"){
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
  $total=array_sum($_POST["subtotal"]);
  
  $sql = "INSERT INTO ventas (fecha_venta, id_cliente, facturacion, total, modalidad_pago, observaciones, id_usuario, anulada) VALUES (?,?,?,?,?,?,?,0)";
  $q = $pdo->prepare($sql);
  $params=array($fecha,$_POST['id_cliente'],$facturacion,$total,$_POST["modalidad_pago"],$_POST['observaciones'],$_SESSION['user']['id']);
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
    $precio = $_POST['precio'][$key];

    if($cantidad>0 and $precio>0){
      
      $subtotal = $_POST['subtotal'][$key];
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
  
  if($facturacion!="R"){

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
    
    $monto_maximo_factura_b=$_POST["monto_maximo_factura_b"];

    $aFacturas = array();

    $tipo_comprobante_bbdd=$facturacion;

    $DocTipo=99;
    $DocNro=0;
    if(isset($_POST["dni_cuit"]) and isset($_POST["identificacion"])){
      $identificacion=$_POST["identificacion"];
      $DocNro=$_POST["dni_cuit"];

      if($identificacion=="cuit"){
        $DocTipo=80;
      }elseif($identificacion=="dni"){
        $DocTipo=96;
      }
    }

    $id_cond_fiscal="";
    $cond_fiscal="";
    if(isset($_POST["id_cond_fiscal"])){
      $id_cond_fiscal=$_POST["id_cond_fiscal"];
    }
    if(isset($_POST["cond_fiscal"])){
      $cond_fiscal=$_POST["cond_fiscal"];
    }

    $tipo_comprobante=1;//1 -> Factura A
    if($tipo_comprobante_bbdd=="B"){

      $tipo_comprobante=6;//6 -> Factura B

      if ($modoDebug==1) {
        echo "total>monto_maximo_factura_b<br>";
        echo $total.">".$monto_maximo_factura_b."<br>";
        var_dump($total>$monto_maximo_factura_b);
        echo "<br><br>";
      }

      //if($total>$monto_maximo_factura_b){
      if(isset($_POST["cantidad_facturas"]) and $_POST["cantidad_facturas"]=="varias"){
        //calculamos la cantidad de facturas a generar
        foreach ($sumasPorIva as $iva => $monto) {
          $cant_facturas=intval($monto/$monto_maximo_factura_b);
          $monto_ultima_factura=$monto-($cant_facturas*$monto_maximo_factura_b);
  
          //completamos el array de facturas para generar $cant_facturas por el valor $monto_maximo_factura_b
          for ($i=0; $i < $cant_facturas; $i++) { 
            $aFacturas[]=[
              $iva=>$monto_maximo_factura_b,
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
      echo "Array Facturas";
      var_dump($aFacturas);
    }

    $cantFacturasOK=0;

    //DEFINIMOS LOS TRIBUOS A PAGAR
    $porcentaje_ingresos_brutos=$monto_ingresos_brutos=0;
    $porcentaje_percepcion_iva=$monto_percepcion_iva=0;
    if($tipo_comprobante_bbdd=="A"){
      $porcentaje_ingresos_brutos=3.31;
      //$porcentaje_percepcion_iva=3;
    }

    $monto_tributos=0;
    $aTributos=[];
    foreach ($aFacturas as $factura) {

      $ImpTotal=array_sum($factura);
      
      $aIva=[];
      $sumaIVA=0;
      //$ImpNeto=$ImpTotal;
      $ImpNeto=0;
      
      //declaramos los tipos de IVA de la factura
      $sumaBaseImp=0;
      foreach ($factura as $iva => $monto) {
        /*$IdIVA=4;
        if($iva==21){
          $IdIVA=5;
        }*/

        $IdIVA=$aCodigosAfipIVA[$iva];

        $total_impuestos=$iva+$porcentaje_ingresos_brutos+$porcentaje_percepcion_iva;

        //echo $monto."<br>";
        //echo $total_impuestos."<br>";
        $baseImponible=round($monto/(($iva/100)+1),2);//1.21 0 1.105 segun corresponda
        //var_dump($baseImponible);
        $sumaBaseImp+=$baseImponible;

        $nuevoImpIVA=$ImpIVA=round(($baseImponible * $iva) / 100, 2);
        //$nuevoImpIVA=round(($baseImponible * $iva) / 100, 2);
        /*var_dump($ImpIVA);
        var_dump($nuevoImpIVA);*/

        $sumaIVA+=$ImpIVA;

        $aIva[]=array(
          'Id'=> $IdIVA, // Id del tipo de IVA (5 para 21%)(ver tipos disponibles) 
          'BaseImp'=> number_format($baseImponible,2,".",""),//100, // Base imponible -> ES IGUAL A ImpNeto?
          'Importe'=> number_format($ImpIVA,2,".",""),//21 // Importe -> ES IGUAL A ImpIVA?
        );

        if($tipo_comprobante_bbdd=="A"){

          $aTributos=[];//array( // (Opcional) Tributos asociados al comprobante

          $baseImponible=$sumaBaseImp;
          //var_dump($baseImponible);

          //si hay ingresos brutos lo calculamos
          if($porcentaje_ingresos_brutos>0){

            $monto_ingresos_brutos = round($baseImponible * $porcentaje_ingresos_brutos / 100, 2);
          
            $aTributos[]=[
              'Id' => 2, // Id del tipo de tributo (ver tipos disponibles) 
              'Desc' => 'Ingresos Brutos', // (Opcional) Descripcion
              'BaseImp' => $baseImponible, // Base imponible para el tributo
              'Alic' => $porcentaje_ingresos_brutos, // Alícuota
              'Importe' => $monto_ingresos_brutos // Importe del tributo
            ];
          }

          //si hay percepcion del iva lo calculamos
          if($porcentaje_percepcion_iva>0){

            $monto_percepcion_iva = round($baseImponible * $porcentaje_percepcion_iva / 100, 2);

            $aTributos[]=[
              'Id' => 1, // Id del tipo de tributo (ver tipos disponibles) 
              'Desc' => 'Percepcion del IVA', // (Opcional) Descripcion
              'BaseImp' => $baseImponible, // Base imponible para el tributo
              'Alic' => $porcentaje_percepcion_iva, // Alícuota
              'Importe' => $monto_percepcion_iva // Importe del tributo
            ];
          }

          $monto_tributos=$monto_ingresos_brutos+$monto_percepcion_iva;

        }
      }

      $ImpTotal+=$monto_tributos;
      //echo $ImpTotal."<br>";

      $ImpNeto=number_format($ImpNeto,2,".","");
      $sumaIVA=number_format($sumaIVA,2,".","");
      $monto_tributos=number_format($monto_tributos,2,".","");

      if($tipo_comprobante_bbdd=="A"){
        $monto_ingresos_brutos=number_format($monto_ingresos_brutos,2,".","");
        $monto_percepcion_iva=number_format($monto_percepcion_iva,2,".","");
      }

      //$ImpNeto=$ImpTotal-$sumaIVA-$monto_tributos;
      $ImpNeto=$sumaBaseImp;
      $data = array(
        'CantReg' 	=> 1, // Cantidad de comprobantes a registrar
        'PtoVta' 	=> $punto_venta, // Punto de venta
        'CbteTipo' 	=> $tipo_comprobante, // Tipo de comprobante (ver tipos disponibles) 
        'Concepto' 	=> 1, // Concepto del Comprobante: (1)Productos, (2)Servicios, (3)Productos y Servicios
        'DocTipo' 	=> $DocTipo, // Tipo de documento del comprador (99 consumidor final, ver tipos disponibles). Para comprobantes clase A y M el campo DocTipo debe ser igual a 80 (CUIT)
        'DocNro' 	=> $DocNro, // Número de documento del comprador (0 consumidor final)
        'CbteDesde' 	=> 2, // Número de comprobante o numero del primer comprobante en caso de ser mas de uno
        'CbteHasta' 	=> 2, // Número de comprobante o numero del último comprobante en caso de ser mas de uno
        'CbteFch' 	=> intval(date('Ymd',strtotime($fecha))), // (Opcional) Fecha del comprobante (yyyymmdd) o fecha actual si es nulo
        'ImpTotal' 	=> number_format($ImpTotal,2,".",""),//121, // Importe total del comprobante
        'ImpTotConc' 	=> 0, // Importe neto no gravado
        //'ImpNeto' 	=> $ImpNeto,//100, // Importe neto gravado
        'ImpNeto' 	=> ($ImpNeto),//100, // Importe neto gravado
        'ImpOpEx' 	=> 0, // Importe exento de IVA
        'ImpIVA' 	=> $sumaIVA,//21, //Importe total de IVA
        //'ImpTrib' 	=> 0, //Importe total de tributos
        'ImpTrib' 	=> $monto_tributos, //Importe total de tributos
        'MonId' 	=> 'PES', //Tipo de moneda usada en el comprobante (ver tipos disponibles)('PES' para pesos argentinos) 
        'MonCotiz' 	=> 1, // Cotización de la moneda usada (1 para pesos argentinos)  
        'Iva'=>$aIva, // (Opcional) Alícuotas asociadas al comprobante
        "CondicionIVAReceptorId"=>$id_cond_fiscal // ID 6 = Responsable Monotributo 
      );

      if($tipo_comprobante_bbdd=="A"){
        $data['Tributos']=$aTributos;
      }

      if ($modoDebug==1) {
        var_dump($data);
      }
      
      try{
        //$res = $afip->ElectronicBilling->CreateVoucher($data);
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

      /*$sql = "INSERT INTO facturas (id_venta,fecha_cbte,tipo_doc,dni,tipo_comprobante,total_bruto,total_neto,id_iva,total_iva,alicuota_ingresos_brutos,total_ingresos_brutos,alicuota_percepcion_iva,total_percepcion_iva,punto_venta,numero_cbte,estado,cae,fecha_vto_cae,id_cbte_relacionado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)";
      $q = $pdo->prepare($sql);
      $params=array($idVenta,$fecha,$DocTipo,$DocNro,$tipo_comprobante_bbdd,$ImpTotal,$ImpNeto,$IdIVA,$sumaIVA,$porcentaje_ingresos_brutos,$monto_ingresos_brutos,$porcentaje_percepcion_iva,$monto_percepcion_iva,$punto_venta,$voucher_number,$estado,$CAE,$CAEFchVto);*/
      $sql = "INSERT INTO facturas (id_venta,fecha_cbte,tipo_doc,dni,condicion_iva_receptor_id,condicion_iva_receptor,tipo_comprobante,total_bruto,total_neto,punto_venta,numero_cbte,homologacion,estado,cae,fecha_vto_cae,id_cbte_relacionado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)";
      $q = $pdo->prepare($sql);
      $params=array($idVenta,$fecha,$DocTipo,$DocNro,$id_cond_fiscal,$cond_fiscal,$tipo_comprobante_bbdd,$ImpTotal,$ImpNeto,$punto_venta,$voucher_number,$homologacion,$estado,$CAE,$CAEFchVto);
      $q->execute($params);
      $idFactura = $pdo->lastInsertId();
      $afe=$q->rowCount();

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

      if($afe==1){
        $cantIva=$cantIvaOK=0;
        foreach($aIva as $key => $value){
          $cantIva++;

          $sql = "INSERT INTO factura_iva (id_factura,codigo_afip,base_imponible,importe) VALUES (?,?,?,?)";
          $q = $pdo->prepare($sql);
          $params=array($idFactura,$value["Id"],$value["BaseImp"],$value["Importe"]);
          $q->execute($params);
          $afe=$q->rowCount();

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

          if($afe==1){
            $cantIvaOK++;
          }
        }
        $cantTributos=$cantTributosOK=0;
        foreach($aTributos as $key => $value){
          $cantTributos++;

          $sql = "INSERT INTO factura_tributos (id_factura,codigo_afip,descripcion,base_imponible,alicuota,importe) VALUES (?,?,?,?,?,?)";
          $q = $pdo->prepare($sql);
          $params=array($idFactura,$value["Id"],$value["Desc"],$value["BaseImp"],$value["Alic"],$value["Importe"]);
          $q->execute($params);
          $afe=$q->rowCount();

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
          if($afe==1){
            $cantTributosOK++;
          }
        }

        if($cantIva==$cantIvaOK and $cantTributos==$cantTributosOK){
          $cantFacturasOK++;
        }
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
      .v_align_middle{ vertical-align: middle; }
      
      /* Estilos para elementos deshabilitados */
      input[disabled] + label,
      label[disabled],
      input[readonly] + label,
      label[readonly] {
          color: #6c757d; /* Cambio de color del texto */
          cursor: not-allowed;
      }
      input[readonly], input[disabled] {
        background-color: #e9ecef !important; /* Cambio de color del texto */
        cursor: not-allowed;
        opacity: 0.5; /* Reducción de la opacidad */
      }
      #tabla_productos input{
        border-top-color: #898989 !important;
      }

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
                          //var_dump($e);
                          // Captura otras excepciones que puedan ocurrir durante la ejecución del código
                          die("<br>Se produjo un error inesperado. Por favor, póngase en contacto con el soporte técnico.");
                          $disabledFacturaElectronica="disabled";
                        }
                      } else {
                        echo "<br>SOAP no está habilitado en tu servidor. Aun no podemos generar Facturas Electronicas y te pedimos disculas por ello. Este mensaje desaparecerá automaticamente cuando SOAP haya sido habilitado";
                        $disabledFacturaElectronica="disabled";
                      }
                      if(isset($yaVencio) and $yaVencio==true){
                        echo "<br>El certificado digital utilizado para la facturación electrónica ha vencido. Por favor, actualízalo para poder generar facturas electrónicas.";
                        $disabledFacturaElectronica="disabled";
                      }
                      $checkedFacturaConsumidorFinal="checked";
                      $checkedSinFactura="";
                      $checkedUnaFactura="checked";
                      $checkedMultipleFacturas="";
                      if($disabledFacturaElectronica=="disabled"){
                        $checkedFacturaConsumidorFinal="";
                        $checkedSinFactura="checked";
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

                            Database::disconnect();?>

                            <label class="col-sm-3 col-form-label">Fecha</label>
                            <div class="col-sm-9"><input type="date" name="fecha" id="fecha" class="form-control form-control-sm" value="<?=date("Y-m-d")?>" min="<?=$ultima_fecha_disponible_para_facturas?>" required></div>
                          </div>

                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cliente</label>
                            <div class="col-sm-9">
                              <select name="id_cliente" id="id_cliente" class="js-example-basic-single col-sm-12" required><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, razon_social, cuit, cond_fiscal, id_cond_fiscal FROM clientes WHERE activo = 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  /*$cuit="";
                                  if($fila["cuit"]){
                                    $cuit=" (".$fila["cuit"].")";
                                  }*/
                                  $selected="";
                                  if($fila["id"]==1){
                                    $selected=" selected";
                                  }?>
                                  <option value='<?=$fila['id']?>' data-cuit='<?=$fila['cuit']?>' data-cond_fiscal='<?=$fila['cond_fiscal']?>' data-id_cond_fiscal='<?=$fila['id_cond_fiscal']?>' <?=$selected?>><?=$fila['razon_social']?></option><?php
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

                          <div id="fila_dni_cuit" class="form-group row">
                            <label class="col-sm-3 col-form-label">Identificacion</label>
                            <div class="col-sm-3">
                              <label class="d-block" for="dni" class="mr-3">
                                <input class="checkbox_animated" id="dni" type="checkbox" name="identificacion" value="dni">
                                <label for="dni">DNI</label>
                              </label>
                              <label class="d-block" for="cuit">
                                <input class="checkbox_animated" id="cuit" type="checkbox" name="identificacion" value="cuit">
                                <label for="cuit">CUIT</label>
                              </label>
                            </div>
                            <div class="col-sm-6">
                              <input type="text" class="form-control" name="dni_cuit" id="dni_cuit" placeholder="Ingrese DNI o CUIT">
                            </div>
                          </div>

                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipo de comprobante</label>
                            <div class="col-sm-9">
                              <label class="d-block mb-0" for="factura_b">
                                <input class="radio_animated" required id="factura_b" type="radio" name="facturacion" value="B" <?=$checkedFacturaConsumidorFinal?> <?=$disabledFacturaElectronica?>>
                                <label class="v_align_middle" for="factura_b">Factura B</label>
                                <fieldset id="factura_b_options" class="ml-4 mt-2">
                                  <!-- <legend>Opciones para Factura B</legend> -->
                                  <label for="una_factura" class="mr-3">
                                    <input class="radio_animated" required id="una_factura" type="radio" name="cantidad_facturas" value="1" <?=$checkedUnaFactura?>>
                                    <label for="una_factura">1 Factura</label>
                                  </label>
                                  <label for="multiple_facturas">
                                    <input class="radio_animated" required id="multiple_facturas" type="radio" name="cantidad_facturas" value="varias" <?=$checkedMultipleFacturas?>>
                                    <label for="multiple_facturas">Multiples Facturas</label>
                                  </label>
                                </fieldset>
                              </label>
                              <label class="d-block" for="factura_a">
                                <input class="radio_animated" required id="factura_a" type="radio" name="facturacion" value="A" <?=$disabledFacturaElectronica?>>
                                <label class="v_align_middle" for="factura_a">Factura A</label>
                              </label>
                              <input type="hidden" name="cuit" id="hidden_cuit_cliente">
                              <label class="d-block" for="sin_factura">
                                <input class="radio_animated" required id="sin_factura" type="radio" name="facturacion" value="R" <?=$checkedSinFactura?>>
                                <label class="v_align_middle" for="sin_factura">Sin factura</label>
                              </label>
                            </div>
                          </div><?php

                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $sqlZon = "SELECT valor FROM parametros WHERE id=6";
                          $q = $pdo->prepare($sqlZon);
                          $q->execute();
                          $fila = $q->fetch(PDO::FETCH_ASSOC);
                          $monto_maximo_factura_b=$fila["valor"];
                          Database::disconnect();?>
                          <input type="hidden" name="monto_maximo_factura_b" id="monto_maximo_factura_b" value="<?=$monto_maximo_factura_b?>">
                          <div class="form-group row">
                            <p class="col-sm-12 text-muted">Las facturas B permiten un monto de $<?=number_format($monto_maximo_factura_b,0,',','.')?> (Recuerde revisar <a href="https://www.afip.gob.ar/fe/emision-autorizacion/datos-comprobantes.asp" target="_blank">este link</a> para verificar el monto) sin informar DNI o CUIT. Si no desea informar, seleccione la opcion "Multiples Facturas"</p>
                          </div>

                          <!-- <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Productos</label>
                          </div> -->
                          <div class="form-group row">
                            <div class="col-sm-12">
                              <table class="table table-sm display" style="width: 100% !important;" id="tabla_productos">
                                <thead>
                                  <tr>
                                    <th style="width: 40%">Descripcion</th>
                                    <th style="width: 20%">Precio</th>
                                    <th style="width: 20%">Cantidad</th>
                                    <th style="width: 20%">Subtotal</th>
                                  </tr>
                                </thead>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT id, descripcion, precio, iva FROM productos p WHERE activo = 1 ORDER BY CASE WHEN id IN (1,2) THEN 0 ELSE 1 END, (SELECT COUNT(*) FROM ventas_detalle vd WHERE vd.id_producto=p.id) DESC";//Coloca el producto con ID 1 al principio, los demás productos vienen después
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    $cantidad=$readonly=$tabindex="";
                                    $precio=$fila["precio"];
                                    if(in_array($fila["id"],[1,2])){
                                      $cantidad=1;
                                      $readonly=" readonly";
                                      $tabindex=' tabindex="-1"';
                                      $precio=0;
                                    }?>
                                    <tr id="<?=$fila["id"]?>">
                                      <td tabindex="-1"><?=$fila["descripcion"]?></td>
                                      <td>
                                        <input type="number" name="precio[]" class="form-control form-control-sm precio" value="<?=$precio?>">
                                        <input type="hidden" name="id_producto[]" value="<?=$fila["id"]?>">
                                        <input type="hidden" name="iva[]" value="<?=$fila["iva"]?>">
                                      </td>
                                      <td><input type="number" name="cantidad[]" step="0.01" min="0" class="form-control form-control-sm cantidad" value="<?=$cantidad?>" <?=$readonly.$tabindex?>></td>
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
                            <div class="col-sm-9">
                              <label id="lbl_total_compra">$ 0</label>
                              <input type="hidden" name="total_compra" id="total_compra">
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Modalidad de pago</label>
                            <div class="col-sm-9">
                              <label class="d-block" for="modalidad_cta_cte">
                                <input class="radio_animated" value="Cuenta Corriente" required id="modalidad_cta_cte" type="radio" name="modalidad_pago"><label for="modalidad_cta_cte">Cuenta Corriente</label>
                              </label>
                              <label class="d-block" for="modalidad_contado">
                                <input class="radio_animated" value="Contado" checked required id="modalidad_contado" type="radio" name="modalidad_pago"><label for="modalidad_contado">Contado</label>
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

                    <div class="modal fade" id="modalGenerarNC" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Atención</h5>
                            <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                          </div>
                          <div class="modal-body">
                            Las Facturas B no pueden superar el monto de $<?=number_format($monto_maximo_factura_b,0,',','.')?> sin informar CUIT o DNI. Por favor seleccione una de las siguientes opciones
                          </div>
                          <div class="modal-footer">
                            <button data-dismiss="modal" class="btn btn-light">Volver</button>
                            <a href="nota_credito.php?id=<?=$id?>" id="btnGenerarNC" class="btn btn-primary">Generar NC y eliminar Venta</a>
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
      var tabla=$('#tabla_productos');
      $(document).ready(function() {

        tabla.DataTable({
          stateSave: false,
          responsive: true,
          processing: true,
          scrollY: '400px', // Ajusta esta altura según tus necesidades
          paging: false,
          ordering: false,
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
          initComplete: function(settings, json){
            $('.precio:first').focus().select();
          }
        })
        
        $('#tabla_productos_wrapper .col-md-6:first').prepend('<h4 class="ml-3">Productos</h4>');

        // Llamar a la función de ajuste de altura cuando se realiza una búsqueda
        tabla.on('search.dt', function() {
          ajustarAlturaTabla();
        });

        // Llamar a la función de ajuste de altura al inicializar la tabla
        ajustarAlturaTabla();

        $("#id_cliente").on("change",function(){
          let cuit=cond_fiscal=id_cond_fiscal="";
          let checkedCuit=false;
          let disabledDniCuit=false;

          console.log(this.value);

          if(this.value>1){
            cuit=$("#id_cliente option[value='"+this.value+"']").data("cuit")

            checkedCuit=true;
            disabledDniCuit=true;
          }
          console.log(cuit);

          cond_fiscal=$("#id_cliente option[value='"+this.value+"']").data("cond_fiscal")
          id_cond_fiscal=$("#id_cliente option[value='"+this.value+"']").data("id_cond_fiscal")
          console.log(cond_fiscal,id_cond_fiscal)

          $("input[name='identificacion']").prop("disabled",disabledDniCuit);
          $("#dni").prop("checked",false)
          $("#cuit").prop("checked",checkedCuit)
          $("#dni_cuit").prop("disabled",disabledDniCuit).val(cuit)

          $("#id_cond_fiscal").val(id_cond_fiscal)
          $("#lbl_cond_fiscal").val(cond_fiscal)
          //$("#hidden_cuit_cliente").val(cuit)
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

              verificarTipoComprobante();
              //verificarMontoTotal();
              ok=verificarClienteRequerido();

              if(ok!=1){
                alert(ok)
              }else{
                ok=validarDniCuit();
                if(ok==1){
                  // Limpiar los filtros de DataTables
                  tabla.DataTable().search('').draw();

                  $("input[name='identificacion']").attr("disabled",false)
                  $("#dni_cuit").attr("disabled",false)
                  
                  $("#btnSubmit").addClass("disabled")
                  this.submit();
                  //console.log("submit")
                }
              }
            }
          }else{
            alert("Añada algún producto")
          }
        });

        $(document).on("keyup change",".cantidad, .precio",function(){
          let fila=$(this).closest("tr");
          let cantidad=fila.find(".cantidad").val()
          if(isNaN(cantidad) || cantidad==""){
            cantidad=0;
          }
          let precio=fila.find(".precio").val()
          if(isNaN(precio) || precio==""){
            precio=0;
          }
          let subtotal=parseFloat(precio)*parseFloat(cantidad)
          fila.find(".subtotal").val(subtotal.toFixed(2))
          actualizarMontoTotal()
        })

        $('input[type="radio"][readonly]').on('click', function(event){
          event.preventDefault(); // Prevenir el cambio de estado
        });

        $('input[name="identificacion"]').change(function() {
          $('input[name="identificacion"]').not(this).prop('checked', false);
        });

        // Cuando se cambia el tipo de comprobante, volvemos a verificar
        $('input[name="facturacion"]').change(function(){
          verificarTipoComprobante();
          verificarClienteRequerido();
        });

      });

      // Estructura de datos para almacenar los subtotales junto con los precios y cantidades
      let subtotalData = {};

      // Función para calcular y mostrar el subtotal en una fila específica
      function calcularSubtotal(fila) {
        const precio = parseFloat(fila.querySelector('.precio').value);
        const cantidad = parseFloat(fila.querySelector('.cantidad').value);
        let subtotal = precio * cantidad;
        if(isNaN(subtotal)){
          subtotal=0;
        }
        fila.querySelector('.subtotal').value = subtotal.toFixed(2);

        // Almacenar el subtotal junto con el precio y la cantidad en la estructura de datos
        subtotalData[fila.id] = { precio, cantidad, subtotal };
        
        // Verificar si el subtotal es 0 y eliminar el elemento correspondiente de la estructura de datos
        if (subtotal === 0) {
          delete subtotalData[fila.id];
        }

        // Calcular y mostrar el total
        calcularTotal();
      }

      // Función para calcular y mostrar el total
      function calcularTotal() {
        let total = 0;
        for (const key in subtotalData) {
          total += subtotalData[key].subtotal;
        }
        //document.getElementById('total').textContent = total.toFixed(2);
        $("#lbl_total_compra").html(new Intl.NumberFormat('es-AR', {currency: 'ARS', style: 'currency'}).format(total))
        $("#total_compra").val(total)
      }

      // Evento para detectar cambios en el precio o la cantidad
      document.querySelectorAll('.precio, .cantidad').forEach(input => {
        input.addEventListener('input', function() {
          const fila = this.closest('tr');
          calcularSubtotal(fila);
        });
      });

      // Función para validar la longitud y los caracteres del campo DNI/CUIT
      function validarDniCuit() {
        var valor = $('#dni_cuit').val();
        var identificacion = $('input[name="identificacion"]:checked').val();

        resp=1;
        if (identificacion === 'dni') {
          // Validar longitud y caracteres para DNI
          if (valor.length < 7 || valor.length > 8 || !/^\d+$/.test(valor)) {
            alert('Si seleccionó DNI como método de identificacion, el mismo debe tener entre 7 y 8 dígitos numéricos.');
            $('#dni_cuit').focus();//val(''); // Limpiar el campo
            resp=0;
          }
        } else if (identificacion === 'cuit') {
          // Validar longitud y caracteres para CUIT
          if (valor.length < 10 || valor.length > 11 || !/^\d+$/.test(valor)) {
            alert('Si seleccionó CUIT como método de identificacion, el mismo debe tener entre 10 y 11 dígitos numéricos.');
            $('#dni_cuit').focus();//val(''); // Limpiar el campo
            resp=0;
          }
        }
        return resp
      }

      // Función para ajustar dinámicamente la altura de la tabla
      function ajustarAlturaTabla() {
        var filaVisible = tabla.DataTable().rows({search: 'applied'}).nodes().length;
        var alturaFila = $('#tabla_productos tbody tr').outerHeight();
        alturaFila+=5;
        var alturaDeseada = filaVisible * alturaFila;
        let alturaMaxima=400;
        if(alturaDeseada>alturaMaxima){
          alturaDeseada=alturaMaxima
        }
        $('#tabla_productos_wrapper .dataTables_scrollBody').css('height', alturaDeseada);
      }

      function verificarTipoComprobante() {
        var tipoComprobante = $('input[name="facturacion"]:checked').val();

        // Si se selecciona "Factura B"
        if (tipoComprobante === 'B') {
          // Habilitar y mostrar los radio buttons de 1 factura y multiples facturas
          $('#una_factura, #multiple_facturas').prop('disabled', false);
        } else {
          // Deshabilitar y ocultar los radio buttons de 1 factura y multiples facturas
          $('#una_factura, #multiple_facturas').prop('disabled', true);
        }
      }

      function actualizarMontoTotal(){
        var table = $('#tabla_productos').DataTable();
        /*console.log(table.data());
        console.log(table.row());*/
        table.rows().every(function(){
            //console.log(this)
            var data = table.row(this).data();
            fila=data[3]
            /*console.log(fila)
            console.log($(fila));*/
            console.log($(fila).val());
            var data = this.data();
            /*console.log(data)
            console.log(data[3])*/
            //console.log($(data[3]).val())
            /*var subtotalValue = data['subtotal']; // Reemplaza 'subtotal' por el nombre real del campo que contiene el subtotal
           console.log(subtotalValue);*/
        });

        var total=0;
        //        $("#tabla_productos").DataTable().rows().data()
        $(".subtotal").each(function(){
          //console.log(this);

          let subtotal=$(this).val()
          if(isNaN(subtotal) || subtotal==""){subtotal=0;}
          total+=parseInt(subtotal);
        })
        /*$("#lbl_total_compra").html(new Intl.NumberFormat('es-AR', {currency: 'ARS', style: 'currency'}).format(total))
        $("#total_compra").val(total)*/
      }

      function verificarClienteRequerido() {
        console.log("verificarClienteRequerido");
        let tipoComprobante = $('input[name="facturacion"]:checked').val();
        let cantidadFacturas = $('input[name="cantidad_facturas"]:checked').val();
        let monto_maximo_factura_b=parseInt($("#monto_maximo_factura_b").val())
        let id_cliente=$("#id_cliente").val()
        let identificacion = $('input[name="identificacion"]:checked').val();
        let dni_cuit=$("#dni_cuit").val()
        // Obtener el monto total
        let montoTotal = $('#total_compra')
        montoTotal = parseFloat(montoTotal.val());

        let ingresoDniCuit=0;
        if(identificacion!=undefined && dni_cuit!=""){
          ingresoDniCuit=1;
        }
        let validarCliente=0;
        if(ingresoDniCuit==0 && id_cliente==1){
          validarCliente=1;
        }

        // Si se selecciona "Factura B" y "1 Factura"
        console.log("tipoComprobante es igual a B?",tipoComprobante,tipoComprobante==='B');
        console.log("cantidadFacturas es igual a 1?",cantidadFacturas,cantidadFacturas==='1');
        console.log("montoTotal es mayor a monto_maximo_factura_b?",montoTotal,monto_maximo_factura_b,montoTotal>monto_maximo_factura_b);
        console.log("validarCliente es igual a 1?",validarCliente,validarCliente==1);

        console.log("identificacion es distinto a undefined?",identificacion,identificacion!=undefined);
        console.log("dni_cuit es distinto a ''?",dni_cuit,dni_cuit!="");
        console.log("si los 2 ultimos son TRUE, ingresoDniCuit deberia ser 1");

        console.log("ingresoDniCuit es igual a 0?",ingresoDniCuit,ingresoDniCuit==0);
        console.log("idCliente es igual a 1?",id_cliente,id_cliente==1);
        console.log("si los 2 ultimos son TRUE, validarCliente deberia ser 1");

        if (tipoComprobante==='B' && cantidadFacturas==='1' && montoTotal>monto_maximo_factura_b && validarCliente==1) {
          return "Debe seleccionar un cliente, ingresar un DNI/CUIT o seleccionar la opcion de 'Factura B - Multiples Facturas'";
        } else {
          if (tipoComprobante==='A' && (id_cliente==='' || id_cliente==1)) {
            return "Debe seleccionar un cliente";
          }else{
            return 1
          }
        }
      }

      function verificarMontoTotal() {
        console.log("verificarMontoTotal");
        // Obtener el monto total
        var montoTotal = parseFloat($('#total_compra').val());
        let id_cliente=$("#id_cliente").val()

        // Si el monto total supera los 90000
        if (montoTotal > 90000) {
          // Mostrar la fila para ingresar DNI o CUIT si se cumple la condición y no se ha seleccionado un cliente
          var tipoComprobante = $('input[name="facturacion"]:checked').val();
          var cantidadFacturas = $('input[name="cantidad_facturas"]:checked').val();
          if (tipoComprobante === 'B' && cantidadFacturas === '1' && (id_cliente === '' || id_cliente === 1)) {
            console.log("mostrar fila_dni_cuit");
            //$('#fila_dni_cuit').show();
          }
        } else {
          // Ocultar la fila para ingresar DNI o CUIT si no se cumple la condición
          console.log("ocultar fila_dni_cuit");
          //$('#fila_dni_cuit').hide();
        }
      }

		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
		
  </body>
</html>