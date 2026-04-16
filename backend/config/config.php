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

// Zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
