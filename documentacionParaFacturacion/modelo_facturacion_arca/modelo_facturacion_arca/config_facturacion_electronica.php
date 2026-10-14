<?php
// Ejemplo: completar únicamente al integrar y habilitar pruebas.
$homologacion = 1;
$produccion = false;
$aInitializeAFIP = [
  'production' => false,
  'cert' => 'crt_axel_homo/wsafip_homo_2026.crt',
  'key' => 'crt_axel_homo/wsafip_homo_2026.key'
];
// Asignar CUIT desde el emisor elegido, nunca desde un dato fijo de otro proyecto.
