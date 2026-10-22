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
  ADD COLUMN `posicion_aperturado` VARCHAR(32) NULL AFTER `modo_generacion`;

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

ALTER TABLE `conceptos_contables`
  ADD COLUMN `id_alicuota_iva` INT(11) DEFAULT NULL;

ALTER TABLE `facturas_compra_detalle`
  ADD COLUMN `porc_descuento` double NOT NULL DEFAULT 0 AFTER `descripcion`;

UPDATE facturas_compra SET id_estado = 2 WHERE id_estado = 1;
UPDATE facturas_venta SET id_estado = 2 WHERE id_estado = 1;

DELETE FROM estados_factura WHERE id = 1;

--TODO APLICADO HASTA ACA

INSERT INTO tipos_notificacion (id, tipo, mensaje, redirect) VALUES
(22, 'Exceso en Facturación de OC', 'Se detectó un exceso de cantidad al facturar respecto a la OC', 'verFacturaCompra.php?id=');

ALTER TABLE `materiales` 
  ADD COLUMN `espesor` DECIMAL(10,2) NULL AFTER `descripcion`,
  ADD COLUMN `ancho` DECIMAL(10,2) NULL AFTER `espesor`;

UPDATE facturas_compra SET observaciones = TRIM(CONCAT(
    COALESCE(descripcion,''),
    CASE WHEN COALESCE(descripcion,'') != '' AND COALESCE(observaciones,'') != '' THEN CHAR(10) ELSE '' END,
    COALESCE(observaciones,'')
)) WHERE COALESCE(descripcion,'') != '';

UPDATE facturas_venta SET observaciones = TRIM(CONCAT(
    COALESCE(descripcion,''),
    CASE WHEN COALESCE(descripcion,'') != '' AND COALESCE(observaciones,'') != '' THEN CHAR(10) ELSE '' END,
    COALESCE(observaciones,'')
)) WHERE COALESCE(descripcion,'') != '';

----------Hay que deshabilitar el check de las claves foraneas, si da error puede que esté habilitado un checkbox debajo del textbox de la consulta

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE `categorias`;

INSERT INTO categorias (id, categoria) VALUES
(1, 'Metales (alum/cobre/bronce)'),
(2, 'Balizamiento'),
(3, 'Bulones / Varillas'),
(4, 'Cables / Riendas / Accesorios'),
(5, 'Caños'),
(6, 'Chapas'),
(7, 'Electricidad'),
(8, 'Ferreteria'),
(9, 'Galvanizado'),
(10, 'Perfiles'),
(11, 'Pinturas'),
(12, 'Sanitarios'),
(13, 'Maderas'),
(14, 'Corralon'),
(15, 'Electronicos'),
(16, 'Servicios'),
(17, 'Rodados'),
(18, 'Art. Varios'),
(19, 'Mano de Obra'),
(20, 'Ingenieria'),
(21, 'Certificaciones/ensayos'),
(22, 'Hospedaje'),
(23, 'Herramientas'),
(24, 'Fabricacion/taller'),
(25, 'Librería/imprenta'),
(26, 'Honorarios'),
(27, 'Limpieza'),
(28, 'Computacion'),
(29, 'Estudios'),
(30, 'Acero Inoxidable');

SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE regimenes_facturacion ADD COLUMN codigo VARCHAR(99) DEFAULT NULL AFTER id;
ALTER TABLE regimenes_facturacion ADD COLUMN articulo VARCHAR(99) DEFAULT NULL AFTER codigo;
ALTER TABLE regimenes_facturacion ADD COLUMN monto DOUBLE DEFAULT 0 AFTER porcentaje;

ALTER TABLE facturas_compra_retenciones ADD COLUMN porcentaje DOUBLE DEFAULT 0 AFTER monto;
ALTER TABLE facturas_compra_retenciones ADD COLUMN base_imponible DOUBLE DEFAULT 0 AFTER porcentaje;

ALTER TABLE facturas_venta_retenciones ADD COLUMN porcentaje DOUBLE DEFAULT 0 AFTER monto;
ALTER TABLE facturas_venta_retenciones ADD COLUMN base_imponible DOUBLE DEFAULT 0 AFTER porcentaje;

UPDATE facturas_compra_retenciones r
INNER JOIN regimenes_facturacion rf ON rf.id = r.id_regimen_facturacion
SET r.porcentaje = rf.porcentaje;

UPDATE facturas_venta_retenciones r
INNER JOIN regimenes_facturacion rf ON rf.id = r.id_regimen_facturacion
SET r.porcentaje = rf.porcentaje;

ALTER TABLE conceptos_contables ADD COLUMN anulado TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE materiales DROP COLUMN activo;
ALTER TABLE facturas_compra DROP COLUMN pagada;

ALTER TABLE facturas_compra_retenciones ADD COLUMN regimen_text VARCHAR(99) DEFAULT NULL AFTER id_factura_compra;
ALTER TABLE facturas_compra_retenciones ADD COLUMN codigo VARCHAR(99) DEFAULT NULL AFTER regimen_text;
ALTER TABLE facturas_compra_retenciones ADD COLUMN articulo VARCHAR(99) DEFAULT NULL AFTER codigo;

ALTER TABLE facturas_venta_retenciones ADD COLUMN regimen_text VARCHAR(99) DEFAULT NULL AFTER id_factura_venta;
ALTER TABLE facturas_venta_retenciones ADD COLUMN codigo VARCHAR(99) DEFAULT NULL AFTER regimen_text;
ALTER TABLE facturas_venta_retenciones ADD COLUMN articulo VARCHAR(99) DEFAULT NULL AFTER codigo;

