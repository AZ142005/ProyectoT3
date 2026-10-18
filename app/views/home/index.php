<?php
// Landing pública del conjunto. El layout base aporta <html>, <head> y <body>;
// esta vista solo produce el contenido y su footer interno (hideFooter = true).
// HomeController inyecta los datos NAP; el respaldo del teléfono permite
// renderizar la vista de forma aislada sin romper el enlace tel:.
$phone = $phone ?? '0271-2259170';
$panelUrl = $panelUrl ?? null;
?>

<!-- Tipografía geométrica compartida con la pantalla de inicio de sesión -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
</style>

<!-- Barra de navegación fija de la landing -->
<header class="sticky top-0 z-50 w-full border-b-2 border-institutional-brown bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-2 px-4 py-3 sm:gap-4 sm:px-6">
        <a href="/" class="flex min-w-0 flex-1 items-center gap-3 overflow-hidden" aria-label="Ir al inicio">
            <img src="/img/logo_condominio.png" alt="Logo del Conjunto Residencial Las Mesetas de Morón" width="570" height="437" class="h-10 w-auto shrink-0">
            <span class="min-w-0">
                <span class="block truncate font-extrabold leading-tight text-on-surface">
                    <span class="sm:hidden">Las Mesetas</span>
                    <span class="hidden sm:inline">Las Mesetas de Morón</span>
                </span>
                <span class="hidden truncate text-xs text-on-surface-variant sm:block">Portal oficial de pagos</span>
            </span>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-semibold text-on-surface-variant lg:flex" aria-label="Navegación principal">
            <a href="#como-pagar" class="transition-colors hover:text-primary">¿Cómo pagar?</a>
            <a href="#ubicacion" class="transition-colors hover:text-primary">Ubicación</a>
            <a href="#preguntas" class="transition-colors hover:text-primary">Preguntas</a>
            <a href="#contacto" class="transition-colors hover:text-primary">Contacto</a>
        </nav>

        <div class="flex shrink-0 items-center gap-2">
            <?php if ($panelUrl !== null): ?>
                <a href="<?= e($panelUrl) ?>" class="inline-flex items-center gap-1 rounded-xl border border-outline-variant bg-white px-3 py-2 text-sm font-bold text-on-surface transition-colors hover:border-primary hover:text-primary sm:px-4">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">dashboard</span>
                    <span class="hidden sm:inline">Mi panel</span>
                </a>
            <?php else: ?>
                <a href="/auth/login" class="inline-flex items-center gap-1 rounded-xl border border-outline-variant bg-white px-3 py-2 text-sm font-bold text-on-surface transition-colors hover:border-primary hover:text-primary sm:px-4">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">login</span>
                    <span class="hidden sm:inline">Iniciar sesión</span>
                </a>
            <?php endif; ?>
            <a href="/pago-directo" class="inline-flex items-center gap-1 rounded-xl bg-primary px-3 py-2 text-sm font-bold text-white transition-colors hover:bg-primary-hover sm:px-4">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">payments</span>
                Pagar
            </a>
        </div>
    </div>
</header>

