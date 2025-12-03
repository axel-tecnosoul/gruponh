# Grupo NH

## Configuración del entorno

La aplicación utiliza la variable `APP_ENV` para distinguir entre entornos (`production`, `development`, etc.). Define esta variable antes de ejecutar cualquier script, ya sea exportándola o creando un archivo `env.php` en la raíz del proyecto con:

```php
<?php
putenv('APP_ENV=development'); // Cambiar según el entorno
```

`env.php` está ignorado por Git y no debe subirse al repositorio. En servidores de producción se recomienda establecer `APP_ENV=production`.

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
    'port' => xxx,
    'smtpSecure' => 'tls'
];
```

Al montar el entorno local:
1. Copiar `config.dev.php.example` a `config.dev.php` y completar con las credenciales reales.
2. Definir `APP_ENV=development` mediante `env.php` o una variable de entorno.

Cuando `APP_ENV` es `development` y existe `config.dev.php`, la aplicación utilizará estas credenciales en lugar de las almacenadas en base de datos.
