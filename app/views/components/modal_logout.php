<!-- Modal Emergente de Confirmación de Cierre de Sesión -->
<div id="modalCierreSesion" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 transition-opacity duration-300" onclick="if(event.target === this) cerrarModalLogout();">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-sm w-full shadow-2xl border border-slate-100 text-center transform transition-all scale-100 duration-200">
        <!-- Ícono decorativo con estilo del sistema -->
        <div class="w-16 h-16 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mx-auto mb-4 border border-red-100 shadow-sm">
            <span class="material-symbols-outlined text-3xl">logout</span>
        </div>

        <!-- Título y mensaje -->
        <h3 class="text-xl font-bold text-on-surface tracking-tight">¿Cerrar Sesión?</h3>
        <p class="text-sm text-on-surface-variant mt-2 leading-relaxed">
            ¿Estás seguro de que deseas salir del sistema? Tendrás que volver a ingresar tus credenciales para acceder.
        </p>

        <!-- Botones de Acción -->
        <div class="flex gap-3 mt-6 justify-center items-center">
            <button type="button" onclick="cerrarModalLogout()" 
                    class="w-1/2 py-3 px-4 rounded-xl border border-outline-variant text-on-surface hover:bg-background font-bold text-sm active:scale-95 transition-all shadow-sm">
                Cancelar
            </button>
            <a id="btnConfirmarLogoutModal" href="/logout" 
               class="w-1/2 py-3 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-sm shadow-md shadow-red-200 active:scale-95 transition-all flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[18px]">logout</span>
                <span>Salir</span>
            </a>
        </div>
    </div>
</div>

<script>
function confirmarCierreSesion(e, url) {
    if (e && e.preventDefault) {
        e.preventDefault();
    }
    var modal = document.getElementById('modalCierreSesion');
    var btn = document.getElementById('btnConfirmarLogoutModal');
    var targetUrl = url || (e && e.currentTarget && e.currentTarget.getAttribute('href')) || '/logout';
    
    if (modal && btn) {
        btn.setAttribute('href', targetUrl);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    } else {
        if (confirm('¿Está seguro de que desea cerrar sesión?')) {
            window.location.href = targetUrl;
        }
    }
    return false;
}

function cerrarModalLogout() {
    var modal = document.getElementById('modalCierreSesion');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

// Cerrar modal al presionar la tecla Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' || event.key === 'Esc') {
        cerrarModalLogout();
    }
});
</script>
