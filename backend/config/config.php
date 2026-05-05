<?php
/**
 * Configuración general — VERSIÓN DESARROLLO LOCAL (XAMPP)
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 *
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  ⚠️  ESTE ARCHIVO NO DEBE SUBIRSE AL REPOSITORIO PÚBLICO              ║
 * ║                                                                      ║
 * ║  Está incluido en .gitignore. Sirve solo para desarrollo local       ║
 * ║  con XAMPP estándar (root sin contraseña).                           ║
 * ║                                                                      ║
 * ║  Para PRODUCCIÓN: copiar config.example.php → config.php             ║
 * ║  y reemplazar los placeholders por las credenciales del CTIC.        ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

// ── BD (XAMPP estándar para desarrollo) ─────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'Proyectointegrador');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── URL base (XAMPP htdocs/prestamo-laboratorios/) ──────────────────────
define('BASE_URL', '/prestamo-laboratorios/');

// ── LDAP UPB (solo dentro de la red institucional) ──────────────────────
define('LDAP_URL',         'ldap://10.146.36.100:389');
define('LDAP_SERVER_HOST', 'ldap://polilla.upbbga.edu.co:389');
define('LDAP_DOMAIN',      'bga.upb');
define('LDAP_BASE_DN',     'OU=OU Empleados,DC=bga,DC=upb');

// ── JWT (HU-08.01) ──────────────────────────────────────────────────────
// Secret SOLO para desarrollo local. Cualquiera con XAMPP puede leerlo —
// para producción se genera uno único en el config.php del despliegue.
define('JWT_SECRET',     'desarrollo_local_xampp_NO_USAR_EN_PRODUCCION');
define('JWT_ISSUER',     'sistema-prestamo-upb-bga-dev');
define('JWT_TTL_HOURS',  8);

// ── SMTP (vacío en desarrollo — usa mail() de PHP si está disponible) ───
define('SMTP_HOST',     '');
define('SMTP_PORT',     587);
define('SMTP_USER',     '');
define('SMTP_PASS',     '');
define('SMTP_FROM',     'no-reply@upb.edu.co');
define('SMTP_FROM_NAME','Sistema Préstamo Laboratorios UPB');

// ── WhatsApp (cada usuario activa el suyo desde Mi Perfil) ──────────────
define('CALLMEBOT_API_KEY', '');

// ── Zona horaria ────────────────────────────────────────────────────────
date_default_timezone_set('America/Bogota');

// ── Sesión: 'Lax' funciona tanto en desarrollo como en producción ───────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
