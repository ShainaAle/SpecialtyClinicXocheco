<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';

$basePath = '../..';
$pageTitle = 'Bitácora';
$pageSubtitle = 'Consulta los movimientos del sistema, los accesos y los cambios guardados por el personal.';
$activeModule = 'bitacora';

function bitacoraRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$search = trim($_GET['q'] ?? '');
$tableFilter = trim($_GET['tabla'] ?? '');
$sort = $_GET['sort'] ?? 'date';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$tables = bitacoraRows(
    $conn,
    'SELECT DISTINCT tabla_afectada FROM BITACORA ORDER BY tabla_afectada'
);

$sortMap = [
    'date' => 'b.fecha_hora',
    'user' => 'usuario',
    'role' => 'rol',
    'table' => 'b.tabla_afectada',
    'action' => 'b.accion',
];
$sortField = $sortMap[$sort] ?? 'b.fecha_hora';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(b.accion LIKE ? OR b.tabla_afectada LIKE ? OR CONCAT(u.nombre, " ", u.apellidos) LIKE ? OR t.nombre_rol LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($tableFilter !== '') {
    $where[] = 'b.tabla_afectada = ?';
    $types .= 's';
    $params[] = $tableFilter;
}

$sql = "SELECT b.id_bitacora, b.fecha_hora, b.accion, b.tabla_afectada,
               COALESCE(CONCAT(u.nombre, ' ', u.apellidos), 'Usuario eliminado') AS usuario,
               COALESCE(t.nombre_rol, '-') AS rol
        FROM BITACORA b
        LEFT JOIN USUARIOS u ON b.id_usuario = u.id_usuario
        LEFT JOIN TIPO_USUARIO t ON u.id_tipo_usuario = t.id_tipo_usuario";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY {$sortField} {$dir}, b.id_bitacora DESC";
$logs = bitacoraRows($conn, $sql, $types, $params);

$stats = [
    ['label' => 'Registros', 'value' => count($logs), 'note' => 'Visibles en pantalla', 'accent' => 'blue'],
    ['label' => 'Hoy', 'value' => count(array_filter($logs, static fn ($item) => date('Y-m-d', strtotime($item['fecha_hora'])) === date('Y-m-d'))), 'note' => 'Movimientos del día', 'accent' => 'sand'],
    ['label' => 'Tablas', 'value' => count($tables), 'note' => 'Con actividad', 'accent' => 'blue'],
    ['label' => 'Login', 'value' => count(array_filter($logs, static fn ($item) => strtoupper((string)$item['accion']) === 'LOGIN')), 'note' => 'Accesos guardados', 'accent' => 'sand'],
];

function bitacoraSortButton(string $label, string $key, string $currentSort, string $currentDir, array $baseParams): string
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
    'tabla' => $tableFilter,
    'sort' => $sort,
    'dir' => strtolower($dir),
];

include '../../src/admin/header.php';
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
        <h2 class="section-title">Buscar y ordenar</h2>
        <p class="section-subtitle">Filtra por texto, tabla afectada o cambia el orden de los movimientos.</p>
    </div>
    <div class="panel-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Acción, tabla o usuario">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tabla</label>
                <select name="tabla" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($tables as $table): ?>
                        <option value="<?php echo htmlspecialchars($table['tabla_afectada']); ?>" <?php echo $tableFilter === $table['tabla_afectada'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($table['tabla_afectada']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Filtro</label>
                <select name="sort" class="form-select">
                    <option value="date" <?php echo $sort === 'date' ? 'selected' : ''; ?>>Fecha</option>
                    <option value="user" <?php echo $sort === 'user' ? 'selected' : ''; ?>>Usuario</option>
                    <option value="role" <?php echo $sort === 'role' ? 'selected' : ''; ?>>Rol</option>
                    <option value="table" <?php echo $sort === 'table' ? 'selected' : ''; ?>>Tabla</option>
                    <option value="action" <?php echo $sort === 'action' ? 'selected' : ''; ?>>Acción</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Orden</label>
                <select name="dir" class="form-select">
                    <option value="asc" <?php echo strtolower($dir) === 'asc' ? 'selected' : ''; ?>>Ascendente</option>
                    <option value="desc" <?php echo strtolower($dir) === 'desc' ? 'selected' : ''; ?>>Descendente</option>
                </select>
            </div>
            <div class="col-md-12 d-flex gap-2">
                <button class="btn btn-brand" type="submit">Aplicar</button>
                <a class="btn btn-soft" href="index.php">Limpiar</a>
            </div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php echo bitacoraSortButton('Fecha', 'date', $sort, $dir, $baseFilterParams); ?>
            <?php echo bitacoraSortButton('Usuario', 'user', $sort, $dir, $baseFilterParams); ?>
            <?php echo bitacoraSortButton('Tabla', 'table', $sort, $dir, $baseFilterParams); ?>
            <?php echo bitacoraSortButton('Acción', 'action', $sort, $dir, $baseFilterParams); ?>
        </div>
    </div>
</div>

<div class="panel-card">
    <div class="panel-head">
        <h2 class="section-title">Movimientos</h2>
        <p class="section-subtitle">Registro de accesos, cambios e inserciones del sistema.</p>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Acción</th>
                        <th>Tabla</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $item): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($item['fecha_hora'])); ?></td>
                            <td><?php echo htmlspecialchars($item['usuario']); ?></td>
                            <td><span class="chip chip-sand"><?php echo htmlspecialchars($item['rol']); ?></span></td>
                            <td><?php echo htmlspecialchars($item['accion']); ?></td>
                            <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['tabla_afectada']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Todavía no hay movimientos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
