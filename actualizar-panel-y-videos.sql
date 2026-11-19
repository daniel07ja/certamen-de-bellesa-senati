-- En phpMyAdmin, selecciona la base existente del certamen antes de importar.
-- Esta migración se puede volver a ejecutar. Conserva los votos y las cuentas.
SET @sql_video = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'candidatos'
          AND COLUMN_NAME = 'video'
    ),
    'SELECT ''La columna candidatos.video ya existe'' AS estado',
    'ALTER TABLE candidatos ADD COLUMN video VARCHAR(100) DEFAULT NULL'
);
PREPARE migrar_video FROM @sql_video;
EXECUTE migrar_video;
DEALLOCATE PREPARE migrar_video;

CREATE TABLE IF NOT EXISTS historial_borrados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    administrador_id INT UNSIGNED NOT NULL,
    usuario VARCHAR(64) NOT NULL,
    responsable VARCHAR(120) NOT NULL,
    instante BIGINT UNSIGNED NOT NULL,
    estudiantes INT UNSIGNED NOT NULL,
    votos_miss INT UNSIGNED NOT NULL,
    votos_mister INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SHOW COLUMNS FROM candidatos LIKE 'video';
SHOW COLUMNS FROM historial_borrados;
