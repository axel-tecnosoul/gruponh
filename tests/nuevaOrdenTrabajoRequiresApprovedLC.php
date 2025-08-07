<?php
function puedeCrearOT($id_estado_lista_corte){
    // 3 corresponde a "Aprobada"
    return $id_estado_lista_corte == 3;
}

$casos = [
    ['estado'=>3, 'esperado'=>true],
    ['estado'=>2, 'esperado'=>false],
];

foreach($casos as $c){
    $resultado = puedeCrearOT($c['estado']);
    echo "estado={$c['estado']} => ".($resultado?'permitido':'rechazado')." esperado=".($c['esperado']?'permitido':'rechazado')."\n";
}
?>
