# Emisión de facturas de venta por ARCA

La emisión se ejecuta al elegir **Emitir y guardar definitivo** en una factura de venta. Los borradores siguen siendo locales. El número correlativo lo consulta ARCA y se guarda localmente antes de enviar la solicitud.

La comunicación ARCA está deshabilitada por defecto. Para guardar facturas definitivas sin validación ni envío remoto, no configure `FACTURACION_ARCA_HABILITADA` (o asígnele `0`). Para habilitar la validación y emisión, establezca `FACTURACION_ARCA_HABILITADA=1` en la configuración de entorno de PHP/Apache. Los endpoints de emisión y conciliación también verifican esta variable.

## Instalación

1. Respaldar la base de datos y ejecutar una sola vez [`facturacion_arca.sql`](./facturacion_arca.sql). La migración agrega los datos fiscales, el historial de emisión y las tablas de IVA, tributos y comprobantes asociados.
2. Ejecutar `composer install` para instalar el generador local de QR.
3. Habilitar `soap` y `openssl` en el PHP usado por Apache. La extensión SOAP también debe estar disponible en cualquier proceso de emisión en consola.
4. Cargar los datos fiscales de las empresas en `empresas` y las condiciones de IVA en `cuentas`. Los campos fiscales de `empresas` se agregan con las consultas de configuración existentes en [`consultas.sql`](./consultas.sql).
5. Configurar certificados de homologación fuera del repositorio. El certificado y la clave privada de cada empresa deben estar en el mismo directorio, y ese directorio debe permitir la copia de los WSDL del SDK.
6. Ejecutar la corrección 24 de [`consultas.sql`](./consultas.sql) para agregar puntos de venta, catálogo de comprobantes, asociaciones por punto de venta y padrones provinciales.
7. Ejecutar la corrección 33 de [`consultas.sql`](./consultas.sql) para agregar datos FCE y plazos configurables.

En `env.php` —archivo local ignorado por Git— se pueden declarar variables como:

```php
putenv('ARCA_HOMOLOGACION=1');
putenv('ARCA_CERT_EMPRESA_2_HOMOLOGACION=C:\\seguridad\\arca\\empresa-2.crt');
putenv('ARCA_KEY_EMPRESA_2_HOMOLOGACION=C:\\seguridad\\arca\\empresa-2.key');
```

Repetir las variables `ARCA_CERT_EMPRESA_<id>_HOMOLOGACION` y `ARCA_KEY_EMPRESA_<id>_HOMOLOGACION` para cada emisor. Si `ARCA_HOMOLOGACION` no está definida, se usa homologación. Para producción se requieren certificados y autorización de producción y se configura `ARCA_HOMOLOGACION=0`; no cambiarlo para probar.

## Flujo y formato

- Se usa el SDK y el PDF de referencia de `Facturador` incluidos en la carpeta de documentación.
- Se congelan los datos del emisor, receptor y los renglones al iniciar la emisión. La reimpresión lee únicamente esa copia y los datos de autorización ARCA.
- La referencia del proyecto se muestra antes de la tabla cuando existe; el N.C.M. fijo `73.08.20.00` se conserva en cada renglón y en el PDF, pero no se envía como campo WSFE porque el servicio usado no define ese dato en la solicitud.
- Para las facturas en dólares, el PDF muestra el total convertido a pesos con la cotización guardada en la factura y ubica esa conversión dentro del panel de resumen impositivo. El resumen conserva una posición fija en la parte inferior, independientemente de la cantidad de renglones.
- El período del servicio se solicita para conceptos de servicios o mixtos. El vencimiento de pago también se informa para las Facturas de Crédito Electrónicas MiPyME de productos; para estas últimas puede proponerse según los días configurados en la condición de venta.
- Los importes de los renglones se consideran netos. Las alícuotas de los conceptos contables se agrupan antes de redondear; se soportan 2,5%, 5%, 10,5%, 21% y 27%. En facturas A/B se bloquea una tasa 0% hasta que exista un mapeo que la distinga de importes exentos o no gravados.
- Se soportan facturas A, B y C en pesos (PES) y dólares (DOL). En moneda local no se solicita cotización y se guarda/informa 1; para dólares sí se requiere la cotización. Para C no se discrimina IVA. La letra debe concordar con la condición fiscal del emisor y, para A, el receptor debe ser Responsable Inscripto.
- El PDF sigue la estructura visual de las facturas de referencia, incorpora CAE, QR ARCA y una marca de agua en homologación. La letra queda libre de los separadores del encabezado; la cantidad y los importes usan dos decimales y la moneda se indica en los títulos de columna, no junto a cada cifra. El resumen y las leyendas fiscales ocupan zonas fijas inferiores; los renglones se compactan y, si no alcanzara el espacio, continúan en hojas adicionales. Solo se ofrece para comprobantes autorizados.
- Un corte de conexión después del envío deja el intento incierto. La acción de conciliación consulta el número reservado y solo recupera un CAE si los datos del comprobante coinciden; no reenvía una operación incierta.

