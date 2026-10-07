-- Modelo de referencia para MySQL/MariaDB. No ejecutar sobre tablas existentes.
-- Revisar sql/LEEME.md antes de integrar. No modifica facturas_venta.
CREATE TABLE comprobantes_arca (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_factura_venta BIGINT UNSIGNED NOT NULL,
    clave_operacion VARCHAR(64) NOT NULL,
    homologacion TINYINT UNSIGNED NOT NULL,
    emisor_cuit CHAR(11) NOT NULL,
    punto_venta INT UNSIGNED NOT NULL,
    tipo_comprobante SMALLINT UNSIGNED NOT NULL,
    numero_comprobante BIGINT UNSIGNED NULL,
    estado ENUM('pendiente', 'enviando', 'autorizado', 'rechazado', 'incierto') NOT NULL DEFAULT 'pendiente',
    fecha_comprobante DATE NOT NULL,
    concepto SMALLINT UNSIGNED NOT NULL,
    receptor_tipo_documento SMALLINT UNSIGNED NOT NULL,
    receptor_numero_documento VARCHAR(20) NOT NULL,
    receptor_condicion_iva SMALLINT UNSIGNED NOT NULL,
    fecha_servicio_desde DATE NULL,
    fecha_servicio_hasta DATE NULL,
    fecha_vencimiento_pago DATE NULL,
    moneda VARCHAR(3) NOT NULL,
    cotizacion DECIMAL(18,6) NOT NULL,
    cancela_misma_moneda CHAR(1) NULL,
    importe_total DECIMAL(18,2) NOT NULL,
    importe_neto DECIMAL(18,2) NOT NULL,
    importe_no_gravado DECIMAL(18,2) NOT NULL,
    importe_exento DECIMAL(18,2) NOT NULL,
    importe_iva DECIMAL(18,2) NOT NULL,
    importe_tributos DECIMAL(18,2) NOT NULL,
    datos_emisor LONGTEXT NOT NULL,
    datos_receptor LONGTEXT NOT NULL,
    detalle_comprobante LONGTEXT NOT NULL,
    version_datos SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    solicitud_arca LONGTEXT NULL,
    respuesta_arca LONGTEXT NULL,
    cae VARCHAR(14) NULL,
    fecha_vencimiento_cae DATE NULL,
    ultimo_error TEXT NULL,
    fecha_ultimo_envio DATETIME NULL,
    fecha_autorizacion DATETIME NULL,
    fecha_conciliacion DATETIME NULL,
    usuario_conciliacion VARCHAR(100) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comprobante_fiscal (homologacion, emisor_cuit, punto_venta, tipo_comprobante, numero_comprobante),
    UNIQUE KEY uq_operacion (homologacion, emisor_cuit, clave_operacion),
    KEY ix_factura_venta (id_factura_venta),
    KEY ix_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comprobantes_arca_iva (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_comprobante_arca BIGINT UNSIGNED NOT NULL,
    codigo_arca SMALLINT UNSIGNED NOT NULL,
    base_imponible DECIMAL(18,2) NOT NULL,
    importe DECIMAL(18,2) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_iva_comprobante (id_comprobante_arca, codigo_arca),
    CONSTRAINT fk_arca_iva_comprobante FOREIGN KEY (id_comprobante_arca)
        REFERENCES comprobantes_arca (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comprobantes_arca_tributos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_comprobante_arca BIGINT UNSIGNED NOT NULL,
    codigo_arca SMALLINT UNSIGNED NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    base_imponible DECIMAL(18,2) NOT NULL,
    alicuota DECIMAL(12,6) NOT NULL,
    importe DECIMAL(18,2) NOT NULL,
    referencia_regimen VARCHAR(100) NULL,
    PRIMARY KEY (id),
    KEY ix_tributo_comprobante (id_comprobante_arca),
    CONSTRAINT fk_arca_tributo_comprobante FOREIGN KEY (id_comprobante_arca)
        REFERENCES comprobantes_arca (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comprobantes_arca_asociados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_comprobante_arca BIGINT UNSIGNED NOT NULL,
    id_comprobante_asociado BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asociado (id_comprobante_arca, id_comprobante_asociado),
    KEY ix_asociado_local (id_comprobante_asociado),
    CONSTRAINT fk_arca_asociados_comprobante FOREIGN KEY (id_comprobante_arca)
        REFERENCES comprobantes_arca (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_arca_asociados_original FOREIGN KEY (id_comprobante_asociado)
        REFERENCES comprobantes_arca (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
