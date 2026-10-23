-- ====================================================================
-- PROYECTO: Control de Aula
-- SCRIPT DE MIGRACIÓN: De versión inicial a versión multidispositivo IR
-- ====================================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';
SET foreign_key_checks = 0;

-- 1. Crear tabla dispositivos_ir si no existe
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

-- 2. Adaptar estado_actual para almacenar última acción del proyector
ALTER TABLE `estado_actual`
  ADD COLUMN IF NOT EXISTS `ultima_accion_proyector` VARCHAR(50) NOT NULL DEFAULT 'ninguna' AFTER `ultima_accion_aire`;

-- 3. Adaptar codigos_ir
-- Si existe la columna 'nombre', migrarla a 'accion' y asignar dispositivo 'aire'
SET @existe_nombre = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'codigos_ir' AND column_name = 'nombre' AND table_schema = DATABASE());

-- Agregar nuevas columnas si no existen
ALTER TABLE `codigos_ir`
  ADD COLUMN IF NOT EXISTS `dispositivo` VARCHAR(50) NOT NULL DEFAULT 'aire' AFTER `id`,
  ADD COLUMN IF NOT EXISTS `accion` VARCHAR(50) NOT NULL DEFAULT '' AFTER `dispositivo`,
  ADD COLUMN IF NOT EXISTS `estado_hex` TEXT DEFAULT NULL AFTER `bits`,
  ADD COLUMN IF NOT EXISTS `raw` MEDIUMTEXT DEFAULT NULL AFTER `estado_hex`,
  ADD COLUMN IF NOT EXISTS `repeticiones` INT NOT NULL DEFAULT 1 AFTER `raw`,
  ADD COLUMN IF NOT EXISTS `pausa_ms` INT NOT NULL DEFAULT 100 AFTER `repeticiones`,
  ADD COLUMN IF NOT EXISTS `frecuencia_khz` INT NOT NULL DEFAULT 38 AFTER `pausa_ms`,
  MODIFY COLUMN `codigo` VARCHAR(50) DEFAULT NULL;

-- Si se migra desde la versión con 'nombre'
UPDATE `codigos_ir` 
SET `accion` = CASE 
    WHEN `nombre` = 'aire_prender' THEN 'prender'
    WHEN `nombre` = 'aire_apagar' THEN 'apagar'
    ELSE `nombre`
END,
`dispositivo` = 'aire'
WHERE `accion` = '' AND @existe_nombre > 0;

-- Eliminar índice único viejo uk_nombre si existe y agregar el nuevo uk_disp_accion
SET @existe_uk_nombre = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_name = 'codigos_ir' AND index_name = 'uk_nombre' AND table_schema = DATABASE());
SET @sql_drop_uk = IF(@existe_uk_nombre > 0, 'ALTER TABLE `codigos_ir` DROP INDEX `uk_nombre`', 'SELECT 1');
PREPARE stmt_drop FROM @sql_drop_uk;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

SET @existe_uk_disp = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_name = 'codigos_ir' AND index_name = 'uk_disp_accion' AND table_schema = DATABASE());
SET @sql_add_uk = IF(@existe_uk_disp = 0, 'ALTER TABLE `codigos_ir` ADD UNIQUE KEY `uk_disp_accion` (`dispositivo`, `accion`), ADD KEY IF NOT EXISTS `idx_dispositivo` (`dispositivo`)', 'SELECT 1');
PREPARE stmt_add FROM @sql_add_uk;
EXECUTE stmt_add;
DEALLOCATE PREPARE stmt_add;

-- Códigos de proyector por defecto
INSERT INTO `codigos_ir` (`dispositivo`, `accion`, `protocolo`, `codigo`, `bits`, `repeticiones`, `pausa_ms`, `frecuencia_khz`, `descripcion`) VALUES
('proyector', 'prender', 'NEC', '0x4CB0F00F', 32, 1, 100, 38, 'Encender proyector'),
('proyector', 'apagar',  'NEC', '0x4CB0708F', 32, 2, 250, 38, 'Apagar proyector')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`);

-- 4. Adaptar comandos_ir
ALTER TABLE `comandos_ir`
  ADD COLUMN IF NOT EXISTS `dispositivo` VARCHAR(50) NOT NULL DEFAULT 'aire' AFTER `id`,
  ADD COLUMN IF NOT EXISTS `estado_hex` TEXT DEFAULT NULL AFTER `bits`,
  ADD COLUMN IF NOT EXISTS `raw` MEDIUMTEXT DEFAULT NULL AFTER `estado_hex`,
  ADD COLUMN IF NOT EXISTS `repeticiones` INT NOT NULL DEFAULT 1 AFTER `raw`,
  ADD COLUMN IF NOT EXISTS `pausa_ms` INT NOT NULL DEFAULT 100 AFTER `repeticiones`,
  ADD COLUMN IF NOT EXISTS `frecuencia_khz` INT NOT NULL DEFAULT 38 AFTER `pausa_ms`,
  MODIFY COLUMN `codigo` VARCHAR(50) DEFAULT NULL,
  ADD KEY IF NOT EXISTS `idx_dispositivo` (`dispositivo`);

-- 5. Adaptar capturas_ir
ALTER TABLE `capturas_ir`
  MODIFY COLUMN `valor` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `estado_hex` TEXT DEFAULT NULL AFTER `bits`,
  ADD COLUMN IF NOT EXISTS `raw` MEDIUMTEXT DEFAULT NULL AFTER `estado_hex`;

SET foreign_key_checks = 1;
