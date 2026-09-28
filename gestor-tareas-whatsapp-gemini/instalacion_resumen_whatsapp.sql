CREATE TABLE IF NOT EXISTS resumenes_whatsapp (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    chat_nombre VARCHAR(190) NOT NULL,
    fecha_resumen DATE NOT NULL,
    resumen MEDIUMTEXT NOT NULL,
    cantidad_mensajes INT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resumen_whatsapp_usuario_chat_fecha (usuario_id, chat_nombre, fecha_resumen),
    KEY idx_resumen_whatsapp_fecha (fecha_resumen),
    KEY idx_resumen_whatsapp_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
