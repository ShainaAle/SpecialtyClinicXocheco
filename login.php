<?php
session_start();
require 'src/conexion/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: signin.php");
    exit();
}

$correo   = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($correo) || empty($password)) {
    header("Location: signin.php?error=1");
    exit();
}

// ── Buscar usuario activo con su rol ──────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT u.id_usuario, u.nombre, u.apellidos, u.password_hash,
           t.id_tipo_usuario, t.nombre_rol
    FROM USUARIOS u
    JOIN TIPO_USUARIO t ON u.id_tipo_usuario = t.id_tipo_usuario
    WHERE u.correo = ?
    LIMIT 1
");
$stmt->bind_param("s", $correo);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    header("Location: signin.php?error=1");
    exit();
}

$usuario = $resultado->fetch_assoc();

// ── Verificar contraseña ──────────────────────────────────────────────────────
// Producción: password_verify($password, $usuario['password_hash'])
// Temporal (sin hash): comparación directa
if ($password !== $usuario['password_hash']) {
    header("Location: signin.php?error=1");
    exit();
}

// ── Guardar sesión ────────────────────────────────────────────────────────────
$_SESSION['id_usuario']      = $usuario['id_usuario'];
$_SESSION['nombre_completo'] = $usuario['nombre'] . ' ' . $usuario['apellidos'];
$_SESSION['id_tipo_usuario'] = $usuario['id_tipo_usuario'];

// Normalizar rol a clave corta para uso en PHP/HTML
$roles_map = [
    'Administrador' => 'admin',
    'Médico'        => 'medico',
    'Recepción'     => 'recepcion',
    'Paciente'      => 'paciente',
    'Farmacéutico'  => 'farmaceutico',
];
$_SESSION['rol']      = $roles_map[$usuario['nombre_rol']] ?? 'paciente';
$_SESSION['nombre_rol'] = $usuario['nombre_rol'];

// ── Establecer @current_user_id para triggers de auditoría ───────────────────
$conn->query("SET @current_user_id = " . (int)$usuario['id_usuario']);

// ── Registrar en bitácora ─────────────────────────────────────────────────────
$id = (int)$usuario['id_usuario'];
$log = $conn->prepare("
    INSERT INTO BITACORA (id_usuario, accion, tabla_afectada)
    VALUES (?, 'LOGIN', 'USUARIOS')
");
$log->bind_param("i", $id);
$log->execute();

// ── Redirigir según rol ───────────────────────────────────────────────────────
$destinos = [
    'admin'        => 'admin/dashboard.php',
    'medico'       => 'medico/dashboard.php',
    'recepcion'    => 'recepcion/dashboard.php',
    'paciente'     => 'paciente/dashboard.php',
    'farmaceutico' => 'farmacia/dashboard.php',
];

header("Location: " . ($destinos[$_SESSION['rol']] ?? 'index.php'));
exit();
?>