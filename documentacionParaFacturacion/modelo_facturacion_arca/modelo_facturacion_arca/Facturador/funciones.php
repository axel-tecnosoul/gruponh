<?php

function get_nombre_comprobante($tipo_comprobante){
  switch ($tipo_comprobante) {
    case 'R':
      $tipo_cbte="Recibo";
      break;
    case 'A':
      $tipo_cbte="Factura A";
      break;
    case 'B':
      $tipo_cbte="Factura B";
      break;
    case 'NCA':
      $tipo_cbte="Nota de Crédito A";
      break;
    case 'NCB':
      $tipo_cbte="Nota de Crédito B";
      break;
      case 'NDA':
        $tipo_cbte="Nota de Débito A";
        break;
      case 'NDB':
        $tipo_cbte="Nota de Débito B";
        break;
    default:
      $tipo_cbte="";
      break;
  }
  return $tipo_cbte;
}

function get_estado_comprobante($estado_abreviado){
  switch ($estado_abreviado) {
    case 'R':
      $estado_completo="Rechazado";
      break;
    case 'A':
      $estado_completo="Aprobado";
      break;
    case 'E':
      $estado_completo="ERROR";
      break;
    default:
      $estado_completo="";
      break;
  }
  return $estado_completo;
}

/**
 * Obtiene los tipos de condiciones de IVA para receptores según AFIP
 * @return array Lista de condiciones fiscales con Id y descripción
 */
function getTiposCondicionesIvaReceptor(){
  $arr_tax_types=[
    [
        "Id"=> 1,
        "Desc"=> "IVA Responsable Inscripto",
        "Cmp_Clase"=> "A/M/C"
    ],
    [
        "Id"=> 6,
        "Desc"=> "Responsable Monotributo",
        "Cmp_Clase"=> "A/M/C"
    ],
    [
        "Id"=> 13,
        "Desc"=> "Monotributista Social",
        "Cmp_Clase"=> "A/M/C"
    ],
    [
        "Id"=> 16,
        "Desc"=> "Monotributo Trabajador Independiente Promovido",
        "Cmp_Clase"=> "A/M/C"
    ],
    [
        "Id"=> 4,
        "Desc"=> "IVA Sujeto Exento",
        "Cmp_Clase"=> "B/C"
    ],
    [
        "Id"=> 5,
        "Desc"=> "Consumidor Final",
        "Cmp_Clase"=> "B/C"
    ],
    [
        "Id"=> 7,
        "Desc"=> "Sujeto No Categorizado",
        "Cmp_Clase"=> "B/C"
    ],
    [
        "Id"=> 8,
        "Desc"=> "Proveedor del Exterior",
        "Cmp_Clase"=> "B/C"
    ],
    [
        "Id"=> 9,
        "Desc"=> "Cliente del Exterior",
        "Cmp_Clase"=> "B/C"
    ],
    [
        "Id"=> 10,
        "Desc"=> "IVA Liberado – Ley N° 19.640",
        "Cmp_Clase"=> "B/C"
    ],
    [
        "Id"=> 15,
        "Desc"=> "IVA No Alcanzado",
        "Cmp_Clase"=> "B/C"
    ]
  ];

  $tax_types = array_map(function($item) {
    return (object)$item;
  }, $arr_tax_types);
  
  return $tax_types;
}

function format_numero_comprobante($punto_venta,$numero_comprobante){
  return str_pad($punto_venta,4,"0",STR_PAD_LEFT)."-".str_pad($numero_comprobante,8,"0",STR_PAD_LEFT);
}

function calcularDeudaProveedor($id_forma_pago,$id_modalidad,$precio_final){
  require_once "config.php";
  require_once 'database.php';

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $sql4 = "SELECT valor FROM parametros WHERE id = 8 ";
  $q4 = $pdo->prepare($sql4);
  $q4->execute();
  $data4 = $q4->fetch(PDO::FETCH_ASSOC);
  $porcentaje_pagar_no_efectivo=(100-$data4["valor"])/100;
  //var_dump($fp);
  $pdo = Database::disconnect();

  $fp = 1;
  //si el pago no es en efectivo se le hace un descuento a la proveedora
  if ($id_forma_pago != 1) {
    //$fp = 0.85;
    //$fp = 0.80;
    $fp = $porcentaje_pagar_no_efectivo;
  }

  $porcentaje_modalidad = 0;
  if ($id_modalidad == 1) {//COMPRA DIRECTA

  } else if ($id_modalidad == 40) {//CONSIGNACION POR PORCENTAJE
    $porcentaje_modalidad = 0.4;
  } else if ($id_modalidad == 50) {//CONSIGNACION POR CREDITO
    $porcentaje_modalidad = 0.5;
  }
  
  $deuda_proveedor = number_format($precio_final*$porcentaje_modalidad*$fp,2,".","");
  return $deuda_proveedor;
}

function calcularDeudaProveedorViejo($id_forma_pago,$id_modalidad,$precio_final){
  $fp = 1;
  //si el pago no es en efectivo se le hace un descuento a la proveedora
  if ($id_forma_pago != 1) {
    $fp = 0.85;
    //$fp = 0.80;
  }

  $porcentaje_modalidad = 0;
  if ($id_modalidad == 1) {//COMPRA DIRECTA

  } else if ($id_modalidad == 40) {//CONSIGNACION POR PORCENTAJE
    $porcentaje_modalidad = 0.4;
  } else if ($id_modalidad == 50) {//CONSIGNACION POR CREDITO
    $porcentaje_modalidad = 0.5;
  }
  
  $deuda_proveedor = number_format($precio_final*$porcentaje_modalidad*$fp,2,".","");
  return $deuda_proveedor;
}

function ultimosDoceMeses($mesAnio=0){
  if($mesAnio==0){
      $mesAnio=date("m-Y");
  }
  $label=[$mesAnio=>0];
  $c=0;
  while($c<11){
    $mesAnio=date("m-Y",strtotime("01-".$mesAnio." - 1 month"));
    $label[$mesAnio]=0;
    $c++;
  }
  $label=array_reverse($label);
  return $label;
}

function formatFechaGraficoLineasPorMeses($array){
  $newArray=[];
  foreach ($array as $key => $value) {
      $newArray[]=date("M y", strtotime("01-".$key));
  }
  return $newArray;
}

function randomColor(){
  $str = "#";
  for ($i = 0 ; $i < 6 ; $i++) {
      $randNum = rand(0, 15);
      switch ($randNum) {
          case 10: $randNum = "A";
          break;
          case 11: $randNum = "B";
          break;
          case 12: $randNum = "C";
          break;
          case 13: $randNum = "D";
          break;
          case 14: $randNum = "E";
          break;
          case 15: $randNum = "F";
          break;
      }
      $str .= $randNum;
  }
  return $str;
}

function debugQuery(PDO $pdo, string $sql, array $params): string {
  foreach ($params as $key => $value) {
    // PDO::quote() añade comillas y escapa caracteres especiales
    $quoted = $pdo->quote($value);

    if (is_int($key)) {
      // marcador posicional «?»
      $sql = preg_replace('/\?/', $quoted, $sql, 1);
    } else {
      // marcador nombrado: quitamos un : adicional si lo trae la clave
      $name    = ltrim($key, ':');
      // \b asegura que no coincida dentro de palabras más largas
      $pattern = '/:' . preg_quote($name, '/') . '\b/';
      $sql     = preg_replace($pattern, $quoted, $sql);
    }
  }
  return $sql;
}