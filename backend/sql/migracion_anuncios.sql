-- =============================================================
-- MIGRACIÓN: tabla para anuncios / información importante
-- =============================================================
-- Aplicar UNA SOLA VEZ:
--   C:\xampp\mysql\bin\mysql.exe -u root Proyectointegrador < backend\sql\migracion_anuncios.sql
-- =============================================================

USE Proyectointegrador;

CREATE TABLE IF NOT EXISTS anuncios (
    n_idanuncio      INT AUTO_INCREMENT PRIMARY KEY,
    t_titulo         VARCHAR(200) NOT NULL,
    t_mensaje        VARCHAR(1000) NOT NULL,
    t_tipo           VARCHAR(20) NOT NULL DEFAULT 'informativo',
    t_estado         VARCHAR(20) NOT NULL DEFAULT 'activo',
    dt_fechapub      DATE NOT NULL,
    dt_fechaexp      DATE NULL,
    n_idusuario      INT NOT NULL,
    dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dt_fechaactu     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (n_idusuario) REFERENCES usuarios(n_idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
