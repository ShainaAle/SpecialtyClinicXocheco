<?php
require_once '../../src/auth.php';
requireRol(['farmaceutico']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';
require_once '../../src/recetas.php';

$basePath = '../..';
$pageTitle = 'Recetas';
$pageSubtitle = 'Surtido de prescripciones con código especial y control de entrega.';
$activeModule = 'recetas';
$portalLabel = 'Farmacia';
$portalRole = 'Farmacéutico';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'farmacia/dashboard.php'],
    ['key' => 'inventario', 'label' => 'Inventario', 'href' => 'farmacia/inventario/'],
    ['key' => 'recetas', 'label' => 'Recetas', 'href' => 'farmacia/recetas/'],
];

function farmaciaRecetaRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

ensurePrescriptionTrackingTable($conn);

$message = '';
$error = '';
$search = trim($_GET['q'] ?? '');
$stateFilter = trim($_GET['estado'] ?? '');
$sort = $_GET['sort'] ?? 'date';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supply') {
    $idReceta = (int)($_POST['id_receta'] ?? 0);
    if ($idReceta <= 0) {
        $error = 'Selecciona una receta válida.';
    } else {
        $conn->begin_transaction();
        try {
            $check = farmaciaRecetaRows(
                $conn,
                "SELECT r.id_receta, rs.estado_surtido
                 FROM RECETAS r
                 LEFT JOIN RECETAS_SURTIDO rs ON rs.id_receta = r.id_receta
                 WHERE r.id_receta = ?",
                'i',
                [$idReceta]
            )[0] ?? null;

            if (!$check) {
                $error = 'La receta no existe.';
            } elseif (($check['estado_surtido'] ?? 'Pendiente') === 'Surtida') {
                $error = 'Esta receta ya fue surtida.';
            } else {
                if (supplyPrescription($conn, $idReceta, (int)$_SESSION['id_usuario'])) {
                    auditLog($conn, 'RECETAS_SURTIDO', 'SURTIR receta #' . $idReceta);
                    $conn->commit();
                    $message = 'Receta surtida correctamente.';
                } else {
                    throw new RuntimeException('No se pudo surtir.');
                }
            }
        } catch (Throwable $e) {
            $conn->rollback();
            if ($error === '') {
                $error = 'No se pudo surtir la receta.';
            }
        }
    }
}

