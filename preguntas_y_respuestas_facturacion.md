# Preguntas y respuestas de facturación

## ¿“Contribuciones patronales” es una retención sufrida al cobrar o un tributo/cargo que debe integrar la factura?

**Pendiente de confirmar.** Para habilitar temporalmente pruebas de homologación, se tratará como una retención sufrida al cobrar: se conserva en las tablas locales de retenciones y no se envía a ARCA como tributo ni se incluye en el total fiscal del comprobante. La documentación de integración distingue las retenciones sufridas al cobrar de las percepciones que integran el comprobante.

En la factura local ID 67 el concepto aparece como “CONTRIBUCIONES PATRONALES”, con porcentaje 5% e importe negativo. También figura como régimen seleccionado, sin un importe adicional independiente. La emisión permitirá esa pareja de datos locales y la dejará fuera del snapshot fiscal; los regímenes seleccionados que no correspondan a una retención guardada seguirán bloqueados hasta mapearlos.

Antes de usar esta interpretación en producción, confirmar con administración/contabilidad si es una retención al momento del pago, una percepción/cargo cobrado al cliente o un concepto de otra naturaleza. Si integra el importe fiscal de la factura, hay que identificar su código ARCA, base, alícuota y tratamiento correcto antes de emitir.

## ¿Qué hacer cuando el envío queda “incierto”, con el número reservado y sin CAE?

**No volver a emitir ni guardar otra factura con esos datos hasta conciliar el intento existente.** El 06/10/2026, la factura local ID 74 quedó en estado `incierto`, en homologación, con el punto de venta 12, tipo de comprobante ARCA 1 y número reservado 1. Una consulta `GetVoucherInfo` no encontró ese número en ARCA y `GetLastVoucher` informó 0. No se hizo un segundo envío. La fila local conserva el número 1 y el estado incierto.

La consulta del servicio no mostró autorización en ese momento, pero se debe revisar también el portal de homologación antes de decidir un reintento. Si el portal muestra el comprobante, usar la acción **Consultar y conciliar con ARCA**; no volver a enviarlo. Si el portal confirma que no existe y no hay una operación todavía procesándose, resolver el intento reservado según el procedimiento operativo y verificar nuevamente el punto de venta/numeración antes de cualquier reenvío. Un resultado de consulta vacío o un error de red, por sí solos, no autorizan a crear un número nuevo ni a dar por rechazado el comprobante.

El envío original no conservó el detalle técnico de la excepción, por lo que no se puede distinguir retrospectivamente si fue una falla de transporte o una respuesta de rechazo del servicio. El código ahora conserva el mensaje técnico de excepciones futuras en el error del intento, manteniendo el estado incierto y sin reintentar automáticamente.
