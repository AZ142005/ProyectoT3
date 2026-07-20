<?php
// Script de análisis estático simple para validar pureza en Controladores y Modelos

$paths = [
    dirname(__DIR__) . '/app/controllers',
    dirname(__DIR__) . '/app/models'
];

$errors = 0;

function scanDirPurity($dir) {
    global $errors;
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            scanDirPurity($path);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($path);
            
            // 1. Buscar cualquier tag HTML simple (ej. <div, <span, <html, etc.)
            if (preg_match('/<[a-z\/!][^>]*>/i', $content, $matches)) {
                echo "❌ INFRACCIÓN DE PUREZA: Se detectaron etiquetas HTML en '$path'. Coincidencia: '" . trim($matches[0]) . "'\n";
                $errors++;
            }
            
            // 2. Buscar instrucciones de salida directa (echo/print) que no estén comentadas
            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                $trimmed = trim($line);
                
                // Omitir líneas que son comentarios
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
                    continue;
                }
                
                // Buscar echo o print como palabras completas
                if (preg_match('/\becho\b/i', $trimmed) || preg_match('/\bprint\b/i', $trimmed)) {
                    // Excluir funciones legítimas de depuración/formato como print_r o printf
                    if (!preg_match('/\b(print_r|printf)\b/i', $trimmed)) {
                        echo "❌ INFRACCIÓN DE PUREZA: Se detectó salida directa (echo/print) en '$path' en línea " . ($index + 1) . ": '$trimmed'\n";
                        $errors++;
                    }
                }
            }
        }
    }
}

echo "Iniciando análisis de pureza MVC en Controladores y Modelos...\n";
foreach ($paths as $path) {
    if (file_exists($path)) {
        scanDirPurity($path);
    }
}

echo "\n";
if ($errors === 0) {
    echo "✅ ÉXITO: Todos los Controladores y Modelos cumplen con la pureza arquitectónica MVC.\n";
    exit(0);
} else {
    echo "❌ ERROR: Se encontraron $errors infracciones de pureza. Debe corregirlas antes de desplegar.\n";
    exit(1);
}
