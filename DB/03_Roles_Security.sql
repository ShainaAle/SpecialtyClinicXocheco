-- ==============================================================================
-- DATABASE CREATION SCRIPT: Clinica Xocheco (File 03 of 07)
-- Purpose: Data Control Language (DCL) - Roles, Users, and Security Permissions
-- Engine: MySQL / MariaDB
-- Author: Shaina Xochitiotzi
-- Version: 1.0
-- Date: 2026-04-20
-- ==============================================================================

USE clinica_xocheco;

-- ==============================================================================
-- 1. ROLE CREATION
-- Note: Requires MySQL 8.0+ or MariaDB 10.0.5+
-- ==============================================================================

-- Drop roles if they exist to prevent errors during re-execution
DROP ROLE IF EXISTS 'rol_admin', 'rol_medico', 'rol_recepcion', 'rol_paciente', 'rol_farmaceutico';

CREATE ROLE 'rol_admin';
CREATE ROLE 'rol_medico';
CREATE ROLE 'rol_recepcion';
CREATE ROLE 'rol_paciente';
CREATE ROLE 'rol_farmaceutico';

-- ==============================================================================
-- 2. PERMISSION ASSIGNMENT (GRANTs)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- ROLE: Administrator
-- Privilege: Total access to all tables, structure, and data.
-- ------------------------------------------------------------------------------
GRANT ALL PRIVILEGES ON clinica_xocheco.* TO 'rol_admin';

-- ------------------------------------------------------------------------------
-- ROLE: Pharmacist (Farmacéutico)
-- Privilege: The ONLY role (besides admin) that can modify INVENTARIO and MEDICAMENTOS.
-- ------------------------------------------------------------------------------
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_xocheco.INVENTARIO TO 'rol_farmaceutico';
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.MEDICAMENTOS TO 'rol_farmaceutico';
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.ESTADOS_MEDICAMENTOS TO 'rol_farmaceutico';
-- Read-only access to prescriptions to dispatch them
GRANT SELECT ON clinica_xocheco.RECETAS TO 'rol_farmaceutico';
GRANT SELECT ON clinica_xocheco.DETALLE_RECETA TO 'rol_farmaceutico';

-- ------------------------------------------------------------------------------
-- ROLE: Doctor (Médico)
-- Privilege: Can manage consultations, prescriptions, and view their appointments.
-- Restrictions: CANNOT modify inventory. CANNOT manage payments.
-- ------------------------------------------------------------------------------
-- Full access to their medical scope
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.CONSULTAS TO 'rol_medico';
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.RECETAS TO 'rol_medico';
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.DETALLE_RECETA TO 'rol_medico';
-- Read-only access to other modules
GRANT SELECT ON clinica_xocheco.CITAS TO 'rol_medico';
GRANT SELECT ON clinica_xocheco.PACIENTES TO 'rol_medico';
GRANT SELECT ON clinica_xocheco.MEDICAMENTOS TO 'rol_medico'; -- Can see catalog, but not inventory

-- ------------------------------------------------------------------------------
-- ROLE: Reception (Recepción)
-- Privilege: Manage appointments, patients, and payments.
-- Restrictions: CANNOT delete records (only update status to 'Canceled'). CANNOT modify inventory.
-- ------------------------------------------------------------------------------
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.PACIENTES TO 'rol_recepcion';
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.USUARIOS TO 'rol_recepcion';
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.CITAS TO 'rol_recepcion';
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.PAGOS TO 'rol_recepcion';
GRANT SELECT, INSERT, UPDATE ON clinica_xocheco.DETALLE_PAGO TO 'rol_recepcion';
-- Read-only access
GRANT SELECT ON clinica_xocheco.MEDICOS TO 'rol_recepcion';
GRANT SELECT ON clinica_xocheco.ESPACIOS_FISICOS TO 'rol_recepcion';
GRANT SELECT ON clinica_xocheco.SERVICIOS TO 'rol_recepcion';

-- ------------------------------------------------------------------------------
-- ROLE: Patient (Paciente)
-- Privilege: Minimum access. Can only read specific catalogs to make an appointment.
-- ------------------------------------------------------------------------------
GRANT SELECT, INSERT ON clinica_xocheco.CITAS TO 'rol_paciente';
GRANT SELECT ON clinica_xocheco.ESPECIALIDADES TO 'rol_paciente';
GRANT SELECT ON clinica_xocheco.MEDICOS TO 'rol_paciente';
GRANT SELECT ON clinica_xocheco.SERVICIOS TO 'rol_paciente';

-- ==============================================================================
-- 3. APPLY CHANGES
-- ==============================================================================
FLUSH PRIVILEGES;

-- ==============================================================================
-- END OF SCRIPT 03_Roles_Security.sql
-- ==============================================================================