## Alcance fiscal actual

Este flujo emite Facturas de Crédito Electrónicas MiPyME A/B/C (tipos 201/206/211), además de facturas comunes A/B/C. Para FCE se envían el vencimiento y los campos opcionales ARCA 2101 (CBU), 2102 (alias) y 27 (SCA/ADC). Las notas de crédito/débito y los tipos de exportación siguen fuera de alcance. Las retenciones sufridas al cobrar se conservan localmente, pero no se envían como tributos ni reducen el total fiscal del comprobante. Un régimen asociado a una retención se trata igual solo cuando coinciden su descripción, código y artículo; otros regímenes siguen bloqueados hasta configurar su clasificación y código ARCA. Las percepciones que integren el comprobante aún requieren un mapeo fiscal explícito. Tampoco se distinguen todavía importes exentos o no gravados de una alícuota gravada al 0%.

Los catálogos y certificados deben validarse con ARCA para cada CUIT y punto de venta. Homologación es el valor seguro por defecto; habilitar producción requiere completar previamente esas verificaciones.

## Configuración por empresa

Desde **Empresas**, seleccione una empresa y abra **Configurar ARCA**. El catálogo inicial de tipos comunes y de factura de crédito MiPyME se carga localmente para poder configurarlo sin conectarse a ARCA. También se pueden importar los puntos de venta y actualizar el catálogo general mediante los métodos `GetSalesPoints()` y `GetVoucherTypes()` del SDK. La consulta remota requiere `FACTURACION_ARCA_HABILITADA=1`, SOAP y el certificado de la empresa configurado; los puntos importados se agregan solo cuando el usuario los elige y guarda.

El método del SDK para tipos no recibe un punto de venta: devuelve el catálogo general del servicio, no los tipos habilitados para cada punto. Por eso la ficha permite asociar manualmente tipos a cada punto. El formulario de factura sugiere los puntos guardados y la validación previa a emitir comprueba la asociación local de punto y tipo. El punto de venta 12 de NH Construcciones quedó configurado localmente para Factura A y Factura B; las facturas C requieren un emisor con condición fiscal habilitada para ese tipo. La consulta del último número autorizado y la emisión/conciliación siguen requiriendo comunicación con ARCA.

La ficha también permite marcar a la empresa como agente de retención y guardar una URL por cada provincia aplicable. Al desmarcar el indicador se eliminan las asociaciones provinciales. Para FCE, permite guardar el CBU y alias predeterminados del emisor. Los días de vencimiento por condición de venta se mantienen desde **Configurar días de vencimiento**, disponible en el formulario de factura.

La emisión usa el flujo explícito del SDK: consulta `GetLastVoucher()`, reserva y guarda durablemente el número siguiente junto con la solicitud, y luego llama a `CreateVoucher()`. Si una emisión de homologación queda incierta, la factura ofrece una acción explícita de reintento. Primero consulta `GetVoucherInfo()` para el número reservado; si ya existe, intenta conciliarlo y no emite de nuevo. Si no existe, vuelve a consultar `GetLastVoucher()` y solo llama a `CreateVoucher()` con la solicitud y el mismo número reservado cuando este sea exactamente el siguiente número esperado. Si la numeración avanzó o no pudo verificarse, no reenvía y deja el intento para conciliación. Un rechazo explícito 10242 por condición IVA del receptor también permite un reintento de homologación: se toma el ID ARCA del snapshot fiscal guardado, se preserva la solicitud rechazada en el historial y se corrige solo ese campo. Estos reintentos solo están disponibles en homologación. En producción se debe conciliar el estado incierto y no usar este reintento.

La unicidad fiscal local bloquea números pendientes, enviando, inciertos y autorizados, pero no mantiene ocupado un número rechazado explícitamente por ARCA: un rechazo sin CAE no avanza `GetLastVoucher()` y el siguiente envío puede necesitar reutilizar ese mismo número después de corregir los datos. El intento rechazado se conserva para auditoría. Si la reserva falla localmente antes de enviar, el mismo intento se puede volver a procesar una vez corregida la restricción; se vuelve a consultar `GetLastVoucher()` y no se conserva un número que nunca se reservó.

En una base existente, aplicar una sola vez `migracion_facturacion_arca_rechazos_reutilizables.sql`. Las instalaciones nuevas obtienen este índice desde `facturacion_arca.sql`.

## PDF y copias

La copia local y el comprobante fiscal autorizado se imprimen como **Original**, **Duplicado** y **Triplicado**, siguiendo la estructura de los modelos de `Facturador`. El encabezado identifica el código ARCA a partir del tipo y la letra: factura A/B/C/E, notas de débito/crédito y factura de crédito electrónica MiPyME. El detalle A discrimina IVA por renglón; B/C/E usa las columnas de bonificación y subtotal del modelo sin exponer una alícuota en cada renglón. La copia local mantiene en las tres hojas la leyenda de documento interno sin validez fiscal.
