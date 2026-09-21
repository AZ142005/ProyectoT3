# -*- coding: utf-8 -*-
"""
report_data.py
Módulo de contenido académico y técnico para la generación de la Bitácora de Proyecto.
"""

TITULO_DOCUMENTO = "INFORME ACADÉMICO DE AVANCES DE PROYECTO DE DESARROLLO DE SOFTWARE"
SUBTITULO_DOCUMENTO = "Sistema Web Integral de Cobranzas, Conciliación Bancaria y Rendición de Cuentas para Condominios (ProyectoT3)"
INSTITUCION = "UNIVERSIDAD NACIONAL EXPERIMENTAL POLITÉCNICA\nDEPARTAMENTO DE INGENIERÍA DE SOFTWARE Y SISTEMAS"
CATEDRA = "INGENIERÍA DE SOFTWARE II / METODOLOGÍAS ÁGILES DE DESARROLLO"
FECHA = "Septiembre de 2026"

INTEGRANTES = [
    {
        "nombre": "Junior",
        "rol_scrum": "Scrum Master & Lead Developer",
        "rol_tecnico": "Arquitecto de Software Web",
        "responsabilidades": "Orquestación del Front Controller, routing declarativo, seguridad criptográfica (AES-256, JWT, 2FA), control de transacciones pesimistas y documentación técnica SDD."
    },
    {
        "nombre": "Amadeo",
        "rol_scrum": "Development Team Member",
        "rol_tecnico": "Desarrollador Backend, Persistencia & Seguridad",
        "responsabilidades": "Diseño de modelos relacionales MySQL con PDO preparado, scripts de migración y rollback idempotentes, mitigación OWASP Top 10 y auditoría de datos."
    },
    {
        "nombre": "Rodrigo",
        "rol_scrum": "Development Team Member",
        "rol_tecnico": "Desarrollador Full-Stack & Servicios de Dominio",
        "responsabilidades": "Implementación del motor de conciliación bancaria con algoritmo Jaro-Winkler, ingesta nativa y parseo de PDF maestro, servicios de notificación y generación de reportes."
    },
    {
        "nombre": "Diosmary",
        "rol_scrum": "Development Team Member",
        "rol_tecnico": "Frontend Lead & Arquitecta UI/UX Web",
        "responsabilidades": "Diseño y maquetación de interfaces responsivas con Bootstrap 5 y CSS3 nativo, accesibilidad, integración de OCR cliente, modales dinámicos y paleta institucional (RNF 1)."
    },
    {
        "nombre": "Valeria",
        "rol_scrum": "Product Development Analyst",
        "rol_tecnico": "Analista de Requerimientos & Prototipado Inicial",
        "responsabilidades": "Levantamiento de requerimientos iniciales (SRS), especificación de historias de usuario, diagramación de flujos y wireframes interactivos en los Sprints 1 y 2."
    }
]

RESUMEN_EJECUTIVO = (
    "El presente informe técnico-académico documenta de manera exhaustiva el ciclo de vida, "
    "evolución arquitectónica y estado de entrega del sistema web 'ProyectoT3', concebido "
    "para solventar de forma rigurosa la gestión administrativa, cobranza inteligente, "
    "conciliación bancaria y rendición transparente de cuentas en complejos residenciales y condominios.\n\n"
    "El desarrollo se ha regido bajo el marco de trabajo ágil Scrum, potenciado por el paradigma de "
    "Desarrollo Guiado por Especificaciones (Spec-Driven Development - SDD). La aplicación se "
    "encuentra construida bajo una estricta arquitectura Modelo-Vista-Controlador (MVC) pura en "
    "PHP Vanilla 8.x, prescindiendo deliberadamente de frameworks de terceros (como Laravel o Symfony) "
    "con el fin de asegurar independencia tecnológica, máxima velocidad de procesamiento, huella mínima de memoria "
    "y un entendimiento didáctico profundo de los patrones de diseño subyacentes. La persistencia descansa "
    "sobre MySQL utilizando exclusivamente sentencias preparadas mediante la extensión PDO, garantizando "
    "inmunidad ante vulnerabilidades de inyección SQL. La interfaz de usuario prioriza Bootstrap 5, "
    "HTML5 semántico y CSS3 nativo, apoyándose en utilidades puntuales de Tailwind CSS.\n\n"
    "Al cierre del presente ciclo de evaluación, el sistema ha alcanzado el 100% de cumplimiento "
    "de los 37 Requerimientos Funcionales (RF 1 a RF 37) y los 5 Requerimientos No Funcionales (RNF 1 a RNF 5) "
    "estipulados en el pliego de requerimientos (SRS). La base de código cuenta con 295 casos de "
    "prueba automatizados en una suite de pruebas propia, totalizando 740 aserciones verificadas con cero fallos, "
    "un cumplimiento estricto de pureza arquitectónica y una auditoría estática de seguridad OWASP Top 10 "
    "completamente limpia."
)

