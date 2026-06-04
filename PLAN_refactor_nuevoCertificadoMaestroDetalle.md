# Plan de refactor y avance - nuevoCertificadoMaestroDetalle

## Estado actualizado (2026-06-03)
- Estado general: en avance alto.
- Ya implementado: flujo principal de alta/edicion por lote, validaciones de incidencia, persistencia por submit, recarga de pagina, autocompletado de grilla al modificar, y soporte de columnas nuevas de lote/aperturado.
- Pendiente principal: consolidar trazabilidad exacta de membresia lote<->item OCC mediante tabla N:N y conectar lectura/escritura a ese modelo.
- Limpieza pendiente posterior: revisar y eliminar columnas o dependencias redundantes solo despues de validar en produccion el uso de la tabla N:N.

## IMPORTANTE (obligatorio antes de empezar)
Antes de implementar cualquier cambio, se debe hacer una copia de respaldo de CADA archivo (a excepcion del de consultas) a modificar.

Sugerencia en Windows (PowerShell), por cada archivo:

```powershell
Copy-Item .\nuevoCertificadoMaestroDetalle.php .\nuevoCertificadoMaestroDetalleOriginal.php
Copy-Item .\eliminarDetalleCertificadoMaestro.php .\eliminarDetalleCertificadoMaestroOriginal.php
```

Tambien se recomienda respaldar la base de datos antes de correr cualquier ALTER TABLE.

## Objetivo
Reemplazar el alta/edicion secuencial de detalles de Certificado Maestro por un flujo con:
- seleccion multiple de items de la OCC del certificado,
- configuracion de imputacion por proyecto,
- modo agrupado/no agrupado,
- aperturado en grilla por incidencias,
- visualizacion clara de agrupados en bloque consecutivo (tipo corchete/llave),
- tipo de item fijo interno en Avance.

## Decisiones funcionales ya definidas
- Los items OCC se toman automaticamente desde la OCC del certificado maestro.
- La suma de incidencias debe ser 100% para guardar.
- En modo no agrupado se replica el mismo aperturado por cada item OCC seleccionado.
- En modo agrupado se aplica siempre a todos los items OCC seleccionados.
- Un lote de aperturado puede relacionarse con uno o varios items OCC (modelo N:N explicito).
- El rediseno incluye alta y edicion.
- Se elimina la seleccion manual de tipo de item; se guarda siempre como Avance.
- Alta y modificacion se resuelven con submit al servidor y recarga de la misma pagina (sin guardado dinamico en cliente).

## Fase 1 - Datos y trazabilidad [COMPLETADA]
1. [x] Agregar columnas en certificados_maestros_detalles:
   - [x] id_occ_detalle (nullable)
   - [x] incidencia_porcentaje (double)
   - [x] monto_base_occ (double)
   - [x] lote_aperturado (varchar)
   - [x] modo_generacion
2. [x] Registrar ALTER TABLE idempotentes en consultas.sql.
3. [x] Mantener id_tipo_item_certificado obligatorio y forzarlo a 1 (Avance) en backend.

## Fase 1B - Tabla N:N lote-item OCC [EN CURSO]
1. [x] Definir tabla intermedia `certificados_maestros_lotes_occ_detalle` en consultas.sql.
2. [x] Incluir migracion para poblar membresias desde datos existentes:
   - [x] exacta cuando `id_occ_detalle` ya existe,
   - [x] backfill para agrupados historicos tomando todos los items de la OCC del certificado.
3. [ ] Adaptar alta/edicion para persistir membresia SIEMPRE en tabla N:N (agrupado y separado).
4. [ ] Adaptar lectura de lotes para que `occ_ids` salga de la tabla N:N y no de inferencia indirecta.
5. [ ] Adaptar eliminacion de lote para limpiar membresias en tabla N:N.

## Fase 2 - Carga y visualizacion de items OCC [PARCIAL]
1. [x] En nuevoCertificadoMaestroDetalle.php, resolver id_occ desde certificados_maestros.id_occ usando id_certificado_maestro.
2. [x] Mostrar tabla de occ_detalles de esa OCC con seleccion multiple por click de fila.
3. [x] Mostrar base total seleccionada y bases individuales para calculo.
4. [x] En edicion, preseleccionar items OCC vinculados a lotes existentes y mostrar sus aperturados al cargar.
5. [~] Para lotes agrupados:
   - [x] renderizar los items OCC del grupo uno a continuacion del otro,
   - [x] aplicar realce visual de grupo (borde/color tipo corchete o llave),
   - [x] mostrar el aperturado comun del grupo luego del ultimo item OCC del bloque,
   - [ ] terminar de respaldar el agrupamiento exclusivamente con membresia N:N persistida.
6. [~] Para lotes separados, mantener visualizacion por item OCC individual (usar membresia N:N como fuente unica al cerrar Fase 1B).

## Fase 3 - Configuracion de imputacion [COMPLETADA]
1. [x] Mantener selector de proyecto unico para la operacion.
2. [x] Selector de modo:
   - [x] Agrupar: base = suma de subtotales OCC seleccionados.
   - [x] No agrupar: mismo aperturado aplicado por cada item OCC.
