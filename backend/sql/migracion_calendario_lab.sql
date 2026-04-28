-- =============================================================
-- MIGRACIÓN: días no hábiles por módulo (corrección CTIC HU-05.01)
-- =============================================================
USE Proyectointegrador;

-- Agregar columna n_idlaboratorio (NULL = global, lab específico = solo ese lab).
ALTER TABLE dias_no_habiles
    ADD COLUMN IF NOT EXISTS n_idlaboratorio INT NULL AFTER n_idusuario,
    ADD CONSTRAINT FK_dias_lab
        FOREIGN KEY (n_idlaboratorio) REFERENCES laboratorios(n_idlaboratorio)
        ON DELETE CASCADE;

-- La constraint UNIQUE original era solo sobre dt_fecha; ahora la
-- unicidad debe ser por (fecha, lab). Eliminamos la vieja.
ALTER TABLE dias_no_habiles DROP INDEX dt_fecha;
ALTER TABLE dias_no_habiles
    ADD UNIQUE KEY uk_fecha_lab (dt_fecha, n_idlaboratorio);
