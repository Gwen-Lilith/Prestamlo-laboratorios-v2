<?php
$htmlFiles = glob(__DIR__ . '/*.html');

$css = <<<HTML

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
HTML;

foreach ($htmlFiles as $file) {
    if (basename($file) === 'index.html' || strpos(basename($file), 'Admin-') !== false || strpos(basename($file), 'dashboard') !== false || basename($file) === 'seleccion-modulo.html' || strpos(basename($file), 'agregar-entrega') !== false || strpos(basename($file), 'ver-entregas') !== false || strpos(basename($file), 'reporte-resumen') !== false || strpos(basename($file), 'Prestamos_X_Elementos') !== false || strpos(basename($file), 'Elementos_Prestados') !== false || strpos(basename($file), 'Inventario_General') !== false) {
        
        $content = file_get_contents($file);

        // Remove any existing charset declarations just in case to avoid duplicates
        $content = preg_replace('/<meta\s+charset\s*=\s*["\'][a-zA-Z0-9\-]+["\']\s*\/?>/i', '', $content);

        // Inject <meta charset="UTF-8">
        $content = preg_replace('/<head>/i', "<head>\n  <meta charset=\"UTF-8\" />", $content, 1);

        // Inject responsive CSS
        if (basename($file) !== 'index.html' && strpos($content, 'Estilos Responsivos Inyectados') === false) {
            $content = str_replace('</head>', $css . "\n</head>", $content);
        }

        // Write safely
        file_put_contents($file, $content);
        echo "Fixed " . basename($file) . "\n";
    }
}
?>
