<?php
/**
 * auth.php
 * Incluir al inicio de cualquier página protegida.
 *
 * Uso:
 *   require_once '../../src/auth.php';
 *   requireRol(['admin', 'recepcion']);
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica que haya sesión activa.
 * Si no la hay, redirige al login.
 */
function requireLogin(): void {
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: /signin.php");
        exit();
    }
}

/**
 * Verifica que el rol del usuario esté en la lista permitida.
 * Si no lo está, devuelve 403 o redirige al dashboard propio.
 *
 * @param string[] $roles_permitidos  Ej: ['admin', 'recepcion']
 */
function requireRol(array $roles_permitidos): void {
    requireLogin();
    if (!in_array($_SESSION['rol'], $roles_permitidos, true)) {
        http_response_code(403);
        // Redirigir al dashboard correcto en lugar de mostrar error genérico
        $destinos = [
            'admin'        => '/admin/dashboard.php',
            'medico'       => '/medico/dashboard.php',
            'recepcion'    => '/recepcion/dashboard.php',
            'paciente'     => '/paciente/dashboard.php',
            'farmaceutico' => '/farmacia/dashboard.php',
        ];
        $destino = $destinos[$_SESSION['rol']] ?? '/index.php';
        header("Location: $destino");
        exit();
    }
}

/**
 * Devuelve true/false para condicionales en vistas (mostrar/ocultar elementos).
 *
 * @param string|string[] $roles
 */
function esRol($roles): bool {
    if (!isset($_SESSION['rol'])) return false;
    $roles = is_array($roles) ? $roles : [$roles];
    return in_array($_SESSION['rol'], $roles, true);
}
?>