# 🧪 Guía Completa de Pruebas en Localhost — XAMPP

## Pre-requisitos

1. **XAMPP** instalado y funcionando (PHP 8+, MySQL/MariaDB)
2. **Navegador moderno** (Chrome, Firefox, Edge)
3. **phpMyAdmin** accesible en `http://localhost/phpmyadmin`

---

## 📋 PASO 1: Copiar el proyecto

Copia **toda** la carpeta del proyecto a htdocs de XAMPP:

```
Copiar de:  C:\PI 1\prestamo-laboratorios-front\
Copiar a:   C:\xampp\htdocs\prestamo-laboratorios\
```

> También puedes crear un **symlink** si prefieres no duplicar archivos:
> ```powershell
> # Ejecutar PowerShell como Administrador
> New-Item -ItemType Junction -Path "C:\xampp\htdocs\prestamo-laboratorios" -Target "C:\PI 1\prestamo-laboratorios-front"
> ```

---

## 📋 PASO 2: Iniciar XAMPP

1. Abre el **XAMPP Control Panel**
2. Haz clic en **Start** en la fila de **Apache**
3. Haz clic en **Start** en la fila de **MySQL**
4. Verifica que ambos estén en verde ✅

---

## 📋 PASO 3: Crear la Base de Datos

### 3.1 — Crear el schema

1. Abre el navegador → `http://localhost/phpmyadmin`
2. Haz clic en la pestaña **SQL** (arriba)
3. Abre el archivo `backend/sql/Base_de_datosPIMejorada.sql` con un editor de texto
4. **Copia TODO el contenido** y pégalo en el campo de texto de phpMyAdmin
5. Haz clic en **Continuar** (o **Go**)
6. Deberías ver el mensaje: **"Proyectointegrador" base de datos creada** ✅

### 3.2 — Insertar datos de prueba

1. En phpMyAdmin, asegúrate de que la base **Proyectointegrador** esté seleccionada (panel izquierdo)
2. Ve a la pestaña **SQL** nuevamente
3. Abre `backend/sql/seed_usuarios.sql` con un editor
4. **Copia TODO el contenido** y pégalo
5. Haz clic en **Continuar**
6. Deberías ver varias filas insertadas ✅

### 3.3 — Verificar

En phpMyAdmin, expande **Proyectointegrador** en el panel izquierdo. Deberías ver:
- `roles` → 3 registros (administrador, auxiliar_tecnico, profesor)
- `usuarios` → 3 registros
- `usuarios_roles` → 3 registros
- `laboratorios` → 2 registros
- `tipos_elementos` → 3 registros
- `elementos` → 3 registros
- `asignaturas` → 2 registros

---

## 📋 PASO 4: Probar el Login

### 4.1 — Abrir el sistema

Abre: **`http://localhost/prestamo-laboratorios/index.html`**

### 4.2 — Credenciales de prueba

| Rol | Correo | Contraseña | Redirección esperada |
|-----|--------|------------|---------------------|
| **Administrador** | `admin@upb.edu.co` | `1234` | → `seleccion-modulo.html` |
| **Auxiliar Técnico** | `auxiliar@upb.edu.co` | `1234` | → `seleccion-modulo.html` |
| **Profesor** | `profesor@upb.edu.co` | `1234` | → `dashboard-usuario.html` |

### 4.3 — Pruebas de login

| Prueba | Acción | Resultado esperado |
|--------|--------|--------------------|
| Login correcto admin | Correo `admin@upb.edu.co`, pass `1234` | Redirige a selección de módulo |
| Login correcto profesor | Correo `profesor@upb.edu.co`, pass `1234` | Redirige a dashboard usuario |
| Login incorrecto | Correo `admin@upb.edu.co`, pass `mal` | Muestra "Correo o contraseña incorrectos." |
| Campos vacíos | Dejar email vacío | Muestra "Por favor completa todos los campos." |
| Correo inválido | Poner `aaa` en email | Muestra error de formato |

---

## 📋 PASO 5: Probar Flujo Administrador

### 5.1 — Seleccionar módulo

1. Login como `admin@upb.edu.co` / `1234`
2. En la pantalla de selección de módulo, el dropdown debería mostrar los laboratorios de la BD:
   - Lab. Electrónica
   - Lab. Redes
