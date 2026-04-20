-- ==============================================================================
-- DATABASE CREATION SCRIPT: Clinica Xocheco (File 01 of 07)
-- Purpose: Data Definition Language (DDL) - Tables, PKs, FKs, and Constraints
-- Engine: MySQL / MariaDB (InnoDB)
-- Author: Shaina Xochitiotzi
-- Version: 1.0
-- Date: 2026-04-20
-- ==============================================================================

-- Create the database
CREATE DATABASE IF NOT EXISTS clinica_xocheco
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE clinica_xocheco;

-- ==============================================================================
-- 1. LOOKUP TABLES & CATALOGS (No foreign keys dependencies)
-- ==============================================================================

-- Stores user roles for system access control (Admin, Médico, Recepción, Paciente, Farmacéutico)
CREATE TABLE TIPO_USUARIO (
    id_tipo_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Centralized address registry to prevent data duplication
CREATE TABLE DOMICILIO (
    id_domicilio INT AUTO_INCREMENT PRIMARY KEY,
    calle VARCHAR(100) NOT NULL,
    numero_exterior VARCHAR(20) NOT NULL,
    colonia VARCHAR(100) NOT NULL,
    codigo_postal VARCHAR(10) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    estado VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Medical specialties catalog
CREATE TABLE ESPECIALIDADES (
    id_especialidad INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Types of physical rooms (e.g., Consultorio, Quirófano, Laboratorio)
CREATE TABLE TIPOS_ESPACIOS_FISICOS (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Billable services and their associated costs and duration
CREATE TABLE SERVICIOS (
    id_servicio INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL COMMENT 'Cost in MXN',
    duracion_horas DECIMAL(5,2) NOT NULL COMMENT 'Duration in hours (e.g., 1.5)'
) ENGINE=InnoDB;

-- Base catalog for medications (abstract representation with current prices)
CREATE TABLE MEDICAMENTOS (
    id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
    nombre_comercial VARCHAR(100) NOT NULL,
    principio_activo VARCHAR(100) NOT NULL,
    presentacion VARCHAR(100) NOT NULL COMMENT 'E.g., Caja con 20 tabletas',
    concentracion VARCHAR(50) NOT NULL COMMENT 'E.g., 500mg',
    precio_actual DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio actual en MXN'
) ENGINE=InnoDB;

-- Medication inventory states (e.g., Disponible, Agotado, Próximo a caducar, Caducado)
CREATE TABLE ESTADOS_MEDICAMENTOS (
    id_estado_medicamento INT AUTO_INCREMENT PRIMARY KEY,
    estado VARCHAR(50) NOT NULL
) ENGINE=InnoDB;


-- ==============================================================================
-- 2. CORE ENTITIES (Users, Patients, Doctors, Spaces)
-- ==============================================================================

-- Master user table for authentication and basic profile data
CREATE TABLE USUARIOS (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_domicilio INT,
    id_tipo_usuario INT NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_domicilio) REFERENCES DOMICILIO(id_domicilio) ON DELETE SET NULL,
    FOREIGN KEY (id_tipo_usuario) REFERENCES TIPO_USUARIO(id_tipo_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Patient clinical profiles linked 1:1 with the User table
CREATE TABLE PACIENTES (
    id_paciente INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    fecha_nacimiento DATE NOT NULL,
    tipo_sangre VARCHAR(5),
    alergias TEXT,
    contacto_emergencia VARCHAR(150),
    adeudo BOOLEAN DEFAULT FALSE COMMENT 'Bandera para indicar si el paciente tiene adeudos pendientes',
    FOREIGN KEY (id_usuario) REFERENCES USUARIOS(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Doctor professional profiles linked 1:1 with the User table
CREATE TABLE MEDICOS (
    id_medico INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    id_especialidad INT NOT NULL,
    cedula_profesional VARCHAR(50) NOT NULL UNIQUE,
    universidad_origen VARCHAR(150),
    turno ENUM('matutino', 'vespertino', 'nocturno') NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES USUARIOS(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_especialidad) REFERENCES ESPECIALIDADES(id_especialidad) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Physical rooms inside the clinic mapped to their types
CREATE TABLE ESPACIOS_FISICOS (
    id_espacio INT AUTO_INCREMENT PRIMARY KEY,
    piso INT NOT NULL,
    numero INT NOT NULL,
    nombre VARCHAR(50),
    id_tipo INT NOT NULL,
    FOREIGN KEY (id_tipo) REFERENCES TIPOS_ESPACIOS_FISICOS(id_tipo) ON DELETE RESTRICT
) ENGINE=InnoDB;


-- ==============================================================================
-- 3. TRANSACTIONAL ENTITIES (Appointments, Consultations, Pharmacy, Payments)
-- ==============================================================================

-- Core scheduling table handling resources (Patient, Doctor, Room, Service)
CREATE TABLE CITAS (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    id_paciente INT NOT NULL,
    id_medico INT NOT NULL,
    id_espacio INT NOT NULL,
    id_servicio INT NOT NULL,
    fecha_hora_inicio DATETIME NOT NULL,
    estado ENUM('Programada', 'Confirmada', 'Cancelada', 'Completada') DEFAULT 'Programada',
    FOREIGN KEY (id_paciente) REFERENCES PACIENTES(id_paciente) ON DELETE RESTRICT,
    FOREIGN KEY (id_medico) REFERENCES MEDICOS(id_medico) ON DELETE RESTRICT,
    FOREIGN KEY (id_espacio) REFERENCES ESPACIOS_FISICOS(id_espacio) ON DELETE RESTRICT,
    FOREIGN KEY (id_servicio) REFERENCES SERVICIOS(id_servicio) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Clinical record resulting from a completed appointment
CREATE TABLE CONSULTAS (
    id_consulta INT AUTO_INCREMENT PRIMARY KEY,
    id_cita INT NOT NULL UNIQUE,
    motivo TEXT NOT NULL,
    diagnostico TEXT,
    observaciones TEXT,
    FOREIGN KEY (id_cita) REFERENCES CITAS(id_cita) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Prescription header linked to a consultation
CREATE TABLE RECETAS (
    id_receta INT AUTO_INCREMENT PRIMARY KEY,
    id_consulta INT NOT NULL,
    fecha_emision DATETIME DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    FOREIGN KEY (id_consulta) REFERENCES CONSULTAS(id_consulta) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Many-to-Many resolution table for prescriptions and medications
CREATE TABLE DETALLE_RECETA (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_receta INT NOT NULL,
    id_medicamento INT NOT NULL,
    dosis VARCHAR(100) NOT NULL,
    frecuencia VARCHAR(100) NOT NULL,
    duracion VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_receta) REFERENCES RECETAS(id_receta) ON DELETE CASCADE,
    FOREIGN KEY (id_medicamento) REFERENCES MEDICAMENTOS(id_medicamento) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Physical lots of medications in the pharmacy mapped to states
CREATE TABLE INVENTARIO (
    id_lote INT AUTO_INCREMENT PRIMARY KEY,
    id_medicamento INT NOT NULL,
    cantidad_disponible INT NOT NULL DEFAULT 0,
    fecha_caducidad DATE NOT NULL,
    fecha_ingreso DATE NOT NULL,
    id_estado_medicamento INT NOT NULL,
    FOREIGN KEY (id_medicamento) REFERENCES MEDICAMENTOS(id_medicamento) ON DELETE RESTRICT,
    FOREIGN KEY (id_estado_medicamento) REFERENCES ESTADOS_MEDICAMENTOS(id_estado_medicamento) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Financial tracking for services provided during appointments
CREATE TABLE PAGOS (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_cita INT NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL,
    fecha_pago DATETIME,
    estado ENUM('Pendiente', 'Pagado') DEFAULT 'Pendiente',
    FOREIGN KEY (id_cita) REFERENCES CITAS(id_cita) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Line items for a payment (receipt details) to track exactly what was charged
CREATE TABLE DETALLE_PAGO (
    id_detalle_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT NOT NULL,
    descripcion_concepto VARCHAR(150) NOT NULL COMMENT 'E.g., Consulta Cardiologia, Paracetamol 500mg',
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pago) REFERENCES PAGOS(id_pago) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Immutable audit log for security and system compliance (Bitácora)
CREATE TABLE BITACORA (
    id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    accion VARCHAR(50) NOT NULL,
    tabla_afectada VARCHAR(50) NOT NULL,
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES USUARIOS(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ==============================================================================
-- END OF 01_DB_Structure
-- ==============================================================================