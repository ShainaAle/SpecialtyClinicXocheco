<?php
function ensurePrescriptionTrackingTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS RECETAS_SURTIDO (
            id_receta INT NOT NULL PRIMARY KEY,
            codigo_receta VARCHAR(30) NOT NULL UNIQUE,
            estado_surtido ENUM('Pendiente', 'Surtida') NOT NULL DEFAULT 'Pendiente',
            fecha_surtido DATETIME NULL,
            id_usuario_surtio INT NULL,
            FOREIGN KEY (id_receta) REFERENCES RECETAS(id_receta) ON DELETE CASCADE,
            FOREIGN KEY (id_usuario_surtio) REFERENCES USUARIOS(id_usuario) ON DELETE SET NULL
        ) ENGINE=InnoDB"
    );
}

function generatePrescriptionCode(int $idReceta, ?string $fechaEmision = null): string
{
    $datePart = $fechaEmision ? date('Ymd', strtotime($fechaEmision)) : date('Ymd');
    return 'RX-' . $datePart . '-' . str_pad((string)$idReceta, 6, '0', STR_PAD_LEFT);
}

function ensurePrescriptionTrackingRows(mysqli $conn, array $recipes): void
{
    ensurePrescriptionTrackingTable($conn);

    $stmt = $conn->prepare(
        "INSERT INTO RECETAS_SURTIDO (id_receta, codigo_receta, estado_surtido, fecha_surtido, id_usuario_surtio)
         VALUES (?, ?, 'Pendiente', NULL, NULL)
         ON DUPLICATE KEY UPDATE codigo_receta = VALUES(codigo_receta)"
    );

    if (!$stmt) {
        return;
    }

    foreach ($recipes as $recipe) {
        $idReceta = (int)($recipe['id_receta'] ?? 0);
        if ($idReceta <= 0) {
            continue;
        }

        $codigo = generatePrescriptionCode($idReceta, $recipe['fecha_emision'] ?? null);
        $stmt->bind_param('is', $idReceta, $codigo);
        $stmt->execute();
    }
}

function getPrescriptionTrackingMap(mysqli $conn, array $recipeIds): array
{
    ensurePrescriptionTrackingTable($conn);
    $recipeIds = array_values(array_unique(array_filter(array_map('intval', $recipeIds), static fn ($value) => $value > 0)));
    if (!$recipeIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($recipeIds), '?'));
    $types = str_repeat('i', count($recipeIds));
    $stmt = $conn->prepare(
        "SELECT id_receta, codigo_receta, estado_surtido, fecha_surtido, id_usuario_surtio
         FROM RECETAS_SURTIDO
         WHERE id_receta IN ({$placeholders})"
    );
    if (!$stmt) {
        return [];
    }

    $bind = [$types];
    foreach ($recipeIds as &$value) {
        $bind[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[(int)$row['id_receta']] = $row;
        }
    }
    return $map;
}

function supplyPrescription(mysqli $conn, int $idReceta, int $userId): bool
{
    ensurePrescriptionTrackingTable($conn);
    $stmt = $conn->prepare(
        "UPDATE RECETAS_SURTIDO
         SET estado_surtido = 'Surtida', fecha_surtido = NOW(), id_usuario_surtio = ?
         WHERE id_receta = ? AND estado_surtido = 'Pendiente'"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $userId, $idReceta);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

