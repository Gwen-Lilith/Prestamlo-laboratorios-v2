<#
.SYNOPSIS
    Empaqueta el proyecto NUV en un .zip listo para despliegue.

.DESCRIPTION
    Crea un archivo .zip que contiene SOLO los archivos necesarios para
    desplegar el sistema en un servidor PHP+MySQL. Excluye:
      - .git/                   (historial de Git, ~200 MB)
      - node_modules/, dist/    (builds locales si los hubiera)
      - assets/fotos/u*         (fotos de prueba subidas por usuarios)
      - templates/              (huérfanos del proyecto Flask)
      - GUIA_PRUEBAS_LOCALHOST.md (solo para desarrollo)
      - EXPORT_*.sql            (exports temporales fallidos)
      - *.zip                   (otros zips locales)
      - generar_requerimientos_pdf.py (herramienta local)
      - NUV-Requerimientos-*.pdf (entregable separado)

    El config.php real (con credenciales) NO se incluye automáticamente —
    si quiere mandarlo al desplegador, debe copiarlo manualmente al ZIP
    o entregárselo por canal seguro aparte.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File crear-zip-deploy.ps1
#>

param(
    [string]$NombreSalida = "NUV-prestamo-laboratorios-deploy.zip"
)

$ErrorActionPreference = 'Stop'

# La raíz del proyecto es donde está este script
$Raiz = Split-Path -Parent $MyInvocation.MyCommand.Path
$Salida = Join-Path (Split-Path -Parent $Raiz) $NombreSalida

Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Magenta
Write-Host " Empaquetador NUV - Sistema Préstamo de Laboratorios" -ForegroundColor Magenta
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Magenta
Write-Host ""
Write-Host "Raíz del proyecto: $Raiz"
Write-Host "Salida:            $Salida"
Write-Host ""

# Si ya existe el zip previo, lo quitamos
if (Test-Path $Salida) {
    Remove-Item $Salida -Force
    Write-Host "✓ ZIP previo eliminado" -ForegroundColor Yellow
}

# Carpeta temporal de staging
$Stage = Join-Path $env:TEMP "nuv-deploy-stage-$(Get-Random)"
New-Item -ItemType Directory -Path $Stage | Out-Null
Write-Host "✓ Stage temporal: $Stage" -ForegroundColor DarkGray
Write-Host ""

# Lista de patrones a EXCLUIR (relativos a $Raiz)
$Excluir = @(
    '.git',
    '.gitignore',
    'node_modules',
    'dist',
    'build',
    'cache',
    'tmp',
    'logs',
    'templates',                        # huérfanos del proyecto Flask
    'GUIA_PRUEBAS_LOCALHOST.md',
    'crear-zip-deploy.ps1',             # este script no necesita ir
    'images'                            # symlink roto (no la carpeta real)
)

# Patrones de archivos a excluir (no carpetas)
$ExcluirArchivos = @(
    'EXPORT_*.sql',
    '*.zip',
    '*.rar',
    '*.7z',
    '*.log',
    '*.bak',
    '*.orig',
    'Thumbs.db',
    '.DS_Store',
    'desktop.ini',
    'generar_requerimientos_pdf.py',
    'NUV-Requerimientos-*.pdf'
)

Write-Host "Copiando archivos al stage..." -ForegroundColor Cyan

# Función para verificar si una ruta debe excluirse
function ShouldExclude($relativePath) {
    foreach ($ex in $Excluir) {
        if ($relativePath -eq $ex -or $relativePath.StartsWith("$ex\") -or $relativePath.StartsWith("$ex/")) {
            return $true
        }
    }
    # También excluir archivos sueltos en assets/fotos que empiezan con 'u' (fotos de prueba)
    if ($relativePath -like 'assets\fotos\u*' -or $relativePath -like 'assets/fotos/u*') {
        return $true
    }
    return $false
}

function ShouldExcludeFile($name) {
    foreach ($pat in $ExcluirArchivos) {
        if ($name -like $pat) { return $true }
    }
    return $false
}

# Copiar todo respetando exclusiones
$totalArchivos = 0
$totalBytes = 0
Get-ChildItem -Path $Raiz -Recurse -Force -File | ForEach-Object {
    $rel = $_.FullName.Substring($Raiz.Length).TrimStart('\','/')
    if (ShouldExclude $rel) { return }
    if (ShouldExcludeFile $_.Name) { return }
    # NO incluir el config.php real (tiene credenciales)
    if ($rel -eq 'backend\config\config.php') {
        Write-Host "  ⚠ EXCLUIDO: backend/config/config.php (contiene credenciales)" -ForegroundColor Yellow
        return
    }
    $dest = Join-Path $Stage $rel
    $destDir = Split-Path -Parent $dest
    if (!(Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    Copy-Item -Path $_.FullName -Destination $dest -Force
    $totalArchivos++
    $totalBytes += $_.Length
}

Write-Host ""
Write-Host "✓ $totalArchivos archivos copiados ($([math]::Round($totalBytes/1MB,2)) MB)" -ForegroundColor Green
Write-Host ""

# Asegurar que existe la carpeta assets/fotos vacía con un .gitkeep
$fotosDir = Join-Path $Stage 'assets\fotos'
if (!(Test-Path $fotosDir)) {
    New-Item -ItemType Directory -Path $fotosDir -Force | Out-Null
}
"# Carpeta para fotos de perfil subidas por los usuarios.`n# El servidor web (apache/www-data) debe tener permisos de escritura aqui (chmod 775)." |
    Out-File -FilePath (Join-Path $fotosDir 'README.txt') -Encoding utf8 -Force

Write-Host "Comprimiendo a ZIP..." -ForegroundColor Cyan
Compress-Archive -Path "$Stage\*" -DestinationPath $Salida -CompressionLevel Optimal -Force

# Limpiar stage
Remove-Item -Recurse -Force $Stage

$tamZip = (Get-Item $Salida).Length / 1MB
Write-Host ""
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host " ✓ ZIP generado: $Salida" -ForegroundColor Green
Write-Host "   Tamaño:       $([math]::Round($tamZip, 2)) MB" -ForegroundColor Green
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""
Write-Host "Para entregar al desplegador:" -ForegroundColor Cyan
Write-Host "  1. Compartir este ZIP con el equipo de despliegue (CTIC)."
Write-Host "  2. Compartir las CREDENCIALES de BD por canal SEGURO APARTE"
Write-Host "     (NO ir adjuntas en el mismo ZIP ni por email plano)."
Write-Host "  3. El desplegador debe seguir las instrucciones de DEPLOY.md."
Write-Host ""
