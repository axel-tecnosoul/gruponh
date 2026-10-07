# Informe de configuración de homologación ARCA

Fecha: 06/10/2026

## Cambios realizados

- Se habilitó la integración ARCA en `env.php` y se mantuvo el ambiente de homologación.
- El certificado y su clave privada existentes se copiaron a `C:\laragon\etc\arca\homologacion\empresa-2\`, fuera de la raíz web. `env.php` apunta a esas copias. Se verificó que ambas copias coincidan con sus archivos de origen y que el certificado y la clave correspondan entre sí.
- Los archivos de origen permanecen en la carpeta de referencia. Se agregó allí una regla Apache para denegar su acceso HTTP; la aplicación usa únicamente las copias fuera de `www`.
- Para la prueba solicitada, se asignó el CUIT de homologación del certificado a las empresas locales ID 1 y 2. Es un dato de prueba temporal, no el CUIT real de NH Construcciones.
- No se modificaron razón social, domicilio, condición de IVA, Ingresos Brutos ni fecha de inicio de actividades: el certificado no permite confirmar esos datos.

## Antes de intentar emitir

- Estado de SQL verificado el 06/10/2026: las tablas de configuración (`arca_puntos_venta`, `arca_tipos_comprobante`, `arca_punto_venta_tipos`, `empresas_padrones_retencion`) ya estaban presentes. Se generó un respaldo completo antes de ejecutar `facturacion_arca.sql`: `C:\laragon\etc\database-backups\gruponh-before-facturacion-arca-20261006-194844.sql`. La migración ya se aplicó y se verificaron las cuatro tablas fiscales (`comprobantes_arca` y sus tablas de IVA, tributos y asociados) y la columna de descuentos.
- `FACTURACION_ARCA_HABILITADA=1` y `ARCA_HOMOLOGACION=1`. El certificado de prueba queda configurado para las empresas ID 1 y 2 y el CUIT local de ambas coincide con el declarado por el certificado.
- Los valores 1 y 6 son IDs internos de `condiciones_iva`, no códigos fiscales de ARCA. En esta base, ID 1 es “Inscripto” e ID 6 es “Responsable monotributo”. La validación actual solo admite letras A/B para ID 1 y C para ID 6; la condición real debe confirmarse con la documentación fiscal, no cambiarse solo para pasar la validación.
- La última factura definitiva observada fue de NH Construcciones (empresa ID 1), condición “Inscripto” (ID 1), con letra C. Esa combinación seguirá causando “La letra del comprobante no coincide con la condición fiscal de la empresa emisora.” El flujo actual requiere letra A o B para esta ficha. Usar una factura A/B de homologación; no cambiar la condición fiscal ni convertir esa factura C solo para pasar la validación.
- La empresa ID 2 tiene actualmente condición “Consumidor final” (ID 3), distinta de las dos condiciones que contempla el flujo de emisor. El certificado por sí solo no acredita la condición fiscal, razón social ni domicilio.
- La emisión también requiere completar los datos fiscales reales de la empresa, configurar un punto de venta y asociarle el tipo de comprobante.
- Se comprobó que el PHP de Laragon tiene SOAP y OpenSSL habilitados. El comando `php` que puede invocarse desde otras consolas usa otra instalación de PHP; para diagnóstico usar el ejecutable de `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64`.
- La bandera habilitada permite consultas y emisiones remotas. Mantener `ARCA_HOMOLOGACION=1`; no activar producción para esta prueba.

## Reemplazo por los datos reales

Cuando se deje de probar:

1. Obtener el CUIT y los demás datos desde documentación fiscal oficial de la empresa. Reemplazar el CUIT de prueba en `empresas` ID 1 (y también ID 2 si continúa asociada al mismo certificado de prueba), además de confirmar razón social, domicilio, condición de IVA, Ingresos Brutos e inicio de actividades.
2. Solicitar/usar certificado y clave autorizados para el CUIT real. Guardarlos fuera de `www`, en un directorio legible por Apache, y actualizar las variables `ARCA_CERT_EMPRESA_<id>_HOMOLOGACION` y `ARCA_KEY_EMPRESA_<id>_HOMOLOGACION` de `env.php`.
3. Para producción, usar certificados de producción y variables con sufijo `_PRODUCCION`; cambiar `ARCA_HOMOLOGACION=0` solo después de validar emisor, punto de venta, permisos y autorización de ARCA.
4. No guardar claves privadas en Git ni incluir su contenido en documentación.

`env.php` está ignorado por Git y contiene solo rutas/configuración; los certificados y la clave copiados están fuera del repositorio.