3. Selecciona uno y haz clic en **"Ingresar al módulo"**
4. Deberías llegar al **Dashboard del Administrador**

### 5.2 — Gestión de Usuarios (Admin-Usuarios.html)

Navega a: `http://localhost/prestamo-laboratorios/Admin-Usuarios.html`

| Prueba | Acción | Resultado esperado |
|--------|--------|--------------------|
| Listar usuarios | Abrir la página | Los 3 usuarios del seed aparecen en la tabla |
| Buscar | Escribir "admin" en el buscador | Filtra por nombre/correo |
| Crear usuario | Clic en "Registrar usuario" → llenar datos → Guardar | Toast verde, usuario aparece en tabla |
| Cambiar rol | Clic en un usuario → Cambiar el select de rol → "Cambiar Rol" | Toast confirmando |
| Inactivar | Clic en el ícono de ban → Confirmar | Toast "usuario inactivado" |

### 5.3 — Gestión de Equipos (Admin-Equipos.html)

Navega a: `http://localhost/prestamo-laboratorios/Admin-Equipos.html`

| Prueba | Acción | Resultado esperado |
|--------|--------|--------------------|
| Listar elementos | Abrir la página | 3 elementos del seed aparecen |
| Crear laboratorio | Tab "Laboratorios" → "Registrar" → guardar | Nuevo lab aparece en tabla y dropdown |
| Crear tipo | Tab "Tipos" → "Registrar" → guardar | Nuevo tipo aparece |
| Crear elemento | Tab "Elementos" → "Registrar" → llenar datos → guardar | Elemento aparece con código QR generado |
| Editar elemento | Clic en lápiz → cambiar datos → guardar | Datos actualizados |
| Inactivar | Clic en ban → confirmar | Estado cambia a "Inactivo" |
| Filtrar por lab | Usar el dropdown de laboratorio | Solo muestra elementos de ese lab |

### 5.4 — Reportes de Inventario y Prestados

En el menú lateral, sección **Reportes**:

| Prueba | Acción | Resultado esperado |
|--------|--------|--------------------|
| Ver Elementos Prestados | Clic en "Elementos Prestados" | Muestra tabla de lectura con todos los préstamos activos |
| Ver Inventario General | Clic en "Inventario General" | Muestra todos los elementos (disponibles, inactivos, etc) |
| Filtros en Inventario | Usar selects de estado/tipo en "Inventario General" | La tabla se actualiza correctamente y refleja la cantidad |

### 5.5 — Detalle de Solicitudes (Admin-Solicitud-Detalle.html)

Navega a: `http://localhost/prestamo-laboratorios/Admin-Solicitud-Detalle.html`

> ⚠️ Solo funciona si ya hay solicitudes creadas. Primero crea una como profesor (ver Paso 6).

| Prueba | Acción | Resultado esperado |
|--------|--------|--------------------|
| Buscar existente | Ingresar ID `1` y buscar | Muestra detalle completo con historial |
| Buscar inexistente | Ingresar ID `999` | Muestra "Solicitud no encontrada" |

---

## 📋 PASO 6: Probar Flujo Profesor

### 6.1 — Dashboard del Profesor

1. Login como `profesor@upb.edu.co` / `1234`
2. Deberías llegar al **Dashboard del Usuario**
3. Las estadísticas se cargan desde la base de datos

### 6.2 — Crear Solicitud de Préstamo

1. En el menú lateral: **Solicitudes** → **Nueva Solicitud**
2. Selecciona un **laboratorio** del dropdown (cargados desde BD)
3. Selecciona un **elemento** (filtrado por el laboratorio y solo disponibles)
4. Ingresa **Fecha inicio** y **Fecha fin**
5. Escribe una **Justificación**
6. Haz clic en **Enviar Solicitud**

| Prueba | Acción | Resultado esperado |
|--------|--------|--------------------|
| Crear solicitud | Llenar todo y enviar | Toast verde "¡Solicitud enviada!" |
| Campos vacíos | Dejar lab vacío y enviar | Toast rojo "Completa campos obligatorios" |
| Fecha inválida | Fecha fin < fecha inicio | Toast rojo "Fecha fin no puede ser anterior" |

### 6.3 — Ver Mis Solicitudes

1. **Solicitudes** → **Mis Solicitudes**
2. Deberías ver la solicitud recién creada con estado **Pendiente**
3. Puedes **cancelarla** con el ícono ✕ (se comunica con el backend)

