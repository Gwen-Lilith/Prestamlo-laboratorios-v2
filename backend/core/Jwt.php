<?php
/**
 * Implementación mínima de JSON Web Tokens (HS256) sin librerías externas
 * (HU-08.01). Cumple RFC 7519 para los campos básicos.
 *
 * Uso:
 *   $token = Jwt::encode(['sub' => 5, 'roles' => ['profesor']]);
 *   $payload = Jwt::decode($token);  // null si inválido o expirado
 *
 * El secreto se lee de la constante JWT_SECRET en config.php.
 * El TTL por defecto es JWT_TTL_HOURS (8h).
 */
require_once __DIR__ . '/../config/config.php';

class Jwt {

    /** Genera un token con los claims dados + iat/exp/iss automáticos. */
    public static function encode(array $claims, $ttlSeconds = null) {
        $ttl = $ttlSeconds ?: (JWT_TTL_HOURS * 3600);
        $now = time();
        $payload = array_merge([
            'iss' => JWT_ISSUER,
            'iat' => $now,
            'exp' => $now + $ttl
        ], $claims);

        $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h64     = self::b64encode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p64     = self::b64encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $sig     = hash_hmac('sha256', "$h64.$p64", JWT_SECRET, true);
        $s64     = self::b64encode($sig);
        return "$h64.$p64.$s64";
    }

    /** Valida el token y devuelve el payload, o null si inválido/expirado. */
    public static function decode($token) {
        if (!is_string($token) || substr_count($token, '.') !== 2) return null;
        list($h64, $p64, $s64) = explode('.', $token);

        // Verificar firma
        $esperada = hash_hmac('sha256', "$h64.$p64", JWT_SECRET, true);
        $recibida = self::b64decode($s64);
        if (!hash_equals($esperada, $recibida)) return null;

        // Decodificar payload
        $payload = json_decode(self::b64decode($p64), true);
        if (!is_array($payload)) return null;

        // Validar exp e iss
        if (isset($payload['exp']) && $payload['exp'] < time())   return null;
        if (isset($payload['iss']) && $payload['iss'] !== JWT_ISSUER) return null;

        return $payload;
    }

    /** Lee el JWT del header Authorization: Bearer <token>. Devuelve null si no hay. */
    public static function leerHeader() {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$auth && function_exists('getallheaders')) {
            $h = getallheaders();
            $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
        }
        if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim($m[1]);
        return null;
    }

    // ─── helpers base64url ───
    private static function b64encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private static function b64decode($data) {
        $pad = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
