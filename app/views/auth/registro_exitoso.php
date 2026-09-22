<div class="min-vh-100 d-flex align-items-center justify-content-center py-5 px-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%, #f1f5f9 100%);">
    <div class="w-100" style="max-width: 520px;">
        <div class="card shadow-lg border-0 rounded-4 p-4 p-md-5 text-center">
            <!-- Icono de Éxito -->
            <div class="mx-auto mb-4 d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 84px; height: 84px; background-color: #dcfce7; color: #16a34a;">
                <span class="material-symbols-outlined" style="font-size: 48px;">check_circle</span>
            </div>

            <!-- Título y Mensaje de Éxito -->
            <h1 class="h3 fw-bold text-dark mb-3">¡Solicitud Enviada con Éxito!</h1>

            <p class="text-secondary fs-6 mb-4" style="line-height: 1.6;">
                Su solicitud de registro ha sido procesada exitosamente. Su cuenta se encuentra en estado <strong class="text-dark">PENDIENTE</strong> y está sujeta a verificación administrativa por parte de la junta del condominio.
            </p>

            <div class="alert alert-warning border-0 rounded-3 text-start p-3 mb-4 d-flex align-items-start gap-2" style="background-color: #fef3c7; color: #92400e;">
                <span class="material-symbols-outlined fs-5 shrink-0 mt-0.5" style="color: #b45309;">hourglass_top</span>
                <span class="small">
                    Por motivos de seguridad, no podrá iniciar sesión en la plataforma hasta que la administración apruebe y valide su asignación de unidad.
                </span>
            </div>

            <!-- Únicamente el botón de volver al inicio -->
            <div class="d-grid mt-2">
                <a href="/" class="btn btn-primary btn-lg rounded-3 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" style="background-color: #27ae60; border-color: #27ae60;">
                    <span class="material-symbols-outlined">home</span>
                    <span>Volver al Inicio</span>
                </a>
            </div>
        </div>

        <p class="text-center text-muted small mt-4 mb-0">
            Sistema de Gestión de Cobranzas &copy; <?= date('Y') ?>
        </p>
    </div>
</div>
