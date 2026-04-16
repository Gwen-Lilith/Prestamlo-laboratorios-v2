DROP DATABASE IF EXISTS Proyectointegrador;
CREATE DATABASE Proyectointegrador;
USE Proyectointegrador;

-- tabla de roles
CREATE TABLE roles (
	n_idrol INT AUTO_INCREMENT PRIMARY KEY,
	t_nombrerol VARCHAR(50) NOT NULL,
	t_descripcion VARCHAR(200),
	t_activo VARCHAR(2) NOT NULL DEFAULT 'S'
);

-- tabla de usuarios
CREATE TABLE usuarios (
	n_idusuario INT AUTO_INCREMENT PRIMARY KEY,
	t_codigoinstitucional VARCHAR(20),
	t_nombres VARCHAR(100) NOT NULL,
	t_apellidos VARCHAR(100) NOT NULL,
	t_correo VARCHAR(150) NOT NULL,
	t_contrasena VARCHAR(255),
	t_ldapdn VARCHAR(255),
	t_tokenqr VARCHAR(255),
	dt_fechaqr TIMESTAMP NULL,
	t_activo VARCHAR(2) NOT NULL DEFAULT 'S',
	dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	dt_fechaactu TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- tabla usuarios_roles
CREATE TABLE usuarios_roles (
	n_idusuariosroles INT AUTO_INCREMENT PRIMARY KEY,
	n_idusuario INT NOT NULL,
	n_idrol INT NOT NULL,
	dt_fechaasignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	n_idusuarioasigno INT,
	t_activo VARCHAR(2) DEFAULT 'S',
	FOREIGN KEY (n_idusuario) REFERENCES usuarios(n_idusuario),
	FOREIGN KEY (n_idrol) REFERENCES roles(n_idrol),
	FOREIGN KEY (n_idusuarioasigno) REFERENCES usuarios(n_idusuario)
);

-- tabla de laboratorios
CREATE TABLE laboratorios (
	n_idlaboratorio INT AUTO_INCREMENT PRIMARY KEY,
	t_nombre VARCHAR(150) NOT NULL,
	t_codigo VARCHAR(30),
	t_ubicacion VARCHAR(200),
	t_descripcion VARCHAR(500),
	n_capacidad INT,
	n_idusuario INT,
	t_activo VARCHAR(2) NOT NULL DEFAULT 'S',
	dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	dt_fechaactu TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (n_idusuario) REFERENCES usuarios(n_idusuario)
);

-- tabla tipos_elementos
CREATE TABLE tipos_elementos (
	n_idtipoelemento INT AUTO_INCREMENT PRIMARY KEY,
	t_nombre VARCHAR(100) NOT NULL,
	t_descripcion VARCHAR(300),
	t_unidadmedida VARCHAR(50),
	t_activo VARCHAR(2) NOT NULL DEFAULT 'S',
	dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	dt_fechaactu TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- tabla elementos
CREATE TABLE elementos (
	n_idelemento INT AUTO_INCREMENT PRIMARY KEY,
	n_idlaboratorio INT NOT NULL,
	n_idtipoelemento INT NOT NULL,
	t_nombre VARCHAR(150) NOT NULL,
	t_numeroinventario VARCHAR(80),
	t_marca VARCHAR(80),
	t_modelo VARCHAR(80),
	t_numeroserie VARCHAR(100),
	t_descripcion VARCHAR(500),
	t_observaciones VARCHAR(500),
	t_estado VARCHAR(20) NOT NULL DEFAULT 'disponible',
	t_activo VARCHAR(2) NOT NULL DEFAULT 'S',
	t_codigoqr VARCHAR(255),
	dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	dt_fechaactu TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (n_idlaboratorio) REFERENCES laboratorios(n_idlaboratorio),
	FOREIGN KEY (n_idtipoelemento) REFERENCES tipos_elementos(n_idtipoelemento)
);

-- tabla solicitudes_prestamo
CREATE TABLE solicitudes_prestamo (
	n_idsolicitud INT AUTO_INCREMENT PRIMARY KEY,
	n_idusuario INT NOT NULL,
	n_idlaboratorio INT NOT NULL,
	dt_fechainicio DATETIME NOT NULL,
	dt_fechafin DATETIME NOT NULL,
	dt_fechadevolucion TIMESTAMP NULL,
	t_estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
	t_proposito VARCHAR(500) NOT NULL,
	t_observaciones VARCHAR(500),
	t_observacionesauxiliar VARCHAR(500),
	n_idusuarioaprobo INT,
	dt_fechaaprobacion TIMESTAMP NULL,
	dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	dt_fechaactu TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (n_idusuario) REFERENCES usuarios(n_idusuario),
	FOREIGN KEY (n_idlaboratorio) REFERENCES laboratorios(n_idlaboratorio),
	FOREIGN KEY (n_idusuarioaprobo) REFERENCES usuarios(n_idusuario)
);

-- tabla solicitudes_elementos
CREATE TABLE solicitudes_elementos (
	n_idsolicitudeselementos INT AUTO_INCREMENT PRIMARY KEY,
	n_idsolicitud INT NOT NULL,
	n_idelemento INT NOT NULL,
	n_cantidad INT NOT NULL DEFAULT 1,
	t_estadosalida VARCHAR(20),
	t_estadoretorno VARCHAR(20),
	t_observaciones VARCHAR(300),
	FOREIGN KEY (n_idsolicitud) REFERENCES solicitudes_prestamo(n_idsolicitud),
	FOREIGN KEY (n_idelemento) REFERENCES elementos(n_idelemento)
);

-- tabla historial_solicitudes
CREATE TABLE historial_solicitudes (
	n_idhistorial INT AUTO_INCREMENT PRIMARY KEY,
	n_idsolicitud INT NOT NULL,
	t_estadoanterior VARCHAR(20),
	t_estadonuevo VARCHAR(20) NOT NULL,
	t_comentario VARCHAR(400),
	n_idusuario INT NOT NULL,
	dt_fechaactu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (n_idsolicitud) REFERENCES solicitudes_prestamo(n_idsolicitud),
	FOREIGN KEY (n_idusuario) REFERENCES usuarios(n_idusuario)
);

CREATE TABLE consumibles (
    n_idconsumible   INT AUTO_INCREMENT PRIMARY KEY,
    n_idlaboratorio  INT NOT NULL,
    t_nombre         VARCHAR(150) NOT NULL,
    t_descripcion    VARCHAR(500),
    t_unidadmedida   VARCHAR(50)  NOT NULL DEFAULT 'unidad',
    n_stockactual    INT NOT NULL DEFAULT 0,
    n_stockminimo    INT NOT NULL DEFAULT 0,
    t_ubicacion      VARCHAR(200),
    t_activo         VARCHAR(2) NOT NULL DEFAULT 'S',
    dt_fechacreacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dt_fechaactu     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (n_idlaboratorio) REFERENCES laboratorios(n_idlaboratorio)
);

CREATE TABLE movimientos_consumibles (
    n_idmovimiento   INT AUTO_INCREMENT PRIMARY KEY,
    n_idconsumible   INT NOT NULL,
    t_tipo           VARCHAR(10) NOT NULL,
    n_cantidad       INT NOT NULL,
    n_stockanterior  INT NOT NULL,
    n_stocknuevo     INT NOT NULL,
    t_motivo         VARCHAR(400),
    n_idusuario      INT NOT NULL,
    dt_fecha         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (n_idconsumible) REFERENCES consumibles(n_idconsumible),
    FOREIGN KEY (n_idusuario)    REFERENCES usuarios(n_idusuario)
);

-- Vistas 

CREATE VIEW v_elementos_disponibles AS
SELECT
	e.n_idelemento,
	e.t_nombre,
	e.t_numeroinventario,
	e.t_estado,
	te.t_nombre AS t_tipoelemento,
	l.t_nombre AS t_laboratorio,
	l.t_ubicacion
FROM elementos e
JOIN tipos_elementos te ON te.n_idtipoelemento = e.n_idtipoelemento
JOIN laboratorios l ON l.n_idlaboratorio = e.n_idlaboratorio
WHERE e.t_activo = 'S' AND e.t_estado = 'disponible';

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
WHERE sp.t_estado IN ('aprobada', 'en_curso');

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
WHERE sp.t_estado = 'en_curso'
AND sp.dt_fechafin < CURRENT_TIMESTAMP;

-- tabla sedes
CREATE TABLE sedes (
    n_idsede INT AUTO_INCREMENT PRIMARY KEY,
    t_nombre VARCHAR(100) NOT NULL
);

-- tabla edificios
CREATE TABLE edificios (
    n_idedificio INT AUTO_INCREMENT PRIMARY KEY,
    n_idsede INT NOT NULL,
    t_nombre VARCHAR(100) NOT NULL,
    t_codigo VARCHAR(50),
    FOREIGN KEY (n_idsede) REFERENCES sedes(n_idsede)
);

-- ajuste tabla laboratorios
ALTER TABLE laboratorios
ADD n_idedificio INT NULL,
ADD FOREIGN KEY (n_idedificio) REFERENCES edificios(n_idedificio);

-- tabla de asignaturas
CREATE TABLE asignaturas (
    n_idasignatura INT AUTO_INCREMENT PRIMARY KEY,
    t_nombre VARCHAR(100) NOT NULL,
    t_activo VARCHAR(2) DEFAULT 'S'
);

-- ajuste tabla solicitudes_prestamo
ALTER TABLE solicitudes_prestamo
ADD n_idasignatura INT NULL,
ADD FOREIGN KEY (n_idasignatura) REFERENCES asignaturas(n_idasignatura);

-- ajuste tabla elementos
ALTER TABLE elementos
ADD t_disponible CHAR(1) DEFAULT 'S';

-- DATOS INICIALES

INSERT INTO roles (t_nombrerol, t_descripcion) VALUES
('administrador', 'tiene acceso a todo el sistema'),
('auxiliar_tecnico', 'gestiona los prestamos y elementos del laboratorio'),
('profesor', 'puede solicitar prestamos y ver disponibilidad');

INSERT INTO tipos_elementos (t_nombre, t_descripcion, t_unidadmedida) VALUES
('Osciloscopio', 'mide senales electricas', 'unidad'),
('Multimetro', 'mide voltaje corriente y resistencia', 'unidad'),
('Fuente de poder', 'suministra voltaje regulado', 'unidad'),
('Computador portatil', 'laptop para simulaciones y practicas', 'unidad'),
('Protoboard', 'tablero para armar circuitos', 'unidad');
