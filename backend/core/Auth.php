<?php
/**
 * Autenticación y manejo de sesiones
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 * 
 * Implementa: login con bcrypt, sesiones PHP seguras, verificación de roles
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Jwt.php';

class Auth {

    /**
     * Iniciar sesión segura si no está activa
     */
    public static function iniciarSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly'  => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    /**
     * Autenticar usuario con correo y contraseña
     * Valida con password_verify contra t_contrasena (bcrypt)
     * 
     * @param string $correo   Correo electrónico
     * @param string $password Contraseña en texto plano
     * @return array|false     Datos del usuario o false si falla
     */
    public static function login($correo, $password) {
        $pdo = Database::getConnection();

        // Buscar usuario activo por correo
        $sql = "SELECT n_idusuario, t_codigoinstitucional, t_nombres, t_apellidos,
                       t_correo, t_contrasena, t_activo, t_fotoperfil
                FROM usuarios
                WHERE t_correo = :correo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch();

        // Verificar que existe
        if (!$usuario) {
            return false;
        }

        // Verificar que está activo. Distinguimos 'N' (pendiente / inactivo)
        // para que el endpoint pueda dar mensaje específico.
        if ($usuario['t_activo'] !== 'S') {
            return 'pending';
        }

        // Verificar contraseña con bcrypt
        if (!password_verify($password, $usuario['t_contrasena'])) {
            return false;
        }

        // Obtener roles del usuario
        $sqlRoles = "SELECT r.n_idrol, r.t_nombrerol 
                     FROM usuarios_roles ur 
                     JOIN roles r ON r.n_idrol = ur.n_idrol 
                     WHERE ur.n_idusuario = :id AND ur.t_activo = 'S'";
        $stmtRoles = $pdo->prepare($sqlRoles);
        $stmtRoles->execute([':id' => $usuario['n_idusuario']]);
        $roles = $stmtRoles->fetchAll();

        $nombresRoles = array_column($roles, 't_nombrerol');

        // Levantar sesión
        self::iniciarSesion();
        $_SESSION['n_idusuario']   = $usuario['n_idusuario'];
        $_SESSION['t_correo']      = $usuario['t_correo'];
        $_SESSION['t_nombres']     = $usuario['t_nombres'];
        $_SESSION['t_apellidos']   = $usuario['t_apellidos'];
        $_SESSION['nombre_completo'] = $usuario['t_nombres'] . ' ' . $usuario['t_apellidos'];
        $_SESSION['roles']         = $nombresRoles;
        $_SESSION['t_codigoinstitucional'] = $usuario['t_codigoinstitucional'];
        $_SESSION['t_fotoperfil']  = $usuario['t_fotoperfil'] ?? null;

        return [
            'n_idusuario'   => $usuario['n_idusuario'],
            't_correo'      => $usuario['t_correo'],
            't_nombres'     => $usuario['t_nombres'],
            't_apellidos'   => $usuario['t_apellidos'],
            'nombre_completo' => $usuario['t_nombres'] . ' ' . $usuario['t_apellidos'],
            'roles'         => $nombresRoles,
            't_codigoinstitucional' => $usuario['t_codigoinstitucional'],
            't_fotoperfil'  => $usuario['t_fotoperfil'] ?? null
        ];
    }

    /**
     * Cerrar sesión completamente
     */
    public static function logout() {
        self::iniciarSesion();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Verificar que el usuario esté logueado
     * Si no está logueado, devuelve error 401 y detiene la ejecución
     */
    public static function requireLogin() {
        self::iniciarSesion();
        // 1) Sesión PHP clásica
        if (isset($_SESSION['n_idusuario'])) return;
        // 2) JWT en header Authorization (HU-08.01)
        $token = Jwt::leerHeader();
        if ($token) {
            $payload = Jwt::decode($token);
            if ($payload && !empty($payload['sub'])) {
                // Hidratar la sesión con los claims del JWT.
                $_SESSION['n_idusuario']           = (int)$payload['sub'];
                $_SESSION['t_correo']              = $payload['email']  ?? '';
                $_SESSION['t_nombres']             = $payload['nombres'] ?? '';
                $_SESSION['t_apellidos']           = $payload['apellidos'] ?? '';
                $_SESSION['nombre_completo']       = trim(($payload['nombres']??'').' '.($payload['apellidos']??''));
                $_SESSION['roles']                 = $payload['roles'] ?? [];
                $_SESSION['t_codigoinstitucional'] = $payload['codigo'] ?? '';
                $_SESSION['t_fotoperfil']          = $payload['foto']   ?? null;
                return;
            }
        }
        Response::error('No autenticado. Inicie sesión.', 401);
    }

    /**
     * Verificar que el usuario tenga un rol específico
     * @param string|array $rol Nombre del rol o array de roles permitidos
     */
    public static function requireRole($rol) {
        self::requireLogin();
        $rolesRequeridos = is_array($rol) ? $rol : [$rol];
        $rolesUsuario = $_SESSION['roles'] ?? [];

        $tieneRol = false;
        foreach ($rolesRequeridos as $r) {
            if (in_array($r, $rolesUsuario)) {
                $tieneRol = true;
                break;
            }
        }

        if (!$tieneRol) {
            Response::error('No tiene permisos para realizar esta acción.', 403);
        }
    }

    /**
     * Obtener datos del usuario actual de la sesión
     * @return array|null
     */
    public static function currentUser() {
        self::iniciarSesion();
        if (!isset($_SESSION['n_idusuario'])) {
            return null;
        }
        return [
            'n_idusuario'     => $_SESSION['n_idusuario'],
            't_correo'        => $_SESSION['t_correo'],
            't_nombres'       => $_SESSION['t_nombres'],
            't_apellidos'     => $_SESSION['t_apellidos'],
            'nombre_completo' => $_SESSION['nombre_completo'],
            'roles'           => $_SESSION['roles'],
            't_codigoinstitucional' => $_SESSION['t_codigoinstitucional'] ?? '',
            't_fotoperfil'    => $_SESSION['t_fotoperfil'] ?? null
        ];
    }

    /**
     * Rate limiting básico para login (defensa contra fuerza bruta).
     * Guarda intentos fallidos por IP en archivos temp (no requiere BD).
     *
     * Configuración: 5 intentos fallidos en 15 minutos -> bloqueo 15 min.
     */
    private static function rateFile($ip) {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upb_login_' . sha1($ip) . '.json';
    }

    /**
     * Si la IP excedió el límite, corta la ejecución con 429.
     * Por defecto: 10 intentos fallidos en 15 min -> bloqueo 15 min.
     */
    public static function checkRateLimit($ip, $maxIntentos = 10, $ventanaSegundos = 900) {
        $file = self::rateFile($ip);
        if (!is_file($file)) return;
        $data = @json_decode(@file_get_contents($file), true);
        if (!is_array($data)) return;
        $ahora = time();
        $recientes = array_values(array_filter(
            $data,
            function ($t) use ($ahora, $ventanaSegundos) { return ($ahora - (int)$t) < $ventanaSegundos; }
        ));
        if (count($recientes) >= $maxIntentos) {
            $restante = $ventanaSegundos - ($ahora - min($recientes));
            $minutos  = max(1, (int)ceil($restante / 60));
            Response::error("Demasiados intentos fallidos. Espere $minutos minutos.", 429);
        }
    }

    /**
     * Registra un intento fallido para la IP.
     */
    public static function registerFailedAttempt($ip) {
        $file = self::rateFile($ip);
        $data = @json_decode(@file_get_contents($file), true);
        if (!is_array($data)) $data = [];
        $data[] = time();
        // Conservar solo los últimos 20 timestamps.
        if (count($data) > 20) $data = array_slice($data, -20);
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Limpia los intentos tras un login exitoso.
     */
    public static function clearFailedAttempts($ip) {
        $file = self::rateFile($ip);
        if (is_file($file)) @unlink($file);
    }

    /**
     * Login via LDAP / Directorio Activo UPB.
     * Usa la extensión nativa `ldap` de PHP. Requiere `extension=ldap`
     * habilitada en php.ini. Credenciales en backend/config/config.php.
     *
     * Flujo:
     *   1. Intenta bind anónimo con las credenciales user@DOMINIO
     *   2. Si el bind es exitoso (AD valida al usuario), busca el correo
     *      correspondiente en la tabla local `usuarios`.
     *   3. Si existe localmente, levanta la sesión PHP.
     *   4. Si NO existe localmente, devuelve 'not_provisioned' para que
     *      el controlador decida si crearlo o rechazarlo.
     *
     * @param string $name     Nombre de usuario AD (sin @dominio)
     * @param string $password Contraseña AD
     * @return array|string|false  array con datos del usuario si OK,
     *                             'not_provisioned' si AD valida pero no
     *                             hay usuario local, false si AD rechaza.
     */
    public static function login_ldap($name, $password) {
        if (!extension_loaded('ldap')) return false;
        if (empty($name) || empty($password)) return false;

        $userPrincipal = $name . '@' . LDAP_DOMAIN;

        // Intentar bind — primero por IP, luego por hostname.
        $bindOk = self::ldapBind(LDAP_URL, $userPrincipal, $password);
        if (!$bindOk && defined('LDAP_SERVER_HOST') && LDAP_SERVER_HOST !== LDAP_URL) {
            $bindOk = self::ldapBind(LDAP_SERVER_HOST, $userPrincipal, $password);
        }
        if (!$bindOk) return false;

        // AD validó. Buscar al usuario localmente (por correo común en UPB).
        $correoIntento = $name . '@upb.edu.co';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT n_idusuario, t_correo, t_nombres, t_apellidos,
                                      t_codigoinstitucional, t_activo, t_fotoperfil
                               FROM usuarios WHERE t_correo = :c OR t_codigoinstitucional = :n
                               LIMIT 1");
        $stmt->execute([':c' => $correoIntento, ':n' => $name]);
        $usuario = $stmt->fetch();
        if (!$usuario || $usuario['t_activo'] !== 'S') {
            return 'not_provisioned';
        }

        // Obtener roles activos
        $sqlRoles = "SELECT r.t_nombrerol FROM usuarios_roles ur
                     JOIN roles r ON r.n_idrol = ur.n_idrol
                     WHERE ur.n_idusuario = :id AND ur.t_activo = 'S'";
        $stmtR = $pdo->prepare($sqlRoles);
        $stmtR->execute([':id' => $usuario['n_idusuario']]);
        $roles = array_column($stmtR->fetchAll(), 't_nombrerol');

        // Levantar sesión local.
        self::iniciarSesion();
        $_SESSION['n_idusuario']         = $usuario['n_idusuario'];
        $_SESSION['t_correo']            = $usuario['t_correo'];
        $_SESSION['t_nombres']           = $usuario['t_nombres'];
        $_SESSION['t_apellidos']         = $usuario['t_apellidos'];
        $_SESSION['nombre_completo']     = $usuario['t_nombres'] . ' ' . $usuario['t_apellidos'];
        $_SESSION['roles']               = $roles;
        $_SESSION['t_codigoinstitucional'] = $usuario['t_codigoinstitucional'];
        $_SESSION['t_fotoperfil']        = $usuario['t_fotoperfil'] ?? null;

        return [
            'n_idusuario'           => $usuario['n_idusuario'],
            't_correo'              => $usuario['t_correo'],
            't_nombres'             => $usuario['t_nombres'],
            't_apellidos'           => $usuario['t_apellidos'],
            'nombre_completo'       => $usuario['t_nombres'] . ' ' . $usuario['t_apellidos'],
            'roles'                 => $roles,
            't_codigoinstitucional' => $usuario['t_codigoinstitucional'],
            't_fotoperfil'          => $usuario['t_fotoperfil'] ?? null
        ];
    }

    /**
     * Helper: intenta un bind LDAP. true si las credenciales son válidas.
     */
    private static function ldapBind($url, $userPrincipal, $password) {
        $ldap = @ldap_connect($url);
        if (!$ldap) return false;
        @ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($ldap, LDAP_OPT_REFERRALS,        0);
        @ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT,  5);
        $ok = @ldap_bind($ldap, $userPrincipal, $password);
        @ldap_unbind($ldap);
        return (bool)$ok;
    }
}
