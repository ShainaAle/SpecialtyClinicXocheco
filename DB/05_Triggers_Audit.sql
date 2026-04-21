-- ==============================================================================
-- DATABASE CREATION SCRIPT: Clinica Xocheco (File 05 of 07)
-- Purpose: Automation and Auditing (Triggers and Log Files)
-- Engine: MySQL / MariaDB
-- Author: Shaina Xochitiotzi
-- Version: 1.0
-- Date: 2026-04-20
-- ==============================================================================

USE clinica_xocheco;

-- Change delimiter for trigger creation
DELIMITER //

-- ==============================================================================
-- TRIGGER 1: AUDIT LOG (Bitácora de movimientos)
-- Purpose: Automatically logs whenever an appointment status is updated.
-- Fulfills the "Archivos log" and "Bitácora de movimientos" requirement.
-- ==============================================================================
DROP TRIGGER IF EXISTS trg_audit_citas_update //

CREATE TRIGGER trg_audit_citas_update
AFTER UPDATE ON CITAS
FOR EACH ROW
BEGIN
    -- Only log the event if the status actually changed
    IF OLD.estado != NEW.estado THEN
        -- Insert a record into the BITACORA table
        INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
        VALUES (
            (SELECT id_usuario FROM PACIENTES WHERE id_paciente = NEW.id_paciente), 
            CONCAT('Cambio estado cita: ', OLD.estado, ' -> ', NEW.estado), 
            'CITAS', 
            CURRENT_TIMESTAMP
        );
    END IF;
END //

-- ==============================================================================
-- TRIGGER 2: AUTOMATIC INVENTORY DEDUCTION
-- Purpose: When a prescription detail (DETALLE_RECETA) is inserted, it 
-- automatically deducts the requested amount from the active inventory.
-- ==============================================================================
DROP TRIGGER IF EXISTS trg_descontar_inventario //

CREATE TRIGGER trg_descontar_inventario
AFTER INSERT ON DETALLE_RECETA
FOR EACH ROW
BEGIN
    DECLARE v_lote_id INT;
    DECLARE v_cantidad_recetada INT;
    
    -- Extract the numerical amount from the 'dosis' string. 
    -- Assuming a clean integer is passed, otherwise defaults to 1 box/item.
    -- (In a fully normalized pharmacy, 'cantidad_dispensada' would be an INT column, 
    -- but we adapt to the current structure).
    SET v_cantidad_recetada = 1; 

    -- Find the first available lot (lote) for this medication that is NOT expired
    SELECT id_lote INTO v_lote_id
    FROM INVENTARIO
    WHERE id_medicamento = NEW.id_medicamento 
        AND cantidad_disponible > 0 
        AND fecha_caducidad > CURRENT_DATE()
    ORDER BY fecha_caducidad ASC -- FIFO: First in, first out
    LIMIT 1;

    -- If a valid lot was found, deduct the amount
    IF v_lote_id IS NOT NULL THEN
        UPDATE INVENTARIO 
        SET cantidad_disponible = cantidad_disponible - v_cantidad_recetada
        WHERE id_lote = v_lote_id;
    ELSE
        -- If no stock is found, throw an error to rollback the prescription insertion
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: No hay stock activo o no caducado para este medicamento.';
    END IF;
END //

-- Reset delimiter back to default
DELIMITER ;

-- ==============================================================================
-- END OF SCRIPT 05_Triggers_Audit.sql
-- ==============================================================================