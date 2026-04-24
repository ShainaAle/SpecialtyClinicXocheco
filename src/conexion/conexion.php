<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "clinica_xocheco"; // nombre exacto de la base

$conn = new mysqli($host, $user, $password, $db);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Opcional: establecer charset
$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['id_usuario'])) {
    $conn->query('SET @current_user_id = ' . (int)$_SESSION['id_usuario']);
}
