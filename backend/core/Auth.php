<?php
/**
 * Autenticación y manejo de sesiones
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 * 
 * Implementa: login con bcrypt, sesiones PHP seguras, verificación de roles
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Response.php';

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
                       t_correo, t_contrasena, t_activo
                FROM usuarios 
                WHERE t_correo = :correo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch();

        // Verificar que existe
        if (!$usuario) {
            return false;
        }

        // Verificar que está activo
        if ($usuario['t_activo'] !== 'S') {
            return false;
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

        return [
            'n_idusuario'   => $usuario['n_idusuario'],
            't_correo'      => $usuario['t_correo'],
            't_nombres'     => $usuario['t_nombres'],
            't_apellidos'   => $usuario['t_apellidos'],
            'nombre_completo' => $usuario['t_nombres'] . ' ' . $usuario['t_apellidos'],
            'roles'         => $nombresRoles,
            't_codigoinstitucional' => $usuario['t_codigoinstitucional']
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
        if (!isset($_SESSION['n_idusuario'])) {
            Response::error('No autenticado. Inicie sesión.', 401);
        }
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
            't_codigoinstitucional' => $_SESSION['t_codigoinstitucional'] ?? ''
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
     * STUB: Login via LDAP / Directorio Activo UPB
     * Preparado para futura integración con el Directorio Activo de la universidad
     * 
     * @param string $correo   Correo institucional
     * @param string $password Contraseña LDAP
     * @return array|false
     */
    /*
    public static function login_ldap($correo, $password) {
        // Configuración del servidor LDAP de la UPB
        // $ldap_host = 'ldap://directorio.upb.edu.co';
        // $ldap_port = 389;
        // $ldap_base_dn = 'DC=upb,DC=edu,DC=co';
        //
        // $ldap_conn = ldap_connect($ldap_host, $ldap_port);
        // ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        // ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
        //
        // // Intentar bind con las credenciales del usuario
        // $ldap_dn = "CN={$correo},{$ldap_base_dn}";
        // $bind = @ldap_bind($ldap_conn, $ldap_dn, $password);
        //
        // if ($bind) {
        //     // Usuario autenticado por LDAP
        //     // Buscar o crear en la tabla local de usuarios
        //     // Asignar rol por defecto (profesor)
        //     // Iniciar sesión local
        //     ldap_close($ldap_conn);
        //     return self::login($correo, $password);
        // }
        //
        // ldap_close($ldap_conn);
        // return false;

        return false; // Stub - no implementado aún
    }
    */
}
