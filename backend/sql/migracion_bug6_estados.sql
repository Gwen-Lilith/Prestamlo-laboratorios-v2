-- =============================================================
-- MIGRACIÓN BUG 6: unificar estado de solicitudes 'en_curso' -> 'prestada'
-- =============================================================
-- Aplicar UNA SOLA VEZ sobre la BD local existente. No reimporta la BD.
-- Ejecutar con XAMPP:
--   C:\xampp\mysql\bin\mysql.exe -u root Proyectointegrador < backend\sql\migracion_bug6_estados.sql
-- =============================================================

USE Proyectointegrador;

-- 1) Normalizar datos existentes (si hubiera alguna solicitud con el estado viejo)
UPDATE solicitudes_prestamo SET t_estado = 'prestada' WHERE t_estado = 'en_curso';

-- 2) Recrear vistas con la nueva nomenclatura
DROP VIEW IF EXISTS v_prestamos_activos;
CREATE VIEW v_prestamos_activos AS
SELECT
    sp.n_idsolicitud,
    sp.t_estado,
    sp.dt_fechainicio,
    sp.dt_fechafin,
    u.t_nombres,
    u.t_apellidos,
    u.t_correo,
    l.t_nombre AS t_laboratorio,
    e.t_nombre AS t_elemento,
    e.t_numeroinventario,
    se.n_cantidad
FROM solicitudes_prestamo sp
JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio
JOIN solicitudes_elementos se ON se.n_idsolicitud = sp.n_idsolicitud
JOIN elementos e ON e.n_idelemento = se.n_idelemento
WHERE sp.t_estado IN ('aprobada', 'prestada');

DROP VIEW IF EXISTS v_prestamos_vencidos;
CREATE VIEW v_prestamos_vencidos AS
SELECT
    sp.n_idsolicitud,
    u.t_nombres,
    u.t_apellidos,
    u.t_correo,
    sp.dt_fechafin,
    l.t_nombre AS t_laboratorio
FROM solicitudes_prestamo sp
JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio
WHERE sp.t_estado = 'prestada'
  AND sp.dt_fechafin < CURRENT_TIMESTAMP;
