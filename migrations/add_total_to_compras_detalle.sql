-- Se agregan columnas subtotal y total separadas en compras_detalle.
-- subtotal = monto ANTES del descuento de línea (qty × precio)
-- total    = monto DESPUÉS del descuento de línea
-- Ambos se guardan y se leen directamente, sin calcular nada on-the-fly.

-- 1. Agregar columna total (si no existe)
ALTER TABLE compras_detalle ADD COLUMN total DOUBLE NOT NULL DEFAULT 0 AFTER subtotal;

-- 2. Para registros existentes:
--    subtotal fue guardado CON descuento aplicado (= total real).
--    Recalculamos subtotal (bruto) y copiamos el valor original a total.

-- Primero, copiar subtotal actual a total (que es el valor con descuento)
UPDATE compras_detalle SET total = ROUND(subtotal, 2);

-- Luego, recalcular subtotal para que sea el valor ANTES del descuento
UPDATE compras_detalle 
SET subtotal = ROUND(CASE 
    WHEN descuento > 0 AND descuento < 100 THEN subtotal / (1 - descuento / 100)
    ELSE subtotal 
END, 2)
WHERE descuento > 0;

-- 3. Redondear valores existentes a 2 decimales
UPDATE compras_detalle SET subtotal = ROUND(subtotal, 2), total = ROUND(total, 2);
