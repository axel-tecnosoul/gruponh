<?php
require('vendor/fpdf/fpdf.php');
//require('vendor/fpdf/autoprint.php');
include 'database.php';
include('vendor/phpqrcode/qrlib.php');
$id = $_GET['id'];

class PDF extends FPDF{

  /**
     * Funcion para obtener el ultimo numero del codigo
     * 
     * @param {string} $code Codigo de 39 caracteres
     **/
    function GetChecksumChar($code) {
      //Step one
      $number_odd = 0;
      for ($i=0; $i < strlen($code); $i+=2) { 
        $number_odd += $code[$i];
      }
      //Step two
      $number_odd *= 3;
      //Step three
      $number_even = 0;
      for ($i=1; $i < strlen($code); $i+=2) { 
        $number_even += $code[$i];
      }
      //Step four
      $sum = $number_odd+$number_even;
      //Step five
      $checksum_char = 10 - ($sum % 10);
      return $checksum_char == 10 ? 0 : $checksum_char;
    }

  // Cabecera de página
  function copia($emision){
    global $id;
    
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    /** Venta */
    $sql = "SELECT f.id, date_format(f.fecha_cbte,'%d/%m/%Y') AS fecha, fecha_cbte, f.punto_venta, f.cae, f.fecha_vto_cae, f.numero_cbte, f.tipo_comprobante, f.total_bruto, f.total_neto, f.id_iva, f.alicuota_ingresos_brutos, f.total_ingresos_brutos, f.alicuota_percepcion_iva, f.total_percepcion_iva, f.total_iva, c.razon_social, c.direccion, c.cuit, c.cond_fiscal, v.id AS id_venta, tipo_doc, dni, f.fecha_hora_alta, f.condicion_iva_receptor FROM facturas f INNER JOIN ventas v ON f.id_venta=v.id INNER JOIN clientes c ON v.id_cliente=c.id WHERE f.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute(array($id));
    //$q->debugDumpParams();
    $data = $q->fetch(PDO::FETCH_ASSOC);

    $cond_fiscal=$data["condicion_iva_receptor"];
    if(!$cond_fiscal){
      $cond_fiscal=$data["cond_fiscal"];
    }

    $tipo_comprobante=$data['tipo_comprobante'];

    /* Variables*/
    $punto_venta = $data['punto_venta'];
    $punto_venta = str_pad($punto_venta,4,"0",STR_PAD_LEFT);

    $numero_cbte = $data['numero_cbte'];
    $numero_cbte = str_pad($data['numero_cbte'],8,"0",STR_PAD_LEFT);

    //$cuit = "30-71775420-0";
    $cuit = "20-34149121-6";
    $fecha_inicio_actividad = "01/10/2022";
    $fecha = $fecha_vto_pago = $data["fecha"];

    $razon_social="";
    $direccion="";
    $cuit_cliente="";

    if(in_array($tipo_comprobante,["A","NCA"])){
      $razon_social=$data["razon_social"];
      $direccion=$data["direccion"];
      $cuit_cliente=$data["cuit"];
    }

    $lbl_tipo_comprobante="Nota de Credito";
    if(in_array($tipo_comprobante,["A","B"])){
      $lbl_tipo_comprobante="Factura";
    }

    $data["total_iva"];
    $otrosTributos=$data["total_ingresos_brutos"]+$data["total_percepcion_iva"];
    $leyTransparenciaFiscal=[
      "IVA" => $data["total_iva"],
      //"IIBB" => $data["total_ingresos_brutos"],//en principio seria solamente IVA
      //"Percepcion IVA" => $data["total_percepcion_iva"],//en principio seria solamente IVA
    ];

    $cantImpuestos=0;
    foreach ($leyTransparenciaFiscal as $key => $value) {
      if($value>0) $cantImpuestos++;
    }
    
    $ingresos_brutos = $cuit;
    $obs="";
    //$obs=$data["observaciones"];
    if(strlen($obs)>100){
      $obs=substr($obs,0,100)."[...]";
    }
    //var_dump($obs);
    /* LINEAS HORIZONTALES*/
    //$this->SetDrawColor(0, 0, 255, 0);
    $this->Line(8, 8, 201, 8);
    $this->Line(8, 14, 201, 14);
    $this->Line(98, 30, 114, 30);
    $this->Line(8, 50, 201, 50);
    $this->Line(8, 58, 201, 58);
    $this->Line(8, 59, 201, 59);
    $this->Line(8, 77, 201, 77);
    $this->Line(8, 265, 201, 265);
    $this->Line(8, 283, 201, 283);

    /* LINEAS VERTICALES*/
    $this->Line(8, 8, 8, 58);
    $this->Line(98, 14, 98, 30);
    $this->Line(114, 14, 114, 30);
    $this->Line(106, 30, 106, 50);
    $this->Line(201, 8, 201, 58);
    $this->Line(201, 59, 201, 77);
    $this->Line(8, 59,8,77);

    /* ORIGINAL */
    $this->Cell(88);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    //$this->Cell(0, 2, utf8_decode("ORIGINAL"));
    $this->Cell(0, 2, utf8_decode(strtoupper($emision)));
    $this->Ln(5);

    /* Filas */
    
    /* TIPO COMPROBANTE*/
    $this->Cell(92);  // mover a la derecha
    $this->SetFont('Arial', 'B', 25);
    $this->Cell(0, 15, utf8_decode(str_replace("NC","",$tipo_comprobante)));
    $this->Ln(5);
    /* Tipo de Factura */
    $this->SetFont('Arial', 'B', 16); //tipo fuente, negrita(B-I-U-BIU), tamañoTexto
    $this->Cell(110); // Movernos a la derecha
    $this->Cell(110, 0, utf8_decode(strtoupper($lbl_tipo_comprobante)));

    $this->Ln(8); // Salto de línea
    /* NOMBRE */
    $this->Cell(1);  // mover a la derecha
    $this->Image('img/logoMaury2.png',7,16,36);
    //$this->Image('img/tu logo azul.jpg',12,16,26);
    $this->Ln(1);
    
    /* Domicilio */
    $this->Cell(32);  // mover a la derecha
    $this->SetFont('Arial', '', 8);
    $this->Cell(48, 4, utf8_decode("San Miguel y 20 de Junio"), 0, 0, '', 0);
    $this->Ln();

    /* Correo */
    $this->Cell(32);  // mover a la derecha
    $this->SetFont('Arial', '', 8);
    $this->Cell(40, 4, utf8_decode("3334 - Puerto Rico - Misiones"), 0, 0, '', 0);
    $this->Ln();

    /* TEL */
    $this->Cell(32);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(6, 4, utf8_decode("Tel: "), 5, 0, '', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(20, 4, utf8_decode("3743443250"), 0, 0, '', 0);
    $this->Ln();

    /* Condición IVA */
    $this->Cell(32);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(33, 4, utf8_decode("Condición frente al IVA: "), 0, 0, '', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(20, 4, utf8_decode("Responsable Inscripto"), 0, 0, '', 0);
    $this->Ln(6);
    $this->SetY(39);

    /* Comp Nº */
    $this->Cell(150);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(59, -24, utf8_decode("Comp Nº: "), 0, 0, '', 0);
    $this->Ln(0);
    $this->Cell(164);
    $this->SetFont('Arial', '', 8);
    $this->Cell(0, -24, utf8_decode($numero_cbte), 0, 0, '', 0);
    $this->Ln(2);

    /* Punto de Venta */
    $this->Cell(110);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(59, -28, utf8_decode("Punto de Venta:"), 0, 0, '', 0);
    $this->Ln(0);
    $this->Cell(132);
    $this->SetFont('Arial', '', 8);
    $this->Cell(0, -28, utf8_decode($punto_venta), 0, 0, '', 0);
    $this->Ln(0);

    /* Fecha de emisión */
    $this->Cell(110);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(85, -15, utf8_decode("Fecha de Emisión: "), 0, 0, '', 0);
    $this->Ln(0);
    $this->Cell(136);
    $this->SetFont('Arial', '', 8);
    $this->Cell(0, -15, utf8_decode($fecha), 0, 0, '', 0);
    $this->Ln(5);

    /* CUIT */
    $this->Cell(110);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(85, -17, utf8_decode("CUIT: "), 0, 0, '', 0);
    $this->Ln(0);
    $this->Cell(119);
    $this->SetFont('Arial', '', 8);
    $this->Cell(0, -17, utf8_decode($cuit), 0, 0, '', 0);
    $this->Ln(5);

    /* Ingresos Brutos */
    $this->Cell(110);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(85, -19, utf8_decode("Ingresos Brutos: "), 0, 0, '', 0);
    $this->Ln(0);
    $this->Cell(134);  // mover a la derecha
    $this->SetFont('Arial', '', 8);
    $this->Cell(85, -19, utf8_decode($ingresos_brutos), 0, 0, '', 0);
    $this->Ln(5);

    /* Fecha de Inicio de Actividades */
    $this->Cell(110);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(85, -20, utf8_decode("Fecha de Inicio de Actividades: "), 0, 0, '', 0);
    $this->Ln(0);
    $this->Cell(153);
    $this->SetFont('Arial', '', 8);
    $this->Cell(0, -20, utf8_decode($fecha_inicio_actividad), 0, 0, '', 0);
    $this->Ln(1);

    /* Fecha de Vencimiento */
    //$this->Cell(110);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(37, -6, utf8_decode("Fecha de Vto. para el pago: "), 0, 0, '', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(20, -6, utf8_decode($fecha_vto_pago), 0, 0, '', 0);
    $this->Ln(10);

    /* Apellido y Nombre / Razon Social */
    $this->Cell(1);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(20, -5, utf8_decode("Razon Social: "), 0, 0, '', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(90, -5, utf8_decode($razon_social), 0, 0, '', 0);
    $this->Ln(5);

    /* Condición IVA */
    $this->Cell(1);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    //$this->Cell(85, 0, utf8_decode("Condición IVA: "), 0, 0, '', 0);
    $this->Cell(85, 0, utf8_decode("Domicilio Comercial: "), 0, 0, '', 0);
    $this->Ln(0);
    $this->Cell(31);
    $this->SetFont('Arial', '', 8);
    //$this->Cell(0, 0, utf8_decode("Consumidor Final"), 0, 0, '', 0);
    $this->Cell(0, 0, utf8_decode($direccion), 0, 0, '', 0);
    $this->Ln(5);

    /* CUIT */
    $this->Cell(110);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(85, -25, utf8_decode("CUIT: "), 0, 0, '', 0);
    $this->Ln(0);
    $this->Cell(118);
    $this->SetFont('Arial', '', 8);
    $this->Cell(0, -25, utf8_decode($cuit_cliente), 0, 0, '', 0);
    $this->Ln(5);

    /* Domicilio Comercial */
    $this->Cell(110);  // mover a la derecha
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(33, -20, utf8_decode("Condición frente al IVA: "), 0, 0, '', 0);
    /*$this->Ln(0);
    $this->Cell(139);*/
    $this->SetFont('Arial', '', 8);
    $this->Cell(50, -20, utf8_decode($cond_fiscal), 0, 0, '', 0);
    $this->Ln(-3);

    /* CAMPOS DE LA TABLA */
    $this->Cell(-2);
    $this->SetFillColor(160, 160, 160); //colorFondo
    $this->SetTextColor(0, 0, 0); //colorTexto
    $this->SetDrawColor(0, 0, 0); //colorBorde
    $this->SetFont('Arial', 'B', 8);
  
    $ln = 0;
    if(in_array($tipo_comprobante,["A","NCA"])){
      $this->Cell(20, 7, utf8_decode('Cantidad'), 1, 0, 'C', 1);
      $this->Cell(98, 7, utf8_decode('Descripción'), 1, 0, 'C', 1);
      $this->Cell(30, 7, utf8_decode('Precio Unitario'), 1, 0, 'C', 1);
      $this->Cell(15, 7, utf8_decode('IVA'), 1, 0, 'C', 1);
      $this->Cell(30, 7, utf8_decode('Importe'), 1, 1, 'C', 1);

      $this->SetFillColor(255, 255, 255); //colorFondo
      $this->SetTextColor(0, 0, 0); //colorTexto
      $this->SetDrawColor(0, 0, 0); //colorBorde
      $this->SetFont('Arial', '', 8);

      $sql2 = " SELECT p.descripcion, vd.cantidad,vd.precio as precio, vd.subtotal, vd.iva FROM ventas_detalle vd LEFT JOIN ventas v ON v.id = vd.id_venta INNER JOIN productos p ON p.id = vd.id_producto WHERE vd.id_venta = ".$data["id_venta"];
      $total = 0;
      $aTiposIva=[];
      foreach ($pdo->query($sql2) as $row){
        if(isset($aTiposIva[$row["iva"]])){
          $aTiposIva[$row["iva"]]+=$row["subtotal"];
        }else{
          $aTiposIva[$row["iva"]]=$row["subtotal"];
        }
        $this->Cell(-2);
        $subtotal=$row["subtotal"];
        //$this->Cell(-63 + $x2);
        $this->Cell(20, 7, utf8_decode($row["cantidad"]), 1, 0, 'C', 0);
        $descripcion=$row["descripcion"];
        if(strlen($descripcion)>105){
            $descripcion=substr($descripcion,0,105)."[...]";
        }
        $this->Cell(98, 7, utf8_decode($descripcion), 1, 0, 'L', 0);
        $this->Cell(30, 7, utf8_decode("$".number_format($row["precio"], 2,',', '.')), 1, 0, 'R', 0);
        $this->Cell(15, 7, utf8_decode($row["iva"]."%"), 1, 0, 'R', 0);
        $this->Cell(30, 7, utf8_decode("$".number_format($row["subtotal"], 2,',', '.')), 1, 1, 'R', 0);
        $total= $subtotal + $total;
        $ln = $ln + 7;//Salto de linea que resta del total
      }
    }else{

      $this->Cell(20, 7, utf8_decode('Cantidad'), 1, 0, 'C', 1);
      $this->Cell(103, 7, utf8_decode('Descripción'), 1, 0, 'C', 1);
      $this->Cell(35, 7, utf8_decode('Precio Unitario'), 1, 0, 'C', 1);
      $this->Cell(35, 7, utf8_decode('Importe'), 1, 1, 'C', 1);

      $this->SetFillColor(255, 255, 255); //colorFondo
      $this->SetTextColor(0, 0, 0); //colorTexto
      $this->SetDrawColor(0, 0, 0); //colorBorde
      $this->SetFont('Arial', '', 8);

      $descripcion="Pollo";
      if($data["id_iva"]==5){//id_iva = 5 -> 21% -> Chorizo
        $descripcion="Chorizo";
      }
      $this->Cell(-2);
      $total=$data["total_bruto"];
      //$this->Cell(-63 + $x2);
      $this->Cell(20, 7, utf8_decode(1), 1, 0, 'C', 0);
      $this->Cell(103, 7, utf8_decode($descripcion), 1, 0, 'L', 0);
      $this->Cell(35, 7, utf8_decode("$".number_format($total, 2,',', '.')), 1, 0, 'R', 0);
      $this->Cell(35, 7, utf8_decode("$".number_format($total, 2,',', '.')), 1, 1, 'R', 0);
      $ln = $ln + 7;//Salto de linea que resta del total
    }

    if(in_array($tipo_comprobante,["A","NCA"])){

      $valor_x_linea=8;
      $cant_lineas=count($aTiposIva);
      $total_espacio=$cant_lineas*$valor_x_linea;
      //$total_espacio=$total_espacio_old+8;//restamos la altura ocupada por imp otros tributos

      $ln = 136 - $ln - $total_espacio - 3;
      $this->Ln($ln);//Con 1 solo dato en la tabla el valor seria 136
      //$this->Cell(111);
      //$aTiposIva
      $this->SetFillColor(160, 160, 160); //colorFondo
      $this->SetTextColor(0, 0, 0); //colorTexto
      $this->SetDrawColor(0, 0, 0); //colorBorde
      $this->SetFont('Arial', 'B', 10);

      $this->Cell(111);
      $this->Cell(40, 8, utf8_decode("Importe Neto:"), "LT", 0, '', 1);
      $this->Cell(40, 8, utf8_decode("$".number_format($data["total_neto"],2, ',', '.')), "TR", 0, 'R', 1);
      $this->Ln();

      $porcentaje_ingresos_brutos=$data["alicuota_ingresos_brutos"];
      $porcentaje_percepcion_iva=$data["alicuota_percepcion_iva"];
      $fecha_correcion="2023-09-28 16:44";
      foreach($aTiposIva as $iva => $monto){
        if($data["fecha_hora_alta"]>=$fecha_correcion){
          $baseImponible=$monto/(($iva/100)+1);//1.21
        }else{
          //forma vieja y erronea.
          //se corrijio en la fecha establecida en el IF pero para no modificar las facturas ya generadas determinamos de esta forma para mostrar las viejas de la misma forma en que se generaron
          $total_impuestos=$iva+$porcentaje_ingresos_brutos+$porcentaje_percepcion_iva;
          $baseImponible=$monto/(($total_impuestos/100)+1);//1.21
        }
        //var_dump($baseImponible);
        //$total_impuestos=$iva+$porcentaje_ingresos_brutos+$porcentaje_percepcion_iva;
        $ImpIVA=$baseImponible*($iva/100);
        
        $this->Cell(111);
        $this->Cell(40, 8, utf8_decode("IVA ".$iva."%:"), "L", 0, '', 1);
        $this->Cell(40, 8, utf8_decode("$".number_format($ImpIVA,2, ',', '.')), "R", 0, 'R', 1);
        $this->Ln();
      }

      if($data["fecha_hora_alta"]>=$fecha_correcion){
        //forma nueva y correcta.
        //se corrijio en la fecha establecida en el IF pero para no modificar las facturas ya generadas determinamos de esta forma para mostrar las viejas de la misma forma en que se generaron
        $total+=$otrosTributos;
      }
      

      $this->Cell(111);
      $this->Cell(40, 8, utf8_decode("Imp Otros Tributos:"), "L", 0, '', 1);
      $this->Cell(40, 8, utf8_decode("$".number_format($otrosTributos,2, ',', '.')), "R", 0, 'R', 1);
      $this->Ln();
      
      $this->Cell(111);
      $this->Cell(40, 8, utf8_decode("Importe Total:"), "LB", 0, '', 1);
      $this->Cell(40, 8, utf8_decode("$".number_format($total,2, ',', '.')), "RB", 0, 'R', 1);
      //$this->Ln();

      /*// Lineas Horizontales
      $this->Line(121, 230-$total_espacio, 201, 230-$total_espacio);
      $this->Line(121, 243, 201, 243);
      // Lineas Verticales
      $this->Line(121, 230-$total_espacio, 121, 243);
      $this->Line(201, 230-$total_espacio, 201, 243);*/
      // Lineas Horizontales
      
      
      /*$this->Line(121, 219-$total_espacio, 201, 219-$total_espacio);
      $this->Line(121, 243, 201, 243);
      // Lineas Verticales
      $this->Line(121, 219-$total_espacio, 121, 243);
      $this->Line(201, 219-$total_espacio, 201, 243);*/
      $this->Ln(10);
    }else{
      $ln=$ln+($cantImpuestos*(8*2));//agregado por lo de transparencia fiscal
      $ln = 144 - $ln;

      $this->Ln($ln);//Con 1 solo dato en la tabla el valor seria 144
      $this->Cell(111);
      $this->SetFillColor(160, 160, 160); //colorFondo
      $this->SetTextColor(0, 0, 0); //colorTexto
      $this->SetDrawColor(0, 0, 0); //colorBorde
      $this->SetFont('Arial', '', 8); 
      $this->Cell(40, 5, utf8_decode("Subtotal:"), "LT", 0, '', 1);
      //$this->Ln(-4);
      //$this->Cell(173);
      $this->Cell(40, 5, utf8_decode("$".number_format($total,2, ',', '.')), "TR", 0, 'R', 1);
      $this->Ln();
      $this->Cell(111);
      $this->SetFont('Arial', 'B', 10);
      $this->Cell(40, 8, utf8_decode("Total Venta:"), "BL", 0, '', 1);
      //$this->Ln(0);
      //$this->Cell(170);
      $this->Cell(40, 8, utf8_decode("$".number_format($total,2, ',', '.')), "BR", 0, 'R', 1);

      /* Lineas Horizontales */
      //$this->Line(121, 230,201,230);
      //$this->Line(121, 243,201,243);
      /* Lineas Verticales */
      //$this->Line(121, 230,121,243);
      //$this->Line(201, 230,201,243);

      // Ley de transparencia fiscal
      $this->Ln(10);
      $this->Cell(-2);
      $this->SetFont('Arial', 'B', 9);
      $this->Cell(193, 8, utf8_decode("Regimen de Transparencia Fiscal al Consumidor (Ley 27.743)"), 1, 1, 'L', 1);
      $this->SetFont('Arial', '', 9);

      foreach ($leyTransparenciaFiscal as $impuesto => $monto) {
        if($monto>0){
          $this->Cell(-2);
          $this->Cell(153, 8, utf8_decode($impuesto." Contenido :"), "LT", 0, 'L', 0);
          $this->Cell(40, 8, utf8_decode("$".number_format($monto, 2, ',', '.')), "TR", 1, 'R', 0);
        }
      }
    }
    
    //$this->Ln(10);
    $this->Cell(-2);
    $this->SetFont('Arial', 'B', 10);
    $this->Cell(193, 12, utf8_decode("Observaciones: "), 1, 0, '', 0);
    $this->Ln(0);
    $this->Cell(25);
    $this->SetFont('Arial', '', 10);
    $this->Cell(190, 12, utf8_decode($obs), 0, 0, '', 0);
    $this->Ln(20);

    //$cuit = '11111111111';//CUIT de la persona/empresa emitio la factura (11 caracteres)
    //$tipo_de_comprobante = '06';//Tipo de comprobante (2 caracteres, completado con 0's)
    //$punto_de_venta = '0001';//Punto de venta (4 caracteres, completado con 0's)
    //$cae = '12345678912345';//CAE (14 caracteres)
    //$vencimiento_cae = '20191210';//Fecha de expiracion del CAE (8 caracteres, formato aaaammdd)
    switch($tipo_comprobante){
      case "A":
        $id_tipo_comprobante=1;
      break;
      case "B":
        $id_tipo_comprobante=6;
      break;
      case "NCA":
        $id_tipo_comprobante=3;
      break;
      case "NCB":
        $id_tipo_comprobante=8;
      break;
    }

    $aQR=[
      "ver" => 1,
      "fecha" => $data['fecha_cbte'],
      "cuit" => intval(str_replace("-","",$cuit)),
      "ptoVta" => intval($data['punto_venta']),
      "tipoCmp" => intval($id_tipo_comprobante),
      "nroCmp" => intval($data['numero_cbte']),
      "importe" => floatval(number_format($total,2,".","")),
      "moneda" => "PES",
      "ctz" => 1,
      "tipoDocRec" => intval($data['tipo_doc']),
      "nroDocRec" => intval($data['dni']),
      "tipoCodAut" => "E",
      "codAut" => intval($data['cae']),
    ];

    $codesDir = "codes/";
    $codeFile = 'qr.png';
    /*
      ECC:
      H -> H - Mejor
      M -> M
      Q -> Q
      L -> L - Peor
    */
    QRcode::png("https://www.afip.gob.ar/fe/qr/?p=".base64_encode(json_encode($aQR)), $codesDir.$codeFile, $ecc="L", $size=5);

    $this->Image($codesDir.$codeFile,7,260,26);
    //$this->Cell(190, 12, utf8_decode($codesDir.$codeFile), 0, 0, '', 0);

    $this->Cell(23);
    $this->SetFont('Arial', 'B', 10);
    $this->Cell(15, 12, utf8_decode("CAE Nº: "), 0, 0, '', 0);
    $this->SetFont('Arial', '', 10);
    $this->Cell(100, 12, utf8_decode($data['cae']), 0, 0, '', 0);
    $this->Ln(5);

    $this->Cell(23);
    $this->SetFont('Arial', 'B', 10);
    $this->Cell(33, 12, utf8_decode("Fecha de Vto CAE: "), 0, 0, '', 0);
    $this->SetFont('Arial', '', 10);
    $fecha_vencimiento_cae=strtotime($data['fecha_vto_cae']);
    $fecha_vencimiento_cae= date("d/m/Y", $fecha_vencimiento_cae);
    $this->Cell(100, 12, utf8_decode($fecha_vencimiento_cae), 0, 0, '', 0);
  

    // Pie de página
    $this->Ln(12);
    //$this->Cell(-63);
    //$this->SetY(-15); // Posición: a 1,5 cm del final
    $this->SetFont('Arial', 'I', 8); //tipo fuente, cursiva, tamañoTexto
    $this->Cell(0, 10, utf8_decode('2023 @ Desarrollado por Misiones Software'), 0, 0, 'C'); // pie de pagina(fecha de pagina)
    //$this->Cell(-63);
    $this->SetY(-15); // Posición: a 1,5 cm del final
    $this->SetFont('Arial', 'I', 8); //tipo fuente, negrita(B-I-U-BIU), tamañoTexto
    $this->Cell(188, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R'); //pie de pagina(numero de pagina)
  }

}


$pdf = new PDF();
//var_dump(get_class_methods('pdf'));

$pdf->AddPage(); /* aqui entran dos para parametros (horientazion,tamaño)V->portrait H->landscape tamaño (A3.A4.A5.letter.legal) */
$pdf->AliasNbPages(); //muestra la pagina / y total de paginas
$pdf->SetAutoPageBreak(false);
$i = 0;
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetDrawColor(0, 0, 0); //colorBorde

$pdf->copia("original");
$pdf->AddPage(); /* aqui entran dos para parametros (horientazion,tamaño)V->portrait H->landscape tamaño (A3.A4.A5.letter.legal) */
$pdf->copia("duplicado");
$pdf->AddPage(); /* aqui entran dos para parametros (horientazion,tamaño)V->portrait H->landscape tamaño (A3.A4.A5.letter.legal) */
$pdf->copia("triplicado");


$i = $i + 1;
/* TABLA */
//$pdf->AutoPrint();
$pdf->Output('Factura.pdf', 'I');//nombreDescarga, Visor(I->visualizar - D->descargar)
