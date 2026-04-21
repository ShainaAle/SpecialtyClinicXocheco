# SpecialtyClinicXocheco 🏥

Welcome to the database repository for the **Xocheco Specialty Clinic**.

To ensure excellent documentation, modularity, and maintainability, this SQL project is divided into **7 independent files**. Each file has a specific purpose and focuses on different aspects of the database architecture (DDL, DCL, Business Logic, Auditing, etc.).

## 🚀 Execution Order

**Important:** The scripts must be executed in the exact numbered order (01 to 07) to respect foreign key dependencies and proper logic flow.

### 📁 01_DB_Structure.sql (DDL - Data Definition Language)

* **Purpose:** Core database and table creation (`CREATE TABLE`).

* **Content:** Definition of Primary Keys (PK), Foreign Keys (FK) with `InnoDB` referential integrity, data types, and structural constraints (`NOT NULL`, `UNIQUE`, `DEFAULT`).

### 📁 02_Indices.sql (Optimization)

* **Purpose:** Query performance optimization.

* **Content:** Creation of indexes (`CREATE INDEX`) on columns frequently used in `WHERE` or `JOIN` clauses (e.g., appointment dates, user emails, inventory expiration dates) to prevent full table scans.

### 📁 03_Roles_Seguridad.sql (DCL - Data Control Language)

* **Purpose:** Define database engine-level permissions, enforcing strict Role-Based Access Control (RBAC).

* **Content:** \* `CREATE ROLE` (e.g., `rol_medico`, `rol_recepcion`, `rol_admin`).

  * `GRANT` statements (e.g., Granting read-only `SELECT` access to receptionists for certain tables, while granting full `INSERT, UPDATE, DELETE` permissions to the administrator and restricting inventory modifications exclusively to pharmacists).

### 📁 04_Procedimientos_Transacciones.sql (Business Logic)

* **Purpose:** Encapsulate complex clinical processes requiring multiple steps into atomic operations.

* **Content:** `CREATE PROCEDURE`. Implementation of explicit **Transactions**, `COMMIT`, and `ROLLBACK` commands.

  * *Example:* A secure scheduling procedure (`sp_agendar_cita_segura`) that verifies room availability, checks for patient debts, and generates the initial invoice. If everything is valid, it saves the data (`COMMIT`). If any validation fails, it cancels the entire operation to prevent data corruption (`ROLLBACK`).

### 📁 05_Triggers_Auditoria.sql (Automation & Auditing)

* **Purpose:** Automatically react to specific database events without backend intervention.

* **Content:** `CREATE TRIGGER`.

  * *Example 1 (Audit):* A trigger that logs a record into the `BITACORA` (Audit Log) table whenever an appointment's status is updated.

  * *Example 2 (Automation):* A trigger that automatically deducts the available quantity from the `INVENTARIO` (Inventory) when a prescription detail is inserted.

### 📁 06_Vistas_Reportes.sql (Predefined Queries)

* **Purpose:** Facilitate data extraction for the frontend's "Reports" module.

* **Content:** `CREATE VIEW`.

  * *Example:* Pre-compiled views such as Monthly Income, Expired Inventory, or Patient Demographics, allowing the web application to fetch complex data without building heavy `JOIN`s in the backend.

### 📁 07_Datos_Prueba.sql (Seed Data)

* **Purpose:** Populate the database with initial dummy information to test the system immediately after deployment.

* **Content:** `INSERT INTO` statements containing base catalogs (specialties, physical spaces, services) and dummy user accounts for all roles.

## 🛠️ Installation / Usage

1. Clone this repository.

2. Open your preferred SQL client (e.g., MySQL Workbench, DBeaver, or phpMyAdmin).

3. Execute the scripts in sequential order from `01` to `07`.