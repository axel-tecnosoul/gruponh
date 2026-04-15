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

UPDATE compras_detalle SET subtotal = ROUND(subtotal, 2), total = ROUND(total, 2);

--TODO APLICADO HASTA ACA