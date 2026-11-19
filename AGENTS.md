# Convenciones de interfaz

- Al crear tablas nuevas, aplicar el estilo y la configuración de DataTables que utiliza el resto del sistema. Reutilizar los patrones existentes en lugar de introducir un estilo distinto.
- Los botones de acción asociados a una tabla (por ejemplo, modificar una factura o aprobar) deben mantener la coherencia visual con el resto de la aplicación. Ubicarlos ordenados a la izquierda, junto al título de la tabla, a su derecha.
- Para acciones e iconos, reutilizar los iconos y estilos ya presentes en otras partes de la aplicación. Mostrar iconos sin texto visible; usar `title` o tooltip para describir la acción cuando corresponda.
- Mantener la coherencia con el estilo y los patrones del código existente. En PHP, evitar encadenar o repetir `echo` constantemente para generar HTML; seguir el patrón de salida que use la página o módulo correspondiente.
- Al renderizar filas de tablas u otros bloques HTML en PHP, preferir alternar bloques PHP/HTML y usar `<?= htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8') ?>` para los valores dinámicos, en lugar de encadenar un `echo` por etiqueta o celda. Mantener siempre el escape adecuado al contexto.
- NO ejecutar consultas sql, colocarlas en el archivo consultas.sql y avisar debidamente.
