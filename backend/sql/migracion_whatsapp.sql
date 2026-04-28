-- =============================================================
-- MIGRACIÓN: campos para canal preferido WhatsApp/email (HU-07.04)
-- =============================================================
USE Proyectointegrador;

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS t_telefono            VARCHAR(20)  NULL  AFTER t_correo,
    ADD COLUMN IF NOT EXISTS t_telefono_apikey     VARCHAR(100) NULL  AFTER t_telefono,
    ADD COLUMN IF NOT EXISTS t_canal_preferido     VARCHAR(20)  NOT NULL DEFAULT 'inapp'
        AFTER t_telefono_apikey;
-- valores válidos de t_canal_preferido: 'inapp' | 'correo' | 'whatsapp' | 'todos'
