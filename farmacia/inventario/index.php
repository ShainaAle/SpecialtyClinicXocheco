<?php
require_once '../../src/auth.php';
requireRol(['farmaceutico']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';

$basePath = '../..';
$pageTitle = 'Inventario';
$pageSubtitle = 'Alta y actualización del stock farmacéutico.';
$activeModule = 'inventario';
$portalLabel = 'Farmacia';
$portalRole = 'Farmacéutico';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'farmacia/dashboard.php'],
    ['key' => 'inventario', 'label' => 'Inventario', 'href' => 'farmacia/inventario/'],
    ['key' => 'recetas', 'label' => 'Recetas', 'href' => 'farmacia/recetas/'],
];

function inventoryRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $bind = [$types];
        foreach ($params as &$value) {
            $bind[] = &$value;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

$message = '';
$error = '';
$search = trim($_GET['q'] ?? '');
$stateFilter = trim($_GET['estado'] ?? '');
$sort = $_GET['sort'] ?? 'stock';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$medications = inventoryRows($conn, 'SELECT id_medicamento, nombre_comercial FROM MEDICAMENTOS ORDER BY nombre_comercial');
$states = inventoryRows($conn, 'SELECT id_estado_medicamento, estado FROM ESTADOS_MEDICAMENTOS ORDER BY estado');

$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $rows = inventoryRows(
        $conn,
        "SELECT i.id_lote, i.id_medicamento, i.cantidad_disponible, i.fecha_caducidad, i.fecha_ingreso, i.id_estado_medicamento,
                m.nombre_comercial
         FROM INVENTARIO i
         INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
         WHERE i.id_lote = ?",
        'i',
        [$editId]
    );
    $editing = $rows[0] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lotId = (int)($_POST['id_lote'] ?? 0);
    $idMedicamento = (int)($_POST['id_medicamento'] ?? 0);
    $cantidad = (int)($_POST['cantidad_disponible'] ?? 0);
    $fechaCaducidad = trim($_POST['fecha_caducidad'] ?? '');
    $fechaIngreso = trim($_POST['fecha_ingreso'] ?? '');
    $idEstado = (int)($_POST['id_estado_medicamento'] ?? 0);

    if ($idMedicamento <= 0 || $cantidad < 0 || $fechaCaducidad === '' || $fechaIngreso === '' || $idEstado <= 0) {
        $error = 'Completa todos los campos.';
    } elseif ($lotId > 0) {
        $stmt = $conn->prepare('UPDATE INVENTARIO SET id_medicamento = ?, cantidad_disponible = ?, fecha_caducidad = ?, fecha_ingreso = ?, id_estado_medicamento = ? WHERE id_lote = ?');
        $stmt->bind_param('iissii', $idMedicamento, $cantidad, $fechaCaducidad, $fechaIngreso, $idEstado, $lotId);
        if ($stmt->execute()) {
            $message = 'Lote actualizado.';
            auditLog($conn, 'INVENTARIO', 'ACTUALIZAR lote #' . $lotId);
        } else {
            $error = 'No se pudo actualizar.';
        }
    } else {
        $stmt = $conn->prepare('INSERT INTO INVENTARIO (id_medicamento, cantidad_disponible, fecha_caducidad, fecha_ingreso, id_estado_medicamento) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iissi', $idMedicamento, $cantidad, $fechaCaducidad, $fechaIngreso, $idEstado);
        if ($stmt->execute()) {
            $message = 'Lote agregado.';
            auditLog($conn, 'INVENTARIO', 'INSERTAR lote #' . (int)$conn->insert_id);
        } else {
            $error = 'No se pudo guardar.';
        }
    }
}

$sortMap = [
    'stock' => 'i.cantidad_disponible',
    'name' => 'm.nombre_comercial',
    'expiry' => 'i.fecha_caducidad',
    'state' => 'e.estado',
];
$sortField = $sortMap[$sort] ?? 'i.cantidad_disponible';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(m.nombre_comercial LIKE ? OR m.principio_activo LIKE ? OR m.concentracion LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params = array_merge($params, [$like, $like, $like]);
}

if ($stateFilter !== '') {
    $where[] = 'e.estado = ?';
    $types .= 's';
    $params[] = $stateFilter;
}

$sql = "SELECT i.id_lote, i.cantidad_disponible, i.fecha_caducidad, i.fecha_ingreso,
               m.nombre_comercial, m.principio_activo, m.concentracion,
               e.estado
        FROM INVENTARIO i
        INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
        INNER JOIN ESTADOS_MEDICAMENTOS e ON i.id_estado_medicamento = e.id_estado_medicamento";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY {$sortField} {$dir}, i.id_lote DESC";
$inventory = inventoryRows($conn, $sql, $types, $params);

