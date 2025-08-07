<?php
function validarCantidad($saldo, $cantidad){
    return is_numeric($cantidad) && $cantidad > 0 && $cantidad <= $saldo;
}

$casos = [
    ['saldo'=>10, 'cantidad'=>5, 'esperado'=>true],
    ['saldo'=>10, 'cantidad'=>0, 'esperado'=>false],
    ['saldo'=>10, 'cantidad'=>-3, 'esperado'=>false],
    ['saldo'=>10, 'cantidad'=>11, 'esperado'=>false],
];

foreach($casos as $c){
    $resultado = validarCantidad($c['saldo'], $c['cantidad']);
    echo "saldo={$c['saldo']} cantidad={$c['cantidad']} => ".($resultado?'true':'false')." esperado=".($c['esperado']?'true':'false')."\n";
}
?>