-- ---------------------------------------------------------------------------
-- ELIMINAR id_regimen_facturacion de facturas_compra_retenciones y
-- facturas_venta_retenciones (la info ya está desnormalizada en regimen_text,
-- codigo, articulo, porcentaje, base_imponible)
-- ---------------------------------------------------------------------------

-- 1) Backfill histórico antes de borrar la FK
ALTER TABLE facturas_compra_retenciones ADD COLUMN regimen_text VARCHAR(99) DEFAULT NULL AFTER id_factura_compra;
ALTER TABLE facturas_compra_retenciones ADD COLUMN codigo VARCHAR(99) DEFAULT NULL AFTER regimen_text;
ALTER TABLE facturas_compra_retenciones ADD COLUMN articulo VARCHAR(99) DEFAULT NULL AFTER codigo;

ALTER TABLE facturas_venta_retenciones ADD COLUMN regimen_text VARCHAR(99) DEFAULT NULL AFTER id_factura_venta;
ALTER TABLE facturas_venta_retenciones ADD COLUMN codigo VARCHAR(99) DEFAULT NULL AFTER regimen_text;
ALTER TABLE facturas_venta_retenciones ADD COLUMN articulo VARCHAR(99) DEFAULT NULL AFTER codigo;

UPDATE facturas_compra_retenciones r
INNER JOIN regimenes_facturacion rf ON rf.id = r.id_regimen_facturacion
SET r.regimen_text    = COALESCE(NULLIF(r.regimen_text, ''), rf.regimen),
    r.codigo          = COALESCE(NULLIF(r.codigo, ''), rf.codigo),
    r.articulo        = COALESCE(NULLIF(r.articulo, ''), rf.articulo),
    r.porcentaje      = CASE WHEN r.porcentaje IS NULL OR r.porcentaje = 0 THEN rf.porcentaje ELSE r.porcentaje END,
    r.base_imponible  = CASE WHEN r.base_imponible IS NULL OR r.base_imponible = 0
                             THEN CASE WHEN rf.porcentaje > 0 THEN r.monto / (rf.porcentaje / 100) ELSE r.monto END
                             ELSE r.base_imponible END;

UPDATE facturas_venta_retenciones r
INNER JOIN regimenes_facturacion rf ON rf.id = r.id_regimen_facturacion
SET r.regimen_text    = COALESCE(NULLIF(r.regimen_text, ''), rf.regimen),
    r.codigo          = COALESCE(NULLIF(r.codigo, ''), rf.codigo),
    r.articulo        = COALESCE(NULLIF(r.articulo, ''), rf.articulo),
    r.porcentaje      = CASE WHEN r.porcentaje IS NULL OR r.porcentaje = 0 THEN rf.porcentaje ELSE r.porcentaje END,
    r.base_imponible  = CASE WHEN r.base_imponible IS NULL OR r.base_imponible = 0
                             THEN CASE WHEN rf.porcentaje > 0 THEN r.monto / (rf.porcentaje / 100) ELSE r.monto END
                             ELSE r.base_imponible END;

-- 2) Drop de FK, índice y columna
ALTER TABLE facturas_compra_retenciones DROP FOREIGN KEY facturas_compra_retenciones_ibfk_2;
ALTER TABLE facturas_compra_retenciones DROP KEY id_regimen_facturacion;
ALTER TABLE facturas_compra_retenciones DROP COLUMN id_regimen_facturacion;

ALTER TABLE facturas_venta_retenciones DROP FOREIGN KEY facturas_venta_retenciones_ibfk_2;
ALTER TABLE facturas_venta_retenciones DROP KEY id_regimen_facturacion;
ALTER TABLE facturas_venta_retenciones DROP COLUMN id_regimen_facturacion;

TRUNCATE `regimenes_facturacion`;

-- ---------------------------------------------------------------------------
-- SIGNOS DE REGIMENES (res_SignoCpr / res_SignoVta)
-- 1 = suma (importe positivo) | 2 = resta (importe negativo)
-- ---------------------------------------------------------------------------
ALTER TABLE regimenes_facturacion ADD COLUMN signo_cpr TINYINT NOT NULL DEFAULT 1 AFTER monto;
ALTER TABLE regimenes_facturacion ADD COLUMN signo_vta TINYINT NOT NULL DEFAULT 1 AFTER signo_cpr;

-- ---------------------------------------------------------------------------
-- TEXTO A IMPRIMIR POR LINEA EN FACTURAS DE VENTA + LIMPIEZA DE CAMPOS
-- 1) facturas_venta_detalle: agregar descripcion (igual que facturas_compra_detalle)
-- 2) texto_impreso nunca se escribia: se reemplaza por descripcion
-- 3) Cabecera: descripcion de facturas_compra/facturas_venta se unifica en
--    observaciones (el backfill de datos ya esta en las lineas 252-262)
-- ---------------------------------------------------------------------------
ALTER TABLE `facturas_venta_detalle`
  ADD COLUMN `descripcion` varchar(255) DEFAULT NULL AFTER `id_concepto_contable`;

ALTER TABLE `facturas_compra_detalle` DROP COLUMN `texto_impreso`;
ALTER TABLE `facturas_venta_detalle` DROP COLUMN `texto_impreso`;