function inventorySortButton(string $label, string $key, string $currentSort, string $currentDir, array $baseParams): string
{
    $isActive = $currentSort === $key;
    $nextDir = $isActive && $currentDir === 'ASC' ? 'desc' : 'asc';
    $baseParams['sort'] = $key;
    $baseParams['dir'] = $nextDir;
    $arrow = $isActive ? ($currentDir === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a class="btn btn-sm ' . ($isActive ? 'btn-brand' : 'btn-soft') . '" href="?' . htmlspecialchars(http_build_query($baseParams)) . '">' . htmlspecialchars($label . $arrow) . '</a>';
}

$baseFilterParams = [
    'q' => $search,
    'estado' => $stateFilter,
    'sort' => $sort,
    'dir' => strtolower($dir),
];

include '../../src/portal/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Lotes</div>
            <div class="stat-value"><?php echo count($inventory); ?></div>
            <div class="stat-note">Registros visibles</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-sand">
            <div class="stat-label">Stock total</div>
            <div class="stat-value"><?php echo (int)array_sum(array_map(static fn ($item) => (int)$item['cantidad_disponible'], $inventory)); ?></div>
            <div class="stat-note">Unidades disponibles</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Catálogo</div>
            <div class="stat-value"><?php echo count($medications); ?></div>
            <div class="stat-note">Medicamentos base</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title"><?php echo $editing ? 'Editar lote' : 'Nuevo lote'; ?></h2>
                <p class="section-subtitle">Alta y actualización de stock.</p>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="id_lote" value="<?php echo (int)($editing['id_lote'] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Medicamento</label>
                        <select name="id_medicamento" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($medications as $medication): ?>
                                <option value="<?php echo (int)$medication['id_medicamento']; ?>" <?php echo ((int)($editing['id_medicamento'] ?? 0) === (int)$medication['id_medicamento']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($medication['nombre_comercial']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cantidad disponible</label>
                        <input type="number" min="0" name="cantidad_disponible" class="form-control" value="<?php echo htmlspecialchars((string)($editing['cantidad_disponible'] ?? '0')); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha de caducidad</label>
                        <input type="date" name="fecha_caducidad" class="form-control" value="<?php echo htmlspecialchars($editing['fecha_caducidad'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha de ingreso</label>
                        <input type="date" name="fecha_ingreso" class="form-control" value="<?php echo htmlspecialchars($editing['fecha_ingreso'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="id_estado_medicamento" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo (int)$state['id_estado_medicamento']; ?>" <?php echo ((int)($editing['id_estado_medicamento'] ?? 0) === (int)$state['id_estado_medicamento']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($state['estado']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-brand" type="submit"><?php echo $editing ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($editing): ?><a class="btn btn-soft" href="index.php">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Listado</h2>
                <p class="section-subtitle">Lotes disponibles en farmacia.</p>
            </div>
            <div class="panel-body">
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-md-5">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Medicamento o principio activo">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo htmlspecialchars($state['estado']); ?>" <?php echo $stateFilter === $state['estado'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($state['estado']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Orden</label>
                        <select name="sort" class="form-select">
                            <option value="stock" <?php echo $sort === 'stock' ? 'selected' : ''; ?>>Stock</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nombre</option>
                            <option value="expiry" <?php echo $sort === 'expiry' ? 'selected' : ''; ?>>Caducidad</option>
                            <option value="state" <?php echo $sort === 'state' ? 'selected' : ''; ?>>Estado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Dirección</label>
                        <select name="dir" class="form-select">
                            <option value="asc" <?php echo strtolower($dir) === 'asc' ? 'selected' : ''; ?>>Asc</option>
                            <option value="desc" <?php echo strtolower($dir) === 'desc' ? 'selected' : ''; ?>>Desc</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-brand" type="submit">Aplicar</button>
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php echo inventorySortButton('Stock', 'stock', $sort, $dir, $baseFilterParams); ?>
                    <?php echo inventorySortButton('Nombre', 'name', $sort, $dir, $baseFilterParams); ?>
                    <?php echo inventorySortButton('Caducidad', 'expiry', $sort, $dir, $baseFilterParams); ?>
                    <?php echo inventorySortButton('Estado', 'state', $sort, $dir, $baseFilterParams); ?>
                </div>

                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Medicamento</th>
                                <th>Stock</th>
                                <th>Caducidad</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                                <tr>
                                    <td><?php echo (int)$item['id_lote']; ?></td>
                                    <td><?php echo htmlspecialchars($item['nombre_comercial']); ?></td>
                                    <td><?php echo (int)$item['cantidad_disponible']; ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($item['fecha_caducidad']))); ?></td>
                                    <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                    <td>
                                        <a class="btn btn-sm btn-soft me-2" href="?edit=<?php echo (int)$item['id_lote']; ?>">Editar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$inventory): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No hay lotes registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
