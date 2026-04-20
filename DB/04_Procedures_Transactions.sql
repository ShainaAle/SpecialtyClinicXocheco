-- ==============================================================================
-- DATABASE CREATION SCRIPT: Clinica Xocheco (File 04 of 07)
-- Purpose: Business Logic (Stored Procedures, Transactions, Commit & Rollback)
-- Engine: MySQL / MariaDB
-- Author: Shaina Xochitiotzi
-- Version: 1.0
-- Date: 2026-04-20
-- ==============================================================================

USE clinica_xocheco;

-- Change delimiter to allow writing complex blocks of code
DELIMITER //

-- ==============================================================================
-- PROCEDURE: sp_agendar_cita_segura
-- Purpose: Safely schedules an appointment ensuring no overlaps and generates the initial payment.
-- Uses explicit COMMIT and ROLLBACK to guarantee data integrity.
-- ==============================================================================
DROP PROCEDURE IF EXISTS sp_agendar_cita_segura //

CREATE PROCEDURE sp_agendar_cita_segura(
    IN p_id_paciente INT,
    IN p_id_medico INT,
    IN p_id_espacio INT,
    IN p_id_servicio INT,
    IN p_fecha_hora DATETIME
)
BEGIN
    -- 1. Variable Declarations
    DECLARE v_tiene_adeudo BOOLEAN;
    DECLARE v_medico_ocupado INT;
    DECLARE v_espacio_ocupado INT;
    
    DECLARE v_id_cita INT;
    DECLARE v_id_pago INT;
    DECLARE v_precio_servicio DECIMAL(10,2);
    DECLARE v_nombre_servicio VARCHAR(100);

    -- 2. Error Handler declaration for unexpected SQL exceptions
    -- If any query fails (e.g., foreign key error), it rolls back everything.
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL; -- Re-throw the error to the frontend
    END;

    -- 3. Start the Transaction explicitly
    START TRANSACTION;

    -- ==========================================
    -- PHASE 1: BUSINESS LOGIC VALIDATIONS
    -- ==========================================
    
    -- Check 1: Does the patient have an outstanding debt?
    SELECT adeudo INTO v_tiene_adeudo FROM PACIENTES WHERE id_paciente = p_id_paciente;
    IF v_tiene_adeudo = TRUE THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: El paciente tiene un adeudo y no puede agendar citas.';
    END IF;

    -- Check 2: Is the doctor already booked at this exact time?
    SELECT COUNT(*) INTO v_medico_ocupado 
    FROM CITAS 
    WHERE id_medico = p_id_medico AND fecha_hora_inicio = p_fecha_hora AND estado != 'Cancelada';
    
    IF v_medico_ocupado > 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: El médico ya tiene una cita en ese horario.';
    END IF;

    -- Check 3: Is the room already booked at this exact time?
    SELECT COUNT(*) INTO v_espacio_ocupado 
    FROM CITAS 
    WHERE id_espacio = p_id_espacio AND fecha_hora_inicio = p_fecha_hora AND estado != 'Cancelada';
    
    IF v_espacio_ocupado > 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: El consultorio/quirófano está ocupado en ese horario.';
    END IF;

    -- ==========================================
    -- PHASE 2: DATA INSERTION (The "All or Nothing" block)
    -- ==========================================

    -- Step A: Insert the Appointment
    INSERT INTO CITAS (id_paciente, id_medico, id_espacio, id_servicio, fecha_hora_inicio, estado)
    VALUES (p_id_paciente, p_id_medico, p_id_espacio, p_id_servicio, p_fecha_hora, 'Programada');
    
    -- Capture the ID of the newly created appointment
    SET v_id_cita = LAST_INSERT_ID();

    -- Step B: Fetch current service details for billing
    SELECT nombre, precio INTO v_nombre_servicio, v_precio_servicio 
    FROM SERVICIOS 
    WHERE id_servicio = p_id_servicio;

    -- Step C: Create the main Payment record
    INSERT INTO PAGOS (id_cita, monto_total, fecha_pago, estado)
    VALUES (v_id_cita, v_precio_servicio, NULL, 'Pendiente');
    
    -- Capture the ID of the newly created payment
    SET v_id_pago = LAST_INSERT_ID();

    -- Step D: Create the Payment Detail line item
    INSERT INTO DETALLE_PAGO (id_pago, descripcion_concepto, cantidad, precio_unitario, subtotal)
    VALUES (v_id_pago, v_nombre_servicio, 1, v_precio_servicio, v_precio_servicio);

    -- ==========================================
    -- PHASE 3: COMMIT
    -- ==========================================
    -- If we reached this line without triggering a SIGNAL or EXIT HANDLER, save everything.
    COMMIT;

END //

-- Reset delimiter back to default
DELIMITER ;

-- ==============================================================================
-- END OF SCRIPT 04_Procedures_Transactions.sql
-- ==============================================================================