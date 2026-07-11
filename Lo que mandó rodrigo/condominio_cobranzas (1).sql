-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-07-2026 a las 22:56:57
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `condominio_cobranzas`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comprobantes_pago`
--

CREATE TABLE `comprobantes_pago` (
  `id` int(11) NOT NULL,
  `residente_id` int(11) NOT NULL,
  `factura_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('transferencia','efectivo','punto_venta','cheque') NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('pendiente','verificado','aprobado','rechazado') DEFAULT 'pendiente',
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas`
--

CREATE TABLE `facturas` (
  `id` int(11) NOT NULL,
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
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `facturas`
--

INSERT INTO `facturas` (`id`, `numero_factura`, `unidad_id`, `mes`, `anio`, `fecha_emision`, `fecha_vencimiento`, `monto_total`, `monto_pagado`, `saldo`, `estado`, `observaciones`) VALUES
(7, 'FAC-2026-06-0001', 1, 6, 2026, '2026-06-01', '2026-06-15', 150.00, 0.00, 150.00, 'pendiente', NULL),
(8, 'FAC-2026-06-0002', 2, 6, 2026, '2026-06-01', '2026-06-15', 180.00, 0.00, 180.00, 'pendiente', NULL),
(9, 'FAC-2026-06-0003', 3, 6, 2026, '2026-06-01', '2026-06-15', 200.00, 0.00, 200.00, 'pendiente', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personas`
--

CREATE TABLE `personas` (
  `id` int(11) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `tipo` enum('propietario','residente','ambos') DEFAULT 'residente',
  `password` varchar(255) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `personas`
--

INSERT INTO `personas` (`id`, `cedula`, `nombre`, `apellido`, `telefono`, `email`, `unidad_id`, `tipo`, `password`, `estado`, `fecha_registro`) VALUES
(1, 'V12345678', 'Juan', 'Perez', '04121234567', 'juan@email.com', 1, 'ambos', '12345678', 1, '2026-07-02 18:00:52'),
(2, 'V87654321', 'Maria', 'Gomez', '04127654321', 'maria@email.com', 2, 'propietario', '87654321', 1, '2026-07-02 18:00:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades`
--

CREATE TABLE `unidades` (
  `id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `torre` varchar(50) DEFAULT NULL,
  `cuota_mensual` decimal(10,2) NOT NULL,
  `estado` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `unidades`
--

INSERT INTO `unidades` (`id`, `numero`, `torre`, `cuota_mensual`, `estado`) VALUES
(1, 'A-101', 'A', 150.00, 1),
(2, 'A-102', 'A', 180.00, 1),
(3, 'B-201', 'B', 200.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) DEFAULT NULL,
  `rol` enum('admin') DEFAULT 'admin',
  `estado` tinyint(4) DEFAULT 1,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `password`, `nombre_completo`, `rol`, `estado`, `ultimo_acceso`, `fecha_registro`) VALUES
(1, 'admin', 'admin123', 'Administrador', 'admin', 1, '2026-07-02 20:55:27', '2026-07-02 18:00:52');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `comprobantes_pago`
--
ALTER TABLE `comprobantes_pago`
  ADD PRIMARY KEY (`id`),
  ADD KEY `residente_id` (`residente_id`),
  ADD KEY `factura_id` (`factura_id`);

--
-- Indices de la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_factura` (`numero_factura`),
  ADD KEY `unidad_id` (`unidad_id`);

--
-- Indices de la tabla `personas`
--
ALTER TABLE `personas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD KEY `unidad_id` (`unidad_id`);

--
-- Indices de la tabla `unidades`
--
ALTER TABLE `unidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `comprobantes_pago`
--
ALTER TABLE `comprobantes_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `facturas`
--
ALTER TABLE `facturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `personas`
--
ALTER TABLE `personas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `comprobantes_pago`
--
ALTER TABLE `comprobantes_pago`
  ADD CONSTRAINT `comprobantes_pago_ibfk_1` FOREIGN KEY (`residente_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comprobantes_pago_ibfk_2` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD CONSTRAINT `facturas_ibfk_1` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `personas`
--
ALTER TABLE `personas`
  ADD CONSTRAINT `personas_ibfk_1` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
