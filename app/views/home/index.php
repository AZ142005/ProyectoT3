<?php
// Landing pública del conjunto. El layout base aporta <html>, <head> y <body>;
// esta vista solo produce el contenido y su footer interno (hideFooter = true).
// HomeController inyecta los datos NAP; el respaldo del teléfono permite
// renderizar la vista de forma aislada sin romper el enlace tel:.
$phone = $phone ?? '0271-2259170';
$panelUrl = $panelUrl ?? null;
?>

<!-- Barra de navegación fija de la landing -->
<header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b-2 border-institutional-brown w-full">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
        <a href="/" class="flex items-center gap-3 min-w-0" aria-label="Ir al inicio">
            <img src="/img/logo_condominio.png" alt="Logo del Conjunto Residencial Las Mesetas de Morón" width="570" height="437" class="h-10 w-auto">
            <span class="min-w-0">
                <span class="block font-extrabold text-on-surface leading-tight truncate">Las Mesetas de Morón</span>
                <span class="block text-xs text-on-surface-variant">Portal oficial de pagos</span>
            </span>
        </a>

        <nav class="hidden lg:flex items-center gap-5 text-sm font-semibold text-on-surface-variant" aria-label="Navegación principal">
            <a href="#como-pagar" class="hover:text-primary transition-colors">¿Cómo pagar?</a>
            <a href="#ubicacion" class="hover:text-primary transition-colors">Ubicación</a>
            <a href="#preguntas" class="hover:text-primary transition-colors">Preguntas</a>
            <a href="#contacto" class="hover:text-primary transition-colors">Contacto</a>
        </nav>

        <div class="flex items-center gap-2 shrink-0">
            <?php if ($panelUrl !== null): ?>
                <a href="<?= e($panelUrl) ?>" class="inline-flex items-center gap-1 rounded-lg border border-outline-variant bg-white px-3 sm:px-4 py-2 text-sm font-bold text-on-surface hover:border-primary hover:text-primary transition-colors">
                    <span class="material-symbols-outlined text-[18px]">dashboard</span>
                    <span class="hidden sm:inline">Mi panel</span>
                </a>
            <?php else: ?>
                <a href="/auth/login" class="inline-flex items-center gap-1 rounded-lg border border-outline-variant bg-white px-3 sm:px-4 py-2 text-sm font-bold text-on-surface hover:border-primary hover:text-primary transition-colors">
                    <span class="material-symbols-outlined text-[18px]">login</span>
                    <span class="hidden sm:inline">Iniciar sesión</span>
                </a>
            <?php endif; ?>
            <a href="/pago-directo" class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 sm:px-4 py-2 text-sm font-bold text-white hover:bg-primary-hover transition-colors">
                <span class="material-symbols-outlined text-[18px]">payments</span>
                Pagar
            </a>
        </div>
    </div>
</header>

<!-- Hero principal -->
<section class="bg-gradient-to-b from-surface-container-high to-background px-4 sm:px-6 py-12 sm:py-16">
    <div class="max-w-6xl mx-auto grid gap-10 lg:grid-cols-2 items-center">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-outline-variant bg-white px-4 py-1.5 text-xs font-bold text-institutional-brown">
                <span class="material-symbols-outlined text-[16px] text-primary">verified</span>
                Portal oficial · Valera, Estado Trujillo
            </span>

            <h1 class="mt-5 text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-on-surface">Conjunto Residencial Las Mesetas de Morón</h1>

            <p class="mt-4 text-base sm:text-lg text-on-surface-variant max-w-xl">Consulta tu deuda y reporta tu pago en línea. Un servicio de la administración del conjunto.</p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/pago-directo" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover transition-colors">
                    <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                    Reportar mi pago
                </a>
                <?php if ($panelUrl !== null): ?>
                    <a href="<?= e($panelUrl) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-outline-variant bg-white px-6 py-3 text-sm font-bold text-on-surface hover:border-primary hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-[20px]">dashboard</span>
                        Ir a mi panel
                    </a>
                <?php else: ?>
                    <a href="/auth/login" class="inline-flex items-center justify-center gap-2 rounded-xl border border-outline-variant bg-white px-6 py-3 text-sm font-bold text-on-surface hover:border-primary hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-[20px]">login</span>
                        Iniciar sesión
                    </a>
                <?php endif; ?>
            </div>

            <p class="mt-6 text-sm text-on-surface-variant">Sitio oficial · RIF <?= e($rif) ?> · Av. Principal de Morón</p>
        </div>

        <div class="justify-self-center">
            <img src="/img/logo_condominio.png" alt="Logo del Conjunto Residencial Las Mesetas de Morón" width="570" height="437" fetchpriority="high" class="w-full max-w-sm sm:max-w-md h-auto">
        </div>
    </div>
