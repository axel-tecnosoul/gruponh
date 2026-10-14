# Decisiones y experiencia para la integración futura

## Alcance acordado

Base de proceso: Facturador. Referencia principal de responsables inscriptos: carniceriaRockembach, conservada en Rockemback. Maury es secundaria. Dos empresas RI, operaciones nacionales en pesos y USD, emisión individual y notas de crédito/débito con importes propios. El paquete es documental, no un componente ya integrado.

Eliminar del diseño futuro la partición de una operación en múltiples facturas B a consumidor final sin destinatario. Las copias históricas conservan esa lógica para mantener su procedencia, no para adoptarla. Tampoco trasladar automáticamente actualización de precios, stock o cuentas corrientes del comercio de origen.

La exportación Bejerman queda fuera. El sistema destino ya posee facturas_venta, conceptos contables, ítems internos y una tabla de regímenes especiales cuyo nombre/esquema debe confirmarse. No recrear esas entidades ni asumir correspondencias de IDs. El Concepto de WSFE (productos/servicios/ambos) no es un concepto contable de Bejerman.

## Datos históricos del comprobante y PDF

Guardar una copia de los datos tal como estaban al emitir: razón social, domicilio, CUIT, condición fiscal del emisor y receptor, IIBB, inicio de actividades y renglones con sus descripciones/importes.

Ejemplo: una factura se emite al cliente con domicilio A. Un mes después su ficha cambia a domicilio B. La reimpresión de aquella factura debe mostrar A. Debe leer los datos guardados del comprobante, no reconstruirlos desde la ficha actual. Lo mismo vale para el emisor, descripciones de ítems y condición fiscal.

El PDF de Facturador es la base visual preferida. Sus consultas actuales todavía utilizan datos actuales de clientes/emisores; copiar el archivo no implementa esta mejora. El esquema nuevo ofrece columnas fiscales y copias JSON de datos históricos; definir y versionar el contenido JSON al integrar (incluyendo renglones) y congelarlo al autorizar.

Pendientes del PDF: variantes factura/NC/ND, desglose de múltiples alícuotas de IVA, tributos y subtotales, moneda y cotización, fechas del servicio, comprobantes asociados, paginación de detalles largos, redondeos coherentes y QR construido con los datos fiscales autorizados. Mantener la fecha enviada, guardada, impresa y del QR consistente.

En Facturador la fecha elegida se guarda, pero el envío utiliza el día actual. En la nueva integración debe existir una única fecha de comprobante validada, no dos fechas con distinto origen.

## NC y ND con importes propios

La NC de Facturador consulta el original y replica importes: sirve como ejemplo de anulación total, no de ajuste parcial.

- Preparar renglones, importes, IVA y tributos propios del ajuste. No copiar automáticamente el total original.
- Una NC parcial no anula toda la venta. No trasladar la regla actual de cobro anulado por cualquier NC autorizada.
- Permitir varias NC/ND para un original cuando el circuito y reglas fiscales lo permitan; no aplicar la restricción actual de una sola NC por factura.
- Contemplar uno o varios comprobantes asociados; tabla comprobantes_arca_asociados.
- Asociar únicamente comprobantes emitidos dentro del nuevo sistema. Guardar ambas referencias obligatorias a comprobantes_arca, sin duplicar CUIT, tipo, punto de venta, número ni fecha del original; obtener esos datos de su registro al preparar el envío o el PDF. Impedir repetir la misma asociación.
- Definir con el sistema destino controles sobre ajustes acumulados y saldos. No inventar una regla universal para una ND ni asumir que todos los ajustes se comparan solo por importe total.
- Definir tratamiento de moneda/cotización, períodos y motivos; validar con normativa y WS vigentes al implementar.
- La tabla de asociaciones no asigna importes a cada original: si el destino necesita imputación monetaria por asociado, diseñarla expresamente. No es automáticamente un campo del WS.

## 4D: guardar el número antes de emitir

Ejemplo: ARCA tiene como última factura la 150. El sistema prepara la 151, la envía y ARCA la autoriza. Se corta la conexión antes de recibir el CAE. ARCA tiene el comprobante, pero el sistema no sabe si se emitió.

Guardar antes «para esta operación intenté autorizar la 151» permite consultarla exactamente. No evita el corte; permite recuperar el resultado. Sin ese dato se dificulta identificar el comprobante, especialmente si hubo otras emisiones.

Secuencia propuesta:

1. Tomar un bloqueo por ambiente + CUIT emisor + punto de venta + tipo.
2. Comprobar que la operación no está autorizada ni tiene un envío incierto.
3. Obtener GetLastVoucher() y calcular el siguiente. No sustituirlo por MAX de la base local.
4. Guardar el número propuesto y la solicitud con CbteDesde/CbteHasta, junto con estado de envío, antes de contactar al servicio de autorización. Confirmar esa escritura local.
5. Enviar con CreateVoucher(), sin volver a calcular el siguiente dentro del SDK.
6. Guardar CAE, vencimiento y estado. Liberar el bloqueo en todos los caminos.

