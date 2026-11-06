-- ====================================================================
-- PROYECTO: Control de Aula
-- BASE DE DATOS: Esquema MySQL / MariaDB (InnoDB, utf8mb4)
-- Arquitectura Multi-Aula completa: Aulas independientes, Iluminación,
-- Termostato de Aire Acondicionado, Proyector Multimedia, Escenas Rápidas,
-- Colas de comandos aisladas por aula, API Keys individuales por ESP32
-- y Permisos de aula por Usuario.
-- ====================================================================

CREATE DATABASE IF NOT EXISTS control_aula CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE control_aula;

SET NAMES utf8mb4;
SET time_zone = '-03:00';
SET foreign_key_checks = 0;

-- 1. Tabla: aulas (Arquitectura de Aulas y Espacios Físicos)
CREATE TABLE IF NOT EXISTS `aulas` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `tipo` ENUM('Aula','Laboratorio','Taller','Otro') NOT NULL DEFAULT 'Aula',
  `ubicacion` VARCHAR(100) DEFAULT '',
  `api_key_esp32` VARCHAR(64) NOT NULL,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  `marca_aire` VARCHAR(50) NOT NULL DEFAULT 'Star (OEM)',
  `protocolo_aire` VARCHAR(30) NOT NULL DEFAULT 'COOLIX',
  `marca_proyector` VARCHAR(50) NOT NULL DEFAULT 'Epson',
  `protocolo_proyector` VARCHAR(30) NOT NULL DEFAULT 'EPSON_NEC',
  `luces` TINYINT(1) NOT NULL DEFAULT 0,
  `aire_encendido` TINYINT(1) NOT NULL DEFAULT 0,
  `ultima_accion_aire` VARCHAR(50) NOT NULL DEFAULT 'ninguna',
  `aire_temp` INT NOT NULL DEFAULT 24,
  `aire_modo` VARCHAR(25) NOT NULL DEFAULT 'frio',
  `aire_fan` VARCHAR(20) NOT NULL DEFAULT 'auto',
  `aire_swing` TINYINT(1) NOT NULL DEFAULT 0,
  `proyector_encendido` TINYINT(1) NOT NULL DEFAULT 0,
  `ultima_accion_proyector` VARCHAR(50) NOT NULL DEFAULT 'ninguna',
  `proyector_fuente` VARCHAR(20) NOT NULL DEFAULT 'hdmi1',
  `proyector_freeze` TINYINT(1) NOT NULL DEFAULT 0,
  `proyector_mute` TINYINT(1) NOT NULL DEFAULT 0,
  `proyector_volumen` INT NOT NULL DEFAULT 50,
  `rssi_wifi` INT DEFAULT NULL,
  `uptime_seg` INT UNSIGNED DEFAULT 0,
  `ultima_conexion` DATETIME DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_api_key_esp32` (`api_key_esp32`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Espacios iniciales por defecto:
INSERT INTO `aulas` (
  `id`, `nombre`, `tipo`, `ubicacion`, `api_key_esp32`, `activa`,
  `marca_aire`, `protocolo_aire`, `marca_proyector`, `protocolo_proyector`,
  `luces`, `aire_encendido`, `aire_temp`, `aire_modo`, `aire_fan`, `aire_swing`,
  `proyector_encendido`, `proyector_fuente`, `proyector_volumen`
) VALUES
(1, 'Aula 101 - General', 'Aula', 'Planta Baja', 'clave_secreta_esp32_aula_101_general_abc12345', 1, 'Star (OEM)', 'COOLIX', 'Epson', 'EPSON_NEC', 0, 0, 24, 'frio', 'auto', 0, 0, 'hdmi1', 50),
(2, 'Laboratorio 1 - Informática', 'Laboratorio', 'Piso 1 - Ala Norte', 'esp32_key_lab1_inform_5678', 1, 'Star (OEM)', 'COOLIX', 'Epson', 'EPSON_NEC', 0, 0, 22, 'frio', 'medio', 0, 0, 'hdmi1', 60),
(3, 'Taller de Electrónica', 'Taller', 'Piso 2 - Talleres', 'esp32_key_taller_elect_9012', 1, 'Star (OEM)', 'COOLIX', 'Epson', 'EPSON_NEC', 0, 0, 25, 'frio', 'auto', 0, 0, 'hdmi2', 40)
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `tipo` = VALUES(`tipo`),
  `ubicacion` = VALUES(`ubicacion`),
  `marca_aire` = VALUES(`marca_aire`),
  `protocolo_aire` = VALUES(`protocolo_aire`),
  `marca_proyector` = VALUES(`marca_proyector`),
  `protocolo_proyector` = VALUES(`protocolo_proyector`);

-- 2. Tabla: dispositivos_ir
CREATE TABLE IF NOT EXISTS `dispositivos_ir` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `nombre` VARCHAR(50) NOT NULL,
  `etiqueta` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_disp_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dispositivos_ir` (`nombre`, `etiqueta`) VALUES
