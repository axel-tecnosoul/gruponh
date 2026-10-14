<?php
require('../vendor/fpdf/fpdf.php');
require_once '../database.php';
require_once '../vendor/phpqrcode/qrlib.php';
require_once '../config_facturacion_electronica.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ID inválido');
}

function pdfText($value) {
    $value = (string)$value;
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
    if ($converted !== false) {
        return $converted;
    }

    return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
}

function formatCuit($value) {
    $digits = preg_replace('/\D+/', '', (string)$value);
    if (strlen($digits) !== 11) {
        return $digits;
    }

    return substr($digits, 0, 2) . '-' . substr($digits, 2, 8) . '-' . substr($digits, 10, 1);
}

function formatMoney($value) {
    return number_format((float)$value, 2, ',', '.');
}

function formatDateAr($value) {
    if (empty($value)) {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string)$value;
    }

    return date('d/m/Y', $timestamp);
}

function letraComprobante($value) {
    $map = array(
        1 => 'A',
        6 => 'B',
        11 => 'C',
        3 => 'A',
        8 => 'B',
        13 => 'C',
    );

    $numeric = (int)$value;
    if (isset($map[$numeric])) {
        return $map[$numeric];
    }

    if ($value === 'A' || $value === 'B' || $value === 'C') {
        return $value;
    }

    return 'B';
}

function condicionIvaLabel($value) {
    $map = array(
        1 => 'Responsable Inscripto',
        2 => 'Responsable Monotributo',
        3 => 'Responsable No Inscripto',
        4 => 'No Responsable',
        5 => 'Consumidor Final',
        6 => 'Exento',
        7 => 'No categorizado',
        8 => 'Responsable Inscripto',
        9 => 'Sujeto no categorizado',
        10 => 'Proveedor del exterior',
        11 => 'Sujeto no alcanzado',
    );

    $numeric = (int)$value;
    return $map[$numeric] ?? 'Consumidor Final';
}

class PDF extends FPDF {
  public $factura = array();
  public $items = array();

  function GetChecksumChar($code) {
      $number_odd = 0;
      for ($i=0; $i < strlen($code); $i+=2) {
          $number_odd += $code[$i];
      }
      $number_odd *= 3;
      $number_even = 0;
      for ($i=1; $i < strlen($code); $i+=2) {
          $number_even += $code[$i];
      }
      $sum = $number_odd + $number_even;
      $checksum_char = 10 - ($sum % 10);
      return $checksum_char == 10 ? 0 : $checksum_char;
  }

