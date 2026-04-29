<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';
require_once '../../src/inventory.php';

inventorySyncStates($conn);

$basePath = '../..';
$pageTitle = 'Inventario';
$pageSubtitle = 'Agrega medicamentos y administra su stock en un solo lugar.';
$activeModule = 'inventario';

function inventoryQueryRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editing = inventoryQueryRows(
        $conn,
        "SELECT i.id_lote, i.id_medicamento, i.cantidad_disponible, i.fecha_caducidad, i.fecha_ingreso,
                m.nombre_comercial, m.principio_activo, m.presentacion, m.concentracion, m.precio_actual,
                e.estado
         FROM INVENTARIO i
         INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
         INNER JOIN ESTADOS_MEDICAMENTOS e ON i.id_estado_medicamento = e.id_estado_medicamento
         WHERE i.id_lote = ?",
        'i',
        [$editId]
    )[0] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lotId = (int)($_POST['id_lote'] ?? 0);
    $useNewMedication = isset($_POST['nuevo_medicamento']) && $_POST['nuevo_medicamento'] === '1';
    $idMedicamento = (int)($_POST['id_medicamento'] ?? 0);
    $cantidad = (int)($_POST['cantidad_disponible'] ?? 0);
    $fechaCaducidad = trim($_POST['fecha_caducidad'] ?? '');
    $fechaIngreso = $editing['fecha_ingreso'] ?? date('Y-m-d');

    $newMedication = [
        'nombre_comercial' => trim($_POST['nombre_comercial'] ?? ''),
        'principio_activo' => trim($_POST['principio_activo'] ?? ''),
        'presentacion' => trim($_POST['presentacion'] ?? ''),
        'concentracion' => trim($_POST['concentracion'] ?? ''),
        'precio_actual' => (float)($_POST['precio_actual'] ?? 0),
    ];

    if ($cantidad < 0) {
        $error = 'La cantidad no puede ser negativa.';
    } elseif ($fechaCaducidad === '') {
        $error = 'Completa la fecha de caducidad.';
    } elseif (strtotime($fechaCaducidad) <= strtotime($fechaIngreso)) {
        $error = 'La caducidad debe ser posterior al ingreso.';
    } else {
        $targetMedicationId = $idMedicamento;

        if ($useNewMedication) {
            if (
                $newMedication['nombre_comercial'] === '' ||
                $newMedication['principio_activo'] === '' ||
                $newMedication['presentacion'] === '' ||
                $newMedication['concentracion'] === '' ||
                $newMedication['precio_actual'] <= 0
            ) {
                $error = 'Completa los datos del medicamento nuevo.';
            } else {
                $duplicate = inventoryMedicationExists($conn, $newMedication['nombre_comercial'], $newMedication['concentracion']);
                if ($duplicate) {
                    $error = 'Ese medicamento ya existe con esa concentración.';
                } else {
                    $targetMedicationId = inventoryCreateMedication($conn, $newMedication);
                    if ($targetMedicationId <= 0) {
                        $error = 'No se pudo crear el medicamento.';
                    }
                }
            }
        } elseif ($targetMedicationId <= 0) {
            $error = 'Selecciona un medicamento o marca agregar nuevo.';
        }

        if ($error === '') {
            $stateName = inventoryDetermineState($fechaCaducidad, $cantidad);
            $states = inventoryStateCatalog($conn);
            $stateId = $states[strtolower($stateName)] ?? 0;

            if ($stateId <= 0) {
                $error = 'No se pudo resolver el estado del stock.';
            } elseif ($lotId > 0) {
                $stmt = $conn->prepare('UPDATE INVENTARIO SET id_medicamento = ?, cantidad_disponible = ?, fecha_caducidad = ?, id_estado_medicamento = ? WHERE id_lote = ?');
                $stmt->bind_param('iisii', $targetMedicationId, $cantidad, $fechaCaducidad, $stateId, $lotId);
                if ($stmt->execute()) {
                    $message = 'Lote actualizado.';
                    auditLog($conn, 'INVENTARIO', 'ACTUALIZAR lote #' . $lotId);
                } else {
                    $error = 'No se pudo actualizar.';
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO INVENTARIO (id_medicamento, cantidad_disponible, fecha_caducidad, fecha_ingreso, id_estado_medicamento) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('iissi', $targetMedicationId, $cantidad, $fechaCaducidad, $fechaIngreso, $stateId);
                if ($stmt->execute()) {
                    $message = 'Lote agregado.';
                    auditLog($conn, 'INVENTARIO', 'INSERTAR lote #' . (int)$conn->insert_id);
                } else {
                    $error = 'No se pudo guardar.';
                }
            }
        }
    }

    inventorySyncStates($conn);
}

$medications = inventoryFetchMedicationList($conn);
$states = inventoryFetchStateList($conn);

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

$inventory = inventoryQueryRows(
    $conn,
    "SELECT i.id_lote, i.id_medicamento, i.cantidad_disponible, i.fecha_caducidad, i.fecha_ingreso,
            m.nombre_comercial, m.principio_activo, m.presentacion, m.concentracion, m.precio_actual,
            e.estado
     FROM INVENTARIO i
     INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
     INNER JOIN ESTADOS_MEDICAMENTOS e ON i.id_estado_medicamento = e.id_estado_medicamento"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
    " ORDER BY {$sortField} {$dir}, i.id_lote DESC",
    $types,
    $params
);

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

