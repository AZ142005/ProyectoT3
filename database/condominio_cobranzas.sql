-- ========================================================
-- BASE DE DATOS: CONDOMINIO DIGITAL (PROYECTO T3)
-- Fecha de Exportación: 2026-08-28 14:30:45
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Estructura de tabla para `auth_otp_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `auth_otp_tokens`;
CREATE TABLE `auth_otp_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `usuario_tipo` enum('usuario','persona') NOT NULL DEFAULT 'persona',
  `codigo_hash` varchar(255) NOT NULL,
  `intentos` tinyint(4) DEFAULT 0,
  `usado` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_usuario_tipo` (`usuario_id`,`usuario_tipo`,`expires_at`),
  KEY `idx_otp_activo` (`usuario_id`,`usuario_tipo`,`usado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `backups_log`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `backups_log`;
CREATE TABLE `backups_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `archivo` varchar(255) NOT NULL,
  `tamano` int(11) DEFAULT 0,
  `checksum` varchar(64) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `categorias_gastos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `categorias_gastos`;
CREATE TABLE `categorias_gastos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(50) DEFAULT 'receipt_long',
  `color` varchar(20) DEFAULT '#6366f1',
  `activo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `categorias_gastos`
INSERT INTO `categorias_gastos` (`id`, `nombre`, `descripcion`, `icono`, `color`, `activo`, `created_at`) VALUES
('1', 'Mantenimiento e Infraestructura', 'Bombas de agua, ascensores, portones eléctricos y áreas comunes', 'build', '#27ae60', '1', '2026-08-27 08:35:03'),
('2', 'Servicios Públicos', 'Suministro eléctrico común, agua potable y aseo urbano', 'water_drop', '#2980b9', '1', '2026-08-27 08:35:03'),
('3', 'Seguridad y Vigilancia', 'Servicio de vigilancia privada y control de accesos', 'security', '#8e44ad', '1', '2026-08-27 08:35:03'),
('4', 'Administración y Honorarios', 'Honorarios de administración, contabilidad y suministros de oficina', 'account_balance', '#d35400', '1', '2026-08-27 08:35:03'),
('5', 'Fondo de Reserva e Imprevistos', 'Aporte al fondo de reserva para emergencias y proyectos comunitarios', 'savings', '#16a085', '1', '2026-08-27 08:35:03');

