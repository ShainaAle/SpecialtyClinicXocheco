<?php
function auditLog(mysqli $conn, string $table, string $action): bool
{
    $userId = (int)($_SESSION['id_usuario'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('INSERT INTO BITACORA (id_usuario, accion, tabla_afectada) VALUES (?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iss', $userId, $action, $table);
    return $stmt->execute();
}
