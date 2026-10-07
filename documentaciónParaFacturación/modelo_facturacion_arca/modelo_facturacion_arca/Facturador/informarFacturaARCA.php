<?php
ini_set("display_errors",1);
ini_set("display_startup_errors",1);
error_reporting(E_ALL);

function puedeReintentarFacturaARCA($factura) {
    if (!$factura || !empty($factura['cae']) || ($factura['estado'] ?? null) === 'A'
        || !empty($factura['id_nota_credito']) || !empty($factura['id_factura_asociada'])
        || in_array((int)($factura['tipo_comprobante'] ?? 0), array(3,8,13), true)) {
        return false;
    }
    $error = (string)($factura['error_arca'] ?? '');
    return strpos($error, 'SOAP-ERROR: Encoding:') === 0
        || strpos($error, 'Validacion local:') === 0
        || $error === 'No se pudo obtener datos del emisor';
}

/**
 * Función para informar factura a ARCA
 * @param int $idFactura ID de la factura a informar
 * @return string Resultado de la operación
 */
function informarFacturaARCA($idFactura) {
    require_once("config.php");

    if(empty($_SESSION['user']) || empty($_SESSION['user']['id'])){
      return "Error: Usuario no autenticado";
    }
    require_once 'database.php';

    // insert data
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $nombreBloqueo = 'factura_arca_'.(int)$idFactura;
    $qBloqueo = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $qBloqueo->execute(array($nombreBloqueo));
    if ((int)$qBloqueo->fetchColumn() !== 1) {
        return 'Error: Esta factura ya se esta procesando. Espere y consulte su estado.';
    }
    try {
    $modoDebug=0;
    if ($modoDebug==1) {
        $pdo->beginTransaction();
        var_dump($idFactura);
    }
    
    $sql = "SELECT f.*, c.nombre, c.cuit, c.email, c.telefono, c.direccion, c.pide_factura, c.cond_fiscal, c.id_cond_fiscal, co.id_emisor,  e.cuit as emisor_cuit, e.punto_venta, e.razon_social as emisor_razon_social
            FROM facturas f 
            INNER JOIN clientes c ON f.id_cliente = c.id 
            LEFT JOIN cobros co ON f.id = co.id_factura
            LEFT JOIN emisores e ON co.id_emisor = e.id
            WHERE f.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute(array($idFactura));
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if(!$data) {
      Database::disconnect();
      return "Error: Factura no encontrada";
    }

    if (!empty($data['cae']) || $data['estado'] === 'A') {
        return 'Error: La factura ya esta autorizada. No se volvera a emitir.';
    }
    if ((!empty($data['solicitud_arca']) || !empty($data['intentos_arca'])) && !puedeReintentarFacturaARCA($data)) {
        return 'Error: Debe verificar el estado de la factura en ARCA antes de reintentar.';
    }

    // Las NC tienen su propio circuito y no deben reenviarse como facturas C.
    if (!empty($data['id_factura_asociada']) || in_array((int)$data['tipo_comprobante'], array(3, 8, 13), true)) {
        Database::disconnect();
        return 'Error: La nota de credito debe gestionarse desde el cobro original';
    }

    // Cargar en cada llamada: require_once puede dejar estas variables fuera de alcance.
    require __DIR__.'/config_facturacion_electronica.php';
    $homologacion_db = (int)$homologacion === 1 ? 1 : 0;
    if ($data['homologacion'] !== null && (int)$data['homologacion'] !== $homologacion_db) {
        return 'Error: El ambiente ARCA no coincide con el del intento original.';
    }
    $pdo->prepare('UPDATE facturas SET homologacion = ? WHERE id = ?')->execute([$homologacion_db, $idFactura]);

    require_once __DIR__.'/validarClienteFacturacion.php';
    $validacionCliente = analizarClienteFacturacion($data);
    if (!$validacionCliente['valido']) {
        $pdo->prepare('UPDATE facturas SET error_arca = ? WHERE id = ?')
            ->execute(['Validacion local: '.implode(' ', $validacionCliente['errores']), $idFactura]);
        Database::disconnect();
        return 'Error: '.implode(' ', $validacionCliente['errores']);
    }

    // Para facturas generadas desde cobros, establecemos valores por defecto
    $punto_venta = $data['punto_venta'] ?? 1; // Punto de venta por defecto para homologación

    require_once 'vendor/afip/Afip.php'; // SDK de AFIP/ARCA para facturación electrónica

    // Asignación y validación del CUIT del emisor
    $aInitializeAFIP['CUIT'] = preg_replace('/\D+/', '', trim((string)($data['emisor_cuit'] ?? '')));
    // Evitar que un error de codificacion SOAP termine el proceso sin registrar el fallo.
    $aInitializeAFIP['exceptions'] = true;
    
    if(empty($aInitializeAFIP['CUIT'])) {
        $sql_error = "UPDATE facturas SET intentos_arca = COALESCE(intentos_arca,0) + 1, homologacion = ?, error_arca = ?, respuesta_arca = ?, punto_venta = ? WHERE id = ?";
        $q_error = $pdo->prepare($sql_error);
        $q_error->execute(array($homologacion_db, "No se pudo obtener datos del emisor", null, 1, $idFactura));
        Database::disconnect();
        return "Error: No se pudo obtener datos del emisor";
    }

    if($modoDebug==1){
      var_dump($aInitializeAFIP);
    }

    // Valores por defecto para registrar contexto incluso en errores tempranos
    $tipo_comprobante = null;
    $CondIVA = null;
    $imp_neto_db = null;
    $imp_iva_db = null;
    $concepto_db = null;
    $doc_tipo_db = null;
    $doc_nro_db = null;
    $imp_trib_db = null;

    try {
        // Datos de la factura adaptados para tabla 'facturas'
        $ImpTotal = $data["imp_total"];
        
        $emisor_cuit = $aInitializeAFIP['CUIT']; // CUIT ya validado
        
        // Determinar tipo de comprobante (por defecto Factura C para monotributistas)
        $tipo_comprobante = "C"; // Factura C por defecto
        $es_monotributista = true; // Por defecto monotributista
        
        // TODO: Determinar el tipo según configuración del emisor
        if($tipo_comprobante=="A"){
            $tipo_comprobante=1;//1 -> Factura A
            $es_monotributista = false;
        }elseif($tipo_comprobante=="B"){
            $tipo_comprobante=6;//6 -> Factura B
            $es_monotributista = false;
        }elseif($tipo_comprobante=="C"){
            $tipo_comprobante=11;//11 -> Factura C (Monotributistas)
            $es_monotributista = true;
        }
        
        // Cálculo de IVA según condición fiscal
        if($es_monotributista || $tipo_comprobante==11){
            // Monotributistas: NO facturan IVA
            $ImpNeto=$ImpTotal;
            $ImpIVA=0;
        } else {
            // Responsables Inscriptos: calculan IVA
            $ImpNeto=$ImpTotal/1.21;
            $ImpIVA=$ImpTotal-$ImpNeto;
        }

        // RECEPTOR/CLIENTE: Su condición se informa a ARCA (no determina tipo de comprobante)
        $CondIVA = $validacionCliente['id_cond_fiscal']; // Condición IVA del cliente (por defecto Consumidor Final)

        // Datos del cliente
        $DocNro = $validacionCliente['cuit'] ?: "0"; // CUIT o 0 para consumidor final
        $DocTipo = $validacionCliente['cuit'] ? 80 : 99; // 80=CUIT, 99=Consumidor Final
        $doc_nro_db = (string)$DocNro;
        $doc_tipo_db = (int)$DocTipo;

        $imp_neto_db = round((float)$ImpNeto,2);
        $imp_iva_db = round((float)$ImpIVA,2);
        $imp_trib_db = 0.00;

        $ImpTotal=number_format((float)$ImpTotal,2,".","");
        $ImpNeto=number_format($imp_neto_db,2,".","");
        $ImpIVA=number_format($imp_iva_db,2,".","");
        

        $fecha_cbte = intval(date('Ymd'));
        $concepto_db = 2;
        $data_factura = array(
            'CantReg' 	=> 1,  // Cantidad de comprobantes a registrar
            'PtoVta' 	=> $punto_venta,  // Punto de venta
            'CbteTipo' 	=> $tipo_comprobante,  // Tipo de comprobante (ver tipos disponibles) 
            'Concepto' 	=> $concepto_db,  // Concepto del Comprobante: (1)Productos, (2)Servicios, (3)Productos y Servicios
            'FchServDesde' => $fecha_cbte,
            'FchServHasta' => $fecha_cbte,
            'FchVtoPago' => $fecha_cbte,
            'DocTipo' 	=> $DocTipo, // Tipo de documento del comprador (99 consumidor final, ver tipos disponibles). Para comprobantes clase A y M el campo DocTipo debe ser igual a 80 (CUIT)
            'DocNro' 	=> $DocNro,  // Número de documento del comprador (0 consumidor final)
            'CondicionIVAReceptorId' 	=> $CondIVA, // Condición frente al IVA del receptor/cliente
            'CbteFch' 	=> $fecha_cbte, // (Opcional) Fecha del comprobante (yyyymmdd) o fecha actual si es nulo
            'ImpTotal' 	=> $ImpTotal,//121, // Importe total del comprobante
            'ImpTotConc' 	=> 0,   // Importe neto no gravado
            'ImpNeto' 	=> $ImpNeto,//100, // Importe neto gravado
            'ImpOpEx' 	=> 0,   // Importe exento de IVA
            'ImpIVA' 	=> $ImpIVA,//21,  //Importe total de IVA
            'ImpTrib' 	=> $imp_trib_db,   //Importe total de tributos
            'MonId' 	=> 'PES', //Tipo de moneda usada en el comprobante (ver tipos disponibles)('PES' para pesos argentinos) 
            'MonCotiz' 	=> 1,     // Cotización de la moneda usada (1 para pesos argentinos)  
        );
        
        // Solo incluir alícuotas de IVA si NO es monotributista
        if(!$es_monotributista && $ImpIVA > 0){
            $data_factura['Iva'] = array( // (Opcional) Alícuotas asociadas al comprobante
                array(
                    'Id' 		=> 5, // Id del tipo de IVA (5 para 21%)(ver tipos disponibles) 
                    'BaseImp' 	=> $ImpNeto,//100, // Base imponible
                    'Importe' 	=> $ImpIVA,//21 // Importe IVA
                )
            );
        }
        
        // Ejemplo: Agregar percepciones y retenciones
        // TODO: Obtener de la venta los tributos aplicados
        /*
        $data_factura['Tributos'] = array(
            array(
                'Id' => 99, // ID del tributo según AFIP
                'Desc' => 'Percepción IIBB Buenos Aires', 
                'BaseImp' => $ImpNeto,
                'Alic' => 2.50, // 2.5%
                'Importe' => $ImpNeto * 0.025
            ),
            array(
                'Id' => 1, // ID para retención Ganancias
                'Desc' => 'Retención Ganancias',
                'BaseImp' => $ImpNeto,
                'Alic' => 6.00, // 6%
                'Importe' => $ImpNeto * 0.06
            )
        );
        */

        if ($modoDebug==1) {
            var_dump($data_factura);
        }

        $solicitud = json_encode($data_factura, JSON_THROW_ON_ERROR);
        $pdo->prepare('UPDATE facturas SET solicitud_arca = ?, emisor_cuit = ?, punto_venta = ?, tipo_comprobante = ?, condicion_iva_receptor = ?, concepto = ?, doc_tipo = ?, doc_nro = ?, imp_neto = ?, imp_iva = ? WHERE id = ?')
            ->execute([$solicitud,$emisor_cuit,$punto_venta,$tipo_comprobante,$CondIVA,$concepto_db,$doc_tipo_db,$doc_nro_db,$imp_neto_db,$imp_iva_db,$idFactura]);
        $afip = new Afip($aInitializeAFIP);
        $pdo->prepare('UPDATE facturas SET error_arca = ? WHERE id = ?')
            ->execute(array('Envio en curso o pendiente de conciliacion con ARCA.', $idFactura));
        $res = $afip->ElectronicBilling->CreateNextVoucher($data_factura);
        
        if(!empty($res['CAE'])){
            $estado="A";
            $CAE=$res['CAE'];//CAE asignado el comprobante
            $CAEFchVto=$res['CAEFchVto'];//Fecha de vencimiento del CAE (yyyy-mm-dd)
            $voucher_number=$res['voucher_number'];//Número asignado al comprobante

            if ($modoDebug==1) {
                var_dump($res);
                var_dump($CAE);
                var_dump($CAEFchVto);
                var_dump($voucher_number);
            }

            // Guardar resultado y snapshot enviado a ARCA en un unico update
            $sql = "UPDATE facturas SET intentos_arca = COALESCE(intentos_arca,0) + 1, homologacion = ?, emisor_cuit = ?, punto_venta = ?, tipo_comprobante = ?, condicion_iva_receptor = ?, concepto = ?, doc_tipo = ?, doc_nro = ?, imp_neto = ?, imp_iva = ?, estado = ?, numero_comprobante = ?, cae = ?, fecha_vencimiento_cae = ?, respuesta_arca = ?, error_arca = NULL WHERE id = ?";
            $q = $pdo->prepare($sql);
            $q->execute(array($homologacion_db,$emisor_cuit,$punto_venta,$tipo_comprobante,$CondIVA,$concepto_db,$doc_tipo_db,$doc_nro_db,$imp_neto_db,$imp_iva_db,$estado,$voucher_number,$CAE,$CAEFchVto,json_encode($res),$idFactura));

            if ($modoDebug==1) {
                $q->debugDumpParams();
                echo "<br><br>Afe: ".$q->rowCount();
                echo "<br><br>";
            }

            if($q->rowCount()==1){
                $resultado = "Factura informada a ARCA correctamente - CAE: $CAE";
            } else {
                $resultado = "Error: No se pudo actualizar la factura";
            }

        } else {
            // Guardar error cuando no se obtiene CAE
            $error_msg = "No se obtuvo CAE de ARCA";
            if(isset($res['Errors'])) {
                $error_msg .= " - " . json_encode($res['Errors']);
            }
            
            $sql_error = "UPDATE facturas SET intentos_arca = COALESCE(intentos_arca,0) + 1, homologacion = ?, emisor_cuit = ?, punto_venta = ?, tipo_comprobante = ?, condicion_iva_receptor = ?, concepto = ?, doc_tipo = ?, doc_nro = ?, imp_neto = ?, imp_iva = ?, error_arca = ?, respuesta_arca = ? WHERE id = ?";
            $q_error = $pdo->prepare($sql_error);
            $q_error->execute(array($homologacion_db,$emisor_cuit,$punto_venta,$tipo_comprobante,$CondIVA,$concepto_db,$doc_tipo_db,$doc_nro_db,$imp_neto_db,$imp_iva_db,$error_msg,json_encode($res),$idFactura));
            
            $resultado = "Error: " . $error_msg;
        }

        if ($modoDebug==1) {
            $pdo->rollBack();
            Database::disconnect();
            return $resultado;
        }
        
        Database::disconnect();
        return $resultado;
        
    } catch (Throwable $e) {
        // Guardar error de excepción y snapshot en un unico update
        $error_msg = $e->getMessage();
        $emisor_cuit = preg_replace('/\D+/', '', trim((string)($data['emisor_cuit'] ?? '')));
        $emisor_cuit_db = ($emisor_cuit !== '') ? $emisor_cuit : null;
        $punto_venta_db = $punto_venta ?? null;
        $tipo_comprobante_db = $tipo_comprobante ?? null;
        $condicion_iva_receptor_db = $CondIVA ?? null;
        $concepto_db_value = $concepto_db ?? null;
        $doc_tipo_db_value = $doc_tipo_db ?? null;
        $doc_nro_db_value = $doc_nro_db ?? null;
        $imp_neto_db_value = $imp_neto_db ?? null;
        $imp_iva_db_value = $imp_iva_db ?? null;
        $homologacion_db_value = $homologacion_db ?? null;
        $respuesta_arca_db = null;
        $sql_error = "UPDATE facturas SET intentos_arca = COALESCE(intentos_arca,0) + 1, homologacion = ?, emisor_cuit = ?, punto_venta = ?, tipo_comprobante = ?, condicion_iva_receptor = ?, concepto = ?, doc_tipo = ?, doc_nro = ?, imp_neto = ?, imp_iva = ?, error_arca = ?, respuesta_arca = ? WHERE id = ?";
        $q_error = $pdo->prepare($sql_error);
        $q_error->execute(array($homologacion_db_value,$emisor_cuit_db,$punto_venta_db,$tipo_comprobante_db,$condicion_iva_receptor_db,$concepto_db_value,$doc_tipo_db_value,$doc_nro_db_value,$imp_neto_db_value,$imp_iva_db_value,$error_msg,$respuesta_arca_db,$idFactura));
        
        Database::disconnect();
        return 'Error: ' . $error_msg;
    }
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute(array($nombreBloqueo));
        Database::disconnect();
    }

}

// Abrir esta URL solo navega: la emision se solicita por POST desde el cobro.
if (!debug_backtrace() && !empty($_GET['id'])) {
    require('config.php');
    if (empty($_SESSION['user'])) { header('Location: index.php'); exit; }
    require_once 'database.php';
    $pdo = Database::connect();
    $q = $pdo->prepare('SELECT id FROM cobros WHERE id_factura = ? LIMIT 1');
    $q->execute(array((int)$_GET['id']));
    $idCobro = $q->fetchColumn();
    header('Location: '.($idCobro ? 'modificarCobro.php?id='.(int)$idCobro : 'listarCobros.php'));
    exit;
}
?>
