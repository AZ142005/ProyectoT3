<?php
session_start();

// Si ya está logueado como residente, ir al dashboard
if (isset($_SESSION['residente_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Si no está logueado, redirigir al inicio
header('Location: ../index.php');
exit;
?>