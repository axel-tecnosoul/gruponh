# Grupo NH

## Desarrollo local

Para enviar correos en un entorno local es necesario crear un archivo `config.dev.php` en la raíz del proyecto con las credenciales SMTP de desarrollo. Este archivo está ignorado por Git y no debe commitearse.

Ejemplo de contenido:

```php
<?php
return [
    'host' => 'smtp.example.local',
    'username' => 'user@example.local',
    'password' => 'secret',
    'from' => 'noreply@example.local',
    'from_name' => 'GrupoNH Dev'
];
```

Al montar el entorno local:
1. Copiar `config.dev.php.example` a `config.dev.php` y completar con las credenciales reales.
2. Definir la variable de entorno `APP_ENV=development`.

Cuando `APP_ENV` es `development` y existe `config.dev.php`, la aplicación utilizará estas credenciales en lugar de las almacenadas en base de datos.