ALTER TABLE `facturas_compra` DROP COLUMN `descripcion`;
ALTER TABLE `facturas_venta` DROP COLUMN `descripcion`;


-- ---------------------------------------------------------------------------
-- CORRECCIÓN 14: Cambiar nombre de columna numero a numero_x en certificados_maestros para evitar conflicto con palabra reservada.
-- ---------------------------------------------------------------------------

ALTER TABLE `certificados_maestros` CHANGE `numero` `numero_x` VARCHAR(99) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 15: Agregar columna cotizacion_dolar en certificados_avances_cabecera para registrar la cotización del dólar al momento de la certificación.
-- ---------------------------------------------------------------------------

ALTER TABLE certificados_avances_cabecera
  ADD COLUMN cotizacion_dolar double NOT NULL DEFAULT 0 AFTER fecha_fin;

-- Ejecutar manualmente. Este archivo no es ejecutado por la aplicación.
-- Anticipos: vinculados al Certificado Maestro.
CREATE TABLE IF NOT EXISTS `certificados_anticipos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_certificado_maestro` INT(11) NOT NULL,
  `fecha` DATE NOT NULL,
  `monto_base` DOUBLE NOT NULL DEFAULT 0,
  `porcentaje` DOUBLE NOT NULL DEFAULT 0,
  `monto_anticipo` DOUBLE NOT NULL DEFAULT 0,
  `observaciones` TEXT NULL,
  `id_usuario` INT(11) NOT NULL,
  `fecha_hora_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_certificados_anticipos_cm` (`id_certificado_maestro`),
  CONSTRAINT `fk_certificados_anticipos_cm`
    FOREIGN KEY (`id_certificado_maestro`) REFERENCES `certificados_maestros` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ajustes: vinculados a un Certificado de Avance.
CREATE TABLE IF NOT EXISTS `certificados_ajustes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_certificado_avance` INT(11) NOT NULL,
  `fecha` DATE NOT NULL,
  `tipo_ajuste` VARCHAR(50) NOT NULL,
  `observaciones` TEXT NULL,
  `monto` DOUBLE NOT NULL DEFAULT 0,
  `id_usuario` INT(11) NOT NULL,
  `fecha_hora_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_certificados_ajustes_ca` (`id_certificado_avance`),
  CONSTRAINT `fk_certificados_ajustes_ca`
    FOREIGN KEY (`id_certificado_avance`) REFERENCES `certificados_avances_cabecera` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Esta primera etapa no actualiza los acumulados de certificados_maestros
-- ni de certificados_avances_cabecera.

-- CORRECCIÓN 16: Aprobación del Certificado Maestro.
-- Los certificados existentes quedan pendientes al crear la columna.
ALTER TABLE `certificados_maestros`
ADD COLUMN `aprobado_cliente` TINYINT(1) NOT NULL DEFAULT 0
AFTER `porcentaje_anticipo`;

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 17: Numeración y revisiones de Certificados de Avance.
-- Sintaxis compatible con MySQL/MariaDB sin ADD COLUMN IF NOT EXISTS.
-- Ejecutar una sola vez; si alguna columna ya existe, omitir esa sentencia.
-- ---------------------------------------------------------------------------

ALTER TABLE `certificados_avances_cabecera`
  ADD COLUMN `nro_certificado` INT(11) NOT NULL DEFAULT 0 AFTER `id_certificado_maestro`;

ALTER TABLE `certificados_avances_cabecera`
  ADD COLUMN `nro_revision` INT(11) NOT NULL DEFAULT 1 AFTER `nro_certificado`;

ALTER TABLE `certificados_avances_cabecera`
  ADD COLUMN `id_certificado_avance_origen` INT(11) NULL DEFAULT NULL AFTER `nro_revision`;

ALTER TABLE `certificados_avances_cabecera`
  ADD COLUMN `motivo_revision` VARCHAR(255) NULL DEFAULT NULL AFTER `id_certificado_avance_origen`;

ALTER TABLE `certificados_avances_cabecera`
  ADD COLUMN `fecha_hora_revision` DATETIME NULL DEFAULT NULL AFTER `motivo_revision`;

ALTER TABLE `certificados_avances_cabecera`
  ADD COLUMN `id_usuario_revision` INT(11) NULL DEFAULT NULL AFTER `fecha_hora_revision`;

-- Backfill: numerar los certificados de avance por Certificado Maestro (1, 2, 3...).
UPDATE `certificados_avances_cabecera` cac
INNER JOIN (
    SELECT t.id,
           @rn := IF(@grp = t.id_certificado_maestro, @rn + 1, 1) AS rn,
           @grp := t.id_certificado_maestro AS grp
    FROM (
        SELECT id, id_certificado_maestro
        FROM certificados_avances_cabecera
        ORDER BY id_certificado_maestro, id
    ) t
    CROSS JOIN (SELECT @rn := 0, @grp := 0) v
) seq ON seq.id = cac.id
SET cac.`nro_certificado` = seq.rn;

UPDATE `certificados_avances_cabecera` SET `nro_revision` = 1 WHERE `nro_revision` = 0;

-- Impacto del ajuste: -1 resta, 1 suma. Desacopio y descuento siempre restan.
-- Redeterminación admite ambos signos desde el formulario.
ALTER TABLE `certificados_ajustes`
  ADD COLUMN `impacto` TINYINT(4) NOT NULL DEFAULT -1 AFTER `monto`;

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 18: Numeración de posiciones en los detalles de OCC.
-- Ejecutar una sola vez; si la columna ya existe, omitir estas sentencias.
-- ---------------------------------------------------------------------------

