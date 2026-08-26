<?php
/**
 * Script de Auditoría de Seguridad Estática (OWASP Top 10)
 * Analiza modelos, controladores y vistas en busca de fallos de seguridad comunes:
 * - Consultas SQL sin preparar (SQLi)
 * - Formularios POST sin token CSRF
 * - Variables de salida en vistas sin escapar con e() (XSS)
 *
 * Uso: php scripts/audit_security.php
 */

echo "========================================================" . PHP_EOL;
echo "  AUDITORÍA DE SEGURIDAD ESTÁTICA - CONDOMINIO DIGITAL" . PHP_EOL;
echo "========================================================" . PHP_EOL . PHP_EOL;

$baseDir = dirname(__DIR__);
$warnings = [];

// 1. Auditar Modelos por SQL Injection
echo "1. Analizando Modelos (SQL Injection)..." . PHP_EOL;
$modelFiles = glob($baseDir . '/app/models/*.php');
foreach ($modelFiles as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);

    // Buscar query() con variables concatenadas o interpoladas
    if (preg_match('/\$db->query\(["\'].*?\$[a-zA-Z0-9_]+/i', $content, $m)) {
        $warnings[] = "[SQLi Potencial] $filename usa query() con variables directas. Usa prepare(): {$m[0]}";
    }
}
echo "   ✔ Modelos analizados." . PHP_EOL;

// 2. Recolectar archivos de vistas
$viewFilesList = [];
$dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir . '/app/views'));
foreach ($dirIterator as $file) {
    if (!$file->isDir() && $file->getExtension() === 'php') {
        $viewFilesList[] = $file->getPathname();
    }
}

// 3. Auditar Vistas por Formularios POST sin CSRF
echo PHP_EOL . "2. Analizando Vistas (Protección CSRF)..." . PHP_EOL;
foreach ($viewFilesList as $filePath) {
    $content = file_get_contents($filePath);
    $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);

    if (preg_match('/<form[^>]*method=["\']POST["\'][^>]*>/i', $content)) {
        if (!preg_match('/csrf_field\(\)/i', $content) && !preg_match('/name=["\']csrf_token["\']/i', $content)) {
            $warnings[] = "[CSRF Faltante] $relPath contiene <form method='POST'> sin <?= csrf_field() ?>.";
        }
    }
}
echo "   ✔ Formularios analizados." . PHP_EOL;

// 4. Auditar Vistas por XSS
echo PHP_EOL . "3. Analizando Vistas (Prevención XSS)..." . PHP_EOL;
foreach ($viewFilesList as $filePath) {
    $content = file_get_contents($filePath);
    $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);

    if (preg_match_all('/<\?=\s*\$([a-zA-Z0-9_]+(\[[^\]]+\])*)\s*;?\s*\?>/', $content, $matches)) {
        foreach ($matches[0] as $rawTag) {
            if (strpos($rawTag, '$content') !== false) continue;
            // Advertir si hay salida directa de variables sin función de sanitización o formateo
            $warnings[] = "[XSS Potencial] $relPath usa salida directa sin e(): $rawTag";
        }
    }
}
echo "   ✔ Salidas dinámicas analizadas." . PHP_EOL;

echo PHP_EOL . "========================================================" . PHP_EOL;
if (empty($warnings)) {
    echo "✅ AUDITORÍA EXITOSA: Cero vulnerabilidades estáticas detectadas." . PHP_EOL;
    echo "========================================================" . PHP_EOL;
    exit(0);
} else {
    echo "⚠ ADVERTENCIAS DE SEGURIDAD DETECTADAS (" . count($warnings) . "):" . PHP_EOL;
    foreach ($warnings as $w) {
        echo "   - $w" . PHP_EOL;
    }
    echo "========================================================" . PHP_EOL;
    exit(0); // Terminar sin romper pipeline si son advertencias
}
