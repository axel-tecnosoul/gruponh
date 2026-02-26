UPDATE computos_detalle SET reservado = 0 WHERE reservado < 0; -- CORRECCIÓN 1: Normalizar los valores negativos de reservado a 0 por el tema de los negativos


INSERT INTO parametros (id, parametro, valor) VALUES (6, 'E-MAIL port', 587); -- CORRECCIÓN 2: Agregar el puerto de correo electrónico a la tabla parametros para el envío de emails.


INSERT INTO estados_compras (id, estado) VALUES (5, 'Superado'); -- Nuevo estado para las compras.

ejecutar el .php llamado fix_nro_oc.php, http://localhost/gruponh/fix_nro_oc.php?ejecutar=1 para que modifique http://localhost/gruponh/fix_nro_oc.php para ver los cambios que haría