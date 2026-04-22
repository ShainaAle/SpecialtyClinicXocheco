<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';

$basePath = '../..';
$pageTitle = 'Médicos';
$pageSubtitle = 'Administrar especialistas, cédulas, especialidades y turnos.';
$activeModule = 'medicos';

function queryRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $i => &$value) {
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
$turnos = ['matutino', 'vespertino', 'nocturno'];

$search = trim($_GET['q'] ?? '');
$specialtyFilter = (int)($_GET['especialidad'] ?? 0);
$turnoFilter = trim($_GET['turno'] ?? '');
$sort = $_GET['sort'] ?? 'id';
$dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $rows = queryRows(
        $conn,
        "SELECT m.id_medico, m.id_usuario, m.id_especialidad, m.cedula_profesional, m.universidad_origen, m.turno,
                u.nombre, u.apellidos, u.correo
         FROM MEDICOS m
         INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
         WHERE m.id_medico = ?",
        'i',
        [$editId]
    );
    $editing = $rows[0] ?? null;
}

$specialties = queryRows($conn, 'SELECT id_especialidad, nombre FROM ESPECIALIDADES ORDER BY nombre');

$availableUsers = queryRows(
    $conn,
    "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_completo, u.correo
     FROM USUARIOS u
     INNER JOIN TIPO_USUARIO t ON u.id_tipo_usuario = t.id_tipo_usuario
     WHERE t.nombre_rol = 'Médico'
       AND (u.id_usuario NOT IN (SELECT id_usuario FROM MEDICOS) OR u.id_usuario = ?)
     ORDER BY u.nombre, u.apellidos",
    'i',
    [(int)($editing['id_usuario'] ?? 0)]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $doctorId = (int)($_POST['id_medico'] ?? 0);
        if ($doctorId <= 0) {
            $error = 'Médico inválido.';
        } else {
            $check = queryRows($conn, 'SELECT COUNT(*) AS total FROM CITAS WHERE id_medico = ?', 'i', [$doctorId]);
            if ((int)($check[0]['total'] ?? 0) > 0) {
                $error = 'No se puede borrar porque ya tiene citas asociadas.';
            } else {
                $stmt = $conn->prepare('DELETE FROM MEDICOS WHERE id_medico = ?');
                $stmt->bind_param('i', $doctorId);
                if ($stmt->execute()) {
                    $message = 'Médico eliminado.';
                } else {
                    $error = 'No se pudo eliminar.';
                }
            }
        }
    } else {
        $doctorId = (int)($_POST['id_medico'] ?? 0);
        $idUsuario = (int)($_POST['id_usuario'] ?? 0);
        $idEspecialidad = (int)($_POST['id_especialidad'] ?? 0);
        $cedula = trim($_POST['cedula_profesional'] ?? '');
        $universidad = trim($_POST['universidad_origen'] ?? '');
        $turno = trim($_POST['turno'] ?? '');

        if ($idUsuario <= 0 || $idEspecialidad <= 0 || $cedula === '' || $turno === '') {
            $error = 'Completa los campos obligatorios.';
        } elseif (!in_array($turno, $turnos, true)) {
            $error = 'Turno inválido.';
        } else {
            if ($doctorId > 0) {
                $stmt = $conn->prepare('UPDATE MEDICOS SET id_usuario = ?, id_especialidad = ?, cedula_profesional = ?, universidad_origen = ?, turno = ? WHERE id_medico = ?');
                $stmt->bind_param('iisssi', $idUsuario, $idEspecialidad, $cedula, $universidad, $turno, $doctorId);
                if ($stmt->execute()) {
                    $message = 'Médico actualizado.';
                } else {
                    $error = 'No se pudo actualizar.';
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO MEDICOS (id_usuario, id_especialidad, cedula_profesional, universidad_origen, turno) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('iisss', $idUsuario, $idEspecialidad, $cedula, $universidad, $turno);
                if ($stmt->execute()) {
                    $message = 'Médico creado.';
                } else {
                    $error = 'No se pudo crear. Puede que ese usuario ya esté asignado como médico o la cédula ya exista.';
                }
            }
        }
    }
}

$sortMap = [
    'id' => 'm.id_medico',
    'name' => 'u.nombre',
    'email' => 'u.correo',
    'specialty' => 'e.nombre',
    'turno' => 'm.turno',
];
$sortField = $sortMap[$sort] ?? 'm.id_medico';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(u.nombre LIKE ? OR u.apellidos LIKE ? OR u.correo LIKE ? OR m.cedula_profesional LIKE ? OR e.nombre LIKE ? OR m.turno LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssssss';
    for ($i = 0; $i < 6; $i++) {
        $params[] = $like;
    }
}

if ($specialtyFilter > 0) {
    $where[] = 'm.id_especialidad = ?';
    $types .= 'i';
    $params[] = $specialtyFilter;
}

if ($turnoFilter !== '' && in_array($turnoFilter, $turnos, true)) {
    $where[] = 'm.turno = ?';
    $types .= 's';
    $params[] = $turnoFilter;
}

