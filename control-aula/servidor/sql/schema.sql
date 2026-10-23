-- ====================================================================
-- PROYECTO: Control de Aula
-- BASE DE DATOS: Esquema MySQL / MariaDB (InnoDB, utf8mb4)
-- Soporte multipropósito: Luces, Aire acondicionado, Proyector y tramas IR avanzadas
-- ====================================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';
SET foreign_key_checks = 0;

-- 1. Tabla: dispositivos_ir
-- Catálogo de equipos controlados por infrarrojo en el aula.
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

-- 2. Tabla: estado_actual
-- Guarda el estado global del aula y la telemetría del ESP32.
CREATE TABLE IF NOT EXISTS `estado_actual` (
  `id` INT NOT NULL DEFAULT 1,
  `luces_deseadas` TINYINT(1) NOT NULL DEFAULT 0,
  `luces_reales` TINYINT(1) NOT NULL DEFAULT 0,
  `ultima_accion_aire` VARCHAR(50) NOT NULL DEFAULT 'ninguna',
  `ultima_accion_proyector` VARCHAR(50) NOT NULL DEFAULT 'ninguna',
  `ip` VARCHAR(45) DEFAULT NULL,
  `rssi` INT DEFAULT NULL,
  `uptime` INT UNSIGNED DEFAULT 0,
  `version_fw` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
  `ultimo_contacto` DATETIME DEFAULT NULL,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `estado_actual` (`id`, `luces_deseadas`, `luces_reales`, `ultima_accion_aire`, `ultima_accion_proyector`, `version_fw`)
VALUES (1, 0, 0, 'ninguna', 'ninguna', '1.0.0')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 3. Tabla: codigos_ir
-- Catálogo de comandos IR por dispositivo y acción.
-- Admite códigos cortos (hex), tramas largas de estado (estado_hex) y secuencias raw.
CREATE TABLE IF NOT EXISTS `codigos_ir` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `dispositivo` VARCHAR(50) NOT NULL,
  `accion` VARCHAR(50) NOT NULL,
  `protocolo` VARCHAR(30) NOT NULL DEFAULT 'NEC',
  `codigo` VARCHAR(50) DEFAULT NULL,
  `bits` INT NOT NULL DEFAULT 32,
  `estado_hex` TEXT DEFAULT NULL,
  `raw` MEDIUMTEXT DEFAULT NULL,
  `repeticiones` INT NOT NULL DEFAULT 1,
  `pausa_ms` INT NOT NULL DEFAULT 100,
  `frecuencia_khz` INT NOT NULL DEFAULT 38,
  `descripcion` VARCHAR(255) DEFAULT '',
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_disp_accion` (`dispositivo`, `accion`),
  KEY `idx_dispositivo` (`dispositivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Códigos iniciales de prueba para Aire y Proyector
INSERT INTO `codigos_ir` (`dispositivo`, `accion`, `protocolo`, `codigo`, `bits`, `repeticiones`, `pausa_ms`, `frecuencia_khz`, `descripcion`) VALUES
('aire', 'prender', 'NEC', '0xFF629D', 32, 1, 100, 38, 'Encender aire acondicionado (código NEC de prueba)'),
('aire', 'apagar',  'NEC', '0xFF02FD', 32, 1, 100, 38, 'Apagar aire acondicionado (código NEC de prueba)'),
('proyector', 'prender', 'NEC', '0x4CB0F00F', 32, 1, 100, 38, 'Encender proyector (código NEC de prueba)'),
('proyector', 'apagar',  'NEC', '0x4CB0708F', 32, 2, 250, 38, 'Apagar proyector (requiere doble confirmación con pausa)')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`);

-- 4. Tabla: comandos_ir
-- Cola de comandos IR para el ESP32. Copia todos los parámetros de transmisión.
-- Expiran automáticamente a los 30 segundos si el ESP32 no los toma.
CREATE TABLE IF NOT EXISTS `comandos_ir` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `dispositivo` VARCHAR(50) NOT NULL DEFAULT 'aire',
  `accion` VARCHAR(50) NOT NULL,
  `protocolo` VARCHAR(30) NOT NULL DEFAULT 'NEC',
  `codigo` VARCHAR(50) DEFAULT NULL,
  `bits` INT NOT NULL DEFAULT 32,
  `estado_hex` TEXT DEFAULT NULL,
  `raw` MEDIUMTEXT DEFAULT NULL,
  `repeticiones` INT NOT NULL DEFAULT 1,
  `pausa_ms` INT NOT NULL DEFAULT 100,
  `frecuencia_khz` INT NOT NULL DEFAULT 38,
  `estado` ENUM('pendiente','entregado','ejecutado','expirado') NOT NULL DEFAULT 'pendiente',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entregado_en` DATETIME DEFAULT NULL,
  `ejecutado_en` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado_creado` (`estado`, `creado_en`),
  KEY `idx_dispositivo` (`dispositivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla: capturas_ir
-- Códigos y tramas capturados por el receptor KY-022 del ESP32.
CREATE TABLE IF NOT EXISTS `capturas_ir` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `protocolo` VARCHAR(30) NOT NULL DEFAULT 'DESCONOCIDO',
  `valor` VARCHAR(50) DEFAULT NULL,
  `bits` INT NOT NULL DEFAULT 0,
  `estado_hex` TEXT DEFAULT NULL,
  `raw` MEDIUMTEXT DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_creado` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla: log_eventos
-- Registro de auditoría cronológico.
CREATE TABLE IF NOT EXISTS `log_eventos` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `origen` VARCHAR(30) NOT NULL,
  `evento` VARCHAR(100) NOT NULL,
  `detalle` TEXT DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabla: intentos_login
-- Mitigación de ataques de fuerza bruta.
CREATE TABLE IF NOT EXISTS `intentos_login` (
  `ip` VARCHAR(45) NOT NULL,
  `intentos` INT NOT NULL DEFAULT 1,
  `ultimo_intento` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bloqueado_hasta` DATETIME DEFAULT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
