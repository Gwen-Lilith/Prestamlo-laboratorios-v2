-- =============================================================
-- MIGRACIÓN OBS 2: tabla para solicitudes de creación de laboratorio
-- =============================================================
-- Aplicar UNA SOLA VEZ sobre la BD local existente. No reimporta la BD.
--   C:\xampp\mysql\bin\mysql.exe -u root Proyectointegrador < backend\sql\migracion_obs2_solicitudes_laboratorio.sql
-- =============================================================

USE Proyectointegrador;

CREATE TABLE IF NOT EXISTS solicitudes_laboratorio (
    n_idsolicitudlab     INT AUTO_INCREMENT PRIMARY KEY,
    t_nombre             VARCHAR(150) NOT NULL,
    t_ubicacion          VARCHAR(200),
    t_motivo             VARCHAR(500) NOT NULL,
    n_idsolicitante      INT NOT NULL,
    t_estado             VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    n_idusuarioresponde  INT NULL,
    t_comentarioresp     VARCHAR(500),
    dt_fecharespuesta    TIMESTAMP NULL,
    dt_fechacreacion     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dt_fechaactu         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (n_idsolicitante)     REFERENCES usuarios(n_idusuario),
    FOREIGN KEY (n_idusuarioresponde) REFERENCES usuarios(n_idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
