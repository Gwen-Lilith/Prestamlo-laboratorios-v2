-- =============================================================
-- MIGRACIÓN: tabla genérica de auditoría (HU-09.01, 09.03)
-- =============================================================
USE Proyectointegrador;

CREATE TABLE IF NOT EXISTS auditoria_acciones (
    n_idauditoria    INT AUTO_INCREMENT PRIMARY KEY,
    t_tabla          VARCHAR(50)  NOT NULL,   -- elementos, usuarios, laboratorios, etc.
    t_accion         VARCHAR(20)  NOT NULL,   -- crear|actualizar|inactivar|cambiar_estado|asignar_rol|alertar_foto|eliminar_foto
    n_idregistro     INT          NULL,       -- id del registro afectado
    n_idusuario      INT          NOT NULL,   -- quien hizo la acción
    t_descripcion    VARCHAR(500) NULL,       -- texto humano describiendo el cambio
    t_diff           TEXT         NULL,       -- JSON con campos antes/después (opcional)
    t_ip             VARCHAR(50)  NULL,
    dt_fecha         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tabla_registro (t_tabla, n_idregistro),
    INDEX idx_usuario (n_idusuario),
    FOREIGN KEY (n_idusuario) REFERENCES usuarios(n_idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
