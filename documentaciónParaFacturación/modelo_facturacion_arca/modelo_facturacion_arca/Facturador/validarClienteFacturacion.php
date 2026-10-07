<?php
// Validacion local minima; la autorizacion definitiva corresponde a ARCA.
function analizarClienteFacturacion($cliente) {
    $errores = [];
    $avisos = [];
    $cuitOriginal = trim((string)($cliente['cuit'] ?? ''));
    $cuit = preg_replace('/[\s-]+/', '', $cuitOriginal);
    $condicion = filter_var($cliente['id_cond_fiscal'] ?? null, FILTER_VALIDATE_INT);
    if (!$cliente) {
        $errores[] = 'Seleccione un cliente existente.';
    } else {
        if (!$condicion || $condicion < 1) {
            $errores[] = 'El cliente no tiene una condicion fiscal valida. Complete su ficha antes de facturar.';
        }
        if ($cuit === '') {
            if ($condicion === 5) {
                $avisos[] = 'Se facturara a Consumidor Final sin identificacion (documento 99, numero 0). La autorizacion depende tambien del importe y las reglas de ARCA.';
            } elseif ($condicion > 0) {
                $errores[] = 'El cliente no es Consumidor Final y no tiene CUIT. Complete el CUIT antes de facturar.';
            }
        } elseif (!preg_match('/^\d{11}$/D', $cuit)) {
            $errores[] = 'El CUIT debe contener 11 digitos; se permiten espacios y guiones. Corrija la ficha del cliente.';
        }
    }
    return ['valido'=>empty($errores), 'errores'=>$errores, 'avisos'=>$avisos,
        'cuit'=>$cuit, 'id_cond_fiscal'=>$condicion,
        'condicion'=> $condicion === 5 ? 'Consumidor Final' : (trim((string)($cliente['cond_fiscal'] ?? '')) ?: ($condicion > 0 ? 'Codigo '.$condicion : 'Sin cargar'))];
}

function validarClienteParaFactura($pdo, $idCliente) {
    $q = $pdo->prepare('SELECT cuit, id_cond_fiscal, cond_fiscal FROM clientes WHERE id = ?');
    $q->execute([$idCliente]);
    $resultado = analizarClienteFacturacion($q->fetch(PDO::FETCH_ASSOC));
    if (!$resultado['valido']) throw new RuntimeException(implode(' ', $resultado['errores']));
    return $resultado;
}
