# Modelo de referencia de facturación ARCA

Recopilación: 16/09/2026. Material de lectura para diseñar la facturación del sistema destino. Las copias no están adaptadas al SQL nuevo ni preparadas para ejecutarse.

## Contenido

| Carpeta | Origen y propósito |
| --- | --- |
| Facturador | Archivos de facturación de C:/xampp/htdocs/facturador: carga, emisión, consulta, NC y PDF base. |
| Rockemback | Referencia principal de RI, de C:/xampp/htdocs/carniceriaRockembach: ventas, IVA, tributos y PDF. |
| Maury | Referencia secundaria de RI, de C:/xampp/htdocs/MauryDistribuciones. |
| afip | Única copia del SDK, tomada de Facturador/vendor/afip, con sus clases y WSDL. |
| sql | Propuesta de cuatro tablas nuevas, pendiente de integración con facturas_venta. |

Se conservan los archivos propios del circuito y sus funciones auxiliares como referencias históricas. Facturador usa nuevoCobro.php como entrada equivalente a nuevaVenta.php. Los archivos config_facturacion_electronica.php son ejemplos sin credenciales, creados para documentar opciones de homologación; no son las configuraciones originales.

No se incluyen assets, Bootstrap, FPDF, phpqrcode, conexiones de base de datos, encabezados, menús ni pies compartidos. Sí se conservan los archivos propios que construyen los PDFs, porque su estructura es parte de la referencia.

**Los includes y rutas originales se mantienen para entender el código de origen, aunque apunten a archivos ausentes o a vendor/afip.** Al estudiar una llamada al SDK, consultar la carpeta afip de la raíz. No se adaptaron rutas para ejecutar las copias. Las bibliotecas auxiliares se identificarán y resolverán en el proyecto destino cuando se integre.

## Recorrido sugerido

1. Leer decisiones_y_pendientes.md.
2. Seguir Facturador/nuevoCobro.php e informarFacturaARCA.php; consultar afip/Class/ElectronicBilling.php.
3. Comparar Rockemback/nuevaVenta.php para IVA y tributos, usando Maury como segunda referencia.
4. Revisar Facturador/generarNCCobro.php y consultarFacturaARCA.php.
5. Comparar Facturador/Facturas/facturaCobro.php con Rockemback/factura.php.
6. Leer sql/LEEME.md antes de diseñar la integración.

## Alcance y límites

Las copias conservan las convenciones y limitaciones originales. Las ramas de emisión múltiple B a consumidor final, cuentas corrientes y reglas particulares de cada negocio se conservan como contexto, pero no integran el alcance del nuevo sistema. funciones.php también puede contener utilidades ajenas al proceso: se conserva su procedencia, sin presentar todas sus funciones como necesarias.

El SDK elegido es el de Facturador; no se conservaron copias alternativas de los otros proyectos ni se afirma que sus versiones sean idénticas. No se actualizó la biblioteca. Revisar compatibilidad y WSDL al implementar los nuevos casos.

El usuario agregó material de homologación después de la recopilación inicial. Se conserva sin modificaciones; no se validó su vigencia, titularidad ni autorización ante ARCA. El agente no generó certificados. Las rutas de los ejemplos siguen siendo orientativas.

La exportación a Bejerman queda fuera. No se modificaron los proyectos originales ni se ejecutó el SQL. El .htaccess de la raíz se conserva para impedir acceso HTTP cuando Apache respeta esa configuración.

manifiesto_copias.json indica origen, ubicación actual y SHA-256 de las copias conservadas. Los ejemplos y documentos nuevos no forman parte de ese manifiesto. VERIFICACION.md registra las comprobaciones y sus límites.

Se excluyeron los listados de ventas/cobros y sus AJAX, los detalles verVenta.php/verCobro.php y las acciones eliminarCobroSinFactura.php/anularVenta.php. Sus reglas comerciales relevantes se documentan sin conservar esas pantallas.