<!-- Hero principal: fotografía del conjunto con velo institucional -->
<section class="relative isolate overflow-hidden border-b-4 border-institutional-brown bg-[#0f2a1d]">
    <img src="/img/conjunto_las_mesetas.jpg"
         alt="Palmeras y áreas verdes del Conjunto Residencial Las Mesetas de Morón"
         width="1440" height="1440" fetchpriority="high"
         class="absolute inset-0 -z-20 h-full w-full object-cover object-center">
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#0b1f16]/95 via-[#0f2a1d]/80 to-[#0f2a1d]/25" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-6xl px-4 pb-8 pt-14 sm:px-6 sm:pb-10 sm:pt-20">
        <div class="max-w-2xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-bold text-emerald-100 backdrop-blur">
                <span class="material-symbols-outlined text-[16px] text-emerald-300" aria-hidden="true">verified</span>
                Portal oficial · Valera, Estado Trujillo
            </span>

            <h1 class="mt-6 text-3xl font-extrabold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-[3.4rem]">
                Conjunto Residencial<br class="hidden sm:block">
                <span class="text-emerald-200">Las Mesetas de Morón</span>
            </h1>

            <p class="mt-5 max-w-xl text-base leading-relaxed text-white/80 sm:text-lg">
                Consulta tu deuda y reporta tu pago en línea. Un servicio de la administración del conjunto.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/pago-directo" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-950/30 transition-colors hover:bg-primary-hover">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">receipt_long</span>
                    Reportar mi pago
                </a>
                <?php if ($panelUrl !== null): ?>
                    <a href="<?= e($panelUrl) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-6 py-3 text-sm font-bold text-white backdrop-blur transition-colors hover:bg-white/20">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">dashboard</span>
                        Ir a mi panel
                    </a>
                <?php else: ?>
                    <a href="/auth/login" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-6 py-3 text-sm font-bold text-white backdrop-blur transition-colors hover:bg-white/20">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">login</span>
                        Iniciar sesión
                    </a>
                <?php endif; ?>
            </div>

            <p class="mt-6 text-sm text-white/60">Sitio oficial · RIF <?= e($rif) ?> · Av. Principal de Morón</p>
        </div>
    </div>

    <!-- Franja de confianza sobre la fotografía -->
    <div class="relative mx-auto max-w-6xl px-4 pb-12 sm:px-6 sm:pb-16">
        <div class="grid gap-4 rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur-md sm:grid-cols-2 sm:gap-6 sm:p-6">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 text-emerald-300">
                    <span class="material-symbols-outlined text-[22px]" aria-hidden="true">bolt</span>
                </span>
                <span>
                    <span class="block text-sm font-bold text-white">Sin crear cuenta</span>
                    <span class="block text-xs text-white/65">Reporta tu pago en minutos.</span>
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 text-emerald-300">
                    <span class="material-symbols-outlined text-[22px]" aria-hidden="true">photo_camera</span>
                </span>
                <span>
                    <span class="block text-sm font-bold text-white">Comprobante simple</span>
                    <span class="block text-xs text-white/65">Foto o PDF desde tu teléfono.</span>
                </span>
            </div>
        </div>
    </div>
</section>

<!-- Accesos rápidos -->
<section class="px-4 py-12 sm:px-6">
    <div class="mx-auto grid max-w-6xl gap-4 sm:grid-cols-2">
        <a href="/pago-directo" class="group flex items-start gap-4 rounded-2xl border border-outline-variant bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                <span class="material-symbols-outlined text-2xl" aria-hidden="true">receipt_long</span>
            </span>
            <span class="min-w-0">
                <h2 class="text-lg font-bold text-on-surface">Reportar un pago</h2>
                <p class="mt-1 text-sm text-on-surface-variant">Sube tu comprobante sin crear cuenta.</p>
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-primary">
                    Comenzar
                    <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-0.5" aria-hidden="true">arrow_forward</span>
                </span>
            </span>
        </a>
        <a href="<?= e($panelUrl ?? '/auth/login') ?>" class="group flex items-start gap-4 rounded-2xl border border-outline-variant bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                <span class="material-symbols-outlined text-2xl" aria-hidden="true">account_balance_wallet</span>
            </span>
            <span class="min-w-0">
                <h2 class="text-lg font-bold text-on-surface"><?= $panelUrl !== null ? 'Ir a mi panel' : 'Consultar mi estado de cuenta' ?></h2>
                <p class="mt-1 text-sm text-on-surface-variant"><?= $panelUrl !== null ? 'Accede a las herramientas de gestión.' : 'Deudas, historial y comunicados.' ?></p>
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-primary">
                    <?= $panelUrl !== null ? 'Abrir panel' : 'Iniciar sesión' ?>
                    <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-0.5" aria-hidden="true">arrow_forward</span>
                </span>
            </span>
        </a>
    </div>
</section>

