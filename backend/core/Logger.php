<?php
/**
 * Logger de acciones para no repudio
 * Registra cada cambio de estado en solicitudes en la tabla historial_solicitudes
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

require_once __DIR__ . '/../config/db.php';

class Logger {

    /**
     * Registrar un cambio de estado en historial_solicitudes
     * Cumple con el principio de no repudio del Track 1
     * 
     * @param int    $n_idsolicitud   ID de la solicitud
     * @param string $estado_anterior Estado anterior (puede ser null para creación)
     * @param string $estado_nuevo    Nuevo estado
     * @param string $comentario      Comentario o razón del cambio
     * @param int    $n_idusuario     ID del usuario que ejecuta la acción
     * @return bool
     */
    public static function registrar($n_idsolicitud, $estado_anterior, $estado_nuevo, $comentario, $n_idusuario) {
        try {
            $pdo = Database::getConnection();
            $sql = "INSERT INTO historial_solicitudes 
                    (n_idsolicitud, t_estadoanterior, t_estadonuevo, t_comentario, n_idusuario) 
                    VALUES (:solicitud, :anterior, :nuevo, :comentario, :usuario)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':solicitud'  => $n_idsolicitud,
                ':anterior'   => $estado_anterior,
                ':nuevo'      => $estado_nuevo,
                ':comentario' => $comentario,
                ':usuario'    => $n_idusuario
            ]);
            return true;
        } catch (PDOException $e) {
            // En caso de error, no detener el flujo pero registrar
            error_log("Error en Logger::registrar - " . $e->getMessage());
            return false;
        }
    }
}
