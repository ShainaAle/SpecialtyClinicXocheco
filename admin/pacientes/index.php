<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';

$basePath = '../..';
$pageTitle = 'Pacientes';
$pageSubtitle = 'Gestionar perfiles clínicos y ver la edad directamente en pantalla.';
$activeModule = 'pacientes';

function patientRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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
$adeudoFilter = $_GET['adeudo'] ?? 'all';
$sort = $_GET['sort'] ?? 'id';
$dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$bloodTypeFilter = trim($_GET['sangre'] ?? '');

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Desconocido'];

$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $rows = patientRows(
        $conn,
        "SELECT p.id_paciente, p.id_usuario, p.fecha_nacimiento, p.tipo_sangre, p.alergias, p.contacto_emergencia, p.adeudo,
                u.nombre, u.apellidos, u.correo
         FROM PACIENTES p
         INNER JOIN USUARIOS u ON p.id_usuario = u.id_usuario
         WHERE p.id_paciente = ?",
        'i',
        [$editId]
    );
    $editing = $rows[0] ?? null;
}

$availableUsers = patientRows(
    $conn,
    "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_completo, u.correo
     FROM USUARIOS u
     INNER JOIN TIPO_USUARIO t ON u.id_tipo_usuario = t.id_tipo_usuario
     WHERE t.nombre_rol = 'Paciente'
       AND (u.id_usuario NOT IN (SELECT id_usuario FROM PACIENTES) OR u.id_usuario = ?)
     ORDER BY u.nombre, u.apellidos",
    'i',
    [(int)($editing['id_usuario'] ?? 0)]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $patientId = (int)($_POST['id_paciente'] ?? 0);
        if ($patientId <= 0) {
            $error = 'Paciente inválido.';
        } else {
            $check = patientRows($conn, 'SELECT COUNT(*) AS total FROM CITAS WHERE id_paciente = ?', 'i', [$patientId]);
            if ((int)($check[0]['total'] ?? 0) > 0) {
                $error = 'No se puede borrar porque ya tiene citas asociadas.';
            } else {
                $stmt = $conn->prepare('DELETE FROM PACIENTES WHERE id_paciente = ?');
                $stmt->bind_param('i', $patientId);
                if ($stmt->execute()) {
                    $message = 'Paciente eliminado.';
                    auditLog($conn, 'PACIENTES', 'ELIMINAR paciente #' . $patientId);
                } else {
                    $error = 'No se pudo eliminar.';
                }
            }
        }
    } else {
        $patientId = (int)($_POST['id_paciente'] ?? 0);
        $idUsuario = (int)($_POST['id_usuario'] ?? 0);
        $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $tipoSangre = trim($_POST['tipo_sangre'] ?? '');
        $alergias = trim($_POST['alergias'] ?? '');
        $contacto = trim($_POST['contacto_emergencia'] ?? '');
        $adeudo = (int)($_POST['adeudo'] ?? 0);

        if ($idUsuario <= 0 || $fechaNacimiento === '') {
            $error = 'Completa usuario y fecha de nacimiento.';
        } else {
            if ($patientId > 0) {
                $stmt = $conn->prepare('UPDATE PACIENTES SET id_usuario = ?, fecha_nacimiento = ?, tipo_sangre = ?, alergias = ?, contacto_emergencia = ?, adeudo = ? WHERE id_paciente = ?');
                $stmt->bind_param('issssii', $idUsuario, $fechaNacimiento, $tipoSangre, $alergias, $contacto, $adeudo, $patientId);
                if ($stmt->execute()) {
                    $message = 'Paciente actualizado.';
                    auditLog($conn, 'PACIENTES', 'ACTUALIZAR paciente #' . $patientId);
                } else {
                    $error = 'No se pudo actualizar.';
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO PACIENTES (id_usuario, fecha_nacimiento, tipo_sangre, alergias, contacto_emergencia, adeudo) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('issssi', $idUsuario, $fechaNacimiento, $tipoSangre, $alergias, $contacto, $adeudo);
                if ($stmt->execute()) {
                    $message = 'Paciente creado.';
                    auditLog($conn, 'PACIENTES', 'INSERTAR paciente #' . (int)$conn->insert_id);
                } else {
                    $error = 'No se pudo crear. Puede que ese usuario ya tenga perfil de paciente.';
                }
            }
        }
    }
}

$sortMap = [
    'id' => 'p.id_paciente',
    'name' => 'u.nombre',
    'age' => 'edad',
    'email' => 'u.correo',
    'blood' => 'p.tipo_sangre',
    'debt' => 'p.adeudo',
];
$sortField = $sortMap[$sort] ?? 'p.id_paciente';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(u.nombre LIKE ? OR u.apellidos LIKE ? OR u.correo LIKE ? OR p.tipo_sangre LIKE ? OR p.alergias LIKE ? OR p.contacto_emergencia LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssssss';
    for ($i = 0; $i < 6; $i++) {
        $params[] = $like;
    }
}

if ($adeudoFilter === '0' || $adeudoFilter === '1') {
    $where[] = 'p.adeudo = ?';
    $types .= 'i';
    $params[] = (int)$adeudoFilter;
}

if ($bloodTypeFilter !== '') {
    $where[] = 'COALESCE(p.tipo_sangre, "") = ?';
    $types .= 's';
    $params[] = $bloodTypeFilter;
}

