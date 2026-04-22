-- =============================================================
-- MIGRACIÓN: columna para ruta de foto de perfil en usuarios
-- =============================================================
-- Aplicar UNA SOLA VEZ:
--   C:\xampp\mysql\bin\mysql.exe -u root Proyectointegrador < backend\sql\migracion_foto_perfil.sql
-- =============================================================

USE Proyectointegrador;

-- Agrega la columna solo si no existe (idempotente en MariaDB/MySQL 8+)
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS t_fotoperfil VARCHAR(255) NULL AFTER t_tokenqr;
