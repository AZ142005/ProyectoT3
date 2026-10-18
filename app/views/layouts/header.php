<?php
// Resolución de metadatos SEO de la página. Los controladores pueden
// sobreescribirlos pasando title/description/robots/canonical/ogImage/jsonLd
// a Controller::render(); aquí solo se aplican los valores por defecto.
$metaTitle = $title ?? 'Conjunto Residencial Las Mesetas de Morón';
$metaDescription = $description ?? '';
$metaRobots = $robots ?? (\App\Core\Auth::check() ? 'noindex,nofollow' : 'index,follow');
$metaCanonical = $canonical ?? '';
$metaOgImage = $ogImage ?? '';
$metaOgType = $ogType ?? 'website';
$metaOgImageWidth = $ogImageWidth ?? null;
$metaOgImageHeight = $ogImageHeight ?? null;
$metaJsonLd = $jsonLd ?? null;
// La imagen OG puede venir absoluta (URL completa) o relativa al sitio.
$metaOgImageUrl = $metaOgImage !== ''
    ? (preg_match('#^https?://#i', $metaOgImage) ? $metaOgImage : app_url($metaOgImage))
    : '';
$metaCanonicalUrl = $metaCanonical !== '' ? app_url($metaCanonical) : '';
?>
<!DOCTYPE html>
<html lang="es-VE">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($metaTitle) ?></title>
    <?php if ($metaDescription !== ''): ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= e($metaRobots) ?>">
    <?php if ($metaCanonicalUrl !== ''): ?>
    <link rel="canonical" href="<?= e($metaCanonicalUrl) ?>">
    <?php endif; ?>
    <meta name="theme-color" content="#27ae60">
    <!-- Open Graph -->
    <meta property="og:site_name" content="Conjunto Residencial Las Mesetas de Morón">
    <meta property="og:locale" content="es_VE">
    <meta property="og:type" content="<?= e($metaOgType) ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <?php if ($metaDescription !== ''): ?>
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if ($metaCanonicalUrl !== ''): ?>
    <meta property="og:url" content="<?= e($metaCanonicalUrl) ?>">
    <?php endif; ?>
    <?php if ($metaOgImageUrl !== ''): ?>
    <meta property="og:image" content="<?= e($metaOgImageUrl) ?>">
    <meta property="og:image:alt" content="Logo del Conjunto Residencial Las Mesetas de Morón">
    <?php if ($metaOgImageWidth !== null): ?>
    <meta property="og:image:width" content="<?= e((string)$metaOgImageWidth) ?>">
    <?php endif; ?>
    <?php if ($metaOgImageHeight !== null): ?>
    <meta property="og:image:height" content="<?= e((string)$metaOgImageHeight) ?>">
    <?php endif; ?>
    <?php endif; ?>
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= e($metaTitle) ?>">
    <?php if ($metaDescription !== ''): ?>
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if ($metaOgImageUrl !== ''): ?>
    <meta name="twitter:image" content="<?= e($metaOgImageUrl) ?>">
    <?php endif; ?>
    <?php if ($metaJsonLd !== null): ?>
    <script type="application/ld+json"><?= json_encode($metaJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
    <!-- Favicon / Icono de la ventana del navegador -->
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png"/>
    <link rel="apple-touch-icon" href="/apple-touch-icon.png"/>
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
            /* Bootstrap 5.3: sus utilidades (.bg-primary, .text-primary, .border-primary) usan
               --bs-primary-rgb con !important y caen al azul por defecto (13,110,253) si no se
               define aquí. RGB de #27ae60 para mantener la paleta institucional. */
            --bs-primary-rgb: 39, 174, 96;
        }

        /* Ocultar icono nativo de revelar contraseña del navegador (Edge / IE) para evitar duplicación con el botón personalizado */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
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

        /* Corrección de colisión Tailwind CSS vs Bootstrap 5: evitar que .collapse oculte con visibility: collapse */
        .collapse.show,
        .collapsing {
            visibility: visible !important;
        }
        .collapse.show *,
        .collapsing * {
            visibility: visible !important;
        }
    </style>
</head>
<body class="bg-background text-on-surface font-sans min-h-screen flex flex-col">
