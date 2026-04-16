-- =============================================
-- Seed de usuarios de prueba para el sistema
-- Contraseñas hasheadas con password_hash('1234', PASSWORD_BCRYPT)
-- =============================================
USE Proyectointegrador;

-- Insertar usuarios de prueba
-- Contraseña para todos: 1234
-- Hash generado con PHP: password_hash('1234', PASSWORD_BCRYPT)

INSERT INTO usuarios (t_codigoinstitucional, t_nombres, t_apellidos, t_correo, t_contrasena, t_activo) VALUES
('ADM001', 'Jairo', 'Peña Fuentes', 'admin@upb.edu.co', '$2y$10$8K1p/a0dR1xFc0aEiQwCkeOw8qXdFz7K5NxGxJKmQLqYZ7Wday4jS', 'S'),
('AUX001', 'Carlos', 'Rodríguez López', 'auxiliar@upb.edu.co', '$2y$10$8K1p/a0dR1xFc0aEiQwCkeOw8qXdFz7K5NxGxJKmQLqYZ7Wday4jS', 'S'),
('PRF001', 'Oscar', 'Santoya Martínez', 'profesor@upb.edu.co', '$2y$10$8K1p/a0dR1xFc0aEiQwCkeOw8qXdFz7K5NxGxJKmQLqYZ7Wday4jS', 'S');

-- Asignar roles
-- admin@upb.edu.co -> administrador (rol 1)
INSERT INTO usuarios_roles (n_idusuario, n_idrol, n_idusuarioasigno) VALUES (1, 1, 1);
-- auxiliar@upb.edu.co -> auxiliar_tecnico (rol 2)
INSERT INTO usuarios_roles (n_idusuario, n_idrol, n_idusuarioasigno) VALUES (2, 2, 1);
-- profesor@upb.edu.co -> profesor (rol 3)
INSERT INTO usuarios_roles (n_idusuario, n_idrol, n_idusuarioasigno) VALUES (3, 3, 1);

-- Insertar sede y edificio de prueba
INSERT INTO sedes (t_nombre) VALUES ('Campus Bucaramanga');
INSERT INTO edificios (n_idsede, t_nombre, t_codigo) VALUES (1, 'Bloque K', 'BLK-K');

-- Insertar laboratorios de prueba
INSERT INTO laboratorios (t_nombre, t_codigo, t_ubicacion, t_descripcion, n_capacidad, n_idusuario, n_idedificio) VALUES
('Lab Automatización Procesos Industriales', 'LAB-API', 'Bloque K, Piso 2', 'Laboratorio de automatización y control de procesos', 30, 1, 1),
('Lab de Física No 1', 'LAB-FIS1', 'Bloque K, Piso 1', 'Laboratorio de física general y mecánica', 25, 1, 1),
('Centro Administrativo de Laboratorios', 'CAL', 'Bloque K, Piso 1', 'Centro de administración general de laboratorios', 10, 1, 1),
('Almacén General', 'ALM-G', 'Bloque K, Sótano', 'Almacén general de equipos y materiales', 50, 1, 1);

-- Insertar elementos de prueba
INSERT INTO elementos (n_idlaboratorio, n_idtipoelemento, t_nombre, t_numeroinventario, t_marca, t_modelo, t_estado, t_codigoqr) VALUES
(1, 1, 'Osciloscopio Digital Tektronix', 'INV-2024-001', 'Tektronix', 'TBS1052B', 'disponible', 'QR-1-001'),
(1, 2, 'Multímetro Fluke 117', 'INV-2024-002', 'Fluke', '117', 'disponible', 'QR-1-002'),
(1, 3, 'Fuente de Poder DC BK Precision', 'INV-2024-003', 'BK Precision', '1672', 'disponible', 'QR-1-003'),
(2, 4, 'Laptop HP ProBook 450', 'INV-2024-004', 'HP', 'ProBook 450 G8', 'disponible', 'QR-2-004'),
(2, 5, 'Protoboard Grande', 'INV-2024-005', 'Genérico', 'MB-102', 'disponible', 'QR-2-005'),
(1, 1, 'Osciloscopio Digital Rigol', 'INV-2024-006', 'Rigol', 'DS1054Z', 'mantenimiento', 'QR-1-006'),
(2, 2, 'Multímetro Fluke 87V', 'INV-2024-007', 'Fluke', '87V', 'disponible', 'QR-2-007');

-- Insertar asignaturas de prueba
INSERT INTO asignaturas (t_nombre) VALUES
('Electrónica Analógica'),
('Circuitos Eléctricos'),
('Física Mecánica'),
('Automatización Industrial'),
('Redes de Computadores');
