<?php
/**
 * Helper para crear notificaciones in-app a usuarios.
 * Se invoca desde los endpoints que cambian estado de solicitudes,
 * disparan alertas de foto, etc. (HU-07.x y HU-08.10)
 */
require_once __DIR__ . '/../config/db.php';

class Notificador {

    /**
     * Crea una notificación para un usuario.
     * Nunca lanza excepción al caller (solo error_log) para no romper el flujo
     * principal del endpoint que la dispara.
     */
    public static function notificar($idUsuario, $tipo, $titulo, $mensaje,
                                     $link = null, $referencia = null) {
        try {
            $pdo = Database::getConnection();
            $sql = "INSERT INTO notificaciones (n_idusuario, t_tipo, t_titulo, t_mensaje, t_link, n_referencia)
                    VALUES (:uid, :tipo, :tit, :msg, :link, :ref)";
            $pdo->prepare($sql)->execute([
                ':uid'  => $idUsuario,
                ':tipo' => substr($tipo, 0, 30),
                ':tit'  => substr($titulo, 0, 200),
                ':msg'  => substr($mensaje, 0, 1000),
                ':link' => $link,
                ':ref'  => $referencia
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Notificador::notificar - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifica a TODOS los administradores y auxiliares técnicos.
     * Útil cuando un evento global debe llegar a todo el staff.
     */
    public static function notificarAdmins($tipo, $titulo, $mensaje, $link = null, $referencia = null) {
        try {
            $pdo = Database::getConnection();
            $rows = $pdo->query(
                "SELECT u.n_idusuario FROM usuarios u
                 JOIN usuarios_roles ur ON ur.n_idusuario = u.n_idusuario AND ur.t_activo='S'
                 JOIN roles r ON r.n_idrol = ur.n_idrol
                 WHERE u.t_activo='S' AND r.t_nombrerol IN ('administrador','auxiliar_tecnico')"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $uid) {
                self::notificar($uid, $tipo, $titulo, $mensaje, $link, $referencia);
            }
            return true;
        } catch (Exception $e) {
            error_log('Notificador::notificarAdmins - ' . $e->getMessage());
            return false;
        }
    }
}