ALTER TABLE `occ_detalles`
  ADD COLUMN `posicion` VARCHAR(50) NULL AFTER `id_occ`;

UPDATE `occ_detalles` d
INNER JOIN (
    SELECT t.id,
           @pos := IF(@grupo = t.id_occ, @pos + 10, 10) AS nueva_pos,
           @grupo := t.id_occ AS grupo
    FROM (
        SELECT id, id_occ
        FROM occ_detalles
        ORDER BY id_occ, id
    ) t
    CROSS JOIN (SELECT @pos := 0, @grupo := NULL) v
) secuencia ON secuencia.id = d.id
SET d.posicion = secuencia.nueva_pos;

ALTER TABLE `occ_detalles`
  MODIFY COLUMN `posicion` VARCHAR(50) NOT NULL;

ALTER TABLE `occ_detalles`
  ADD INDEX `idx_occ_detalles_posicion` (`id_occ`, `posicion`);

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 20: Link opcional de archivo asociado a una OCC.
-- ---------------------------------------------------------------------------
ALTER TABLE `occ`
  ADD COLUMN `link_archivo` VARCHAR(1000) NULL AFTER `observaciones`;

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 19: Perfil Operaciones — acceso CM/CA sin información económica
-- Opción A: nuevo permiso/acción para vista medición-only
-- ---------------------------------------------------------------------------
-- Perfil
SET @next_perfil := (SELECT COALESCE(MAX(id),0)+1 FROM perfiles);
INSERT INTO perfiles (id, perfil, anulado)
SELECT @next_perfil, 'Operaciones', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM perfiles WHERE perfil='Operaciones');
SET @idOp := (SELECT id FROM perfiles WHERE perfil='Operaciones' LIMIT 1);

-- Permiso contenedor
SET @next_permiso := (SELECT COALESCE(MAX(id),0)+1 FROM permisos);
INSERT INTO permisos (id, permiso, anulado)
SELECT @next_permiso, 'Operaciones_Sin_Economico', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE permiso='Operaciones_Sin_Economico');
SET @idPermOp := (SELECT id FROM permisos WHERE permiso='Operaciones_Sin_Economico' LIMIT 1);

-- Acción para gatear vista sin montos
SET @next_accion := (SELECT COALESCE(MAX(id),0)+1 FROM acciones);
INSERT INTO acciones (id, accion)
SELECT @next_accion, 'Ver_Certificados_Sin_Montos' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM acciones WHERE accion='Ver_Certificados_Sin_Montos');
SET @idAccSinMontos := (SELECT id FROM acciones WHERE accion='Ver_Certificados_Sin_Montos' LIMIT 1);

-- Vincular acción <-> permiso
SET @next_ap := (SELECT COALESCE(MAX(id),0)+1 FROM acciones_permisos);
INSERT INTO acciones_permisos (id, id_accion, id_permiso)
SELECT @next_ap, @idAccSinMontos, @idPermOp FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM acciones_permisos WHERE id_accion=@idAccSinMontos AND id_permiso=@idPermOp);

-- Asignar al permiso Operaciones las acciones mínimas: 373 (Menu Certificados), 376 (Ver CA), 377 (Nuevo CA), 378 (Modificar CA)
-- Corrección: acciones_permisos vincula permiso -> acción; permisos_perfil vincula perfil -> permiso (1 fila)
SET @next_ap := (SELECT COALESCE(MAX(id),0)+1 FROM acciones_permisos);
INSERT INTO acciones_permisos (id, id_accion, id_permiso)
SELECT @next_ap, 373, @idPermOp FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM acciones_permisos WHERE id_accion=373 AND id_permiso=@idPermOp);
SET @next_ap := (SELECT COALESCE(MAX(id),0)+1 FROM acciones_permisos);
INSERT INTO acciones_permisos (id, id_accion, id_permiso)
SELECT @next_ap, 376, @idPermOp FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM acciones_permisos WHERE id_accion=376 AND id_permiso=@idPermOp);
SET @next_ap := (SELECT COALESCE(MAX(id),0)+1 FROM acciones_permisos);
INSERT INTO acciones_permisos (id, id_accion, id_permiso)
SELECT @next_ap, 377, @idPermOp FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM acciones_permisos WHERE id_accion=377 AND id_permiso=@idPermOp);
SET @next_ap := (SELECT COALESCE(MAX(id),0)+1 FROM acciones_permisos);
INSERT INTO acciones_permisos (id, id_accion, id_permiso)
SELECT @next_ap, 378, @idPermOp FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM acciones_permisos WHERE id_accion=378 AND id_permiso=@idPermOp);

-- Vincular perfil Operaciones -> permiso Operaciones (único vínculo)
SET @next_pp := (SELECT COALESCE(MAX(id),0)+1 FROM permisos_perfil);
INSERT INTO permisos_perfil (id, id_permiso, id_perfil)
SELECT @next_pp, @idPermOp, @idOp FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permisos_perfil WHERE id_permiso=@idPermOp AND id_perfil=@idOp);

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 21: Empresas configurables (CUIT, dirección, email, IVA, IIBB, inicio).
-- Ejecutar una sola vez; si alguna columna ya existe, omitir esa sentencia.
-- ---------------------------------------------------------------------------
ALTER TABLE `empresas` ADD COLUMN `cuit` VARCHAR(99) NULL;
ALTER TABLE `empresas` ADD COLUMN `direccion` VARCHAR(199) NULL;
ALTER TABLE `empresas` ADD COLUMN `email` VARCHAR(99) NULL;
ALTER TABLE `empresas` ADD COLUMN `id_condicion_iva` INT(11) NULL;
ALTER TABLE `empresas` ADD COLUMN `iibb` VARCHAR(99) NULL;
ALTER TABLE `empresas` ADD COLUMN `fecha_inicio_actividades` DATE NULL;

