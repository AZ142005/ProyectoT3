<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;

/**
 * Controlador de compatibilidad: redirige las rutas legacy
 * /admin/login y /admin/logout al flujo de autenticación unificado.
 */
class AdminAuthController extends Controller {
    public function login() {
        // Si ya está autenticado como admin, ir al dashboard
        if (Auth::hasRole('admin')) {
            $this->redirect('/admin/dashboard');
        }

        // Redirigir al login unificado
        $this->redirect('/auth/login');
    }

    public function logout() {
        Auth::logout();
        $this->redirect('/auth/login');
    }
}
