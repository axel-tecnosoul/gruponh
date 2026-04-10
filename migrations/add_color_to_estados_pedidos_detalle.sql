-- Agregar columna color a estados_pedidos_detalle para badges
ALTER TABLE `estados_pedidos_detalle` ADD COLUMN `color` VARCHAR(30) NULL DEFAULT NULL AFTER `descripcion`;

UPDATE `estados_pedidos_detalle` SET `color` = 'badge-secondary' WHERE `id` = 1; -- Pendiente
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-warning'   WHERE `id` = 2; -- Comprando
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-info'      WHERE `id` = 3; -- Comprando Parcial
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-primary'   WHERE `id` = 4; -- Comprando y Entregando
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-success'   WHERE `id` = 5; -- Comprando Total
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-success'   WHERE `id` = 6; -- Entregado Parcial
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-success'   WHERE `id` = 7; -- Entregado
UPDATE `estados_pedidos_detalle` SET `color` = 'badge-danger'    WHERE `id` = 8; -- Cancelado
