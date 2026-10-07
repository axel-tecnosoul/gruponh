<?php
// Ejemplo: completar únicamente al integrar y habilitar pruebas.
$homologacion = 1;
$produccion = false;
$aInitializeAFIP = [
  'production' => false,
  'exceptions' => true,
  'res_folder' => __DIR__.'/credenciales/',
  'ta_folder' => __DIR__.'/tickets/',
  'cert' => 'homologacion.crt',
  'key' => 'homologacion.key'
];
// Asignar CUIT desde el emisor elegido, nunca desde un dato fijo de otro proyecto.