$sql = "SELECT p.id_paciente, p.id_usuario, p.fecha_nacimiento, p.tipo_sangre, p.alergias, p.contacto_emergencia, p.adeudo,
               TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
               u.nombre, u.apellidos, u.correo
        FROM PACIENTES p
        INNER JOIN USUARIOS u ON p.id_usuario = u.id_usuario";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY {$sortField} {$dir}, p.id_paciente ASC";
$patients = patientRows($conn, $sql, $types, $params);

function patientSortButton(string $label, string $key, string $currentSort, string $currentDir, array $baseParams): string
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
    'adeudo' => $adeudoFilter,
    'sangre' => $bloodTypeFilter,
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
            <div class="stat-label">Pacientes</div>
            <div class="stat-value"><?php echo count($patients); ?></div>
            <div class="stat-note">Registros visibles</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-sand">
            <div class="stat-label">Con adeudo</div>
            <div class="stat-value"><?php echo count(array_filter($patients, static fn ($item) => (int)$item['adeudo'] === 1)); ?></div>
            <div class="stat-note">Pacientes pendientes</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Tipos de sangre</div>
            <div class="stat-value"><?php echo count($bloodTypes); ?></div>
            <div class="stat-note">Catálogo de referencia</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title"><?php echo $editing ? 'Editar paciente' : 'Nuevo paciente'; ?></h2>
                <p class="section-subtitle">Perfil clínico del usuario.</p>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id_paciente" value="<?php echo (int)($editing['id_paciente'] ?? 0); ?>">
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
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control" value="<?php echo htmlspecialchars($editing['fecha_nacimiento'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de sangre</label>
                        <input type="text" name="tipo_sangre" class="form-control" value="<?php echo htmlspecialchars($editing['tipo_sangre'] ?? ''); ?>" placeholder="O+">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alergias</label>
                        <textarea name="alergias" class="form-control" rows="3"><?php echo htmlspecialchars($editing['alergias'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contacto de emergencia</label>
                        <input type="text" name="contacto_emergencia" class="form-control" value="<?php echo htmlspecialchars($editing['contacto_emergencia'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adeudo</label>
                    <select name="adeudo" class="form-select">
                        <option value="0" <?php echo ((int)($editing['adeudo'] ?? 0) === 0) ? 'selected' : ''; ?>>No</option>
                        <option value="1" <?php echo ((int)($editing['adeudo'] ?? 0) === 1) ? 'selected' : ''; ?>>Sí</option>
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
                <p class="section-subtitle">Busca por nombre, correo, alergias o tipo de sangre.</p>
            </div>
            <div class="panel-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre, correo, alergia...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Adeudo</label>
                        <select name="adeudo" class="form-select">
                            <option value="all" <?php echo $adeudoFilter === 'all' ? 'selected' : ''; ?>>Todos</option>
                            <option value="0" <?php echo $adeudoFilter === '0' ? 'selected' : ''; ?>>Sin adeudo</option>
                            <option value="1" <?php echo $adeudoFilter === '1' ? 'selected' : ''; ?>>Con adeudo</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sangre</label>
                        <select name="sangre" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($bloodTypes as $bloodType): ?>
                                <option value="<?php echo htmlspecialchars($bloodType); ?>" <?php echo $bloodTypeFilter === $bloodType ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bloodType); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Orden</label>
                        <select name="sort" class="form-select">
                            <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nombre</option>
                            <option value="age" <?php echo $sort === 'age' ? 'selected' : ''; ?>>Edad</option>
                            <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Correo</option>
                            <option value="blood" <?php echo $sort === 'blood' ? 'selected' : ''; ?>>Sangre</option>
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
                    <?php echo patientSortButton('ID', 'id', $sort, $dir, $baseFilterParams); ?>
                    <?php echo patientSortButton('Nombre', 'name', $sort, $dir, $baseFilterParams); ?>
                    <?php echo patientSortButton('Edad', 'age', $sort, $dir, $baseFilterParams); ?>
                    <?php echo patientSortButton('Correo', 'email', $sort, $dir, $baseFilterParams); ?>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Listado</h2>
                <p class="section-subtitle">La edad se calcula al vuelo, no se guarda en la base.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Paciente</th>
                                <th>Correo</th>
                                <th>Edad</th>
                                <th>Sangre</th>
                                <th>Adeudo</th>
                                <th>Contacto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><?php echo (int)$patient['id_paciente']; ?></td>
                                    <td><?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['correo']); ?></td>
                                    <td><?php echo (int)$patient['edad']; ?></td>
                                    <td><span class="chip chip-blue"><?php echo htmlspecialchars($patient['tipo_sangre'] ?: '-'); ?></span></td>
                                    <td><span class="chip <?php echo (int)$patient['adeudo'] === 1 ? 'chip-red' : 'chip-green'; ?>"><?php echo (int)$patient['adeudo'] === 1 ? 'Sí' : 'No'; ?></span></td>
                                    <td><?php echo htmlspecialchars($patient['contacto_emergencia'] ?: '-'); ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-soft" href="?<?php echo htmlspecialchars(http_build_query(array_merge($baseFilterParams, ['edit' => $patient['id_paciente']]))); ?>">Editar</a>
                                            <form method="post" onsubmit="return confirm('¿Eliminar este paciente?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_paciente" value="<?php echo (int)$patient['id_paciente']; ?>">
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