-- FK a condiciones_iva (omitir si ya existe: error 1826).
ALTER TABLE `empresas`
  ADD CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`id_condicion_iva`) REFERENCES `condiciones_iva` (`id`);

-- Acción Menu_Empresas (id por MAX+1, no hardcodea 393 de desarrollo).
SET @next_accion := (SELECT COALESCE(MAX(id),0)+1 FROM acciones);
INSERT INTO acciones (id, accion)
SELECT @next_accion, 'Menu_Empresas' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM acciones WHERE accion='Menu_Empresas');
SET @idAccEmpresas := (SELECT id FROM acciones WHERE accion='Menu_Empresas' LIMIT 1);

-- Vincular al permiso 102 ('Total', mismo que Menu Regimenes).
SET @next_ap := (SELECT COALESCE(MAX(id),0)+1 FROM acciones_permisos);
INSERT INTO acciones_permisos (id, id_accion, id_permiso)
SELECT @next_ap, @idAccEmpresas, 102 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM acciones_permisos WHERE id_accion=@idAccEmpresas AND id_permiso=102);

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 22: La primera revisión de Certificados de Avance es la 0.
-- Solo nuevas altas (el código inserta 0); sin migración de historial.
-- Este DEFAULT es para ambientes nuevos; en desarrollo ya está aplicado el dato.
-- ---------------------------------------------------------------------------
ALTER TABLE `certificados_avances_cabecera` ALTER COLUMN `nro_revision` SET DEFAULT 0;

INSERT INTO parametros (id, parametro, valor) VALUES (12, 'Monto mínimo Factura de crédito electrónica MiPyme', '5549862');

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 23: Descuento por renglón en facturas de venta.
-- La migración ARCA y el guardado de facturas usan esta columna.
-- Es idempotente para poder ejecutar el bloque en bases que ya la tienen.
-- ---------------------------------------------------------------------------
SET @discount_column_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'facturas_venta_detalle'
    AND COLUMN_NAME = 'porcentaje_descuento'
);
SET @discount_column_sql := IF(
  @discount_column_exists = 0,
  'ALTER TABLE `facturas_venta_detalle` ADD COLUMN `porcentaje_descuento` DECIMAL(9,4) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE discount_column_statement FROM @discount_column_sql;
EXECUTE discount_column_statement;
DEALLOCATE PREPARE discount_column_statement;

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 24: Configuración local de ARCA por empresa.
-- Permite mantener puntos de venta, tipos asociados y padrones provinciales.
-- ---------------------------------------------------------------------------
SET @retention_column_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND COLUMN_NAME = 'es_agente_retencion'
);
SET @retention_column_sql := IF(
  @retention_column_exists = 0,
  'ALTER TABLE `empresas` ADD COLUMN `es_agente_retencion` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE retention_column_statement FROM @retention_column_sql;
EXECUTE retention_column_statement;
DEALLOCATE PREPARE retention_column_statement;

CREATE TABLE IF NOT EXISTS `arca_puntos_venta` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_empresa` INT NOT NULL,
  `numero` INT NOT NULL,
  `emision_tipo` VARCHAR(50) NOT NULL DEFAULT '',
  `bloqueado` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_baja` VARCHAR(10) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arca_punto_venta_empresa_numero` (`id_empresa`, `numero`),
  CONSTRAINT `fk_arca_punto_venta_empresa`
    FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `arca_tipos_comprobante` (
  `codigo` INT NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL,
  `fecha_desde` VARCHAR(10) NOT NULL DEFAULT '',
  `fecha_hasta` VARCHAR(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `arca_tipos_comprobante` (`codigo`, `descripcion`) VALUES
  (1, 'Factura A'), (2, 'Nota de Débito A'), (3, 'Nota de Crédito A'),
  (4, 'Recibo A'), (5, 'Nota de Venta al Contado A'),
  (6, 'Factura B'), (7, 'Nota de Débito B'), (8, 'Nota de Crédito B'),
  (9, 'Recibo B'), (10, 'Nota de Venta al Contado B'),
  (11, 'Factura C'), (12, 'Nota de Débito C'), (13, 'Nota de Crédito C'),
  (15, 'Recibo C'), (19, 'Factura E'), (20, 'Nota de Débito E'),
  (21, 'Nota de Crédito E'),
  (201, 'Factura de Crédito Electrónica MiPyME A'),
  (202, 'Nota de Débito Electrónica MiPyME A'),
  (203, 'Nota de Crédito Electrónica MiPyME A'),
  (206, 'Factura de Crédito Electrónica MiPyME B'),
  (207, 'Nota de Débito Electrónica MiPyME B'),
  (208, 'Nota de Crédito Electrónica MiPyME B'),
  (211, 'Factura de Crédito Electrónica MiPyME C'),
  (212, 'Nota de Débito Electrónica MiPyME C'),
  (213, 'Nota de Crédito Electrónica MiPyME C')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`);

