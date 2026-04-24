<?php
require_once '../src/auth.php';
requireRol(['farmaceutico']);
require_once '../src/conexion/conexion.php';

$basePath = '..';
$pageTitle = 'Farmacia';
$pageSubtitle = 'Revisa stock, caducidades y movimientos del área farmacéutica.';
$activeModule = 'dashboard';
$portalLabel = 'Farmacia';
$portalRole = 'Farmacéutico';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'farmacia/dashboard.php'],
    ['key' => 'inventario', 'label' => 'Inventario', 'href' => 'farmacia/inventario/'],
    ['key' => 'recetas', 'label' => 'Recetas', 'href' => 'farmacia/recetas/'],
];

function farmaciaRows(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

$inventory = farmaciaRows(
    $conn,
    "SELECT m.nombre_comercial, i.cantidad_disponible, i.fecha_caducidad, e.estado
     FROM INVENTARIO i
     INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
     INNER JOIN ESTADOS_MEDICAMENTOS e ON i.id_estado_medicamento = e.id_estado_medicamento
     ORDER BY i.fecha_caducidad ASC
     LIMIT 6"
);

$lowStockItems = farmaciaRows(
    $conn,
    "SELECT m.nombre_comercial, i.cantidad_disponible
     FROM INVENTARIO i
     INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
     WHERE i.cantidad_disponible <= 10
     ORDER BY i.cantidad_disponible ASC, m.nombre_comercial ASC
     LIMIT 5"
);

$expiringSoonItems = farmaciaRows(
    $conn,
    "SELECT m.nombre_comercial, i.fecha_caducidad
     FROM INVENTARIO i
     INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
     WHERE i.fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY i.fecha_caducidad ASC, m.nombre_comercial ASC
     LIMIT 5"
);

$stats = [
    ['label' => 'Lotes', 'value' => count($inventory), 'note' => 'Visibles', 'accent' => 'blue'],
    ['label' => 'Stock bajo', 'value' => count($lowStockItems), 'note' => 'Revisar pronto', 'accent' => 'sand'],
    ['label' => 'Caducan', 'value' => count($expiringSoonItems), 'note' => 'En 30 días', 'accent' => 'blue'],
    ['label' => 'Estado', 'value' => 1, 'note' => 'Farmacia activa', 'accent' => 'sand'],
];

include '../src/portal/header.php';
?>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card <?php echo $stat['accent'] === 'blue' ? 'accent-blue' : 'accent-sand'; ?>">
                <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                <div class="stat-value"><?php echo (int)$stat['value']; ?></div>
                <div class="stat-note"><?php echo htmlspecialchars($stat['note']); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="panel-card mb-4">
    <div class="panel-head">
        <h2 class="section-title">Alertas de inventario</h2>
        <p class="section-subtitle">Medicamentos que conviene atender hoy.</p>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="mini-card h-100">
                    <strong class="d-block mb-3">Stock bajo</strong>
                    <?php if ($lowStockItems): ?>
                        <div class="d-grid gap-2">
                            <?php foreach ($lowStockItems as $item): ?>
                                <div class="d-flex justify-content-between gap-2">
                                    <span><?php echo htmlspecialchars($item['nombre_comercial']); ?></span>
                                    <span class="chip chip-red"><?php echo (int)$item['cantidad_disponible']; ?> uds</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted-soft">Sin alertas de stock bajo.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mini-card h-100">
                    <strong class="d-block mb-3">Próximos a caducar</strong>
                    <?php if ($expiringSoonItems): ?>
                        <div class="d-grid gap-2">
                            <?php foreach ($expiringSoonItems as $item): ?>
                                <div class="d-flex justify-content-between gap-2">
                                    <span><?php echo htmlspecialchars($item['nombre_comercial']); ?></span>
                                    <span class="chip <?php echo strtotime($item['fecha_caducidad']) < strtotime(date('Y-m-d')) ? 'chip-red' : 'chip-amber'; ?>">
                                        <?php echo date('d/m/Y', strtotime($item['fecha_caducidad'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted-soft">Sin alertas de caducidad cercana.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="panel-card">
    <div class="panel-head">
        <h2 class="section-title">Inventario reciente</h2>
        <p class="section-subtitle">Vista rápida del stock más próximo a revisarse.</p>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Medicamento</th>
                        <th>Stock</th>
                        <th>Caducidad</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['nombre_comercial']); ?></td>
                            <td><?php echo (int)$item['cantidad_disponible']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($item['fecha_caducidad'])); ?></td>
                            <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$inventory): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No hay registros de inventario.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel-card mt-4">
    <div class="panel-head">
        <h2 class="section-title">Accesos rápidos</h2>
        <p class="section-subtitle">Lo que más vas a usar en farmacia.</p>
    </div>
    <div class="panel-body">
        <div class="mini-grid">
            <a class="mini-card text-decoration-none text-reset" href="inventario/">
                <strong class="d-block mb-1">Inventario</strong>
                <span class="text-muted-soft small">Stock y caducidades.</span>
            </a>
            <a class="mini-card text-decoration-none text-reset" href="recetas/">
                <strong class="d-block mb-1">Recetas</strong>
                <span class="text-muted-soft small">Prescripciones emitidas.</span>
            </a>
        </div>
    </div>
</div>

<?php include '../src/admin/footer.php'; ?>
