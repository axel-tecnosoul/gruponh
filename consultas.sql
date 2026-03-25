UPDATE computos_detalle SET reservado = 0 WHERE reservado < 0; -- CORRECCIÓN 1: Normalizar los valores negativos de reservado a 0 por el tema de los negativos


INSERT INTO parametros (id, parametro, valor) VALUES (6, 'E-MAIL port', 587); -- CORRECCIÓN 2: Agregar el puerto de correo electrónico a la tabla parametros para el envío de emails.


INSERT INTO estados_compra (id, estado) VALUES (5, 'Superado'); -- Nuevo estado para las compras.

ejecutar el .php llamado fix_nro_oc.php, http://localhost/gruponh/fix_nro_oc.php?ejecutar=1 para que modifique http://localhost/gruponh/fix_nro_oc.php para ver los cambios que haría

ALTER TABLE `ingresos` CHANGE `nro_remito` `nro_remito` VARCHAR(99) NOT NULL; -- CORRECCIÓN 3: Modificar el tipo de dato de nro_remito a VARCHAR para permitir números de remito con guiones o letras, si es necesario.

ALTER TABLE `ingresos` ADD `ruta_documento` VARCHAR(500) NOT NULL AFTER `nro_remito`; -- CORRECCIÓN 4: Agregar una nueva columna para almacenar la ruta del documento asociado al ingreso, como el remito escaneado o factura.
ALTER TABLE `ingresos` CHANGE `ruta_documento` `ruta_documento` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL; -- CORRECCIÓN 5: Permitir que la ruta del documento sea NULL en caso de que no se haya subido un documento asociado al ingreso.

ALTER TABLE `computos` ADD `fecha_hora_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `id_cuenta_valido`, ADD `fecha_hora_ultima_modificacion` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `fecha_hora_alta`;

ALTER TABLE materiales ADD `fecha_hora_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `id_cuenta_valido`, ADD `fecha_hora_ultima_modificacion` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `fecha_hora_alta`;
ALTER TABLE materiales ADD COLUMN perimetro DECIMAL(10,2) NULL; -- CORRECCIÓN 6: Agregar una nueva columna para almacenar el perímetro de los materiales, si es relevante para el cálculo de costos o logística.