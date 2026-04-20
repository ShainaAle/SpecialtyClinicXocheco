-- ==============================================================================
-- DATABASE CREATION SCRIPT: Clinica Xocheco (File 02 of 07)
-- Purpose: Database Optimization (Indexes for fast querying and reporting)
-- Engine: MySQL / MariaDB (InnoDB)
-- Author: Shaina Xochitiotzi
-- Version: 1.0
-- Date: 2026-04-20
-- ==============================================================================

USE clinica_xocheco;

-- ==============================================================================
-- 1. USERS & PATIENTS INDEXES
-- ==============================================================================

-- Composite index to speed up patient/user searches by full name from the web app
CREATE INDEX idx_usuarios_nombre_completo 
ON USUARIOS (nombre, apellidos);

-- Index to optimize demographic reports (e.g., "Reporte de pacientes por edad")
CREATE INDEX idx_pacientes_nacimiento 
ON PACIENTES (fecha_nacimiento);


-- ==============================================================================
-- 2. SCHEDULING & APPOINTMENTS INDEXES (Crucial for system performance)
-- ==============================================================================

-- Index to speed up daily agenda queries and reports "by period"
CREATE INDEX idx_citas_fecha 
ON CITAS (fecha_hora_inicio);

-- Composite index: Optimizes the query that checks if a doctor is available at a specific time
CREATE INDEX idx_citas_disponibilidad_medico 
ON CITAS (id_medico, fecha_hora_inicio);

-- Composite index: Optimizes the query that checks if a room is available at a specific time
CREATE INDEX idx_citas_disponibilidad_espacio 
ON CITAS (id_espacio, fecha_hora_inicio);

-- Index to quickly filter appointments by their current state (Scheduled, Canceled, etc.)
CREATE INDEX idx_citas_estado 
ON CITAS (estado);


-- ==============================================================================
-- 3. PHARMACY & INVENTORY INDEXES
-- ==============================================================================

-- Index to quickly search for medications by their commercial name in the pharmacy portal
CREATE INDEX idx_medicamentos_nombre 
ON MEDICAMENTOS (nombre_comercial);

-- Index to instantly trigger expiration alerts ("Alertas de caducidad")
CREATE INDEX idx_inventario_caducidad 
ON INVENTARIO (fecha_caducidad);

-- Index to quickly separate active stock from expired stock
CREATE INDEX idx_inventario_estado 
ON INVENTARIO (id_estado_medicamento);


-- ==============================================================================
-- 4. FINANCIAL INDEXES
-- ==============================================================================

-- Composite index to generate income reports ("Reporte de ingresos por periodo")
-- Grouping by status (Paid) and the date the payment was made.
CREATE INDEX idx_pagos_estado_fecha 
ON PAGOS (estado, fecha_pago);

-- ==============================================================================
-- END OF SCRIPT 02_Indexes.sql
-- ==============================================================================
-- Note: Unique indexes were already implicitly created in File 01 
-- using the UNIQUE keyword (e.g., correo, cedula_profesional).
-- ==============================================================================