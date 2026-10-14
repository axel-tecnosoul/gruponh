# Esquema propuesto

`crear_comprobantes_arca.sql` crea cuatro tablas nuevas en MySQL/MariaDB con InnoDB. No fue ejecutado contra ninguna base. No contiene DROP ni IF NOT EXISTS: una tabla existente debe provocar un error visible, no ocultar un esquema incompatible.

## Relaciones

- `comprobantes_arca.id_factura_venta` identifica la operación comercial de destino. Su tipo BIGINT UNSIGNED es provisional: debe coincidir con la clave real de `facturas_venta`. La clave foránea externa se agregará al integrar, una vez conocido ese esquema. La relación permite varios comprobantes por operación.
- `_iva` guarda bases e importes agrupados por código de alícuota ARCA.
- `_tributos` admite varios renglones, incluso del mismo código. `referencia_regimen` es una referencia orientativa al régimen de origen, pendiente de mapear a las tablas reales; no inventa una relación con una tabla desconocida.
- `_asociados` admite varios originales por comprobante y contiene únicamente `id`, `id_comprobante_arca` e `id_comprobante_asociado`. Ambas referencias son obligatorias y apuntan a `comprobantes_arca`; la combinación es única para evitar asociaciones repetidas. Solo se emitirán NC/ND sobre comprobantes emitidos dentro del nuevo sistema. El CUIT, tipo, punto de venta, número y fecha del original se obtienen de su fila en `comprobantes_arca` al preparar la solicitud a ARCA o el PDF. No representa una distribución de importes entre originales.

## Identidad, números y duplicados

`homologacion` debe valer 1 para pruebas y 0 para producción. La identidad fiscal incluye ambiente, CUIT, punto de venta, tipo y número. El número es NULL antes de asignarlo y debe persistirse antes del envío. No es el ID autoincremental.

`clave_operacion` la genera la aplicación para una intención concreta de emisión y la reutiliza ante dobles clics o reenvíos de esa misma intención. Cada NC/ND nueva tiene su propia clave. No usar simplemente el ID de venta. Su comparación usa la colación de la tabla: conviene usar UUID normalizado a minúsculas.

Los índices únicos son una última defensa; no reemplazan el bloqueo por ambiente/CUIT/punto de venta/tipo ni la consulta y conciliación explicadas en decisiones_y_pendientes.md. Un intento rechazado conserva su número propuesto hasta resolver explícitamente su siguiente acción; no crear otra fila con esa identidad. Si otro proceso ocupó el número, conciliar y resolver manualmente la identidad del intento antes de intentar emitir nuevamente. No sobrescribir evidencia de un envío incierto.

## Datos históricos y respuesta

`datos_emisor`, `datos_receptor` y `detalle_comprobante` contienen JSON validado por la aplicación, con contrato identificado por `version_datos`. Se usa LONGTEXT para no depender de las diferencias de JSON entre versiones MySQL/MariaDB. Incluir los datos imprimibles del emisor/receptor y los renglones con descripción, cantidad, unidad, precio, descuentos y tratamiento de IVA. Definir ese contrato al integrar: no duplicar datos incompatibles con las columnas fiscales.

Estos datos se congelan al autorizar: reimprimir no debe consultar nombres, domicilios o precios actuales para sustituir los históricos. El detalle del sistema destino sigue siendo su origen comercial; esta copia conserva lo efectivamente emitido.

`solicitud_arca` conserva el cuerpo de negocio preparado para el envío, sin Auth, Token, Sign ni credenciales. `respuesta_arca` puede guardar solo el resumen elegido (CAE, vencimiento y número); no se exige respuesta completa. Errores y observaciones relevantes deben conservarse en `ultimo_error` o en un resumen estructurado según su naturaleza. No descartar observaciones de una autorización exitosa. Los campos individuales siguen siendo la fuente para consultas; validar que coincidan con el resumen.

## Validaciones pendientes en la aplicación

El esquema es almacenamiento, no un motor de reglas fiscales. Validar catálogos y campos obligatorios para cada caso contra el servicio vigente antes de emitir. En particular:

- Fechas, documento, condición IVA, tipo/concepto, moneda y cotización; `cancela_misma_moneda` se mapea al campo del servicio únicamente cuando corresponde.
- Importes y redondeos con aritmética decimal; total coherente con neto, no gravado, exento, IVA y tributos. Verificar bases y alícuotas aplicables. Los importes están expresados en la moneda del comprobante.
- Transiciones de estado, CAE y vencimiento requeridos al autorizar; inmovilizar datos fiscales y sus tablas hijas luego de la autorización.
- Asociaciones sin autorreferencias, ambiente/emisor compatibles y originales autorizados dentro del nuevo sistema. Validar fechas y reglas de asociación según tipo de comprobante. Las claves foráneas garantizan la existencia de ambas filas, pero estas reglas deben validarse en la aplicación.
- La NC/ND tiene importes propios. No asumir cancelación total ni distribuir su monto entre originales sin una regla comercial explícita.
- Sincronizar estado comercial y autorización en una transacción local corta. Un rollback SQL no revierte una autorización remota.

No se definen claves foráneas a usuarios, ítems ni regímenes porque su estructura es desconocida. `usuario_conciliacion` es una identificación de auditoría provisional. Revisar tipos, tamaños y precisión frente al motor y servicio reales antes de migrar. Las restricciones RESTRICT evitan cascadas accidentales; no impiden por sí solas editar o eliminar un comprobante autorizado sin hijos: esa protección debe implementarse en el servicio y permisos de acceso.