<!-- Cómo pagar -->
<section id="como-pagar" class="scroll-mt-24 border-y border-outline-variant bg-white px-4 py-14 sm:px-6">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-2xl text-center">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Proceso simple</span>
            <h2 class="mt-2 text-2xl font-extrabold text-on-surface sm:text-3xl">¿Cómo pagar?</h2>
            <p class="mt-3 text-sm text-on-surface-variant sm:text-base">Reporta tu pago en tres pasos, desde cualquier dispositivo.</p>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-3">
            <article class="relative overflow-hidden rounded-2xl border border-outline-variant bg-background p-6 transition-colors hover:border-primary/40">
                <span class="absolute right-5 top-3 text-4xl font-black text-primary/10" aria-hidden="true">01</span>
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary text-white shadow-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>
                </span>
                <h3 class="mt-4 text-lg font-bold text-on-surface">Reporta tu pago</h3>
                <p class="mt-2 text-sm leading-relaxed text-on-surface-variant">Sube una foto o PDF de tu comprobante sin iniciar sesión.</p>
            </article>
            <article class="relative overflow-hidden rounded-2xl border border-outline-variant bg-background p-6 transition-colors hover:border-primary/40">
                <span class="absolute right-5 top-3 text-4xl font-black text-primary/10" aria-hidden="true">02</span>
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary text-white shadow-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">fact_check</span>
                </span>
                <h3 class="mt-4 text-lg font-bold text-on-surface">La administración verifica</h3>
                <p class="mt-2 text-sm leading-relaxed text-on-surface-variant">El pago se concilia con el estado de cuenta de tu unidad.</p>
            </article>
            <article class="relative overflow-hidden rounded-2xl border border-outline-variant bg-background p-6 transition-colors hover:border-primary/40">
                <span class="absolute right-5 top-3 text-4xl font-black text-primary/10" aria-hidden="true">03</span>
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary text-white shadow-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">mark_email_read</span>
                </span>
                <h3 class="mt-4 text-lg font-bold text-on-surface">Recibe la confirmación</h3>
                <p class="mt-2 text-sm leading-relaxed text-on-surface-variant">Recibirás una notificación por correo cuando tu pago sea revisado.</p>
            </article>
        </div>
    </div>
</section>

<!-- Ubicación y datos de contacto -->
<section id="ubicacion" class="scroll-mt-24 px-4 py-14 sm:px-6">
    <div class="mx-auto max-w-5xl">
        <div class="mx-auto max-w-2xl text-center">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Nuestra sede</span>
            <h2 class="mt-2 text-2xl font-extrabold text-on-surface sm:text-3xl">Ubicación</h2>
        </div>

        <div class="mt-8 overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm">
            <div class="grid md:grid-cols-[1.15fr_.85fr]">
                <div class="space-y-5 p-6 sm:p-8">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined shrink-0 text-primary" aria-hidden="true">location_on</span>
                        <address class="not-italic text-on-surface">
                            <?= e($addressLine) ?><br>
                            <?= e($cityLine) ?>
                        </address>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined shrink-0 text-primary" aria-hidden="true">call</span>
                        <a href="tel:<?= e($phoneHref) ?>" class="text-on-surface transition-colors hover:text-primary"><?= e($phone) ?></a>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined shrink-0 text-primary" aria-hidden="true">mail</span>
                        <a href="mailto:<?= e($email) ?>" class="break-all text-on-surface transition-colors hover:text-primary"><?= e($email) ?></a>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined shrink-0 text-primary" aria-hidden="true">badge</span>
                        <span class="text-on-surface">RIF <?= e($rif) ?></span>
                    </div>

                    <a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary-hover">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">map</span>
                        Ver en Google Maps
                    </a>
                </div>

                <div class="relative hidden overflow-hidden bg-gradient-to-br from-primary to-primary-hover p-8 text-white md:flex md:flex-col md:justify-between">
                    <div class="absolute -right-12 -top-12 h-44 w-44 rounded-full border-[22px] border-white/10" aria-hidden="true"></div>
                    <div class="absolute -bottom-10 -left-10 h-36 w-36 rounded-full border-[18px] border-white/10" aria-hidden="true"></div>
                    <span class="relative grid h-14 w-14 place-items-center rounded-2xl bg-white/15">
                        <span class="material-symbols-outlined text-3xl" aria-hidden="true">location_on</span>
                    </span>
                    <div class="relative">
                        <p class="text-xs font-bold uppercase tracking-widest text-white/70">Cómo llegar</p>
                        <p class="mt-2 text-xl font-extrabold leading-snug">Av. Principal de Morón<br>Urb. Las Mesetas</p>
                        <p class="mt-1 text-sm text-white/75">Valera, Estado Trujillo · Venezuela</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Preguntas frecuentes -->
