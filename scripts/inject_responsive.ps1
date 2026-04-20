$htmlFiles = Get-ChildItem -Path "C:\PI 1\prestamo-laboratorios-front\*.html"
$css = @"

  <!-- Estilos Responsivos Inyectados -->
  <style>
    @media (max-width: 768px) {
      .layout { flex-direction: column; }
      .sidebar {
        position: fixed;
        top: 52px;
        bottom: 0;
        left: 0;
        z-index: 1000;
        width: 250px !important;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        box-shadow: 2px 0 12px rgba(0,0,0,0.15);
      }
      .sidebar.collapsed {
        transform: translateX(0);
      }
      .stats-row { grid-template-columns: 1fr 1fr !important; }
      .form-row { grid-template-columns: 1fr !important; }
      .page-header { flex-direction: column; align-items: flex-start; gap: 6px; }
      .toolbar { flex-direction: column; align-items: stretch; }
      .toolbar-left { flex-direction: column; align-items: stretch; width: 100%; }
      .search-box { width: 100%; }
      .search-box input { width: 100% !important; }
      .filter-select { width: 100%; }
      .btn-primary { width: 100%; justify-content: center; }
      .btn-export { width: 100%; justify-content: center; margin-top: 10px; }
    }
    @media (max-width: 480px) {
      .stats-row { grid-template-columns: 1fr !important; }
    }
  </style>
</head>
"@

$count = 0
foreach ($file in $htmlFiles) {
    if ($file.Name -eq "index.html") { continue }
    $content = Get-Content $file.FullName -Raw
    if (-not $content) { continue }
    if ($content -notmatch "Estilos Responsivos Inyectados") {
        $content = $content -replace '</head>', $css
        Set-Content -Path $file.FullName -Value $content -Encoding UTF8
        Write-Host "Updated $($file.Name)"
        $count++
    } else {
        Write-Host "Skipped $($file.Name) (already injected)"
    }
}
Write-Host "Total updated: $count files."
