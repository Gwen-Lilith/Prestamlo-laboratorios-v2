<?php
/**
 * Helper de envío de WhatsApp (HU-07.04) usando CallMeBot — API pública gratuita.
 *
 * Para que funcione, cada usuario debe activar el bot en WhatsApp UNA VEZ:
 *   1. Agregar el contacto +34 644 71 81 85 (CallMeBot)
 *   2. Enviar el mensaje: "I allow callmebot to send me messages"
 *   3. Recibe un apikey personal -> guardar en usuarios.t_telefono_apikey
 *
 * Si el usuario no ha activado, este helper devuelve false silenciosamente.
 * Tambien permite generar un link wa.me que el admin puede compartir.
 */
require_once __DIR__ . '/../config/config.php';

class WhatsApp {

    /**
     * Envía un mensaje de WhatsApp al número indicado vía CallMeBot.
     * @param string $telefono Número en formato internacional sin '+', p.ej. "573001234567"
     * @param string $apikey   Token personal entregado por CallMeBot
     * @param string $mensaje  Texto plano del mensaje (max ~640 chars)
     * @return bool
     */
    public static function enviarCallMeBot($telefono, $apikey, $mensaje) {
        if (empty($telefono) || empty($apikey)) return false;
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        $url = 'https://api.callmebot.com/whatsapp.php?phone='
             . urlencode($telefono)
             . '&text='   . urlencode(self::sanitizar($mensaje))
             . '&apikey=' . urlencode($apikey);
        $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
        $resp = @file_get_contents($url, false, $ctx);
        return $resp !== false && stripos($resp, 'sent') !== false;
    }

    /**
     * Genera un link wa.me que abre WhatsApp en el dispositivo del usuario
     * con el mensaje precargado. No requiere apikey ni acuerdo previo.
     * Útil para "Avisar por WhatsApp" desde el panel admin.
     */
    public static function linkWaMe($telefono, $mensaje) {
        $tel = preg_replace('/[^0-9]/', '', (string)$telefono);
        return 'https://wa.me/' . $tel . '?text=' . urlencode(self::sanitizar($mensaje));
    }

    private static function sanitizar($s) {
        return mb_substr(trim((string)$s), 0, 640, 'UTF-8');
    }
}
