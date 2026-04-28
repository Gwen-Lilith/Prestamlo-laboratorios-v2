<?php
/**
 * Helper para crear notificaciones in-app + correo + WhatsApp (HU-07.x).
 * Se invoca desde los endpoints que cambian estado de solicitudes,
 * disparan alertas de foto, etc.
 *
 * Despacho según t_canal_preferido del usuario:
 *   'inapp'    → solo notificación in-app (default)
 *   'correo'   → in-app + email
 *   'whatsapp' → in-app + whatsapp
 *   'todos'    → los tres canales
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/WhatsApp.php';

class Notificador {

    /**
     * Crea una notificación para un usuario y la despacha por los canales
     * que el usuario tenga configurados.
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

            // Despacho multicanal (HU-07.04)
            self::despacharCanales($idUsuario, $titulo, $mensaje, $link);

            return true;
        } catch (Exception $e) {
            error_log('Notificador::notificar - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Despacha el mensaje al canal preferido del usuario.
     * Si el usuario no tiene canales secundarios configurados, no hace nada.
     * Errores de envío se silencian (solo error_log).
     */
    private static function despacharCanales($idUsuario, $titulo, $mensaje, $link) {
        try {
            $pdo = Database::getConnection();
            $row = $pdo->prepare("SELECT t_correo, t_telefono, t_telefono_apikey, t_canal_preferido
                                  FROM usuarios WHERE n_idusuario = :id");
            $row->execute([':id' => $idUsuario]);
            $u = $row->fetch();
            if (!$u) return;
            $canal = $u['t_canal_preferido'] ?: 'inapp';

            if (in_array($canal, ['correo', 'todos'], true) && !empty($u['t_correo'])) {
                $url  = $link ? (rtrim(BASE_URL, '/') . '/' . $link) : null;
                $html = Mailer::plantillaHTML($titulo, $mensaje,
                    $url ? 'Abrir en el sistema' : null, $url);
                @Mailer::enviar($u['t_correo'], '[UPB Préstamos] ' . $titulo, $html);
            }
            if (in_array($canal, ['whatsapp', 'todos'], true)
                && !empty($u['t_telefono']) && !empty($u['t_telefono_apikey'])) {
                $txt = "🔔 *$titulo*\n$mensaje";
                if ($link) $txt .= "\n\n📲 Abre el sistema para más detalles.";
                @WhatsApp::enviarCallMeBot($u['t_telefono'], $u['t_telefono_apikey'], $txt);
            }
        } catch (Exception $e) {
            error_log('Notificador::despacharCanales - ' . $e->getMessage());
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
