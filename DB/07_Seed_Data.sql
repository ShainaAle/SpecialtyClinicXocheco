-- ==============================================================================
-- DATABASE CREATION SCRIPT: Clinica Xocheco (File 07 of 07)
-- Purpose: Seed Data (Initial dummy data to test the system and views)
-- Engine: MySQL / MariaDB
-- ==============================================================================

USE clinica_xocheco;

-- Disable foreign key checks temporarily to allow clean inserts
SET FOREIGN_KEY_CHECKS = 0;

-- ==============================================================================
-- 1. BASE CATALOGS
-- ==============================================================================

INSERT INTO TIPO_USUARIO (id_tipo_usuario, nombre_rol) VALUES 
(1, 'Administrador'), 
(2, 'Médico'), 
(3, 'Recepción'), 
(4, 'Paciente'),
(5, 'Farmacéutico');

INSERT INTO ESPECIALIDADES (id_especialidad, nombre) VALUES 
(1, 'Medicina General'),
(2, 'Cardiología'),
(3, 'Pediatría'),
(4, 'Ginecología');

INSERT INTO TIPOS_ESPACIOS_FISICOS (id_tipo, tipo) VALUES 
(1, 'Consultorio'),
(2, 'Quirófano'),
(3, 'Laboratorio'),
(4, 'Farmacia');

INSERT INTO ESPACIOS_FISICOS (id_espacio, piso, numero, nombre, id_tipo) VALUES 
(1, 1, 101, 'Consultorio A', 1),
(2, 1, 102, 'Consultorio B', 1),
(3, 2, 201, 'Quirófano Principal', 2),
(4, 1, 100, 'Farmacia Central', 4);

INSERT INTO SERVICIOS (id_servicio, nombre, descripcion, precio, duracion_horas) VALUES 
(1, 'Consulta General', 'Revisión médica de rutina', 500.00, 0.50),
(2, 'Consulta Especialidad', 'Revisión con médico especialista', 800.00, 1.00),
(3, 'Electrocardiograma', 'Estudio del corazón', 1200.00, 1.50);

INSERT INTO ESTADOS_MEDICAMENTOS (id_estado_medicamento, estado) VALUES 
(1, 'Disponible'),
(2, 'Agotado'),
(3, 'Próximo a caducar'),
(4, 'Caducado');

INSERT INTO MEDICAMENTOS (id_medicamento, nombre_comercial, principio_activo, presentacion, concentracion, precio_actual) VALUES 
(1, 'Tempra', 'Paracetamol', 'Caja con 20 tabletas', '500mg', 55.00),
(2, 'Aspirina Protect', 'Ácido Acetilsalicílico', 'Caja con 28 tabletas', '100mg', 120.00),
(3, 'Amoxil', 'Amoxicilina', 'Caja con 12 cápsulas', '500mg', 250.00);

-- ==============================================================================
-- 2. USERS & PROFILES
-- ==============================================================================

INSERT INTO DOMICILIO (id_domicilio, calle, numero_exterior, colonia, codigo_postal, ciudad, estado) VALUES 
(1, 'Av. 5 de Febrero', '123', 'Centro', '76000', 'Querétaro', 'Querétaro'),
(2, 'Bernardo Quintana', '456', 'Álamos', '76160', 'Querétaro', 'Querétaro');

-- Dummy users (Passwords should be hashed in production!)
INSERT INTO USUARIOS (id_usuario, id_domicilio, id_tipo_usuario, nombre, apellidos, correo, password_hash) VALUES 
(1, 1, 1, 'Carlos', 'Admin', 'admin@xocheco.com', 'hash123'),
(2, 1, 2, 'Roberto', 'García', 'dr.roberto@xocheco.com', 'hash123'),
(3, 1, 3, 'Ana', 'López', 'recepcion@xocheco.com', 'hash123'),
(4, 2, 4, 'María', 'Fernández', 'maria.f@gmail.com', 'hash123'),
(5, 1, 5, 'Pedro', 'Farmacia', 'farmacia@xocheco.com', 'hash123');

INSERT INTO MEDICOS (id_medico, id_usuario, id_especialidad, cedula_profesional, universidad_origen, turno) VALUES 
(1, 2, 2, 'CED-98765432', 'UNAM', 'matutino');

INSERT INTO PACIENTES (id_paciente, id_usuario, fecha_nacimiento, tipo_sangre, alergias, contacto_emergencia, adeudo) VALUES 
(1, 4, '1990-05-15', 'O+', 'Penicilina', 'Juan Fernández - 4421234567', 0);

-- ==============================================================================
-- 3. INVENTORY & TRANSACTIONS (To test views)
-- ==============================================================================

-- Insert active inventory
INSERT INTO INVENTARIO (id_lote, id_medicamento, cantidad_disponible, fecha_caducidad, fecha_ingreso, id_estado_medicamento) VALUES 
(1, 1, 50, '2027-12-31', '2025-01-01', 1),
(2, 2, 30, '2026-06-30', '2025-01-01', 1);

-- Schedule a completed appointment
INSERT INTO CITAS (id_cita, id_paciente, id_medico, id_espacio, id_servicio, fecha_hora_inicio, estado) VALUES 
(1, 1, 1, 1, 2, '2026-04-20 10:00:00', 'Completada');

-- Clinical Consultation and Prescription
INSERT INTO CONSULTAS (id_consulta, id_cita, motivo, diagnostico, observaciones) VALUES 
(1, 1, 'Dolor de pecho', 'Arritmia leve', 'Reposo y medicación');

INSERT INTO RECETAS (id_receta, id_consulta, fecha_emision, observaciones) VALUES 
(1, 1, '2026-04-20 10:30:00', 'Tomar con alimentos');

INSERT INTO DETALLE_RECETA (id_detalle, id_receta, id_medicamento, dosis, frecuencia, duracion) VALUES 
(1, 1, 2, '1 tableta', 'Cada 24 horas', '30 días');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- END OF SCRIPT 07
-- ==============================================================================