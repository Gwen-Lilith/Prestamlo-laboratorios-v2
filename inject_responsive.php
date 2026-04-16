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

$successCount = 0;
foreach ($htmlFiles as $file) {
    if (basename($file) === 'index.html') continue; // skip index since it has style.css
    $content = file_get_contents($file);
    if (strpos($content, 'Estilos Responsivos Inyectados') === false) {
        $content = str_replace('</head>', $css . "\n</head>", $content);
        file_put_contents($file, $content);
        $successCount++;
        echo "Updated " . basename($file) . "\n";
    } else {
        echo "Skipped " . basename($file) . " (already injected)\n";
    }
}
echo "\nTotal updated: " . $successCount . " files.\n";
?>
