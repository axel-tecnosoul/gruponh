<?php
ini_set("display_errors",1);
ini_set("display_startup_errors",1);
error_reporting(E_ALL);

include './afip/Afip.php';
include 'config_facturacion_electronica.php';//poner $homologacion=1 para facturar en modo homologacion. Retorna $aInitializeAFIP.

$cuitAxel=20351290340;
$cuitGaby=27332168970;

$aInitializeAFIP['CUIT']=$cuitAxel;//CUIT de la empresa que factura (11 caracteres)

$afip = new Afip($aInitializeAFIP);
$server_status = $afip->ElectronicBilling->GetServerStatus();

echo 'Este es el estado del servidor:';
var_dump($server_status);

// Información específica de tu CUIT
echo "\n=== INFORMACIÓN ESPECÍFICA DE TU CUIT ===\n";
try {
    $sales_points = $afip->ElectronicBilling->GetSalesPoints();
    echo 'Puntos de venta habilitados para tu CUIT:';
    var_dump($sales_points);
} catch (Exception $e) {
    echo "Error obteniendo puntos de venta: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), '602') !== false) {
        echo "NOTA: Este error es normal en ambiente de homologación.\n";
        echo "En producción, el CUIT debe tener puntos de venta asignados por ARCA.\n";
    }
}

$voucher_types = $afip->ElectronicBilling->GetVoucherTypes();
echo 'Obtener tipos de comprobantes disponibles:';
var_dump($voucher_types);

$aloquot_types = $afip->ElectronicBilling->GetAliquotTypes();
echo 'Obtener tipos de alícuotas disponibles';
var_dump($aloquot_types);

$option_types = $afip->ElectronicBilling->GetOptionsTypes();
echo 'Obtener tipos de opciones disponibles para el comprobante';
var_dump($option_types);

$tax_types = $afip->ElectronicBilling->GetTaxTypes();
echo 'Obtener tipos de tributos disponibles:';
var_dump($tax_types);

$cuit = '11111111111';//CUIT de la persona/empresa emitio la factura (11 caracteres)
$tipo_de_comprobante = '06';//Tipo de comprobante (2 caracteres, completado con 0's)
$punto_de_venta = '0001';//Punto de venta (4 caracteres, completado con 0's)
$cae = '12345678912345';//CAE (14 caracteres)
$vencimiento_cae = '20191210';//Fecha de expiracion del CAE (8 caracteres, formato aaaammdd)

$barcode = $cuit.$tipo_de_comprobante.$punto_de_venta.$cae.$vencimiento_cae;
$barcode .= GetChecksumChar($barcode);
var_dump($barcode);//Mostramos por pantalla el numero del codigo de barras de 40 caracteres
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