    <footer class="bg-[#1a252f] text-white/50 py-6 px-8 text-center text-xs mt-auto border-t border-white/5 w-full">
        <div class="flex justify-center gap-5 flex-wrap mb-2">
            <a href="/admin/login" class="text-white/60 hover:text-primary-container transition-colors">Administrador</a>
            <span class="opacity-20">|</span>
            <a href="#" class="text-white/60 hover:text-primary-container transition-colors">Términos y Condiciones</a>
        </div>
        <p>
            &copy; <?= date('Y') ?> Condominio Digital - Sistema de Cobranzas. Todos los derechos reservados.
        </p>
    </footer>

    <!-- Modal de confirmación de cierre de sesión -->
    <?php if (file_exists(VIEWS_PATH . '/components/modal_logout.php')) { include VIEWS_PATH . '/components/modal_logout.php'; } ?>

    <!-- Bootstrap 5.3.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
