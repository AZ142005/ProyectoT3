<!-- Tipografía moderna geométrica para interfaces (Plus Jakarta Sans) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<div class="login-split-wrapper">
    <!-- Columna Izquierda: Área del Formulario -->
    <div class="login-form-side">
        <!-- Espaciador flexible superior para centrado vertical perfecto -->
        <div class="login-spacer"></div>

        <!-- Contenedor Central del Formulario -->
        <div class="login-form-container">
            <!-- Logo y Encabezado Principal -->
            <div class="login-brand-header text-center mb-4">
                <div class="login-brand-logo mb-3">
                    <img src="/img/logo_condominio.png" alt="Conjunto Residencial Las Mesetas" width="298" height="298">
                </div>
                <p class="login-brand-subtitle">Ingresa a tu cuenta para continuar</p>
            </div>

            <!-- Mensajes de Error / Advertencia -->
            <?php if (!empty($error)): ?>
                <?php 
                $esAdvertencia = (
                    stripos($error, 'pendiente') !== false ||
                    stripos($error, 'verificada') !== false ||
                    stripos($error, 'inactiva') !== false ||
                    stripos($error, 'revisión') !== false
                );
                ?>
                <div class="alert <?= $esAdvertencia ? 'alert-warning' : 'alert-danger' ?> d-flex align-items-center gap-2 py-2 px-3 mb-4 rounded-3 small" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" x2="12" y1="8" y2="12"/>
                        <line x1="12" x2="12.01" y1="16" y2="16"/>
                    </svg>
                    <div class="text-start"><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulario de Inicio de Sesión -->
            <form method="POST" action="/auth/login" class="mb-4" novalidate>
                <?= csrf_field() ?>

                <!-- Correo Electrónico (Borde a borde, icono SVG limpio de sobre) -->
                <div class="login-field-group mb-3">
                    <label for="email" class="login-label">
                        Correo Electrónico <span class="text-danger">*</span>
                    </label>
                    <div class="login-input-wrapper">
                        <span class="login-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="20" height="16" x="2" y="4" rx="2"/>
                                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" required autocomplete="username"
                               placeholder="usuario@ejemplo.com"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               class="login-input" aria-label="Correo Electrónico">
                    </div>
                </div>

                <!-- Contraseña (Borde a borde, icono SVG de candado y toggle interactivo) -->
                <div class="login-field-group mb-3">
                    <label for="password" class="login-label">
                        Contraseña <span class="text-danger">*</span>
                    </label>
                    <div class="login-input-wrapper">
                        <span class="login-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="login-input">
                        <button type="button" onclick="togglePassword('password', this)" class="login-toggle-password" title="Mostrar u ocultar contraseña">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="icon-eye-off d-none" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>
                                <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
                                <path d="M17.479 17.499A10.75 10.75 0 0 1 2.062 12.349a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 2.899-4.324"/>
                                <line x1="2" x2="22" y1="2" y2="22"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Botón Principal con degradado sutil, drop shadow e icono centrado -->
                <button type="submit" class="btn btn-login-primary w-100 mt-4">
                    <span>Iniciar Sesión</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"/>
                        <path d="m12 5 7 7-7 7"/>
                    </svg>
                </button>
            </form>

            <!-- Acciones Secundarias y Botón Secundario de Ancho Completo -->
            <div class="login-secondary-section pt-3 border-top">
                <p class="login-register-prompt text-center mb-3">
                    <span class="text-question">¿No tienes cuenta?</span>
                    <a href="/auth/register" class="register-link">Crear Cuenta de Residente</a>
                </p>
                <a href="/pago-directo" class="btn btn-login-secondary w-100">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="14" x="2" y="5" rx="2"/>
                        <line x1="2" x2="22" y1="10" y2="10"/>
                    </svg>
                    <span>Pagar sin iniciar sesión</span>
                </a>
            </div>
        </div>

        <!-- Espaciador flexible inferior -->
        <div class="login-spacer"></div>

        <!-- Footer discreto en la parte inferior -->
        <div class="login-footer text-center">
            <div class="d-flex justify-content-center gap-3 mb-1">
                <?php if (!\App\Core\Auth::check()): ?>
                    <a href="/admin/login" class="login-footer-link">Administrador</a>
                    <span class="opacity-25">&bull;</span>
                <?php endif; ?>
                <a href="#" class="login-footer-link">Términos y Condiciones</a>
            </div>
            <div class="login-footer-copy">
                &copy; <?= date('Y') ?> Condominio Digital. Todos los derechos reservados.
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Área Visual (Fotografía Arquitectónica de Alta Calidad + Overlay Elegante) -->
    <div class="login-visual-side">
        <div class="visual-brand-card">
            <div class="visual-pill">
                <span class="visual-pill-dot"></span>
                <span>Plataforma Digital para la Cobranza</span>
            </div>
            <h2 class="visual-card-title">Gestión de pagos eficiente</h2>
            <p class="visual-card-desc">Gestiona tus pagos y consulta tu estado de deuda en tiempo real, de forma simple y segura.</p>
        </div>
    </div>
