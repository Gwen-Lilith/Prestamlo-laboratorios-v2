# Guía de Despliegue — Sistema NUV Préstamo de Laboratorios

Sistema desarrollado para la **Universidad Pontificia Bolivariana — Seccional Bucaramanga**.

---

## Stack requerido en el servidor

| Componente | Versión mínima | Notas |
|---|---|---|
| **PHP** | 8.0+ (idealmente 8.2) | con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`, `session`, `json`. Opcionales: `ldap` (login UPB), `curl` (WhatsApp). |
| **MySQL** o **MariaDB** | MySQL 5.7+ / MariaDB 10.4+ | charset `utf8mb4`, collation `utf8mb4_general_ci`. |
| **Apache** | 2.4 | con `mod_rewrite` y `mod_headers` activos. |
| **HTTPS** | recomendado | si hay HTTPS, descomentar `session.cookie_secure` en `config.php`. |

> **No funciona** en plataformas estáticas (GitHub Pages, Netlify, Vercel, Cloudflare Pages, S3) — el sistema requiere ejecutar PHP en el servidor.

---

## Estructura del proyecto

```
prestamo-laboratorios-front/
├── *.html                       ← Páginas del frontend
├── css/                         ← Estilos
├── assets/
│   ├── images/                  ← Logos, avatares por defecto (✅ van al deploy)
│   ├── js/                      ← volver.js (UI globals + helpers)
│   └── fotos/                   ← Subidas en runtime (necesita escritura)
├── backend/
│   ├── .htaccess                ← Bloquea acceso a config/, core/, sql/
│   ├── config/
│   │   ├── config.example.php   ← Template (sin credenciales)
│   │   ├── config.php           ← Generar a partir de example (NO se sube a git)
│   │   └── db.php               ← Singleton PDO
│   ├── core/                    ← Auth, Auditor, Jwt, Logger, Mailer, Notificador, etc.
│   ├── lib/                     ← MicroPdf
│   ├── modules/                 ← 60 endpoints REST
│   └── sql/                     ← Esquema + migraciones + seed
└── scripts/                     ← Utilities CLI (no exponer por web)
```

---

## Pasos de despliegue

### 1. Subir archivos al servidor

Subir todo el contenido de `prestamo-laboratorios-front/` al directorio del hosting (ej. `/var/www/html/prestamos/` o `public_html/prestamos/`).

**No subir:**
- `.git/` (historial)
- `assets/fotos/u*` (fotos de prueba)
- `scripts/` puede no subirse o subirse pero está protegido por `.htaccess`
- `templates/` (huérfanos del proyecto Flask, ya excluidos)

### 2. Crear y configurar la base de datos

```sql
CREATE DATABASE Proyectointegrador
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

CREATE USER 'laboratorio_app'@'%' IDENTIFIED BY 'CONTRASENA_FUERTE';
GRANT SELECT, INSERT, UPDATE, DELETE ON Proyectointegrador.* TO 'laboratorio_app'@'%';
FLUSH PRIVILEGES;
```

### 3. Importar el esquema y datos iniciales

En el orden EXACTO:

```bash
mysql -u root -p Proyectointegrador < backend/sql/Base_de_datosPIMejorada.sql
mysql -u root -p Proyectointegrador < backend/sql/seed_usuarios.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_bug6_estados.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_obs2_solicitudes_laboratorio.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_anuncios.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_calendario.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_calendario_lab.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_foto_perfil.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_alertas_foto.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_notificaciones.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_auditoria.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_festivos_co.sql
mysql -u root -p Proyectointegrador < backend/sql/migracion_whatsapp.sql
```

O usar el `EXPORT-deploy.sql` que ya incluye TODO el esquema final aplicado en orden (más simple).

### 4. Configurar el archivo `config.php`

```bash
cd backend/config/
cp config.example.php config.php
```

Editar `config.php` y reemplazar:

```php
define('DB_HOST',    '10.146.36.56');                    // IP/host del servidor MySQL
define('DB_NAME',    'Proyectointegrador');              // nombre de la BD
define('DB_USER',    'laboratorio_app');                 // usuario con permisos
define('DB_PASS',    'tu_contraseña_real');              // contraseña fuerte
define('BASE_URL',   '/prestamos/');                     // path donde vive el sitio

