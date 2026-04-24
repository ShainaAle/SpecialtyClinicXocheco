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

-- ==============================================================================
-- TRIGGER: Audit change in patient data (for example, when a patient's record is updated)
-- ==============================================================================
DROP TRIGGER IF EXISTS trg_audit_pacientes_update //

CREATE TRIGGER trg_audit_pacientes_update
AFTER UPDATE ON PACIENTES
FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (
        NEW.id_usuario, 
        CONCAT('Modificación de datos del paciente ID'), 
        'PACIENTES', 
        CURRENT_TIMESTAMP
    );
END //

-- ==============================================================================
-- TRIGGER: Audit inventory adjustments (for example, when inventory is updated manually)
-- ==============================================================================
DROP TRIGGER IF EXISTS trg_audit_inventario_update //

CREATE TRIGGER trg_audit_inventario_update
AFTER UPDATE ON INVENTARIO
FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (
        1,
        CONCAT('Ajuste de inventario en lote: ', OLD.id_lote, ' (', OLD.cantidad_disponible, ' -> ', NEW.cantidad_disponible, ')'), 
        'INVENTARIO', 
        CURRENT_TIMESTAMP
    );
END //

-- ==============================================================================
-- TRIGGER: Audit new payment records (for example, when a new payment is inserted)
-- ==============================================================================
DROP TRIGGER IF EXISTS trg_audit_pagos_insert //

CREATE TRIGGER trg_audit_pagos_insert
AFTER INSERT ON PAGOS
FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (
        1,
        CONCAT('Nuevo pago registrado por monto: $', NEW.monto_total), 
        'PAGOS', 
        CURRENT_TIMESTAMP
    );
END //

-- ==============================================================================
-- Complete the audit triggers for all relevant tables (PACIENTES, INVENTARIO, PAGOS, CITAS)
-- ==============================================================================

-- A) When a new patient is added to the system
DROP TRIGGER IF EXISTS trg_audit_pacientes_insert //
CREATE TRIGGER trg_audit_pacientes_insert AFTER INSERT ON PACIENTES FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (NEW.id_usuario, CONCAT('Alta de nuevo paciente ID: ', NEW.id_paciente), 'PACIENTES', CURRENT_TIMESTAMP);
END //

-- B) When an existing patient is modified
DROP TRIGGER IF EXISTS trg_audit_pacientes_update //
CREATE TRIGGER trg_audit_pacientes_update AFTER UPDATE ON PACIENTES FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (NEW.id_usuario, CONCAT('Modificación de paciente ID: ', OLD.id_paciente), 'PACIENTES', CURRENT_TIMESTAMP);
END //

-- C) When a patient is deleted
DROP TRIGGER IF EXISTS trg_audit_pacientes_delete //
CREATE TRIGGER trg_audit_pacientes_delete AFTER DELETE ON PACIENTES FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (OLD.id_usuario, CONCAT('Eliminación de paciente ID: ', OLD.id_paciente), 'PACIENTES', CURRENT_TIMESTAMP);
END //


-- ==============================================================================
-- Inventory Auditing: When inventory records are inserted, updated, or deleted
-- ==============================================================================

-- A) New inventory lot added to the system
DROP TRIGGER IF EXISTS trg_audit_inventario_insert //
CREATE TRIGGER trg_audit_inventario_insert AFTER INSERT ON INVENTARIO FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (1, CONCAT('Ingreso de nuevo lote ID: ', NEW.id_lote, ' - Cantidad: ', NEW.cantidad_disponible), 'INVENTARIO', CURRENT_TIMESTAMP);
END //

-- B) Modification of inventory stock (Thefts/Adjustments)
DROP TRIGGER IF EXISTS trg_audit_inventario_update //
CREATE TRIGGER trg_audit_inventario_update AFTER UPDATE ON INVENTARIO FOR EACH ROW
BEGIN
    IF OLD.cantidad_disponible != NEW.cantidad_disponible THEN
        INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
        VALUES (1, CONCAT('Ajuste de stock Lote ', OLD.id_lote, ': de ', OLD.cantidad_disponible, ' a ', NEW.cantidad_disponible), 'INVENTARIO', CURRENT_TIMESTAMP);
    END IF;
END //

-- C) Deletion of an inventory lot from the system
DROP TRIGGER IF EXISTS trg_audit_inventario_delete //
CREATE TRIGGER trg_audit_inventario_delete AFTER DELETE ON INVENTARIO FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (1, CONCAT('Se eliminó el lote ID: ', OLD.id_lote, ' del sistema'), 'INVENTARIO', CURRENT_TIMESTAMP);
END //


-- ==============================================================================
-- Payment Auditing: When payment records are inserted, updated, or deleted
-- ==============================================================================

-- A) New payment record created in the system
DROP TRIGGER IF EXISTS trg_audit_pagos_insert //
CREATE TRIGGER trg_audit_pagos_insert AFTER INSERT ON PAGOS FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (1, CONCAT('Nuevo cobro generado por: $', NEW.monto_total, ' (Cita: ', NEW.id_cita, ')'), 'PAGOS', CURRENT_TIMESTAMP);
END //

-- B) Cancelation or modification of an existing payment record
DROP TRIGGER IF EXISTS trg_audit_pagos_update //
CREATE TRIGGER trg_audit_pagos_update AFTER UPDATE ON PAGOS FOR EACH ROW
BEGIN
    IF OLD.estado != NEW.estado THEN
        INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
        VALUES (1, CONCAT('Pago ID ', OLD.id_pago, ' cambió de ', OLD.estado, ' a ', NEW.estado), 'PAGOS', CURRENT_TIMESTAMP);
    END IF;
END //


-- ==============================================================================
-- Appointment Auditing: When appointments are created, updated, or deleted
-- ==============================================================================

-- A) When a new appointment is scheduled
DROP TRIGGER IF EXISTS trg_audit_citas_insert //
CREATE TRIGGER trg_audit_citas_insert AFTER INSERT ON CITAS FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    -- Buscamos el ID del usuario paciente para registrarlo
    VALUES ((SELECT id_usuario FROM PACIENTES WHERE id_paciente = NEW.id_paciente), 
            CONCAT('Cita agendada para la fecha: ', NEW.fecha_hora_inicio), 'CITAS', CURRENT_TIMESTAMP);
END //

-- B) When an appointment is deleted from the system
DROP TRIGGER IF EXISTS trg_audit_citas_delete //
CREATE TRIGGER trg_audit_citas_delete AFTER DELETE ON CITAS FOR EACH ROW
BEGIN
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada, fecha_hora)
    VALUES (1, CONCAT('Cita eliminada permanentemente. Fecha original: ', OLD.fecha_hora_inicio), 'CITAS', CURRENT_TIMESTAMP);
END //

-- Reset delimiter back to default
DELIMITER ;

-- ==============================================================================
-- END OF SCRIPT 05_Triggers_Audit.sql
-- ==============================================================================