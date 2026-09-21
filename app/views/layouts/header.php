<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title ?? 'Sistema de Cobranzas - Condominio') ?></title>
    <!-- Google Fonts & Material Symbols -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>

    <!-- Tailwind CSS CDN para MVP (Estructura de compile preparada) -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#27ae60", // Verde institucional principal
                        "primary-hover": "#1e8449",
                        "primary-container": "#facc15", // Amarillo institucional de resaltado
                        "on-primary-container": "#6c5700",
                        "institutional-green": "#27ae60",
                        "institutional-yellow": "#facc15",
                        "institutional-brown": "#4a2c11", // Marrón oscuro institucional (RNF 1)
                        "institutional-brown-dark": "#2d1a0c",
                        "institutional-brown-light": "#6d4c41",
                        "background": "#f0f7f0", // Color verde claro de fondo heredado
                        "on-surface": "#2c3e50",
                        "on-surface-variant": "#5a7a6a",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-high": "#d5f5e3",
                        "outline-variant": "#d5f5e3",
                        "tertiary": "#0053db"
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --color-institutional-green: #27ae60;
            --color-institutional-green-hover: #1e8449;
            --color-institutional-yellow: #facc15;
            --color-institutional-brown: #4a2c11;
            --color-institutional-brown-dark: #2d1a0c;
            --color-institutional-brown-light: #6d4c41;
            --bs-primary: #27ae60;
        }

        /* RNF 1: Paleta institucional (Verde, Amarillo y en menor medida Marrón) */
        .border-institutional-brown {
            border-color: #4a2c11 !important;
        }
        .bg-institutional-brown {
            background-color: #4a2c11 !important;
            color: #ffffff !important;
        }
        .text-institutional-brown {
            color: #4a2c11 !important;
        }
        .badge-institutional-brown {
            background-color: #f7f3ee;
            color: #4a2c11;
            border: 1px solid #d7ccc8;
        }
        .header-institutional-accent {
            border-bottom: 2px solid #4a2c11;
        }
        .card-institutional-accent {
            border-top: 3px solid #27ae60;
            border-bottom: 2px solid #4a2c11;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #27ae60;
            border-radius: 20px;
        }
    </style>
</head>
<body class="bg-background text-on-surface font-sans min-h-screen flex flex-col">
