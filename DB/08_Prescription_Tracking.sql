-- ==============================================================================
-- SUPPORT SCRIPT: Prescription tracking
-- Purpose: Store a special prescription code and its supply status.
-- ==============================================================================

USE clinica_xocheco;

CREATE TABLE IF NOT EXISTS RECETAS_SURTIDO (
    id_receta INT NOT NULL PRIMARY KEY,
    codigo_receta VARCHAR(30) NOT NULL UNIQUE,
    estado_surtido ENUM('Pendiente', 'Surtida') NOT NULL DEFAULT 'Pendiente',
    fecha_surtido DATETIME NULL,
    id_usuario_surtio INT NULL,
    FOREIGN KEY (id_receta) REFERENCES RECETAS(id_receta) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_surtio) REFERENCES USUARIOS(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

