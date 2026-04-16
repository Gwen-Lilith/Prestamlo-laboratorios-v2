# 🧪 Sistema de Préstamo de Laboratorios — UPB Bucaramanga

> Sistema web para la gestión integral de préstamo de elementos y equipos de laboratorio de la **Universidad Pontificia Bolivariana, Seccional Bucaramanga**.

---

## 📋 Descripción General

Este sistema permite administrar el ciclo completo de préstamo de equipos de laboratorio: desde la solicitud por parte de profesores hasta la aprobación por parte de administradores o auxiliares técnicos, el seguimiento del inventario general, y la generación de reportes exportables en PDF, Excel y CSV.

---

## 🏗️ Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| **Frontend** | HTML5 + CSS3 + JavaScript vanilla |
| **Backend** | PHP 8.2 puro (sin framework) |
| **Base de Datos** | MySQL 8 (via XAMPP) |
| **ORM / DB Access** | PDO con prepared statements |
| **Estilos** | CSS custom (variables, grid, flexbox) |
| **Fuentes** | Google Fonts — Nunito |
| **Iconos** | Font Awesome 6.5 |
| **Exportación** | jsPDF + jsPDF-AutoTable + SheetJS (XLSX) |
| **Servidor local** | Apache (XAMPP) |

---

## 📁 Estructura del Proyecto

```
prestamo-laboratorios-front/
│
├── index.html                        # Login / Registro
├── seleccion-modulo.html             # Selector de laboratorio tras login admin
├── dashboard.html                    # Panel principal Administrador/Auxiliar
├── dashboard-usuario.html            # Panel principal Profesor/Usuario
│
├── Admin-Usuarios.html               # CRUD de usuarios
├── Admin-Equipos.html                # CRUD de elementos (equipos)
├── Admin-Solicitud-Elementos.html    # Listado de solicitudes de elementos
├── Admin-Solicitud-Detalle.html      # Detalle de una solicitud específica
├── Admin-Solicitudes-Seleccionar.html# Selector de solicitudes para aprobar
├── Admin-Solicitudes-Canceladas.html # Solicitudes canceladas/rechazadas
├── Admin_Calendario.html             # Vista calendario de préstamos
├── Admin-Informacion-Importante.html # Tablón de avisos/comunicados
├── Admin-Foto-Perfil.html            # Gestión de foto de perfil
│
├── Elementos_Prestados.html          # Reporte: elementos actualmente prestados
├── Inventario_General.html           # Inventario completo con edición y formatos
├── Prestamos_X_Elementos.html        # Histórico por elemento específico
├── reporte-resumen.html              # Resumen general de préstamos
│
├── agregar-entrega.html              # Registrar devolución de elemento
├── ver-entregas.html                 # Listado de entregas/devoluciones
│
├── assets/
│   └── images/                       # Logo UPB, avatares de roles
├── css/
│   └── style.css                     # Estilos globales compartidos
│
└── backend/
    ├── config/
    │   ├── config.php                # Constantes BD, URL base, timezone
    │   └── db.php                    # Singleton PDO (Database::getConnection())
    │
    ├── core/
    │   ├── Auth.php                  # Login bcrypt, sesiones PHP, verificación de roles
    │   ├── Response.php              # Clase helper para respuestas JSON estandarizadas
    │   ├── Validator.php             # Validación y sanitización de entradas
    │   └── Logger.php                # Registro de actividad / auditoría
    │
    ├── modules/
    │   ├── auth/
    │   │   └── login.php             # POST → autenticar y crear sesión PHP
    │   ├── usuarios/
    │   │   ├── listar.php            # GET → listado de usuarios con roles
    │   │   ├── crear.php             # POST → crear nuevo usuario
    │   │   ├── actualizar.php        # POST → editar usuario existente
    │   │   └── ...
    │   ├── laboratorios/
    │   │   ├── listar.php            # GET → laboratorios activos + conteo elementos
    │   │   └── crear.php             # POST → crear laboratorio
    │   ├── elementos/
    │   │   ├── listar.php            # GET → inventario con tipo y laboratorio
    │   │   ├── crear.php             # POST → agregar elemento
    │   │   ├── actualizar.php        # POST → editar elemento
    │   │   └── cambiar_estado.php    # POST → activar/inactivar elemento
    │   ├── tipos_elementos/
    │   │   └── listar.php            # GET → tipos (Ej: Osciloscopio, Laptop)
    │   ├── solicitudes/
    │   │   ├── listar.php            # GET → solicitudes (filtrable por estado)
    │   │   ├── crear.php             # POST → nueva solicitud de préstamo
    │   │   ├── aprobar.php           # POST → aprobar solicitud
    │   │   ├── rechazar.php          # POST → rechazar solicitud
    │   │   ├── cancelar.php          # POST → cancelar solicitud
    │   │   ├── detalle.php           # GET → detalle completo de una solicitud
    │   │   ├── historial.php         # GET → historial por usuario
    │   │   └── registrar_devolucion.php # POST → registrar devolución
    │   └── reportes/
    │       ├── prestamos_activos.php # GET → préstamos activos en curso
    │       ├── prestamos_vencidos.php# GET → préstamos vencidos (fecha pasada)
    │       ├── uso_por_laboratorio.php # GET → estadística por laboratorio
    │       └── exportar_csv.php      # GET → exportar reporte general en CSV
    │
    └── sql/
        ├── Base_de_datosPIMejorada.sql  # DDL completo — schema de todas las tablas
        └── seed_usuarios.sql            # Datos iniciales: usuarios, labs, elementos prueba
```