3. [x] Quitar selector Tipo Item visible de la UI (se mantiene hidden forzado).
4. [x] Mantener en tabla OCC solo accion de mostrar/ocultar desglose por item.

## Fase 4 - Aperturado en tabla dinamica [COMPLETADA]
1. [x] Reemplazar campos sueltos por una grilla de filas.
2. [x] Columnas editables por fila:
   - [x] descripcion
   - [x] unidad
   - [x] cantidad
   - [x] incidencia (%)
3. [x] Columnas calculadas:
   - [x] precio unitario incidencia
   - [x] total fila
4. [x] Validacion bloqueante: suma de incidencias = 100.
5. [x] Formula de calculo:
   - [x] total_fila = base * incidencia / 100
   - [x] precio_unitario_fila = total_fila / cantidad (cantidad > 0)

## Fase 5 - Persistencia por submit y recarga [COMPLETADA]
1. [x] Guardar en transaccion via submit tradicional del formulario.
2. [x] Forzar id_tipo_item_certificado = 1 (Avance).
3. [x] Modo agrupado:
   - [x] insertar una tanda de filas segun aperturado.
4. [x] Modo no agrupado:
   - [x] replicar la tanda por cada item OCC seleccionado,
   - [x] recalcular por base individual,
   - [x] guardar id_occ_detalle correspondiente.
5. [x] Actualizar solo monto_acumulado_avances.
6. [x] Registrar log con modo y cantidad de filas creadas.
7. [x] Redirigir/recargar pagina luego de alta/modificacion (flujo por submit activo).

## Fase 6 - Rediseno de edicion [COMPLETADA]
1. [x] Editar por lote_aperturado (conjunto de filas) en lugar de solo fila individual.
2. [x] Cargar lote desde boton Modificar y autocompletar toda la grilla (descripcion, unidad, cantidad, incidencia).
3. [x] Recalcular y guardar en bloque mediante submit al servidor.
4. [x] Ajuste de acumulado:
   - [x] restar subtotal previo del lote,
   - [x] sumar subtotal nuevo del lote.
5. [x] Tras guardar, recargar pagina y mantener continuidad de operacion.
6. [x] Fallback para registros viejos sin lote_aperturado: edicion simple (via flujo legacy por id detalle).

## Fase 7 - Eliminacion y consistencia [PARCIAL]
1. [~] Revisar eliminarDetalleCertificadoMaestro.php para decremento correcto de acumulados (incluye eliminacion por lote_aperturado; falta limpieza de tabla N:N).
2. [~] Confirmar compatibilidad con listados/ver/imprimir existentes (validacion funcional completa aun pendiente).

## Fase 8 - Limpieza y retiro de legado [PENDIENTE]
1. [ ] Confirmar que toda la lectura/escritura de membresias lote<->items OCC usa exclusivamente `certificados_maestros_lotes_occ_detalle`.
2. [ ] Verificar que no queden dependencias funcionales de `id_occ_detalle` en `certificados_maestros_detalles` para determinar pertenencia de grupo.
3. [ ] Si la validacion es exitosa, evaluar retiro de columnas o rutas legacy que ya no aporten valor operativo.
4. [ ] Antes de eliminar cualquier columna legacy, respaldar y documentar el script de limpieza para rollback.

## Verificacion funcional
1. Agrupado: N items OCC + M filas aperturado => M filas creadas.
2. No agrupado: N items OCC + M filas aperturado => N x M filas creadas.
3. Verificar que los nuevos detalles queden en Avance.
4. Verificar impacto exacto en monto_acumulado_avances.
5. Verificar que en agrupado los items del grupo se vean consecutivos con realce visual y aperturado al final del bloque.
6. Verificar edicion de lote desde Modificar con autocompletado total de grilla.
7. Verificar recarga correcta de la misma pagina luego de alta/modificacion.
8. Validar errores esperados (incidencia != 100, sin items OCC, sin filas, etc.).
9. Verificar que un lote agrupado con multiples grupos coexistentes conserve exactamente su conjunto OCC al recargar/editar.
10. Verificar que un lote separado tenga 1:1 en tabla N:N y renderice solo en su item.
11. Verificar que al eliminar lote se eliminen tambien sus membresias N:N.

## Pendientes concretos (siguiente iteracion)
1. Conectar nuevoCertificadoMaestroDetalle.php a la tabla N:N para escritura/lectura de membresias por lote.
2. Conectar eliminarDetalleCertificadoMaestro.php para borrar membresias de la tabla N:N al eliminar lote.
3. Validar integridad de migracion historica y ajustar casos puntuales donde agrupados legacy requieran correccion manual.
4. Al cerrar la migracion, preparar script de limpieza para columnas legacy redundantes, dejando primero solo trazabilidad funcional probada.

## Archivos esperados a modificar
- nuevoCertificadoMaestroDetalle.php
- eliminarDetalleCertificadoMaestro.php
- consultas.sql

Nota: si durante la implementacion aparece la necesidad de tocar archivos adicionales, agregar esos archivos a la lista y respaldarlos antes de modificarlos.