<section id="preguntas" class="scroll-mt-24 border-y border-outline-variant bg-white px-4 py-14 sm:px-6">
    <div class="mx-auto max-w-3xl">
        <div class="text-center">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Resolvemos tus dudas</span>
            <h2 class="mt-2 text-2xl font-extrabold text-on-surface sm:text-3xl">Preguntas frecuentes</h2>
        </div>

        <div class="mt-8 space-y-3">
            <?php foreach ($faqs as $faq): ?>
                <details class="group rounded-xl border border-outline-variant bg-background px-5 py-4 transition-colors open:border-primary/40">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-on-surface [&amp;::-webkit-details-marker]:hidden">
                        <?= e($faq['pregunta']) ?>
                        <span class="material-symbols-outlined text-on-surface-variant transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-on-surface-variant"><?= e($faq['respuesta']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Contacto -->
<section id="contacto" class="scroll-mt-24 px-4 py-14 sm:px-6">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-2xl text-center">
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Estamos para ayudarte</span>
            <h2 class="mt-2 text-2xl font-extrabold text-on-surface sm:text-3xl">Contacto</h2>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <a href="tel:<?= e($phoneHref) ?>" class="group flex items-center gap-3 rounded-2xl border border-outline-variant bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                    <span class="material-symbols-outlined" aria-hidden="true">call</span>
                </span>
                <span class="min-w-0">
                    <span class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">Teléfono</span>
                    <span class="block text-sm font-semibold text-on-surface"><?= e($phone) ?></span>
                </span>
            </a>

            <a href="mailto:<?= e($email) ?>" class="group flex items-center gap-3 rounded-2xl border border-outline-variant bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                    <span class="material-symbols-outlined" aria-hidden="true">mail</span>
                </span>
                <span class="min-w-0">
                    <span class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">Correo</span>
                    <span class="block text-sm font-semibold text-on-surface"><?= e($email) ?></span>
                </span>
            </a>

            <a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener" class="group flex items-center gap-3 rounded-2xl border border-outline-variant bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                    <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>
                </span>
                <span class="min-w-0">
                    <span class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">Instagram</span>
                    <span class="block text-sm font-semibold text-on-surface">@conjunto.lasmesetas</span>
                </span>
            </a>

            <a href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener" class="group flex items-center gap-3 rounded-2xl border border-outline-variant bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                    <span class="material-symbols-outlined" aria-hidden="true">thumb_up</span>
                </span>
                <span class="min-w-0">
                    <span class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">Facebook</span>
                    <span class="block text-sm font-semibold text-on-surface">lasmesetasdmoron</span>
                </span>
            </a>
        </div>

        <div class="mt-8 overflow-hidden rounded-2xl border-b-4 border-institutional-brown bg-gradient-to-r from-primary to-primary-hover p-6 text-center text-white shadow-md sm:p-8">
            <p class="mx-auto max-w-2xl text-sm leading-relaxed text-white/85">Oficina administrativa en Av. Principal de Morón. Para horarios de atención, contacta a la administración.</p>
            <a href="/pago-directo" class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-primary shadow-sm transition-colors hover:bg-surface-container-high">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">receipt_long</span>
                Reportar mi pago
            </a>
        </div>
    </div>
</section>

<!-- Footer institucional de la landing -->
<footer class="mt-auto w-full bg-institutional-brown text-white">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
        <div>
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white p-1.5">
                    <img src="/img/logo_condominio.png" alt="" width="570" height="437" loading="lazy" class="h-full w-auto object-contain">
                </span>
                <p class="font-extrabold leading-snug"><?= e($siteName) ?></p>
            </div>
            <address class="mt-3 not-italic text-sm text-white/80">
                <?= e($addressLine) ?><br>
                <?= e($cityLine) ?>
            </address>
            <p class="mt-2 text-sm text-white/80">RIF <?= e($rif) ?></p>
        </div>

        <div>
            <p class="text-sm font-bold uppercase tracking-wide">Contacto</p>
            <ul class="mt-2 space-y-1 text-sm text-white/80">
                <li><a href="tel:<?= e($phoneHref) ?>" class="transition-colors hover:text-white"><?= e($phone) ?></a></li>
                <li><a href="mailto:<?= e($email) ?>" class="transition-colors hover:text-white"><?= e($email) ?></a></li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-bold uppercase tracking-wide">Enlaces</p>
            <ul class="mt-2 space-y-1 text-sm text-white/80">
                <li><a href="/auth/login" class="transition-colors hover:text-white">Iniciar sesión</a></li>
                <li><a href="/pago-directo" class="transition-colors hover:text-white">Reportar mi pago</a></li>
                <li><a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener" class="transition-colors hover:text-white">Instagram</a></li>
                <li><a href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener" class="transition-colors hover:text-white">Facebook</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t border-white/20 px-4 py-5 text-center text-xs text-white/70 sm:px-6">
        <p>Sitio oficial. Nunca solicitamos tu contraseña por correo electrónico.</p>
    </div>
</footer>
