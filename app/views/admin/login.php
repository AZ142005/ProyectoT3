<div class="flex-1 flex items-center justify-center px-4 py-16">
    <div class="w-full max-w-md bg-surface-container-lowest border border-outline-variant rounded-2xl p-8 shadow-lg relative overflow-hidden transition-all duration-300 hover:shadow-xl">
        <!-- Decoración de Fondo -->
        <div class="absolute -top-12 -right-12 w-24 h-24 bg-primary-container/25 rounded-full blur-xl pointer-events-none"></div>
        <div class="absolute -bottom-12 -left-12 w-24 h-24 bg-on-surface/5 rounded-full blur-xl pointer-events-none"></div>

        <div class="text-center mb-6">
            <span class="inline-block bg-[#2c3e50] text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-3 shadow-sm">Administrador</span>
            <h2 class="text-2xl font-bold text-on-surface">Consola de Control</h2>
            <p class="text-on-surface-variant text-sm mt-2">
                Ingresa tus credenciales para acceder al panel de administración del condominio.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl p-4 text-sm mb-6 flex items-start gap-2">
                <span class="material-symbols-outlined text-[20px] shrink-0">error</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/login" class="flex flex-col gap-5">
            <!-- CSRF Token -->
            <?= csrf_field() ?>
            
            <div class="flex flex-col gap-1.5">
                <label for="usuario" class="text-sm font-semibold text-on-surface-variant">Usuario</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">person</span>
                    <input type="text" id="usuario" name="usuario" placeholder="Ej. admin" required autofocus
                           class="w-full pl-10 pr-4 py-3 bg-background border border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all duration-200">
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-sm font-semibold text-on-surface-variant">Contraseña</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">lock</span>
                    <input type="password" id="password" name="password" placeholder="••••••••" required
                           class="w-full pl-10 pr-12 py-3 bg-background border border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all duration-200">
                    <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/80 hover:text-on-surface transition-colors p-1 flex items-center justify-center">
                        <span class="material-symbols-outlined" id="eyeIcon">visibility</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-[#2c3e50] hover:bg-[#1a252f] text-white font-bold rounded-xl shadow-md hover:shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">login</span>
                Iniciar Sesión
            </button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeIcon.textContent = type === 'text' ? 'visibility_off' : 'visibility';
        });
    });
</script>