</section>

<!-- Accesos rápidos -->
<section class="px-4 sm:px-6 py-10">
    <div class="max-w-6xl mx-auto grid gap-4 sm:grid-cols-2">
        <a href="/pago-directo" class="block rounded-2xl border border-outline-variant bg-white p-6 shadow-sm hover:border-primary transition-colors">
            <span class="material-symbols-outlined text-primary text-3xl">receipt_long</span>
            <h2 class="mt-3 text-lg font-bold text-on-surface">Reportar un pago</h2>
            <p class="mt-1 text-sm text-on-surface-variant">Sube tu comprobante sin crear cuenta.</p>
        </a>
        <a href="<?= e($panelUrl ?? '/auth/login') ?>" class="block rounded-2xl border border-outline-variant bg-white p-6 shadow-sm hover:border-primary transition-colors">
            <span class="material-symbols-outlined text-primary text-3xl">account_balance_wallet</span>
            <h2 class="mt-3 text-lg font-bold text-on-surface"><?= $panelUrl !== null ? 'Ir a mi panel' : 'Consultar mi estado de cuenta' ?></h2>
            <p class="mt-1 text-sm text-on-surface-variant"><?= $panelUrl !== null ? 'Accede a las herramientas de gestión.' : 'Deudas, historial y comunicados.' ?></p>
        </a>
    </div>
</section>

<!-- Cómo pagar -->
<section id="como-pagar" class="scroll-mt-24 border-y border-outline-variant bg-white px-4 sm:px-6 py-12">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface text-center">¿Cómo pagar?</h2>

        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <article class="rounded-2xl border border-outline-variant bg-background p-6">
                <span class="material-symbols-outlined text-primary text-3xl">photo_camera</span>
                <h3 class="mt-3 text-lg font-bold text-on-surface">1. Reporta tu pago</h3>
                <p class="mt-2 text-sm text-on-surface-variant">Sube una foto o PDF de tu comprobante sin iniciar sesión.</p>
            </article>
            <article class="rounded-2xl border border-outline-variant bg-background p-6">
                <span class="material-symbols-outlined text-primary text-3xl">fact_check</span>
                <h3 class="mt-3 text-lg font-bold text-on-surface">2. La administración verifica</h3>
                <p class="mt-2 text-sm text-on-surface-variant">El pago se concilia con el estado de cuenta de tu unidad.</p>
            </article>
            <article class="rounded-2xl border border-outline-variant bg-background p-6">
                <span class="material-symbols-outlined text-primary text-3xl">mark_email_read</span>
                <h3 class="mt-3 text-lg font-bold text-on-surface">3. Recibe la confirmación</h3>
                <p class="mt-2 text-sm text-on-surface-variant">Recibirás una notificación por correo cuando tu pago sea revisado.</p>
            </article>
        </div>
    </div>
</section>

<!-- Ubicación y datos de contacto -->
<section id="ubicacion" class="scroll-mt-24 px-4 sm:px-6 py-12">
    <div class="max-w-3xl mx-auto">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface text-center">Ubicación</h2>

        <div class="mt-8 rounded-2xl border border-outline-variant bg-white p-6 sm:p-8 space-y-5">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-primary shrink-0">location_on</span>
                <address class="not-italic text-on-surface">
                    <?= e($addressLine) ?><br>
                    <?= e($cityLine) ?>
                </address>
            </div>

            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary shrink-0">call</span>
                <a href="tel:<?= e($phoneHref) ?>" class="text-on-surface hover:text-primary transition-colors"><?= e($phone) ?></a>
            </div>

            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary shrink-0">mail</span>
                <a href="mailto:<?= e($email) ?>" class="text-on-surface hover:text-primary transition-colors"><?= e($email) ?></a>
            </div>

            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary shrink-0">badge</span>
                <span class="text-on-surface">RIF <?= e($rif) ?></span>
            </div>

            <a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover transition-colors">
                <span class="material-symbols-outlined text-[20px]">map</span>
                Ver en Google Maps
            </a>
        </div>
    </div>
</section>

