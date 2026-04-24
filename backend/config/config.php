<?php
/**
 * Configuración general del sistema
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

// Configuración de base de datos (XAMPP estándar)
define('DB_HOST', 'localhost');
define('DB_NAME', 'Proyectointegrador');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// URL base del proyecto (ajustar según ubicación en htdocs)
define('BASE_URL', '/prestamo-laboratorios/');

// ── Configuración LDAP / Directorio Activo UPB ──
// Credenciales entregadas por el CTIC. El servidor LDAP está en la red
// interna de la UPB; desde fuera del campus estas conexiones fallarán.
define('LDAP_URL',         'ldap://10.146.36.100:389');
define('LDAP_SERVER_HOST', 'ldap://polilla.upbbga.edu.co:389');   // fallback por hostname
define('LDAP_DOMAIN',      'bga.upb');
define('LDAP_BASE_DN',     'OU=OU Empleados,DC=bga,DC=upb');

// Zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