-- --------------------------------------------------------
-- Estructura de tabla para `comprobantes_pago`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `comprobantes_pago`;
CREATE TABLE `comprobantes_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `residente_id` int(11) NOT NULL,
  `factura_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('transferencia','efectivo','punto_venta','cheque') NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('pendiente','verificado','aprobado','rechazado') DEFAULT 'pendiente',
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `residente_id` (`residente_id`),
  KEY `factura_id` (`factura_id`),
  CONSTRAINT `comprobantes_pago_ibfk_1` FOREIGN KEY (`residente_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comprobantes_pago_ibfk_2` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `comunicados`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `comunicados`;
CREATE TABLE `comunicados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `contenido` text NOT NULL,
  `nivel_urgencia` enum('normal','importante','urgente') DEFAULT 'normal',
  `admin_id` int(11) DEFAULT NULL,
  `edificio_id` int(11) DEFAULT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `fecha_publicacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comunicados_unidad` (`unidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `conciliacion_lotes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `conciliacion_lotes`;
CREATE TABLE `conciliacion_lotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lote` varchar(50) NOT NULL,
  `banco` varchar(50) DEFAULT NULL,
  `total_movimientos` int(11) DEFAULT 0,
  `creditos` int(11) DEFAULT 0,
  `debitos` int(11) DEFAULT 0,
  `procesado` tinyint(1) DEFAULT 0,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `edificios`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `edificios`;
CREATE TABLE `edificios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `edificios`
INSERT INTO `edificios` (`id`, `nombre`, `descripcion`, `estado`, `fecha_registro`) VALUES
('1', 'A', NULL, '1', '2026-07-19 22:23:34'),
('2', 'B', NULL, '1', '2026-07-19 22:23:34'),
('3', 'Torre A', 'Edificio Residencial Principal', '1', '2026-07-19 22:24:18'),
('4', 'Torre B', 'Edificio Residencial Secundario', '1', '2026-07-19 22:24:18'),
('5', 'Torre C', 'Edificio de Suites / Anexo', '1', '2026-07-19 22:24:18');

-- --------------------------------------------------------
-- Estructura de tabla para `estacionamientos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `estacionamientos`;
CREATE TABLE `estacionamientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unidad_id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `tipo` varchar(50) DEFAULT 'regular',
  `edificio_id` int(11) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_estacionamiento_unidad` (`unidad_id`),
  KEY `idx_estacionamientos_unidad` (`unidad_id`),
  KEY `idx_estacionamiento_edificio` (`edificio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `extractos_bancarios`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `extractos_bancarios`;
CREATE TABLE `extractos_bancarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lote_importacion` varchar(50) NOT NULL,
  `banco` varchar(50) NOT NULL,
  `fecha_movimiento` date NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `tipo_movimiento` enum('credito','debito') NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `saldo` decimal(12,2) DEFAULT NULL,
  `conciliado` tinyint(1) DEFAULT 0,
  `pago_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `fecha_carga` datetime DEFAULT current_timestamp(),
  `usuario_carga` int(11) DEFAULT NULL,
  `estado` enum('pendiente','conciliado','rechazado') DEFAULT 'pendiente',
  `estado_conciliacion` varchar(20) DEFAULT 'pendiente',
  `referencia_pago` varchar(100) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_extractos_lote` (`lote_importacion`),
  KEY `idx_extractos_fecha` (`fecha_movimiento`),
  KEY `idx_extractos_conciliado` (`conciliado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `facturas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `facturas`;
CREATE TABLE `facturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_factura` varchar(50) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `mes` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto_total` decimal(10,2) NOT NULL,
  `monto_pagado` decimal(10,2) DEFAULT 0.00,
  `saldo` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','pagada','vencida') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_factura` (`numero_factura`),
  KEY `unidad_id` (`unidad_id`),
  KEY `idx_facturas_morosidad` (`unidad_id`,`estado`,`saldo`,`fecha_vencimiento`),
  KEY `idx_facturas_antiguedad` (`fecha_vencimiento`,`estado`,`saldo`),
  CONSTRAINT `facturas_ibfk_1` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `facturas`
INSERT INTO `facturas` (`id`, `numero_factura`, `unidad_id`, `mes`, `anio`, `fecha_emision`, `fecha_vencimiento`, `monto_total`, `monto_pagado`, `saldo`, `estado`, `observaciones`, `deleted_at`) VALUES
('7', 'FAC-2026-06-0001', '1', '6', '2026', '2026-06-01', '2026-06-15', '150.00', '0.00', '150.00', 'pendiente', NULL, NULL),
('8', 'FAC-2026-06-0002', '2', '6', '2026', '2026-06-01', '2026-06-15', '180.00', '0.00', '180.00', 'pendiente', NULL, NULL),
('9', 'FAC-2026-06-0003', '3', '6', '2026', '2026-06-01', '2026-06-15', '200.00', '0.00', '200.00', 'pendiente', NULL, NULL),
('10', 'FAC-2026-08-0001', '1', '8', '2026', '2026-08-27', '2026-09-11', '150.00', '0.00', '150.00', 'pendiente', NULL, NULL),
('11', 'FAC-2026-08-0002', '2', '8', '2026', '2026-08-27', '2026-09-11', '180.00', '0.00', '180.00', 'pendiente', NULL, NULL),
('12', 'FAC-2026-08-0003', '3', '8', '2026', '2026-08-27', '2026-09-11', '200.00', '0.00', '200.00', 'pendiente', NULL, NULL),
('13', 'FAC-2026-08-0004', '4', '8', '2026', '2026-08-27', '2026-09-11', '220.00', '0.00', '220.00', 'pendiente', NULL, NULL);

-- --------------------------------------------------------
-- Estructura de tabla para `gastos_comunes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `gastos_comunes`;
CREATE TABLE `gastos_comunes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) DEFAULT NULL,
  `mes` int(11) DEFAULT NULL,
  `anio` int(11) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `monto_total` decimal(10,2) DEFAULT 0.00,
  `fecha` date NOT NULL,
  `proveedor` varchar(150) DEFAULT NULL,
  `nro_factura_proveedor` varchar(50) DEFAULT NULL,
  `soporte_digital` varchar(255) DEFAULT NULL,
  `periodo` varchar(20) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gastos_periodo` (`periodo`),
  KEY `idx_gastos_categoria` (`categoria_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `gastos_comunes`
INSERT INTO `gastos_comunes` (`id`, `categoria_id`, `mes`, `anio`, `descripcion`, `monto_total`, `fecha`, `proveedor`, `nro_factura_proveedor`, `soporte_digital`, `periodo`, `admin_id`, `deleted_at`, `created_at`) VALUES
('1', '1', '1', '2026', 'Mantenimiento ascensores Q1-2026', '5000.00', '2026-01-15', 'Elevadores CA', NULL, NULL, NULL, '1', NULL, '2026-08-27 08:29:53'),
('2', '2', '1', '2026', 'Agua potable enero 2026', '3200.00', '2026-01-31', 'Hidrocapital', NULL, NULL, NULL, '1', NULL, '2026-08-27 08:29:53'),
('3', '3', '1', '2026', 'Vigilancia enero 2026', '2800.00', '2026-01-31', 'Seguridad 24h C.A.', NULL, NULL, NULL, '1', NULL, '2026-08-27 08:29:53');

-- --------------------------------------------------------
-- Estructura de tabla para `jwt_blacklist`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `jwt_blacklist`;
CREATE TABLE `jwt_blacklist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `jti` varchar(64) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `blacklisted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_jti` (`jti`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `log_auditoria`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `log_auditoria`;
CREATE TABLE `log_auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pago_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `estado_anterior` varchar(20) DEFAULT NULL,
  `estado_nuevo` varchar(20) NOT NULL,
  `motivo` text DEFAULT NULL,
  `accion` varchar(50) NOT NULL DEFAULT 'cambio_estado',
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `idx_log_auditoria_pago` (`pago_id`,`fecha_registro`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_tabla_registro` (`tabla_afectada`,`registro_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `log_auditoria_ibfk_1` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `log_auditoria_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `movimientos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `movimientos`;
CREATE TABLE `movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unidad_id` int(11) NOT NULL,
  `tipo` enum('cargo','abono','ajuste') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_movimientos_unidad` (`unidad_id`),
  KEY `idx_movimientos_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `movimientos_cuenta`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `movimientos_cuenta`;
CREATE TABLE `movimientos_cuenta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unidad_id` int(11) NOT NULL,
  `tipo` enum('cargo_factura','abono_pago','ajuste') NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `saldo_anterior` decimal(12,2) NOT NULL,
  `saldo_posterior` decimal(12,2) NOT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_movimientos_unidad` (`unidad_id`,`fecha_movimiento`),
  CONSTRAINT `movimientos_cuenta_ibfk_1` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `notificaciones`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `persona_id` int(11) NOT NULL,
  `comunicado_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_persona` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `notificaciones_cola`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notificaciones_cola`;
CREATE TABLE `notificaciones_cola` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `destinatario_email` varchar(255) NOT NULL,
  `destinatario_telefono` varchar(50) DEFAULT NULL,
  `asunto` varchar(200) NOT NULL,
  `cuerpo_html` mediumtext NOT NULL,
  `canal` enum('email','whatsapp','ambos') DEFAULT 'email',
  `prioridad` enum('alta','normal','baja') DEFAULT 'normal',
  `estado` enum('pendiente','enviado','fallido') DEFAULT 'pendiente',
  `intentos` tinyint(4) DEFAULT 0,
  `proximo_intento` timestamp NULL DEFAULT NULL,
  `error_mensaje` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cola_procesamiento` (`estado`,`proximo_intento`,`prioridad`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `otp_codes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `otp_codes`;
CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `persona_id` int(11) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT 0,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_persona` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `pagos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `pagos`;
CREATE TABLE `pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `residente_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `banco_pagador` varchar(100) DEFAULT NULL,
  `banco_receptor` varchar(100) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `archivo` varchar(255) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'PENDIENTE',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `residente_id` (`residente_id`),
  KEY `unidad_id` (`unidad_id`),
  KEY `idx_pagos_conciliacion` (`referencia`,`monto`,`estado`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`residente_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `personas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `personas`;
CREATE TABLE `personas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `tipo` enum('propietario','inquilino','ambos') NOT NULL DEFAULT 'propietario',
  `password` varchar(255) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `intentos_fallidos` int(11) DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula` (`cedula`),
  KEY `unidad_id` (`unidad_id`),
  KEY `idx_personas_unidad_estado` (`unidad_id`,`estado`),
  CONSTRAINT `personas_ibfk_1` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `personas`
INSERT INTO `personas` (`id`, `cedula`, `nombre`, `apellido`, `telefono`, `email`, `unidad_id`, `tipo`, `password`, `estado`, `fecha_registro`, `intentos_fallidos`, `bloqueado_hasta`, `two_factor_enabled`) VALUES
('2', 'V87654321', 'Maria', 'Gomez', '04127654321', 'maria@email.com', '2', 'propietario', '$2y$10$kPLbyrYMVLKwBWKVy3YIpu9Lt2vtnLfXqvkWU0YIA8cD/gWtiYYnC', '1', '2026-07-02 14:00:52', '0', NULL, '0');

-- --------------------------------------------------------
-- Estructura de tabla para `rate_limits`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(64) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_key_ip` (`key`,`ip`),
  KEY `idx_window` (`window_start`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcado de datos para la tabla `rate_limits`
INSERT INTO `rate_limits` (`id`, `key`, `ip`, `attempts`, `window_start`) VALUES
('1', 'login', '127.0.0.1', '1', '2026-08-27 08:24:41'),
('2', 'login', '127.0.0.1', '1', '2026-08-27 08:27:01'),
('3', 'login', '127.0.0.1', '1', '2026-08-27 08:27:29'),
('4', 'login', '127.0.0.1', '1', '2026-08-27 08:28:05'),
('5', 'login', '127.0.0.1', '1', '2026-08-27 08:30:18'),
('6', 'login', '127.0.0.1', '1', '2026-08-27 08:30:53'),
('7', 'login', '127.0.0.1', '1', '2026-08-27 08:31:47'),
('8', 'login', '127.0.0.1', '1', '2026-08-27 08:32:30'),
('9', 'login', '::1', '1', '2026-08-27 08:39:55'),
('10', 'login', '::1', '1', '2026-08-27 09:26:45'),
('11', 'login', '::1', '1', '2026-08-27 10:04:48'),
('12', 'login', '::1', '1', '2026-08-27 12:22:17'),
('13', 'login', '::1', '1', '2026-08-27 12:58:56'),
('14', 'login', '::1', '1', '2026-08-27 13:11:40'),
('15', 'login', '::1', '1', '2026-08-27 14:30:50'),
('16', 'login', '::1', '1', '2026-08-27 15:10:40'),
('17', 'login', '::1', '1', '2026-08-27 15:54:24'),
('18', 'solicitud_cambio_1', '::1', '1', '2026-08-27 16:03:39'),
('19', 'register', '::1', '1', '2026-08-27 16:05:59'),
('20', 'register', '::1', '1', '2026-08-27 16:06:21'),
('21', 'register', '::1', '1', '2026-08-27 16:06:26'),
('22', 'register', '::1', '1', '2026-08-27 16:06:48'),
('23', 'register', '::1', '1', '2026-08-27 16:20:10'),
('24', 'register', '::1', '1', '2026-08-27 16:20:28'),
('25', 'register', '::1', '1', '2026-08-27 16:20:41'),
('26', 'login', '127.0.0.1', '1', '2026-08-28 08:51:06'),
('27', 'solicitud_cambio_1', '127.0.0.1', '1', '2026-08-28 08:51:19'),
('28', 'login', '127.0.0.1', '1', '2026-08-28 08:51:45'),
('29', 'login', '127.0.0.1', '1', '2026-08-28 09:59:04');

-- --------------------------------------------------------
-- Estructura de tabla para `refresh_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `refresh_tokens`;
CREATE TABLE `refresh_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `usuario_tipo` enum('usuario','persona') NOT NULL DEFAULT 'persona',
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revocado` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_refresh_usuario` (`usuario_id`,`usuario_tipo`,`revocado`),
  KEY `idx_refresh_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `solicitudes_cambio`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `solicitudes_cambio`;
CREATE TABLE `solicitudes_cambio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `persona_id` int(11) NOT NULL,
  `campo` varchar(50) NOT NULL,
  `valor_anterior` varchar(255) DEFAULT NULL,
  `valor_nuevo` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `admin_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_solicitudes_persona` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `solicitudes_cambio_datos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `solicitudes_cambio_datos`;
CREATE TABLE `solicitudes_cambio_datos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `persona_id` int(11) NOT NULL,
  `datos_nuevos_json` varchar(2048) NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  `motivo_admin` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_respuesta` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_solicitudes_persona` (`persona_id`,`estado`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `solicitudes_cambio_datos_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitudes_cambio_datos_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estructura de tabla para `unidades`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `unidades`;
CREATE TABLE `unidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `edificio_id` int(11) DEFAULT NULL,
  `propietario_id` int(11) DEFAULT NULL,
  `numero` varchar(20) NOT NULL,
  `cuota_mensual` decimal(10,2) NOT NULL,
  `estado` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `fk_unidades_edificio` (`edificio_id`),
  KEY `propietario_id` (`propietario_id`),
  CONSTRAINT `fk_unidades_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `edificios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unidades_ibfk_1` FOREIGN KEY (`propietario_id`) REFERENCES `personas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `unidades`
INSERT INTO `unidades` (`id`, `edificio_id`, `propietario_id`, `numero`, `cuota_mensual`, `estado`) VALUES
('1', '3', NULL, 'A-101', '150.00', '1'),
('2', '3', NULL, 'A-102', '180.00', '1'),
('3', '4', NULL, 'B-201', '200.00', '1'),
('4', '5', NULL, 'C-301', '220.00', '1');

-- --------------------------------------------------------
-- Estructura de tabla para `usuarios`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) DEFAULT NULL,
  `rol` enum('admin') DEFAULT 'admin',
  `estado` tinyint(4) DEFAULT 1,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `intentos_fallidos` int(11) DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `usuarios`
INSERT INTO `usuarios` (`id`, `usuario`, `email`, `password`, `nombre_completo`, `rol`, `estado`, `ultimo_acceso`, `fecha_registro`, `intentos_fallidos`, `bloqueado_hasta`, `two_factor_enabled`) VALUES
('1', 'admin', 'admin@conjunto.com', '$2y$12$MfAH2ZrJjnc5dhe0QEAsNuVxNmcAfTQiaE4ljpZF7gi9tP3LCYwXW', 'Junior', 'admin', '1', '2026-08-28 10:28:26', '2026-07-02 14:00:52', '0', NULL, '0');

-- --------------------------------------------------------
-- Estructura de tabla para `vehiculos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `vehiculos`;
CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unidad_id` int(11) DEFAULT NULL,
  `persona_id` int(11) NOT NULL,
  `estacionamiento_id` int(11) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `anio` int(11) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `placa` varchar(20) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_placa` (`placa`),
  KEY `idx_vehiculos_persona` (`persona_id`),
  KEY `idx_vehiculos_unidad` (`unidad_id`),
  KEY `idx_vehiculos_estacionamiento` (`estacionamiento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