$sortMap = [
    'date' => 'r.fecha_emision',
    'code' => 'codigo_receta',
    'patient' => 'paciente',
    'doctor' => 'medico',
    'state' => 'estado_surtido',
];
$sortField = $sortMap[$sort] ?? 'r.fecha_emision';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(COALESCE(rs.codigo_receta, CONCAT(\'RX-\', DATE_FORMAT(r.fecha_emision, \'%Y%m%d\'), \'-\', LPAD(r.id_receta, 6, \'0\'))) LIKE ? OR pu.nombre LIKE ? OR pu.apellidos LIKE ? OR mu.nombre LIKE ? OR mu.apellidos LIKE ? OR m.nombre_comercial LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
    $types .= str_repeat('s', 6);
}

if ($stateFilter !== '') {
    $where[] = "COALESCE(rs.estado_surtido, 'Pendiente') = ?";
    $params[] = $stateFilter;
    $types .= 's';
}

$rawRows = farmaciaRecetaRows(
    $conn,
    "SELECT r.id_receta, r.fecha_emision, r.observaciones AS receta_observaciones,
            c.id_cita,
            CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
            CONCAT(mu.nombre, ' ', mu.apellidos) AS medico,
            m.nombre_comercial,
            m.concentracion,
            dr.dosis,
            dr.frecuencia,
            dr.duracion,
            rs.codigo_receta,
            COALESCE(rs.estado_surtido, 'Pendiente') AS estado_surtido,
            rs.fecha_surtido
     FROM RECETAS r
     INNER JOIN CONSULTAS co ON r.id_consulta = co.id_consulta
     INNER JOIN CITAS c ON co.id_cita = c.id_cita
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN MEDICOS md ON c.id_medico = md.id_medico
     INNER JOIN USUARIOS mu ON md.id_usuario = mu.id_usuario
     INNER JOIN DETALLE_RECETA dr ON dr.id_receta = r.id_receta
     INNER JOIN MEDICAMENTOS m ON dr.id_medicamento = m.id_medicamento
     LEFT JOIN RECETAS_SURTIDO rs ON rs.id_receta = r.id_receta"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
    " ORDER BY {$sortField} {$dir}, r.id_receta DESC, m.nombre_comercial ASC",
    $types,
    $params
);

if ($rawRows) {
    ensurePrescriptionTrackingRows($conn, $rawRows);
}
$trackingMap = getPrescriptionTrackingMap($conn, array_column($rawRows, 'id_receta'));

$prescriptions = [];
foreach ($rawRows as $row) {
    $id = (int)$row['id_receta'];
    if (!isset($prescriptions[$id])) {
        $tracking = $trackingMap[$id] ?? [];
        $prescriptions[$id] = [
            'id_receta' => $id,
            'codigo_receta' => $tracking['codigo_receta'] ?? generatePrescriptionCode($id, $row['fecha_emision']),
            'estado_surtido' => $tracking['estado_surtido'] ?? $row['estado_surtido'] ?? 'Pendiente',
            'fecha_surtido' => $tracking['fecha_surtido'] ?? $row['fecha_surtido'] ?? null,
            'fecha_emision' => $row['fecha_emision'],
            'id_cita' => (int)$row['id_cita'],
            'paciente' => $row['paciente'],
            'medico' => $row['medico'],
            'receta_observaciones' => $row['receta_observaciones'],
            'medicamentos' => [],
        ];
    }
    $prescriptions[$id]['medicamentos'][] = [
        'nombre' => $row['nombre_comercial'],
        'concentracion' => $row['concentracion'],
        'dosis' => $row['dosis'],
        'frecuencia' => $row['frecuencia'],
        'duracion' => $row['duracion'],
    ];
}
$prescriptions = array_values($prescriptions);

$stats = [
    ['label' => 'Recetas', 'value' => count($prescriptions), 'note' => 'Visibles', 'accent' => 'blue'],
    ['label' => 'Pendientes', 'value' => count(array_filter($prescriptions, static fn ($item) => $item['estado_surtido'] !== 'Surtida')), 'note' => 'Por surtir', 'accent' => 'sand'],
    ['label' => 'Surtidas', 'value' => count(array_filter($prescriptions, static fn ($item) => $item['estado_surtido'] === 'Surtida')), 'note' => 'Entregadas', 'accent' => 'blue'],
    ['label' => 'Modo', 'value' => 1, 'note' => 'Control de entrega', 'accent' => 'sand'],
];

function farmaciaRecetaSortButton(string $label, string $key, string $currentSort, string $currentDir, array $baseParams): string
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
        <h2 class="section-title">Buscar recetas</h2>
        <p class="section-subtitle">Busca por código, paciente, médico o medicamento.</p>
    </div>
    <div class="panel-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="RX-..., paciente, médico...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="Pendiente" <?php echo $stateFilter === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="Surtida" <?php echo $stateFilter === 'Surtida' ? 'selected' : ''; ?>>Surtida</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Orden</label>
                <select name="sort" class="form-select">
                    <option value="date" <?php echo $sort === 'date' ? 'selected' : ''; ?>>Fecha</option>
                    <option value="code" <?php echo $sort === 'code' ? 'selected' : ''; ?>>Código</option>
                    <option value="patient" <?php echo $sort === 'patient' ? 'selected' : ''; ?>>Paciente</option>
                    <option value="doctor" <?php echo $sort === 'doctor' ? 'selected' : ''; ?>>Médico</option>
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
    </div>
</div>

<div class="panel-card mb-4">
    <div class="panel-head">
        <h2 class="section-title">Orden rápido</h2>
        <p class="section-subtitle">Cambia el orden sin volver a escribir.</p>
    </div>
    <div class="panel-body d-flex flex-wrap gap-2">
        <?php echo farmaciaRecetaSortButton('Fecha', 'date', $sort, $dir, $baseFilterParams); ?>
        <?php echo farmaciaRecetaSortButton('Código', 'code', $sort, $dir, $baseFilterParams); ?>
        <?php echo farmaciaRecetaSortButton('Paciente', 'patient', $sort, $dir, $baseFilterParams); ?>
        <?php echo farmaciaRecetaSortButton('Médico', 'doctor', $sort, $dir, $baseFilterParams); ?>
        <?php echo farmaciaRecetaSortButton('Estado', 'state', $sort, $dir, $baseFilterParams); ?>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($prescriptions as $item): ?>
        <div class="col-xl-6">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h2 class="section-title"><?php echo htmlspecialchars($item['codigo_receta']); ?></h2>
                    <p class="section-subtitle"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($item['fecha_emision']))); ?></p>
                </div>
                <div class="panel-body">
                    <div class="mini-card mb-3">
                        <div class="d-flex flex-wrap gap-3 justify-content-between">
                            <div>
                                <div class="text-muted-soft small">Paciente</div>
                                <strong><?php echo htmlspecialchars($item['paciente']); ?></strong>
                            </div>
                            <div>
                                <div class="text-muted-soft small">Médico</div>
                                <strong><?php echo htmlspecialchars($item['medico']); ?></strong>
                            </div>
                            <div>
                                <div class="text-muted-soft small">Estado</div>
                                <span class="chip <?php echo $item['estado_surtido'] === 'Surtida' ? 'chip-green' : 'chip-sand'; ?>">
                                    <?php echo htmlspecialchars($item['estado_surtido']); ?>
                                </span>
                            </div>
                            <div>
                                <div class="text-muted-soft small">Cita</div>
                                <strong>#<?php echo (int)$item['id_cita']; ?></strong>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($item['fecha_surtido'])): ?>
                        <div class="mini-card mb-3">
                            <div class="text-muted-soft small">Surtida el</div>
                            <strong><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($item['fecha_surtido']))); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mb-3">
                        <?php foreach ($item['medicamentos'] as $med): ?>
                            <div class="mini-card">
                                <strong class="d-block"><?php echo htmlspecialchars($med['nombre']); ?> <?php echo htmlspecialchars($med['concentracion']); ?></strong>
                                <div class="text-muted-soft small">Dosis: <?php echo htmlspecialchars($med['dosis']); ?> · Frecuencia: <?php echo htmlspecialchars($med['frecuencia']); ?> · Duración: <?php echo htmlspecialchars($med['duracion']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if ($item['estado_surtido'] !== 'Surtida'): ?>
                            <form method="post" class="m-0">
                                <input type="hidden" name="action" value="supply">
                                <input type="hidden" name="id_receta" value="<?php echo (int)$item['id_receta']; ?>">
                                <button class="btn btn-brand" type="submit">Marcar surtida</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-soft" type="button" disabled>Ya surtida</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$prescriptions): ?>
        <div class="col-12">
            <div class="panel-card">
                <div class="panel-body text-center py-5 text-muted">
                    No hay recetas para mostrar.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../src/admin/footer.php'; ?>
