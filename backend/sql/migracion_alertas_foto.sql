-- =============================================================
-- MIGRACIÓN: Moderación de foto de perfil por admin (HU-08.10)
-- =============================================================
USE Proyectointegrador;

-- Columnas auxiliares en usuarios para gestionar el ciclo de la alerta.
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS dt_alerta_foto      TIMESTAMP NULL AFTER t_fotoperfil,
    ADD COLUMN IF NOT EXISTS n_idusuario_alerto  INT       NULL AFTER dt_alerta_foto,
    ADD COLUMN IF NOT EXISTS t_motivo_alerta     VARCHAR(300) NULL AFTER n_idusuario_alerto;
