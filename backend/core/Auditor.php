<?php
/**
 * Helper genérico de auditoría (HU-09.01, 09.03).
 * Cualquier endpoint que modifique datos críticos puede invocar
 * Auditor::registrar() para dejar huella de quién hizo qué y cuándo.
 */
require_once __DIR__ . '/../config/db.php';

class Auditor {

    /**
     * Registra una acción en la tabla auditoria_acciones.
     * Nunca lanza excepción para no romper el flujo del endpoint que llama.
     *
     * @param string   $tabla        Nombre de la tabla afectada (ej. 'elementos')
     * @param string   $accion       'crear'|'actualizar'|'inactivar'|'cambiar_estado'|'asignar_rol'|'alertar_foto'|'eliminar_foto'
     * @param int|null $idRegistro   ID del registro modificado
     * @param int      $idUsuario    Usuario que ejecutó la acción
     * @param string   $descripcion  Texto humano del cambio
     * @param array    $diff         Opcional: ['antes' => [...], 'despues' => [...]]
     */
    public static function registrar($tabla, $accion, $idRegistro, $idUsuario,
                                     $descripcion = '', $diff = null) {
        try {
            $pdo = Database::getConnection();
            $sql = "INSERT INTO auditoria_acciones (t_tabla, t_accion, n_idregistro,
                                                    n_idusuario, t_descripcion, t_diff, t_ip)
                    VALUES (:t, :a, :r, :u, :d, :j, :ip)";
            $pdo->prepare($sql)->execute([
                ':t'  => substr($tabla, 0, 50),
                ':a'  => substr($accion, 0, 20),
                ':r'  => $idRegistro,
                ':u'  => $idUsuario,
                ':d'  => substr((string)$descripcion, 0, 500),
                ':j'  => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Auditor::registrar - ' . $e->getMessage());
            return false;
        }
    }
}