</div>

<style>
/* Reset de Viewport y Bloqueo Estricto de Scroll Vertical */
html, body {
    height: 100% !important;
    max-height: 100vh !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    background-color: #ffffff;
}

body > main {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow: hidden !important;
    padding: 0 !important;
    margin: 0 !important;
}

body > footer {
    display: none !important;
}

/* Wrapper Split-Screen a Pantalla Completa */
.login-split-wrapper {
    height: 100vh;
    max-height: 100vh;
    width: 100vw;
    display: flex;
    overflow: hidden;
    background-color: #ffffff;
}

/* Columna Izquierda: Área del Formulario */
.login-form-side {
    flex: 0 0 46%;
    width: 46%;
    max-width: 540px;
    height: 100vh;
    max-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 2.75rem 8% 1.75rem;
    background-color: #ffffff;
    box-sizing: border-box;
    overflow-y: auto;
}

.login-spacer {
    flex: 1 1 auto;
    min-height: 0.5rem;
}

.login-form-container {
    width: 100%;
    max-width: 380px;
    margin: 0 auto;
    align-self: center;
}

/* Logo y Marca */
.login-brand-header {
    text-align: center;
}

.login-brand-logo img {
    display: block;
    width: 298px;
    height: auto;
    max-width: 100%;
    margin: 0 auto;
    transform: translateX(-4px);
}

.login-brand-subtitle {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6B7280;
    margin-bottom: 0;
}

/* Etiquetas de Campos */
.login-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.4rem;
    display: block;
}

/* Envoltorio de Inputs (Fondo gris suave, bordes sutiles, foco reactivo) */
.login-input-wrapper {
    display: flex;
    align-items: center;
    width: 100%;
    background-color: #F9FAFB;
    border: 1px solid #E5E7EB;
    border-radius: 0.75rem;
    padding: 0 0.875rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.login-input-wrapper:hover {
    border-color: #D1D5DB;
    background-color: #F3F4F6;
}

.login-input-wrapper:focus-within {
    background-color: #ffffff;
    border-color: #10B981;
    box-shadow: 0 0 0 3.5px rgba(16, 185, 129, 0.18);
}

.login-input-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9CA3AF;
    margin-right: 0.75rem;
    flex-shrink: 0;
    transition: color 0.2s ease;
}

.login-input-wrapper:focus-within .login-input-icon {
    color: #059669;
}

.login-input {
    flex: 1 1 auto;
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    padding: 0.75rem 0;
    font-size: 0.9rem;
    font-weight: 500;
    color: #111827;
}

.login-input::placeholder {
    color: #9CA3AF;
    font-weight: 400;
}

.login-toggle-password {
    background: none;
    border: none;
    outline: none;
    padding: 0.25rem;
    color: #9CA3AF;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 6px;
    transition: color 0.2s ease;
}

.login-toggle-password:hover {
    color: #4B5563;
}