include '../../src/admin/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card accent-blue">
            <div class="stat-label">Lotes</div>
            <div class="stat-value"><?php echo count($inventory); ?></div>
            <div class="stat-note">Registros visibles</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card accent-sand">
            <div class="stat-label">Medicamentos</div>
            <div class="stat-value"><?php echo count($medications); ?></div>
            <div class="stat-note">Catálogo actual</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card accent-blue">
            <div class="stat-label">Stock total</div>
            <div class="stat-value"><?php echo (int)array_sum(array_map(static fn ($item) => (int)$item['cantidad_disponible'], $inventory)); ?></div>
            <div class="stat-note">Unidades disponibles</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card accent-sand">
            <div class="stat-label">Estados</div>
            <div class="stat-value"><?php echo count($states); ?></div>
            <div class="stat-note">Catálogo automático</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title"><?php echo $editing ? 'Editar lote' : 'Nuevo lote'; ?></h2>
                <p class="section-subtitle">Puedes crear el medicamento y su stock desde aquí.</p>
            </div>
            <div class="panel-body">
                <form method="post" id="inventory-form">
                    <input type="hidden" name="id_lote" value="<?php echo (int)($editing['id_lote'] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Medicamento existente</label>
                        <select name="id_medicamento" class="form-select" id="existing-medication">
                            <option value="">Selecciona</option>
                            <?php foreach ($medications as $medication): ?>
                                <option value="<?php echo (int)$medication['id_medicamento']; ?>" <?php echo ((int)($editing['id_medicamento'] ?? 0) === (int)$medication['id_medicamento']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($medication['nombre_comercial'] . ' · ' . $medication['concentracion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="new-med-toggle" name="nuevo_medicamento">
                        <label class="form-check-label" for="new-med-toggle">Agregar nuevo medicamento</label>
                    </div>

                    <div id="new-med-box" class="mini-card mb-3 d-none">
                        <strong class="d-block mb-2">Datos del medicamento</strong>
                        <div class="mb-3">
                            <label class="form-label">Nombre comercial</label>
                            <input type="text" name="nombre_comercial" class="form-control" placeholder="Diazepam">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Principio activo</label>
                            <input type="text" name="principio_activo" class="form-control" placeholder="Diazepam">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Presentación</label>
                            <input type="text" name="presentacion" class="form-control" placeholder="Caja con 20 tabletas">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Concentración</label>
                            <input type="text" name="concentracion" class="form-control" placeholder="5 mg">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Precio actual</label>
                            <input type="number" min="0" step="0.01" name="precio_actual" class="form-control" placeholder="0.00">
                        </div>
                    </div>

                    <div class="mini-card mb-3">
                        <strong class="d-block mb-2">Datos del stock</strong>
                        <div class="mb-3">
                            <label class="form-label">Cantidad disponible</label>
                            <input type="number" min="0" name="cantidad_disponible" class="form-control" value="<?php echo htmlspecialchars((string)($editing['cantidad_disponible'] ?? '0')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha de ingreso</label>
                            <div class="form-control bg-light"><?php echo htmlspecialchars($editing['fecha_ingreso'] ?? date('Y-m-d')); ?></div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Fecha de caducidad</label>
                            <input type="date" name="fecha_caducidad" class="form-control" value="<?php echo htmlspecialchars($editing['fecha_caducidad'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-brand" type="submit"><?php echo $editing ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($editing): ?><a class="btn btn-soft" href="index.php">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Listado</h2>
                <p class="section-subtitle">Busca, ordena y revisa el estado actual del stock.</p>
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
                                <th>Ingreso</th>
                                <th>Caducidad</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                                <tr>
                                    <td><?php echo (int)$item['id_lote']; ?></td>
                                    <td>
                                        <strong class="d-block"><?php echo htmlspecialchars($item['nombre_comercial']); ?></strong>
                                        <span class="text-muted-soft small"><?php echo htmlspecialchars($item['concentracion']); ?></span>
                                    </td>
                                    <td><?php echo (int)$item['cantidad_disponible']; ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($item['fecha_ingreso']))); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($item['fecha_caducidad']))); ?></td>
                                    <td><span class="chip <?php echo inventoryGetStateChip($item['estado']); ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                    <td>
                                        <a class="btn btn-sm btn-soft me-2" href="?edit=<?php echo (int)$item['id_lote']; ?>">Editar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$inventory): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No hay lotes registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const toggle = document.getElementById('new-med-toggle');
    const box = document.getElementById('new-med-box');
    const select = document.getElementById('existing-medication');
    if (!toggle || !box || !select) return;
    const sync = () => {
        const show = toggle.checked;
        box.classList.toggle('d-none', !show);
        select.disabled = show;
    };
    toggle.addEventListener('change', sync);
    sync();
})();
</script>

<?php include '../../src/admin/footer.php'; ?>
