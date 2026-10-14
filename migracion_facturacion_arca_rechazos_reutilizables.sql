-- Ejecutar una vez en la base ya migrada para permitir reutilizar numeros
-- cuyo intento fue rechazado explicitamente, conservando el historial.
-- Los numeros pendientes, enviando, inciertos y autorizados siguen siendo unicos.

SET @has_active_number_column = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'comprobantes_arca'
      AND COLUMN_NAME = 'numero_comprobante_activo'
);
SET @add_active_number_column = IF(
    @has_active_number_column = 0,
    'ALTER TABLE comprobantes_arca ADD COLUMN numero_comprobante_activo BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN estado = ''rechazado'' THEN NULL ELSE numero_comprobante END) STORED AFTER estado',
    'SELECT 1'
);
PREPARE add_active_number_column_statement FROM @add_active_number_column;
EXECUTE add_active_number_column_statement;
DEALLOCATE PREPARE add_active_number_column_statement;

SET @has_legacy_voucher_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'comprobantes_arca'
      AND INDEX_NAME = 'uq_comprobante_fiscal'
);
SET @drop_legacy_voucher_index = IF(
    @has_legacy_voucher_index > 0,
    'ALTER TABLE comprobantes_arca DROP INDEX uq_comprobante_fiscal',
    'SELECT 1'
);
PREPARE drop_legacy_voucher_index_statement FROM @drop_legacy_voucher_index;
EXECUTE drop_legacy_voucher_index_statement;
DEALLOCATE PREPARE drop_legacy_voucher_index_statement;

SET @has_active_voucher_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'comprobantes_arca'
      AND INDEX_NAME = 'uq_comprobante_fiscal_activo'
);
SET @add_active_voucher_index = IF(
    @has_active_voucher_index = 0,
    'ALTER TABLE comprobantes_arca ADD UNIQUE KEY uq_comprobante_fiscal_activo (homologacion, emisor_cuit, punto_venta, tipo_comprobante, numero_comprobante_activo)',
    'SELECT 1'
);
PREPARE add_active_voucher_index_statement FROM @add_active_voucher_index;
EXECUTE add_active_voucher_index_statement;
DEALLOCATE PREPARE add_active_voucher_index_statement;