METODOLOGIA_TEXT = (
    "Para garantizar una cadencia de entrega predecible, mitigación continua de riesgos y alineación con los "
    "estándares de la industria, el proyecto adoptó el marco de trabajo ágil Scrum, complementado por la metodología "
    "Spec-Driven Development (SDD). La combinación de ambos enfoques permitió sincronizar la velocidad de iteración "
    "con una disciplina formal en la especificación previa de cambios arquitectónicos.\n\n"
    "Bajo Scrum, el ciclo de vida se organizó en iteraciones cortas (Sprints) con duraciones de entre 1 y 2 semanas. "
    "Se implementaron de forma disciplinada las cinco ceremonias estándar: (1) Sprint Planning, para la estimación de "
    "historias mediante Planning Poker y definición del Sprint Goal; (2) Daily Scrum, sincronizaciones diarias breves "
    "para identificar cuellos de botella; (3) Backlog Refinement, revisión continua de criterios de aceptación; "
    "(4) Sprint Review, demostración funcional del incremento de software potencialmente desplegable; y (5) Sprint "
    "Retrospective, análisis introspectivo de procesos técnicos y de equipo.\n\n"
    "Por su parte, la adopción de SDD (evidenciada en el directorio '.sdd/') introdujo un rigor preventivo "
    "fundamental: antes de proceder a la mutación de código en componentes críticos (tales como el motor de OCR, "
    "la ingesta del PDF maestro o la refactorización de concurrencia en registros), el equipo elaboró propuestas formales "
    "('proposals/'), especificaciones de requerimientos ('specs/'), documentos de diseño arquitectónico ('designs/') "
    "y desgloses de tareas atómicas ('tasks/'). Este enfoque redujo drásticamente el retrabajo técnico y aseguró "
    "que el 100% del código producido cumpliera con las restricciones no negociables de seguridad y pureza MVC."
)

CONTINGENCIA_TEXT = (
    "En todo proyecto de ingeniería de software real, la gestión del talento humano y la capacidad de adaptación "
    "organizacional ante eventos imprevistos constituyen factores determinantes para el éxito del proyecto. A inicios "
    "del mes de agosto de 2026, coincidiendo con la transición hacia el Sprint 4 (enfocado en el desarrollo core de lógica "
    "de negocio avanzada), la integrante Valeria experimentó circunstancias de fuerza mayor de índole estrictamente "
    "personal que le impidieron continuar participando de forma activa y operativa en las tareas técnicas de programación.\n\n"
    "Ante esta contingencia, la dirección del proyecto (liderada por Junior como Scrum Master) activó un protocolo de "
    "resiliencia y reasignación ágil de recursos fundamentado en las siguientes acciones estratégicas:\n\n"
    "1. Preservación Institucional y Académica: Valeria se mantuvo como miembro formal del equipo y del proyecto, "
    "reconociendo y capitalizando sus aportes fundamentales en la fase de análisis de requerimientos (SRS), especificación "
    "de historias de usuario y diseño de wireframes interactivos durante los Sprints 1 y 2.\n\n"
    "2. Rebalanceo del Sprint Backlog y Absorción de Puntos de Historia: La capacidad técnica estimada del equipo "
    "(Team Velocity) sufrió una disminución teórica inicial del 20%. Durante la sesión de Sprint Planning correspondiente, "
    "los puntos de historia asignados originalmente a Valeria fueron redistribuidos entre Amadeo (quien absorbió los "
    "modelos de datos de estacionamientos, vehículos y esquemas relacionales), Rodrigo (quien asumió el desarrollo de los "
    "servicios de notificación, reportes de morosidad y cartas de deuda) y Junior (quien asumió la consolidación del "
    "enrutador y la refactorización de clases base).\n\n"
    "3. Especialización y Liderazgo de Frontend: Con el objetivo de liberar a los desarrolladores backend de tareas de "
    "estilizado e integración visual, Diosmary consolidó su rol como Frontend Lead, asumiendo de forma autónoma e integral "
    "la arquitectura de componentes Bootstrap 5, el sistema de diseño responsivo, los modales interactivos y la validación "
    "del DOM en el cliente.\n\n"
    "4. Introducción de Abstracciones de Productividad: Para contrarrestar la carga adicional de trabajo, el equipo diseñó "
    "la clase abstracta 'BaseModel' y librerías de helpers compartidas ('helpers.php'), eliminando código repetitivo y "
    "permitiendo que la velocidad efectiva del equipo se mantuviera constante entre 45 y 50 puntos de historia por sprint.\n\n"
    "El resultado de esta reorganización fue sobresaliente: el equipo no solo evitó desviaciones en el cronograma maestro, "
    "sino que la estabilidad del código se incrementó gracias a una mayor especialización de responsabilidades."
)