### 6.4 — Ver Entregas

1. **Solicitudes** → **Mis Entregas**
2. Muestra las solicitudes aprobadas/finalizadas

---

## 📋 PASO 7: Probar flujo completo (Profesor solicita → Admin aprueba)

1. **Login como profesor** → Crear una solicitud
2. **Cerrar sesión** → **Login como admin**
3. Ir a **Admin-Solicitud-Detalle.html** → Buscar la solicitud por ID
4. Los estados se pueden ver en la BD directamente via phpMyAdmin
5. Para aprobar/rechazar: Se puede usar la API directamente:

```
http://localhost/prestamo-laboratorios/backend/modules/solicitudes/aprobar.php
```
Con body JSON: `{"id": 1, "observaciones": "Aprobada"}`

---

## 📋 PASO 8: Probar APIs directamente (opcional / avanzado)

Puedes probar los endpoints directamente desde la **consola del navegador** (F12 → Console):

### Login
```javascript
fetch('backend/modules/auth/login.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({correo: 'admin@upb.edu.co', password: '1234'}),
  credentials: 'include'
}).then(r => r.json()).then(console.log);
```

### Listar usuarios
```javascript
fetch('backend/modules/usuarios/listar.php', {credentials:'include'})
  .then(r => r.json()).then(console.log);
```

### Listar laboratorios
```javascript
fetch('backend/modules/laboratorios/listar.php', {credentials:'include'})
  .then(r => r.json()).then(console.log);
```

### Listar elementos
```javascript
fetch('backend/modules/elementos/listar.php', {credentials:'include'})
  .then(r => r.json()).then(console.log);
```

### Crear solicitud
```javascript
fetch('backend/modules/solicitudes/crear.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  credentials: 'include',
  body: JSON.stringify({
    idlaboratorio: 1,
    fechainicio: '2026-05-01',
    fechafin: '2026-05-03',
    proposito: 'Práctica de laboratorio',
    elementos: [{idelemento: 1, cantidad: 1}]
  })
}).then(r => r.json()).then(console.log);
```

### Verificar sesión activa
```javascript
fetch('backend/modules/auth/check_session.php', {credentials:'include'})
  .then(r => r.json()).then(console.log);
```

### Exportar CSV
```
http://localhost/prestamo-laboratorios/backend/modules/reportes/exportar_csv.php
```
(Abre directamente en el navegador para descargar el archivo CSV)

---

## ❗ Solución de Problemas

### "Error de conexión con el servidor"
- Verifica que **Apache** y **MySQL** estén encendidos en XAMPP
- Verifica que el proyecto esté en `C:\xampp\htdocs\prestamo-laboratorios\`

### "Error de conexión a la base de datos"
- Verifica que la BD **Proyectointegrador** exista en phpMyAdmin
- Verifica en `backend/config/config.php` que el usuario sea `root` y password vacío

### "No autenticado. Inicie sesión."
- La sesión PHP expiró. Vuelve a hacer login desde `index.html`

### La tabla muestra vacía
- Verifica que ejecutaste `seed_usuarios.sql` en phpMyAdmin
- Revisa la consola del navegador (F12) para ver errores de fetch

### "Access denied" o error 403
- Verifica que el módulo `mod_rewrite` esté habilitado en Apache
- En XAMPP normalmente está habilitado por defecto

---

## ✅ Checklist de Verificación

- [ ] XAMPP Apache + MySQL encendidos
- [ ] Proyecto copiado a `htdocs/prestamo-laboratorios/`
- [ ] BD creada con `Base_de_datosPIMejorada.sql`
- [ ] Datos de prueba insertados con `seed_usuarios.sql`
- [ ] Login admin funciona → redirige a selección de módulo
- [ ] Login profesor funciona → redirige a dashboard usuario
- [ ] Admin-Usuarios muestra usuarios de la BD
- [ ] Admin-Equipos muestra labs/tipos/elementos de la BD
- [ ] Crear nuevo usuario desde Admin-Usuarios funciona
- [ ] Crear nuevo laboratorio desde Admin-Equipos funciona
- [ ] Profesor puede crear solicitud → se guarda en BD
- [ ] Solicitud detalle muestra datos reales por ID
- [ ] Profesor puede cancelar su solicitud pendiente
