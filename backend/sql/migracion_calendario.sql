-- =============================================================
-- MIGRACIÓN: tabla para días no hábiles del calendario (compartida)
-- =============================================================
-- Aplicar UNA SOLA VEZ:
--   C:\xampp\mysql\bin\mysql.exe -u root Proyectointegrador < backend\sql\migracion_calendario.sql
-- =============================================================

USE Proyectointegrador;

CREATE TABLE IF NOT EXISTS dias_no_habiles (
    n_iddia          INT AUTO_INCREMENT PRIMARY KEY,
    dt_fecha         DATE NOT NULL UNIQUE,
    t_motivo         VARCHAR(200) NOT NULL DEFAULT 'Marcado manual',
    t_descripcion    VARCHAR(500),
    n_idusuario      INT NOT NULL,
    dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (n_idusuario) REFERENCES usuarios(n_idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