('aire', 'Aire acondicionado'),
('proyector', 'Proyector')
ON DUPLICATE KEY UPDATE `etiqueta` = VALUES(`etiqueta`);

-- 3. Tabla: estado_actual (Retrocompatibilidad para endpoints y clientes mono-aula)
CREATE TABLE IF NOT EXISTS `estado_actual` (
  `id` INT NOT NULL DEFAULT 1,
  `luces` TINYINT(1) NOT NULL DEFAULT 0,
  `luces_deseadas` TINYINT(1) NOT NULL DEFAULT 0,
  `luces_reales` TINYINT(1) NOT NULL DEFAULT 0,
  `aire_encendido` TINYINT(1) NOT NULL DEFAULT 0,
  `ultima_accion_aire` VARCHAR(50) NOT NULL DEFAULT 'ninguna',
  `aire_temp` INT NOT NULL DEFAULT 24,
  `aire_modo` VARCHAR(25) NOT NULL DEFAULT 'frio',
  `aire_fan` VARCHAR(20) NOT NULL DEFAULT 'auto',
  `aire_swing` TINYINT(1) NOT NULL DEFAULT 0,
  `proyector_encendido` TINYINT(1) NOT NULL DEFAULT 0,
  `ultima_accion_proyector` VARCHAR(50) NOT NULL DEFAULT 'ninguna',
  `proyector_fuente` VARCHAR(20) NOT NULL DEFAULT 'hdmi1',
  `proyector_freeze` TINYINT(1) NOT NULL DEFAULT 0,
  `proyector_mute` TINYINT(1) NOT NULL DEFAULT 0,
  `proyector_volumen` INT NOT NULL DEFAULT 50,
  `ip` VARCHAR(45) DEFAULT NULL,
  `rssi` INT DEFAULT NULL,
  `uptime` INT UNSIGNED DEFAULT 0,
  `version_fw` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
  `ultimo_contacto` DATETIME DEFAULT NULL,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `estado_actual` (
  `id`, `luces`, `luces_deseadas`, `luces_reales`,
  `aire_encendido`, `ultima_accion_aire`, `aire_temp`, `aire_modo`, `aire_fan`, `aire_swing`,
  `proyector_encendido`, `ultima_accion_proyector`, `proyector_fuente`, `proyector_freeze`, `proyector_mute`, `proyector_volumen`,
  `version_fw`
) VALUES (
  1, 0, 0, 0,
  0, 'ninguna', 24, 'frio', 'auto', 0,
  0, 'ninguna', 'hdmi1', 0, 0, 50,
  '1.0.0'
) ON DUPLICATE KEY UPDATE `id` = `id`;

-- 4. Tabla: codigos_ir (Plantilla Global + Códigos por Aula)
CREATE TABLE IF NOT EXISTS `codigos_ir` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `dispositivo` VARCHAR(50) NOT NULL,
  `accion` VARCHAR(50) NOT NULL,
  `protocolo` VARCHAR(30) NOT NULL DEFAULT 'NEC',
  `codigo` VARCHAR(50) DEFAULT NULL,
  `codigo_hex` VARCHAR(50) DEFAULT NULL,
  `bits` INT NOT NULL DEFAULT 32,
  `estado_hex` TEXT DEFAULT NULL,
  `raw` MEDIUMTEXT DEFAULT NULL,
  `repeticiones` INT NOT NULL DEFAULT 1,
  `pausa_ms` INT NOT NULL DEFAULT 100,
  `frecuencia_khz` INT NOT NULL DEFAULT 38,
  `descripcion` VARCHAR(255) DEFAULT '',
  `aula_id` INT DEFAULT NULL,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_disp_accion_aula` (`dispositivo`, `accion`, `aula_id`),
  KEY `idx_aula` (`aula_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `codigos_ir` (`dispositivo`, `accion`, `protocolo`, `codigo`, `codigo_hex`, `bits`, `repeticiones`, `pausa_ms`, `frecuencia_khz`, `descripcion`, `aula_id`) VALUES
-- Códigos reales Aire BGH (Coolix 24-bit / 38 kHz - Solo Encender y Apagar)
('aire', 'prender',          'COOLIX',   '0xB23F40',       '0xB23F40',       24, 1, 100, 38, 'BGH Aire Encender', NULL),
('aire', 'apagar',           'COOLIX',   '0xB27BE0',       '0xB27BE0',       24, 1, 100, 38, 'BGH Aire Apagar', NULL),
-- Códigos reales Proyector Epson (Protocolo NEC 32-bit / 38 kHz - Prefijo 0xC1AA)
('proyector', 'prender',     'NEC', '0xC1AA09F6', '0xC1AA09F6', 32, 1, 100,  38, 'Proyector Epson Power (Encender)', NULL),
('proyector', 'apagar',      'NEC', '0xC1AA09F6', '0xC1AA09F6', 32, 2, 2000, 38, 'Proyector Epson Power (Apagar 2x)', NULL),
('proyector', 'vol_mas',     'NEC', '0xC1AA19E6', '0xC1AA19E6', 32, 1, 100,  38, 'Proyector Epson Subir Volumen', NULL),
('proyector', 'vol_menos',   'NEC', '0xC1AA9966', '0xC1AA9966', 32, 1, 100,  38, 'Proyector Epson Bajar Volumen', NULL),
('proyector', 'subir',       'NEC', '0xC1AA19E6', '0xC1AA19E6', 32, 1, 100,  38, 'Proyector Epson Subir Volumen', NULL),
('proyector', 'bajar',       'NEC', '0xC1AA9966', '0xC1AA9966', 32, 1, 100,  38, 'Proyector Epson Bajar Volumen', NULL),
('proyector', 'vol_mute',    'NEC', '0xC1AAC936', '0xC1AAC936', 32, 1, 100,  38, 'Proyector Epson A/V Mute', NULL),
('proyector', 'hdmi1',       'NEC', '0xC1AA9B64', '0xC1AA9B64', 32, 1, 100,  38, 'Proyector Epson Entrada HDMI 1', NULL),
('proyector', 'hdmi2',       'NEC', '0xC1AA0CF3', '0xC1AA0CF3', 32, 1, 100,  38, 'Proyector Epson Entrada HDMI 2 / Source', NULL),
('proyector', 'vga',         'NEC', '0xC1AAA659', '0xC1AAA659', 32, 1, 100,  38, 'Proyector Epson Entrada VGA (Computer)', NULL),
('proyector', 'freeze',      'NEC', '0xC1AA49B6', '0xC1AA49B6', 32, 1, 100,  38, 'Proyector Epson Congelar Imagen (Freeze)', NULL),
('proyector', 'av_mute',     'NEC', '0xC1AAC936', '0xC1AAC936', 32, 1, 100,  38, 'Proyector Epson Pantalla en Negro (AV Mute)', NULL),
-- Códigos específicos asignados a Aula 1 (Proyector Epson NEC)
('proyector', 'prender',     'NEC', '0xC1AA09F6', '0xC1AA09F6', 32, 1, 100,  38, 'Proyector Epson Power (Encender)', 1),
('proyector', 'apagar',      'NEC', '0xC1AA09F6', '0xC1AA09F6', 32, 2, 2000, 38, 'Proyector Epson Power (Apagar 2x)', 1),
('proyector', 'vol_mas',     'NEC', '0xC1AA4AB5', '0xC1AA4AB5', 32, 1, 100,  38, 'Proyector Epson Subir Volumen', 1),
('proyector', 'vol_menos',   'NEC', '0xC1AA4EB1', '0xC1AA4EB1', 32, 1, 100,  38, 'Proyector Epson Bajar Volumen', 1),
('proyector', 'subir',       'NEC', '0xC1AA4AB5', '0xC1AA4AB5', 32, 1, 100,  38, 'Proyector Epson Subir Volumen', 1),
('proyector', 'bajar',       'NEC', '0xC1AA4EB1', '0xC1AA4EB1', 32, 1, 100,  38, 'Proyector Epson Bajar Volumen', 1)
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `codigo_hex` = VALUES(`codigo_hex`), `protocolo` = VALUES(`protocolo`), `bits` = VALUES(`bits`), `repeticiones` = VALUES(`repeticiones`), `pausa_ms` = VALUES(`pausa_ms`), `frecuencia_khz` = VALUES(`frecuencia_khz`), `descripcion` = VALUES(`descripcion`);

-- 5. Tabla: comandos_ir (Cola de comandos IR filtrada por aula)
CREATE TABLE IF NOT EXISTS `comandos_ir` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `aula_id` INT DEFAULT 1,
  `dispositivo` VARCHAR(50) NOT NULL DEFAULT 'aire',
  `accion` VARCHAR(50) NOT NULL,
  `protocolo` VARCHAR(30) NOT NULL DEFAULT 'NEC',
  `codigo` VARCHAR(50) DEFAULT NULL,
  `codigo_hex` VARCHAR(50) DEFAULT NULL,
  `bits` INT NOT NULL DEFAULT 32,
  `estado_hex` TEXT DEFAULT NULL,
  `raw` MEDIUMTEXT DEFAULT NULL,
  `repeticiones` INT NOT NULL DEFAULT 1,
  `pausa_ms` INT NOT NULL DEFAULT 100,
  `frecuencia_khz` INT NOT NULL DEFAULT 38,
  `ac_state` TEXT DEFAULT NULL,
  `estado` ENUM('pendiente','entregado','ejecutado','expirado') NOT NULL DEFAULT 'pendiente',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entregado_en` DATETIME DEFAULT NULL,
  `ejecutado_en` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aula_estado` (`aula_id`, `estado`, `creado_en`),
  KEY `idx_estado_creado` (`estado`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla: capturas_ir (Capturas con identificación de aula)
CREATE TABLE IF NOT EXISTS `capturas_ir` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `aula_id` INT DEFAULT NULL,
  `protocolo` VARCHAR(30) NOT NULL DEFAULT 'DESCONOCIDO',
  `valor` VARCHAR(50) DEFAULT NULL,
  `bits` INT NOT NULL DEFAULT 0,
  `estado_hex` TEXT DEFAULT NULL,
  `raw` MEDIUMTEXT DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aula_creado` (`aula_id`, `creado_en`),
  KEY `idx_creado` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabla: log_eventos (Auditoría con aula_id)
CREATE TABLE IF NOT EXISTS `log_eventos` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `aula_id` INT DEFAULT NULL,
  `origen` VARCHAR(30) NOT NULL,
  `evento` VARCHAR(100) NOT NULL,
  `detalle` TEXT DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aula_creado` (`aula_id`, `creado_en`),
  KEY `idx_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabla: intentos_login
CREATE TABLE IF NOT EXISTS `intentos_login` (
  `ip` VARCHAR(45) NOT NULL,
  `intentos` INT NOT NULL DEFAULT 1,
  `ultimo_intento` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bloqueado_hasta` DATETIME DEFAULT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabla: usuarios (Cuentas por Aula/Espacio y Administrador)
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `aula_id` INT DEFAULT NULL,
  `usuario` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nombre_completo` VARCHAR(100) NOT NULL,
  `rol` ENUM('admin','aula','profesor') NOT NULL DEFAULT 'aula',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `aulas_permitidas` VARCHAR(255) NOT NULL DEFAULT 'todas',
  `preferencias_json` TEXT DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario` (`usuario`),
  KEY `idx_usuario_aula` (`aula_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuentas iniciales por defecto:
--  * admin    / admin123 (rol: admin, supervisor general de todos los salones)
--  * aula101  / aula123  (rol: aula, vinculado a Aula 101)
--  * lab1     / lab123   (rol: aula, vinculado a Laboratorio 1)
--  * taller1  / taller123 (rol: aula, vinculado a Taller de Electrónica)
INSERT INTO `usuarios` (`usuario`, `password_hash`, `nombre_completo`, `rol`, `aula_id`, `activo`, `aulas_permitidas`) VALUES
('admin', '$2y$10$2jKPHsivzyJCV8ckLvSi3eymLTnwp9SlsPVGPaHELdJjNVmCYLxTS', 'Administrador General', 'admin', NULL, 1, 'todas'),
('aula101', '$2y$10$/48eB9JyldUD4X.j/Uk2Geg4A78aNbe6s2w0h/xY9fL71fhXZKSc.', 'Aula 101 - General', 'aula', 1, 1, '[1]'),
('lab1', '$2y$10$TrMD4d0H1w6hdz5ad0CqWeq5TX6uHOO7r1oQYi1AKx7m/XCaT0mdm', 'Laboratorio 1 - Informática', 'aula', 2, 1, '[2]'),
('taller1', '$2y$10$qYbZ8/6LOHylr5nml8xky.SdsrcNXY5KNSCDYgPwvKF5AUNGq5.Am', 'Taller de Electrónica', 'aula', 3, 1, '[3]')
ON DUPLICATE KEY UPDATE
  `nombre_completo` = VALUES(`nombre_completo`),
  `rol` = VALUES(`rol`),
  `aula_id` = VALUES(`aula_id`),
  `password_hash` = VALUES(`password_hash`);

SET foreign_key_checks = 1;