CreateNextVoucher() actualmente hace la consulta y suma internamente; por eso hay que separar sus pasos para persistir antes. GenerarNCCobro.php ya reserva el número antes de enviar, pero aún falta coordinación entre operaciones distintas que comparten numeración.

El número propuesto no significa autorizado. La unicidad en SQL evita duplicados locales pero no reemplaza el bloqueo. El bloqueo local tampoco coordina otro programa externo: revisar si el punto de venta es exclusivo y conciliar conflictos. Si un número propuesto pertenece a otra operación, no sobreescribir el registro autorizado ni avanzar a ciegas; conservar el intento y resolver su identidad.

## 4F: conciliar un resultado incierto

Para la factura 151 del ejemplo:

1. Consultar GetVoucherInfo(151, punto de venta, tipo) con el mismo emisor y ambiente.
2. Si existe, comparar receptor, fecha, importes desglosados, moneda/cotización y demás datos relevantes; para ajustes, también asociaciones.
3. Si corresponde a la operación, recuperar CAE/vencimiento/estado en una transacción local, sin emitir otra factura.
4. Si existe pero los datos difieren, mantener pendiente para revisión; no asociar el CAE de otro comprobante.
5. Si ARCA responde que no existe, evaluar un nuevo envío solo tras descartar un proceso aún en curso y verificar de nuevo la numeración y la solicitud.
6. Si la consulta falla, seguir en estado incierto. Error de red no equivale a comprobante inexistente.

No emitir una NC para arreglar una inconsistencia puramente local sin determinar antes qué autorizó ARCA. ConsultarFacturaARCA.php muestra información y comparaciones de CAE/IVA, pero NO implementa esta recuperación ni compara todos los campos necesarios.

No se requiere guardar todos los reintentos o toda la respuesta. Guardar último intento, número propuesto, solicitud, error y resultado simplificado. No reemplazar un intento incierto con una solicitud nueva hasta conciliarlo. Registrar fecha y usuario de la conciliación. Validar importes según precisión/moneda, no comparar floats por igualdad arbitraria.

Los estados del SQL son una propuesta de aplicación: pendiente, enviando, autorizado, rechazado e incierto. No son los códigos literales de ARCA. Una excepción genérica no prueba rechazo: puede haberse autorizado. El cambio de estados y las restricciones deben implementarse en PHP; el DDL solo define almacenamiento.

## IVA, tributos, moneda y configuración

Rockemback agrupa IVA por alícuota y guarda factura_iva/factura_tributos. Reutilizar el criterio revisando sus cálculos, supuestos de precios con IVA, redondeos y reglas específicas de percepciones. No copiar porcentajes, mínimos ni IDs sin validar el régimen/catálogo aplicable.

Definir configuración por emisor, ambiente y punto de venta; conservar los datos históricos del comprobante. Facturador actualmente fija C, servicios y PES. Las copias RI revisadas también fijan PES. USD, CanMisMonExt, períodos del servicio y NC/ND parciales requieren implementación y pruebas, no quedan resueltos copiando esos archivos.

Percepciones que integran el comprobante se reflejan en Tributos cuando corresponda. Retenciones sufridas al cobrar no reducen el total fiscal ni se agregan como tributos de la factura. Si solo se registra el cierre, separar dinero recibido, retenciones documentadas y saldo. Ser RI no implica ser agente; contemplar régimen, jurisdicción y vigencia por empresa.

El cliente quiere mantener resultados simplificados. No ampliar respuesta_arca al XML completo por defecto. CondicionIVAReceptorId se verifica con consulta posterior a ARCA; un valor local no prueba su recepción. Una respuesta sin el campo tampoco demuestra por sí sola que nunca se envió.

## Evolución hacia módulo común

Hacerla al integrar con el primer sistema RI: el destino prepara una operación; el componente común valida técnicamente, numera, emite, consulta y recupera; el destino gestiona efectos comerciales. Mantener un mecanismo sencillo de adaptación de sesión, conexión, catálogos e IDs. No imponer el esquema de ventas de otro proyecto ni crear una arquitectura general antes de conocer esos contratos.

## Validaciones futuras

Probar RI, monedas, varias alícuotas, tributos, fechas, comprobantes asociados, NC parcial y ND, redondeos, doble envío, dos operaciones simultáneas, corte después de autorización, fallo al persistir CAE, consulta inexistente y consulta fallida. Renderizar PDFs largos y cortos. La copia histórica de Maury/Rockemback usa una transacción local que incluye el envío remoto: un rollback de SQL NO revierte lo emitido en ARCA; adoptar guardado previo durable del modelo nuevo.

## Reglas conservadas de pantallas excluidas

Los listados, detalles y acciones comerciales de eliminación/anulación se excluyen de la referencia. Al integrar, mostrar acciones según el estado fiscal y volver a validar su disponibilidad en el servidor. No permitir eliminar una operación con comprobante autorizado ni tratar una anulación comercial como anulación fiscal. Si el envío es incierto, consultar y conciliar antes de permitir nuevas acciones. Una NC parcial no implica cancelar toda la venta.