<!-- Preguntas frecuentes -->
<section id="preguntas" class="scroll-mt-24 border-y border-outline-variant bg-white px-4 sm:px-6 py-12">
    <div class="max-w-3xl mx-auto">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface text-center">Preguntas frecuentes</h2>

        <div class="mt-8 space-y-3">
            <?php foreach ($faqs as $faq): ?>
                <details class="group rounded-xl border border-outline-variant bg-background px-5 py-4">
                    <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-semibold text-on-surface [&amp;::-webkit-details-marker]:hidden">
                        <?= e($faq['pregunta']) ?>
                        <span class="material-symbols-outlined text-on-surface-variant transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
                    </summary>
                    <p class="mt-3 text-sm text-on-surface-variant"><?= e($faq['respuesta']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Contacto -->
<section id="contacto" class="scroll-mt-24 px-4 sm:px-6 py-12">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-on-surface text-center">Contacto</h2>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <a href="tel:<?= e($phoneHref) ?>" class="flex items-center gap-3 rounded-2xl border border-outline-variant bg-white p-5 hover:border-primary transition-colors">
                <span class="material-symbols-outlined text-primary text-3xl">call</span>
                <span>
                    <span class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">Teléfono</span>
                    <span class="block font-semibold text-on-surface"><?= e($phone) ?></span>
                </span>
            </a>

            <a href="mailto:<?= e($email) ?>" class="flex items-center gap-3 rounded-2xl border border-outline-variant bg-white p-5 hover:border-primary transition-colors">
                <span class="material-symbols-outlined text-primary text-3xl">mail</span>
                <span class="min-w-0">
                    <span class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">Correo</span>
                    <span class="block font-semibold text-on-surface truncate"><?= e($email) ?></span>
                </span>
            </a>

            <a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-2xl border border-outline-variant bg-white p-5 hover:border-primary transition-colors">
                <span class="material-symbols-outlined text-primary text-3xl">photo_camera</span>
                <span>
                    <span class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">Instagram</span>
                    <span class="block font-semibold text-on-surface">@conjunto.lasmesetas</span>
                </span>
            </a>

            <a href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-2xl border border-outline-variant bg-white p-5 hover:border-primary transition-colors">
                <span class="material-symbols-outlined text-primary text-3xl">thumb_up</span>
                <span>
                    <span class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">Facebook</span>
                    <span class="block font-semibold text-on-surface">lasmesetasdmoron</span>
                </span>
            </a>
        </div>

        <div class="mt-8 rounded-2xl border border-outline-variant bg-surface-container-high p-6 sm:p-8 text-center">
            <p class="text-sm text-on-surface-variant">Oficina administrativa en Av. Principal de Morón. Para horarios de atención, contacta a la administración.</p>
            <a href="/pago-directo" class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover transition-colors">
                <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                Reportar mi pago
            </a>
        </div>
    </div>
</section>

<!-- Footer institucional de la landing -->
<footer class="mt-auto w-full bg-institutional-brown text-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <p class="font-extrabold"><?= e($siteName) ?></p>
            <address class="not-italic mt-2 text-sm text-white/80">
                <?= e($addressLine) ?><br>
                <?= e($cityLine) ?>
            </address>
            <p class="mt-2 text-sm text-white/80">RIF <?= e($rif) ?></p>
        </div>

        <div>
            <p class="text-sm font-bold uppercase tracking-wide">Contacto</p>
            <ul class="mt-2 space-y-1 text-sm text-white/80">
                <li><a href="tel:<?= e($phoneHref) ?>" class="hover:text-white transition-colors"><?= e($phone) ?></a></li>
                <li><a href="mailto:<?= e($email) ?>" class="hover:text-white transition-colors"><?= e($email) ?></a></li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-bold uppercase tracking-wide">Enlaces</p>
            <ul class="mt-2 space-y-1 text-sm text-white/80">
                <li><a href="/auth/login" class="hover:text-white transition-colors">Iniciar sesión</a></li>
                <li><a href="/pago-directo" class="hover:text-white transition-colors">Reportar mi pago</a></li>
                <li><a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener" class="hover:text-white transition-colors">Instagram</a></li>
                <li><a href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener" class="hover:text-white transition-colors">Facebook</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t border-white/20 px-4 sm:px-6 py-5 text-center text-xs text-white/70">
        <p>Sitio oficial. Nunca solicitamos tu contraseña por correo electrónico.</p>
        <p class="mt-1">© <?= date('Y') ?> Conjunto Residencial Las Mesetas de Morón.</p>
    </div>
</footer>
