<?php
/**
 * Configuración general del sistema — TEMPLATE PÚBLICO
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 *
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  INSTRUCCIONES PARA EL DESPLEGADOR                                   ║
 * ║                                                                      ║
 * ║  1. Copia este archivo como `config.php` en la misma carpeta:        ║
 * ║       cp config.example.php config.php                               ║
 * ║                                                                      ║
 * ║  2. Reemplaza los valores marcados con __PONER_*__ por los reales.   ║
 * ║                                                                      ║
 * ║  3. NUNCA subas el config.php al repositorio público — el .gitignore ║
 * ║     ya lo excluye.                                                   ║
 * ║                                                                      ║
 * ║  4. Genera un JWT_SECRET nuevo y único (no copies el de ejemplo):    ║
 * ║       php -r "echo bin2hex(random_bytes(48));"                       ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

// ── Base de datos (pedir al CTIC) ───────────────────────────────────────
// Servidor MySQL/MariaDB del hosting. En la red interna UPB suele ser
// una IP fija (ej. 10.146.x.x). Para desarrollo local con XAMPP: 'localhost'.
define('DB_HOST',    '__PONER_HOST_BD__');           // ej. 10.146.36.56
define('DB_NAME',    '__PONER_NOMBRE_BD__');         // ej. Proyectointegrador
define('DB_USER',    '__PONER_USUARIO_BD__');        // ej. laboratorio_app
define('DB_PASS',    '__PONER_PASSWORD_BD__');       // contraseña del usuario de BD
define('DB_CHARSET', 'utf8mb4');

// ── URL base del proyecto (ajustar según ubicación) ─────────────────────
// Si el proyecto vive en https://dominio/sistema-prestamos/  -> '/sistema-prestamos/'
// Si vive en https://prestamos.upb.edu.co/                   -> '/'
// Si vive en https://upb.edu.co/laboratorios/prestamos/      -> '/laboratorios/prestamos/'
define('BASE_URL', '__PONER_BASE_URL__');             // siempre con / al inicio y al final

// ── LDAP / Directorio Activo UPB (CTIC) ─────────────────────────────────
// Solo funciona si el servidor está dentro de la red UPB.
// Si despliegan FUERA de la red UPB, dejar las constantes vacías para
// que el sistema use solo login local con bcrypt.
define('LDAP_URL',         'ldap://10.146.36.100:389');
define('LDAP_SERVER_HOST', 'ldap://polilla.upbbga.edu.co:389');   // fallback por hostname
define('LDAP_DOMAIN',      'bga.upb');
define('LDAP_BASE_DN',     'OU=OU Empleados,DC=bga,DC=upb');

// ── JWT (HU-08.01) ──────────────────────────────────────────────────────
// CRÍTICO: generar un secret único de mínimo 32 caracteres aleatorios.
// Ejecutar:  php -r "echo bin2hex(random_bytes(48));"
// NUNCA reutilizar el secret entre instalaciones.
define('JWT_SECRET',     '__PONER_JWT_SECRET_GENERADO_ALEATORIO__');
define('JWT_ISSUER',     'sistema-prestamo-upb-bga');
define('JWT_TTL_HOURS',  8);   // tiempo de vida del access token

// ── SMTP / Correo (HU-07.04) ────────────────────────────────────────────
// Si SMTP_HOST queda vacío usa la función mail() nativa (requiere sendmail
// configurado en el hosting). Pedir al CTIC los datos del relay UPB.
define('SMTP_HOST',     '');                          // ej. smtp.upb.edu.co
define('SMTP_PORT',     587);
define('SMTP_USER',     '');                          // ej. notif-prestamos@upb.edu.co
define('SMTP_PASS',     '');
define('SMTP_FROM',     'no-reply@upb.edu.co');
define('SMTP_FROM_NAME','Sistema Préstamo Laboratorios UPB');

// ── WhatsApp (HU-07.04) — vía CallMeBot, API pública gratuita ───────────
// Cada usuario configura su propia API key desde "Mi Perfil". Esta clave
// global se usa solo si el sistema necesita enviar como bot institucional.
define('CALLMEBOT_API_KEY', '');

// ── Zona horaria ────────────────────────────────────────────────────────
date_default_timezone_set('America/Bogota');

// ── Configuración de sesión segura ──────────────────────────────────────
// IMPORTANTE: si el frontend y el backend viven en el MISMO dominio
// (que es el caso normal de este proyecto), usar 'Lax'. Solo poner 'Strict'
// si tienes la certeza de que nunca habrá redirecciones cross-site.
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
// Si el sitio corre por HTTPS (recomendado en producción), descomentar:
// ini_set('session.cookie_secure', 1);
