-- Respaldo generado por PRESTA-APP
-- Base de datos: db
-- Fecha: 2026-07-23 20:39:40

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Estructura de la tabla `auditoria`
DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE `auditoria` (
  `id_auditoria` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `accion` varchar(255) DEFAULT NULL,
  `modulo` varchar(100) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_auditoria`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `caja`
DROP TABLE IF EXISTS `caja`;
CREATE TABLE `caja` (
  `id_movimiento` int NOT NULL AUTO_INCREMENT,
  `tipo_movimiento` enum('Entrada','Salida') NOT NULL,
  `origen` enum('Prestamo','Pago','Gasto','Ajuste') NOT NULL,
  `id_referencia` int DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_usuario` int DEFAULT NULL,
  PRIMARY KEY (`id_movimiento`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `caja_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Datos de la tabla `caja`
INSERT INTO `caja` (`id_movimiento`, `tipo_movimiento`, `origen`, `id_referencia`, `monto`, `descripcion`, `fecha`, `id_usuario`) VALUES
('1', 'Entrada', 'Pago', '3', '9250.00', 'Pago recibido — ID Pago #3', '2026-07-17 19:48:20', '1'),
('2', 'Entrada', 'Ajuste', NULL, '100000.00', 'para caja', '2026-07-17 20:17:36', '1'),
('3', 'Entrada', 'Pago', '4', '1000.57', 'Pago recibido — ID Pago #4', '2026-07-18 04:29:12', '1'),
('4', 'Entrada', 'Pago', '5', '38785.07', 'Pago recibido — ID Pago #5', '2026-07-21 18:06:15', '1'),
('5', 'Entrada', 'Pago', '6', '1000.57', 'Pago recibido — ID Pago #6', '2026-07-23 20:09:48', '1'),
('6', 'Entrada', 'Pago', '7', '1004.29', 'Pago recibido — ID Pago #7', '2026-07-23 20:10:14', '1'),
('7', 'Entrada', 'Pago', '8', '3807.69', 'Pago recibido — ID Pago #8', '2026-07-23 20:11:40', '1');

-- Estructura de la tabla `cierre_caja`
DROP TABLE IF EXISTS `cierre_caja`;
CREATE TABLE `cierre_caja` (
  `id_cierre` int NOT NULL AUTO_INCREMENT,
  `fecha` date DEFAULT NULL,
  `total_entrada` decimal(12,2) DEFAULT NULL,
  `total_salida` decimal(12,2) DEFAULT NULL,
  `saldo` decimal(12,2) DEFAULT NULL,
  `id_usuario` int DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cierre`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `cierre_caja_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `clientes`
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `fotografia` varchar(255) DEFAULT NULL,
  `nombre_garante` varchar(100) DEFAULT NULL,
  `telefono_garante` varchar(100) DEFAULT NULL,
  `cedula_garante` varchar(20) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('Activo','Inactivo','Bloqueado') DEFAULT 'Activo',
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `cedula` (`cedula`),
  UNIQUE KEY `correo` (`correo`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Datos de la tabla `clientes`
INSERT INTO `clientes` (`id_cliente`, `nombre`, `apellido`, `cedula`, `direccion`, `telefono`, `correo`, `fotografia`, `nombre_garante`, `telefono_garante`, `cedula_garante`, `fecha`, `estado`) VALUES
('1', 'idalia', 'otildo', '02301641169', 'la punta', '8293175844', 'garciaarberto@gmail.com', NULL, 'josefa', '8297865446', '02340225336987', '2026-06-10 09:43:57', 'Activo'),
('2', 'Angel', 'Pinales', '40211402694', 'La punta pescadora', '8296042700', 'thunegraestefani20@gmail.com', NULL, NULL, NULL, NULL, '2026-06-18 17:48:32', 'Activo'),
('7', 'Angel', 'Grerman', '40211802674', 'Santo domingo', '8293375864', 'garcuiaarberto94@gmail.com', 'uploads/065d487177b6ea9f349864c7ec0b9aa1.jpg', 'Estefani', '8296042700', '02301161179', '2026-07-10 10:34:16', 'Activo'),
('8', 'Estefani', 'carela', '02311161689', 'San francisco de macoris', '8296578900', 'nicauriflores@gmail.com', 'uploads/0f4f77f775370670276176ee2af56ed8.jpg', 'Kenia', '8293125678', '02301209809', '2026-07-10 11:24:18', 'Activo'),
('16', 'Maria', 'german', '40225336987', 'San francisco de macoris', '8293456789', 'mariagerman22@gmail.com', 'uploads/b4f439571e341fba959f63d02f6700a3.jpg', 'carola reyes', '8296573897', '4023689764', '2026-07-17 19:16:56', 'Activo');

-- Estructura de la tabla `configuracion`
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `tasa_mora` decimal(5,2) DEFAULT NULL,
  `dias_aplicacion_mora` int DEFAULT '10',
  `backup_automatico` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `configuracion_sistema`
DROP TABLE IF EXISTS `configuracion_sistema`;
CREATE TABLE `configuracion_sistema` (
  `id` int NOT NULL DEFAULT '1',
  `mora_porcentaje_default` decimal(5,2) NOT NULL DEFAULT '5.00',
  `dias_gracia` int NOT NULL DEFAULT '0',
  `backup_automatico` tinyint(1) NOT NULL DEFAULT '0',
  `backup_frecuencia` enum('Diario','Semanal','Mensual') NOT NULL DEFAULT 'Diario',
  `backup_hora` time NOT NULL DEFAULT '02:00:00',
  `backup_destino` enum('Local','GoogleDrive','OneDrive') NOT NULL DEFAULT 'Local',
  `backup_ultima_ejecucion` datetime DEFAULT NULL,
  `google_refresh_token` text,
  `google_email` varchar(255) DEFAULT NULL,
  `actualizado_por` int DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_config_singleton` CHECK ((`id` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Datos de la tabla `configuracion_sistema`
INSERT INTO `configuracion_sistema` (`id`, `mora_porcentaje_default`, `dias_gracia`, `backup_automatico`, `backup_frecuencia`, `backup_hora`, `backup_destino`, `backup_ultima_ejecucion`, `google_refresh_token`, `google_email`, `actualizado_por`, `actualizado_en`) VALUES
('1', '5.00', '0', '0', 'Diario', '02:00:00', 'Local', NULL, NULL, NULL, NULL, NULL);

-- Estructura de la tabla `contratos`
DROP TABLE IF EXISTS `contratos`;
CREATE TABLE `contratos` (
  `Id_Contrato` int NOT NULL AUTO_INCREMENT,
  `Id_usuario` int DEFAULT NULL,
  `Id_Vehiculo` int DEFAULT NULL,
  `FechaInicio` date NOT NULL,
  `FechaFin` date NOT NULL,
  `FechaContrato` date NOT NULL,
  `TipoContrato` varchar(100) DEFAULT NULL,
  `Estado` enum('Activo','Finalizado','Cancelado') NOT NULL DEFAULT 'Activo',
  `ContratoCosto` decimal(10,0) NOT NULL,
  `ContratoImpuesto` decimal(10,0) NOT NULL,
  `ContratoTotal` decimal(10,0) NOT NULL,
  PRIMARY KEY (`Id_Contrato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `cuentas_contables`
DROP TABLE IF EXISTS `cuentas_contables`;
CREATE TABLE `cuentas_contables` (
  `id_cuenta` int NOT NULL AUTO_INCREMENT,
  `nombre_cuenta` varchar(100) DEFAULT NULL,
  `tipo` enum('Activo','pasivo','ingreso','gasto') DEFAULT NULL,
  PRIMARY KEY (`id_cuenta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `factura`
DROP TABLE IF EXISTS `factura`;
CREATE TABLE `factura` (
  `Id_Factura` int NOT NULL AUTO_INCREMENT,
  `Id_Renta` int NOT NULL,
  `FechaEmicion` date DEFAULT NULL,
  `SubTotal` decimal(10,0) NOT NULL,
  `Impuesto` decimal(10,0) DEFAULT '0',
  `FacturaTotal` decimal(10,0) NOT NULL,
  `FacturaEstado` enum('Pendiente','Pagada','Anulada') DEFAULT 'Pendiente',
  `FechaVencimiento` date NOT NULL,
  `FechaPago` date DEFAULT NULL,
  `FechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Factura`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `inpecciones`
DROP TABLE IF EXISTS `inpecciones`;
CREATE TABLE `inpecciones` (
  `Id_Inpecciones` int NOT NULL AUTO_INCREMENT,
  `Id_Vehiculos` int NOT NULL,
  `Id_usuario` int DEFAULT NULL,
  `FechaInspeccion` date NOT NULL,
  `NivelCombustible` varchar(50) NOT NULL,
  `EstadoGomas` varchar(50) NOT NULL,
  `EstadoFrenos` varchar(50) NOT NULL,
  `EstadoLuces` varchar(50) NOT NULL,
  `EstadoCarroceria` varchar(50) NOT NULL,
  `Observaciones` text NOT NULL,
  PRIMARY KEY (`Id_Inpecciones`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `movimientos_contables`
DROP TABLE IF EXISTS `movimientos_contables`;
CREATE TABLE `movimientos_contables` (
  `id_movimiento` int NOT NULL AUTO_INCREMENT,
  `id_cuenta` int NOT NULL,
  `debe` decimal(12,2) DEFAULT '0.00',
  `haber` decimal(12,2) DEFAULT '0.00',
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_movimiento`),
  KEY `id_cuenta` (`id_cuenta`),
  CONSTRAINT `movimientos_contables_ibfk_1` FOREIGN KEY (`id_cuenta`) REFERENCES `cuentas_contables` (`id_cuenta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `pagos`
DROP TABLE IF EXISTS `pagos`;
CREATE TABLE `pagos` (
  `id_pagos` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int NOT NULL,
  `fecha_pago` datetime DEFAULT CURRENT_TIMESTAMP,
  `monto_pagado` decimal(12,2) NOT NULL,
  `monto_mora` decimal(12,2) DEFAULT '0.00',
  `monto_interes` decimal(12,2) DEFAULT '0.00',
  `monto_capital` decimal(12,2) DEFAULT '0.00',
  `saldo_restante` decimal(12,2) NOT NULL,
  `registrado_por` int DEFAULT NULL,
  PRIMARY KEY (`id_pagos`),
  KEY `fk_pago_prestamo` (`id_prestamo`),
  CONSTRAINT `fk_pago_prestamo` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos` (`id_prestamo`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Datos de la tabla `pagos`
INSERT INTO `pagos` (`id_pagos`, `id_prestamo`, `fecha_pago`, `monto_pagado`, `monto_mora`, `monto_interes`, `monto_capital`, `saldo_restante`, `registrado_por`) VALUES
('1', '1', '2026-07-16 23:27:18', '38785.07', '0.00', '38785.07', '0.00', '129283.56', '1'),
('2', '1', '2026-07-16 23:27:40', '38785.07', '0.00', '38785.07', '0.00', '129283.56', '1'),
('3', '2', '2026-07-17 19:48:20', '9250.00', '0.00', '4440.00', '4810.00', '217190.00', '1'),
('4', '5', '2026-07-18 04:29:12', '1000.57', '0.00', '679.39', '321.18', '13686.80', '1'),
('5', '1', '2026-07-21 18:06:15', '38785.07', '0.00', '38785.07', '0.00', '129283.56', '1'),
('6', '5', '2026-07-23 20:09:48', '1000.57', '0.00', '663.81', '336.76', '13350.04', '1'),
('7', '4', '2026-07-23 20:10:14', '1004.29', '0.00', '407.74', '596.55', '13463.51', '1'),
('8', '3', '2026-07-23 20:11:40', '3807.69', '0.00', '2475.00', '1332.69', '48167.28', '1');

-- Estructura de la tabla `prestamos`
DROP TABLE IF EXISTS `prestamos`;
CREATE TABLE `prestamos` (
  `id_prestamo` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `tasa_interes` decimal(5,2) NOT NULL,
  `tipo_interes` enum('Compuesto','Simple') DEFAULT 'Compuesto',
  `modalidad_pagos` enum('Diario','Semanal','Quincenal','Mensual') NOT NULL,
  `plazo` int NOT NULL,
  `cuota_monto` decimal(12,2) NOT NULL,
  `saldo_pendiente` decimal(12,2) NOT NULL,
  `mora_porcentaje` decimal(5,2) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('Activo','Cancelado','Vencido','Refinanciado') DEFAULT 'Activo',
  PRIMARY KEY (`id_prestamo`),
  KEY `fk_prestamo_cliente` (`id_cliente`),
  CONSTRAINT `fk_prestamo_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Datos de la tabla `prestamos`
INSERT INTO `prestamos` (`id_prestamo`, `id_cliente`, `monto`, `tasa_interes`, `tipo_interes`, `modalidad_pagos`, `plazo`, `cuota_monto`, `saldo_pendiente`, `mora_porcentaje`, `fecha_inicio`, `fecha_fin`, `fecha_registro`, `estado`) VALUES
('1', '2', '30000.00', '30.00', 'Compuesto', 'Semanal', '14', '9234.54', '129283.56', '30.00', '2026-07-16', '2026-10-22', '2026-07-16 22:10:50', 'Activo'),
('2', '8', '150000.00', '2.00', 'Simple', 'Mensual', '24', '9250.00', '217190.00', '5.00', '2026-07-17', '2028-07-06', '2026-07-17 19:47:18', 'Activo'),
('3', '1', '30000.00', '5.00', 'Simple', 'Semanal', '13', '3807.69', '48167.28', '5.00', '2026-07-17', '2026-10-16', '2026-07-17 23:25:50', 'Activo'),
('4', '7', '10000.00', '2.90', 'Simple', 'Semanal', '14', '1004.29', '13463.51', '5.00', '2026-07-17', '2026-10-23', '2026-07-17 23:33:42', 'Activo'),
('5', '1', '10000.00', '4.85', 'Compuesto', 'Semanal', '14', '1000.57', '13350.04', '5.00', '2026-07-18', '2026-10-24', '2026-07-18 04:26:17', 'Activo');

-- Estructura de la tabla `rentas`
DROP TABLE IF EXISTS `rentas`;
CREATE TABLE `rentas` (
  `Id_Rentas` int NOT NULL AUTO_INCREMENT,
  `Id_Reservaciones` int NOT NULL,
  `Id_cliente` int NOT NULL,
  `Id_Usuario` int NOT NULL,
  `FechaEntrega` date NOT NULL,
  `FechaDevolucion` date NOT NULL,
  `kilometrajeDevolucion` decimal(10,0) NOT NULL,
  `TarifaDiaria` decimal(10,0) NOT NULL,
  `CargosAdicionales` decimal(10,0) DEFAULT '0',
  `Descuento` decimal(10,0) DEFAULT '0',
  `TarifaTotal` decimal(10,0) NOT NULL,
  `EstadoRenta` enum('Activa','Finalizado','Con retrazo') DEFAULT 'Activa',
  `FechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Rentas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `resevaciones`
DROP TABLE IF EXISTS `resevaciones`;
CREATE TABLE `resevaciones` (
  `Id_Reservaciones` int NOT NULL AUTO_INCREMENT,
  `Id_cliente` int NOT NULL,
  `Id_Vehiculos` int NOT NULL,
  `Id_Usuario` int NOT NULL,
  `FechaEntrega` date NOT NULL,
  `FechaDevolucion` date NOT NULL,
  `Estado` enum('Disponible','Rentado','En mantenimiento') DEFAULT 'Disponible',
  `FechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Reservaciones`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `respaldos`
DROP TABLE IF EXISTS `respaldos`;
CREATE TABLE `respaldos` (
  `id_respaldo` int NOT NULL AUTO_INCREMENT,
  `archivo` varchar(255) NOT NULL,
  `tamano_bytes` bigint DEFAULT NULL,
  `destino` enum('Local','GoogleDrive','OneDrive') NOT NULL DEFAULT 'Local',
  `tipo` enum('Manual','Automatico') NOT NULL,
  `estado` enum('Exitoso','Fallido') NOT NULL,
  `mensaje_error` text,
  `generado_por` int DEFAULT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`id_respaldo`),
  KEY `generado_por` (`generado_por`),
  CONSTRAINT `respaldos_ibfk_1` FOREIGN KEY (`generado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Datos de la tabla `respaldos`
INSERT INTO `respaldos` (`id_respaldo`, `archivo`, `tamano_bytes`, `destino`, `tipo`, `estado`, `mensaje_error`, `generado_por`, `fecha`) VALUES
('1', 'respaldo_2026-07-20_052254.sql', NULL, 'Local', 'Manual', 'Fallido', '', '1', '2026-07-20 05:22:54'),
('2', 'respaldo_2026-07-20_052704.sql', NULL, 'Local', 'Manual', 'Fallido', '\"mysqldump\" no se reconoce como un comando interno o externo,\r\nprograma o archivo por lotes ejecutable.', '1', '2026-07-20 05:27:04'),
('3', 'respaldo_2026-07-20_053447.sql', NULL, 'Local', 'Manual', 'Fallido', '\"C:\\xampp\\mysql\\bin\" no se reconoce como un comando interno o externo,\r\nprograma o archivo por lotes ejecutable.', '1', '2026-07-20 05:34:47'),
('4', 'respaldo_2026-07-20_053923.sql', NULL, 'Local', 'Manual', 'Fallido', '', '1', '2026-07-20 05:39:24'),
('5', 'respaldo_2026-07-20_054027.sql', NULL, 'Local', 'Manual', 'Fallido', '', '1', '2026-07-20 05:40:27'),
('6', 'respaldo_2026-07-20_055329.sql', NULL, 'Local', 'Manual', 'Fallido', 'mysqldump.exe: Got error: 1045: \"Plugin caching_sha2_password could not be loaded: No se puede encontrar el módulo especificado. Library path is \'caching_sha2_password.dll\'\" when trying to connect', '1', '2026-07-20 05:53:29'),
('7', 'respaldo_2026-07-20_060058.sql', NULL, 'Local', 'Manual', 'Fallido', 'mysqldump.exe: Got error: 1045: \"Plugin caching_sha2_password could not be loaded: No se puede encontrar el módulo especificado. Library path is \'caching_sha2_password.dll\'\" when trying to connect', '1', '2026-07-20 06:00:58'),
('8', 'respaldo_2026-07-21_170556.sql', NULL, 'Local', 'Manual', 'Fallido', 'mysqldump.exe: Got error: 1045: \"Plugin caching_sha2_password could not be loaded: No se puede encontrar el módulo especificado. Library path is \'caching_sha2_password.dll\'\" when trying to connect', '1', '2026-07-21 17:05:56');

-- Estructura de la tabla `roles`
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id_rol` int NOT NULL,
  `nombre_rol` varchar(50) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Datos de la tabla `roles`
INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
('1', 'Administrador');

-- Estructura de la tabla `usuario`
DROP TABLE IF EXISTS `usuario`;
CREATE TABLE `usuario` (
  `Id_usuario` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) DEFAULT NULL,
  `Apellido` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `licencia` varchar(50) DEFAULT NULL,
  `Cedula` varchar(50) DEFAULT NULL,
  `Email` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`Id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Estructura de la tabla `usuarios`
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `id_rol` int NOT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  `intentos_fallidos` int DEFAULT '0',
  `bloqueado` tinyint(1) DEFAULT '0',
  `ultimo_login` datetime DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  KEY `id_rol` (`id_rol`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Datos de la tabla `usuarios`
INSERT INTO `usuarios` (`id_usuario`, `nombre_usuario`, `contrasena`, `id_rol`, `estado`, `intentos_fallidos`, `bloqueado`, `ultimo_login`, `fecha_registro`) VALUES
('1', 'Angel', '1234', '1', 'Activo', '0', '0', '2026-07-23 20:39:24', '2026-04-24 18:25:41'),
('2', 'raiseli', '$2y$10$H0ZdPjN7cbm6qYTixPGUmOudoCxovoi64q1OzpIt4uWrSuyZYKrSC', '1', 'Activo', '0', '0', NULL, '2026-07-23 20:23:06');

SET FOREIGN_KEY_CHECKS = 1;
