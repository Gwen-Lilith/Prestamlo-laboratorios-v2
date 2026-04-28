-- =============================================================
-- Festivos nacionales de Colombia para 2026 y 2027 (HU-05.01)
-- =============================================================
-- Aplicar UNA SOLA VEZ. Si quieres re-poblar, primero:
--   DELETE FROM dias_no_habiles WHERE t_motivo = 'Festivo nacional';
-- =============================================================

USE Proyectointegrador;

INSERT IGNORE INTO dias_no_habiles (dt_fecha, t_motivo, t_descripcion, n_idusuario) VALUES
-- 2026
('2026-01-01', 'Festivo nacional', 'Año Nuevo',                                1),
('2026-01-12', 'Festivo nacional', 'Día de los Reyes Magos',                   1),
('2026-03-23', 'Festivo nacional', 'Día de San José',                          1),
('2026-04-02', 'Festivo nacional', 'Jueves Santo',                             1),
('2026-04-03', 'Festivo nacional', 'Viernes Santo',                            1),
('2026-05-01', 'Festivo nacional', 'Día del Trabajo',                          1),
('2026-05-18', 'Festivo nacional', 'Día de la Ascensión',                      1),
('2026-06-08', 'Festivo nacional', 'Corpus Christi',                           1),
('2026-06-15', 'Festivo nacional', 'Sagrado Corazón',                          1),
('2026-06-29', 'Festivo nacional', 'San Pedro y San Pablo',                    1),
('2026-07-20', 'Festivo nacional', 'Día de la Independencia',                  1),
('2026-08-07', 'Festivo nacional', 'Batalla de Boyacá',                        1),
('2026-08-17', 'Festivo nacional', 'Asunción de la Virgen',                    1),
('2026-10-12', 'Festivo nacional', 'Día de la Raza',                           1),
('2026-11-02', 'Festivo nacional', 'Día de Todos los Santos',                  1),
('2026-11-16', 'Festivo nacional', 'Independencia de Cartagena',               1),
('2026-12-08', 'Festivo nacional', 'Inmaculada Concepción',                    1),
('2026-12-25', 'Festivo nacional', 'Navidad',                                  1),
-- 2027
('2027-01-01', 'Festivo nacional', 'Año Nuevo',                                1),
('2027-01-11', 'Festivo nacional', 'Día de los Reyes Magos',                   1),
('2027-03-22', 'Festivo nacional', 'Día de San José',                          1),
('2027-03-25', 'Festivo nacional', 'Jueves Santo',                             1),
('2027-03-26', 'Festivo nacional', 'Viernes Santo',                            1),
('2027-05-01', 'Festivo nacional', 'Día del Trabajo',                          1),
('2027-05-10', 'Festivo nacional', 'Día de la Ascensión',                      1),
('2027-05-31', 'Festivo nacional', 'Corpus Christi',                           1),
('2027-06-07', 'Festivo nacional', 'Sagrado Corazón',                          1),
('2027-07-05', 'Festivo nacional', 'San Pedro y San Pablo',                    1),
('2027-07-20', 'Festivo nacional', 'Día de la Independencia',                  1),
('2027-08-07', 'Festivo nacional', 'Batalla de Boyacá',                        1),
('2027-08-16', 'Festivo nacional', 'Asunción de la Virgen',                    1),
('2027-10-18', 'Festivo nacional', 'Día de la Raza',                           1),
('2027-11-01', 'Festivo nacional', 'Día de Todos los Santos',                  1),
('2027-11-15', 'Festivo nacional', 'Independencia de Cartagena',               1),
('2027-12-08', 'Festivo nacional', 'Inmaculada Concepción',                    1),
('2027-12-25', 'Festivo nacional', 'Navidad',                                  1);
