-- ==============================================================================
-- DATABASE CREATION SCRIPT: Clinica Xocheco (File 06 of 07)
-- Purpose: Pre-defined Views for the "Reportes" module required by the rubric
-- Engine: MySQL / MariaDB
-- Author: Shaina Xochitiotzi
-- Version: 1.0
-- Date: 2026-04-20
-- ==============================================================================

USE clinica_xocheco;

-- ==============================================================================
-- 1. DOCTORS BY SPECIALTY REPORT
-- Rubric: "Reporte de médicos [por especialidad...]"
-- ==============================================================================
CREATE OR REPLACE VIEW vw_reporte_medicos_especialidad AS
SELECT 
    e.nombre AS especialidad,
    u.nombre AS nombre_medico,
    u.apellidos AS apellidos_medico,
    m.cedula_profesional,
    m.turno
FROM MEDICOS m
JOIN USUARIOS u ON m.id_usuario = u.id_usuario
JOIN ESPECIALIDADES e ON m.id_especialidad = e.id_especialidad
ORDER BY e.nombre, u.apellidos;


-- ==============================================================================
-- 2. PATIENT DEMOGRAPHICS REPORT
-- Rubric: "Reporte de pacientes [por edad...]"
-- Note: Calculates current age dynamically using TIMESTAMPDIFF
-- ==============================================================================
CREATE OR REPLACE VIEW vw_reporte_pacientes_edades AS
SELECT 
    p.id_paciente,
    u.nombre,
    u.apellidos,
    p.fecha_nacimiento,
    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
    p.tipo_sangre,
    p.adeudo
FROM PACIENTES p
JOIN USUARIOS u ON p.id_usuario = u.id_usuario;


-- ==============================================================================
-- 3. INVENTORY REPORT (ACTIVE VS EXPIRED)
-- Rubric: "Reporte de inventario de medicamentos [activos y caducos...]"
-- ==============================================================================
CREATE OR REPLACE VIEW vw_reporte_inventario AS
SELECT 
    m.nombre_comercial,
    m.principio_activo,
    m.concentracion,
    m.precio_actual,
    i.cantidad_disponible,
    i.fecha_caducidad,
    em.estado AS estado_inventario,
    -- Custom flag to quickly identify expired stock
    CASE 
        WHEN i.fecha_caducidad < CURDATE() THEN 'Caducado'
        WHEN i.fecha_caducidad BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Próximo a caducar'
        ELSE 'Activo'
    END AS alerta_caducidad
FROM INVENTARIO i
JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
JOIN ESTADOS_MEDICAMENTOS em ON i.id_estado_medicamento = em.id_estado_medicamento;


-- ==============================================================================
-- 4. INCOME & FINANCIAL REPORT
-- Rubric: "Reporte de ingresos [por periodo...]"
-- Note: The frontend can filter this view simply by adding: 
-- "WHERE fecha_pago BETWEEN '2026-01-01' AND '2026-01-31'"
-- ==============================================================================
CREATE OR REPLACE VIEW vw_reporte_ingresos AS
SELECT 
    pg.id_pago,
    c.fecha_hora_inicio AS fecha_cita,
    pg.fecha_pago,
    u_paciente.nombre AS paciente_nombre,
    s.nombre AS servicio_cobrado,
    pg.monto_total,
    pg.estado AS estado_pago
FROM PAGOS pg
JOIN CITAS c ON pg.id_cita = c.id_cita
JOIN PACIENTES p ON c.id_paciente = p.id_paciente
JOIN USUARIOS u_paciente ON p.id_usuario = u_paciente.id_usuario
JOIN SERVICIOS s ON c.id_servicio = s.id_servicio;


-- ==============================================================================
-- 5. DISEASES & DIAGNOSIS REPORT
-- Rubric: "Reporte de enfermedades [por periodo...]"
-- ==============================================================================
CREATE OR REPLACE VIEW vw_reporte_enfermedades AS
SELECT 
    c.id_consulta,
    citas.fecha_hora_inicio AS fecha_consulta,
    c.motivo,
    c.diagnostico,
    u_medico.nombre AS medico_tratante,
    e.nombre AS especialidad
FROM CONSULTAS c
JOIN CITAS citas ON c.id_cita = citas.id_cita
JOIN MEDICOS m ON citas.id_medico = m.id_medico
JOIN USUARIOS u_medico ON m.id_usuario = u_medico.id_usuario
JOIN ESPECIALIDADES e ON m.id_especialidad = e.id_especialidad
WHERE c.diagnostico IS NOT NULL AND c.diagnostico != '';

-- ==============================================================================
-- END OF SCRIPT 06
-- ==============================================================================