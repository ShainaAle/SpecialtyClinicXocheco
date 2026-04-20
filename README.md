# SpecialtyClinicXocheco


Estructura del Proyecto de Base de Datos - Clínica Xocheco

Para garantizar una excelente documentación y modularidad, el proyecto SQL se dividirá en los siguientes 7 archivos independientes. Cada archivo tiene un propósito específico y debe ejecutarse en el orden numerado para respetar las dependencias.

📁 01_DB_Structure.sql (DDL - Data Definition Language)

Propósito: Creación de la base de datos y todas las tablas (CREATE TABLE).

Contenido: Definición de claves primarias (PK), claves foráneas (FK), tipos de datos y restricciones de integridad (NOT NULL, UNIQUE, DEFAULT).

📁 02_Indices.sql (Optimización)

Propósito: Mejorar el rendimiento de las consultas.

Contenido: Creación de índices (CREATE INDEX) en las columnas que más se usarán en los bloques WHERE o JOIN (ej. fechas de citas, correos de usuarios, caducidades de inventario).

📁 03_Roles_Seguridad.sql (DCL - Data Control Language)

Propósito: Definir los permisos a nivel de motor de base de datos, separando quién puede hacer qué.

Contenido: * CREATE ROLE (ej. rol_medico, rol_recepcion, rol_admin).

GRANT (ej. Dar permiso de solo lectura (SELECT) a recepción sobre ciertas tablas, pero permisos completos (INSERT, UPDATE, DELETE) al administrador).

📁 04_Procedimientos_Transacciones.sql (Lógica de Negocio)

Propósito: Encapsular procesos complejos de la clínica que requieran múltiples pasos.

Contenido: CREATE PROCEDURE. Aquí implementaremos los Rollbacks explícitos y Commits.

Ejemplo: Un procedimiento AgendarCita() que primero verifique si hay espacio, luego revise si el paciente tiene adeudos, y si todo está bien, inserte la cita (COMMIT). Si algo falla a la mitad, se cancela todo (ROLLBACK).

📁 05_Triggers_Auditoria.sql (Automatización)

Propósito: Reaccionar automáticamente a eventos en la base de datos.

Contenido: CREATE TRIGGER.

Ejemplo 1: Un trigger que inserte un registro en la tabla BITACORA cada vez que alguien elimine una cita.

Ejemplo 2: Un trigger que reste la cantidad_disponible del INVENTARIO cuando se inserte un DETALLE_RECETA.

📁 06_Vistas_Reportes.sql (Consultas Predefinidas)

Propósito: Facilitar la extracción de datos para el módulo de "Reportes" que pide tu rúbrica.

Contenido: CREATE VIEW.

Ejemplo: Una vista Vista_Ingresos_Mensuales o Vista_Inventario_Caducado que tu aplicación web pueda consultar fácilmente sin hacer JOINs complejos en el backend.

📁 07_Datos_Prueba.sql (Seed Data)

Propósito: Poblar la base de datos con información inicial para poder probar el sistema inmediatamente.

Contenido: Sentencias INSERT INTO con catálogos base (tipos de sangre, servicios) y usuarios de prueba.