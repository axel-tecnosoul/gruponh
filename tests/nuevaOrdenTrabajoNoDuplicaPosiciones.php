<?php
function upsertDetalle($db, $id_ot, $id_pos, $cantidad){
    foreach($db as &$r){
        if($r['id_orden_trabajo']==$id_ot && $r['id_posicion']==$id_pos){
            $r['cantidad']=$cantidad;
            return $db;
        }
    }
    $db[]=['id_orden_trabajo'=>$id_ot,'id_posicion'=>$id_pos,'cantidad'=>$cantidad];
    return $db;
}

$datos=[];
$datos=upsertDetalle($datos,1,100,5); // inserta nueva posicion
$datos=upsertDetalle($datos,1,100,7); // actualiza cantidad sin duplicar

echo 'registros='.count($datos).' cantidad='.$datos[0]['cantidad']."\n";
?>