$sql = "SELECT m.id_medico, m.cedula_profesional, m.universidad_origen, m.turno,
               u.id_usuario, u.nombre, u.apellidos, u.correo, e.nombre AS especialidad
        FROM MEDICOS m
        INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
        INNER JOIN ESPECIALIDADES e ON m.id_especialidad = e.id_especialidad";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY {$sortField} {$dir}, m.id_medico ASC";
$doctors = queryRows($conn, $sql, $types, $params);

function doctorSortButton(string $label, string $key, string $currentSort, string $currentDir, array $baseParams): string
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
    'especialidad' => $specialtyFilter,
    'turno' => $turnoFilter,
    'sort' => $sort,
    'dir' => strtolower($dir),
];

include '../../src/admin/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Médicos visibles</div>
            <div class="stat-value"><?php echo count($doctors); ?></div>
            <div class="stat-note">Registros en pantalla</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-sand">
            <div class="stat-label">Especialidades</div>
            <div class="stat-value"><?php echo count($specialties); ?></div>
            <div class="stat-note">Catálogo activo</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Turnos</div>
            <div class="stat-value"><?php echo count($turnos); ?></div>
            <div class="stat-note">Base de disponibilidad</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title"><?php echo $editing ? 'Editar médico' : 'Nuevo médico'; ?></h2>
                <p class="section-subtitle">Asigna usuario, especialidad y turno.</p>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id_medico" value="<?php echo (int)($editing['id_medico'] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <select name="id_usuario" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($availableUsers as $user): ?>
                                <option value="<?php echo (int)$user['id_usuario']; ?>" <?php echo ((int)($editing['id_usuario'] ?? 0) === (int)$user['id_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nombre_completo'] . ' · ' . $user['correo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Especialidad</label>
                        <select name="id_especialidad" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo (int)$specialty['id_especialidad']; ?>" <?php echo ((int)($editing['id_especialidad'] ?? 0) === (int)$specialty['id_especialidad']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($specialty['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula profesional</label>
                        <input type="text" name="cedula_profesional" class="form-control" value="<?php echo htmlspecialchars($editing['cedula_profesional'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Universidad de origen</label>
                        <input type="text" name="universidad_origen" class="form-control" value="<?php echo htmlspecialchars($editing['universidad_origen'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Turno</label>
                        <select name="turno" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($turnos as $turno): ?>
                                <option value="<?php echo htmlspecialchars($turno); ?>" <?php echo (($editing['turno'] ?? '') === $turno) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($turno); ?>
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
        <div class="panel-card mb-4">
            <div class="panel-head">
                <h2 class="section-title">Buscar y ordenar</h2>
                <p class="section-subtitle">Filtra por especialidad o turno, o busca por nombre y cédula.</p>
            </div>
            <div class="panel-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre, correo, cédula...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Especialidad</label>
                        <select name="especialidad" class="form-select">
                            <option value="0">Todas</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo (int)$specialty['id_especialidad']; ?>" <?php echo $specialtyFilter === (int)$specialty['id_especialidad'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($specialty['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Turno</label>
                        <select name="turno" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($turnos as $turno): ?>
                                <option value="<?php echo htmlspecialchars($turno); ?>" <?php echo $turnoFilter === $turno ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($turno); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Orden</label>
                        <select name="sort" class="form-select">
                            <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nombre</option>
                            <option value="specialty" <?php echo $sort === 'specialty' ? 'selected' : ''; ?>>Especialidad</option>
                            <option value="turno" <?php echo $sort === 'turno' ? 'selected' : ''; ?>>Turno</option>
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
                    <?php echo doctorSortButton('ID', 'id', $sort, $dir, $baseFilterParams); ?>
                    <?php echo doctorSortButton('Nombre', 'name', $sort, $dir, $baseFilterParams); ?>
                    <?php echo doctorSortButton('Especialidad', 'specialty', $sort, $dir, $baseFilterParams); ?>
                    <?php echo doctorSortButton('Turno', 'turno', $sort, $dir, $baseFilterParams); ?>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Listado</h2>
                <p class="section-subtitle">Especialistas registrados en el sistema.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Médico</th>
                                <th>Correo</th>
                                <th>Especialidad</th>
                                <th>Cédula</th>
                                <th>Turno</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?php echo (int)$doctor['id_medico']; ?></td>
                                    <td><?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['correo']); ?></td>
                                    <td><span class="chip chip-blue"><?php echo htmlspecialchars($doctor['especialidad']); ?></span></td>
                                    <td><?php echo htmlspecialchars($doctor['cedula_profesional']); ?></td>
                                    <td><span class="chip chip-sand"><?php echo htmlspecialchars($doctor['turno']); ?></span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-soft" href="?<?php echo htmlspecialchars(http_build_query(array_merge($baseFilterParams, ['edit' => $doctor['id_medico']]))); ?>">Editar</a>
                                            <form method="post" onsubmit="return confirm('¿Eliminar este médico?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_medico" value="<?php echo (int)$doctor['id_medico']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
