# Preguntas y respuestas de facturación

## ¿“Contribuciones patronales” es una retención sufrida al cobrar o un tributo/cargo que debe integrar la factura?

**Pendiente de confirmar.** Para habilitar temporalmente pruebas de homologación, se tratará como una retención sufrida al cobrar: se conserva en las tablas locales de retenciones y no se envía a ARCA como tributo ni se incluye en el total fiscal del comprobante. La documentación de integración distingue las retenciones sufridas al cobrar de las percepciones que integran el comprobante.

En la factura local ID 67 el concepto aparece como “CONTRIBUCIONES PATRONALES”, con porcentaje 5% e importe negativo. También figura como régimen seleccionado, sin un importe adicional independiente. La emisión permitirá esa pareja de datos locales y la dejará fuera del snapshot fiscal; los regímenes seleccionados que no correspondan a una retención guardada seguirán bloqueados hasta mapearlos.

Antes de usar esta interpretación en producción, confirmar con administración/contabilidad si es una retención al momento del pago, una percepción/cargo cobrado al cliente o un concepto de otra naturaleza. Si integra el importe fiscal de la factura, hay que identificar su código ARCA, base, alícuota y tratamiento correcto antes de emitir.

## ¿Qué pasó con la factura 74 en homologación?

El 06/10/2026, la factura local ID 74 quedó inicialmente incierta, en homologación, con PV 12, tipo ARCA 1 y número local 1. Al reintentar se recibió un rechazo explícito de ARCA, código 10242: `Condicion IVA receptor no es un valor permitido`. La solicitud había enviado `CondicionIVAReceptorId=0`, aunque el snapshot fiscal del receptor contenía el mapeo ARCA `1`. La causa era un error local al guardar la etiqueta textual de condición IVA en una columna numérica.

Se corrigió el mapeo y se reintentó la misma factura en homologación mediante `CreateNextVoucher()`, sin consultar previamente `GetLastVoucher()` desde la aplicación. ARCA autorizó el comprobante número 1 y el CAE quedó guardado localmente. No se creó otra factura ni se reenvió a producción. El intento rechazado se conserva como parte del historial de respuesta.

La autorización anterior confirma que la condición IVA corregida fue aceptada. A partir del cambio del 06/10/2026, tanto las emisiones nuevas como los reintentos explícitos usan el flujo `GetLastVoucher()` → reserva durable del número → `CreateVoucher()`. Para un reintento de homologación, el sistema solo envía si el número reservado sigue siendo el próximo; si la numeración avanzó o no pudo consultarse, no reenvía y requiere conciliación.

El punto de venta 12 estaba asociado localmente solo a Factura A, por lo que la validación no dejaba emitir Factura B. Se agregó la asociación ARCA tipo 6 (Factura B) para ese punto de venta de prueba. La Factura C no se habilitó para NH Construcciones porque su condición fiscal configurada no corresponde a ese tipo. En los PDF fiscales se guarda y muestra la letra; para comprobantes históricos sin ese dato en el snapshot, se deriva del código de tipo ARCA.

## ¿Por qué el segundo intento quedó como “Conflicto de numeración local”?

La factura local ID 75 propuso el número ARCA 1 para el punto de venta 12 y tipo 1, pero la factura ID 74 ya conserva localmente ese número como incierto. La restricción única local impidió que ambas facturas reclamen la misma identidad fiscal; por eso la fila de la factura 75 quedó `rechazado` con el mensaje “No se envió a ARCA”. Este conflicto fue local, no un rechazo de ARCA.

Para repetir una prueba no se debe crear otra reserva con el mismo número en otra factura. La factura 74 ya está autorizada; no debe volver a emitirse. La factura 75 sigue rechazada localmente por conflicto de numeración y nunca fue enviada a ARCA.

## ¿Por qué Factura B volvió a chocar con el número 1?

El SDK de Facturador implementa `CreateNextVoucher()` como `GetLastVoucher() + 1` seguido por `CreateVoucher()`. `GetLastVoucher()` devuelve el último número autorizado, no el último número intentado. Por eso, después del rechazo explícito 10015 de la Factura B número 1 (factura local 100), el siguiente número ARCA para B seguía siendo 1. El código local calculaba correctamente el siguiente número, pero la antigua restricción SQL también retenía para siempre el número de intentos rechazados; la factura local 102 chocó con esa reserva y no llegó a enviarse.

Se cambió la restricción para que solo números pendientes, en envío, inciertos o autorizados sean únicos. Los intentos rechazados se conservan como historial, pero liberan el número fiscal para otro envío. La factura local 102 tenía un conflicto antes de enviar, así que su intento ahora se puede reintentar: volverá a consultar `GetLastVoucher()` y reservará el siguiente número antes de llamar `CreateVoucher()`. Este cambio corrige el bloqueo local; no corrige el rechazo ARCA 10015 ni garantiza que una Factura B con el CUIT de prueba sea aceptada.
