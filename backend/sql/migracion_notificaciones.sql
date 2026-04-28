-- =============================================================
-- MIGRACIÓN: tabla notificaciones in-app (HU-07.01, 07.02, 07.04)
-- =============================================================
USE Proyectointegrador;

CREATE TABLE IF NOT EXISTS notificaciones (
    n_idnotificacion INT AUTO_INCREMENT PRIMARY KEY,
    n_idusuario      INT NOT NULL,            -- destinatario
    t_tipo           VARCHAR(30) NOT NULL,    -- info|aprobada|rechazada|prestada|devuelta|vencida|alerta_foto|stock_bajo
    t_titulo         VARCHAR(200) NOT NULL,
    t_mensaje        VARCHAR(1000) NOT NULL,
    t_link           VARCHAR(255) NULL,       -- ruta opcional a la que el click debe ir
    n_referencia     INT NULL,                -- id del recurso relacionado (solicitud, etc.)
    t_leida          VARCHAR(2) NOT NULL DEFAULT 'N',  -- 'S' o 'N'
    dt_fechalectura  TIMESTAMP NULL,
    dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_destinatario (n_idusuario, t_leida),
    FOREIGN KEY (n_idusuario) REFERENCES usuarios(n_idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
