-- CORRECCIÓN 1: Normalizar los valores negativos de reservado a 0 por el tema de los negativos
UPDATE computos_detalle SET reservado = 0 WHERE reservado < 0;

-- CORRECCIÓN 2: Agregar el puerto de correo electrónico a la tabla parametros para el envío de emails.
INSERT INTO parametros (id, parametro, valor) VALUES (6, 'E-MAIL port', 587);

-- Nuevo estado para las compras.
INSERT INTO estados_compra (id, estado) VALUES (5, 'Superado');

ejecutar el .php llamado fix_nro_oc.php, http://localhost/gruponh/fix_nro_oc.php?ejecutar=1 para que modifique http://localhost/gruponh/fix_nro_oc.php para ver los cambios que haría

-- CORRECCIÓN 3: Modificar el tipo de dato de nro_remito a VARCHAR para permitir números de remito con guiones o letras, si es necesario.
ALTER TABLE `ingresos` CHANGE `nro_remito` `nro_remito` VARCHAR(99) NOT NULL;

-- CORRECCIÓN 4: Agregar una nueva columna para almacenar la ruta del documento asociado al ingreso, como el remito escaneado o factura.
ALTER TABLE `ingresos` ADD `ruta_documento` VARCHAR(500) NOT NULL AFTER `nro_remito`;

-- CORRECCIÓN 5: Permitir que la ruta del documento sea NULL en caso de que no se haya subido un documento asociado al ingreso.
ALTER TABLE `ingresos` CHANGE `ruta_documento` `ruta_documento` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;

ALTER TABLE `computos` ADD `fecha_hora_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `id_cuenta_valido`, ADD `fecha_hora_ultima_modificacion` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `fecha_hora_alta`;

-- CORRECCIÓN 6: Agregar una nueva columna para almacenar el perímetro de los materiales, si es relevante para el cálculo de costos o logística.
ALTER TABLE materiales ADD COLUMN perimetro DECIMAL(10,2) NULL;

ALTER TABLE materiales ADD `fecha_hora_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD `fecha_hora_ultima_modificacion` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `fecha_hora_alta`;

-- CORRECCIÓN 7: Colores para estados_pedidos_detalle (para usar en badges en el frontend)
ALTER TABLE `estados_pedidos_detalle` ADD COLUMN `color` VARCHAR(30) NULL DEFAULT NULL AFTER `descripcion`;

UPDATE `estados_pedidos_detalle` SET `color` = 'badge-secondary' WHERE `id` = 1; -- Pendiente
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-warning'   WHERE `id` = 2; -- Comprando
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-info'      WHERE `id` = 3; -- Comprando Parcial
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-primary'   WHERE `id` = 4; -- Comprando y Entregando
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-success'   WHERE `id` = 5; -- Comprando Total
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-success'   WHERE `id` = 6; -- Entregado Parcial
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-success'   WHERE `id` = 7; -- Entregado
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-danger'    WHERE `id` = 8; -- Cancelado

--- CORRECCIÓN 8: Agregar columnas subtotal y total separadas en compras_detalle.
ALTER TABLE compras_detalle ADD COLUMN total DOUBLE NOT NULL DEFAULT 0 AFTER subtotal;

UPDATE compras_detalle SET total = ROUND(subtotal, 2);

UPDATE compras_detalle SET subtotal = ROUND(CASE WHEN descuento > 0 AND descuento < 100 THEN subtotal / (1 - descuento / 100) ELSE subtotal END, 2) WHERE descuento > 0;

-- CORRECCIÓN 9: Agregar fecha y tipo de colada de origen en coladas, y vincular coladas internas a una colada de origen.
ALTER TABLE `coladas`
  MODIFY `id_compra` int(11) NULL,
  MODIFY `id_proveedor` int(11) NULL,
  ADD `fecha` DATE NULL,
  ADD `es_origen` TINYINT(1) NOT NULL DEFAULT 0;

--TODO APLICADO HASTA ACA

-- CORRECCIÓN 9: Registrar el porcentaje de anticipo en certificados maestros.
ALTER TABLE `certificados_maestros`
ADD COLUMN IF NOT EXISTS `porcentaje_anticipo` DOUBLE NOT NULL DEFAULT 0 AFTER `cotizacion_dolar`;

-- CORRECCIÓN 10: Fase 1 Certificado Maestro Detalle - trazabilidad de origen OCC y aperturado.
ALTER TABLE `certificados_maestros_detalles`
ADD COLUMN IF NOT EXISTS `id_occ_detalle` INT(11) NULL AFTER `id_certificado_maestro`;

