<?php
function inventoryNormalizeKey(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return strtolower($ascii !== false ? $ascii : $value);
}

function inventoryStateCatalog(mysqli $conn): array
{
    $needed = ['Disponible', 'Proximo a caducar', 'Caducado', 'Agotado'];
    $map = [];

    $result = $conn->query('SELECT id_estado_medicamento, estado FROM ESTADOS_MEDICAMENTOS');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[inventoryNormalizeKey($row['estado'])] = (int)$row['id_estado_medicamento'];
        }
    }

    $stmt = $conn->prepare('INSERT INTO ESTADOS_MEDICAMENTOS (estado) VALUES (?)');
    if ($stmt) {
        foreach ($needed as $state) {
            $key = inventoryNormalizeKey($state);
            if (!isset($map[$key])) {
                $stmt->bind_param('s', $state);
                $stmt->execute();
                $map[$key] = (int)$conn->insert_id;
            }
        }
    }

    return $map;
}

function inventoryDetermineState(string $fechaCaducidad, int $cantidad): string
{
    $today = new DateTimeImmutable('today');
    $expiry = new DateTimeImmutable($fechaCaducidad);

    if ($cantidad <= 0) {
        return 'Agotado';
    }

    if ($expiry < $today) {
        return 'Caducado';
    }

    if ($expiry <= $today->modify('+30 days')) {
        return 'Proximo a caducar';
    }

    return 'Disponible';
}

function inventorySyncStates(mysqli $conn): void
{
    $states = inventoryStateCatalog($conn);
    $stmt = $conn->prepare('SELECT id_lote, cantidad_disponible, fecha_caducidad FROM INVENTARIO');
    if (!$stmt) {
        return;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        return;
    }

    $update = $conn->prepare('UPDATE INVENTARIO SET id_estado_medicamento = ? WHERE id_lote = ?');
    if (!$update) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $stateName = inventoryDetermineState($row['fecha_caducidad'], (int)$row['cantidad_disponible']);
        $stateId = $states[inventoryNormalizeKey($stateName)] ?? null;
        if ($stateId) {
            $lotId = (int)$row['id_lote'];
            $update->bind_param('ii', $stateId, $lotId);
            $update->execute();
        }
    }
}

function inventoryMedicationExists(mysqli $conn, string $nombre, string $concentracion): ?array
{
    $stmt = $conn->prepare(
        'SELECT id_medicamento, nombre_comercial, principio_activo, presentacion, concentracion, precio_actual
         FROM MEDICAMENTOS
         WHERE LOWER(nombre_comercial) = LOWER(?) AND LOWER(concentracion) = LOWER(?)
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $nombre, $concentracion);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function inventoryCreateMedication(mysqli $conn, array $data): int
{
    $stmt = $conn->prepare(
        'INSERT INTO MEDICAMENTOS (nombre_comercial, principio_activo, presentacion, concentracion, precio_actual)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param(
        'ssssd',
        $data['nombre_comercial'],
        $data['principio_activo'],
        $data['presentacion'],
        $data['concentracion'],
        $data['precio_actual']
    );
    if (!$stmt->execute()) {
        return 0;
    }

    return (int)$conn->insert_id;
}

function inventoryFetchMedicationList(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query('SELECT id_medicamento, nombre_comercial, concentracion FROM MEDICAMENTOS ORDER BY nombre_comercial, concentracion');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function inventoryFetchStateList(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query('SELECT id_estado_medicamento, estado FROM ESTADOS_MEDICAMENTOS ORDER BY estado');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function inventoryGetStateChip(string $state): string
{
    $state = inventoryNormalizeKey($state);
    if ($state === inventoryNormalizeKey('Caducado')) {
        return 'chip-red';
    }
    if ($state === inventoryNormalizeKey('Proximo a caducar')) {
        return 'chip-amber';
    }
    if ($state === inventoryNormalizeKey('Agotado')) {
        return 'chip-red';
    }
    return 'chip-green';
}
