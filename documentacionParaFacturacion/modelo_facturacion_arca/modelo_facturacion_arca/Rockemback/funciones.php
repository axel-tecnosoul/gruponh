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

function format_numero_comprobante($punto_venta,$numero_comprobante){
  return str_pad($punto_venta,4,"0",STR_PAD_LEFT)."-".str_pad($numero_comprobante,8,"0",STR_PAD_LEFT);
}

function getTiposCondicionesIvaReceptor2(){
  include 'vendor/afip/Afip.php';
  include 'config_facturacion_electronica.php';//poner $homologacion=1 para facturar en modo homologacion. Retorna $aInitializeAFIP.

  $afip = new Afip($aInitializeAFIP);
  /*$server_status = (array) $afip->ElectronicBilling->GetServerStatus();
  var_dump($server_status);*/

  $tax_types=null;
  try{
    $tax_types = $afip->ElectronicBilling->ExecuteRequest('FEParamGetCondicionIvaReceptor');
    //var_dump($tax_types);
    $tax_types = $tax_types->ResultGet->CondicionIvaReceptor;
  }catch(Exception $e){
    // Fecha actual en formato YYYY-MM-DD
    $fechaActual = date('Y-m-d');
    $fechaImplementacionARCA = '2025-04-15';

    if ($fechaActual<$fechaImplementacionARCA) {
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
    } else {
      die("Algo ha fallado al obtener las condiciones de iva del receptor del WS de ARCA: ".$e->getMessage());
    }
  }
  return $tax_types;
}

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