/* Botón Principal (Verde con degradado sutil y drop-shadow suave) */
.btn-login-primary {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    border: none;
    border-radius: 0.75rem;
    padding: 0.78rem 1.25rem;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.35);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.btn-login-primary:hover, .btn-login-primary:focus {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #ffffff;
    box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.45);
    transform: translateY(-1px);
}

.btn-login-primary:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px 0 rgba(16, 185, 129, 0.3);
}

/* Sección de Acciones Secundarias */
.login-secondary-section {
    border-top: 1px solid #F3F4F6 !important;
}

.login-register-prompt {
    font-size: 0.875rem;
}

.text-question {
    color: #4B5563;
    font-weight: 500;
}

.register-link {
    color: #059669;
    font-weight: 600;
    text-decoration: none;
    margin-left: 0.3rem;
    transition: color 0.15s ease;
}

.register-link:hover {
    color: #047857;
    text-decoration: underline;
}

/* Botón Secundario (Fondo gris claro, ancho completo) */
.btn-login-secondary {
    background-color: #F3F4F6;
    border: 1px solid #E5E7EB;
    border-radius: 0.75rem;
    padding: 0.72rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #1F2937;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-login-secondary:hover, .btn-login-secondary:focus {
    background-color: #E5E7EB;
    border-color: #D1D5DB;
    color: #111827;
}

/* Footer Discreto */
.login-footer {
    padding-top: 0.75rem;
    font-size: 0.75rem;
}

.login-footer-link {
    color: #9CA3AF;
    text-decoration: none;
    transition: color 0.15s ease;
}

.login-footer-link:hover {
    color: #059669;
}

.login-footer-copy {
    color: #9CA3AF;
    font-size: 0.72rem;
    margin-top: 0.25rem;
}

/* Columna Derecha: Área Visual (Fotografía Arquitectónica + Overlay Oscuro Elegante) */
.login-visual-side {
    flex: 1 1 auto;
    height: 100vh;
    max-height: 100vh;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-end;
    padding: 3.5rem;
    background-color: #0f172a;
    background-image: 
        linear-gradient(180deg, rgba(15, 23, 42, 0.45) 0%, rgba(15, 23, 42, 0.88) 100%),
        url('https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1800&q=85');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    box-sizing: border-box;
}

/* Tarjeta Flotante con Glassmorphism */
.visual-brand-card {
    position: relative;
    z-index: 2;
    max-width: 500px;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 1.25rem;
    padding: 2rem 2.25rem;
    color: #ffffff;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
}

.visual-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(16, 185, 129, 0.16);
    border: 1px solid rgba(16, 185, 129, 0.32);
    color: #34D399;
    padding: 0.3rem 0.8rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 1rem;
    letter-spacing: 0.02em;
}

.visual-pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #10B981;
    box-shadow: 0 0 8px #10B981;
}

.visual-card-title {
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1.35;
    letter-spacing: -0.02em;
    color: #F8FAFC;
    margin-bottom: 0.6rem;
}

.visual-card-desc {
    font-size: 0.875rem;
    line-height: 1.55;
    color: #94A3B8;
    margin-bottom: 0;
}

/* Responsive Móvil (< 768px): ocultar visual y expandir formulario a 100% */
@media (max-width: 767.98px) {
    .login-visual-side {
        display: none !important;
    }
    .login-form-side {
        flex: 1 0 100%;
        width: 100%;
        max-width: 100%;
        padding: 2rem 7% 1.5rem;
    }
}

/* Ocultar iconos nativos de Edge / IE */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none !important;
}
</style>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const iconEye = btn.querySelector('.icon-eye');
    const iconEyeOff = btn.querySelector('.icon-eye-off');
    if (input.type === 'password') {
        input.type = 'text';
        iconEye.classList.add('d-none');
        iconEyeOff.classList.remove('d-none');
    } else {
        input.type = 'password';
        iconEyeOff.classList.add('d-none');
        iconEye.classList.remove('d-none');
    }
}
</script>
