<?php
/**
 * Test Runner personalizado para ProyectoT3.
 * Ejecuta: php tests/run.php
 * Opcional: php tests/run.php --filter=ConfigTest
 */

// Suppress all output before session config
ob_start();

// Configure sessions BEFORE anything else
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar configuración base del proyecto
$configLoaded = false;
try {
    require_once __DIR__ . '/../app/config/config.php';
    $configLoaded = true;
} catch (\Throwable $e) {
    // Config puede fallar si .env no existe
}

// Auto-descubrir archivos de test
$testFiles = glob(__DIR__ . '/*Test.php');
$filter = $argv[1] ?? null;

$allResults = [];
$startTime = microtime(true);

ob_end_flush();

echo "\n";
echo "╔══════════════════════════════════════════════════╗\n";
echo "║       PROYECTOT3 — SUITE DE PRUEBAS UNITARIAS   ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

foreach ($testFiles as $file) {
    $className = basename($file, '.php');

    if ($filter && !str_contains($className, $filter)) {
        continue;
    }

    require_once $file;
    $fqcn = "Tests\\{$className}";

    if (!class_exists($fqcn)) {
        continue;
    }

    echo "━━━ {$className} ━━━\n";

    $methods = (new ReflectionClass($fqcn))->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $testInstance = new $fqcn();
        $methodName = $method->getName();
        $shortMethod = preg_replace('/^test/', '', $methodName);
        $shortMethod = lcfirst($shortMethod);

        set_error_handler(function ($severity, $message) {
            throw new \RuntimeException("PHP Error [{$severity}]: {$message}");
        });

        try {
            $testInstance->$methodName();
        } catch (\Throwable $e) {
            $testInstance->addError("EXCEPTION in {$methodName}: {$e->getMessage()}");
        }

        restore_error_handler();

        $results = $testInstance->results();

        if ($results['failed'] === 0 && $results['errors'] === 0) {
            if ($results['skipped'] > 0) {
                echo "  ⏭ {$shortMethod} (skipped)\n";
            } else {
                echo "  ✅ {$shortMethod}\n";
            }
        } else {
            echo "  ❌ {$shortMethod}\n";
            foreach ($results['failures'] as $fail) {
                echo "     ⚠ {$fail}\n";
            }
        }

        $allResults[$className . '::' . $methodName] = $results;
    }

    echo "\n";
}

$elapsed = round(microtime(true) - $startTime, 3);
$totalPassed = array_sum(array_column($allResults, 'passed'));
$totalFailed = array_sum(array_column($allResults, 'failed'));
$totalErrors = array_sum(array_column($allResults, 'errors'));
$totalSkipped = array_sum(array_column($allResults, 'skipped'));
$totalTests = count($allResults);

echo "╔══════════════════════════════════════════════════╗\n";
echo "║                   RESUMEN                       ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  Total: {$totalTests} tests | ✅ {$totalPassed} passed | ❌ {$totalFailed} failed | ⚠ {$totalErrors} errors | ⏭ {$totalSkipped} skipped\n";
echo "║  Tiempo: {$elapsed}s\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

if ($totalFailed > 0 || $totalErrors > 0) {
    echo "FALLO — Hay pruebas que no pasaron.\n";
    exit(1);
} else {
    echo "TODAS LAS PRUEBAS PASARON.\n";
    exit(0);
}
