# -*- coding: utf-8 -*-
"""
report_code.py
Fragmentos reales de código fuente extraídos de la base de código y su correspondiente
análisis teórico formal (complejidad algorítmica, patrones de diseño, mantenibilidad y seguridad).
"""

CODE_SECTIONS = [
    {
        "titulo": "1. Algoritmo Fonético y de Similitud Textual de Jaro-Winkler en Conciliación Bancaria",
        "archivo": "app/services/ConciliacionBancariaService.php",
        "lenguaje": "PHP 8.x Vanilla",
        "codigo": """public function calcularSimilitudJaroWinkler(string $str1, string $str2): float {
    if ($str1 === $str2) {
        return 1.0;
    }
    $len1 = strlen($str1);
    $len2 = strlen($str2);
    if ($len1 === 0 || $len2 === 0) {
        return 0.0;
    }

    $matchDistance = max(1, intdiv(max($len1, $len2), 2) - 1);
    $str1Matches = array_fill(0, $len1, false);
    $str2Matches = array_fill(0, $len2, false);

    $matches = 0;
    for ($i = 0; $i < $len1; $i++) {
        $start = max(0, $i - $matchDistance);
        $end = min($i + $matchDistance + 1, $len2);

        for ($j = $start; $j < $end; $j++) {
            if ($str2Matches[$j]) continue;
            if ($str1[$i] !== $str2[$j]) continue;

            $str1Matches[$i] = true;
            $str2Matches[$j] = true;
            $matches++;
            break;
        }
    }

    if ($matches === 0) {
        return 0.0;
    }

    $transpositions = 0;
    $k = 0;
    for ($i = 0; $i < $len1; $i++) {
        if (!$str1Matches[$i]) continue;
        while (!$str2Matches[$k]) {
            $k++;
        }
        if ($str1[$i] !== $str2[$k]) {
            $transpositions++;
        }
        $k++;
    }

    $jaro = (($matches / $len1) + ($matches / $len2) + (($matches - ($transpositions / 2)) / $matches)) / 3.0;

    // Bonificación de prefijo común Winkler (hasta 4 caracteres con factor de escala 0.1)
    $prefixLen = 0;
    for ($i = 0; $i < min(4, min($len1, $len2)); $i++) {
        if ($str1[$i] === $str2[$i]) {
            $prefixLen++;
        } else {
            break;
        }
    }

    $winkler = $jaro + ($prefixLen * 0.1 * (1.0 - $jaro));
    return min(1.0, $winkler);
}""",
        "analisis_teorico": (
            "Fundamentación Teórica y Eficiencia Algorítmica:\n"
            "El algoritmo de Jaro-Winkler representa una extensión heurística de la distancia de Jaro, diseñada originalmente "
            "para la deduplicación de registros en censos poblacionales y particularmente efectiva en cadenas numéricas y nombres cortos. "
            "En el contexto de la conciliación bancaria venezolana, los números de referencia emitidos por plataformas interbancarias "
            "(como Pago Móvil o transferencias ACH) sufren frecuentemente de omisiones de ceros a la izquierda, transposición involuntaria "
            "de dígitos adyacentes por error humano del residente al reportar, o inclusión de prefijos alfanuméricos variables (ej. 'REF-', 'PM-').\n\n"
            "Complejidad Temporal: El cálculo opera en un tiempo de O(N · M), donde N y M son las longitudes de las cadenas de referencia "
            "comparadas. Dado que las referencias financieras poseen una longitud típica acotada (6 a 12 caracteres), la cota temporal práctica "
            "es O(1) con un número máximo de iteraciones despreciable (~144 comparaciones en el peor caso), ejecutándose en microsegundos sin impacto "
            "apreciable en la CPU.\n\n"
            "Complejidad Espacial: Requiere O(N + M) espacio en memoria para los arreglos booleanos de coincidencia ($str1Matches y $str2Matches), "
            "lo que previene el desbordamiento de la pila de llamadas (call stack) que sufrirían enfoques recursivos como Levenshtein tradicional.\n\n"
            "Patrón de Diseño y Mantenibilidad: Este algoritmo se encapsula dentro del servicio de dominio 'ConciliacionBancariaService' "
            "(patrón Domain Service en Domain-Driven Design), desacoplando por completo la matemática de comparación difusa (fuzzy matching) "
            "tanto de la capa de persistencia (PDO) como de los controladores HTTP. Esto permite someter la función a pruebas unitarias puras "
            "(verificables en 'tests/ConciliacionTest.php') con casos de prueba deterministas que evalúan transposiciones, identidades exactas "
            "y discrepancias totales."
        )
    },
    {
        "titulo": "2. Descompresión y Tokenización Nativa de Flujos Binarios PDF (/FlateDecode)",
        "archivo": "app/services/GastoParserService.php",
        "lenguaje": "PHP 8.x Vanilla",
        "codigo": """private function extraerTextoDeStream(string $stream): string {
    $descomprimido = @gzuncompress($stream);
    if ($descomprimido === false) {
        $descomprimido = @gzinflate($stream);
    }
    $data = ($descomprimido !== false) ? $descomprimido : $stream;
    $texto = '';

    // Operador estándar de impresión de texto en PDF: (texto) Tj
    if (preg_match_all('/\\((.*?)\\)\\s*Tj/s', $data, $textMatches)) {
        $texto .= ' ' . implode(' ', $textMatches[1]);
    }

    // Operador de arreglo de espaciado y glifos: [(t)(e)(x)(t)(o)] TJ
    if (preg_match_all('/\\[(.*?)\\]\\s*TJ/s', $data, $tjMatches)) {
        foreach ($tjMatches[1] as $tj) {
            if (preg_match_all('/\\((.*?)\\)/s', $tj, $subMatches)) {
                $texto .= ' ' . implode('', $subMatches[1]);
            }
        }
    }

    // Fallback heurístico si no existen operadores estándar
    if (empty(trim($texto))) {
        preg_match_all('/[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\\.,:\\\\/\\-]{3,}/', $data, $plain);
        $texto = implode(' ', $plain[0] ?? []);
    }

    // Normalización de caracteres de escape nativos de especificación ISO 32000-1
    $texto = str_replace(['\\\\(', '\\\\)', '\\\\\\\\'], ['(', ')', '\\\\'], $texto);
    return $texto;
}""",
        "analisis_teorico": (
            "Fundamentación Teórica y Soberanía Tecnológica (RF 32):\n"
            "El estándar internacional ISO 32000-1 para documentos PDF especifica que el contenido visual y textual de las páginas "
            "se serializa en bloques 'stream ... endstream' comprimidos habitualmente mediante el algoritmo Deflate (codificación zlib / RFC 1951), "
            "declarado bajo el filtro '/FlateDecode'. La mayoría de los desarrolladores resuelven esta tarea invocando binarios del sistema operativo "
            "(como 'pdftotext', 'poppler-utils' o envoltorios de Python/Node), introduciendo dependencias externas frágiles y riesgos de inyección de comandos.\n\n"
            "La solución implementada en 'GastoParserService' alcanza soberanía e independencia tecnológica absoluta al explotar la extensión nativa zlib "
            "de PHP mediante '@gzuncompress' y '@gzinflate'. Una vez descomprimido el flujo binario, el parser ejecuta un analizador léxico ligero "
            "mediante expresiones regulares compiladas que reconocen los operadores gráficos de PostScript/PDF: 'Tj' (render text string) y 'TJ' "
            "(render array with character kerning offsets).\n\n"
            "Eficiencia y Consumo de Recursos: La descompresión binaria directa en memoria evita la creación y posterior borrado de archivos temporales "
            "en disco (eliminando la sobrecarga de I/O de almacenamiento). El parser segmenta previamente los objetos '/Type /Page', garantizando que "
            "solo se mantenga en memoria el stream de la página que está siendo evaluada, previniendo el agotamiento del 'memory_limit' en documentos de gran volumen.\n\n"
            "Mantenibilidad: La técnica de fallback heurístico garantiza degradación elegante (graceful degradation): si un generador de PDF de facturación "
            "emite sintaxis no canónica, el sistema aún es capaz de recuperar tokens alfanuméricos estructurados para su inferencia semántica posterior."
        )
    },
    {
        "titulo": "3. Control de Concurrencia Pesimista e Integridad Transaccional ACID",
        "archivo": "app/models/SolicitudesRegistroModel.php",
        "lenguaje": "PHP 8.x / MySQL PDO",
        "codigo": """public function crearSolicitud(array $data): int {
    $db = $this->db();
    $inTransactionExternally = $db->inTransaction();
    if (!$inTransactionExternally) {
        $db->beginTransaction();
    }

    try {
        $unidadId = intval($data['unidad_id'] ?? 0);

        // Bloqueo pesimista exclusivo de fila sobre la unidad habitacional
        $stmtU = $db->prepare("SELECT id, estado, propietario_id FROM unidades WHERE id = :id FOR UPDATE");
        $stmtU->execute(['id' => $unidadId]);
        $unidad = $stmtU->fetch(PDO::FETCH_ASSOC);

        if (!$unidad || (int)$unidad['estado'] !== 1 || !empty($unidad['propietario_id'])) {
            throw new \\RuntimeException('El apartamento seleccionado ya no se encuentra disponible.');
        }

        // Comprobación atómica de ocupación activa en 'personas'
        $stmtP = $db->prepare("SELECT COUNT(*) FROM personas WHERE unidad_id = :id AND estado = 1");
        $stmtP->execute(['id' => $unidadId]);
        if ((int)$stmtP->fetchColumn() > 0) {
            throw new \\RuntimeException('El apartamento seleccionado ya posee residentes asignados.');
        }

        // Comprobación de solicitudes concurrentes en estado 'pendiente'
        $stmtS = $db->prepare("SELECT COUNT(*) FROM solicitudes_registro WHERE unidad_id = :id AND estado = 'pendiente'");
        $stmtS->execute(['id' => $unidadId]);
        if ((int)$stmtS->fetchColumn() > 0) {
            throw new \\RuntimeException('Existe una solicitud previa en revisión para este apartamento.');
        }

        // Inserción inmutable de la solicitud con credencial cifrada
        $stmtIns = $db->prepare("
            INSERT INTO solicitudes_registro (
                unidad_id, cedula, nombre, apellido, telefono, email,
                numero_residentes, password_hash, estado, created_at
            ) VALUES (
                :unidad_id, :cedula, :nombre, :apellido, :telefono, :email,
                :num_res, :pass, 'pendiente', NOW()
            )
        ");
        $stmtIns->execute([
            'unidad_id' => $unidadId,
            'cedula'    => $data['cedula'],
            'nombre'    => $data['nombre'],
            'apellido'  => $data['apellido'],
            'telefono'  => $data['telefono'],
            'email'     => $data['email'],
            'num_res'   => $data['numero_residentes'],
            'pass'      => $data['password_hash'],
        ]);
        $solicitudId = (int)$db->lastInsertId();

        if (!$inTransactionExternally) {
            $db->commit();
        }
        return $solicitudId;
    } catch (\\Exception $e) {
        if (!$inTransactionExternally && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}""",
        "analisis_teorico": (
            "Fundamentación Teórica de Concurrencia y Aislamiento Transaccional:\n"
            "En sistemas de reservas inmobiliarias y asignación habitacional multiusuario, la concurrencia descontrolada puede ocasionar la anomalía "
            "de 'Doble Asignación' (Double-Allocation Anomaly), una condición de carrera crítica (Race Condition) donde dos residentes consultan un "
            "apartamento libre simultáneamente y ambos completan su registro antes de que el estado de la base de datos se actualice.\n\n"
            "Mecanismo de Bloqueo Pesimista: La instrucción 'SELECT ... FOR UPDATE' le instruye al motor de almacenamiento InnoDB de MySQL que coloque "
            "un bloqueo exclusivo a nivel de fila (Exclusive Row-Level Lock - X Lock) sobre el registro específico de la tabla 'unidades'. Cualquier otra "
            "transacción concurrente que intente leer con 'FOR UPDATE' o modificar esa misma unidad quedará encolada y bloqueada en el gestor de la base de "
            "datos hasta que la transacción en curso emita 'COMMIT' o 'ROLLBACK'.\n\n"
            "Garantías ACID: La atomicidad e integridad son absolutas. Si ocurre un fallo en cualquier punto (ej. cédula duplicada, error de conexión o "
            "validación de negocio), el bloque 'catch' dispara un 'rollBack()', restaurando el estado previo de la base de datos sin dejar registros "
            "huérfanos. Al encapsular la verificación de disponibilidad e inserción dentro del mismo bloque atómico, se garantiza el nivel de consistencia "
            "exigido por el estándar SDD (Spec-Driven Development)."
        )
    },
    {
        "titulo": "4. Arquitectura del Front Controller y Enrutador Declarativo Orientado a Objetos",
        "archivo": "app/core/Router.php",
        "lenguaje": "PHP 8.x Vanilla",
        "codigo": """public function dispatch(string $method, string $uri): void {
    $routesForMethod = $this->routes[$method] ?? [];

    foreach ($routesForMethod as $pattern => $routeInfo) {
        // Transformación de placeholders {id} a expresiones regulares nombradas (?P<id>[^/]+)
        $regex = preg_replace('/\\{([a-zA-Z0-9_]+)\\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#i';

        if (preg_match($regex, $uri, $matches)) {
            // Ejecución de middlewares de autenticación y autorización por rol
            foreach ($routeInfo['middlewares'] as $mw) {
                if ($mw === 'auth') {
                    Auth::requireLogin();
                } elseif ($mw === UserRole::ADMIN || $mw === 'admin') {
                    Auth::requireRole(UserRole::ADMIN);
                } elseif ($mw === UserRole::RESIDENTE || $mw === 'residente') {
                    Auth::requireRole(UserRole::RESIDENTE);
                } elseif ($mw === UserRole::AUDITOR || $mw === 'auditor') {
                    Auth::requireRole(UserRole::AUDITOR);
                }
            }

            // Aplicación del middleware global de fiscalización (bloqueo de escrituras para rol auditor)
            RoleMiddleware::handle();

            // Filtrado estricto de parámetros capturados en la URI
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            [$controllerClass, $actionMethod] = $routeInfo['handler'];
            $controller = new $controllerClass();
            $controller->{$actionMethod}(...$params);
            return;
        }
    }

    // Gestión centralizada de ruta inexistente (HTTP 404 Not Found)
    http_response_code(404);
    $notFoundView = VIEWS_PATH . '/errors/404.php';
    if (file_exists($notFoundView)) {
        require_once $notFoundView;
    } else {
        echo \"<h1>404 Not Found</h1>\";
    }
}""",
        "analisis_teorico": (
            "Fundamentación Arquitectónica: Patrón Front Controller (GoF / Fowler):\n"
            "El patrón Front Controller centraliza la gestión de todas las peticiones entrantes a una aplicación web en un único punto de entrada "
            "('public/index.php'), delegando la responsabilidad de resolución sintáctica de URIs a la clase 'Router'. Este enfoque erradica por completo "
            "la proliferación de scripts PHP dispersos en carpetas públicas con lógicas de seguridad desincronizadas.\n\n"
            "Mecanismo de Despacho Dinámico con Parámetros Nombrados: La técnica de compilación de rutas en tiempo de ejecución transforma patrones "
            "declarativos legibles (como '/pagos/detalle/{id}') en expresiones regulares con grupos de captura nombrados '(?P<id>[^/]+)'. Al ejecutarse "
            "'preg_match', los argumentos son extraídos de la URI mediante 'array_filter(..., ARRAY_FILTER_USE_KEY)' y desempaquetados con el operador "
            "splat ('...$params') directamente en los parámetros de tipo del método del controlador destino.\n\n"
            "Seguridad en la Capa de Enrutamiento: Antes de instanciar o transferir el control a cualquier controlador, el despachador evalúa la matriz "
            "de middlewares de seguridad asociados. Si la sesión no existe ('Auth::requireLogin()') o el rol no posee privilegios ('Auth::requireRole()'), "
            "la petición es rechazada de inmediato con redirección o código HTTP 403 Forbidden. Esto asegura el principio de defensa en profundidad, "
            "garantizando que ninguna acción administrativa sea accesible por omisión de chequeos en el cuerpo del controlador."
        )
    },
    {
        "titulo": "5. Gestión Asíncrona del DOM, OCR y Reciclaje de Memoria en Frontend",
        "archivo": "app/views/pagos/residente/subir.php",
        "lenguaje": "JavaScript Nativo ES6+ / HTML5 API",
        "codigo": """function handleFiles(file) {
    // Validación estricta de cuota de almacenamiento en el cliente (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert("El archivo excede el tamaño máximo permitido de 5MB.");
        fileInput.value = '';
        resetPreview();
        return;
    }

    dropzoneInitial.classList.add('hidden');
    dropzonePreview.classList.remove('hidden');
    dropzonePreview.classList.add('flex');
    btnOCR.disabled = false;

    // PREVENCIÓN DE FUGAS DE MEMORIA: Revocación explícita del objeto Blob previo
    if (currentObjectURL) {
        URL.revokeObjectURL(currentObjectURL);
        currentObjectURL = null;
    }

    currentObjectURL = URL.createObjectURL(file);

    if (file.type === 'application/pdf') {
        imagePreview.classList.add('hidden');
        pdfPreview.classList.remove('hidden');
        pdfPreview.classList.add('flex');
        pdfName.textContent = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
    } else if (file.type === 'image/jpeg' || file.type === 'image/png') {
        pdfPreview.classList.add('hidden');
        pdfPreview.classList.remove('flex');
        imagePreview.classList.remove('hidden');
        imagePreview.src = currentObjectURL;
    } else {
        alert("Formato de archivo no válido. Solo JPG, PNG o PDF.");
        fileInput.value = '';
        resetPreview();
    }
}

function resetPreview() {
    if (currentObjectURL) {
        URL.revokeObjectURL(currentObjectURL);
        currentObjectURL = null;
    }
    dropzoneInitial.classList.remove('hidden');
    dropzonePreview.classList.add('hidden');
    dropzonePreview.classList.remove('flex');
    btnOCR.disabled = true;
}""",
        "analisis_teorico": (
            "Fundamentación Teórica de Rendimiento en Clientes Web y Recolección de Basura:\n"
            "Cuando una aplicación web procesa archivos multimedia pesados (como fotografías de recibos de pago en alta resolución tomadas desde teléfonos "
            "móviles), el uso de la API 'URL.createObjectURL(blob)' crea un identificador único que apunta a los bytes crudos del archivo almacenados en la "
            "memoria interna del proceso del navegador (V8 en Chrome/Edge, SpiderMonkey en Firefox).\n\n"
            "Análisis de Fuga de Memoria (Memory Leak Prevention): A diferencia de las cadenas Base64 tradicionales que se recolectan automáticamente "
            "cuando la variable pierde su alcance, los punteros generados por 'createObjectURL' permanecen anclados en memoria durante toda la vida del "
            "documento HTML a menos que se liberen manualmente. Si un usuario prueba consecutivamente 5 o 6 fotos de comprobantes, la pestaña del navegador "
            "acumularía 30-50 MB de memoria no liberada, provocando lentitud o cierres abruptos (crashes) en terminales de bajos recursos.\n\n"
            "La invocación deliberada de 'URL.revokeObjectURL(currentObjectURL)' dentro de 'handleFiles()' y 'resetPreview()' asegura la recolección "
            "inmediata de basura (Garbage Collection) en el motor JavaScript. Esta disciplina técnica, combinada con la validación reactiva de eventos "
            "Drag and Drop ('preventDefault', 'stopPropagation') y la bifurcación visual en el DOM para documentos PDF vs. imágenes matriciales, evidencia "
            "un diseño de interfaz centrado en el rendimiento, la robustez de recursos y la experiencia de usuario (UX)."
        )
    }
]