  function Footer() {
      $this->SetY(-15);
      $this->SetFont('Arial', 'I', 8);
      $this->Cell(0, 10, pdfText('2026 @ Desarrollado por Misiones Software'), 0, 0, 'C');
      $this->SetY(-15);
      $this->SetFont('Arial', 'I', 8);
      $this->Cell(188, 10, pdfText('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
  }

  function copia($emision) {
    $data = $this->factura;
    $items = $this->items;
    if ((int)$data['homologacion'] === 1) {
        $this->SetTextColor(225, 225, 225);
        $this->SetFont('Arial', 'B', 37);
        $this->Text(35, 151, pdfText('HOMOLOGACION'));
        $this->SetFont('Arial', 'B', 22);
        $this->Text(48, 166, pdfText('SIN VALIDEZ FISCAL'));
        $this->SetTextColor(0, 0, 0);
    }

    $puntoVenta = !empty($data['punto_venta']) ? str_pad((string)$data['punto_venta'], 4, '0', STR_PAD_LEFT) : '';
    $numeroComprobante = !empty($data['numero_comprobante']) ? str_pad((string)$data['numero_comprobante'], 8, '0', STR_PAD_LEFT) : '';
    $fecha = formatDateAr($data['fecha_cbte']);
    $fechaVtoPago = $fecha;
    $cuitFuente = !empty($data['emisor_cuit_db']) ? $data['emisor_cuit_db'] : ($data['emisor_cuit'] ?? '');
    $cuitEmpresa = !empty($cuitFuente) ? formatCuit($cuitFuente) : '30-71775420-0';
    $ingresosBrutos = !empty($data['emisor_ingresos_brutos']) ? (string)$data['emisor_ingresos_brutos'] : $cuitEmpresa;
    $fechaInicioActividad = !empty($data['emisor_fecha_inicio_actividades']) ? formatDateAr($data['emisor_fecha_inicio_actividades']) : '01/10/2022';
    
    $tipo_comprobante = $data['tipo_comprobante'];
    $letraComprobante = letraComprobante($data['tipo_comprobante']);
    
    $ambiente = !empty($data['homologacion']) ? 'HOMOLOGACIÓN' : 'PRODUCCIÓN';
    $condicionIva = condicionIvaLabel($data['condicion_iva_receptor'] ?? 5);
    $docTipo = $data['doc_tipo'] ?: 99;
    $docNro = $data['doc_nro'] ?: 0;
    $cae = $data['cae'] ?: '';
    $fechaVtoCae = formatDateAr($data['fecha_vencimiento_cae']);
    $razonSocial = $data['razon_social'] ?? '';
    $direccion = $data['direccion'] ?? '';
    $clienteCuit = formatCuit($data['cuit'] ?? '');

    $razonSocialEmisor = !empty($data['emisor_razon_social']) ? (string)$data['emisor_razon_social'] : 'EMISOR';
    $domicilioEmisor = !empty($data['emisor_direccion']) ? (string)$data['emisor_direccion'] : 'Riobamba 2751 - 1653 - VILLA BALLESTER - BS.AS';
    $emailEmisor = !empty($data['emisor_email']) ? (string)$data['emisor_email'] : 'miroperitooficial@gmail.com';
    $telefonoEmisor = !empty($data['emisor_telefono']) ? (string)$data['emisor_telefono'] : '1140467012';
    $condicionIvaEmisor = !empty($data['emisor_condicion_iva']) ? (string)$data['emisor_condicion_iva'] : 'Responsable Inscripto';
    $totalCalculado = 0;
    $obs = '';
    if (!empty($data['id_factura_asociada'])) {
        $obs = 'Factura asociada: '.str_pad((string)$data['original_punto_venta'], 5, '0', STR_PAD_LEFT).'-'.str_pad((string)$data['original_numero'], 8, '0', STR_PAD_LEFT).' | Motivo: '.(string)$data['motivo_nc'];
    }

    /*
    ancho_total = AT = 210;
    margen_superior = MS = 8;
    margen_izquierdo = MI = 8;
    margen_derecho = MD = 8;
    */

    //$this->Line(0, 1, 210, 1);

    //LINEAS HORIZONTALES
    $this->Line(8, 8, 202, 8);//linea superior. Desde MI,MS hasta AT-MD,MS
    $this->Line(8, 14, 202, 14);//abajo de emision. Desde MI,MS+6 hasta AT-MD,MS+6
    $this->Line(97, 30, 113, 30);//abajo del tipo de comprobante
    
    $this->Line(8, 50, 202, 50);//arriba periodo facturado
    $this->Line(8, 58, 202, 58);//abajo periodo facturado
    
    $this->Line(8, 59, 202, 59);//arriba datos del cliente
    $this->Line(8, 77, 202, 77);//abajo datos del cliente

    //$this->Line(122, 229, 202, 229);//arriba subtotal, total e impuestos
    //$this->Line(122, 242, 202, 242);//abajo subtotal, total e impuestos

    $this->Line(8, 265, 202, 265);//arriba cae
    $this->Line(8, 283, 202, 283);//abajo cae
    //FIN LINEAS HORIZONTALES

    //INICIO LINEAS VERTICALES
    $this->Line(8, 8, 8, 58);//izquierda emision, periodo facturado y datos del cliente
    $this->Line(202, 8, 202, 58);//derecha emision, periodo facturado y datos del cliente

    $this->Line(97, 14, 97, 30);//a la izquierda del tipo de comprobante
    $this->Line(113, 14, 113, 30);//a la derecha del tipo de comprobante
    $this->Line(105, 30, 105, 50);//abajo del tipo de comprobante

    $this->Line(202, 59, 202, 77);//derecha datos del cliente
    $this->Line(8, 59, 8, 77);//izquierda datos del cliente

    //$this->Line(122, 229, 122, 242);//izquierda subtotal, total e impuestos
    //$this->Line(202, 229, 202, 242);//derecha subtotal, total e impuestos
    //FIN LINEAS VERTICALES

    $this->SetMargins(8, 8, 8);
    $this->SetY(8);
    $this->SetX(8);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(194, 6, pdfText(strtoupper($emision)), 0, 1, 'C', 0);
    
    /*$this->Cell(-10);
    $this->SetFont('Arial', '', 7);
    $this->Cell(0, 2, pdfText($ambiente), 0, 0, 'R');*/
    
    $this->Ln(2);

    //$this->Cell(92);
    $this->SetFont('Arial', 'B', 25);
    $this->Cell(194, 8, pdfText($letraComprobante), 0, 1, 'C', 0);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(194, 6, pdfText("COD. ".$tipo_comprobante), 0, 1, 'C', 0);
    
    //para que la razon social del emisor y la palabra factura queden a la misma altura que el tipo de comprobante usamos SetY para establecer desde donde queremos continuar
    $this->SetY(18);

    /*if (is_file('../assets/images/logoBackend.png')) {
        $this->Image('../assets/images/logoBackend.png',28,16,48);
    }*/

    $this->SetFont('Arial', 'B', 16);
    $this->Cell(83, 10, pdfText($razonSocialEmisor), 0, 0, 'C', 0);
    $this->Cell(28, 10, pdfText(''), 0, 0, 'C', 0);
    $this->Cell(83, 10, pdfText(in_array((int)$tipo_comprobante, [3,8,13], true) ? 'NOTA DE CREDITO' : 'FACTURA'), 0, 0, 'L', 0);
    $this->Ln(10);

    $this->Cell(83, 6, pdfText(''), 0, 0, 'C', 0);
    $this->Cell(28, 6, pdfText(''), 0, 0, 'C', 0);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(23, 6, pdfText('Punto de Venta: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(10, 6, pdfText($puntoVenta), 0, 0, 'L', 0);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(20, 6, pdfText('Comp Nº: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(30, 6, pdfText($numeroComprobante), 0, 0, 'L', 0);

    $this->Ln(4);

    $this->Cell(3);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(15, 6, pdfText('Domicilio: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(65, 6, pdfText($domicilioEmisor), 0, 0, 'L', 0);
    
    $this->Cell(28, 6, pdfText(''), 0, 0, 'C', 0);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(28, 6, pdfText('Fecha de Emisión: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(55, 6, pdfText($fecha), 0, 0, 'L', 0);

    $this->Ln(4);

    $this->Cell(3);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(15, 6, pdfText('Email: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(65, 6, pdfText($emailEmisor), 0, 0, 'L', 0);
    
    $this->Cell(28, 6, pdfText(''), 0, 0, 'C', 0);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(28, 6, pdfText('CUIT: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(55, 6, pdfText($cuitEmpresa), 0, 0, 'L', 0);

    $this->Ln(4);

    $this->Cell(3);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(15, 6, pdfText('Tel: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(65, 6, pdfText($telefonoEmisor), 0, 0, 'L', 0);
    
    $this->Cell(28, 6, pdfText(''), 0, 0, 'C', 0);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(28, 6, pdfText('Ingresos Brutos: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(55, 6, pdfText($ingresosBrutos), 0, 0, 'L', 0);

    $this->Ln(4);

    $this->Cell(3);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(35, 6, pdfText('Condición frente al IVA: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(45, 6, pdfText($condicionIvaEmisor), 0, 0, 'L', 0);
    
    $this->Cell(28, 6, pdfText(''), 0, 0, 'C', 0);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(45, 6, pdfText('Fecha de Inicio de Actividades: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(38, 6, pdfText($fechaInicioActividad), 0, 0, 'L', 0);

    $this->Ln(6);

    $this->Cell(3);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(35, 8, pdfText('Período facturado desde: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(35, 8, pdfText($fecha), 0, 0, 'L', 0);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(10, 8, pdfText('Hasta: '), 0, 0, 'L', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(46, 8, pdfText($fecha), 0, 0, 'L', 0);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(40, 8, pdfText('Fecha de Vto. para el pago: '), 0, 0, '', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(25, 8, pdfText($fechaVtoPago), 0, 0, '', 0);
    
    $this->Ln(11);

    $this->Cell(3);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(22, 7, pdfText('CUIT: '), 0, 0, 'R', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(43, 7, pdfText($clienteCuit ?: '-'), 0, 0, 'L', 0);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(20, 7, pdfText('Razón Social: '), 0, 0, 'R', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(106, 7, pdfText($razonSocial), 0, 0, 'L', 0);

    $this->Ln(7);

    $this->Cell(3);
    $this->SetFont('Arial', 'B', 8);
    $this->Cell(22, 7, pdfText('Condición IVA: '), 0, 0, 'R', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(45, 7, pdfText($condicionIva ?: '-'), 0, 0, 'L', 0);

    $this->SetFont('Arial', 'B', 8);
    $this->Cell(18, 7, pdfText('Domicilio: '), 0, 0, 'R', 0);
    $this->SetFont('Arial', '', 8);
    $this->Cell(106, 7, pdfText($direccion), 0, 0, 'L', 0);

    $this->Ln(10);

    $this->SetFillColor(160, 160, 160);
    $this->SetTextColor(0, 0, 0);
    $this->SetDrawColor(0, 0, 0);
    $this->SetFont('Arial', 'B', 8);
        $mostrarBonificacion = false;
        foreach ($items as $item) {
            if ((float)($item['bonificacion'] ?? 0) > 0) {
                $mostrarBonificacion = true;
                break;
            }
        }

    $this->Cell(15, 7, pdfText('Código'), 1, 0, 'C', 1);
    $this->Cell(100, 7, pdfText('Descripción'), 1, 0, 'C', 1);
    $this->Cell(15, 7, pdfText('Cant.'), 1, 0, 'C', 1);
    $this->Cell(26, 7, pdfText('Precio Unit.'), 1, 0, 'C', 1);
        if ($mostrarBonificacion) {
            $this->Cell(12, 7, pdfText('Bonif'), 1, 0, 'C', 1);
            $this->Cell(26, 7, pdfText('Subtotal'), 1, 1, 'C', 1);
        } else {
            $this->Cell(38, 7, pdfText('Subtotal'), 1, 1, 'C', 1);
        }

    $this->SetFillColor(255, 255, 255);
    $this->SetTextColor(0, 0, 0);
    $this->SetDrawColor(0, 0, 0);
    $this->SetFont('Arial', '', 8);

    $ln=0;
    /*$row = array(
        'descripcion' => 'Producto de prueba para verificar el corte de texto en la factura, esto es solo un texto de prueba para ver si se muestra completo o se corta en la factura generada por el sistema, vamos a seguir escribiendo para ver hasta donde llega el texto y si se muestra completo o no en la factura, esto es solo un texto de prueba para verificar el funcionamiento del campo de observaciones en la factura, espero que se muestre completo y sin problemas en la factura generada por el sistema.',
        'cantidad' => 2,
        'precio' => 1500.50,
    );
    for($i=0; $i<20; $i++) {*/
    foreach ($items as $row) {
      $descripcion = (string)$row['descripcion'];
      $largoMaximo = 73;
      if (strlen($descripcion) > $largoMaximo) {
        $descripcion = substr($descripcion, 0, $largoMaximo) . '[...]';
      }
      $cantidad = (float)$row['cantidad'];
      $precio = (float)$row['precio'];
      $bonif = (float)($row['bonificacion'] ?? 0);
      $subtotalBruto = $precio * $cantidad;
      $subtotalItem = $subtotalBruto - $bonif;
      if ($subtotalItem < 0) {
        $subtotalItem = 0;
      }
      $totalCalculado += $subtotalItem;

      $this->Cell(15, 7, pdfText(''), 1, 0, 'C', 0);
      $this->Cell(100, 7, pdfText($descripcion), 1, 0, 'L', 0);
      $this->Cell(15, 7, pdfText((string)$cantidad), 1, 0, 'C', 0);
      $this->Cell(26, 7, pdfText('$' . formatMoney($precio)), 1, 0, 'R', 0);
      if ($mostrarBonificacion) {
        $this->Cell(12, 7, pdfText('$' . formatMoney($bonif)), 1, 0, 'R', 0);
        $this->Cell(26, 7, pdfText('$' . formatMoney($subtotalItem)), 1, 1, 'R', 0);
      } else {
        $this->Cell(38, 7, pdfText('$' . formatMoney($subtotalItem)), 1, 1, 'R', 0);
      }

      $ln = $ln + 7;//Salto de linea que resta del total
    }

    //$ln = 136 - $ln - $total_espacio - 3;
    $ln = 145 - $ln;
    $this->Ln($ln);

    $total = (float)($data['imp_total'] ?? $totalCalculado);

    //$this->Ln(1);
    $this->SetFillColor(160, 160, 160);
    $this->SetTextColor(0, 0, 0);
    $this->SetDrawColor(0, 0, 0);

    $this->Cell(114);
    $this->SetFont('Arial', '', 8);
    $this->Cell(40, 6, pdfText('  Subtotal'), 'LT', 0, '', 1);
    $this->Cell(40, 6, pdfText('$' . formatMoney($total)), 'RT', 0, 'R', 1);

    $this->Ln();
    
    $this->Cell(114);
    $this->SetFont('Arial', 'B', 10);
    $this->Cell(40, 6, pdfText('  Total Venta'), 'LB', 0, '', 1);
    $this->Cell(40, 6, pdfText('$' . formatMoney($total)), 'RB', 0, 'R', 1);

    $this->Ln(10);

    $this->SetFont('Arial', 'B', 10);
    $this->Cell(193, 12, pdfText('Observaciones: '), 1, 0, '', 0);
    $this->Ln(0);
    $this->Cell(27);
    $this->SetFont('Arial', '', 10);
    $largoMaximo = 100;
    if (strlen($obs) > $largoMaximo) {
      $obs = substr($obs, 0, $largoMaximo) . '[...]';
    }
    $this->Cell(190, 12, pdfText($obs), 0, 0, '', 0);

    $aQR = array(
      'ver' => 1,
      'fecha' => date('Y-m-d', strtotime($data['fecha_cbte'] ?? 'now')),
      'cuit' => intval(str_replace('-', '', $cuitEmpresa)),
      'ptoVta' => intval($data['punto_venta'] ?? 0),
      'tipoCmp' => intval($data['tipo_comprobante'] ?? 0),
      'nroCmp' => intval($data['numero_comprobante'] ?? 0),
      'importe' => floatval(number_format($total, 2, '.', '')),
      'moneda' => 'PES',
      'ctz' => 1,
      'tipoDocRec' => intval($docTipo),
      'nroDocRec' => intval($docNro),
      'tipoCodAut' => 'E',
      'codAut' => intval($cae),
    );

    $codesDir = '../codes/';
    if (!is_dir($codesDir)) {
      @mkdir($codesDir, 0777, true);
    }
    $codeFile = 'qr_factura_cobro_'.(int)$data['id'].'.png';
    QRcode::png('https://www.afip.gob.ar/fe/qr/?p=' . base64_encode(json_encode($aQR)), $codesDir . $codeFile, 'L', 5);

    if (is_file($codesDir . $codeFile)) {
      $this->Image($codesDir . $codeFile, 7, 260, 26);
    }

    $this->Ln(20);

    $this->SetFont('Arial', 'B', 10);
    $this->Cell(24);
    $this->Cell(15, 12, pdfText('CAE Nº: '), 0, 0, '', 0);
    $this->SetFont('Arial', '', 10);
    $this->Cell(100, 12, pdfText($cae), 0, 0, '', 0);

    $this->Ln(5);
    
    $this->Cell(24);
    $this->SetFont('Arial', 'B', 10);
    $this->Cell(32, 12, pdfText('Fecha de Vto CAE: '), 0, 0, '', 0);
    $this->SetFont('Arial', '', 10);
    $this->Cell(100, 12, pdfText($fechaVtoCae), 0, 0, '', 0);
  }
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT f.id, f.fecha_cbte, f.imp_total, f.estado, f.cae, f.fecha_vencimiento_cae, f.numero_comprobante, f.punto_venta, f.tipo_comprobante, f.homologacion, f.emisor_cuit, f.condicion_iva_receptor,
    f.id_factura_asociada, f.motivo_nc, original.punto_venta AS original_punto_venta, original.numero_comprobante AS original_numero, f.concepto, f.doc_tipo, f.doc_nro, f.imp_neto, f.imp_iva,
    c.razon_social, c.direccion, c.cuit, c.email, c.telefono,
    co.id_emisor,
    e.razon_social AS emisor_razon_social,
    e.cuit AS emisor_cuit_db,
    e.direccion AS emisor_direccion,
    e.email AS emisor_email,
    e.telefono AS emisor_telefono,
    e.condicion_iva AS emisor_condicion_iva,
    e.ingresos_brutos AS emisor_ingresos_brutos,
    e.fecha_inicio_actividades AS emisor_fecha_inicio_actividades
  FROM facturas f
    INNER JOIN clientes c ON f.id_cliente = c.id
    LEFT JOIN facturas original ON original.id = f.id_factura_asociada
    LEFT JOIN cobros co ON COALESCE(f.id_factura_asociada, f.id) = co.id_factura
    LEFT JOIN emisores e ON co.id_emisor = e.id
  WHERE f.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);
$factura = $q->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
  Database::disconnect();
  die('Factura no encontrada');
}

$sql = "SELECT descripcion, precio, cantidad, 0 AS bonificacion FROM factura_detalle WHERE id_factura = ? ORDER BY id ASC";//traemos 0 como bonificacion para no mostrar el campo, pero dejarlo preparado por si en algun momento lo tenemos
$q = $pdo->prepare($sql);
$q->execute([$id]);
$items = $q->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

$pdf = new PDF();
$pdf->factura = $factura;
$pdf->items = $items;
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(false);
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 8);

$pdf->AddPage();
$pdf->copia('original');
$pdf->AddPage();
$pdf->copia('duplicado');
$pdf->AddPage();
$pdf->copia('triplicado');

$pdfContent = $pdf->Output('S');

/*if (isset($_GET['enviar']) && !empty($factura['email'])) {
    require_once '../PHPMailer/PHPMailerAutoload.php';
    $mail = new PHPMailer();
    $mail->setFrom('no-reply@example.com', 'Mi Empresa');
    $mail->addAddress($factura['email'], $factura['razon_social']);
    $mail->Subject = 'Factura';
    $mail->Body = 'Adjuntamos su factura.';
    $mail->addStringAttachment($pdfContent, 'factura.pdf');
    @$mail->send();
}*/

header('Content-Type: application/pdf');

$cuitSinGuiones = preg_replace('/\D/', '', (string)($factura['emisor_cuit_db'] ?? $factura['emisor_cuit'] ?? ''));
$tipoCbte = str_pad((string)(int)($factura['tipo_comprobante'] ?? 0), 3, '0', STR_PAD_LEFT);
$ptoVta = str_pad((string)(int)($factura['punto_venta'] ?? 0), 5, '0', STR_PAD_LEFT);
$nroCbte = str_pad((string)(int)($factura['numero_comprobante'] ?? 0), 8, '0', STR_PAD_LEFT);

$pdfFilename = $cuitSinGuiones . '_' . $tipoCbte . '_' . $ptoVta . '_' . $nroCbte . '.pdf';

header('Content-Disposition: inline; filename="' . $pdfFilename . '"');
echo $pdfContent;