---

## 🗄️ Modelo de Base de Datos

La base de datos se llama **`Proyectointegrador`** y contiene las siguientes tablas principales:

| Tabla | Descripción |
|-------|-------------|
| `roles` | Roles del sistema: `administrador`, `auxiliar_tecnico`, `profesor` |
| `usuarios` | Usuarios con código institucional, correo y contraseña bcrypt |
| `usuarios_roles` | Relación N:M usuarios ↔ roles |
| `sedes` | Sedes universitarias (Ej: Campus Bucaramanga) |
| `edificios` | Edificios por sede (Ej: Bloque K) |
| `laboratorios` | Laboratorios/almacenes con código, ubicación, capacidad |
| `tipos_elementos` | Categorías de elementos (Ej: Osciloscopio, Multímetro) |
| `elementos` | Equipos/elementos con inventario, serial, estado, disponibilidad |
| `asignaturas` | Asignaturas que pueden solicitar préstamos |
| `solicitudes_prestamo` | Cabecera del préstamo con fechas, estado y observaciones |
| `solicitudes_elementos` | Detalle — qué elementos lleva cada solicitud |
| `consumibles` | Materiales consumibles (gestión separada) |

---

## 👥 Roles y Permisos

| Rol | Acceso |
|-----|--------|
| **Administrador** | Acceso total: usuarios, elementos, laboratorios, solicitudes, reportes |
| **Auxiliar Técnico** | Aprobar/rechazar solicitudes, gestionar elementos e inventario |
| **Profesor** | Crear solicitudes de préstamo, ver su historial propio |

---

## ⚙️ Instalación y Configuración Local

### Requisitos previos
- **XAMPP** ≥ 8.2 con Apache y MySQL activos
- Navegador moderno (Chrome, Edge, Firefox)

### Pasos de instalación

**1. Clonar / copiar el proyecto**
```bash
# Opción A — Junction / symlink (recomendado para desarrollo)
mklink /J "C:\xampp\htdocs\prestamo-laboratorios" "C:\PI 1\prestamo-laboratorios-front"

# Opción B — Copiar directamente a htdocs
xcopy /E /I "C:\PI 1\prestamo-laboratorios-front" "C:\xampp\htdocs\prestamo-laboratorios"
```

**2. Configurar la base de datos**

Desde el shell de MySQL de XAMPP (o desde el menú de comandos):
```powershell
# Crear schema completo
Get-Content -Raw "backend\sql\Base_de_datosPIMejorada.sql" | C:\xampp\mysql\bin\mysql.exe -u root

# Cargar datos de prueba
Get-Content -Raw "backend\sql\seed_usuarios.sql" | C:\xampp\mysql\bin\mysql.exe -u root
```

O bien, importar ambos archivos desde **phpMyAdmin** → `http://localhost/phpmyadmin`.

**3. Verificar la configuración de BD**

Abrir `backend/config/config.php` y confirmar que los valores coincidan con tu entorno XAMPP:
```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'Proyectointegrador');
define('DB_USER',    'root');
define('DB_PASS',    '');           // Sin contraseña en XAMPP estándar
define('DB_CHARSET', 'utf8mb4');
define('BASE_URL',   '/prestamo-laboratorios/');
```