ALTER TABLE `certificados_maestros_detalles`
ADD COLUMN IF NOT EXISTS `incidencia_porcentaje` DOUBLE NOT NULL DEFAULT 0 AFTER `subtotal`;

ALTER TABLE `certificados_maestros_detalles`
ADD COLUMN IF NOT EXISTS `monto_base_occ` DOUBLE NOT NULL DEFAULT 0 AFTER `incidencia_porcentaje`;

ALTER TABLE `certificados_maestros_detalles`
ADD COLUMN IF NOT EXISTS `lote_aperturado` VARCHAR(64) NULL AFTER `monto_base_occ`;

-- CORRECCIÓN 11: Fase 5 Certificado Maestro Detalle - persistir modo de generacion.
ALTER TABLE `certificados_maestros_detalles`
ADD COLUMN IF NOT EXISTS `modo_generacion` VARCHAR(20) NULL AFTER `lote_aperturado`;

-- Compatibilidad con registros existentes previos al aperturado.
UPDATE `certificados_maestros_detalles`
SET `modo_generacion` = 'legacy'
WHERE `modo_generacion` IS NULL OR `modo_generacion` = '';

UPDATE `certificados_maestros_detalles`
SET `lote_aperturado` = CONCAT('LEGACY-', `id`)
WHERE (`lote_aperturado` IS NULL OR `lote_aperturado` = '')
	AND `modo_generacion` = 'legacy';

