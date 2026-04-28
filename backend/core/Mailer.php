<?php
/**
 * Helper minimalista para envío de correos (HU-07.04).
 * Usa la función nativa mail() de PHP. Si SMTP_HOST está configurado en
 * config.php, intenta SMTP directo por socket (sin PHPMailer).
 *
 * Comportamiento:
 *   - Si SMTP_HOST está vacío: usa mail() nativo (requiere sendmail en XAMPP).
 *   - Si SMTP_HOST está configurado: hace conexión SMTP directa por socket.
 *   - Nunca lanza excepción al caller; siempre devuelve bool.
 */
require_once __DIR__ . '/../config/config.php';

class Mailer {

    /**
     * Envía un correo en formato HTML al destinatario indicado.
     * @param string $to       Correo destino
     * @param string $subject  Asunto
     * @param string $htmlBody Cuerpo HTML
     * @return bool true si se envió, false si falló (registra en error_log)
     */
    public static function enviar($to, $subject, $htmlBody) {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Mailer: dirección inválida ($to)");
            return false;
        }

        $from     = SMTP_FROM;
        $fromName = SMTP_FROM_NAME;

        if (!empty(SMTP_HOST)) {
            return self::enviarSMTP($to, $subject, $htmlBody, $from, $fromName);
        }
        // Fallback: mail() nativo
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $fromName <$from>\r\n";
        $headers .= "Reply-To: $from\r\n";
        $headers .= "X-Mailer: SistemaPrestamoUPB/1.0\r\n";
        $ok = @mail($to, $subject, $htmlBody, $headers);
        if (!$ok) error_log("Mailer: mail() nativo falló para $to (¿sendmail configurado?)");
        return (bool)$ok;
    }

    /** Conexión SMTP cruda por socket. Suficiente para servidores que aceptan AUTH LOGIN. */
    private static function enviarSMTP($to, $subject, $body, $from, $fromName) {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $user = SMTP_USER;
        $pass = SMTP_PASS;

        $sock = @fsockopen(($port == 465 ? 'ssl://' : '') . $host, $port, $errno, $errstr, 8);
        if (!$sock) { error_log("SMTP connect fail: $errno $errstr"); return false; }
        stream_set_timeout($sock, 8);

        $cmd = function ($s) use ($sock) {
            if ($s !== null) fwrite($sock, $s . "\r\n");
            $r = '';
            while ($line = fgets($sock, 515)) {
                $r .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $r;
        };

        $cmd(null);                                            // banner
        $cmd("EHLO upbbga.edu.co");
        if ($port == 587) {
            $cmd("STARTTLS");
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd("EHLO upbbga.edu.co");
        }
        if (!empty($user)) {
            $cmd("AUTH LOGIN");
            $cmd(base64_encode($user));
            $cmd(base64_encode($pass));
        }
        $cmd("MAIL FROM: <$from>");
        $cmd("RCPT TO: <$to>");
        $cmd("DATA");
        $msg  = "Subject: " . self::encodeHeader($subject) . "\r\n";
        $msg .= "From: " . self::encodeHeader($fromName) . " <$from>\r\n";
        $msg .= "To: <$to>\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $msg .= $body . "\r\n.";
        $resp = $cmd($msg);
        $cmd("QUIT");
        fclose($sock);
        return strpos($resp, '250') === 0 || strpos($resp, '250') !== false;
    }

    /** Codifica el header (Subject, From) en UTF-8 base64 para no romper acentos. */
    private static function encodeHeader($s) {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    /**
     * Plantilla HTML estándar de notificaciones del sistema.
     * Usa colores institucionales UPB.
     */
    public static function plantillaHTML($titulo, $mensaje, $cta = null, $url = null) {
        $btn = '';
        if ($cta && $url) {
            $btn = "<p style='margin-top:18px'>
                <a href='" . htmlspecialchars($url) . "'
                   style='background:#6B1F7C;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:700'>
                   " . htmlspecialchars($cta) . "
                </a></p>";
        }
        $tit = htmlspecialchars($titulo);
        $msg = nl2br(htmlspecialchars($mensaje));
        $year = date('Y');
        return "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;background:#F0F0F5;margin:0;padding:24px'>
<table style='max-width:540px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden'>
<tr><td style='background:linear-gradient(90deg,#6B1F7C,#C0186A);padding:18px 24px;color:#fff;font-size:16px;font-weight:800'>
UPB · Sistema de Préstamo de Laboratorios</td></tr>
<tr><td style='padding:24px'>
  <h2 style='color:#1A1A2E;margin:0 0 12px'>$tit</h2>
  <p style='color:#4A4A6A;font-size:14px;line-height:1.5'>$msg</p>
  $btn
</td></tr>
<tr><td style='padding:14px 24px;background:#F9F9FC;color:#888;font-size:11px;text-align:center'>
Universidad Pontificia Bolivariana — Bucaramanga · &copy; $year<br>
Este es un mensaje automático, no responder.
</td></tr></table></body></html>";
    }
}
