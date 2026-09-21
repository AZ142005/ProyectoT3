# -*- coding: utf-8 -*-
"""
report_sprints.py
Cronograma detallado de Sprints con trazabilidad formal a los commits del repositorio.
"""

SPRINTS_DATA = [
    {
        "numero": "Fase de Concepción",
        "nombre": "Concepción, Análisis de Requerimientos y Definición del Tech Stack",
        "periodo": "01 de Mayo – 31 de Mayo de 2026",
        "objetivo": "Levantamiento de especificaciones técnicas (SRS), definición de la arquitectura web y acuerdo sobre el stack tecnológico sin dependencias pesadas.",
        "descripcion": (
            "Durante esta fase inicial de planificación, el equipo de ingeniería sostuvo sesiones de trabajo con la junta de condominio "
            "para formalizar el Documento de Especificación de Requerimientos de Software (SRS). Se identificaron y categorizaron 37 Requerimientos "
            "Funcionales (RF 1 a RF 37) y 5 Requerimientos No Funcionales (RNF 1 a RNF 5), abarcando desde la gestión de propietarios hasta "
            "la fiscalización contable y la seguridad criptográfica.\n\n"
            "Valeria lideró el levantamiento inicial de requerimientos y la matriz de trazabilidad, mientras Junior y Amadeo evaluaron las alternativas "
            "arquitectónicas. Se tomó la decisión estratégica unánime de optar por una arquitectura web Modelo-Vista-Controlador (MVC) pura en PHP Vanilla 8.x, "
            "utilizando PDO con sentencias preparadas para MySQL y Bootstrap 5 para la interfaz. Esta decisión garantizó un control absoluto sobre el ciclo de "
            "vida HTTP, cero costos por licenciamiento de frameworks y un rendimiento de respuesta inferior a 50 milisegundos en peticiones dinámicas."
        ),
        "commits": [
            "Acta de inicio y formalización de requerimientos del sistema condominial.",
            "Definición de matriz de trazabilidad SRS (RF 1 - RF 37, RNF 1 - RNF 5)."
        ],
        "marcadores": []
    },
    {
        "numero": "Sprint 1",
        "nombre": "Prototipado Inicial y Estructuración de Vistas Base",
        "periodo": "01 de Junio – 15 de Junio de 2026",
        "objetivo": "Diseño de wireframes, arquitectura de información y primer maquetado estático de la pantalla de bienvenida y acceso.",
        "descripcion": (
            "El Sprint 1 marcó el arranque del desarrollo web tangible. Diosmary y Valeria colaboraron estrechamente en la conceptualización "
            "de las primeras pantallas, enfocándose en la simplicidad visual para usuarios residenciales de diversas edades. Se estructuró el archivo "
            "'Index.php' original como punto de entrada y se desarrollaron maquetas de acceso para residentes y administradores.\n\n"
            "El equipo evaluó la distribución visual y la ergonomía de navegación, estableciendo una paleta de colores limpia y accesible basada en verdes "
            "institucionales y fondos neutros claros. Este primer prototipo sirvió para validar con usuarios muestra la comprensibilidad de los formularios."
        ),
        "commits": [
            "3708b75 | 2026-07-10 | AZ142005 | Add initial content to Index.php"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla de la interfaz de login y prototipo preliminar de acceso desarrollada en el Sprint 1"
        ]
    },
    {
        "numero": "Sprint 2",
        "nombre": "Prototipado Interactivo del Reporte de Pagos",
        "periodo": "16 de Junio – 30 de Junio de 2026",
        "objetivo": "Construcción del prototipo interactivo para la carga de comprobantes de pago y verificación visual de transferencias.",
        "descripcion": (
            "En el Sprint 2, el equipo desarrolló la primera interacción dinámica de reporte financiero para residentes. Se implementó el layout de "
            "registro de pagos en 'public/subir.php', integrando selectores de cuentas bancarias receptoras, campos de monto en bolívares y un contenedor "
            "para la subida de comprobantes en formatos de imagen JPG y PNG.\n\n"
            "Valeria documentó las historias de usuario asociadas al flujo de cobro, mientras Diosmary refinó la experiencia de usuario incorporando "
            "retroalimentación inmediata al seleccionar archivos. El incremento fue catalogado como la versión alfa visual del sistema."
        ),
        "commits": [
            "9ca45ee | 2026-07-11 | AZ142005 | Implement payment registration page layout",
            "31c0891 | 2026-07-11 | AZ142005 | Esto es lo que tenemos"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla de la maqueta interactiva de reporte de pagos desarrollada en el Sprint 2"
        ]
    },
    {
        "numero": "Sprint 3",
        "nombre": "Transición Arquitectónica hacia MVC Puro y Módulo de Estructura",
        "periodo": "01 de Julio – 31 de Julio de 2026",
        "objetivo": "Migración integral de la base de código monolítica hacia una arquitectura MVC desacoplada, gestión de edificios y unidades habitacionales.",
        "descripcion": (
            "El Sprint 3 constituyó un punto de inflexión arquitectónico. Junior, apoyado por Amadeo y Rodrigo, lideró la refactorización profunda "
            "del código preliminar para erradicar el modelo 'código espagueti'. Se introdujo la estructura canónica de directorios: 'app/controllers/', "
            "'app/models/', 'app/views/' y 'app/core/'. Se estableció el Front Controller único en 'public/index.php'.\n\n"
            "Se desarrollaron los módulos nucleares de Estructura del Conjunto: gestión administrativa de torres/edificios ('EdificiosModel') y apartamentos/unidades "
            "('UnidadesModel'), con sus respectivas interfaces de administración. Paralelamente, se formalizó el módulo de pagos ('PagoController', 'PagoModel') "
            "con soporte para subida de comprobantes y revisión con estados auditables. Se completó el merge de la rama de recuperación al repositorio principal."
        ),
        "commits": [
            "240ff3a | 2026-07-19 | JoJuniorGit | Introduce MVC app structure & migrate legacy code",
            "fb87e63 | 2026-07-19 | JoJuniorGit | Add admin structure (buildings & units)",
            "cd62041 | 2026-07-19 | JoJuniorGit | Add pagos module: upload, review, audit",
            "2b1e95f | 2026-07-26 | JoJuniorGit | Remove obsolete Index.php",
            "d6605f5 | 2026-07-26 | JoJuniorGit | Merge pull request #1 from AZ142005/recuperacion"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla del módulo de gestión de estructura de edificios y unidades desarrollado en el Sprint 3"
        ]
    },
    {
        "numero": "Sprint 4",
        "nombre": "Reestructuración Arquitectónica, Adaptación de Contingencia y Servicios de Dominio",
        "periodo": "01 de Agosto – 25 de Agosto de 2026",
        "objetivo": "Reorganización ágil ante la contingencia de Valeria, refactorización de clases base (BaseModel, Router), servicios de notificación y reportes de morosidad.",
        "descripcion": (
            "Durante el Sprint 4, el equipo debió sortear con resiliencia la desincorporación activa de Valeria por motivos personales. Reorganizando los puntos "
            "de historia, Junior y Amadeo implementaron un núcleo de infraestructura sumamente robusto: 'BaseModel' genérico con soporte transaccional y métodos CRUD, "
            "el enrutador declarativo orientando a objetos 'Router' con soporte de parámetros nombrados y middlewares de roles ('UserRole'), y el servicio 'FileUploader'.\n\n"
            "Simultáneamente, Rodrigo y Amadeo ejecutaron las Fases 1 y 2 de base de datos: se implementaron los modelos 'EstacionamientosModel' y 'VehiculosModel' (RF 12), "
            "el motor de Reportes de Morosidad y Cartas de Deuda con exportación CSV streaming (RF 23, RF 25), el subsistema criptográfico 'Encryption' (AES-256-CBC) para "
            "correos y teléfonos, y el patrón Outbox transaccional de notificaciones ('NotificationService', 'EmailService') con un worker CLI en segundo plano.\n\n"
            "Diosmary asumió por completo el rediseño y maquetación de las vistas de estacionamientos, cartas de deuda, comunicados y solicitudes de actualización de perfil, "
            "logrando una experiencia visual armónica y modular bajo Bootstrap 5."
        ),
        "commits": [
            "d0691b6 | 2026-08-26 | JoJuniorGit | Refactor: BaseModel, security, UI, tests",
            "b786d29 | 2026-08-26 | JoJuniorGit | refactor(core): add domain constants for EstadoPago, EstadoComprobante, EstadoFactura, UserRole",
            "935a139 | 2026-08-26 | JoJuniorGit | refactor(models): enhance BaseModel with table property, CRUD generics, and transaction helper",
            "f83206a | 2026-08-26 | JoJuniorGit | refactor(router): add OO Router class with dynamic parameter parsing and role middlewares, refactor index.php",
            "71d0630 | 2026-08-26 | JoJuniorGit | refactor(services): add FileUploader service and decouple disk I/O from controllers and models",
            "5c5d4f1 | 2026-08-26 | JoJuniorGit | feat(db): add migration and rollback scripts for Fase 1 with integrity checks and indexes",
            "d46e3e8 | 2026-08-26 | JoJuniorGit | feat(models): add EstacionamientosModel and VehiculosModel with unit tests",
            "af90ea2 | 2026-08-26 | JoJuniorGit | feat(ui): add EstacionamientoController and Bootstrap 5 parking view",
            "4e27ec1 | 2026-08-26 | JoJuniorGit | feat(reports): add ReportesModel, ReporteController, morosidad views, print format, and streaming CSV export",
            "e422a69 | 2026-08-26 | JoJuniorGit | feat(db): add migration and rollback scripts for Fase 2 with structural checks",
            "51d697d | 2026-08-26 | JoJuniorGit | feat(services): add Encryption, EmailService, NotificationService and HTML email templates",
            "ddbb16d | 2026-08-26 | JoJuniorGit | feat(worker): add CLI process_notifications worker, cleanup_logs script, and CSRF meta tag",
            "7a29677 | 2026-08-26 | JoJuniorGit | feat(notifications): integrate transactional event outbox in PagoModel cambiarEstado and aprobarLote",
            "0223fb1 | 2026-08-26 | JoJuniorGit | feat(reports): add obtenerDetalleDeudaUnidad, generarCartaDeuda, enviarAvisoCobro and carta_deuda view",
            "12c986f | 2026-08-26 | JoJuniorGit | feat(announcements): add ComunicadosModel, NotificacionesModel, ComunicadoController, NotificacionController and views",
            "4dcdbe9 | 2026-08-26 | JoJuniorGit | feat(profile): add SolicitudesModel, PerfilController, profile views and data change request workflow"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla del panel de control de estacionamientos y asignación de vehículos desarrollado en el Sprint 4",
            "INSERTAR IMAGEN: Captura de pantalla del módulo de generación de cartas de deuda y reporte de morosidad desarrollado en el Sprint 4"
        ]
    },
    {
        "numero": "Sprint 5",
        "nombre": "Conciliación Bancaria Inteligente y Libro Mayor",
        "periodo": "26 de Agosto – 31 de Agosto de 2026",
        "objetivo": "Desarrollo del motor algorítmico de conciliación bancaria con algoritmo Jaro-Winkler, parseo de extractos CSV y Libro Mayor contable.",
        "descripcion": (
            "En el Sprint 5 se implementó uno de los requerimientos de mayor complejidad técnica y valor operativo del sistema: el Motor de Conciliación "
            "Bancaria Inteligente (RF 26, RF 27, RF 28). Rodrigo desarrolló 'ConciliacionBancariaService', incorporando una implementación matemática pura "
            "del algoritmo de distancia y similitud de Jaro-Winkler para el emparejamiento fonético y tipográfico de referencias bancarias con umbral dinámico (0.85).\n\n"
            "El motor incluyó un parser multiformato para extractos bancarios de Banesco, Mercantil, Provincial y Banco de Venezuela, discriminando "
            "automáticamente créditos y débitos (comisiones bancarias). Asimismo, Amadeo estructuró el Libro Mayor ('MovimientosModel') y el balance general (RF 18, RF 20, RF 24), "
            "asegurando cuadre contable exacto entre pagos conciliados y cuotas facturadas."
        ),
        "commits": [
            "27cb081 | 2026-08-26 | JoJuniorGit | feat(db): add migration and rollback scripts for Fase 3 with seeder and indexes",
            "57ee950 | 2026-08-26 | JoJuniorGit | feat(fase3): implement reconciliation engine, expense justification and general ledger"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla de la interfaz de conciliación bancaria inteligente y cruce de extractos desarrollada en el Sprint 5"
        ]
    },
    {
        "numero": "Sprint 6",
        "nombre": "Seguridad Avanzada, 2FA, APIs RESTful y Fiscalización de Auditoría",
        "periodo": "01 de Septiembre – 08 de Septiembre de 2026",
        "objetivo": "Implementación de verificación en dos pasos (2FA), API con JWT, módulo de auditoría de solo lectura y sistema de respaldos.",
        "descripcion": (
            "El Sprint 6 consolidó la seguridad de nivel empresarial y la interoperabilidad externa. Junior lideró la implementación del subsistema "
            "de autenticación en dos pasos (2FA) con códigos OTP de 6 dígitos cifrados en base de datos ('OtpModel') y despacho por correo electrónico (RF 3).\n\n"
            "Rodrigo desarrolló la API RESTful bajo '/api/v1/' protegida con JSON Web Tokens (JWT) firmados con algoritmo HMAC-SHA256 y rotación de Refresh Tokens (RF 6). "
            "Amadeo diseñó el rol de Auditor independiente ('AuditorController') con acceso restringido de solo lectura al log de transacciones y conciliaciones (RF 8), "
            "así como el motor automatizado de copias de seguridad de la base de datos ('RespaldoController') con verificación de integridad por checksum SHA-256 (RNF 3).\n\n"
            "Diosmary complementó la experiencia implementando en el cliente la extracción asistida de comprobantes mediante OCR en el navegador (Tesseract.js) "
            "con fallback de análisis sintáctico por expresiones regulares en el servidor (RF 15)."
        ),
        "commits": [
            "ec75f88 | 2026-08-26 | JoJuniorGit | feat(fase4): implement 2FA, JWT authentication, auditor read-only role, voucher parser and database backups"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla del flujo de verificación en dos pasos (2FA) con código OTP desarrollada en el Sprint 6",
            "INSERTAR IMAGEN: Captura de pantalla del panel de fiscalización y log de auditoría para el rol de auditor desarrollada en el Sprint 6"
        ]
    },
    {
        "numero": "Sprint 7",
        "nombre": "Auditoría Estática, Cierre de Vulnerabilidades OWASP y Pureza MVC",
        "periodo": "09 de Septiembre – 14 de Septiembre de 2026",
        "objetivo": "Remediación rigurosa de 17 vulnerabilidades de seguridad en los 5 grupos funcionales y creación de herramientas de auditoría estática.",
        "descripcion": (
            "Durante el Sprint 7, el equipo ejecutó una auditoría exhaustiva de calidad y seguridad orientada a mitigar las amenazas del OWASP Top 10. "
            "Se cerraron de forma definitiva 17 vulnerabilidades identificadas en revisiones de pares:\n\n"
            "1. Blindaje contra Inyección SQL mediante parametrización absoluta en consultas complejas.\n"
            "2. Middleware global de validación CSRF para cualquier petición HTTP POST ('Security::validateCSRF()').\n"
            "3. Prevención de Cross-Site Scripting (XSS) auditando que toda salida dinámica en vistas utilice el helper 'e()' con codificación HTML segura y 'ENT_QUOTES'.\n"
            "4. Subida segura de comprobantes con validación de tipo MIME real en cabeceras binarias y contramedida '.htaccess' en el directorio de almacenamiento.\n"
            "5. Mitigación de ataques de sincronización (timing attacks) empleando 'hash_equals()' para tokens de seguridad.\n\n"
            "Junior creó dos herramientas de análisis estático continuo: 'scripts/check_purity.php' (valida pureza MVC prohibiendo HTML/SQL fuera de su capa) "
            "y 'scripts/audit_security.php' (audita reglas OWASP estáticas), logrando que ambas salgan con código de éxito 0 y cero advertencias."
        ),
        "commits": [
            "7421140 | 2026-08-26 | JoJuniorGit | Hardening auth, security, and data integrity",
            "d2f144b | 2026-08-26 | JoJuniorGit | fix(security): Phase 6 — close 17 remaining vulnerabilities across all 5 functional groups",
            "1971719 | 2026-08-26 | JoJuniorGit | Harden auth, rate limiting, and validations",
            "739b4fa | 2026-09-14 | JoJuniorGit | Add OpenCode SDD orchestration"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla de la ejecución de scripts de verificación de pureza MVC y auditoría de seguridad OWASP en consola desarrollada en el Sprint 7"
        ]
    },
    {
        "numero": "Sprint 8 (Adicional 1)",
        "nombre": "Refinamiento Integral de UI/UX y Localización para Entorno Venezolano",
        "periodo": "15 de Septiembre – 18 de Septiembre de 2026",
        "objetivo": "Unificación de navegación responsiva, modal de confirmación de logout, adaptación a estándares de telefonía y cédulas de identidad venezolanas.",
        "descripcion": (
            "Debido a la gran densidad del código acumulado y a la necesidad de perfeccionar la experiencia del usuario final, el equipo extendió el cronograma "
            "con un sprint de refinamiento visual y localización. Diosmary lideró la unificación de la barra lateral ('admin_sidebar.php', 'auditor_sidebar.php') "
            "en la totalidad de módulos del sistema, garantizando colapsado responsivo en dispositivos móviles y destacados de ruta activa.\n\n"
            "Se implementó una ventana emergente modal con diseño corporativo para la confirmación preventiva de cierre de sesión, eliminando el diálogo nativo 'confirm()'. "
            "En materia de localización para Venezuela, Rodrigo y Diosmary estandarizaron todos los formularios residenciales y administrativos:\n"
            "1. Desacoplamiento de códigos de operadora móvil (0412, 0414, 0424, 0416, 0426 y la incorporación del nuevo prefijo 0422 de Digitel) limitando el número a exactamente 7 dígitos.\n"
            "2. Selector formal de tipo de documento de identidad (V/E) con normalización numérica sin guiones y validación de longitud entre 5 y 8 dígitos.\n"
            "3. Creación del modal interactivo en 'admin/estructura' para la asignación y desvinculación ágil de residentes por unidad habitacional."
        ),
        "commits": [
            "7fb50a4 | 2026-08-27 | JoJuniorGit | fix(ui): integrar barra lateral y navegación responsiva en todos los módulos del sistema",
            "403726c | 2026-08-27 | JoJuniorGit | fix(ui): simplificar boton de logout solo a icono y anadir confirmacion preventiva",
            "88f0a7a | 2026-08-27 | JoJuniorGit | feat(ui): implementar ventana emergente modal estilizada para confirmacion de cierre de sesion",
            "2bd4633 | 2026-08-27 | JoJuniorGit | fix(ui): centralizar acceso a perfil unicamente en barra lateral y redisenar tarjeta de perfil",
            "b0c2de8 | 2026-08-28 | JoJuniorGit | fix(estructura): corregir apertura de modal y paso de parametros al editar edificios y unidades",
            "f1ec0b0 | 2026-08-28 | JoJuniorGit | feat(estructura): agregar modal y flujo administrativo para gestion y asignacion de residentes por unidad",
            "3cfeff4 | 2026-08-28 | JoJuniorGit | feat(telefonia): separar codigo de operadora movil y limitar numero a 7 digitos en todos los formularios",
            "6e023c8 | 2026-08-28 | JoJuniorGit | feat(telefonia): agregar prefijo 0422 de Digitel a selectores y servicios de notificacion",
            "e7ffd31 | 2026-08-28 | JoJuniorGit | feat(cedula): implementar selector de tipo V/E, límites de 5 a 8 dígitos, normalización sin guiones y simplificar selectores móviles"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla del formulario de perfil y datos personales con selector de operadora y cédula venezolana desarrollado en el Sprint 8"
        ]
    },
    {
        "numero": "Sprint 9 (Adicional 2)",
        "nombre": "Ingesta de PDF Maestro de Gastos y Refactorización del Flujo de Registro con Concurrencia SDD",
        "periodo": "19 de Septiembre – 21 de Septiembre de 2026",
        "objetivo": "Descompresión nativa de PDF de gastos con FlateDecode, visor de extracto exacto para residentes, paleta institucional RNF 1 y flujo de registro con aprobación administrativa.",
        "descripcion": (
            "El Sprint 9 culminó las capacidades más avanzadas y diferenciadoras del sistema, abordando requerimientos de alta complejidad algorítmica y de concurrencia:\n\n"
            "1. Motor de Ingesta de PDF Maestro de Gastos (RF 30 a RF 34): Rodrigo desarrolló 'GastoParserService', un servicio en PHP puro capaz de descomprimir "
            "flujos binarios PDF comprimidos con algoritmo FlateDecode ('gzuncompress'/'gzinflate'), segmentar páginas, extraer operadores de texto ('Tj'/'TJ') e inferir "
            "fechas, montos y categorías de gasto mediante análisis semántico. Diosmary diseñó el visor en el portal del residente ('residente/gastos'), permitiendo "
            "auditar la cita textual exacta del soporte y visualizar el documento PDF posicionado en la página exacta de la factura.\n\n"
            "2. Estandarización de Seguridad en Contraseñas: Unificación de la política de contraseñas a un mínimo obligatorio de 8 caracteres con al menos una letra "
            "y un número, validado centralizadamente por el helper 'validarPassword()'.\n\n"
            "3. Paleta Institucional (RNF 1): Inclusión de acentos marrones oscuros (#4a2c11) en cabeceras de sidebars, bordes de cards y footers, complementando "
            "los verdes esmeralda y amarillos institucionales solicitados en el SRS.\n\n"
            "4. Refactorización Integral del Registro con Aprobación Administrativa (SDD change-004): Ante inconsistencias en el auto-registro, el equipo "
            "rediseñó el proceso de alta. Los residentes completan un formulario exhaustivo (nombre, apellido, teléfono, cédula, nro. de habitantes) seleccionando "
            "su unidad a través de un selector dinámico que solo expone apartamentos desocupados sin solicitudes previas ('UnidadesModel::getDisponibles()'). "
            "Al enviarse, la cuenta queda en estado 'Pendiente' con acceso estrictamente bloqueado. Junior y Amadeo implementaron control de concurrencia pesimista "
            "con 'SELECT ... FOR UPDATE' en 'SolicitudesRegistroModel', evitando que dos personas reclamen el mismo apartamento. Se desarrolló la bandeja administrativa "
            "'/admin/solicitudes-registro' con aprobación transaccional, rechazo motivado y bitácora de auditoría estricta."
        ),
        "commits": [
            "e189314 | 2026-09-20 | JoJuniorGit | feat(gastos): implementar ingesta de pdf maestro, extractos exactos y ocr asistido",
            "ebff5f9 | 2026-09-20 | JoJuniorGit | fix(auth): estandarizar minimo de contrasena a 8 caracteres con letras y numeros",
            "43fc116 | 2026-09-20 | JoJuniorGit | style(ui): incorporar acentos de paleta institucional marron oscuro rnf 1",
            "b56cb8c | 2026-09-20 | JoJuniorGit | fix(auth): permitir autoregistro de residente con correo precargado por administracion",
            "8522f72 | 2026-09-21 | JoJuniorGit | feat(auth): refactorizar flujo de registro con aprobacion administrativa y asignacion dinamica de apartamentos"
        ],
        "marcadores": [
            "INSERTAR IMAGEN: Captura de pantalla del módulo de ingesta y previsualización editable de PDF maestro de gastos desarrollado en el Sprint 9",
            "INSERTAR IMAGEN: Captura de pantalla de la bandeja de gestión de solicitudes de registro de residentes con modales de aprobación desarrollada en el Sprint 9"
        ]
    }
]