// Generar JWT_SECRET único:
//   php -r "echo bin2hex(random_bytes(48));"
define('JWT_SECRET', '<<resultado del comando anterior>>');
```

### 5. Permisos de archivos

```bash
# Carpeta donde se suben las fotos de perfil
chmod 775 assets/fotos/
chown www-data:www-data assets/fotos/    # ajustar al usuario web del hosting

# .htaccess de seguridad ya bloquea config/ y core/
```

### 6. Convertir las contraseñas iniciales a bcrypt

Los usuarios seed (`admin@upb.edu.co`, `auxiliar@upb.edu.co`, `profesor@upb.edu.co`) tienen contraseña `1234` pero en BD pueden estar como texto plano. Para convertirlas a bcrypt:

```bash
php scripts/fix_hashes.php
```

Esto regenera todos los hashes del seed a `password_hash('1234', PASSWORD_BCRYPT)`. El sistema solo acepta login con bcrypt válido.

### 7. Verificar la instalación

Acceder a:

```
https://tu-dominio.upb.edu.co/prestamos/index.html
```

Login de prueba:

| Correo | Password | Rol |
|---|---|---|
| `admin@upb.edu.co` | `1234` | Super Admin |
| `auxiliar@upb.edu.co` | `1234` | Admin Módulo |
| `profesor@upb.edu.co` | `1234` | Usuario / Profesor |

**Importante:** cambiar estas contraseñas inmediatamente después del primer login.

---

## Errores comunes y solución

| Síntoma | Causa probable | Solución |
|---|---|---|
| **404 Not Found** en `backend/modules/...` | `BASE_URL` mal | Ajustar `BASE_URL` en `config.php` al path real |
| **500 Internal Server Error** | PHP no conecta a MySQL | Verificar `DB_HOST`/`DB_USER`/`DB_PASS` |
| **"Error de conexión a la base de datos"** | BD no creada o sin permisos | Crear BD + GRANT correcto |
| **Login falla con "Correo o contraseña incorrectos"** | Hashes en BD son texto plano | Ejecutar `php scripts/fix_hashes.php` |
| **Foto de perfil no se sube** | `assets/fotos/` sin permisos | `chmod 775 assets/fotos/` |
| **CORS bloqueado** | Frontend y backend en distinto dominio | Editar `backend/.htaccess` con el dominio exacto |
| **mod_rewrite ignorado** | Apache sin mod_rewrite | Activar el módulo o pedir al hosting |
| **LDAP timeout** | Servidor fuera de la red UPB | Comentar las constantes `LDAP_*` o desplegar dentro de UPB |
| **HTTPS pero sesión se pierde** | Cookies sin `Secure` flag | Descomentar `ini_set('session.cookie_secure', 1)` en `config.php` |

---

## Verificación post-despliegue

Health check incluido en el sistema:

```bash
php scripts/health_check.php
```

Debe reportar todo en verde:
- ✅ 15 tablas con datos
- ✅ 3 vistas funcionando
- ✅ Sin hashes falsos
- ✅ Sin mojibake (caracteres mal codificados)
- ✅ Sin FKs rotas

---

## Servicios externos opcionales

### SMTP para notificaciones por correo

Pedir al CTIC los datos del relay y editar:

```php
define('SMTP_HOST', 'smtp.upb.edu.co');
define('SMTP_PORT', 587);
define('SMTP_USER', 'notificaciones@upb.edu.co');
define('SMTP_PASS', '<<password>>');
```

### WhatsApp (CallMeBot)

Cada usuario configura su propio API key desde "Mi Perfil" tras seguir la guía de
[CallMeBot](https://www.callmebot.com/blog/free-api-whatsapp-messages/).

### LDAP UPB

Solo funciona si el servidor está dentro de la red UPB. Las constantes ya están
configuradas en `config.example.php` con los datos del CTIC. Si despliegan fuera
de UPB, dejar las constantes vacías y el sistema usa solo login local con bcrypt.

---

## Contacto y soporte

- **Repositorio:** https://github.com/Gwen-Lilith/Prestamlo-laboratorios-v2
- **Equipo:** 5 — UPB Bucaramanga
- **Líder de BD:** Alexander Gelves

Cualquier problema durante el despliegue, revisar primero:
1. El log de errores de Apache (`error.log`)
2. La salida del health check (`php scripts/health_check.php`)
3. El estado de la sesión (cookies y `config.php`)
