<?php
$htmlFiles = glob(__DIR__ . '/*.html');

foreach ($htmlFiles as $file) {
    if (basename($file) === 'index.html' || strpos(basename($file), 'Admin-') !== false || strpos(basename($file), 'dashboard') !== false || basename($file) === 'seleccion-modulo.html' || strpos(basename($file), 'agregar-entrega') !== false || strpos(basename($file), 'ver-entregas') !== false || strpos(basename($file), 'reporte-resumen') !== false || strpos(basename($file), 'Prestamos_X_Elementos') !== false || strpos(basename($file), 'Elementos_Prestados.html') !== false || strpos(basename($file), 'Inventario_General') !== false) {
        $content = file_get_contents($file);

        // Check if there's any broken character like 'Ã³' 
        // This denotes that it was written as UTF-8 but is being manipulated as iso-8859-1
        if (strpos($content, 'Ã') !== false) {
            // It's double encoded or ANSI containing raw UTF8 bytes. 
            // We read those bytes as iso-8859-1 string then re-encode properly as utf8.
            $content = mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
        }

        // Now remove existing meta charset if any
        $content = preg_replace('/<meta charset="?[a-zA-Z0-9\-]+"?[^>]*>/i', '', $content);
        
        // Add meta charset="UTF-8" right after <head>
        $content = preg_replace('/<head>/i', "<head>\n  <meta charset=\"UTF-8\" />", $content, 1);

        file_put_contents($file, $content);
        echo "Fixed " . basename($file) . "\n";
    }
}
echo "All HTML files updated successfully.";
?>