CREATE TABLE IF NOT EXISTS `arca_punto_venta_tipos` (
  `id_punto_venta` INT NOT NULL,
  `codigo_tipo_comprobante` INT NOT NULL,
  PRIMARY KEY (`id_punto_venta`, `codigo_tipo_comprobante`),
  CONSTRAINT `fk_arca_punto_venta_tipos_punto`
    FOREIGN KEY (`id_punto_venta`) REFERENCES `arca_puntos_venta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_arca_punto_venta_tipos_tipo`
    FOREIGN KEY (`codigo_tipo_comprobante`) REFERENCES `arca_tipos_comprobante` (`codigo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `empresas_padrones_retencion` (
  `id_empresa` INT NOT NULL,
  `id_provincia` INT NOT NULL,
  `url_padron` VARCHAR(2048) NOT NULL,
  PRIMARY KEY (`id_empresa`, `id_provincia`),
  CONSTRAINT `fk_empresas_padrones_retencion_empresa`
    FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_empresas_padrones_retencion_provincia`
    FOREIGN KEY (`id_provincia`) REFERENCES `provincias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 25: Padrón completo de tipos de comprobante (Excel AFIP 001-104).
-- Amplía sin reemplazar: conserva filas legacy de `tipos_comprobante` (id 1-6)
-- y el catálogo parcial de `arca_tipos_comprobante`. 077/078/079 se omiten
-- por estar vacíos en el Excel. Los tipos MiPyME (201-213) se conservan.
-- Empresas (configurarEmpresaARCA) lee `arca_tipos_comprobante`;
-- facturas de compra/venta leen `tipos_comprobante`.
-- ---------------------------------------------------------------------------

-- 25.1) Ampliar arca_tipos_comprobante (Empresas/ARCA) con el padrón completo.
INSERT INTO `arca_tipos_comprobante` (`codigo`, `descripcion`) VALUES
  (1, 'FACTURA A'),
  (2, 'NOTA DE DEBITO A'),
  (3, 'NOTA DE CREDITO A'),
  (4, 'RECIBO A'),
  (5, 'NOTA DE VENTA AL CONTADO A'),
  (6, 'FACTURA B'),
  (7, 'NOTA DE DEBITO B'),
  (8, 'NOTA DE CREDITO B'),
  (9, 'RECIBO B'),
  (10, 'NOTA DE VENTA AL CONTADO B'),
  (11, 'FACTURA C'),
  (12, 'NOTA DE DEBITO C'),
  (13, 'NOTA DE CREDITO C'),
  (14, 'DOCUMENTO ADUANERO'),
  (15, 'RECIBO C'),
  (16, 'NOTA DE VENTA AL CONTADO C'),
  (19, 'FACTURAS DE EXPORTACION'),
  (20, 'NOTA DE DEBITO POR OPERACIONES CON EL EXTERIOR'),
  (21, 'NOTA DE CREDITO POR OPERACIONES CON EL EXTERIOR'),
  (22, 'FACTURA - PERMISO EXPORTACION SIMPLIFICADO - DTO. 855/97'),
  (30, 'COMPROBANTE DE COMPRA DE BIENES USADOS'),
  (31, 'MANDATO - CONSIGNACION'),
  (32, 'COMPROBANTE PARA RECICLAR MATERIALES'),
  (34, 'COMPROBANTE A DEL APARTADO A INCISO F R G N 1415'),
  (35, 'COMPROBANTE B DEL ANEXO I, APARTADO A, INC. F), RG N 1415'),
  (36, 'COMPROBANTE C DEL ANEXO I, APARTADO A, INC. F), R.G. N 1415'),
  (37, 'NOTA DE DEBITO O DOCUMENTO EQUIVALENTE QUE CUMPLA CON LA R.G. N 1415'),
  (38, 'NOTA DE CREDITO O DOCUMENTO EQUIVALENTE QUE CUMPLA CON LA R.G. N 1415'),
  (39, 'OTRO COMPROBANTE A QUE CUMPLE CON LA R G 1415'),
  (40, 'OTRO COMPROBANTE B QUE CUMPLA CON LA R.G. N 1415'),
  (41, 'OTRO COMPROBANTE C QUE CUMPLA CON LA R.G. N 1415'),
  (50, 'RECIBO FACTURA A REGIMEN DE FACTURA DE CREDITO'),
  (51, 'FACTURA M'),
  (52, 'NOTA DE DEBITO M'),
  (53, 'NOTA DE CREDITO M'),
  (54, 'RECIBO M'),
  (55, 'NOTA DE VENTA AL CONTADO M'),
  (56, 'COMPROBANTE M DEL ANEXO I APARTADO A INC F R G N 1415'),
  (57, 'OTRO COMPROBANTE M QUE CUMPLA CON LA R G N 1415'),
  (58, 'CUENTA DE VENTA Y LIQUIDO PRODUCTO M'),
  (59, 'LIQUIDACION M'),
  (60, 'CUENTA DE VENTA Y LIQUIDO PRODUCTO A'),
  (61, 'CUENTA DE VENTA Y LIQUIDO PRODUCTO B'),
  (63, 'LIQUIDACION A'),
  (64, 'LIQUIDACION B'),
  (65, 'NOTA DE CREDITO DE COMPROBANTE CON COD. 34, 39, 58, 59, 60, 63, 96, 97'),
  (66, 'DESPACHO DE IMPORTACION'),
  (67, 'IMPORTACION DE SERVICIOS'),
  (68, 'LIQUIDACION C'),
  (70, 'RECIBO FACTURA DE CREDITO'),
  (71, 'CREDITO FISCAL POR CONTRIBUCION PATRONAL'),
  (73, 'FORMULARIO 1116 RT'),
  (74, 'CARTA DE PORTE PARA EL TRANSPORTE AUTOMOTOR PARA GRANOS'),
  (75, 'CARTA DE PORTE PARA EL TRANSPORTE FERROVIARIO PARA GRANOS'),
  (80, 'COMPROBANTE DIARIO DE CIERRE (ZETA)'),
  (81, 'TIQUE FACTURA A CONTROLADORES FISCALES'),
  (82, 'TIQUE - FACTURA B'),
  (83, 'TIQUE'),
  (84, 'COMPROBANTE FACTURA DE SERVICIOS PUBLICOS INTERESES FINANCIEROS'),
  (85, 'NOTA DE CREDITO SERVICIOS PUBLICOS NOTA DE CREDITO CONTROLADORES FISCALES'),
  (86, 'NOTA DE DEBITO SERVICIOS PUBLICOS'),
  (87, 'OTRO COMPROBANTE - SERVICIOS DEL EXTERIOR'),
  (88, 'OTRO COMPROBANTE - DOCUMENTO EXCEPTUADO / REMITO ELECTRONICO'),
  (89, 'OTRO COMPROBANTE - DOCUMENTO EXCEPTUADO - NOTA DE DEBITO / RESUMEN DE DATO'),
  (90, 'OTRO COMPROBANTE - DOCUMENTO EXCEPTUADO - NOTA DE CREDITO'),
  (91, 'REMITO R'),
  (92, 'AJUSTE CONTABLE QUE INCREMENTA EL DEBITO FISCAL'),
  (93, 'AJUSTE CONTABLE QUE DISMINUYE EL DEBITO FISCAL'),
  (94, 'AJUSTE CONTABLE QUE INCREMENTA EL CREDITO FISCAL'),
  (95, 'AJUSTE CONTABLE QUE DISMINUYE EL CREDITO FISCAL'),
  (96, 'FORMULARIO 1116 B'),
  (97, 'FORMULARIO 1116 C'),
  (99, 'OTROS COMP QUE NO CUMPLEN CON LA R G 3419 Y SUS MODIF'),
  (101, 'AJUSTE ANUAL PROVENIENTE DE LA D J DEL IVA POSITIVO'),
  (102, 'AJUSTE ANUAL PROVENIENTE DE LA D J DEL IVA NEGATIVO'),
  (103, 'NOTA DE ASIGNACION'),
  (104, 'NOTA DE CREDITO DE ASIGNACION'),
  (201, 'Factura de Crédito Electrónica MiPyME A'),
  (202, 'Nota de Débito Electrónica MiPyME A'),
  (203, 'Nota de Crédito Electrónica MiPyME A'),
  (206, 'Factura de Crédito Electrónica MiPyME B'),
  (207, 'Nota de Débito Electrónica MiPyME B'),
  (208, 'Nota de Crédito Electrónica MiPyME B'),
  (211, 'Factura de Crédito Electrónica MiPyME C'),
  (212, 'Nota de Débito Electrónica MiPyME C'),
  (213, 'Nota de Crédito Electrónica MiPyME C')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);

-- 25.2) Ampliar tipos_comprobante (facturas compra/venta) sin borrar id 1-6.
-- Se agregan columnas de forma idempotente (mismo patrón que CORRECCIÓN 23/24).
SET @tc_codigo_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tipos_comprobante'
    AND COLUMN_NAME = 'codigo_afip'
);
SET @tc_codigo_sql := IF(
  @tc_codigo_exists = 0,
  'ALTER TABLE `tipos_comprobante` ADD COLUMN `codigo_afip` INT NULL',
  'SELECT 1'
);
PREPARE tc_codigo_statement FROM @tc_codigo_sql;
EXECUTE tc_codigo_statement;
DEALLOCATE PREPARE tc_codigo_statement;

SET @tc_activo_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tipos_comprobante'
    AND COLUMN_NAME = 'activo'
);
SET @tc_activo_sql := IF(
  @tc_activo_exists = 0,
  'ALTER TABLE `tipos_comprobante` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT 1'
);
PREPARE tc_activo_statement FROM @tc_activo_sql;
EXECUTE tc_activo_statement;
DEALLOCATE PREPARE tc_activo_statement;

SET @tc_codigo_uq_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tipos_comprobante'
    AND INDEX_NAME = 'uq_tipos_comprobante_codigo'
);
SET @tc_codigo_uq_sql := IF(
  @tc_codigo_uq_exists = 0,
  'ALTER TABLE `tipos_comprobante` ADD UNIQUE KEY `uq_tipos_comprobante_codigo` (`codigo_afip`)',
  'SELECT 1'
);
PREPARE tc_codigo_uq_statement FROM @tc_codigo_uq_sql;
EXECUTE tc_codigo_uq_statement;
DEALLOCATE PREPARE tc_codigo_uq_statement;

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 26: Persistir montos por imputación en factura de venta.
-- facturas_venta_detalle_x_certificados_avance solo guardaba ids, por eso al
-- editar el lápiz mostraba 1 x monto_total y perdía lo tipeado.
-- ---------------------------------------------------------------------------
SET @fvxca_cant_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'facturas_venta_detalle_x_certificados_avance'
    AND COLUMN_NAME = 'cantidad'
);
SET @fvxca_cant_sql := IF(
  @fvxca_cant_exists = 0,
  'ALTER TABLE `facturas_venta_detalle_x_certificados_avance` ADD COLUMN `cantidad` DOUBLE NOT NULL DEFAULT 1 AFTER `id_certificado_avance`',
  'SELECT 1'
);
PREPARE fvxca_cant_statement FROM @fvxca_cant_sql;
EXECUTE fvxca_cant_statement;
DEALLOCATE PREPARE fvxca_cant_statement;

SET @fvxca_prec_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'facturas_venta_detalle_x_certificados_avance'
    AND COLUMN_NAME = 'precio'
);
SET @fvxca_prec_sql := IF(
  @fvxca_prec_exists = 0,
  'ALTER TABLE `facturas_venta_detalle_x_certificados_avance` ADD COLUMN `precio` DOUBLE NOT NULL DEFAULT 0 AFTER `cantidad`',
  'SELECT 1'
);
PREPARE fvxca_prec_statement FROM @fvxca_prec_sql;
EXECUTE fvxca_prec_statement;
DEALLOCATE PREPARE fvxca_prec_statement;

-- Backfill histórico: precio = monto_total del CA, cantidad = 1.
UPDATE `facturas_venta_detalle_x_certificados_avance` fvxca
INNER JOIN `certificados_avances_cabecera` ca ON ca.`id` = fvxca.`id_certificado_avance`
SET fvxca.`precio` = ca.`monto_total`,
    fvxca.`cantidad` = 1
WHERE fvxca.`precio` IS NULL OR fvxca.`precio` = 0;

ALTER TABLE `facturas_compra_detalle` CHANGE `porc_descuento` `porcentaje_descuento` DOUBLE NOT NULL DEFAULT '0';

ALTER TABLE `facturas_venta_otros`
| ADD COLUMN `regimen_text` VARCHAR(99) NULL DEFAULT NULL AFTER `id_regimen`,
  ADD COLUMN `codigo` VARCHAR(99) NULL DEFAULT NULL AFTER `regimen_text`,
  ADD COLUMN `articulo` VARCHAR(99) NULL DEFAULT NULL AFTER `codigo`;

-- ---------------------------------------------------------------------------
-- CORRECCIÓN 27: Eliminar la tabla tipos_comprobante.
-- El sistema ya usa arca_tipos_comprobante (por codigo ARCA). Primero hay que
-- quitar las FK que referencian la tabla; omitir cada ALTER si no existe en
-- el ambiente. Ejecutar manualmente.
-- ---------------------------------------------------------------------------
ALTER TABLE `facturas_venta`  DROP FOREIGN KEY `facturas_venta_ibfk_8`;
ALTER TABLE `facturas_compra` DROP FOREIGN KEY `facturas_compra_ibfk_8`;

DROP TABLE IF EXISTS `tipos_comprobante`;

-- ---------------------------------------------------------------------------
-- CORRECCION 28 (Computos): fecha de alta y de ultima modificacion del computo.
-- Sirve para ordenar la pantalla de inicio por modificacion (los mas recientes
-- primero). La columna de modificacion se actualiza sola en cada UPDATE.
-- Nota: si ya se aplico (linea 21 de este archivo), omitir el ALTER para que
-- no de error de columna duplicada.
-- ---------------------------------------------------------------------------
ALTER TABLE `computos` ADD `fecha_hora_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `id_cuenta_valido`, ADD `fecha_hora_ultima_modificacion` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `fecha_hora_alta`;

UPDATE empresas SET cuit = '30680640919', direccion = 'Gutierrez Ricardo 2874 Piso: 1 y 2 - Ciudad de Buenos Aires', email = '', iibb = '901-165299-7', fecha_inicio_actividades = '1994-09-01' WHERE empresas.id = 1;
UPDATE empresas SET cuit = '30711153957', direccion = 'Av. Nicolas Milazzo 3251 - Ranelagh, Buenas Aires', id_condicion_iva = '1', iibb = '901-535262-3', fecha_inicio_actividades = '2009-11-01' WHERE empresas.id = 2;

UPDATE condiciones_iva SET condicion_iva = 'IVA Responsable Inscripto' WHERE condiciones_iva.id = 1;

-- ---------------------------------------------------------------------------
-- CORRECCION 29 (Facturación ARCA): facturas_venta/facturas_compra.id_tipo_comprobante
-- ---------------------------------------------------------------------------
UPDATE facturas_venta SET id_tipo_comprobante = id_tipo_comprobante - 1000 WHERE id_tipo_comprobante >= 1001;
UPDATE facturas_compra SET id_tipo_comprobante = id_tipo_comprobante - 1000 WHERE id_tipo_comprobante >= 1001;

ALTER TABLE `facturas_venta` ADD CONSTRAINT `facturas_venta_ibfk_8` FOREIGN KEY (`id_tipo_comprobante`) REFERENCES `arca_tipos_comprobante` (`codigo`);
ALTER TABLE `facturas_compra` ADD CONSTRAINT `facturas_compra_ibfk_8` FOREIGN KEY (`id_tipo_comprobante`) REFERENCES `arca_tipos_comprobante` (`codigo`);

UPDATE `empresas` SET `empresa` = 'NH Construcciones SRL' WHERE `empresas`.`id` = 1
UPDATE `empresas` SET `empresa` = 'NH Net SA' WHERE `empresas`.`id` = 2