<!DOCTYPE html>

<html lang="es">Siu<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Registrar Pago - PropManage Pro</title>
<!-- Google Fonts: Inter & Material Symbols -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<!-- Theme Configuration -->
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "on-surface": "#121c2a",
                        "secondary-container": "#7cf994",
                        "surface-variant": "#d9e3f6",
                        "on-secondary-fixed-variant": "#005320",
                        "outline-variant": "#d1c6ab",
                        "surface-tint": "#735c00",
                        "on-primary-fixed": "#231b00",
                        "surface-container-low": "#eff4ff",
                        "on-tertiary-fixed-variant": "#003ea8",
                        "on-secondary": "#ffffff",
                        "tertiary-container": "#c2cfff",
                        "on-background": "#121c2a",
                        "on-primary-container": "#6c5700",
                        "on-tertiary-fixed": "#00174b",
                        "secondary": "#006e2d",
                        "surface": "#f8f9ff",
                        "inverse-surface": "#27313f",
                        "surface-container-highest": "#d9e3f6",
                        "on-error": "#ffffff",
                        "tertiary": "#0053db",
                        "background": "#f8f9ff",
                        "secondary-fixed-dim": "#62df7d",
                        "error": "#ba1a1a",
                        "surface-container": "#e6eeff",
                        "surface-container-high": "#dee9fc",
                        "on-tertiary-container": "#004ecf",
                        "on-secondary-container": "#007230",
                        "error-container": "#ffdad6",
                        "primary": "#735c00",
                        "on-primary": "#ffffff",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-fixed-variant": "#574500",
                        "primary-container": "#facc15",
                        "tertiary-fixed": "#dbe1ff",
                        "surface-dim": "#d0dbed",
                        "inverse-on-surface": "#eaf1ff",
                        "inverse-primary": "#eec200",
                        "surface-bright": "#f8f9ff",
                        "on-error-container": "#93000a",
                        "primary-fixed": "#ffe083",
                        "on-surface-variant": "#4d4632",
                        "outline": "#7f7660",
                        "on-tertiary": "#ffffff",
                        "primary-fixed-dim": "#eec200",
                        "secondary-fixed": "#7ffc97",
                        "on-secondary-fixed": "#002109",
                        "tertiary-fixed-dim": "#b4c5ff"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "xl": "32px",
                        "sm": "8px",
                        "lg": "24px",
                        "md": "16px",
                        "xs": "4px",
                        "margin-mobile": "16px",
                        "gutter": "20px",
                        "base": "4px",
                        "margin-desktop": "40px"
                    },
                    "fontFamily": {
                        "body-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "body-sm": ["Inter"],
                        "label-sm": ["Inter"],
                        "display-lg": ["Inter"],
                        "body-lg": ["Inter"],
                        "label-md": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "headline-md": ["Inter"]
                    },
                    "fontSize": {
                        "body-md": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
                        "headline-lg": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "600" }],
                        "body-sm": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
                        "label-sm": ["12px", { "lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "500" }],
                        "display-lg": ["48px", { "lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                        "body-lg": ["18px", { "lineHeight": "28px", "fontWeight": "400" }],
                        "label-md": ["14px", { "lineHeight": "20px", "fontWeight": "600" }],
                        "headline-lg-mobile": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
                        "headline-md": ["24px", { "lineHeight": "32px", "fontWeight": "600" }]
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
            background-color: #d1c6ab;
            border-radius: 20px;
        }
        /* Radio button styled as card hide default input */
        .payment-method-radio:checked + label {
            border-color: #facc15;
            background-color: #eff4ff;
            box-shadow: 0 0 0 1px #facc15;
        }
        .payment-method-radio:checked + label .check-icon {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md min-h-screen">
<!-- SideNavBar Shared Component -->
<nav class="bg-surface-container-lowest dark:bg-inverse-surface h-screen w-64 flex flex-col border-r border-outline-variant dark:border-outline flat no shadows fixed left-0 top-0 overflow-y-auto z-20 hidden md:flex">
<!-- Brand Header -->
<div class="px-lg py-xl border-b border-outline-variant dark:border-outline mb-md flex items-center gap-md">
<div class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-on-primary-container">domain</span>
</div>
<div>
<h1 class="font-headline-md text-headline-md font-bold text-on-surface dark:text-inverse-on-surface leading-tight tracking-tight">PropManage Pro</h1>
<p class="font-label-sm text-label-sm text-on-surface-variant">Enterprise Edition</p>
</div>
</div>
<!-- Navigation Links -->
<div class="flex-1 px-md flex flex-col gap-sm custom-scrollbar">
<!-- Dashboard (Inactive) -->
<a class="flex items-center gap-md text-on-surface-variant hover:text-on-surface px-md py-sm hover:bg-surface-container-high transition-colors duration-200 rounded-xl" href="#">
<span class="material-symbols-outlined">dashboard</span>
<span class="font-label-md text-label-md">Dashboard</span>
</a>
<!-- Tenants (Inactive) -->
<a class="flex items-center gap-md text-on-surface-variant hover:text-on-surface px-md py-sm hover:bg-surface-container-high transition-colors duration-200 rounded-xl" href="#">
<span class="material-symbols-outlined">group</span>
<span class="font-label-md text-label-md">Tenants</span>
</a>
<!-- Payments (Active) -->
<a class="flex items-center gap-md bg-primary-container text-on-primary-container font-bold rounded-xl px-md py-sm Active: scale-[0.98] transition-transform" href="#">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
<span class="font-label-md text-label-md">Payments</span>
</a>
<!-- Reports (Inactive) -->
<a class="flex items-center gap-md text-on-surface-variant hover:text-on-surface px-md py-sm hover:bg-surface-container-high transition-colors duration-200 rounded-xl" href="#">
<span class="material-symbols-outlined">analytics</span>
<span class="font-label-md text-label-md">Reports</span>
</a>
</div>
<!-- CTA Button -->
<div class="px-md py-lg border-t border-outline-variant">
<button class="w-full bg-primary-container text-on-primary-container font-label-md text-label-md font-bold py-md rounded-lg flex items-center justify-center gap-sm hover:bg-primary-fixed transition-colors">
<span class="material-symbols-outlined">add_circle</span>
                Nuevo Contrato
            </button>
</div>
<!-- Footer Links -->
<div class="px-md pb-lg pt-sm flex flex-col gap-xs">
<a class="flex items-center gap-md text-on-surface-variant hover:text-on-surface px-md py-sm hover:bg-surface-container-high transition-colors duration-200 rounded-xl" href="#">
<span class="material-symbols-outlined">settings</span>
<span class="font-label-md text-label-md">Settings</span>
</a>
<a class="flex items-center gap-md text-on-surface-variant hover:text-on-surface px-md py-sm hover:bg-surface-container-high transition-colors duration-200 rounded-xl" href="#">
<span class="material-symbols-outlined">help</span>
<span class="font-label-md text-label-md">Support</span>
</a>
</div>
</nav>
<!-- TopNavBar Shared Component -->
<header class="bg-surface dark:bg-surface-dim border-b border-outline-variant dark:border-outline flat no shadows flex justify-between items-center h-16 px-margin-desktop ml-64 w-[calc(100%-16rem)] docked full-width top-0 fixed z-10 hidden md:flex">
<!-- Search Area (on_right logic dictates spacing) -->
<div class="flex-1"></div>
<div class="flex items-center gap-lg">
<!-- Search Input -->
<div class="relative focus-within:ring-2 focus-within:ring-primary-container rounded-lg transition-all">
<span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">search</span>
<input class="bg-surface-container-lowest border border-outline-variant text-primary dark:text-primary-fixed font-label-md text-label-md rounded-lg pl-xl pr-sm py-xs w-64 focus:outline-none focus:border-on-surface" placeholder="Buscar..." type="text"/>
</div>
<!-- Trailing Icons -->
<div class="flex items-center gap-sm">
<button class="text-on-surface-variant hover:text-primary transition-all p-xs rounded-full hover:bg-surface-container-highest flex items-center justify-center">
<span class="material-symbols-outlined">notifications</span>
</button>
<button class="text-on-surface-variant hover:text-primary transition-all p-xs rounded-full hover:bg-surface-container-highest flex items-center justify-center">
<span class="material-symbols-outlined">account_circle</span>
</button>
</div>
</div>
</header>
<!-- Main Content Canvas -->
<main class="md:ml-64 pt-[80px] px-margin-mobile md:px-margin-desktop pb-xl">
<!-- Page Header -->
<div class="mb-lg">
<div class="flex items-center gap-sm text-on-surface-variant font-label-sm text-label-sm mb-xs">
<span class="material-symbols-outlined text-[16px]">payments</span>
<span>/</span>
<span>Registros</span>
</div>
<h2 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface">Registrar Pago</h2>
<p class="font-body-md text-body-md text-on-surface-variant mt-xs">Ingresa los detalles del pago recibido para actualizar el balance del inquilino.</p>
</div>
<!-- Bento Grid Layout -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">
<!-- Left Column: Registration Form (col-span-8) -->
<div class="lg:col-span-8 bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
<form action="#" class="flex flex-col gap-lg" method="POST">
<!-- Section: Tenant Details -->
<div class="flex flex-col gap-md">
<h3 class="font-label-md text-label-md text-on-surface border-b border-outline-variant pb-xs">Detalles del Inquilino</h3>
<div class="flex flex-col gap-xs">
<label class="font-label-sm text-label-sm text-on-surface-variant" for="tenant-select">Seleccionar Inquilino</label>
<div class="relative">
<select class="w-full appearance-none bg-surface-container-lowest border border-outline-variant text-on-surface font-body-md text-body-md rounded-lg pl-md pr-xl py-sm focus:outline-none focus:border-on-surface focus:ring-1 focus:ring-primary-container cursor-pointer transition-colors" id="tenant-select" name="tenant">
<option disabled="" selected="" value="">Seleccione un inquilino...</option>
<option value="1">Carlos Mendoza - Apto 3B</option>
<option value="2">Ana Ruiz - Local Comercial 1</option>
<option value="3">Empresa Tech SRL - Oficina 402</option>
</select>
<span class="material-symbols-outlined absolute right-md top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
</div>
</div>
</div>
<!-- Section: Payment Details -->
<div class="flex flex-col gap-md">
<h3 class="font-label-md text-label-md text-on-surface border-b border-outline-variant pb-xs">Información del Pago</h3>
<div class="grid grid-cols-1 md:grid-cols-2 gap-md">
<!-- Amount -->
<div class="flex flex-col gap-xs">
<label class="font-label-sm text-label-sm text-on-surface-variant" for="amount">Monto Recibido</label>
<div class="relative">
<span class="absolute left-md top-1/2 -translate-y-1/2 text-on-surface-variant font-body-md text-body-md">$</span>
<input class="w-full bg-surface-container-lowest border border-outline-variant text-on-surface font-body-md text-body-md rounded-lg pl-xl pr-md py-sm focus:outline-none focus:border-on-surface focus:ring-1 focus:ring-primary-container transition-colors text-right tabular-nums" id="amount" name="amount" placeholder="0.00" step="0.01" type="number"/>
</div>
</div>
<!-- Date -->
<div class="flex flex-col gap-xs">
<label class="font-label-sm text-label-sm text-on-surface-variant" for="payment-date">Fecha de Pago</label>
<div class="relative">
<input class="w-full bg-surface-container-lowest border border-outline-variant text-on-surface font-body-md text-body-md rounded-lg pl-md pr-xl py-sm focus:outline-none focus:border-on-surface focus:ring-1 focus:ring-primary-container transition-colors" id="payment-date" name="payment_date" type="date"/>
<!-- Native date pickers usually have their own icon, but ensuring style consistency -->
</div>
</div>
</div>
<!-- Month Corresponding -->
<div class="flex flex-col gap-xs">
<label class="font-label-sm text-label-sm text-on-surface-variant" for="month-paid">Mes Correspondiente</label>
<div class="relative">
<select class="w-full appearance-none bg-surface-container-lowest border border-outline-variant text-on-surface font-body-md text-body-md rounded-lg pl-md pr-xl py-sm focus:outline-none focus:border-on-surface focus:ring-1 focus:ring-primary-container cursor-pointer transition-colors" id="month-paid" name="month_paid">
<option value="10">Octubre 2023</option>
<option selected="" value="11">Noviembre 2023</option>
<option value="12">Diciembre 2023</option>
</select>
<span class="material-symbols-outlined absolute right-md top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
</div>
</div>
</div>
<!-- Section: Payment Method -->
<div class="flex flex-col gap-md">
<h3 class="font-label-md text-label-md text-on-surface border-b border-outline-variant pb-xs">Método de Pago</h3>
<div class="grid grid-cols-1 md:grid-cols-3 gap-sm">
<!-- Option 1: Cash -->
<div class="relative">
<input checked="" class="payment-method-radio sr-only" id="method-cash" name="payment_method" type="radio" value="cash"/>
<label class="flex flex-col items-center justify-center gap-xs p-md border border-outline-variant rounded-lg cursor-pointer hover:bg-surface-container-lowest transition-all bg-surface-container-lowest relative overflow-hidden" for="method-cash">
<span class="material-symbols-outlined text-on-surface-variant text-[28px]">payments</span>
<span class="font-label-sm text-label-sm text-on-surface">Efectivo</span>
<span class="check-icon material-symbols-outlined absolute top-xs right-xs text-primary-container opacity-0 transition-opacity" style="font-variation-settings: 'FILL' 1; font-size: 18px;">check_circle</span>
</label>
</div>
<!-- Option 2: Transfer -->
<div class="relative">
<input class="payment-method-radio sr-only" id="method-transfer" name="payment_method" type="radio" value="transfer"/>
<label class="flex flex-col items-center justify-center gap-xs p-md border border-outline-variant rounded-lg cursor-pointer hover:bg-surface-container-lowest transition-all bg-surface-container-lowest relative overflow-hidden" for="method-transfer">
<span class="material-symbols-outlined text-on-surface-variant text-[28px]">account_balance</span>
<span class="font-label-sm text-label-sm text-on-surface">Transferencia</span>
<span class="check-icon material-symbols-outlined absolute top-xs right-xs text-primary-container opacity-0 transition-opacity" style="font-variation-settings: 'FILL' 1; font-size: 18px;">check_circle</span>
</label>
</div>
<!-- Option 3: Card -->
<div class="relative">
<input class="payment-method-radio sr-only" id="method-card" name="payment_method" type="radio" value="card"/>
<label class="flex flex-col items-center justify-center gap-xs p-md border border-outline-variant rounded-lg cursor-pointer hover:bg-surface-container-lowest transition-all bg-surface-container-lowest relative overflow-hidden" for="method-card">
<span class="material-symbols-outlined text-on-surface-variant text-[28px]">credit_card</span>
<span class="font-label-sm text-label-sm text-on-surface">Tarjeta</span>
<span class="check-icon material-symbols-outlined absolute top-xs right-xs text-primary-container opacity-0 transition-opacity" style="font-variation-settings: 'FILL' 1; font-size: 18px;">check_circle</span>
</label>
</div>
</div>
<div class="flex flex-col gap-xs mt-sm">
<label class="font-label-sm text-label-sm text-on-surface-variant" for="reference-notes">Notas / Referencia (Opcional)</label>
<input class="w-full bg-surface-container-lowest border border-outline-variant text-on-surface font-body-md text-body-md rounded-lg pl-md pr-md py-sm focus:outline-none focus:border-on-surface focus:ring-1 focus:ring-primary-container transition-colors" id="reference-notes" name="reference_notes" placeholder="Ej. Comprobante #12345" type="text"/>
</div>
</div>
<!-- Form Actions -->
<div class="flex items-center justify-end gap-md pt-md border-t border-outline-variant">
<button class="px-lg py-sm font-label-md text-label-md text-on-surface border border-outline-variant rounded-lg hover:bg-surface-container-highest transition-colors" type="button">
                            Cancelar
                        </button>
<button class="px-lg py-sm font-label-md text-label-md text-on-primary-container bg-primary-container font-bold rounded-lg hover:bg-primary-fixed transition-colors flex items-center gap-xs" type="submit">
<span class="material-symbols-outlined text-[18px]">save</span>
                            Registrar Pago
                        </button>
</div>
</form>
</div>
<!-- Right Column: Tenant Summary Context (col-span-4) -->
<div class="lg:col-span-4 flex flex-col gap-md">
<!-- Balance Card -->
<div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm">
<h3 class="font-label-md text-label-md text-on-surface-variant flex items-center gap-xs mb-md">
<span class="material-symbols-outlined text-[18px]">account_box</span>
                        Resumen de Cuenta
                    </h3>
<div class="flex flex-col gap-md">
<!-- Simulated active tenant data -->
<div class="flex items-center gap-md pb-md border-b border-outline-variant">
<div class="w-12 h-12 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface font-headline-md text-headline-md font-bold">
                                CM
                            </div>
<div>
<p class="font-label-md text-label-md text-on-surface">Carlos Mendoza</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Apto 3B - Edificio Central</p>
</div>
</div>
<!-- Financial Status -->
<div class="bg-surface-container-high rounded-lg p-md flex flex-col gap-xs">
<p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Deuda Actual</p>
<div class="flex items-end justify-between">
<span class="font-display-lg text-display-lg text-on-surface leading-none tabular-nums">$1,200.00</span>
<!-- Status Badge: Pending/Overdue style -->
<span class="bg-[#ffdad6] text-[#93000a] font-label-sm text-label-sm px-sm py-[2px] rounded-full flex items-center gap-xs">
<span class="material-symbols-outlined text-[14px]">warning</span>
                                    Atrasado
                                </span>
</div>
</div>
<!-- Quick Context Data -->
<div class="grid grid-cols-2 gap-sm">
<div class="border border-outline-variant rounded-lg p-sm">
<p class="font-label-sm text-label-sm text-on-surface-variant">Renta Mensual</p>
<p class="font-label-md text-label-md text-on-surface tabular-nums">$1,200.00</p>
</div>
<div class="border border-outline-variant rounded-lg p-sm">
<p class="font-label-sm text-label-sm text-on-surface-variant">Último Pago</p>
<p class="font-label-md text-label-md text-on-surface">05/09/2023</p>
</div>
</div>
</div>
</div>
<!-- Info Hint -->
<div class="bg-surface-container-low border border-outline-variant rounded-xl p-md flex items-start gap-sm">
<span class="material-symbols-outlined text-tertiary mt-xs text-[20px]">info</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">
                        El pago se aplicará automáticamente al mes más antiguo con deuda pendiente si no se especifica lo contrario en notas.
                    </p>
</div>
</div>
</div>
</main>
<script>
        // Micro-interaction for dropdown selection to simulate updating the side panel
        document.getElementById('tenant-select').addEventListener('change', function(e) {
            // In a real app, this would fetch data. Here we just add a small visual pulse to the summary card.
            const summaryCard = document.querySelector('.lg\\:col-span-4 > div:first-child');
            summaryCard.style.opacity = '0.5';
            setTimeout(() => {
                summaryCard.style.opacity = '1';
                summaryCard.style.transition = 'opacity 0.3s ease';
            }, 150);
        });
    </script>
</body></html>
