-- =====================================================================
-- Fase 12: Solicitudes de Registro y Aprobación Administrativa
-- =====================================================================

CREATE TABLE IF NOT EXISTS solicitudes_registro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    unidad_id INT NOT NULL,
    numero_residentes INT DEFAULT 1 NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
    admin_id INT NULL,
    motivo_rechazo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_solicitud_estado (estado),
    INDEX idx_solicitud_unidad (unidad_id),
    INDEX idx_solicitud_cedula (cedula),
    INDEX idx_solicitud_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
