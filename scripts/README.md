# Scripts utilitarios — ejecución SOLO desde CLI

Esta carpeta contiene scripts de uso puntual (migraciones de datos,
generación de hashes, reescritura masiva de HTML). **No están pensados
para correr vía HTTP** y están bloqueados con `.htaccess`.

## Cuándo usar cada uno

| Script | Para qué sirve |
|--------|----------------|
| `genhash.php` | Imprime un hash bcrypt de la contraseña `'1234'`. Para regenerar seeds. |
| `test_listar.php` | Prueba rápida de conexión a BD y listado de laboratorios. Debug. |
| `fix_encoding.php` | Detecta y repara mojibake (archivos UTF-8 mal interpretados como ISO-8859-1). |
| `rewrite_html.php` | Reescritura masiva de HTML — revisar antes de ejecutar. |
| `inject_responsive.php` / `.ps1` | Inyectar media queries responsive en los HTML. |

## Cómo ejecutar

Siempre desde la línea de comandos, nunca desde el navegador:

```bash
"C:/xampp/php/php.exe" "C:/PI 1/prestamo-laboratorios-front/scripts/genhash.php"
```
