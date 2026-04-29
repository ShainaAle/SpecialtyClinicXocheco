<?php
require_once '../../src/auth.php';
requireRol(['admin', 'recepcion']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/recepcion/context.php';

$basePath = '../..';
$pageTitle = 'Espacios físicos';
$pageSubtitle = 'Buscar consultorios, quirófanos, laboratorios y farmacia central.';
$activeModule = 'espacios';

function spacesRowsRecepcion(mysqli $conn, string $sql, string $types = '', array $params = []): array
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
$typeFilter = (int)($_GET['tipo'] ?? 0);
$sort = $_GET['sort'] ?? 'piso';
$dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$types = [];
$typeResult = $conn->query('SELECT id_tipo, tipo FROM TIPOS_ESPACIOS_FISICOS ORDER BY tipo');
if ($typeResult) {
    while ($row = $typeResult->fetch_assoc()) {
        $types[] = $row;
    }
}

$sortMap = [
    'id' => 'e.id_espacio',
    'piso' => 'e.piso',
    'numero' => 'e.numero',
    'nombre' => 'e.nombre',
    'tipo' => 't.tipo',
];
$sortField = $sortMap[$sort] ?? 'e.piso';

$where = [];
$params = [];
$typesBind = '';

if ($search !== '') {
    $where[] = '(e.nombre LIKE ? OR t.tipo LIKE ? OR CAST(e.piso AS CHAR) LIKE ? OR CAST(e.numero AS CHAR) LIKE ?)';
    $like = '%' . $search . '%';
    $typesBind .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($typeFilter > 0) {
    $where[] = 'e.id_tipo = ?';
    $typesBind .= 'i';
    $params[] = $typeFilter;
}

$sql = "SELECT e.id_espacio, e.piso, e.numero, e.nombre, t.tipo
    FROM ESPACIOS_FISICOS e
    INNER JOIN TIPOS_ESPACIOS_FISICOS t ON e.id_tipo = t.id_tipo";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY {$sortField} {$dir}, e.id_espacio ASC";
$spaces = spacesRowsRecepcion($conn, $sql, $typesBind, $params);

function spacesSortButtonRecepcion(string $label, string $key, string $currentSort, string $currentDir, array $baseParams): string
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
    'tipo' => $typeFilter,
    'sort' => $sort,
    'dir' => strtolower($dir),
];

include '../../src/portal/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Espacios visibles</div>
            <div class="stat-value"><?php echo count($spaces); ?></div>
            <div class="stat-note">Registros en pantalla</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-sand">
            <div class="stat-label">Tipos</div>
            <div class="stat-value"><?php echo count($types); ?></div>
            <div class="stat-note">Catálogo activo</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Filtro</div>
            <div class="stat-value"><?php echo htmlspecialchars($sort); ?></div>
            <div class="stat-note">Orden actual</div>
        </div>
    </div>
</div>

<div class="panel-card mb-4">
    <div class="panel-head">
        <h2 class="section-title">Buscar y ordenar</h2>
        <p class="section-subtitle">Encuentra rápido un espacio por nombre, piso, número o tipo.</p>
    </div>
    <div class="panel-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Consultorio, piso, número...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo (int)$type['id_tipo']; ?>" <?php echo $typeFilter === (int)$type['id_tipo'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['tipo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Orden</label>
                <select name="sort" class="form-select">
                    <option value="piso" <?php echo $sort === 'piso' ? 'selected' : ''; ?>>Piso</option>
                    <option value="numero" <?php echo $sort === 'numero' ? 'selected' : ''; ?>>Número</option>
                    <option value="nombre" <?php echo $sort === 'nombre' ? 'selected' : ''; ?>>Nombre</option>
                    <option value="tipo" <?php echo $sort === 'tipo' ? 'selected' : ''; ?>>Tipo</option>
                    <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Dir</label>
                <select name="dir" class="form-select">
                    <option value="asc" <?php echo strtolower($dir) === 'asc' ? 'selected' : ''; ?>>↑</option>
                    <option value="desc" <?php echo strtolower($dir) === 'desc' ? 'selected' : ''; ?>>↓</option>
                </select>
            </div>
            <div class="col-md-12 d-flex gap-2">
                <button class="btn btn-brand" type="submit">Aplicar</button>
                <a class="btn btn-soft" href="index.php">Limpiar</a>
            </div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php echo spacesSortButtonRecepcion('Piso', 'piso', $sort, $dir, $baseFilterParams); ?>
            <?php echo spacesSortButtonRecepcion('Número', 'numero', $sort, $dir, $baseFilterParams); ?>
            <?php echo spacesSortButtonRecepcion('Nombre', 'nombre', $sort, $dir, $baseFilterParams); ?>
            <?php echo spacesSortButtonRecepcion('Tipo', 'tipo', $sort, $dir, $baseFilterParams); ?>
        </div>
    </div>
</div>

<div class="panel-card">
    <div class="panel-head">
        <h2 class="section-title">Listado</h2>
        <p class="section-subtitle">Vista de consulta para recepción.</p>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Piso</th>
                        <th>Número</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spaces as $item): ?>
                        <tr>
                            <td><?php echo (int)$item['id_espacio']; ?></td>
                            <td><?php echo (int)$item['piso']; ?></td>
                            <td><?php echo (int)$item['numero']; ?></td>
                            <td><?php echo htmlspecialchars($item['nombre'] ?? '-'); ?></td>
                            <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['tipo']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$spaces): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No hay espacios cargados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
