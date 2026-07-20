<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Sistema de Cobranzas - Condominio') ?></title>
    <!-- Google Fonts & Material Symbols -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    
    <!-- Tailwind CSS CDN para MVP (Estructura de compile preparada) -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#27ae60", // Verde del sistema original
                        "primary-hover": "#1e8449",
                        "primary-container": "#facc15", // Amarillo de resaltado
                        "on-primary-container": "#6c5700",
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
