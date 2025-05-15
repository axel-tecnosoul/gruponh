<?php
require 'config.php';
require 'database.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT `IdCuenta`, `NombreCuenta`, `FechaDeAlta`, `CodigoExterno`, `RazonSocial`, `Usuario`, `CUIT`, `Contacto`, `DomicilioLegal`, `DomicilioFiscalDeEntrada`, `DomicilioFiscalDeSalida`, `CodigoPostal`, `Telefono`, `Fax`, `Email`, `Observaciones`, `CuentaGestion`, `IdCondicionAnteIVA`, `IdLocalidad`, `IdRubro`, `Vigente`, `EsRecurso`, `IdTipo`, `DiasFiscalesDeEntrada`, `DiasFiscalesDeSalida`, `TiposFiscalesDeSalida`, `ConsultasFiscalesDeSalida`, `TelefonoDeConsultasFiscalesDeSalida`, `IdPuesto`, `CuitSinGuiones` FROM `cuentas_import` WHERE 1";
foreach ($pdo->query($sql) as $row) {
	
	$sql = "select id_provincia from localidades where id = ?";
	$q = $pdo->prepare($sql);
	$q->execute([$row[18]]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$provincia = $data["id_provincia"];
	
	$sql3 = "select count(*) cant from cuentas where id = ?";
	$q3 = $pdo->prepare($sql3);
	$q3->execute([$row[0]]);
	$data3 = $q3->fetch(PDO::FETCH_ASSOC);
	if ($data3['cant'] == 0) {
		$sql2 = "INSERT INTO `cuentas`(`id`, `id_tipo_cuenta`, `nombre`, `razon_social`, `cuit`, `contacto`, `email`, `telefono`, `id_puesto`, `codigo_postal`, `id_pais`, `id_provincia`, `id_localidad`, `observaciones`, `activo`, `es_recurso`, `anulado`, `cuenta_gestion`, `codigo_externo`, `id_condicion_iva`, `direccion`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
		$q2 = $pdo->prepare($sql2);
		$q2->execute([$row[0],$row[22],$row[1],$row[4],$row[6],$row[7],$row[14],$row[12],$row[28],$row[11],13,$provincia,$row[18],$row[15],$row[20],$row[21],0,$row[16],$row[3],$row[17],$row[8]]);
	}
}

Database::disconnect();
	
die("¡PROCESO FINALIZADO!");
?>