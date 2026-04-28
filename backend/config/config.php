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

// ── JWT (HU-08.01) ──
// Secreto firmado HMAC-SHA256. CAMBIAR en producción. Generado con random_bytes.
define('JWT_SECRET',     'NUV_UPB_BGA_2026_*7tHk!9pZeR$2qXmW#3vYsAfL_secreto_jwt_cambiar_en_prod');
define('JWT_ISSUER',     'sistema-prestamo-upb-bga');
define('JWT_TTL_HOURS',  8);   // tiempo de vida del access token

// ── SMTP / Correo (HU-07.04) ──
// Si SMTP_HOST queda vacío usa la función mail() nativa de PHP (requiere
// sendmail configurado en XAMPP). En producción, configurar al SMTP UPB.
define('SMTP_HOST',     '');                         // ej. smtp.upb.edu.co
define('SMTP_PORT',     587);
define('SMTP_USER',     '');                         // ej. notif-prestamos@upb.edu.co
define('SMTP_PASS',     '');
define('SMTP_FROM',     'no-reply@upb.edu.co');
define('SMTP_FROM_NAME','Sistema Préstamo Laboratorios UPB');

// ── WhatsApp (HU-07.04) — vía CallMeBot, API pública gratuita ──
// Si CALLMEBOT_API_KEY queda vacío, los enlaces wa.me se generan pero no
// se hacen llamadas API automáticas.
define('CALLMEBOT_API_KEY', '');     // se obtiene activando el bot en WhatsApp

// Zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