**4. Acceder al sistema**

```
http://localhost/prestamo-laboratorios/
```

---

## 🔑 Credenciales de Prueba

> Todos los usuarios tienen la contraseña `1234` (hasheada con bcrypt en la BD).

| Correo | Contraseña | Rol |
|--------|-----------|-----|
| `admin@upb.edu.co` | `1234` | Administrador |
| `auxiliar@upb.edu.co` | `1234` | Auxiliar Técnico |
| `profesor@upb.edu.co` | `1234` | Profesor |

---

## 🌊 Flujo Principal de Uso

```
1. Login  →  2. Selección de Laboratorio  →  3. Dashboard
                                              │
                      ┌───────────────────────┼──────────────────────┐
                      ▼                       ▼                      ▼
              Gestión Elementos       Solicitudes Préstamo     Reportes
              (inventario, estado)    (aprobar, rechazar)     (PDF, Excel, CSV)
```

1. El usuario ingresa con su correo y contraseña.
2. El administrador/auxiliar selecciona el **laboratorio** con el que trabajará (guarda en `sessionStorage`).
3. Desde el **dashboard** accede a los módulos: Usuarios, Elementos, Solicitudes, Reportes, Calendario.
4. Las solicitudes pasan por estados: `pendiente → aprobada → en_curso → finalizada` (o `rechazada/cancelada`).

---

## 🔌 API Endpoints Principales

Todos los endpoints retornan JSON con la estructura:
```json
{ "ok": true|false, "data": {...}, "mensaje": "..." }
```

| Método | Endpoint | Descripción | Auth |
|--------|----------|-------------|------|
| `POST` | `/backend/modules/auth/login.php` | Autenticación | ❌ pública |
| `GET` | `/backend/modules/laboratorios/listar.php` | Listar laboratorios activos | ✅ |
| `GET` | `/backend/modules/elementos/listar.php` | Inventario de elementos | ✅ |
| `POST` | `/backend/modules/elementos/actualizar.php` | Editar elemento | ✅ Admin |
| `GET` | `/backend/modules/solicitudes/listar.php` | Listar solicitudes | ✅ |
| `POST` | `/backend/modules/solicitudes/crear.php` | Nueva solicitud | ✅ |
| `POST` | `/backend/modules/solicitudes/aprobar.php` | Aprobar solicitud | ✅ Admin |
| `GET` | `/backend/modules/reportes/prestamos_activos.php` | Reporte préstamos activos | ✅ |
| `GET` | `/backend/modules/reportes/exportar_csv.php` | Exportar reporte CSV | ✅ |

---

## 📝 Notas de Desarrollo

### Convención de columnas en la BD
- `n_` → campos numéricos (IDs, cantidades)
- `t_` → campos texto (nombres, estados, códigos)
- `dt_` → campos fecha/hora (timestamps)

### Seguridad implementada
- Contraseñas hasheadas con **bcrypt** (`password_hash` / `password_verify`)
- Sesiones PHP con cookie `HttpOnly + SameSite=Strict`
- Todas las queries usan **prepared statements** (prevención SQLi)
- Verificación de rol en cada endpoint protegido

### Exportación de reportes
Las vistas `Inventario_General.html`, `Elementos_Prestados.html` y `Prestamos_X_Elementos.html` incluyen exportación del lado cliente vía:
- **jsPDF** + **jsPDF-AutoTable** → PDF con diseño institucional UPB
- **SheetJS (XLSX)** → Excel con hoja de resumen estadístico adicional
- **CSV** → formato universal con soporte UTF-8 (BOM incluido)

---

## 🤝 Equipo de Desarrollo

Proyecto Integrador — **Universidad Pontificia Bolivariana, Seccional Bucaramanga**

| Rol | Responsabilidad |
|-----|----------------|
| Desarrollo Full Stack | Frontend HTML/CSS/JS + Backend PHP/MySQL |
| Arquitectura de BD | Diseño del schema relacional normalizado |
| UI/UX | Diseño de interfaz con identidad visual UPB |

---

## 📄 Licencia

Proyecto académico desarrollado para la **Escuela de Ingeniería — UPB Bucaramanga**.  
Uso interno universitario. No distribuir sin autorización.