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
-- Códigos reales Star AC (Coolix OEM 24-bit)
('aire', 'prender',          'COOLIX', '0xB2BF40', '0xB2BF40', 24, 1, 100, 38, 'Star Aire Encender (24°C Frío Auto)', NULL),
('aire', 'apagar',           'COOLIX', '0xB27BE0', '0xB27BE0', 24, 1, 100, 38, 'Star Aire Apagar', NULL),
('aire', 'frio_18',          'COOLIX', '0xB2BF00', '0xB2BF00', 24, 1, 100, 38, 'Star Aire 18°C Frío Max', NULL),
('aire', 'frio_20',          'COOLIX', '0xB2BF10', '0xB2BF10', 24, 1, 100, 38, 'Star Aire 20°C Frío', NULL),
('aire', 'frio_22',          'COOLIX', '0xB2BF20', '0xB2BF20', 24, 1, 100, 38, 'Star Aire 22°C Frío', NULL),
('aire', 'frio_24',          'COOLIX', '0xB2BF40', '0xB2BF40', 24, 1, 100, 38, 'Star Aire 24°C Frío Eco', NULL),
('aire', 'frio_26',          'COOLIX', '0xB2BF60', '0xB2BF60', 24, 1, 100, 38, 'Star Aire 26°C Frío', NULL),
('aire', 'calor_21',         'COOLIX', '0xB2BF95', '0xB2BF95', 24, 1, 100, 38, 'Star Aire 21°C Calor', NULL),
('aire', 'calor_26',         'COOLIX', '0xB2BF9C', '0xB2BF9C', 24, 1, 100, 38, 'Star Aire 26°C Calor Confort', NULL),
('aire', 'modo_frio',        'COOLIX', '0xB2BF40', '0xB2BF40', 24, 1, 100, 38, 'Star Aire Modo Frío', NULL),
('aire', 'modo_calor',       'COOLIX', '0xB2BF9C', '0xB2BF9C', 24, 1, 100, 38, 'Star Aire Modo Calor', NULL),
('aire', 'modo_ventilacion', 'COOLIX', '0xB2BFC8', '0xB2BFC8', 24, 1, 100, 38, 'Star Aire Modo Ventilación', NULL),
('aire', 'modo_seco',        'COOLIX', '0xB2BF84', '0xB2BF84', 24, 1, 100, 38, 'Star Aire Modo Seco (Deshumidificador)', NULL),
('aire', 'modo_auto',        'COOLIX', '0xB2BF08', '0xB2BF08', 24, 1, 100, 38, 'Star Aire Modo Automático', NULL),
('aire', 'fan_auto',         'COOLIX', '0xB2BF40', '0xB2BF40', 24, 1, 100, 38, 'Star Aire Ventilador Auto', NULL),
('aire', 'fan_bajo',         'COOLIX', '0xB2BF42', '0xB2BF42', 24, 1, 100, 38, 'Star Aire Ventilador Bajo', NULL),
('aire', 'fan_medio',        'COOLIX', '0xB2BF44', '0xB2BF44', 24, 1, 100, 38, 'Star Aire Ventilador Medio', NULL),
('aire', 'fan_alto',         'COOLIX', '0xB2BF46', '0xB2BF46', 24, 1, 100, 38, 'Star Aire Ventilador Alto', NULL),
('aire', 'swing',            'COOLIX', '0xB26BE0', '0xB26BE0', 24, 1, 100, 38, 'Star Aire Oscilación Swing', NULL),
('aire', 'swing_on',         'COOLIX', '0xB26BE0', '0xB26BE0', 24, 1, 100, 38, 'Star Aire Oscilación Swing ON', NULL),
('aire', 'swing_off',        'COOLIX', '0xB26BE0', '0xB26BE0', 24, 1, 100, 38, 'Star Aire Oscilación Swing OFF', NULL),
-- Códigos reales Epson Proyector (Protocolo NEC 32-bit / 38 kHz)
('proyector', 'prender',     'NEC', '0x8355906F', '0x8355906F', 32, 1, 100, 38, 'Epson Proyector Encender', NULL),
('proyector', 'apagar',      'NEC', '0x8355906F', '0x8355906F', 32, 2, 2000, 38, 'Epson Proyector Apagar (doble confirmación 2 seg)', NULL),
('proyector', 'hdmi1',       'NEC', '0x8355D926', '0x8355D926', 32, 1, 100, 38, 'Epson Proyector Entrada HDMI 1', NULL),
('proyector', 'hdmi2',       'NEC', '0x835530CF', '0x835530CF', 32, 1, 100, 38, 'Epson Proyector Entrada HDMI 2', NULL),
('proyector', 'vga',         'NEC', '0x8355659A', '0x8355659A', 32, 1, 100, 38, 'Epson Proyector Entrada VGA (Computer)', NULL),
('proyector', 'freeze',      'NEC', '0x8355926D', '0x8355926D', 32, 1, 100, 38, 'Epson Proyector Congelar Imagen (Freeze)', NULL),
('proyector', 'av_mute',     'NEC', '0x8355936C', '0x8355936C', 32, 1, 100, 38, 'Epson Proyector Pantalla en Negro (AV Mute)', NULL),
('proyector', 'vol_mas',     'NEC', '0x835552AD', '0x835552AD', 32, 1, 100, 38, 'Epson Proyector Subir Volumen', NULL),
('proyector', 'vol_menos',   'NEC', '0x8355728D', '0x8355728D', 32, 1, 100, 38, 'Epson Proyector Bajar Volumen', NULL),
('proyector', 'vol_mute',    'NEC', '0x8355936C', '0x8355936C', 32, 1, 100, 38, 'Epson Proyector Silenciar Audio (Mute)', NULL)
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `codigo_hex` = VALUES(`codigo_hex`), `protocolo` = VALUES(`protocolo`), `bits` = VALUES(`bits`), `repeticiones` = VALUES(`repeticiones`), `pausa_ms` = VALUES(`pausa_ms`), `descripcion` = VALUES(`descripcion`);

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
('taller1', '$2y$10$qYbZ8/6LOHylr5nml8xky.SdsrcNXY5KNSCDYgPwvKF5AUNGq5.Am', 'Taller de Electrónica', 'aula', 3, 1, '[3]'),
('profesor', '$2y$10$ErTRob8vbwM3TDbxQFPFc.vEvN4LbND2oMHejmfRniZfs7fmnORbq', 'Profesor de Prueba', 'profesor', 1, 1, 'todas')
ON DUPLICATE KEY UPDATE
  `nombre_completo` = VALUES(`nombre_completo`),
  `rol` = VALUES(`rol`),
  `aula_id` = VALUES(`aula_id`),
  `password_hash` = VALUES(`password_hash`);

SET foreign_key_checks = 1;
