<?php
/**
 * Endpoint: Autenticación contra el Directorio Activo UPB (LDAP)
 * Método: POST
 * Body:    { "name": "usuario", "password": "..." }
 * Respuesta exitosa:    { "ok": true }
 * Respuesta rechazada:  { "ok": false }, HTTP 401
 *
 * Equivalente PHP puro al script Node que entregó el CTIC. Usa la extensión
 * nativa `ldap` de PHP (no requiere Composer ni librerías externas).
 *
 * Configuración en backend/config/config.php:
 *   LDAP_URL         => ldap://10.146.36.100:389
 *   LDAP_SERVER_HOST => ldap://polilla.upbbga.edu.co:389  (fallback)
 *   LDAP_DOMAIN      => bga.upb
 *   LDAP_BASE_DN     => OU=OU Empleados,DC=bga,DC=upb
 *
 * Nota: el servidor LDAP está en la red interna UPB. Desde fuera del campus
 * las conexiones serán rechazadas por red y el endpoint responderá 401.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido.', 405);
}
if (!extension_loaded('ldap')) {
    Response::error('La extensión LDAP de PHP no está habilitada en el servidor.', 500);
}

$data     = Validator::obtenerBodyJSON();
$name     = Validator::obtenerCampo($data, 'name');
$password = $data['password'] ?? '';

if (empty($name) || empty($password)) {
    Response::error('missing credentials', 400);
}

/**
 * Intenta un bind LDAP contra la URL indicada.
 * Devuelve true si las credenciales son válidas, false en cualquier otro caso.
 */
function intentarBind($url, $userPrincipal, $password) {
    $ldap = @ldap_connect($url);
    if (!$ldap) return false;

    @ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    @ldap_set_option($ldap, LDAP_OPT_REFERRALS,        0);
    @ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT,  5);

    // Silenciar warnings de ldap_bind cuando las credenciales son inválidas
    // (la función los emite igualmente sin afectar al flujo).
    $ok = @ldap_bind($ldap, $userPrincipal, $password);
    @ldap_unbind($ldap);
    return (bool)$ok;
}

$userPrincipal = $name . '@' . LDAP_DOMAIN;

// Intento primero por IP, luego por hostname (si falla red).
$autenticado = intentarBind(LDAP_URL, $userPrincipal, $password);
if (!$autenticado && defined('LDAP_SERVER_HOST') && LDAP_SERVER_HOST !== LDAP_URL) {
    $autenticado = intentarBind(LDAP_SERVER_HOST, $userPrincipal, $password);
}

if ($autenticado) {
    Response::json(['ok' => true], 200, 'Autenticación LDAP exitosa.');
} else {
    // Mismo shape que el script JS de referencia: {ok:false}, 401
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'data' => null, 'mensaje' => 'Credenciales inválidas.'], JSON_UNESCAPED_UNICODE);
    exit;
}