-- CORRECCIÓN 12: Relación N a N entre lote de aperturado e items OCC.
-- Esta tabla permite conocer exactamente qué items OCC integra cada lote (agrupado o separado).
CREATE TABLE IF NOT EXISTS `certificados_maestros_lotes_occ_detalle` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`id_certificado_maestro` INT(11) NOT NULL,
	`lote_aperturado` VARCHAR(64) NOT NULL,
	`id_occ_detalle` INT(11) NOT NULL,
	`modo_generacion` VARCHAR(20) NULL,
	`fecha_hora_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_cm_lote_occ` (`id_certificado_maestro`,`lote_aperturado`,`id_occ_detalle`),
	KEY `idx_cm_lote` (`id_certificado_maestro`,`lote_aperturado`),
	KEY `idx_occ_detalle` (`id_occ_detalle`),
	CONSTRAINT `fk_cm_lote_occ_cm` FOREIGN KEY (`id_certificado_maestro`) REFERENCES `certificados_maestros` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `fk_cm_lote_occ_det` FOREIGN KEY (`id_occ_detalle`) REFERENCES `occ_detalles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- MIGRACIÓN 12.1: Poblar membresías exactas para lotes que ya tengan id_occ_detalle informado.
INSERT IGNORE INTO `certificados_maestros_lotes_occ_detalle`
(`id_certificado_maestro`, `lote_aperturado`, `id_occ_detalle`, `modo_generacion`)
SELECT DISTINCT
	cmd.`id_certificado_maestro`,
	cmd.`lote_aperturado`,
	cmd.`id_occ_detalle`,
	cmd.`modo_generacion`
FROM `certificados_maestros_detalles` cmd
WHERE cmd.`lote_aperturado` IS NOT NULL
	AND cmd.`lote_aperturado` <> ''
	AND cmd.`id_occ_detalle` IS NOT NULL;

-- MIGRACIÓN 12.2: Backfill para lotes agrupados históricos sin id_occ_detalle.
-- Criterio: vincular el lote a TODOS los items de la OCC del certificado para mantener el comportamiento actual.
INSERT IGNORE INTO `certificados_maestros_lotes_occ_detalle`
(`id_certificado_maestro`, `lote_aperturado`, `id_occ_detalle`, `modo_generacion`)
SELECT DISTINCT
	cmd.`id_certificado_maestro`,
	cmd.`lote_aperturado`,
	od.`id` AS `id_occ_detalle`,
	cmd.`modo_generacion`
FROM `certificados_maestros_detalles` cmd
INNER JOIN `certificados_maestros` cm ON cm.`id` = cmd.`id_certificado_maestro`
INNER JOIN `occ_detalles` od ON od.`id_occ` = cm.`id_occ`
WHERE cmd.`lote_aperturado` IS NOT NULL
	AND cmd.`lote_aperturado` <> ''
	AND cmd.`modo_generacion` = 'agrupar'
	AND cmd.`id_occ_detalle` IS NULL;

ALTER TABLE `ingresos_detalle`
  ADD `id_colada_origen` int(11) NULL AFTER `id_colada`;

ALTER TABLE `ingresos_detalle`
  ADD CONSTRAINT `ingresos_detalle_ibfk_3` FOREIGN KEY (`id_colada_origen`) REFERENCES `coladas` (`id`);

INSERT INTO `estados_lista_corte` (`id`, `estado`) VALUES (NULL, 'Gestionada');

ALTER TABLE coladas
ADD COLUMN fecha DATE NULL AFTER adjunto,
ADD COLUMN es_origen TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha;

ALTER TABLE coladas
MODIFY id_proveedor INT NULL,
MODIFY id_compra INT NULL,
MODIFY adjunto VARCHAR(500) NULL;

-- CORRECCIÓN 13: Separar lote_aperturado en lote (batch) y aperturado (breakdown)

ALTER TABLE `certificados_maestros_detalles`
    CHANGE `lote_aperturado` `aperturado` VARCHAR(64) NULL;

ALTER TABLE `certificados_maestros_detalles`
    ADD COLUMN `lote` VARCHAR(64) NULL AFTER `aperturado`;

-- Backfill: para datos existentes, lote = aperturado
UPDATE `certificados_maestros_detalles`
    SET `lote` = `aperturado`
    WHERE `lote` IS NULL AND `aperturado` IS NOT NULL AND `aperturado` <> '';

-- Tabla N:N
ALTER TABLE `certificados_maestros_lotes_occ_detalle`
    CHANGE `lote_aperturado` `aperturado` VARCHAR(64) NOT NULL;

ALTER TABLE `certificados_maestros_lotes_occ_detalle`
    ADD COLUMN `lote` VARCHAR(64) NULL AFTER `aperturado`;

UPDATE `certificados_maestros_lotes_occ_detalle`
    SET `lote` = `aperturado`
    WHERE `lote` IS NULL;

--TODO APLICADO HASTA ACA //Lo borré sin querér

-- Migración: múltiples OC por factura de compra
-- Paso 1: Crear tabla pivote
CREATE TABLE IF NOT EXISTS `facturas_compra_x_compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_factura_compra` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `estado_anterior` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_factura_compra` (`id_factura_compra`),
  KEY `id_compra` (`id_compra`),
  CONSTRAINT `fcxc_ibfk_1` FOREIGN KEY (`id_factura_compra`) REFERENCES `facturas_compra` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fcxc_ibfk_2` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Paso 2: Migrar datos existentes
INSERT INTO facturas_compra_x_compras (id_factura_compra, id_compra, estado_anterior)
SELECT fc.id, fc.id_orden_compra, NULL
FROM facturas_compra fc
WHERE fc.id_orden_compra IS NOT NULL;

-- Paso 3: Eliminar columna id_orden_compra
ALTER TABLE facturas_compra DROP FOREIGN KEY facturas_compra_ibfk_7;
ALTER TABLE facturas_compra DROP COLUMN id_orden_compra;

-- Paso 4: Agregar columna descripcion a facturas_compra_detalle
ALTER TABLE `facturas_compra_detalle`
ADD COLUMN `descripcion` varchar(255) DEFAULT NULL AFTER `id_concepto_contable`;

INSERT INTO estados_factura (id, estado) VALUES (5, 'Exportada');

ALTER TABLE facturas_compra ADD pagada tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE facturas_venta ADD pagada tinyint(1) NOT NULL DEFAULT 0;

UPDATE facturas_compra SET pagada = 1 WHERE id_estado = 4;
UPDATE facturas_venta SET pagada = 1 WHERE id_estado = 4;

ALTER TABLE facturas_compra ADD COLUMN IF NOT EXISTS exportada tinyint(4) NOT NULL DEFAULT 0;
ALTER TABLE facturas_venta ADD COLUMN IF NOT EXISTS exportada tinyint(4) NOT NULL DEFAULT 0;

UPDATE facturas_compra SET exportada = 1 WHERE id_estado = 5;
UPDATE facturas_venta SET exportada = 1 WHERE id_estado = 5;

UPDATE facturas_compra SET id_estado = 3 WHERE id_estado IN (4, 5);
UPDATE facturas_venta SET id_estado = 3 WHERE id_estado IN (4, 5);

DELETE FROM estados_factura WHERE id IN (4, 5);

ALTER TABLE `facturas_compra_detalle_x_compras_detalle`
  ADD COLUMN `cantidad` DOUBLE NOT NULL DEFAULT 0 AFTER `id_compra_detalle`,
  ADD COLUMN `precio` DOUBLE NOT NULL DEFAULT 0 AFTER `cantidad`;