<?php
require("config.php");
require 'database.php';
require 'permisos.php';

$id_computo = $_POST['id_computo'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//$sql = " SELECT d.id, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), d.aprobado, d.id_material, d.reservado, d.comprado,SUM(id.saldo) AS disponible,d.comentarios, date_format(d.fecha_necesidad,'%y%m%d'), d.cancelado FROM computos_detalle d inner join materiales m on m.id = d.id_material left JOIN ingresos_detalle id ON id.id_material=m.id WHERE d.cancelado = 0 and d.id_computo = $id_computo GROUP BY m.id";
//$sql = "SELECT cd.id AS id_computo_detalle, m.concepto, cd.cantidad AS cantidad_solicitada, date_format(cd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad_formatted, cd.aprobado, cd.id_material, cd.reservado, cd.comprado, SUM(id.saldo) AS disponible, cd.comentarios, date_format(cd.fecha_necesidad,'%y%m%d') AS fecha_necesidad, cd.cancelado, pd.cantidad AS cantidad_pedida FROM computos_detalle cd inner join materiales m on m.id = cd.id_material left JOIN ingresos_detalle id ON id.id_material=m.id LEFT JOIN pedidos p ON p.id_computo=cd.id_computo LEFT JOIN pedidos_detalle pd ON pd.id_pedido AND pd.id_material=m.id WHERE cd.cancelado = 0 and cd.id_computo =$id_computo GROUP BY m.id";
$sql = "SELECT cd.id AS id_computo_detalle, m.concepto, cd.cantidad AS cantidad_solicitada, date_format(cd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad_formatted, date_format(cd.fecha_necesidad,'%y%m%d') AS fecha_necesidad, cd.aprobado, cd.id_material, cd.reservado, SUM(pd.cantidad) AS cantidad_pedida, cd.comprado, m.id AS id_material, cd.cancelado, cd.comentarios, c.id_estado FROM computos_detalle cd JOIN computos c ON c.id = cd.id_computo inner join materiales m on m.id = cd.id_material LEFT JOIN pedidos p ON cd.id_computo=p.id_computo LEFT JOIN pedidos_detalle pd ON pd.id_pedido=p.id AND pd.id_material=m.id WHERE cd.id_computo = ".$id_computo." GROUP BY cd.id";//cd.cancelado = 0 and 
$aConceptos=[];

foreach ($pdo->query($sql) as $row) {

  $id_computo_detalle = $row["id_computo_detalle"];
  $cantidad_solicitada = $row["cantidad_solicitada"];
  $reservado=$row["reservado"];
  $comprado=$row["comprado"];
  //$cantidad_pedida = $row["cantidad_pedida"];
  $cantidad_pedida = !empty($row['cantidad_pedida']) ? $row['cantidad_pedida'] : 0;

  // 1) Calcular stock disponible
  $sql3  = "SELECT SUM(saldo) AS disponible FROM ingresos_detalle WHERE id_material = " . $row["id_material"];
  $q3    = $pdo->prepare($sql3);
  $q3->execute();
  $data3 = $q3->fetch(PDO::FETCH_ASSOC);

  $enStock = !empty($data3['disponible']) ? $data3['disponible'] : 0;

  /*$disponible=$row["disponible"];
	if (empty($disponible)) {
		$disponible = 0;
	}*/

  $aprobado="No";
	if ($row["aprobado"]==1) {
		$aprobado = 'Si';
	}

	//$saldo = $cantidad_solicitada-$disponible-$reservado-$comprado;
  $saldo = $cantidad_solicitada - $reservado - $cantidad_pedida;


	$cancelado = "No";
	if ($row["cancelado"]==1) {
		$cancelado = "Si";
	}

  $acciones = "";
  if($row["id_estado"]==2){
    if (!empty(tienePermiso(294))) {
      if ($aprobado=="No") {
        $acciones.="<span class='abrirModalAprobarItem' data-id_computo='$id_computo' data-id_computo_detalle='$id_computo_detalle'><img src='img/aprobar.png' width='24' height='25' border='0' alt='Aprobar' title='Aprobar'></span>&nbsp;&nbsp;";
      }
      if ($cancelado=="No") {
        $acciones.="<span class='abrirModalCancelarItem' data-id_computo='$id_computo' data-id_computo_detalle='$id_computo_detalle'><img src='img/cancelar.png' width='24' height='25' border='0' alt='Cancelar' title='Cancelar'></span>&nbsp;&nbsp;";
      }
    }
  }
  if (!empty(tienePermiso(311))) {
    if ($reservado > 0) {
      //$acciones.="<a href='cancelarStockPedido.php?id=$id_computo_detalle&idComputo=".$id_computo."'><img src='img/neg.png' width='24' height='25' border='0' alt='Cancelar Reserva' title='Cancelar Reserva'></a>&nbsp;&nbsp;";
      $acciones.="<span class='abrirModalCancelarReservaItem' data-id_computo='$id_computo' data-id_computo_detalle='$id_computo_detalle'><img src='img/neg.png' width='24' height='25' border='0' alt='Cancelar Reserva' title='Cancelar Reserva'></span>";
    }
  }

  $aConceptos[]=[
    0 => $row["concepto"],
    1 => $cantidad_solicitada,
    2 => $acciones,
    3 => "<span style='display: none;'>". $row["fecha_necesidad"] . "</span>".$row["fecha_necesidad_formatted"],
    4 => $aprobado,
    5 => $enStock,
    6 => $reservado,
    7 => $cantidad_pedida,
    8 => $comprado,
    9 => $saldo,
	  10 => $row["comentarios"]
  ];
}

Database::disconnect();
echo json_encode($aConceptos);
?>